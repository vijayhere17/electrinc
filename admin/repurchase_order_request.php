<?php
session_start();

$memberName   = $_SESSION['full_name'] ?? 'Swapnil Solanki';
$customerId   = $_SESSION['member_id'] ?? 'FC7981495';
$managerName  = $_SESSION['full_name'] ?? 'Swapnil Solanki';
$managerRole  = 'MLM Manager';
$profileImage = 'https://i.pravatar.cc/80?img=12';

$searchBy    = trim($_REQUEST['search_by'] ?? '');
$searchValue = trim($_REQUEST['search_value'] ?? '');
$fromDate    = trim($_REQUEST['from_date'] ?? '');
$toDate      = trim($_REQUEST['to_date'] ?? '');
$filter      = trim($_REQUEST['filter'] ?? 'All');

$rows = [];
$errorMessage = '';
$successMessage = '';

include __DIR__ . '/config/db.php';

if (!isset($conn) || !($conn instanceof mysqli)) {
    die("Database connection failed. Please check config/db.php");
}

$conn->set_charset("utf8mb4");

$tableName = 'repurchase_orders';
$tableCheck = $conn->query("SHOW TABLES LIKE '{$tableName}'");

if (!$tableCheck || $tableCheck->num_rows === 0) {
    $errorMessage = "Table '{$tableName}' not found in database.";
}

$existingColumns = [];
$signupColumns = [];

if ($errorMessage === '') {
    $colRes = $conn->query("SHOW COLUMNS FROM {$tableName}");
    if ($colRes) {
        while ($col = $colRes->fetch_assoc()) {
            $existingColumns[] = $col['Field'];
        }
    } else {
        $errorMessage = 'Unable to read repurchase_orders table structure.';
    }
}

if ($errorMessage === '') {
    $signupCheck = $conn->query("SHOW TABLES LIKE 'signup_users'");
    if (!$signupCheck || $signupCheck->num_rows === 0) {
        $errorMessage = "Table 'signup_users' not found in database.";
    } else {
        $signupColRes = $conn->query("SHOW COLUMNS FROM signup_users");
        if ($signupColRes) {
            while ($col = $signupColRes->fetch_assoc()) {
                $signupColumns[] = $col['Field'];
            }
        } else {
            $errorMessage = 'Unable to read signup_users table structure.';
        }
    }
}

function firstExistingColumn(array $columns, array $candidates): string
{
    foreach ($candidates as $candidate) {
        if (in_array($candidate, $columns, true)) {
            return $candidate;
        }
    }
    return '';
}

function formatDateValue($value): string
{
    if (!$value || $value === '0000-00-00' || $value === '0000-00-00 00:00:00') {
        return '-';
    }

    $time = strtotime($value);
    if ($time) {
        return date('d-m-Y', $time);
    }

    return (string)$value;
}

function buildReceiptViewLink(int $rowId, string $receipt): string
{
    if ($rowId <= 0 || trim($receipt) === '') {
        return '';
    }

    return 'view_repurchase_receipt.php?row_id=' . $rowId;
}

if ($errorMessage === '') {
    $idColumn             = firstExistingColumn($existingColumns, ['id']);
    $requestNoColumn      = firstExistingColumn($existingColumns, ['order_no', 'request_no', 'request_number', 'repurchase_no', 'id']);
    $requestDateColumn    = firstExistingColumn($existingColumns, ['order_date', 'request_date', 'created_at', 'date', 'deposit_date']);
    $customerIdColumn     = firstExistingColumn($existingColumns, ['customer_id', 'associate_id', 'member_id', 'login_id']);
    $paymentModeColumn    = firstExistingColumn($existingColumns, ['payment_mode', 'payment_type', 'mode']);
    $amountColumn         = firstExistingColumn($existingColumns, ['amount', 'total_amount', 'price']);
    $transactionNoColumn  = firstExistingColumn($existingColumns, ['transaction_number', 'transaction_no', 'transaction_id', 'txn_no', 'utr_no']);
    $receiptColumn        = firstExistingColumn($existingColumns, ['payment_slip', 'receipt', 'receipt_file', 'slip', 'payment_receipt']);
    $statusColumn         = firstExistingColumn($existingColumns, ['status']);
    $transferColumn       = firstExistingColumn($existingColumns, ['transfer', 'is_transferred']);
    $cancelColumn         = firstExistingColumn($existingColumns, ['cancel', 'is_cancelled']);

    $signupCustomerIdColumn = firstExistingColumn($signupColumns, ['customer_id', 'associate_id', 'member_id', 'login_id']);
    $signupNameColumn       = firstExistingColumn($signupColumns, ['full_name', 'name', 'customer_name']);
    $signupMobileColumn     = firstExistingColumn($signupColumns, ['mobile_no', 'mobile', 'phone', 'mobile_number']);

    if ($idColumn === '') {
        $errorMessage = 'id column not found in repurchase_orders table.';
    } elseif ($customerIdColumn === '') {
        $errorMessage = 'customer_id/associate_id/member_id/login_id column not found in repurchase_orders table.';
    } elseif ($signupCustomerIdColumn === '') {
        $errorMessage = 'customer_id/associate_id/member_id/login_id column not found in signup_users table.';
    }

    if ($errorMessage === '' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
        $rowId = (int)($_POST['row_id'] ?? 0);
        $newStatus = trim($_POST['new_status'] ?? '');

        $allowedStatuses = ['Pending', 'Approved', 'Rejected'];

        if ($rowId <= 0) {
            $errorMessage = 'Invalid request id.';
        } elseif ($statusColumn === '') {
            $errorMessage = 'Status column not found in database.';
        } elseif (!in_array($newStatus, $allowedStatuses, true)) {
            $errorMessage = 'Invalid status selected.';
        } else {
            $updateSql = "UPDATE {$tableName} SET {$statusColumn} = ? WHERE {$idColumn} = ? LIMIT 1";
            $updateStmt = $conn->prepare($updateSql);

            if ($updateStmt) {
                $updateStmt->bind_param('si', $newStatus, $rowId);

                if ($updateStmt->execute()) {
                    $successMessage = "Status updated successfully to {$newStatus}.";
                } else {
                    $errorMessage = 'Failed to update status.';
                }

                $updateStmt->close();
            } else {
                $errorMessage = 'Failed to prepare status update query.';
            }
        }
    }

    $nameSelect = $signupNameColumn !== ''
        ? "COALESCE(su.{$signupNameColumn}, '') AS name"
        : "'' AS name";

    $mobileSelect = $signupMobileColumn !== ''
        ? "COALESCE(su.{$signupMobileColumn}, '') AS mobile"
        : "'' AS mobile";

    if ($errorMessage === '') {
        $selectParts = [];
        $selectParts[] = 'ro.' . $idColumn . ' AS row_id';
        $selectParts[] = 'ro.' . ($requestNoColumn !== '' ? $requestNoColumn : $idColumn) . ' AS request_no';
        $selectParts[] = 'ro.' . ($requestDateColumn !== '' ? $requestDateColumn : $idColumn) . ' AS request_date';
        $selectParts[] = 'ro.' . $customerIdColumn . ' AS associate_id';
        $selectParts[] = $nameSelect;
        $selectParts[] = $mobileSelect;
        $selectParts[] = "'' AS bank_name";
        $selectParts[] = ($paymentModeColumn !== '' ? 'ro.' . $paymentModeColumn : "''") . ' AS payment_mode';
        $selectParts[] = "'' AS deposit_date";
        $selectParts[] = ($amountColumn !== '' ? 'ro.' . $amountColumn : '0') . ' AS amount';
        $selectParts[] = ($transactionNoColumn !== '' ? 'ro.' . $transactionNoColumn : "''") . ' AS transaction_no';
        $selectParts[] = ($receiptColumn !== '' ? 'ro.' . $receiptColumn : "''") . ' AS receipt';
        $selectParts[] = ($statusColumn !== '' ? 'ro.' . $statusColumn : "'Pending'") . ' AS status';
        $selectParts[] = ($transferColumn !== '' ? 'ro.' . $transferColumn : "''") . ' AS transfer';
        $selectParts[] = ($cancelColumn !== '' ? 'ro.' . $cancelColumn : "''") . ' AS cancel_col';

        $sql = "
            SELECT
                " . implode(",\n                ", $selectParts) . "
            FROM {$tableName} ro
            LEFT JOIN signup_users su ON ro.{$customerIdColumn} = su.{$signupCustomerIdColumn}
            WHERE 1=1
        ";

        $params = [];
        $types = '';

        $searchMap = [];
        $searchMap['associate_id'] = 'ro.' . $customerIdColumn;

        if ($signupNameColumn !== '') {
            $searchMap['name'] = 'su.' . $signupNameColumn;
        }

        if ($signupMobileColumn !== '') {
            $searchMap['mobile'] = 'su.' . $signupMobileColumn;
        }

        if ($searchBy !== '' && isset($searchMap[$searchBy]) && $searchValue !== '') {
            $sql .= " AND {$searchMap[$searchBy]} LIKE ?";
            $types .= 's';
            $params[] = '%' . $searchValue . '%';
        }

        if ($requestDateColumn !== '') {
            if ($fromDate !== '') {
                $sql .= " AND DATE(ro.{$requestDateColumn}) >= ?";
                $types .= 's';
                $params[] = $fromDate;
            }

            if ($toDate !== '') {
                $sql .= " AND DATE(ro.{$requestDateColumn}) <= ?";
                $types .= 's';
                $params[] = $toDate;
            }
        }

        if ($statusColumn !== '' && in_array($filter, ['Pending', 'Approved', 'Rejected'], true)) {
            $sql .= " AND LOWER(ro.{$statusColumn}) = LOWER(?)";
            $types .= 's';
            $params[] = $filter;
        }

        $orderByColumn = firstExistingColumn($existingColumns, ['id', 'order_date', 'created_at', 'request_date']);
        if ($orderByColumn !== '') {
            $sql .= " ORDER BY ro.{$orderByColumn} DESC";
        }

        $stmt = $conn->prepare($sql);

        if ($stmt) {
            if (!empty($params)) {
                $stmt->bind_param($types, ...$params);
            }

            if ($stmt->execute()) {
                $result = $stmt->get_result();
                while ($row = $result->fetch_assoc()) {
                    $rows[] = $row;
                }
            } else {
                $errorMessage = 'Unable to load repurchase order request data.';
            }

            $stmt->close();
        } else {
            $errorMessage = 'Database query preparation failed.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Repurchase Order Request</title>
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
                    <i data-lucide="badge-dollar-sign" class="w-4 h-4 text-[#c9983b]"></i>
                </div>

                <h2 class="text-[17px] md:text-[18px] leading-none text-[#2f2f2f] font-normal whitespace-nowrap"
                    style="font-family: Georgia, 'Times New Roman', serif;">
                    REPURCHASE ORDER REQUEST
                </h2>

                <div class="h-[1px] bg-[#c9983b] flex-1"></div>
            </div>

            <?php if ($errorMessage !== ''): ?>
                <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                    <?php echo htmlspecialchars($errorMessage); ?>
                </div>
            <?php endif; ?>

            <?php if ($successMessage !== ''): ?>
                <div class="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">
                    <?php echo htmlspecialchars($successMessage); ?>
                </div>
            <?php endif; ?>

            <div class="relative bg-white border border-[#dfdfdf] rounded-[12px] min-h-[310px] px-3 py-4">

                <form method="GET" class="space-y-3">
                    <div class="flex flex-wrap items-center gap-x-4 gap-y-3 text-[16px] font-medium text-[#222]">

                        <div class="flex items-center gap-2">
                            <label class="whitespace-nowrap">Search Associate by:</label>
                            <select
                                name="search_by"
                                class="w-[145px] h-[26px] rounded-[6px] border border-[#d8dce2] bg-[#f2f4f7] px-2 text-[16px] text-[#555] outline-none"
                            >
                                <option value="">Select</option>
                                <option value="associate_id" <?php if ($searchBy === 'associate_id') echo 'selected'; ?>>Associate ID</option>
                                <option value="name" <?php if ($searchBy === 'name') echo 'selected'; ?>>Name</option>
                                <option value="mobile" <?php if ($searchBy === 'mobile') echo 'selected'; ?>>Mobile No.</option>
                            </select>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center gap-x-4 gap-y-3 text-[16px] font-medium text-[#222]">
                        <div class="flex items-center gap-2">
                            <label class="whitespace-nowrap">Search Value:</label>
                            <input
                                type="text"
                                name="search_value"
                                value="<?php echo htmlspecialchars($searchValue); ?>"
                                class="w-[100px] h-[24px] rounded-[4px] border border-[#d8dce2] bg-[#f2f4f7] px-2 text-[16px] outline-none"
                            >
                        </div>

                        <button
                            type="submit"
                            class="h-[24px] min-w-[62px] rounded-[5px] bg-[#143a66] text-white text-[15px] px-3 flex items-center justify-center gap-1"
                        >
                            <i data-lucide="search" class="w-3 h-3"></i>
                            <span>Search</span>
                        </button>
                    </div>

                    <div class="flex flex-wrap items-center justify-between gap-y-3">
                        <div class="flex flex-wrap items-center gap-x-4 gap-y-3 text-[16px] font-medium text-[#222]">
                            <div class="flex items-center gap-2">
                                <label class="whitespace-nowrap">Search by Joining Date:</label>

                                <span class="text-[#888]">From:</span>
                                <div class="relative">
                                    <input
                                        type="date"
                                        name="from_date"
                                        value="<?php echo htmlspecialchars($fromDate); ?>"
                                        class="w-[150px] h-[24px] rounded-[4px] border border-[#d8dce2] bg-[#f2f4f7] px-2 pr-7 text-[15px] outline-none"
                                    >
                                </div>

                                <span class="text-[#888]">To:</span>
                                <div class="relative">
                                    <input
                                        type="date"
                                        name="to_date"
                                        value="<?php echo htmlspecialchars($toDate); ?>"
                                        class="w-[150px] h-[24px] rounded-[4px] border border-[#d8dce2] bg-[#f2f4f7] px-2 pr-7 text-[15px] outline-none"
                                    >
                                </div>
                            </div>

                            <button
                                type="submit"
                                class="h-[24px] min-w-[86px] rounded-[5px] bg-[#143a66] text-white text-[15px] px-3 flex items-center justify-center gap-1"
                            >
                                <i data-lucide="calendar-days" class="w-3 h-3"></i>
                                <span>Search By Date</span>
                            </button>
                        </div>

                        <div class="flex items-center gap-2 text-[16px] font-medium text-[#888]">
                            <label>Filter:</label>
                            <select
                                name="filter"
                                onchange="this.form.submit()"
                                class="w-[72px] h-[24px] rounded-[4px] border border-[#d8dce2] bg-[#f2f4f7] px-2 text-[15px] text-[#777] outline-none"
                            >
                                <option value="All" <?php if ($filter === 'All') echo 'selected'; ?>>All</option>
                                <option value="Pending" <?php if ($filter === 'Pending') echo 'selected'; ?>>Pending</option>
                                <option value="Approved" <?php if ($filter === 'Approved') echo 'selected'; ?>>Approved</option>
                                <option value="Rejected" <?php if ($filter === 'Rejected') echo 'selected'; ?>>Rejected</option>
                            </select>
                        </div>
                    </div>
                </form>

                <div class="mt-3 rounded-[6px] overflow-hidden border border-[#d6a246]">
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[1350px] border-collapse">
                            <thead>
                                <tr class="bg-[#cd9737] text-white text-[15px] h-[28px]">
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
                                    <th class="px-2 font-medium whitespace-nowrap text-left">Receipt View</th>
                                    <th class="px-2 font-medium whitespace-nowrap text-left">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($rows)): ?>
                                    <?php foreach ($rows as $row): ?>
                                        <?php
                                            $currentStatus = trim((string)($row['status'] ?? 'Pending'));
                                            $receiptViewLink = buildReceiptViewLink((int)($row['row_id'] ?? 0), (string)($row['receipt'] ?? ''));
                                            $statusClass = 'text-yellow-700';

                                            if (strcasecmp($currentStatus, 'Approved') === 0) {
                                                $statusClass = 'text-green-700';
                                            } elseif (strcasecmp($currentStatus, 'Rejected') === 0) {
                                                $statusClass = 'text-red-700';
                                            }
                                        ?>
                                        <tr class="h-[34px] text-[15px] text-[#2d2d2d] border-t border-[#ececec]">
                                            <td class="px-2 whitespace-nowrap"><?php echo htmlspecialchars($row['request_no'] ?: '-'); ?></td>
                                            <td class="px-2 whitespace-nowrap"><?php echo htmlspecialchars(formatDateValue($row['request_date'] ?? '')); ?></td>
                                            <td class="px-2 whitespace-nowrap"><?php echo htmlspecialchars($row['associate_id'] ?: '-'); ?></td>
                                            <td class="px-2 whitespace-nowrap"><?php echo htmlspecialchars($row['name'] ?: '-'); ?></td>
                                            <td class="px-2 whitespace-nowrap"><?php echo htmlspecialchars($row['mobile'] ?: '-'); ?></td>
                                            <td class="px-2 whitespace-nowrap"><?php echo htmlspecialchars($row['bank_name'] ?: '-'); ?></td>
                                            <td class="px-2 whitespace-nowrap"><?php echo htmlspecialchars($row['payment_mode'] ?: '-'); ?></td>
                                            <td class="px-2 whitespace-nowrap"><?php echo htmlspecialchars($row['deposit_date'] ?: '-'); ?></td>
                                            <td class="px-2 whitespace-nowrap"><?php echo htmlspecialchars($row['amount'] ?: '-'); ?></td>
                                            <td class="px-2 whitespace-nowrap"><?php echo htmlspecialchars($row['transaction_no'] ?: '-'); ?></td>
                                            <td class="px-2 whitespace-nowrap"><?php echo htmlspecialchars($row['receipt'] ?: '-'); ?></td>
                                            <td class="px-2 whitespace-nowrap font-semibold <?php echo $statusClass; ?>">
                                                <?php echo htmlspecialchars($currentStatus ?: '-'); ?>
                                            </td>
                                            <td class="px-2 whitespace-nowrap"><?php echo htmlspecialchars($row['transfer'] ?: '-'); ?></td>
                                            <td class="px-2 whitespace-nowrap">
                                                <?php if ($receiptViewLink !== ''): ?>
                                                    <a href="<?php echo htmlspecialchars($receiptViewLink); ?>" target="_blank" rel="noopener noreferrer" class="inline-flex items-center rounded bg-[#143a66] px-2 py-[2px] text-[12px] text-white">View</a>
                                                <?php else: ?>
                                                    -
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-2 whitespace-nowrap">
                                                <div class="flex items-center gap-1">
                                                    <form method="POST" class="m-0">
                                                        <input type="hidden" name="row_id" value="<?php echo (int)$row['row_id']; ?>">
                                                        <input type="hidden" name="new_status" value="Pending">
                                                        <input type="hidden" name="search_by" value="<?php echo htmlspecialchars($searchBy); ?>">
                                                        <input type="hidden" name="search_value" value="<?php echo htmlspecialchars($searchValue); ?>">
                                                        <input type="hidden" name="from_date" value="<?php echo htmlspecialchars($fromDate); ?>">
                                                        <input type="hidden" name="to_date" value="<?php echo htmlspecialchars($toDate); ?>">
                                                        <input type="hidden" name="filter" value="<?php echo htmlspecialchars($filter); ?>">

                                                        <button type="submit" name="update_status" class="px-2 py-[2px] rounded bg-yellow-500 text-white text-[12px]">
                                                            Pending
                                                        </button>
                                                    </form>

                                                    <form method="POST" class="m-0">
                                                        <input type="hidden" name="row_id" value="<?php echo (int)$row['row_id']; ?>">
                                                        <input type="hidden" name="new_status" value="Approved">
                                                        <input type="hidden" name="search_by" value="<?php echo htmlspecialchars($searchBy); ?>">
                                                        <input type="hidden" name="search_value" value="<?php echo htmlspecialchars($searchValue); ?>">
                                                        <input type="hidden" name="from_date" value="<?php echo htmlspecialchars($fromDate); ?>">
                                                        <input type="hidden" name="to_date" value="<?php echo htmlspecialchars($toDate); ?>">
                                                        <input type="hidden" name="filter" value="<?php echo htmlspecialchars($filter); ?>">

                                                        <button type="submit" name="update_status" class="px-2 py-[2px] rounded bg-green-600 text-white text-[12px]">
                                                            Approve
                                                        </button>
                                                    </form>

                                                    <form method="POST" class="m-0">
                                                        <input type="hidden" name="row_id" value="<?php echo (int)$row['row_id']; ?>">
                                                        <input type="hidden" name="new_status" value="Rejected">
                                                        <input type="hidden" name="search_by" value="<?php echo htmlspecialchars($searchBy); ?>">
                                                        <input type="hidden" name="search_value" value="<?php echo htmlspecialchars($searchValue); ?>">
                                                        <input type="hidden" name="from_date" value="<?php echo htmlspecialchars($fromDate); ?>">
                                                        <input type="hidden" name="to_date" value="<?php echo htmlspecialchars($toDate); ?>">
                                                        <input type="hidden" name="filter" value="<?php echo htmlspecialchars($filter); ?>">

                                                        <button type="submit" name="update_status" class="px-2 py-[2px] rounded bg-red-600 text-white text-[12px]">
                                                            Cancel
                                                        </button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="15" class="h-[320px] bg-white"></td>
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