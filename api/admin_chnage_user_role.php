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
if (!$input || !isset($input['admin_id']) || !isset($input['user_id']) || !isset($input['account_type'])) {
    json_response(['success' => false, 'message' => 'admin_id, user_id, and account_type are required'], 400);
}

$admin_id = intval($input['admin_id']);
$user_id = intval($input['user_id']);
$newRole = trim(strtolower($input['account_type']));

if (!in_array($newRole, ['student', 'faculty'], true)) {
    json_response(['success' => false, 'message' => 'Only student or faculty roles are allowed'], 400);
}

if ($admin_id <= 0 || $user_id <= 0) {
    json_response(['success' => false, 'message' => 'Invalid admin_id or user_id'], 400);
}

if ($admin_id === $user_id) {
    json_response(['success' => false, 'message' => 'You cannot change your own role'], 403);
}

try {
    $pdo = db();

    // Verify requester is admin.
    $adminCheck = $pdo->prepare('SELECT id FROM users WHERE id = :id AND account_type = "admin"');
    $adminCheck->execute(['id' => $admin_id]);
    if (!$adminCheck->fetch()) {
        json_response(['success' => false, 'message' => 'Unauthorized'], 403);
    }

    // Target user must exist and only be student/faculty (no admin role changes here).
    $userStmt = $pdo->prepare('SELECT id, account_type, username, name FROM users WHERE id = :id');
    $userStmt->execute(['id' => $user_id]);
    $user = $userStmt->fetch();
    if (!$user) {
        json_response(['success' => false, 'message' => 'User not found'], 404);
    }

    $currentRole = $user['account_type'] ?? 'student';
    if (!in_array($currentRole, ['student', 'faculty'], true)) {
        json_response(['success' => false, 'message' => 'Only student/faculty accounts can be changed with this endpoint'], 400);
    }

    if ($currentRole === $newRole) {
        json_response(['success' => false, 'message' => 'User already has that role'], 400);
    }

    $upd = $pdo->prepare('UPDATE users SET account_type = :role WHERE id = :id');
    $upd->execute([
        'role' => $newRole,
        'id' => $user_id
    ]);

    log_admin_action(
        $pdo,
        $admin_id,
        'Change User Role',
        "Changed user #{$user_id} (" . ($user['username'] ?: 'unknown') . ") role from {$currentRole} to {$newRole}"
    );

    json_response([
        'success' => true,
        'message' => 'User role updated successfully',
        'user' => [
            'id' => (int)$user_id,
            'account_type' => $newRole
        ]
    ]);
} catch (Exception $e) {
    json_response(['success' => false, 'message' => 'Server error'], 500);
}
?>
