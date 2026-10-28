<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/includes/inventory.php';   // pulls customers.php (frc_unit_identifiers)
if (!isset($conn) || !($conn instanceof mysqli)) die('Database connection failed.');

$fid = (int)$franchiseId;                            // authenticated franchise PK — never from URL
$id  = (int)($_GET['id'] ?? 0);

/* ---- the sale (strictly scoped to this franchise) ---- */
$sale = null;
$s = $conn->prepare("SELECT cp.*, c.customer_code, c.mobile AS c_mobile, c.email AS c_email,
                            c.address AS c_address, c.city AS c_city, c.state AS c_state,
                            c.district AS c_district, c.pincode AS c_pincode,
                            p.name AS current_product_name, p.model AS current_model, p.category AS p_category,
                            u.unit_no, u.status AS unit_status
                     FROM fr_customer_purchases cp
                     LEFT JOIN fr_customers c ON c.id = cp.customer_id
                     LEFT JOIN fr_products  p ON p.id = cp.product_id
                     LEFT JOIN fr_units     u ON u.id = cp.unit_id
                     WHERE cp.id = ? AND cp.franchise_id = ? LIMIT 1");
$s->bind_param('ii', $id, $fid);
$s->execute();
$sale = $s->get_result()->fetch_assoc() ?: null;
$s->close();

if (!$sale) {
    http_response_code(404);
    echo '<p style="font-family:sans-serif;padding:40px">Invoice not found for this franchise. <a href="sales.php">Back to Sales</a></p>';
    exit;
}

$idents = (!empty($sale['unit_id']) && function_exists('frc_unit_identifiers'))
    ? frc_unit_identifiers($conn, (int)$sale['unit_id']) : [];

/* ---- franchise ("from") details ---- */
$F = $GLOBALS['franchise'] ?? [];
$ftype = strtolower(trim((string)($franchiseType ?? '')));
$typeLabel = $ftype === 'city' ? 'City Franchise' : ($ftype === 'mobile' ? 'Mobile Franchise' : 'State Franchise');
$fromLines = array_values(array_filter([
    trim((string)($F['address_1'] ?? '')),
    trim((string)($F['address_2'] ?? '')),
    trim(implode(', ', array_filter([$F['city'] ?? '', $F['state'] ?? '', $F['postal_code'] ?? '']))),
]));

$pname  = $sale['current_product_name'] ?: $sale['product_name'] ?: 'Product';
$model  = $sale['current_model'] ?: $sale['model'];
$qtyN   = max(1, (int)$sale['quantity']);
$rate   = (float)$sale['unit_price'] ?: ((float)$sale['sale_amount'] / $qtyN);
$disc   = (float)($sale['discount'] ?? 0);
$total  = (float)$sale['sale_amount'];
$gross  = $rate * $qtyN;
if ($gross < $total) $gross = $total + $disc;
$paidAmt   = (float)($sale['amount_paid'] ?? 0);
$balance   = max(0.0, $total - $paidAmt);
$payMethod = trim((string)($sale['payment_method'] ?? ''));
$invNo  = $sale['invoice_no'] ?: ('SALE-' . str_pad((string)$sale['id'], 5, '0', STR_PAD_LEFT));
$invDt  = $sale['sale_date'] ?: substr((string)$sale['created_at'], 0, 10);

if (!function_exists('siv_inr')) {
    function siv_inr($n) { return '₹' . number_format((float)$n, 2); }
}
if (!function_exists('siv_words')) {
    function siv_words($num) {
    $num = (int) round($num);
    if ($num === 0) return 'Zero Rupees Only';
    $ones = ['', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine', 'Ten',
        'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen'];
    $tens = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];
    $seg = function ($n) use (&$seg, $ones, $tens) {
        if ($n < 20) return $ones[$n];
        if ($n < 100) return trim($tens[intdiv($n, 10)] . ' ' . $ones[$n % 10]);
        return trim($ones[intdiv($n, 100)] . ' Hundred ' . $seg($n % 100));
    };
    $out = '';
    $crore = intdiv($num, 10000000); $num %= 10000000;
    $lakh  = intdiv($num, 100000);   $num %= 100000;
    $thou  = intdiv($num, 1000);     $num %= 1000;
    $rest  = $num;
    if ($crore) $out .= $seg($crore) . ' Crore ';
    if ($lakh)  $out .= $seg($lakh) . ' Lakh ';
    if ($thou)  $out .= $seg($thou) . ' Thousand ';
    if ($rest)  $out .= $seg($rest) . ' ';
    return trim($out) . ' Rupees Only';
    }
}
$payBadge = $sale['payment_status'] === 'Paid' ? '#0E7A3B' : '#B25400';
$delBadge = $sale['delivery_status'] === 'Delivered' ? '#0E7A3B' : '#6A6A6A';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Invoice <?php echo e($invNo); ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        :root{ --saffron:#E0700C; --green:#0E7A3B; --ink:#232323; --ink2:#6A6A6A; --ink3:#9A948C; --line:#E7E2D8; }
        *{ box-sizing:border-box; }
        body{ margin:0; background:#F4F1EA; font-family:'Segoe UI',system-ui,Arial,sans-serif; color:var(--ink); }
        .bar{ max-width:820px; margin:22px auto 0; display:flex; gap:10px; justify-content:flex-end; }
        .btn{ border:1px solid var(--line); background:#fff; color:var(--ink); border-radius:9px; padding:9px 16px;
            font-size:13px; font-weight:600; cursor:pointer; text-decoration:none; display:inline-flex; align-items:center; gap:8px; }
        .btn.primary{ background:linear-gradient(100deg,var(--saffron),var(--green)); color:#fff; border:0; }
        .sheet{ max-width:820px; margin:14px auto 40px; background:#fff; border:1px solid var(--line);
            border-radius:14px; overflow:hidden; box-shadow:0 20px 40px -24px rgba(120,60,10,.25); }
        .accent{ height:5px; background:linear-gradient(90deg,var(--saffron) 0%,#F0A44F 45%,#7FBF8E 55%,var(--green) 100%); }
        .pad{ padding:30px 34px; }
        .top{ display:flex; justify-content:space-between; gap:24px; flex-wrap:wrap; }
        .brand h1{ margin:0; font-size:20px; letter-spacing:-.01em; }
        .brand .muted, .muted{ color:var(--ink2); font-size:12.5px; line-height:1.6; }
        .doc{ text-align:right; }
        .doc .tag{ display:inline-block; font-size:11px; letter-spacing:.16em; text-transform:uppercase;
            color:var(--saffron); font-weight:700; }
        .doc h2{ margin:2px 0 8px; font-size:26px; letter-spacing:.02em; }
        .kv{ font-size:12.5px; color:var(--ink2); }
        .kv b{ color:var(--ink); }
        .parties{ display:flex; gap:22px; flex-wrap:wrap; margin-top:24px; }
        .party{ flex:1; min-width:230px; border:1px solid var(--line); border-radius:11px; padding:14px 16px; background:#FCFAF6; }
        .party .lbl{ font-size:10.5px; letter-spacing:.14em; text-transform:uppercase; color:var(--ink3); font-weight:700; }
        .party .nm{ font-size:14.5px; font-weight:700; margin:5px 0 3px; }
        table.items{ width:100%; border-collapse:collapse; margin-top:24px; font-size:13px; }
        table.items th{ background:#FBF6EE; color:var(--ink2); text-align:left; padding:10px 12px; font-size:11px;
            letter-spacing:.06em; text-transform:uppercase; border-bottom:1px solid var(--line); }
        table.items td{ padding:12px; border-bottom:1px solid #F1ECE3; vertical-align:top; }
        table.items td.r, table.items th.r{ text-align:right; }
        .idents{ margin-top:6px; font-size:11.5px; color:var(--ink2); }
        .idents span{ display:inline-block; background:#F3EEE4; border-radius:6px; padding:2px 7px; margin:2px 4px 0 0; }
        .totals{ margin-top:18px; margin-left:auto; width:300px; font-size:13px; }
        .totals div{ display:flex; justify-content:space-between; padding:7px 0; }
        .totals .grand{ border-top:2px solid var(--ink); margin-top:4px; padding-top:10px; font-size:16px; font-weight:800; }
        .words{ margin-top:14px; font-size:12.5px; color:var(--ink2); }
        .words b{ color:var(--ink); }
        .status{ margin-top:20px; display:flex; gap:10px; flex-wrap:wrap; }
        .chip{ font-size:11.5px; font-weight:700; border-radius:999px; padding:5px 12px; border:1px solid var(--line); }
        .foot{ margin-top:26px; padding-top:16px; border-top:1px dashed var(--line); font-size:11.5px; color:var(--ink3); }
        @media print{
            body{ background:#fff; }
            .bar{ display:none; }
            .sheet{ box-shadow:none; border:0; margin:0; max-width:none; border-radius:0; }
            @page{ margin:14mm; }
        }
    </style>
</head>
<body>

<div class="bar">
    <a href="sales.php" class="btn">← Back to Sales</a>
    <button class="btn primary" onclick="window.print()">⬇ Download / Print</button>
</div>

<div class="sheet">
    <div class="accent"></div>
    <div class="pad">

        <div class="top">
            <div class="brand">
                <h1><?php echo e($franchiseName); ?></h1>
                <div class="muted">
                    <?php echo e($typeLabel); ?> · Code <?php echo e($franchiseCode ?: '—'); ?><br>
                    <?php foreach ($fromLines as $ln) { echo e($ln) . '<br>'; } ?>
                    <?php if (!empty($F['mobile'])): ?>Mob: <?php echo e($F['mobile']); ?><?php endif; ?>
                    <?php if (!empty($F['email'])): ?> · <?php echo e($F['email']); ?><?php endif; ?><br>
                    <?php if (!empty($F['gst_no'])): ?>GSTIN: <?php echo e($F['gst_no']); ?><?php endif; ?>
                </div>
            </div>
            <div class="doc">
                <span class="tag">Tax Invoice</span>
                <h2>INVOICE</h2>
                <div class="kv">Invoice No: <b><?php echo e($invNo); ?></b></div>
                <div class="kv">Date: <b><?php echo e(date('d M Y', strtotime($invDt))); ?></b></div>
                <?php if (!empty($sale['sale_ref']) && $sale['sale_ref'] !== $invNo): ?>
                    <div class="kv">Ref: <b><?php echo e($sale['sale_ref']); ?></b></div>
                <?php endif; ?>
            </div>
        </div>

        <div class="parties">
            <div class="party">
                <div class="lbl">Bill To</div>
                <div class="nm"><?php echo e($sale['customer_name'] ?: 'Walk-in Customer'); ?></div>
                <div class="muted">
                    <?php if (!empty($sale['customer_code'])): ?>Customer ID: <?php echo e($sale['customer_code']); ?><br><?php endif; ?>
                    <?php
                    $custAddr = array_filter([$sale['c_address'] ?? '', trim(implode(', ', array_filter([$sale['c_district'] ?? '', $sale['c_city'] ?? '', $sale['c_state'] ?? '', $sale['c_pincode'] ?? ''])))]);
                    foreach ($custAddr as $ln) echo e($ln) . '<br>';
                    ?>
                    <?php if (!empty($sale['c_mobile'])): ?>Mob: <?php echo e($sale['c_mobile']); ?><?php endif; ?>
                    <?php if (!empty($sale['c_email'])): ?> · <?php echo e($sale['c_email']); ?><?php endif; ?>
                </div>
            </div>
            <div class="party">
                <div class="lbl">Sale Details</div>
                <div class="muted" style="margin-top:5px;">
                    Sold by: <b><?php echo e($sale['created_by'] ?: $franchiseName); ?></b><br>
                    Category: <?php echo e($sale['p_category'] ?: '—'); ?><br>
                    <?php if (!empty($sale['unit_no'])): ?>Unit / Vehicle: <b><?php echo e($sale['unit_no']); ?></b><br><?php endif; ?>
<?php if ($payMethod !== ''): ?>Mode: <b><?php echo e($payMethod); ?></b><br><?php endif; ?>
                    Payment: <b style="color:<?php echo $payBadge; ?>"><?php echo e($sale['payment_status']); ?></b> ·
                    Delivery: <b style="color:<?php echo $delBadge; ?>"><?php echo e($sale['delivery_status']); ?></b>
                </div>
            </div>
        </div>

        <table class="items">
            <thead>
                <tr>
                    <th style="width:52px;">#</th>
                    <th>Description</th>
                    <th class="r" style="width:70px;">Qty</th>
                    <th class="r" style="width:130px;">Rate</th>
                    <th class="r" style="width:140px;">Amount</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>1</td>
                    <td>
                        <b><?php echo e($pname); ?></b><?php if ($model): ?> — <?php echo e($model); ?><?php endif; ?>
                        <?php if (!empty($idents)): ?>
                            <div class="idents">
                                <?php foreach ($idents as $k => $v): ?><span><?php echo e($k); ?>: <?php echo e($v); ?></span><?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($sale['remarks'])): ?><div class="muted" style="margin-top:5px;"><?php echo e($sale['remarks']); ?></div><?php endif; ?>
                    </td>
                    <td class="r"><?php echo $qtyN; ?></td>
                    <td class="r"><?php echo siv_inr($rate); ?></td>
                    <td class="r"><?php echo siv_inr($total); ?></td>
                </tr>
            </tbody>
        </table>

        <div class="totals">
            <div><span>Subtotal</span><span><?php echo siv_inr($gross); ?></span></div>
            <?php if ($disc > 0): ?><div><span>Discount</span><span>− <?php echo siv_inr($disc); ?></span></div><?php endif; ?>
            <div class="muted"><span>Taxes</span><span>Inclusive</span></div>
            <div class="grand"><span>Total</span><span><?php echo siv_inr($total); ?></span></div>
            <?php if ($sale['payment_status'] !== 'Paid'): ?>
                <div style="margin-top:6px;"><span>Amount received</span><span><?php echo siv_inr($paidAmt); ?></span></div>
                <div style="color:#B25400;font-weight:700;"><span>Balance due</span><span><?php echo siv_inr($balance); ?></span></div>
            <?php endif; ?>
        </div>

        <div class="words">Amount in words: <b><?php echo e(siv_words($total)); ?></b></div>

        <div class="foot">
            This is a computer-generated invoice issued by <?php echo e($franchiseName); ?> (<?php echo e($franchiseCode); ?>).
            Goods once sold are subject to the franchise's return &amp; warranty policy. For any query, contact the franchise using the details above.
        </div>

    </div>
</div>

</body>
</html>
