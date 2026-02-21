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
$followerId = isset($input['follower_id']) ? intval($input['follower_id']) : 0;
$followedId = isset($input['followed_id']) ? intval($input['followed_id']) : 0;

if (!$followerId || !$followedId || $followerId === $followedId) {
    json_response(['success' => false, 'message' => 'Invalid follow request'], 400);
}

try {
    $pdo = db();

    // Check if already following
    $chk = $pdo->prepare('SELECT 1 FROM follows WHERE follower_id = :f AND followed_id = :t LIMIT 1');
    $chk->execute(['f' => $followerId, 't' => $followedId]);
    $exists = (bool)$chk->fetch();

    if ($exists) {
        $del = $pdo->prepare('DELETE FROM follows WHERE follower_id = :f AND followed_id = :t');
        $del->execute(['f' => $followerId, 't' => $followedId]);
        json_response(['success' => true, 'isFollowed' => false]);
    } else {
        $ins = $pdo->prepare('INSERT INTO follows (follower_id, followed_id) VALUES (:f, :t)');
        $ins->execute(['f' => $followerId, 't' => $followedId]);
        // Notify the followed user (need 'follow' in notifications type ENUM)
        $followedUser = $pdo->prepare('SELECT name, username FROM users WHERE id = :id');
        $followedUser->execute(['id' => $followedId]);
        $fu = $followedUser->fetch();
        $followerUser = $pdo->prepare('SELECT name, username FROM users WHERE id = :id');
        $followerUser->execute(['id' => $followerId]);
        $fr = $followerUser->fetch();
        $followerName = $fr['name'] ?: $fr['username'] ?: 'Someone';
        $notifMsg = $followerName . ' started following you.';
        try {
            $notifInsert = $pdo->prepare('INSERT INTO notifications (user_id, from_user_id, post_id, type, message) VALUES (:uid, :from_id, NULL, :type, :msg)');
            $notifInsert->execute([
                'uid' => $followedId,
                'from_id' => $followerId,
                'type' => 'follow',
                'msg' => $notifMsg
            ]);
        } catch (PDOException $e) {
            // If type 'follow' not in ENUM yet, ignore
        }
        json_response(['success' => true, 'isFollowed' => true]);
    }
} catch (Exception $e) {
    json_response(['success' => false, 'message' => 'Server error'], 500);
}

