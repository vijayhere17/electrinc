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
    $selectParts[] = hasColumn($signupColumns,'repurchase_left_team_bv')
    ? "repurchase_left_team_bv"
    : "0 AS repurchase_left_team_bv";

$selectParts[] = hasColumn($signupColumns,'repurchase_right_team_bv')
    ? "repurchase_right_team_bv"
    : "0 AS repurchase_right_team_bv";

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
        $balanceLeftBV  = (float)($memberRow['repurchase_left_team_bv'] ?? 0);
$balanceRightBV = (float)($memberRow['repurchase_right_team_bv'] ?? 0);

$todayLeftBV = 0;
$todayRightBV = 0;
$finalMatchingBV = 0;

if (tableExists($conn, 'repurchase_matching_income_log')) {

    $stmtToday = $conn->prepare("
        SELECT
            COALESCE(SUM(left_bv),0) AS total_left,
            COALESCE(SUM(right_bv),0) AS total_right,
            COALESCE(SUM(matching_bv),0) AS total_matching
        FROM repurchase_matching_income_log
        WHERE customer_id = ?
        AND DATE(created_at) = CURDATE()
    ");

    if ($stmtToday) {
       $stmtToday->bind_param("s", $sessionCustomerId);
        $stmtToday->execute();

        $todayData = $stmtToday->get_result()->fetch_assoc();

        $todayLeftBV = (float)($todayData['total_left'] ?? 0);
        $todayRightBV = (float)($todayData['total_right'] ?? 0);
        $finalMatchingBV = (float)($todayData['total_matching'] ?? 0);

        $stmtToday->close();
    }
}
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


if (!isset($balanceLeftBV)) {
    $balanceLeftBV = 0;
}

if (!isset($balanceRightBV)) {
    $balanceRightBV = 0;
}

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

$payoutSummary = [
    'gross' => 0,
    'tds' => 0,
    'admin' => 0,
    'net' => 0
];

$payoutRows = [];

$stmt = $conn->prepare("
    SELECT
        COALESCE(SUM(gross_income),0) gross,
        COALESCE(SUM(tds_amount),0) tds,
        COALESCE(SUM(admin_charge),0) admin,
        COALESCE(SUM(net_payable),0) net
    FROM member_payout_closing
    WHERE customer_id = ?
");

$stmt->bind_param("s", $sessionCustomerId);
$stmt->execute();
$payoutSummary = $stmt->get_result()->fetch_assoc();
$stmt->close();

$stmt = $conn->prepare("
    SELECT *
    FROM member_payout_closing
    WHERE customer_id = ?
    ORDER BY id DESC
");

$stmt->bind_param("s", $sessionCustomerId);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $payoutRows[] = $row;
}

$stmt->close();
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payout History</title>

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
    Payout History
</div>

<div class="grid grid-cols-2 xl:grid-cols-4 gap-4 mt-5">

    <div class="bg-white border-4 border-[#102B4E] rounded-xl p-5">
        <p class="text-sm text-[#102B4E] font-semibold">Gross Income</p>
        <h3 class="text-2xl font-bold mt-2">
            ₹<?php echo number_format($payoutSummary['gross'],2); ?>
        </h3>
    </div>

    <div class="bg-white border-4 border-[#D4A64A] rounded-xl p-5">
        <p class="text-sm text-[#D4A64A] font-semibold">TDS</p>
        <h3 class="text-2xl font-bold mt-2">
            ₹<?php echo number_format($payoutSummary['tds'],2); ?>
        </h3>
    </div>

    <div class="bg-white border-4 border-[#102B4E] rounded-xl p-5">
        <p class="text-sm text-[#102B4E] font-semibold">Admin Charge</p>
        <h3 class="text-2xl font-bold mt-2">
            ₹<?php echo number_format($payoutSummary['admin'],2); ?>
        </h3>
    </div>

    <div class="bg-white border-4 border-[#D4A64A] rounded-xl p-5">
        <p class="text-sm text-[#D4A64A] font-semibold">Net Payable</p>
        <h3 class="text-2xl font-bold mt-2">
            ₹<?php echo number_format($payoutSummary['net'],2); ?>
        </h3>
    </div>

</div>

<div class="bg-white rounded-xl shadow-sm mt-6 overflow-hidden">

    <div class="px-5 py-4 border-b font-semibold text-[#102B4E]">
        Payout History
    </div>

    <div class="overflow-x-auto">

        <table class="w-full">

            <thead class="bg-slate-100">

                <tr>
                    <th class="px-4 py-3 text-left">Period</th>
                    <th class="px-4 py-3 text-left">Gross</th>
                    <th class="px-4 py-3 text-left">TDS</th>
                    <th class="px-4 py-3 text-left">Admin</th>
                    <th class="px-4 py-3 text-left">Net Payable</th>
                    <th class="px-4 py-3 text-left">Status</th>
                    <th class="px-4 py-3 text-left">Date</th>
                </tr>

            </thead>

            <tbody>

            <?php if(empty($payoutRows)): ?>

                <tr>
                    <td colspan="7" class="px-4 py-6 text-center">
                        No payout history found.
                    </td>
                </tr>

            <?php else: ?>

                <?php foreach($payoutRows as $row): ?>

                    <tr class="border-b">

                        <td class="px-4 py-3">
                            <?php echo date('d M Y', strtotime($row['closing_from'])); ?>
                            -
                            <?php echo date('d M Y', strtotime($row['closing_to'])); ?>
                        </td>

                        <td class="px-4 py-3">
                            ₹<?php echo number_format($row['gross_income'],2); ?>
                        </td>

                        <td class="px-4 py-3">
                            ₹<?php echo number_format($row['tds_amount'],2); ?>
                        </td>

                        <td class="px-4 py-3">
                            ₹<?php echo number_format($row['admin_charge'],2); ?>
                        </td>

                        <td class="px-4 py-3 font-semibold text-green-700">
                            ₹<?php echo number_format($row['net_payable'],2); ?>
                        </td>

                        <td class="px-4 py-3">

                            <?php
                            $statusClass = 'bg-yellow-100 text-yellow-700';

                            if ($row['status'] === 'Paid') {
                                $statusClass = 'bg-green-100 text-green-700';
                            }

                            if ($row['status'] === 'Hold') {
                                $statusClass = 'bg-red-100 text-red-700';
                            }
                            ?>

                            <span class="px-3 py-1 rounded-full text-xs font-semibold <?php echo $statusClass; ?>">
                                <?php echo htmlspecialchars($row['status']); ?>
                            </span>

                        </td>

                        <td class="px-4 py-3">
                            <?php echo date('d M Y', strtotime($row['created_at'])); ?>
                        </td>

                    </tr>

                <?php endforeach; ?>

            <?php endif; ?>

            </tbody>

        </table>

    </div>

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