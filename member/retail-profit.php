<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['customer_id'])) {
    header("Location: login.php");
    exit();
}

$memberName   = $_SESSION['full_name'] ?? "Member";
$customerId   = $_SESSION['customer_id'];
$managerName  = "Swapnil Solanki";
$managerRole  = "MLM Manager";
$profileImage = "https://i.pravatar.cc/80?img=12";
$navTitle     = "My Profile";
$navIcon      = "user-round";

include __DIR__ . '/config/db.php';

$retailProfitRows = [];

$sql = "SELECT id, profit_date, transaction_id, target_business, percentage, earned, status
        FROM retail_profit_status
        WHERE customer_id = ?
        ORDER BY id DESC";

$stmt = $conn->prepare($sql);

if ($stmt) {
    $stmt->bind_param("s", $customerId);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $retailProfitRows[] = [
            'sr_no' => $row['id'],
            'date' => !empty($row['profit_date']) ? date('d-m-Y', strtotime($row['profit_date'])) : '-',
            'transaction_id' => $row['transaction_id'],
            'target_business' => '₹' . number_format((float)$row['target_business'], 2),
            'percentage' => number_format((float)$row['percentage'], 2) . '%',
            'earned' => '₹' . number_format((float)$row['earned'], 2),
            'status' => $row['status'],
        ];
    }

    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Retail Profit</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        body{
            font-family: Arial, Helvetica, sans-serif;
            background:#f3f3f3;
        }
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
                    RETAIL PROFIT
                </h2>

                <div class="w-full h-[2px] bg-[#d1a14b]"></div>
            </div>

            <div class="mt-8 rounded-[14px] border border-[#dddddd] bg-[#f7f7f7] p-4 min-h-[490px]">
                <div class="overflow-x-auto">
                    <table class="w-full border-separate border-spacing-0">
                        <thead>
                            <tr class="bg-[#C89741] text-white text-[13px] font-semibold">
                                <th class="px-6 py-5 text-left rounded-l-[10px] min-w-[90px]">Sr No</th>
                                <th class="px-6 py-5 text-left min-w-[120px]">Date</th>
                                <th class="px-6 py-5 text-left min-w-[150px]">Transaction ID</th>
                                <th class="px-6 py-5 text-left min-w-[160px]">Traget Business</th>
                                <th class="px-6 py-5 text-left min-w-[80px]">%</th>
                                <th class="px-6 py-5 text-left min-w-[120px]">Earned</th>
                                <th class="px-6 py-5 text-left rounded-r-[10px] min-w-[110px]">Status</th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php if (count($retailProfitRows) > 0): ?>
                                <?php foreach ($retailProfitRows as $row): ?>
                                    <tr class="text-[13px] text-[#2f2f2f] bg-white">
                                        <td class="px-6 py-5 border-b border-[#d8d8d8] align-middle">
                                            <?php echo htmlspecialchars($row['sr_no']); ?>
                                        </td>
                                        <td class="px-6 py-5 border-b border-[#d8d8d8] align-middle">
                                            <?php echo htmlspecialchars($row['date']); ?>
                                        </td>
                                        <td class="px-6 py-5 border-b border-[#d8d8d8] align-middle">
                                            <?php echo htmlspecialchars($row['transaction_id']); ?>
                                        </td>
                                        <td class="px-6 py-5 border-b border-[#d8d8d8] align-middle font-semibold">
                                            <?php echo htmlspecialchars($row['target_business']); ?>
                                        </td>
                                        <td class="px-6 py-5 border-b border-[#d8d8d8] align-middle">
                                            <?php echo htmlspecialchars($row['percentage']); ?>
                                        </td>
                                        <td class="px-6 py-5 border-b border-[#d8d8d8] align-middle font-semibold">
                                            <?php echo htmlspecialchars($row['earned']); ?>
                                        </td>
                                        <td class="px-6 py-5 border-b border-[#d8d8d8] align-middle">
                                            <?php echo htmlspecialchars($row['status']); ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="h-[420px] align-top text-center">
                                        <div class="pt-5 text-[14px] font-semibold text-[#b0b0b0]">
                                            No Record Found...
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>

    <script>
        lucide.createIcons();
    </script>
</body>
</html>