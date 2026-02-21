<?php
require_once __DIR__ . '/config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

$user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
if (!$user_id) {
    json_response(['success' => false, 'message' => 'user_id required'], 400);
}

try {
    $pdo = db();
    // Archived = stories older than 24 hours (expired)
    $stmt = $pdo->prepare('
        SELECT s.id, s.user_id, s.media_type, s.media_url, s.created_at,
               (SELECT COUNT(*) FROM story_views WHERE story_id = s.id) AS view_count,
               (SELECT COUNT(*) FROM story_likes WHERE story_id = s.id) AS like_count
        FROM stories s
        WHERE s.user_id = :uid AND s.created_at <= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ORDER BY s.created_at ASC
    ');
    $stmt->execute(['uid' => $user_id]);
    $rows = $stmt->fetchAll();
    $stories = array_map(function ($r) {
        return [
            'id' => (int)$r['id'],
            'media_type' => $r['media_type'],
            'media_url' => $r['media_url'],
            'created_at' => $r['created_at'],
            'view_count' => (int)$r['view_count'],
            'like_count' => (int)$r['like_count']
        ];
    }, $rows);
    json_response(['success' => true, 'stories' => $stories]);
} catch (Exception $e) {
    json_response(['success' => false, 'message' => 'Server error'], 500);
}
?>
