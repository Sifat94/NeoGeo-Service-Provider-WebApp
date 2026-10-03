<?php
/* =====================================================================
   NOIR SERVICES — LOGIN PANEL (admin_login.php)
   ---------------------------------------------------------------------
   Database table : services_db.admin
   Columns        : email (PRIMARY KEY), password
   ---------------------------------------------------------------------
   • Login: Email + Password (with show/hide password icon)
   • No id, no name, no last_login column — only email + password
   • On success  → redirect to adash.php
   • On failure  → stay here, show error
   • Brute-force protection: 5 tries → 60s lock
   • Back to Website + Forgot password links
   ===================================================================== */

ob_start();

session_set_cookie_params([
    'httponly' => true,
    'samesite' => 'Lax',
    'path' => '/',
]);
session_start();

const DB_HOST = 'localhost';
const DB_NAME = 'services_db';
const DB_USER = 'root';
const DB_PASS = '';
const DB_TABLE = 'admin';
const SITE_FILE = 'index.php';
const ADMIN_FILE = 'adash.php';
const LOGIN_FILE = 'admin_login.php';

/* ---------- helpers ---------- */
function e($s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}
function token(): string
{
    if (empty($_SESSION['t']))
        $_SESSION['t'] = bin2hex(random_bytes(16));
    return $_SESSION['t'];
}
function isAdmin(): bool
{
    return !empty($_SESSION['admin_email']);
}
function db(): PDO
{
    static $pdo = null;
    if (!$pdo)
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
            DB_USER,
            DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
        );
    return $pdo;
}
function pass_ok(string $in, string $stored): bool
{
    if (preg_match('/^\$(2y|2a|argon2id?)\$/', $stored))
        return password_verify($in, $stored);
    return hash_equals($stored, $in);
}
function redirect(string $to): void
{
    if (!headers_sent()) {
        header('Location: ' . $to);
    } else {
        echo '<!doctype html><meta charset="utf-8"><script>location.replace('
            . json_encode($to) . ');</script>';
    }
    exit;
}

/* ---------- already logged in? go to dashboard ---------- */
if (isAdmin()) {
    redirect(ADMIN_FILE);
}

/* ---------- handle POST ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    /* CSRF */
    if (!hash_equals(token(), (string) ($_POST['t'] ?? ''))) {
        http_response_code(403);
        exit('Invalid request token.');
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'login') {

        /* brute-force lock */
        if (($_SESSION['lock'] ?? 0) > time()) {
            $_SESSION['flash'] = 'Too many failed attempts. Please wait a minute and try again.';
            redirect(LOGIN_FILE);
        }

        try {
            $inEmail = trim((string) ($_POST['email'] ?? ''));
            $inPass = (string) ($_POST['password'] ?? '');

            if ($inEmail === '' || $inPass === '') {
                $_SESSION['flash'] = 'Please enter both email and password.';
                redirect(LOGIN_FILE);
            }

            /* query — only email + password columns exist */
            $q = db()->prepare('SELECT email, password FROM ' . DB_TABLE . ' WHERE email = ? LIMIT 1');
            $q->execute([$inEmail]);
            $u = $q->fetch();

            if ($u && pass_ok($inPass, (string) $u['password'])) {

                /* set session data — no id, no name column */
                $_SESSION['admin_email'] = $u['email'];
                $_SESSION['admin_name'] = 'Administrator';
                $_SESSION['tries'] = 0;
                $_SESSION['flash'] = 'Welcome back, ' . $u['email'] . '.';

                session_regenerate_id(true);

                redirect(ADMIN_FILE);
            }

            /* wrong credentials */
            $_SESSION['tries'] = ($_SESSION['tries'] ?? 0) + 1;
            if ($_SESSION['tries'] >= 5) {
                $_SESSION['lock'] = time() + 60;
                $_SESSION['tries'] = 0;
                $_SESSION['flash'] = 'Too many failed attempts. Locked for 60 seconds.';
            } else {
                $_SESSION['flash'] = 'Wrong email or password.';
            }
            redirect(LOGIN_FILE);

        } catch (Throwable $ex) {
            $_SESSION['flash'] = 'Database error: ' . $ex->getMessage();
            redirect(LOGIN_FILE);
        }
    }

    /* unknown action */
    redirect(LOGIN_FILE);
}

$flash = $_SESSION['flash'] ?? '';
unset($_SESSION['flash']);
$T = token();
$brand = 'Noir Services';
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin Sign in — <?= e($brand) ?></title>
    <meta name="robots" content="noindex, nofollow">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <script>try {var t = localStorage.getItem('theme') || 'dark'; document.documentElement.setAttribute('data-theme', t)} catch (e) { }</script>
    <style>
        :root {
            --pad: 34px
        }

        [data-theme="light"] {
            --bg: #ffffff;
            --fg: #000000;
            --mute: #5c5c5c;
            --card: #ffffff;
            --line: #000000;
            --soft: #f2f2f2;
            --sh: 0 24px 44px -22px rgba(0, 0, 0, .45), 0 10px 18px -12px rgba(0, 0, 0, .22)
        }

        [data-theme="dark"] {
            --bg: #000000;
            --fg: #ffffff;
            --mute: #9a9a9a;
            --card: #121212;
            --line: #ffffff;
            --soft: #161616;
            --sh: 0 26px 48px -22px rgba(0, 0, 0, 1), 0 0 0 1px rgba(255, 255, 255, .14), 12px 12px 0 -2px rgba(255, 255, 255, .07)
        }

        [data-theme="night"] {
            --bg: #17120c;
            --fg: #f3e6cf;
            --mute: #a8987e;
            --card: #211a11;
            --line: #f3e6cf;
            --soft: #1d1710;
            --sh: 0 26px 48px -22px rgba(0, 0, 0, .85), 0 0 0 1px rgba(243, 230, 207, .16), 12px 12px 0 -2px rgba(243, 230, 207, .07)
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            border-radius: 0 !important
        }

        html,
        body {
            height: 100%
        }

        body {
            font-family: 'Poppins', sans-serif;
            font-size: 15px;
            line-height: 1.7;
            background: var(--bg);
            color: var(--fg);
            transition: background .4s, color .4s;
            overflow-x: hidden
        }

        a {
            color: inherit;
            text-decoration: none
        }

        button,
        input {
            font: inherit;
            color: inherit
        }

        :focus-visible {
            outline: 2px solid var(--fg);
            outline-offset: 3px
        }

        .auth {
            display: grid;
            grid-template-columns: 1.05fr 1fr;
            min-height: 100vh
        }

        .auth-l {
            background: var(--fg);
            color: var(--bg);
            padding: 64px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            gap: 40px;
            position: relative;
            overflow: hidden
        }

        .auth-l::before {
            content: "";
            position: absolute;
            inset: -40%;
            background-image: linear-gradient(currentColor 1px, transparent 1px), linear-gradient(90deg, currentColor 1px, transparent 1px);
            background-size: 72px 72px;
            opacity: .08;
            animation: drift 32s linear infinite
        }

        .auth-l h1 {
            font-size: clamp(30px, 3.4vw, 56px);
            margin-bottom: 18px;
            position: relative;
            letter-spacing: -.03em;
            line-height: 1.1;
            font-weight: 600
        }

        .auth-l p {
            max-width: 520px;
            opacity: .78;
            position: relative
        }

        .auth-l .brand,
        .auth-l small {
            position: relative
        }

        .auth-r {
            display: grid;
            place-items: center;
            padding: 40px
        }

        .brand {
            font-weight: 700;
            font-size: 21px;
            letter-spacing: -.04em;
            display: inline-flex;
            align-items: center;
            gap: 11px
        }

        .brand i {
            display: block;
            width: 20px;
            height: 20px;
            background: var(--fg);
            transform: rotate(45deg);
            animation: spin 14s linear infinite
        }

        .auth-l .brand i {
            background: var(--bg)
        }

        .card {
            background: var(--card);
            border: 1px solid var(--line);
            box-shadow: var(--sh);
            width: 100%;
            max-width: 460px;
            padding: 44px
        }

        .card h2 {
            font-size: 28px;
            margin-bottom: 8px;
            font-weight: 600;
            letter-spacing: -.03em;
            line-height: 1.2
        }

        .card .sub {
            color: var(--mute);
            margin-bottom: 26px;
            font-size: 14px
        }

        .flash {
            border: 1px solid var(--line);
            background: var(--fg);
            color: var(--bg);
            padding: 13px 20px;
            margin-bottom: 22px;
            font-size: 14px
        }

        label.f {
            display: block;
            font-size: 13px;
            color: var(--mute);
            margin-bottom: 18px
        }

        .in {
            display: block;
            width: 100%;
            margin-top: 6px;
            padding: 13px 15px;
            background: var(--bg);
            border: 1px solid var(--line);
            outline: 0;
            transition: box-shadow .25s;
            font-size: 15px
        }

        .in:focus {
            box-shadow: 5px 5px 0 var(--fg)
        }

        .pw-wrap {
            position: relative
        }

        .pw-wrap .in {
            padding-right: 52px
        }

        .pw-toggle {
            position: absolute;
            right: 0;
            bottom: 0;
            top: auto;
            height: calc(100% - 24px);
            width: 48px;
            background: transparent;
            border: 0;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--mute);
            transition: color .25s;
            margin-top: 6px
        }

        .pw-wrap .in+.pw-toggle {
            bottom: 0
        }

        .pw-toggle:hover {
            color: var(--fg)
        }

        .pw-toggle svg {
            width: 20px;
            height: 20px;
            stroke: currentColor;
            fill: none;
            stroke-width: 1.8;
            stroke-linecap: round;
            stroke-linejoin: round
        }

        .pw-toggle .eye-off {
            display: none
        }

        .pw-toggle.on .eye-on {
            display: none
        }

        .pw-toggle.on .eye-off {
            display: block
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 9px;
            padding: 13px 26px;
            border: 1px solid var(--fg);
            background: var(--fg);
            color: var(--bg);
            font-weight: 500;
            cursor: pointer;
            position: relative;
            overflow: hidden;
            transition: color .3s, background .3s;
            z-index: 0;
            font-size: 14px
        }

        .btn::before {
            content: "";
            position: absolute;
            inset: 0;
            background: var(--bg);
            transform: translateX(-101%);
            transition: transform .38s cubic-bezier(.7, 0, .2, 1);
            z-index: -1
        }

        .btn:hover {
            color: var(--fg)
        }

        .btn:hover::before {
            transform: none
        }

        .row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 20px;
            font-size: 13px
        }

        .row a {
            border-bottom: 1px solid var(--line);
            padding-bottom: 1px;
            transition: opacity .25s
        }

        .row a:hover {
            opacity: .7
        }

        .themes {
            position: fixed;
            top: 20px;
            right: 20px;
            display: flex;
            border: 1px solid var(--line);
            background: var(--bg);
            z-index: 20
        }

        .themes button {
            background: transparent;
            border: 0;
            padding: 7px 12px;
            font-size: 12px;
            cursor: pointer;
            transition: background .25s, color .25s
        }

        .themes button+button {
            border-left: 1px solid var(--line)
        }

        .themes button.on {
            background: var(--fg);
            color: var(--bg)
        }

        @keyframes spin {
            to {
                transform: rotate(405deg)
            }
        }

        @keyframes drift {
            to {
                transform: translate(72px, 72px)
            }
        }

        @media (prefers-reduced-motion:reduce) {
            * {
                animation: none !important;
                transition: none !important
            }
        }

        @media (max-width:980px) {
            .auth {
                grid-template-columns: 1fr
            }

            .auth-l {
                padding: 40px 28px;
                min-height: auto
            }

            .auth-l h1 {
                font-size: 32px
            }

            .auth-r {
                padding: 24px
            }

            .card {
                padding: 32px 26px
            }
        }

        @media (max-width:520px) {
            .themes {
                top: 10px;
                right: 10px
            }

            .card {
                padding: 26px 20px
            }
        }
    </style>
</head>

<body>

    <div class="themes" role="group" aria-label="Theme">
        <button type="button" data-set="light">Light</button>
        <button type="button" data-set="night">Night</button>
        <button type="button" data-set="dark">Dark</button>
    </div>

    <div class="auth">
        <div class="auth-l">
            <a class="brand" href="<?= e(SITE_FILE) ?>"><i></i><?= e($brand) ?></a>
            <div>
                <h1>Admin control panel.</h1>
                <p>Sign in to manage service cards, moderate client reviews and update your profile.</p>
            </div>
            <small>Authorized staff only</small>
        </div>

        <div class="auth-r">
            <form class="card" method="post" action="<?= e(LOGIN_FILE) ?>">
                <h2>Sign in</h2>
                <p class="sub">Use your admin email and password.</p>

                <?php if ($flash): ?>
                    <div class="flash"><?= e($flash) ?></div><?php endif; ?>

                <input type="hidden" name="t" value="<?= e($T) ?>">
                <input type="hidden" name="action" value="login">

                <label class="f">Email address
                    <input class="in" type="email" name="email" required autofocus autocomplete="username">
                </label>

                <label class="f">Password
                    <span class="pw-wrap">
                        <input class="in" type="password" name="password" id="pw" required
                            autocomplete="current-password">
                        <button type="button" class="pw-toggle" id="pwToggle" aria-label="Show password"
                            aria-pressed="false">
                            <svg class="eye-on" viewBox="0 0 24 24">
                                <path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7S1 12 1 12z" />
                                <circle cx="12" cy="12" r="3" />
                            </svg>
                            <svg class="eye-off" viewBox="0 0 24 24">
                                <path
                                    d="M17.94 17.94A10.94 10.94 0 0 1 12 19c-7 0-11-7-11-7a18.45 18.45 0 0 1 4.06-4.94" />
                                <path d="M9.9 4.24A10.94 10.94 0 0 1 12 5c7 0 11 7 11 7a18.5 18.5 0 0 1-3.17 4.19" />
                                <path d="M9.88 9.88a3 3 0 0 0 4.24 4.24" />
                                <line x1="1" y1="1" x2="23" y2="23" />
                            </svg>
                        </button>
                    </span>
                </label>

                <button class="btn" type="submit" style="width:100%;justify-content:center">Sign in</button>

                <div class="row">
                    <a href="forgot.php">Forgot password?</a>
                    <a href="<?= e(SITE_FILE) ?>">← Back to website</a>
                </div>
            </form>
        </div>
    </div>

    <script>
        (function () {
            var root = document.documentElement, btns = document.querySelectorAll('.themes button');
            function setTheme(t) {
                root.setAttribute('data-theme', t);
                try {localStorage.setItem('theme', t);} catch (e) { }
                btns.forEach(function (b) {b.classList.toggle('on', b.dataset.set === t);});
            }
            btns.forEach(function (b) {b.addEventListener('click', function () {setTheme(b.dataset.set);});});
            setTheme(root.getAttribute('data-theme') || 'dark');

            var pw = document.getElementById('pw');
            var tg = document.getElementById('pwToggle');
            if (pw && tg) {
                tg.addEventListener('click', function () {
                    var show = pw.type === 'password';
                    pw.type = show ? 'text' : 'password';
                    tg.classList.toggle('on', show);
                    tg.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
                    tg.setAttribute('aria-pressed', show ? 'true' : 'false');
                    pw.focus();
                    try {var v = pw.value; pw.setSelectionRange(v.length, v.length);} catch (e) { }
                });
            }
        })();
    </script>
</body>

</html>