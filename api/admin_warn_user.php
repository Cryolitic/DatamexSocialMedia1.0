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

try {
    $pdo = db();
    
    // Verify admin
    $adminCheck = $pdo->prepare('SELECT id FROM users WHERE id = :id AND (is_admin = 1 OR account_type = "admin")');
    $adminCheck->execute(['id' => $admin_id]);
    if (!$adminCheck->fetch()) {
        json_response(['success' => false, 'message' => 'Unauthorized'], 403);
    }
    
    // Get user
    $userStmt = $pdo->prepare('SELECT id, warnings, warning_reasons FROM users WHERE id = :id');
    $userStmt->execute(['id' => $user_id]);
    $user = $userStmt->fetch();
    
    if (!$user) {
        json_response(['success' => false, 'message' => 'User not found'], 404);
    }
    
    // Get admin name for notification
    $adminStmt = $pdo->prepare('SELECT name, username FROM users WHERE id = :id');
    $adminStmt->execute(['id' => $admin_id]);
    $admin = $adminStmt->fetch();
    $adminName = $admin['name'] ?: $admin['username'] ?: 'Administrator';
    
    // Get user name
    $userNameStmt = $pdo->prepare('SELECT name, username FROM users WHERE id = :id');
    $userNameStmt->execute(['id' => $user_id]);
    $userName = $userNameStmt->fetch();
    $userDisplayName = $userName['name'] ?: $userName['username'] ?: 'User';
    
    // Update warnings
    $warnings = (int)$user['warnings'] + 1;
    $reasons = json_decode($user['warning_reasons'] ?: '[]', true);
    $reasons[] = [
        'reason' => $reason,
        'admin_id' => $admin_id,
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    $updateStmt = $pdo->prepare('UPDATE users SET warnings = :warnings, warning_reasons = :reasons WHERE id = :id');
    $updateStmt->execute([
        'warnings' => $warnings,
        'reasons' => json_encode($reasons),
        'id' => $user_id
    ]);
    
    // Notify the user about the warning
    $notifStmt = $pdo->prepare('
        INSERT INTO notifications (user_id, from_user_id, type, message)
        VALUES (:uid, :from_uid, "warning", :msg)
    ');
    $notifStmt->execute([
        'uid' => $user_id,
        'from_uid' => $admin_id,
        'msg' => "You received a warning from {$adminName}. Reason: {$reason}"
    ]);
    
    // Log admin action
    log_admin_action($pdo, $admin_id, 'Warn User', "Warned user #{$user_id}: {$reason}");
    
    json_response([
        'success' => true,
        'message' => 'User warned successfully',
        'warnings' => $warnings
    ]);
} catch (Exception $e) {
    json_response(['success' => false, 'message' => 'Server error'], 500);
}
?>
