<?php
require_once __DIR__ . '/config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

$viewer_id = isset($_GET['viewer_id']) ? (int)$_GET['viewer_id'] : 0;

try {
    $pdo = db();
    // Stories expire after 24 hours
    $stmt = $pdo->prepare('
        SELECT s.id, s.user_id, s.media_type, s.media_url, s.created_at,
               u.name, u.username, u.avatar,
               (SELECT COUNT(*) FROM story_views WHERE story_id = s.id) AS view_count,
               (SELECT COUNT(*) FROM story_likes WHERE story_id = s.id) AS like_count
        FROM stories s
        JOIN users u ON u.id = s.user_id
        WHERE s.created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ORDER BY s.user_id, s.created_at ASC
    ');
    $stmt->execute();
    $rows = $stmt->fetchAll();
    $by_user = [];
    foreach ($rows as $r) {
        $uid = (int)$r['user_id'];
        $sid = (int)$r['id'];
        $liked = false;
        if ($viewer_id) {
            $lk = $pdo->prepare('SELECT 1 FROM story_likes WHERE story_id = :sid AND user_id = :vid LIMIT 1');
            $lk->execute(['sid' => $sid, 'vid' => $viewer_id]);
            $liked = (bool)$lk->fetch();
        }
        if (!isset($by_user[$uid])) {
            $by_user[$uid] = [
                'user_id' => $uid,
                'name' => $r['name'],
                'username' => $r['username'],
                'avatar' => $r['avatar'] ?: 'assets/images/default-avatar.png',
                'stories' => []
            ];
        }
        $by_user[$uid]['stories'][] = [
            'id' => $sid,
            'media_type' => $r['media_type'],
            'media_url' => $r['media_url'],
            'created_at' => $r['created_at'],
            'view_count' => (int)$r['view_count'],
            'like_count' => (int)$r['like_count'],
            'liked' => $liked
        ];
    }
    json_response(['success' => true, 'story_users' => array_values($by_user)]);
} catch (Exception $e) {
    json_response(['success' => false, 'message' => 'Server error'], 500);
}
?>
