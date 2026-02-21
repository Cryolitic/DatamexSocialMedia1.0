<?php
require_once __DIR__ . '/config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

$admin_id = isset($_GET['admin_id']) ? intval($_GET['admin_id']) : 0;

if ($admin_id <= 0) {
    json_response(['success' => false, 'message' => 'Admin required'], 400);
}

try {
    $pdo = db();
    
    $adminCheck = $pdo->prepare('SELECT id FROM users WHERE id = :id AND (is_admin = 1 OR account_type = "admin")');
    $adminCheck->execute(['id' => $admin_id]);
    if (!$adminCheck->fetch()) {
        json_response(['success' => false, 'message' => 'Unauthorized'], 403);
    }
    
    $stmt = $pdo->prepare("
        SELECT id, username, email, name, account_type, status, created_at, warnings
        FROM users
        WHERE account_type IN ('student', 'faculty')
        ORDER BY account_type ASC, name ASC
    ");
    $stmt->execute();
    $rows = $stmt->fetchAll();
    
    $accounts = array_map(function ($r) {
        return [
            'id' => (int)$r['id'],
            'username' => $r['username'],
            'email' => $r['email'],
            'name' => $r['name'],
            'account_type' => $r['account_type'],
            'status' => $r['status'],
            'warnings' => (int)($r['warnings'] ?? 0),
            'created_at' => $r['created_at'],
        ];
    }, $rows);
    
    json_response(['success' => true, 'accounts' => $accounts]);
} catch (Exception $e) {
    json_response(['success' => false, 'message' => 'Server error'], 500);
}
