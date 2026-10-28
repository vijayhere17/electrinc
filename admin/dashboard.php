<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* CSP FIX FOR TAILWIND CDN */
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.tailwindcss.com https://unpkg.com; style-src 'self' 'unsafe-inline' https://cdn.tailwindcss.com; img-src 'self' data: https://i.pravatar.cc; font-src 'self' data:; object-src 'none';");

include __DIR__ . '/config/db.php';
include_once __DIR__ . '/includes/admin_access_control.php';

if (!isset($conn) || !($conn instanceof mysqli)) {
    die("Database connection failed.");
}

if (function_exists('adminRequireAccessOrRedirect')) {
    adminRequireAccessOrRedirect();
}

$conn->set_charset("utf8mb4");

// monthly bonus auto-distribution — runs once on/after the configured day
if (is_file(__DIR__ . '/../includes/income_common.php')) {
    require_once __DIR__ . '/../includes/income_common.php';
    if (function_exists('income_maybe_run_monthly')) {
        income_maybe_run_monthly($conn);
    }
}

function tableExists(mysqli $conn, string $tableName): bool {
    $safeName = $conn->real_escape_string($tableName);
    $result = $conn->query("SHOW TABLES LIKE '{$safeName}'");
    return $result && $result->num_rows > 0;
}

function getTableColumns(mysqli $conn, string $tableName): array {
    $columns = [];
    $result = $conn->query("SHOW COLUMNS FROM `$tableName`");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $columns[] = strtolower($row['Field']);
        }
    }
    return $columns;
}

function firstExistingColumn(array $columns, array $names): ?string {
    foreach ($names as $name) {
        if (in_array(strtolower($name), $columns, true)) {
            return strtolower($name);
        }
    }
    return null;
}

function findAdminIdentity(mysqli $conn, string $identifier): ?array {
    if (!tableExists($conn, 'signup_admin')) {
        return null;
    }

    $adminColumns = getTableColumns($conn, 'signup_admin');
    $idCol = firstExistingColumn($adminColumns, ['customer_id', 'member_id', 'admin_id', 'user_id']);
    $nameCol = firstExistingColumn($adminColumns, ['full_name', 'name', 'username']);
    $statusCol = firstExistingColumn($adminColumns, ['registration_status', 'active_status', 'status']);

    if (!$idCol) {
        return null;
    }

    $selectParts = [];
    $selectParts[] = "`$idCol` AS customer_id";
    $selectParts[] = $nameCol ? "`$nameCol` AS full_name" : "'Admin' AS full_name";
    $selectParts[] = $statusCol ? "`$statusCol` AS registration_status" : "'Active' AS registration_status";

    $sql = "SELECT " . implode(', ', $selectParts) . " FROM signup_admin WHERE `$idCol` = ? LIMIT 1";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('s', $identifier);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    return $row ?: null;
}

function formatIndianCurrency($amount) {
    return '₹' . number_format((float)$amount, 2);
}

function formatNumberValue($number) {
    return number_format((int)$number);
}

function getFirstExistingTable(mysqli $conn, array $candidates): ?string {
    foreach ($candidates as $tableName) {
        if (tableExists($conn, $tableName)) {
            return $tableName;
        }
    }
    return null;
}

function getTableCount(mysqli $conn, string $tableName): int {
    if (!tableExists($conn, $tableName)) {
        return 0;
    }

    $result = $conn->query("SELECT COUNT(*) AS total_count FROM `$tableName`");
    if (!($result instanceof mysqli_result)) {
        return 0;
    }

    $row = $result->fetch_assoc();
    return (int)($row['total_count'] ?? 0);
}

function getCountByToday(mysqli $conn, string $tableName, ?string $dateColumn, string $extraWhere = '1=1'): int {
    if (!$dateColumn || !tableExists($conn, $tableName)) {
        return 0;
    }

    $sql = "SELECT COUNT(*) AS total_count
            FROM `$tableName`
            WHERE $extraWhere
              AND `$dateColumn` IS NOT NULL
              AND `$dateColumn` <> ''
              AND DATE(`$dateColumn`) = CURDATE()";

    $result = $conn->query($sql);
    if (!($result instanceof mysqli_result)) {
        return 0;
    }

    $row = $result->fetch_assoc();
    return (int)($row['total_count'] ?? 0);
}

function getSumAmount(mysqli $conn, string $tableName, ?string $sumColumn, ?string $dateColumn = null, bool $thisMonthOnly = false): float {
    if (!$sumColumn || !tableExists($conn, $tableName)) {
        return 0;
    }

    $where = '1=1';
    if ($thisMonthOnly && $dateColumn) {
        $where = "`$dateColumn` IS NOT NULL
                  AND `$dateColumn` <> ''
                  AND YEAR(`$dateColumn`) = YEAR(CURDATE())
                  AND MONTH(`$dateColumn`) = MONTH(CURDATE())";
    }

    $sql = "SELECT COALESCE(SUM(`$sumColumn`), 0) AS total_amount
            FROM `$tableName`
            WHERE $where";

    $result = $conn->query($sql);
    if (!($result instanceof mysqli_result)) {
        return 0;
    }

    $row = $result->fetch_assoc();
    return (float)($row['total_amount'] ?? 0);
}

if (!tableExists($conn, 'signup_users')) {
    die("signup_users table not found.");
}

$signupColumns = getTableColumns($conn, 'signup_users');

$customerCol = firstExistingColumn($signupColumns, [
    'customer_id',
    'member_id',
    'associate_id',
    'user_id'
]);

$nameCol = firstExistingColumn($signupColumns, [
    'full_name',
    'name',
    'member_name',
    'username'
]);

$statusCol = firstExistingColumn($signupColumns, [
    'registration_status',
    'active_status',
    'status'
]);

$leftRefCol       = firstExistingColumn($signupColumns, ['left_referral_code', 'left_referral']);
$rightRefCol      = firstExistingColumn($signupColumns, ['right_referral_code', 'right_referral']);
$purchaseBalCol   = firstExistingColumn($signupColumns, ['purchase_balance']);
$repurchaseBalCol = firstExistingColumn($signupColumns, ['repurchase_balance']);
$turnoverCol      = firstExistingColumn($signupColumns, ['total_turnover']);

if (!$customerCol) {
    die("customer_id/member_id column not found in signup_users.");
}

$viewCustomerId = trim((string)(
    $_GET['view_customer_id']
    ?? $_GET['customer_id']
    ?? $_GET['member_id']
    ?? $_GET['associate_id']
    ?? ''
));

$sessionCustomerId = trim((string)(
    $_SESSION['view_customer_id']
    ?? $_SESSION['customer_id']
    ?? $_SESSION['member_id']
    ?? $_SESSION['associate_id']
    ?? ''
));

$customerIdToOpen = $viewCustomerId !== '' ? $viewCustomerId : $sessionCustomerId;

if ($customerIdToOpen === '') {
    header("Location: login.php");
    exit();
}

$selectParts = [];
$selectParts[] = "`$customerCol` AS customer_id";
$selectParts[] = $nameCol ? "`$nameCol` AS full_name" : "'' AS full_name";

if ($statusCol) {
    if ($statusCol === 'status') {
        $selectParts[] = "CASE
            WHEN `status` IN ('1', 1, 'Active', 'active', 'ACTIVE') THEN 'Active'
            WHEN `status` IN ('0', 0, 'Inactive', 'inactive', 'INACTIVE') THEN 'Inactive'
            ELSE `status`
        END AS registration_status";
    } else {
        $selectParts[] = "`$statusCol` AS registration_status";
    }
} else {
    $selectParts[] = "'Active' AS registration_status";
}

$selectParts[] = $leftRefCol ? "`$leftRefCol` AS left_referral_code" : "'' AS left_referral_code";
$selectParts[] = $rightRefCol ? "`$rightRefCol` AS right_referral_code" : "'' AS right_referral_code";
$selectParts[] = $purchaseBalCol ? "`$purchaseBalCol` AS purchase_balance" : "0 AS purchase_balance";
$selectParts[] = $repurchaseBalCol ? "`$repurchaseBalCol` AS repurchase_balance" : "0 AS repurchase_balance";
$selectParts[] = $turnoverCol ? "`$turnoverCol` AS total_turnover" : "0 AS total_turnover";

$sql = "SELECT " . implode(", ", $selectParts) . "
        FROM signup_users
        WHERE `$customerCol` = ?
        LIMIT 1";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    die("Query error: " . $conn->error);
}

$stmt->bind_param("s", $customerIdToOpen);
$stmt->execute();
$result = $stmt->get_result();
$memberRow = $result ? $result->fetch_assoc() : null;
$stmt->close();

if (!$memberRow) {
    $adminRow = findAdminIdentity($conn, $customerIdToOpen);

    if ($adminRow) {
        $memberRow = [
            'customer_id' => $adminRow['customer_id'] ?? $customerIdToOpen,
            'full_name' => $adminRow['full_name'] ?? ($_SESSION['full_name'] ?? 'Admin'),
            'registration_status' => $adminRow['registration_status'] ?? 'Active',
            'left_referral_code' => '',
            'right_referral_code' => '',
            'purchase_balance' => 0,
            'repurchase_balance' => 0,
            'total_turnover' => 0,
        ];
    } else {
        die("Member not found: " . htmlspecialchars($customerIdToOpen));
    }
}

$customerId = $memberRow['customer_id'];
$memberName = $memberRow['full_name'] ?: 'Member';
$registrationStatus = $memberRow['registration_status'] ?: 'Active';

$_SESSION['view_customer_id'] = $customerId;
$_SESSION['customer_id'] = $customerId;
$_SESSION['member_id'] = $customerId;
$_SESSION['associate_id'] = $customerId;
$_SESSION['full_name'] = $memberName;
$_SESSION['member_name'] = $memberName;

$purchaseBalance = (float)($memberRow['purchase_balance'] ?? 0);
$repurchaseBalance = (float)($memberRow['repurchase_balance'] ?? 0);
$totalTurnover = (float)($memberRow['total_turnover'] ?? 0);

$totalLeftMembers = 0;
$totalRightMembers = 0;

$placementCol = firstExistingColumn($signupColumns, [
    'placement_id',
    'parent_id',
    'sponsor_id'
]);

$positionCol = firstExistingColumn($signupColumns, [
    'position',
    'placement_position'
]);

if ($placementCol && $positionCol) {
    $countStmt = $conn->prepare("
        SELECT
            SUM(CASE WHEN UPPER(`$positionCol`) = 'L' THEN 1 ELSE 0 END) AS total_left,
            SUM(CASE WHEN UPPER(`$positionCol`) = 'R' THEN 1 ELSE 0 END) AS total_right
        FROM signup_users
        WHERE `$placementCol` = ?
    ");

    if ($countStmt) {
        $countStmt->bind_param("s", $customerId);
        $countStmt->execute();
        $countRow = $countStmt->get_result()->fetch_assoc();
        $totalLeftMembers = (int)($countRow['total_left'] ?? 0);
        $totalRightMembers = (int)($countRow['total_right'] ?? 0);
        $countStmt->close();
    }
}

$totalOrders = 0;

if (tableExists($conn, 'repurchase_orders')) {
    $orderColumns = getTableColumns($conn, 'repurchase_orders');
    $orderCustomerCol = firstExistingColumn($orderColumns, [
        'customer_id',
        'member_id',
        'associate_id',
        'user_id'
    ]);

    if ($orderCustomerCol) {
        $orderStmt = $conn->prepare("
            SELECT COUNT(*) AS total_orders
            FROM repurchase_orders
            WHERE `$orderCustomerCol` = ?
        ");

        if ($orderStmt) {
            $orderStmt->bind_param("s", $customerId);
            $orderStmt->execute();
            $orderRow = $orderStmt->get_result()->fetch_assoc();
            $totalOrders = (int)($orderRow['total_orders'] ?? 0);
            $orderStmt->close();
        }
    }
}

$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
$scheme = $isHttps ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';

$projectPath = str_replace('\\', '/', dirname($_SERVER['PHP_SELF'] ?? ''));
$projectPath = rtrim($projectPath, '/');

if ($projectPath === '/' || $projectPath === '.') {
    $projectPath = '';
}

$signupBaseUrl = $scheme . '://' . $host . $projectPath . '/signup.php';

$leftReferralLink = $signupBaseUrl . '?placement_id=' . urlencode($customerId) . '&position=L';
$rightReferralLink = $signupBaseUrl . '?placement_id=' . urlencode($customerId) . '&position=R';

if ($leftRefCol && $rightRefCol) {
    $saveRefStmt = $conn->prepare("
        UPDATE signup_users
        SET `$leftRefCol` = ?, `$rightRefCol` = ?
        WHERE `$customerCol` = ?
        LIMIT 1
    ");

    if ($saveRefStmt) {
        $saveRefStmt->bind_param("sss", $leftReferralLink, $rightReferralLink, $customerId);
        $saveRefStmt->execute();
        $saveRefStmt->close();
    }
}

$orderTable = getFirstExistingTable($conn, ['repurchase_orders', 'orders', 'order_master']);

$ecomTotalUsers = getTableCount($conn, 'signup_users');

/* franchise-sourced numbers — orders/sales come from the franchise showroom (fr_customer_purchases),
   never from the site directly, so the admin overview reflects that instead of ecommerce tables */
$franchiseTotalCount = getTableCount($conn, 'franchies_holder')
    + getTableCount($conn, 'city_franchies_holder')
    + getTableCount($conn, 'mobile_franchies_holder');

$franchiseTotalSales   = 0;
$franchiseTodaySales   = 0;
$franchiseTotalProducts = 0;
$franchiseTotalTurnover = 0.0;
$franchiseTotalCommission = 0.0;

if (tableExists($conn, 'fr_customer_purchases')) {
    $franchiseTotalSales = getTableCount($conn, 'fr_customer_purchases');
    $franchiseTodaySales = getCountByToday($conn, 'fr_customer_purchases', 'created_at');
    $r = $conn->query("SELECT COALESCE(SUM(sale_amount),0) t FROM fr_customer_purchases");
    if ($r instanceof mysqli_result) $franchiseTotalTurnover = (float)($r->fetch_assoc()['t'] ?? 0);
}
if (tableExists($conn, 'fr_products')) {
    $franchiseTotalProducts = getTableCount($conn, 'fr_products');
}
if (tableExists($conn, 'commission_ledger')) {
    $r = $conn->query("SELECT COALESCE(SUM(commission_amount),0) t FROM commission_ledger");
    if ($r instanceof mysqli_result) $franchiseTotalCommission = (float)($r->fetch_assoc()['t'] ?? 0);
}

$thisMonthRevenue = 0;
if ($orderTable) {
    $orderColumns = getTableColumns($conn, $orderTable);
    $orderDateCol = firstExistingColumn($orderColumns, ['created_at', 'order_date', 'created_on', 'added_on', 'date']);
    $orderAmountCol = firstExistingColumn($orderColumns, ['grand_total', 'total_amount', 'amount', 'net_amount', 'final_amount']);
    $thisMonthRevenue = getSumAmount($conn, $orderTable, $orderAmountCol, $orderDateCol, true);
}
$ecomTotalOrders = $orderTable ? getTableCount($conn, $orderTable) : 0;
$ecomTodayOrders = $orderTable ? getCountByToday($conn, $orderTable, $orderDateCol ?? null) : 0;

$memberTotalMembers = $ecomTotalUsers;
$memberTodayMembers = getCountByToday(
    $conn,
    'signup_users',
    firstExistingColumn($signupColumns, ['created_at', 'registration_date', 'join_date', 'created_on', 'added_on'])
);

$memberActiveMembers = 0;
$memberInactiveMembers = 0;
if ($statusCol) {
    $statusSummarySql = "SELECT
        SUM(CASE WHEN LOWER(TRIM(CAST(`$statusCol` AS CHAR))) IN ('1', 'active') THEN 1 ELSE 0 END) AS active_members,
        SUM(CASE WHEN LOWER(TRIM(CAST(`$statusCol` AS CHAR))) IN ('0', 'inactive') THEN 1 ELSE 0 END) AS inactive_members
    FROM signup_users";
    $statusSummaryResult = $conn->query($statusSummarySql);
    if ($statusSummaryResult instanceof mysqli_result) {
        $statusSummary = $statusSummaryResult->fetch_assoc();
        $memberActiveMembers = (int)($statusSummary['active_members'] ?? 0);
        $memberInactiveMembers = (int)($statusSummary['inactive_members'] ?? 0);
    }
}

if ($memberInactiveMembers === 0 && $memberTotalMembers >= $memberActiveMembers) {
    $memberInactiveMembers = $memberTotalMembers - $memberActiveMembers;
}

$memberTodayActivation = getCountByToday(
    $conn,
    'signup_users',
    firstExistingColumn($signupColumns, ['activation_date', 'active_date', 'activated_at'])
);

$memberRepurchaseOrders = $ecomTotalOrders;
$memberTodayTotalOrders = $ecomTodayOrders;

$birthdays = [];
$anniversaries = [];

$mobileCol = firstExistingColumn($signupColumns, ['mobile', 'mobile_no', 'mobile_number', 'phone']);
$cityCol = firstExistingColumn($signupColumns, ['city']);
$stateCol = firstExistingColumn($signupColumns, ['state']);
$dobCol = firstExistingColumn($signupColumns, ['dob', 'date_of_birth', 'birth_date']);
$anniversaryCol = firstExistingColumn($signupColumns, ['anniversary_date', 'marriage_anniversary', 'wedding_anniversary', 'dom', 'date_of_marriage', 'marriage_date']);

if ($customerCol && $nameCol && $dobCol) {
    $birthdaySql = "SELECT
            `$customerCol` AS member_id,
            `$nameCol` AS full_name,
            " . ($mobileCol ? "`$mobileCol`" : "''") . " AS mobile,
            `$dobCol` AS dob,
            " . ($cityCol ? "`$cityCol`" : "''") . " AS city,
            " . ($stateCol ? "`$stateCol`" : "''") . " AS state
        FROM signup_users
        WHERE `$dobCol` IS NOT NULL
          AND `$dobCol` <> ''
          AND DAY(`$dobCol`) = DAY(CURDATE())
          AND MONTH(`$dobCol`) = MONTH(CURDATE())
        ORDER BY `$nameCol` ASC
        LIMIT 100";

    $birthdayResult = $conn->query($birthdaySql);
    if ($birthdayResult instanceof mysqli_result) {
        while ($birthdayRow = $birthdayResult->fetch_assoc()) {
            $birthdays[] = $birthdayRow;
        }
    }
}

if ($customerCol && $nameCol && $anniversaryCol) {
    $anniversarySql = "SELECT
            `$customerCol` AS member_id,
            `$nameCol` AS full_name,
            " . ($mobileCol ? "`$mobileCol`" : "''") . " AS mobile,
            `$anniversaryCol` AS dob,
            " . ($cityCol ? "`$cityCol`" : "''") . " AS city,
            " . ($stateCol ? "`$stateCol`" : "''") . " AS state
        FROM signup_users
        WHERE `$anniversaryCol` IS NOT NULL
          AND `$anniversaryCol` <> ''
          AND DAY(`$anniversaryCol`) = DAY(CURDATE())
          AND MONTH(`$anniversaryCol`) = MONTH(CURDATE())
        ORDER BY `$nameCol` ASC
        LIMIT 100";

    $anniversaryResult = $conn->query($anniversarySql);
    if ($anniversaryResult instanceof mysqli_result) {
        while ($anniversaryRow = $anniversaryResult->fetch_assoc()) {
            $anniversaries[] = $anniversaryRow;
        }
    }
}

$managerName = $memberName;
$managerRole = 'Distributor';
$profileImage = "https://i.pravatar.cc/80?img=12";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>

    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        /* stat cards: tricolour gradient wash + premium silver-shine border */
        .bg-white.border.border-gray-300.rounded-2xl{
            background:linear-gradient(135deg, rgba(224,112,12,.10) 0%, #ffffff 48%, rgba(14,122,59,.10) 100%);
            border-color:#D9DEE6;
            box-shadow:0 1px 3px rgba(20,16,10,.06), inset 0 1px 0 rgba(255,255,255,.95);
        }
        .bg-white.border.border-gray-300.rounded-2xl:hover{
            box-shadow:0 16px 34px -18px rgba(160,90,20,.28), inset 0 1px 0 rgba(255,255,255,.95);
        }
    </style>
</head>

<body class="bg-[#f3f4f6] font-sans">

    <?php include __DIR__ . '/includes/sidebar.php'; ?>

    <div class="lg:ml-64 min-h-screen">
        <?php include __DIR__ . '/includes/navbar.php'; ?>

        <div class="p-3 sm:p-4 md:p-6">

            <div id="adminWelcome" class="mb-5 sm:mb-7 rounded-2xl p-3.5 sm:p-4 text-white shadow-sm" style="background:linear-gradient(100deg,#E0700C 0%, #EAA63F 44%, #77BF87 60%, #0E7A3B 100%); transition:opacity .45s ease, transform .45s ease, margin .45s ease, max-height .45s ease, padding .45s ease; max-height:120px; overflow:hidden;">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div class="flex items-baseline gap-2">
                        <span class="text-[13px] sm:text-[14px] text-white/80">Welcome back,</span>
                        <h1 class="text-[16px] sm:text-[19px] font-bold leading-none"><?php echo htmlspecialchars($memberName); ?></h1>
                    </div>
                    <div class="px-3 py-1.5 rounded-full text-[12px] sm:text-[13px] font-semibold bg-white/15 border border-white/20 whitespace-nowrap">
                        <?php echo htmlspecialchars($registrationStatus); ?> Account
                    </div>
                </div>
            </div>

            <section>
                <div class="flex flex-wrap items-center gap-3 mb-3 sm:mb-4">
                    <div class="w-7 h-7 rounded-[4px] border border-[#ead9b5] bg-[#faf5ea] flex items-center justify-center shrink-0">
                        <i data-lucide="gift" class="w-[14px] h-[14px] text-[#c79435]"></i>
                    </div>
                    <h2 class="whitespace-nowrap text-[18px] sm:text-[24px] leading-none font-normal text-[#000000CC]" style="font-family: Georgia, 'Times New Roman', serif;">FRANCHISE DASHBOARD</h2>
                    <div class="hidden sm:block flex-1 h-[1px] bg-[#d2a041]"></div>
                </div>

                <div class="grid grid-cols-2 md:grid-cols-3 gap-3 sm:gap-4 md:gap-6">
                    <div class="bg-white border border-gray-300 rounded-2xl min-h-[110px] sm:min-h-[130px] p-3 sm:p-5 shadow-sm hover:shadow-md hover:-translate-y-0.5 transition-all">
                        <div class="flex items-center gap-2 sm:gap-3">
                            <div class="w-8 h-8 sm:w-11 sm:h-11 rounded-xl bg-[#4d7ed0]/10 flex items-center justify-center shrink-0">
                                <i data-lucide="building-2" class="w-[16px] h-[16px] sm:w-5 sm:h-5 text-[#4d7ed0]"></i>
                            </div>
                            <h3 class="text-[12px] sm:text-[15px] md:text-[17px] font-semibold text-gray-500 leading-tight">Total Franchises</h3>
                        </div>
                        <p class="mt-2 sm:mt-3 text-[20px] sm:text-[26px] md:text-[30px] font-bold text-[#1e293b] leading-none"><?php echo formatNumberValue($franchiseTotalCount); ?></p>
                        <p class="mt-1 sm:mt-2 text-[11px] sm:text-[14px] font-semibold text-[#4d7ed0]">State + City + Mobile</p>
                    </div>

                    <div class="bg-white border border-gray-300 rounded-2xl min-h-[110px] sm:min-h-[130px] p-3 sm:p-5 shadow-sm hover:shadow-md hover:-translate-y-0.5 transition-all">
                        <div class="flex items-center gap-2 sm:gap-3">
                            <div class="w-8 h-8 sm:w-11 sm:h-11 rounded-xl bg-[#d8862e]/10 flex items-center justify-center shrink-0">
                                <i data-lucide="shopping-cart" class="w-[16px] h-[16px] sm:w-5 sm:h-5 text-[#d8862e]"></i>
                            </div>
                            <h3 class="text-[12px] sm:text-[15px] md:text-[17px] font-semibold text-gray-500 leading-tight">Total Franchise Sales</h3>
                        </div>
                        <p class="mt-2 sm:mt-3 text-[20px] sm:text-[26px] md:text-[30px] font-bold text-[#1e293b] leading-none"><?php echo formatNumberValue($franchiseTotalSales); ?></p>
                        <p class="mt-1 sm:mt-2 text-[11px] sm:text-[14px] font-semibold text-[#d8862e]">Across all showrooms</p>
                    </div>

                    <div class="bg-white border border-gray-300 rounded-2xl min-h-[110px] sm:min-h-[130px] p-3 sm:p-5 shadow-sm hover:shadow-md hover:-translate-y-0.5 transition-all">
                        <div class="flex items-center gap-2 sm:gap-3">
                            <div class="w-8 h-8 sm:w-11 sm:h-11 rounded-xl bg-[#42b36a]/10 flex items-center justify-center shrink-0">
                                <i data-lucide="calendar-clock" class="w-[16px] h-[16px] sm:w-5 sm:h-5 text-[#42b36a]"></i>
                            </div>
                            <h3 class="text-[12px] sm:text-[15px] md:text-[17px] font-semibold text-gray-500 leading-tight">Today's Sales</h3>
                        </div>
                        <p class="mt-2 sm:mt-3 text-[20px] sm:text-[26px] md:text-[30px] font-bold text-[#1e293b] leading-none"><?php echo formatNumberValue($franchiseTodaySales); ?></p>
                        <p class="mt-1 sm:mt-2 text-[11px] sm:text-[14px] font-semibold text-[#42b36a]">Sales placed today</p>
                    </div>

                    <div class="bg-white border border-gray-300 rounded-2xl min-h-[110px] sm:min-h-[130px] p-3 sm:p-5 shadow-sm hover:shadow-md hover:-translate-y-0.5 transition-all">
                        <div class="flex items-center gap-2 sm:gap-3">
                            <div class="w-8 h-8 sm:w-11 sm:h-11 rounded-xl bg-[#42b36a]/10 flex items-center justify-center shrink-0">
                                <i data-lucide="package" class="w-[16px] h-[16px] sm:w-5 sm:h-5 text-[#42b36a]"></i>
                            </div>
                            <h3 class="text-[12px] sm:text-[15px] md:text-[17px] font-semibold text-gray-500 leading-tight">Total Products</h3>
                        </div>
                        <p class="mt-2 sm:mt-3 text-[20px] sm:text-[26px] md:text-[30px] font-bold text-[#1e293b] leading-none"><?php echo formatNumberValue($franchiseTotalProducts); ?></p>
                        <p class="mt-1 sm:mt-2 text-[11px] sm:text-[14px] font-semibold text-[#42b36a]">Across all franchises</p>
                    </div>

                    <div class="bg-white border border-gray-300 rounded-2xl min-h-[110px] sm:min-h-[130px] p-3 sm:p-5 shadow-sm hover:shadow-md hover:-translate-y-0.5 transition-all">
                        <div class="flex items-center gap-2 sm:gap-3">
                            <div class="w-8 h-8 sm:w-11 sm:h-11 rounded-xl bg-[#d8862e]/10 flex items-center justify-center shrink-0">
                                <i data-lucide="badge-indian-rupee" class="w-[16px] h-[16px] sm:w-5 sm:h-5 text-[#d8862e]"></i>
                            </div>
                            <h3 class="text-[12px] sm:text-[15px] md:text-[17px] font-semibold text-gray-500 leading-tight">Total Commission</h3>
                        </div>
                        <p class="mt-2 sm:mt-3 text-[20px] sm:text-[26px] md:text-[30px] font-bold text-[#1e293b] leading-none"><?php echo formatIndianCurrency($franchiseTotalCommission); ?></p>
                        <p class="mt-1 sm:mt-2 text-[11px] sm:text-[14px] font-semibold text-[#d8862e]">Sponsor + turnover income</p>
                    </div>

                    <div class="bg-white border border-gray-300 rounded-2xl min-h-[110px] sm:min-h-[130px] p-3 sm:p-5 shadow-sm hover:shadow-md hover:-translate-y-0.5 transition-all">
                        <div class="flex items-center gap-2 sm:gap-3">
                            <div class="w-8 h-8 sm:w-11 sm:h-11 rounded-xl bg-[#42b36a]/10 flex items-center justify-center shrink-0">
                                <i data-lucide="trending-up" class="w-[16px] h-[16px] sm:w-5 sm:h-5 text-[#42b36a]"></i>
                            </div>
                            <h3 class="text-[12px] sm:text-[15px] md:text-[17px] font-semibold text-gray-500 leading-tight">Total Turnover</h3>
                        </div>
                        <p class="mt-2 sm:mt-3 text-[20px] sm:text-[26px] md:text-[30px] font-bold text-[#1e293b] leading-none"><?php echo formatIndianCurrency($franchiseTotalTurnover); ?></p>
                        <p class="mt-1 sm:mt-2 text-[11px] sm:text-[14px] font-semibold text-[#42b36a]">Franchise showroom sales</p>
                    </div>
                </div>
            </section>

            <section class="mt-6 sm:mt-8">
                <div class="flex flex-wrap items-center gap-3 mb-3 sm:mb-4">
                    <div class="w-7 h-7 rounded-[4px] border border-[#ead9b5] bg-[#faf5ea] flex items-center justify-center shrink-0">
                        <i data-lucide="gift" class="w-[14px] h-[14px] text-[#c79435]"></i>
                    </div>
                    <h2 class="whitespace-nowrap text-[18px] sm:text-[24px] leading-none font-normal text-[#000000CC]" style="font-family: Georgia, 'Times New Roman', serif;">MEMBER DASHBOARD</h2>
                    <div class="hidden sm:block flex-1 h-[1px] bg-[#d2a041]"></div>
                </div>

                <div class="grid grid-cols-2 md:grid-cols-4 gap-3 sm:gap-4 md:gap-6">
                    <div class="bg-white border border-gray-300 rounded-2xl min-h-[110px] sm:min-h-[130px] p-3 sm:p-5 shadow-sm hover:shadow-md hover:-translate-y-0.5 transition-all">
                        <div class="flex items-center gap-2 sm:gap-3">
                            <div class="w-8 h-8 sm:w-11 sm:h-11 rounded-xl bg-[#d8862e]/10 flex items-center justify-center shrink-0">
                                <i data-lucide="users-round" class="w-[16px] h-[16px] sm:w-5 sm:h-5 text-[#d8862e]"></i>
                            </div>
                            <h3 class="text-[12px] sm:text-[15px] md:text-[17px] font-semibold text-gray-500 leading-tight">Total Members</h3>
                        </div>
                        <p class="mt-2 sm:mt-3 text-[20px] sm:text-[26px] md:text-[30px] font-bold text-[#1e293b] leading-none"><?php echo formatNumberValue($memberTotalMembers); ?></p>
                        <p class="mt-1 sm:mt-2 text-[11px] sm:text-[14px] font-semibold text-[#d8862e]">All registered Members</p>
                    </div>

                    <div class="bg-white border border-gray-300 rounded-2xl min-h-[110px] sm:min-h-[130px] p-3 sm:p-5 shadow-sm hover:shadow-md hover:-translate-y-0.5 transition-all">
                        <div class="flex items-center gap-2 sm:gap-3">
                            <div class="w-8 h-8 sm:w-11 sm:h-11 rounded-xl bg-[#42b36a]/10 flex items-center justify-center shrink-0">
                                <i data-lucide="user-plus" class="w-[16px] h-[16px] sm:w-5 sm:h-5 text-[#42b36a]"></i>
                            </div>
                            <h3 class="text-[12px] sm:text-[15px] md:text-[17px] font-semibold text-gray-500 leading-tight">Today's Members</h3>
                        </div>
                        <p class="mt-2 sm:mt-3 text-[20px] sm:text-[26px] md:text-[30px] font-bold text-[#1e293b] leading-none"><?php echo formatNumberValue($memberTodayMembers); ?></p>
                        <p class="mt-1 sm:mt-2 text-[11px] sm:text-[14px] font-semibold text-[#42b36a]">New registrations today</p>
                    </div>

                    <div class="bg-white border border-gray-300 rounded-2xl min-h-[110px] sm:min-h-[130px] p-3 sm:p-5 shadow-sm hover:shadow-md hover:-translate-y-0.5 transition-all">
                        <div class="flex items-center gap-2 sm:gap-3">
                            <div class="w-8 h-8 sm:w-11 sm:h-11 rounded-xl bg-[#4d7ed0]/10 flex items-center justify-center shrink-0">
                                <i data-lucide="trending-up" class="w-[16px] h-[16px] sm:w-5 sm:h-5 text-[#4d7ed0]"></i>
                            </div>
                            <h3 class="text-[12px] sm:text-[15px] md:text-[17px] font-semibold text-gray-500 leading-tight">This Month</h3>
                        </div>
                        <p class="mt-2 sm:mt-3 text-[20px] sm:text-[26px] md:text-[30px] font-bold text-[#1e293b] leading-none"><?php echo formatIndianCurrency($thisMonthRevenue); ?></p>
                        <p class="mt-1 sm:mt-2 text-[11px] sm:text-[14px] font-semibold text-[#4d7ed0]">Monthly earnings</p>
                    </div>

                    <div class="bg-white border border-gray-300 rounded-2xl min-h-[110px] sm:min-h-[130px] p-3 sm:p-5 shadow-sm hover:shadow-md hover:-translate-y-0.5 transition-all">
                        <div class="flex items-center gap-2 sm:gap-3">
                            <div class="w-8 h-8 sm:w-11 sm:h-11 rounded-xl bg-[#42b36a]/10 flex items-center justify-center shrink-0">
                                <i data-lucide="user-check" class="w-[16px] h-[16px] sm:w-5 sm:h-5 text-[#42b36a]"></i>
                            </div>
                            <h3 class="text-[12px] sm:text-[15px] md:text-[17px] font-semibold text-gray-500 leading-tight">Active Members</h3>
                        </div>
                        <p class="mt-2 sm:mt-3 text-[20px] sm:text-[26px] md:text-[30px] font-bold text-[#1e293b] leading-none"><?php echo formatNumberValue($memberActiveMembers); ?></p>
                        <p class="mt-1 sm:mt-2 text-[11px] sm:text-[14px] font-semibold text-[#42b36a]">Currently active Members</p>
                    </div>

                    <div class="bg-white border border-gray-300 rounded-2xl min-h-[110px] sm:min-h-[130px] p-3 sm:p-5 shadow-sm hover:shadow-md hover:-translate-y-0.5 transition-all">
                        <div class="flex items-center gap-2 sm:gap-3">
                            <div class="w-8 h-8 sm:w-11 sm:h-11 rounded-xl bg-[#df5d5d]/10 flex items-center justify-center shrink-0">
                                <i data-lucide="user-x" class="w-[16px] h-[16px] sm:w-5 sm:h-5 text-[#df5d5d]"></i>
                            </div>
                            <h3 class="text-[12px] sm:text-[15px] md:text-[17px] font-semibold text-gray-500 leading-tight">Inactive Members</h3>
                        </div>
                        <p class="mt-2 sm:mt-3 text-[20px] sm:text-[26px] md:text-[30px] font-bold text-[#1e293b] leading-none"><?php echo formatNumberValue($memberInactiveMembers); ?></p>
                        <p class="mt-1 sm:mt-2 text-[11px] sm:text-[14px] font-semibold text-[#df5d5d]">Inactive Members</p>
                    </div>

                    <div class="bg-white border border-gray-300 rounded-2xl min-h-[110px] sm:min-h-[130px] p-3 sm:p-5 shadow-sm hover:shadow-md hover:-translate-y-0.5 transition-all">
                        <div class="flex items-center gap-2 sm:gap-3">
                            <div class="w-8 h-8 sm:w-11 sm:h-11 rounded-xl bg-[#42b36a]/10 flex items-center justify-center shrink-0">
                                <i data-lucide="zap" class="w-[16px] h-[16px] sm:w-5 sm:h-5 text-[#42b36a]"></i>
                            </div>
                            <h3 class="text-[12px] sm:text-[15px] md:text-[17px] font-semibold text-gray-500 leading-tight">Today Activation</h3>
                        </div>
                        <p class="mt-2 sm:mt-3 text-[20px] sm:text-[26px] md:text-[30px] font-bold text-[#1e293b] leading-none"><?php echo formatNumberValue($memberTodayActivation); ?></p>
                        <p class="mt-1 sm:mt-2 text-[11px] sm:text-[14px] font-semibold text-[#42b36a]">Activated today</p>
                    </div>

                    <div class="bg-white border border-gray-300 rounded-2xl min-h-[110px] sm:min-h-[130px] p-3 sm:p-5 shadow-sm hover:shadow-md hover:-translate-y-0.5 transition-all">
                        <div class="flex items-center gap-2 sm:gap-3">
                            <div class="w-8 h-8 sm:w-11 sm:h-11 rounded-xl bg-[#8b5cf6]/10 flex items-center justify-center shrink-0">
                                <i data-lucide="repeat" class="w-[16px] h-[16px] sm:w-5 sm:h-5 text-[#8b5cf6]"></i>
                            </div>
                            <h3 class="text-[12px] sm:text-[15px] md:text-[17px] font-semibold text-gray-500 leading-tight">Repurchase Orders</h3>
                        </div>
                        <p class="mt-2 sm:mt-3 text-[20px] sm:text-[26px] md:text-[30px] font-bold text-[#1e293b] leading-none"><?php echo formatNumberValue($memberRepurchaseOrders); ?></p>
                        <p class="mt-1 sm:mt-2 text-[11px] sm:text-[14px] font-semibold text-[#8b5cf6]">Repeat purchases</p>
                    </div>

                    <div class="bg-white border border-gray-300 rounded-2xl min-h-[110px] sm:min-h-[130px] p-3 sm:p-5 shadow-sm hover:shadow-md hover:-translate-y-0.5 transition-all">
                        <div class="flex items-center gap-2 sm:gap-3">
                            <div class="w-8 h-8 sm:w-11 sm:h-11 rounded-xl bg-[#7c86ee]/10 flex items-center justify-center shrink-0">
                                <i data-lucide="shopping-bag" class="w-[16px] h-[16px] sm:w-5 sm:h-5 text-[#7c86ee]"></i>
                            </div>
                            <h3 class="text-[12px] sm:text-[15px] md:text-[17px] font-semibold text-gray-500 leading-tight">Today's Total Orders</h3>
                        </div>
                        <p class="mt-2 sm:mt-3 text-[20px] sm:text-[26px] md:text-[30px] font-bold text-[#1e293b] leading-none"><?php echo formatNumberValue($memberTodayTotalOrders); ?></p>
                        <p class="mt-1 sm:mt-2 text-[11px] sm:text-[14px] font-semibold text-[#7c86ee]">Orders placed today</p>
                    </div>
                </div>
            </section>
        </div>
    </div>

    <script>
        function copyReferral(elementId) {
            const input = document.getElementById(elementId);
            if (!input) return;

            input.type = 'text';
            input.select();
            input.setSelectionRange(0, 99999);
            document.execCommand('copy');
            input.type = 'hidden';
        }

        if (typeof lucide !== 'undefined') {
            lucide.createIcons();
        }

        (function () {
            var el = document.getElementById('adminWelcome');
            if (!el) return;
            var KEY = 'adminWelcomeShown';
            var seen = false;
            try { seen = sessionStorage.getItem(KEY) === '1'; } catch (e) {}
            if (seen) { el.remove(); return; }
            try { sessionStorage.setItem(KEY, '1'); } catch (e) {}

            setTimeout(function () {
                el.style.opacity = '0';
                el.style.transform = 'translateY(-6px)';
                el.style.maxHeight = '0';
                el.style.marginTop = '-28px';
                el.style.paddingTop = '0';
                el.style.paddingBottom = '0';
                setTimeout(function () { el.remove(); }, 500);
            }, 5000);
        })();
    </script>

</body>
</html>