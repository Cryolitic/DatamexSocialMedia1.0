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
if (!$input || !isset($input['admin_id']) || !isset($input['post_id'])) {
    json_response(['success' => false, 'message' => 'Admin ID and Post ID are required'], 400);
}

$admin_id = intval($input['admin_id']);
$post_id = intval($input['post_id']);

try {
    $pdo = db();
    
    // Verify admin
    $adminCheck = $pdo->prepare('SELECT id FROM users WHERE id = :id AND (is_admin = 1 OR account_type = "admin")');
    $adminCheck->execute(['id' => $admin_id]);
    if (!$adminCheck->fetch()) {
        json_response(['success' => false, 'message' => 'Unauthorized'], 403);
    }
    
    // Get post info
    $postStmt = $pdo->prepare('SELECT id, user_id, content FROM posts WHERE id = :id AND deleted_at IS NULL');
    $postStmt->execute(['id' => $post_id]);
    $post = $postStmt->fetch();
    
    if (!$post) {
        json_response(['success' => false, 'message' => 'Post not found'], 404);
    }
    
    $postOwnerId = (int)$post['user_id'];
    $contentSnapshot = mb_substr($post['content'], 0, 500);
    
    // Notify post owner before soft-delete (so post_id still exists for FK)
    $notifStmt = $pdo->prepare('
        INSERT INTO notifications (user_id, from_user_id, post_id, type, message, post_content_snapshot)
        VALUES (:user_id, :from_user_id, :post_id, :type, :message, :snapshot)
    ');
    $notifStmt->execute([
        'user_id' => $postOwnerId,
        'from_user_id' => $admin_id,
        'post_id' => $post_id,
        'type' => 'post_deleted',
        'message' => 'An admin removed your post.',
        'snapshot' => $contentSnapshot
    ]);
    
    // Soft delete post
    $deleteStmt = $pdo->prepare('UPDATE posts SET deleted_at = NOW() WHERE id = :id');
    $deleteStmt->execute(['id' => $post_id]);
    
    // Log admin action
    $contentPreview = mb_substr($post['content'], 0, 100);
    log_admin_action($pdo, $admin_id, 'Delete Post', "Deleted post #{$post_id} by user #{$post['user_id']}: {$contentPreview}");
    
    json_response([
        'success' => true,
        'message' => 'Post deleted successfully'
    ]);
} catch (Exception $e) {
    json_response(['success' => false, 'message' => 'Server error'], 500);
}
?>
