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
if (!$input || !isset($input['story_id']) || !isset($input['user_id'])) {
    json_response(['success' => false, 'message' => 'story_id and user_id required'], 400);
}

$story_id = (int)$input['story_id'];
$user_id = (int)$input['user_id'];

try {
    $pdo = db();
    $chk = $pdo->prepare('SELECT 1 FROM story_likes WHERE story_id = :sid AND user_id = :uid LIMIT 1');
    $chk->execute(['sid' => $story_id, 'uid' => $user_id]);
    $liked = (bool)$chk->fetch();
    if ($liked) {
        $stmt = $pdo->prepare('DELETE FROM story_likes WHERE story_id = :sid AND user_id = :uid');
        $stmt->execute(['sid' => $story_id, 'uid' => $user_id]);
        json_response(['success' => true, 'liked' => false]);
    } else {
        $stmt = $pdo->prepare('INSERT IGNORE INTO story_likes (story_id, user_id) VALUES (:sid, :uid)');
        $stmt->execute(['sid' => $story_id, 'uid' => $user_id]);
        // Notify story owner: "[Name] liked your story"
        $storyRow = $pdo->prepare('SELECT user_id FROM stories WHERE id = :sid LIMIT 1');
        $storyRow->execute(['sid' => $story_id]);
        $owner = $storyRow->fetch();
        if ($owner && (int)$owner['user_id'] !== $user_id) {
            $liker = $pdo->prepare('SELECT name, username FROM users WHERE id = :uid LIMIT 1');
            $liker->execute(['uid' => $user_id]);
            $likerRow = $liker->fetch();
            $likerName = $likerRow['name'] ?: $likerRow['username'] ?: 'Someone';
            $msg = $likerName . ' liked your story.';
            $notifStmt = $pdo->prepare('INSERT INTO notifications (user_id, from_user_id, post_id, type, message) VALUES (:uid, :from_id, NULL, :type, :msg)');
            $notifStmt->execute([
                'uid' => (int)$owner['user_id'],
                'from_id' => $user_id,
                'type' => 'story_like',
                'msg' => $msg
            ]);
        }
        json_response(['success' => true, 'liked' => true]);
    }
} catch (Exception $e) {
    json_response(['success' => false, 'message' => 'Server error'], 500);
}
?>
