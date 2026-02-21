<?php
require_once __DIR__ . '/config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

$user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;

if (!$user_id) {
    json_response(['success' => false, 'message' => 'User ID required'], 400);
}

try {
    $pdo = db();
    $stmt = $pdo->prepare('
        SELECT n.*, fu.name AS from_name
        FROM notifications n
        LEFT JOIN users fu ON fu.id = n.from_user_id
        WHERE n.user_id = :uid
        ORDER BY n.created_at DESC
        LIMIT 50
    ');
    $stmt->execute(['uid' => $user_id]);
    $rows = $stmt->fetchAll();

    $notifications = array_map(function ($n) {
        return [
            'id' => (int)$n['id'],
            'type' => $n['type'],
            'message' => $n['message'],
            'post_id' => isset($n['post_id']) ? (int)$n['post_id'] : null,
            'from_user_id' => $n['from_user_id'] ? (int)$n['from_user_id'] : null,
            'from_name' => $n['from_name'] ?? null,
            'read' => (bool)$n['is_read'],
            'timestamp' => $n['created_at'],
            'post_content_snapshot' => isset($n['post_content_snapshot']) ? $n['post_content_snapshot'] : null
        ];
    }, $rows);

    json_response(['success' => true, 'notifications' => $notifications]);
} catch (Exception $e) {
    json_response(['success' => false, 'message' => 'Server error'], 500);
}
?>
