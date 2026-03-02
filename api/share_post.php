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
$content = isset($input['content']) ? trim((string)$input['content']) : '';
$privacy = isset($input['privacy']) ? (string)$input['privacy'] : 'public';
if ($privacy === 'private') $privacy = 'only_me';
$allowed_privacy = ['only_me', 'followers', 'friends_of_friends', 'public'];
if (!in_array($privacy, $allowed_privacy, true)) $privacy = 'public';

try {
    $pdo = db();
    // Ensure source post exists and not deleted
    $postCheck = $pdo->prepare('SELECT user_id FROM posts WHERE id = :pid AND deleted_at IS NULL');
    $postCheck->execute(['pid' => $post_id]);
    $postRow = $postCheck->fetch();
    if (!$postRow) {
        json_response(['success' => false, 'message' => 'Post not found'], 404);
    }

    // Create a new post as a share wrapper with optional user caption + privacy.
    $stmt = $pdo->prepare("
        INSERT INTO posts (user_id, content, media_type, media_urls, privacy, reference_post)
        VALUES (:uid, :content, 'text', NULL, :privacy, :pid)
    ");
    $stmt->execute([
        'uid' => $user_id,
        'content' => $content,
        'privacy' => $privacy,
        'pid' => $post_id
    ]);

    // Count shares (posts referencing this post)
    $countStmt = $pdo->prepare('SELECT COUNT(*) AS cnt FROM posts WHERE reference_post = :pid');
    $countStmt->execute(['pid' => $post_id]);
    $share_count = (int)$countStmt->fetch()['cnt'];

    // Get sharer account type
    $userStmt = $pdo->prepare('SELECT name, username, account_type FROM users WHERE id = :id');
    $userStmt->execute(['id' => $user_id]);
    $user = $userStmt->fetch();
    
    // Notify owner
    if ($postRow['user_id'] != $user_id) {
        $message = ($user['name'] ?: 'Someone') . ' shared your post';
        $notif = $pdo->prepare('
            INSERT INTO notifications (user_id, from_user_id, post_id, type, message)
            VALUES (:uid, :from_uid, :pid, "share", :msg)
        ');
        $notif->execute([
            'uid' => $postRow['user_id'],
            'from_uid' => $user_id,
            'pid' => $post_id,
            'msg' => $message
        ]);
    }
    
    // Notify all admins when a student shares
    if ($user && $user['account_type'] === 'student') {
        $adminStmt = $pdo->prepare('SELECT id FROM users WHERE account_type = "admin"');
        $adminStmt->execute();
        $admins = $adminStmt->fetchAll();
        
        foreach ($admins as $admin) {
            $notifStmt = $pdo->prepare('
                INSERT INTO notifications (user_id, from_user_id, post_id, type, message)
                VALUES (:uid, :from_uid, :pid, "admin_share", :msg)
            ');
            $notifStmt->execute([
                'uid' => $admin['id'],
                'from_uid' => $user_id,
                'pid' => $post_id,
                'msg' => ($user['name'] ?: $user['username']) . ' shared a post'
            ]);
        }
    }

    json_response(['success' => true, 'share_count' => $share_count]);
} catch (Exception $e) {
    json_response(['success' => false, 'message' => 'Server error'], 500);
}
?>
