<?php
session_start();

$memberName   = $_SESSION['full_name'] ?? 'Swapnil Solanki';
$customerId   = $_SESSION['member_id'] ?? 'MLM-00484';
$managerName  = $_SESSION['full_name'] ?? 'Swapnil Solanki';
$managerRole  = 'MLM Manager';
$profileImage = 'https://i.pravatar.cc/80?img=12';

$associateId = $_GET['associate_id'] ?? '';
$status      = $_GET['status'] ?? '';
$fromDate    = $_GET['from_date'] ?? '';
$toDate      = $_GET['to_date'] ?? '';

$rows = [];

include __DIR__ . '/config/db.php';

$sql = "SELECT withdraw_date, associate_id, mobile_no, pan_no, account_name, ifs_code, bank_name, branch_name, gross_amount, tds, admin_charge, net_amount, reference_no, status FROM cheque_requests WHERE 1=1";
$params = [];
$types = '';

if ($associateId !== '') {
    $sql .= ' AND associate_id LIKE ?';
    $types .= 's';
    $params[] = '%' . $associateId . '%';
}

if (in_array($status, ['Pending', 'Approved', 'Rejected'], true)) {
    $sql .= ' AND status = ?';
    $types .= 's';
    $params[] = $status;
}

if ($fromDate !== '') {
    $sql .= ' AND withdraw_date >= ?';
    $types .= 's';
    $params[] = $fromDate;
}

if ($toDate !== '') {
    $sql .= ' AND withdraw_date <= ?';
    $types .= 's';
    $params[] = $toDate;
}

$sql .= ' ORDER BY id DESC';

$stmt = $conn->prepare($sql);
if ($stmt) {
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cheque Requests All</title>
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
                    CHEQUE REQUESTS ALL
                </h2>

                <div class="h-[1px] bg-[#c9983b] flex-1"></div>
            </div>

            <!-- Main Box -->
            <div class="bg-white border border-[#dfdfdf] rounded-[12px] min-h-[405px] px-4 py-4">

                <!-- Search Form -->
                <form method="GET" class="space-y-4">

                    <div class="flex flex-wrap items-center gap-x-6 gap-y-4 text-[16px] text-[#3a3a3a] font-medium">
                        <div class="flex items-center gap-3">
                            <label class="whitespace-nowrap font-medium text-[16px] text-[#3a3a3a] font-medium">Associate ID:</label>
                            <input
                                type="text"
                                name="associate_id"
                                value="<?php echo htmlspecialchars($associateId); ?>"
                                class="w-[156px] h-[24px] rounded-[6px] border border-[#d8dce2] bg-[#f2f4f7] px-3 text-[15px] outline-none"
                            >
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center gap-x-6 gap-y-4 text-[16px] text-[#3a3a3a] font-medium">
                        <div class="flex items-center gap-3">
                            <label class="whitespace-nowrap font-medium text-[16px] text-[#3a3a3a] font-medium">Status:</label>
                            <select
                                name="status"
                                class="w-[152px] h-[24px] rounded-[6px] border border-[#d8dce2] bg-[#f2f4f7] px-2 text-[15px] text-[#666] outline-none"
                            >
                                <option value=""></option>
                                <option value="Pending" <?php if ($status === 'Pending') echo 'selected'; ?>>Pending</option>
                                <option value="Approved" <?php if ($status === 'Approved') echo 'selected'; ?>>Approved</option>
                                <option value="Rejected" <?php if ($status === 'Rejected') echo 'selected'; ?>>Rejected</option>
                            </select>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center gap-x-4 gap-y-4 text-[16px] text-[#3a3a3a] font-medium">
                        <div class="flex items-center gap-2">
                            <label class="whitespace-nowrap font-medium text-[16px] text-[#3a3a3a] font-medium">Search by joining Date:</label>

                            <span class="text-[#b1b1b1] text-[10px]">From:</span>
                            <input
                                type="date"
                                name="from_date"
                                value="<?php echo htmlspecialchars($fromDate); ?>"
                                class="w-[150px] h-[24px] rounded-[6px] border border-[#d8dce2] bg-[#f2f4f7] px-2 text-[15px] outline-none"
                            >

                            <span class="text-[#b1b1b1] text-[15px]">To:</span>
                            <input
                                type="date"
                                name="to_date"
                                value="<?php echo htmlspecialchars($toDate); ?>"
                                class="w-[150px] h-[24px] rounded-[6px] border border-[#d8dce2] bg-[#f2f4f7] px-2 text-[15px] outline-none"
                            >
                        </div>

                        <button
                            type="submit"
                            class="h-[24px] min-w-[112px] rounded-[5px] bg-[#143a66] text-white text-[15px] px-3 flex items-center justify-center gap-1"
                        >
                            <i data-lucide="calendar-days" class="w-3 h-3"></i>
                            <span>Search By Date</span>
                        </button>
                    </div>

                </form>

                <!-- Table -->
                <div class="mt-5 rounded-[6px] overflow-hidden border border-[#d6a246]">
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[1280px] border-collapse">
                            <thead>
                                <tr class="bg-[#cd9737] text-white text-[13px] h-[36px]">
                                    <th class="px-3 font-medium whitespace-nowrap text-left">Withdraw Date</th>
                                    <th class="px-3 font-medium whitespace-nowrap text-left">Associate ID</th>
                                    <th class="px-3 font-medium whitespace-nowrap text-left">Mobile No.</th>
                                    <th class="px-3 font-medium whitespace-nowrap text-left">PAN No.</th>
                                    <th class="px-3 font-medium whitespace-nowrap text-left">Account Name</th>
                                    <th class="px-3 font-medium whitespace-nowrap text-left">IFS Code</th>
                                    <th class="px-3 font-medium whitespace-nowrap text-left">Bank Name</th>
                                    <th class="px-3 font-medium whitespace-nowrap text-left">Branch Name</th>
                                    <th class="px-3 font-medium whitespace-nowrap text-left">Gross Amount</th>
                                    <th class="px-3 font-medium whitespace-nowrap text-left">TDS</th>
                                    <th class="px-3 font-medium whitespace-nowrap text-left">Admin Charge</th>
                                    <th class="px-3 font-medium whitespace-nowrap text-left">Net Amount</th>
                                    <th class="px-3 font-medium whitespace-nowrap text-left">Reference No.</th>
                                    <th class="px-3 font-medium whitespace-nowrap text-left">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($rows)): ?>
                                    <?php foreach ($rows as $row): ?>
                                        <tr class="h-[34px] text-[10px] text-[#2d2d2d] border-t border-[#ececec]">
                                            <td class="px-3 whitespace-nowrap"><?php echo htmlspecialchars($row['withdraw_date'] ?? ''); ?></td>
                                            <td class="px-3 whitespace-nowrap"><?php echo htmlspecialchars($row['associate_id'] ?? ''); ?></td>
                                            <td class="px-3 whitespace-nowrap"><?php echo htmlspecialchars($row['mobile_no'] ?? ''); ?></td>
                                            <td class="px-3 whitespace-nowrap"><?php echo htmlspecialchars($row['pan_no'] ?? ''); ?></td>
                                            <td class="px-3 whitespace-nowrap"><?php echo htmlspecialchars($row['account_name'] ?? ''); ?></td>
                                            <td class="px-3 whitespace-nowrap"><?php echo htmlspecialchars($row['ifs_code'] ?? ''); ?></td>
                                            <td class="px-3 whitespace-nowrap"><?php echo htmlspecialchars($row['bank_name'] ?? ''); ?></td>
                                            <td class="px-3 whitespace-nowrap"><?php echo htmlspecialchars($row['branch_name'] ?? ''); ?></td>
                                            <td class="px-3 whitespace-nowrap"><?php echo htmlspecialchars($row['gross_amount'] ?? ''); ?></td>
                                            <td class="px-3 whitespace-nowrap"><?php echo htmlspecialchars($row['tds'] ?? ''); ?></td>
                                            <td class="px-3 whitespace-nowrap"><?php echo htmlspecialchars($row['admin_charge'] ?? ''); ?></td>
                                            <td class="px-3 whitespace-nowrap"><?php echo htmlspecialchars($row['net_amount'] ?? ''); ?></td>
                                            <td class="px-3 whitespace-nowrap"><?php echo htmlspecialchars($row['reference_no'] ?? ''); ?></td>
                                            <td class="px-3 whitespace-nowrap"><?php echo htmlspecialchars($row['status'] ?? ''); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="14" class="h-[330px] bg-white"></td>
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