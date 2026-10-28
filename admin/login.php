<?php
session_start();
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.tailwindcss.com https://unpkg.com; style-src 'self' 'unsafe-inline' https://cdn.tailwindcss.com https://fonts.googleapis.com; img-src 'self' data: https://i.pravatar.cc; font-src 'self' data: https://fonts.gstatic.com; object-src 'none';");
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/admin_access_control.php';
$error = "";

function tableExists(mysqli $conn, string $tableName): bool {
    $safeName = $conn->real_escape_string($tableName);
    $result = $conn->query("SHOW TABLES LIKE '{$safeName}'");
    return $result instanceof mysqli_result && $result->num_rows > 0;
}

function getExistingColumns(mysqli $conn, string $tableName): array {
    $columns = [];
    $result = $conn->query("SHOW COLUMNS FROM `{$tableName}`");
    if (!($result instanceof mysqli_result)) {
        return $columns;
    }

    while ($row = $result->fetch_assoc()) {
        if (isset($row['Field'])) {
            $columns[] = $row['Field'];
        }
    }

    return $columns;
}

function findUserByLoginIdentifier(mysqli $conn, string $tableName, string $identifier): ?array {
    $existingColumns = getExistingColumns($conn, $tableName);
    $allowedIdentifierColumns = ['customer_id', 'email', 'mobile', 'member_id'];
    $identifierColumns = array_values(array_intersect($allowedIdentifierColumns, $existingColumns));

    if (empty($identifierColumns)) {
        return null;
    }

    $escapedIdentifier = $conn->real_escape_string($identifier);
    $conditions = array_map(static fn($col) => "`{$col}` = '{$escapedIdentifier}'", $identifierColumns);

    $nameColumn = in_array('full_name', $existingColumns, true)
        ? 'full_name'
        : (in_array('name', $existingColumns, true) ? 'name' : 'customer_id');

    $sql = "SELECT id, customer_id, {$nameColumn} AS full_name, password FROM `{$tableName}` WHERE " . implode(' OR ', $conditions) . " LIMIT 1";
    $result = $conn->query($sql);
    $user = $result && $result->num_rows === 1 ? $result->fetch_assoc() : null;

    return $user ?: null;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $member_id = trim($_POST['member_id'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($member_id) || empty($password)) {
        $error = "Please enter Member ID and Password.";
    } else {
        $authTables = ['signup_admin', 'signup_users'];
        $user = null;
        $matchedTable = '';

        foreach ($authTables as $table) {
            if (!tableExists($conn, $table)) {
                continue;
            }

            $user = findUserByLoginIdentifier($conn, $table, $member_id);
            if ($user) {
                $matchedTable = $table;
                break;
            }
        }

        if ($user) {
            $storedPassword = (string)($user['password'] ?? '');
            $passwordMatches = password_verify($password, $storedPassword) || hash_equals($storedPassword, $password);

            if ($passwordMatches) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['member_id'] = $user['customer_id'];
                $_SESSION['customer_id'] = $user['customer_id'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['admin_auth'] = true;

                $isSuperAdmin = ($matchedTable === 'signup_admin');
                $_SESSION['is_super_admin'] = $isSuperAdmin ? 1 : 0;
                $_SESSION['role'] = $isSuperAdmin ? 'admin' : 'member_admin';

                adminRefreshSessionPermissions($conn);

                header("Location: dashboard.php");
                exit();
            }
        }

        $error = "Invalid Admin ID or Password";
    }
}
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Sign In</title>
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

    .brand{
        position:relative; overflow:hidden; isolation:isolate;
        background:linear-gradient(150deg,var(--night) 0%,var(--night-2) 58%,#1E4C30 100%);
    }
    .brand::before{
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
            <p class="text-[12px] font-bold tracking-[.2em] uppercase" style="color:var(--ember)">Admin Console</p>
            <h1 class="mt-4 text-[34px] xl:text-[40px] font-extrabold leading-[1.12] text-white">
                Complete control of<br>your platform.
            </h1>
            <p class="mt-4 text-[15px] leading-relaxed" style="color:rgba(255,255,255,.72)">
                Configure income rules, oversee franchises and members, and keep everything auditable.
            </p>

            <div class="mt-9 space-y-5">
                <div class="feat-row">
                    <span class="feat-ic"><i data-lucide="sliders-horizontal" class="w-4 h-4" style="color:var(--mint)"></i></span>
                    <div><p class="text-white text-[14px] font-semibold leading-tight">Configurable income rules</p>
                        <p class="text-[12.5px] mt-0.5" style="color:rgba(255,255,255,.55)">Every %, cap and payout rule — with full change history.</p></div>
                </div>
                <div class="feat-row">
                    <span class="feat-ic"><i data-lucide="building-2" class="w-4 h-4" style="color:var(--ember)"></i></span>
                    <div><p class="text-white text-[14px] font-semibold leading-tight">Franchise &amp; member oversight</p>
                        <p class="text-[12.5px] mt-0.5" style="color:rgba(255,255,255,.55)">Approvals, KYC and reports in one console.</p></div>
                </div>
                <div class="feat-row">
                    <span class="feat-ic"><i data-lucide="shield-check" class="w-4 h-4" style="color:var(--mint)"></i></span>
                    <div><p class="text-white text-[14px] font-semibold leading-tight">Secure, auditable access</p>
                        <p class="text-[12.5px] mt-0.5" style="color:rgba(255,255,255,.55)">Role-based access with a complete activity trail.</p></div>
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
                    <p class="mt-3 text-[10.5px] font-bold tracking-[.2em] uppercase" style="color:var(--ember)">Admin Console</p>
                    <p class="mt-1.5 text-[12.5px] leading-snug" style="color:rgba(255,255,255,.7)">Complete control of your platform.</p>
                </div>
            </div>

            <p class="text-[12px] font-bold tracking-[.16em] uppercase" style="color:var(--saffron-d)">Welcome back</p>
            <h2 class="mt-1.5 text-[26px] font-extrabold" style="color:var(--ink)">Sign in to the console</h2>
            <p class="mt-2 text-[13.5px]" style="color:var(--ink-2)">Use your Admin ID and password to continue.</p>

            <form method="POST" class="mt-8 space-y-5">

                <div class="fr-field">
                    <label for="mid">Admin / Member ID</label>
                    <i data-lucide="user" class="w-[17px] h-[17px] ic"></i>
                    <input id="mid" type="text" name="member_id" placeholder="Enter Admin ID" autocomplete="off" required>
                </div>

                <div class="fr-field">
                    <label for="apw">Password</label>
                    <i data-lucide="lock" class="w-[17px] h-[17px] ic"></i>
                    <input id="apw" type="password" name="password" placeholder="••••••••" autocomplete="off" required>
                </div>

                <?php if($error!=""){ ?>
                    <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-2.5 text-red-700 text-[13px] flex items-center gap-2">
                        <i data-lucide="alert-circle" class="w-4 h-4 shrink-0"></i> <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php } ?>

                <div class="flex items-center justify-between text-[12.5px]" style="color:var(--ink-2)">
                    <label class="flex items-center gap-1.5"><input type="checkbox"> Remember Me</label>
                    <a href="#" class="font-semibold" style="color:var(--saffron-d)">Forgot password?</a>
                </div>

                <button type="submit" class="fr-btn">
                    Sign In <i data-lucide="arrow-right" class="w-4 h-4"></i>
                </button>
            </form>

            <!-- <div class="text-center mt-5 text-sm">
            Don't have an account?
            <a href="signup.php" class="text-blue-500">Sign Up</a>
            </div> -->

            <p class="mt-6 flex items-center justify-center gap-1.5 text-[11.5px]" style="color:var(--ink-2)">
                <i data-lucide="shield-check" class="w-3.5 h-3.5" style="color:var(--green)"></i> Your connection to this console is secure
            </p>
        </div>
    </div>
</div>

<script>if (typeof lucide !== 'undefined') lucide.createIcons();</script>
</body>
</html>