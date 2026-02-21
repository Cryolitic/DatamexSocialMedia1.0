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
    json_response(['success' => false, 'valid' => false, 'message' => 'Method not allowed'], 405);
}

$input = json_decode(file_get_contents('php://input'), true);
$email = isset($input['email']) ? trim($input['email']) : '';
$code = isset($input['code']) ? trim($input['code']) : '';

if (empty($email) || empty($code)) {
    json_response(['success' => true, 'valid' => false, 'message' => 'Email and code required']);
    exit;
}
$email = strtolower($email);

try {
    $pdo = db();
    $stmt = $pdo->prepare('SELECT id FROM email_verification WHERE LOWER(TRIM(email)) = :email AND code = :code AND expires_at > NOW() ORDER BY created_at DESC LIMIT 1');
    $stmt->execute(['email' => $email, 'code' => $code]);
    $row = $stmt->fetch();
    
    $valid = (bool)$row;
    // Do not delete here; register.php will delete when completing registration
    
    json_response(['success' => true, 'valid' => $valid]);
} catch (Exception $e) {
    json_response(['success' => false, 'valid' => false, 'message' => 'Server error'], 500);
}
