<?php
require_once __DIR__ . '/config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false, 'message' => 'Method not allowed'], 405);
}

$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';
$token = trim($input['token'] ?? '');

if ($token === '') {
    json_response(['success' => false, 'message' => 'Token is required'], 400);
}

$pdo =db();

if ($action === 'validate') {
    $stmt = $pdo->prepare('SELECT user_id FROM password_resets WHERE token = :token AND expires_at > NOW() LIMIT 1');
    $stmt->execute(['token'=> $token]);
    if (!$stmt->fetch()) {
        json_response(['success' => false, 'message' => 'Token invalid or expired'], 400);
    }

    json_response(['success' => true, 'message' => 'Token valid']);
}
if ($action === 'reset') {
    $newPassword = $input['newPassword'] ?? '';
    if (strlen($newPassword) < 8) {
        json_response(['success' => false, 'message' => 'Password must be at least 8 chars'], 400);
    }

    $stmt = $pdo->prepare('
        SELECT user_id
        FROM password_resets
        WHERE token = :token AND expires_at > NOW()
        LIMIT 1
    ');
    $stmt->execute(['token' => $token]);
    $row = $stmt->fetch();

    if (!$row) {
        json_response(['success' => false, 'message' => 'Token invalid or expired'], 400);
    }

    $hash = password_hash($newPassword, PASSWORD_BCRYPT);
    $pdo->prepare('UPDATE users SET password = :pw WHERE id = :id')
        ->execute(['pw' => $hash, 'id' => $row['user_id']]);

    $pdo->prepare('DELETE FROM password_resets WHERE token = :token')
        ->execute(['token' => $token]);

    json_response(['success' => true, 'message' => 'Password updated']);
}

json_response(['success' => false, 'message' => 'Invalid action'], 400);
?>