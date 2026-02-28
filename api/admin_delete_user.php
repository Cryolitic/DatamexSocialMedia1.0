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
if (!$input || !isset($input['admin_id']) || !isset($input['user_id'])) {
    json_response(['success' => false, 'message' => 'Admin ID and User ID are required'], 400);
}

$admin_id = intval($input['admin_id']);
$user_id = intval($input['user_id']);

try {
    $pdo = db();
    
    // Verify admin
    $adminCheck = $pdo->prepare('SELECT id FROM users WHERE id = :id AND account_type = "admin"');
    $adminCheck->execute(['id' => $admin_id]);
    if (!$adminCheck->fetch()) {
        json_response(['success' => false, 'message' => 'Unauthorized'], 403);
    }
    
    // Prevent self-deletion
    if ($admin_id === $user_id) {
        json_response(['success' => false, 'message' => 'Cannot delete your own account'], 400);
    }
    
    // Get user info for logging
    $userStmt = $pdo->prepare('SELECT username, name FROM users WHERE id = :id');
    $userStmt->execute(['id' => $user_id]);
    $user = $userStmt->fetch();
    
    if (!$user) {
        json_response(['success' => false, 'message' => 'User not found'], 404);
    }
    
    // Delete user (cascade will handle related records)
    $deleteStmt = $pdo->prepare('DELETE FROM users WHERE id = :id');
    $deleteStmt->execute(['id' => $user_id]);
    
    // Log admin action
    log_admin_action($pdo, $admin_id, 'Delete User', "Deleted user #{$user_id} ({$user['username']})");
    
    json_response([
        'success' => true,
        'message' => 'User deleted successfully'
    ]);
} catch (Exception $e) {
    json_response(['success' => false, 'message' => 'Server error'], 500);
}
?>
