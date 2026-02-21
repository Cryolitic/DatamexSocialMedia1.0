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
if (!$input || !isset($input['user_id']) || !isset($input['content'])) {
    json_response(['success' => false, 'message' => 'user_id and content required'], 400);
}

$user_id = (int)$input['user_id'];
$content = trim($input['content']);
if (mb_strlen($content) > 60) {
    json_response(['success' => false, 'message' => 'Note must be 60 characters or less'], 400);
}
if ($content === '') {
    json_response(['success' => false, 'message' => 'Please type your note'], 400);
}

try {
    $pdo = db();
    $stmt = $pdo->prepare('INSERT INTO notes (user_id, title, content) VALUES (:uid, "", :content)');
    $stmt->execute(['uid' => $user_id, 'content' => $content]);
    $id = (int)$pdo->lastInsertId();
    json_response(['success' => true, 'note_id' => $id]);
} catch (Exception $e) {
    json_response(['success' => false, 'message' => 'Server error'], 500);
}
?>
