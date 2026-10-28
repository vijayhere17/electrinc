<?php
////////
error_reporting(E_ALL);
ini_set('display_errors', 1);
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// CLEAR OLD SESSION (VERY IMPORTANT)
session_unset();
session_destroy();
session_start();

require_once __DIR__ . '/config/db.php';

$error = "";

if (!isset($conn) || !($conn instanceof mysqli)) {
    die("Database connection failed. Please check config/db.php");
}

$conn->set_charset("utf8mb4");

function columnExists(mysqli $conn, string $table, string $column): bool
{
    $stmt = $conn->prepare("
        SELECT COUNT(*) AS c
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = ?
        AND COLUMN_NAME = ?
    ");

    if (!$stmt) {
        return false;
    }

    $stmt->bind_param("ss", $table, $column);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return !empty($row['c']);
}

$idColumn = "member_id";

if (columnExists($conn, "signup_users", "customer_id")) {
    $idColumn = "customer_id";
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $customer_id = trim($_POST["customer_id"] ?? "");
    $password    = trim($_POST["password"] ?? "");

    if ($customer_id === "" || $password === "") {
        $error = "Please enter Customer ID and Password.";
    } else {
        $sql = "
            SELECT 
                id,
                `$idColumn` AS customer_id,
                full_name,
                password
            FROM signup_users
            WHERE `$idColumn` = ?
            LIMIT 1
        ";

        $stmt = $conn->prepare($sql);

        if (!$stmt) {
            $error = "SQL Error: " . $conn->error;
        } else {
            $stmt->bind_param("s", $customer_id);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result && $result->num_rows === 1) {
                $user = $result->fetch_assoc();

                $dbPassword = $user["password"];
                $loginOk = false;

                if (password_verify($password, $dbPassword)) {
                    $loginOk = true;
                }

                if (!$loginOk && $password === $dbPassword) {
                    $loginOk = true;
                }

                if ($loginOk) {
                    session_regenerate_id(true);

                    $_SESSION["user_id"]     = $user["id"];
                    $_SESSION["customer_id"] = $user["customer_id"];
                    $_SESSION["member_id"]   = $user["customer_id"];
                    $_SESSION["full_name"]   = $user["full_name"];

                    header("Location: dashboard.php");
                    exit();
                } else {
                    $error = "Invalid Customer ID or Password.";
                }
            } else {
                $error = "Invalid Customer ID or Password.";
            }

            $stmt->close();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Member Sign In</title>
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
            <p class="text-[12px] font-bold tracking-[.2em] uppercase" style="color:var(--ember)">Member Portal</p>
            <h1 class="mt-4 text-[34px] xl:text-[40px] font-extrabold leading-[1.12] text-white">
                Your network, your<br>income — one place.
            </h1>
            <p class="mt-4 text-[15px] leading-relaxed" style="color:rgba(255,255,255,.72)">
                Track your team, every income type and your wallet — updated in real time.
            </p>

            <div class="mt-9 space-y-5">
                <div class="feat-row">
                    <span class="feat-ic"><i data-lucide="git-branch" class="w-4 h-4" style="color:var(--mint)"></i></span>
                    <div><p class="text-white text-[14px] font-semibold leading-tight">Grow your network</p>
                        <p class="text-[12.5px] mt-0.5" style="color:rgba(255,255,255,.55)">Sponsor, binary, level and pool income — tracked automatically.</p></div>
                </div>
                <div class="feat-row">
                    <span class="feat-ic"><i data-lucide="wallet" class="w-4 h-4" style="color:var(--ember)"></i></span>
                    <div><p class="text-white text-[14px] font-semibold leading-tight">Wallet &amp; withdrawals</p>
                        <p class="text-[12.5px] mt-0.5" style="color:rgba(255,255,255,.55)">P2P transfers and bank payouts, on your withdrawal dates.</p></div>
                </div>
                <div class="feat-row">
                    <span class="feat-ic"><i data-lucide="bar-chart-3" class="w-4 h-4" style="color:var(--mint)"></i></span>
                    <div><p class="text-white text-[14px] font-semibold leading-tight">Transparent income reports</p>
                        <p class="text-[12.5px] mt-0.5" style="color:rgba(255,255,255,.55)">Every income type, one clear dashboard.</p></div>
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
                    <p class="mt-3 text-[10.5px] font-bold tracking-[.2em] uppercase" style="color:var(--ember)">Member Portal</p>
                    <p class="mt-1.5 text-[12.5px] leading-snug" style="color:rgba(255,255,255,.7)">Your network, your income — one place.</p>
                </div>
            </div>

            <p class="text-[12px] font-bold tracking-[.16em] uppercase" style="color:var(--saffron-d)">Welcome back</p>
            <h2 class="mt-1.5 text-[26px] font-extrabold" style="color:var(--ink)">Sign in to your account</h2>
            <p class="mt-2 text-[13.5px]" style="color:var(--ink-2)">Use the Customer ID and password from your registration.</p>

            <form method="POST" class="mt-8 space-y-5">

                <div class="fr-field">
                    <label for="cid">Customer ID</label>
                    <i data-lucide="user" class="w-[17px] h-[17px] ic"></i>
                    <input id="cid" type="text" name="customer_id" placeholder="Enter Customer ID" autocomplete="off" required>
                </div>

                <div class="fr-field">
                    <label for="pw">Password</label>
                    <i data-lucide="lock" class="w-[17px] h-[17px] ic"></i>
                    <input id="pw" type="password" name="password" placeholder="••••••••" autocomplete="off" required>
                </div>

                <?php if ($error !== ""): ?>
                    <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-2.5 text-red-700 text-[13px] flex items-center gap-2">
                        <i data-lucide="alert-circle" class="w-4 h-4 shrink-0"></i> <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <div class="flex items-center justify-between text-[12.5px]" style="color:var(--ink-2)">
                    <label class="flex items-center gap-1.5"><input type="checkbox"> Remember Me</label>
                    <a href="forgot_password.php" class="font-semibold" style="color:var(--saffron-d)">Forgot password?</a>
                </div>

                <button type="submit" class="fr-btn">
                    Sign In <i data-lucide="arrow-right" class="w-4 h-4"></i>
                </button>
            </form>

            <p class="mt-6 text-center text-[13px]" style="color:var(--ink-2)">
                Don't have an account? <a href="signup.php" class="font-semibold" style="color:var(--saffron-d)">Sign Up</a>
            </p>

            <p class="mt-4 flex items-center justify-center gap-1.5 text-[11.5px]" style="color:var(--ink-2)">
                <i data-lucide="shield-check" class="w-3.5 h-3.5" style="color:var(--green)"></i> Your connection to this panel is secure
            </p>
        </div>
    </div>
</div>

<script>if (typeof lucide !== 'undefined') lucide.createIcons();</script>
</body>
</html>