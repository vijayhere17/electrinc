<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/config/db.php';

if (!isset($conn) || !($conn instanceof mysqli)) {
    die("Database connection failed.");
}

function e($v) {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function money($v) {
    return '₹' . number_format((float)$v, 2);
}

function safeDate($date, $default) {
    return preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$date) ? $date : $default;
}

$franchiseType = '';
$franchiseId   = '';
$franchiseName = '';

if (!empty($_SESSION['state_franchise_id'])) {
    $franchiseType = 'STATE';
    $franchiseId   = $_SESSION['state_franchise_id'];
    $franchiseName = $_SESSION['state_franchise_name'] ?? $_SESSION['franchise_name'] ?? 'State Franchise';
} elseif (!empty($_SESSION['state_franchies_id'])) {
    $franchiseType = 'STATE';
    $franchiseId   = $_SESSION['state_franchies_id'];
    $franchiseName = $_SESSION['state_franchies_name'] ?? $_SESSION['franchise_name'] ?? 'State Franchise';
} elseif (!empty($_SESSION['city_franchise_id'])) {
    $franchiseType = 'CITY';
    $franchiseId   = $_SESSION['city_franchise_id'];
    $franchiseName = $_SESSION['city_franchise_name'] ?? $_SESSION['franchise_name'] ?? 'City Franchise';
} elseif (!empty($_SESSION['city_franchies_id'])) {
    $franchiseType = 'CITY';
    $franchiseId   = $_SESSION['city_franchies_id'];
    $franchiseName = $_SESSION['city_franchies_name'] ?? $_SESSION['franchise_name'] ?? 'City Franchise';
} elseif (!empty($_SESSION['mobile_franchise_id'])) {
    $franchiseType = 'MOBILE';
    $franchiseId   = $_SESSION['mobile_franchise_id'];
    $franchiseName = $_SESSION['mobile_franchise_name'] ?? $_SESSION['franchise_name'] ?? 'Mobile Franchise';
} elseif (!empty($_SESSION['mobile_franchies_id'])) {
    $franchiseType = 'MOBILE';
    $franchiseId   = $_SESSION['mobile_franchies_id'];
    $franchiseName = $_SESSION['mobile_franchies_name'] ?? $_SESSION['franchise_name'] ?? 'Mobile Franchise';
} elseif (!empty($_SESSION['franchise_type']) && !empty($_SESSION['franchise_id'])) {
    $franchiseType = strtoupper(trim($_SESSION['franchise_type']));
    $franchiseId   = $_SESSION['franchise_id'];
    $franchiseName = $_SESSION['franchise_name'] ?? ($franchiseType . ' Franchise');
}

if ($franchiseType === '' || $franchiseId === '') {
    die('<div style="font-family:sans-serif;padding:40px;color:#c00;">
        <h2>Access Denied</h2>
        <p>You must be logged in as a State, City, or Mobile Franchise to view this report.</p>
        <a href="login.php">Login</a>
    </div>');
}

$originalFranchiseId = $franchiseId;
$franchiseCode = '';

if ($franchiseType === 'STATE') {
    $stmt = $conn->prepare("SELECT franchise_code FROM franchies_holder WHERE id = ? LIMIT 1");
    $stmt->bind_param("i", $originalFranchiseId);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $franchiseCode = $row['franchise_code'];
    }
    $stmt->close();
} elseif ($franchiseType === 'CITY') {
    $stmt = $conn->prepare("SELECT franchies_id FROM city_franchies_holder WHERE id = ? LIMIT 1");
    $stmt->bind_param("i", $originalFranchiseId);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $franchiseCode = $row['franchies_id'];
    }
    $stmt->close();
} elseif ($franchiseType === 'MOBILE') {
    $stmt = $conn->prepare("SELECT mobile_franchies_id FROM mobile_franchies_holder WHERE id = ? LIMIT 1");
    $stmt->bind_param("i", $originalFranchiseId);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $franchiseCode = $row['mobile_franchies_id'];
    }
    $stmt->close();
}

$from     = safeDate($_GET['from'] ?? date('Y-m-01'), date('Y-m-01'));
$to       = safeDate($_GET['to'] ?? date('Y-m-d'), date('Y-m-d'));
$memberId = trim($_GET['member_id'] ?? '');
$product  = trim($_GET['product_name'] ?? '');

$summarySql = "
    SELECT
    cl.sale_id AS order_id,
    cl.created_at,
    fos.member_id,
    fos.member_name,
    SUM(fos.quantity) AS total_products,
    SUM(fos.total_amount) AS total_business,
    SUM(cl.commission_amount) AS total_commission
FROM commission_ledger cl
INNER JOIN franchise_order_sale fos
    ON fos.id = cl.sale_id
    WHERE cl.receiver_type = ?
      AND (cl.receiver_id = ? OR cl.receiver_id = ?)
      AND DATE(cl.created_at) BETWEEN ? AND ?
";

$params = [
    $franchiseType,
    (string)$originalFranchiseId,
    (string)$franchiseCode,
    $from,
    $to
];
$types = 'sssss';

if ($memberId !== '') {
    $summarySql .= " AND fos.member_id LIKE ?";
    $params[] = '%' . $memberId . '%';
    $types .= 's';
}

if ($product !== '') {
    $summarySql .= " AND fos.product_name LIKE ?";
    $params[] = '%' . $product . '%';
    $types .= 's';
}

$summarySql .= "
   GROUP BY cl.sale_id
ORDER BY cl.created_at DESC
";

$stmt = $conn->prepare($summarySql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

$rows = [];
$orderIds = [];
$sumCommission = 0;
$sumBusiness = 0;
$totalProductsSold = 0;
$totalSales = 0;

while ($row = $result->fetch_assoc()) {
    $rows[] = $row;
    $orderIds[] = (int)$row['order_id'];
    $sumCommission += (float)$row['total_commission'];
    $sumBusiness += (float)$row['total_business'];
    $totalProductsSold += (int)$row['total_products'];
    $totalSales++;
}
$stmt->close();

$detailsByOrder = [];

if (!empty($orderIds)) {
   $placeholders = implode(',', array_fill(0, count($orderIds), '?'));

    $detailSql = "
        SELECT
    cl.sale_id AS order_id,
    cl.sale_id,
    fos.product_name,
    fos.total_amount,
    cl.commission_percent,
    cl.commission_amount,
    cl.remarks
FROM commission_ledger cl
INNER JOIN franchise_order_sale fos
    ON fos.id = cl.sale_id
        WHERE cl.receiver_type = ?
          AND (cl.receiver_id = ? OR cl.receiver_id = ?)
          AND DATE(cl.created_at) BETWEEN ? AND ?
          AND cl.sale_id IN ($placeholders)
    ";

    $detailParams = [
        $franchiseType,
        (string)$originalFranchiseId,
        (string)$franchiseCode,
        $from,
        $to
    ];
    $detailTypes = 'sssss';

    if ($memberId !== '') {
        $detailSql .= " AND fos.member_id LIKE ?";
        $detailParams[] = '%' . $memberId . '%';
        $detailTypes .= 's';
    }

    if ($product !== '') {
        $detailSql .= " AND fos.product_name LIKE ?";
        $detailParams[] = '%' . $product . '%';
        $detailTypes .= 's';
    }

    $detailSql .= " ORDER BY cl.sale_id DESC, cl.id ASC";

    $detailParams = array_merge($detailParams, $orderIds);
    $detailTypes .= str_repeat('i', count($orderIds));

    $detailStmt = $conn->prepare($detailSql);
    $detailStmt->bind_param($detailTypes, ...$detailParams);
    $detailStmt->execute();
    $detailResult = $detailStmt->get_result();

    while ($detailRow = $detailResult->fetch_assoc()) {
        $detailsByOrder[(int)$detailRow['order_id']][] = $detailRow;
    }

    $detailStmt->close();
}

$typeLabels = [
    'STATE'  => ['icon' => 'landmark',   'color' => '#1a5276', 'bg' => '#d6e4f0', 'label' => 'State Franchise'],
    'CITY'   => ['icon' => 'building-2', 'color' => '#1e8449', 'bg' => '#d5f5e3', 'label' => 'City Franchise'],
    'MOBILE' => ['icon' => 'smartphone', 'color' => '#7d3c98', 'bg' => '#f4ecf7', 'label' => 'Mobile Franchise'],
];
$typeInfo = $typeLabels[$franchiseType] ?? ['icon' => 'wallet', 'color' => '#cd9737', 'bg' => '#f5eee3', 'label' => $franchiseType . ' Franchise'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Commission Report — <?= e($franchiseName); ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<script src="https://cdn.tailwindcss.com"></script>
<script src="https://unpkg.com/lucide@latest"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
<style>
    @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap');
    body { font-family: 'Plus Jakarta Sans', sans-serif; }
    .stat-card { transition: transform .15s, box-shadow .15s; }
    .stat-card:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(0,0,0,.08); }
    .details-row td { padding-top: 0; }
    @media print {
        .no-print { display: none !important; }
        .sidebar-wrap { margin-left: 0 !important; }
        .details-row { display: none !important; }
    }
</style>
</head>
<body class="bg-[#f3f4f6] overflow-x-hidden">

<?php include __DIR__ . '/includes/sidebar.php'; ?>

<div class="md:ml-64 sidebar-wrap min-h-screen">
<?php include __DIR__ . '/includes/navbar.php'; ?>

<main class="px-3 sm:px-5 py-4 sm:py-5">
<div class="max-w-[1800px] mx-auto">

    <div class="mt-3 sm:mt-5 flex items-center gap-3 mb-5">
        <div class="w-9 h-9 rounded-lg flex items-center justify-center shrink-0" style="background:<?= $typeInfo['bg']; ?>">
            <i data-lucide="<?= $typeInfo['icon']; ?>" class="w-4 h-4" style="color:<?= $typeInfo['color']; ?>"></i>
        </div>
        <div class="min-w-0 flex-1">
            <h2 class="text-base sm:text-[20px] font-semibold text-[#40352a]" style="font-family: Georgia, serif;">COMMISSION REPORT</h2>
            <p class="text-xs text-gray-500 truncate">
                <?= e($typeInfo['label']); ?> — <?= e($franchiseName); ?> (ID: <?= e($originalFranchiseId); ?>)
            </p>
        </div>
        <div class="h-[2px] bg-[#d1a14b] w-16 sm:flex-1 shrink-0"></div>
    </div>

    <div class="grid grid-cols-3 gap-2 sm:gap-4 mb-5">

        <div class="stat-card bg-white rounded-xl border shadow-sm p-3 sm:p-5 flex flex-col sm:flex-row items-center sm:items-start gap-2 sm:gap-4">
            <div class="w-9 h-9 sm:w-11 sm:h-11 rounded-lg bg-green-50 flex items-center justify-center shrink-0">
                <i data-lucide="indian-rupee" class="w-4 h-4 sm:w-5 sm:h-5 text-green-600"></i>
            </div>
            <div class="text-center sm:text-left">
                <p class="text-[10px] sm:text-xs text-gray-500 font-medium uppercase tracking-wide leading-tight">Commission<br class="sm:hidden"> Earned</p>
                <p class="text-sm sm:text-xl font-bold text-green-700 mt-0.5"><?= money($sumCommission); ?></p>
            </div>
        </div>

        <div class="stat-card bg-white rounded-xl border shadow-sm p-3 sm:p-5 flex flex-col sm:flex-row items-center sm:items-start gap-2 sm:gap-4">
            <div class="w-9 h-9 sm:w-11 sm:h-11 rounded-lg bg-blue-50 flex items-center justify-center shrink-0">
                <i data-lucide="package" class="w-4 h-4 sm:w-5 sm:h-5 text-blue-600"></i>
            </div>
            <div class="text-center sm:text-left">
                <p class="text-[10px] sm:text-xs text-gray-500 font-medium uppercase tracking-wide leading-tight">Products<br class="sm:hidden"> Sold</p>
                <p class="text-sm sm:text-xl font-bold text-blue-700 mt-0.5"><?= number_format($totalProductsSold); ?></p>
            </div>
        </div>

        <div class="stat-card bg-white rounded-xl border shadow-sm p-3 sm:p-5 flex flex-col sm:flex-row items-center sm:items-start gap-2 sm:gap-4">
            <div class="w-9 h-9 sm:w-11 sm:h-11 rounded-lg bg-orange-50 flex items-center justify-center shrink-0">
                <i data-lucide="trending-up" class="w-4 h-4 sm:w-5 sm:h-5 text-orange-500"></i>
            </div>
            <div class="text-center sm:text-left">
                <p class="text-[10px] sm:text-xs text-gray-500 font-medium uppercase tracking-wide leading-tight">Total<br class="sm:hidden"> Business</p>
                <p class="text-sm sm:text-xl font-bold text-orange-600 mt-0.5"><?= money($sumBusiness); ?></p>
            </div>
        </div>

    </div>

    <div class="bg-white rounded-xl shadow border p-4 sm:p-5 mb-5 no-print">
        <form method="GET">
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-3">
                <div>
                    <label class="block mb-1 text-xs sm:text-sm font-semibold text-gray-700">From Date</label>
                    <input type="date" name="from" value="<?= e($from); ?>"
                           class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-300">
                </div>
                <div>
                    <label class="block mb-1 text-xs sm:text-sm font-semibold text-gray-700">To Date</label>
                    <input type="date" name="to" value="<?= e($to); ?>"
                           class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-300">
                </div>
                <div>
                    <label class="block mb-1 text-xs sm:text-sm font-semibold text-gray-700">Member ID</label>
                    <input type="text" name="member_id" value="<?= e($memberId); ?>"
                           placeholder="Search member…"
                           class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-300">
                </div>
                <div>
                    <label class="block mb-1 text-xs sm:text-sm font-semibold text-gray-700">Product Name</label>
                    <input type="text" name="product_name" value="<?= e($product); ?>"
                           placeholder="Search product…"
                           class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-300">
                </div>
            </div>
            <div class="flex gap-2">
                <button type="submit"
                        class="bg-[#12395f] text-white px-5 py-2 rounded-lg text-sm font-semibold flex items-center gap-2 hover:bg-[#0e2d4a] transition">
                    <i data-lucide="search" class="w-4 h-4"></i> Search
                </button>
                <a href="commission-report.php"
                   class="bg-gray-100 text-gray-600 px-5 py-2 rounded-lg text-sm font-semibold flex items-center gap-2 hover:bg-gray-200 transition">
                    <i data-lucide="x" class="w-4 h-4"></i> Reset
                </a>
            </div>
        </form>
    </div>

    <div class="bg-white rounded-xl shadow border overflow-hidden">

        <div class="flex flex-col sm:flex-row sm:items-center justify-between px-4 py-3 border-b bg-gray-50 gap-2 no-print">
            <p class="text-sm text-gray-600">
                <strong><?= count($rows); ?></strong> sale records &nbsp;·&nbsp;
                <span class="text-xs text-gray-400">
                    <?= e(date('d M Y', strtotime($from))); ?> – <?= e(date('d M Y', strtotime($to))); ?>
                </span>
            </p>
            <div class="flex gap-2">
                <button type="button" onclick="exportToExcel()"
                        class="bg-green-600 hover:bg-green-700 text-white px-3 py-1.5 rounded-lg text-sm font-semibold flex items-center gap-1.5 transition">
                    <i data-lucide="file-spreadsheet" class="w-4 h-4"></i> Excel
                </button>
                <button type="button" onclick="window.print()"
                        class="bg-gray-500 hover:bg-gray-600 text-white px-3 py-1.5 rounded-lg text-sm font-semibold flex items-center gap-1.5 transition">
                    <i data-lucide="printer" class="w-4 h-4"></i> Print
                </button>
            </div>
        </div>

        <div class="overflow-x-auto -webkit-overflow-scrolling-touch">
            <table class="w-full text-sm" id="commissionTable" style="min-width:1000px;">
                <thead class="bg-[#12395f] text-white">
                    <tr>
                        <th class="px-3 py-3 text-left font-semibold whitespace-nowrap">#</th>
                        <th class="px-3 py-3 text-left font-semibold whitespace-nowrap">Date</th>
                        <th class="px-3 py-3 text-left font-semibold whitespace-nowrap">Sale ID</th>
                        <th class="px-3 py-3 text-left font-semibold whitespace-nowrap">Member ID</th>
                        <th class="px-3 py-3 text-left font-semibold whitespace-nowrap">Member Name</th>
                        <th class="px-3 py-3 text-center font-semibold whitespace-nowrap">Products</th>
                        <th class="px-3 py-3 text-right font-semibold whitespace-nowrap">Business</th>
                        <th class="px-3 py-3 text-right font-semibold whitespace-nowrap">Commission</th>
                        <th class="px-3 py-3 text-center font-semibold whitespace-nowrap no-print">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">

                <?php if (!empty($rows)): ?>
                    <?php foreach ($rows as $i => $row): ?>
                        <?php
                            $orderId = (int)$row['order_id'];
$detailRows = $detailsByOrder[$orderId] ?? [];
                            $detailRowId = 'details-row-' . $orderId;
$buttonId = 'toggle-btn-' . $orderId;
                        ?>
                        <tr class="hover:bg-blue-50 transition-colors">
                            <td class="px-3 py-3 text-gray-400"><?= $i + 1; ?></td>
                            <td class="px-3 py-3 whitespace-nowrap text-gray-600">
                                <?= e(date('d-m-Y', strtotime($row['created_at']))); ?>
                                <br><span class="text-xs text-gray-400"><?= e(date('H:i', strtotime($row['created_at']))); ?></span>
                            </td>
                            <td class="px-3 py-3 font-bold text-blue-700 whitespace-nowrap">#<?= e($row['order_id']); ?></td>
                            <td class="px-3 py-3 text-gray-700 whitespace-nowrap"><?= e($row['member_id']); ?></td>
                            <td class="px-3 py-3 font-medium text-gray-800 whitespace-nowrap"><?= e($row['member_name']); ?></td>
                            <td class="px-3 py-3 text-center whitespace-nowrap">
                                <span class="bg-blue-50 text-blue-700 border border-blue-200 px-2.5 py-1 rounded-full text-xs font-bold inline-block min-w-[90px]">
                                    <?= (int)$row['total_products']; ?> Product<?= ((int)$row['total_products'] !== 1 ? 's' : ''); ?>
                                </span>
                            </td>
                            <td class="px-3 py-3 text-right font-semibold text-gray-700 whitespace-nowrap"><?= money($row['total_business']); ?></td>
                            <td class="px-3 py-3 text-right font-bold text-green-700 whitespace-nowrap"><?= money($row['total_commission']); ?></td>
                            <td class="px-3 py-3 text-center whitespace-nowrap no-print">
                                <button
                                    type="button"
                                    id="<?= e($buttonId); ?>"
                                    class="inline-flex items-center gap-2 bg-[#12395f] text-white px-3 py-1.5 rounded-lg text-xs font-semibold hover:bg-[#0e2d4a] transition"
                                    aria-expanded="false"
                                    aria-controls="<?= e($detailRowId); ?>"
                                    onclick="toggleDetails('<?= e($detailRowId); ?>', this)"
                                >
                                    <i data-lucide="chevron-down" class="w-4 h-4"></i>
                                    View Details
                                </button>
                            </td>
                        </tr>

                        <tr id="<?= e($detailRowId); ?>" class="details-row hidden bg-[#f8fafc]">
                            <td colspan="9" class="px-4 pb-4">
                                <div class="rounded-xl border border-gray-200 bg-white overflow-hidden mt-1">
                                    <div class="px-4 py-3 border-b bg-gray-50 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                                        <div>
                                            <h4 class="text-sm font-bold text-gray-800">Order #<?= e($row['order_id']); ?> Details</h4>
                                            <p class="text-xs text-gray-500">
                                                <?= e($row['member_name']); ?> (<?= e($row['member_id']); ?>)
                                            </p>
                                        </div>
                                        <div class="flex flex-col sm:flex-row gap-2 sm:gap-4 text-xs">
                                            <span class="font-semibold text-gray-600">
                                                Total Business:
                                                <span class="text-orange-600"><?= money($row['total_business']); ?></span>
                                            </span>
                                            <span class="font-semibold text-gray-600">
                                                Total Commission:
                                                <span class="text-green-700"><?= money($row['total_commission']); ?></span>
                                            </span>
                                        </div>
                                    </div>

                                    <div class="overflow-x-auto">
                                        <table class="w-full text-sm" style="min-width:700px;">
                                            <thead class="bg-gray-100 text-gray-700">
                                                <tr>
                                                    <th class="px-3 py-2 text-left font-semibold whitespace-nowrap">#</th>
                                                    <th class="px-3 py-2 text-left font-semibold whitespace-nowrap">Product</th>
                                                    <th class="px-3 py-2 text-right font-semibold whitespace-nowrap">Amount</th>
                                                    <th class="px-3 py-2 text-center font-semibold whitespace-nowrap">Comm %</th>
                                                    <th class="px-3 py-2 text-right font-semibold whitespace-nowrap">Commission</th>
                                                    <th class="px-3 py-2 text-left font-semibold whitespace-nowrap">Remarks</th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-gray-100">
                                                <?php if (!empty($detailRows)): ?>
                                                    <?php foreach ($detailRows as $dIndex => $detail): ?>
                                                        <?php
                                                            $remark = strtolower($detail['remarks'] ?? '');
                                                            $badge = str_contains($remark, 'override')
                                                                ? 'bg-orange-50 text-orange-700 border border-orange-200'
                                                                : 'bg-green-50 text-green-700 border border-green-200';
                                                        ?>
                                                        <tr>
                                                            <td class="px-3 py-2 text-gray-400"><?= $dIndex + 1; ?></td>
                                                            <td class="px-3 py-2 text-gray-800 font-medium"><?= e($detail['product_name']); ?></td>
                                                            <td class="px-3 py-2 text-right whitespace-nowrap"><?= money($detail['total_amount']); ?></td>
                                                            <td class="px-3 py-2 text-center whitespace-nowrap">
                                                                <span class="bg-blue-50 text-blue-700 border border-blue-200 px-2 py-0.5 rounded-full text-xs font-bold">
                                                                    <?= e($detail['commission_percent']); ?>%
                                                                </span>
                                                            </td>
                                                            <td class="px-3 py-2 text-right font-semibold text-green-700 whitespace-nowrap"><?= money($detail['commission_amount']); ?></td>
                                                            <td class="px-3 py-2">
                                                                <span class="<?= $badge; ?> text-xs px-2 py-1 rounded-md whitespace-nowrap">
                                                                    <?= e($detail['remarks']); ?>
                                                                </span>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>

                                                    <tr class="bg-[#f8fafc] font-semibold">
                                                        <td colspan="2" class="px-3 py-3 text-right text-gray-600">Total</td>
                                                        <td class="px-3 py-3 text-right text-orange-600 whitespace-nowrap"><?= money($row['total_business']); ?></td>
                                                        <td class="px-3 py-3 text-center text-gray-400">—</td>
                                                        <td class="px-3 py-3 text-right text-green-700 whitespace-nowrap"><?= money($row['total_commission']); ?></td>
                                                        <td class="px-3 py-3 text-gray-500"><?= count($detailRows); ?> item(s)</td>
                                                    </tr>
                                                <?php else: ?>
                                                    <tr>
                                                        <td colspan="6" class="text-center py-8 text-gray-400">
                                                            No product details found for this sale.
                                                        </td>
                                                    </tr>
                                                <?php endif; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>

                    <tr class="bg-[#eef4fb] border-t-2 border-[#12395f] font-bold">
                        <td colspan="6" class="px-3 py-3 text-right text-gray-700 uppercase text-xs tracking-widest">Grand Total</td>
                        <td class="px-3 py-3 text-right text-gray-800 whitespace-nowrap"><?= money($sumBusiness); ?></td>
                        <td class="px-3 py-3 text-right text-green-700 whitespace-nowrap"><?= money($sumCommission); ?></td>
                        <td class="px-3 py-3 text-xs text-gray-500 no-print"><?= $totalSales; ?> sale(s)</td>
                    </tr>

                <?php else: ?>
                    <tr>
                        <td colspan="9" class="text-center py-14 text-gray-400">
                            <div class="flex flex-col items-center gap-3">
                                <i data-lucide="inbox" class="w-10 h-10 text-gray-300"></i>
                                <span>No commission records found for the selected date range.</span>
                            </div>
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
document.addEventListener("DOMContentLoaded", function () {
    if (typeof lucide !== "undefined") {
        lucide.createIcons();
    }
});

function toggleDetails(rowId, button) {
    const row = document.getElementById(rowId);
    if (!row) return;

    const isOpening = row.classList.contains('hidden');

    document.querySelectorAll('.details-row').forEach(function(detailRow) {
        if (detailRow.id !== rowId) {
            detailRow.classList.add('hidden');
        }
    });

    document.querySelectorAll('button[aria-controls]').forEach(function(btn) {
        if (btn.getAttribute('aria-controls') !== rowId) {
            btn.setAttribute('aria-expanded', 'false');
            btn.innerHTML = `
                <i data-lucide="chevron-down" class="w-4 h-4"></i>
                View Details
            `;
        }
    });

    if (isOpening) {
        row.classList.remove('hidden');
        button.setAttribute('aria-expanded', 'true');
        button.innerHTML = `
            <i data-lucide="chevron-up" class="w-4 h-4"></i>
            Hide Details
        `;
    } else {
        row.classList.add('hidden');
        button.setAttribute('aria-expanded', 'false');
        button.innerHTML = `
            <i data-lucide="chevron-down" class="w-4 h-4"></i>
            View Details
        `;
    }

    if (typeof lucide !== "undefined") {
        lucide.createIcons();
    }
}

function exportToExcel() {
    const originalTable = document.getElementById('commissionTable');
    const clonedTable = originalTable.cloneNode(true);

    clonedTable.querySelectorAll('.details-row').forEach(row => row.remove());
    clonedTable.querySelectorAll('.no-print').forEach(el => el.remove());

    const wb = XLSX.utils.book_new();
    const ws = XLSX.utils.table_to_sheet(clonedTable);

    ws['!cols'] = [
        { wch: 4 },
        { wch: 14 },
        { wch: 10 },
        { wch: 14 },
        { wch: 24 },
        { wch: 12 },
        { wch: 14 },
        { wch: 14 }
    ];

    XLSX.utils.book_append_sheet(wb, ws, 'Commission Report');
    XLSX.writeFile(wb, 'Commission_<?= e($franchiseType); ?>_<?= e($franchiseId); ?>_<?= e($from); ?>_<?= e($to); ?>.xlsx');
}
</script>

</body>
</html>