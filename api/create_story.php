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

$user_id = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
if (!$user_id) {
    json_response(['success' => false, 'message' => 'user_id required'], 400);
}

if (empty($_FILES['media']) || $_FILES['media']['error'] !== UPLOAD_ERR_OK) {
    json_response(['success' => false, 'message' => 'Upload a photo or video'], 400);
}

$file = $_FILES['media'];
$allowed_image = ['image/png', 'image/jpeg', 'image/jpg'];
$allowed_video = ['video/mp4'];
$type = $file['type'];
$is_video = in_array($type, $allowed_video);
$is_image = in_array($type, $allowed_image);
if (!$is_image && !$is_video) {
    json_response(['success' => false, 'message' => 'Only PNG, JPG or MP4 allowed'], 400);
}

try {
    $pdo = db();
    $upload_dir = dirname(__DIR__) . '/uploads/stories/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    $ext = $is_video ? 'mp4' : (pathinfo($file['name'], PATHINFO_EXTENSION) ?: 'jpg');
    $filename = 'story_' . uniqid() . '.' . $ext;
    $path = $upload_dir . $filename;
    if (!move_uploaded_file($file['tmp_name'], $path)) {
        json_response(['success' => false, 'message' => 'Upload failed'], 500);
    }
    $media_url = 'uploads/stories/' . $filename;
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
