<?php
require_once __DIR__ . '/config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

$admin_id = isset($_GET['admin_id']) ? intval($_GET['admin_id']) : 0;
if ($admin_id <= 0) {
    json_response(['success' => false, 'message' => 'admin_id required'], 400);
}

try {
    $pdo = db();
    $adminCheck = $pdo->prepare('SELECT id FROM users WHERE id = :id AND account_type = "admin"');
    $adminCheck->execute(['id' => $admin_id]);
    if (!$adminCheck->fetch()) {
        json_response(['success' => false, 'message' => 'Unauthorized'], 403);
    }

    $stmt = $pdo->query('
        SELECT 
            id,
            admin_id,
            action,
            details,
            created_at
        FROM admin_logs
        ORDER BY created_at DESC
        LIMIT 100
    ');
    $logs = $stmt->fetchAll();

    json_response(['success' => true, 'logs' => $logs]);
} catch (Exception $e) {
    json_response(['success' => false, 'message' => 'Server error'], 500);
}
?>
