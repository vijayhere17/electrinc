<?php
session_start();

$memberName   = $_SESSION['full_name'] ?? 'Swapnil Solanki';
$customerId   = $_SESSION['member_id'] ?? 'MLM-00484';
$managerName  = $_SESSION['full_name'] ?? 'Swapnil Solanki';
$managerRole  = 'MLM Manager';
$profileImage = 'https://i.pravatar.cc/80?img=12';

$rows = [];

include __DIR__ . '/config/db.php';

$sql = 'SELECT week_no, date, total_payouts, total_amount, pending_amount, view_payment FROM payout_closing ORDER BY id DESC';
$result = $conn->query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payout Closing List</title>
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
                    PAYOUT CLOSING LIST
                </h2>

                <div class="h-[1px] bg-[#c9983b] flex-1"></div>
            </div>

            <!-- Main Box -->
            <div class="bg-white border border-[#dfdfdf] rounded-[12px] min-h-[405px] px-3 py-3">

                <!-- Table -->
                <div class="rounded-[6px] overflow-hidden border border-[#d6a246]">
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[900px] border-collapse">
                            <thead>
                                <tr class="bg-[#cd9737] text-white text-[15px] h-[28px]">
                                    <th class="px-3 font-medium whitespace-nowrap text-left">Week No.</th>
                                    <th class="px-3 font-medium whitespace-nowrap text-left">Date</th>
                                    <th class="px-3 font-medium whitespace-nowrap text-left">Total Payouts</th>
                                    <th class="px-3 font-medium whitespace-nowrap text-left">Total Amount</th>
                                    <th class="px-3 font-medium whitespace-nowrap text-left">Pending Amount</th>
                                    <th class="px-3 font-medium whitespace-nowrap text-left">View Payment</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($rows)): ?>
                                    <?php foreach ($rows as $row): ?>
                                        <tr class="h-[34px] text-[15px] text-[#2d2d2d] border-t border-[#ececec]">
                                            <td class="px-3 whitespace-nowrap"><?php echo htmlspecialchars($row['week_no'] ?? ''); ?></td>
                                            <td class="px-3 whitespace-nowrap"><?php echo htmlspecialchars($row['date'] ?? ''); ?></td>
                                            <td class="px-3 whitespace-nowrap"><?php echo htmlspecialchars($row['total_payouts'] ?? ''); ?></td>
                                            <td class="px-3 whitespace-nowrap"><?php echo htmlspecialchars($row['total_amount'] ?? ''); ?></td>
                                            <td class="px-3 whitespace-nowrap"><?php echo htmlspecialchars($row['pending_amount'] ?? ''); ?></td>
                                            <td class="px-3 whitespace-nowrap"><?php echo htmlspecialchars($row['view_payment'] ?? ''); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="h-[360px] bg-white"></td>
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