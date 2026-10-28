<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$memberName   = $_SESSION['full_name'] ?? 'Swapnil Solanki';
$customerId   = $_SESSION['customer_id'] ?? $_SESSION['member_id'] ?? 'MLM-00484';
$managerName  = $_SESSION['full_name'] ?? 'Swapnil Solanki';
$managerRole  = 'MLM Manager';
$profileImage = 'https://i.pravatar.cc/80?img=12';

include __DIR__ . '/config/db.php';

if (!isset($conn) || !($conn instanceof mysqli)) {
    die("Database connection failed. Please check config/db.php");
}

$conn->set_charset("utf8mb4");

$searchBy    = trim($_GET['search_by'] ?? '');
$searchValue = trim($_GET['search_value'] ?? '');
$fromDate    = trim($_GET['from_date'] ?? '');
$toDate      = trim($_GET['to_date'] ?? '');

$walletRows = [];
$errorMessage = '';
$dateFilterAvailable = false;

function tableExists(mysqli $conn, string $tableName): bool {
    $safeName = $conn->real_escape_string($tableName);
    $result = $conn->query("SHOW TABLES LIKE '{$safeName}'");
    return $result instanceof mysqli_result && $result->num_rows > 0;
}

function getTableColumns(mysqli $conn, string $tableName): array {
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

function firstExistingColumn(array $existingColumns, array $candidates): ?string {
    foreach ($candidates as $column) {
        if (in_array($column, $existingColumns, true)) {
            return $column;
        }
    }

    return null;
}

function fetchRowsFromMembers(mysqli $conn, string $tableName, ?string $searchBy, string $searchValue, string $fromDate, string $toDate): array {
    $columns = getTableColumns($conn, $tableName);
    $loginColumn = firstExistingColumn($columns, ['customer_id', 'member_id', 'associate_id', 'login_id']);
    $nameColumn = firstExistingColumn($columns, ['full_name', 'name']);
    $mobileColumn = firstExistingColumn($columns, ['mobile', 'mobile_no', 'phone']);
    $createdColumn = firstExistingColumn($columns, ['created_at', 'created_on', 'date_created']);

    if ($createdColumn !== null) {
        $GLOBALS['dateFilterAvailable'] = true;
    }

    if (!$loginColumn || !$nameColumn) {
        return [];
    }

    $sql = "SELECT {$loginColumn} AS login_id, {$nameColumn} AS name";
    $sql .= $mobileColumn ? ", {$mobileColumn} AS mobile_no" : ", '' AS mobile_no";
    $sql .= $createdColumn ? ", {$createdColumn} AS created_at" : ", '' AS created_at";
    $sql .= " FROM `{$tableName}` WHERE 1=1";

    $params = [];
    $types = '';

    $searchColumns = [
        'associate_id' => $loginColumn,
        'login_id' => $loginColumn,
        'full_name' => $nameColumn,
        'name' => $nameColumn,
        'mobile_no' => $mobileColumn,
        'mobile' => $mobileColumn,
    ];

    if ($searchBy !== '' && $searchValue !== '' && isset($searchColumns[$searchBy]) && $searchColumns[$searchBy] !== null) {
        $sql .= " AND {$searchColumns[$searchBy]} LIKE ?";
        $types .= 's';
        $params[] = '%' . $searchValue . '%';
    }

    if ($createdColumn !== '') {
        if ($fromDate !== '') {
            $sql .= " AND DATE({$createdColumn}) >= ?";
            $types .= 's';
            $params[] = $fromDate;
        }

        if ($toDate !== '') {
            $sql .= " AND DATE({$createdColumn}) <= ?";
            $types .= 's';
            $params[] = $toDate;
        }
    }

    $sql .= $createdColumn !== '' ? " ORDER BY {$createdColumn} DESC" : " ORDER BY login_id DESC";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return [];
    }

    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }

    $rows = [];
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
    }

    $stmt->close();

    return $rows;
}

/*
|--------------------------------------------------------------------------
| Build member-first wallet report data
|--------------------------------------------------------------------------
*/
if ($errorMessage === '') {
    $members = [];
    foreach (['signup_users'] as $tableName) {
        if (!tableExists($conn, $tableName)) {
            continue;
        }

        $tableRows = fetchRowsFromMembers($conn, $tableName, $searchBy, $searchValue, $fromDate, $toDate);
        foreach ($tableRows as $row) {
            $row['source_table'] = $tableName;
            $members[] = $row;
        }
    }

    $balanceMap = [];
    if (tableExists($conn, 'wallet_balance')) {
        $balanceResult = $conn->query("SELECT customer_id, associate_id, balance, last_updated FROM wallet_balance");
        if ($balanceResult instanceof mysqli_result) {
            while ($row = $balanceResult->fetch_assoc()) {
                $key = trim((string)($row['customer_id'] ?? ''));
                if ($key === '') {
                    $key = trim((string)($row['associate_id'] ?? ''));
                }

                if ($key !== '') {
                    $balanceMap[$key] = $row;
                }
            }
        }
    } else {
        $errorMessage = 'wallet_balance table not found in database.';
    }

    foreach ($members as $member) {
        $loginId = (string)($member['login_id'] ?? '');
        $balanceRow = $loginId !== '' && isset($balanceMap[$loginId]) ? $balanceMap[$loginId] : null;
        $walletRows[] = [
            'date'           => $balanceRow['last_updated'] ?? ($member['created_at'] ?? ''),
            'login_id'       => $loginId,
            'name'           => $member['name'] ?? '',
            'pan_no'         => '',
            'mobile_no'      => $member['mobile_no'] ?? '',
            'wallet_balance' => $balanceRow['balance'] ?? 0,
        ];
    }

    usort($walletRows, static function (array $left, array $right): int {
        return strcmp((string)($right['date'] ?? ''), (string)($left['date'] ?? ''));
    });
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Wallet Report</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body class="m-0 bg-[#f3f4f6] font-sans overflow-x-hidden">

    <?php include __DIR__ . '/includes/sidebar.php'; ?>

    <div class="ml-64 min-h-screen">
        <?php include __DIR__ . '/includes/navbar.php'; ?>

        <main class="px-8 py-5">

            <div class="flex items-center gap-4 mb-8">
                <div class="w-9 h-9 rounded-[8px] border border-[#ead9b5] bg-[#f8f1e7] flex items-center justify-center shrink-0">
                    <i data-lucide="wallet-cards" class="w-5 h-5 text-[#c79435]"></i>
                </div>

                <h2
                    class="text-[24px] font-normal text-[#313131] leading-none tracking-wide whitespace-nowrap"
                    style="font-family: Georgia, 'Times New Roman', serif;"
                >
                    WALLET REPORT
                </h2>

                <div class="h-[1px] bg-[#c9963b] flex-1"></div>
            </div>

            <?php if ($errorMessage !== ''): ?>
                <div class="mb-5 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                    <?php echo htmlspecialchars($errorMessage); ?>
                </div>
            <?php endif; ?>

            <?php if (!$dateFilterAvailable): ?>
                <div class="mb-5 rounded-lg border border-yellow-200 bg-yellow-50 px-4 py-3 text-sm text-yellow-700">
                    Date column not found in wallet_balance table, so date filter and date values are unavailable.
                </div>
            <?php endif; ?>

            <div class="w-full rounded-[18px] border border-[#dddddd] bg-transparent min-h-[540px] px-6 py-6">

                <form method="GET" class="space-y-5">
                    <div class="flex flex-col gap-4">

                        <div class="flex items-center gap-4 flex-wrap">
                            <label class="w-[120px] text-[16px] font-medium text-[#3a3a3a] leading-[1.2]">
                                Search Associate by:
                            </label>

                            <div class="relative">
                                <select
                                    name="search_by"
                                    class="w-[140px] h-[30px] rounded-[8px] border border-[#d4d7dc] bg-[#e8e9eb] px-3 pr-9 text-[13px] text-[#5c6570] outline-none appearance-none"
                                >
                                    <option value=""></option>
                                    <option value="associate_id" <?php if ($searchBy === 'associate_id') echo 'selected'; ?>>Login ID</option>
                                    <option value="full_name" <?php if ($searchBy === 'full_name') echo 'selected'; ?>>Name</option>
                                    <option value="mobile_no" <?php if ($searchBy === 'mobile_no') echo 'selected'; ?>>Mobile</option>
                                </select>
                                <i data-lucide="chevron-down" class="w-4 h-4 text-[#8a8a8a] absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none"></i>
                            </div>
                        </div>

                        <div class="flex items-center gap-4 flex-wrap">
                            <label class="w-[120px] text-[16px] font-medium text-[#3a3a3a]">
                                Search Value:
                            </label>

                            <input
                                type="text"
                                name="search_value"
                                value="<?php echo htmlspecialchars($searchValue); ?>"
                                class="w-[145px] h-[30px] rounded-[8px] border border-[#d4d7dc] bg-[#e8e9eb] px-3 text-[13px] text-[#5c6570] outline-none"
                            >
                        </div>

                        <div class="flex items-center gap-3 flex-wrap">
                            <label class="text-[16px] font-medium text-[#3a3a3a]">
                                Search by joining Date:
                            </label>

                            <span class="text-[16px] font-medium text-[#9a9a9a]">From:</span>
                            <input
                                type="date"
                                name="from_date"
                                value="<?php echo htmlspecialchars($fromDate); ?>"
                                class="w-[150px] h-[30px] rounded-[8px] border border-[#d4d7dc] bg-[#e8e9eb] px-3 text-[14px]  font-medium text-[#5c6570] outline-none"
                                <?php echo !$dateFilterAvailable ? 'disabled' : ''; ?>
                            >

                            <span class="text-[16px] font-medium text-[#9a9a9a]">To:</span>
                            <input
                                type="date"
                                name="to_date"
                                value="<?php echo htmlspecialchars($toDate); ?>"
                                class="w-[150px] h-[30px] rounded-[8px] border border-[#d4d7dc] bg-[#e8e9eb] px-3 text-[14px]  font-medium text-[#5c6570] outline-none"
                                <?php echo !$dateFilterAvailable ? 'disabled' : ''; ?>
                            >

                            <button
                                type="submit"
                                class="h-[30px] px-5 rounded-[8px] bg-[#1b3c67] text-white text-[13px] font-medium flex items-center gap-2 hover:bg-[#163353]"
                            >
                                <i data-lucide="calendar-days" class="w-4 h-4"></i>
                                <span>Search By Date</span>
                            </button>
                        </div>
                    </div>
                </form>

                <div class="mt-6 w-full overflow-x-auto">
                    <table class="w-full min-w-[1100px] border-separate border-spacing-0">
                        <thead>
                            <tr class="bg-[#cb9a41] text-white font-medium text-[15px]">
                                <th class="h-[32px] px-4 text-left font-medium rounded-l-[8px] whitespace-nowrap">Date</th>
                                <th class="h-[32px] px-4 text-left font-medium whitespace-nowrap">Login ID</th>
                                <th class="h-[32px] px-4 text-left font-medium whitespace-nowrap">Name</th>
                                <th class="h-[32px] px-4 text-left font-medium whitespace-nowrap">PAN No.</th>
                                <th class="h-[32px] px-4 text-left font-medium whitespace-nowrap">Mobile No.</th>
                                <th class="h-[32px] px-4 text-left font-medium whitespace-nowrap">Wallet Balance</th>
                                <th class="h-[32px] px-4 text-left font-medium rounded-r-[8px] whitespace-nowrap">View Wallet Status</th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php if (!empty($walletRows)): ?>
                                <?php foreach ($walletRows as $row): ?>
                                    <tr class="bg-white">
                                        <td class="px-4 py-3 text-[15px] text-[#555555] border-b border-[#ececec] whitespace-nowrap">
                                            <?php echo $row['date'] !== '' ? htmlspecialchars($row['date']) : '-'; ?>
                                        </td>
                                        <td class="px-4 py-3 text-[15px] text-[#555555] border-b border-[#ececec] whitespace-nowrap">
                                            <?php echo $row['login_id'] !== '' ? htmlspecialchars($row['login_id']) : '-'; ?>
                                        </td>
                                        <td class="px-4 py-3 text-[15px] text-[#555555] border-b border-[#ececec] whitespace-nowrap">
                                            <?php echo $row['name'] !== '' ? htmlspecialchars($row['name']) : '-'; ?>
                                        </td>
                                        <td class="px-4 py-3 text-[15px] text-[#555555] border-b border-[#ececec] whitespace-nowrap">
                                            <?php echo $row['pan_no'] !== '' ? htmlspecialchars($row['pan_no']) : '-'; ?>
                                        </td>
                                        <td class="px-4 py-3 text-[15px] text-[#555555] border-b border-[#ececec] whitespace-nowrap">
                                            <?php echo $row['mobile_no'] !== '' ? htmlspecialchars($row['mobile_no']) : '-'; ?>
                                        </td>
                                        <td class="px-4 py-3 text-[15px] text-[#555555] border-b border-[#ececec] whitespace-nowrap">
                                            ₹<?php echo number_format((float)$row['wallet_balance'], 2); ?>
                                        </td>
                                        <td class="px-4 py-3 text-[15px] text-[#555555] border-b border-[#ececec] whitespace-nowrap">
                                            <?php if ($row['login_id'] !== ''): ?>
                                                <a
                                                    href="wallet-status.php?associate_id=<?php echo urlencode($row['login_id']); ?>"
                                                    class="inline-flex items-center justify-center h-[28px] px-4 rounded-[6px] bg-[#1b3c67] text-white text-[12px] hover:bg-[#163353]"
                                                >
                                                    View
                                                </a>
                                            <?php else: ?>
                                                <span class="inline-flex items-center justify-center h-[28px] px-4 rounded-[6px] bg-gray-300 text-white text-[12px] cursor-not-allowed">
                                                    View
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="px-4 py-10 text-center text-[14px] text-[#777] bg-white border-b border-[#ececec]">
                                        No member wallet data found.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

            </div>
        </main>
    </div>

    <script>
        lucide.createIcons();
    </script>
</body>
</html> 