<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../vendor/autoload.php';

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
$uploadApi = cloudinary_upload_api();
if (!in_array($file_type, $allowed_types)) {
    json_response(['success' => false, 'message' => 'Only PNG and JPG are allowed'], 400);
}
try{
    $uploadResult = $uploadApi->upload($file['tmp_name'],[
        'folder' => 'uploads/avatars',
        'resource_type' => 'auto'
    ]);
    $avatar_id = json_encode([
        'public_id' => $uploadResult['public_id'],
        'resource_type' => $uploadResult['resource_type']
    ]);
    $avatar_url = $uploadResult['secure_url'];
} catch (Exception $e){
    json_response([
        'success' => false,
        'message' => 'Cloud Upload failed: '. $e->getMessage()
    ]);
}

try {
    $pdo = db();
    $stmt = $pdo->prepare('SELECT avatar, avatar_id FROM users WHERE id =:id ');
    $stmt->execute(['id' => $user_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $currentAvatar = $row['avatar'] ?? null;
    $currentAvatarId = json_decode($row['avatar_id'] ?? '', true);
    $isCloudinary = is_string($currentAvatar) && str_contains($currentAvatar, 'res.cloudinary.com/');
    if (!empty($currentAvatar) && $isCloudinary && is_array($currentAvatarId)) {
        try{
            $uploadApi->destroy($currentAvatarId['public_id'],[
                'resource_type' => $currentAvatarId['resource_type'] ?? 'image',
                'invalidate' => true
            ]);
        }catch (Exception $e) {
            json_response(['success' => false, 'message' => 'Cloud Deletion'.$e->getMessage()], 500);
        }
    }
} catch (Exception $e) {
    json_response(['success' => false, 'message' => 'Server error'], 500);
}

try {
    $pdo = db();
    $stmt = $pdo->prepare('UPDATE users SET avatar = :avatar, avatar_id = :avatar_id WHERE id = :id');
    $stmt->execute(['avatar' => $avatar_url,'avatar_id' => $avatar_id, 'id' => $user_id]);

    json_response(['success' => true, 'avatar' => $avatar_url]);
} catch (Exception $e) {
    json_response(['success' => false, 'message' => 'Server error'], 500);
}

