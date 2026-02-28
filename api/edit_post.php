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

if (!$input || !isset($input['post_id']) || !isset($input['content']) || !isset($input['user_id'])) {
    json_response(['success' => false, 'message' => 'Post ID, content, and user are required'], 400);
}

$post_id = intval($input['post_id']);
$content = trim($input['content']);
$user_id = intval($input['user_id']);

if (empty($content)) {
    json_response(['success' => false, 'message' => 'Content cannot be empty'], 400);
}

try {
    $pdo = db();

    // Check ownership or admin
    $check = $pdo->prepare('SELECT user_id FROM posts WHERE id = :id');
    $check->execute(['id' => $post_id]);
    $post = $check->fetch();
    if (!$post) {
        json_response(['success' => false, 'message' => 'Post not found'], 404);
    }

    // Verify owner or admin
    $userStmt = $pdo->prepare('SELECT account_type FROM users WHERE id = :id');
    $userStmt->execute(['id' => $user_id]);
    $userRow = $userStmt->fetch();
    $isAdmin = $userRow && ($userRow['account_type'] === 'admin');
    if ($post['user_id'] != $user_id && !$isAdmin) {
        json_response(['success' => false, 'message' => 'Unauthorized'], 403);
    }

    // Update content
    $upd = $pdo->prepare('UPDATE posts SET content = :content, updated_at = NOW() WHERE id = :id');
    $upd->execute(['content' => $content, 'id' => $post_id]);

    json_response(['success' => true, 'message' => 'Post updated successfully']);
} catch (Exception $e) {
    json_response(['success' => false, 'message' => 'Server error'], 500);
}
?>
