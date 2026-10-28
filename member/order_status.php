<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['customer_id'])) {
    header("Location: login.php");
    exit();
}

$pageTitle  = 'My Order Status';
$customerId = $_SESSION['customer_id'];

include __DIR__ . '/config/db.php';

function e($str) {
    return htmlspecialchars((string)($str ?? ''), ENT_QUOTES, 'UTF-8');
}

function formatOrderId(int $id, string $type = 'REPURCHASE'): string {
    if ($type === 'ACTIVATION') return 'ACT' . str_pad($id, 4, '0', STR_PAD_LEFT);
    if ($type === 'REPURCHASE') return 'ORD' . str_pad($id, 4, '0', STR_PAD_LEFT);
    return 'INV' . str_pad($id, 4, '0', STR_PAD_LEFT);
}

function fetchMemberOrderItems_member(mysqli $conn, int $orderId): array {
    $items = [];
    $stmt = $conn->prepare("SELECT product_name, product_price, quantity, amount, bv FROM member_order_items WHERE order_id = ? ORDER BY id ASC");
    if ($stmt) {
        $stmt->bind_param('i', $orderId);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) { $items[] = $row; }
        $stmt->close();
    }
    return $items;
}

function fetchEcomOrderItems_member(mysqli $conn, int $orderId): array {
    $items = [];
    $stmt = $conn->prepare("SELECT product_name, product_price, quantity FROM order_items WHERE order_id = ? ORDER BY id ASC");
    if ($stmt) {
        $stmt->bind_param('i', $orderId);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) { $items[] = $row; }
        $stmt->close();
    }
    return $items;
}

/* Fetch activation items from products_json column */
function fetchActivationItems_member(mysqli $conn, int $orderId, string $customerId): array {
    $stmt = $conn->prepare("SELECT products_json, amount, payment_mode, franchise_code, created_at FROM member_activation WHERE id = ? AND customer_id = ? LIMIT 1");
    if (!$stmt) return [];
    $stmt->bind_param('is', $orderId, $customerId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row) return [];
    $decoded = json_decode((string)($row['products_json'] ?? '[]'), true);
    if (!is_array($decoded)) return [];
    $items = [];
    foreach ($decoded as $p) {
        $items[] = [
            'product_name'  => $p['product_name'] ?? '-',
            'product_price' => $p['price'] ?? 0,
            'quantity'      => $p['qty'] ?? 1,
            'amount'        => ($p['price'] ?? 0) * ($p['qty'] ?? 1),
            'bv'            => null,
        ];
    }
    return $items;
}

$companyAddress = '123, Business Hub, Ring Road, Surat - 395002, Gujarat, India';
$companyEmail   = 'support@netavexa.com';
$companyPhone   = '+91 98765 43210';
$companyGST     = 'GSTIN: 24XXXXX0000X1ZX';

/* ─────────────────────────────────────────────
   INVOICE VIEW
───────────────────────────────────────────── */
if (isset($_GET['view_invoice']) && $_GET['view_invoice'] !== '') {
    $invoiceOrderId   = (int)$_GET['view_invoice'];
    $invoiceOrderType = strtoupper(trim($_GET['type'] ?? 'REPURCHASE'));

    $invoiceOrder = null;
    $invoiceItems = [];

    if ($invoiceOrderType === 'REPURCHASE') {
        $stmt = $conn->prepare("SELECT * FROM member_orders WHERE id = ? AND member_id = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param('is', $invoiceOrderId, $customerId);
            $stmt->execute();
            $invoiceOrder = $stmt->get_result()->fetch_assoc();
            $stmt->close();
        }
        $invoiceItems = fetchMemberOrderItems_member($conn, $invoiceOrderId);
    } elseif ($invoiceOrderType === 'ACTIVATION') {
        $stmt = $conn->prepare("SELECT * FROM member_activation WHERE id = ? AND customer_id = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param('is', $invoiceOrderId, $customerId);
            $stmt->execute();
            $invoiceOrder = $stmt->get_result()->fetch_assoc();
            $stmt->close();
        }
        $invoiceItems = fetchActivationItems_member($conn, $invoiceOrderId, $customerId);
    } else {
        $stmt = $conn->prepare("SELECT * FROM orders WHERE id = ? AND customer_id = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param('is', $invoiceOrderId, $customerId);
            $stmt->execute();
            $invoiceOrder = $stmt->get_result()->fetch_assoc();
            $stmt->close();
        }
        $invoiceItems = fetchEcomOrderItems_member($conn, $invoiceOrderId);
    }

    if (!$invoiceOrder) { die('Invoice not found.'); }

    $grandTotal       = (float)($invoiceOrder['total_amount'] ?? $invoiceOrder['amount'] ?? $invoiceOrder['total'] ?? 0);
    $memberName       = $invoiceOrder['member_name'] ?? $_SESSION['customer_name'] ?? '';
    $memberInitial    = strtoupper(substr(trim($memberName), 0, 1)) ?: 'M';
    $memberIdVal      = $invoiceOrder['member_id'] ?? $invoiceOrder['customer_id'] ?? $customerId;
    $formattedOrderId = formatOrderId($invoiceOrderId, $invoiceOrderType);

    $totalBV = 0;
    if ($invoiceOrderType === 'REPURCHASE') {
        foreach ($invoiceItems as $it) {
            $totalBV += (float)($it['bv'] ?? 0) * (int)($it['quantity'] ?? 1);
        }
    }

    header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice <?php echo e($formattedOrderId); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Inter', Arial, sans-serif; background: #eef2f7; color: #1e293b; min-height: 100vh; padding: 24px 16px 40px; }
        .action-bar { max-width: 860px; margin: 0 auto 16px; display: flex; justify-content: flex-end; gap: 10px; flex-wrap: wrap; }
        .btn { display: inline-flex; align-items: center; gap: 6px; padding: 9px 18px; border-radius: 8px; font-size: 13px; font-weight: 700; cursor: pointer; border: none; text-decoration: none; transition: opacity .15s; }
        .btn:hover { opacity: .85; }
        .btn-primary   { background: #103258; color: #fff; }
        .btn-secondary { background: #e2e8f0; color: #334155; }
        .invoice-card { max-width: 860px; margin: 0 auto; background: #fff; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 24px rgba(0,0,0,.10); }
        .inv-header { background: linear-gradient(135deg, #0d2742 0%, #1a4d80 100%); padding: 22px 28px; display: flex; align-items: flex-start; justify-content: space-between; gap: 20px; flex-wrap: wrap; }
        .inv-header-left { display: flex; flex-direction: column; gap: 12px; flex: 1; min-width: 220px; }
        .inv-brand-top { display: flex; align-items: center; gap: 14px; }
        .inv-logo-box { height: 44px; display: flex; align-items: center; justify-content: center; font-size: 11px; font-weight: 800; color: #fff; flex-shrink: 0; }
        .inv-logo-box img { height: 100%; width: auto; object-fit: contain; }
        .inv-title { font-size: 20px; font-weight: 800; color: #fff; letter-spacing: .02em; }
        .inv-company-info { display: flex; flex-direction: column; gap: 6px; }
        .inv-ci { display: inline-flex; align-items: center; gap: 6px; font-size: 11.5px; color: rgba(200,223,240,.95); font-weight: 500; }
        .inv-ci svg { flex-shrink: 0; opacity: .85; }
        .inv-ci a { color: rgba(200,223,240,.95); text-decoration: none; }
        .inv-ci a:hover { color: #fff; text-decoration: underline; }
        .inv-gst-badge { display: inline-block; background: rgba(200,151,65,.22); border: 1px solid rgba(200,151,65,.45); color: #f5d98a; font-size: 10.5px; font-weight: 700; padding: 3px 12px; border-radius: 10px; letter-spacing: .4px; width: fit-content; }
        .inv-header-right { display: flex; flex-direction: column; align-items: flex-end; gap: 6px; flex-shrink: 0; text-align: right; }
        .inv-date-label    { font-size: 11px; color: #93c5fd; font-weight: 500; }
        .inv-date-val      { font-size: 13px; color: #fff; font-weight: 700; }
        .inv-orderid-label { font-size: 11px; color: #93c5fd; font-weight: 500; margin-top: 4px; }
        .inv-orderid-val   { font-size: 16px; color: #fbbf24; font-weight: 800; letter-spacing: .04em; }
        .inv-badges { display: flex; gap: 7px; justify-content: flex-end; flex-wrap: wrap; margin-top: 4px; }
        .badge { display: inline-flex; align-items: center; gap: 4px; padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 700; }
        .badge-type     { background: rgba(255,255,255,.15); color: #e0f2fe; border: 1px solid rgba(255,255,255,.2); }
        .badge-approved { background: rgba(34,197,94,.15); color: #86efac; border: 1px solid rgba(34,197,94,.3); letter-spacing: .06em; }
        .inv-member { padding: 20px 28px 0; display: flex; align-items: center; gap: 14px; }
        .member-avatar { width: 46px; height: 46px; border-radius: 50%; background: linear-gradient(135deg, #103258, #1a6ea8); display: flex; align-items: center; justify-content: center; font-size: 20px; font-weight: 800; color: #fff; flex-shrink: 0; }
        .member-name { font-size: 18px; font-weight: 700; color: #1e293b; }
        .member-tags { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; margin-top: 5px; }
        .member-id-tag { display: inline-flex; align-items: center; gap: 5px; padding: 3px 10px; border-radius: 20px; background: #f1f5f9; font-size: 12px; color: #64748b; font-weight: 600; }
        .inv-divider { border: none; border-top: 1.5px solid #f1f5f9; margin: 18px 28px; }
        .inv-body { padding: 0 28px 24px; }
        .section-label { font-size: 11px; font-weight: 700; color: #64748b; letter-spacing: .1em; text-transform: uppercase; margin-bottom: 14px; }
        .info-grid { display: grid; grid-template-columns: repeat(3,1fr); gap: 14px; margin-bottom: 24px; }
        @media (max-width:620px){ .info-grid { grid-template-columns: 1fr 1fr; } }
        @media (max-width:400px){ .info-grid { grid-template-columns: 1fr; } }
        .info-item .lbl { font-size: 11px; color: #94a3b8; font-weight: 600; text-transform: uppercase; letter-spacing: .06em; }
        .info-item .val { font-size: 14px; font-weight: 700; color: #1e293b; margin-top: 3px; }
        .table-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; min-width: 480px; }
        thead tr { background: #0d2742; }
        thead th { padding: 13px 16px; text-align: left; font-size: 12px; font-weight: 700; color: #cbd5e1; letter-spacing: .07em; text-transform: uppercase; }
        thead th:last-child { text-align: right; }
        tbody tr { border-bottom: 1px solid #f1f5f9; }
        tbody tr:last-child { border-bottom: none; }
        tbody tr:hover { background: #f8fafc; }
        tbody td { padding: 14px 16px; font-size: 14px; color: #334155; }
        tbody td:last-child { text-align: right; font-weight: 700; color: #1e293b; }
        td.serial { color: #94a3b8; font-weight: 600; }
        .bv-cell { color: #7c3aed; font-weight: 700; text-align: center !important; }
        .totals-section { display: flex; justify-content: flex-end; margin-top: 20px; }
        .totals-box { width: 100%; max-width: 380px; border: 1.5px solid #e2e8f0; border-radius: 12px; overflow: hidden; }
        .totals-bv-row { padding: 11px 18px; border-bottom: 1px solid #f1f5f9; display: flex; justify-content: space-between; align-items: center; }
        .totals-bv-row .t-label { font-size: 13px; font-weight: 600; color: #7c3aed; }
        .totals-bv-row .t-val   { font-size: 14px; font-weight: 800; color: #7c3aed; }
        .totals-note-row { padding: 10px 18px; border-bottom: 1px solid #f1f5f9; font-size: 12px; color: #64748b; font-style: italic; text-align: center; }
        .totals-grand { background: linear-gradient(135deg, #0d2742, #1a4d80); padding: 15px 18px; display: flex; justify-content: space-between; align-items: center; }
        .totals-grand .t-label { color: #e0f2fe; font-size: 15px; font-weight: 700; }
        .totals-grand .t-val   { color: #fbbf24; font-size: 17px; font-weight: 800; }
        .inv-footer { background: #f8fafc; border-top: 1.5px solid #e2e8f0; padding: 16px 28px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px; font-size: 12px; color: #94a3b8; }
        .inv-footer strong { color: #475569; }
        @media print { .no-print { display: none !important; } body { background: #fff; padding: 0; } .invoice-card { box-shadow: none; border-radius: 0; } }
        @media (max-width:600px) { .inv-header { padding: 16px 18px; } .inv-header-right { align-items: flex-start; text-align: left; } .inv-body { padding: 0 16px 20px; } .inv-member { padding: 16px 18px 0; } .inv-divider { margin: 14px 18px; } .inv-footer { padding: 14px 18px; } .inv-title { font-size: 17px; } }
        /* Activation badge strip */
        .act-badge-strip { background: linear-gradient(90deg, #0d2742, #1a5c2a); padding: 8px 28px; display: flex; align-items: center; gap: 10px; font-size: 12px; font-weight: 700; color: #86efac; letter-spacing: .05em; }
    </style>
</head>
<body>

<div class="action-bar no-print">
    <button onclick="window.print()" class="btn btn-primary">🖨 Print / Save PDF</button>
    <a href="member_order_status.php" class="btn btn-secondary">← Back</a>
</div>

<div class="invoice-card">

    <div class="inv-header">
        <div class="inv-header-left">
            <div class="inv-brand-top">
                <div class="inv-logo-box">
                    <?php $logoPath = __DIR__ . '/assets/electrinc-logo.png'; if (file_exists($logoPath)) { echo '<img src="assets/electrinc-logo.png" alt="Logo">'; } else { echo 'LOGO'; } ?>
                </div>
                <div class="inv-title">
                    <?php
                    if ($invoiceOrderType === 'ACTIVATION')  echo 'MEMBER ACTIVATION INVOICE';
                    elseif ($invoiceOrderType === 'REPURCHASE') echo 'MEMBER NETVEXA INVOICE';
                    else echo 'NETVEXA ORDER INVOICE';
                    ?>
                </div>
            </div>
            <div class="inv-company-info">
                <span class="inv-ci">
                    <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    <?php echo e($companyAddress); ?>
                </span>
                <span class="inv-ci">
                    <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                    <a href="mailto:<?php echo e($companyEmail); ?>"><?php echo e($companyEmail); ?></a>
                </span>
                <span class="inv-ci">
                    <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                    <?php echo e($companyPhone); ?>
                </span>
                <span class="inv-gst-badge"><?php echo e($companyGST); ?></span>
            </div>
        </div>
        <div class="inv-header-right">
            <div class="inv-date-label">Invoice Date</div>
            <div class="inv-date-val"><?php echo e(date('d-m-Y')); ?></div>
            <div class="inv-orderid-label">Order ID</div>
            <div class="inv-orderid-val"><?php echo e($formattedOrderId); ?></div>
            <div class="inv-badges">
                <span class="badge badge-type">
                    <?php
                    if ($invoiceOrderType === 'ACTIVATION')  echo '✅ Activation';
                    elseif ($invoiceOrderType === 'REPURCHASE') echo '📱 Member Order';
                    else echo '🛒 E-Commerce';
                    ?>
                </span>
                <span class="badge badge-approved">APPROVED</span>
            </div>
        </div>
    </div>

    <?php if ($invoiceOrderType === 'ACTIVATION'): ?>
    <div class="act-badge-strip">
        ✅ &nbsp; Member Activation Order &nbsp;·&nbsp; Franchise ID: <?php echo e($invoiceOrder['franchise_code'] ?? '-'); ?>
    </div>
    <?php endif; ?>

    <div class="inv-member">
        <div class="member-avatar"><?php echo e($memberInitial); ?></div>
        <div>
            <div class="member-name"><?php echo e($memberName ?: '-'); ?></div>
            <div class="member-tags">
                <span class="member-id-tag">🪪 <?php echo e($memberIdVal ?: '-'); ?></span>
            </div>
        </div>
    </div>

    <hr class="inv-divider">

    <div class="inv-body">

        <div class="section-label">Order Information</div>
        <div class="info-grid">
            <div class="info-item">
                <div class="lbl">Order ID</div>
                <div class="val"><?php echo e($formattedOrderId); ?></div>
            </div>
            <div class="info-item">
                <div class="lbl">Order Date</div>
                <div class="val">
                    <?php $dateField = $invoiceOrder['created_at'] ?? $invoiceOrder['order_date'] ?? ''; echo $dateField ? e(date('d-m-Y', strtotime($dateField))) : '-'; ?>
                </div>
            </div>
            <div class="info-item">
                <div class="lbl">Payment Mode</div>
                <div class="val"><?php echo e($invoiceOrder['payment_method'] ?? $invoiceOrder['payment_mode'] ?? '-'); ?></div>
            </div>
            <div class="info-item">
                <div class="lbl">Order Type</div>
                <div class="val"><?php echo e($invoiceOrderType); ?></div>
            </div>
            <?php if (!empty($invoiceOrder['utr_number'])): ?>
            <div class="info-item">
                <div class="lbl">UTR Number</div>
                <div class="val"><?php echo e($invoiceOrder['utr_number']); ?></div>
            </div>
            <?php endif; ?>
            <?php if (!empty($invoiceOrder['bank_name'])): ?>
            <div class="info-item">
                <div class="lbl">Bank Name</div>
                <div class="val"><?php echo e($invoiceOrder['bank_name']); ?></div>
            </div>
            <?php endif; ?>
            <?php
            $franchiseIdField = $invoiceOrder['distributor_id'] ?? $invoiceOrder['franchise_code'] ?? '';
            if (!empty($franchiseIdField)):
            ?>
            <div class="info-item">
                <div class="lbl">Franchise ID</div>
                <div class="val"><?php echo e($franchiseIdField); ?></div>
            </div>
            <?php endif; ?>
        </div>

        <div class="section-label">Products</div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Product Name</th>
                        <th>Quantity</th>
                        <th>Unit Price</th>
                        <?php if ($invoiceOrderType === 'REPURCHASE'): ?>
                        <th style="text-align:center">BV</th>
                        <?php endif; ?>
                        <th style="text-align:right">Total Price</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!empty($invoiceItems)): ?>
                    <?php foreach ($invoiceItems as $idx => $item): ?>
                    <tr>
                        <td class="serial"><?php echo $idx + 1; ?></td>
                        <td><?php echo e($item['product_name'] ?? '-'); ?></td>
                        <td><?php echo (int)($item['quantity'] ?? 0); ?></td>
                        <td>₹<?php echo number_format((float)($item['product_price'] ?? 0), 2); ?></td>
                        <?php if ($invoiceOrderType === 'REPURCHASE'): ?>
                        <td class="bv-cell"><?php echo number_format((float)($item['bv'] ?? 0) * (int)($item['quantity'] ?? 1), 2); ?></td>
                        <?php endif; ?>
                        <td>₹<?php echo number_format((float)($item['amount'] ?? (($item['product_price'] ?? 0) * ($item['quantity'] ?? 0))), 2); ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="<?php echo $invoiceOrderType === 'REPURCHASE' ? 6 : 5; ?>" style="text-align:center;color:#94a3b8;padding:24px">No products found.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="totals-section">
            <div class="totals-box">
                <?php if ($invoiceOrderType === 'REPURCHASE' && $totalBV > 0): ?>
                <div class="totals-bv-row">
                    <span class="t-label">⭐ Total BV</span>
                    <span class="t-val"><?php echo number_format($totalBV, 2); ?></span>
                </div>
                <?php endif; ?>
                <div class="totals-note-row">All taxes included in the total amount</div>
                <div class="totals-grand">
                    <span class="t-label">Total Amount</span>
                    <span class="t-val">₹<?php echo number_format($grandTotal, 2); ?></span>
                </div>
            </div>
        </div>

    </div>

    <div class="inv-footer">
        <div>Thank you for choosing <strong>NETAVEXA</strong> Franchise Services.</div>
        <div>This is a system-generated invoice.</div>
    </div>

</div>

</body>
</html>
<?php
    exit;
}

/* ─────────────────────────────────────────────
   ORDER LIST DATA
───────────────────────────────────────────── */
$orderRows    = [];
$errorMessage = '';

$stmt = $conn->prepare("
(
    SELECT
        id,
        order_date,
        product_name,
        quantity,
        price,
        total,
        total_pv,
        status,
        'ECOMMERCE' AS order_type
    FROM orders
    WHERE customer_id = ?
)
UNION ALL
(
    SELECT
        id,
        created_at AS order_date,
        '' AS product_name,
        0 AS quantity,
        0 AS price,
        total_amount AS total,
        0 AS total_pv,
        'Pending' AS status,
        'REPURCHASE' AS order_type
    FROM member_orders
    WHERE member_id = ?
)
UNION ALL
(
    SELECT
        id,
        created_at AS order_date,
        '' AS product_name,
        0 AS quantity,
        0 AS price,
        amount AS total,
        0 AS total_pv,
        'Approved' AS status,
        'ACTIVATION' AS order_type
    FROM member_activation
    WHERE customer_id = ?
)
ORDER BY order_date DESC
");

if ($stmt) {

    $stmt->bind_param("sss", $customerId, $customerId, $customerId);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {

        if ($row['order_type'] === 'REPURCHASE') {
            $stmtProduct = $conn->prepare("
                SELECT
                    GROUP_CONCAT(product_name SEPARATOR ', ') AS products,
                    SUM(amount) AS total_price,
                    SUM(quantity) AS total_qty,
                    SUM(quantity * bv) AS total_bv
                FROM member_order_items
                WHERE order_id = ?
            ");
        } elseif ($row['order_type'] === 'ACTIVATION') {
            /* For activation we pull data from products_json directly */
            $stmtProduct = null;
        } else {
            $stmtProduct = $conn->prepare("
                SELECT
                    GROUP_CONCAT(product_name SEPARATOR ', ') AS products,
                    SUM(product_price * quantity) AS total_price,
                    SUM(quantity) AS total_qty
                FROM order_items
                WHERE order_id = ?
            ");
        }

        if ($row['order_type'] === 'ACTIVATION') {
            /* Decode products from JSON */
            $actStmt = $conn->prepare("SELECT products_json, amount, payment_mode, franchise_code, member_name FROM member_activation WHERE id = ? LIMIT 1");
            $actRow  = null;
            if ($actStmt) {
                $actStmt->bind_param('i', $row['id']);
                $actStmt->execute();
                $actRow = $actStmt->get_result()->fetch_assoc();
                $actStmt->close();
            }

            $actProducts = json_decode((string)($actRow['products_json'] ?? '[]'), true);
            $productNames = [];
            $totalQty     = 0;
            $totalPrice   = 0;
            $detailsHtml  = '';

            if (is_array($actProducts)) {
                foreach ($actProducts as $ap) {
                    $pName  = $ap['product_name'] ?? '-';
                    $pQty   = (int)($ap['qty']   ?? 1);
                    $pPrice = (float)($ap['price'] ?? 0);
                    $productNames[] = $pName;
                    $totalQty      += $pQty;
                    $totalPrice    += $pPrice * $pQty;
                    $detailsHtml   .= $pName . ' | Qty: ' . $pQty . ' | Price: ₹' . number_format($pPrice, 2) . "\n";
                }
            }

            $row['product_name']     = !empty($productNames) ? implode(', ', $productNames) : ($actRow['member_name'] ?? 'Activation');
            $row['order_details']    = $detailsHtml ?: 'Activation order';
            $row['shipping_details'] = 'Qty: ' . $totalQty . ' | Total: ₹' . number_format($totalPrice, 2);
            $row['invoice_no']       = formatOrderId((int)$row['id'], 'ACTIVATION');

        } else {
            $stmtProduct->bind_param("i", $row['id']);
            $stmtProduct->execute();
            $productResult = $stmtProduct->get_result()->fetch_assoc();

            if ($row['order_type'] === 'REPURCHASE') {
                $stmtItems = $conn->prepare("SELECT product_name, product_price, quantity, bv FROM member_order_items WHERE order_id = ?");
            } else {
                $stmtItems = $conn->prepare("SELECT product_name, product_price, quantity FROM order_items WHERE order_id = ?");
            }

            $stmtItems->bind_param("i", $row['id']);
            $stmtItems->execute();
            $itemResult = $stmtItems->get_result();

            $detailsHtml = '';
            while ($item = $itemResult->fetch_assoc()) {
                $detailsHtml .= $item['product_name'] . ' | Qty: ' . $item['quantity'] . ' | Price: ₹' . $item['product_price'];
                if ($row['order_type'] === 'REPURCHASE') {
                    $detailsHtml .= ' | BV: ' . $item['bv'];
                }
                $detailsHtml .= "\n";
            }

            $row['order_details'] = $detailsHtml;
            $row['product_name']  = $productResult['products'] ?: '-';

            if ($row['order_type'] === 'REPURCHASE') {
                $row['shipping_details'] =
                    'Qty: ' . ($productResult['total_qty'] ?? 0) .
                    ' | Total: ₹' . number_format($productResult['total_price'] ?? 0, 2) .
                    ' | BV: ' . ($productResult['total_bv'] ?? 0);
            } else {
                $row['shipping_details'] =
                    'Qty: ' . ($productResult['total_qty'] ?? 0) .
                    ' | Total: ₹' . number_format($productResult['total_price'] ?? 0, 2) .
                    ' | PV: ' . $row['total_pv'];
            }

            $row['invoice_no'] = formatOrderId((int)$row['id'], $row['order_type']);
        }

        $row['purchase_date']   = date('d-m-Y', strtotime($row['order_date']));
        $row['dispatch_status'] = $row['status'];
        $orderRows[] = $row;
    }

    $stmt->close();

} else {
    $errorMessage = "Query prepare failed.";
}

/* Helper: badge class for status */
function statusBadgeClass(string $status): string {
    $s = strtolower(trim($status));
    if (str_contains($s, 'dispatch') || $s === 'delivered') return 'badge-dispatched';
    if (str_contains($s, 'approved') || str_contains($s, 'active')) return 'badge-dispatched';
    if (str_contains($s, 'pending'))  return 'badge-pending';
    if (str_contains($s, 'process') || str_contains($s, 'transit')) return 'badge-processing';
    if (str_contains($s, 'cancel'))   return 'badge-cancelled';
    return 'badge-default';
}

/* Helper: type pill */
function orderTypePill(string $type): string {
    if ($type === 'ACTIVATION')  return '<span class="type-pill pill-act">✅ Activation</span>';
    if ($type === 'REPURCHASE')  return '<span class="type-pill pill-rep">🛒 Repurchase</span>';
    return '<span class="type-pill pill-eco">🛍 E-Commerce</span>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Order Status</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
    <style>
        .mobile-cards { display: none; }
        @media (max-width: 767px) {
            .desktop-table { display: none; }
            .mobile-cards  { display: block; }
            .page-wrap     { margin-left: 0 !important; }
        }
        @media (min-width: 768px) and (max-width: 1023px) {
            .page-wrap { margin-left: 0 !important; }
        }
        .badge-dispatched { background: #dcfce7; color: #15803d; }
        .badge-pending    { background: #fef9c3; color: #92400e; }
        .badge-processing { background: #dbeafe; color: #1d4ed8; }
        .badge-cancelled  { background: #fee2e2; color: #dc2626; }
        .badge-default    { background: #f1f5f9; color: #475569; }
        .order-card { animation: fadeUp 0.25s ease both; }
        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(8px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        .table-scroll::-webkit-scrollbar { height: 4px; }
        .table-scroll::-webkit-scrollbar-track { background: #f1f5f9; }
        .table-scroll::-webkit-scrollbar-thumb { background: #d6b06b; border-radius: 2px; }
        /* order type pills */
        .type-pill { display: inline-flex; align-items: center; gap: 3px; padding: 2px 8px; border-radius: 20px; font-size: 11px; font-weight: 700; white-space: nowrap; }
        .pill-act  { background: #dcfce7; color: #15803d; border: 1px solid #bbf7d0; }
        .pill-rep  { background: #dbeafe; color: #1d4ed8; border: 1px solid #bfdbfe; }
        .pill-eco  { background: #fef9c3; color: #92400e; border: 1px solid #fde68a; }
    </style>
</head>
<body class="bg-[#f3f3f3] font-sans">

<?php include __DIR__ . '/includes/sidebar.php'; ?>

<div class="page-wrap ml-0 lg:ml-64 min-h-screen">

    <?php include __DIR__ . '/includes/navbar.php'; ?>

    <div class="p-4 md:p-6">

        <div class="flex items-center gap-3 mb-6">
            <div class="h-9 w-9 rounded-lg bg-[#f3e9d7] flex items-center justify-center text-[#c7993e] flex-shrink-0">
                <i data-lucide="badge-dollar-sign" class="w-5 h-5"></i>
            </div>
            <h2 class="text-[18px] md:text-[22px] font-bold text-[#40352a] tracking-wide whitespace-nowrap">MY ORDER STATUS</h2>
            <div class="flex-1 h-px bg-[#d6b06b] hidden sm:block"></div>
            <div class="text-xs text-gray-400 font-medium whitespace-nowrap">
                <?php echo count($orderRows); ?> order<?php echo count($orderRows) !== 1 ? 's' : ''; ?>
            </div>
        </div>

        <?php if (!empty($errorMessage)): ?>
        <div class="bg-red-50 border border-red-200 text-red-600 text-sm font-semibold px-4 py-3 rounded-lg mb-4">
            <?php echo htmlspecialchars($errorMessage); ?>
        </div>
        <?php endif; ?>

        <?php if (!empty($orderRows)): ?>

        <!-- DESKTOP TABLE -->
        <div class="desktop-table bg-white border border-[#dddddd] rounded-xl overflow-hidden shadow-sm">
            <div class="table-scroll overflow-x-auto">
                <table class="w-full text-sm min-w-[900px]">
                    <thead>
                        <tr class="bg-[#C89741] text-white text-xs font-semibold uppercase tracking-wide">
                            <th class="px-4 py-3 text-center">Sr No.</th>
                            <th class="px-4 py-3 text-center">Purchase Date</th>
                            <th class="px-4 py-3 text-left">Type</th>
                            <th class="px-4 py-3 text-left">Product</th>
                            <th class="px-4 py-3 text-left">Details</th>
                            <th class="px-4 py-3 text-center">Status</th>
                            <th class="px-4 py-3 text-center">Invoice No.</th>
                            <th class="px-4 py-3 text-center">View</th>
                            <th class="px-4 py-3 text-center">Invoice</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php foreach ($orderRows as $index => $order): ?>
                        <?php
                            $badgeClass  = statusBadgeClass($order['dispatch_status'] ?? '');
                            $statusLabel = $order['dispatch_status'] ?: '—';
                        ?>
                        <tr class="hover:bg-[#fffdf7] transition-colors <?php echo $index % 2 === 0 ? 'bg-white' : 'bg-[#fafafa]'; ?>">
                            <td class="px-4 py-3 text-center text-gray-400 font-medium text-xs"><?php echo $index + 1; ?></td>
                            <td class="px-4 py-3 text-center text-gray-600 text-xs">
                                <?php echo htmlspecialchars(date('d M Y', strtotime($order['purchase_date']))); ?>
                            </td>
                            <td class="px-4 py-3">
                                <?php echo orderTypePill($order['order_type']); ?>
                            </td>
                            <td class="px-4 py-3 font-semibold text-[#3d3d3d] max-w-[200px]">
                                <?php echo htmlspecialchars($order['product_name']); ?>
                            </td>
                            <td class="px-4 py-3 text-gray-500 text-xs">
                                <?php echo htmlspecialchars($order['shipping_details'] ?: '—'); ?>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <span class="inline-block px-2.5 py-1 rounded-full text-xs font-semibold <?php echo $badgeClass; ?>">
                                    <?php echo htmlspecialchars($statusLabel); ?>
                                </span>
                            </td>
                            <td class="px-4 py-3 text-center font-mono text-xs text-gray-600">
                                <?php echo htmlspecialchars($order['invoice_no'] ?: '—'); ?>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <button
                                    onclick="showOrderDetails(`<?= htmlspecialchars($order['order_details'], ENT_QUOTES) ?>`)"
                                    class="px-3 py-1 bg-[#C89741] text-white rounded text-xs hover:bg-[#b08530]">
                                    View
                                </button>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <a href="?view_invoice=<?php echo (int)$order['id']; ?>&type=<?php echo urlencode($order['order_type']); ?>" target="_blank"
                                   class="inline-flex items-center gap-1 rounded-[7px] bg-[#0f2d56] px-3 py-1.5 text-white text-xs font-semibold hover:bg-[#173d70]">
                                    📄 Invoice
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- MOBILE CARDS -->
        <div class="mobile-cards space-y-3">
            <?php foreach ($orderRows as $index => $order): ?>
            <?php
                $badgeClass  = statusBadgeClass($order['dispatch_status'] ?? '');
                $statusLabel = $order['dispatch_status'] ?: '—';
            ?>
            <div class="order-card bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden"
                 style="animation-delay: <?php echo $index * 0.04; ?>s">

                <div class="bg-[#C89741] px-4 py-2.5 flex items-center justify-between">
                    <div class="flex items-center gap-2 flex-wrap">
                        <span class="text-white/70 text-xs font-semibold">#<?php echo $index + 1; ?></span>
                        <?php echo orderTypePill($order['order_type']); ?>
                        <span class="text-white font-bold text-sm"><?php echo htmlspecialchars($order['product_name']); ?></span>
                    </div>
                    <span class="inline-block px-2.5 py-1 rounded-full text-xs font-semibold <?php echo $badgeClass; ?> flex-shrink-0">
                        <?php echo htmlspecialchars($statusLabel); ?>
                    </span>
                </div>

                <div class="grid grid-cols-2 gap-x-4 gap-y-3 px-4 py-3 text-xs">

                    <div>
                        <div class="text-gray-400 font-semibold uppercase tracking-wide mb-0.5">Purchase Date</div>
                        <div class="font-semibold text-gray-700">
                            <?php echo htmlspecialchars(date('d M Y', strtotime($order['purchase_date']))); ?>
                        </div>
                    </div>

                    <div>
                        <div class="text-gray-400 font-semibold uppercase tracking-wide mb-0.5">Invoice No.</div>
                        <div class="font-mono font-semibold text-gray-700">
                            <?php echo htmlspecialchars($order['invoice_no'] ?: '—'); ?>
                        </div>
                    </div>

                    <div class="col-span-2">
                        <div class="text-gray-400 font-semibold uppercase tracking-wide mb-0.5">Details</div>
                        <div class="font-medium text-gray-600 leading-relaxed">
                            <?php echo htmlspecialchars($order['shipping_details'] ?: '—'); ?>
                        </div>
                    </div>

                    <div class="col-span-2 mt-1 flex gap-2">
                        <button
                            onclick="showOrderDetails(`<?= htmlspecialchars($order['order_details'], ENT_QUOTES) ?>`)"
                            class="flex-1 bg-[#C89741] text-white py-2 rounded-lg text-xs font-semibold hover:bg-[#b08530]">
                            View Details
                        </button>
                        <a href="?view_invoice=<?php echo (int)$order['id']; ?>&type=<?php echo urlencode($order['order_type']); ?>" target="_blank"
                           class="flex-1 text-center bg-[#0f2d56] text-white py-2 rounded-lg text-xs font-semibold hover:bg-[#173d70]">
                            📄 Invoice
                        </a>
                    </div>

                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <?php else: ?>

        <div class="bg-white border border-[#dddddd] rounded-xl flex flex-col items-center justify-center py-16 px-6 text-center shadow-sm">
            <div class="w-14 h-14 rounded-full bg-[#f3e9d7] flex items-center justify-center mb-4">
                <i data-lucide="package-open" class="w-7 h-7 text-[#c7993e]"></i>
            </div>
            <p class="text-[16px] font-semibold text-gray-400 mb-1">No Orders Found</p>
            <p class="text-sm text-gray-400">Your orders will appear here once placed.</p>
        </div>

        <?php endif; ?>

    </div>
</div>

<!-- Order Details Modal -->
<div id="orderModal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center px-3">
    <div class="bg-white w-full max-w-lg rounded-xl shadow-xl overflow-hidden">
        <div class="flex items-center justify-between bg-[#103258] px-5 py-4">
            <h3 class="text-white text-[15px] font-semibold">Order Details</h3>
            <button onclick="closeOrderDetails()" class="text-white text-[24px] leading-none hover:opacity-70">&times;</button>
        </div>
        <div class="p-5">
            <pre id="orderContent" class="text-sm whitespace-pre-wrap text-gray-700 leading-relaxed"></pre>
        </div>
    </div>
</div>

<script>
function showOrderDetails(data) {
    document.getElementById('orderContent').textContent = data;
    document.getElementById('orderModal').classList.remove('hidden');
}
function closeOrderDetails() {
    document.getElementById('orderModal').classList.add('hidden');
}
document.getElementById('orderModal')?.addEventListener('click', function(e) {
    if (e.target === this) closeOrderDetails();
});
document.addEventListener('DOMContentLoaded', function () {
    if (typeof lucide !== 'undefined') lucide.createIcons();
});
</script>

</body>
</html>