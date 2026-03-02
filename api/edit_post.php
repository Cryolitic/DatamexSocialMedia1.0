<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../vendor/autoload.php';
ini_set('display_errors', '0');

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

function ini_size_to_bytes(string $value): int {
    $value = trim($value);
    if ($value === '') return 0;
    $unit = strtolower(substr($value, -1));
    $num = (float)$value;
    return match ($unit) {
        'g' => (int)($num * 1024 * 1024 * 1024),
        'm' => (int)($num * 1024 * 1024),
        'k' => (int)($num * 1024),
        default => (int)$num,
    };
}

$postMaxBytes = ini_size_to_bytes((string)ini_get('post_max_size'));
$contentLength = isset($_SERVER['CONTENT_LENGTH']) ? (int)$_SERVER['CONTENT_LENGTH'] : 0;
if ($postMaxBytes > 0 && $contentLength > $postMaxBytes) {
    json_response([
        'success' => false,
        'message' => 'Total upload is too large for server limit. Please upload smaller or fewer files.'
    ], 413);
}

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

$post_id = 0;
$user_id = 0;
$content = '';
$removed_media_urls = [];

if (isset($_POST['post_id']) || isset($_FILES['media'])) {
    $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
    $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
    $content = isset($_POST['content']) ? trim((string)$_POST['content']) : '';
    if (!empty($_POST['removed_media_urls'])) {
        $removed = json_decode((string)$_POST['removed_media_urls'], true);
        if (is_array($removed)) {
            $removed_media_urls = array_values(array_filter($removed, fn($x) => is_string($x) && trim($x) !== ''));
        }
    }
} else {
    $input = json_decode(file_get_contents('php://input'), true);
    if (is_array($input)) {
        $post_id = isset($input['post_id']) ? intval($input['post_id']) : 0;
        $user_id = isset($input['user_id']) ? intval($input['user_id']) : 0;
        $content = isset($input['content']) ? trim((string)$input['content']) : '';
        if (isset($input['removed_media_urls']) && is_array($input['removed_media_urls'])) {
            $removed_media_urls = array_values(array_filter($input['removed_media_urls'], fn($x) => is_string($x) && trim($x) !== ''));
        }
    }
}

if (!$post_id || !$user_id) {
    json_response(['success' => false, 'message' => 'Post ID and user are required'], 400);
}

try {
    $pdo = db();
    $uploadApi = cloudinary_upload_api();

    $check = $pdo->prepare('SELECT user_id, media_urls FROM posts WHERE id = :id');
    $check->execute(['id' => $post_id]);
    $post = $check->fetch();
    if (!$post) {
        json_response(['success' => false, 'message' => 'Post not found'], 404);
    }

    $userStmt = $pdo->prepare('SELECT account_type FROM users WHERE id = :id');
    $userStmt->execute(['id' => $user_id]);
    $userRow = $userStmt->fetch();
    $isAdmin = $userRow && ($userRow['account_type'] === 'admin');
    if ($post['user_id'] != $user_id && !$isAdmin) {
        json_response(['success' => false, 'message' => 'Unauthorized'], 403);
    }

    $existing_media = [];
    if (!empty($post['media_urls'])) {
        $decoded = json_decode($post['media_urls'], true);
        if (is_array($decoded)) {
            foreach ($decoded as $item) {
                if (is_array($item) && !empty($item['url'])) {
                    $existing_media[] = [
                        'url' => $item['url'],
                        'public_id' => $item['public_id'] ?? null,
                        'resource_type' => $item['resource_type'] ?? 'image'
                    ];
                } elseif (is_string($item) && trim($item) !== '') {
                    $existing_media[] = [
                        'url' => trim($item),
                        'public_id' => null,
                        'resource_type' => 'image'
                    ];
                }
            }
        }
    }

    $remove_lookup = [];
    foreach ($removed_media_urls as $url) {
        $remove_lookup[$url] = true;
    }

    $kept_media = [];
    $to_delete = [];
    foreach ($existing_media as $media) {
        if (!empty($remove_lookup[$media['url']])) {
            $to_delete[] = $media;
        } else {
            $kept_media[] = $media;
        }
    }

    foreach ($to_delete as $media) {
        if (!empty($media['public_id'])) {
            try {
                $uploadApi->destroy($media['public_id'], [
                    'resource_type' => $media['resource_type'] ?? 'image',
                    'invalidate' => true
                ]);
            } catch (Exception $e) {
                json_response([
                    'success' => false,
                    'message' => 'Cloud delete failed: ' . $e->getMessage()
                ], 500);
            }
        }
    }

    $new_media = [];
    if (isset($_FILES['media'])) {
        $upload = $_FILES['media'];
        $isMulti = is_array($upload['name']);
        $files = [];
        if ($isMulti) {
            for ($i = 0; $i < count($upload['name']); $i++) {
                if (($upload['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                    continue;
                }
                $files[] = [
                    'name' => $upload['name'][$i],
                    'type' => $upload['type'][$i] ?? null,
                    'tmp_name' => $upload['tmp_name'][$i],
                    'error' => $upload['error'][$i],
                    'size' => $upload['size'][$i],
                ];
            }
        } else {
            if (($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                $files[] = $upload;
            }
        }

        $max_size = 30 * 1024 * 1024;
        $allowed_types = ['image/png', 'image/jpeg', 'image/jpg', 'video/mp4'];
        foreach ($files as $file) {
            if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
                json_response(['success' => false, 'message' => upload_error_message((int)($file['error'] ?? UPLOAD_ERR_NO_FILE))], 400);
            }
            if (($file['size'] ?? 0) > $max_size) {
                json_response(['success' => false, 'message' => 'File size exceeds 30MB limit'], 400);
            }
            $file_type = mime_content_type($file['tmp_name']);
            if (!in_array($file_type, $allowed_types)) {
                json_response(['success' => false, 'message' => 'Only PNG, JPG, and MP4 are allowed'], 400);
            }
            try {
                $uploadResult = $uploadApi->upload($file['tmp_name'], [
                    'folder' => 'uploads/posts',
                    'resource_type' => 'auto'
                ]);
                $new_media[] = [
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

    $final_media = array_values(array_merge($kept_media, $new_media));
    if ($content === '' && empty($final_media)) {
        json_response(['success' => false, 'message' => 'Post must have text or media'], 400);
    }

    $media_type = 'text';
    if (!empty($final_media)) {
        $has_video = false;
        foreach ($final_media as $media) {
            if (($media['resource_type'] ?? '') === 'video') {
                $has_video = true;
                break;
            }
            $url = strtolower((string)($media['url'] ?? ''));
            if ($url !== '' && substr($url, -4) === '.mp4') {
                $has_video = true;
                break;
            }
        }
        $media_type = $has_video ? 'video' : 'image';
    }

    $media_urls_json = !empty($final_media) ? json_encode($final_media) : null;
    $upd = $pdo->prepare('UPDATE posts SET content = :content, media_type = :media_type, media_urls = :media_urls, updated_at = NOW() WHERE id = :id');
    $upd->execute([
        'content' => $content,
        'media_type' => $media_type,
        'media_urls' => $media_urls_json,
        'id' => $post_id
    ]);

    json_response([
        'success' => true,
        'message' => 'Post updated successfully',
        'post' => [
            'id' => $post_id,
            'content' => $content,
            'media' => array_values(array_map(fn($m) => $m['url'], $final_media)),
            'media_type' => $media_type
        ]
    ]);
} catch (Exception $e) {
    json_response(['success' => false, 'message' => 'Server error'], 500);
}
?>
