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
