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
if (!$input || !isset($input['user_id']) || !isset($input['current_password']) || !isset($input['new_password'])) {
    json_response(['success' => false, 'message' => 'user_id, current_password and new_password required'], 400);
}

$user_id = (int)$input['user_id'];
$current = $input['current_password'];
$new = $input['new_password'];

if (strlen($new) < 10) {
    json_response(['success' => false, 'message' => 'New password must be at least 10 characters'], 400);
}
if (!preg_match('/[A-Z]/', $new) || !preg_match('/[a-z]/', $new) || !preg_match('/[0-9]/', $new)) {
    json_response(['success' => false, 'message' => 'New password must contain uppercase, lowercase and number'], 400);
}
if (preg_match('/[^A-Za-z0-9]/', $new)) {
    json_response(['success' => false, 'message' => 'New password must not contain special characters'], 400);
}

try {
    $pdo = db();
    $stmt = $pdo->prepare('SELECT id, password FROM users WHERE id = :id');
    $stmt->execute(['id' => $user_id]);
    $user = $stmt->fetch();
    if (!$user || !password_verify($current, $user['password'])) {
        json_response(['success' => false, 'message' => 'Current password is incorrect'], 400);
    }
    $hash = password_hash($new, PASSWORD_BCRYPT);
    $up = $pdo->prepare('UPDATE users SET password = :pw WHERE id = :id');
    $up->execute(['pw' => $hash, 'id' => $user_id]);
    json_response(['success' => true, 'message' => 'Password updated']);
} catch (Exception $e) {
    json_response(['success' => false, 'message' => 'Server error'], 500);
}
?>
