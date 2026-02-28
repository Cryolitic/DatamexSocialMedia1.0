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
    
    $adminCheck = $pdo->prepare('SELECT id, account_type FROM users WHERE id = :id AND account_type IN ("admin", "faculty")');
    $adminCheck->execute(['id' => $admin_id]);
    $viewer = $adminCheck->fetch();
    if (!$viewer) {
        json_response(['success' => false, 'message' => 'Unauthorized'], 403);
    }

    if ($viewer['account_type'] === 'admin') {
        $stmt = $pdo->prepare("
            SELECT id, username, email, name, account_type, status, created_at, warnings
            FROM users
            WHERE account_type IN ('admin', 'student', 'faculty')
            ORDER BY
                CASE account_type
                    WHEN 'admin' THEN 1
                    WHEN 'faculty' THEN 2
                    WHEN 'student' THEN 3
                    ELSE 4
                END ASC,
                name ASC
        ");
    } else {
        $stmt = $pdo->prepare("
            SELECT id, username, email, name, account_type, status, created_at, warnings
            FROM users
            WHERE account_type IN ('student', 'faculty')
            ORDER BY account_type ASC, name ASC
        ");
    }
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
