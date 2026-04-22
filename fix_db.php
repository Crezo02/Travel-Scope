<?php
require_once 'config.php';

$messages = [];
$errors   = [];

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8",
        DB_USER, DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    // ── Fix 1: users table ──────────────────────────────────────────
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id            INT AUTO_INCREMENT PRIMARY KEY,
        username      VARCHAR(50)  NOT NULL UNIQUE,
        password_hash VARCHAR(255) NOT NULL,
        created_at    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
    )");
    $messages[] = "✅ <strong>users</strong> table is ready.";

    // ── Fix 2: Rebuild search_history table ─────────────────────────
    // We DROP and CREATE to ensure the columns are exactly what the code expects
    $pdo->exec("DROP TABLE IF EXISTS search_history");
    
    $pdo->exec("CREATE TABLE search_history (
        id             INT AUTO_INCREMENT PRIMARY KEY,
        user_id        INT          DEFAULT NULL,
        search_query   VARCHAR(255) NOT NULL,
        resolved_city  VARCHAR(255) NOT NULL,
        search_time    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
    )");
    
    $messages[] = "✅ <strong>search_history</strong> table has been REBUILT with correct columns!";

} catch (PDOException $e) {
    $errors[] = htmlspecialchars($e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Fix Database – TravelScope</title>
    <style>
        body { font-family: sans-serif; background: #0a0a1a; color: #e2e8f0;
               display:flex; align-items:center; justify-content:center; min-height:100vh; margin:0; }
        .card { background: rgba(30,41,59,.8); border:1px solid rgba(255,255,255,.1);
                backdrop-filter: blur(10px); border-radius:16px; padding:2rem 2.5rem; 
                max-width:480px; width:100%; text-align:center; box-shadow: 0 20px 50px rgba(0,0,0,0.5); }
        h1 { font-size:1.5rem; margin-bottom:1.5rem; background: linear-gradient(135deg, #a855f7, #ec4899);
             -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .msg { background:rgba(16,185,129,.1); border:1px solid rgba(16,185,129,.3);
               border-radius:8px; padding:.7rem 1rem; margin:.5rem 0; color:#6ee7b7; text-align:left; font-size:.9rem; }
        .err { background:rgba(239,68,68,.1); border:1px solid rgba(239,68,68,.3);
               border-radius:8px; padding:.7rem 1rem; margin:.5rem 0; color:#fca5a5; text-align:left; font-size:.9rem; }
        .btn { display:inline-block; margin-top:1.5rem; padding:.7rem 1.8rem;
               background:linear-gradient(135deg,#6366f1,#a855f7); color:#fff;
               text-decoration:none; border-radius:10px; font-weight:700; font-size:.9rem;
               transition: transform 0.2s; }
        .btn:hover { transform: scale(1.05); }
    </style>
</head>
<body>
<div class="card">
    <h1>🚀 Database Repair</h1>
    <?php foreach ($messages as $m): ?>
        <div class="msg"><?= $m ?></div>
    <?php endforeach; ?>
    <?php foreach ($errors as $e): ?>
        <div class="err">❌ <?= $e ?></div>
    <?php endforeach; ?>
    <?php if (empty($errors)): ?>
        <p style="font-size: 0.85rem; color: #94a3b8; margin-top: 1rem;">Database is now perfectly synced with your code.</p>
        <a href="index.html" class="btn">Start Exploring →</a>
    <?php endif; ?>
</div>
</body>
</html>
