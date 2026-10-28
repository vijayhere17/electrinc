<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/includes/inventory.php';   // pulls in customers.php too
if (!isset($conn) || !($conn instanceof mysqli)) die('Database connection failed.');

$fid   = (int)$franchiseId;                         // authenticated franchise PK — never from URL
$fcode = (string)$franchiseCode;
$by    = (string)($franchiseName ?: $franchiseCode ?: ('FR' . $fid));

$msg = ''; $err = ''; $dupWarn = [];
$viewId = (int)($_GET['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = (string)($_POST['action'] ?? '');

    if ($act === 'create_customer') {
        $r = frc_customer_save($conn, $fid, $fcode, $_POST, $by);
        if (!empty($r['ok'])) {
            frc_values_set($conn, $fid, (int)$r['id'], (array)($_POST['cf'] ?? []));
            $note = $r['message'] . (!empty($r['duplicates']) ? ' (note: ' . count($r['duplicates']) . ' possible duplicate by mobile/email)' : '');
            header('Location: customers.php?id=' . (int)$r['id'] . '&ok=' . urlencode($note));
            exit;
        }
        $err = $r['message'];

    } elseif ($act === 'update_customer' && $viewId > 0) {
        $r = frc_customer_save($conn, $fid, $fcode, $_POST, $by, $viewId);
        if (!empty($r['ok'])) frc_values_set($conn, $fid, $viewId, (array)($_POST['cf'] ?? []));
        $msg = $r['ok'] ? $r['message'] : ''; $err = $r['ok'] ? '' : $r['message'];
        $dupWarn = $r['duplicates'] ?? [];

    } elseif ($act === 'set_status' && $viewId > 0) {
        $r = frc_customer_set_status($conn, $fid, $viewId, (string)($_POST['status'] ?? ''), $by);
        $msg = $r['ok'] ? $r['message'] : ''; $err = $r['ok'] ? '' : $r['message'];

    } elseif ($act === 'add_field') {
        $r = frc_field_add($conn, $fid, (string)($_POST['field_label'] ?? ''), (string)($_POST['field_type'] ?? 'text'), !empty($_POST['is_searchable']));
        $msg = $r['ok'] ? $r['message'] : ''; $err = $r['ok'] ? '' : $r['message'];

    } elseif ($act === 'del_field') {
        $r = frc_field_delete($conn, $fid, (int)($_POST['field_id'] ?? 0));
        $msg = $r['ok'] ? $r['message'] : ''; $err = $r['ok'] ? '' : $r['message'];
    }
}
if (isset($_GET['ok'])) $msg = (string)$_GET['ok'];

$fields   = frc_fields_list($conn, $fid);
$customer = $viewId > 0 ? frc_customer_get($conn, $fid, $viewId) : null;

/* ---- live duplicate check on the open customer ---- */
if ($customer && empty($dupWarn)) {
    $dupWarn = frc_find_duplicates($conn, $fid, (string)$customer['mobile'], (string)$customer['email'], $viewId);
}

/* ---- list ---- */
$q     = trim((string)($_GET['q'] ?? ''));
$fstat = trim((string)($_GET['status'] ?? ''));
$list  = $customer ? [] : frc_customer_list($conn, $fid, ['q' => $q, 'status' => $fstat]);

/* ---- manage-view data ---- */
$cvals     = $customer ? frc_values_get($conn, $viewId) : [];
$purchases = $customer ? frc_customer_purchases($conn, $fid, $viewId) : [];
$vehicles  = $customer ? frc_customer_vehicles($conn, $fid, $viewId) : [];
$summary   = $customer ? frc_customer_summary($conn, $fid, $viewId) : [];
$history   = $customer ? frc_history($conn, $fid, $viewId, 150) : [];

$suggestedFields = ['Alternate Mobile', 'Occupation', 'Company Name', 'ID Reference', 'Driving Licence No', 'Referred By'];

function cust_input($name, $label, $val, $type = 'text', $span = 1)
{
    $cls = $span === 2 ? 'sm:col-span-2' : ($span === 3 ? 'sm:col-span-2 lg:col-span-3' : '');
    echo '<label class="block ' . $cls . '"><span class="text-gray-500 text-[11px]">' . e($label) . '</span>';
    echo '<input name="' . e($name) . '" type="' . e($type) . '" value="' . e($val) . '" class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2"></label>';
}
function cust_money($n) { return '₹' . number_format((float)$n, 2); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Customers &amp; Buyers</title>
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
                    <h1 class="text-[18px] sm:text-[20px] font-semibold">Customers &amp; Buyers</h1>
                    <p class="text-[12px] text-white/80 mt-1"><?php echo e($franchiseName); ?> · <?php echo e($franchiseCode); ?></p>
                </div>
                <div class="flex gap-2">
                    <a href="inventory.php" class="bg-white/15 hover:bg-white/25 rounded-lg px-3 py-2 text-[12px]">Product Master</a>
                    <a href="stock-report.php" class="bg-white/15 hover:bg-white/25 rounded-lg px-3 py-2 text-[12px]">Stock Report</a>
                </div>
            </div>

            <?php if ($msg): ?><div class="rounded-lg border border-green-200 bg-green-50 px-4 py-2.5 text-green-700 text-[13px]"><?php echo e($msg); ?></div><?php endif; ?>
            <?php if ($err): ?><div class="rounded-lg border border-red-200 bg-red-50 px-4 py-2.5 text-red-700 text-[13px]"><?php echo e($err); ?></div><?php endif; ?>

            <?php if ($customer): /* ================= SINGLE CUSTOMER ================= */ ?>

            <a href="customers.php" class="text-[12px] text-[#17457a] hover:underline inline-flex items-center gap-1"><i data-lucide="arrow-left" class="w-3.5 h-3.5"></i> All customers</a>

            <div class="flex items-center gap-3 flex-wrap">
                <h2 class="text-[16px] font-semibold text-gray-800"><?php echo e($customer['customer_name']); ?></h2>
                <span class="text-[12px] text-gray-500"><?php echo e($customer['customer_code'] ?: ('#' . $customer['id'])); ?></span>
                <span class="text-[11px] rounded-full px-2 py-0.5 <?php echo $customer['status'] === 'Active' ? 'bg-green-50 text-green-700' : 'bg-gray-200 text-gray-600'; ?>"><?php echo e($customer['status']); ?></span>
                <form method="post" class="ml-auto">
                    <input type="hidden" name="action" value="set_status">
                    <input type="hidden" name="status" value="<?php echo $customer['status'] === 'Active' ? 'Inactive' : 'Active'; ?>">
                    <button class="text-[12px] rounded-lg border px-3 py-1.5 <?php echo $customer['status'] === 'Active' ? 'border-gray-300 text-gray-600 hover:bg-gray-100' : 'border-green-300 text-green-700 hover:bg-green-50'; ?>">
                        <?php echo $customer['status'] === 'Active' ? 'Mark Inactive' : 'Mark Active'; ?>
                    </button>
                </form>
            </div>

            <?php if (!empty($dupWarn)): ?>
                <div class="rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-[12px] text-amber-800">
                    <strong>Possible duplicate customer(s)</strong> sharing this mobile/email — records are not merged automatically:
                    <?php foreach ($dupWarn as $dp): ?>
                        <a href="customers.php?id=<?php echo (int)$dp['id']; ?>" class="underline ml-1"><?php echo e($dp['customer_name']); ?> (<?php echo e($dp['customer_code'] ?: ('#' . $dp['id'])); ?>)</a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <?php if ($customer['status'] !== 'Active'): ?>
                <p class="text-[12px] text-amber-700 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2">This customer is inactive. All historical purchases below remain available and traceable.</p>
            <?php endif; ?>

            <!-- summary strip -->
            <div class="grid grid-cols-2 md:grid-cols-5 gap-3">
                <?php
                $cards = [
                    ['Purchases', (int)($summary['purchases'] ?? 0)],
                    ['Total Value', cust_money($summary['total_value'] ?? 0)],
                    ['Vehicles / Items Owned', (int)($summary['vehicles'] ?? 0)],
                    ['Payment Pending', (int)($summary['pending_payment'] ?? 0)],
                    ['Delivery Pending', (int)($summary['pending_delivery'] ?? 0)],
                ];
                foreach ($cards as $c): ?>
                    <div class="bg-white border border-gray-200 rounded-xl px-4 py-3">
                        <div class="text-[10px] uppercase tracking-wide text-gray-400"><?php echo $c[0]; ?></div>
                        <div class="mt-1 text-[16px] font-semibold text-[#17457a]"><?php echo $c[1]; ?></div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- details -->
                <form method="post" class="bg-white border border-gray-200 rounded-xl p-5 shadow-sm lg:col-span-2">
                    <input type="hidden" name="action" value="update_customer">
                    <h3 class="text-[14px] font-semibold text-[#173a67] mb-3">Customer Information</h3>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 text-[13px]">
                        <?php
                        cust_input('customer_name', 'Customer Name *', $customer['customer_name'], 'text', 2);
                        cust_input('mobile', 'Mobile Number', $customer['mobile']);
                        cust_input('email', 'Email Address', $customer['email']);
                        cust_input('dob', 'Date of Birth', ($customer['dob'] && $customer['dob'] !== '0000-00-00') ? $customer['dob'] : '', 'date');
                        ?>
                        <label class="block"><span class="text-gray-500 text-[11px]">Customer Status</span>
                            <select name="status" class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2">
                                <option <?php echo $customer['status'] === 'Active' ? 'selected' : ''; ?>>Active</option>
                                <option <?php echo $customer['status'] === 'Inactive' ? 'selected' : ''; ?>>Inactive</option>
                            </select></label>
                        <div class="text-[11px] text-gray-400 flex items-end pb-2">Customer ID <strong class="ml-1 text-gray-600"><?php echo e($customer['customer_code'] ?: '—'); ?></strong> · Registered <?php echo e($customer['registered_date'] ?: substr((string)$customer['created_at'], 0, 10)); ?></div>
                    </div>

                    <h3 class="text-[13px] font-semibold text-[#173a67] mt-5 mb-2">Address</h3>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 text-[13px]">
                        <?php
                        cust_input('address', 'Address', $customer['address'], 'text', 3);
                        cust_input('state', 'State', $customer['state']);
                        cust_input('city', 'City', $customer['city']);
                        cust_input('district', 'District', $customer['district']);
                        cust_input('pincode', 'Pincode', $customer['pincode']);
                        cust_input('country', 'Country', $customer['country'] ?: 'India');
                        ?>
                    </div>

                    <?php if (!empty($fields)): ?>
                        <h3 class="text-[13px] font-semibold text-[#173a67] mt-5 mb-2">Custom Information</h3>
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 text-[13px]">
                            <?php foreach ($fields as $f): ?>
                                <label class="block"><span class="text-gray-500 text-[11px]"><?php echo e($f['field_label']); ?></span>
                                    <input name="cf[<?php echo e($f['field_key']); ?>]"
                                           type="<?php echo $f['field_type'] === 'date' ? 'date' : ($f['field_type'] === 'number' ? 'number' : 'text'); ?>"
                                           value="<?php echo e($cvals[$f['field_key']] ?? ''); ?>"
                                           class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2"></label>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <label class="block mt-4 text-[13px]"><span class="text-gray-500 text-[11px]">Remarks</span>
                        <textarea name="remarks" rows="2" class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2"><?php echo e($customer['remarks']); ?></textarea></label>

                    <button class="mt-4 bg-[#17457a] text-white rounded-lg px-5 py-2 text-[13px]">Save Customer</button>
                </form>

                <!-- custom field definitions -->
                <div class="bg-white border border-gray-200 rounded-xl p-5 shadow-sm">
                    <h3 class="text-[14px] font-semibold text-[#173a67] mb-3">Custom Fields</h3>
                    <p class="text-[11px] text-gray-400 mb-2">Optional fields shown on every customer of this franchise.</p>
                    <div class="space-y-1.5">
                        <?php if (empty($fields)): ?><p class="text-[12px] text-gray-400">None yet.</p><?php endif; ?>
                        <?php foreach ($fields as $f): ?>
                            <div class="flex items-center justify-between text-[12px] bg-gray-50 rounded px-2 py-1.5">
                                <span><?php echo e($f['field_label']); ?>
                                    <span class="text-gray-400">(<?php echo e($f['field_type']); ?><?php echo (int)$f['is_searchable'] === 1 ? ', search' : ''; ?>)</span></span>
                                <form method="post" onsubmit="return confirm('Remove this field from all customers?');">
                                    <input type="hidden" name="action" value="del_field"><input type="hidden" name="field_id" value="<?php echo (int)$f['id']; ?>">
                                    <button class="text-red-500 hover:text-red-700"><i data-lucide="x" class="w-3.5 h-3.5"></i></button>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <form method="post" class="mt-3 border-t border-gray-100 pt-3 space-y-2 text-[12px]">
                        <input type="hidden" name="action" value="add_field">
                        <input name="field_label" list="cfieldsuggest" placeholder="Field name e.g. Occupation" required class="w-full border border-gray-300 rounded px-2 py-1.5">
                        <datalist id="cfieldsuggest"><?php foreach ($suggestedFields as $sug): ?><option value="<?php echo e($sug); ?>"><?php endforeach; ?></datalist>
                        <div class="flex items-center gap-2">
                            <select name="field_type" class="border border-gray-300 rounded px-2 py-1.5"><option value="text">text</option><option value="number">number</option><option value="date">date</option></select>
                            <label class="flex items-center gap-1"><input type="checkbox" name="is_searchable" value="1" checked> searchable</label>
                        </div>
                        <button class="bg-[#17457a] text-white rounded px-3 py-1.5">+ Add Custom Field</button>
                    </form>
                </div>
            </div>

            <!-- purchase history -->
            <div class="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden">
                <div class="px-5 py-3 border-b border-gray-100"><h3 class="text-[14px] font-semibold text-[#173a67]">Purchase History</h3></div>
                <div class="overflow-x-auto">
                    <table class="w-full text-[13px]">
                        <thead class="bg-gray-50 text-gray-500 text-[12px]">
                            <tr>
                                <th class="text-left px-5 py-2.5">Date</th>
                                <th class="text-left px-5 py-2.5">Product / Model</th>
                                <th class="text-right px-5 py-2.5">Qty</th>
                                <th class="text-right px-5 py-2.5">Amount</th>
                                <th class="text-left px-5 py-2.5">Invoice</th>
                                <th class="text-left px-5 py-2.5">Payment</th>
                                <th class="text-left px-5 py-2.5">Delivery</th>
                                <th class="text-left px-5 py-2.5">Vehicle / Identifiers</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <?php if (empty($purchases)): ?>
                                <tr><td colspan="8" class="px-5 py-8 text-center text-gray-400">No purchases recorded yet.</td></tr>
                            <?php endif; ?>
                            <?php foreach ($purchases as $pu): ?>
                                <tr>
                                    <td class="px-5 py-2.5 text-gray-500 whitespace-nowrap"><?php echo e($pu['sale_date'] ?: substr((string)$pu['created_at'], 0, 10)); ?></td>
                                    <td class="px-5 py-2.5 font-medium text-gray-800"><?php echo e($pu['current_product_name'] ?: $pu['product_name'] ?: '—'); ?>
                                        <?php $mdl = $pu['current_model'] ?: $pu['model']; if ($mdl): ?><span class="text-gray-400 text-[11px]">/ <?php echo e($mdl); ?></span><?php endif; ?></td>
                                    <td class="px-5 py-2.5 text-right"><?php echo (int)$pu['quantity']; ?></td>
                                    <td class="px-5 py-2.5 text-right"><?php echo cust_money($pu['sale_amount']); ?></td>
                                    <td class="px-5 py-2.5 text-gray-600">
                                        <a href="sale-invoice.php?id=<?php echo (int)$pu['id']; ?>" target="_blank" class="inline-flex items-center gap-1 text-[#0E7A3B] hover:underline">
                                            <i data-lucide="file-text" class="w-3.5 h-3.5"></i><?php echo e($pu['invoice_no'] ?: 'View'); ?>
                                        </a>
                                    </td>
                                    <td class="px-5 py-2.5"><span class="text-[11px] rounded-full px-2 py-0.5 <?php echo $pu['payment_status'] === 'Paid' ? 'bg-green-50 text-green-700' : 'bg-amber-50 text-amber-700'; ?>"><?php echo e($pu['payment_status']); ?></span></td>
                                    <td class="px-5 py-2.5"><span class="text-[11px] rounded-full px-2 py-0.5 <?php echo $pu['delivery_status'] === 'Delivered' ? 'bg-green-50 text-green-700' : 'bg-gray-100 text-gray-600'; ?>"><?php echo e($pu['delivery_status']); ?></span></td>
                                    <td class="px-5 py-2.5 text-gray-500 text-[12px]">
                                        <?php if ($pu['unit_no']): ?>
                                            <a href="product-unit.php?id=<?php echo (int)$pu['unit_id']; ?>" class="text-[#17457a] hover:underline"><?php echo e($pu['unit_no']); ?></a>
                                        <?php endif; ?>
                                        <?php foreach (($pu['identifiers'] ?? []) as $k => $v): ?>
                                            <span class="block"><?php echo e($k); ?>: <?php echo e($v); ?></span>
                                        <?php endforeach; ?>
                                        <?php if (!$pu['unit_no'] && empty($pu['identifiers'])): ?>—<?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- vehicles owned -->
            <div class="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden">
                <div class="px-5 py-3 border-b border-gray-100"><h3 class="text-[14px] font-semibold text-[#173a67]">Products / Vehicles Owned</h3></div>
                <div class="overflow-x-auto">
                    <table class="w-full text-[13px]">
                        <thead class="bg-gray-50 text-gray-500 text-[12px]">
                            <tr>
                                <th class="text-left px-5 py-2.5">Unit</th><th class="text-left px-5 py-2.5">Product / Model</th>
                                <th class="text-left px-5 py-2.5">Status</th><th class="text-left px-5 py-2.5">Invoice</th>
                                <th class="text-left px-5 py-2.5">Sold</th><th class="text-left px-5 py-2.5">Delivered</th>
                                <th class="text-left px-5 py-2.5">Identifiers</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <?php if (empty($vehicles)): ?>
                                <tr><td colspan="7" class="px-5 py-8 text-center text-gray-400">No individually-tracked items linked to this customer.</td></tr>
                            <?php endif; ?>
                            <?php foreach ($vehicles as $v): ?>
                                <tr>
                                    <td class="px-5 py-2.5"><a href="product-unit.php?id=<?php echo (int)$v['id']; ?>" class="text-[#17457a] hover:underline font-medium"><?php echo e($v['unit_no']); ?></a></td>
                                    <td class="px-5 py-2.5"><?php echo e($v['product_name']); ?><?php if ($v['model']): ?> <span class="text-gray-400 text-[11px]">/ <?php echo e($v['model']); ?></span><?php endif; ?></td>
                                    <td class="px-5 py-2.5"><span class="text-[11px] rounded-full px-2 py-0.5 bg-gray-100 text-gray-700"><?php echo e($v['status']); ?></span></td>
                                    <td class="px-5 py-2.5 text-gray-600"><?php echo e($v['invoice_no'] ?: '—'); ?></td>
                                    <td class="px-5 py-2.5 text-gray-500"><?php echo e($v['sale_date'] ?: '—'); ?></td>
                                    <td class="px-5 py-2.5 text-gray-500"><?php echo e($v['delivery_date'] ?: '—'); ?></td>
                                    <td class="px-5 py-2.5 text-gray-500 text-[12px]">
                                        <?php foreach (($v['identifiers'] ?? []) as $k => $val): ?><span class="block"><?php echo e($k); ?>: <?php echo e($val); ?></span><?php endforeach; ?>
                                        <?php if (empty($v['identifiers'])): ?>—<?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- customer history -->
            <div class="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden">
                <div class="px-5 py-3 border-b border-gray-100"><h3 class="text-[14px] font-semibold text-[#173a67]">Customer History</h3></div>
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
                <summary class="cursor-pointer px-5 py-4 text-[14px] font-semibold text-[#173a67] flex items-center gap-2"><i data-lucide="plus" class="w-4 h-4"></i> Add Customer</summary>
                <form method="post" class="px-5 pb-5 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 text-[13px]">
                    <input type="hidden" name="action" value="create_customer">
                    <?php
                    cust_input('customer_name', 'Customer Name *', '', 'text', 2);
                    cust_input('mobile', 'Mobile Number', '');
                    cust_input('email', 'Email Address', '');
                    cust_input('dob', 'Date of Birth', '', 'date');
                    cust_input('address', 'Address', '', 'text', 3);
                    cust_input('state', 'State', '');
                    cust_input('city', 'City', '');
                    cust_input('district', 'District', '');
                    cust_input('pincode', 'Pincode', '');
                    cust_input('country', 'Country', 'India');
                    ?>
                    <label class="block"><span class="text-gray-500 text-[11px]">Customer Status</span>
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

                    <div class="sm:col-span-2 lg:col-span-3">
                        <button class="bg-[#17457a] text-white rounded-lg px-5 py-2 text-[13px]">Create Customer</button>
                        <span class="ml-2 text-[11px] text-gray-400">Customer ID is generated automatically. A duplicate-mobile/email warning is shown but records are never merged.</span>
                    </div>
                </form>
            </details>

            <form method="get" class="flex flex-wrap gap-2">
                <input name="q" value="<?php echo e($q); ?>" placeholder="Search name / mobile / email / Customer ID / chassis / any identifier / invoice" class="flex-1 min-w-[240px] border border-gray-300 rounded-lg px-3 py-2 text-[13px]">
                <select name="status" class="border border-gray-300 rounded-lg px-3 py-2 text-[13px] bg-white">
                    <option value="">All statuses</option>
                    <option value="Active" <?php echo $fstat === 'Active' ? 'selected' : ''; ?>>Active</option>
                    <option value="Inactive" <?php echo $fstat === 'Inactive' ? 'selected' : ''; ?>>Inactive</option>
                </select>
                <button class="bg-[#17457a] text-white rounded-lg px-4 py-2 text-[13px]">Search</button>
                <?php if ($q !== '' || $fstat !== ''): ?><a href="customers.php" class="border border-gray-300 rounded-lg px-4 py-2 text-[13px] bg-white">Clear</a><?php endif; ?>
            </form>

            <div class="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-[13px]">
                        <thead class="bg-gray-50 text-gray-500 text-[12px]">
                            <tr>
                                <th class="text-left px-5 py-2.5">Customer</th>
                                <th class="text-left px-5 py-2.5">Customer ID</th>
                                <th class="text-left px-5 py-2.5">Mobile</th>
                                <th class="text-left px-5 py-2.5">Email</th>
                                <th class="text-left px-5 py-2.5">City / State</th>
                                <th class="text-left px-5 py-2.5">Status</th>
                                <th class="px-5 py-2.5"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <?php if (empty($list)): ?>
                                <tr><td colspan="7" class="px-5 py-8 text-center text-gray-400">No customers<?php echo ($q !== '' || $fstat !== '') ? ' match this search' : ' yet — use “Add Customer” above'; ?>.</td></tr>
                            <?php endif; ?>
                            <?php foreach ($list as $c): ?>
                                <tr>
                                    <td class="px-5 py-2.5 font-medium text-gray-800"><?php echo e($c['customer_name']); ?></td>
                                    <td class="px-5 py-2.5 text-gray-500"><?php echo e($c['customer_code'] ?: ('#' . $c['id'])); ?></td>
                                    <td class="px-5 py-2.5 text-gray-500"><?php echo e($c['mobile'] ?: '—'); ?></td>
                                    <td class="px-5 py-2.5 text-gray-500"><?php echo e($c['email'] ?: '—'); ?></td>
                                    <td class="px-5 py-2.5 text-gray-500"><?php echo e(trim(($c['city'] ?: '—') . ' / ' . ($c['state'] ?: '—'))); ?></td>
                                    <td class="px-5 py-2.5"><span class="text-[11px] rounded-full px-2 py-0.5 <?php echo $c['status'] === 'Active' ? 'bg-green-50 text-green-700' : 'bg-gray-200 text-gray-600'; ?>"><?php echo e($c['status']); ?></span></td>
                                    <td class="px-5 py-2.5 text-right"><a href="customers.php?id=<?php echo (int)$c['id']; ?>" class="text-[12px] text-[#17457a] hover:underline">View</a></td>
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
