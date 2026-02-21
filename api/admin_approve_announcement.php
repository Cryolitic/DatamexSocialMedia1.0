<?php
require_once __DIR__ . '/config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false, 'message' => 'Method not allowed'], 405);
}

$input = json_decode(file_get_contents('php://input'), true);
$admin_id = isset($input['admin_id']) ? intval($input['admin_id']) : 0;
$post_id = isset($input['post_id']) ? intval($input['post_id']) : 0;

if ($admin_id <= 0 || $post_id <= 0) {
    json_response(['success' => false, 'message' => 'admin_id and post_id required'], 400);
}

try {
    $pdo = db();
    
    $adminCheck = $pdo->prepare('SELECT id FROM users WHERE id = :id AND (is_admin = 1 OR account_type = "admin")');
    $adminCheck->execute(['id' => $admin_id]);
    if (!$adminCheck->fetch()) {
        json_response(['success' => false, 'message' => 'Unauthorized'], 403);
    }
    
    $up = $pdo->prepare("
        UPDATE posts 
        SET announcement_status = 'approved' 
        WHERE id = :id AND post_type = 'announcement' AND (announcement_status = 'pending' OR announcement_status IS NULL)
    ");
    $up->execute(['id' => $post_id]);
    
    if ($up->rowCount() === 0) {
        json_response(['success' => false, 'message' => 'Post not found or already approved'], 404);
    }
    
    log_admin_action($pdo, $admin_id, 'approve_announcement', "post_id={$post_id}");
    
    json_response(['success' => true, 'message' => 'Announcement approved']);
} catch (Exception $e) {
    json_response(['success' => false, 'message' => 'Server error'], 500);
}
