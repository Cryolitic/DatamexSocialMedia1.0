<?php
require_once __DIR__ . '/config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

$userId = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
$viewerId = isset($_GET['viewer_id']) ? intval($_GET['viewer_id']) : 0;

if (!$userId) {
    json_response(['success' => false, 'message' => 'User is required'], 400);
}

try {
    $pdo = db();
    $uStmt = $pdo->prepare('SELECT id, username, name, email, avatar, bio, cover_photo, new_user_guide_dismissed FROM users WHERE id = :id LIMIT 1');
    $uStmt->execute(['id' => $userId]);
    $u = $uStmt->fetch();
    if (!$u) {
        json_response(['success' => false, 'message' => 'User not found'], 404);
    }

    $followers = $pdo->prepare('SELECT COUNT(*) AS c FROM follows WHERE followed_id = :id');
    $followers->execute(['id' => $userId]);
    $followerCount = (int)($followers->fetch()['c'] ?? 0);

    $following = $pdo->prepare('SELECT COUNT(*) AS c FROM follows WHERE follower_id = :id');
    $following->execute(['id' => $userId]);
    $followingCount = (int)($following->fetch()['c'] ?? 0);

    // Keep sidebar "Posts" aligned with timeline posts (exclude announcements).
    $posts = $pdo->prepare("SELECT COUNT(*) AS c FROM posts WHERE user_id = :id AND deleted_at IS NULL AND (post_type = 'post' OR post_type IS NULL)");
    $posts->execute(['id' => $userId]);
    $postCount = (int)($posts->fetch()['c'] ?? 0);

    // Latest note (max 60 chars) - 24h expiry like stories
    $noteStmt = $pdo->prepare('SELECT n.id, n.content,
        (SELECT COUNT(*) FROM note_likes WHERE note_id = n.id) AS like_count
        FROM notes n WHERE n.user_id = :id AND n.created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ORDER BY n.updated_at DESC, n.created_at DESC LIMIT 1');
    $noteStmt->execute(['id' => $userId]);
    $noteRow = $noteStmt->fetch();
    $latestNote = null;
    if ($noteRow) {
        $nid = (int)$noteRow['id'];
        $liked = false;
        if ($viewerId) {
            $lk = $pdo->prepare('SELECT 1 FROM note_likes WHERE note_id = :nid AND user_id = :vid LIMIT 1');
            $lk->execute(['nid' => $nid, 'vid' => $viewerId]);
            $liked = (bool)$lk->fetch();
        }
        $latestNote = ['id' => $nid, 'content' => $noteRow['content'], 'like_count' => (int)$noteRow['like_count'], 'liked' => $liked];
    }

    // Has active story (24h)
    $storyStmt = $pdo->prepare('SELECT 1 FROM stories WHERE user_id = :id AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR) LIMIT 1');
    $storyStmt->execute(['id' => $userId]);
    $hasStory = (bool)$storyStmt->fetch();

    $isFollowed = false;
$isFollower = false;
    if ($viewerId && $viewerId !== $userId) {
        $chk = $pdo->prepare('SELECT 1 FROM follows WHERE follower_id = :v AND followed_id = :u LIMIT 1');
        $chk->execute(['v' => $viewerId, 'u' => $userId]);
        $isFollowed = (bool)$chk->fetch();

    $chk2 = $pdo->prepare('SELECT 1 FROM follows WHERE follower_id = :u AND followed_id = :v LIMIT 1');
    $chk2->execute(['u' => $userId, 'v' => $viewerId]);
    $isFollower = (bool)$chk2->fetch();
    }

    json_response([
        'success' => true,
        'profile' => [
            'id' => (int)$u['id'],
            'username' => $u['username'] ?: $u['email'],
            'name' => $u['name'] ?: ($u['username'] ?: $u['email']),
            'email' => $u['email'],
            'avatar' => $u['avatar'] ?: 'assets/images/default-avatar.png',
            'bio' => $u['bio'] ?: '',
            'cover_photo' => $u['cover_photo'] ?: null,
            'postCount' => $postCount,
            'followerCount' => $followerCount,
            'followingCount' => $followingCount,
            'isFollowed' => $isFollowed,
            'isFollower' => $isFollower,
            'latestNote' => $latestNote,
            'hasStory' => $hasStory,
            'new_user_guide_dismissed' => (bool)($u['new_user_guide_dismissed'] ?? 0),
        ]
    ]);
} catch (Exception $e) {
    json_response(['success' => false, 'message' => 'Server error'], 500);
}

