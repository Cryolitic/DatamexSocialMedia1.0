<?php


require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../vendor/autoload.php';

function upload_error_message(int $code): string {
    return match ($code) {
        UPLOAD_ERR_INI_SIZE => 'Upload failed: file exceeds server upload_max_filesize limit.',
        UPLOAD_ERR_FORM_SIZE => 'Upload failed: file exceeds form size limit.',
        UPLOAD_ERR_PARTIAL => 'Upload failed: file was only partially uploaded.',
        UPLOAD_ERR_NO_FILE => 'Upload failed: no file was uploaded.',
        UPLOAD_ERR_NO_TMP_DIR => 'Upload failed: missing temporary folder.',
        UPLOAD_ERR_CANT_WRITE => 'Upload failed: cannot write file to disk.',
        UPLOAD_ERR_EXTENSION => 'Upload failed: blocked by a PHP extension.',
        default => 'Upload failed due to unknown error.',
    };
}


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

$user_id = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
if (!$user_id) {
    json_response(['success' => false, 'message' => 'user_id required'], 400);
}

if (empty($_FILES['media']) || !isset($_FILES['media']['error']) || $_FILES['media']['error'] !== UPLOAD_ERR_OK) {
    $err = isset($_FILES['media']['error']) ? (int)$_FILES['media']['error'] : UPLOAD_ERR_NO_FILE;
    json_response(['success' => false, 'message' => upload_error_message($err)], 400);
}

$file = $_FILES['media'];
$max_size = 30 * 1024 * 1024; // 30MB
if (($file['size'] ?? 0) > $max_size) {
    json_response(['success' => false, 'message' => 'File size exceeds 30MB limit'], 400);
}

$allowed_image = ['image/png', 'image/jpeg', 'image/jpg'];
$allowed_video = ['video/mp4'];
$type = mime_content_type($file['tmp_name']) ?: ($file['type'] ?? '');
$is_video = in_array($type, $allowed_video);
$is_image = in_array($type, $allowed_image);
$uploadApi = cloudinary_upload_api();
if (!$is_image && !$is_video) {
    json_response(['success' => false, 'message' => 'Only PNG, JPG or MP4 allowed'], 400);
}

try{
    $uploadResult = $uploadApi->upload($file['tmp_name'],[
        'folder' => 'uploads/stories',
        'resource_type' => 'auto'
    ]);
    $media_url = json_encode([
        'url' => $uploadResult['secure_url'],
        'public_id' => $uploadResult['public_id'],
        'resource_type' => $uploadResult['resource_type']
    ]);
} catch (Exception $e){
    json_response([
        'success' => false,
        'message' => 'Cloud Upload failed: '. $e->getMessage()
    ], 500);
}

try {
    $pdo = db();
    $ext = $is_video ? 'mp4' : (pathinfo($file['name'], PATHINFO_EXTENSION) ?: 'jpg');
    $media_type = $is_video ? 'video' : 'image';
    $stmt = $pdo->prepare('INSERT INTO stories (user_id, media_type, media_url) VALUES (:uid, :type, :url)');
    $stmt->execute(['uid' => $user_id, 'type' => $media_type, 'url' => $media_url]);
    $id = (int)$pdo->lastInsertId();

    // Notify all followers: "[Name] posted a story" (like Facebook)
    $author = $pdo->prepare('SELECT name, username FROM users WHERE id = :id');
    $author->execute(['id' => $user_id]);
    $authorRow = $author->fetch();
    $authorName = $authorRow['name'] ?: $authorRow['username'] ?: 'Someone';
    $message = $authorName . ' posted a story.';
    $followers = $pdo->prepare('SELECT follower_id FROM follows WHERE followed_id = :uid');
    $followers->execute(['uid' => $user_id]);
    $notifStmt = $pdo->prepare('INSERT INTO notifications (user_id, from_user_id, post_id, type, message) VALUES (:uid, :from_id, NULL, :type, :msg)');
    while ($row = $followers->fetch()) {
        $follower_id = (int)$row['follower_id'];
        if ($follower_id === $user_id) continue;
        try {
            $notifStmt->execute([
                'uid' => $follower_id,
                'from_id' => $user_id,
                'type' => 'story',
                'msg' => $message
            ]);
        } catch (PDOException $e) { /* skip duplicate or enum error */ }
    }

    json_response(['success' => true, 'story_id' => $id, 'media_url' => $media_url, 'media_type' => $media_type]);
} catch (Exception $e) {
    json_response(['success' => false, 'message' => 'Server error'], 500);
}
?>
