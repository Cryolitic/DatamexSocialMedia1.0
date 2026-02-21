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
if (!$input || !isset($input['user_id']) || !isset($input['password']) || !isset($input['new_username'])) {
    json_response(['success' => false, 'message' => 'user_id, password and new_username required'], 400);
}

$user_id = (int)$input['user_id'];
$password = $input['password'];
$new_username = trim($input['new_username']);

if (strlen($new_username) < 3) {
    json_response(['success' => false, 'message' => 'Username must be at least 3 characters'], 400);
}
if (strlen($new_username) > 50) {
    json_response(['success' => false, 'message' => 'Username must be 50 characters or less'], 400);
}
if (!preg_match('/^[A-Za-z0-9_]+$/', $new_username)) {
    json_response(['success' => false, 'message' => 'Username can only contain letters, numbers and underscore'], 400);
}

try {
    $pdo = db();
    $stmt = $pdo->prepare('SELECT id, password, username FROM users WHERE id = :id');
    $stmt->execute(['id' => $user_id]);
    $user = $stmt->fetch();
    if (!$user || !password_verify($password, $user['password'])) {
        json_response(['success' => false, 'message' => 'Password is incorrect'], 400);
    }
    if ($user['username'] === $new_username) {
        json_response(['success' => false, 'message' => 'New username is the same as current'], 400);
    }
    $chk = $pdo->prepare('SELECT id FROM users WHERE username = :u AND id != :id');
    $chk->execute(['u' => $new_username, 'id' => $user_id]);
    if ($chk->fetch()) {
        json_response(['success' => false, 'message' => 'Username is already taken'], 400);
    }
    $up = $pdo->prepare('UPDATE users SET username = :u WHERE id = :id');
    $up->execute(['u' => $new_username, 'id' => $user_id]);
    json_response(['success' => true, 'message' => 'Username updated', 'username' => $new_username]);
} catch (Exception $e) {
    json_response(['success' => false, 'message' => 'Server error'], 500);
}
?>
