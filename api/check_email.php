<?php
require_once __DIR__ . '/config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

$email = isset($_GET['email']) ? trim($_GET['email']) : '';

if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    json_response(['success' => false, 'exists' => false, 'message' => 'Invalid email'], 400);
}

try {
    $pdo = db();
    $normalizedEmail = strtolower($email);
    $stmt = $pdo->prepare('SELECT id FROM users WHERE LOWER(TRIM(email)) = :email LIMIT 1');
    $stmt->execute(['email' => $normalizedEmail]);
    $exists = (bool)$stmt->fetch();
    
    json_response(['success' => true, 'exists' => $exists]);
} catch (Exception $e) {
    json_response(['success' => false, 'exists' => false, 'message' => 'Server error'], 500);
}
