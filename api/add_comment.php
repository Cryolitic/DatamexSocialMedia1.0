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
if (!$input || !isset($input['post_id']) || !isset($input['comment'])) {
    json_response(['success' => false, 'message' => 'Post ID and comment are required'], 400);
}

$post_id = intval($input['post_id']);
$comment_data = $input['comment'];

if (!isset($comment_data['text']) || empty(trim($comment_data['text']))) {
    json_response(['success' => false, 'message' => 'Comment text is required'], 400);
}

$user_id = intval($comment_data['user_id']);
$text = trim($comment_data['text']);

try {
    $pdo = db();
    $pdo->beginTransaction();

    // Validate post exists and not deleted
    $postCheck = $pdo->prepare('SELECT id, user_id FROM posts WHERE id = :pid AND deleted_at IS NULL');
    $postCheck->execute(['pid' => $post_id]);
    $postRow = $postCheck->fetch();
    if (!$postRow) {
        json_response(['success' => false, 'message' => 'Post not found'], 404);
    }

    $stmt = $pdo->prepare('
        INSERT INTO comments (post_id, user_id, content)
        VALUES (:post_id, :user_id, :content)
    ');
    $stmt->execute([
        'post_id' => $post_id,
        'user_id' => $user_id,
        'content' => $text
    ]);

    $comment_id = (int)$pdo->lastInsertId();

    $commentStmt = $pdo->prepare('
        SELECT c.*, u.username, u.name, u.avatar
        FROM comments c
        JOIN users u ON c.user_id = u.id
        WHERE c.id = :id
    ');
    $commentStmt->execute(['id' => $comment_id]);
    $comment = $commentStmt->fetch();

    // Get commenter account type
    $commenterStmt = $pdo->prepare('SELECT account_type, name, username FROM users WHERE id = :id');
    $commenterStmt->execute(['id' => $user_id]);
    $commenter = $commenterStmt->fetch();
    
    // Notify post owner
    if ($postRow['user_id'] != $user_id) {
        $message = ($comment['name'] ?: 'Someone') . ' commented on your post';
        $notifStmt = $pdo->prepare('
            INSERT INTO notifications (user_id, from_user_id, post_id, type, message)
            VALUES (:uid, :from_uid, :pid, "comment", :msg)
        ');
        $notifStmt->execute([
            'uid' => $postRow['user_id'],
            'from_uid' => $user_id,
            'pid' => $post_id,
            'msg' => $message
        ]);
    }
    
    // Notify all admins when a student comments
    if ($commenter && $commenter['account_type'] === 'student') {
        $adminStmt = $pdo->prepare('SELECT id FROM users WHERE is_admin = 1 OR account_type = "admin"');
        $adminStmt->execute();
        $admins = $adminStmt->fetchAll();
        
        foreach ($admins as $admin) {
            $notifStmt = $pdo->prepare('
                INSERT INTO notifications (user_id, from_user_id, post_id, type, message)
                VALUES (:uid, :from_uid, :pid, "admin_comment", :msg)
            ');
            $notifStmt->execute([
                'uid' => $admin['id'],
                'from_uid' => $user_id,
                'pid' => $post_id,
                'msg' => ($commenter['name'] ?: $commenter['username']) . ' commented: ' . (mb_substr($text, 0, 50) . (mb_strlen($text) > 50 ? '...' : ''))
            ]);
        }
    }

    $pdo->commit();

    json_response([
        'success' => true,
        'comment' => [
            'id' => $comment_id,
            'user_id' => $user_id,
            'name' => $comment['name'] ?: $comment['username'],
            'avatar' => $comment['avatar'] ?: 'assets/images/default-avatar.png',
            'text' => $text,
            'timestamp' => $comment['created_at']
        ]
    ]);
} catch (Exception $e) {
    if ($pdo && $pdo->inTransaction()) $pdo->rollBack();
    json_response(['success' => false, 'message' => 'Server error'], 500);
}
?>
