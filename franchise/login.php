<?php
session_start();

require_once __DIR__ . '/config.php';

if (!isset($conn) || !($conn instanceof mysqli)) {
    die("Database connection failed.");
}

$error = '';

/* ─────────────────────────────────────────────
   Helper: table existence check
───────────────────────────────────────────── */
function tableExists(mysqli $conn, string $table): bool
{
    $t   = $conn->real_escape_string($table);
    $res = $conn->query("SHOW TABLES LIKE '$t'");
    return ($res && $res->num_rows > 0);
}

/* ─────────────────────────────────────────────
   Helper: column existence check
───────────────────────────────────────────── */
function columnExists(mysqli $conn, string $table, string $column): bool
{
    if (!tableExists($conn, $table)) return false;
    $t = $conn->real_escape_string($table);
    $c = $conn->real_escape_string($column);
    $res = $conn->query("SHOW COLUMNS FROM `$t` LIKE '$c'");
    return ($res && $res->num_rows > 0);
}

/* ─────────────────────────────────────────────
   STATE / CITY: generic login row fetch
───────────────────────────────────────────── */
function getLoginRow(mysqli $conn, string $table, array $idColumns, string $loginId): ?array
{
    if (!tableExists($conn, $table)) return null;

    foreach ($idColumns as $column) {
        if (!columnExists($conn, $table, $column)) continue;

        $stmt = $conn->prepare("SELECT * FROM `$table` WHERE `$column` = ? LIMIT 1");
        if (!$stmt) continue;

        $stmt->bind_param("s", $loginId);
        $stmt->execute();
        $result = $stmt->get_result();
        $row    = ($result && $result->num_rows > 0) ? $result->fetch_assoc() : null;
        $stmt->close();

        if ($row) {
            $row['_login_column'] = $column;
            return $row;
        }
    }
    return null;
}

/* ─────────────────────────────────────────────
   MOBILE FRANCHISE: dedicated login function
   Scans ALL columns in mobile_franchies_holder
   to find the ID — no hardcoding risk
───────────────────────────────────────────── */
function getMobileLoginRow(mysqli $conn, string $loginId): ?array
{
    $table = 'mobile_franchies_holder';
    if (!tableExists($conn, $table)) return null;

    // Priority ID columns for mobile table
    $idColumns = [
        'mobile_franchies_id',
        'mobile_franchise_id',
        'franchies_id',
        'franchises_id',
        'franchise_id',
    ];

    foreach ($idColumns as $col) {
        // Check column exists before querying
        $chk = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$col'");
        if (!$chk || $chk->num_rows === 0) continue;

        $stmt = $conn->prepare("SELECT * FROM `$table` WHERE `$col` = ? LIMIT 1");
        if (!$stmt) continue;

        $stmt->bind_param("s", $loginId);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result && $result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $stmt->close();
            $row['_login_column'] = $col;
            return $row;
        }
        $stmt->close();
    }

    return null;
}

/* ─────────────────────────────────────────────
   Universal password verifier
   Works for ALL franchise types
   Tries every known password column name
───────────────────────────────────────────── */
function verifyPassword(array $row, string $password): bool
{
    $passwordColumns = [
        'mobile_franchies_password',   // mobile table
        'plain_password',              // mobile table plain text
        'city_franchies_password',     // city table
        'franchies_password',          // state table
        'franchises_password',
        'password_hash',
        'password',
        'password_text',
        'franchies_password_text',
        'franchises_password_text',
    ];

    foreach ($passwordColumns as $col) {
        if (!isset($row[$col])) continue;
        $dbPass = trim((string)$row[$col]);
        if ($dbPass === '') continue;

        // Try bcrypt / argon2 hash first
        if (password_get_info($dbPass)['algo'] !== 0) {
            if (password_verify($password, $dbPass)) return true;
        }

        // Plain text fallback
        if ($dbPass === $password) return true;
    }

    return false;
}

/* ─────────────────────────────────────────────
   Set session for any franchise type
───────────────────────────────────────────── */
function setFranchiseSession(array $row, string $type, string $table, string $loginId): void
{
    session_regenerate_id(true);

    $col  = $row['_login_column'] ?? '';
    $code = ($col && isset($row[$col])) ? $row[$col] : $loginId;

    $_SESSION['franchise_id']       = (int)($row['id'] ?? 0);
    $_SESSION['franchise_type']     = $type;   // 'state' | 'city' | 'mobile'
    $_SESSION['franchise_table']    = $table;
    $_SESSION['franchise_login_id'] = $code;
    $_SESSION['franchise_code']     = $code;
    $_SESSION['franchise_name']     = $row['owner_name']
                                   ?? $row['shop_name']
                                   ?? $row['name']
                                   ?? $row['full_name']
                                   ?? 'Franchise';
    $_SESSION['franchise_email']    = $row['email']  ?? '';
    $_SESSION['franchise_mobile']   = $row['mobile'] ?? '';
    $_SESSION['franchise_state']    = $row['state']  ?? '';
    $_SESSION['franchise_city']     = $row['city']   ?? '';
}

/* ─────────────────────────────────────────────
   Already logged in → redirect
   Only redirect when BOTH keys are genuinely set
   (prevents stale session from changing UI after logout)
───────────────────────────────────────────── */
if (
    isset($_SESSION['franchise_id'], $_SESSION['franchise_type']) &&
    (int)$_SESSION['franchise_id'] > 0 &&
    !empty($_SESSION['franchise_type'])
) {
    header("Location: dashboard.php");
    exit();
}

/* ─────────────────────────────────────────────
   POST: process login
   Order: State → City → Mobile
───────────────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $loginId  = trim($_POST['franchises_id']       ?? $_POST['franchies_id']       ?? '');
    $password = trim($_POST['franchises_password']  ?? $_POST['franchies_password']  ?? '');

    if ($loginId === '' || $password === '') {
        $error = "Please enter Franchise ID and Password.";

    } else {

        $matched = false;

        /* ── 1. STATE ─────────────────────────────── */
        $row = getLoginRow($conn, 'franchies_holder', [
            'franchies_id', 'franchises_id', 'franchise_id',
        ], $loginId);

        if ($row) {
            $matched = true;
            if (isset($row['status']) && strtolower(trim($row['status'])) !== 'active') {
                $error = "Your franchise account is inactive. Please contact support.";
            } elseif (!verifyPassword($row, $password)) {
                $error = "Invalid Franchise ID or Password.";
            } else {
                setFranchiseSession($row, 'state', 'franchies_holder', $loginId);
                header("Location: dashboard.php");
                exit();
            }
        }

        /* ── 2. CITY ──────────────────────────────── */
        if (!$matched) {
            $row = getLoginRow($conn, 'city_franchies_holder', [
                'city_franchies_id', 'city_franchise_id',
                'franchies_id', 'franchises_id', 'franchise_id',
            ], $loginId);

            if ($row) {
                $matched = true;
                if (isset($row['status']) && strtolower(trim($row['status'])) !== 'active') {
                    $error = "Your franchise account is inactive. Please contact support.";
                } elseif (!verifyPassword($row, $password)) {
                    $error = "Invalid Franchise ID or Password.";
                } else {
                    setFranchiseSession($row, 'city', 'city_franchies_holder', $loginId);
                    header("Location: dashboard.php");
                    exit();
                }
            }
        }

        /* ── 3. MOBILE ────────────────────────────── */
        if (!$matched) {
            $row = getMobileLoginRow($conn, $loginId);

            if ($row) {
                $matched = true;
                if (isset($row['status']) && strtolower(trim($row['status'])) !== 'active') {
                    $error = "Your franchise account is inactive. Please contact support.";
                } elseif (!verifyPassword($row, $password)) {
                    $error = "Invalid Franchise ID or Password.";
                } else {
                    setFranchiseSession($row, 'mobile', 'mobile_franchies_holder', $loginId);
                    header("Location: dashboard.php");
                    exit();
                }
            }
        }

        /* ── No match anywhere ────────────────────── */
        if (!$matched) {
            $error = "Invalid Franchise ID or Password.";
        }
    }
}

/* ─────────────────────────────────────────────
   Logo path: try to detect the correct base path
   automatically so it works on any server setup
───────────────────────────────────────────── */
$scriptDir  = dirname($_SERVER['SCRIPT_NAME']); // e.g. /freshiya_main
$scriptDir  = rtrim($scriptDir, '/');
$logoSrc    = $scriptDir . '/assets/electrinc-logo.png';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Netvexa – Sign In</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --gold:      #c7922f;
            --gold-dark: #a97520;
            --bg:        #f0f1f3;
            --card:      #f8f8fa;
            --text:      #1a1a1a;
            --muted:     #6b7280;
            --border:    #d1d5db;
            --red:       #dc2626;
            --radius:    16px;
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: var(--bg);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 16px;
        }

        /* ── Card ── */
        .card {
            background: var(--card);
            border-radius: var(--radius);
            padding: clamp(28px, 6vw, 48px) clamp(24px, 5vw, 48px);
            width: 100%;
            max-width: 560px;
            box-shadow: 0 4px 24px rgba(0,0,0,0.09);
        }

        /* ── Header ── */
        .header {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 12px;
            margin-bottom: 32px;
        }

        .logo-wrap {
            width: clamp(140px, 32vw, 190px);
            height: 66px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #103258;
            border-radius: 12px;
            padding: 8px 16px;
        }

        .logo {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
            display: block;
        }

        /* Hide broken-image icon if logo fails to load */
        .logo[src=""] ,
        .logo:not([src]) {
            display: none;
        }

        .header h1 {
            font-size: clamp(20px, 4vw, 26px);
            font-weight: 700;
            color: var(--text);
            letter-spacing: -0.4px;
        }

        /* ── Badge strip ── */
        .badge-strip {
            display: flex;
            justify-content: center;
            gap: 8px;
            flex-wrap: wrap;
            margin-bottom: 28px;
        }

        .badge {
            font-size: 11px;
            font-weight: 600;
            padding: 4px 12px;
            border-radius: 20px;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }

        .badge-state  { background: #fef3c7; color: #92400e; border: 1px solid #f9c74f; }
        .badge-city   { background: #dbeafe; color: #1e3a5f; border: 1px solid #93c5fd; }
        .badge-mobile { background: #d1fae5; color: #065f46; border: 1px solid #6ee7b7; }

        /* ── Form groups ── */
        .form-group {
            display: flex;
            align-items: center;
            gap: 12px;
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 10px 14px;
            background: #fff;
            margin-bottom: 16px;
            transition: border-color .2s, box-shadow .2s;
        }

        .form-group:focus-within {
            border-color: var(--gold);
            box-shadow: 0 0 0 3px rgba(199,146,47,.15);
        }

        .form-icon {
            width: 36px;
            height: 36px;
            background: #f3f4f6;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            flex-shrink: 0;
        }

        .form-group input {
            flex: 1;
            border: none;
            outline: none;
            font-family: inherit;
            font-size: 14px;
            color: var(--text);
            background: transparent;
            min-width: 0;
        }

        .form-group input::placeholder { color: var(--muted); }

        .toggle-pw {
            background: none;
            border: none;
            cursor: pointer;
            color: var(--muted);
            font-size: 18px;
            padding: 0 2px;
            flex-shrink: 0;
            line-height: 1;
        }

        /* ── Error box ── */
        .error-box {
            background: #fef2f2;
            border: 1px solid #fecaca;
            border-radius: 8px;
            color: var(--red);
            font-size: 13px;
            padding: 10px 14px;
            text-align: center;
            margin-bottom: 18px;
        }

        /* ── Submit button ── */
        .btn-login {
            display: block;
            width: 100%;
            padding: 13px;
            background: var(--gold);
            color: #fff;
            font-family: inherit;
            font-size: 15px;
            font-weight: 700;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            letter-spacing: 0.3px;
            transition: background .2s, transform .1s, box-shadow .2s;
        }

        .btn-login:hover  { background: var(--gold-dark); box-shadow: 0 4px 14px rgba(199,146,47,.35); }
        .btn-login:active { transform: scale(.98); }

        /* ── Footer note ── */
        .footer-note {
            text-align: center;
            font-size: 12px;
            color: var(--muted);
            margin-top: 20px;
        }

        /* ── Responsive ── */
        @media (max-width: 400px) {
            .badge-strip { gap: 6px; }
            .badge { font-size: 10px; padding: 3px 9px; }
            .form-group { padding: 8px 10px; }
            .form-icon { width: 30px; height: 30px; font-size: 14px; }
        }
    </style>
</head>

<body>

<div class="card">

    <!-- Header -->
    <div class="header">
        <div class="logo-wrap">
            <img
                src="<?= htmlspecialchars($logoSrc, ENT_QUOTES, 'UTF-8') ?>"
                alt="Netvexa Logo"
                class="logo"
                onerror="this.style.display='none'"
            >
        </div>
        <h1>Franchise Sign In</h1>
    </div>

    <!-- Franchise type badges -->
    <div class="badge-strip">
        <span class="badge badge-state">🏛 State</span>
        <span class="badge badge-city">🏙 City</span>
        <span class="badge badge-mobile">📱 Mobile</span>
    </div>

    <!-- Login form -->
    <form method="POST" autocomplete="off">

        <!-- Franchise ID -->
        <div class="form-group">
            <div class="form-icon">👤</div>
            <input
                type="text"
                name="franchises_id"
                id="franchises_id"
                placeholder="Enter Franchise ID"
                value="<?= htmlspecialchars($_POST['franchises_id'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                required
                autocomplete="username"
            >
        </div>

        <!-- Password -->
        <div class="form-group">
            <div class="form-icon">🔒</div>
            <input
                type="password"
                name="franchises_password"
                id="franchises_password"
                placeholder="Enter Password"
                required
                autocomplete="current-password"
            >
            <button
                type="button"
                class="toggle-pw"
                onclick="togglePassword()"
                aria-label="Show/hide password"
                id="toggleBtn"
            >👁</button>
        </div>

        <!-- Error message -->
        <?php if ($error !== ''): ?>
            <div class="error-box" role="alert">
                ⚠️ <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <button type="submit" class="btn-login">Login</button>

    </form>

    <p class="footer-note">
        State &bull; City &bull; Mobile Franchise &mdash; all supported
    </p>

</div>

<script>
function togglePassword() {
    const input = document.getElementById('franchises_password');
    const btn   = document.getElementById('toggleBtn');
    if (input.type === 'password') {
        input.type    = 'text';
        btn.textContent = '🙈';
    } else {
        input.type    = 'password';
        btn.textContent = '👁';
    }
}
</script>

</body>
</html>