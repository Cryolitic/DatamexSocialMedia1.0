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
if (!$input || !isset($input['user_id'])) {
    json_response(['success' => false, 'message' => 'User ID is required'], 400);
}

$user_id = intval($input['user_id']);
$notification_id = isset($input['notification_id']) ? intval($input['notification_id']) : null;
$mark_all = isset($input['mark_all']) ? (bool)$input['mark_all'] : false;
$mark_as_read = isset($input['mark_as_read']) ? (bool)$input['mark_as_read'] : true;

try {
    $pdo = db();
    
    if ($mark_all) {
        // Mark all notifications as read/unread
        $stmt = $pdo->prepare('UPDATE notifications SET is_read = :read WHERE user_id = :uid');
        $stmt->execute([
            'read' => $mark_as_read ? 1 : 0,
            'uid' => $user_id
        ]);
        json_response([
            'success' => true,
            'message' => $mark_as_read ? 'All notifications marked as read' : 'All notifications marked as unread'
        ]);
    } else if ($notification_id) {
        // Mark single notification as read/unread
        $stmt = $pdo->prepare('UPDATE notifications SET is_read = :read WHERE id = :id AND user_id = :uid');
        $stmt->execute([
            'read' => $mark_as_read ? 1 : 0,
            'id' => $notification_id,
            'uid' => $user_id
        ]);
        json_response([
            'success' => true,
            'message' => $mark_as_read ? 'Notification marked as read' : 'Notification marked as unread'
        ]);
    } else {
        json_response(['success' => false, 'message' => 'Notification ID or mark_all is required'], 400);
    }
} catch (Exception $e) {
    json_response(['success' => false, 'message' => 'Server error'], 500);
}
?>
