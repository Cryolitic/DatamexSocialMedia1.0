<?php
require_once __DIR__ . '/config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

try {
    $pdo = db();
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
            p.content AS post_content
        FROM reports r
        INNER JOIN users u ON r.reporter_id = u.id
        INNER JOIN posts p ON r.post_id = p.id
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
