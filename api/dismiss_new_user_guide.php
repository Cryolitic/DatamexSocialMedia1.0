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
$user_id = isset($input['user_id']) ? intval($input['user_id']) : 0;

if ($user_id <= 0) {
    json_response(['success' => false, 'message' => 'Valid user_id is required'], 400);
}

try {
    $pdo = db();

    $stmt = $pdo->prepare('UPDATE users SET new_user_guide_dismissed = 1 WHERE id = :id');
    $stmt->execute(['id' => $user_id]);

    if ($stmt->rowCount() === 0) {
        $exists = $pdo->prepare('SELECT id FROM users WHERE id = :id');
        $exists->execute(['id' => $user_id]);
        if (!$exists->fetch()) {
            json_response(['success' => false, 'message' => 'User not found'], 404);
        }
    }

    json_response(['success' => true]);
} catch (Exception $e) {
    json_response(['success' => false, 'message' => 'Server error'], 500);
}
?>
