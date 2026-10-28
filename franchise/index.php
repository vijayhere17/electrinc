<?php

ini_set('display_errors', 0);
error_reporting(E_ALL);

session_start();



require_once __DIR__ . '/config/db.php';

$error = "";

if (!isset($conn) || !($conn instanceof mysqli)) {
    die("Database connection failed. Please check config/db.php");
}

$error = '';

function tableExists(mysqli $conn, string $table): bool
{
    $safeTable = $conn->real_escape_string($table);
    $result = $conn->query("SHOW TABLES LIKE '$safeTable'");
    $exists = $result && $result->num_rows > 0;

    return $exists;
}

function columnExists(mysqli $conn, string $table, string $column): bool
{
    if (!tableExists($conn, $table)) {
        return false;
    }

    $safeTable = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
    $safeColumn = $conn->real_escape_string($column);
    $result = $conn->query("SHOW COLUMNS FROM `$safeTable` LIKE '$safeColumn'");
    $exists = $result && $result->num_rows > 0;

    return $exists;
}

function getFranchiseLogin(mysqli $conn, string $table, string $loginId): ?array
{
    if (!tableExists($conn, $table)) {
        return null;
    }

    $columns = [
        'franchise_code',
        'franchies_id',
        'franchise_id',
        'franchises_id'
    ];

    foreach ($columns as $column) {

        if (!columnExists($conn, $table, $column)) {
            continue;
        }

        $stmt = $conn->prepare(
            "SELECT * FROM `$table` WHERE `$column` = ? LIMIT 1"
        );

        if (!$stmt) {
            continue;
        }

        $stmt->bind_param("s", $loginId);
        $stmt->execute();

        $result = $stmt->get_result();

        if ($result && $result->num_rows > 0) {

            $row = $result->fetch_assoc();

            $stmt->close();

            return $row;
        }

        $stmt->close();
    }

    return null;
}

function getMobileFranchiseLogin(mysqli $conn, string $loginId): ?array
{
    $table = 'mobile_franchies_holder';

    if (!tableExists($conn, $table)) {
        return null;
    }

    foreach (['mobile_franchies_id', 'mobile_franchise_id', 'franchies_id', 'franchises_id', 'franchise_id'] as $column) {
        if (!columnExists($conn, $table, $column)) {
            continue;
        }

        $stmt = $conn->prepare("SELECT * FROM `$table` WHERE `$column` = ? LIMIT 1");
        if (!$stmt) {
            continue;
        }

        $stmt->bind_param('s', $loginId);
        $stmt->execute();

        $result = $stmt->get_result();
        if ($result && $result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $row['_login_column'] = $column;
            $stmt->close();
            return $row;
        }

        $stmt->close();
    }

    return null;
}

function checkPassword(array $row, string $password): bool
{
    $passwordColumns = [
    'mobile_franchies_password',
    'plain_password',
    'password_hash',

    'franchise_password',

    'franchies_password',
    'password_text',
    'password',
    'franchies_password_text',
    'franchises_password',
    'franchises_password_text',
];

    foreach ($passwordColumns as $column) {
        if (!isset($row[$column])) {
            continue;
        }

        $storedPassword = trim((string)$row[$column]);
        if ($storedPassword === '') {
            continue;
        }

        if (password_get_info($storedPassword)['algo'] !== 0) {
            if (password_verify($password, $storedPassword)) {
                return true;
            }
        }

        if ($storedPassword === $password) {
            return true;
        }
    }

    return false;
}

if (!empty($_SESSION['franchise_id']) && !empty($_SESSION['franchise_type'])) {
    header("Location: dashboard.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $loginId = trim($_POST['franchies_id'] ?? $_POST['franchises_id'] ?? '');
    $password = trim($_POST['franchies_password'] ?? $_POST['franchises_password'] ?? '');

    if ($loginId === '' || $password === '') {
        $error = "Please enter Franchise ID and Password.";
    } else {
        $stateRow = getFranchiseLogin($conn, 'franchies_holder', $loginId);
        
        if ($stateRow && checkPassword($stateRow, $password)) {
            session_regenerate_id(true);

            $_SESSION['franchise_type'] = 'state';
            $_SESSION['franchise_id'] = (int)($stateRow['id'] ?? 0);
            $_SESSION['franchise_code'] = $stateRow['franchies_id'] ?? $loginId;
            $_SESSION['franchise_name'] = $stateRow['name'] ?? $stateRow['owner_name'] ?? 'State Franchise';

            header("Location: dashboard.php");
            exit();
        }

        $cityRow = getFranchiseLogin($conn, 'city_franchies_holder', $loginId);
        if ($cityRow && checkPassword($cityRow, $password)) {
            session_regenerate_id(true);

            $_SESSION['franchise_type'] = 'city';
            $_SESSION['franchise_id'] = (int)($cityRow['id'] ?? 0);
            $_SESSION['franchise_code'] = $cityRow['franchies_id'] ?? $loginId;
            $_SESSION['franchise_name'] = $cityRow['full_name'] ?? $cityRow['name'] ?? 'City Franchise';

            header("Location: dashboard.php");
            exit();
        }

        $mobileRow = getMobileFranchiseLogin($conn, $loginId);
        if ($mobileRow && checkPassword($mobileRow, $password)) {
            session_regenerate_id(true);

            $loginColumn = $mobileRow['_login_column'] ?? '';
            $loginCode = ($loginColumn !== '' && isset($mobileRow[$loginColumn]))
                ? (string)$mobileRow[$loginColumn]
                : ($mobileRow['mobile_franchies_id'] ?? $loginId);

            $_SESSION['franchise_type'] = 'mobile';
            $_SESSION['franchise_id'] = (int)($mobileRow['id'] ?? 0);
            $_SESSION['franchise_code'] = $loginCode;
            $_SESSION['franchise_name'] = $mobileRow['owner_name'] ?? $mobileRow['shop_name'] ?? $mobileRow['full_name'] ?? 'Mobile Franchise';

            header("Location: dashboard.php");
            exit();
        }

        $error = "Invalid Franchise ID or Password.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Franchise Login</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        :root{
            --saffron:#E0700C; --saffron-d:#B25400; --ember:#F3A24A;
            --green:#0E7A3B;    --green-d:#0A5E2D;    --mint:#6FD69A;
            --night:#0A1F16;    --night-2:#123B27;
            --ink:#1C1C1C; --ink-2:#6B6B6B; --line:#EAE5DA;
        }
        *{ font-family:'Manrope',system-ui,sans-serif; }
        html,body{ height:100%; }
        body{ background:#FBFAF6; }

        /* ---------- left brand panel ---------- */
        .brand{
            position:relative; overflow:hidden; isolation:isolate;
            background:linear-gradient(150deg,var(--night) 0%,var(--night-2) 58%,#1E4C30 100%);
        }
        .brand::before{ /* fine dot grid texture */
            content:""; position:absolute; inset:0; z-index:0; opacity:.5;
            background-image:radial-gradient(rgba(255,255,255,.09) 1px, transparent 1px);
            background-size:22px 22px;
            mask-image:radial-gradient(120% 100% at 0% 0%, #000 40%, transparent 85%);
        }
        .glow-a{ position:absolute; z-index:0; width:480px; height:480px; border-radius:999px; top:-160px; right:-140px;
            background:radial-gradient(circle, rgba(224,112,12,.35), transparent 70%); filter:blur(10px); }
        .glow-b{ position:absolute; z-index:0; width:520px; height:520px; border-radius:999px; bottom:-220px; left:-160px;
            background:radial-gradient(circle, rgba(111,214,154,.22), transparent 70%); filter:blur(10px); }
        .route-line{ position:absolute; z-index:0; opacity:.35; }

        .feat-row{ display:flex; align-items:flex-start; gap:12px; }
        .feat-ic{ width:34px; height:34px; border-radius:10px; background:rgba(255,255,255,.08);
            border:1px solid rgba(255,255,255,.14); display:flex; align-items:center; justify-content:center; flex-shrink:0; }

        /* ---------- right form panel ---------- */
        .fr-field label{ display:block; font-size:11px; font-weight:700; letter-spacing:.06em; text-transform:uppercase; color:var(--ink-2); margin-bottom:6px; }
        .fr-field{ position:relative; }
        .fr-field .ic{ position:absolute; left:14px; top:38px; color:#B7AFA0; pointer-events:none; }
        .fr-field input{
            width:100%; background:#fff; border:1.5px solid var(--line); border-radius:11px;
            padding:12.5px 14px 12.5px 42px; font-size:14.5px; color:var(--ink); outline:none;
            transition:border-color .15s ease, box-shadow .15s ease;
        }
        .fr-field input::placeholder{ color:#B7AFA0; }
        .fr-field input:focus{ border-color:var(--saffron); box-shadow:0 0 0 3.5px rgba(224,112,12,.13); }

        .fr-btn{
            width:100%; background:linear-gradient(100deg,var(--night-2),var(--green));
            color:#fff; font-weight:700; font-size:14.5px; letter-spacing:.01em;
            padding:14px 24px; border-radius:11px; border:0; cursor:pointer;
            display:flex; align-items:center; justify-content:center; gap:8px;
            box-shadow:0 16px 30px -16px rgba(10,60,35,.55);
            transition:transform .12s ease, box-shadow .12s ease, background .2s ease;
        }
        .fr-btn:hover{ transform:translateY(-1px); background:linear-gradient(100deg,var(--night-2),var(--saffron-d)); }
        .fr-btn:active{ transform:translateY(0); }
        .fr-btn svg{ transition:transform .15s ease; }
        .fr-btn:hover svg{ transform:translateX(3px); }

        /* ---------- mobile mini brand hero ---------- */
        .mobile-brand{
            position:relative; overflow:hidden; isolation:isolate;
            background:linear-gradient(150deg,var(--night) 0%,var(--night-2) 58%,#1E4C30 100%);
        }
        .mobile-brand::before{
            content:""; position:absolute; inset:0; z-index:0; opacity:.5;
            background-image:radial-gradient(rgba(255,255,255,.09) 1px, transparent 1px);
            background-size:16px 16px;
            mask-image:radial-gradient(120% 100% at 50% 0%, #000 30%, transparent 85%);
        }
        @media (max-width:400px){
            .fr-field input{ padding-left:40px; font-size:14px; }
        }
    </style>
</head>

<body>
<div class="min-h-screen flex">

    <!-- LEFT — brand / value panel (desktop) -->
    <div class="brand hidden lg:flex lg:w-[55%] xl:w-[58%] flex-col justify-between px-14 xl:px-20 py-14 relative">
        <svg class="route-line" width="360" height="360" style="top:8%;right:6%;" viewBox="0 0 360 360" fill="none">
            <path d="M10 300 C 90 300, 90 200, 170 200 S 250 100, 350 60" stroke="url(#g1)" stroke-width="2" stroke-dasharray="2 10" stroke-linecap="round"/>
            <defs><linearGradient id="g1" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#E0700C"/><stop offset="1" stop-color="#6FD69A"/></linearGradient></defs>
        </svg>
        <div class="glow-a"></div>
        <div class="glow-b"></div>

        <div class="relative z-10">
            <img src="assets/electrinc-logo.png" alt="Electrinc" class="h-9 w-auto">
        </div>

        <div class="relative z-10 max-w-[480px]">
            <p class="text-[12px] font-bold tracking-[.2em] uppercase" style="color:var(--ember)">Franchise Partner Portal</p>
            <h1 class="mt-4 text-[34px] xl:text-[40px] font-extrabold leading-[1.12] text-white">
                Run your EV showroom,<br>end to end.
            </h1>
            <p class="mt-4 text-[15px] leading-relaxed" style="color:rgba(255,255,255,.72)">
                One panel for inventory, vendors, customers, billing and payouts — built for how your showroom actually works.
            </p>

            <div class="mt-9 space-y-5">
                <div class="feat-row">
                    <span class="feat-ic"><i data-lucide="boxes" class="w-4 h-4" style="color:var(--mint)"></i></span>
                    <div><p class="text-white text-[14px] font-semibold leading-tight">Live inventory &amp; vehicle tracking</p>
                        <p class="text-[12.5px] mt-0.5" style="color:rgba(255,255,255,.55)">Chassis, motor and battery — traced from vendor to customer.</p></div>
                </div>
                <div class="feat-row">
                    <span class="feat-ic"><i data-lucide="receipt" class="w-4 h-4" style="color:var(--ember)"></i></span>
                    <div><p class="text-white text-[14px] font-semibold leading-tight">Billing &amp; auto-numbered invoices</p>
                        <p class="text-[12.5px] mt-0.5" style="color:rgba(255,255,255,.55)">One screen from sale to a downloadable invoice.</p></div>
                </div>
                <div class="feat-row">
                    <span class="feat-ic"><i data-lucide="trending-up" class="w-4 h-4" style="color:var(--mint)"></i></span>
                    <div><p class="text-white text-[14px] font-semibold leading-tight">Turnover, sponsor income &amp; cashback</p>
                        <p class="text-[12.5px] mt-0.5" style="color:rgba(255,255,255,.55)">Every payout rule transparent and up to date.</p></div>
                </div>
            </div>
        </div>

        <p class="relative z-10 text-[12px]" style="color:rgba(255,255,255,.4)">&copy; <?php echo date('Y'); ?> Electrinc. All rights reserved.</p>
    </div>

    <!-- RIGHT — sign-in form -->
    <div class="w-full lg:w-[45%] xl:w-[42%] flex items-center justify-center px-5 sm:px-10 py-8 sm:py-12 bg-white">
        <div class="w-full max-w-[380px]">

            <div class="lg:hidden mobile-brand rounded-2xl mb-7 -mx-1">
                <div class="relative z-10 px-6 py-6 flex flex-col items-center text-center">
                    <img src="assets/electrinc-logo.png" alt="Electrinc" class="h-7 w-auto">
                    <p class="mt-3 text-[10.5px] font-bold tracking-[.2em] uppercase" style="color:var(--ember)">Franchise Partner Portal</p>
                    <p class="mt-1.5 text-[12.5px] leading-snug" style="color:rgba(255,255,255,.7)">Run your EV showroom, end to end.</p>
                </div>
            </div>

            <p class="text-[12px] font-bold tracking-[.16em] uppercase" style="color:var(--saffron-d)">Welcome back</p>
            <h2 class="mt-1.5 text-[26px] font-extrabold" style="color:var(--ink)">Sign in to your panel</h2>
            <p class="mt-2 text-[13.5px]" style="color:var(--ink-2)">Use the Franchise ID and password issued to your showroom.</p>

            <form method="POST" class="mt-8 space-y-5" autocomplete="off">

                <div class="fr-field">
                    <label for="fid">Franchise ID</label>
                    <i data-lucide="user" class="w-[17px] h-[17px] ic"></i>
                    <input id="fid" type="text" name="franchies_id" placeholder="e.g. FR001" autocomplete="off" required>
                </div>

                <div class="fr-field">
                    <label for="fpw">Password</label>
                    <i data-lucide="lock" class="w-[17px] h-[17px] ic"></i>
                    <input id="fpw" type="password" name="franchies_password" placeholder="••••••••" autocomplete="off" required>
                </div>

                <?php if ($error !== '') { ?>
                    <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-2.5 text-red-700 text-[13px] flex items-center gap-2">
                        <i data-lucide="alert-circle" class="w-4 h-4 shrink-0"></i> <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php } ?>

                <button type="submit" class="fr-btn">
                    Sign In <i data-lucide="arrow-right" class="w-4 h-4"></i>
                </button>
            </form>

            <p class="mt-6 flex items-center justify-center gap-1.5 text-[11.5px]" style="color:var(--ink-2)">
                <i data-lucide="shield-check" class="w-3.5 h-3.5" style="color:var(--green)"></i> Your connection to this panel is secure
            </p>
        </div>
    </div>
</div>

<script>if (typeof lucide !== 'undefined') lucide.createIcons();</script>
</body>
</html>