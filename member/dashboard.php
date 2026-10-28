<?php
include __DIR__ . '/session_bootstrap.php';
include __DIR__ . '/member_auth.php';
include __DIR__ . '/config/db.php';

header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.tailwindcss.com https://unpkg.com; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' data: https://fonts.gstatic.com; img-src 'self' data: https://i.pravatar.cc; object-src 'none';");

if (
    !isset($_SESSION['customer_id']) &&
    !isset($_SESSION['member_id']) &&
    empty($_SESSION['admin_preview'])
) {
    header("Location: login.php");
    exit();
}

if (!isset($conn) || !($conn instanceof mysqli)) {
    die("Database connection failed. Please check config/db.php");
}

$conn->set_charset("utf8mb4");

$sessionCustomerId = $_SESSION['customer_id'] ?? $_SESSION['member_id'] ?? '';
$requestedPreviewMemberId = trim((string)($_GET['member_id'] ?? ''));
$isPreviewRequest = (isset($_GET['preview']) && $_GET['preview'] === '1');

if ($isPreviewRequest && !empty($_SESSION['admin_view_mode']) && $requestedPreviewMemberId !== '') {
    $_SESSION['member_id'] = $requestedPreviewMemberId;
    $_SESSION['customer_id'] = $requestedPreviewMemberId;
    $_SESSION['user_id'] = $requestedPreviewMemberId;
    $_SESSION['admin_view_member_id'] = $requestedPreviewMemberId;
    $_SESSION['preview_selected_member_id'] = $requestedPreviewMemberId;
    $sessionCustomerId = $requestedPreviewMemberId;
}

$memberRow = null;

function tableExists(mysqli $conn, string $tableName): bool
{
    $safeName = $conn->real_escape_string($tableName);
    $result = $conn->query("SHOW TABLES LIKE '{$safeName}'");
    return $result instanceof mysqli_result && $result->num_rows > 0;
}

function getTableColumns(mysqli $conn, string $tableName): array
{
    $columns = [];
    $safeName = $conn->real_escape_string($tableName);
    $result = $conn->query("SHOW COLUMNS FROM `{$safeName}`");
    if ($result instanceof mysqli_result) {
        while ($row = $result->fetch_assoc()) {
            if (isset($row['Field'])) {
                $columns[] = strtolower((string)$row['Field']);
            }
        }
    }
    return $columns;
}

function hasColumn(array $columns, string $column): bool
{
    return in_array(strtolower($column), $columns, true);
}

function firstExistingColumn(array $columns, array $candidates): ?string
{
    foreach ($candidates as $candidate) {
        if (hasColumn($columns, $candidate)) {
            return strtolower($candidate);
        }
    }
    return null;
}

function formatIndianCurrency($amount): string
{
    return '₹' . number_format((float)$amount, 2);
}

function formatNumberValue($number): string
{
    return number_format((int)$number);
}

function getAllDownlineIds(mysqli $conn, string $rootId, string $placementCol, string $customerCol): array
{
    $allIds  = [];
    $queue   = [$rootId];
    $visited = [];

    while (!empty($queue)) {
        $current = array_shift($queue);
        if (isset($visited[$current])) continue;
        $visited[$current] = true;

        $safePC = $conn->real_escape_string($placementCol);
        $safeCC = $conn->real_escape_string($customerCol);
        $safeId = $conn->real_escape_string($current);

        $res = $conn->query("
            SELECT `{$safeCC}` AS cid
            FROM signup_users
            WHERE `{$safePC}` = '{$safeId}'
        ");

        if ($res instanceof mysqli_result) {
            while ($row = $res->fetch_assoc()) {
                $cid = (string)$row['cid'];
                if (!isset($visited[$cid])) {
                    $allIds[] = $cid;
                    $queue[]  = $cid;
                }
            }
        }
    }

    return $allIds;
}

$signupColumns = tableExists($conn, 'signup_users') ? getTableColumns($conn, 'signup_users') : [];

$customerCol      = firstExistingColumn($signupColumns, ['customer_id', 'member_id', 'user_id']);
$nameCol          = firstExistingColumn($signupColumns, ['full_name', 'name']);
$statusCol        = firstExistingColumn($signupColumns, ['registration_status', 'active_status', 'status']);
$purchaseBalCol   = firstExistingColumn($signupColumns, ['purchase_balance']);
$repurchaseBalCol = firstExistingColumn($signupColumns, ['repurchase_balance']);
$turnoverCol      = firstExistingColumn($signupColumns, ['total_turnover']);
$placementCol     = firstExistingColumn($signupColumns, ['placement_id', 'parent_id']);
$positionCol      = firstExistingColumn($signupColumns, ['position']);
$referralCol      = firstExistingColumn($signupColumns, ['referral_id', 'sponsor_id', 'referred_by', 'introducer_id', 'upline_id']);

if ($customerCol) {
    $selectParts = [];
    $selectParts[] = "`{$customerCol}` AS customer_id";
    $selectParts[] = $nameCol ? "`{$nameCol}` AS full_name" : "'' AS full_name";

    if ($statusCol) {
        if ($statusCol === 'status') {
            $selectParts[] = "CASE
                WHEN `status` IN ('1', 1, 'Active', 'active') THEN 'Active'
                WHEN `status` IN ('0', 0, 'Inactive', 'inactive') THEN 'Inactive'
                ELSE `status`
            END AS registration_status";
        } else {
            $selectParts[] = "`{$statusCol}` AS registration_status";
        }
    } else {
        $selectParts[] = "'Active' AS registration_status";
    }

    $selectParts[] = $purchaseBalCol   ? "`{$purchaseBalCol}` AS purchase_balance"    : "0 AS purchase_balance";
    $selectParts[] = $repurchaseBalCol ? "`{$repurchaseBalCol}` AS repurchase_balance" : "0 AS repurchase_balance";
    $selectParts[] = $turnoverCol      ? "`{$turnoverCol}` AS total_turnover"          : "0 AS total_turnover";

    $sql = "SELECT " . implode(", ", $selectParts) . "
            FROM signup_users
            WHERE `{$customerCol}` = ?
            LIMIT 1";

    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("s", $sessionCustomerId);
        $stmt->execute();
        $result    = $stmt->get_result();
        $memberRow = $result ? $result->fetch_assoc() : null;
        $stmt->close();
    }
}

$memberName         = $memberRow['full_name']            ?? ($_SESSION['full_name'] ?? 'Member');
$customerId         = $memberRow['customer_id']          ?? $sessionCustomerId;
$registrationStatus = $memberRow['registration_status'] ?? 'Active';
$purchaseBalance    = isset($memberRow['purchase_balance'])   ? (float)$memberRow['purchase_balance']   : 0;
$repurchaseBalance  = isset($memberRow['repurchase_balance']) ? (float)$memberRow['repurchase_balance'] : 0;
$totalTurnover      = isset($memberRow['total_turnover'])     ? (float)$memberRow['total_turnover']     : 0;

$totalLeftMembers       = 0;
$totalRightMembers      = 0;
$totalRegisteredMembers = 0;
$totalActiveMembers     = 0;
$myDirectReferrals      = 0;
$totalIncome            = 0;
$totalPurchases         = 0;
$thisMonthPurchase      = 0;

if ($placementCol && $customerCol) {
    $allDownlineIds = getAllDownlineIds($conn, $customerId, $placementCol, $customerCol);
    $totalRegisteredMembers = count($allDownlineIds);

    if ($totalRegisteredMembers > 0) {
        $placeholders = implode(',', array_fill(0, count($allDownlineIds), '?'));
        $types        = str_repeat('s', count($allDownlineIds));

        if ($statusCol) {
            if ($statusCol === 'status') {
                $activeSql = "SELECT COUNT(*) AS total_active
                              FROM signup_users
                              WHERE `{$customerCol}` IN ({$placeholders})
                                AND `{$statusCol}` IN ('1', 1, 'Active', 'active')";
            } else {
                $activeSql = "SELECT COUNT(*) AS total_active
                              FROM signup_users
                              WHERE `{$customerCol}` IN ({$placeholders})
                                AND LOWER(`{$statusCol}`) IN ('active', '1')";
            }

            $activeStmt = $conn->prepare($activeSql);
            if ($activeStmt) {
                $activeStmt->bind_param($types, ...$allDownlineIds);
                $activeStmt->execute();
                $activeRow          = $activeStmt->get_result()->fetch_assoc();
                $totalActiveMembers = (int)($activeRow['total_active'] ?? 0);
                $activeStmt->close();
                
                /* CORRECT LEFT & RIGHT TEAM COUNT */
if ($placementCol && $positionCol && $customerCol) {

    // LEFT DIRECT MEMBER
    $leftDirectId = null;

    $leftStmt = $conn->prepare("
        SELECT `{$customerCol}` AS cid
        FROM signup_users
        WHERE `{$placementCol}` = ?
        AND UPPER(`{$positionCol}`) = 'L'
        LIMIT 1
    ");

    if ($leftStmt) {
        $leftStmt->bind_param("s", $customerId);
        $leftStmt->execute();

        $leftResult = $leftStmt->get_result()->fetch_assoc();

        if ($leftResult) {
            $leftDirectId = $leftResult['cid'];
        }

        $leftStmt->close();
    }

    // RIGHT DIRECT MEMBER
    $rightDirectId = null;

    $rightStmt = $conn->prepare("
        SELECT `{$customerCol}` AS cid
        FROM signup_users
        WHERE `{$placementCol}` = ?
        AND UPPER(`{$positionCol}`) = 'R'
        LIMIT 1
    ");

    if ($rightStmt) {
        $rightStmt->bind_param("s", $customerId);
        $rightStmt->execute();

        $rightResult = $rightStmt->get_result()->fetch_assoc();

        if ($rightResult) {
            $rightDirectId = $rightResult['cid'];
        }

        $rightStmt->close();
    }

    // TOTAL LEFT TEAM
    if ($leftDirectId) {
        $leftTeamIds = getAllDownlineIds(
            $conn,
            $leftDirectId,
            $placementCol,
            $customerCol
        );

        $totalLeftMembers = count($leftTeamIds) + 1;
    }

    // TOTAL RIGHT TEAM
    if ($rightDirectId) {
        $rightTeamIds = getAllDownlineIds(
            $conn,
            $rightDirectId,
            $placementCol,
            $customerCol
        );

        $totalRightMembers = count($rightTeamIds) + 1;
    }
}
            }
        }

        
    }
}

if ($referralCol && $customerCol) {
    $directStmt = $conn->prepare("
        SELECT COUNT(*) AS total_direct
        FROM signup_users
        WHERE `{$referralCol}` = ?
    ");
    if ($directStmt) {
        $directStmt->bind_param("s", $customerId);
        $directStmt->execute();
        $directRow         = $directStmt->get_result()->fetch_assoc();
        $myDirectReferrals = (int)($directRow['total_direct'] ?? 0);
        $directStmt->close();
    }
}

if (tableExists($conn, 'repurchase_orders')) {
    $orderColumns     = getTableColumns($conn, 'repurchase_orders');
    $orderCustomerCol = firstExistingColumn($orderColumns, ['customer_id', 'member_id', 'user_id']);
    $orderAmountCol   = firstExistingColumn($orderColumns, ['grand_total', 'total_amount', 'amount', 'final_amount', 'net_amount']);
    $orderDateCol     = firstExistingColumn($orderColumns, ['created_at', 'order_date', 'purchase_date', 'date', 'added_on']);

    if ($orderCustomerCol && $orderAmountCol) {
        $purchaseStmt = $conn->prepare("
            SELECT COALESCE(SUM(`{$orderAmountCol}`), 0) AS total_purchase
            FROM repurchase_orders
            WHERE `{$orderCustomerCol}` = ?
        ");
        if ($purchaseStmt) {
            $purchaseStmt->bind_param("s", $customerId);
            $purchaseStmt->execute();
            $purchaseRow    = $purchaseStmt->get_result()->fetch_assoc();
            $totalPurchases = (float)($purchaseRow['total_purchase'] ?? 0);
            $purchaseStmt->close();
        }
    }
}

$incomeTables = ['income_wallet', 'member_income', 'wallet_income', 'daily_income', 'income_history'];
foreach ($incomeTables as $incomeTable) {
    if (!tableExists($conn, $incomeTable)) continue;
    $incomeCols        = getTableColumns($conn, $incomeTable);
    $incomeCustomerCol = firstExistingColumn($incomeCols, ['customer_id', 'member_id', 'user_id']);
    $incomeAmountCol   = firstExistingColumn($incomeCols, ['amount', 'income_amount', 'credit', 'net_amount']);
    if ($incomeCustomerCol && $incomeAmountCol) {
        $incomeStmt = $conn->prepare("
            SELECT COALESCE(SUM(`{$incomeAmountCol}`), 0) AS total_income
            FROM `{$incomeTable}`
            WHERE `{$incomeCustomerCol}` = ?
        ");
        if ($incomeStmt) {
            $incomeStmt->bind_param("s", $customerId);
            $incomeStmt->execute();
            $incomeRow   = $incomeStmt->get_result()->fetch_assoc();
            $totalIncome = (float)($incomeRow['total_income'] ?? 0);
            $incomeStmt->close();
            break;
        }
    }
}

if ($totalIncome == 0 && hasColumn($signupColumns, 'wallet_balance') && $customerCol) {
    $wbStmt = $conn->prepare("SELECT wallet_balance FROM signup_users WHERE `{$customerCol}` = ? LIMIT 1");
    if ($wbStmt) {
        $wbStmt->bind_param("s", $customerId);
        $wbStmt->execute();
        $wbRow       = $wbStmt->get_result()->fetch_assoc();
        $totalIncome = (float)($wbRow['wallet_balance'] ?? 0);
        $wbStmt->close();
    }
}

/* ── Direct Sponsor Income (Income Plan #1) ── */
$directSponsorIncome    = 0.0;
$directSponsorFund      = 0.0;
$directSponsorWithdraw  = 0.0;
if (tableExists($conn, 'direct_sponsor_income')) {
    $dsStmt = $conn->prepare("SELECT COALESCE(SUM(income_amount),0) inc, COALESCE(SUM(fund_amount),0) fnd, COALESCE(SUM(withdrawable_amount),0) wd
                              FROM direct_sponsor_income WHERE sponsor_id = ?");
    if ($dsStmt) {
        $dsStmt->bind_param("s", $customerId);
        $dsStmt->execute();
        $dsRow = $dsStmt->get_result()->fetch_assoc();
        $directSponsorIncome   = (float)($dsRow['inc'] ?? 0);
        $directSponsorFund     = (float)($dsRow['fnd'] ?? 0);
        $directSponsorWithdraw = (float)($dsRow['wd'] ?? 0);
        $dsStmt->close();
    }
}

/* ── Binary Income (Income Plan #2) ── */
$binaryIncomePaid     = 0.0;
$binaryIncomeWithdraw = 0.0;
$binaryIncomeFlushed  = 0.0;
$binaryPairs          = 0;
if (tableExists($conn, 'binary_income')) {
    $biStmt = $conn->prepare("SELECT COUNT(*) cnt, COALESCE(SUM(paid_amount),0) paid, COALESCE(SUM(withdrawable_amount),0) wd, COALESCE(SUM(flushed_amount),0) fl
                              FROM binary_income WHERE customer_id = ?");
    if ($biStmt) {
        $biStmt->bind_param("s", $customerId);
        $biStmt->execute();
        $biRow = $biStmt->get_result()->fetch_assoc();
        $binaryPairs          = (int)($biRow['cnt'] ?? 0);
        $binaryIncomePaid     = (float)($biRow['paid'] ?? 0);
        $binaryIncomeWithdraw = (float)($biRow['wd'] ?? 0);
        $binaryIncomeFlushed  = (float)($biRow['fl'] ?? 0);
        $biStmt->close();
    }
}

/* ── Unified income summary (all types) from member_income_ledger ── */
$incomeByType      = [];   // type => paid
$incomeTotalPaid   = 0.0;
$incomeTotalFlush  = 0.0;
$incomeCappedPaid  = 0.0;
if (tableExists($conn, 'member_income_ledger')) {
    $ilStmt = $conn->prepare("SELECT income_type,
                                     COALESCE(SUM(paid_amount),0)    AS paid,
                                     COALESCE(SUM(flushed_amount),0) AS flushed,
                                     COALESCE(SUM(CASE WHEN counts_to_cap=1 THEN paid_amount ELSE 0 END),0) AS capped
                              FROM member_income_ledger WHERE customer_id = ? GROUP BY income_type");
    if ($ilStmt) {
        $ilStmt->bind_param("s", $customerId);
        $ilStmt->execute();
        $ilRes = $ilStmt->get_result();
        while ($ir = $ilRes->fetch_assoc()) {
            $incomeByType[$ir['income_type']] = (float)$ir['paid'];
            $incomeTotalPaid  += (float)$ir['paid'];
            $incomeTotalFlush += (float)$ir['flushed'];
            $incomeCappedPaid += (float)$ir['capped'];
        }
        $ilStmt->close();
    }
}
$levelIncome = [
    1 => $incomeByType['Level 1'] ?? 0.0,
    2 => $incomeByType['Level 2'] ?? 0.0,
    3 => $incomeByType['Level 3'] ?? 0.0,
    4 => $incomeByType['Level 4'] ?? 0.0,
    5 => $incomeByType['Level 5'] ?? 0.0,
];
$levelIncomeTotal = array_sum($levelIncome);
$autoPoolIncome   = $incomeByType['Auto Pool'] ?? 0.0;
$autoPoolPools    = 0;
if (tableExists($conn, 'auto_pool_positions')) {
    $apS = $conn->prepare("SELECT COUNT(*) c FROM auto_pool_positions WHERE customer_id = ?");
    if ($apS) {
        $apS->bind_param("s", $customerId);
        $apS->execute();
        $autoPoolPools = (int)($apS->get_result()->fetch_assoc()['c'] ?? 0);
        $apS->close();
    }
}

/* ── Performance Bonus (Income Plan #5) — this month's directs + total earned ── */
$perfBonusIncome = $incomeByType['Performance Bonus'] ?? 0.0;
$perfDirectsThisMonth = 0;
if (tableExists($conn, 'performance_bonus_contributions')) {
    $pk = date('Y-m');
    $pbS = $conn->prepare("SELECT COUNT(*) c FROM performance_bonus_contributions WHERE sponsor_id = ? AND period_key = ?");
    if ($pbS) {
        $pbS->bind_param("ss", $customerId, $pk);
        $pbS->execute();
        $perfDirectsThisMonth = (int)($pbS->get_result()->fetch_assoc()['c'] ?? 0);
        $pbS->close();
    }
}

/* ── Team Performance Bonus (Income Plan #6) — this month's matching business + total earned ── */
$teamPerfIncome = $incomeByType['Team Performance Bonus'] ?? 0.0;
$teamMatchThisMonth = 0.0;
if (tableExists($conn, 'team_business_contributions')) {
    $pk = date('Y-m');
    $tbS = $conn->prepare("SELECT COALESCE(SUM(CASE WHEN side='L' THEN amount ELSE 0 END),0) l,
                                  COALESCE(SUM(CASE WHEN side='R' THEN amount ELSE 0 END),0) r
                           FROM team_business_contributions WHERE leader_id = ? AND period_key = ?");
    if ($tbS) {
        $tbS->bind_param("ss", $customerId, $pk);
        $tbS->execute();
        $tbr = $tbS->get_result()->fetch_assoc();
        $teamMatchThisMonth = min((float)($tbr['l'] ?? 0), (float)($tbr['r'] ?? 0));
        $tbS->close();
    }
}
$withdrawableBalance = 0.0;
if (hasColumn($signupColumns, 'wallet_balance') && $customerCol) {
    $wbS = $conn->prepare("SELECT wallet_balance FROM signup_users WHERE `{$customerCol}` = ? LIMIT 1");
    if ($wbS) {
        $wbS->bind_param("s", $customerId);
        $wbS->execute();
        $withdrawableBalance = (float)($wbS->get_result()->fetch_assoc()['wallet_balance'] ?? 0);
        $wbS->close();
    }
}



$isHttps = (
    (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
    (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443)
);
$scheme      = $isHttps ? 'https' : 'http';
$host        = $_SERVER['HTTP_HOST'] ?? 'localhost';
$projectPath = str_replace('\\', '/', dirname($_SERVER['PHP_SELF'] ?? ''));
$projectPath = rtrim($projectPath, '/');
if ($projectPath === '/' || $projectPath === '\\') $projectPath = '';
$signupBaseUrl = $scheme . '://' . $host . $projectPath . '/signup.php';
$referralLink  = $signupBaseUrl . '?ref=' . urlencode($customerId);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>

    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Inter', 'sans-serif'] },
                    colors: {
                        brand: {
                            navy:     '#123B6D',
                            navyDark: '#0E2E56',
                            gold:     '#D7B25B',
                            goldDark: '#B99237',
                            border:   '#D9DEE7',
                            muted:    '#6B7280',
                            surface:  '#F8FAFC',
                            page:     '#F3F5F8'
                        }
                    },
                    boxShadow: { soft: '0 4px 14px rgba(18,59,109,0.06)' }
                }
            }
        }
    </script>

    <style>
        body { font-family: 'Inter', sans-serif; background: #F3F5F8; }
        .stat-card { min-height: 96px; }
        .metric-card { min-height: 126px; }
        .metric-card-lg { min-height: 138px; }
        @media (max-width: 1024px) { .content-wrap { margin-left: 0 !important; } }
    </style>
</head>
<body class="bg-brand-page font-sans text-slate-800">

    <?php include __DIR__ . '/includes/sidebar.php'; ?>

    <div class="content-wrap ml-0 lg:ml-64 min-h-screen">
        <?php include __DIR__ . '/includes/navbar.php'; ?>

        <main class="px-4 md:px-5 py-4">

            <div class="bg-brand-navy text-white rounded-md px-5 py-3 text-[15px] font-semibold shadow-soft">
                Welcome To, <?php echo htmlspecialchars($memberName); ?>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4 mt-5">

    <!-- Customer ID -->
    <div class="bg-white border-4 border-[#D4A64A] rounded-xl p-5 shadow-sm">
        <div class="flex items-start justify-between gap-4">

            <div class="min-w-0">
                <h3 class="text-[13px] font-semibold text-[#D4A64A] uppercase tracking-wide">
                    Customer ID
                </h3>

                <p class="mt-3 text-[15px] md:text-[16px] font-bold text-[#102B4E] break-words">
                    <?php echo htmlspecialchars($customerId); ?>
                </p>
            </div>

            <div class="h-10 w-10 rounded-lg bg-[#102B4E] flex items-center justify-center text-white shrink-0">
                <i data-lucide="user-round" class="w-5 h-5"></i>
            </div>

        </div>
    </div>

    <!-- Customer Name -->
    <div class="bg-white border-4 border-[#102B4E] rounded-xl p-5 shadow-sm">
        <div class="flex items-start justify-between gap-4">

            <div class="min-w-0">
                <h3 class="text-[13px] font-semibold text-[#102B4E] uppercase tracking-wide">
                    Customer Name
                </h3>

                <p class="mt-3 text-[15px] md:text-[16px] font-bold text-[#102B4E] break-words">
                    <?php echo htmlspecialchars($memberName); ?>
                </p>
            </div>

            <div class="h-10 w-10 rounded-lg bg-[#D4A64A] flex items-center justify-center text-white shrink-0">
                <i data-lucide="badge-check" class="w-5 h-5"></i>
            </div>

        </div>
    </div>

    <!-- Status -->
    <div class="bg-white border-4 border-[#D4A64A] rounded-xl p-5 shadow-sm">
        <div class="flex items-start justify-between gap-4">

            <div class="min-w-0">
                <h3 class="text-[13px] font-semibold text-[#D4A64A] uppercase tracking-wide">
                    Status
                </h3>

                <p class="mt-3 text-[15px] md:text-[16px] font-bold <?php echo in_array(strtolower((string)$registrationStatus), ['active','1']) ? 'text-[#102B4E]' : 'text-red-600'; ?>">
                    <?php echo in_array(strtolower((string)$registrationStatus), ['active','1']) ? 'Active' : 'Inactive'; ?>
                </p>
            </div>

            <div class="h-10 w-10 rounded-lg bg-[#102B4E] flex items-center justify-center text-white shrink-0">
                <i data-lucide="circle-check-big" class="w-5 h-5"></i>
            </div>

        </div>
    </div>

</div>

            <div class="mt-5">
                <div class="bg-white border border-brand-border rounded-xl p-5 shadow-soft">
                    <div class="flex items-center gap-3 mb-4">
                        <div class="h-10 w-10 rounded-lg bg-[#F8F2E5] flex items-center justify-center text-brand-goldDark shrink-0">
                            <i data-lucide="link" class="w-4 h-4"></i>
                        </div>
                        <div>
                            <h3 class="text-[16px] font-semibold text-brand-navy">Referral Link</h3>
                            <p class="text-[13px] text-brand-muted">Share with your network</p>
                        </div>
                    </div>

                    <input type="hidden" id="refLink" value="<?php echo htmlspecialchars($referralLink); ?>">

                    <div class="flex flex-col lg:flex-row lg:items-center gap-3">
                        <div class="flex-1 min-w-0">
                            <div class="w-full bg-brand-surface border border-brand-border rounded-lg px-4 h-[48px] flex items-center text-[13px] text-slate-700 overflow-hidden">
                                <span class="truncate block w-full"><?php echo htmlspecialchars($referralLink); ?></span>
                            </div>
                        </div>

                        <div class="flex items-center gap-3 lg:shrink-0">
                            <a href="<?php echo htmlspecialchars($referralLink); ?>" target="_blank" rel="noopener noreferrer"
                               class="inline-flex items-center justify-center gap-2 rounded-lg bg-brand-navy hover:bg-brand-navyDark text-white px-5 h-[48px] text-[14px] font-semibold transition whitespace-nowrap">
                                <i data-lucide="external-link" class="w-4 h-4"></i>
                                Open Link
                            </a>

                            <button type="button" onclick="copyReferral(this)"
                                class="inline-flex items-center justify-center gap-2 rounded-lg border border-brand-border bg-white hover:bg-slate-50 text-brand-navy px-5 h-[48px] text-[14px] font-semibold transition whitespace-nowrap">
                                <i data-lucide="copy" class="w-4 h-4"></i>
                                Copy Link
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Updated Metric Cards -->
           <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 sm:gap-4 mt-5">

    <div class="bg-white border-4 border-[#102B4E] rounded-xl p-4 sm:p-5 shadow-sm">
        <div class="h-10 w-10 rounded-lg bg-[#102B4E] flex items-center justify-center text-white">
            <i data-lucide="users" class="w-5 h-5"></i>
        </div>
        <p class="mt-4 text-[13px] sm:text-[14px] text-[#102B4E] font-semibold">Registered Members</p>
        <h3 class="mt-1 text-[22px] sm:text-[26px] font-bold text-[#102B4E]"><?php echo formatNumberValue($totalRegisteredMembers); ?></h3>
    </div>

    <div class="bg-white border-4 border-[#D4A64A] rounded-xl p-4 sm:p-5 shadow-sm">
        <div class="h-10 w-10 rounded-lg bg-[#D4A64A] flex items-center justify-center text-white">
            <i data-lucide="user-check" class="w-5 h-5"></i>
        </div>
        <p class="mt-4 text-[13px] sm:text-[14px] text-[#D4A64A] font-semibold">Active Members</p>
        <h3 class="mt-1 text-[22px] sm:text-[26px] font-bold text-[#102B4E]"><?php echo formatNumberValue($totalActiveMembers); ?></h3>
    </div>

    <div class="bg-white border-4 border-[#102B4E] rounded-xl p-4 sm:p-5 shadow-sm">
        <div class="h-10 w-10 rounded-lg bg-[#102B4E] flex items-center justify-center text-white">
            <i data-lucide="git-branch" class="w-5 h-5"></i>
        </div>
        <p class="mt-4 text-[13px] sm:text-[14px] text-[#102B4E] font-semibold">Left Team</p>
        <h3 class="mt-1 text-[22px] sm:text-[26px] font-bold text-[#102B4E]"><?php echo formatNumberValue($totalLeftMembers); ?></h3>
    </div>

    <div class="bg-white border-4 border-[#102B4E] rounded-xl p-4 sm:p-5 shadow-sm">
        <div class="h-10 w-10 rounded-lg bg-[#102B4E] flex items-center justify-center text-white">
            <i data-lucide="git-branch" class="w-5 h-5 rotate-180"></i>
        </div>
        <p class="mt-4 text-[13px] sm:text-[14px] text-[#102B4E] font-semibold">Right Team</p>
        <h3 class="mt-1 text-[22px] sm:text-[26px] font-bold text-[#102B4E]"><?php echo formatNumberValue($totalRightMembers); ?></h3>
    </div>

    <div class="bg-white border-4 border-[#D4A64A] rounded-xl p-4 sm:p-5 shadow-sm">
        <div class="h-10 w-10 rounded-lg bg-[#D4A64A] flex items-center justify-center text-white">
            <i data-lucide="share-2" class="w-5 h-5"></i>
        </div>
        <p class="mt-4 text-[13px] sm:text-[14px] text-[#D4A64A] font-semibold">Direct Referrals</p>
        <h3 class="mt-1 text-[22px] sm:text-[26px] font-bold text-[#102B4E]"><?php echo formatNumberValue($myDirectReferrals); ?></h3>
    </div>

    <div class="bg-gradient-to-r from-[#102B4E] to-[#163B68] border-4 border-[#D4A64A] rounded-xl p-4 sm:p-5 shadow-sm text-white">
        <div class="h-10 w-10 rounded-lg bg-[#D4A64A] flex items-center justify-center text-white">
            <i data-lucide="indian-rupee" class="w-5 h-5"></i>
        </div>
        <p class="mt-4 text-[13px] sm:text-[14px] text-white/80 font-semibold">Total Income</p>
        <h3 class="mt-1 text-[22px] sm:text-[26px] font-bold text-white"><?php echo formatIndianCurrency($totalIncome); ?></h3>
    </div>

<a href="direct-sponsor-income.php" class="block bg-white border-4 border-[#D4A64A] rounded-xl p-4 sm:p-5 shadow-sm hover:shadow-md transition">
    <div class="h-10 w-10 rounded-lg bg-[#102B4E] flex items-center justify-center text-white">
        <i data-lucide="hand-coins" class="w-5 h-5"></i>
    </div>
    <p class="mt-4 text-[13px] sm:text-[14px] font-semibold text-[#D4A64A]">Direct Sponsor Income</p>
    <h3 class="mt-1 text-[22px] sm:text-[26px] font-bold text-[#102B4E]">
        <?php echo formatIndianCurrency($directSponsorIncome); ?>
    </h3>
    <p class="mt-2 text-[11px] sm:text-[12px] text-[#6b7a8d]">
        Withdrawable <?php echo formatIndianCurrency($directSponsorWithdraw); ?>
    </p>
</a>

<a href="binary-income.php" class="block bg-white border-4 border-[#102B4E] rounded-xl p-4 sm:p-5 shadow-sm hover:shadow-md transition">
    <div class="h-10 w-10 rounded-lg bg-[#D4A64A] flex items-center justify-center text-white">
        <i data-lucide="git-fork" class="w-5 h-5"></i>
    </div>
    <p class="mt-4 text-[13px] sm:text-[14px] font-semibold text-[#102B4E]">Binary Income</p>
    <h3 class="mt-1 text-[22px] sm:text-[26px] font-bold text-[#102B4E]">
        <?php echo formatIndianCurrency($binaryIncomePaid); ?>
    </h3>
    <p class="mt-2 text-[11px] sm:text-[12px] text-[#6b7a8d]">
        <?php echo (int)$binaryPairs; ?> Jodi<?php echo $binaryPairs === 1 ? '' : 's'; ?>
        &nbsp;·&nbsp; Withdrawable <?php echo formatIndianCurrency($binaryIncomeWithdraw); ?>
        <?php if ($binaryIncomeFlushed > 0): ?>
            &nbsp;·&nbsp; <span class="text-[#c0392b]">Flushed <?php echo formatIndianCurrency($binaryIncomeFlushed); ?></span>
        <?php endif; ?>
    </p>
</a>

<a href="level-income.php" class="block bg-white border-4 border-[#D4A64A] rounded-xl p-4 sm:p-5 shadow-sm hover:shadow-md transition">
    <div class="h-10 w-10 rounded-lg bg-[#102B4E] flex items-center justify-center text-white">
        <i data-lucide="list-tree" class="w-5 h-5"></i>
    </div>
    <p class="mt-4 text-[13px] sm:text-[14px] font-semibold text-[#D4A64A]">Level Income</p>
    <h3 class="mt-1 text-[22px] sm:text-[26px] font-bold text-[#102B4E]">
        <?php echo formatIndianCurrency($levelIncomeTotal); ?>
    </h3>
    <p class="mt-2 text-[11px] sm:text-[12px] text-[#6b7a8d]">Across 5 levels of your downline's Binary Income</p>
</a>

<a href="auto-pool-income.php" class="block bg-white border-4 border-[#102B4E] rounded-xl p-4 sm:p-5 shadow-sm hover:shadow-md transition">
    <div class="h-10 w-10 rounded-lg bg-[#D4A64A] flex items-center justify-center text-white">
        <i data-lucide="layers" class="w-5 h-5"></i>
    </div>
    <p class="mt-4 text-[13px] sm:text-[14px] font-semibold text-[#102B4E]">Auto Pool Income</p>
    <h3 class="mt-1 text-[22px] sm:text-[26px] font-bold text-[#102B4E]">
        <?php echo formatIndianCurrency($autoPoolIncome); ?>
    </h3>
    <p class="mt-2 text-[11px] sm:text-[12px] text-[#6b7a8d]">
        In <?php echo (int)$autoPoolPools; ?> pool<?php echo $autoPoolPools === 1 ? '' : 's'; ?>
        &nbsp;·&nbsp; company-funded entry
    </p>
</a>

<a href="performance-bonus.php" class="block bg-white border-4 border-[#D4A64A] rounded-xl p-4 sm:p-5 shadow-sm hover:shadow-md transition">
    <div class="h-10 w-10 rounded-lg bg-[#102B4E] flex items-center justify-center text-white">
        <i data-lucide="trophy" class="w-5 h-5"></i>
    </div>
    <p class="mt-4 text-[13px] sm:text-[14px] font-semibold text-[#D4A64A]">Performance Bonus</p>
    <h3 class="mt-1 text-[22px] sm:text-[26px] font-bold text-[#102B4E]">
        <?php echo formatIndianCurrency($perfBonusIncome); ?>
    </h3>
    <p class="mt-2 text-[11px] sm:text-[12px] text-[#6b7a8d]">
        <?php echo (int)$perfDirectsThisMonth; ?> direct<?php echo $perfDirectsThisMonth === 1 ? '' : 's'; ?> this month
        &nbsp;·&nbsp; monthly top-3 pool
    </p>
</a>

<a href="team-performance-bonus.php" class="block bg-white border-4 border-[#102B4E] rounded-xl p-4 sm:p-5 shadow-sm hover:shadow-md transition">
    <div class="h-10 w-10 rounded-lg bg-[#D4A64A] flex items-center justify-center text-white">
        <i data-lucide="users-round" class="w-5 h-5"></i>
    </div>
    <p class="mt-4 text-[13px] sm:text-[14px] font-semibold text-[#102B4E]">Team Performance Bonus</p>
    <h3 class="mt-1 text-[22px] sm:text-[26px] font-bold text-[#102B4E]">
        <?php echo formatIndianCurrency($teamPerfIncome); ?>
    </h3>
    <p class="mt-2 text-[11px] sm:text-[12px] text-[#6b7a8d]">
        Matching business this month <?php echo formatIndianCurrency($teamMatchThisMonth); ?>
        &nbsp;·&nbsp; monthly leader top-3
    </p>
</a>


</div>

        </main>
    </div>

    <script>
        function copyReferral(btn) {
            const input = document.getElementById('refLink');
            if (!input) return;
            navigator.clipboard.writeText(input.value).then(() => {
                const original = btn.innerHTML;
                btn.innerHTML = '<i data-lucide="check" class="w-4 h-4"></i> Copied';
                btn.classList.add('bg-green-50', 'text-green-700', 'border-green-200');
                lucide.createIcons();
                setTimeout(() => {
                    btn.innerHTML = original;
                    btn.classList.remove('bg-green-50', 'text-green-700', 'border-green-200');
                    lucide.createIcons();
                }, 1400);
            }).catch(() => alert('Copy failed'));
        }

        document.addEventListener('DOMContentLoaded', () => lucide.createIcons());
    </script>

</body>
</html>