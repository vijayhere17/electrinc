<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/includes/inventory.php';   // pulls customers.php + franchise_finance.php
if (!isset($conn) || !($conn instanceof mysqli)) die('Database connection failed.');

$fid   = (int)$franchiseId;                          // authenticated franchise PK — never from URL
$fcode = (string)$franchiseCode;
$ftype = (string)$franchiseType;
$by    = (string)($franchiseName ?: $franchiseCode ?: ('FR' . $fid));

/* ---------------- AJAX: product + its available units ---------------- */
if (($_GET['ajax'] ?? '') === 'product') {
    header('Content-Type: application/json');
    $pidA = (int)($_GET['product_id'] ?? 0);
    $p = fr_product_get($conn, $fid, $pidA);
    if (!$p) { echo json_encode(['ok' => false]); exit; }
    $out = [
        'ok'           => true,
        'is_serialized' => (int)$p['is_serialized'],
        'price'        => (float)$p['price'],
        'available'    => 0,
        'units'        => [],
    ];
    if ((int)$p['is_serialized'] === 1) {
        $r = $conn->query("SELECT id, unit_no FROM fr_units WHERE franchise_id = " . $fid . " AND product_id = " . $pidA . " AND status = 'Available' ORDER BY id ASC");
        while ($r && ($u = $r->fetch_assoc())) {
            $idv = frc_unit_identifiers($conn, (int)$u['id']);
            $out['units'][] = ['id' => (int)$u['id'], 'unit_no' => $u['unit_no'], 'idents' => $idv];
        }
        $out['available'] = count($out['units']);
    } else {
        $out['available'] = (int)$p['qty_available'];
    }
    echo json_encode($out);
    exit;
}

/* ---------------- POST: create sale ---------------- */
$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create_sale') {
    try {
        /* 1. customer */
        $custRef  = (int)($_POST['customer_ref'] ?? 0);
        $custName = trim((string)($_POST['customer_name'] ?? ''));
        if ($custRef <= 0 && ($_POST['new_customer'] ?? '') === '1') {
            $nc = frc_customer_save($conn, $fid, $fcode, [
                'customer_name' => $custName,
                'mobile'        => $_POST['new_mobile'] ?? '',
                'email'         => $_POST['new_email'] ?? '',
            ], $by);
            if (empty($nc['ok'])) throw new RuntimeException($nc['message'] ?? 'Could not add customer.');
            $custRef  = (int)$nc['id'];
        }

        /* 2. product / unit / qty */
        $pid   = (int)($_POST['product_id'] ?? 0);
        $prod  = fr_product_get($conn, $fid, $pid);
        if (!$prod) throw new RuntimeException('Select a valid product.');
        $serial = (int)$prod['is_serialized'] === 1;
        $unitId = (int)($_POST['unit_id'] ?? 0);
        $qty    = $serial ? 1 : max(1, (int)($_POST['quantity'] ?? 1));

        /* 3. pricing */
        $price   = round((float)($_POST['selling_price'] ?? $prod['price']), 2);
        $discount = max(0.0, round((float)($_POST['discount'] ?? 0), 2));
        $final    = max(0.0, ($price * $qty) - $discount);
        $payMethod = trim((string)($_POST['payment_method'] ?? 'Cash'));
        $payStatus = in_array(($_POST['payment_status'] ?? 'Paid'), ['Paid', 'Partial', 'Pending'], true) ? $_POST['payment_status'] : 'Paid';
        $paid      = $payStatus === 'Paid' ? $final : ($payStatus === 'Partial' ? min($final, max(0.0, round((float)($_POST['amount_paid'] ?? 0), 2))) : 0.0);
        $deliverNow = ($_POST['deliver_now'] ?? '') === '1';
        $today     = date('Y-m-d');

        $meta = [
            'customer_ref'   => $custRef,
            'customer_name'  => $custName,
            'unit_price'     => $price,
            'discount'       => $discount,
            'sale_amount'    => $final,
            'payment_method' => $payMethod,
            'payment_status' => $payStatus,
            'amount_paid'    => $paid,
            'sale_status'    => 'Completed',
            'sale_date'      => $today,
            'franchise_code' => $fcode,
            'franchise_type' => $ftype,
        ];

        if ($serial) {
            if ($unitId <= 0) throw new RuntimeException('Select the exact unit / scooter being sold.');
            $chk = fr_unit_get($conn, $fid, $unitId);
            if (!$chk || (string)$chk['status'] !== 'Available') throw new RuntimeException('That unit is not available for sale.');
            $meta['delivery_date'] = $deliverNow ? $today : '';
            $r = fr_unit_set_status($conn, $fid, $unitId, $deliverNow ? 'Delivered' : 'Sold', $meta, $by);
            if (empty($r['ok'])) throw new RuntimeException($r['message'] ?? 'Sale failed.');
            $row = $conn->query("SELECT id FROM fr_customer_purchases WHERE franchise_id = " . $fid . " AND unit_id = " . $unitId . " ORDER BY id DESC LIMIT 1")->fetch_assoc();
        } else {
            $meta['delivery_status'] = $deliverNow ? 'Delivered' : 'Pending';
            $r = fr_nonserial_move($conn, $fid, $pid, 'sell', $qty, $meta, $by);
            if (empty($r['ok'])) throw new RuntimeException($r['message'] ?? 'Sale failed.');
            $row = $conn->query("SELECT id FROM fr_customer_purchases WHERE franchise_id = " . $fid . " AND product_id = " . $pid . " ORDER BY id DESC LIMIT 1")->fetch_assoc();
        }

        $saleId = (int)($row['id'] ?? 0);
        if ($saleId > 0) { header('Location: sale-invoice.php?id=' . $saleId . '&new=1'); exit; }
        header('Location: sales.php'); exit;

    } catch (\Throwable $e) {
        $err = $e->getMessage();
    }
}

/* ---------------- form data ---------------- */
$customers = frc_active_customers($conn, $fid);
$products  = [];
$r = $conn->query("SELECT id, name, model, price, is_serialized, status FROM fr_products WHERE franchise_id = " . $fid . " AND status = 'Active' ORDER BY name ASC");
while ($r && ($x = $r->fetch_assoc())) $products[] = $x;

$recent = [];
$r = $conn->query("SELECT cp.id, cp.invoice_no, cp.sale_date, cp.sale_amount, cp.payment_status,
                          COALESCE(NULLIF(c.customer_name,''), cp.customer_name) AS buyer, cp.product_name
                   FROM fr_customer_purchases cp
                   LEFT JOIN fr_customers c ON c.id = cp.customer_id
                   WHERE cp.franchise_id = " . $fid . " ORDER BY cp.id DESC LIMIT 8");
while ($r && ($x = $r->fetch_assoc())) $recent[] = $x;

function pos_money($n) { return '₹' . number_format((float)$n, 2); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Billing POS</title>
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

            <div class="bg-[#17457a] rounded-xl px-5 sm:px-6 py-4 text-white shadow flex items-center justify-between flex-wrap gap-3">
                <div>
                    <h1 class="text-[18px] sm:text-[20px] font-semibold">Billing POS — New Sale</h1>
                    <p class="text-[12px] text-white/80 mt-1"><?php echo e($franchiseName); ?> · <?php echo e($franchiseCode); ?> · one screen: customer → unit → invoice</p>
                </div>
                <div class="flex gap-2">
                    <a href="sales.php" class="bg-white/15 hover:bg-white/25 rounded-lg px-3 py-2 text-[12px]">All Sales</a>
                    <a href="stock-report.php" class="bg-white/15 hover:bg-white/25 rounded-lg px-3 py-2 text-[12px]">Stock</a>
                </div>
            </div>

            <?php if ($err): ?><div class="rounded-lg border border-red-200 bg-red-50 px-4 py-2.5 text-red-700 text-[13px]"><?php echo e($err); ?></div><?php endif; ?>

            <?php if (empty($products)): ?>
                <div class="bg-white border border-gray-200 rounded-xl p-6 text-center text-gray-500 text-[13px]">
                    No active products. <a href="inventory.php" class="text-[#17457a] underline">Add a product</a> and receive stock first.
                </div>
            <?php else: ?>
            <form method="post" id="posForm" class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <input type="hidden" name="action" value="create_sale">

                <!-- LEFT: customer + product -->
                <div class="lg:col-span-2 space-y-6">

                    <!-- CUSTOMER -->
                    <div class="bg-white border border-gray-200 rounded-xl p-5 shadow-sm">
                        <h2 class="text-[14px] font-semibold text-[#173a67] mb-3 flex items-center gap-2"><i data-lucide="user" class="w-4 h-4"></i> Customer</h2>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-[13px]">
                            <label class="block sm:col-span-2"><span class="text-gray-500 text-[11px]">Existing customer</span>
                                <select name="customer_ref" id="customer_ref" class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2">
                                    <option value="0">— walk-in / new —</option>
                                    <?php foreach ($customers as $c): ?>
                                        <option value="<?php echo (int)$c['id']; ?>"><?php echo e($c['customer_name'] . ($c['mobile'] ? ' · ' . $c['mobile'] : '') . ' (' . $c['customer_code'] . ')'); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                            <label class="flex items-center gap-2 sm:col-span-2 text-[12px]"><input type="checkbox" name="new_customer" value="1" id="new_customer"> Save as a new customer</label>
                            <label class="block"><span class="text-gray-500 text-[11px]">Name <span id="nameReq" class="hidden text-red-500">*</span></span>
                                <input name="customer_name" id="customer_name" class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2" placeholder="Customer name (or leave for walk-in)"></label>
                            <label class="block"><span class="text-gray-500 text-[11px]">Mobile</span>
                                <input name="new_mobile" class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2"></label>
                            <label class="block sm:col-span-2 nc-only hidden"><span class="text-gray-500 text-[11px]">Email</span>
                                <input name="new_email" type="email" class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2"></label>
                        </div>
                    </div>

                    <!-- PRODUCT + UNIT -->
                    <div class="bg-white border border-gray-200 rounded-xl p-5 shadow-sm">
                        <h2 class="text-[14px] font-semibold text-[#173a67] mb-3 flex items-center gap-2"><i data-lucide="package" class="w-4 h-4"></i> Product &amp; Unit</h2>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-[13px]">
                            <label class="block sm:col-span-2"><span class="text-gray-500 text-[11px]">Product *</span>
                                <select name="product_id" id="product_id" required class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2">
                                    <option value="">Select…</option>
                                    <?php foreach ($products as $p): ?>
                                        <option value="<?php echo (int)$p['id']; ?>" data-price="<?php echo (float)$p['price']; ?>" data-serial="<?php echo (int)$p['is_serialized']; ?>">
                                            <?php echo e($p['name'] . ($p['model'] ? ' / ' . $p['model'] : '')); ?> <?php echo (int)$p['is_serialized'] === 1 ? '· unit-tracked' : '· qty'; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </label>

                            <div id="unitWrap" class="block sm:col-span-2 hidden">
                                <span class="text-gray-500 text-[11px]">Select the exact unit / scooter *</span>
                                <select name="unit_id" id="unit_id" class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2">
                                    <option value="">—</option>
                                </select>
                                <p id="unitIdents" class="mt-1.5 text-[11px] text-gray-500"></p>
                                <p class="text-[11px] text-gray-400">Only <b>available</b> units are listed — a sold or reserved unit cannot be picked.</p>
                            </div>

                            <label id="qtyWrap" class="block hidden"><span class="text-gray-500 text-[11px]">Quantity *</span>
                                <input name="quantity" id="quantity" type="number" min="1" value="1" class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2"></label>

                            <p id="stockHint" class="sm:col-span-2 text-[12px] text-gray-500"></p>
                        </div>
                    </div>
                </div>

                <!-- RIGHT: pricing + payment -->
                <div class="space-y-6">
                    <div class="bg-white border border-gray-200 rounded-xl p-5 shadow-sm">
                        <h2 class="text-[14px] font-semibold text-[#173a67] mb-3 flex items-center gap-2"><i data-lucide="receipt" class="w-4 h-4"></i> Pricing</h2>
                        <div class="space-y-3 text-[13px]">
                            <label class="block"><span class="text-gray-500 text-[11px]">Selling price (per unit)</span>
                                <input name="selling_price" id="selling_price" type="number" step="0.01" class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2"></label>
                            <label class="block"><span class="text-gray-500 text-[11px]">Discount (total)</span>
                                <input name="discount" id="discount" type="number" step="0.01" value="0" class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2"></label>
                            <div class="flex items-center justify-between border-t border-gray-100 pt-3">
                                <span class="text-gray-500">Final amount</span>
                                <span id="finalAmt" class="text-[20px] font-bold text-[#173a67]">₹0.00</span>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white border border-gray-200 rounded-xl p-5 shadow-sm">
                        <h2 class="text-[14px] font-semibold text-[#173a67] mb-3 flex items-center gap-2"><i data-lucide="indian-rupee" class="w-4 h-4"></i> Payment</h2>
                        <div class="space-y-3 text-[13px]">
                            <label class="block"><span class="text-gray-500 text-[11px]">Method</span>
                                <select name="payment_method" class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2">
                                    <?php foreach (['Cash', 'UPI', 'Card', 'Bank Transfer', 'Finance / Loan', 'Cheque', 'Other'] as $pm): ?>
                                        <option><?php echo $pm; ?></option>
                                    <?php endforeach; ?>
                                </select></label>
                            <label class="block"><span class="text-gray-500 text-[11px]">Status</span>
                                <select name="payment_status" id="payment_status" class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2">
                                    <option>Paid</option><option>Partial</option><option>Pending</option>
                                </select></label>
                            <label class="block hidden" id="paidWrap"><span class="text-gray-500 text-[11px]">Amount received</span>
                                <input name="amount_paid" id="amount_paid" type="number" step="0.01" value="0" class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2">
                                <span id="remainHint" class="text-[11px] text-amber-600"></span></label>
                            <label class="flex items-center gap-2 text-[12px] pt-1"><input type="checkbox" name="deliver_now" value="1" checked> Deliver now (mark Delivered)</label>
                        </div>
                        <button class="mt-4 w-full bg-[#17457a] hover:bg-[#123a66] text-white rounded-lg px-5 py-2.5 text-[13px] font-semibold">
                            Create Sale &amp; Generate Invoice
                        </button>
                        <p class="mt-2 text-[11px] text-gray-400">Invoice number is generated automatically. Stock reduces on save.</p>
                    </div>
                </div>
            </form>
            <?php endif; ?>

            <!-- RECENT -->
            <div class="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden">
                <div class="px-5 py-3 border-b border-gray-100 text-[14px] font-semibold text-[#173a67]">Recent Sales</div>
                <div class="overflow-x-auto">
                    <table class="w-full text-[13px]">
                        <thead class="bg-gray-50 text-gray-500 text-[12px]"><tr>
                            <th class="text-left px-5 py-2.5">Date</th><th class="text-left px-5 py-2.5">Invoice</th>
                            <th class="text-left px-5 py-2.5">Customer</th><th class="text-left px-5 py-2.5">Product</th>
                            <th class="text-right px-5 py-2.5">Amount</th><th class="text-left px-5 py-2.5">Payment</th><th class="px-5 py-2.5"></th>
                        </tr></thead>
                        <tbody class="divide-y divide-gray-100">
                            <?php if (empty($recent)): ?><tr><td colspan="7" class="px-5 py-8 text-center text-gray-400">No sales yet.</td></tr><?php endif; ?>
                            <?php foreach ($recent as $s): ?>
                                <tr>
                                    <td class="px-5 py-2.5 text-gray-500 whitespace-nowrap"><?php echo e($s['sale_date']); ?></td>
                                    <td class="px-5 py-2.5"><a href="sale-invoice.php?id=<?php echo (int)$s['id']; ?>" target="_blank" class="text-[#0E7A3B] hover:underline"><?php echo e($s['invoice_no'] ?: 'View'); ?></a></td>
                                    <td class="px-5 py-2.5"><?php echo e($s['buyer'] ?: 'Walk-in'); ?></td>
                                    <td class="px-5 py-2.5 text-gray-600"><?php echo e($s['product_name']); ?></td>
                                    <td class="px-5 py-2.5 text-right font-semibold"><?php echo pos_money($s['sale_amount']); ?></td>
                                    <td class="px-5 py-2.5"><span class="text-[11px] rounded-full px-2 py-0.5 <?php echo $s['payment_status'] === 'Paid' ? 'bg-green-50 text-green-700' : 'bg-amber-50 text-amber-700'; ?>"><?php echo e($s['payment_status']); ?></span></td>
                                    <td class="px-5 py-2.5 text-right"><a href="sale-invoice.php?id=<?php echo (int)$s['id']; ?>" target="_blank" class="text-[12px] text-[#17457a] hover:underline">Invoice</a></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </main>
</div>

<script>
if (typeof lucide !== 'undefined') lucide.createIcons();
(function () {
    var f = document.getElementById('posForm');
    if (!f) return;
    var prod = document.getElementById('product_id'),
        unitWrap = document.getElementById('unitWrap'), unitSel = document.getElementById('unit_id'),
        unitIdents = document.getElementById('unitIdents'),
        qtyWrap = document.getElementById('qtyWrap'), qty = document.getElementById('quantity'),
        stockHint = document.getElementById('stockHint'),
        price = document.getElementById('selling_price'), disc = document.getElementById('discount'),
        finalAmt = document.getElementById('finalAmt'),
        payStatus = document.getElementById('payment_status'), paidWrap = document.getElementById('paidWrap'),
        paid = document.getElementById('amount_paid'), remainHint = document.getElementById('remainHint'),
        newCust = document.getElementById('new_customer'), custRef = document.getElementById('customer_ref'),
        nameReq = document.getElementById('nameReq'), custName = document.getElementById('customer_name');

    function money(n){ return '₹' + (Number(n)||0).toLocaleString('en-IN', {minimumFractionDigits:2, maximumFractionDigits:2}); }
    function recalc(){
        var q = (unitWrap.classList.contains('hidden')) ? (parseInt(qty.value)||1) : 1;
        var fin = Math.max(0, (parseFloat(price.value)||0) * q - (parseFloat(disc.value)||0));
        finalAmt.textContent = money(fin);
        if (payStatus.value === 'Paid') { paid.value = fin.toFixed(2); }
        if (payStatus.value === 'Partial') {
            var rem = Math.max(0, fin - (parseFloat(paid.value)||0));
            remainHint.textContent = 'Remaining: ' + money(rem);
        } else { remainHint.textContent = ''; }
    }
    payStatus.addEventListener('change', function(){
        paidWrap.classList.toggle('hidden', payStatus.value !== 'Partial');
        recalc();
    });
    [price, disc, qty, paid].forEach(function(el){ el && el.addEventListener('input', recalc); });

    prod.addEventListener('change', function(){
        var opt = prod.options[prod.selectedIndex];
        unitWrap.classList.add('hidden'); qtyWrap.classList.add('hidden');
        unitSel.innerHTML = '<option value="">—</option>'; unitIdents.textContent = ''; stockHint.textContent = '';
        if (!prod.value) return;
        price.value = opt.getAttribute('data-price') || '';
        fetch('pos.php?ajax=product&product_id=' + encodeURIComponent(prod.value))
            .then(function(r){ return r.json(); })
            .then(function(d){
                if (!d || !d.ok) return;
                if (d.is_serialized) {
                    unitWrap.classList.remove('hidden');
                    unitSel.required = true;
                    (d.units || []).forEach(function(u){
                        var o = document.createElement('option');
                        o.value = u.id; o.textContent = u.unit_no;
                        o.dataset.idents = Object.entries(u.idents || {}).map(function(kv){ return kv[0]+': '+kv[1]; }).join('  ·  ');
                        unitSel.appendChild(o);
                    });
                    stockHint.textContent = d.available + ' unit(s) available';
                } else {
                    qtyWrap.classList.remove('hidden');
                    unitSel.required = false;
                    qty.max = d.available;
                    stockHint.textContent = d.available + ' in stock';
                }
                recalc();
            });
    });
    unitSel.addEventListener('change', function(){
        var o = unitSel.options[unitSel.selectedIndex];
        unitIdents.textContent = (o && o.dataset.idents) ? o.dataset.idents : '';
    });

    function syncCust(){
        var isNew = newCust.checked;
        document.querySelectorAll('.nc-only').forEach(function(el){ el.classList.toggle('hidden', !isNew); });
        nameReq.classList.toggle('hidden', !isNew);
        custName.required = isNew;
        if (isNew) { custRef.value = '0'; custRef.disabled = true; } else { custRef.disabled = false; }
    }
    newCust.addEventListener('change', syncCust);
    custRef.addEventListener('change', function(){ if (custRef.value !== '0') { newCust.checked = false; syncCust(); } });
    syncCust();
    recalc();
})();
</script>
</body>
</html>
