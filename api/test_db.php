<?php
/**
 * Database Connection Test Script
 * Access this file in browser: http://localhost/system%20capstone/api/test_db.php
 */
require_once __DIR__ . '/config.php';

header('Content-Type: text/html; charset=utf-8');

echo "<h2>Database Connection Test</h2>";

try {
    $pdo = db();
    echo "<p style='color: green;'>✓ Database connection successful!</p>";
    echo "<p>Database: " . DB_NAME . "</p>";
    echo "<p>Host: " . DB_HOST . "</p>";
    
    // Test query
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM users");
    $result = $stmt->fetch();
    echo "<p>Users in database: " . $result['count'] . "</p>";
    
    echo "<h3>Tables:</h3>";
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    echo "<ul>";
    foreach ($tables as $table) {
        echo "<li>$table</li>";
    }
    echo "</ul>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Error: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<h3>How to fix:</h3>";
    echo "<ol>";
    echo "<li>Open XAMPP Control Panel</li>";
    echo "<li>Click 'Start' button next to MySQL</li>";
    echo "<li>Wait until MySQL shows 'Running' status</li>";
    echo "<li>Refresh this page</li>";
    echo "</ol>";
}
?>
