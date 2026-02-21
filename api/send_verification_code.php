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
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false, 'message' => 'Method not allowed'], 405);
}

$input = json_decode(file_get_contents('php://input'), true);
$email = isset($input['email']) ? trim($input['email']) : '';

if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    json_response(['success' => false, 'message' => 'Invalid email'], 400);
}
$email = strtolower($email);
 
try {
    $pdo = db();
    
    // Avoid redundant: do not send if email is already registered
    $check = $pdo->prepare('SELECT id FROM users WHERE LOWER(TRIM(email)) = :email LIMIT 1');
    $check->execute(['email' => $email]);
    if ($check->fetch()) {
        json_response(['success' => false, 'message' => 'This email is already registered'], 400);
    }
    
    $code = (string)random_int(100000, 999999);
    $pdo->prepare('DELETE FROM email_verification WHERE email = :email')->execute(['email' => $email]);
    $ins = $pdo->prepare('INSERT INTO email_verification (email, code, expires_at) VALUES (:email, :code, DATE_ADD(NOW(), INTERVAL 10 MINUTE))');
    $ins->execute(['email' => $email, 'code' => $code]);
    
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

    $mail->isHTML(false);
    $mail->Subject = 'DCSA - Verification Code';
    $mail->Body = "Your verification code is: {$code}\n\nIt expires in 10 minutes.\n\nIf you did not request this, ignore this message.";

    $mail->send();

    json_response([
        'success' => true,
        'message' => 'Verification code sent to your email'
    ]);
    
    
} catch (Exception $e) {
    error_log('Mailer error: ' . $e->getMessage());

    json_response([
        'success' => false,
        'message' => 'Unable to send verification code right now.'
    ], 500);
}
