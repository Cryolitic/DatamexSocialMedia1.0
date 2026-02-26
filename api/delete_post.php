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
if (!$input || !isset($input['post_id']) || !isset($input['user_id'])) {
    json_response(['success' => false, 'message' => 'Post ID and user are required'], 400);
}

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
    $userStmt = $pdo->prepare('SELECT is_admin FROM users WHERE id = :id');
    $userStmt->execute(['id' => $user_id]);
    $user = $userStmt->fetch();
    $isAdmin = $user ? (bool)$user['is_admin'] : false;
    if ($post['user_id'] != $user_id && !$isAdmin) {
        json_response(['success' => false, 'message' => 'Unauthorized'], 403);
    }

    $del = $pdo->prepare('DELETE FROM posts WHERE id = :id');
    $del->execute(['id' => $post_id]);

    if (!empty($post['media_urls'])) {
        $decoded = json_decode($post['media_urls'], true);
        if (is_array($decoded)) {
            foreach ($decoded as $mediaPath) {
                if (!is_string($mediaPath) || $mediaPath === '') {
                    continue;
                }
                if (str_starts_with($mediaPath, 'uploads/posts/')) {
                    $path = __DIR__ . '/../' . $mediaPath;
                    if (file_exists($path)) {
                        @unlink($path);
                    }
                }
            }
        }
    }

    json_response(['success' => true, 'message' => 'Post deleted successfully']);
} catch (Exception $e) {
    json_response(['success' => false, 'message' => 'Server error'], 500);
}
?>
