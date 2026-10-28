<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/includes/inventory.php';
if (!isset($conn) || !($conn instanceof mysqli)) die('Database connection failed.');

$fid = (int)$franchiseId;

/* ---- search units (by name / model / unit no / any identifier) ---- */
$q = trim((string)($_GET['q'] ?? ''));
$hits = $q !== '' ? fr_search_units($conn, $fid, $q, 100) : [];

/* ---- per-product totals + overall ---- */
$rows = [];
$overall = ['total' => 0, 'available' => 0, 'reserved' => 0, 'sold' => 0, 'delivered' => 0, 'returned' => 0, 'damaged' => 0];
$r = $conn->query("SELECT * FROM fr_products WHERE franchise_id = " . $fid . " ORDER BY name ASC");
while ($r && ($p = $r->fetch_assoc())) {
    $c = fr_product_counts($conn, $p);
    $rows[] = ['p' => $p, 'c' => $c];
    foreach ($overall as $k => $_) $overall[$k] += (int)$c[$k];
}

/* ---- movement history (filterable) ---- */
$fType = trim((string)($_GET['type'] ?? ''));
$fProd = (int)($_GET['product'] ?? 0);
$where = 'm.franchise_id = ?'; $par = [$fid]; $ty = 'i';
if ($fType !== '') { $where .= ' AND m.movement_type = ?'; $par[] = $fType; $ty .= 's'; }
if ($fProd > 0)   { $where .= ' AND m.product_id = ?';   $par[] = $fProd; $ty .= 'i'; }
$mv = [];
$s = $conn->prepare("SELECT m.*, p.name AS product_name, u.unit_no
                     FROM fr_stock_movements m
                     JOIN fr_products p ON p.id = m.product_id
                     LEFT JOIN fr_units u ON u.id = m.unit_id
                     WHERE $where ORDER BY m.id DESC LIMIT 300");
$s->bind_param($ty, ...$par); $s->execute();
$rs = $s->get_result();
while ($x = $rs->fetch_assoc()) $mv[] = $x;
$s->close();

$mtypes = ['Received', 'Sold', 'Delivered', 'Reserved', 'Unreserved', 'Returned', 'Damaged', 'Transferred', 'Adjustment', 'Cancelled'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Stock Report</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <script src="assets/report-export.js"></script>
</head>
<body class="bg-[#f3f3f3] font-sans overflow-x-hidden">
<?php include __DIR__ . '/includes/sidebar.php'; ?>
<div class="ml-64 min-h-screen bg-[#f3f3f3]">
    <?php include __DIR__ . '/includes/navbar.php'; ?>
    <main class="px-4 sm:px-5 py-6">
        <div class="max-w-7xl mx-auto space-y-6">

            <div class="bg-[#17457a] rounded-xl px-5 sm:px-6 py-4 text-white shadow flex items-center justify-between flex-wrap gap-3">
                <div>
                    <h1 class="text-[18px] sm:text-[20px] font-semibold">Stock Report</h1>
                    <p class="text-[12px] text-white/80 mt-1"><?php echo e($franchiseName); ?> · <?php echo e($franchiseCode); ?> — this franchise only</p>
                </div>
                <div class="flex gap-2">
                    <a href="inventory.php" class="bg-white/15 hover:bg-white/25 rounded-lg px-3 py-2 text-[12px]">Product Master</a>
                    <a href="stock-entry.php" class="bg-white/15 hover:bg-white/25 rounded-lg px-3 py-2 text-[12px]">Stock Entry</a>
                </div>
            </div>

            <!-- OVERALL -->
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
                <?php foreach (['Total' => 'total', 'Available' => 'available', 'Reserved' => 'reserved', 'Sold' => 'sold', 'Returned' => 'returned', 'Damaged' => 'damaged'] as $lbl => $k): ?>
                    <div class="bg-white border border-gray-200 rounded-xl p-4 shadow-sm">
                        <p class="text-[11px] text-gray-500 font-medium"><?php echo $lbl; ?></p>
                        <p class="mt-1 text-[22px] font-semibold text-[#173a67]"><?php echo (int)$overall[$k]; ?></p>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- SEARCH -->
            <div class="bg-white border border-gray-200 rounded-xl p-5 shadow-sm">
                <form method="get" class="flex gap-2">
                    <input name="q" value="<?php echo e($q); ?>" placeholder="Search: product, model, unit no, chassis, motor, battery, serial, any custom field…" class="flex-1 border border-gray-300 rounded-lg px-3 py-2 text-[13px]">
                    <button class="bg-[#17457a] text-white rounded-lg px-4 py-2 text-[13px]">Search</button>
                    <?php if ($q !== ''): ?><a href="stock-report.php" class="border border-gray-300 bg-white rounded-lg px-4 py-2 text-[13px]">Clear</a><?php endif; ?>
                </form>
                <?php if ($q !== ''): ?>
                    <div class="mt-3 overflow-x-auto">
                        <table class="w-full text-[13px]">
                            <thead class="bg-gray-50 text-gray-500 text-[12px]"><tr>
                                <th class="text-left px-4 py-2">Unit No</th><th class="text-left px-4 py-2">Product</th><th class="text-left px-4 py-2">Status</th><th class="text-left px-4 py-2">Customer</th><th class="text-left px-4 py-2">Received</th><th class="px-4 py-2"></th>
                            </tr></thead>
                            <tbody class="divide-y divide-gray-100">
                                <?php if (empty($hits)): ?><tr><td colspan="6" class="px-4 py-6 text-center text-gray-400">No matching units.</td></tr><?php endif; ?>
                                <?php foreach ($hits as $h): ?>
                                    <tr>
                                        <td class="px-4 py-2 font-medium"><?php echo e($h['unit_no']); ?></td>
                                        <td class="px-4 py-2 text-gray-600"><?php echo e($h['product_name'] . ($h['model'] ? ' / ' . $h['model'] : '')); ?></td>
                                        <td class="px-4 py-2"><span class="text-[11px] rounded-full px-2 py-0.5 bg-gray-100 text-gray-700"><?php echo e($h['status']); ?></span></td>
                                        <td class="px-4 py-2 text-gray-600"><?php echo e($h['customer_name'] ?: '—'); ?></td>
                                        <td class="px-4 py-2 text-gray-500"><?php echo e($h['received_date'] ?: '—'); ?></td>
                                        <td class="px-4 py-2 text-right"><a href="product-unit.php?id=<?php echo (int)$h['id']; ?>" class="text-[12px] text-[#17457a] hover:underline">Open</a></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <!-- PER-PRODUCT -->
            <div class="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden">
                <div class="px-5 py-3 border-b border-gray-100 flex items-center justify-between flex-wrap gap-2">
                    <h2 class="text-[14px] font-semibold text-[#173a67]">By Product</h2>
                    <div class="flex gap-2 no-print">
                        <button type="button" onclick="frExportTableToExcel('byProductTable', 'Stock_By_Product_<?php echo e($franchiseCode); ?>.xlsx', 'By Product')"
                                class="bg-green-600 hover:bg-green-700 text-white px-3 py-1.5 rounded-lg text-[12px] font-semibold flex items-center gap-1.5 transition">
                            <i data-lucide="file-spreadsheet" class="w-4 h-4"></i> Excel
                        </button>
                        <button type="button" onclick="window.print()"
                                class="bg-gray-500 hover:bg-gray-600 text-white px-3 py-1.5 rounded-lg text-[12px] font-semibold flex items-center gap-1.5 transition">
                            <i data-lucide="printer" class="w-4 h-4"></i> Print / PDF
                        </button>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-[13px]" id="byProductTable">
                        <thead class="bg-gray-50 text-gray-500 text-[12px]">
                            <tr><th class="text-left px-5 py-2.5">Product</th><th class="text-right px-5 py-2.5">Total</th><th class="text-right px-5 py-2.5">Available</th><th class="text-right px-5 py-2.5">Reserved</th><th class="text-right px-5 py-2.5">Sold</th><th class="text-right px-5 py-2.5">Returned</th><th class="text-right px-5 py-2.5">Damaged</th></tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <?php if (empty($rows)): ?><tr><td colspan="7" class="px-5 py-8 text-center text-gray-400">No products.</td></tr><?php endif; ?>
                            <?php foreach ($rows as $rw): $p = $rw['p']; $c = $rw['c']; ?>
                                <tr>
                                    <td class="px-5 py-2.5 font-medium"><a href="inventory.php?id=<?php echo (int)$p['id']; ?>" class="text-[#17457a] hover:underline"><?php echo e($p['name']); ?></a></td>
                                    <td class="px-5 py-2.5 text-right font-semibold"><?php echo (int)$c['total']; ?></td>
                                    <td class="px-5 py-2.5 text-right text-green-700"><?php echo (int)$c['available']; ?></td>
                                    <td class="px-5 py-2.5 text-right text-amber-700"><?php echo (int)$c['reserved']; ?></td>
                                    <td class="px-5 py-2.5 text-right"><?php echo (int)($c['sold'] + $c['delivered']); ?></td>
                                    <td class="px-5 py-2.5 text-right"><?php echo (int)$c['returned']; ?></td>
                                    <td class="px-5 py-2.5 text-right text-red-600"><?php echo (int)$c['damaged']; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- MOVEMENT HISTORY -->
            <div class="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden">
                <div class="px-5 py-3 border-b border-gray-100 flex items-center justify-between flex-wrap gap-2">
                    <h2 class="text-[14px] font-semibold text-[#173a67]">Stock Movement History</h2>
                    <form method="get" class="flex gap-2 text-[12px] no-print">
                        <?php if ($q !== ''): ?><input type="hidden" name="q" value="<?php echo e($q); ?>"><?php endif; ?>
                        <select name="type" class="border border-gray-300 rounded px-2 py-1.5">
                            <option value="">All types</option>
                            <?php foreach ($mtypes as $t): ?><option value="<?php echo $t; ?>" <?php echo $fType === $t ? 'selected' : ''; ?>><?php echo $t; ?></option><?php endforeach; ?>
                        </select>
                        <select name="product" class="border border-gray-300 rounded px-2 py-1.5">
                            <option value="0">All products</option>
                            <?php foreach ($rows as $rw): ?><option value="<?php echo (int)$rw['p']['id']; ?>" <?php echo $fProd === (int)$rw['p']['id'] ? 'selected' : ''; ?>><?php echo e($rw['p']['name']); ?></option><?php endforeach; ?>
                        </select>
                        <button class="bg-[#17457a] text-white rounded px-3 py-1.5">Filter</button>
                    </form>
                    <div class="flex gap-2 no-print">
                        <button type="button" onclick="frExportTableToExcel('movementTable', 'Stock_Movements_<?php echo e($franchiseCode); ?>.xlsx', 'Movements')"
                                class="bg-green-600 hover:bg-green-700 text-white px-3 py-1.5 rounded-lg text-[12px] font-semibold flex items-center gap-1.5 transition">
                            <i data-lucide="file-spreadsheet" class="w-4 h-4"></i> Excel
                        </button>
                        <button type="button" onclick="window.print()"
                                class="bg-gray-500 hover:bg-gray-600 text-white px-3 py-1.5 rounded-lg text-[12px] font-semibold flex items-center gap-1.5 transition">
                            <i data-lucide="printer" class="w-4 h-4"></i> Print / PDF
                        </button>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-[13px]" id="movementTable">
                        <thead class="bg-gray-50 text-gray-500 text-[12px]">
                            <tr>
                                <th class="text-left px-5 py-2.5">Date</th><th class="text-left px-5 py-2.5">Type</th><th class="text-left px-5 py-2.5">Product</th>
                                <th class="text-left px-5 py-2.5">Unit</th><th class="text-right px-5 py-2.5">Qty</th><th class="text-right px-5 py-2.5">Bal.</th>
                                <th class="text-left px-5 py-2.5">Reason</th><th class="text-left px-5 py-2.5">Reference</th><th class="text-left px-5 py-2.5">By</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <?php if (empty($mv)): ?><tr><td colspan="9" class="px-5 py-8 text-center text-gray-400">No movements recorded yet.</td></tr><?php endif; ?>
                            <?php foreach ($mv as $m): ?>
                                <tr>
                                    <td class="px-5 py-2.5 whitespace-nowrap text-gray-500"><?php echo e(substr((string)$m['created_at'], 0, 16)); ?></td>
                                    <td class="px-5 py-2.5"><span class="text-[11px] rounded-full px-2 py-0.5 bg-gray-100 text-gray-700"><?php echo e($m['movement_type']); ?></span></td>
                                    <td class="px-5 py-2.5 text-gray-700"><?php echo e($m['product_name']); ?></td>
                                    <td class="px-5 py-2.5 text-gray-500"><?php echo e($m['unit_no'] ?: '—'); ?></td>
                                    <td class="px-5 py-2.5 text-right font-medium <?php echo (int)$m['qty_change'] < 0 ? 'text-red-600' : 'text-green-700'; ?>"><?php echo (int)$m['qty_change'] > 0 ? '+' : ''; ?><?php echo (int)$m['qty_change']; ?></td>
                                    <td class="px-5 py-2.5 text-right text-gray-500"><?php echo $m['balance_after'] === null ? '—' : (int)$m['balance_after']; ?></td>
                                    <td class="px-5 py-2.5 text-gray-600"><?php echo e($m['reason'] ?: '—'); ?></td>
                                    <td class="px-5 py-2.5 text-gray-500"><?php echo e($m['reference'] ?: '—'); ?></td>
                                    <td class="px-5 py-2.5 text-gray-500"><?php echo e($m['performed_by'] ?: '—'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </main>
</div>
<script>if (typeof lucide !== 'undefined') lucide.createIcons();</script>
</body>
</html>
