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
if (!$input || !isset($input['note_id']) || !isset($input['user_id'])) {
    json_response(['success' => false, 'message' => 'note_id and user_id required'], 400);
}

$note_id = (int)$input['note_id'];
$user_id = (int)$input['user_id'];
$content = isset($input['content']) ? trim($input['content']) : null;
if ($content !== null && mb_strlen($content) > 60) {
    json_response(['success' => false, 'message' => 'Note must be 60 characters or less'], 400);
}

try {
    $pdo = db();
    $check = $pdo->prepare('SELECT id FROM notes WHERE id = :id AND user_id = :uid');
    $check->execute(['id' => $note_id, 'uid' => $user_id]);
    if (!$check->fetch()) {
        json_response(['success' => false, 'message' => 'Note not found'], 404);
    }
    $updates = [];
    $params = ['id' => $note_id];
    if ($content !== null) { $updates[] = 'content = :content'; $params['content'] = $content; }
    if (empty($updates)) {
        json_response(['success' => true]);
        return;
    }
    $sql = 'UPDATE notes SET ' . implode(', ', $updates) . ' WHERE id = :id';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    json_response(['success' => true]);
} catch (Exception $e) {
    json_response(['success' => false, 'message' => 'Server error'], 500);
}
?>
