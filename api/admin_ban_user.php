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
if (!$input || !isset($input['admin_id']) || !isset($input['user_id']) || !isset($input['duration']) || !isset($input['reason'])) {
    json_response(['success' => false, 'message' => 'Admin ID, User ID, duration, and reason are required'], 400);
}

$admin_id = intval($input['admin_id']);
$user_id = intval($input['user_id']);
$duration_value = intval($input['duration']);
$duration_unit = isset($input['duration_unit']) ? $input['duration_unit'] : 'hours'; // minutes, hours, days
$reason = trim($input['reason']);

try {
    $pdo = db();
    
    // Verify admin
    $adminCheck = $pdo->prepare('SELECT id FROM users WHERE id = :id AND (is_admin = 1 OR account_type = "admin")');
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
    
    // Calculate ban duration
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
    
    $banned_until = date('Y-m-d H:i:s', time() + $seconds);
    
    // Update user
    $updateStmt = $pdo->prepare('UPDATE users SET status = "banned", banned_until = :banned_until, ban_reason = :reason WHERE id = :id');
    $updateStmt->execute([
        'banned_until' => $banned_until,
        'reason' => $reason,
        'id' => $user_id
    ]);
    
    // Log admin action
    log_admin_action($pdo, $admin_id, 'Ban User', "Banned user #{$user_id} for {$duration_value} {$duration_unit}: {$reason}");
    
    json_response([
        'success' => true,
        'message' => 'User banned successfully',
        'banned_until' => $banned_until
    ]);
} catch (Exception $e) {
    json_response(['success' => false, 'message' => 'Server error'], 500);
}
?>
