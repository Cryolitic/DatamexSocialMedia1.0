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

try {
    $pdo = db();
    $stmt = $pdo->prepare('DELETE FROM notes WHERE id = :id AND user_id = :uid');
    $stmt->execute(['id' => $note_id, 'uid' => $user_id]);
    json_response(['success' => true]);
} catch (Exception $e) {
    json_response(['success' => false, 'message' => 'Server error'], 500);
}
?>
