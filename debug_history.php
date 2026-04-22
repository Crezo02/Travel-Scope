<?php
session_start();
require_once 'config.php';

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Debug – TravelScope</title>
<style>
  body { font-family: sans-serif; background:#0f172a; color:#e2e8f0; padding:2rem; }
  h2   { color:#a855f7; margin-top:2rem; }
  .ok  { color:#10b981; } .warn { color:#f59e0b; } .err { color:#ef4444; }
  pre  { background:#1e293b; padding:1rem; border-radius:8px; overflow-x:auto; font-size:.85rem; }
  table{ border-collapse:collapse; width:100%; margin-top:.5rem; }
  th,td{ border:1px solid #334155; padding:.4rem .8rem; text-align:left; font-size:.85rem; }
  th   { background:#1e293b; color:#94a3b8; }
</style>
</head>
<body>
<h1>🔍 TravelScope – Database Debug</h1>

<!-- ── 1. Session ───────────────────────────────────────────── -->
<h2>1. PHP Session</h2>
<?php if (isset($_SESSION['user_id'])): ?>
  <p class="ok">✅ Logged in as: <strong><?= htmlspecialchars($_SESSION['username'] ?? 'unknown') ?></strong>
     (user_id = <?= (int)$_SESSION['user_id'] ?>)</p>
<?php else: ?>
  <p class="err">❌ NOT logged in – session has no user_id.<br>
     Go to <a href="login.php" style="color:#a855f7">login.php</a> first, then come back here.</p>
<?php endif; ?>

<?php
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8",
        DB_USER, DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    // ── 2. Table structure ────────────────────────────────────
    echo "<h2>2. search_history Table Columns</h2>";
    $cols = $pdo->query("SHOW COLUMNS FROM search_history")->fetchAll(PDO::FETCH_ASSOC);
    if ($cols) {
        echo "<table><tr><th>Field</th><th>Type</th><th>Null</th><th>Default</th></tr>";
        foreach ($cols as $c) {
            echo "<tr><td>{$c['Field']}</td><td>{$c['Type']}</td><td>{$c['Null']}</td><td>{$c['Default']}</td></tr>";
        }
        echo "</table>";
    } else {
        echo "<p class='err'>❌ Table 'search_history' doesn't exist!</p>";
    }

    // ── 3. Test INSERT ────────────────────────────────────────
    echo "<h2>3. Test INSERT into search_history</h2>";
    $userId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
    $stmt = $pdo->prepare(
        "INSERT INTO search_history (user_id, search_query, resolved_city) VALUES (:uid, :query, :city)"
    );
    $stmt->execute([':uid' => $userId, ':query' => 'debug-test', ':city' => 'DebugCity']);
    $insertId = $pdo->lastInsertId();
    echo "<p class='ok'>✅ INSERT succeeded! Row ID = $insertId (user_id = " . ($userId ?? 'NULL') . ")</p>";

    // Clean up the test row
    $pdo->prepare("DELETE FROM search_history WHERE id = ?")->execute([$insertId]);
    echo "<p class='warn'>🧹 Test row deleted.</p>";

    // ── 4. Existing rows for this user ────────────────────────
    echo "<h2>4. Existing search_history rows (last 10)</h2>";
    $rows = $pdo->query("SELECT * FROM search_history ORDER BY id DESC LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
    if ($rows) {
        echo "<table><tr>";
        foreach (array_keys($rows[0]) as $k) echo "<th>$k</th>";
        echo "</tr>";
        foreach ($rows as $r) {
            echo "<tr>";
            foreach ($r as $v) echo "<td>" . htmlspecialchars((string)$v) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p class='warn'>⚠️ Table is empty – no searches have been saved yet.</p>";
    }

} catch (PDOException $e) {
    echo "<h2 class='err'>❌ Database Error</h2><pre>" . htmlspecialchars($e->getMessage()) . "</pre>";
}
?>

<p style="margin-top:2rem">
  <a href="index.html" style="color:#6366f1">← Back to homepage</a> &nbsp;|&nbsp;
  <a href="history.php" style="color:#a855f7">View History →</a>
</p>
</body>
</html>
