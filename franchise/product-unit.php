<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/includes/inventory.php';
if (!isset($conn) || !($conn instanceof mysqli)) die('Database connection failed.');

$fid = (int)$franchiseId;
$by  = (string)($franchiseName ?: $franchiseCode ?: ('FR' . $fid));
$unitId = (int)($_GET['id'] ?? 0);

$msg = ''; $err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $unitId > 0) {
    $act = (string)($_POST['action'] ?? '');
    if ($act === 'save_values') {
        $u = fr_unit_get($conn, $fid, $unitId);
        if ($u) { fr_values_set($conn, 'fr_unit_values', $unitId, (int)$u['product_id'], (array)($_POST['cf'] ?? [])); $msg = 'Identifiers / custom fields saved.'; }
        else $err = 'Unit not found.';
    } elseif ($act === 'set_status') {
        $r = fr_unit_set_status($conn, $fid, $unitId, (string)($_POST['status'] ?? ''), [
            'customer_ref' => $_POST['customer_ref'] ?? 0,
            'customer_name' => $_POST['customer_name'] ?? '', 'customer_id' => $_POST['customer_id'] ?? '',
            'sale_ref' => $_POST['sale_ref'] ?? '', 'invoice_no' => $_POST['invoice_no'] ?? '',
            'sale_amount' => $_POST['sale_amount'] ?? 0, 'payment_status' => $_POST['payment_status'] ?? 'Paid',
            'sale_date' => $_POST['sale_date'] ?? '', 'delivery_date' => $_POST['delivery_date'] ?? '',
            'reason' => $_POST['reason'] ?? '', 'remarks' => $_POST['remarks'] ?? '',
            'franchise_code' => $franchiseCode, 'franchise_type' => $franchiseType,
        ], $by);
        $msg = $r['ok'] ? $r['message'] : ''; $err = $r['ok'] ? '' : $r['message'];
    }
}

$customers = function_exists('frc_active_customers') ? frc_active_customers($conn, $fid) : [];

$H = $unitId > 0 ? fr_unit_history($conn, $fid, $unitId) : [];
if (empty($H)) {
    $notFound = true;
} else {
    $notFound = false;
    $unit = $H['unit']; $vals = $H['values']; $entry = $H['entry']; $moves = $H['moves'];
    $fields = fr_fields_list($conn, (int)$unit['product_id']);
    $vendorRow = null;
    $vid = (int)($unit['vendor_id'] ?? ($entry['vendor_id'] ?? 0));
    if ($vid > 0 && function_exists('frv_vendor_get')) $vendorRow = frv_vendor_get($conn, $fid, $vid);
    $customerRow = null;
    $cid = (int)($unit['customer_ref'] ?? 0);
    if ($cid > 0 && function_exists('frc_customer_get')) $customerRow = frc_customer_get($conn, $fid, $cid);
}
function iv_d($s) { return ($s && $s !== '0000-00-00') ? e(substr((string)$s, 0, 16)) : '—'; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Unit Traceability</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body class="bg-[#f3f3f3] font-sans overflow-x-hidden">
<?php include __DIR__ . '/includes/sidebar.php'; ?>
<div class="ml-64 min-h-screen bg-[#f3f3f3]">
    <?php include __DIR__ . '/includes/navbar.php'; ?>
    <main class="px-4 sm:px-5 py-6">
        <div class="max-w-6xl mx-auto space-y-6">

            <?php if ($notFound): ?>
                <div class="bg-white border border-gray-200 rounded-xl p-8 text-center text-gray-500">
                    Unit not found for this franchise. <a href="inventory.php" class="text-[#17457a] underline">Back to inventory</a>
                </div>
            <?php else: ?>

            <div class="bg-[#17457a] rounded-xl px-5 sm:px-6 py-4 text-white shadow flex items-center justify-between flex-wrap gap-3">
                <div>
                    <h1 class="text-[18px] sm:text-[20px] font-semibold"><?php echo e($unit['unit_no']); ?> — <?php echo e($unit['product_name']); ?></h1>
                    <p class="text-[12px] text-white/80 mt-1">Current status: <strong class="text-white"><?php echo e($unit['status']); ?></strong></p>
                </div>
                <a href="inventory.php?id=<?php echo (int)$unit['product_id']; ?>" class="bg-white/15 hover:bg-white/25 rounded-lg px-3 py-2 text-[12px]">Back to product</a>
            </div>

            <?php if ($msg): ?><div class="rounded-lg border border-green-200 bg-green-50 px-4 py-2.5 text-green-700 text-[13px]"><?php echo e($msg); ?></div><?php endif; ?>
            <?php if ($err): ?><div class="rounded-lg border border-red-200 bg-red-50 px-4 py-2.5 text-red-700 text-[13px]"><?php echo e($err); ?></div><?php endif; ?>

            <!-- TRACE CHAIN -->
            <div class="bg-white border border-gray-200 rounded-xl p-5 shadow-sm">
                <h2 class="text-[14px] font-semibold text-[#173a67] mb-3">Traceability</h2>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-x-8 gap-y-2 text-[13px]">
                    <div class="flex justify-between"><span class="text-gray-500">Vendor / Source</span><span class="font-medium">
                        <?php if ($vendorRow): ?>
                            <a href="vendors.php?id=<?php echo (int)$vendorRow['id']; ?>" class="text-[#17457a] hover:underline"><?php echo e($vendorRow['vendor_name']); ?></a>
                        <?php else: echo e($unit['source_vendor'] ?: ($entry['source'] ?? '—')); endif; ?>
                    </span></div>
                    <div class="flex justify-between"><span class="text-gray-500">Received Date</span><span class="font-medium"><?php echo iv_d($unit['received_date']); ?></span></div>
                    <div class="flex justify-between"><span class="text-gray-500">Stock Entry Ref</span><span class="font-medium"><?php echo e($entry['reference_no'] ?? '—'); ?> <?php echo $entry ? '(#' . (int)$entry['id'] . ')' : ''; ?></span></div>
                    <div class="flex justify-between"><span class="text-gray-500">Franchise</span><span class="font-medium"><?php echo e($franchiseName); ?> · <?php echo e($franchiseCode); ?></span></div>
                    <div class="flex justify-between"><span class="text-gray-500">Current Status</span><span class="font-medium"><?php echo e($unit['status']); ?></span></div>
                    <div class="flex justify-between"><span class="text-gray-500">Customer</span><span class="font-medium">
                        <?php if ($customerRow): ?>
                            <a href="customers.php?id=<?php echo (int)$customerRow['id']; ?>" class="text-[#17457a] hover:underline"><?php echo e($customerRow['customer_name']); ?></a>
                            <?php if ($customerRow['customer_code']): ?> <span class="text-gray-400">(<?php echo e($customerRow['customer_code']); ?>)</span><?php endif; ?>
                        <?php else: echo e($unit['customer_name'] ?: '—'); echo $unit['customer_id'] ? ' (' . e($unit['customer_id']) . ')' : ''; endif; ?>
                    </span></div>
                    <div class="flex justify-between"><span class="text-gray-500">Sale Date</span><span class="font-medium"><?php echo iv_d($unit['sale_date']); ?></span></div>
                    <div class="flex justify-between"><span class="text-gray-500">Delivery Date</span><span class="font-medium"><?php echo iv_d($unit['delivery_date']); ?></span></div>
                    <div class="flex justify-between"><span class="text-gray-500">Invoice</span><span class="font-medium"><?php echo e($unit['invoice_no'] ?: '—'); ?></span></div>
                    <div class="flex justify-between"><span class="text-gray-500">Sale Reference</span><span class="font-medium"><?php echo e($unit['sale_ref'] ?: '—'); ?></span></div>
                </div>
                <p class="mt-3 text-[11px] text-gray-400">Vendor → Franchise → Stock → Customer → Sale / Delivery</p>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- IDENTIFIERS -->
                <form method="post" class="bg-white border border-gray-200 rounded-xl p-5 shadow-sm">
                    <input type="hidden" name="action" value="save_values">
                    <h2 class="text-[14px] font-semibold text-[#173a67] mb-3">Identifiers / Custom Fields</h2>
                    <?php if (empty($fields)): ?>
                        <p class="text-[12px] text-gray-400">No custom fields defined for this product. Add them from the
                            <a href="inventory.php?id=<?php echo (int)$unit['product_id']; ?>" class="text-[#17457a] underline">product page</a>.</p>
                    <?php else: ?>
                        <div class="space-y-3 text-[13px]">
                            <?php foreach ($fields as $f): ?>
                                <label class="block">
                                    <span class="text-gray-500 text-[11px]"><?php echo e($f['field_label']); ?><?php echo (int)$f['is_identifier'] === 1 ? ' *' : ''; ?></span>
                                    <input name="cf[<?php echo e($f['field_key']); ?>]"
                                           type="<?php echo $f['field_type'] === 'date' ? 'date' : ($f['field_type'] === 'number' ? 'number' : 'text'); ?>"
                                           value="<?php echo e($vals[$f['field_key']] ?? ''); ?>"
                                           class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2">
                                </label>
                            <?php endforeach; ?>
                        </div>
                        <button class="mt-4 bg-[#17457a] text-white rounded-lg px-5 py-2 text-[13px]">Save</button>
                    <?php endif; ?>
                </form>

                <!-- STATUS CHANGE -->
                <form method="post" class="bg-white border border-gray-200 rounded-xl p-5 shadow-sm">
                    <input type="hidden" name="action" value="set_status">
                    <h2 class="text-[14px] font-semibold text-[#173a67] mb-3">Change Status</h2>
                    <div class="space-y-3 text-[13px]">
                        <label class="block"><span class="text-gray-500 text-[11px]">New status</span>
                            <select name="status" class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2">
                                <?php foreach (fr_inv_statuses() as $st): ?>
                                    <option <?php echo $unit['status'] === $st ? 'selected' : ''; ?>><?php echo $st; ?></option>
                                <?php endforeach; ?>
                            </select></label>
                        <label class="block"><span class="text-gray-500 text-[11px]">Customer</span>
                            <select name="customer_ref" class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2">
                                <option value="0">— none / walk-in —</option>
                                <?php foreach ($customers as $cu): ?>
                                    <option value="<?php echo (int)$cu['id']; ?>" <?php echo (int)($unit['customer_ref'] ?? 0) === (int)$cu['id'] ? 'selected' : ''; ?>>
                                        <?php echo e($cu['customer_name'] . ($cu['mobile'] ? ' — ' . $cu['mobile'] : '') . ($cu['customer_code'] ? ' (' . $cu['customer_code'] . ')' : '')); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <span class="text-[10px] text-gray-400">Linking a customer records this sale in their purchase history. <a href="customers.php" class="text-[#17457a] underline">Manage customers</a></span></label>
                        <div class="grid grid-cols-2 gap-3">
                            <label class="block"><span class="text-gray-500 text-[11px]">Customer name (override / walk-in)</span>
                                <input name="customer_name" value="<?php echo e($unit['customer_name']); ?>" class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2"></label>
                            <label class="block"><span class="text-gray-500 text-[11px]">Customer ID (free text)</span>
                                <input name="customer_id" value="<?php echo e($unit['customer_id']); ?>" class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2"></label>
                            <label class="block"><span class="text-gray-500 text-[11px]">Payment status</span>
                                <select name="payment_status" class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2">
                                    <option>Paid</option><option>Pending</option><option>Partial</option>
                                </select></label>
                            <label class="block"><span class="text-gray-500 text-[11px]">Invoice no.</span>
                                <input name="invoice_no" value="<?php echo e($unit['invoice_no']); ?>" placeholder="auto-generated if left blank" class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2"></label>
                            <label class="block"><span class="text-gray-500 text-[11px]">Sale reference</span>
                                <input name="sale_ref" value="<?php echo e($unit['sale_ref']); ?>" class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2"></label>
                            <label class="block"><span class="text-gray-500 text-[11px]">Sale amount (commission base)</span>
                                <input name="sale_amount" type="number" step="0.01" value="<?php echo e((float)($unit['sale_amount'] ?? 0) ?: ''); ?>" placeholder="defaults to product price" class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2"></label>
                            <label class="block"><span class="text-gray-500 text-[11px]">Sale date</span>
                                <input name="sale_date" type="date" value="<?php echo e($unit['sale_date'] && $unit['sale_date'] !== '0000-00-00' ? $unit['sale_date'] : ''); ?>" class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2"></label>
                            <label class="block"><span class="text-gray-500 text-[11px]">Delivery date</span>
                                <input name="delivery_date" type="date" value="<?php echo e($unit['delivery_date'] && $unit['delivery_date'] !== '0000-00-00' ? $unit['delivery_date'] : ''); ?>" class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2"></label>
                        </div>
                        <label class="block"><span class="text-gray-500 text-[11px]">Reason</span>
                            <input name="reason" class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2"></label>
                        <label class="block"><span class="text-gray-500 text-[11px]">Remarks</span>
                            <input name="remarks" class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2"></label>
                    </div>
                    <button class="mt-4 bg-[#17457a] text-white rounded-lg px-5 py-2 text-[13px]">Update Status</button>
                </form>
            </div>

            <!-- UNIT HISTORY -->
            <div class="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden">
                <div class="px-5 py-3 border-b border-gray-100"><h2 class="text-[14px] font-semibold text-[#173a67]">Movement History</h2></div>
                <div class="overflow-x-auto">
                    <table class="w-full text-[13px]">
                        <thead class="bg-gray-50 text-gray-500 text-[12px]"><tr>
                            <th class="text-left px-5 py-2.5">Date</th><th class="text-left px-5 py-2.5">Type</th><th class="text-right px-5 py-2.5">Qty</th><th class="text-left px-5 py-2.5">Reason</th><th class="text-left px-5 py-2.5">Reference</th><th class="text-left px-5 py-2.5">By</th><th class="text-left px-5 py-2.5">Remarks</th>
                        </tr></thead>
                        <tbody class="divide-y divide-gray-100">
                            <?php if (empty($moves)): ?><tr><td colspan="7" class="px-5 py-6 text-center text-gray-400">No movements.</td></tr><?php endif; ?>
                            <?php foreach ($moves as $m): ?>
                                <tr>
                                    <td class="px-5 py-2.5 whitespace-nowrap text-gray-500"><?php echo e(substr((string)$m['created_at'], 0, 16)); ?></td>
                                    <td class="px-5 py-2.5"><span class="text-[11px] rounded-full px-2 py-0.5 bg-gray-100 text-gray-700"><?php echo e($m['movement_type']); ?></span></td>
                                    <td class="px-5 py-2.5 text-right"><?php echo (int)$m['qty_change'] > 0 ? '+' : ''; ?><?php echo (int)$m['qty_change']; ?></td>
                                    <td class="px-5 py-2.5 text-gray-600"><?php echo e($m['reason'] ?: '—'); ?></td>
                                    <td class="px-5 py-2.5 text-gray-500"><?php echo e($m['reference'] ?: '—'); ?></td>
                                    <td class="px-5 py-2.5 text-gray-500"><?php echo e($m['performed_by'] ?: '—'); ?></td>
                                    <td class="px-5 py-2.5 text-gray-500"><?php echo e($m['remarks'] ?: '—'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <?php endif; ?>
        </div>
    </main>
</div>
<script>if (typeof lucide !== 'undefined') lucide.createIcons();</script>
</body>
</html>
