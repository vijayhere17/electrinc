<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/config/db.php';

if (!function_exists('e')) {
    function e($v) {
        return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    }
}

$selfPage = basename($_SERVER['PHP_SELF']);

/*
|--------------------------------------------------------------------------
| SAFETY: MAKE SURE THE LOG TABLE EXISTS
|--------------------------------------------------------------------------
| (It is also created in franchies_product_add_list.php, this is just a
| safeguard in case this report is opened first.)
*/

$conn->query("
    CREATE TABLE IF NOT EXISTS franchies_product_stock_log (
        id INT AUTO_INCREMENT PRIMARY KEY,
        product_id INT NOT NULL,
        previous_quantity INT NOT NULL DEFAULT 0,
        added_quantity INT NOT NULL DEFAULT 0,
        new_quantity INT NOT NULL DEFAULT 0,
        added_by VARCHAR(100) NOT NULL DEFAULT 'Admin',
        remarks VARCHAR(255) DEFAULT NULL,
        created_at DATETIME NOT NULL,
        KEY idx_product_id (product_id),
        KEY idx_created_at (created_at)
    )
");

/*
|--------------------------------------------------------------------------
| FILTERS
|--------------------------------------------------------------------------
*/

$filterProductId = isset($_GET['product_id']) ? (int)$_GET['product_id'] : 0;
$filterFrom       = trim($_GET['from'] ?? '');
$filterTo         = trim($_GET['to'] ?? '');

$where  = [];
$params = [];
$types  = '';

if ($filterProductId > 0) {
    $where[]  = "l.product_id = ?";
    $params[] = $filterProductId;
    $types   .= 'i';
}

if ($filterFrom !== '') {
    $where[]  = "DATE(l.created_at) >= ?";
    $params[] = $filterFrom;
    $types   .= 's';
}

if ($filterTo !== '') {
    $where[]  = "DATE(l.created_at) <= ?";
    $params[] = $filterTo;
    $types   .= 's';
}

$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

/*
|--------------------------------------------------------------------------
| PRODUCT DROPDOWN
|--------------------------------------------------------------------------
*/

$productOptions = [];
$prodRes = $conn->query("SELECT id, product_name FROM franchies_add_product ORDER BY product_name ASC");
if ($prodRes) {
    while ($p = $prodRes->fetch_assoc()) {
        $productOptions[] = $p;
    }
}

/*
|--------------------------------------------------------------------------
| FETCH LOG ROWS
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT
        l.id,
        l.product_id,
        l.previous_quantity,
        l.added_quantity,
        l.new_quantity,
        l.added_by,
        l.remarks,
        l.created_at,
        p.product_name
    FROM franchies_product_stock_log l
    LEFT JOIN franchies_add_product p ON p.id = l.product_id
    $whereSql
    ORDER BY l.created_at DESC, l.id DESC
";

$logs = [];

if ($params) {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $logs[] = $row;
    }
    $stmt->close();
} else {
    $res = $conn->query($sql);
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $logs[] = $row;
        }
    }
}

/*
|--------------------------------------------------------------------------
| SUMMARY (respects current filters)
|--------------------------------------------------------------------------
*/

$totalAdded = 0;
$totalTransactions = count($logs);
foreach ($logs as $l) {
    if ((int)$l['added_quantity'] > 0) {
        $totalAdded += (int)$l['added_quantity'];
    }
}

$currentTotalStockRes = $conn->query("SELECT COALESCE(SUM(quantity), 0) AS total FROM franchies_add_product");
$currentTotalStock = 0;
if ($currentTotalStockRes && ($row = $currentTotalStockRes->fetch_assoc())) {
    $currentTotalStock = (int)$row['total'];
}
?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>Product Stock Report</title>

<script src="https://cdn.tailwindcss.com"></script>

<script src="https://unpkg.com/lucide@latest"></script>

</head>

<body class="bg-[#f3f4f6] font-sans overflow-x-hidden">

<!-- SIDEBAR -->

<?php include __DIR__ . "/includes/sidebar.php"; ?>

<!-- MAIN -->

<div class="lg:ml-64 min-h-screen bg-[#f3f4f6]">

    <!-- NAVBAR -->

    <?php include __DIR__ . "/includes/navbar.php"; ?>

    <!-- CONTENT -->

    <main class="px-4 sm:px-6 lg:px-8 py-8">

        <!-- PAGE TITLE -->

        <div class="flex items-center gap-4 mb-6">

            <div class="w-9 h-9 rounded-[8px] border border-[#ead9b5] bg-[#f8f1e7] flex items-center justify-center">

                <i data-lucide="clipboard-list"
                   class="w-5 h-5 text-[#c79435]"></i>

            </div>

            <h2 class="text-[22px] sm:text-[24px] font-normal text-[#313131] tracking-wide"
                style="font-family: Georgia, 'Times New Roman', serif;">

                PRODUCT STOCK REPORT

            </h2>

            <div class="h-[1px] bg-[#c9963b] flex-1"></div>

            <a href="franchies_product_add_list.php"
               class="inline-flex items-center gap-2 rounded-[10px] bg-[#313131] hover:bg-black text-white text-sm font-semibold px-4 py-2 whitespace-nowrap transition">
                <i data-lucide="arrow-left" class="w-4 h-4"></i>
                Back to Products
            </a>

        </div>

        <!-- SUMMARY -->

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-8">

            <div class="bg-white rounded-2xl shadow-sm p-5 flex items-center gap-4">
                <div class="w-11 h-11 rounded-xl bg-blue-50 flex items-center justify-center">
                    <i data-lucide="boxes" class="w-5 h-5 text-blue-600"></i>
                </div>
                <div>
                    <p class="text-xs text-slate-500 font-semibold uppercase tracking-wide">Current Total Stock</p>
                    <p class="text-xl font-bold text-slate-800"><?php echo (int)$currentTotalStock; ?></p>
                </div>
            </div>

            <div class="bg-white rounded-2xl shadow-sm p-5 flex items-center gap-4">
                <div class="w-11 h-11 rounded-xl bg-green-50 flex items-center justify-center">
                    <i data-lucide="trending-up" class="w-5 h-5 text-green-600"></i>
                </div>
                <div>
                    <p class="text-xs text-slate-500 font-semibold uppercase tracking-wide">Total Stock Added (filtered)</p>
                    <p class="text-xl font-bold text-slate-800">+<?php echo (int)$totalAdded; ?></p>
                </div>
            </div>

            <div class="bg-white rounded-2xl shadow-sm p-5 flex items-center gap-4">
                <div class="w-11 h-11 rounded-xl bg-amber-50 flex items-center justify-center">
                    <i data-lucide="list-ordered" class="w-5 h-5 text-amber-600"></i>
                </div>
                <div>
                    <p class="text-xs text-slate-500 font-semibold uppercase tracking-wide">Transactions (filtered)</p>
                    <p class="text-xl font-bold text-slate-800"><?php echo (int)$totalTransactions; ?></p>
                </div>
            </div>

        </div>

        <!-- FILTERS -->

        <div class="bg-white rounded-2xl shadow-sm p-4 sm:p-6 mb-8">

            <form method="GET" action="<?php echo e($selfPage); ?>"
                  class="grid grid-cols-1 sm:grid-cols-4 gap-4 items-end">

                <div>
                    <label class="block text-sm font-semibold text-slate-600 mb-1">Product</label>
                    <select name="product_id"
                            class="w-full h-[42px] rounded-[10px] border border-[#d4d6db] bg-[#e8e9eb] px-3 text-sm text-[#4a4a4a] outline-none">
                        <option value="0">All Products</option>
                        <?php foreach ($productOptions as $opt): ?>
                            <option value="<?php echo (int)$opt['id']; ?>"
                                <?php echo $filterProductId === (int)$opt['id'] ? 'selected' : ''; ?>>
                                <?php echo e($opt['product_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-slate-600 mb-1">From Date</label>
                    <input type="date" name="from" value="<?php echo e($filterFrom); ?>"
                           class="w-full h-[42px] rounded-[10px] border border-[#d4d6db] bg-[#e8e9eb] px-3 text-sm text-[#4a4a4a] outline-none">
                </div>

                <div>
                    <label class="block text-sm font-semibold text-slate-600 mb-1">To Date</label>
                    <input type="date" name="to" value="<?php echo e($filterTo); ?>"
                           class="w-full h-[42px] rounded-[10px] border border-[#d4d6db] bg-[#e8e9eb] px-3 text-sm text-[#4a4a4a] outline-none">
                </div>

                <div class="flex gap-2">
                    <button type="submit"
                            class="flex-1 h-[42px] rounded-[10px] bg-[#be8b36] hover:bg-[#a97a2f] text-white text-sm font-semibold transition">
                        Filter
                    </button>
                    <a href="<?php echo e($selfPage); ?>"
                       class="flex-1 h-[42px] rounded-[10px] bg-gray-200 hover:bg-gray-300 text-slate-700 text-sm font-semibold transition inline-flex items-center justify-center">
                        Reset
                    </a>
                </div>

            </form>

        </div>

        <!-- TABLE -->

        <div class="bg-white rounded-2xl shadow-sm overflow-hidden">

            <div class="overflow-x-auto">

                <table class="w-full border-collapse min-w-[900px]">

                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-4 py-4 text-left text-sm font-bold text-slate-700">#</th>
                            <th class="px-4 py-4 text-left text-sm font-bold text-slate-700">Product Name</th>
                            <th class="px-4 py-4 text-left text-sm font-bold text-slate-700">Before Stock</th>
                            <th class="px-4 py-4 text-left text-sm font-bold text-slate-700">Change</th>
                            <th class="px-4 py-4 text-left text-sm font-bold text-slate-700">After Stock</th>
                            <th class="px-4 py-4 text-left text-sm font-bold text-slate-700">Added By</th>
                            <th class="px-4 py-4 text-left text-sm font-bold text-slate-700">Remarks</th>
                            <th class="px-4 py-4 text-left text-sm font-bold text-slate-700">Date &amp; Time</th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php if (!empty($logs)): ?>

                            <?php $i = 1; ?>

                            <?php foreach ($logs as $row): ?>

                                <?php $change = (int)$row['added_quantity']; ?>

                                <tr class="border-t border-gray-200 hover:bg-slate-50">

                                    <td class="px-4 py-4 text-sm"><?php echo $i++; ?></td>

                                    <td class="px-4 py-4 text-sm font-semibold text-slate-800">
                                        <?php echo e($row['product_name'] ?? '(Deleted Product)'); ?>
                                    </td>

                                    <td class="px-4 py-4 text-sm text-slate-600">
                                        <?php echo (int)$row['previous_quantity']; ?>
                                    </td>

                                    <td class="px-4 py-4 text-sm font-bold <?php echo $change >= 0 ? 'text-green-600' : 'text-red-600'; ?>">
                                        <?php echo $change >= 0 ? '+' . $change : $change; ?>
                                    </td>

                                    <td class="px-4 py-4 text-sm font-semibold text-blue-700">
                                        <?php echo (int)$row['new_quantity']; ?>
                                    </td>

                                    <td class="px-4 py-4 text-sm text-slate-600">
                                        <?php echo e($row['added_by']); ?>
                                    </td>

                                    <td class="px-4 py-4 text-sm text-slate-500">
                                        <?php echo $row['remarks'] !== null && $row['remarks'] !== '' ? e($row['remarks']) : '-'; ?>
                                    </td>

                                    <td class="px-4 py-4 text-sm whitespace-nowrap text-slate-500">
                                        <?php echo e($row['created_at']); ?>
                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        <?php else: ?>

                            <tr>
                                <td colspan="8" class="px-4 py-10 text-center text-gray-500">
                                    No stock movements found for the selected filters.
                                </td>
                            </tr>

                        <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </div>

    </main>

</div>

<script>
lucide.createIcons();
</script>

</body>
</html>