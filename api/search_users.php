<?php
require_once __DIR__ . '/config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

$q = isset($_GET['q']) ? trim($_GET['q']) : '';
$viewerId = isset($_GET['viewer_id']) ? intval($_GET['viewer_id']) : 0;

if ($q === '' || strlen($q) < 2) {
    json_response(['success' => true, 'users' => []]);
}

try {
    $pdo = db();

    // Basic search: username, name, email
    $like = '%' . $q . '%';
    $stmt = $pdo->prepare("
        SELECT u.id, u.username, u.name, u.email, u.avatar,
               IF(f.follower_id IS NULL, 0, 1) AS is_followed
        FROM users u
        LEFT JOIN follows f ON f.follower_id = :viewer AND f.followed_id = u.id
        WHERE (u.username LIKE :q OR u.name LIKE :q OR u.email LIKE :q)
        ORDER BY (u.name LIKE :qExact) DESC, u.created_at DESC
        LIMIT 10
    ");
    $stmt->execute([
        'viewer' => $viewerId,
        'q' => $like,
        'qExact' => $q . '%',
    ]);
    $rows = $stmt->fetchAll();

    $users = array_map(function ($u) {
        return [
            'id' => (int)$u['id'],
            'username' => $u['username'] ?: $u['email'],
            'name' => $u['name'] ?: ($u['username'] ?: $u['email']),
            'email' => $u['email'],
            'avatar' => $u['avatar'] ?: 'assets/images/default-avatar.png',
            'isFollowed' => (bool)$u['is_followed']
        ];
    }, $rows);

    json_response(['success' => true, 'users' => $users]);
} catch (Exception $e) {
    json_response(['success' => false, 'message' => 'Server error'], 500);
}

