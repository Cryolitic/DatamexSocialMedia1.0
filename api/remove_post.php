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

if (!$admin_id) {
    json_response(['success' => false, 'message' => 'Moderator ID is required'], 400);
}

try {
    $pdo = db();
    $pdo->beginTransaction();

    // Verify moderator (admin or staff/faculty)
    $modStmt = $pdo->prepare('SELECT id, account_type FROM users WHERE id = :id AND account_type IN ("admin", "faculty")');
    $modStmt->execute(['id' => $admin_id]);
    $moderator = $modStmt->fetch();
    if (!$moderator) {
        json_response(['success' => false, 'message' => 'Unauthorized'], 403);
    }

    // Check post
    $postStmt = $pdo->prepare('
        SELECT p.id, p.user_id, p.post_type, u.account_type AS owner_account_type
        FROM posts p
        JOIN users u ON u.id = p.user_id
        WHERE p.id = :id
    ');
    $postStmt->execute(['id' => $post_id]);
    $post = $postStmt->fetch();
    if (!$post) {
        json_response(['success' => false, 'message' => 'Post not found'], 404);
    }

    // Faculty cannot remove admin posts and cannot remove announcement posts.
    if ($moderator['account_type'] === 'faculty') {
        if ($post['owner_account_type'] === 'admin') {
            json_response(['success' => false, 'message' => 'Faculty cannot remove admin posts'], 403);
        }
        if ($post['post_type'] === 'announcement') {
            json_response(['success' => false, 'message' => 'Faculty cannot remove announcement posts'], 403);
        }
    }

    // Soft delete post
    $delStmt = $pdo->prepare('UPDATE posts SET deleted_at = NOW() WHERE id = :id');
    $delStmt->execute(['id' => $post_id]);

    // Resolve related reports
    $repStmt = $pdo->prepare('UPDATE reports SET status = "resolved", resolved_at = NOW() WHERE post_id = :pid');
    $repStmt->execute(['pid' => $post_id]);

    // Log moderator action
    log_admin_action($pdo, $admin_id, 'remove_post', "Removed post #{$post_id}. Reason: {$reason}");

    $pdo->commit();
    json_response(['success' => true, 'message' => 'Post removed successfully']);
} catch (Exception $e) {
    if ($pdo && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    json_response(['success' => false, 'message' => 'Server error'], 500);
}
?>
