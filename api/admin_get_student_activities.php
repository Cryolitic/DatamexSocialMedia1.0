<?php
require_once __DIR__ . '/config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

$admin_id = isset($_GET['admin_id']) ? intval($_GET['admin_id']) : 0;
$limit = isset($_GET['limit']) ? max(1, intval($_GET['limit'])) : 50;

if (!$admin_id) {
    json_response(['success' => false, 'message' => 'Admin ID required'], 400);
}

try {
    $pdo = db();
    
// Verify moderator (admin or staff/faculty)
$adminCheck = $pdo->prepare('SELECT id FROM users WHERE id = :id AND account_type IN ("admin", "faculty")');
    $adminCheck->execute(['id' => $admin_id]);
    if (!$adminCheck->fetch()) {
        json_response(['success' => false, 'message' => 'Unauthorized'], 403);
    }
    
    // Get recent student posts
    $postsStmt = $pdo->prepare("
        SELECT p.*, u.username, u.name, u.avatar, u.account_type, u.warnings, u.status
        FROM posts p
        JOIN users u ON p.user_id = u.id
        WHERE u.account_type = 'student' AND p.deleted_at IS NULL AND p.post_type = 'post'
        ORDER BY p.created_at DESC
        LIMIT :limit
    ");
    $postsStmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $postsStmt->execute();
    $posts = $postsStmt->fetchAll();
    
    // Get recent student comments
    $commentsStmt = $pdo->prepare("
        SELECT c.*, u.username, u.name, u.avatar, u.account_type, u.warnings, u.status, p.content AS post_content
        FROM comments c
        JOIN users u ON c.user_id = u.id
        JOIN posts p ON c.post_id = p.id
        WHERE u.account_type = 'student' AND p.deleted_at IS NULL
        ORDER BY c.created_at DESC
        LIMIT :limit
    ");
    $commentsStmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $commentsStmt->execute();
    $comments = $commentsStmt->fetchAll();
    
    // Format posts
    $formattedPosts = array_map(function($p) {
        $media = null;
        if (!empty($p['media_urls'])) {
            $decoded = json_decode($p['media_urls'], true);
            if (is_array($decoded)) {
                $media = $decoded;
            }
        }
        return [
            'id' => (int)$p['id'],
            'type' => 'post',
            'user_id' => (int)$p['user_id'],
            'username' => $p['username'],
            'name' => $p['name'],
            'avatar' => $p['avatar'] ?: 'assets/images/default-avatar.png',
            'content' => $p['content'],
            'media' => $media,
            'warnings' => (int)$p['warnings'],
            'status' => $p['status'],
            'timestamp' => $p['created_at']
        ];
    }, $posts);
    
    // Format comments
    $formattedComments = array_map(function($c) {
        return [
            'id' => (int)$c['id'],
            'type' => 'comment',
            'user_id' => (int)$c['user_id'],
            'username' => $c['username'],
            'name' => $c['name'],
            'avatar' => $c['avatar'] ?: 'assets/images/default-avatar.png',
            'content' => $c['content'],
            'post_id' => (int)$c['post_id'],
            'post_content' => mb_substr($c['post_content'], 0, 100) . (mb_strlen($c['post_content']) > 100 ? '...' : ''),
            'warnings' => (int)$c['warnings'],
            'status' => $c['status'],
            'timestamp' => $c['created_at']
        ];
    }, $comments);
    
    // Combine and sort by timestamp
    $activities = array_merge($formattedPosts, $formattedComments);
    usort($activities, function($a, $b) {
        return strtotime($b['timestamp']) - strtotime($a['timestamp']);
    });
    
    json_response([
        'success' => true,
        'activities' => array_slice($activities, 0, $limit)
    ]);
} catch (Exception $e) {
    json_response(['success' => false, 'message' => 'Server error'], 500);
}
?>
