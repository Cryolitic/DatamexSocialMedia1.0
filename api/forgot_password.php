<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

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

    // Block creating a new reset while an existing one is still valid.
    $activeResetStmt = $pdo->prepare('
        SELECT token, expires_at
        FROM password_resets
        WHERE user_id = :uid AND expires_at > NOW()
        ORDER BY expires_at DESC
        LIMIT 1
    ');
    $activeResetStmt->execute(['uid' => $user['id']]);
    $activeReset = $activeResetStmt->fetch();
    if ($activeReset) {
        json_response([
            'success' => false,
            'message' => 'A reset link is already active. Please wait until it expires before requesting another.',
            'expiresAt' => $activeReset['expires_at']
        ], 429);
    }

    $resetToken = bin2hex(random_bytes(16));
    $resetLink = "http://localhost/BSIT_3B/system%20capstone/reset_password.html?token=" . urlencode($resetToken);
    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host = 'smtp.gmail.com';
    $mail->SMTPAuth = true;
    $mail->Username = 'notthebestgamer64@gmail.com';     
    $mail->Password = pass;
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = 587;

    $mail->setFrom('notthebestgamer64@gmail.com', 'DCSA');
    $mail->addAddress($email);

    $mail->isHTML(true);
    $mail->Subject = 'DCSA - Password Reset';
    $mail->Body = "
    Hello {$user['name']},

    Click <a href=\"{$resetLink}\">Reset your password</a>.

    This link expires in 10 minutes.
    ";
    $mail->send();

    $pdo->prepare('DELETE FROM password_resets WHERE user_id = :user_id')->execute(['user_id' => $user['id']]);
    $tokenStmt = $pdo->prepare('
        INSERT INTO password_resets (user_id, token, expires_at)
        VALUES (:uid, :token, DATE_ADD(NOW(), INTERVAL 10 MINUTE))
        ON DUPLICATE KEY UPDATE token = VALUES(token), expires_at = VALUES(expires_at), created_at = CURRENT_TIMESTAMP
    ');
    $tokenStmt->execute([
        'uid' => $user['id'],
        'token' => $resetToken
    ]);

    $expStmt = $pdo->prepare('SELECT expires_at FROM password_resets WHERE token = :token LIMIT 1');
    $expStmt->execute(['token' => $resetToken]);
    $expiry = $expStmt->fetchColumn();

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
