<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/includes/inventory.php';
if (!isset($conn) || !($conn instanceof mysqli)) die('Database connection failed.');

$fid   = (int)$franchiseId;
$fcode = (string)$franchiseCode;
$by    = (string)($franchiseName ?: $franchiseCode ?: ('FR' . $fid));

$msg = ''; $err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'receive') {
    $r = fr_stock_receive($conn, $fid, $fcode, (int)($_POST['product_id'] ?? 0), (int)($_POST['quantity'] ?? 0), [
        'received_date'    => $_POST['received_date'] ?? '',
        'reference_no'     => $_POST['reference_no'] ?? '',
        'vendor_id'       => $_POST['vendor_id'] ?? 0,
        'source'          => $_POST['source'] ?? '',
        'purchase_details' => $_POST['purchase_details'] ?? '',
        'remarks'         => $_POST['remarks'] ?? '',
    ], $by);
    if (!empty($r['ok'])) { $msg = $r['message']; } else { $err = $r['message']; }
}

$preProduct = (int)($_GET['product_id'] ?? 0);

$prodList = [];
$r = $conn->query("SELECT id, name, model, is_serialized FROM fr_products WHERE franchise_id = " . $fid . " AND status = 'Active' ORDER BY name ASC");
while ($r && ($x = $r->fetch_assoc())) $prodList[] = $x;

$vendors = function_exists('frv_active_vendors') ? frv_active_vendors($conn, $fid) : [];

$entries = [];
$s = $conn->prepare("SELECT e.*, p.name AS product_name, v.vendor_name
                     FROM fr_stock_entries e
                     JOIN fr_products p ON p.id = e.product_id
                     LEFT JOIN fr_vendors v ON v.id = e.vendor_id AND v.franchise_id = e.franchise_id
                     WHERE e.franchise_id = ? ORDER BY e.id DESC LIMIT 100");
$s->bind_param('i', $fid); $s->execute();
$rs = $s->get_result();
while ($x = $rs->fetch_assoc()) $entries[] = $x;
$s->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Stock Entry</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body class="bg-[#f3f3f3] font-sans overflow-x-hidden">
<?php include __DIR__ . '/includes/sidebar.php'; ?>
<div class="ml-64 min-h-screen bg-[#f3f3f3]">
    <?php include __DIR__ . '/includes/navbar.php'; ?>
    <main class="px-4 sm:px-5 py-6">
        <div class="max-w-7xl mx-auto space-y-6">

            <div class="bg-[#17457a] rounded-xl px-5 sm:px-6 py-4 text-white shadow flex items-center justify-between flex-wrap gap-3">
                <div>
                    <h1 class="text-[18px] sm:text-[20px] font-semibold">Stock Entry — Receive Products</h1>
                    <p class="text-[12px] text-white/80 mt-1">Products received from company / state franchise are added to <?php echo e($franchiseName); ?>'s stock.</p>
                </div>
                <a href="inventory.php" class="bg-white/15 hover:bg-white/25 rounded-lg px-3 py-2 text-[12px]">Product Master</a>
            </div>

            <?php if ($msg): ?><div class="rounded-lg border border-green-200 bg-green-50 px-4 py-2.5 text-green-700 text-[13px]"><?php echo e($msg); ?></div><?php endif; ?>
            <?php if ($err): ?><div class="rounded-lg border border-red-200 bg-red-50 px-4 py-2.5 text-red-700 text-[13px]"><?php echo e($err); ?></div><?php endif; ?>

            <?php if (empty($prodList)): ?>
                <div class="bg-white border border-gray-200 rounded-xl p-6 text-center text-gray-500 text-[13px]">
                    No active products. <a href="inventory.php" class="text-[#17457a] underline">Add a product</a> first, then receive stock.
                </div>
            <?php else: ?>
            <form method="post" class="bg-white border border-gray-200 rounded-xl p-5 shadow-sm">
                <input type="hidden" name="action" value="receive">
                <h2 class="text-[14px] font-semibold text-[#173a67] mb-3">Receive Stock</h2>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 text-[13px]">
                    <label class="block sm:col-span-2 lg:col-span-1"><span class="text-gray-500 text-[11px]">Product *</span>
                        <select name="product_id" required class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2">
                            <option value="">Select…</option>
                            <?php foreach ($prodList as $p): ?>
                                <option value="<?php echo (int)$p['id']; ?>" <?php echo $preProduct === (int)$p['id'] ? 'selected' : ''; ?>>
                                    <?php echo e($p['name'] . ($p['model'] ? ' / ' . $p['model'] : '')); ?> <?php echo (int)$p['is_serialized'] === 1 ? '(unit-tracked)' : '(qty)'; ?>
                                </option>
                            <?php endforeach; ?>
                        </select></label>
                    <label class="block"><span class="text-gray-500 text-[11px]">Quantity *</span>
                        <input name="quantity" type="number" min="1" value="1" required class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2"></label>
                    <label class="block"><span class="text-gray-500 text-[11px]">Received Date</span>
                        <input name="received_date" type="date" value="<?php echo date('Y-m-d'); ?>" class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2"></label>
                    <label class="block"><span class="text-gray-500 text-[11px]">Invoice / Reference No.</span>
                        <input name="reference_no" class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2"></label>
                    <label class="block"><span class="text-gray-500 text-[11px]">Vendor</span>
                        <select name="vendor_id" id="vendor_id" class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2">
                            <option value="0">— none / other source —</option>
                            <?php foreach ($vendors as $v): ?>
                                <option value="<?php echo (int)$v['id']; ?>"><?php echo e($v['vendor_name'] . ($v['company_name'] ? ' — ' . $v['company_name'] : '')); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <span class="text-[10px] text-gray-400">Only active vendors are listed. <a href="vendors.php" class="text-[#17457a] underline">Manage vendors</a></span></label>
                    <label class="block"><span class="text-gray-500 text-[11px]">Other source (if no vendor)</span>
                        <input name="source" list="srcs" placeholder="Company / State Franchise" class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2">
                        <datalist id="srcs"><option value="Company"><option value="State Franchise"></datalist></label>
                    <label class="block sm:col-span-2 lg:col-span-1"><span class="text-gray-500 text-[11px]">Purchase / Transfer details</span>
                        <input name="purchase_details" class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2"></label>
                    <label class="block sm:col-span-2 lg:col-span-3"><span class="text-gray-500 text-[11px]">Remarks</span>
                        <textarea name="remarks" rows="2" class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2"></textarea></label>
                </div>
                <p class="mt-2 text-[11px] text-gray-400">For unit-tracked products, one unit row is created per quantity (identifiers can be filled from each unit's page).</p>
                <button class="mt-3 bg-[#17457a] text-white rounded-lg px-6 py-2 text-[13px]">Receive &amp; Add to Stock</button>
            </form>
            <?php endif; ?>

            <div class="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden">
                <div class="px-5 py-3 border-b border-gray-100"><h2 class="text-[14px] font-semibold text-[#173a67]">Recent Stock Entries</h2></div>
                <div class="overflow-x-auto">
                    <table class="w-full text-[13px]">
                        <thead class="bg-gray-50 text-gray-500 text-[12px]">
                            <tr><th class="text-left px-5 py-2.5">#</th><th class="text-left px-5 py-2.5">Date</th><th class="text-left px-5 py-2.5">Product</th><th class="text-right px-5 py-2.5">Qty</th><th class="text-left px-5 py-2.5">Invoice / Ref</th><th class="text-left px-5 py-2.5">Vendor / Source</th><th class="text-left px-5 py-2.5">By</th></tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <?php if (empty($entries)): ?><tr><td colspan="7" class="px-5 py-8 text-center text-gray-400">No stock entries yet.</td></tr><?php endif; ?>
                            <?php foreach ($entries as $en): ?>
                                <tr>
                                    <td class="px-5 py-2.5 text-gray-400"><?php echo (int)$en['id']; ?></td>
                                    <td class="px-5 py-2.5 whitespace-nowrap"><?php echo e($en['received_date'] ?: substr((string)$en['created_at'], 0, 10)); ?></td>
                                    <td class="px-5 py-2.5 font-medium"><?php echo e($en['product_name']); ?></td>
                                    <td class="px-5 py-2.5 text-right font-semibold">+<?php echo (int)$en['quantity']; ?></td>
                                    <td class="px-5 py-2.5 text-gray-600"><?php echo e($en['reference_no'] ?: '—'); ?></td>
                                    <td class="px-5 py-2.5 text-gray-600">
                                        <?php if (!empty($en['vendor_name'])): ?>
                                            <a href="vendors.php?id=<?php echo (int)$en['vendor_id']; ?>" class="text-[#17457a] hover:underline"><?php echo e($en['vendor_name']); ?></a>
                                        <?php else: ?><?php echo e($en['source'] ?: '—'); ?><?php endif; ?>
                                    </td>
                                    <td class="px-5 py-2.5 text-gray-500"><?php echo e($en['performed_by'] ?: '—'); ?></td>
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
