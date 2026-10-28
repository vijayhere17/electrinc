<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include __DIR__ . '/config/db.php';

if (!isset($_SESSION['customer_id']) && !isset($_SESSION['member_id'])) {
    header("Location: login.php");
    exit();
}

$customerId = $_SESSION['customer_id'] ?? $_SESSION['member_id'];

$rows = [];
$totalEarn = 0;
$totalRecords = 0;

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

$stmt = $conn->prepare("
    SELECT *
    FROM level_matching_bonus_status
    WHERE customer_id = ?
    ORDER BY id DESC
");

$stmt->bind_param("s", $customerId);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $earn = (float)($row['earn'] ?? 0);

    $rows[] = [
        'id' => $row['id'],
        'date' => !empty($row['bonus_date']) ? date('d-m-Y', strtotime($row['bonus_date'])) : '-',
        'left_id' => $row['left_id'] ?? '-',
        'right_id' => $row['right_id'] ?? '-',
        'pair_no' => $row['pair_no'] ?? 0,
        'earn' => $earn,
        'status' => $row['status'] ?? 'Pending'
    ];

    $totalEarn += $earn;
    $totalRecords++;
}

$stmt->close();
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Level Matching Bonus</title>

    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>

    <style>
        body {
            font-family: Arial, Helvetica, sans-serif;
            background: #f3f3f3;
        }

        table {
            border-collapse: separate !important;
            border-spacing: 0 !important;
        }

        thead th {
            border: none !important;
        }
    </style>
</head>

<body class="bg-[#f3f3f3]">

<?php include __DIR__ . '/includes/sidebar.php'; ?>

<div class="ml-64 min-h-screen">

    <?php include __DIR__ . '/includes/navbar.php'; ?>

    <div class="px-5 py-3">

        <div class="mt-5 flex items-center gap-4">
            <div class="w-[36px] h-[36px] rounded-[8px] bg-[#f5eee3] flex items-center justify-center">
                <i data-lucide="gift" class="w-[18px] h-[18px] text-[#cd9737]"></i>
            </div>

            <h2 class="whitespace-nowrap text-[20px] font-normal text-[#40352a]" style="font-family: Georgia, 'Times New Roman', serif;">
                REPURCHASE            </h2>

            <div class="w-full h-[2px] bg-[#d1a14b]"></div>
        </div>

        <div class="mt-6 rounded-[20px] bg-white border border-[#e5e7eb] shadow-sm overflow-hidden">

            <div class="overflow-x-auto">
                <table class="w-full min-w-[900px]">

                    <thead>
                        <tr class="bg-[#C89741] text-white text-[13px] font-semibold">
                            <th class="px-6 py-4 text-left">Sr No.</th>
                            <th class="px-6 py-4 text-left">Date</th>
                            <th class="px-6 py-4 text-left">Left ID</th>
                            <th class="px-6 py-4 text-left">Right ID</th>
                            <th class="px-6 py-4 text-left">Pair No</th>
                            <th class="px-6 py-4 text-left">Earned</th>
                            <th class="px-6 py-4 text-left">Status</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php if (!empty($rows)): ?>
                            <?php foreach ($rows as $row): ?>
                                <?php
                                $status = strtolower($row['status']);

                                if ($status == "approved" || $status == "paid") {
                                    $statusClass = "bg-green-100 text-green-700";
                                } elseif ($status == "rejected") {
                                    $statusClass = "bg-red-100 text-red-700";
                                } else {
                                    $statusClass = "bg-yellow-100 text-yellow-700";
                                }
                                ?>

                                <tr class="border-b border-[#eef2f6]">
                                    <td class="px-6 py-4">
                                        <?php echo htmlspecialchars($row['id']); ?>
                                    </td>

                                    <td class="px-6 py-4">
                                        <?php echo htmlspecialchars($row['date']); ?>
                                    </td>

                                    <td class="px-6 py-4">
                                        <?php echo htmlspecialchars($row['left_id']); ?>
                                    </td>

                                    <td class="px-6 py-4">
                                        <?php echo htmlspecialchars($row['right_id']); ?>
                                    </td>

                                    <td class="px-6 py-4">
                                        <?php echo htmlspecialchars($row['pair_no']); ?>
                                    </td>

                                    <td class="px-6 py-4 font-semibold text-[#1f5c34]">
                                        ₹<?php echo number_format($row['earn'], 2); ?>
                                    </td>

                                    <td class="px-6 py-4">
                                        <span class="px-3 py-1 rounded-full text-xs font-semibold <?php echo $statusClass; ?>">
                                            <?php echo htmlspecialchars($row['status']); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>

                            <tr class="bg-[#fafbfd]">
                                <td colspan="5" class="px-6 py-4 text-right font-bold text-[#374151]">
                                    Total Earn
                                </td>

                                <td class="px-6 py-4 font-bold text-[#1f5c34]">
                                    ₹<?php echo number_format($totalEarn, 2); ?>
                                </td>

                                <td class="px-6 py-4 font-bold text-[#374151]">
                                    <?php echo $totalRecords; ?> Records
                                </td>
                            </tr>

                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="px-6 py-10 text-center text-gray-500">
                                    No level matching bonus records found.
                                </td>
                            </tr>

                            <tr class="bg-[#fafbfd]">
                                <td colspan="5" class="px-6 py-4 text-right font-bold text-[#374151]">
                                    Total Earn
                                </td>

                                <td class="px-6 py-4 font-bold text-[#1f5c34]">
                                    ₹0.00
                                </td>

                                <td class="px-6 py-4 font-bold text-[#374151]">
                                    0 Records
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