<?php
session_start();

$memberName   = $_SESSION['full_name'] ?? 'Swapnil Solanki';
$customerId   = $_SESSION['member_id'] ?? 'FC7981495';
$managerName  = $_SESSION['full_name'] ?? 'Swapnil Solanki';
$managerRole  = 'MLM Manager';
$profileImage = 'https://i.pravatar.cc/80?img=12';

$searchBy    = $_GET['search_by'] ?? '';
$searchValue = $_GET['search_value'] ?? '';
$fromDate    = $_GET['from_date'] ?? '';
$toDate      = $_GET['to_date'] ?? '';
$filter      = $_GET['filter'] ?? 'All';

$rows = [];

include __DIR__ . '/config/db.php';

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

function fetchMembersForRequests(mysqli $conn, string $tableName, string $searchBy, string $searchValue, string $fromDate, string $toDate): array {
    $columns = getTableColumns($conn, $tableName);
    $loginColumn = firstExistingColumn($columns, ['customer_id', 'member_id', 'associate_id', 'login_id']);
    $nameColumn = firstExistingColumn($columns, ['full_name', 'name']);
    $mobileColumn = firstExistingColumn($columns, ['mobile', 'mobile_no', 'phone']);
    $createdColumn = firstExistingColumn($columns, ['created_at', 'created_on', 'date_created']);

    if (!$loginColumn || !$nameColumn) {
        return [];
    }

    $sql = "SELECT {$loginColumn} AS associate_id, {$nameColumn} AS name";
    $sql .= $mobileColumn ? ", {$mobileColumn} AS mobile" : ", '' AS mobile";
    $sql .= $createdColumn ? ", {$createdColumn} AS created_at" : ", '' AS created_at";
    $sql .= " FROM `{$tableName}` WHERE 1=1";

    $params = [];
    $types = '';
    $searchMap = [
        'associate_id' => $loginColumn,
        'name' => $nameColumn,
        'mobile' => $mobileColumn,
    ];

    if ($searchBy !== '' && $searchValue !== '' && isset($searchMap[$searchBy]) && $searchMap[$searchBy] !== null) {
        $sql .= " AND {$searchMap[$searchBy]} LIKE ?";
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

    $sql .= $createdColumn !== '' ? " ORDER BY {$createdColumn} DESC, {$loginColumn} DESC" : " ORDER BY {$loginColumn} DESC";

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

$rows = [];

$memberRows = [];
foreach (['signup_users'] as $tableName) {
    if (!tableExists($conn, $tableName)) {
        continue;
    }

    foreach (fetchMembersForRequests($conn, $tableName, $searchBy, $searchValue, $fromDate, $toDate) as $memberRow) {
        $memberRows[] = $memberRow;
    }
}

$requestMap = [];
if (tableExists($conn, 'wallet_requests')) {
    $requestResult = $conn->query('SELECT * FROM wallet_requests ORDER BY id DESC');
    if ($requestResult instanceof mysqli_result) {
        while ($requestRow = $requestResult->fetch_assoc()) {
            $associateId = trim((string)($requestRow['associate_id'] ?? ''));
            if ($associateId !== '' && !isset($requestMap[$associateId])) {
                $requestMap[$associateId] = $requestRow;
            }
        }
    }
}

foreach ($memberRows as $memberRow) {
    $associateId = (string)($memberRow['associate_id'] ?? '');
    $requestRow = $associateId !== '' && isset($requestMap[$associateId]) ? $requestMap[$associateId] : [];

    if (in_array($filter, ['Pending', 'Approved', 'Rejected'], true)) {
        $status = (string)($requestRow['status'] ?? '');
        if ($status !== $filter) {
            continue;
        }
    }

    $rows[] = [
        'request_no'     => $requestRow['request_no'] ?? '',
        'request_date'   => $requestRow['request_date'] ?? ($memberRow['created_at'] ?? ''),
        'associate_id'   => $associateId,
        'name'           => $requestRow['name'] ?? ($memberRow['name'] ?? ''),
        'mobile'         => $requestRow['mobile'] ?? ($memberRow['mobile'] ?? ''),
        'bank_name'      => $requestRow['bank_name'] ?? '',
        'payment_mode'   => $requestRow['payment_mode'] ?? '',
        'deposit_date'   => $requestRow['deposit_date'] ?? '',
        'amount'         => $requestRow['amount'] ?? '',
        'transaction_no' => $requestRow['transaction_no'] ?? '',
        'receipt'        => $requestRow['receipt'] ?? '',
        'status'         => $requestRow['status'] ?? '',
        'transfer'       => $requestRow['transfer'] ?? '',
        'cancel'         => $requestRow['cancel'] ?? '',
    ];
}

usort($rows, static function (array $left, array $right): int {
    return strcmp((string)($right['request_date'] ?? ''), (string)($left['request_date'] ?? ''));
});
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Wallet Request</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body class="m-0 bg-[#f3f4f6] font-sans overflow-x-hidden">

    <?php include __DIR__ . '/includes/sidebar.php'; ?>

    <div class="ml-64 min-h-screen w-[calc(100%-16rem)]">
        <?php include __DIR__ . '/includes/navbar.php'; ?>

        <main class="px-5 py-4">

            <!-- Section Heading -->
            <div class="flex items-center gap-4 mb-4">
                <div class="w-9 h-9 rounded-[8px] bg-[#f6f2ea] flex items-center justify-center shrink-0">
                    <i data-lucide="badge-dollar-sign" class="w-4 h-4 text-[#c9983b]"></i>
                </div>

                <h2 class="text-[17px] md:text-[18px] leading-none text-[#2f2f2f] font-normal whitespace-nowrap"
                    style="font-family: Georgia, 'Times New Roman', serif;">
                    WALLET REQUEST
                </h2>

                <div class="h-[1px] bg-[#c9983b] flex-1"></div>
            </div>

            <!-- Main Box -->
            <div class="relative bg-white border border-[#dfdfdf] rounded-[12px] min-h-[310px] px-3 py-4">

                <!-- Search Area -->
                <form method="GET" class="space-y-3">
                    <div class="flex flex-wrap items-center gap-x-4 gap-y-3 text-[16px] font-medium text-[#3a3a3a]">

                        <div class="flex items-center gap-2">
                            <label class="whitespace-nowrap">Search Associate by :</label>
                            <select
                                name="search_by"
                                class="w-[160px] h-[26px] rounded-[6px] border border-[#d8dce2] bg-[#f2f4f7] px-2 text-[15px] text-[#555] outline-none"
                            >
                                <option value="">Select</option>
                                <option value="associate_id" <?php if ($searchBy === 'associate_id') echo 'selected'; ?>>Associate ID</option>
                                <option value="name" <?php if ($searchBy === 'name') echo 'selected'; ?>>Name</option>
                                <option value="mobile" <?php if ($searchBy === 'mobile') echo 'selected'; ?>>Mobile No.</option>
                            </select>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center gap-x-4 gap-y-3 text-[16px] font-medium text-[#3a3a3a]">
                        <div class="flex items-center gap-2">
                            <label class="whitespace-nowrap">Search Value:</label>
                            <input
                                type="text"
                                name="search_value"
                                value="<?php echo htmlspecialchars($searchValue); ?>"
                                class="w-[100px] h-[24px] rounded-[4px] border border-[#d8dce2] bg-[#f2f4f7] px-2 text-[11px] outline-none"
                            >
                        </div>

                        <button
                            type="submit"
                            class="h-[24px] min-w-[62px] rounded-[5px] bg-[#143a66] text-white text-[14px] px-3 flex items-center justify-center gap-1"
                        >
                            <i data-lucide="search" class="w-3 h-3"></i>
                            <span>Search</span>
                        </button>
                    </div>

                    <div class="flex flex-wrap items-center justify-between gap-y-3">
                        <div class="flex flex-wrap items-center gap-x-4 gap-y-3 text-[16px] font-medium text-[#3a3a3a]">
                            <div class="flex items-center gap-2">
                                <label class="whitespace-nowrap">Search by Joining Date:</label>

                                <span class="text-[#888]">From:</span>
                                <div class="relative">
                                    <input
                                        type="date"
                                        name="from_date"
                                        value="<?php echo htmlspecialchars($fromDate); ?>"
                                        class="w-[150px] h-[24px] rounded-[4px] border border-[#d8dce2] bg-[#f2f4f7] px-2 pr-7 text-[14px] outline-none"
                                    >
                                </div>

                                <span class="text-[#888]">To:</span>
                                <div class="relative">
                                    <input
                                        type="date"
                                        name="to_date"
                                        value="<?php echo htmlspecialchars($toDate); ?>"
                                        class="w-[150px] h-[24px] rounded-[4px] border border-[#d8dce2] bg-[#f2f4f7] px-2 pr-7 text-[14px] outline-none"
                                    >
                                </div>
                            </div>

                            <button
                                type="submit"
                                class="h-[24px] min-w-[86px] rounded-[5px] bg-[#143a66] text-white text-[14px] px-3 flex items-center justify-center gap-1"
                            >
                                <i data-lucide="calendar-days" class="w-3 h-3"></i>
                                <span>Search By Date</span>
                            </button>
                        </div>

                        <div class="flex items-center gap-2 text-[16px] text-[#3a3a3a] font-medium">
                            <label>Filter:</label>
                            <select
                                name="filter"
                                class="w-[72px] h-[24px] rounded-[4px] border border-[#d8dce2] bg-[#f2f4f7] px-2 text-[14px] text-[#777] outline-none"
                            >
                                <option <?php if ($filter === 'All') echo 'selected'; ?>>All</option>
                                <option <?php if ($filter === 'Pending') echo 'selected'; ?>>Pending</option>
                                <option <?php if ($filter === 'Approved') echo 'selected'; ?>>Approved</option>
                                <option <?php if ($filter === 'Rejected') echo 'selected'; ?>>Rejected</option>
                            </select>
                        </div>
                    </div>
                </form>

                <!-- Table -->
                <div class="mt-3 rounded-[6px] overflow-hidden border border-[#d6a246]">
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[1150px] border-collapse">
                            <thead>
                                <tr class="bg-[#cd9737] text-white font-medium text-[15px] h-[28px]">
                                    <th class="px-2 font-medium whitespace-nowrap text-left">Request No.</th>
                                    <th class="px-2 font-medium whitespace-nowrap text-left">Request Date</th>
                                    <th class="px-2 font-medium whitespace-nowrap text-left">Associate Id</th>
                                    <th class="px-2 font-medium whitespace-nowrap text-left">Name</th>
                                    <th class="px-2 font-medium whitespace-nowrap text-left">Mobile No.</th>
                                    <th class="px-2 font-medium whitespace-nowrap text-left">Bank Name</th>
                                    <th class="px-2 font-medium whitespace-nowrap text-left">Payment Mode</th>
                                    <th class="px-2 font-medium whitespace-nowrap text-left">Deposit Date</th>
                                    <th class="px-2 font-medium whitespace-nowrap text-left">Amount</th>
                                    <th class="px-2 font-medium whitespace-nowrap text-left">Transaction No.</th>
                                    <th class="px-2 font-medium whitespace-nowrap text-left">Receipt</th>
                                    <th class="px-2 font-medium whitespace-nowrap text-left">Status</th>
                                    <th class="px-2 font-medium whitespace-nowrap text-left">Transfer</th>
                                    <th class="px-2 font-medium whitespace-nowrap text-left">Cancel</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($rows)): ?>
                                    <?php foreach ($rows as $row): ?>
                                        <tr class="h-[34px] text-[15px] text-[#2d2d2d] border-t border-[#ececec]">
                                            <td class="px-2 whitespace-nowrap"><?php echo htmlspecialchars($row['request_no'] !== '' ? $row['request_no'] : '-'); ?></td>
                                            <td class="px-2 whitespace-nowrap"><?php echo htmlspecialchars($row['request_date'] !== '' ? $row['request_date'] : '-'); ?></td>
                                            <td class="px-2 whitespace-nowrap"><?php echo htmlspecialchars($row['associate_id'] !== '' ? $row['associate_id'] : '-'); ?></td>
                                            <td class="px-2 whitespace-nowrap"><?php echo htmlspecialchars($row['name'] !== '' ? $row['name'] : '-'); ?></td>
                                            <td class="px-2 whitespace-nowrap"><?php echo htmlspecialchars($row['mobile'] !== '' ? $row['mobile'] : '-'); ?></td>
                                            <td class="px-2 whitespace-nowrap"><?php echo htmlspecialchars($row['bank_name'] !== '' ? $row['bank_name'] : '-'); ?></td>
                                            <td class="px-2 whitespace-nowrap"><?php echo htmlspecialchars($row['payment_mode'] !== '' ? $row['payment_mode'] : '-'); ?></td>
                                            <td class="px-2 whitespace-nowrap"><?php echo htmlspecialchars($row['deposit_date'] !== '' ? $row['deposit_date'] : '-'); ?></td>
                                            <td class="px-2 whitespace-nowrap"><?php echo htmlspecialchars($row['amount'] !== '' ? $row['amount'] : '-'); ?></td>
                                            <td class="px-2 whitespace-nowrap"><?php echo htmlspecialchars($row['transaction_no'] !== '' ? $row['transaction_no'] : '-'); ?></td>
                                            <td class="px-2 whitespace-nowrap"><?php echo htmlspecialchars($row['receipt'] !== '' ? $row['receipt'] : '-'); ?></td>
                                            <td class="px-2 whitespace-nowrap"><?php echo htmlspecialchars($row['status'] !== '' ? $row['status'] : '-'); ?></td>
                                            <td class="px-2 whitespace-nowrap"><?php echo htmlspecialchars($row['transfer'] !== '' ? $row['transfer'] : '-'); ?></td>
                                            <td class="px-2 whitespace-nowrap"><?php echo htmlspecialchars($row['cancel'] !== '' ? $row['cancel'] : '-'); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="14" class="h-[320px] bg-white"></td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Floating Notification -->
                <div class="fixed right-5 top-[185px] z-50">
                    <div class="relative">
                        <!-- <div class="w-[54px] h-[54px] rounded-full bg-[#2b2b2b] shadow-[0_8px_24px_rgba(0,0,0,0.35)] p-[4px]">
                            <img
                                src="https://i.pravatar.cc/120?img=15"
                                alt="User"
                                class="w-full h-full rounded-full object-cover border-[3px] border-white"
                            >
                        </div> -->

                        <!-- <div class="absolute right-[58px] top-[-16px] w-[300px] bg-[#2d2d2d] text-white rounded-[24px] px-5 py-4 shadow-[0_10px_30px_rgba(0,0,0,0.35)]">
                            <div class="flex items-start gap-3">
                                <img
                                    src="https://i.pravatar.cc/80?img=15"
                                    alt="User"
                                    class="w-10 h-10 rounded-full object-cover shrink-0"
                                >
                                <div class="text-[12px] leading-[1.35]">
                                    <p class="font-semibold text-[13px]">
                                        Swapnil Solanki <span class="font-normal text-[#c4c4c4]">7 days ago</span>
                                    </p>
                                    <p class="mt-1 text-[11px]">
                                        add three option 1. pending 2. approved 3. rejected
                                    </p>
                                </div>
                            </div>

                            <div class="absolute right-[-8px] top-[28px] w-4 h-4 bg-[#2d2d2d] rotate-45"></div>
                        </div> -->
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