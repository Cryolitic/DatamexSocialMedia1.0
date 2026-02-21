<?php
require_once __DIR__ . '/config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

$story_id = isset($_GET['story_id']) ? (int)$_GET['story_id'] : 0;
$owner_id = isset($_GET['owner_id']) ? (int)$_GET['owner_id'] : 0;

if (!$story_id || !$owner_id) {
    json_response(['success' => false, 'message' => 'story_id and owner_id required'], 400);
}

try {
    $pdo = db();
    // Verify story belongs to owner
    $chk = $pdo->prepare('SELECT id FROM stories WHERE id = :sid AND user_id = :oid LIMIT 1');
    $chk->execute(['sid' => $story_id, 'oid' => $owner_id]);
    if (!$chk->fetch()) {
        json_response(['success' => false, 'message' => 'Story not found'], 404);
    }
    $stmt = $pdo->prepare('
        SELECT v.viewer_id, v.created_at, u.name, u.username, u.avatar
        FROM story_views v
        JOIN users u ON u.id = v.viewer_id
        WHERE v.story_id = :sid
        ORDER BY v.created_at DESC
    ');
    $stmt->execute(['sid' => $story_id]);
    $rows = $stmt->fetchAll();
    $viewers = array_map(function ($r) {
        return [
            'viewer_id' => (int)$r['viewer_id'],
            'name' => $r['name'],
            'username' => $r['username'],
            'avatar' => $r['avatar'] ?: 'assets/images/default-avatar.png',
            'viewed_at' => $r['created_at']
        ];
    }, $rows);
    json_response(['success' => true, 'viewers' => $viewers]);
} catch (Exception $e) {
    json_response(['success' => false, 'message' => 'Server error'], 500);
}
?>
