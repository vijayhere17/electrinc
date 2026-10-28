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

/* MEMBER TDS */
$sql = "
SELECT
'Member' AS source_type,
customer_id AS code,
full_name AS name,
closing_from,
closing_to,
gross_income AS gross,
tds_amount AS tds,
status
FROM member_payout_closing
WHERE 1=1
";

if ($associateId != '') {
    $sql .= " AND customer_id LIKE '%".$conn->real_escape_string($associateId)."%'";
}

if ($status != '') {
    $sql .= " AND status = '".$conn->real_escape_string($status)."'";
}

if ($fromDate != '') {
    $sql .= " AND closing_from >= '".$conn->real_escape_string($fromDate)."'";
}

if ($toDate != '') {
    $sql .= " AND closing_to <= '".$conn->real_escape_string($toDate)."'";
}

$res = $conn->query($sql);

while ($r = $res->fetch_assoc()) {
    $rows[] = $r;
}

/* FRANCHISE TDS */
$sql = "
SELECT
'Franchise' AS source_type,
franchise_code AS code,
franchise_name AS name,
closing_from,
closing_to,
gross_commission AS gross,
tds_amount AS tds,
status
FROM franchise_payout_closing
WHERE 1=1
";

if ($associateId != '') {
    $sql .= " AND franchise_code LIKE '%".$conn->real_escape_string($associateId)."%'";
}

if ($status != '') {
    $sql .= " AND status = '".$conn->real_escape_string($status)."'";
}

if ($fromDate != '') {
    $sql .= " AND closing_from >= '".$conn->real_escape_string($fromDate)."'";
}

if ($toDate != '') {
    $sql .= " AND closing_to <= '".$conn->real_escape_string($toDate)."'";
}

$res = $conn->query($sql);

while ($r = $res->fetch_assoc()) {
    $rows[] = $r;
}

usort($rows, function($a, $b) {
    return strtotime($b['closing_to']) - strtotime($a['closing_to']);
});
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TDS Report</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body class="m-0 bg-[#f3f4f6] font-sans overflow-x-hidden">

    <?php include __DIR__ . '/includes/sidebar.php'; ?>

    <div class="ml-64 min-h-screen w-[calc(100%-16rem)]">
        <?php include __DIR__ . '/includes/navbar.php'; ?>

        <main class="px-5 py-4">

            <div class="flex items-center gap-4 mb-4">
                <div class="w-9 h-9 rounded-[8px] bg-[#f6f2ea] flex items-center justify-center shrink-0">
                    <i data-lucide="receipt-indian-rupee" class="w-4 h-4 text-[#c9983b]"></i>
                </div>

                <h2 class="text-[17px] md:text-[18px] leading-none text-[#2f2f2f] font-normal whitespace-nowrap"
                    style="font-family: Georgia, 'Times New Roman', serif;">
                    TDS REPORT
                </h2>

                <div class="h-[1px] bg-[#c9983b] flex-1"></div>
            </div>

            <div class="bg-white border border-[#dfdfdf] rounded-[12px] min-h-[405px] px-4 py-4">

                <form method="GET" class="space-y-4">

                    <div class="flex flex-wrap items-center gap-x-6 gap-y-4 text-[16px] text-[#3a3a3a] font-medium">
                        <div class="flex items-center gap-3">
                            <label class="whitespace-nowrap font-medium text-[16px] text-[#3a3a3a] font-medium">Member / Franchise Code:</label>
                            <input
                                type="text"
                                name="associate_id"
                                value="<?php echo htmlspecialchars($associateId); ?>"
                                class="w-[156px] h-[24px] rounded-[6px] border border-[#d8dce2] bg-[#f2f4f7] px-3 text-[15px] outline-none"
                            >
                        </div>

                        <div class="flex items-center gap-3">
                            <label class="whitespace-nowrap font-medium text-[16px] text-[#3a3a3a] font-medium">Status:</label>
                            <select
                                name="status"
                                class="w-[152px] h-[24px] rounded-[6px] border border-[#d8dce2] bg-[#f2f4f7] px-2 text-[15px] text-[#666] outline-none"
                            >
                               <option value=""></option>
<option value="Pending" <?php if ($status === 'Pending') echo 'selected'; ?>>Pending</option>
<option value="Paid" <?php if ($status === 'Paid') echo 'selected'; ?>>Paid</option>
<option value="Hold" <?php if ($status === 'Hold') echo 'selected'; ?>>Hold</option>
                            </select>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center gap-x-4 gap-y-4 text-[16px] text-[#3a3a3a] font-medium">
                        <div class="flex items-center gap-2">
                            <label class="whitespace-nowrap font-medium text-[16px] text-[#3a3a3a] font-medium">Closing Date:</label>

                            <span class="text-[#b1b1b1] text-[15px]">From:</span>
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
                            <i data-lucide="search" class="w-3 h-3"></i>
                            <span>Search</span>
                        </button>
                    </div>

                </form>
                <?php
$totalGross = 0;
$totalTds = 0;

foreach ($rows as $r) {
    $totalGross += (float)$r['gross'];
    $totalTds += (float)$r['tds'];
}
?>

<div class="flex flex-wrap gap-3 mb-4">
    <div class="bg-green-50 border border-green-200 px-4 py-2 rounded-lg">
        <span class="font-semibold text-green-700">
            Gross Amount: ₹<?= number_format($totalGross,2) ?>
        </span>
    </div>

    <div class="bg-red-50 border border-red-200 px-4 py-2 rounded-lg">
        <span class="font-semibold text-red-700">
            Total TDS: ₹<?= number_format($totalTds,2) ?>
        </span>
    </div>

   
</div>

<div class="mt-5 rounded-[6px] overflow-hidden border border-[#d6a246]">
    <div class="overflow-x-auto">
        <table class="w-full min-w-[1100px] border-collapse">
            <thead>
                <tr class="bg-[#cd9737] text-white text-[15px] h-[36px]">
                    <th class="px-3 font-medium text-left">Type</th>
                    <th class="px-3 font-medium text-left">Code</th>
                    <th class="px-3 font-medium text-left">Name</th>
                    <th class="px-3 font-medium text-left">Period</th>
                    <th class="px-3 font-medium text-left">Gross Income</th>
                    <th class="px-3 font-medium text-left">TDS Amount</th>
                    <th class="px-3 font-medium text-left">Status</th>
                </tr>
            </thead>

            <tbody>

            <?php if (!empty($rows)): ?>

                <?php foreach ($rows as $row): ?>

                    <tr class="h-[42px] text-[14px] text-[#2d2d2d] border-t border-[#ececec] hover:bg-[#fafafa]">

                        <td class="px-3">
                            <?php if (($row['source_type'] ?? '') == 'Member'): ?>
                                <span class="bg-blue-100 text-blue-700 px-3 py-1 rounded-full text-xs font-semibold">
                                    Member
                                </span>
                            <?php else: ?>
                                <span class="bg-purple-100 text-purple-700 px-3 py-1 rounded-full text-xs font-semibold">
                                    Franchise
                                </span>
                            <?php endif; ?>
                        </td>

                        <td class="px-3 font-semibold text-blue-600">
                            <?= htmlspecialchars($row['code'] ?? '') ?>
                        </td>

                        <td class="px-3">
                            <?= htmlspecialchars($row['name'] ?? '') ?>
                        </td>

                        <td class="px-3 whitespace-nowrap">
                            <?= date('d-m-Y', strtotime($row['closing_from'])) ?>
                            →
                            <?= date('d-m-Y', strtotime($row['closing_to'])) ?>
                        </td>

                        <td class="px-3 font-semibold text-green-700">
                            ₹<?= number_format((float)$row['gross'], 2) ?>
                        </td>

                        <td class="px-3 font-semibold text-red-600">
                            ₹<?= number_format((float)$row['tds'], 2) ?>
                        </td>

                        <td class="px-3">

                            <?php
                            $status = $row['status'] ?? '';

                            if ($status == 'Paid') {
                                echo '<span class="bg-green-100 text-green-700 px-3 py-1 rounded-full text-xs font-semibold">Paid</span>';
                            } elseif ($status == 'Hold') {
                                echo '<span class="bg-red-100 text-red-700 px-3 py-1 rounded-full text-xs font-semibold">Hold</span>';
                            } else {
                                echo '<span class="bg-yellow-100 text-yellow-700 px-3 py-1 rounded-full text-xs font-semibold">Pending</span>';
                            }
                            ?>

                        </td>

                    </tr>

                <?php endforeach; ?>

                <tr class="bg-[#fff8ea] font-bold border-t-2 border-[#cd9737]">
                    <td colspan="4" class="px-3 py-3 text-right">
                        GRAND TOTAL
                    </td>

                    <td class="px-3 py-3 text-green-700">
                        ₹<?= number_format($totalGross, 2) ?>
                    </td>

                    <td class="px-3 py-3 text-red-600">
                        ₹<?= number_format($totalTds, 2) ?>
                    </td>

                    <td></td>
                </tr>

            <?php else: ?>

                <tr>
                    <td colspan="7" class="h-[250px] text-center text-gray-400">
                        No TDS records found
                    </td>
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
