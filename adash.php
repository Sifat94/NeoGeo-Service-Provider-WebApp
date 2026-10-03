<?php
/* =====================================================================
   NOIR SERVICES — INLINE ADMIN EDITOR (adash.php)
   ---------------------------------------------------------------------
   Visual: index.php er moto same layout
   Function: sob section inline edit + save → data.json
   Cards : MySQL `card` table
   Gmail : mailto: link support (via Site editor)
   Account : name + login email + password (ONLY 3 options)
   ---------------------------------------------------------------------
   Login check: $_SESSION['admin_email'] (admin_login.php)
   ===================================================================== */

ob_start();
session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax', 'path' => '/']);
session_start();

const DB_HOST = 'localhost';
const DB_NAME = 'services_db';
const DB_USER = 'root';
const DB_PASS = '';
const DB_TABLE = 'admin';
const CARD_TABLE = 'card';
const DATA_FILE = __DIR__ . '/data.json';
const LOGIN_FILE = 'admin_login.php';
const DASH_FILE = 'adash.php';
const SITE_FILE = 'index.php';

/* ---------- defaults ---------- */
function defaults(): array
{
    return [
        'settings' => [
            'brand' => 'Noir Services',
            'headline' => 'Business services, delivered with precision.',
            'sub' => 'Choose the services your company needs, then talk to our team directly on WhatsApp. No forms, no waiting.',
            'whatsapp' => '',
            'about_title' => '',
            'about_text' => "",
            'address' => '',
            'email' => '',
            'phone' => '',
            'hours' => '',
            'map_embed' => '',
            'facebook' => '',
            'linkedin' => '',
            'instagram' => '',
            'x' => '',
            'youtube' => '',
            'gmail' => '',
        ],
        'reviews' => [],
    ];
}

/* ---------- LOAD ---------- */
function load(): array
{
    if (!file_exists(DATA_FILE))
        save(defaults());

    $raw = (string) file_get_contents(DATA_FILE);
    $d = json_decode($raw, true);
    if (!is_array($d))
        $d = [];

    $def = defaults();
    $d['settings'] = array_merge($def['settings'], $d['settings'] ?? []);

    foreach (['facebook', 'linkedin', 'instagram', 'x', 'youtube', 'gmail', 'map_embed'] as $k) {
        if (!isset($d['settings'][$k]) || !is_string($d['settings'][$k])) {
            $d['settings'][$k] = $def['settings'][$k] ?? '';
        }
    }

    $d['reviews'] = $d['reviews'] ?? $def['reviews'];

    $onDisk = json_decode($raw, true);
    if ($d !== $onDisk)
        save($d);

    return $d;
}

function save(array $d): void
{
    file_put_contents(DATA_FILE, json_encode($d, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
}

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
function clean($s, int $max): string
{
    return mb_substr(trim(strip_tags((string) $s)), 0, $max);
}
function cleanMulti($s, int $max): string
{
    return mb_substr(trim((string) $s), 0, $max);
}
function stars(int $n): string
{
    $n = max(0, min(5, $n));
    return str_repeat('★', $n) . str_repeat('☆', 5 - $n);
}
function safe_url($s): string
{
    $s = trim((string) $s);
    return (preg_match('~^https?://~i', $s) && filter_var($s, FILTER_VALIDATE_URL)) ? mb_substr($s, 0, 500) : '';
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
    if (!headers_sent())
        header('Location: ' . $to);
    else
        echo '<!doctype html><meta charset="utf-8"><script>location.replace(' . json_encode($to) . ');</script>';
    exit;
}
function revStatus(array $r): string
{
    if (isset($r['status']) && in_array($r['status'], ['pending', 'approved', 'rejected'], true))
        return $r['status'];
    return !empty($r['visible']) ? 'approved' : 'pending';
}

/* ---------- card helpers (MySQL) ---------- */
function cardsAll(): array
{
    try {
        return db()->query('SELECT `category`,`heading`,`sub_heading`,`price`,`select`,`whatsapp` FROM ' . CARD_TABLE . ' ORDER BY `heading` ASC')->fetchAll();
    } catch (Throwable $e) {
        return [];
    }
}
function cardInsert(array $r): bool
{
    try {
        $q = db()->prepare('INSERT INTO ' . CARD_TABLE . ' (`category`,`heading`,`sub_heading`,`price`,`select`,`whatsapp`) VALUES (?,?,?,?,?,?)');
        return $q->execute([$r['category'], $r['heading'], $r['sub_heading'], $r['price'], $r['select'], $r['whatsapp']]);
    } catch (Throwable $e) {
        return false;
    }
}
function cardUpdate(string $old, array $r): bool
{
    try {
        $q = db()->prepare('UPDATE ' . CARD_TABLE . ' SET `category`=?,`heading`=?,`sub_heading`=?,`price`=?,`select`=?,`whatsapp`=? WHERE `heading`=?');
        return $q->execute([$r['category'], $r['heading'], $r['sub_heading'], $r['price'], $r['select'], $r['whatsapp'], $old]);
    } catch (Throwable $e) {
        return false;
    }
}
function cardDelete(string $h): bool
{
    try {
        return db()->prepare('DELETE FROM ' . CARD_TABLE . ' WHERE `heading`=?')->execute([$h]);
    } catch (Throwable $e) {
        return false;
    }
}

/* ---------- AUTH GUARD ---------- */
if (!isAdmin()) {
    $_SESSION['flash'] = 'Please sign in to continue.';
    redirect(LOGIN_FILE);
}

/* ---------- ACTIONS ---------- */
$data = load();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals(token(), (string) ($_POST['t'] ?? ''))) {
        http_response_code(403);
        exit('Invalid request token.');
    }
    $a = $_POST['action'] ?? '';

    /* LOGOUT */
    if ($a === 'logout') {
        unset($_SESSION['admin_email'], $_SESSION['admin_name'], $_SESSION['tries'], $_SESSION['lock'], $_SESSION['t']);
        session_regenerate_id(true);
        session_destroy();
        session_start();
        session_regenerate_id(true);
        $_SESSION['flash'] = 'You have been logged out.';
        redirect(LOGIN_FILE);
    }

    /* SAVE SETTING (inline editor — Site editor panel) */
    if ($a === 'save_setting') {
        $key = preg_replace('/[^a-z_]/', '', (string) ($_POST['key'] ?? ''));
        $val = trim((string) ($_POST['value'] ?? ''));

        if ($key === '') {
            echo json_encode(['ok' => false, 'err' => 'no_key']);
            exit;
        }
        if (!array_key_exists($key, $data['settings']))
            $data['settings'][$key] = '';

        if ($key === 'whatsapp') {
            $data['settings'][$key] = preg_replace('/\D/', '', $val);
        } elseif ($key === 'email') {
            $data['settings'][$key] = filter_var($val, FILTER_VALIDATE_EMAIL) ?: '';
        } elseif ($key === 'gmail') {
            $data['settings'][$key] = filter_var($val, FILTER_VALIDATE_EMAIL) ?: clean($val, 190);
        } elseif ($key === 'map_embed') {
            $data['settings'][$key] = preg_match('~^https?://~i', $val) ? mb_substr($val, 0, 1000) : '';
        } elseif (in_array($key, ['facebook', 'linkedin', 'instagram', 'x', 'youtube'], true)) {
            if ($val !== '' && !preg_match('~^https?://~i', $val))
                $val = 'https://' . ltrim($val, '/');
            $data['settings'][$key] = mb_substr($val, 0, 500);
        } elseif ($key === 'about_text') {
            $data['settings'][$key] = cleanMulti($val, 1500);
        } else {
            $data['settings'][$key] = clean($val, 400);
        }

        save($data);
        echo json_encode(['ok' => true, 'value' => $data['settings'][$key]]);
        exit;
    }

    /* PROFILE — display name only (Account panel) */
    if ($a === 'profile_save') {
        $nm = clean($_POST['name'] ?? '', 120);
        if ($nm) {
            $_SESSION['admin_name'] = $nm;
            $_SESSION['flash'] = 'Display name updated.';
        } else {
            $_SESSION['flash'] = 'Please enter a name.';
        }
        redirect(DASH_FILE . '?p=account');
    }

    /* LOGIN EMAIL CHANGE (updates services_db.admin directly) */
    if ($a === 'login_email_change') {
        try {
            $newEmail = trim((string) ($_POST['new_email'] ?? ''));
            $curPass = (string) ($_POST['current_password'] ?? '');
            $curEmail = $_SESSION['admin_email'] ?? '';

            if (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
                $_SESSION['flash'] = 'Please enter a valid email address.';
                redirect(DASH_FILE . '?p=account');
            }

            $q = db()->prepare('SELECT password FROM ' . DB_TABLE . ' WHERE email = ? LIMIT 1');
            $q->execute([$curEmail]);
            $row = $q->fetch();
            if (!$row || !pass_ok($curPass, (string) $row['password'])) {
                $_SESSION['flash'] = 'Current password is wrong.';
                redirect(DASH_FILE . '?p=account');
            }

            $chk = db()->prepare('SELECT email FROM ' . DB_TABLE . ' WHERE email = ? AND email <> ? LIMIT 1');
            $chk->execute([$newEmail, $curEmail]);
            if ($chk->fetch()) {
                $_SESSION['flash'] = 'That email is already used by another admin.';
                redirect(DASH_FILE . '?p=account');
            }

            if ($newEmail === $curEmail) {
                $_SESSION['flash'] = 'That is already your login email.';
                redirect(DASH_FILE . '?p=account');
            }

            $upd = db()->prepare('UPDATE ' . DB_TABLE . ' SET email = ? WHERE email = ?');
            $upd->execute([$newEmail, $curEmail]);

            $_SESSION['admin_email'] = $newEmail;
            $_SESSION['flash'] = 'Login email updated — use the new email next time you sign in.';
        } catch (Throwable $ex) {
            $_SESSION['flash'] = 'Could not update the email: ' . $ex->getMessage();
        }
        redirect(DASH_FILE . '?p=account');
    }

    /* CARD INSERT */
    if ($a === 'card_insert') {
        $row = [
            'category' => clean($_POST['category'] ?? '', 100),
            'heading' => clean($_POST['heading'] ?? '', 150),
            'sub_heading' => clean($_POST['sub_heading'] ?? '', 255),
            'price' => clean($_POST['price'] ?? '', 60),
            'select' => clean($_POST['select'] ?? 'Select', 30),
            'whatsapp' => preg_replace('/\D/', '', (string) ($_POST['whatsapp'] ?? '')),
        ];
        $_SESSION['flash'] = ($row['heading'] && cardInsert($row)) ? 'Card added.' : 'Could not add card.';
        redirect(DASH_FILE . '?p=cards');
    }
    if ($a === 'card_update') {
        $old = clean($_POST['old_heading'] ?? '', 150);
        $row = [
            'category' => clean($_POST['category'] ?? '', 100),
            'heading' => clean($_POST['heading'] ?? '', 150),
            'sub_heading' => clean($_POST['sub_heading'] ?? '', 255),
            'price' => clean($_POST['price'] ?? '', 60),
            'select' => clean($_POST['select'] ?? 'Select', 30),
            'whatsapp' => preg_replace('/\D/', '', (string) ($_POST['whatsapp'] ?? '')),
        ];
        $_SESSION['flash'] = ($old && $row['heading'] && cardUpdate($old, $row)) ? 'Card updated.' : 'Could not update.';
        redirect(DASH_FILE . '?p=cards');
    }
    if ($a === 'card_delete') {
        $h = clean($_POST['heading'] ?? '', 150);
        $_SESSION['flash'] = $h && cardDelete($h) ? 'Card deleted.' : 'Could not delete.';
        redirect(DASH_FILE . '?p=cards');
    }

    /* REVIEWS */
    if (in_array($a, ['review_approve', 'review_reject', 'review_pending', 'review_delete'], true)) {
        $id = $_POST['id'] ?? '';
        if ($a === 'review_delete') {
            $data['reviews'] = array_values(array_filter($data['reviews'], fn($r) => $r['id'] !== $id));
        } else {
            $map = ['review_approve' => 'approved', 'review_reject' => 'rejected', 'review_pending' => 'pending'];
            foreach ($data['reviews'] as $i => $r)
                if ($r['id'] === $id) {
                    $data['reviews'][$i]['status'] = $map[$a];
                    $data['reviews'][$i]['visible'] = ($map[$a] === 'approved');
                }
        }
        save($data);
        $_SESSION['flash'] = 'Review updated.';
        redirect(DASH_FILE . '?p=reviews');
    }
    if ($a === 'review_add') {
        $nm = clean($_POST['name'] ?? '', 60);
        $tx = clean($_POST['text'] ?? '', 500);
        if ($nm && $tx) {
            array_unshift($data['reviews'], [
                'id' => uniqid('r'),
                'name' => $nm,
                'company' => clean($_POST['company'] ?? '', 80),
                'rating' => max(1, min(5, (int) ($_POST['rating'] ?? 5))),
                'text' => $tx,
                'status' => 'approved',
                'visible' => true,
            ]);
            save($data);
            $_SESSION['flash'] = 'Review added.';
        }
        redirect(DASH_FILE . '?p=reviews');
    }

    /* PASSWORD CHANGE */
    if ($a === 'password_change') {
        try {
            $q = db()->prepare('SELECT password FROM ' . DB_TABLE . ' WHERE email = ?');
            $q->execute([$_SESSION['admin_email'] ?? '']);
            $row = $q->fetch();
            $cur = (string) ($_POST['current'] ?? '');
            $new1 = (string) ($_POST['new'] ?? '');
            $new2 = (string) ($_POST['confirm'] ?? '');
            if (!$row || !pass_ok($cur, (string) $row['password']))
                $_SESSION['flash'] = 'Current password is wrong.';
            elseif (strlen($new1) < 8)
                $_SESSION['flash'] = 'New password must be 8+ characters.';
            elseif ($new1 !== $new2)
                $_SESSION['flash'] = 'New passwords do not match.';
            else {
                db()->prepare('UPDATE ' . DB_TABLE . ' SET password = ? WHERE email = ?')
                    ->execute([password_hash($new1, PASSWORD_DEFAULT), $_SESSION['admin_email'] ?? '']);
                $_SESSION['flash'] = 'Password updated.';
            }
        } catch (Throwable $ex) {
            $_SESSION['flash'] = 'Could not update password.';
        }
        redirect(DASH_FILE . '?p=account');
    }

    redirect(DASH_FILE);
}

/* ---------- VIEW STATE ---------- */
$flash = $_SESSION['flash'] ?? '';
unset($_SESSION['flash']);
$st = $data['settings'];
$T = token();

$cards = cardsAll();
$servicesTotal = count($cards);
$reviews = $data['reviews'];
$reviewsPending = count(array_filter($reviews, fn($r) => revStatus($r) === 'pending'));
$reviewsApproved = count(array_filter($reviews, fn($r) => revStatus($r) === 'approved'));
$avg = $reviews ? round(array_sum(array_column($reviews, 'rating')) / count($reviews), 1) : 0;
$waLink = 'https://wa.me/' . preg_replace('/\D/', '', $st['whatsapp']);
$socials = [
    'facebook' => 'Facebook',
    'linkedin' => 'LinkedIn',
    'instagram' => 'Instagram',
    'x' => 'X',
    'youtube' => 'YouTube',
    'gmail' => 'Gmail address',
];

$panel = $_GET['p'] ?? 'site';
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin — <?= e($st['brand']) ?></title>
    <meta name="robots" content="noindex, nofollow">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <script>try {var t = localStorage.getItem('theme') || 'dark'; document.documentElement.setAttribute('data-theme', t)} catch (e) { }</script>
    <style>
        :root {
            --wrap: 1920px;
            --pad: 120px
        }

        [data-theme="light"] {
            --bg: #ffffff;
            --fg: #000000;
            --mute: #5c5c5c;
            --card: #ffffff;
            --line: #000000;
            --soft: #f2f2f2;
            --sh: 0 34px 60px -26px rgba(0, 0, 0, .45), 0 12px 22px -12px rgba(0, 0, 0, .25);
            --hl: #fef3c7;
            --hlb: #f59e0b
        }

        [data-theme="dark"] {
            --bg: #000000;
            --fg: #ffffff;
            --mute: #9a9a9a;
            --card: #121212;
            --line: #ffffff;
            --soft: #161616;
            --sh: 0 34px 60px -24px rgba(0, 0, 0, 1), 0 0 0 1px rgba(255, 255, 255, .14), 14px 14px 0 -2px rgba(255, 255, 255, .07);
            --hl: #3a2a00;
            --hlb: #f5c542
        }

        [data-theme="night"] {
            --bg: #17120c;
            --fg: #f3e6cf;
            --mute: #a8987e;
            --card: #211a11;
            --line: #f3e6cf;
            --soft: #1d1710;
            --sh: 0 34px 60px -24px rgba(0, 0, 0, .85), 0 0 0 1px rgba(243, 230, 207, .16), 14px 14px 0 -2px rgba(243, 230, 207, .07);
            --hl: #3a2a00;
            --hlb: #f5c542
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            border-radius: 0 !important
        }

        html {
            scroll-behavior: smooth
        }

        body {
            font-family: 'Poppins', sans-serif;
            font-size: 16px;
            line-height: 1.7;
            background: var(--bg);
            color: var(--fg);
            transition: background .5s, color .5s;
            overflow-x: hidden
        }

        a {
            color: inherit;
            text-decoration: none
        }

        button,
        input,
        select,
        textarea {
            font: inherit;
            color: inherit
        }

        :focus-visible {
            outline: 2px solid var(--fg);
            outline-offset: 3px
        }

        .wrap {
            max-width: var(--wrap);
            margin: 0 auto;
            padding-left: var(--pad);
            padding-right: var(--pad)
        }

        h1,
        h2,
        h3 {
            font-weight: 600;
            letter-spacing: -.03em;
            line-height: 1.08
        }

        .adminbar {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 100;
            background: var(--fg);
            color: var(--bg);
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 10px 20px;
            flex-wrap: wrap;
            box-shadow: 0 6px 18px -6px rgba(0, 0, 0, .5)
        }

        .adminbar .status {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
            font-weight: 600;
            letter-spacing: .5px;
            text-transform: uppercase;
            padding: 5px 12px;
            border: 1px solid currentColor
        }

        .adminbar .status i {
            width: 8px;
            height: 8px;
            background: #2ecc71;
            border-radius: 50%;
            display: block;
            animation: pulse 2s infinite
        }

        .adminbar .nav {
            display: flex;
            gap: 6px;
            flex-wrap: wrap
        }

        .adminbar .nav a {
            padding: 7px 14px;
            font-size: 13px;
            border: 1px solid transparent;
            opacity: .8;
            transition: opacity .25s, border-color .25s
        }

        .adminbar .nav a:hover,
        .adminbar .nav a.on {
            opacity: 1;
            border-color: currentColor
        }

        .adminbar .spacer {
            flex: 1
        }

        .adminbar .user {
            font-size: 13px;
            opacity: .75;
            word-break: break-all
        }

        .adminbar .acts {
            display: flex;
            gap: 6px
        }

        .adminbar .acts a,
        .adminbar .acts button {
            padding: 7px 14px;
            font-size: 13px;
            background: var(--bg);
            color: var(--fg);
            border: 1px solid var(--bg);
            cursor: pointer;
            font-weight: 500;
            transition: transform .2s
        }

        .adminbar .acts a:hover,
        .adminbar .acts button:hover {
            transform: translateY(-2px)
        }

        .adminbar .acts .red {
            background: #e74c3c;
            color: #fff;
            border-color: #e74c3c
        }

        @keyframes pulse {

            0%,
            100% {
                opacity: 1
            }

            50% {
                opacity: .3
            }
        }

        @media(max-width:800px) {
            .adminbar {
                padding: 8px 12px;
                gap: 8px
            }

            .adminbar .user {
                display: none
            }
        }

        .editable {
            position: relative;
            cursor: pointer;
            transition: background .2s, box-shadow .2s;
            padding: 2px 6px;
            margin: -2px -6px;
            border: 1px dashed transparent;
            border-radius: 3px !important
        }

        .editable:hover {
            background: var(--hl);
            border-color: var(--hlb)
        }

        .editable::after {
            content: "✎";
            position: absolute;
            right: 4px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 12px;
            color: var(--hlb);
            opacity: 0;
            transition: opacity .2s;
            pointer-events: none
        }

        .editable:hover::after {
            opacity: 1
        }

        .editable.big::after {
            right: 12px;
            font-size: 18px
        }

        .modal {
            position: fixed;
            inset: 0;
            z-index: 200;
            background: rgba(0, 0, 0, .7);
            display: none;
            align-items: center;
            justify-content: center;
            padding: 20px
        }

        .modal.on {
            display: flex
        }

        .modal .box {
            background: var(--card);
            border: 1px solid var(--line);
            box-shadow: var(--sh);
            width: 100%;
            max-width: 720px;
            max-height: 90vh;
            overflow: auto;
            padding: 32px
        }

        .modal .box h3 {
            font-size: 22px;
            margin-bottom: 6px
        }

        .modal .box .sub {
            color: var(--mute);
            font-size: 14px;
            margin-bottom: 20px
        }

        .modal .box label {
            display: block;
            font-size: 13px;
            color: var(--mute);
            margin-bottom: 14px
        }

        .modal .box .in {
            display: block;
            width: 100%;
            margin-top: 6px;
            padding: 12px 14px;
            background: var(--bg);
            border: 1px solid var(--line);
            outline: 0;
            font-size: 15px;
            transition: box-shadow .25s
        }

        .modal .box .in:focus {
            box-shadow: 5px 5px 0 var(--fg)
        }

        .modal .box textarea.in {
            min-height: 140px;
            resize: vertical;
            line-height: 1.7
        }

        .modal .foot {
            display: flex;
            gap: 10px;
            margin-top: 20px;
            flex-wrap: wrap
        }

        .modal .foot button {
            padding: 12px 24px;
            border: 1px solid var(--fg);
            cursor: pointer;
            font-weight: 500;
            font-size: 14px;
            transition: transform .2s
        }

        .modal .foot button:hover {
            transform: translateY(-2px)
        }

        .modal .foot .primary {
            background: var(--fg);
            color: var(--bg)
        }

        .modal .foot .ghost {
            background: transparent;
            color: var(--fg)
        }

        main {
            padding-top: 70px
        }

        .hero {
            padding-top: 80px;
            padding-bottom: 100px;
            position: relative;
            overflow: hidden
        }

        .hero .wrap {
            display: grid;
            grid-template-columns: 1.35fr 1fr;
            gap: 80px;
            align-items: center;
            position: relative
        }

        .hero h1 {
            font-size: clamp(44px, 5.6vw, 108px)
        }

        .hero p.sub {
            max-width: 620px;
            margin: 34px 0 44px;
            color: var(--mute);
            font-size: 18px
        }

        .cta {
            display: flex;
            gap: 16px;
            flex-wrap: wrap
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 16px 34px;
            border: 1px solid var(--fg);
            background: var(--fg);
            color: var(--bg);
            font-weight: 500;
            cursor: pointer;
            font-size: 14px;
            transition: transform .25s
        }

        .btn:hover {
            transform: translateY(-3px)
        }

        .btn.ghost {
            background: transparent;
            color: var(--fg)
        }

        .stage {
            position: relative;
            height: 520px;
            display: grid;
            place-items: center
        }

        .panel {
            width: 360px;
            padding: 40px;
            background: var(--card);
            box-shadow: var(--sh);
            border: 1px solid var(--line);
            text-align: center
        }

        .panel .big {
            font-size: 64px;
            font-weight: 600;
            letter-spacing: -.05em;
            line-height: 1;
            color: var(--fg)
        }

        .panel .stars {
            letter-spacing: 4px;
            margin: 10px 0 24px;
            font-size: 20px
        }

        .panel dl {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            border-top: 1px solid var(--line);
            padding-top: 22px
        }

        .panel dt {
            font-size: 13px;
            color: var(--mute)
        }

        .panel dd {
            font-size: 26px;
            font-weight: 600
        }

        .panel .lbl {
            font-size: 13px;
            color: var(--mute);
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 8px;
            font-weight: 600
        }

        section {
            padding-top: 90px;
            padding-bottom: 20px
        }

        .head {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            gap: 40px;
            margin-bottom: 50px;
            border-bottom: 1px solid var(--line);
            padding-bottom: 26px;
            flex-wrap: wrap
        }

        .head h2 {
            font-size: clamp(30px, 3.2vw, 52px)
        }

        .head p {
            max-width: 460px;
            color: var(--mute)
        }

        .about {
            display: grid;
            grid-template-columns: 1.2fr 1fr;
            gap: 80px;
            align-items: start
        }

        .about h3 {
            font-size: clamp(28px, 2.6vw, 42px);
            margin-bottom: 24px
        }

        .about p {
            color: var(--mute);
            font-size: 18px;
            margin-bottom: 18px;
            max-width: 760px
        }

        .steps {
            display: grid;
            gap: 24px
        }

        .step {
            display: flex;
            gap: 24px;
            padding: 26px 30px;
            background: var(--card);
            border: 1px solid var(--line);
            box-shadow: var(--sh)
        }

        .step b {
            font-size: 40px;
            font-weight: 600;
            line-height: 1;
            min-width: 40px
        }

        .step h4 {
            font-size: 19px;
            font-weight: 600;
            margin-bottom: 4px
        }

        .step p {
            font-size: 15px;
            margin: 0
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 32px
        }

        .card {
            background: var(--card);
            border: 1px solid var(--line);
            padding: 32px 28px 26px;
            display: flex;
            flex-direction: column;
            min-height: 320px;
            box-shadow: var(--sh);
            position: relative
        }

        .card .tag {
            font-size: 13px;
            color: var(--mute);
            border: 1px solid var(--line);
            align-self: flex-start;
            padding: 2px 12px;
            margin-bottom: 22px
        }

        .card h3 {
            font-size: 22px;
            margin-bottom: 12px
        }

        .card p {
            color: var(--mute);
            flex: 1;
            font-size: 15px
        }

        .card .row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 24px;
            padding-top: 20px;
            border-top: 1px solid var(--line);
            gap: 10px;
            flex-wrap: wrap
        }

        .card .price {
            font-weight: 600;
            font-size: 17px
        }

        .card .acts {
            display: flex;
            gap: 6px;
            flex-wrap: wrap
        }

        .card .mini {
            padding: 6px 12px;
            font-size: 12px;
            border: 1px solid var(--line);
            background: transparent;
            cursor: pointer;
            transition: background .2s, color .2s
        }

        .card .mini:hover {
            background: var(--fg);
            color: var(--bg)
        }

        .card .mini.edit {
            background: var(--fg);
            color: var(--bg)
        }

        .card .mini.del {
            border-style: dashed
        }

        .addcard {
            grid-column: 1/-1;
            border: 1px dashed var(--line);
            padding: 30px;
            text-align: center;
            background: var(--card)
        }

        .rgrid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 32px
        }

        .rev {
            background: var(--card);
            border: 1px solid var(--line);
            padding: 28px;
            box-shadow: var(--sh);
            display: flex;
            flex-direction: column;
            gap: 14px;
            position: relative
        }

        .rev.pending {
            border-left: 4px solid #f5c542
        }

        .rev.rejected {
            border-left: 4px solid #e74c3c;
            opacity: .65
        }

        .rev.approved {
            border-left: 4px solid #2ecc71
        }

        .rev .stars {
            font-size: 18px;
            letter-spacing: 3px
        }

        .rev q {
            font-size: 16px;
            line-height: 1.7;
            quotes: none;
            flex: 1
        }

        .rev footer {
            border-top: 1px solid var(--line);
            padding-top: 14px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap
        }

        .rev footer b {
            font-weight: 600
        }

        .rev footer small {
            color: var(--mute)
        }

        .rev .acts {
            display: flex;
            gap: 6px;
            flex-wrap: wrap
        }

        .rev .mini {
            padding: 5px 10px;
            font-size: 11px;
            border: 1px solid var(--line);
            background: transparent;
            cursor: pointer;
            font-weight: 600;
            letter-spacing: .5px;
            text-transform: uppercase
        }

        .rev .mini.ok {
            background: #2ecc71;
            border-color: #2ecc71;
            color: #000
        }

        .rev .mini.warn {
            background: #f5c542;
            border-color: #f5c542;
            color: #000
        }

        .rev .mini.danger {
            border-style: dashed
        }

        .rev .mini:hover {
            transform: translateY(-2px)
        }

        .contact {
            display: grid;
            grid-template-columns: 1fr 1.2fr;
            gap: 40px
        }

        .cinfo,
        .cform {
            background: var(--card);
            border: 1px solid var(--line);
            box-shadow: var(--sh);
            padding: 40px
        }

        .cinfo dl {
            display: grid;
            gap: 22px;
            margin-bottom: 30px
        }

        .cinfo dt {
            font-size: 13px;
            color: var(--mute);
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 6px;
            font-weight: 600
        }

        .cinfo dd {
            font-size: 17px;
            font-weight: 500
        }

        .cinfo dd a {
            border-bottom: 1px solid var(--line)
        }

        .cform h3 {
            font-size: 24px;
            margin-bottom: 8px
        }

        .cform p {
            color: var(--mute);
            font-size: 14px;
            margin-bottom: 20px
        }

        .cform label {
            display: block;
            font-size: 13px;
            color: var(--mute);
            margin-bottom: 14px
        }

        .cform .in {
            display: block;
            width: 100%;
            margin-top: 6px;
            padding: 12px 14px;
            background: var(--bg);
            border: 1px solid var(--line);
            outline: 0;
            font-size: 15px
        }

        .cform .two {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 14px
        }

        .map-wrap {
            margin-top: 30px;
            border: 1px solid var(--line);
            background: var(--soft)
        }

        .map-wrap iframe {
            width: 100%;
            height: 300px;
            border: 0;
            display: block
        }

        .flash {
            position: fixed;
            top: 80px;
            left: 50%;
            transform: translateX(-50%);
            z-index: 150;
            background: #2ecc71;
            color: #000;
            padding: 12px 26px;
            font-weight: 600;
            box-shadow: 0 10px 30px -8px rgba(0, 0, 0, .5);
            animation: drop 3s ease forwards
        }

        @keyframes drop {
            0% {
                top: -100px;
                opacity: 0
            }

            10% {
                top: 80px;
                opacity: 1
            }

            90% {
                top: 80px;
                opacity: 1
            }

            100% {
                top: -100px;
                opacity: 0
            }
        }

        .acard {
            background: var(--card);
            border: 1px solid var(--line);
            padding: 34px;
            box-shadow: var(--sh);
            margin-bottom: 26px
        }

        .acard h3 {
            font-size: 20px;
            margin-bottom: 16px
        }

        .acard label {
            display: block;
            font-size: 13px;
            color: var(--mute);
            margin-bottom: 14px
        }

        .acard .in {
            display: block;
            width: 100%;
            margin-top: 6px;
            padding: 12px 14px;
            background: var(--bg);
            border: 1px solid var(--line);
            outline: 0;
            font-size: 15px;
            transition: box-shadow .25s
        }

        .acard .in:focus {
            box-shadow: 5px 5px 0 var(--fg)
        }

        .acard .two {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 14px
        }

        @media(max-width:1180px) {
            :root {
                --pad: 60px
            }

            .grid,
            .rgrid {
                grid-template-columns: repeat(2, 1fr)
            }

            .hero .wrap,
            .about,
            .contact {
                grid-template-columns: 1fr;
                gap: 40px
            }

            .fgrid {
                grid-template-columns: 1fr 1fr
            }
        }

        @media(max-width:720px) {
            :root {
                --pad: 20px
            }

            .grid,
            .rgrid,
            .two,
            .acard .two,
            .fgrid {
                grid-template-columns: 1fr
            }

            .hero h1 {
                font-size: 44px
            }

            .panel {
                width: 280px
            }

            .cform,
            .cinfo,
            .acard {
                padding: 26px
            }
        }
    </style>
</head>

<body>

    <div class="adminbar">
        <span class="status"><i></i> Editing live site</span>
        <nav class="nav">
            <a href="?p=site" class="<?= $panel === 'site' ? 'on' : '' ?>">Site editor</a>
            <a href="?p=cards" class="<?= $panel === 'cards' ? 'on' : '' ?>">Cards (<?= $servicesTotal ?>)</a>
            <a href="?p=reviews" class="<?= $panel === 'reviews' ? 'on' : '' ?>">Reviews
                <?= $reviewsPending ? '<b>(' . $reviewsPending . ' new)</b>' : '' ?></a>
            <a href="?p=account" class="<?= $panel === 'account' ? 'on' : '' ?>">Account</a>
        </nav>
        <span class="spacer"></span>
        <span class="user"><?= e($_SESSION['admin_name'] ?? 'Admin') ?> ·
            <?= e($_SESSION['admin_email'] ?? '') ?></span>
        <div class="acts">
            <a href="<?= e(SITE_FILE) ?>" target="_blank">View site</a>
            <form method="post" style="display:inline">
                <input type="hidden" name="t" value="<?= e($T) ?>">
                <input type="hidden" name="action" value="logout">
                <button class="red">Log out</button>
            </form>
        </div>
    </div>

    <?php if ($flash): ?>
        <div class="flash"><?= e($flash) ?></div><?php endif; ?>

    <main>

        <?php if ($panel === 'site'): ?>

            <div class="hero">
                <div class="wrap">
                    <div>
                        <h1>
                            <span class="editable big" data-key="headline" data-type="text"><?= e($st['headline']) ?></span>
                        </h1>
                        <p class="sub editable" data-key="sub" data-type="textarea"><?= e($st['sub']) ?></p>
                        <div class="cta">
                            <a class="btn" href="<?= e(SITE_FILE) ?>#services" target="_blank">Preview site →</a>
                        </div>
                    </div>
                    <div class="stage">
                        <div class="panel">
                            <div class="lbl">Average rating</div>
                            <div class="big"><?= $avg ?: '—' ?></div>
                            <div class="stars"><?= stars((int) round($avg ?: 5)) ?></div>
                            <dl>
                                <div>
                                    <dt>Cards</dt>
                                    <dd><?= $servicesTotal ?></dd>
                                </div>
                                <div>
                                    <dt>Reviews</dt>
                                    <dd><?= count($reviews) ?></dd>
                                </div>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>

            <section id="about">
                <div class="wrap">
                    <div class="head">
                        <h2 class="editable" data-key="about_title" data-type="text"><?= e($st['about_title']) ?></h2>
                        <p>About section — click to edit</p>
                    </div>
                    <div class="about">
                        <div>
                            <h3>Your story</h3>
                            <?php foreach (preg_split('/\R{2,}/', (string) $st['about_text']) as $para):
                                if (trim($para) === '')
                                    continue; ?>
                                <p><?= nl2br(e(trim($para))) ?></p>
                            <?php endforeach; ?>
                            <p class="editable" data-key="about_text" data-type="textarea"
                                style="border:1px dashed var(--hlb);background:var(--hl);padding:12px;color:var(--fg);margin-top:14px;font-size:14px">
                                ✎ Edit full about text (all paragraphs)
                            </p>
                        </div>
                        <div class="steps">
                            <div class="step"><b>1</b>
                                <div>
                                    <h4>Pick your services</h4>
                                    <p>Select one or more cards on the site.</p>
                                </div>
                            </div>
                            <div class="step"><b>2</b>
                                <div>
                                    <h4>Message on WhatsApp</h4>
                                    <p>Selection arrives as ready-made message.</p>
                                </div>
                            </div>
                            <div class="step"><b>3</b>
                                <div>
                                    <h4>Get a clear plan</h4>
                                    <p>Reply with scope, timeline and price.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section id="contact">
                <div class="wrap">
                    <div class="head">
                        <h2>Contact & brand</h2>
                        <p>Click any field to edit — save and it goes live immediately.</p>
                    </div>
                    <div class="contact">
                        <div class="cinfo">
                            <div class="lbl"
                                style="font-size:13px;color:var(--mute);text-transform:uppercase;letter-spacing:1px;font-weight:600;margin-bottom:14px">
                                Brand</div>
                            <p style="margin-bottom:24px"><b class="editable" data-key="brand" data-type="text"
                                    style="font-size:22px;font-weight:700"><?= e($st['brand']) ?></b></p>

                            <dl>
                                <div>
                                    <dt>Address</dt>
                                    <dd class="editable" data-key="address" data-type="textarea"><?= e($st['address']) ?>
                                    </dd>
                                </div>
                                <div>
                                    <dt>Email</dt>
                                    <dd class="editable" data-key="email" data-type="email"><?= e($st['email']) ?></dd>
                                </div>
                                <div>
                                    <dt>Phone</dt>
                                    <dd class="editable" data-key="phone" data-type="text"><?= e($st['phone']) ?></dd>
                                </div>
                                <div>
                                    <dt>Business hours</dt>
                                    <dd class="editable" data-key="hours" data-type="text"><?= e($st['hours']) ?></dd>
                                </div>
                                <div>
                                    <dt>WhatsApp (digits, with country code)</dt>
                                    <dd class="editable" data-key="whatsapp" data-type="text"><?= e($st['whatsapp']) ?></dd>
                                </div>
                            </dl>
                        </div>
                        <div class="cform">
                            <h3>Map & socials</h3>
                            <p>Change the Google Map embed URL and social links.</p>
                            <label>Map embed URL
                                <input class="in" readonly value="<?= e($st['map_embed']) ?>"
                                    onclick="openEdit('map_embed','url','Map embed URL','Google Maps → Share → Embed a map → copy only the src URL.')">
                            </label>
                            <div class="two">
                                <?php foreach ($socials as $k => $label): ?>
                                    <label><?= e($label) ?>
                                        <input class="in" readonly value="<?= e($st[$k] ?? '') ?>"
                                            onclick="openEdit('<?= $k ?>','<?= $k === 'gmail' ? 'email' : 'url' ?>','<?= e($label) ?>','<?= $k === 'gmail' ? 'Enter your Gmail address — clicking the icon on site will open email compose.' : '' ?>')">
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    <?php if (!empty($st['map_embed'])): ?>
                        <div class="map-wrap">
                            <iframe src="<?= e($st['map_embed']) ?>" loading="lazy"></iframe>
                        </div>
                    <?php endif; ?>
                </div>
            </section>

            <div style="padding:60px 0;text-align:center;color:var(--mute)">
                <p style="font-size:14px">↑ End of live editor. Use the top bar to switch to <a href="?p=cards"
                        style="border-bottom:1px solid var(--line)">Cards</a> or <a href="?p=reviews"
                        style="border-bottom:1px solid var(--line)">Reviews</a>.</p>
            </div>

        <?php elseif ($panel === 'cards'): ?>

            <section style="padding-top:40px">
                <div class="wrap">
                    <div class="head">
                        <h2>Service cards (<?= $servicesTotal ?>)</h2>
                        <p>Saved to MySQL <code>services_db.card</code>. Changes appear on the site instantly.</p>
                    </div>

                    <form class="addcard" method="post">
                        <input type="hidden" name="t" value="<?= e($T) ?>">
                        <input type="hidden" name="action" value="card_insert">
                        <h3 style="margin-bottom:18px;font-size:20px">Add new card</h3>
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;text-align:left">
                            <label style="display:block;font-size:13px;color:var(--mute)">Heading
                                <input class="in" name="heading" required
                                    style="display:block;width:100%;margin-top:6px;padding:12px;background:var(--bg);border:1px solid var(--line)">
                            </label>
                            <label style="display:block;font-size:13px;color:var(--mute)">Category
                                <input class="in" name="category"
                                    style="display:block;width:100%;margin-top:6px;padding:12px;background:var(--bg);border:1px solid var(--line)">
                            </label>
                        </div>
                        <label style="display:block;font-size:13px;color:var(--mute);text-align:left;margin-top:14px">Sub
                            heading
                            <textarea class="in" name="sub_heading"
                                style="display:block;width:100%;margin-top:6px;padding:12px;background:var(--bg);border:1px solid var(--line);min-height:90px;resize:vertical"></textarea>
                        </label>
                        <div
                            style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:14px;margin-top:14px;text-align:left">
                            <label style="display:block;font-size:13px;color:var(--mute)">Price
                                <input class="in" name="price"
                                    style="display:block;width:100%;margin-top:6px;padding:12px;background:var(--bg);border:1px solid var(--line)">
                            </label>
                            <label style="display:block;font-size:13px;color:var(--mute)">Select label
                                <input class="in" name="select" placeholder="Select"
                                    style="display:block;width:100%;margin-top:6px;padding:12px;background:var(--bg);border:1px solid var(--line)">
                            </label>
                            <label style="display:block;font-size:13px;color:var(--mute)">WhatsApp
                                <input class="in" name="whatsapp" placeholder="8801700000000"
                                    style="display:block;width:100%;margin-top:6px;padding:12px;background:var(--bg);border:1px solid var(--line)">
                            </label>
                        </div>
                        <button class="btn" type="submit" style="margin-top:20px">Add card</button>
                    </form>

                    <div class="grid" style="margin-top:40px">
                        <?php foreach ($cards as $c): ?>
                            <div class="card">
                                <?php if ($c['category']): ?><span class="tag"><?= e($c['category']) ?></span><?php endif; ?>
                                <h3><?= e($c['heading']) ?></h3>
                                <p><?= e($c['sub_heading']) ?></p>
                                <div class="row">
                                    <span class="price"><?= e($c['price']) ?></span>
                                    <div class="acts">
                                        <button class="mini edit"
                                            onclick='openCardEdit(<?= json_encode($c, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>Edit</button>
                                        <form method="post" style="display:inline"
                                            onsubmit="return confirm('Delete this card?')">
                                            <input type="hidden" name="t" value="<?= e($T) ?>">
                                            <input type="hidden" name="action" value="card_delete">
                                            <input type="hidden" name="heading" value="<?= e($c['heading']) ?>">
                                            <button class="mini del">Delete</button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        <?php if (!$cards): ?>
                            <div class="addcard" style="grid-column:1/-1">
                                <p style="color:var(--mute)">No cards yet. Add your first one above.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </section>

        <?php elseif ($panel === 'reviews'): ?>

            <section style="padding-top:40px">
                <div class="wrap">
                    <div class="head">
                        <h2>Client reviews (<?= count($reviews) ?>)</h2>
                        <p><?= $reviewsPending ?> pending · <?= $reviewsApproved ?> approved. Approve/reject to publish on
                            site.</p>
                    </div>

                    <form class="addcard" method="post" style="margin-bottom:30px">
                        <input type="hidden" name="t" value="<?= e($T) ?>">
                        <input type="hidden" name="action" value="review_add">
                        <h3 style="margin-bottom:16px;font-size:20px">Add a review manually</h3>
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;text-align:left">
                            <label style="display:block;font-size:13px;color:var(--mute)">Name
                                <input name="name" required
                                    style="display:block;width:100%;margin-top:6px;padding:12px;background:var(--bg);border:1px solid var(--line)">
                            </label>
                            <label style="display:block;font-size:13px;color:var(--mute)">Company
                                <input name="company"
                                    style="display:block;width:100%;margin-top:6px;padding:12px;background:var(--bg);border:1px solid var(--line)">
                            </label>
                        </div>
                        <label style="display:block;font-size:13px;color:var(--mute);text-align:left;margin-top:14px">Rating
                            <select name="rating"
                                style="display:block;width:100%;margin-top:6px;padding:12px;background:var(--bg);border:1px solid var(--line)">
                                <?php for ($i = 5; $i >= 1; $i--): ?>
                                    <option value="<?= $i ?>"><?= stars($i) ?> (<?= $i ?>)</option><?php endfor; ?>
                            </select>
                        </label>
                        <label style="display:block;font-size:13px;color:var(--mute);text-align:left;margin-top:14px">Review
                            <textarea name="text" required
                                style="display:block;width:100%;margin-top:6px;padding:12px;background:var(--bg);border:1px solid var(--line);min-height:90px;resize:vertical"></textarea>
                        </label>
                        <button class="btn" type="submit" style="margin-top:16px">Add review</button>
                    </form>

                    <div class="rgrid">
                        <?php foreach ($reviews as $r):
                            $stat = revStatus($r); ?>
                            <div class="rev <?= e($stat) ?>">
                                <div class="stars"><?= stars((int) $r['rating']) ?></div>
                                <q><?= e($r['text']) ?></q>
                                <footer>
                                    <div>
                                        <b><?= e($r['name']) ?></b>
                                        <?php if ($r['company']): ?><small> · <?= e($r['company']) ?></small><?php endif; ?>
                                    </div>
                                    <span class="mini"
                                        style="text-transform:uppercase;font-weight:600;letter-spacing:.5px"><?= e($stat) ?></span>
                                </footer>
                                <div class="acts">
                                    <form method="post" style="display:inline">
                                        <input type="hidden" name="t" value="<?= e($T) ?>">
                                        <input type="hidden" name="id" value="<?= e($r['id']) ?>">
                                        <?php if ($stat !== 'approved'): ?>
                                            <button class="mini ok" name="action" value="review_approve">Approve</button>
                                        <?php endif; ?>
                                        <?php if ($stat !== 'rejected'): ?>
                                            <button class="mini warn" name="action" value="review_reject">Reject</button>
                                        <?php endif; ?>
                                        <button class="mini danger" name="action" value="review_delete"
                                            onclick="return confirm('Delete this review?')">Delete</button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        <?php if (!$reviews): ?>
                            <div class="addcard" style="grid-column:1/-1">
                                <p style="color:var(--mute)">No reviews yet.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </section>

        <?php else: ?>

            <section style="padding-top:40px">
                <div class="wrap" style="max-width:760px">
                    <div class="head">
                        <h2>Account settings</h2>
                        <p>Signed in as <b><?= e($_SESSION['admin_email'] ?? '') ?></b></p>
                    </div>

                    <!-- 1. NAME CHANGE -->
                    <form method="post" class="acard">
                        <input type="hidden" name="t" value="<?= e($T) ?>">
                        <input type="hidden" name="action" value="profile_save">
                        <h3>Display name</h3>
                        <p style="color:var(--mute);font-size:13px;margin-bottom:14px">
                            Currently: <b><?= e($_SESSION['admin_name'] ?? 'Administrator') ?></b><br>
                            This name appears in the top bar and sidebar.
                        </p>
                        <label>New display name
                            <input class="in" name="name" value="<?= e($_SESSION['admin_name'] ?? 'Administrator') ?>"
                                required maxlength="120">
                        </label>
                        <button class="btn" type="submit" style="margin-top:16px">Save name</button>
                    </form>

                    <!-- 2. LOGIN EMAIL CHANGE -->
                    <form method="post" class="acard">
                        <input type="hidden" name="t" value="<?= e($T) ?>">
                        <input type="hidden" name="action" value="login_email_change">
                        <h3>Login email</h3>
                        <p style="color:var(--mute);font-size:13px;margin-bottom:14px">
                            Currently: <b><?= e($_SESSION['admin_email'] ?? '') ?></b><br>
                            Changing this updates <code><?= e(DB_NAME) ?>.<?= e(DB_TABLE) ?></code> directly. Use the new
                            email next time you sign in.
                        </p>
                        <label>New email address
                            <input class="in" type="email" name="new_email" required placeholder="newadmin@example.com"
                                maxlength="190">
                        </label>
                        <label>Confirm with current password
                            <input class="in" type="password" name="current_password" required
                                autocomplete="current-password">
                        </label>
                        <button class="btn" type="submit" style="margin-top:16px">Update login email</button>
                    </form>

                    <!-- 3. PASSWORD CHANGE -->
                    <form method="post" class="acard">
                        <input type="hidden" name="t" value="<?= e($T) ?>">
                        <input type="hidden" name="action" value="password_change">
                        <h3>Change password</h3>
                        <p style="color:var(--mute);font-size:14px;margin-bottom:14px">
                            Stored hashed in <code><?= e(DB_NAME) ?>.<?= e(DB_TABLE) ?></code>.
                        </p>
                        <label>Current password
                            <input class="in" type="password" name="current" required autocomplete="current-password">
                        </label>
                        <label>New password (8+ characters)
                            <input class="in" type="password" name="new" required minlength="8" autocomplete="new-password">
                        </label>
                        <label>Confirm new password
                            <input class="in" type="password" name="confirm" required minlength="8"
                                autocomplete="new-password">
                        </label>
                        <button class="btn" type="submit">Update password</button>
                    </form>
                </div>
            </section>

        <?php endif; ?>

    </main>

    <!-- ============ EDIT MODAL ============ -->
    <div class="modal" id="modal">
        <div class="box">
            <h3 id="mTitle">Edit</h3>
            <p class="sub" id="mSub"></p>
            <input type="hidden" id="mKey">
            <label id="mWrap">
                <span id="mLabel"></span>
                <input class="in" id="mInput" type="text">
                <textarea class="in" id="mText" style="display:none"></textarea>
            </label>
            <div class="foot">
                <button class="primary" id="mSave" type="button">Save</button>
                <button class="ghost" id="mCancel" type="button">Cancel</button>
            </div>
        </div>
    </div>

    <!-- Card edit modal -->
    <div class="modal" id="cardModal">
        <div class="box">
            <h3>Edit card</h3>
            <p class="sub">Changes will save to MySQL <code>card</code> table.</p>
            <form method="post">
                <input type="hidden" name="t" value="<?= e($T) ?>">
                <input type="hidden" name="action" value="card_update">
                <input type="hidden" name="old_heading" id="cOld">
                <label>Heading<input class="in" name="heading" id="cHeading" required></label>
                <label>Category<input class="in" name="category" id="cCategory"></label>
                <label>Sub heading<textarea class="in" name="sub_heading" id="cSub"
                        style="min-height:120px"></textarea></label>
                <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:14px">
                    <label>Price<input class="in" name="price" id="cPrice"></label>
                    <label>Select<input class="in" name="select" id="cSelect"></label>
                    <label>WhatsApp<input class="in" name="whatsapp" id="cWhats"></label>
                </div>
                <div class="foot">
                    <button class="primary" type="submit">Save card</button>
                    <button class="ghost" type="button"
                        onclick="document.getElementById('cardModal').classList.remove('on')">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        (function () {
            var root = document.documentElement, btns = document.querySelectorAll('.themes button');
            function setTheme(t) {
                root.setAttribute('data-theme', t); try {localStorage.setItem('theme', t)} catch (e) { }
                btns.forEach(function (b) {b.classList.toggle('on', b.dataset.set === t)})
            }
            btns.forEach(function (b) {b.addEventListener('click', function () {setTheme(b.dataset.set)})});
            setTheme(root.getAttribute('data-theme') || 'dark');
        })();

        var modal = document.getElementById('modal'),
            mKey = document.getElementById('mKey'),
            mTitle = document.getElementById('mTitle'),
            mSub = document.getElementById('mSub'),
            mLabel = document.getElementById('mLabel'),
            mInput = document.getElementById('mInput'),
            mText = document.getElementById('mText'),
            mSave = document.getElementById('mSave'),
            mCancel = document.getElementById('mCancel');

        function openEdit(key, type, title, hint) {
            mKey.value = key;
            mTitle.textContent = title || ('Edit ' + key);
            mSub.textContent = hint || '';
            var el = document.querySelector('.editable[data-key="' + key + '"]');
            var current = '';
            if (el) {
                if (el.dataset.type === 'textarea' || type === 'textarea') current = el.textContent.trim();
                else current = el.textContent.trim();
            } else {
                var ro = document.querySelector('input[onclick*="openEdit(\'' + key + '\'"]');
                if (ro) current = ro.value;
            }
            mLabel.textContent = title || key;
            if (type === 'textarea') {mInput.style.display = 'none'; mText.style.display = 'block'; mText.value = current; setTimeout(function () {mText.focus()}, 80)}
            else {mText.style.display = 'none'; mInput.style.display = 'block'; mInput.type = (type === 'email' ? 'email' : (type === 'url' ? 'url' : 'text')); mInput.value = current; setTimeout(function () {mInput.focus()}, 80)}
            modal.classList.add('on');
        }
        function closeEdit() {modal.classList.remove('on');}
        mCancel.addEventListener('click', closeEdit);
        modal.addEventListener('click', function (ev) {if (ev.target === modal) closeEdit()});

        mSave.addEventListener('click', function () {
            var key = mKey.value;
            var val = (mText.style.display === 'block') ? mText.value : mInput.value;
            mSave.disabled = true; mSave.textContent = 'Saving…';
            var fd = new FormData();
            fd.append('t', '<?= e($T) ?>');
            fd.append('action', 'save_setting');
            fd.append('key', key);
            fd.append('value', val);
            fetch('', {method: 'POST', body: fd}).then(function (r) {return r.json()}).then(function (res) {
                mSave.disabled = false; mSave.textContent = 'Save';
                if (res.ok) {
                    var el = document.querySelector('.editable[data-key="' + key + '"]');
                    if (el) el.textContent = res.value;
                    var ro = document.querySelector('input[onclick*="openEdit(\'' + key + '\'"]');
                    if (ro) ro.value = res.value;
                    closeEdit();
                    showToast('Saved — live on site ✓');
                } else {showToast('Could not save', true);}
            }).catch(function () {mSave.disabled = false; mSave.textContent = 'Save'; showToast('Error', true);});
        });

        document.querySelectorAll('.editable').forEach(function (el) {
            el.addEventListener('click', function () {
                openEdit(el.dataset.key, el.dataset.type || 'text', el.dataset.key, 'Edit and save — live on site');
            });
        });

        function openCardEdit(c) {
            document.getElementById('cOld').value = c.heading;
            document.getElementById('cHeading').value = c.heading;
            document.getElementById('cCategory').value = c.category || '';
            document.getElementById('cSub').value = c.sub_heading || '';
            document.getElementById('cPrice').value = c.price || '';
            document.getElementById('cSelect').value = c['select'] || '';
            document.getElementById('cWhats').value = c.whatsapp || '';
            document.getElementById('cardModal').classList.add('on');
        }

        function showToast(msg, err) {
            var d = document.createElement('div');
            d.className = 'flash';
            if (err) d.style.background = '#e74c3c';
            d.textContent = msg;
            document.body.appendChild(d);
            setTimeout(function () {d.remove()}, 3000);
        }
    </script>
</body>

</html>