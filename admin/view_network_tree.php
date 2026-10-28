<?php
session_start();

$memberName   = $_SESSION['full_name'] ?? 'Swapnil Solanki';
$customerId   = $_SESSION['member_id'] ?? 'MLM-00484';
$managerName  = $_SESSION['full_name'] ?? 'Swapnil Solanki';
$managerRole  = 'MLM Manager';
$profileImage = 'https://i.pravatar.cc/80?img=12';

$associateId = trim($_GET['associate_id'] ?? '');
$parentRow = null;
$downlineRows = [];
$errorMessage = '';

include __DIR__ . '/config/db.php';

function hasAssocColumn(array $columns, string $name): bool {
    return in_array(strtolower($name), $columns, true);
}

function tableExists(mysqli $conn, string $table): bool {
    $escaped = $conn->real_escape_string($table);
    $result = $conn->query("SHOW TABLES LIKE '{$escaped}'");
    return $result instanceof mysqli_result && $result->num_rows > 0;
}

function getTableColumns(mysqli $conn, string $table): array {
    $columns = [];
    if (!tableExists($conn, $table)) {
        return $columns;
    }

    $result = $conn->query("SHOW COLUMNS FROM `{$table}`");
    if ($result instanceof mysqli_result) {
        while ($row = $result->fetch_assoc()) {
            if (isset($row['Field'])) {
                $columns[] = strtolower((string)$row['Field']);
            }
        }
    }

    return $columns;
}

function firstExistingColumn(array $columns, array $candidates): ?string {
    foreach ($candidates as $candidate) {
        if (hasAssocColumn($columns, $candidate)) {
            return $candidate;
        }
    }

    return null;
}

$sourceTable = null;
$columns = [];

if (tableExists($conn, 'associates')) {
    $assocCount = 0;
    $countRes = $conn->query('SELECT COUNT(*) AS total FROM associates');
    if ($countRes instanceof mysqli_result) {
        $assocCount = (int)($countRes->fetch_assoc()['total'] ?? 0);
    }

    if ($assocCount > 0) {
        $sourceTable = 'associates';
        $columns = getTableColumns($conn, 'associates');
    }
}

if ($sourceTable === null && tableExists($conn, 'signup_users')) {
    $sourceTable = 'signup_users';
    $columns = getTableColumns($conn, 'signup_users');
}

$loginWinCodeCol = null;
$uplineWinCodeCol = null;
$nameCol = null;
$positionCol = null;
$mobileCol = null;
$joiningDateCol = null;

if ($sourceTable === 'associates') {
    $loginWinCodeCol = firstExistingColumn($columns, ['login_win_code', 'win_code']);
    $uplineWinCodeCol = firstExistingColumn($columns, ['upline_win_code', 'upline_id', 'sponsor_id']);
    $nameCol = firstExistingColumn($columns, ['name', 'full_name']);
    $positionCol = firstExistingColumn($columns, ['position', 'side']);
    $mobileCol = firstExistingColumn($columns, ['mobile', 'mobile_no', 'phone']);
    $joiningDateCol = firstExistingColumn($columns, ['joining_date', 'created_at', 'created_on']);
} elseif ($sourceTable === 'signup_users') {
    $loginWinCodeCol = firstExistingColumn($columns, ['customer_id', 'member_id', 'associate_id', 'login_id']);
    $uplineWinCodeCol = firstExistingColumn($columns, ['placement_id', 'upline_id', 'sponsor_id', 'referral_id', 'refferal_id', 'parent_id']);
    $nameCol = firstExistingColumn($columns, ['full_name', 'name']);
    $positionCol = firstExistingColumn($columns, ['position', 'side']);
    $mobileCol = firstExistingColumn($columns, ['mobile', 'mobile_no', 'phone']);
    $joiningDateCol = firstExistingColumn($columns, ['created_at', 'joining_date', 'created_on']);
}

if ($associateId !== '' && $sourceTable && $loginWinCodeCol && $uplineWinCodeCol) {
    $parentSql = 'SELECT '
        . ($loginWinCodeCol ? "$loginWinCodeCol AS login_win_code" : "'' AS login_win_code") . ', '
        . ($uplineWinCodeCol ? "$uplineWinCodeCol AS upline_win_code" : "'' AS upline_win_code") . ', '
        . ($nameCol ? "$nameCol AS name" : "'' AS name") . ', '
        . ($positionCol ? "$positionCol AS position" : "'' AS position")
        . ' FROM ' . $sourceTable . ' WHERE ' . $loginWinCodeCol . ' = ? LIMIT 1';

    $parentStmt = $conn->prepare($parentSql);
    if ($parentStmt) {
        $parentStmt->bind_param('s', $associateId);
        $parentStmt->execute();
        $parentResult = $parentStmt->get_result();
        $parentRow = $parentResult ? $parentResult->fetch_assoc() : null;
        $parentStmt->close();
    }

    if (!$parentRow) {
        $errorMessage = 'No associate found for this WIN Code.';
    } else {
        $downlineSql = 'SELECT '
            . ($loginWinCodeCol ? "$loginWinCodeCol AS login_win_code" : "'' AS login_win_code") . ', '
            . ($nameCol ? "$nameCol AS name" : "'' AS name") . ', '
            . ($positionCol ? "$positionCol AS position" : "'' AS position") . ', '
            . ($mobileCol ? "$mobileCol AS mobile" : "'' AS mobile") . ', '
            . ($joiningDateCol ? "$joiningDateCol AS joining_date" : "'' AS joining_date")
            . ' FROM ' . $sourceTable . ' WHERE ' . $uplineWinCodeCol . ' = ?';

        if (hasAssocColumn($columns, 'id')) {
            $downlineSql .= ' ORDER BY id DESC';
        } elseif ($joiningDateCol) {
            $downlineSql .= " ORDER BY {$joiningDateCol} DESC";
        }

        $downlineStmt = $conn->prepare($downlineSql);
        if ($downlineStmt) {
            $downlineStmt->bind_param('s', $associateId);
            $downlineStmt->execute();
            $downlineResult = $downlineStmt->get_result();
            while ($downlineResult && ($row = $downlineResult->fetch_assoc())) {
                $downlineRows[] = $row;
            }
            $downlineStmt->close();
        }
    }
} elseif ($associateId !== '' && (!$sourceTable || !$loginWinCodeCol || !$uplineWinCodeCol)) {
    $errorMessage = 'Network tree is not configured in current database schema.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Network Tree</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body class="m-0 bg-[#f3f4f6] font-sans overflow-x-hidden">

    <?php include __DIR__ . '/includes/sidebar.php'; ?>

    <div class="ml-64 min-h-screen w-[calc(100%-16rem)]">
        <?php include __DIR__ . '/includes/navbar.php'; ?>

        <main class="px-5 py-4">

            <div class="flex items-center gap-4 mb-4">
                <div class="w-9 h-9 rounded-[8px] bg-[#f6f2ea] flex items-center justify-center shrink-0">
                    <i data-lucide="network" class="w-4 h-4 text-[#c9983b]"></i>
                </div>

                <h2 class="text-[17px] md:text-[18px] leading-none text-[#2f2f2f] font-normal whitespace-nowrap"
                    style="font-family: Georgia, 'Times New Roman', serif;">
                    VIEW NETWORK TREE
                </h2>

                <div class="h-[1px] bg-[#c9983b] flex-1"></div>
            </div>

            <div class="relative bg-white border border-[#dfdfdf] rounded-[12px] min-h-[360px] px-3 py-4">

                <form method="GET" class="space-y-3">
                    <div class="flex flex-wrap items-center gap-x-4 gap-y-3 text-[16px] text-[#3a3a3a] font-medium">
                        <div class="flex items-center gap-2">
                            <label class="whitespace-nowrap">Search by Customer Code:</label>
                            <input
                                type="text"
                                name="associate_id"
                                value="<?php echo htmlspecialchars($associateId); ?>"
                                class="w-[145px] h-[24px] rounded-[4px] border border-[#d8dce2] bg-[#f2f4f7] px-2 text-[15px] text-[#555] outline-none"
                            >
                        </div>

                        <button
                            type="submit"
                            class="h-[24px] min-w-[72px] rounded-[5px] bg-[#143a66] text-white text-[15px] px-3 flex items-center justify-center gap-1"
                        >
                            <i data-lucide="search" class="w-3 h-3"></i>
                            <span>Search</span>
                        </button>
                    </div>
                </form>

                <?php if ($errorMessage !== ''): ?>
                    <div class="mt-4 text-[12px] text-red-600"><?php echo htmlspecialchars($errorMessage); ?></div>
                <?php endif; ?>

                <?php if ($parentRow): ?>
                    <div class="mt-4 rounded-[6px] border border-[#d6a246] bg-[#fff9ef] px-3 py-2 text-[11px] text-[#2d2d2d]">
                        <span class="font-semibold">Selected Associate:</span>
                        <?php echo htmlspecialchars($parentRow['login_win_code'] ?? ''); ?> |
                        <?php echo htmlspecialchars($parentRow['name'] ?? ''); ?> |
                        Position: <?php echo htmlspecialchars($parentRow['position'] ?? ''); ?> |
                        Upline: <?php echo htmlspecialchars($parentRow['upline_win_code'] ?? ''); ?>
                    </div>
                <?php endif; ?>

                <div class="mt-3 rounded-[6px] overflow-hidden border border-[#d6a246]">
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[980px] border-collapse">
                            <thead>
                                <tr class="bg-[#cd9737] text-white text-[13px] h-[28px]">
                                    <th class="px-2 font-medium whitespace-nowrap text-left">#</th>
                                    <th class="px-2 font-medium whitespace-nowrap text-left">Associate Code</th>
                                    <th class="px-2 font-medium whitespace-nowrap text-left">Name</th>
                                    <th class="px-2 font-medium whitespace-nowrap text-left">Position</th>
                                    <th class="px-2 font-medium whitespace-nowrap text-left">Mobile</th>
                                    <th class="px-2 font-medium whitespace-nowrap text-left">Joining Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($downlineRows)): ?>
                                    <?php foreach ($downlineRows as $index => $row): ?>
                                        <tr class="h-[34px] text-[10px] text-[#2d2d2d] border-t border-[#ececec]">
                                            <td class="px-2 whitespace-nowrap"><?php echo $index + 1; ?></td>
                                            <td class="px-2 whitespace-nowrap"><?php echo htmlspecialchars($row['login_win_code'] ?? ''); ?></td>
                                            <td class="px-2 whitespace-nowrap"><?php echo htmlspecialchars($row['name'] ?? ''); ?></td>
                                            <td class="px-2 whitespace-nowrap"><?php echo htmlspecialchars($row['position'] ?? ''); ?></td>
                                            <td class="px-2 whitespace-nowrap"><?php echo htmlspecialchars($row['mobile'] ?? ''); ?></td>
                                            <td class="px-2 whitespace-nowrap"><?php echo htmlspecialchars($row['joining_date'] ?? ''); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="h-[320px] bg-white"></td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>

        </main>
    </div>

    <script>
        lucide.createIcons();
    </script>
</body>
</html>
