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

if (!$input || !isset($input['fullName']) || !isset($input['email']) || !isset($input['password'])) {
    json_response(['success' => false, 'message' => 'Full name, email, and password are required'], 400);
}

$fullName = trim($input['fullName']);
$email = trim($input['email']);
$password = $input['password'];
$accountType = isset($input['accountType']) ? $input['accountType'] : 'student';
$username = isset($input['studentId']) ? trim($input['studentId']) : null;
$verificationCode = isset($input['verificationCode']) ? trim($input['verificationCode']) : '';

// Validate account type
if (!in_array($accountType, ['student', 'faculty', 'admin'])) {
    json_response(['success' => false, 'message' => 'Invalid account type'], 400);
}

// Password: at least 10 chars, upper, lower, numbers, no special characters
if (strlen($password) < 10) {
    json_response(['success' => false, 'message' => 'Password must be at least 10 characters'], 400);
}
if (!preg_match('/[A-Z]/', $password)) {
    json_response(['success' => false, 'message' => 'Password must contain at least one uppercase letter'], 400);
}
if (!preg_match('/[a-z]/', $password)) {
    json_response(['success' => false, 'message' => 'Password must contain at least one lowercase letter'], 400);
}
if (!preg_match('/[0-9]/', $password)) {
    json_response(['success' => false, 'message' => 'Password must contain at least one number'], 400);
}
if (preg_match('/[^A-Za-z0-9]/', $password)) {
    json_response(['success' => false, 'message' => 'Password must not contain special characters'], 400);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    json_response(['success' => false, 'message' => 'Invalid email format'], 400);
}
$email = strtolower($email);

if ($verificationCode === '') {
    json_response(['success' => false, 'message' => 'Email verification is required'], 400);
}
if (!preg_match('/^\d{6}$/', $verificationCode)) {
    json_response(['success' => false, 'message' => 'Invalid verification code format'], 400);
}

try {
    $pdo = db();

    // Verification is required before registration.
    $v = $pdo->prepare('SELECT id FROM email_verification WHERE LOWER(TRIM(email)) = :email AND code = :code AND expires_at > NOW() ORDER BY created_at DESC LIMIT 1');
    $v->execute(['email' => $email, 'code' => $verificationCode]);
    if (!$v->fetch()) {
        json_response(['success' => false, 'message' => 'Invalid or expired verification code. Request a new code.'], 400);
    }
    $pdo->prepare('DELETE FROM email_verification WHERE LOWER(TRIM(email)) = :email')->execute(['email' => $email]);

    // Check duplicates (email/username already used)
    $check = $pdo->prepare('SELECT id FROM users WHERE LOWER(TRIM(email)) = :email OR username = :username');
    $check->execute(['email' => $email, 'username' => $username]);
    if ($check->fetch()) {
        json_response(['success' => false, 'message' => 'Email or ID already exists'], 400);
    }

    $hash = password_hash($password, PASSWORD_BCRYPT);
    $stmt = $pdo->prepare('
        INSERT INTO users (username, email, password, name, bio, avatar, account_type)
        VALUES (:username, :email, :password, :name, :bio, :avatar, :account_type)
    ');
    $stmt->execute([
        'username' => $username ?: null,
        'email' => $email,
        'password' => $hash,
        'name' => $fullName,
        'bio' => '',
        'avatar' => 'assets/images/default-avatar.png',
        'account_type' => $accountType,
    ]);

    $userId = (int)$pdo->lastInsertId();

    json_response([
        'success' => true,
        'message' => 'Registration successful',
        'user' => [
            'id' => $userId,
            'username' => $username ?: $email,
            'name' => $fullName,
            'email' => $email,
            'avatar' => 'assets/images/default-avatar.png',
            'accountType' => $accountType,
            'isAdmin' => ($accountType === 'admin')
        ]
    ]);
} catch (Exception $e) {
    error_log('Registration error: ' . $e->getMessage());
    json_response(['success' => false, 'message' => 'Server error: ' . $e->getMessage()], 500);
}
?>
