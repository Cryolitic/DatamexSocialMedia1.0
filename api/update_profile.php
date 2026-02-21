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
$name = isset($_POST['name']) ? trim($_POST['name']) : null;
$bio = isset($_POST['bio']) ? trim($_POST['bio']) : null;
$cover_file = $_FILES['cover'] ?? null;

if (!$user_id) {
    json_response(['success' => false, 'message' => 'User is required'], 400);
}

$cover_path = null;
if ($cover_file && $cover_file['error'] === UPLOAD_ERR_OK) {
    $max_size = 5 * 1024 * 1024;
    if ($cover_file['size'] > $max_size) {
        json_response(['success' => false, 'message' => 'Cover exceeds 5MB limit'], 400);
    }
    $allowed = ['image/png', 'image/jpeg', 'image/jpg'];
    $file_type = mime_content_type($cover_file['tmp_name']);
    if (!in_array($file_type, $allowed)) {
        json_response(['success' => false, 'message' => 'Cover must be PNG or JPG'], 400);
    }
    $ext = pathinfo($cover_file['name'], PATHINFO_EXTENSION);
    $filename = uniqid('cover_') . '.' . $ext;
    $dir = __DIR__ . '/../uploads/covers/';
    if (!is_dir($dir)) mkdir($dir, 0777, true);
    $target = $dir . $filename;
    if (!move_uploaded_file($cover_file['tmp_name'], $target)) {
        json_response(['success' => false, 'message' => 'Failed to upload cover'], 500);
    }
    $cover_path = 'uploads/covers/' . $filename;
}

try {
    $pdo = db();
    $fields = [];
    $params = ['id' => $user_id];
    if ($name !== null) {
        $fields[] = 'name = :name';
        $params['name'] = $name;
    }
    if ($bio !== null) {
        $fields[] = 'bio = :bio';
        $params['bio'] = $bio;
    }
    if ($cover_path) {
        $fields[] = 'cover_photo = :cover';
        $params['cover'] = $cover_path;
    }

    if (!$fields) {
        json_response(['success' => false, 'message' => 'No changes provided'], 400);
    }

    $sql = 'UPDATE users SET ' . implode(', ', $fields) . ' WHERE id = :id';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    // Return updated profile
    $out = $pdo->prepare('SELECT id, username, name, email, avatar, bio, cover_photo FROM users WHERE id = :id');
    $out->execute(['id' => $user_id]);
    $user = $out->fetch();

    json_response([
        'success' => true,
        'user' => [
            'id' => (int)$user['id'],
            'username' => $user['username'] ?: $user['email'],
            'name' => $user['name'] ?: ($user['username'] ?: $user['email']),
            'email' => $user['email'],
            'avatar' => $user['avatar'] ?: 'assets/images/default-avatar.png',
            'bio' => $user['bio'] ?: '',
            'cover_photo' => $user['cover_photo'] ?: null,
        ]
    ]);
} catch (Exception $e) {
    json_response(['success' => false, 'message' => 'Server error'], 500);
}

