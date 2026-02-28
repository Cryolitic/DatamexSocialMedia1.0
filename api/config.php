<?php
/**
 * Database configuration for XAMPP (MySQL) with auto migration.
 * Default credentials: host=localhost, user=root, password=""
 */

const DB_HOST = 'localhost';
const DB_NAME = 'socmec_datamex';
const DB_USER = 'root';
const DB_PASS = '';
const pass = 'ragj sdfu yktt xxwi';
const cloud_name = 'dmkoc4lis';
const a_key = '144113122467983';
const sec = '3u7sjMGJ3Mv9pVJOmez_verPJOY';
 
function db(): PDO {
    static $pdo = null;
    if ($pdo) {
        return $pdo;
    }

    try {
        $dsn = 'mysql:host=' . DB_HOST . ';charset=utf8mb4';
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_TIMEOUT => 5, // 5 second timeout
        ]);

        // Create database if not exists
        $pdo->exec('CREATE DATABASE IF NOT EXISTS `' . DB_NAME . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        $pdo->exec('USE `' . DB_NAME . '`');

        migrate($pdo);
        return $pdo;
    } catch (PDOException $e) {
        // Check if it's a connection error
        if (strpos($e->getMessage(), '2002') !== false || strpos($e->getMessage(), 'refused') !== false) {
            throw new Exception('MySQL is not running. Please start MySQL in XAMPP Control Panel.');
        }
        throw $e;
    }
}

function migrate(PDO $pdo): void {
    // Users table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) UNIQUE,
            email VARCHAR(100) UNIQUE NOT NULL,
            password VARCHAR(255) NOT NULL,
            name VARCHAR(100),
            bio TEXT,
            cover_photo VARCHAR(255),
            avatar VARCHAR(255),
            account_type ENUM('student','faculty','admin') DEFAULT 'student',
            is_admin TINYINT(1) DEFAULT 0,
            status ENUM('active','suspended','banned') DEFAULT 'active',
            warnings INT DEFAULT 0,
            warning_reasons TEXT,
            failed_login_attempts INT DEFAULT 0,
            lock_level TINYINT(1) DEFAULT 0,
            lock_until DATETIME NULL,
            banned_until DATETIME NULL,
            ban_reason TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    // Ensure new columns exist when table was created previously
    try {
        $pdo->exec("ALTER TABLE users ADD COLUMN account_type ENUM('student','faculty','admin') DEFAULT 'student'");
    } catch (PDOException $e) {
        // Column already exists, ignore
    }
    try {
        $pdo->exec("ALTER TABLE users ADD COLUMN status ENUM('active','suspended','banned') DEFAULT 'active'");
    } catch (PDOException $e) {}
    try {
        $pdo->exec("ALTER TABLE users ADD COLUMN warnings INT DEFAULT 0");
    } catch (PDOException $e) {}
    try {
        $pdo->exec("ALTER TABLE users ADD COLUMN warning_reasons TEXT");
    } catch (PDOException $e) {}
    try {
        $pdo->exec("ALTER TABLE users ADD COLUMN failed_login_attempts INT DEFAULT 0");
    } catch (PDOException $e) {}
    try {
        $pdo->exec("ALTER TABLE users ADD COLUMN lock_level TINYINT(1) DEFAULT 0");
    } catch (PDOException $e) {}
    try {
        $pdo->exec("ALTER TABLE users ADD COLUMN lock_until DATETIME NULL");
    } catch (PDOException $e) {}
    try {
        $pdo->exec("ALTER TABLE users ADD COLUMN banned_until DATETIME NULL");
    } catch (PDOException $e) {}
    try {
        $pdo->exec("ALTER TABLE notifications MODIFY type ENUM('like','comment','share','admin_post','admin_comment','admin_share','warning','follow','post_deleted','story','story_like') NOT NULL");
    } catch (PDOException $e) {}
    try {
        $pdo->exec("ALTER TABLE notifications ADD COLUMN post_content_snapshot TEXT NULL");
    } catch (PDOException $e) {}
    try {
        $pdo->exec("ALTER TABLE users ADD COLUMN ban_reason TEXT");
    } catch (PDOException $e) {}
    try {
        $pdo->exec("ALTER TABLE users ADD COLUMN cover_photo VARCHAR(255)");
    } catch (PDOException $e) {}

    // Follows table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS follows (
            follower_id INT NOT NULL,
            followed_id INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (follower_id, followed_id),
            FOREIGN KEY (follower_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (followed_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // Posts table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS posts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            content TEXT,
            media_type VARCHAR(20),
            media_urls TEXT NULL,
            privacy ENUM('only_me','followers','friends_of_friends','public','private') DEFAULT 'public',
            post_type ENUM('post','announcement') DEFAULT 'post',
            announcement_status ENUM('pending','approved') NULL,
            reference_post INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL,
            deleted_at TIMESTAMP NULL,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (reference_post) REFERENCES posts(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    try {
        $pdo->exec("ALTER TABLE posts ADD COLUMN updated_at TIMESTAMP NULL");
    } catch (PDOException $e) {}
    try {
        $pdo->exec("ALTER TABLE posts ADD COLUMN deleted_at TIMESTAMP NULL");
    } catch (PDOException $e) {}
    try {
        $pdo->exec("ALTER TABLE posts ADD COLUMN media_urls TEXT NULL");
    } catch (PDOException $e) {}
    try {
        $pdo->exec("ALTER TABLE posts DROP COLUMN media_url");
    } catch (PDOException $e) {}
    try {
        $pdo->exec("ALTER TABLE posts ADD COLUMN post_type ENUM('post','announcement') DEFAULT 'post'");
    } catch (PDOException $e) {}
    try {
        $pdo->exec("ALTER TABLE posts ADD COLUMN announcement_status ENUM('pending','approved') NULL");
    } catch (PDOException $e) {}
    try {
        $pdo->exec("ALTER TABLE posts MODIFY COLUMN privacy ENUM('only_me','followers','friends_of_friends','public','private') DEFAULT 'public'");
    } catch (PDOException $e) {}
    // Migrate: existing announcements and 'private' -> 'only_me'
    try {
        $pdo->exec("UPDATE posts SET announcement_status = 'approved' WHERE post_type = 'announcement' AND (announcement_status IS NULL OR announcement_status = '')");
        $pdo->exec("UPDATE posts SET privacy = 'only_me' WHERE privacy = 'private'");
    } catch (PDOException $e) {}

    // Comments table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS comments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            post_id INT NOT NULL,
            user_id INT NOT NULL,
            content TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // Likes table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS likes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            post_id INT NOT NULL,
            user_id INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_like (post_id, user_id),
            FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // Notifications table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS notifications (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            from_user_id INT NULL,
            post_id INT NULL,
            type ENUM('like','comment','share','admin_post','admin_comment','admin_share','warning') NOT NULL,
            message VARCHAR(255) NOT NULL,
            is_read TINYINT(1) DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (from_user_id) REFERENCES users(id) ON DELETE SET NULL,
            FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // Messages table (basic)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS messages (
            id INT AUTO_INCREMENT PRIMARY KEY,
            sender_id INT NOT NULL,
            receiver_id INT NOT NULL,
            content TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // Password resets table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS password_resets (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            token VARCHAR(64) NOT NULL,
            expires_at DATETIME NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_token (token),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // Email verification codes (for registration; avoid redundant email if already used)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS email_verification (
            id INT AUTO_INCREMENT PRIMARY KEY,
            email VARCHAR(100) NOT NULL,
            code VARCHAR(10) NOT NULL,
            expires_at DATETIME NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_email (email),
            INDEX idx_expires (expires_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // Reports table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS reports (
            id INT AUTO_INCREMENT PRIMARY KEY,
            post_id INT NOT NULL,
            reporter_id INT NOT NULL,
            reason TEXT,
            status ENUM('pending','resolved','dismissed') DEFAULT 'pending',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            resolved_at TIMESTAMP NULL,
            FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
            FOREIGN KEY (reporter_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // Admin logs table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS admin_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            admin_id INT NOT NULL,
            action VARCHAR(100),
            details TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // Stories (MyDay) - 24h photo/video
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS stories (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            media_type ENUM('image','video') NOT NULL,
            media_url VARCHAR(255) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_stories_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // Notes (Facebook-style) - content only, max 60 chars
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS notes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            title VARCHAR(255) NULL,
            content VARCHAR(60) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    try {
        $pdo->exec("ALTER TABLE notes MODIFY title VARCHAR(255) NULL");
    } catch (PDOException $e) {}

    // Story views - who viewed which story
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS story_views (
            id INT AUTO_INCREMENT PRIMARY KEY,
            story_id INT NOT NULL,
            viewer_id INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_view (story_id, viewer_id),
            FOREIGN KEY (story_id) REFERENCES stories(id) ON DELETE CASCADE,
            FOREIGN KEY (viewer_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // Story likes
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS story_likes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            story_id INT NOT NULL,
            user_id INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_story_like (story_id, user_id),
            FOREIGN KEY (story_id) REFERENCES stories(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // Note likes
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS note_likes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            note_id INT NOT NULL,
            user_id INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_note_like (note_id, user_id),
            FOREIGN KEY (note_id) REFERENCES notes(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // Seed default admin if missing
    try {
        $adminCheck = $pdo->query("SELECT id FROM users WHERE email = 'admin@localhost' LIMIT 1")->fetch();
        if (!$adminCheck) {
            $hash = password_hash('admin123', PASSWORD_BCRYPT);
            $seed = $pdo->prepare('
                INSERT INTO users (username, email, password, name, avatar, account_type, is_admin, status)
                VALUES (:username, :email, :password, :name, :avatar, :account_type, 1, "active")
            ');
            $seed->execute([
                'username' => 'admin',
                'email' => 'admin@localhost',
                'password' => $hash,
                'name' => 'Administrator',
                'avatar' => 'assets/images/default-avatar.png',
                'account_type' => 'admin'
            ]);
        }
    } catch (PDOException $e) {
        // Ignore if admin already exists
    }
}

function json_response(array $data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

/**
 * Convenience helper to store an admin action in admin_logs.
 */
function log_admin_action(PDO $pdo, int $adminId, string $action, string $details = ''): void {
    $stmt = $pdo->prepare('
        INSERT INTO admin_logs (admin_id, action, details)
        VALUES (:admin_id, :action, :details)
    ');
    $stmt->execute([
        'admin_id' => $adminId,
        'action' => $action,
        'details' => $details
    ]);
}

// Cloudinary function
function cloudinary_upload_api(): \Cloudinary\Api\Upload\UploadApi {
    static $configured = false;

    if (!$configured) {
        \Cloudinary\Configuration\Configuration::instance([
            'cloud' => [
                'cloud_name' => cloud_name,
                'api_key' => a_key,
                'api_secret' => sec,
            ],
            'url' => ['secure' => true]
        ]);
        $configured = true;
    }

    return new \Cloudinary\Api\Upload\UploadApi();
}

// default avatar
const defaultAvatar = 'https://res.cloudinary.com/dmkoc4lis/image/upload/v1772225107/default-avatar_ylfaff.png';
?>
