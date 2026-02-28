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

$input = json_decode(file_get_contents('php://input'), true);
if (!$input || !isset($input['post_id']) || !isset($input['user_id'])) {
    json_response(['success' => false, 'message' => 'Post ID and user are required'], 400);
}


$uploadApi = cloudinary_upload_api();
$post_id = intval($input['post_id']);
$user_id = intval($input['user_id']);

try {
    $pdo = db();
    $stmt = $pdo->prepare('SELECT media_urls, user_id FROM posts WHERE id = :id');
    $stmt->execute(['id' => $post_id]);
    $post = $stmt->fetch();

    if (!$post) {
        json_response(['success' => false, 'message' => 'Post not found'], 404);
    }

    // Verify owner or admin
    

    if (!empty($post['media_urls'])) {
        $decoded = json_decode($post['media_urls'], true);
        if (is_array($decoded)) {
            foreach ($decoded as $media) {
                if (!is_array($media) || empty($media['public_id'])) {
                    continue;
                }
                try {
                    $uploadApi->destroy($media['public_id'],[
                    'resource_type' => $media['resource_type'] ?? 'image',
                    'invalidate' => true
                    ]);
                } catch(Exception $e) {
                    json_response([
                        'success' => false,
                        'message' => 'Cloud delete failed: ' . $e->getMessage()
                    ], 500);
                }
            }
        }
    }

    
    $del = $pdo->prepare('DELETE FROM posts WHERE id = :id');
    $del->execute(['id' => $post_id]);

    json_response(['success' => true, 'message' => 'Post deleted successfully']);
} catch (Exception $e) {
    json_response(['success' => false, 'message' => 'Server error'], 500);
}
?>
