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

$rewardRows = [];

$conn->query("
CREATE TABLE IF NOT EXISTS reward_bonus_status (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id VARCHAR(50) NOT NULL,
    reward_name VARCHAR(100) NULL,
    reward_amount DECIMAL(12,2) DEFAULT 0,
    reward_date DATE DEFAULT CURRENT_DATE,
    status VARCHAR(50) DEFAULT 'Pending',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)
");

$stmt = $conn->prepare("
    SELECT *
    FROM reward_bonus_status
    WHERE customer_id = ?
    ORDER BY id DESC
");

$stmt->bind_param("s", $customerId);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $rewardRows[] = [
        'id' => $row['id'],
        'reward' => $row['reward_name'] ?? '-',
        'amount' => (float)($row['reward_amount'] ?? 0),
        'date' => !empty($row['reward_date']) ? date('d-m-Y', strtotime($row['reward_date'])) : '-',
        'status' => $row['status'] ?? 'Pending'
    ];
}

$stmt->close();
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Reward Bonus</title>

    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>

    <style>
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
                REWARD BONUS
            </h2>

            <div class="w-full h-[2px] bg-[#d1a14b]"></div>
        </div>

        <div class="mt-6 rounded-[20px] bg-white border border-[#e5e7eb] shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full min-w-[900px]">

                    <thead>
                        <tr class="bg-[#C89741] text-white text-[13px] font-semibold">
                            <th class="px-6 py-4 text-left">Sr No.</th>
                            <th class="px-6 py-4 text-left">Reward</th>
                            <th class="px-6 py-4 text-left">Amount</th>
                            <th class="px-6 py-4 text-left">Date</th>
                            <th class="px-6 py-4 text-left">Status</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php if (!empty($rewardRows)): ?>
                            <?php foreach ($rewardRows as $row): ?>
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

                                    <td class="px-6 py-4 font-semibold">
                                        <?php echo htmlspecialchars($row['reward']); ?>
                                    </td>

                                    <td class="px-6 py-4 text-[#1f5c34] font-semibold">
                                        ₹<?php echo number_format($row['amount'], 2); ?>
                                    </td>

                                    <td class="px-6 py-4">
                                        <?php echo htmlspecialchars($row['date']); ?>
                                    </td>

                                    <td class="px-6 py-4">
                                        <span class="px-3 py-1 rounded-full text-xs font-semibold <?php echo $statusClass; ?>">
                                            <?php echo htmlspecialchars($row['status']); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>

                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="px-6 py-10 text-center text-gray-500">
                                    No reward bonus records found.
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