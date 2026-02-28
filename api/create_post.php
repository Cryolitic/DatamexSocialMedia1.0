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
$content = isset($_POST['content']) ? trim($_POST['content']) : '';
$privacy = isset($_POST['privacy']) ? $_POST['privacy'] : 'public';
// Normalize: only_me, followers, friends_of_friends, public (legacy 'private' -> only_me)
if ($privacy === 'private') $privacy = 'only_me';
$post_type = isset($_POST['post_type']) ? $_POST['post_type'] : 'post';
$reference_post = isset($_POST['reference_post']) ? intval($_POST['reference_post']) : null;
$media_files = [];
$uploadApi = cloudinary_upload_api();

if (!$user_id) {
    json_response(['success' => false, 'message' => 'User is required'], 400);
}



// Handle file upload (single: media, multiple: media[])
if (isset($_FILES['media'])) {
    $upload = $_FILES['media'];
    $isMulti = is_array($upload['name']);

    $files = [];
    if ($isMulti) {
        for ($i = 0; $i < count($upload['name']); $i++) {
            $files[] = [
                'name' => $upload['name'][$i],
                'type' => $upload['type'][$i] ?? null,
                'tmp_name' => $upload['tmp_name'][$i],
                'error' => $upload['error'][$i],
                'size' => $upload['size'][$i],
            ];
        }
    } else {
        $files[] = $upload;
    }

    $max_size = 5 * 1024 * 1024;
    $allowed_types = ['image/png', 'image/jpeg', 'image/jpg', 'video/mp4'];
    
    

    foreach ($files as $file) {
        if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
            continue;
        }
        if ($file['size'] > $max_size) {
            json_response(['success' => false, 'message' => 'File size exceeds 5MB limit'], 400);
        }
        $file_type = mime_content_type($file['tmp_name']);
        if (!in_array($file_type, $allowed_types)) {
            json_response(['success' => false, 'message' => 'Only PNG, JPG, and MP4 are allowed'], 400);
        }
        try {
            $uploadResult = $uploadApi->upload($file['tmp_name'],[
                'folder' => 'uploads/posts',
                'resource_type' => 'auto'
            ]);
            $media_files[] = [
                'url' => $uploadResult['secure_url'],
                'public_id' => $uploadResult['public_id'],
                'resource_type' => $uploadResult['resource_type'] ?? 'image'
            ];
        } catch (Exception $e) {
            json_response([
                'success' => false,
                'message' => 'Cloud upload failed: ' . $e->getMessage()
            ], 500);
        }
        
    }
}

if (empty($content) && empty($media_files)) {
    json_response(['success' => false, 'message' => 'Post content or media is required'], 400);
}

try {
    $pdo = db();
    
    // Get user account type
    $userStmt = $pdo->prepare('SELECT id, username, name, avatar, account_type FROM users WHERE id = :id');
    $userStmt->execute(['id' => $user_id]);
    $user = $userStmt->fetch();
    
    if (!$user) {
        json_response(['success' => false, 'message' => 'User not found'], 404);
    }
    
    // Only faculty and admin can create announcements
    if ($post_type === 'announcement' && !in_array($user['account_type'], ['faculty', 'admin'])) {
        json_response(['success' => false, 'message' => 'Only faculty and admin can create announcements'], 403);
    }
    
    // Announcements are always public; faculty announcements need admin approval
    $announcement_status = null;
    if ($post_type === 'announcement') {
        $privacy = 'public';
        if ($user['account_type'] === 'faculty') {
            $announcement_status = 'pending';
        } elseif ($user['account_type'] === 'admin') {
            $announcement_status = 'approved';
        }
    }
    
    $allowed_privacy = ['only_me', 'followers', 'friends_of_friends', 'public'];
    if (!in_array($privacy, $allowed_privacy)) $privacy = 'public';
    
    $stmt = $pdo->prepare("
        INSERT INTO posts (user_id, content, media_type, media_urls, privacy, post_type, announcement_status, reference_post)
        VALUES (:user_id, :content, :media_type, :media_urls, :privacy, :post_type, :announcement_status, :reference_post)
    ");
    $media_type = 'text';
    if (!empty($media_files)) {
        // If any file is mp4 => video, else image(s)
        $hasVideo = false;
        foreach ($media_files as $m) {
            if (($m['resource_type'] ?? '') === 'video') { $hasVideo = true; break; }
        }
        $media_type = $hasVideo ? 'video' : 'image';
    }
    $media_urls_json = !empty($media_files) ? json_encode($media_files) : null;
    $stmt->execute([
        'user_id' => $user_id,
        'content' => $content,
        'media_type' => $media_type,
        'media_urls' => $media_urls_json,
        'privacy' => $privacy,
        'post_type' => $post_type,
        'announcement_status' => $announcement_status,
        'reference_post' => $reference_post ?: null
    ]);

    $post_id = (int)$pdo->lastInsertId();
    
    // Notify all admins when a student posts, or when faculty posts a pending announcement
    if (($user['account_type'] === 'student' && $post_type === 'post') || ($user['account_type'] === 'faculty' && $post_type === 'announcement' && $announcement_status === 'pending')) {
        $adminStmt = $pdo->prepare('SELECT id FROM users WHERE is_admin = 1 OR account_type = "admin"');
        $adminStmt->execute();
        $admins = $adminStmt->fetchAll();
        $msg = $post_type === 'announcement' 
            ? ($user['name'] ?: $user['username']) . ' posted an announcement (pending approval): ' . (mb_substr($content, 0, 50) . (mb_strlen($content) > 50 ? '...' : ''))
            : ($user['name'] ?: $user['username']) . ' posted: ' . (mb_substr($content, 0, 50) . (mb_strlen($content) > 50 ? '...' : ''));
        foreach ($admins as $admin) {
            $notifStmt = $pdo->prepare('
                INSERT INTO notifications (user_id, from_user_id, post_id, type, message)
                VALUES (:uid, :from_uid, :pid, "admin_post", :msg)
            ');
            $notifStmt->execute([
                'uid' => $admin['id'],
                'from_uid' => $user_id,
                'pid' => $post_id,
                'msg' => $msg
            ]);
        }
    }

    json_response([
        'success' => true,
        'post' => [
            'id' => $post_id,
            'user_id' => $user_id,
            'username' => $user['username'] ?: $user['id'],
            'name' => $user['name'] ?: $user['username'],
            'avatar' => $user['avatar'] ?: 'https://res.cloudinary.com/dmkoc4lis/image/upload/v1772225107/default-avatar_ylfaff.png',
            'content' => $content,
            'media' => !empty($media_files) ? $media_files : null,
            'likes' => 0,
            'comments' => [],
            'shares' => 0,
            'timestamp' => date('Y-m-d H:i:s'),
            'reference_post' => $reference_post
        ]
    ]);
} catch (Exception $e) {
    json_response(['success' => false, 'message' => 'Server error'], 500);
}
?>
