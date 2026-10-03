<?php
/* =====================================================================
   NOIR SERVICES - Landing page (index.php)
   ---------------------------------------------------------------------
   • Brand, hero, about, reviews, contact  → from data.json
   • Service cards                          → from MySQL `card` table
   • Social icons (SVG)                     → footer
   • Gmail icon                             → opens mail client (mailto:)
   • Review submission                      → goes to admin for approval
   ---------------------------------------------------------------------
   Admin panel: admin_login.php → adash.php
   ===================================================================== */

ob_start();
session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax', 'path' => '/']);
session_start();

const DATA_FILE = __DIR__ . '/data.json';
const DB_HOST = 'localhost';
const DB_NAME = 'services_db';
const DB_USER = 'root';
const DB_PASS = '';
const CARD_TABLE = 'card';

/* ---------- defaults ---------- */
function defaults(): array
{
    return [
        'settings' => [
            'brand' => 'Noir Services',
            'headline' => 'Business services, delivered with precision.',
            'sub' => 'Choose the services your company needs, then talk to our team directly on WhatsApp. No forms, no waiting.',
            'whatsapp' => '8801936602289',
            'about_title' => 'A small team that treats every client like a long-term partner.',
            'about_text' => "We help growing companies with the services they need most: strategy, technology, marketing, brand and finance.\n\nInstead of long proposals and forms, you pick what you need and talk to our team directly on WhatsApp. You get quick answers, a clear scope and a fair price.",
            'address' => 'Level 5, Business Tower, Gulshan Avenue, Dhaka 1212, Bangladesh',
            'email' => 'hello@example.com',
            'phone' => '+880 1700-000000',
            'hours' => 'Sun to Thu, 9:00 am to 6:00 pm',
            'map_embed' => '',
            'facebook' => '',
            'linkedin' => '',
            'instagram' => '',
            'x' => '',
            'youtube' => '',
            'gmail' => '',
        ],
        'reviews' => [
            ['id' => 'r1', 'name' => 'Rahim Chowdhury', 'company' => 'Delta Textiles', 'rating' => 5, 'text' => 'They rebuilt our ordering portal in three weeks. Communication over WhatsApp made everything fast.', 'status' => 'approved', 'visible' => true],
            ['id' => 'r2', 'name' => 'Sarah Imran', 'company' => 'Greenline Foods', 'rating' => 5, 'text' => 'Professional from the first message. Our brand finally looks like the company we are.', 'status' => 'approved', 'visible' => true],
            ['id' => 'r3', 'name' => 'Mahmud Hasan', 'company' => 'Apex Logistics', 'rating' => 4, 'text' => 'Reliable accounting support. Clear reports and no surprises at tax time.', 'status' => 'approved', 'visible' => true],
        ],
    ];
}
function load(): array
{
    if (!file_exists(DATA_FILE))
        save(defaults());
    $d = json_decode((string) file_get_contents(DATA_FILE), true);
    if (!is_array($d))
        return defaults();
    $def = defaults();
    $d['settings'] = array_merge($def['settings'], $d['settings'] ?? []);
    $d['reviews'] = $d['reviews'] ?? $def['reviews'];
    return $d;
}
function save(array $d): void
{
    file_put_contents(DATA_FILE, json_encode($d, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
}

/* ---------- MySQL card fetch ---------- */
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
function cardsAll(): array
{
    try {
        $q = db()->query('SELECT `category`, `heading`, `sub_heading`, `price`, `select`, `whatsapp` FROM `' . CARD_TABLE . '` ORDER BY `heading` ASC');
        return $q->fetchAll();
    } catch (Throwable $e) {
        return [];
    }
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
function clean($s, int $max): string
{
    return mb_substr(trim(strip_tags((string) $s)), 0, $max);
}
function stars(int $n): string
{
    $n = max(0, min(5, $n));
    return str_repeat('★', $n) . str_repeat('☆', 5 - $n);
}
function revStatus(array $r): string
{
    if (isset($r['status']) && in_array($r['status'], ['pending', 'approved', 'rejected'], true))
        return $r['status'];
    return !empty($r['visible']) ? 'approved' : 'pending';
}

/* ---------- social SVG icons (black & white) ---------- */
function socialIcon(string $key): string
{
    $icons = [
        'facebook' => '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M22 12.06C22 6.5 17.52 2 12 2S2 6.5 2 12.06c0 5.02 3.66 9.18 8.44 9.94v-7.03H7.9v-2.91h2.54V9.85c0-2.51 1.49-3.89 3.77-3.89 1.09 0 2.24.2 2.24.2v2.46h-1.26c-1.24 0-1.63.77-1.63 1.56v1.88h2.78l-.44 2.91h-2.34V22c4.78-.76 8.44-4.92 8.44-9.94Z"/></svg>',
        'linkedin' => '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M20.45 20.45h-3.56v-5.57c0-1.33-.02-3.04-1.85-3.04-1.85 0-2.13 1.45-2.13 2.94v5.67H9.35V9h3.41v1.56h.05c.48-.9 1.64-1.85 3.37-1.85 3.6 0 4.27 2.37 4.27 5.46v6.28ZM5.34 7.43a2.06 2.06 0 1 1 0-4.13 2.06 2.06 0 0 1 0 4.13ZM7.12 20.45H3.56V9h3.56v11.45ZM22.22 0H1.77C.79 0 0 .77 0 1.73v20.54C0 23.23.79 24 1.77 24h20.45c.98 0 1.78-.77 1.78-1.73V1.73C24 .77 23.2 0 22.22 0Z"/></svg>',
        'instagram' => '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2.16c3.2 0 3.58.01 4.85.07 1.17.05 1.8.25 2.23.41.56.22.96.48 1.38.9.42.42.68.82.9 1.38.16.42.36 1.06.41 2.23.06 1.27.07 1.65.07 4.85s-.01 3.58-.07 4.85c-.05 1.17-.25 1.8-.41 2.23a3.7 3.7 0 0 1-.9 1.38c-.42.42-.82.68-1.38.9-.42.16-1.06.36-2.23.41-1.27.06-1.65.07-4.85.07s-3.58-.01-4.85-.07c-1.17-.05-1.8-.25-2.23-.41a3.7 3.7 0 0 1-1.38-.9 3.7 3.7 0 0 1-.9-1.38c-.16-.42-.36-1.06-.41-2.23-.06-1.27-.07-1.65-.07-4.85s.01-3.58.07-4.85c.05-1.17.25-1.8.41-2.23.22-.56.48-.96.9-1.38.42-.42.82-.68 1.38-.9.42-.16 1.06-.36 2.23-.41C8.42 2.17 8.8 2.16 12 2.16Zm0-2.16C8.74 0 8.33.01 7.05.07 5.78.13 4.9.33 4.14.63a5.87 5.87 0 0 0-2.13 1.38A5.87 5.87 0 0 0 .63 4.14C.33 4.9.13 5.78.07 7.05.01 8.33 0 8.74 0 12s.01 3.67.07 4.95c.06 1.27.26 2.15.56 2.91.31.79.72 1.46 1.38 2.13a5.87 5.87 0 0 0 2.13 1.38c.76.3 1.64.5 2.91.56 1.28.06 1.69.07 4.95.07s3.67-.01 4.95-.07c1.27-.06 2.15-.26 2.91-.56a5.87 5.87 0 0 0 2.13-1.38 5.87 5.87 0 0 0 1.38-2.13c.3-.76.5-1.64.56-2.91.06-1.28.07-1.69.07-4.95s-.01-3.67-.07-4.95c-.06-1.27-.26-2.15-.56-2.91a5.87 5.87 0 0 0-1.38-2.13A5.87 5.87 0 0 0 19.86.63C19.1.33 18.22.13 16.95.07 15.67.01 15.26 0 12 0Zm0 5.84a6.16 6.16 0 1 0 0 12.32 6.16 6.16 0 0 0 0-12.32ZM12 16a4 4 0 1 1 0-8 4 4 0 0 1 0 8Zm7.85-10.4a1.44 1.44 0 1 1-2.88 0 1.44 1.44 0 0 1 2.88 0Z"/></svg>',
        'x' => '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231Zm-1.161 17.52h1.833L7.084 4.126H5.117Z"/></svg>',
        'youtube' => '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M23.5 6.19a3.02 3.02 0 0 0-2.12-2.14C19.5 3.55 12 3.55 12 3.55s-7.5 0-9.38.5A3.02 3.02 0 0 0 .5 6.19C0 8.07 0 12 0 12s0 3.93.5 5.81a3.02 3.02 0 0 0 2.12 2.14c1.88.5 9.38.5 9.38.5s7.5 0 9.38-.5a3.02 3.02 0 0 0 2.12-2.14C24 15.93 24 12 24 12s0-3.93-.5-5.81ZM9.55 15.57V8.43L15.82 12l-6.27 3.57Z"/></svg>',
        'whatsapp' => '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M17.47 14.38c-.3-.15-1.76-.87-2.03-.97-.27-.1-.47-.15-.67.15-.2.3-.77.97-.94 1.17-.17.2-.35.22-.65.07-.3-.15-1.26-.46-2.4-1.48-.89-.79-1.49-1.77-1.66-2.07-.17-.3-.02-.46.13-.61.13-.13.3-.35.45-.52.15-.17.2-.3.3-.5.1-.2.05-.37-.02-.52-.07-.15-.67-1.62-.92-2.22-.24-.58-.49-.5-.67-.51l-.57-.01c-.2 0-.52.07-.79.37-.27.3-1.04 1.02-1.04 2.48s1.07 2.88 1.22 3.08c.15.2 2.1 3.2 5.08 4.49.71.31 1.26.49 1.69.63.71.23 1.36.19 1.87.12.57-.09 1.76-.72 2.01-1.41.25-.7.25-1.29.17-1.41-.07-.12-.27-.2-.57-.35Zm-5.42 7.44h-.01a9.87 9.87 0 0 1-5.03-1.38l-.36-.21-3.74.98 1-3.65-.24-.37a9.86 9.86 0 0 1-1.51-5.26c0-5.45 4.44-9.88 9.9-9.88a9.83 9.83 0 0 1 7 2.9 9.83 9.83 0 0 1 2.9 7c0 5.45-4.44 9.88-9.9 9.88Zm8.42-18.3A11.82 11.82 0 0 0 12.05 0C5.5 0 .17 5.33.16 11.88c0 2.1.55 4.14 1.59 5.94L.06 24l6.33-1.66a11.87 11.87 0 0 0 5.66 1.44h.01c6.55 0 11.88-5.33 11.89-11.88a11.8 11.8 0 0 0-3.48-8.4Z"/></svg>',
        'gmail' => '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M24 5.457v13.909c0 .904-.732 1.636-1.636 1.636h-3.819V11.73L12 16.64l-6.545-4.91v9.273H1.636A1.636 1.636 0 0 1 0 19.366V5.457c0-2.023 2.309-3.178 3.927-1.964L5.455 4.64 12 9.548l6.545-4.91 1.528-1.145C21.69 2.28 24 3.434 24 5.457z"/></svg>',
    ];
    return $icons[$key] ?? '';
}

/* ---------- review submission ---------- */
$data = load();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals(token(), (string) ($_POST['t'] ?? ''))) {
        http_response_code(403);
        exit('Invalid request token.');
    }
    $a = $_POST['action'] ?? '';
    if ($a === 'review_add') {
        if (($_POST['website'] ?? '') === '') {
            $name = clean($_POST['name'] ?? '', 60);
            $text = clean($_POST['text'] ?? '', 500);
            $rating = (int) ($_POST['rating'] ?? 0);
            if ($name && $text && $rating >= 1 && $rating <= 5) {
                $data['reviews'][] = [
                    'id' => uniqid('r'),
                    'name' => $name,
                    'company' => clean($_POST['company'] ?? '', 80),
                    'rating' => $rating,
                    'text' => $text,
                    'status' => 'pending',
                    'visible' => false,
                ];
                save($data);
                $_SESSION['flash'] = 'Thank you. Your review has been submitted and is awaiting approval.';
            } else {
                $_SESSION['flash'] = 'Please add your name, a rating and your review.';
            }
        }
        header('Location: ?#reviews');
        exit;
    }
}

$flash = $_SESSION['flash'] ?? '';
unset($_SESSION['flash']);
$st = $data['settings'];
$socials = ['facebook' => 'Facebook', 'linkedin' => 'LinkedIn', 'instagram' => 'Instagram', 'x' => 'X', 'youtube' => 'YouTube', 'gmail' => 'Gmail'];
$waLink = 'https://wa.me/' . preg_replace('/\D/', '', $st['whatsapp']);

$services = cardsAll();

$reviews = array_values(array_filter($data['reviews'], fn($r) => revStatus($r) === 'approved'));
$avg = $reviews ? round(array_sum(array_column($reviews, 'rating')) / count($reviews), 1) : 0;
$T = token();
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($st['brand']) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <script>try {var t = localStorage.getItem('theme') || 'dark'; document.documentElement.setAttribute('data-theme', t)} catch (e) { }</script>
    <style>
        :root {
            --wrap: 1920px;
            --pad: 160px
        }

        [data-theme="light"] {
            --bg: #ffffff;
            --fg: #000000;
            --mute: #5c5c5c;
            --card: #ffffff;
            --line: #000000;
            --soft: #f2f2f2;
            --sh: 0 34px 60px -26px rgba(0, 0, 0, .45), 0 12px 22px -12px rgba(0, 0, 0, .25)
        }

        [data-theme="dark"] {
            --bg: #000000;
            --fg: #ffffff;
            --mute: #9a9a9a;
            --card: #121212;
            --line: #ffffff;
            --soft: #161616;
            --sh: 0 34px 60px -24px rgba(0, 0, 0, 1), 0 0 0 1px rgba(255, 255, 255, .14), 14px 14px 0 -2px rgba(255, 255, 255, .07)
        }

        [data-theme="night"] {
            --bg: #17120c;
            --fg: #f3e6cf;
            --mute: #a8987e;
            --card: #211a11;
            --line: #f3e6cf;
            --soft: #1d1710;
            --sh: 0 34px 60px -24px rgba(0, 0, 0, .85), 0 0 0 1px rgba(243, 230, 207, .16), 14px 14px 0 -2px rgba(243, 230, 207, .07)
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
            position: relative;
            overflow: hidden;
            transition: color .35s, background .35s, transform .25s;
            z-index: 0
        }

        .btn::before {
            content: "";
            position: absolute;
            inset: 0;
            background: var(--bg);
            transform: translateX(-101%);
            transition: transform .4s cubic-bezier(.7, 0, .2, 1);
            z-index: -1
        }

        .btn:hover {
            color: var(--fg)
        }

        .btn:hover::before {
            transform: none
        }

        .btn.ghost {
            background: transparent;
            color: var(--fg)
        }

        .btn.ghost::before {
            background: var(--fg)
        }

        .btn.ghost:hover {
            color: var(--bg)
        }

        .btn.sm {
            padding: 10px 20px;
            font-size: 14px
        }

        .btn.danger {
            background: transparent;
            color: var(--fg);
            border-style: dashed
        }

        header.nav {
            position: sticky;
            top: 0;
            z-index: 50;
            background: var(--bg);
            border-bottom: 1px solid var(--line);
            transition: background .5s
        }

        .nav .wrap {
            display: flex;
            align-items: center;
            justify-content: space-between;
            height: 84px;
            gap: 24px
        }

        .brand {
            font-weight: 700;
            font-size: 22px;
            letter-spacing: -.04em;
            display: flex;
            align-items: center;
            gap: 12px
        }

        .brand i {
            display: block;
            width: 22px;
            height: 22px;
            background: var(--fg);
            transform: rotate(45deg);
            animation: spin 14s linear infinite
        }

        .links {
            display: flex;
            gap: 36px;
            font-size: 15px
        }

        .links a {
            position: relative
        }

        .links a::after {
            content: "";
            position: absolute;
            left: 0;
            bottom: -4px;
            height: 1px;
            width: 100%;
            background: var(--fg);
            transform: scaleX(0);
            transform-origin: left;
            transition: transform .35s
        }

        .links a:hover::after {
            transform: scaleX(1)
        }

        .themes {
            display: flex;
            border: 1px solid var(--line)
        }

        .themes button {
            background: transparent;
            border: 0;
            padding: 8px 16px;
            font-size: 13px;
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

        .hero {
            padding-top: 110px;
            padding-bottom: 120px;
            position: relative;
            overflow: hidden
        }

        .hero::before {
            content: "";
            position: absolute;
            inset: -50% -10%;
            background-image: linear-gradient(var(--line) 1px, transparent 1px), linear-gradient(90deg, var(--line) 1px, transparent 1px);
            background-size: 80px 80px;
            opacity: .07;
            animation: drift 30s linear infinite;
            pointer-events: none
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

        .hero h1 span {
            display: block;
            overflow: hidden
        }

        .hero h1 span b {
            display: block;
            font-weight: inherit;
            animation: rise .9s cubic-bezier(.2, .8, .2, 1) both
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

        .stage {
            position: relative;
            height: 520px;
            display: grid;
            place-items: center;
            perspective: 1200px
        }

        .ring {
            position: absolute;
            border: 1px solid var(--line);
            animation: spin 26s linear infinite
        }

        .ring.a {
            width: 420px;
            height: 420px
        }

        .ring.b {
            width: 300px;
            height: 300px;
            animation-direction: reverse;
            animation-duration: 20s;
            opacity: .6
        }

        .panel {
            position: relative;
            width: 360px;
            padding: 40px;
            background: var(--card);
            box-shadow: var(--sh);
            border: 1px solid var(--line)
        }

        .fl {
            animation: float 6s ease-in-out infinite;
            transform-style: preserve-3d
        }

        .panel .big {
            font-size: 64px;
            font-weight: 600;
            letter-spacing: -.05em;
            line-height: 1
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
            font-weight: 600;
            letter-spacing: -.03em
        }

        .marquee {
            background: var(--fg);
            color: var(--bg);
            overflow: hidden;
            white-space: nowrap;
            padding: 22px 0;
            transition: background .5s, color .5s
        }

        .marquee div {
            display: inline-flex;
            gap: 64px;
            padding-right: 64px;
            animation: scroll 32s linear infinite;
            font-size: 22px;
            font-weight: 500;
            letter-spacing: -.02em
        }

        .marquee:hover div {
            animation-play-state: paused
        }

        .marquee span::after {
            content: "";
            display: inline-block;
            width: 8px;
            height: 8px;
            background: var(--bg);
            margin-left: 64px;
            transform: rotate(45deg)
        }

        section {
            padding-top: 130px;
            padding-bottom: 20px
        }

        .head {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            gap: 40px;
            margin-bottom: 60px;
            border-bottom: 1px solid var(--line);
            padding-bottom: 30px
        }

        .head h2 {
            font-size: clamp(34px, 3.6vw, 64px)
        }

        .head p {
            max-width: 460px;
            color: var(--mute)
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 48px;
            perspective: 1400px
        }

        .card {
            background: var(--card);
            border: 1px solid var(--line);
            padding: 44px 40px 36px;
            display: flex;
            flex-direction: column;
            min-height: 380px;
            box-shadow: var(--sh);
            transform-style: preserve-3d;
            transition: transform .15s ease-out, box-shadow .4s, background .5s;
            position: relative;
            will-change: transform
        }

        .card::after {
            content: "";
            position: absolute;
            inset: 0;
            background: radial-gradient(420px circle at var(--mx, 50%) var(--my, 0%), rgba(128, 128, 128, .16), transparent 60%);
            opacity: 0;
            transition: opacity .3s;
            pointer-events: none
        }

        .card:hover::after {
            opacity: 1
        }

        .card .tag {
            font-size: 14px;
            color: var(--mute);
            border: 1px solid var(--line);
            align-self: flex-start;
            padding: 2px 14px;
            margin-bottom: 28px;
            transform: translateZ(30px)
        }

        .card h3 {
            font-size: 28px;
            margin-bottom: 14px;
            transform: translateZ(46px)
        }

        .card p {
            color: var(--mute);
            flex: 1;
            transform: translateZ(22px)
        }

        .card .row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 32px;
            padding-top: 24px;
            border-top: 1px solid var(--line);
            transform: translateZ(34px);
            gap: 10px;
            flex-wrap: wrap
        }

        .card .price {
            font-weight: 600;
            font-size: 18px
        }

        .card .acts {
            display: flex;
            gap: 8px
        }

        .pick {
            border: 1px solid var(--line);
            background: transparent;
            padding: 10px 18px;
            font-size: 14px;
            cursor: pointer;
            transition: background .25s, color .25s
        }

        .pick:hover,
        .card.sel .pick {
            background: var(--fg);
            color: var(--bg)
        }

        .card.sel {
            outline: 2px solid var(--fg);
            outline-offset: -2px
        }

        .wa {
            display: inline-flex;
            align-items: center;
            border: 1px solid var(--fg);
            background: var(--fg);
            color: var(--bg);
            padding: 10px 18px;
            font-size: 14px;
            transition: transform .25s
        }

        .wa:hover {
            transform: translateY(-3px)
        }

        .bar {
            position: fixed;
            left: 50%;
            bottom: 28px;
            transform: translate(-50%, 160%);
            z-index: 60;
            background: var(--fg);
            color: var(--bg);
            display: flex;
            align-items: center;
            gap: 28px;
            padding: 16px 16px 16px 32px;
            box-shadow: 0 30px 60px -10px rgba(0, 0, 0, .6);
            transition: transform .5s cubic-bezier(.2, .8, .2, 1);
            max-width: calc(100% - 32px)
        }

        .bar.show {
            transform: translate(-50%, 0)
        }

        .bar b {
            font-weight: 600
        }

        .bar a {
            background: var(--bg);
            color: var(--fg);
            padding: 12px 26px;
            font-weight: 500
        }

        .bar button {
            background: transparent;
            border: 0;
            color: var(--bg);
            cursor: pointer;
            text-decoration: underline;
            font-size: 14px
        }

        .rgrid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 48px;
            perspective: 1400px
        }

        .rev {
            background: var(--card);
            border: 1px solid var(--line);
            padding: 40px;
            box-shadow: var(--sh);
            transform-style: preserve-3d;
            transition: transform .15s ease-out, background .5s;
            display: flex;
            flex-direction: column;
            gap: 20px
        }

        .rev .stars {
            font-size: 20px;
            letter-spacing: 4px;
            transform: translateZ(30px)
        }

        .rev q {
            font-size: 18px;
            line-height: 1.7;
            quotes: none;
            flex: 1;
            transform: translateZ(20px)
        }

        .rev footer {
            border-top: 1px solid var(--line);
            padding-top: 18px
        }

        .rev footer b {
            display: block;
            font-weight: 600
        }

        .rev footer small {
            color: var(--mute)
        }

        .formwrap {
            display: grid;
            grid-template-columns: 1fr 1.4fr;
            gap: 80px;
            margin-top: 100px;
            padding: 64px;
            background: var(--card);
            border: 1px solid var(--line);
            box-shadow: var(--sh)
        }

        .formwrap h3 {
            font-size: 36px
        }

        .formwrap p {
            color: var(--mute);
            margin-top: 14px
        }

        label.f {
            display: block;
            font-size: 14px;
            color: var(--mute);
            margin-bottom: 18px
        }

        .in {
            display: block;
            width: 100%;
            margin-top: 6px;
            padding: 14px 16px;
            background: var(--bg);
            border: 1px solid var(--line);
            outline: 0;
            transition: box-shadow .25s
        }

        .in:focus {
            box-shadow: 6px 6px 0 var(--fg)
        }

        textarea.in {
            min-height: 120px;
            resize: vertical
        }

        .two {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 18px
        }

        .starin {
            display: inline-flex;
            flex-direction: row-reverse;
            margin: 6px 0 22px
        }

        .starin input {
            position: absolute;
            opacity: 0
        }

        .starin label {
            font-size: 34px;
            cursor: pointer;
            color: var(--mute);
            line-height: 1;
            padding-right: 6px;
            transition: transform .2s, color .2s
        }

        .starin label:hover,
        .starin label:hover~label,
        .starin input:checked~label {
            color: var(--fg)
        }

        .starin label:hover {
            transform: scale(1.2)
        }

        .starin input:focus-visible+label {
            outline: 2px solid var(--fg)
        }

        .hp {
            position: absolute;
            left: -9999px
        }

        .flash {
            border: 1px solid var(--line);
            background: var(--fg);
            color: var(--bg);
            padding: 14px 22px;
            margin: 28px 0 0
        }

        .empty {
            border: 1px dashed var(--line);
            padding: 50px;
            text-align: center;
            color: var(--mute);
            grid-column: 1/-1
        }

        .about {
            display: grid;
            grid-template-columns: 1.2fr 1fr;
            gap: 80px;
            align-items: start
        }

        .about h3 {
            font-size: clamp(28px, 2.6vw, 46px);
            margin-bottom: 28px
        }

        .about p {
            color: var(--mute);
            font-size: 18px;
            margin-bottom: 20px;
            max-width: 760px
        }

        .steps {
            display: grid;
            gap: 28px;
            perspective: 1400px
        }

        .step {
            display: flex;
            gap: 28px;
            align-items: flex-start;
            padding: 30px 34px;
            background: var(--card);
            border: 1px solid var(--line);
            box-shadow: var(--sh);
            transform-style: preserve-3d;
            transition: transform .15s ease-out, background .5s
        }

        .step b {
            font-size: 44px;
            font-weight: 600;
            letter-spacing: -.05em;
            line-height: 1;
            min-width: 44px;
            transform: translateZ(30px)
        }

        .step h4 {
            font-size: 20px;
            font-weight: 600;
            letter-spacing: -.02em
        }

        .step p {
            font-size: 15px;
            margin: 4px 0 0
        }

        .contact {
            display: grid;
            grid-template-columns: 1fr 1.2fr;
            gap: 48px;
            perspective: 1400px
        }

        .cinfo,
        .cform {
            background: var(--card);
            border: 1px solid var(--line);
            box-shadow: var(--sh);
            padding: 48px;
            transform-style: preserve-3d;
            transition: transform .15s ease-out, background .5s
        }

        .cinfo dl {
            display: grid;
            gap: 26px;
            margin-bottom: 36px
        }

        .cinfo dt {
            font-size: 14px;
            color: var(--mute)
        }

        .cinfo dd {
            font-size: 18px;
            font-weight: 500
        }

        .cinfo dd a {
            border-bottom: 1px solid var(--line)
        }

        .map-wrap {
            margin-top: 34px;
            border: 1px solid var(--line);
            background: var(--soft)
        }

        .map-wrap iframe {
            width: 100%;
            height: 340px;
            border: 0;
            display: block
        }

        footer.site {
            margin-top: 140px;
            border-top: 1px solid var(--line)
        }

        .fcta {
            background: var(--fg);
            color: var(--bg);
            padding: 72px 0;
            transition: background .5s, color .5s
        }

        .fcta .wrap {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 40px;
            flex-wrap: wrap
        }

        .fcta h2 {
            font-size: clamp(30px, 3.2vw, 56px);
            max-width: 900px
        }

        .fcta .btn {
            background: var(--bg);
            color: var(--fg);
            border-color: var(--bg)
        }

        .fcta .btn::before {
            background: var(--fg)
        }

        .fcta .btn:hover {
            color: var(--bg)
        }

        .fgrid {
            display: grid;
            grid-template-columns: 1.5fr 1fr 1fr 1.3fr;
            gap: 64px;
            padding-top: 84px;
            padding-bottom: 70px
        }

        .fgrid h4 {
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 20px
        }

        .fgrid ul {
            list-style: none;
            display: grid;
            gap: 10px
        }

        .fgrid a,
        .fgrid p {
            color: var(--mute);
            font-size: 15px;
            transition: color .25s
        }

        .fgrid a:hover {
            color: var(--fg)
        }

        .fgrid .brand {
            color: var(--fg)
        }

        .fbrand>p {
            margin: 22px 0 28px;
            max-width: 360px
        }

        /* ============ SOCIAL ICONS (black & white, SVG) ============ */
        .soc {
            display: flex;
            gap: 10px;
            flex-wrap: wrap
        }

        .soc a {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 42px;
            height: 42px;
            padding: 0;
            border: 1px solid var(--line);
            color: var(--fg);
            transition: background .25s, color .25s, transform .25s;
        }

        .soc a svg {
            width: 18px;
            height: 18px;
            display: block
        }

        .soc a:hover {
            background: var(--fg);
            color: var(--bg);
            transform: translateY(-3px)
        }

        .fbot {
            display: flex;
            justify-content: space-between;
            gap: 20px;
            flex-wrap: wrap;
            padding-top: 26px;
            padding-bottom: 26px;
            border-top: 1px solid var(--line);
            color: var(--mute);
            font-size: 14px
        }

        .fbot a:hover {
            color: var(--fg)
        }

        @keyframes spin {
            to {
                transform: rotate(405deg)
            }
        }

        @keyframes drift {
            to {
                transform: translate(80px, 80px)
            }
        }

        @keyframes scroll {
            to {
                transform: translateX(-100%)
            }
        }

        @keyframes float {
            50% {
                transform: translateY(-16px)
            }
        }

        @keyframes rise {
            from {
                transform: translateY(105%)
            }

            to {
                transform: none
            }
        }

        @media (prefers-reduced-motion:reduce) {
            * {
                animation: none !important;
                transition: none !important
            }
        }

        @media (max-width:1500px) {
            :root {
                --pad: 80px
            }

            .grid,
            .rgrid {
                gap: 32px
            }
        }

        @media (max-width:1180px) {

            .grid,
            .rgrid {
                grid-template-columns: repeat(2, 1fr)
            }

            .hero .wrap,
            .formwrap,
            .about,
            .contact {
                grid-template-columns: 1fr;
                gap: 50px
            }

            .stage {
                height: 440px
            }

            .links {
                display: none
            }

            .fgrid {
                grid-template-columns: 1fr 1fr
            }
        }

        @media (max-width:720px) {
            :root {
                --pad: 24px
            }

            .grid,
            .rgrid,
            .two,
            .fgrid {
                grid-template-columns: 1fr
            }

            .nav .wrap {
                height: auto;
                padding-top: 14px;
                padding-bottom: 14px;
                flex-wrap: wrap
            }

            .hero {
                padding-top: 60px
            }

            .head {
                flex-direction: column;
                align-items: flex-start
            }

            .formwrap,
            .cinfo,
            .cform {
                padding: 28px
            }

            .bar {
                flex-wrap: wrap;
                gap: 12px;
                padding: 14px 16px
            }

            section {
                padding-top: 80px
            }

            .ring.a {
                width: 320px;
                height: 320px
            }

            .ring.b {
                width: 230px;
                height: 230px
            }

            .panel {
                width: 290px;
                padding: 28px
            }

            .step {
                padding: 22px;
                gap: 18px
            }

            .fcta {
                padding: 48px 0
            }
        }
    </style>
</head>

<body id="top">

    <header class="nav">
        <div class="wrap">
            <a class="brand" href="?"><i></i><?= e($st['brand']) ?></a>
            <nav class="links">
                <a href="#services">Services</a>
                <a href="#about">About us</a>
                <a href="#reviews">Client reviews</a>
                <a href="#contact">Contact us</a>
            </nav>
            <div class="themes" role="group" aria-label="Theme">
                <button type="button" data-set="light">Light</button>
                <button type="button" data-set="night">Night light</button>
                <button type="button" data-set="dark">Dark</button>
            </div>
        </div>
    </header>

    <main>
        <!-- ============ HERO ============ -->
        <div class="hero">
            <div class="wrap">
                <div>
                    <h1>
                        <?php foreach (preg_split('/(?<=[.,])\s+|\s+(?=\S+\s+\S+\s+\S+$)/', (string) $st['headline']) as $i => $line): ?>
                            <span><b style="animation-delay:<?= .08 + $i * .14 ?>s"><?= e($line) ?></b></span>
                        <?php endforeach; ?>
                    </h1>
                    <p class="sub"><?= e($st['sub']) ?></p>
                    <div class="cta">
                        <a class="btn" href="#services">Browse services</a>
                        <a class="btn ghost"
                            href="<?= e($waLink) ?>?text=<?= rawurlencode('Hello, I would like to discuss a service for my business.') ?>"
                            target="_blank" rel="noopener">Chat on WhatsApp</a>
                    </div>
                </div>
                <div class="stage">
                    <div class="ring a"></div>
                    <div class="ring b"></div>
                    <div class="fl">
                        <div class="panel tilt" data-tilt="10">
                            <div class="big"><?= $avg ?: '5.0' ?></div>
                            <div class="stars"><?= stars((int) round($avg ?: 5)) ?></div>
                            <dl>
                                <div>
                                    <dt>Services</dt>
                                    <dd><?= count($services) ?></dd>
                                </div>
                                <div>
                                    <dt>Client reviews</dt>
                                    <dd><?= count($reviews) ?></dd>
                                </div>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ============ MARQUEE ============ -->
        <?php if ($services): ?>
            <div class="marquee" aria-hidden="true">
                <div>
                    <?php foreach (array_merge($services, $services) as $s): ?><span><?= e($s['heading']) ?></span><?php endforeach; ?>
                </div>
                <div>
                    <?php foreach (array_merge($services, $services) as $s): ?><span><?= e($s['heading']) ?></span><?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- ============ SERVICES ============ -->
        <section id="services">
            <div class="wrap">
                <div class="head">
                    <h2>Select what your business needs</h2>
                    <p>Tap Select on one or more services, then send them to our team in one WhatsApp message.</p>
                </div>
                <div class="grid">
                    <?php foreach ($services as $s): ?>
                        <article class="card tilt" data-tilt="8" data-title="<?= e($s['heading']) ?>">
                            <?php if (!empty($s['category'])): ?><span
                                    class="tag"><?= e($s['category']) ?></span><?php endif; ?>
                            <h3><?= e($s['heading']) ?></h3>
                            <p><?= e($s['sub_heading']) ?></p>
                            <div class="row">
                                <span class="price"><?= e($s['price']) ?></span>
                                <div class="acts">
                                    <button type="button" class="pick"
                                        aria-pressed="false"><?= e($s['select'] ?: 'Select') ?></button>
                                    <a class="wa" target="_blank" rel="noopener"
                                        href="<?= e($waLink) ?>?text=<?= rawurlencode('Hello, I am interested in: ' . $s['heading']) ?>">WhatsApp</a>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                    <?php if (!$services): ?>
                        <div class="empty">Services are being updated. Please check back shortly.</div><?php endif; ?>
                </div>
            </div>
        </section>

        <!-- ============ ABOUT ============ -->
        <section id="about">
            <div class="wrap">
                <div class="head">
                    <h2>About us</h2>
                    <p>Who we are and how a project with us starts.</p>
                </div>
                <div class="about">
                    <div>
                        <h3><?= e($st['about_title']) ?></h3>
                        <?php foreach (preg_split('/\R{2,}/', (string) $st['about_text']) as $para):
                            if (trim($para) === '')
                                continue; ?>
                            <p><?= nl2br(e(trim($para))) ?></p>
                        <?php endforeach; ?>
                    </div>
                    <div class="steps">
                        <div class="step tilt" data-tilt="6"><b>1</b>
                            <div>
                                <h4>Pick your services</h4>
                                <p>Select one or more cards on this page.</p>
                            </div>
                        </div>
                        <div class="step tilt" data-tilt="6"><b>2</b>
                            <div>
                                <h4>Message us on WhatsApp</h4>
                                <p>Your selection arrives as a ready-made message.</p>
                            </div>
                        </div>
                        <div class="step tilt" data-tilt="6"><b>3</b>
                            <div>
                                <h4>Get a clear plan</h4>
                                <p>We reply with scope, timeline and price.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- ============ REVIEWS ============ -->
        <section id="reviews">
            <div class="wrap">
                <div class="head">
                    <h2>What our clients say</h2>
                    <p><?= $reviews ? 'Rated ' . $avg . ' out of 5 by ' . count($reviews) . ' companies.' : 'Be the first to review our work.' ?>
                    </p>
                </div>
                <?php if ($flash): ?>
                    <div class="flash" style="margin:0 0 40px"><?= e($flash) ?></div><?php endif; ?>
                <div class="rgrid">
                    <?php foreach (array_reverse($reviews) as $r): ?>
                        <article class="rev tilt" data-tilt="7">
                            <div class="stars" aria-label="<?= (int) $r['rating'] ?> out of 5">
                                <?= stars((int) $r['rating']) ?></div>
                            <q><?= e($r['text']) ?></q>
                            <footer>
                                <b><?= e($r['name']) ?></b><?php if (!empty($r['company'])): ?><small><?= e($r['company']) ?></small><?php endif; ?>
                            </footer>
                        </article>
                    <?php endforeach; ?>
                    <?php if (!$reviews): ?>
                        <div class="empty">No reviews yet. Be the first to submit one below.</div><?php endif; ?>
                </div>

                <form class="formwrap" id="write" method="post" action="?#reviews">
                    <div>
                        <h3>Worked with us? Leave a review.</h3>
                        <p>Your review is submitted for approval — it appears here once our team publishes it.</p>
                    </div>
                    <div>
                        <input type="hidden" name="t" value="<?= e($T) ?>">
                        <input type="hidden" name="action" value="review_add">
                        <input class="hp" type="text" name="website" tabindex="-1" autocomplete="off"
                            aria-hidden="true">
                        <div class="two">
                            <label class="f">Your name<input class="in" name="name" maxlength="60" required></label>
                            <label class="f">Company (optional)<input class="in" name="company" maxlength="80"></label>
                        </div>
                        <div class="f" style="margin-bottom:0">Your rating</div>
                        <div class="starin" role="radiogroup" aria-label="Rating">
                            <?php for ($i = 5; $i >= 1; $i--): ?>
                                <input type="radio" name="rating" id="st<?= $i ?>" value="<?= $i ?>" <?= $i === 5 ? 'checked' : '' ?>><label for="st<?= $i ?>" title="<?= $i ?> stars">★</label>
                            <?php endfor; ?>
                        </div>
                        <label class="f">Your review<textarea class="in" name="text" maxlength="500"
                                required></textarea></label>
                        <button class="btn" type="submit">Submit review</button>
                    </div>
                </form>
            </div>
        </section>

        <!-- ============ CONTACT ============ -->
        <section id="contact">
            <div class="wrap">
                <div class="head">
                    <h2>Contact us</h2>
                    <p>Send a message straight to our WhatsApp, or visit the office.</p>
                </div>
                <div class="contact">
                    <div class="cinfo tilt" data-tilt="4">
                        <dl>
                            <?php if (!empty($st['address'])): ?>
                                <div>
                                    <dt>Office address</dt>
                                    <dd><?= e($st['address']) ?></dd>
                                </div><?php endif; ?>
                            <?php if (!empty($st['email'])): ?>
                                <div>
                                    <dt>Email</dt>
                                    <dd><a href="mailto:<?= e($st['email']) ?>"><?= e($st['email']) ?></a></dd>
                                </div><?php endif; ?>
                            <?php if (!empty($st['phone'])): ?>
                                <div>
                                    <dt>Phone</dt>
                                    <dd><a
                                            href="tel:<?= e(preg_replace('/[^\d+]/', '', $st['phone'])) ?>"><?= e($st['phone']) ?></a>
                                    </dd>
                                </div><?php endif; ?>
                            <?php if (!empty($st['hours'])): ?>
                                <div>
                                    <dt>Business hours</dt>
                                    <dd><?= e($st['hours']) ?></dd>
                                </div><?php endif; ?>
                        </dl>
                        <div class="cta">
                            <a class="btn" href="<?= e($waLink) ?>" target="_blank" rel="noopener">Chat on WhatsApp</a>
                            <?php if (!empty($st['address'])): ?>
                                <a class="btn ghost"
                                    href="https://www.google.com/maps/search/?api=1&query=<?= rawurlencode($st['address']) ?>"
                                    target="_blank" rel="noopener">Get directions</a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <form class="cform tilt" data-tilt="4" id="cform">
                        <h3 style="font-size:30px;margin-bottom:8px">Tell us what you need</h3>
                        <p style="color:var(--mute);margin-bottom:28px">This opens WhatsApp with your message ready to
                            send.</p>
                        <div class="two">
                            <label class="f">Your name<input class="in" name="name" required maxlength="60"></label>
                            <label class="f">Company<input class="in" name="company" maxlength="80"></label>
                        </div>
                        <label class="f">Message<textarea class="in" name="message" required
                                maxlength="600"></textarea></label>
                        <button class="btn" type="submit">Send on WhatsApp</button>
                    </form>
                </div>

                <?php if (!empty($st['map_embed'])): ?>
                    <div class="map-wrap">
                        <iframe src="<?= e($st['map_embed']) ?>" loading="lazy" referrerpolicy="no-referrer-when-downgrade"
                            allowfullscreen></iframe>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    </main>

    <div class="bar" id="bar" role="status">
        <span><b id="cnt">0</b> selected</span>
        <button type="button" id="clear">Clear</button>
        <a id="send" target="_blank" rel="noopener" href="#">Send to WhatsApp</a>
    </div>

    <footer class="site">
        <div class="fcta">
            <div class="wrap">
                <h2>Ready to move your business forward?</h2>
                <a class="btn"
                    href="<?= e($waLink) ?>?text=<?= rawurlencode('Hello, I would like to discuss a service for my business.') ?>"
                    target="_blank" rel="noopener">Start on WhatsApp</a>
            </div>
        </div>
        <div class="wrap fgrid">
            <div class="fbrand">
                <a class="brand" href="?"><i></i><?= e($st['brand']) ?></a>
                <p><?= e($st['headline']) ?></p>
                <!-- ============ SOCIAL ICONS (SVG) ============ -->
                <div class="soc">
                    <?php foreach ($socials as $k => $label):
                        if (!empty($st[$k])): ?>
                            <?php if ($k === 'gmail'): ?>
                                <a href="mailto:<?= e($st[$k]) ?>" aria-label="Email us" title="Email us">
                                    <?= socialIcon('gmail') ?>
                                </a>
                            <?php else: ?>
                                <a href="<?= e($st[$k]) ?>" target="_blank" rel="noopener" aria-label="<?= e($label) ?>"
                                    title="<?= e($label) ?>">
                                    <?= socialIcon($k) ?>
                                </a>
                            <?php endif; ?>
                        <?php endif; endforeach; ?>
                    <a href="<?= e($waLink) ?>" target="_blank" rel="noopener" aria-label="WhatsApp" title="WhatsApp">
                        <?= socialIcon('whatsapp') ?>
                    </a>
                </div>
            </div>
            <div>
                <h4>Services</h4>
                <ul>
                    <?php foreach (array_slice($services, 0, 6) as $s): ?>
                        <li><a href="#services"><?= e($s['heading']) ?></a></li><?php endforeach; ?>
                </ul>
            </div>
            <div>
                <h4>Company</h4>
                <ul>
                    <li><a href="#about">About us</a></li>
                    <li><a href="#reviews">Client reviews</a></li>
                    <li><a href="#write">Write a review</a></li>
                    <li><a href="#contact">Contact us</a></li>
                </ul>
            </div>
            <div>
                <h4>Visit and contact</h4>
                <ul>
                    <?php if (!empty($st['address'])): ?>
                        <li>
                            <p><?= e($st['address']) ?></p>
                        </li><?php endif; ?>
                    <?php if (!empty($st['email'])): ?>
                        <li><a href="mailto:<?= e($st['email']) ?>"><?= e($st['email']) ?></a></li><?php endif; ?>
                    <?php if (!empty($st['phone'])): ?>
                        <li><a href="tel:<?= e(preg_replace('/[^\d+]/', '', $st['phone'])) ?>"><?= e($st['phone']) ?></a>
                        </li><?php endif; ?>
                    <?php if (!empty($st['hours'])): ?>
                        <li>
                            <p><?= e($st['hours']) ?></p>
                        </li><?php endif; ?>
                </ul>
            </div>
        </div>
        <div class="wrap fbot">
            <span>&copy; <?= date('Y') ?> <?= e($st['brand']) ?>. All rights reserved.</span>
            <a href="#top">Back to top</a>
        </div>
    </footer>

    <script>
        (function () {
            /* theme */
            var root = document.documentElement, btns = document.querySelectorAll('.themes button');
            function setTheme(t) {
                root.setAttribute('data-theme', t);
                try {localStorage.setItem('theme', t);} catch (e) { }
                btns.forEach(function (b) {b.classList.toggle('on', b.dataset.set === t);});
            }
            btns.forEach(function (b) {b.addEventListener('click', function () {setTheme(b.dataset.set);});});
            setTheme(root.getAttribute('data-theme') || 'dark');

            /* tilt */
            if (!matchMedia('(prefers-reduced-motion: reduce)').matches && matchMedia('(hover:hover)').matches) {
                document.querySelectorAll('.tilt').forEach(function (el) {
                    var max = parseFloat(el.dataset.tilt) || 8;
                    el.addEventListener('mousemove', function (ev) {
                        var r = el.getBoundingClientRect(), x = (ev.clientX - r.left) / r.width, y = (ev.clientY - r.top) / r.height;
                        el.style.transform = 'perspective(1000px) rotateX(' + ((.5 - y) * max * 2) + 'deg) rotateY(' + ((x - .5) * max * 2) + 'deg) translateY(-8px)';
                        el.style.setProperty('--mx', (x * 100) + '%'); el.style.setProperty('--my', (y * 100) + '%');
                    });
                    el.addEventListener('mouseleave', function () {el.style.transform = '';});
                });
            }

            /* service selection + WhatsApp */
            var bar = document.getElementById('bar');
            if (!bar) return;
            var num = <?= json_encode(preg_replace('/\D/', '', $st['whatsapp'])) ?>, picked = {};
            var cnt = document.getElementById('cnt'), send = document.getElementById('send');
            function refresh() {
                var names = Object.keys(picked);
                cnt.textContent = names.length;
                bar.classList.toggle('show', names.length > 0);
                var msg = 'Hello, I am interested in these services:\n' + names.map(function (n) {return '- ' + n}).join('\n');
                send.href = 'https://wa.me/' + num + '?text=' + encodeURIComponent(msg);
            }
            document.querySelectorAll('.card').forEach(function (card) {
                var b = card.querySelector('.pick');
                if (!b) return;
                b.dataset.label = b.textContent;
                b.addEventListener('click', function () {
                    var t = card.dataset.title, on = !picked[t];
                    if (on) picked[t] = 1; else delete picked[t];
                    card.classList.toggle('sel', on);
                    b.setAttribute('aria-pressed', on);
                    b.textContent = on ? 'Selected' : (b.dataset.label || 'Select');
                    refresh();
                });
            });
            document.getElementById('clear').addEventListener('click', function () {
                picked = {};
                document.querySelectorAll('.card').forEach(function (c) {
                    c.classList.remove('sel');
                    var b = c.querySelector('.pick');
                    if (b) {b.textContent = b.dataset.label || 'Select'; b.setAttribute('aria-pressed', 'false');}
                });
                refresh();
            });
            var cf = document.getElementById('cform');
            if (cf) cf.addEventListener('submit', function (ev) {
                ev.preventDefault();
                var f = new FormData(cf), names = Object.keys(picked);
                var msg = 'Hello, I am ' + f.get('name') + (f.get('company') ? ' from ' + f.get('company') : '') + '.\n' + f.get('message');
                if (names.length) msg += '\n\nServices I selected:\n' + names.map(function (n) {return '- ' + n}).join('\n');
                window.open('https://wa.me/' + num + '?text=' + encodeURIComponent(msg), '_blank', 'noopener');
            });
        })();
    </script>
</body>

</html>