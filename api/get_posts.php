<?php
require_once __DIR__ . '/config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = isset($_GET['limit']) ? max(1, intval($_GET['limit'])) : 20;
$offset = ($page - 1) * $limit;
$user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
$profile_user_id = isset($_GET['profile_user_id']) ? intval($_GET['profile_user_id']) : 0; // For viewing someone's profile
$announcements_only = isset($_GET['announcements_only']) && $_GET['announcements_only'] === '1';

try {
    $pdo = db();
    $safeAvatar = function ($v) {
        $raw = trim((string)($v ?? ''));
        $l = strtolower($raw);
        if ($raw === '' || $l === 'null' || $l === 'undefined' || $l === 'false' || $l === 'nan') {
            return 'assets/images/default-avatar.png';
        }
        return $raw;
    };
    
    // Check if user is admin
    $isAdmin = false;
    if ($user_id > 0) {
        $adminCheck = $pdo->prepare('SELECT id FROM users WHERE id = :id AND account_type = "admin"');
        $adminCheck->execute(['id' => $user_id]);
        $isAdmin = (bool)$adminCheck->fetch();
    }

    // Get announcements first (always at top)
    $announcementsStmt = $pdo->prepare("
        SELECT 
            p.*,
            u.username,
            u.name,
            u.avatar,
            u.account_type,
            COALESCE(lc.like_count, 0) AS likes,
            COALESCE(cc.comment_count, 0) AS comment_count,
            COALESCE(sc.share_count, 0) AS share_count,
            IF(ul.user_id IS NULL, 0, 1) AS is_liked,
            IF(f.follower_id IS NULL, 0, 1) AS is_followed
        FROM posts p
        JOIN users u ON p.user_id = u.id
        LEFT JOIN (
            SELECT post_id, COUNT(*) AS like_count FROM likes GROUP BY post_id
        ) lc ON lc.post_id = p.id
        LEFT JOIN (
            SELECT post_id, COUNT(*) AS comment_count FROM comments GROUP BY post_id
        ) cc ON cc.post_id = p.id
        LEFT JOIN (
            SELECT reference_post AS post_id, COUNT(*) AS share_count FROM posts WHERE reference_post IS NOT NULL AND deleted_at IS NULL GROUP BY reference_post
        ) sc ON sc.post_id = p.id
        LEFT JOIN likes ul ON ul.post_id = p.id AND ul.user_id = :uid
        LEFT JOIN follows f ON f.follower_id = :uid2 AND f.followed_id = p.user_id
        WHERE
            p.deleted_at IS NULL
            AND p.post_type = 'announcement'
            AND u.status != 'banned'
            AND (u.banned_until IS NULL OR u.banned_until < NOW())
            AND (p.announcement_status = 'approved' OR p.announcement_status IS NULL OR u.account_type = 'admin')
        ORDER BY p.created_at DESC
        LIMIT 50
    ");
    $announcementsStmt->bindValue(':uid', $user_id, PDO::PARAM_INT);
    $announcementsStmt->bindValue(':uid2', $user_id, PDO::PARAM_INT);
    $announcementsStmt->execute();
    $announcements = $announcementsStmt->fetchAll();
    
    // When announcements_only: return only announcements
    if ($announcements_only) {
        $postIds = array_column($announcements, 'id');
        $commentsByPost = [];
        if ($postIds) {
            $in = implode(',', array_fill(0, count($postIds), '?'));
            $cStmt = $pdo->prepare("SELECT c.*, u.username, u.name, u.avatar FROM comments c JOIN users u ON u.id = c.user_id WHERE c.post_id IN ($in) ORDER BY c.created_at ASC");
            $cStmt->execute($postIds);
            while ($row = $cStmt->fetch()) {
                $commentsByPost[$row['post_id']][] = ['id'=>(int)$row['id'],'user_id'=>(int)$row['user_id'],'name'=>$row['name']?:$row['username'],'avatar'=>$safeAvatar($row['avatar']),'text'=>$row['content'],'timestamp'=>$row['created_at']];
            }
        }
        $responsePosts = array_map(function ($p) use ($commentsByPost, $safeAvatar) {
            $media = null;
            if (!empty($p['media_urls'])) {
                $decoded = json_decode($p['media_urls'], true);
                if (is_array($decoded)) {
                    $media = array_values(array_filter(array_map(function($item) {
                        if (is_array($item) && !empty($item['url'])) {
                            return $item['url'];
                        }
                        if (is_string($item) && $item !== '') {
                            return $item;
                        }
                        return null;
                    }, $decoded
                    )));
                }
            }
            return ['id'=>(int)$p['id'],'user_id'=>(int)$p['user_id'],'username'=>$p['username']?:$p['user_id'],'name'=>$p['name']?:$p['username'],'avatar'=>$safeAvatar($p['avatar']),'content'=>$p['content'],'media'=>$media,'media_type'=>$p['media_type'],'likes'=>(int)$p['likes'],'comments'=>$commentsByPost[$p['id']]??[],'shares'=>(int)$p['share_count'],'timestamp'=>$p['created_at'],'isLiked'=>(bool)$p['is_liked'],'reference_post'=>$p['reference_post'],'privacy'=>$p['privacy'],'post_type'=>$p['post_type']??'post','account_type'=>$p['account_type']??'student'];
        }, $announcements);
        json_response(['success' => true, 'posts' => $responsePosts]);
        exit;
    }
    
    // Get regular posts
    // If admin, show all posts (especially student posts) regardless of privacy/follow status
    if ($isAdmin) {
        $stmt = $pdo->prepare("
            SELECT 
                p.*,
                u.username,
                u.name,
                u.avatar,
                u.account_type,
                COALESCE(lc.like_count, 0) AS likes,
                COALESCE(cc.comment_count, 0) AS comment_count,
                COALESCE(sc.share_count, 0) AS share_count,
                IF(ul.user_id IS NULL, 0, 1) AS is_liked,
                IF(f.follower_id IS NULL, 0, 1) AS is_followed
            FROM posts p
            JOIN users u ON p.user_id = u.id
            LEFT JOIN (
                SELECT post_id, COUNT(*) AS like_count FROM likes GROUP BY post_id
            ) lc ON lc.post_id = p.id
            LEFT JOIN (
                SELECT post_id, COUNT(*) AS comment_count FROM comments GROUP BY post_id
            ) cc ON cc.post_id = p.id
            LEFT JOIN (
                SELECT reference_post AS post_id, COUNT(*) AS share_count FROM posts WHERE reference_post IS NOT NULL AND deleted_at IS NULL GROUP BY reference_post
            ) sc ON sc.post_id = p.id
            LEFT JOIN likes ul ON ul.post_id = p.id AND ul.user_id = :uid
            LEFT JOIN follows f ON f.follower_id = :uid2 AND f.followed_id = p.user_id
            WHERE
                p.deleted_at IS NULL
                AND p.post_type = 'post'
                AND u.status != 'banned'
                AND (u.banned_until IS NULL OR u.banned_until < NOW())
            ORDER BY p.created_at DESC
            LIMIT :limit OFFSET :offset
        ");
        $stmt->bindValue(':uid', $user_id, PDO::PARAM_INT);
        $stmt->bindValue(':uid2', $user_id, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    } else {
        // Regular users - follow privacy settings
        // If viewing someone's profile, show all their posts (for students viewing other students)
        if ($profile_user_id > 0 && $profile_user_id != $user_id) {
            // Check if profile owner is a student
            $profileOwnerCheck = $pdo->prepare('SELECT account_type FROM users WHERE id = :id');
            $profileOwnerCheck->execute(['id' => $profile_user_id]);
            $profileOwner = $profileOwnerCheck->fetch();
            
            if ($profileOwner && $profileOwner['account_type'] === 'student') {
                // Show all posts from this student when viewing their profile
                $stmt = $pdo->prepare("
                    SELECT 
                        p.*,
                        u.username,
                        u.name,
                        u.avatar,
                        u.account_type,
                        COALESCE(lc.like_count, 0) AS likes,
                        COALESCE(cc.comment_count, 0) AS comment_count,
                        COALESCE(sc.share_count, 0) AS share_count,
                        IF(ul.user_id IS NULL, 0, 1) AS is_liked,
                        IF(f.follower_id IS NULL, 0, 1) AS is_followed
                    FROM posts p
                    JOIN users u ON p.user_id = u.id
                    LEFT JOIN (
                        SELECT post_id, COUNT(*) AS like_count FROM likes GROUP BY post_id
                    ) lc ON lc.post_id = p.id
                    LEFT JOIN (
                        SELECT post_id, COUNT(*) AS comment_count FROM comments GROUP BY post_id
                    ) cc ON cc.post_id = p.id
                    LEFT JOIN (
                        SELECT reference_post AS post_id, COUNT(*) AS share_count FROM posts WHERE reference_post IS NOT NULL AND deleted_at IS NULL GROUP BY reference_post
                    ) sc ON sc.post_id = p.id
                    LEFT JOIN likes ul ON ul.post_id = p.id AND ul.user_id = :uid
                    LEFT JOIN follows f ON f.follower_id = :uid2 AND f.followed_id = p.user_id
                    WHERE
                        p.deleted_at IS NULL
                        AND p.post_type = 'post'
                        AND p.user_id = :profile_uid
                        AND u.status != 'banned'
                        AND (u.banned_until IS NULL OR u.banned_until < NOW())
                        AND (
                        (p.user_id = :uid3)
                        OR (p.privacy = 'public')
                        OR (p.privacy = 'followers' AND f.follower_id IS NOT NULL)
                        )
                    ORDER BY p.created_at DESC
                    LIMIT :limit OFFSET :offset
                ");
                $stmt->bindValue(':uid', $user_id, PDO::PARAM_INT);
                $stmt->bindValue(':uid2', $user_id, PDO::PARAM_INT);
                $stmt->bindValue(':uid3', $user_id, PDO::PARAM_INT);
                $stmt->bindValue(':profile_uid', $profile_user_id, PDO::PARAM_INT);
                $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
                $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            } else {
                // For non-students, follow privacy settings
                $stmt = $pdo->prepare("
                    SELECT 
                        p.*,
                        u.username,
                        u.name,
                        u.avatar,
                        u.account_type,
                        COALESCE(lc.like_count, 0) AS likes,
                        COALESCE(cc.comment_count, 0) AS comment_count,
                        COALESCE(sc.share_count, 0) AS share_count,
                        IF(ul.user_id IS NULL, 0, 1) AS is_liked,
                        IF(f.follower_id IS NULL, 0, 1) AS is_followed
                    FROM posts p
                    JOIN users u ON p.user_id = u.id
                    LEFT JOIN (
                        SELECT post_id, COUNT(*) AS like_count FROM likes GROUP BY post_id
                    ) lc ON lc.post_id = p.id
                    LEFT JOIN (
                        SELECT post_id, COUNT(*) AS comment_count FROM comments GROUP BY post_id
                    ) cc ON cc.post_id = p.id
                    LEFT JOIN (
                        SELECT reference_post AS post_id, COUNT(*) AS share_count FROM posts WHERE reference_post IS NOT NULL AND deleted_at IS NULL GROUP BY reference_post
                    ) sc ON sc.post_id = p.id
                    LEFT JOIN likes ul ON ul.post_id = p.id AND ul.user_id = :uid
                    LEFT JOIN follows f ON f.follower_id = :uid2 AND f.followed_id = p.user_id
                    WHERE
                        p.deleted_at IS NULL
                        AND p.post_type = 'post'
                        AND p.user_id = :profile_uid
                        AND u.status != 'banned'
                        AND (u.banned_until IS NULL OR u.banned_until < NOW())
                        AND (
                        (p.user_id = :uid3)
                        OR (p.privacy = 'public')
                        OR (p.privacy = 'followers' AND f.follower_id IS NOT NULL)
                        OR (p.privacy = 'friends_of_friends' AND (f.follower_id IS NOT NULL OR EXISTS (SELECT 1 FROM follows f0 INNER JOIN follows f2 ON f2.followed_id = f0.follower_id WHERE f0.followed_id = p.user_id AND f2.follower_id = :uid_fof)))
                        )
                    ORDER BY p.created_at DESC
                    LIMIT :limit OFFSET :offset
                ");
                $stmt->bindValue(':uid', $user_id, PDO::PARAM_INT);
                $stmt->bindValue(':uid2', $user_id, PDO::PARAM_INT);
                $stmt->bindValue(':uid3', $user_id, PDO::PARAM_INT);
                $stmt->bindValue(':uid_fof', $user_id, PDO::PARAM_INT);
                $stmt->bindValue(':profile_uid', $profile_user_id, PDO::PARAM_INT);
                $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
                $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            }
        } else {
            // Regular feed view
            $stmt = $pdo->prepare("
                SELECT 
                    p.*,
                    u.username,
                    u.name,
                    u.avatar,
                    u.account_type,
                    COALESCE(lc.like_count, 0) AS likes,
                    COALESCE(cc.comment_count, 0) AS comment_count,
                    COALESCE(sc.share_count, 0) AS share_count,
                    IF(ul.user_id IS NULL, 0, 1) AS is_liked,
                    IF(f.follower_id IS NULL, 0, 1) AS is_followed
                FROM posts p
                JOIN users u ON p.user_id = u.id
                LEFT JOIN (
                    SELECT post_id, COUNT(*) AS like_count FROM likes GROUP BY post_id
                ) lc ON lc.post_id = p.id
                LEFT JOIN (
                    SELECT post_id, COUNT(*) AS comment_count FROM comments GROUP BY post_id
                ) cc ON cc.post_id = p.id
                LEFT JOIN (
                    SELECT reference_post AS post_id, COUNT(*) AS share_count FROM posts WHERE reference_post IS NOT NULL AND deleted_at IS NULL GROUP BY reference_post
                ) sc ON sc.post_id = p.id
                LEFT JOIN likes ul ON ul.post_id = p.id AND ul.user_id = :uid
                LEFT JOIN follows f ON f.follower_id = :uid2 AND f.followed_id = p.user_id
                WHERE
                    p.deleted_at IS NULL
                    AND p.post_type = 'post'
                    AND u.status != 'banned'
                    AND (u.banned_until IS NULL OR u.banned_until < NOW())
                    AND (
                    (p.user_id = :uid3)
                    OR (p.privacy = 'public')
                    OR (p.privacy = 'followers' AND f.follower_id IS NOT NULL)
                    OR (p.privacy = 'friends_of_friends' AND (f.follower_id IS NOT NULL OR EXISTS (SELECT 1 FROM follows f0 INNER JOIN follows f2 ON f2.followed_id = f0.follower_id WHERE f0.followed_id = p.user_id AND f2.follower_id = :uid_fof)))
                    )
                ORDER BY f.follower_id IS NOT NULL DESC, p.created_at DESC
                LIMIT :limit OFFSET :offset
            ");
            $stmt->bindValue(':uid', $user_id, PDO::PARAM_INT);
            $stmt->bindValue(':uid2', $user_id, PDO::PARAM_INT);
            $stmt->bindValue(':uid3', $user_id, PDO::PARAM_INT);
            $stmt->bindValue(':uid_fof', $user_id, PDO::PARAM_INT);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        }
    }
    $stmt->execute();
    $posts = $stmt->fetchAll();
    
    // Combine announcements and posts (announcements first)
    $allPosts = array_merge($announcements, $posts);

    // Load comments per post
    $postIds = array_column($allPosts, 'id');
    $commentsByPost = [];
    if ($postIds) {
        $in = implode(',', array_fill(0, count($postIds), '?'));
        $cStmt = $pdo->prepare("
            SELECT c.*, u.username, u.name, u.avatar
            FROM comments c
            JOIN users u ON u.id = c.user_id
            WHERE c.post_id IN ($in)
            ORDER BY c.created_at ASC
        ");
        $cStmt->execute($postIds);
        while ($row = $cStmt->fetch()) {
            $commentsByPost[$row['post_id']][] = [
                'id' => (int)$row['id'],
                'user_id' => (int)$row['user_id'],
                'name' => $row['name'] ?: $row['username'],
                'avatar' => $safeAvatar($row['avatar']),
                'text' => $row['content'],
                'timestamp' => $row['created_at'],
            ];
        }
    }

    $extractMedia = function ($mediaUrls) {
        if (empty($mediaUrls)) return null;
        $decoded = json_decode($mediaUrls, true);
        if (!is_array($decoded)) return null;
        return array_values(array_filter(array_map(function($item) {
            if (is_array($item) && !empty($item['url'])) return $item['url'];
            if (is_string($item) && $item !== '') return $item;
            return null;
        }, $decoded)));
    };

    // Resolve shared-post chain to the root/original post for display.
    $referenceRowCache = [];
    $resolvedReferenceCache = [];
    $resolveReference = function ($startId) use (&$resolveReference, &$referenceRowCache, &$resolvedReferenceCache, $pdo, $safeAvatar, $extractMedia) {
        $startId = (int)$startId;
        if ($startId <= 0) return null;
        if (array_key_exists($startId, $resolvedReferenceCache)) {
            return $resolvedReferenceCache[$startId];
        }

        $currentId = $startId;
        $visited = [];
        $last = null;

        while ($currentId > 0 && !isset($visited[$currentId])) {
            $visited[$currentId] = true;
            if (!array_key_exists($currentId, $referenceRowCache)) {
                $refStmt = $pdo->prepare("
                    SELECT p.*, u.username, u.name, u.avatar, u.account_type
                    FROM posts p
                    JOIN users u ON u.id = p.user_id
                    WHERE p.id = :id AND p.deleted_at IS NULL
                ");
                $refStmt->execute(['id' => $currentId]);
                $referenceRowCache[$currentId] = $refStmt->fetch() ?: null;
            }

            $row = $referenceRowCache[$currentId];
            if (!$row) break;

            $last = [
                'id' => (int)$row['id'],
                'user_id' => (int)$row['user_id'],
                'username' => $row['username'] ?: $row['user_id'],
                'name' => $row['name'] ?: $row['username'],
                'avatar' => $safeAvatar($row['avatar']),
                'content' => $row['content'],
                'media' => $extractMedia($row['media_urls']),
                'media_type' => $row['media_type'],
                'timestamp' => $row['created_at'],
                'post_type' => $row['post_type'] ?? 'post',
                'account_type' => $row['account_type'] ?? 'student'
            ];

            $nextId = (int)($row['reference_post'] ?? 0);
            if ($nextId <= 0) break;
            $currentId = $nextId;
        }

        $resolvedReferenceCache[$startId] = $last;
        return $last;
    };

    // Map response
    $responsePosts = array_map(function ($p) use ($commentsByPost, $safeAvatar, $resolveReference) {
        $media = null;
            if (!empty($p['media_urls'])) {
                $decoded = json_decode($p['media_urls'], true);
                if (is_array($decoded)) {
                    $media = array_values(array_filter(array_map(function($item) {
                        if (is_array($item) && !empty($item['url'])) {
                            return $item['url'];
                        }
                        if (is_string($item) && $item !== '') {
                            return $item;
                        }
                        return null;
                    }, $decoded
                    )));
                }
            }
        return [
            'id' => (int)$p['id'],
            'user_id' => (int)$p['user_id'],
            'username' => $p['username'] ?: $p['user_id'],
            'name' => $p['name'] ?: $p['username'],
            'avatar' => $safeAvatar($p['avatar']),
            'content' => $p['content'],
            'media' => $media,
            'media_type' => $p['media_type'],
            'likes' => (int)$p['likes'],
            'comments' => $commentsByPost[$p['id']] ?? [],
            'shares' => (int)$p['share_count'],
            'timestamp' => $p['created_at'],
            'isLiked' => (bool)$p['is_liked'],
            'reference_post' => $p['reference_post'],
            'reference' => !empty($p['reference_post']) ? $resolveReference((int)$p['reference_post']) : null,
            'privacy' => $p['privacy'],
            'post_type' => $p['post_type'] ?? 'post',
            'account_type' => $p['account_type'] ?? 'student'
        ];
    }, $allPosts);

    json_response(['success' => true, 'posts' => $responsePosts]);
} catch (Exception $e) {
    json_response(['success' => false, 'message' => 'Server error'], 500);
}
?>
