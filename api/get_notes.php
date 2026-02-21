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
    $stmt = $pdo->prepare('SELECT n.id, n.content, n.created_at, n.updated_at,
        (SELECT COUNT(*) FROM note_likes WHERE note_id = n.id) AS like_count
        FROM notes n WHERE n.user_id = :uid AND n.created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ORDER BY n.updated_at DESC, n.created_at DESC');
    $stmt->execute(['uid' => $user_id]);
    $rows = $stmt->fetchAll();
    $viewer_id = isset($_GET['viewer_id']) ? (int)$_GET['viewer_id'] : 0;
    $notes = [];
    foreach ($rows as $r) {
        $nid = (int)$r['id'];
        $liked = false;
        if ($viewer_id) {
            $lk = $pdo->prepare('SELECT 1 FROM note_likes WHERE note_id = :nid AND user_id = :vid LIMIT 1');
            $lk->execute(['nid' => $nid, 'vid' => $viewer_id]);
            $liked = (bool)$lk->fetch();
        }
        $notes[] = [
            'id' => $nid,
            'content' => $r['content'],
            'created_at' => $r['created_at'],
            'updated_at' => $r['updated_at'],
            'like_count' => (int)$r['like_count'],
            'liked' => $liked
        ];
    }
    json_response(['success' => true, 'notes' => $notes]);
} catch (Exception $e) {
    json_response(['success' => false, 'message' => 'Server error'], 500);
}
?>
