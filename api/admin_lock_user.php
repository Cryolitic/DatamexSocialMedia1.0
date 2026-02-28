<?php
require_once __DIR__ . '/config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false, 'message' => 'Method not allowed'], 405);
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input || !isset($input['admin_id']) || !isset($input['user_id']) || !isset($input['reason'])) {
    json_response(['success' => false, 'message' => 'Admin ID, User ID, and reason are required'], 400);
}

$admin_id = intval($input['admin_id']);
$user_id = intval($input['user_id']);
$reason = trim($input['reason']);
$duration_value = isset($input['duration']) ? intval($input['duration']) : 1;
$duration_unit = isset($input['duration_unit']) ? $input['duration_unit'] : 'days';

try {
    $pdo = db();
    
    // Verify admin
    $adminCheck = $pdo->prepare('SELECT id FROM users WHERE id = :id AND account_type = "admin"');
    $adminCheck->execute(['id' => $admin_id]);
    if (!$adminCheck->fetch()) {
        json_response(['success' => false, 'message' => 'Unauthorized'], 403);
    }
    
    // Get user
    $userStmt = $pdo->prepare('SELECT id FROM users WHERE id = :id');
    $userStmt->execute(['id' => $user_id]);
    if (!$userStmt->fetch()) {
        json_response(['success' => false, 'message' => 'User not found'], 404);
    }
    
    // Calculate lock duration
    $seconds = 0;
    switch ($duration_unit) {
        case 'minutes':
            $seconds = $duration_value * 60;
            break;
        case 'hours':
            $seconds = $duration_value * 3600;
            break;
        case 'days':
            $seconds = $duration_value * 86400;
            break;
        default:
            json_response(['success' => false, 'message' => 'Invalid duration unit'], 400);
    }
    
    $lock_until = date('Y-m-d H:i:s', time() + $seconds);
    
    // Update user - set lock_until and lock_level
    $updateStmt = $pdo->prepare('UPDATE users SET lock_until = :lock_until, lock_level = 2 WHERE id = :id');
    $updateStmt->execute([
        'lock_until' => $lock_until,
        'id' => $user_id
    ]);
    
    // Get admin name for notification
    $adminStmt = $pdo->prepare('SELECT name, username FROM users WHERE id = :id');
    $adminStmt->execute(['id' => $admin_id]);
    $admin = $adminStmt->fetch();
    $adminName = $admin['name'] ?: $admin['username'] ?: 'Administrator';
    
    // Notify the user about the lock
    $notifStmt = $pdo->prepare('
        INSERT INTO notifications (user_id, from_user_id, type, message)
        VALUES (:uid, :from_uid, "warning", :msg)
    ');
    $notifStmt->execute([
        'uid' => $user_id,
        'from_uid' => $admin_id,
        'msg' => "Your account has been locked due to sensitive and not reliable content. Reason: {$reason}"
    ]);
    
    // Log admin action
    log_admin_action($pdo, $admin_id, 'Lock User', "Locked user #{$user_id} for {$duration_value} {$duration_unit}. Reason: {$reason}");
    
    json_response([
        'success' => true,
        'message' => 'User account locked successfully',
        'lock_until' => $lock_until
    ]);
} catch (Exception $e) {
    json_response(['success' => false, 'message' => 'Server error'], 500);
}
?>
