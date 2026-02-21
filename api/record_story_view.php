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
if (!$input || !isset($input['story_id']) || !isset($input['viewer_id'])) {
    json_response(['success' => false, 'message' => 'story_id and viewer_id required'], 400);
}

$story_id = (int)$input['story_id'];
$viewer_id = (int)$input['viewer_id'];

try {
    $pdo = db();
    $stmt = $pdo->prepare('INSERT IGNORE INTO story_views (story_id, viewer_id) VALUES (:sid, :vid)');
    $stmt->execute(['sid' => $story_id, 'vid' => $viewer_id]);
    json_response(['success' => true]);
} catch (Exception $e) {
    json_response(['success' => false, 'message' => 'Server error'], 500);
}
?>
