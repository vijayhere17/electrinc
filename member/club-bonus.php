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

$clubRows = [];
$totalEarn = 0;
$totalRecords = 0;


/* AUTO CREATE TABLE */

$conn->query("
CREATE TABLE IF NOT EXISTS club_bonus_status (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id VARCHAR(50) NOT NULL,
    club_level VARCHAR(50),
    bonus DECIMAL(12,2) DEFAULT 0,
    per_count INT DEFAULT 0,
    income DECIMAL(12,2) DEFAULT 0,
    bonus_date DATE DEFAULT CURRENT_DATE,
    status VARCHAR(50) DEFAULT 'Pending',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)
");


/* FETCH DATA */

$stmt = $conn->prepare("
SELECT *
FROM club_bonus_status
WHERE customer_id = ?
ORDER BY id DESC
");

$stmt->bind_param("s", $customerId);
$stmt->execute();

$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {

    $income = (float)$row['income'];

    $clubRows[] = [
        'club'   => $row['club_level'],
        'bonus'  => $row['bonus'],
        'per'    => $row['per_count'],
        'income' => $income,
        'date'   => date('d-m-Y', strtotime($row['bonus_date'])),
        'status' => $row['status']
    ];

    $totalEarn += $income;
    $totalRecords++;
}

$stmt->close();
?>

<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Club Bonus</title>

<script src="https://cdn.tailwindcss.com"></script>
<script src="https://unpkg.com/lucide@latest"></script>

<style>
body{
font-family: Arial, Helvetica, sans-serif;
background:#f3f3f3;
}
</style>
</head>

<body>

<?php include __DIR__.'/includes/sidebar.php'; ?>

<div class="ml-64 min-h-screen">

<?php include __DIR__.'/includes/navbar.php'; ?>


<div class="px-5 py-3">


<!-- HEADER -->

<div class="mt-5 flex items-center gap-4">

<div class="w-[36px] h-[36px] rounded-[8px] bg-[#f5eee3] flex items-center justify-center">
<i data-lucide="gift" class="w-[18px] h-[18px] text-[#cd9737]"></i>
</div>

<h2 class="whitespace-nowrap text-[20px] font-normal text-[#40352a]"
style="font-family: Georgia, 'Times New Roman', serif;">
CLUB BONUS
</h2>

<div class="w-full h-[2px] bg-[#d1a14b]"></div>

</div>


<!-- TABLE -->

<div class="mt-6 rounded-[20px] bg-white border border-[#e5e7eb] shadow-sm overflow-hidden">

<div class="overflow-x-auto">

<table class="w-full min-w-[900px] border-collapse">


<!-- HEADER ROW (NO VERTICAL LINES) -->

<thead>

<tr class="bg-[#C89741] text-white text-[13px] font-semibold">

<th class="px-6 py-4 text-left">Club</th>

<th class="px-6 py-4 text-left">Bonus</th>

<th class="px-6 py-4 text-left">Per</th>

<th class="px-6 py-4 text-left">Income</th>

<th class="px-6 py-4 text-left">Date</th>

<th class="px-6 py-4 text-left">Status</th>

</tr>

</thead>


<tbody>


<?php if(!empty($clubRows)): ?>

<?php foreach($clubRows as $row): ?>

<?php
$status = strtolower($row['status']);

if($status=="approved" || $status=="paid"){
$statusClass="bg-green-100 text-green-700";
}
elseif($status=="rejected"){
$statusClass="bg-red-100 text-red-700";
}
else{
$statusClass="bg-yellow-100 text-yellow-700";
}
?>

<tr class="bg-white border-b border-[#eef2f6]">

<td class="px-6 py-4 font-semibold">
<?= $row['club'] ?>
</td>

<td class="px-6 py-4">
₹<?= number_format($row['bonus'],2) ?>
</td>

<td class="px-6 py-4">
<?= $row['per'] ?>
</td>

<td class="px-6 py-4 font-semibold text-[#1f5c34]">
₹<?= number_format($row['income'],2) ?>
</td>

<td class="px-6 py-4">
<?= $row['date'] ?>
</td>

<td class="px-6 py-4">
<span class="px-3 py-1 rounded-full text-xs font-semibold <?= $statusClass ?>">
<?= $row['status'] ?>
</span>
</td>

</tr>

<?php endforeach; ?>


<!-- TOTAL ROW -->

<tr class="bg-[#fafbfd]">

<td colspan="3"
class="px-6 py-4 text-right font-bold text-[#374151]">

Total Earn

</td>

<td class="px-6 py-4 font-bold text-[#1f5c34]">

₹<?= number_format($totalEarn,2) ?>

</td>

<td colspan="2"
class="px-6 py-4 font-bold text-[#374151]">

<?= $totalRecords ?> Records

</td>

</tr>


<?php else: ?>

<tr>

<td colspan="6"
class="px-6 py-10 text-center text-gray-500">

No club bonus records found.

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