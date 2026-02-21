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
    $chk = $pdo->prepare('SELECT 1 FROM note_likes WHERE note_id = :nid AND user_id = :uid LIMIT 1');
    $chk->execute(['nid' => $note_id, 'uid' => $user_id]);
    $liked = (bool)$chk->fetch();
    if ($liked) {
        $stmt = $pdo->prepare('DELETE FROM note_likes WHERE note_id = :nid AND user_id = :uid');
        $stmt->execute(['nid' => $note_id, 'uid' => $user_id]);
        json_response(['success' => true, 'liked' => false]);
    } else {
        $stmt = $pdo->prepare('INSERT IGNORE INTO note_likes (note_id, user_id) VALUES (:nid, :uid)');
        $stmt->execute(['nid' => $note_id, 'uid' => $user_id]);
        json_response(['success' => true, 'liked' => true]);
    }
} catch (Exception $e) {
    json_response(['success' => false, 'message' => 'Server error'], 500);
}
?>
