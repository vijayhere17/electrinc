<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/includes/inventory.php';   // pulls in vendors.php too
if (!isset($conn) || !($conn instanceof mysqli)) die('Database connection failed.');

$fid   = (int)$franchiseId;                         // authenticated franchise PK — never from URL
$fcode = (string)$franchiseCode;
$by    = (string)($franchiseName ?: $franchiseCode ?: ('FR' . $fid));

$msg = ''; $err = '';
$viewId = (int)($_GET['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = (string)($_POST['action'] ?? '');

    if ($act === 'create_vendor') {
        $r = frv_vendor_save($conn, $fid, $fcode, $_POST, $by);
        if (!empty($r['ok'])) {
            frv_values_set($conn, $fid, (int)$r['id'], (array)($_POST['cf'] ?? []));
            header('Location: vendors.php?id=' . (int)$r['id'] . '&ok=' . urlencode($r['message']));
            exit;
        }
        $err = $r['message'];

    } elseif ($act === 'update_vendor' && $viewId > 0) {
        $r = frv_vendor_save($conn, $fid, $fcode, $_POST, $by, $viewId);
        if (!empty($r['ok'])) frv_values_set($conn, $fid, $viewId, (array)($_POST['cf'] ?? []));
        $msg = $r['ok'] ? $r['message'] : ''; $err = $r['ok'] ? '' : $r['message'];

    } elseif ($act === 'set_status' && $viewId > 0) {
        $r = frv_vendor_set_status($conn, $fid, $viewId, (string)($_POST['status'] ?? ''), $by);
        $msg = $r['ok'] ? $r['message'] : ''; $err = $r['ok'] ? '' : $r['message'];

    } elseif ($act === 'add_field') {
        $r = frv_field_add($conn, $fid, (string)($_POST['field_label'] ?? ''), (string)($_POST['field_type'] ?? 'text'), !empty($_POST['is_searchable']));
        $msg = $r['ok'] ? $r['message'] : ''; $err = $r['ok'] ? '' : $r['message'];

    } elseif ($act === 'del_field') {
        $r = frv_field_delete($conn, $fid, (int)($_POST['field_id'] ?? 0));
        $msg = $r['ok'] ? $r['message'] : ''; $err = $r['ok'] ? '' : $r['message'];
    }
}
if (isset($_GET['ok'])) $msg = (string)$_GET['ok'];

$fields = frv_fields_list($conn, $fid);
$vendor = $viewId > 0 ? frv_vendor_get($conn, $fid, $viewId) : null;

/* ---- list ---- */
$q      = trim((string)($_GET['q'] ?? ''));
$fstat  = trim((string)($_GET['status'] ?? ''));
$list   = $vendor ? [] : frv_vendor_list($conn, $fid, ['q' => $q, 'status' => $fstat]);

/* ---- manage-view data ---- */
$vvals    = $vendor ? frv_values_get($conn, $viewId) : [];
$summary  = $vendor ? frv_vendor_product_summary($conn, $fid, $viewId) : ['rows' => [], 'totals' => []];
$history  = $vendor ? frv_history($conn, $fid, $viewId, 150) : [];

$suggestedFields = ['Vendor Code', 'Alternate Contact', 'Bank Name', 'Account Number', 'IFSC', 'Payment Terms', 'Transport Details', 'Credit Days'];

function vend_input($name, $label, $val, $type = 'text', $span = 1)
{
    $cls = $span === 2 ? 'sm:col-span-2' : ($span === 3 ? 'sm:col-span-2 lg:col-span-3' : '');
    echo '<label class="block ' . $cls . '"><span class="text-gray-500 text-[11px]">' . e($label) . '</span>';
    echo '<input name="' . e($name) . '" type="' . e($type) . '" value="' . e($val) . '" class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2"></label>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Vendors &amp; Suppliers</title>
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
                    <h1 class="text-[18px] sm:text-[20px] font-semibold">Vendors &amp; Suppliers</h1>
                    <p class="text-[12px] text-white/80 mt-1"><?php echo e($franchiseName); ?> · <?php echo e($franchiseCode); ?></p>
                </div>
                <div class="flex gap-2">
                    <a href="stock-entry.php" class="bg-white/15 hover:bg-white/25 rounded-lg px-3 py-2 text-[12px]">Stock Entry</a>
                    <a href="inventory.php" class="bg-white/15 hover:bg-white/25 rounded-lg px-3 py-2 text-[12px]">Product Master</a>
                </div>
            </div>

            <?php if ($msg): ?><div class="rounded-lg border border-green-200 bg-green-50 px-4 py-2.5 text-green-700 text-[13px]"><?php echo e($msg); ?></div><?php endif; ?>
            <?php if ($err): ?><div class="rounded-lg border border-red-200 bg-red-50 px-4 py-2.5 text-red-700 text-[13px]"><?php echo e($err); ?></div><?php endif; ?>

            <?php if ($vendor): /* ================= SINGLE VENDOR ================= */ ?>

            <a href="vendors.php" class="text-[12px] text-[#17457a] hover:underline inline-flex items-center gap-1"><i data-lucide="arrow-left" class="w-3.5 h-3.5"></i> All vendors</a>

            <div class="flex items-center gap-3 flex-wrap">
                <h2 class="text-[16px] font-semibold text-gray-800"><?php echo e($vendor['vendor_name']); ?></h2>
                <span class="text-[11px] rounded-full px-2 py-0.5 <?php echo $vendor['status'] === 'Active' ? 'bg-green-50 text-green-700' : 'bg-gray-200 text-gray-600'; ?>"><?php echo e($vendor['status']); ?></span>
                <form method="post" class="ml-auto">
                    <input type="hidden" name="action" value="set_status">
                    <input type="hidden" name="status" value="<?php echo $vendor['status'] === 'Active' ? 'Inactive' : 'Active'; ?>">
                    <button class="text-[12px] rounded-lg border px-3 py-1.5 <?php echo $vendor['status'] === 'Active' ? 'border-gray-300 text-gray-600 hover:bg-gray-100' : 'border-green-300 text-green-700 hover:bg-green-50'; ?>">
                        <?php echo $vendor['status'] === 'Active' ? 'Mark Inactive' : 'Mark Active'; ?>
                    </button>
                </form>
            </div>
            <?php if ($vendor['status'] !== 'Active'): ?>
                <p class="text-[12px] text-amber-700 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2">This vendor is inactive — it cannot be selected for new stock entries, but all historical records below remain intact.</p>
            <?php endif; ?>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- details -->
                <form method="post" class="bg-white border border-gray-200 rounded-xl p-5 shadow-sm lg:col-span-2">
                    <input type="hidden" name="action" value="update_vendor">
                    <h3 class="text-[14px] font-semibold text-[#173a67] mb-3">Vendor Details</h3>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 text-[13px]">
                        <?php
                        vend_input('vendor_name', 'Vendor Name *', $vendor['vendor_name'], 'text', 2);
                        vend_input('company_name', 'Company Name', $vendor['company_name']);
                        vend_input('mobile', 'Mobile Number', $vendor['mobile']);
                        vend_input('email', 'Email', $vendor['email']);
                        vend_input('gst_no', 'GST Number', $vendor['gst_no']);
                        vend_input('address', 'Address', $vendor['address'], 'text', 3);
                        vend_input('state', 'State', $vendor['state']);
                        vend_input('city', 'City', $vendor['city']);
                        vend_input('district', 'District', $vendor['district']);
                        vend_input('pincode', 'Pincode', $vendor['pincode']);
                        vend_input('country', 'Country', $vendor['country'] ?: 'India');
                        ?>
                        <label class="block"><span class="text-gray-500 text-[11px]">Vendor Status</span>
                            <select name="status" class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2">
                                <option <?php echo $vendor['status'] === 'Active' ? 'selected' : ''; ?>>Active</option>
                                <option <?php echo $vendor['status'] === 'Inactive' ? 'selected' : ''; ?>>Inactive</option>
                            </select></label>
                        <label class="block sm:col-span-2 lg:col-span-3"><span class="text-gray-500 text-[11px]">Remarks</span>
                            <textarea name="remarks" rows="2" class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2"><?php echo e($vendor['remarks']); ?></textarea></label>
                    </div>

                    <?php if (!empty($fields)): ?>
                        <h3 class="text-[13px] font-semibold text-[#173a67] mt-5 mb-2">Custom Information</h3>
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 text-[13px]">
                            <?php foreach ($fields as $f): ?>
                                <label class="block"><span class="text-gray-500 text-[11px]"><?php echo e($f['field_label']); ?></span>
                                    <input name="cf[<?php echo e($f['field_key']); ?>]"
                                           type="<?php echo $f['field_type'] === 'date' ? 'date' : ($f['field_type'] === 'number' ? 'number' : 'text'); ?>"
                                           value="<?php echo e($vvals[$f['field_key']] ?? ''); ?>"
                                           class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2"></label>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <button class="mt-4 bg-[#17457a] text-white rounded-lg px-5 py-2 text-[13px]">Save Vendor</button>
                    <p class="mt-2 text-[11px] text-gray-400">Added <?php echo e(substr((string)$vendor['created_at'], 0, 16)); ?> · by <?php echo e($vendor['created_by'] ?: '—'); ?></p>
                </form>

                <!-- custom field definitions -->
                <div class="bg-white border border-gray-200 rounded-xl p-5 shadow-sm">
                    <h3 class="text-[14px] font-semibold text-[#173a67] mb-3">Custom Fields</h3>
                    <p class="text-[11px] text-gray-400 mb-2">Optional fields shown on every vendor of this franchise.</p>
                    <div class="space-y-1.5">
                        <?php if (empty($fields)): ?><p class="text-[12px] text-gray-400">None yet.</p><?php endif; ?>
                        <?php foreach ($fields as $f): ?>
                            <div class="flex items-center justify-between text-[12px] bg-gray-50 rounded px-2 py-1.5">
                                <span><?php echo e($f['field_label']); ?>
                                    <span class="text-gray-400">(<?php echo e($f['field_type']); ?><?php echo (int)$f['is_searchable'] === 1 ? ', search' : ''; ?>)</span></span>
                                <form method="post" onsubmit="return confirm('Remove this field from all vendors?');">
                                    <input type="hidden" name="action" value="del_field"><input type="hidden" name="field_id" value="<?php echo (int)$f['id']; ?>">
                                    <button class="text-red-500 hover:text-red-700"><i data-lucide="x" class="w-3.5 h-3.5"></i></button>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <form method="post" class="mt-3 border-t border-gray-100 pt-3 space-y-2 text-[12px]">
                        <input type="hidden" name="action" value="add_field">
                        <input name="field_label" list="vfieldsuggest" placeholder="Field name e.g. Vendor Code" required class="w-full border border-gray-300 rounded px-2 py-1.5">
                        <datalist id="vfieldsuggest"><?php foreach ($suggestedFields as $sug): ?><option value="<?php echo e($sug); ?>"><?php endforeach; ?></datalist>
                        <div class="flex items-center gap-2">
                            <select name="field_type" class="border border-gray-300 rounded px-2 py-1.5"><option value="text">text</option><option value="number">number</option><option value="date">date</option></select>
                            <label class="flex items-center gap-1"><input type="checkbox" name="is_searchable" value="1" checked> searchable</label>
                        </div>
                        <button class="bg-[#17457a] text-white rounded px-3 py-1.5">+ Add Custom Field</button>
                    </form>
                </div>
            </div>

            <!-- supplied products -->
            <div class="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden">
                <div class="px-5 py-3 border-b border-gray-100 flex items-center justify-between">
                    <h3 class="text-[14px] font-semibold text-[#173a67]">Products Supplied by this Vendor</h3>
                    <a href="stock-entry.php" class="text-[12px] text-[#17457a] hover:underline">+ Receive stock</a>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-[13px]">
                        <thead class="bg-gray-50 text-gray-500 text-[12px]">
                            <tr>
                                <th class="text-left px-5 py-2.5">Product</th>
                                <th class="text-right px-5 py-2.5">Supplied</th>
                                <th class="text-right px-5 py-2.5">In Stock</th>
                                <th class="text-right px-5 py-2.5">Sold</th>
                                <th class="text-right px-5 py-2.5">Returned</th>
                                <th class="text-right px-5 py-2.5">Damaged</th>
                                <th class="text-left px-5 py-2.5">Supply Dates</th>
                                <th class="text-left px-5 py-2.5">Invoice / Ref</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <?php if (empty($summary['rows'])): ?>
                                <tr><td colspan="8" class="px-5 py-8 text-center text-gray-400">No stock received from this vendor yet.</td></tr>
                            <?php endif; ?>
                            <?php foreach ($summary['rows'] as $row): ?>
                                <tr>
                                    <td class="px-5 py-2.5 font-medium text-gray-800"><?php echo e($row['name']); ?>
                                        <?php if ($row['model']): ?><span class="text-gray-400 text-[11px]">/ <?php echo e($row['model']); ?></span><?php endif; ?>
                                        <?php if (!$row['is_serialized']): ?><span class="text-[10px] text-gray-400">(qty-only)</span><?php endif; ?></td>
                                    <td class="px-5 py-2.5 text-right font-semibold"><?php echo (int)$row['supplied']; ?></td>
                                    <td class="px-5 py-2.5 text-right"><?php echo $row['in_stock'] === null ? '—' : (int)$row['in_stock']; ?></td>
                                    <td class="px-5 py-2.5 text-right"><?php echo $row['is_serialized'] ? (int)($row['sold'] + $row['delivered']) : '—'; ?></td>
                                    <td class="px-5 py-2.5 text-right"><?php echo $row['is_serialized'] ? (int)$row['returned'] : '—'; ?></td>
                                    <td class="px-5 py-2.5 text-right"><?php echo $row['is_serialized'] ? (int)$row['damaged'] : '—'; ?></td>
                                    <td class="px-5 py-2.5 text-gray-500 whitespace-nowrap"><?php echo e($row['first_date'] === $row['last_date'] ? ($row['first_date'] ?: '—') : ($row['first_date'] . ' → ' . $row['last_date'])); ?></td>
                                    <td class="px-5 py-2.5 text-gray-500"><?php echo e($row['refs'] ?: '—'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <?php if (!empty($summary['rows'])): $t = $summary['totals']; ?>
                        <tfoot class="bg-gray-50 text-[12px] font-semibold text-gray-700">
                            <tr>
                                <td class="px-5 py-2.5">Totals (unit-tracked)</td>
                                <td class="px-5 py-2.5 text-right"><?php echo (int)$t['supplied']; ?></td>
                                <td class="px-5 py-2.5 text-right"><?php echo (int)$t['in_stock']; ?></td>
                                <td class="px-5 py-2.5 text-right"><?php echo (int)$t['sold']; ?></td>
                                <td class="px-5 py-2.5 text-right"><?php echo (int)$t['returned']; ?></td>
                                <td class="px-5 py-2.5 text-right"><?php echo (int)$t['damaged']; ?></td>
                                <td colspan="2"></td>
                            </tr>
                        </tfoot>
                        <?php endif; ?>
                    </table>
                </div>
                <p class="px-5 py-2 text-[10px] text-gray-400">Unit-level stock counts (In Stock / Sold / Returned / Damaged) are shown for unit-tracked products received from this vendor. Quantity-only products show total supplied.</p>
            </div>

            <!-- vendor history -->
            <div class="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden">
                <div class="px-5 py-3 border-b border-gray-100"><h3 class="text-[14px] font-semibold text-[#173a67]">Vendor History</h3></div>
                <div class="overflow-x-auto">
                    <table class="w-full text-[13px]">
                        <thead class="bg-gray-50 text-gray-500 text-[12px]"><tr>
                            <th class="text-left px-5 py-2.5">When</th><th class="text-left px-5 py-2.5">Action</th>
                            <th class="text-left px-5 py-2.5">Detail</th><th class="text-left px-5 py-2.5">By</th>
                        </tr></thead>
                        <tbody class="divide-y divide-gray-100">
                            <?php if (empty($history)): ?><tr><td colspan="4" class="px-5 py-6 text-center text-gray-400">No history yet.</td></tr><?php endif; ?>
                            <?php foreach ($history as $h): ?>
                                <tr>
                                    <td class="px-5 py-2.5 text-gray-500 whitespace-nowrap"><?php echo e(substr((string)$h['created_at'], 0, 16)); ?></td>
                                    <td class="px-5 py-2.5"><span class="text-[11px] rounded-full px-2 py-0.5 bg-gray-100 text-gray-700"><?php echo e($h['action']); ?></span></td>
                                    <td class="px-5 py-2.5 text-gray-600"><?php echo e($h['detail']); ?></td>
                                    <td class="px-5 py-2.5 text-gray-500"><?php echo e($h['performed_by'] ?: '—'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <?php else: /* ================= LIST + ADD ================= */ ?>

            <details class="bg-white border border-gray-200 rounded-xl shadow-sm">
                <summary class="cursor-pointer px-5 py-4 text-[14px] font-semibold text-[#173a67] flex items-center gap-2"><i data-lucide="plus" class="w-4 h-4"></i> Add Vendor</summary>
                <form method="post" class="px-5 pb-5 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 text-[13px]">
                    <input type="hidden" name="action" value="create_vendor">
                    <?php
                    vend_input('vendor_name', 'Vendor Name *', '', 'text', 2);
                    vend_input('company_name', 'Company Name', '');
                    vend_input('mobile', 'Mobile Number', '');
                    vend_input('email', 'Email', '');
                    vend_input('gst_no', 'GST Number', '');
                    vend_input('address', 'Address', '', 'text', 3);
                    vend_input('state', 'State', '');
                    vend_input('city', 'City', '');
                    vend_input('district', 'District', '');
                    vend_input('pincode', 'Pincode', '');
                    vend_input('country', 'Country', 'India');
                    ?>
                    <label class="block"><span class="text-gray-500 text-[11px]">Vendor Status</span>
                        <select name="status" class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2"><option>Active</option><option>Inactive</option></select></label>
                    <label class="block sm:col-span-2 lg:col-span-3"><span class="text-gray-500 text-[11px]">Remarks</span>
                        <textarea name="remarks" rows="2" class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2"></textarea></label>

                    <?php if (!empty($fields)): ?>
                        <div class="sm:col-span-2 lg:col-span-3 text-[11px] font-semibold text-gray-500 mt-1">Custom Information (optional)</div>
                        <?php foreach ($fields as $f): ?>
                            <label class="block"><span class="text-gray-500 text-[11px]"><?php echo e($f['field_label']); ?></span>
                                <input name="cf[<?php echo e($f['field_key']); ?>]"
                                       type="<?php echo $f['field_type'] === 'date' ? 'date' : ($f['field_type'] === 'number' ? 'number' : 'text'); ?>"
                                       class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2"></label>
                        <?php endforeach; ?>
                    <?php endif; ?>

                    <div class="sm:col-span-2 lg:col-span-3"><button class="bg-[#17457a] text-white rounded-lg px-5 py-2 text-[13px]">Create Vendor</button></div>
                </form>
            </details>

            <form method="get" class="flex flex-wrap gap-2">
                <input name="q" value="<?php echo e($q); ?>" placeholder="Search name / company / mobile / GST / custom field" class="flex-1 min-w-[220px] border border-gray-300 rounded-lg px-3 py-2 text-[13px]">
                <select name="status" class="border border-gray-300 rounded-lg px-3 py-2 text-[13px] bg-white">
                    <option value="">All statuses</option>
                    <option value="Active" <?php echo $fstat === 'Active' ? 'selected' : ''; ?>>Active</option>
                    <option value="Inactive" <?php echo $fstat === 'Inactive' ? 'selected' : ''; ?>>Inactive</option>
                </select>
                <button class="bg-[#17457a] text-white rounded-lg px-4 py-2 text-[13px]">Search</button>
                <?php if ($q !== '' || $fstat !== ''): ?><a href="vendors.php" class="border border-gray-300 rounded-lg px-4 py-2 text-[13px] bg-white">Clear</a><?php endif; ?>
            </form>

            <div class="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-[13px]">
                        <thead class="bg-gray-50 text-gray-500 text-[12px]">
                            <tr>
                                <th class="text-left px-5 py-2.5">Vendor</th>
                                <th class="text-left px-5 py-2.5">Company</th>
                                <th class="text-left px-5 py-2.5">Mobile</th>
                                <th class="text-left px-5 py-2.5">GST</th>
                                <th class="text-left px-5 py-2.5">City / State</th>
                                <th class="text-left px-5 py-2.5">Status</th>
                                <th class="px-5 py-2.5"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <?php if (empty($list)): ?>
                                <tr><td colspan="7" class="px-5 py-8 text-center text-gray-400">No vendors<?php echo ($q !== '' || $fstat !== '') ? ' match this search' : ' yet — use “Add Vendor” above'; ?>.</td></tr>
                            <?php endif; ?>
                            <?php foreach ($list as $v): ?>
                                <tr>
                                    <td class="px-5 py-2.5 font-medium text-gray-800"><?php echo e($v['vendor_name']); ?></td>
                                    <td class="px-5 py-2.5 text-gray-500"><?php echo e($v['company_name'] ?: '—'); ?></td>
                                    <td class="px-5 py-2.5 text-gray-500"><?php echo e($v['mobile'] ?: '—'); ?></td>
                                    <td class="px-5 py-2.5 text-gray-500"><?php echo e($v['gst_no'] ?: '—'); ?></td>
                                    <td class="px-5 py-2.5 text-gray-500"><?php echo e(trim(($v['city'] ?: '—') . ' / ' . ($v['state'] ?: '—'))); ?></td>
                                    <td class="px-5 py-2.5"><span class="text-[11px] rounded-full px-2 py-0.5 <?php echo $v['status'] === 'Active' ? 'bg-green-50 text-green-700' : 'bg-gray-200 text-gray-600'; ?>"><?php echo e($v['status']); ?></span></td>
                                    <td class="px-5 py-2.5 text-right"><a href="vendors.php?id=<?php echo (int)$v['id']; ?>" class="text-[12px] text-[#17457a] hover:underline">Manage</a></td>
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
