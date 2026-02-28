<?php
require_once __DIR__ . '/config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

$moderator_id = isset($_GET['moderator_id']) ? intval($_GET['moderator_id']) : 0;
if ($moderator_id <= 0) {
    json_response(['success' => false, 'message' => 'moderator_id required'], 400);
}

try {
    $pdo = db();
    $modCheck = $pdo->prepare('SELECT id FROM users WHERE id = :id AND account_type IN ("admin", "faculty")');
    $modCheck->execute(['id' => $moderator_id]);
    if (!$modCheck->fetch()) {
        json_response(['success' => false, 'message' => 'Unauthorized'], 403);
    }

    $stmt = $pdo->query('
        SELECT 
            r.id,
            r.post_id,
            r.reporter_id,
            r.reason,
            r.status,
            r.created_at,
            u.name AS reporter_name,
            u.username AS reporter_username,
            p.content AS post_content,
            p.post_type,
            pu.account_type AS post_owner_account_type
        FROM reports r
        INNER JOIN users u ON r.reporter_id = u.id
        INNER JOIN posts p ON r.post_id = p.id
        INNER JOIN users pu ON pu.id = p.user_id
        WHERE r.status = "pending"
        ORDER BY r.created_at DESC
        LIMIT 100
    ');
    $reports = $stmt->fetchAll();

    json_response(['success' => true, 'reports' => $reports]);
} catch (Exception $e) {
    json_response(['success' => false, 'message' => 'Server error'], 500);
}
?>
