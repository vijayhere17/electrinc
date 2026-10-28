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
$managerName  = "Swapnil Solanki";
$managerRole  = "MLM Manager";
$profileImage = "https://i.pravatar.cc/80?img=12";
$navTitle     = "My Profile";
$navIcon      = "user-round";

include __DIR__ . '/config/db.php';

if (!isset($conn) || !($conn instanceof mysqli)) {
    die("Database connection failed.");
}

$conn->set_charset("utf8mb4");

// Create level matching bonus status table if it doesn't exist
$conn->query("
    CREATE TABLE IF NOT EXISTS level_matching_bonus_status (
        id INT AUTO_INCREMENT PRIMARY KEY,
        customer_id VARCHAR(50) NOT NULL,
        bonus_date DATE DEFAULT CURRENT_DATE,
        left_id VARCHAR(50) NULL,
        right_id VARCHAR(50) NULL,
        pair_no INT DEFAULT 0,
        earn DECIMAL(12,2) DEFAULT 0,
        status VARCHAR(50) DEFAULT 'Pending',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )
");

$levelMatchingRows = [];
$totalEarn = 0;

// Fetch level matching bonus data

$sql = "
    SELECT 
        p.id,
        p.created_at AS report_date,
        p.amount AS credit,
        p.status,
        p.left_member_id,
        p.right_member_id,
p.pair_no
    FROM level_matching_pairs p
    WHERE p.customer_id = ?
    ORDER BY p.id ASC
";

$stmt = $conn->prepare($sql);

if ($stmt) {
    $stmt->bind_param("s", $customerId);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $credit = (float)($row['credit'] ?? 0);
$totalEarn += (float)($row['credit'] ?? 0);
        
        $levelMatchingRows[] = [
            'sr_no' => $row['pair_no'],
            'date' => !empty($row['report_date']) ? date('d-m-Y', strtotime($row['report_date'])) : '-',
            'description' => $row['description'],
            'left_member_id'  => $row['left_member_id'] ?? '-',
'right_member_id' => $row['right_member_id'] ?? '-',
            'credit'      => '₹' . number_format($credit, 2),
            'debit' => '₹' . number_format((float)$row['debit'], 2),
            'balance'     => '₹' . number_format($totalEarn, 2),
            'status'      => $row['status'] ?: 'Pending',
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
    <title>Level Matching Bonus</title>
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
                    LEVEL MATCHING BONUS
                </h2>

                <div class="w-full h-[2px] bg-[#d1a14b]"></div>
            </div>

            <div class="mt-8 rounded-[14px] border border-[#dddddd] bg-[#f7f7f7] p-4 min-h-[490px]">
               <div class="overflow-x-auto">

    <table class="w-full border-separate border-spacing-0">

        <thead>
            <tr class="bg-[#C89741] text-white text-[13px] font-semibold">

                <th class="px-5 py-5 text-left rounded-l-[10px] min-w-[90px]">
                    Pair No
                </th>

                <th class="px-5 py-5 text-left min-w-[130px]">
                    Date
                </th>

                <th class="px-5 py-5 text-left min-w-[180px]">
                    Left ID
                </th>

                <th class="px-5 py-5 text-left min-w-[180px]">
                    Right ID
                </th>

                <th class="px-5 py-5 text-left rounded-r-[10px] min-w-[140px]">
                    Pair Income
                </th>

            </tr>
        </thead>

        <tbody>

            <?php if (count($levelMatchingRows) > 0): ?>

                <?php
               
                $totalIncome = 0;
                $visiblePairNo = 0;
                ?>

                <?php foreach ($levelMatchingRows as $row): ?>

                    <?php
                    $creditAmount = (float) str_replace(['₹', ','], '', $row['credit']);

if ($row['status'] != 'Skipped') {
    $visiblePairNo++;
}

$totalIncome += $creditAmount;
                    ?>

                    <tr class="bg-white hover:bg-[#faf7f2] transition">

                        <td class="px-5 py-5 border-b border-[#e5e5e5]">

                            <div class="font-bold text-[#102B4E] text-[16px]">
                              #<?php echo $row['status'] == 'Skipped' ? '-' : $visiblePairNo; ?>
                            </div>

                        </td>

                        <td class="px-5 py-5 border-b border-[#e5e5e5]">

                            <div class="font-medium text-[#444]">
                                <?php echo htmlspecialchars($row['date']); ?>
                            </div>

                        </td>

                        <td class="px-5 py-5 border-b border-[#e5e5e5]">

                            <div class="font-semibold text-[#102B4E]">
                                <?php echo htmlspecialchars($row['left_member_id']); ?>
                            </div>

                        </td>

                        <td class="px-5 py-5 border-b border-[#e5e5e5]">

                            <div class="font-semibold text-[#102B4E]">
                                <?php echo htmlspecialchars($row['right_member_id']); ?>
                            </div>

                        </td>

                        <td class="px-5 py-5 border-b border-[#e5e5e5]">

                            <?php if ($creditAmount > 0): ?>

                                <span class="inline-flex items-center px-4 py-2 rounded-full bg-[#dcfce7] text-[#15803d] font-bold text-[13px]">
                                    + ₹<?php echo number_format($creditAmount, 2); ?>
                                </span>

                            <?php else: ?>

                                <span class="inline-flex items-center px-4 py-2 rounded-full bg-[#fee2e2] text-[#dc2626] font-bold text-[13px]">
                                    ₹0.00
                                </span>

                            <?php endif; ?>

                        </td>

                    </tr>

                <?php endforeach; ?>

                <tr class="bg-[#102B4E] text-white">

                    <td colspan="4" class="px-5 py-5 text-right font-bold text-[16px] rounded-bl-[10px]">
                        TOTAL INCOME
                    </td>

                    <td class="px-5 py-5 font-bold text-[16px] rounded-br-[10px]">
                        ₹<?php echo number_format($totalIncome, 2); ?>
                    </td>

                </tr>

            <?php else: ?>

                <tr>

                    <td colspan="5" class="h-[420px] align-top text-center">

                        <div class="pt-10">

                            <div class="text-[16px] font-semibold text-[#999]">
                                No Level Matching Income Found
                            </div>

                            <div class="mt-2 text-[13px] text-[#b5b5b5]">
                                Your pair income will appear here
                            </div>

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