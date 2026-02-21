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

$user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
if (!$user_id) {
    json_response(['success' => false, 'message' => 'User is required'], 400);
}

if (!isset($_FILES['avatar']) || $_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
    json_response(['success' => false, 'message' => 'Avatar file is required'], 400);
}

$file = $_FILES['avatar'];
$max_size = 5 * 1024 * 1024;
if ($file['size'] > $max_size) {
    json_response(['success' => false, 'message' => 'File size exceeds 5MB limit'], 400);
}

$allowed_types = ['image/png', 'image/jpeg', 'image/jpg'];
$file_type = mime_content_type($file['tmp_name']);
if (!in_array($file_type, $allowed_types)) {
    json_response(['success' => false, 'message' => 'Only PNG and JPG are allowed'], 400);
}

$extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
$filename = uniqid('avatar_') . '.' . $extension;
$upload_dir = __DIR__ . '/../uploads/avatars/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}
$avatar_path = $upload_dir . $filename;
if (!move_uploaded_file($file['tmp_name'], $avatar_path)) {
    json_response(['success' => false, 'message' => 'Failed to upload file'], 500);
}

$avatar_url = 'uploads/avatars/' . $filename;

try {
    $pdo = db();
    $stmt = $pdo->prepare('UPDATE users SET avatar = :avatar WHERE id = :id');
    $stmt->execute(['avatar' => $avatar_url, 'id' => $user_id]);

    json_response(['success' => true, 'avatar' => $avatar_url]);
} catch (Exception $e) {
    json_response(['success' => false, 'message' => 'Server error'], 500);
}

