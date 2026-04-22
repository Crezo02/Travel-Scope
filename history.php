<?php
session_start();
require_once 'config.php';

// Must be logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$userId   = (int)$_SESSION['user_id'];
$username = $_SESSION['username'] ?? 'Traveller';
$history  = [];
$error    = '';

// Handle delete single
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    try {
        $pdo = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8", DB_USER, DB_PASS,
                       [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $del = $pdo->prepare("DELETE FROM search_history WHERE id = ? AND user_id = ?");
        $del->execute([(int)$_POST['delete_id'], $userId]);
    } catch (PDOException $e) { /* silent */ }
    header('Location: history.php');
    exit;
}

// Handle clear all
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['clear_all'])) {
    try {
        $pdo = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8", DB_USER, DB_PASS,
                       [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $pdo->prepare("DELETE FROM search_history WHERE user_id = ?")->execute([$userId]);
    } catch (PDOException $e) { /* silent */ }
    header('Location: history.php');
    exit;
}

// Fetch this user's history
try {
    $pdo = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8", DB_USER, DB_PASS,
                   [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $stmt = $pdo->prepare("SELECT * FROM search_history WHERE user_id = ? ORDER BY search_time DESC LIMIT 100");
    $stmt->execute([$userId]);
    $history = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = 'Could not load history: ' . htmlspecialchars($e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Search History – TravelScope</title>
    <meta name="description" content="View your personal travel search history on TravelScope.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Outfit:wght@700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Inter', sans-serif;
            background: #0a0a1a;
            color: #f0f0f5;
            min-height: 100vh;
            padding-top: 90px;
        }

        /* ── Mesh bg ── */
        .bg-mesh {
            position: fixed; inset: 0; z-index: 0;
            background:
                radial-gradient(ellipse 70% 50% at 15% 20%, rgba(99,102,241,.14) 0%, transparent 60%),
                radial-gradient(ellipse 60% 50% at 85% 70%, rgba(168,85,247,.12) 0%, transparent 55%),
                #0a0a1a;
        }

        /* ── Navbar ── */
        .navbar {
            position: fixed; top: 0; left: 0; right: 0; z-index: 1000;
            padding: .9rem 0;
            background: rgba(10,10,26,.9);
            backdrop-filter: blur(20px);
            border-bottom: 1px solid rgba(255,255,255,.08);
        }
        .nav-container {
            max-width: 1100px; margin: 0 auto; padding: 0 2rem;
            display: flex; align-items: center; justify-content: space-between;
        }
        .logo {
            font-family: 'Outfit', sans-serif; font-size: 1.4rem; font-weight: 800;
            text-decoration: none; color: #f0f0f5; display: flex; align-items: center; gap: .4rem;
        }
        .logo-accent {
            background: linear-gradient(135deg, #6366f1, #a855f7, #ec4899);
            -webkit-background-clip: text; -webkit-text-fill-color: transparent;
        }
        .nav-right { display: flex; align-items: center; gap: .8rem; }
        .nav-user { font-size: .85rem; color: #94a3b8; font-weight: 500; }
        .btn-nav {
            padding: .45rem 1.1rem; border-radius: 999px; font-size: .85rem;
            font-weight: 600; text-decoration: none; transition: all .2s; cursor: pointer;
            border: none; font-family: inherit;
        }
        .btn-home {
            background: rgba(255,255,255,.06);
            border: 1px solid rgba(255,255,255,.1);
            color: #94a3b8;
        }
        .btn-home:hover { color: #f0f0f5; background: rgba(255,255,255,.1); }
        .btn-logout { background: transparent; color: #ef4444; border: 1px solid rgba(239,68,68,.3); }
        .btn-logout:hover { background: rgba(239,68,68,.1); }

        /* ── Page wrapper ── */
        .page {
            position: relative; z-index: 1;
            max-width: 900px; margin: 0 auto; padding: 2rem 1.5rem 5rem;
        }

        /* ── Header ── */
        .page-header {
            display: flex; align-items: flex-end; justify-content: space-between;
            flex-wrap: wrap; gap: 1rem; margin-bottom: 2rem;
        }
        .page-title {
            font-family: 'Outfit', sans-serif; font-size: 2rem; font-weight: 800;
            background: linear-gradient(135deg, #a855f7, #ec4899);
            -webkit-background-clip: text; -webkit-text-fill-color: transparent;
        }
        .page-subtitle { color: #64748b; font-size: .9rem; margin-top: .3rem; }
        .count-badge {
            display: inline-block; padding: .3rem .9rem;
            background: rgba(168,85,247,.15); border: 1px solid rgba(168,85,247,.3);
            border-radius: 999px; font-size: .8rem; color: #c084fc; font-weight: 600;
        }

        /* ── Clear All button ── */
        .btn-clear {
            padding: .5rem 1.2rem; border-radius: 10px;
            background: rgba(239,68,68,.1); border: 1px solid rgba(239,68,68,.25);
            color: #fca5a5; font-size: .85rem; font-weight: 600;
            font-family: inherit; cursor: pointer; transition: all .2s;
        }
        .btn-clear:hover { background: rgba(239,68,68,.2); }

        /* ── History cards ── */
        .history-list { display: flex; flex-direction: column; gap: .75rem; }

        .history-card {
            background: rgba(255,255,255,.04);
            border: 1px solid rgba(255,255,255,.08);
            border-radius: 16px;
            padding: 1.2rem 1.5rem;
            display: flex; align-items: center; gap: 1.2rem;
            transition: all .25s;
            animation: fadeUp .4s ease both;
        }
        .history-card:hover {
            background: rgba(255,255,255,.07);
            border-color: rgba(168,85,247,.3);
            transform: translateY(-2px);
            box-shadow: 0 8px 30px rgba(0,0,0,.3);
        }
        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(12px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        .card-icon {
            width: 46px; height: 46px; border-radius: 12px;
            background: linear-gradient(135deg, rgba(99,102,241,.25), rgba(168,85,247,.25));
            border: 1px solid rgba(168,85,247,.2);
            display: flex; align-items: center; justify-content: center;
            font-size: 1.3rem; flex-shrink: 0;
        }

        .card-body { flex: 1; min-width: 0; }
        .card-city {
            font-size: 1.05rem; font-weight: 700; color: #f0f0f5;
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        }
        .card-query {
            font-size: .8rem; color: #64748b; margin-top: .15rem;
        }
        .card-query span { color: #94a3b8; }

        .card-time {
            font-size: .78rem; color: #475569; white-space: nowrap; flex-shrink: 0;
            display: flex; flex-direction: column; align-items: flex-end; gap: .1rem;
        }

        /* Search again button */
        .btn-search-again {
            padding: .4rem .9rem; border-radius: 8px;
            background: rgba(99,102,241,.15); border: 1px solid rgba(99,102,241,.3);
            color: #818cf8; font-size: .8rem; font-weight: 600;
            text-decoration: none; transition: all .2s; white-space: nowrap;
            cursor: pointer; font-family: inherit;
        }
        .btn-search-again:hover { background: rgba(99,102,241,.25); color: #a5b4fc; }

        /* Delete button */
        .btn-delete {
            width: 30px; height: 30px; border-radius: 8px; flex-shrink: 0;
            background: transparent; border: 1px solid rgba(239,68,68,.2);
            color: #ef4444; font-size: .95rem; cursor: pointer;
            display: flex; align-items: center; justify-content: center;
            transition: all .2s; font-family: inherit;
        }
        .btn-delete:hover { background: rgba(239,68,68,.15); border-color: rgba(239,68,68,.5); }

        /* ── Empty state ── */
        .empty-state {
            text-align: center; padding: 5rem 2rem;
            background: rgba(255,255,255,.03);
            border: 1px solid rgba(255,255,255,.07);
            border-radius: 20px;
        }
        .empty-icon { font-size: 3.5rem; margin-bottom: 1rem; }
        .empty-title { font-family: 'Outfit', sans-serif; font-size: 1.4rem; font-weight: 700; color: #cbd5e1; }
        .empty-sub { color: #64748b; font-size: .9rem; margin-top: .5rem; }
        .btn-go-search {
            display: inline-block; margin-top: 1.5rem;
            padding: .7rem 1.8rem; border-radius: 10px;
            background: linear-gradient(135deg, #6366f1, #a855f7);
            color: white; font-weight: 700; text-decoration: none; font-size: .9rem;
            box-shadow: 0 4px 15px rgba(99,102,241,.4);
            transition: opacity .2s;
        }
        .btn-go-search:hover { opacity: .9; }

        /* ── Error ── */
        .alert-error {
            padding: 1rem 1.4rem; border-radius: 12px;
            background: rgba(239,68,68,.12); border: 1px solid rgba(239,68,68,.25);
            color: #fca5a5; font-size: .9rem;
        }
    </style>
</head>
<body>
    <div class="bg-mesh"></div>

    <!-- Navbar -->
    <nav class="navbar">
        <div class="nav-container">
            <a href="index.html" class="logo">🌍 Travel<span class="logo-accent">Scope</span></a>
            <div class="nav-right">
                <span class="nav-user">👋 <?= htmlspecialchars($username) ?></span>
                <a href="index.html" class="btn-nav btn-home">← Home</a>
                <a href="logout.php" class="btn-nav btn-logout">Logout</a>
            </div>
        </div>
    </nav>

    <div class="page">

        <!-- Page Header -->
        <div class="page-header">
            <div>
                <h1 class="page-title">🗺️ My Travel History</h1>
                <p class="page-subtitle">Every destination you've explored on TravelScope</p>
            </div>
            <div style="display:flex;align-items:center;gap:.8rem;flex-wrap:wrap;">
                <?php if (!empty($history)): ?>
                    <span class="count-badge"><?= count($history) ?> search<?= count($history) !== 1 ? 'es' : '' ?></span>
                    <form method="POST" onsubmit="return confirm('Clear your entire history?')">
                        <input type="hidden" name="clear_all" value="1">
                        <button type="submit" class="btn-clear">🗑 Clear All</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($error): ?>
            <div class="alert-error">⚠️ <?= $error ?></div>

        <?php elseif (empty($history)): ?>
            <div class="empty-state">
                <div class="empty-icon">🔍</div>
                <p class="empty-title">No searches yet</p>
                <p class="empty-sub">Search any city on TravelScope and it will appear here.</p>
                <a href="index.html" class="btn-go-search">Start Exploring →</a>
            </div>

        <?php else: ?>
            <div class="history-list">
                <?php foreach ($history as $i => $row):
                    $city  = htmlspecialchars($row['resolved_city']);
                    $query = htmlspecialchars($row['search_query']);
                    $time  = strtotime($row['search_time']);
                    $date  = date('M j, Y', $time);
                    $clock = date('g:i A', $time);
                    $emojis = ['🗼','🗾','🏙️','🏝️','🎡','🏛️','🌍','🗺️','🌏','🌎'];
                    $emoji  = $emojis[$i % count($emojis)];
                ?>
                <div class="history-card" style="animation-delay: <?= $i * 0.05 ?>s">
                    <div class="card-icon"><?= $emoji ?></div>

                    <div class="card-body">
                        <div class="card-city"><?= $city ?></div>
                        <?php if (strtolower($query) !== strtolower($row['resolved_city'])): ?>
                            <div class="card-query">Searched: <span>"<?= $query ?>"</span></div>
                        <?php endif; ?>
                    </div>

                    <div class="card-time">
                        <span><?= $date ?></span>
                        <span><?= $clock ?></span>
                    </div>

                    <!-- Search again -->
                    <a href="index.html?q=<?= urlencode($row['resolved_city']) ?>"
                       class="btn-search-again">🔍 Again</a>

                    <!-- Delete -->
                    <form method="POST" style="margin:0">
                        <input type="hidden" name="delete_id" value="<?= (int)$row['id'] ?>">
                        <button type="submit" class="btn-delete" title="Remove">✕</button>
                    </form>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // Auto-trigger search if ?q= param present (from "Search Again")
        const params = new URLSearchParams(window.location.search);
        if (params.has('q')) {
            sessionStorage.setItem('ts_autosearch', params.get('q'));
            window.location.href = 'index.html';
        }
    </script>
</body>
</html>
