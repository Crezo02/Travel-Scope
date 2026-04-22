<?php
require_once 'config.php';

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // ── search_history table ──────────────────────────────────────────
    $pdo->exec("CREATE TABLE IF NOT EXISTS search_history (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT DEFAULT NULL,
        search_query VARCHAR(255) NOT NULL,
        resolved_city VARCHAR(255) NOT NULL,
        search_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // ── Add user_id column if the table already existed without it ────
    $cols = $pdo->query("SHOW COLUMNS FROM search_history LIKE 'user_id'")->fetchAll();
    if (empty($cols)) {
        $pdo->exec("ALTER TABLE search_history ADD COLUMN user_id INT DEFAULT NULL AFTER id");
    }

    // ── users table ───────────────────────────────────────────────────
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) NOT NULL UNIQUE,
        password_hash VARCHAR(255) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    echo "<div style='font-family: sans-serif; text-align: center; margin-top: 50px; color: #e2e8f0; background:#0f172a; min-height:100vh; padding:2rem;'>";
    echo "<h1 style='color: #10b981;'>&#10003; Database Setup Complete!</h1>";
    echo "<p>Tables <strong>search_history</strong> and <strong>users</strong> are ready in <code>" . DB_NAME . "</code>.</p>";
    echo "<div style='margin-top:1.5rem;'>";
    echo "<a href='register.php' style='display:inline-block;padding:10px 24px;background:linear-gradient(135deg,#a855f7,#ec4899);color:white;text-decoration:none;border-radius:8px;margin:0 8px;'>Create Account</a>";
    echo "<a href='login.php'    style='display:inline-block;padding:10px 24px;background:linear-gradient(135deg,#6366f1,#06b6d4);color:white;text-decoration:none;border-radius:8px;margin:0 8px;'>Login</a>";
    echo "</div></div>";
} catch(PDOException $e) {
    echo "<div style='font-family: sans-serif; text-align: center; margin-top: 50px;'>";
    echo "<h1 style='color: #ef4444;'>Database Error</h1>";
    echo "<p>Connection failed: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p>Please make sure the database '" . DB_NAME . "' exists on your MySQL server.</p>";
    echo "</div>";
}
?>
