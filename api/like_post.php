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
if (!$input || !isset($input['post_id']) || !isset($input['user_id'])) {
    json_response(['success' => false, 'message' => 'Post ID and User ID are required'], 400);
}

$post_id = intval($input['post_id']);
$user_id = intval($input['user_id']);

try {
    $pdo = db();
    $pdo->beginTransaction();

    // Ensure post exists and not deleted
    $postCheck = $pdo->prepare('SELECT user_id FROM posts WHERE id = :pid AND deleted_at IS NULL');
    $postCheck->execute(['pid' => $post_id]);
    $postRow = $postCheck->fetch();
    if (!$postRow) {
        json_response(['success' => false, 'message' => 'Post not found'], 404);
    }

    // Check existing like
    $check = $pdo->prepare('SELECT id FROM likes WHERE post_id = :pid AND user_id = :uid');
    $check->execute(['pid' => $post_id, 'uid' => $user_id]);
    $existing = $check->fetch();

    if ($existing) {
        $del = $pdo->prepare('DELETE FROM likes WHERE id = :id');
        $del->execute(['id' => $existing['id']]);
        $liked = false;
    } else {
        $ins = $pdo->prepare('INSERT INTO likes (post_id, user_id) VALUES (:pid, :uid)');
        $ins->execute(['pid' => $post_id, 'uid' => $user_id]);
        $liked = true;

        // Notify owner
        if ($postRow['user_id'] != $user_id) {
            $userStmt = $pdo->prepare('SELECT name FROM users WHERE id = :id');
            $userStmt->execute(['id' => $user_id]);
            $user = $userStmt->fetch();
            $message = ($user['name'] ?: 'Someone') . ' liked your post';
            $notif = $pdo->prepare('
                INSERT INTO notifications (user_id, from_user_id, post_id, type, message)
                VALUES (:uid, :from_uid, :pid, "like", :msg)
            ');
            $notif->execute([
                'uid' => $postRow['user_id'],
                'from_uid' => $user_id,
                'pid' => $post_id,
                'msg' => $message
            ]);
        }
    }

    $countStmt = $pdo->prepare('SELECT COUNT(*) AS cnt FROM likes WHERE post_id = :pid');
    $countStmt->execute(['pid' => $post_id]);
    $like_count = (int)$countStmt->fetch()['cnt'];

    $pdo->commit();
    json_response(['success' => true, 'liked' => $liked, 'like_count' => $like_count]);
} catch (Exception $e) {
    if ($pdo && $pdo->inTransaction()) $pdo->rollBack();
    json_response(['success' => false, 'message' => 'Server error'], 500);
}
?>
