<?php
session_start();
require_once 'config.php';

$error = '';

// Already logged in → go to main site
if (isset($_SESSION['user_id'])) {
    header('Location: index.html');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($username === '' || $password === '') {
        $error = 'Please fill in all fields.';
    } else {
        try {
            $pdo = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8",
                DB_USER, DB_PASS,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
            $stmt = $pdo->prepare("SELECT id, username, password_hash FROM users WHERE username = ? LIMIT 1");
            $stmt->execute([$username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && password_verify($password, $user['password_hash'])) {
                $_SESSION['user_id']  = $user['id'];
                $_SESSION['username'] = $user['username'];
                // Pass username to browser sessionStorage, then redirect
                echo "<!DOCTYPE html><html><head><meta charset='UTF-8'></head><body>
                <script>
                    sessionStorage.setItem('ts_username', " . json_encode($user['username']) . ");
                    window.location.href = 'index.html';
                </script></body></html>";
                exit;
            } else {
                $error = 'Invalid username or password.';
            }
        } catch (PDOException $e) {
            $error = 'Database error: ' . htmlspecialchars($e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login – TravelScope</title>
    <meta name="description" content="Sign in to TravelScope and explore personalized travel cost insights.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Inter', sans-serif;
            background: #0f172a;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            overflow: hidden;
        }

        /* ── Animated mesh background ── */
        .bg-mesh {
            position: fixed; inset: 0; z-index: 0;
            background:
                radial-gradient(ellipse 80% 60% at 20% 30%, rgba(168,85,247,.18) 0%, transparent 60%),
                radial-gradient(ellipse 60% 50% at 80% 70%, rgba(236,72,153,.15) 0%, transparent 55%),
                radial-gradient(ellipse 70% 70% at 50% 50%, rgba(99,102,241,.10) 0%, transparent 65%),
                #0f172a;
            animation: meshShift 12s ease-in-out infinite alternate;
        }
        @keyframes meshShift {
            0%  { filter: hue-rotate(0deg); }
            100%{ filter: hue-rotate(30deg); }
        }

        /* ── Floating particles ── */
        #particles { position: fixed; inset: 0; z-index: 1; pointer-events: none; }
        .particle {
            position: absolute; border-radius: 50%; opacity: .25;
            animation: rise linear infinite;
        }
        @keyframes rise {
            0%   { transform: translateY(110vh) scale(.6); opacity: 0; }
            10%  { opacity: .25; }
            90%  { opacity: .25; }
            100% { transform: translateY(-10vh) scale(1); opacity: 0; }
        }

        /* ── Card ── */
        .auth-card {
            position: relative; z-index: 10;
            background: rgba(30, 41, 59, .65);
            border: 1px solid rgba(255,255,255,.1);
            backdrop-filter: blur(20px) saturate(180%);
            -webkit-backdrop-filter: blur(20px) saturate(180%);
            border-radius: 24px;
            padding: 2.8rem 2.5rem;
            width: 100%;
            max-width: 420px;
            box-shadow: 0 30px 60px rgba(0,0,0,.5), 0 0 0 1px rgba(255,255,255,.05) inset;
            animation: cardIn .5s cubic-bezier(.34,1.56,.64,1) both;
        }
        @keyframes cardIn {
            from { opacity: 0; transform: translateY(30px) scale(.96); }
            to   { opacity: 1; transform: translateY(0)    scale(1); }
        }

        /* ── Logo ── */
        .auth-logo {
            text-align: center;
            margin-bottom: 1.8rem;
        }
        .auth-logo .icon {
            font-size: 2.8rem;
            display: block;
            margin-bottom: .5rem;
            filter: drop-shadow(0 0 16px rgba(168,85,247,.6));
            animation: float 3s ease-in-out infinite;
        }
        @keyframes float {
            0%,100%{ transform: translateY(0); }
            50%    { transform: translateY(-6px); }
        }
        .auth-logo h1 {
            font-size: 1.6rem; font-weight: 800;
            background: linear-gradient(135deg, #a855f7 0%, #ec4899 100%);
            -webkit-background-clip: text; -webkit-text-fill-color: transparent;
            letter-spacing: -.5px;
        }
        .auth-logo p {
            color: #64748b; font-size: .85rem; margin-top: .3rem;
        }

        /* ── Tabs ── */
        .tabs {
            display: flex; gap: 0;
            background: rgba(0,0,0,.25);
            border-radius: 12px;
            padding: 4px;
            margin-bottom: 1.8rem;
        }
        .tab-btn {
            flex: 1; padding: .55rem;
            background: transparent; border: none;
            color: #64748b; font-size: .9rem; font-weight: 600;
            border-radius: 9px; cursor: pointer;
            transition: all .25s;
        }
        .tab-btn.active {
            background: linear-gradient(135deg, #a855f7, #ec4899);
            color: #fff;
            box-shadow: 0 4px 14px rgba(168,85,247,.4);
        }

        /* ── Form ── */
        .form-group { margin-bottom: 1.2rem; }
        .form-group label {
            display: block; margin-bottom: .45rem;
            color: #94a3b8; font-size: .82rem; font-weight: 500; letter-spacing: .3px;
        }
        .input-wrap { position: relative; }
        .input-wrap .input-icon {
            position: absolute; left: .9rem; top: 50%; transform: translateY(-50%);
            color: #475569; font-size: 1rem; pointer-events: none;
        }
        .form-group input {
            width: 100%;
            padding: .75rem 1rem .75rem 2.5rem;
            background: rgba(0,0,0,.2);
            border: 1px solid rgba(255,255,255,.08);
            border-radius: 12px;
            color: #f1f5f9;
            font-size: .95rem;
            font-family: inherit;
            transition: border-color .3s, box-shadow .3s, background .3s;
        }
        .form-group input:focus {
            outline: none;
            border-color: #a855f7;
            background: rgba(0,0,0,.3);
            box-shadow: 0 0 0 3px rgba(168,85,247,.18);
        }
        .form-group input::placeholder { color: #475569; }

        /* ── Alert ── */
        .alert {
            display: flex; align-items: center; gap: .6rem;
            padding: .8rem 1rem;
            border-radius: 10px;
            font-size: .88rem;
            margin-bottom: 1.2rem;
            animation: shake .4s ease;
        }
        @keyframes shake {
            0%,100%{ transform: translateX(0); }
            25%    { transform: translateX(-6px); }
            75%    { transform: translateX( 6px); }
        }
        .alert-error {
            background: rgba(239,68,68,.15);
            color: #fca5a5;
            border: 1px solid rgba(239,68,68,.3);
        }
        .alert-success {
            background: rgba(16,185,129,.15);
            color: #6ee7b7;
            border: 1px solid rgba(16,185,129,.3);
        }

        /* ── Submit button ── */
        .btn-submit {
            width: 100%; margin-top: .5rem;
            padding: .82rem;
            background: linear-gradient(135deg, #a855f7 0%, #ec4899 100%);
            border: none; border-radius: 12px;
            color: #fff; font-size: 1rem; font-weight: 700;
            font-family: inherit; cursor: pointer;
            transition: opacity .3s, transform .2s, box-shadow .3s;
            box-shadow: 0 6px 20px rgba(168,85,247,.4);
            letter-spacing: .3px;
        }
        .btn-submit:hover  { opacity: .9; box-shadow: 0 8px 28px rgba(168,85,247,.55); }
        .btn-submit:active { transform: scale(.98); }

        /* ── Footer link ── */
        .auth-footer {
            text-align: center; margin-top: 1.4rem;
            color: #64748b; font-size: .85rem;
        }
        .auth-footer a {
            color: #a855f7; text-decoration: none; font-weight: 600;
            transition: color .2s;
        }
        .auth-footer a:hover { color: #ec4899; }

        /* ── Back link ── */
        .back-link {
            display: block; text-align: center;
            margin-top: 1rem; color: #475569;
            font-size: .8rem; text-decoration: none;
            transition: color .2s;
        }
        .back-link:hover { color: #94a3b8; }
    </style>
</head>
<body>
    <div class="bg-mesh"></div>
    <div id="particles"></div>

    <div class="auth-card">
        <div class="auth-logo">
            <span class="icon">✈️</span>
            <h1>TravelScope</h1>
            <p>Sign in to your account</p>
        </div>

        <!-- Tab bar (visual only — register.php handles sign-up) -->
        <div class="tabs" role="tablist">
            <button class="tab-btn active" id="tab-login"    onclick="window.location='login.php'"    role="tab">Sign In</button>
            <button class="tab-btn"        id="tab-register" onclick="window.location='register.php'" role="tab">Create Account</button>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error" role="alert">⚠️ <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="login.php" id="login-form" novalidate>
            <div class="form-group">
                <label for="username">Username</label>
                <div class="input-wrap">
                    <span class="input-icon">👤</span>
                    <input type="text" id="username" name="username"
                           placeholder="Enter your username"
                           value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                           required autocomplete="username">
                </div>
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <div class="input-wrap">
                    <span class="input-icon">🔒</span>
                    <input type="password" id="password" name="password"
                           placeholder="Enter your password"
                           required autocomplete="current-password">
                </div>
            </div>

            <button type="submit" class="btn-submit" id="login-btn">Sign In →</button>
        </form>

        <p class="auth-footer">
            Don't have an account? <a href="register.php">Create one for free</a>
        </p>
        <a class="back-link" href="index.html">← Return to website</a>
    </div>

    <script>
        // Particle system
        const pc = document.getElementById('particles');
        const cols = ['#6366f1','#a855f7','#ec4899','#06b6d4','#10b981'];
        for (let i = 0; i < 35; i++) {
            const p = document.createElement('div');
            p.className = 'particle';
            const s = Math.random() * 5 + 2;
            p.style.cssText = `
                width:${s}px;height:${s}px;
                left:${Math.random()*100}%;
                background:${cols[i%cols.length]};
                animation-duration:${Math.random()*18+14}s;
                animation-delay:${Math.random()*14}s;
            `;
            pc.appendChild(p);
        }

        // Button loading state
        document.getElementById('login-form').addEventListener('submit', function() {
            const btn = document.getElementById('login-btn');
            btn.textContent = 'Signing in…';
            btn.style.opacity = '.7';
        });
    </script>
</body>
</html>
