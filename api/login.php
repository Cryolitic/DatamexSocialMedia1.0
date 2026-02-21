<?php
/**
 * Login API Endpoint (email + password)
 */
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

if (!$input || !isset($input['username']) || !isset($input['password'])) {
    json_response(['success' => false, 'message' => 'Email and password are required'], 400);
}

$email = trim($input['username']);
$password = $input['password'];

try {
    $pdo = db();
    // Allow login by email OR username
    $stmt = $pdo->prepare('SELECT * FROM users WHERE email = :ident OR username = :ident LIMIT 1');
    $stmt->execute(['ident' => $email]);
    $user = $stmt->fetch();

    if ($user) {
        // Enforce lock
        if (!empty($user['lock_until'])) {
            $lockUntil = strtotime($user['lock_until']);
            if ($lockUntil && $lockUntil > time()) {
                $remaining = $lockUntil - time();
                json_response([
                    'success' => false,
                    'message' => 'Too many attempts. Try again later.',
                    'lock' => [
                        'until' => $user['lock_until'],
                        'seconds_remaining' => $remaining,
                        'level' => (int)($user['lock_level'] ?? 0),
                    ]
                ], 429);
            }
        }

        if (isset($user['status']) && $user['status'] === 'suspended') {
            json_response(['success' => false, 'message' => 'Account suspended, contact admin'], 403);
        }
    }

    if (!$user || !password_verify($password, $user['password'])) {
        // If user exists, track attempts for lockout
        if ($user) {
            $lockLevel = (int)($user['lock_level'] ?? 0);
            $attempts = (int)($user['failed_login_attempts'] ?? 0);

            // Requirement:
            // - 3 consecutive fails => 60 seconds lock
            // - after 60 seconds, if wrong again => lock for 1 day
            if ($lockLevel === 1) {
                // temp lock was already used; next failure after it expires triggers 1-day lock
                $lockUntil = date('Y-m-d H:i:s', time() + 86400);
                $upd = $pdo->prepare('UPDATE users SET lock_level = 2, lock_until = :lock_until, failed_login_attempts = 0 WHERE id = :id');
                $upd->execute(['lock_until' => $lockUntil, 'id' => (int)$user['id']]);
                json_response([
                    'success' => false,
                    'message' => 'Account locked for 1 day due to repeated failed attempts.',
                    'lock' => ['until' => $lockUntil, 'seconds_remaining' => 86400, 'level' => 2]
                ], 429);
            }

            $attempts += 1;
            if ($attempts >= 3) {
                $lockUntil = date('Y-m-d H:i:s', time() + 60);
                $upd = $pdo->prepare('UPDATE users SET lock_level = 1, lock_until = :lock_until, failed_login_attempts = 0 WHERE id = :id');
                $upd->execute(['lock_until' => $lockUntil, 'id' => (int)$user['id']]);
                json_response([
                    'success' => false,
                    'message' => 'Too many attempts. Locked for 60 seconds.',
                    'lock' => ['until' => $lockUntil, 'seconds_remaining' => 60, 'level' => 1]
                ], 429);
            } else {
                $upd = $pdo->prepare('UPDATE users SET failed_login_attempts = :attempts WHERE id = :id');
                $upd->execute(['attempts' => $attempts, 'id' => (int)$user['id']]);
            }
        }

        json_response(['success' => false, 'message' => 'Invalid email/username or password'], 401);
    }

    // Successful login: reset lockout counters
    $reset = $pdo->prepare('UPDATE users SET failed_login_attempts = 0, lock_level = 0, lock_until = NULL WHERE id = :id');
    $reset->execute(['id' => (int)$user['id']]);

    // Check if user is banned
    if (!empty($user['banned_until'])) {
        $bannedUntil = strtotime($user['banned_until']);
        if ($bannedUntil && $bannedUntil > time()) {
            $remaining = $bannedUntil - time();
            json_response([
                'success' => false,
                'message' => 'Your account is banned. ' . ($user['ban_reason'] ? 'Reason: ' . $user['ban_reason'] : ''),
                'ban' => [
                    'until' => $user['banned_until'],
                    'seconds_remaining' => $remaining,
                    'reason' => $user['ban_reason']
                ]
            ], 403);
        } else {
            // Ban expired, restore account
            $pdo->prepare('UPDATE users SET status = "active", banned_until = NULL, ban_reason = NULL WHERE id = :id')
                ->execute(['id' => (int)$user['id']]);
        }
    }

    json_response([
        'success' => true,
        'user' => [
            'id' => (int)$user['id'],
            'username' => $user['username'] ?: $user['email'],
            'name' => $user['name'] ?: $user['email'],
            'email' => $user['email'],
            'avatar' => $user['avatar'] ?: 'assets/images/default-avatar.png',
            'accountType' => $user['account_type'] ?? 'student',
            'isAdmin' => (bool)$user['is_admin']
        ]
    ]);
} catch (Exception $e) {
    error_log('Login error: ' . $e->getMessage());
    json_response(['success' => false, 'message' => 'Server error: ' . $e->getMessage()], 500);
}
?>
