<?php
require_once __DIR__ . '/config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

$admin_id = isset($_GET['admin_id']) ? intval($_GET['admin_id']) : 0;
$status = isset($_GET['status']) ? $_GET['status'] : 'all'; // pending | approved | all

if ($admin_id <= 0) {
    json_response(['success' => false, 'message' => 'Admin required'], 400);
}

try {
    $pdo = db();
    
    $adminCheck = $pdo->prepare('SELECT id FROM users WHERE id = :id AND account_type = "admin"');
    $adminCheck->execute(['id' => $admin_id]);
    if (!$adminCheck->fetch()) {
        json_response(['success' => false, 'message' => 'Unauthorized'], 403);
    }
    
    $statusCond = "AND p.post_type = 'announcement'";
    if ($status === 'pending') {
        $statusCond .= " AND (p.announcement_status = 'pending' OR (p.announcement_status IS NULL AND u.account_type = 'faculty'))";
    } elseif ($status === 'approved') {
        $statusCond .= " AND (p.announcement_status = 'approved' OR u.account_type = 'admin')";
    }
    
    $stmt = $pdo->prepare("
        SELECT p.id, p.content, p.created_at, p.announcement_status,
               u.id AS user_id, u.username, u.name, u.avatar, u.account_type
        FROM posts p
        JOIN users u ON p.user_id = u.id
        WHERE p.deleted_at IS NULL
        {$statusCond}
        ORDER BY p.created_at DESC
        LIMIT 100
    ");
    $stmt->execute();
    $rows = $stmt->fetchAll();
    
    $list = array_map(function ($r) {
        return [
            'id' => (int)$r['id'],
            'content' => $r['content'],
            'created_at' => $r['created_at'],
            'announcement_status' => $r['announcement_status'],
            'user_id' => (int)$r['user_id'],
            'username' => $r['username'],
            'name' => $r['name'],
            'avatar' => $r['avatar'] ?: 'assets/images/default-avatar.png',
            'account_type' => $r['account_type'],
        ];
    }, $rows);
    
    json_response(['success' => true, 'announcements' => $list]);
} catch (Exception $e) {
    json_response(['success' => false, 'message' => 'Server error'], 500);
}
