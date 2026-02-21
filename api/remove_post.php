<?php
require_once __DIR__ . '/config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false, 'message' => 'Method not allowed'], 405);
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['post_id'])) {
    json_response(['success' => false, 'message' => 'Post ID is required'], 400);
}

$post_id = intval($input['post_id']);
$admin_id = isset($input['admin_id']) ? intval($input['admin_id']) : null;
$reason = isset($input['reason']) ? trim($input['reason']) : 'Removed by admin';

try {
    $pdo = db();
    $pdo->beginTransaction();

    // Check post
    $postStmt = $pdo->prepare('SELECT id, user_id FROM posts WHERE id = :id');
    $postStmt->execute(['id' => $post_id]);
    $post = $postStmt->fetch();
    if (!$post) {
        json_response(['success' => false, 'message' => 'Post not found'], 404);
    }

    // Soft delete post
    $delStmt = $pdo->prepare('UPDATE posts SET deleted_at = NOW() WHERE id = :id');
    $delStmt->execute(['id' => $post_id]);

    // Resolve related reports
    $repStmt = $pdo->prepare('UPDATE reports SET status = "resolved", resolved_at = NOW() WHERE post_id = :pid');
    $repStmt->execute(['pid' => $post_id]);

    // Log admin action if provided
    if ($admin_id) {
        log_admin_action($pdo, $admin_id, 'remove_post', "Removed post #{$post_id}. Reason: {$reason}");
    }

    $pdo->commit();
    json_response(['success' => true, 'message' => 'Post removed successfully']);
} catch (Exception $e) {
    if ($pdo && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    json_response(['success' => false, 'message' => 'Server error'], 500);
}
?>
