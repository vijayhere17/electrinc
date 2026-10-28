<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['customer_id']) && !isset($_SESSION['member_id'])) {
    header("Location: login.php");
    exit();
}

$memberName   = $_SESSION['full_name'] ?? "Member";
$customerId   = $_SESSION['customer_id'] ?? $_SESSION['member_id'];
$navTitle     = "Repurchase Report";
$navIcon      = "badge-check";

include __DIR__ . '/config/db.php';

if (!isset($conn) || !($conn instanceof mysqli)) {
    die("Database connection failed.");
}

$conn->set_charset("utf8mb4");

$repurchaseRows = [];
$runningBalance = 0;

$stmt = $conn->prepare("
    SELECT id, income_date, from_member,
           left_bv, right_bv, carry_left_bv, carry_right_bv,
           matching_bv, income_amount, status, created_at
    FROM repurchase_matching_income_log
    WHERE customer_id = ?
    ORDER BY id ASC
");

if ($stmt) {
    $stmt->bind_param("s", $customerId);
    $stmt->execute();
    $result = $stmt->get_result();
    $srNo = 1;

    while ($row = $result->fetch_assoc()) {
        $income = (float)$row['income_amount'];
        $runningBalance += $income;

        $repurchaseRows[] = [
            'sr_no'        => $srNo++,
            'date'         => !empty($row['income_date']) ? date('d-m-Y', strtotime($row['income_date'])) : '-',
            'from_member'  => $row['from_member'],
            'left_bv'      => number_format((float)$row['left_bv'], 2),
            'right_bv'     => number_format((float)$row['right_bv'], 2),
            'carry_left'   => number_format((float)$row['carry_left_bv'], 2),
            'carry_right'  => number_format((float)$row['carry_right_bv'], 2),
            'matching_bv'  => number_format((float)$row['matching_bv'], 2),
            'credit'       => number_format($income, 2),
            'balance'      => number_format($runningBalance, 2),
            'status'       => $row['status'] ?? 'Paid',
        ];
    }

    $stmt->close();
}

$totalIncome = $runningBalance;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Repurchase Report</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        body { font-family: Arial, Helvetica, sans-serif; background: #f3f3f3; }
    </style>
</head>
<body class="bg-[#f3f3f3]">

    <?php include __DIR__ . '/includes/sidebar.php'; ?>

    <div class="ml-64 min-h-screen">
        <?php include __DIR__ . '/includes/navbar.php'; ?>

        <div class="px-5 py-3">

            <div class="mt-5 flex items-center gap-4">
                <div class="w-[36px] h-[36px] rounded-[8px] bg-[#f5eee3] flex items-center justify-center shrink-0">
                    <i data-lucide="badge-check" class="w-[18px] h-[18px] text-[#cd9737]"></i>
                </div>
                <h2 class="whitespace-nowrap text-[20px] font-normal text-[#40352a]" style="font-family: Georgia, 'Times New Roman', serif;">
                    REPURCHASE MATCHING BONUS
                </h2>
                <div class="w-full h-[2px] bg-[#d1a14b]"></div>
            </div>

            <!-- Summary Cards -->
            <div class="mt-6 grid grid-cols-3 gap-4">
                <div class="bg-white rounded-[12px] border border-[#dddddd] px-5 py-4">
                    <p class="text-[12px] text-[#888888]">Total Matching Income</p>
                    <p class="text-[22px] font-semibold text-[#2f2f2f] mt-1">₹<?php echo number_format($totalIncome, 2); ?></p>
                </div>
                <div class="bg-white rounded-[12px] border border-[#dddddd] px-5 py-4">
                    <p class="text-[12px] text-[#888888]">Total Entries</p>
                    <p class="text-[22px] font-semibold text-[#2f2f2f] mt-1"><?php echo count($repurchaseRows); ?></p>
                </div>
                <div class="bg-white rounded-[12px] border border-[#dddddd] px-5 py-4">
                    <p class="text-[12px] text-[#888888]">Current Balance</p>
                    <p class="text-[22px] font-semibold text-[#C89741] mt-1">₹<?php echo number_format($totalIncome, 2); ?></p>
                </div>
            </div>

            <!-- Table -->
            <div class="mt-6 rounded-[14px] border border-[#dddddd] bg-[#f7f7f7] p-4 min-h-[490px]">
                <div class="overflow-x-auto">
                    <table class="w-full border-separate border-spacing-0">
                        <thead>
                            <tr class="bg-[#C89741] text-white text-[13px] font-semibold">
                                <th class="px-4 py-5 text-left rounded-l-[10px] min-w-[55px]">Sr No</th>
                                <th class="px-4 py-5 text-left min-w-[110px]">Date</th>
                                <th class="px-4 py-5 text-left min-w-[130px]">From Member</th>
                                <th class="px-4 py-5 text-left min-w-[100px]">Left BV</th>
                                <th class="px-4 py-5 text-left min-w-[100px]">Right BV</th>
                                <th class="px-4 py-5 text-left min-w-[110px]">Carry Left</th>
                                <th class="px-4 py-5 text-left min-w-[110px]">Carry Right</th>
                                <th class="px-4 py-5 text-left min-w-[120px]">Matching BV</th>
                                <th class="px-4 py-5 text-left min-w-[110px]">Credit (₹)</th>
                                <th class="px-4 py-5 text-left min-w-[120px]">Balance (₹)</th>
                                <th class="px-4 py-5 text-left rounded-r-[10px] min-w-[90px]">Status</th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php if (count($repurchaseRows) > 0): ?>
                                <?php foreach ($repurchaseRows as $index => $row): ?>
                                    <tr class="text-[13px] text-[#2f2f2f] <?php echo $index % 2 === 0 ? 'bg-white' : 'bg-[#fafafa]'; ?>">
                                        <td class="px-4 py-4 border-b border-[#eeeeee] align-middle"><?php echo $row['sr_no']; ?></td>
                                        <td class="px-4 py-4 border-b border-[#eeeeee] align-middle"><?php echo htmlspecialchars($row['date']); ?></td>
                                        <td class="px-4 py-4 border-b border-[#eeeeee] align-middle text-[#555555] font-semibold"><?php echo htmlspecialchars($row['from_member']); ?></td>
                                        <td class="px-4 py-4 border-b border-[#eeeeee] align-middle text-[#1565c0]"><?php echo $row['left_bv']; ?></td>
                                        <td class="px-4 py-4 border-b border-[#eeeeee] align-middle text-[#1565c0]"><?php echo $row['right_bv']; ?></td>
                                        <td class="px-4 py-4 border-b border-[#eeeeee] align-middle text-[#888888]"><?php echo $row['carry_left']; ?></td>
                                        <td class="px-4 py-4 border-b border-[#eeeeee] align-middle text-[#888888]"><?php echo $row['carry_right']; ?></td>
                                        <td class="px-4 py-4 border-b border-[#eeeeee] align-middle font-semibold text-[#C89741]"><?php echo $row['matching_bv']; ?></td>
                                        <td class="px-4 py-4 border-b border-[#eeeeee] align-middle font-semibold text-[#2e7d32]">₹<?php echo $row['credit']; ?></td>
                                        <td class="px-4 py-4 border-b border-[#eeeeee] align-middle font-semibold text-[#1a237e]">₹<?php echo $row['balance']; ?></td>
                                        <td class="px-4 py-4 border-b border-[#eeeeee] align-middle">
                                            <?php
                                                $st = $row['status'];
                                                $stClass = $st === 'Paid'
                                                    ? 'bg-[#e8f5e9] text-[#2e7d32]'
                                                    : 'bg-[#fff8e1] text-[#f57f17]';
                                            ?>
                                            <span class="px-3 py-1 rounded-full text-[11px] font-semibold <?php echo $stClass; ?>">
                                                <?php echo htmlspecialchars($st); ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>

                                <tr class="bg-[#fdf6e3] text-[13px] font-semibold">
                                    <td colspan="8" class="px-4 py-4 text-right text-[#C89741]">Total Income</td>
                                    <td class="px-4 py-4 text-[#2e7d32]">₹<?php echo number_format($totalIncome, 2); ?></td>
                                    <td class="px-4 py-4 text-[#1a237e]">₹<?php echo number_format($totalIncome, 2); ?></td>
                                    <td class="px-4 py-4"></td>
                                </tr>

                            <?php else: ?>
                                <tr>
                                    <td colspan="11" class="h-[420px] align-top text-center">
                                        <div class="pt-5 text-[14px] font-semibold text-[#b0b0b0]">No Record Found...</div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>

    <script>lucide.createIcons();</script>
</body>
</html>