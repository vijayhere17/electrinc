<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['customer_id']) && !isset($_SESSION['member_id'])) {
    header("Location: login.php");
    exit();
}

$customerId = $_SESSION['customer_id'] ?? $_SESSION['member_id'];

include __DIR__ . '/config/db.php';

if (!isset($conn) || !($conn instanceof mysqli)) {
    die("Database connection failed.");
}

$conn->set_charset("utf8mb4");


/* ---------- AUTO CREATE TABLE ---------- */

$conn->query("
CREATE TABLE IF NOT EXISTS reward_bonus_income_report (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id VARCHAR(50) NOT NULL,
    report_date DATE DEFAULT CURRENT_DATE,
    description VARCHAR(255) DEFAULT 'Reward Bonus',
    reward_name VARCHAR(150) NULL,
    credit DECIMAL(12,2) DEFAULT 0,
    debit DECIMAL(12,2) DEFAULT 0,
    balance DECIMAL(12,2) DEFAULT 0,
    status VARCHAR(50) DEFAULT 'Pending',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)
");


/* ---------- FETCH DATA ---------- */

$rewardRows = [];

$stmt = $conn->prepare("
SELECT
    id,
    club_no,
    reward_name,
    reward_budget,
    status,
    achieved_date
FROM reward_achievers
WHERE customer_id = ?
ORDER BY id DESC
");

$stmt->bind_param("s", $customerId);
$stmt->execute();

$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {

    $rewardRows[] = [
    'sr_no'      => $row['id'],
    'date'       => !empty($row['achieved_date'])
                    ? date('d-m-Y', strtotime($row['achieved_date']))
                    : '-',
    'club_no'    => $row['club_no'],
    'reward'     => $row['reward_name'],
    'budget'     => '₹' . number_format($row['reward_budget'],2),
    'status'     => $row['status']
];
}

$stmt->close();
?>


<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Reward Bonus</title>

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

<!-- HEADER -->

<div class="mt-5 flex items-center gap-4">

<div class="w-[36px] h-[36px] rounded-[8px] bg-[#f5eee3] flex items-center justify-center shrink-0">
<i data-lucide="badge-check" class="w-[18px] h-[18px] text-[#cd9737]"></i>
</div>

<h2 class="whitespace-nowrap text-[20px] font-normal text-[#40352a]"
style="font-family: Georgia, 'Times New Roman', serif;">
REWARD BONUS
</h2>

<div class="w-full h-[2px] bg-[#d1a14b]"></div>

</div>


<!-- TABLE -->

<div class="mt-8 rounded-[14px] border border-[#dddddd] bg-[#f7f7f7] p-4 min-h-[490px]">

<div class="overflow-x-auto">

<table class="w-full border-separate border-spacing-0">

<thead>

<tr class="bg-[#C89741] text-white text-[13px] font-semibold">

<th class="px-6 py-5 text-left rounded-l-[10px]">Sr No</th>

<th class="px-6 py-5 text-left">Date</th>

<th class="px-6 py-5 text-left">Club</th>

<th class="px-6 py-5 text-left">Reward</th>

<th class="px-6 py-5 text-left">Budget (₹)</th>

<th class="px-6 py-5 text-left rounded-r-[10px]">Status</th>

</tr>

</thead>


<tbody>

<?php if(!empty($rewardRows)): ?>

<?php foreach($rewardRows as $row): ?>

<tr class="text-[13px] text-[#2f2f2f] bg-white">

    <td class="px-6 py-5 border-b border-[#d8d8d8]">
        <?= $row['sr_no'] ?>
    </td>

    <td class="px-6 py-5 border-b border-[#d8d8d8]">
        <?= $row['date'] ?>
    </td>

    <td class="px-6 py-5 border-b border-[#d8d8d8] font-semibold text-[#393939]">
        Club <?= $row['club_no'] ?>
    </td>

    <td class="px-6 py-5 border-b border-[#d8d8d8]">
        <?= $row['reward'] ?>
    </td>

    <td class="px-6 py-5 border-b border-[#d8d8d8] font-semibold text-[#0a7a2f]">
        <?= $row['budget'] ?>
    </td>

    <td class="px-6 py-5 border-b border-[#d8d8d8]">
        <?php if($row['status'] == 'Pending'): ?>
            <span class="px-3 py-1 rounded-full bg-yellow-100 text-yellow-700 text-[12px] font-semibold">
                Pending
            </span>
        <?php elseif($row['status'] == 'Approved'): ?>
            <span class="px-3 py-1 rounded-full bg-blue-100 text-blue-700 text-[12px] font-semibold">
                Approved
            </span>
        <?php elseif($row['status'] == 'Delivered'): ?>
            <span class="px-3 py-1 rounded-full bg-green-100 text-green-700 text-[12px] font-semibold">
                Delivered
            </span>
        <?php else: ?>
            <span class="px-3 py-1 rounded-full bg-red-100 text-red-700 text-[12px] font-semibold">
                Rejected
            </span>
        <?php endif; ?>
    </td>

</tr>

<?php endforeach; ?>

<?php else: ?>

<tr>

<td colspan="6" class="h-[420px] align-top text-center">

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