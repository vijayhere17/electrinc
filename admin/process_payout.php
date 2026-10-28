<?php
session_start();

$memberName   = $_SESSION['full_name'] ?? 'Swapnil Solanki';
$customerId   = $_SESSION['member_id'] ?? 'MLM-00484';
$managerName  = $_SESSION['full_name'] ?? 'Swapnil Solanki';
$managerRole  = 'MLM Manager';
$profileImage = 'https://i.pravatar.cc/80?img=12';

$rows = [];
$successMessage = '';

include __DIR__ . '/config/db.php';

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
        if (in_array(strtolower($candidate), $columns, true)) {
            return $candidate;
        }
    }

    return null;
}

$requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($requestMethod === 'POST') {
    $successMessage = 'Payout process started successfully.';
}

$hasPayoutProcessing = tableExists($conn, 'payout_processing');
if ($hasPayoutProcessing) {
    $sql = 'SELECT sr_no, system_id, win_code, name, pan_no, aadhar_no, mobile_no, net_amount FROM payout_processing ORDER BY id DESC';
    $result = $conn->query($sql);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $rows[] = [
                'sr_no' => $row['sr_no'] ?? '',
                'system_id' => $row['system_id'] ?? ($row['win_code'] ?? ''),
                'name' => $row['name'] ?? '',
                'pan_no' => $row['pan_no'] ?? '',
                'aadhar_no' => $row['aadhar_no'] ?? '',
                'mobile_no' => $row['mobile_no'] ?? '',
                'net_amount' => $row['net_amount'] ?? '0',
            ];
        }
    }
}

if (empty($rows) && tableExists($conn, 'signup_users')) {
    $signupColumns = getTableColumns($conn, 'signup_users');
    $profileColumns = tableExists($conn, 'member_profiles') ? getTableColumns($conn, 'member_profiles') : [];
    $walletColumns = tableExists($conn, 'wallet_balance') ? getTableColumns($conn, 'wallet_balance') : [];

    $signupIdCol = firstExistingColumn($signupColumns, ['customer_id', 'member_id', 'associate_id', 'login_id']);
    $signupNameCol = firstExistingColumn($signupColumns, ['full_name', 'name']);
    $signupMobileCol = firstExistingColumn($signupColumns, ['mobile', 'mobile_no', 'phone']);

    if ($signupIdCol && $signupNameCol) {
        $profileJoinEnabled = !empty($profileColumns) && in_array('customer_id', $profileColumns, true);
        $profileNameCol = firstExistingColumn($profileColumns, ['full_name', 'name']);
        $profileMobileCol = firstExistingColumn($profileColumns, ['mobile_no', 'mobile', 'phone']);
        $profilePanCol = firstExistingColumn($profileColumns, ['pan_number', 'pan_no', 'pan']);
        $profileAadharCol = firstExistingColumn($profileColumns, ['aadhar_number', 'aadhar_no', 'aadhaar_number']);

        $walletJoinCol = null;
        if (!empty($walletColumns)) {
            if (in_array('customer_id', $walletColumns, true)) {
                $walletJoinCol = 'customer_id';
            } elseif (in_array('associate_id', $walletColumns, true)) {
                $walletJoinCol = 'associate_id';
            }
        }

        $sql = 'SELECT '
            . 'su.' . $signupIdCol . ' AS system_id, '
            . 'COALESCE('
            . ($signupNameCol ? 'su.' . $signupNameCol : "''")
            . ($profileJoinEnabled && $profileNameCol ? ', mp.' . $profileNameCol : '')
            . ') AS name, '
            . ($profileJoinEnabled && $profilePanCol ? 'mp.' . $profilePanCol : "''") . ' AS pan_no, '
            . ($profileJoinEnabled && $profileAadharCol ? 'mp.' . $profileAadharCol : "''") . ' AS aadhar_no, '
            . 'COALESCE('
            . ($signupMobileCol ? 'su.' . $signupMobileCol : "''")
            . ($profileJoinEnabled && $profileMobileCol ? ', mp.' . $profileMobileCol : '')
            . ') AS mobile_no, '
            . ($walletJoinCol ? 'COALESCE(wb.balance, 0)' : '0') . ' AS net_amount '
            . 'FROM signup_users su ';

        if ($profileJoinEnabled) {
            $sql .= 'LEFT JOIN member_profiles mp ON mp.customer_id = su.' . $signupIdCol . ' ';
        }

        if ($walletJoinCol) {
            $sql .= 'LEFT JOIN wallet_balance wb ON wb.' . $walletJoinCol . ' = su.' . $signupIdCol . ' ';
        }

        $sql .= 'ORDER BY su.id DESC';

        $result = $conn->query($sql);
        if ($result) {
            $srNo = 1;
            while ($row = $result->fetch_assoc()) {
                $rows[] = [
                    'sr_no' => (string)$srNo++,
                    'system_id' => $row['system_id'] ?? '',
                    'name' => $row['name'] ?? '',
                    'pan_no' => $row['pan_no'] ?? '',
                    'aadhar_no' => $row['aadhar_no'] ?? '',
                    'mobile_no' => $row['mobile_no'] ?? '',
                    'net_amount' => $row['net_amount'] ?? '0',
                ];
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Process Payout</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body class="m-0 bg-[#f3f4f6] font-sans overflow-x-hidden">

    <?php include __DIR__ . '/includes/sidebar.php'; ?>

    <div class="ml-64 min-h-screen w-[calc(100%-16rem)]">
        <?php include __DIR__ . '/includes/navbar.php'; ?>

        <main class="px-5 py-4">

            <!-- Heading -->
            <div class="flex items-center gap-4 mb-4">
                <div class="w-9 h-9 rounded-[8px] bg-[#f6f2ea] flex items-center justify-center shrink-0">
                    <i data-lucide="badge-indian-rupee" class="w-4 h-4 text-[#c9983b]"></i>
                </div>

                <h2 class="text-[17px] md:text-[18px] leading-none text-[#2f2f2f] font-normal whitespace-nowrap"
                    style="font-family: Georgia, 'Times New Roman', serif;">
                    PROCESS PAYOUT
                </h2>

                <div class="h-[1px] bg-[#c9983b] flex-1"></div>
            </div>

            <!-- Info Bar -->
            <div class="h-[32px] rounded-[4px] bg-[#143a66] text-white text-[16px] font-medium px-3 flex items-center">
                Note! Click on the below given button to process balance payment of all Associates Binary Amount. All Associates' Balances will be added in cheque request.
            </div>

            <!-- Button -->
            <div class="flex justify-center mt-4">
                <form method="POST">
                    <button
                        type="submit"
                        class="h-[28px] min-w-[70px] rounded-[6px] bg-[#c9983b] text-white text-[11px] font-medium px-5 hover:opacity-95 transition"
                    >
                        Process
                    </button>
                </form>
            </div>

            <?php if ($successMessage !== ''): ?>
                <div class="mt-3 text-center text-[11px] text-green-700">
                    <?php echo htmlspecialchars($successMessage); ?>
                </div>
            <?php endif; ?>

            <!-- Description -->
            <!-- <div class="mt-5 text-[16px] text-[#8b8b8b]">
                <span class="font-semibold text-[#3a3a3a]">Cheque List</span>
                 of the payouts that is going to be processed and shown in cheque requests after payout process
            </div> -->

            <!-- Table -->
            <div class="mt-3 rounded-[6px] overflow-hidden border border-[#d6a246]">
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[980px] border-collapse">
                        <thead>
                            <tr class="bg-[#cd9737] text-white text-[15px] h-[28px]">
                                <th class="px-2 font-medium whitespace-nowrap text-left">Sr No.</th>
                                <th class="px-2 font-medium whitespace-nowrap text-left">Customer ID</th>
                                <!-- <th class="px-2 font-medium whitespace-nowrap text-left">WIN Code</th> -->
                                <th class="px-2 font-medium whitespace-nowrap text-left">Name</th>
                                <th class="px-2 font-medium whitespace-nowrap text-left">PAN No.</th>
                                <th class="px-2 font-medium whitespace-nowrap text-left">Aadhar No.</th>
                                <th class="px-2 font-medium whitespace-nowrap text-left">Mobile No.</th>
                                <th class="px-2 font-medium whitespace-nowrap text-left">Net Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($rows)): ?>
                                <?php foreach ($rows as $row): ?>
                                    <tr class="h-[34px] text-[15px] text-[#2d2d2d] border-t border-[#ececec]">
                                        <td class="px-2 whitespace-nowrap"><?php echo htmlspecialchars($row['sr_no'] ?? ''); ?></td>
                                        <td class="px-2 whitespace-nowrap"><?php echo htmlspecialchars($row['system_id'] ?? ''); ?></td>
                                        <td class="px-2 whitespace-nowrap"><?php echo htmlspecialchars($row['name'] ?? ''); ?></td>
                                        <td class="px-2 whitespace-nowrap"><?php echo htmlspecialchars($row['pan_no'] ?? ''); ?></td>
                                        <td class="px-2 whitespace-nowrap"><?php echo htmlspecialchars($row['aadhar_no'] ?? ''); ?></td>
                                        <td class="px-2 whitespace-nowrap"><?php echo htmlspecialchars($row['mobile_no'] ?? ''); ?></td>
                                        <td class="px-2 whitespace-nowrap"><?php echo htmlspecialchars($row['net_amount'] ?? ''); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" class="h-[320px] bg-white"></td>
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