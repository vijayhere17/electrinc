<?php
declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config/db.php';
include_once __DIR__ . '/includes/admin_access_control.php';

if (function_exists('adminRequireAccessOrRedirect')) {
    adminRefreshSessionPermissions($conn);
    adminRequireAccessOrRedirect();
}

if (!isset($conn) || !($conn instanceof mysqli)) {
    die('Database connection failed.');
}

$conn->set_charset('utf8mb4');

$searchBy    = trim((string)($_GET['search_by'] ?? ''));
$searchValue = trim((string)($_GET['search_value'] ?? ''));
$fromDate    = trim((string)($_GET['from_date'] ?? ''));
$toDate      = trim((string)($_GET['to_date'] ?? ''));
$filter      = trim((string)($_GET['filter'] ?? 'All'));

$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 100;
$offset  = ($page - 1) * $perPage;

$rows         = [];
$totalRows    = 0;
$totalPages   = 1;
$errorMessage = '';

function e($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function tableExists(mysqli $conn, string $tableName): bool
{
    $safeName = $conn->real_escape_string($tableName);
    $result   = $conn->query("SHOW TABLES LIKE '{$safeName}'");
    return $result instanceof mysqli_result && $result->num_rows > 0;
}

function getTableColumns(mysqli $conn, string $tableName): array
{
    $columns   = [];
    $safeName  = $conn->real_escape_string($tableName);
    $colResult = $conn->query("SHOW COLUMNS FROM `{$safeName}`");

    if ($colResult instanceof mysqli_result) {
        while ($col = $colResult->fetch_assoc()) {
            if (isset($col['Field'])) {
                $columns[] = strtolower((string)$col['Field']);
            }
        }
    }

    return $columns;
}

function hasColumn(array $columns, string $name): bool
{
    return in_array(strtolower($name), $columns, true);
}

function getExistingRefs(string $alias, array $columns, array $candidates): array
{
    $refs = [];

    foreach ($candidates as $candidate) {
        if (hasColumn($columns, $candidate)) {
            $refs[] = "{$alias}.`" . strtolower($candidate) . "`";
        }
    }

    return $refs;
}

function buildCoalesce(array $refs, string $fallback = "''"): ?string
{
    if (empty($refs)) {
        return null;
    }

    if (count($refs) === 1) {
        return $refs[0];
    }

    return 'COALESCE(' . implode(', ', $refs) . ", {$fallback})";
}

function bindParams(mysqli_stmt $stmt, string $types, array $params): void
{
    if ($types === '' || empty($params)) {
        return;
    }

    $bindParams = [$types];
    foreach ($params as $key => $value) {
        $bindParams[] = &$params[$key];
    }

    call_user_func_array([$stmt, 'bind_param'], $bindParams);
}

function isValidDate(string $date): bool
{
    if ($date === '') {
        return false;
    }

    $d = DateTime::createFromFormat('Y-m-d', $date);
    return $d && $d->format('Y-m-d') === $date;
}

$hasSignupUsers    = tableExists($conn, 'signup_users');
$hasMemberProfiles = tableExists($conn, 'member_profiles');

$signupColumns  = $hasSignupUsers ? getTableColumns($conn, 'signup_users') : [];
$profileColumns = $hasMemberProfiles ? getTableColumns($conn, 'member_profiles') : [];

$canJoinProfiles = $hasSignupUsers
    && $hasMemberProfiles
    && hasColumn($signupColumns, 'customer_id')
    && hasColumn($profileColumns, 'customer_id');

if ($hasSignupUsers || $hasMemberProfiles) {
    $nameRefs      = [];
    $mobileRefs    = [];
    $stateRefs     = [];
    $cityRefs      = [];
    $joiningRefs   = [];
    $customerRefs  = [];
    $sponsorRefs   = [];
    $positionRefs  = [];
    $panStatusRefs = [];
    $teamSizeRefs  = [];
    $walletRefs    = [];
    $passwordRefs  = [];
    $emailRefs     = [];

    $activeStatusExpr = "'Inactive'";

    if ($hasSignupUsers) {
        $nameRefs      = array_merge($nameRefs, getExistingRefs('su', $signupColumns, ['full_name', 'name']));
        $mobileRefs    = array_merge($mobileRefs, getExistingRefs('su', $signupColumns, ['mobile', 'phone', 'mobile_no']));
        $stateRefs     = array_merge($stateRefs, getExistingRefs('su', $signupColumns, ['state', 'state_name']));
        $cityRefs      = array_merge($cityRefs, getExistingRefs('su', $signupColumns, ['city', 'city_name']));
        $joiningRefs   = array_merge($joiningRefs, getExistingRefs('su', $signupColumns, ['created_at', 'joining_date']));
        $customerRefs  = array_merge($customerRefs, getExistingRefs('su', $signupColumns, ['customer_id', 'member_id', 'user_id']));
        $sponsorRefs   = array_merge($sponsorRefs, getExistingRefs('su', $signupColumns, ['sponsor_id', 'referral_id', 'refferal_id']));
        $positionRefs  = array_merge($positionRefs, getExistingRefs('su', $signupColumns, ['position']));
        $panStatusRefs = array_merge($panStatusRefs, getExistingRefs('su', $signupColumns, ['pan_status']));
        $walletRefs    = array_merge($walletRefs, getExistingRefs('su', $signupColumns, ['wallet_balance']));
        $passwordRefs  = array_merge($passwordRefs, getExistingRefs('su', $signupColumns, ['password']));
        $emailRefs     = array_merge($emailRefs, getExistingRefs('su', $signupColumns, ['email', 'email_id']));

        if (hasColumn($signupColumns, 'total_left_members') && hasColumn($signupColumns, 'total_right_members')) {
            $teamSizeRefs[] = 'COALESCE(su.`total_left_members`, 0) + COALESCE(su.`total_right_members`, 0)';
        } elseif (hasColumn($signupColumns, 'team_size')) {
            $teamSizeRefs[] = 'su.`team_size`';
        }

        if (hasColumn($signupColumns, 'activated_at') && hasColumn($signupColumns, 'registration_status')) {
            $activeStatusExpr = "CASE
                WHEN su.`activated_at` IS NOT NULL AND su.`activated_at` <> '' THEN 'Active'
                WHEN LOWER(TRIM(su.`registration_status`)) = 'active' THEN 'Active'
                ELSE 'Inactive'
            END";
        } elseif (hasColumn($signupColumns, 'activated_at')) {
            $activeStatusExpr = "CASE
                WHEN su.`activated_at` IS NOT NULL AND su.`activated_at` <> '' THEN 'Active'
                ELSE 'Inactive'
            END";
        } elseif (hasColumn($signupColumns, 'registration_status')) {
            $activeStatusExpr = "CASE
                WHEN LOWER(TRIM(su.`registration_status`)) = 'active' THEN 'Active'
                ELSE 'Inactive'
            END";
        }
    }

    if ($hasMemberProfiles) {
        $nameRefs      = array_merge($nameRefs, getExistingRefs('mp', $profileColumns, ['full_name', 'name']));
        $mobileRefs    = array_merge($mobileRefs, getExistingRefs('mp', $profileColumns, ['mobile', 'phone', 'mobile_no']));
        $stateRefs     = array_merge($stateRefs, getExistingRefs('mp', $profileColumns, ['state', 'state_name']));
        $cityRefs      = array_merge($cityRefs, getExistingRefs('mp', $profileColumns, ['city', 'city_name']));
        $joiningRefs   = array_merge($joiningRefs, getExistingRefs('mp', $profileColumns, ['created_at', 'joining_date']));
        $customerRefs  = array_merge($customerRefs, getExistingRefs('mp', $profileColumns, ['customer_id', 'member_id', 'user_id']));
        $sponsorRefs   = array_merge($sponsorRefs, getExistingRefs('mp', $profileColumns, ['sponsor_id', 'referral_id', 'refferal_id']));
        $positionRefs  = array_merge($positionRefs, getExistingRefs('mp', $profileColumns, ['position']));
        $panStatusRefs = array_merge($panStatusRefs, getExistingRefs('mp', $profileColumns, ['pan_status']));
        $passwordRefs  = array_merge($passwordRefs, getExistingRefs('mp', $profileColumns, ['password']));
        $emailRefs     = array_merge($emailRefs, getExistingRefs('mp', $profileColumns, ['email', 'email_id']));

        if (hasColumn($profileColumns, 'total_left_members') && hasColumn($profileColumns, 'total_right_members')) {
            $teamSizeRefs[] = 'COALESCE(mp.`total_left_members`, 0) + COALESCE(mp.`total_right_members`, 0)';
        } elseif (hasColumn($profileColumns, 'team_size')) {
            $teamSizeRefs[] = 'mp.`team_size`';
        }
    }

    $nameExpr      = buildCoalesce($nameRefs);
    $mobileExpr    = buildCoalesce($mobileRefs);
    $stateExpr     = buildCoalesce($stateRefs);
    $cityExpr      = buildCoalesce($cityRefs);
    $joiningExpr   = buildCoalesce($joiningRefs);
    $customerExpr  = buildCoalesce($customerRefs);
    $sponsorExpr   = buildCoalesce($sponsorRefs);
    $positionExpr  = buildCoalesce($positionRefs);
    $panStatusExpr = buildCoalesce($panStatusRefs);
    $teamSizeExpr  = buildCoalesce($teamSizeRefs, '0') ?? '0';
    $walletExpr    = buildCoalesce($walletRefs, '0') ?? '0';
    $passwordExpr  = buildCoalesce($passwordRefs);
    $emailExpr     = buildCoalesce($emailRefs);

    $selectParts   = [];
    $selectParts[] = ($joiningExpr   ? "{$joiningExpr}" : "''") . " AS joining_date";
    $selectParts[] = ($customerExpr  ? "{$customerExpr}" : "''") . " AS customer_id";
    $selectParts[] = ($sponsorExpr   ? "{$sponsorExpr}" : "''") . " AS sponsor_id";
    $selectParts[] = ($positionExpr  ? "{$positionExpr}" : "''") . " AS position";
    $selectParts[] = ($nameExpr      ? "{$nameExpr}" : "''") . " AS name";
    $selectParts[] = ($mobileExpr    ? "{$mobileExpr}" : "''") . " AS mobile";
    $selectParts[] = ($stateExpr     ? "{$stateExpr}" : "''") . " AS state";
    $selectParts[] = ($cityExpr      ? "{$cityExpr}" : "''") . " AS city";
    $selectParts[] = ($panStatusExpr ? "{$panStatusExpr}" : "''") . " AS pan_status";
    $selectParts[] = "{$activeStatusExpr} AS active_status";
    $selectParts[] = "COALESCE({$teamSizeExpr}, 0) AS team_size";
    $selectParts[] = "COALESCE({$walletExpr}, 0) AS wallet_balance";
    $selectParts[] = ($passwordExpr ? "{$passwordExpr}" : "''") . " AS password";
    $selectParts[] = ($emailExpr ? "{$emailExpr}" : "''") . " AS email";

    if ($hasSignupUsers) {
        $fromClause = 'FROM signup_users su';
        if ($canJoinProfiles) {
            $fromClause .= ' LEFT JOIN member_profiles mp ON mp.customer_id = su.customer_id';
        }
    } else {
        $fromClause = 'FROM member_profiles mp';
    }

    $whereClause = ' WHERE 1=1';
    $params      = [];
    $types       = '';
    $orderClause = '';

    $searchMap = [
        'name'        => $nameExpr,
        'mobile'      => $mobileExpr,
        'customer_id' => $customerExpr,
    ];

    if ($searchBy !== '' && isset($searchMap[$searchBy]) && $searchMap[$searchBy] && $searchValue !== '') {
        $whereClause .= "
            AND CONVERT({$searchMap[$searchBy]} USING utf8mb4)
            COLLATE utf8mb4_general_ci
            LIKE
            CONVERT(? USING utf8mb4)
            COLLATE utf8mb4_general_ci
        ";
        $types   .= 's';
        $params[] = '%' . $searchValue . '%';
    }

    if (isValidDate($fromDate) && $joiningExpr) {
        $whereClause .= " AND DATE({$joiningExpr}) >= ?";
        $types       .= 's';
        $params[]     = $fromDate;
    }

    if (isValidDate($toDate) && $joiningExpr) {
        $whereClause .= " AND DATE({$joiningExpr}) <= ?";
        $types       .= 's';
        $params[]     = $toDate;
    }

    if (isValidDate($fromDate) && isValidDate($toDate) && $fromDate > $toDate) {
        $errorMessage = 'From date cannot be greater than To date.';
    }

    if ($filter === 'Active') {
        $whereClause .= "
            AND CONVERT({$activeStatusExpr} USING utf8mb4)
            COLLATE utf8mb4_general_ci = 'Active'
        ";
    } elseif ($filter === 'Inactive') {
        $whereClause .= "
            AND CONVERT({$activeStatusExpr} USING utf8mb4)
            COLLATE utf8mb4_general_ci = 'Inactive'
        ";
    }

    if ($joiningExpr) {
        $orderClause = " ORDER BY {$joiningExpr} DESC";
    } elseif ($customerExpr) {
        $orderClause = " ORDER BY {$customerExpr} DESC";
    }

    if ($errorMessage === '') {
        $countSql  = "SELECT COUNT(*) AS total {$fromClause}{$whereClause}";
        $countStmt = $conn->prepare($countSql);

        if ($countStmt) {
            bindParams($countStmt, $types, $params);
            $countStmt->execute();
            $countResult = $countStmt->get_result();
            $countRow    = $countResult ? $countResult->fetch_assoc() : null;
            $totalRows   = (int)($countRow['total'] ?? 0);
            $countStmt->close();
        } else {
            $errorMessage = 'Count query prepare failed: ' . $conn->error;
        }
    }

    if ($errorMessage === '') {
        $totalPages = $perPage > 0 ? (int)ceil($totalRows / $perPage) : 1;

        if ($page > $totalPages && $totalPages > 0) {
            $page   = $totalPages;
            $offset = ($page - 1) * $perPage;
        }

        $sql = 'SELECT ' . implode(', ', $selectParts) . " {$fromClause}{$whereClause}{$orderClause} LIMIT {$perPage} OFFSET {$offset}";
        $stmt = $conn->prepare($sql);

        if ($stmt) {
            bindParams($stmt, $types, $params);

            if ($stmt->execute()) {
                $result = $stmt->get_result();
                while ($row = $result->fetch_assoc()) {
                    $rows[] = $row;
                }
            } else {
                $errorMessage = 'Query execute failed: ' . $stmt->error;
            }

            $stmt->close();
        } else {
            $errorMessage = 'Query prepare failed: ' . $conn->error;
        }
    }
} else {
    $errorMessage = 'Required table not found.';
}

function paginateUrl(int $p): string
{
    $params = $_GET;
    $params['page'] = $p;
    return '?' . http_build_query($params);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Associate Report</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body class="m-0 bg-[#f3f4f6] font-sans overflow-x-hidden">

<?php if (file_exists(__DIR__ . '/includes/sidebar.php')) include __DIR__ . '/includes/sidebar.php'; ?>

<div class="min-h-screen <?php echo file_exists(__DIR__ . '/includes/sidebar.php') ? 'lg:ml-64 lg:w-[calc(100%-16rem)]' : ''; ?>">
    <?php if (file_exists(__DIR__ . '/includes/navbar.php')) include __DIR__ . '/includes/navbar.php'; ?>

    <main class="px-3 sm:px-4 md:px-5 py-4">
        <div class="flex items-center gap-3 sm:gap-4 mb-4">
            <div class="w-9 h-9 rounded-[8px] bg-[#f6f2ea] flex items-center justify-center shrink-0">
                <i data-lucide="badge-help" class="w-4 h-4 text-[#c9983b]"></i>
            </div>
            <h2 class="text-[15px] sm:text-[17px] md:text-[18px] leading-none text-[#2f2f2f] font-normal whitespace-nowrap" style="font-family: Georgia, 'Times New Roman', serif;">
                ASSOCIATE REPORT
            </h2>
            <div class="h-[1px] bg-[#c9983b] flex-1"></div>
        </div>

        <div class="relative bg-white border border-[#dfdfdf] rounded-[12px] min-h-[360px] px-3 sm:px-4 py-4">
            <form method="GET" class="space-y-4">
                <input type="hidden" name="page" value="1">

                <div class="grid grid-cols-1 xl:grid-cols-2 gap-4">
                    <div class="rounded-lg border border-[#e5e7eb] p-3">
                        <div class="flex flex-col gap-3 md:flex-row md:items-end">
                            <div class="w-full md:w-[180px]">
                                <label class="mb-1 block text-sm font-medium text-[#3a3a3a]">Search Associate by</label>
                                <select name="search_by" class="w-full h-10 rounded-md border border-[#d8dce2] bg-[#f2f4f7] px-3 text-sm text-[#555] outline-none">
                                    <option value="">Select</option>
                                    <option value="name" <?php echo $searchBy === 'name' ? 'selected' : ''; ?>>Name</option>
                                    <option value="customer_id" <?php echo $searchBy === 'customer_id' ? 'selected' : ''; ?>>Customer Id</option>
                                    <option value="mobile" <?php echo $searchBy === 'mobile' ? 'selected' : ''; ?>>Mobile</option>
                                </select>
                            </div>

                            <div class="flex-1">
                                <label class="mb-1 block text-sm font-medium text-[#3a3a3a]">Search Value</label>
                                <input type="text" name="search_value" value="<?php echo e($searchValue); ?>" class="w-full h-10 rounded-md border border-[#d8dce2] bg-[#f2f4f7] px-3 text-sm outline-none">
                            </div>

                            <button type="submit" class="h-10 w-full md:w-auto rounded-md bg-[#143a66] px-4 text-white text-sm font-semibold inline-flex items-center justify-center gap-2 hover:bg-[#0e2e52]">
                                <i data-lucide="search" class="w-4 h-4"></i> Search
                            </button>
                        </div>
                    </div>

                    <div class="rounded-lg border border-[#e5e7eb] p-3">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                            <div>
                                <label class="mb-1 block text-sm font-medium text-[#3a3a3a]">From Date</label>
                                <input type="date" name="from_date" value="<?php echo e($fromDate); ?>" class="w-full h-10 rounded-md border border-[#d8dce2] bg-[#f2f4f7] px-3 text-sm outline-none">
                            </div>

                            <div>
                                <label class="mb-1 block text-sm font-medium text-[#3a3a3a]">To Date</label>
                                <input type="date" name="to_date" value="<?php echo e($toDate); ?>" class="w-full h-10 rounded-md border border-[#d8dce2] bg-[#f2f4f7] px-3 text-sm outline-none">
                            </div>

                            <div>
                                <label class="mb-1 block text-sm font-medium text-[#3a3a3a]">Filter</label>
                                <select name="filter" class="w-full h-10 rounded-md border border-[#d8dce2] bg-[#f2f4f7] px-3 text-sm text-[#555] outline-none">
                                    <option value="All" <?php echo $filter === 'All' ? 'selected' : ''; ?>>All</option>
                                    <option value="Active" <?php echo $filter === 'Active' ? 'selected' : ''; ?>>Active</option>
                                    <option value="Inactive" <?php echo $filter === 'Inactive' ? 'selected' : ''; ?>>Inactive</option>
                                </select>
                            </div>
                        </div>

                        <div class="mt-3 flex flex-col sm:flex-row gap-3">
                            <button type="submit" class="h-10 w-full sm:w-auto rounded-md bg-[#143a66] px-4 text-white text-sm font-semibold inline-flex items-center justify-center gap-2 hover:bg-[#0e2e52]">
                                <i data-lucide="calendar-days" class="w-4 h-4"></i> Apply Filters
                            </button>
                        </div>
                    </div>
                </div>
            </form>

            <?php if ($errorMessage !== ''): ?>
                <div class="mt-3 rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
                    <?php echo e($errorMessage); ?>
                </div>
            <?php endif; ?>

            <div class="mt-3 rounded-[6px] overflow-hidden border border-[#d6a246]">
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[1100px] border-collapse text-[13px] sm:text-[14px] md:text-[15px]">
                        <thead>
                            <tr class="bg-[#cd9737] text-white h-[42px]">
                                <th class="px-2 py-2 font-medium whitespace-nowrap text-left">Sr No</th>
                                <th class="px-2 py-2 font-medium whitespace-nowrap text-left">View Account</th>
                                <th class="px-2 py-2 font-medium whitespace-nowrap text-left">Joining Date</th>
                                <th class="px-2 py-2 font-medium whitespace-nowrap text-left">Customer Id</th>
                                <th class="px-2 py-2 font-medium whitespace-nowrap text-left">Sponsor Id</th>
                                <th class="px-2 py-2 font-medium whitespace-nowrap text-left">Position</th>
                                <th class="px-2 py-2 font-medium whitespace-nowrap text-left">Name</th>
                                <th class="px-2 py-2 font-medium whitespace-nowrap text-left">Mobile</th>
                                <th class="px-2 py-2 font-medium whitespace-nowrap text-left">Email</th>
                                <th class="px-2 py-2 font-medium whitespace-nowrap text-left">State</th>
                                <th class="px-2 py-2 font-medium whitespace-nowrap text-left">City</th>
                                <th class="px-2 py-2 font-medium whitespace-nowrap text-left">Team</th>
                                <th class="px-2 py-2 font-medium whitespace-nowrap text-left">Wallet</th>
                                <th class="px-2 py-2 font-medium whitespace-nowrap text-left">Active Status</th>
                                <th class="px-2 py-2 font-medium whitespace-nowrap text-left">Edit</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($rows)): ?>
                                <?php $sr = $offset + 1; ?>
                                <?php foreach ($rows as $row): ?>
                                    <?php
                                        $rowCustomerId = trim((string)($row['customer_id'] ?? ''));
                                        $activeStatus  = trim((string)($row['active_status'] ?? 'Inactive'));
                                        $walletBalance = is_numeric($row['wallet_balance'] ?? null)
                                            ? number_format((float)$row['wallet_balance'], 2)
                                            : '0.00';
                                    ?>
                                    <tr class="text-[#2d2d2d] border-t border-[#ececec] hover:bg-[#fafafa]">
                                        <td class="px-2 py-2 whitespace-nowrap"><?php echo $sr++; ?></td>
                                        <td class="px-2 py-2 whitespace-nowrap">
                                            <?php if ($rowCustomerId !== ''): ?>
                                                <a href="generate-member-access.php?customer_id=<?php echo urlencode($rowCustomerId); ?>"
                                                   target="_blank"
                                                   class="inline-flex items-center justify-center h-[34px] min-w-[72px] rounded-[7px] bg-[#143a66] px-3 text-white text-[13px] font-semibold hover:bg-[#0e2e52]">
                                                    View
                                                </a>
                                            <?php else: ?>
                                                <span class="text-gray-400">N/A</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-2 py-2 whitespace-nowrap"><?php echo e($row['joining_date'] ?? ''); ?></td>
                                        <td class="px-2 py-2 whitespace-nowrap"><?php echo e($row['customer_id'] ?? ''); ?></td>
                                        <td class="px-2 py-2 whitespace-nowrap"><?php echo e($row['sponsor_id'] ?? ''); ?></td>
                                        <td class="px-2 py-2 whitespace-nowrap"><?php echo e($row['position'] ?? ''); ?></td>
                                        <td class="px-2 py-2 whitespace-nowrap"><?php echo e($row['name'] ?? ''); ?></td>
                                        <td class="px-2 py-2 whitespace-nowrap"><?php echo e($row['mobile'] ?? ''); ?></td>
                                        <td class="px-2 py-2 break-words min-w-[180px]"><?php echo e($row['email'] ?? ''); ?></td>
                                        <td class="px-2 py-2 whitespace-nowrap"><?php echo e($row['state'] ?? ''); ?></td>
                                        <td class="px-2 py-2 whitespace-nowrap"><?php echo e($row['city'] ?? ''); ?></td>
                                        <td class="px-2 py-2 whitespace-nowrap"><?php echo e($row['team_size'] ?? '0'); ?></td>
                                        <td class="px-2 py-2 whitespace-nowrap"><?php echo e($walletBalance); ?></td>
                                        <td class="px-2 py-2 whitespace-nowrap">
                                            <?php if ($activeStatus === 'Active'): ?>
                                                <span class="inline-flex items-center rounded-full bg-green-100 px-2 py-0.5 text-xs font-semibold text-green-700">Active</span>
                                            <?php else: ?>
                                                <span class="inline-flex items-center rounded-full bg-red-100 px-2 py-0.5 text-xs font-semibold text-red-700">Inactive</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-2 py-2 whitespace-nowrap">
                                            <?php if ($rowCustomerId !== ''): ?>
                                                <button
                                                    type="button"
                                                    onclick="openEditModal('<?php echo e($rowCustomerId); ?>')"
                                                    class="inline-flex items-center justify-center h-[34px] min-w-[72px] rounded-[7px] bg-[#c9983b] px-3 text-white text-[13px] font-semibold hover:bg-[#b8862d]">
                                                    Edit
                                                </button>
                                            <?php else: ?>
                                                <span class="text-gray-400">N/A</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="15" class="px-2 py-10 text-center text-[#6b7280]">No data found.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <?php if ($totalPages > 1): ?>
                <div class="mt-3 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 text-[14px] text-[#555]">
                    <span>Showing <?php echo count($rows); ?> of <?php echo $totalRows; ?> records</span>

                    <div class="flex flex-wrap items-center gap-1">
                        <?php if ($page > 1): ?>
                            <a href="<?php echo e(paginateUrl($page - 1)); ?>" class="px-3 py-1 rounded-[5px] border border-[#d8dce2] bg-[#f2f4f7] hover:bg-white">&#8249; Prev</a>
                        <?php endif; ?>

                        <?php
                        $start = max(1, $page - 2);
                        $end   = min($totalPages, $page + 2);
                        for ($p = $start; $p <= $end; $p++):
                        ?>
                            <a href="<?php echo e(paginateUrl($p)); ?>"
                               class="px-3 py-1 rounded-[5px] border <?php echo $p === $page ? 'bg-[#143a66] text-white border-[#143a66]' : 'border-[#d8dce2] bg-[#f2f4f7] hover:bg-white'; ?>">
                                <?php echo $p; ?>
                            </a>
                        <?php endfor; ?>

                        <?php if ($page < $totalPages): ?>
                            <a href="<?php echo e(paginateUrl($page + 1)); ?>" class="px-3 py-1 rounded-[5px] border border-[#d8dce2] bg-[#f2f4f7] hover:bg-white">Next &#8250;</a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <div id="editModal"
             class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 p-3 sm:p-4">
            <div class="w-full max-w-[650px] rounded-[16px] bg-white shadow-2xl max-h-[90vh] overflow-y-auto">
                <div class="flex items-center justify-between border-b px-4 sm:px-6 py-4">
                    <h3 class="text-[18px] sm:text-[20px] font-bold text-[#143a66]">
                        Edit Member
                    </h3>

                    <button
                        type="button"
                        onclick="closeEditModal()"
                        class="text-[28px] leading-none text-gray-500 hover:text-black">
                        &times;
                    </button>
                </div>

                <div class="p-4 sm:p-6">
                    <input type="hidden" id="edit_customer_id">

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="mb-1 block text-sm font-medium">Full Name</label>
                            <input
                                type="text"
                                id="edit_name"
                                class="w-full rounded-lg border border-gray-300 px-3 py-2.5 outline-none focus:border-[#143a66]">
                        </div>

                        <div>
                            <label class="mb-1 block text-sm font-medium">Mobile</label>
                            <input
                                type="text"
                                id="edit_mobile"
                                class="w-full rounded-lg border border-gray-300 px-3 py-2.5 outline-none focus:border-[#143a66]">
                        </div>

                        <div>
                            <label class="mb-1 block text-sm font-medium">Email</label>
                            <input
                                type="text"
                                id="edit_email"
                                class="w-full rounded-lg border border-gray-300 px-3 py-2.5 outline-none focus:border-[#143a66]">
                        </div>

                        <div>
                            <label class="mb-1 block text-sm font-medium">Number</label>
                            <input
                                type="text"
                                id="edit_number"
                                class="w-full rounded-lg border border-gray-300 px-3 py-2.5 outline-none focus:border-[#143a66]">
                        </div>

                        <div>
                            <label class="mb-1 block text-sm font-medium">State</label>
                            <input
                                type="text"
                                id="edit_state"
                                class="w-full rounded-lg border border-gray-300 px-3 py-2.5 outline-none focus:border-[#143a66]">
                        </div>

                        <div>
                            <label class="mb-1 block text-sm font-medium">City</label>
                            <input
                                type="text"
                                id="edit_city"
                                class="w-full rounded-lg border border-gray-300 px-3 py-2.5 outline-none focus:border-[#143a66]">
                        </div>
                    </div>

                    <div class="mt-6 flex flex-col-reverse sm:flex-row justify-end gap-3">
                        <button
                            type="button"
                            onclick="closeEditModal()"
                            class="w-full sm:w-auto rounded-lg border border-gray-300 px-5 py-2.5 font-medium">
                            Cancel
                        </button>

                        <button
                            type="button"
                            onclick="saveMember()"
                            class="w-full sm:w-auto rounded-lg bg-[#143a66] px-5 py-2.5 font-semibold text-white hover:bg-[#0e2e52]">
                            Save Changes
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<script>
function openEditModal(customerId) {
    fetch('get-member.php?customer_id=' + encodeURIComponent(customerId))
        .then(res => res.json())
        .then(data => {
            document.getElementById('edit_customer_id').value = data.customer_id || '';
            document.getElementById('edit_name').value = data.full_name || '';
            document.getElementById('edit_mobile').value = data.mobile || '';
            document.getElementById('edit_state').value = data.state || '';
            document.getElementById('edit_city').value = data.city || '';
            document.getElementById('edit_email').value = data.email || '';
            document.getElementById('edit_number').value = data.number || data.mobile || '';

            document.getElementById('editModal').classList.remove('hidden');
            document.getElementById('editModal').classList.add('flex');
        })
        .catch(() => {
            alert('Unable to fetch member details.');
        });
}

function closeEditModal() {
    document.getElementById('editModal').classList.add('hidden');
    document.getElementById('editModal').classList.remove('flex');
}

function saveMember() {
    const saveButton = event?.target;
    if (saveButton) {
        saveButton.disabled = true;
        saveButton.textContent = 'Saving...';
    }

    const formData = new FormData();
    formData.append('customer_id', document.getElementById('edit_customer_id').value);
    formData.append('full_name', document.getElementById('edit_name').value);
    formData.append('mobile', document.getElementById('edit_mobile').value);
    formData.append('state', document.getElementById('edit_state').value);
    formData.append('city', document.getElementById('edit_city').value);
    formData.append('email', document.getElementById('edit_email').value);
    formData.append('number', document.getElementById('edit_number').value);

    fetch('update-member.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.text())
    .then(response => {
        const text = String(response).toLowerCase();

        if (text.includes('success') || text.includes('updated')) {
            alert('Member updated successfully');
            location.reload();
            return;
        }

        alert(response || 'Update response received.');
    })
    .catch(() => {
        alert('Failed to update member.');
    })
    .finally(() => {
        if (saveButton) {
            saveButton.disabled = false;
            saveButton.textContent = 'Save Changes';
        }
    });
}

window.addEventListener('click', function (e) {
    const modal = document.getElementById('editModal');
    if (e.target === modal) {
        closeEditModal();
    }
});

if (typeof lucide !== 'undefined') {
    lucide.createIcons();
}
</script>
</body>
</html>