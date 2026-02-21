<?php
require_once __DIR__ . '/config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

$user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;

if (!$user_id) {
    json_response(['success' => false, 'message' => 'User ID required'], 400);
}

try {
    $pdo = db();
    $stmt = $pdo->prepare('SELECT status, banned_until, ban_reason, lock_until, lock_level FROM users WHERE id = :id');
    $stmt->execute(['id' => $user_id]);
    $user = $stmt->fetch();
    
    if (!$user) {
        json_response(['success' => false, 'message' => 'User not found'], 404);
    }
    
    $locked = false;
    $lock_until = null;
    
    // Check if account is locked
    if (!empty($user['lock_until'])) {
        $lockUntil = strtotime($user['lock_until']);
        if ($lockUntil && $lockUntil > time()) {
            $locked = true;
            $lock_until = $user['lock_until'];
        }
    }
    
    $banned = false;
    $banned_until = null;
    $ban_reason = null;
    
    // Check if account is banned
    if ($user['status'] === 'banned' || !empty($user['banned_until'])) {
        $bannedUntil = strtotime($user['banned_until']);
        if ($bannedUntil && $bannedUntil > time()) {
            $banned = true;
            $banned_until = $user['banned_until'];
            $ban_reason = $user['ban_reason'];
        } else if ($user['status'] === 'banned') {
            // Ban expired, restore account
            $pdo->prepare('UPDATE users SET status = "active", banned_until = NULL, ban_reason = NULL WHERE id = :id')
                ->execute(['id' => $user_id]);
        }
    }
    
    json_response([
        'success' => true,
        'locked' => $locked,
        'lock_until' => $lock_until,
        'banned' => $banned,
        'banned_until' => $banned_until,
        'ban_reason' => $ban_reason,
        'status' => $user['status']
    ]);
} catch (Exception $e) {
    json_response(['success' => false, 'message' => 'Server error'], 500);
}
?>
