<?php
require_once __DIR__ . '/config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false, 'message' => 'Method not allowed'], 405);
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['studentId']) || !isset($input['email'])) {
    json_response(['success' => false, 'message' => 'Student ID and Email are required'], 400);
}

$studentId = trim($input['studentId']);
$email = trim($input['email']);

try {
    $pdo = db();

    // Look up user by username (studentId) and email
    $stmt = $pdo->prepare('SELECT id, email, username, name FROM users WHERE (username = :username OR email = :email) AND email = :email LIMIT 1');
    $stmt->execute(['username' => $studentId, 'email' => $email]);
    $user = $stmt->fetch();

    if (!$user) {
        json_response(['success' => false, 'message' => 'Account not found'], 404);
    }

    $resetToken = bin2hex(random_bytes(16));
    $expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));

    $tokenStmt = $pdo->prepare('
        INSERT INTO password_resets (user_id, token, expires_at)
        VALUES (:uid, :token, :expires_at)
        ON DUPLICATE KEY UPDATE token = VALUES(token), expires_at = VALUES(expires_at), created_at = CURRENT_TIMESTAMP
    ');
    $tokenStmt->execute([
        'uid' => $user['id'],
        'token' => $resetToken,
        'expires_at' => $expiry
    ]);

    // In a real app you would email the link. We return the token so it can be tested locally.
    json_response([
        'success' => true,
        'message' => 'Password reset link generated',
        'resetToken' => $resetToken,
        'expiresAt' => $expiry
    ]);
} catch (Exception $e) {
    json_response(['success' => false, 'message' => 'Server error'], 500);
}
?>
