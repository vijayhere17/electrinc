<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include __DIR__ . '/config/db.php';

if (!isset($conn) || !($conn instanceof mysqli)) {
    die("Database connection failed.");
}

function e($str) {
    return htmlspecialchars((string)($str ?? ''), ENT_QUOTES, 'UTF-8');
}

function tableExists($conn, $table) {
    $table = $conn->real_escape_string($table);
    $res = $conn->query("SHOW TABLES LIKE '$table'");
    return ($res && $res->num_rows > 0);
}

function colExists($conn, $table, $col) {
    $table = $conn->real_escape_string($table);
    $col   = $conn->real_escape_string($col);
    $res   = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$col'");
    return ($res && $res->num_rows > 0);
}

/* Format order ID as ORD0030 */
function formatOrderId(int $id): string {
    return 'ORD' . str_pad($id, 4, '0', STR_PAD_LEFT);
}

function fetchMemberOrderItems(mysqli $conn, int $orderId): array {
    $items = [];
    $stmt = $conn->prepare("SELECT product_name, product_price, quantity, amount FROM member_order_items WHERE order_id = ? ORDER BY id ASC");
    if ($stmt) {
        $stmt->bind_param('i', $orderId);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $items[] = $row;
        }
        $stmt->close();
    }
    return $items;
}

function fetchMemberOrderById(mysqli $conn, int $orderId): ?array {
    $stmt = $conn->prepare("SELECT * FROM member_orders WHERE id = ? LIMIT 1");
    if (!$stmt) return null;
    $stmt->bind_param('i', $orderId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

/* Fetch mobile from signup_users using customer_id */
function fetchMemberMobile(mysqli $conn, string $memberId): string {
    if ($memberId === '') return '';
    $stmt = $conn->prepare("SELECT mobile FROM signup_users WHERE customer_id = ? LIMIT 1");
    if (!$stmt) return '';
    $stmt->bind_param('s', $memberId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return (string)($row['mobile'] ?? '');
}

/* -----------------------------------------------------------------------
 * RESOLVE FRANCHISE ID + CODE FROM SESSION
 * Covers state / city / mobile franchise session key variants.
 * ----------------------------------------------------------------------- */
function resolveSessionFranchise(): array {

    $id = 0;

    if (!empty($_SESSION['franchise_id'])) {
        $id = (int)$_SESSION['franchise_id'];
    } elseif (!empty($_SESSION['city_franchies_holder_id'])) {
        $id = (int)$_SESSION['city_franchies_holder_id'];
    } elseif (!empty($_SESSION['mobile_franchise_id'])) {
        $id = (int)$_SESSION['mobile_franchise_id'];
    }

    $code = '';

    if (!empty($_SESSION['franchise_code'])) {
        $code = trim($_SESSION['franchise_code']);
    } elseif (!empty($_SESSION['franchies_id'])) {
        $code = trim($_SESSION['franchies_id']);
    } elseif (!empty($_SESSION['mobile_franchies_id'])) {
        $code = trim($_SESSION['mobile_franchies_id']);
    }

    return [
        'id'   => $id,
        'code' => $code
    ];
}


$franchise    = resolveSessionFranchise();
$franchiseId  = $franchise['id'];
$franchiseCode= $franchise['code'];

if ($franchiseCode == '' && $franchiseId > 0) {

    $stmt = $conn->prepare("
        SELECT franchise_code
        FROM franchies_holder
        WHERE id = ?
        LIMIT 1
    ");

    if ($stmt) {
        $stmt->bind_param("i", $franchiseId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!empty($row['franchise_code'])) {
            $franchiseCode = $row['franchise_code'];
        }
    }
}

// Legacy variable kept for invoice section
$franchiseCodeLegacy = $_SESSION['franchies_id'] ?? $_SESSION['franchise_code'] ?? $_SESSION['franchise_id'] ?? '';

/* ─────────────────────────────────────────────
   INVOICE VIEW  (design UNCHANGED)
───────────────────────────────────────────── */
if (isset($_GET['view_invoice']) && $_GET['view_invoice'] !== '') {
    $invoiceOrderId = (int)$_GET['view_invoice'];
    $invoiceOrder   = $invoiceOrderId > 0 ? fetchMemberOrderById($conn, $invoiceOrderId) : null;

    if (!$invoiceOrder) {
        die('Invoice not found.');
    }

    $invoiceItems = fetchMemberOrderItems($conn, $invoiceOrderId);

    /* ── CHANGED: grand total = total_amount as-is, no CGST/SGST ── */
    $grandTotal = (float)($invoiceOrder['total_amount'] ?? 0);

    $memberName    = $invoiceOrder['member_name'] ?? '';
    $memberInitial = strtoupper(substr(trim($memberName), 0, 1)) ?: 'M';
    $memberIdVal   = $invoiceOrder['member_id'] ?? '';

    $memberMobile = fetchMemberMobile($conn, (string)$memberIdVal);

    $formattedOrderId = formatOrderId($invoiceOrderId);

    /* ---- COMPANY INFO ---- */
    $companyAddress = '123, Business Hub, Ring Road, Surat - 395002, Gujarat, India';
    $companyEmail   = 'support@netavexa.com';
    $companyPhone   = '+91 98765 43210';
    $companyGST     = 'GSTIN: 24XXXXX0000X1ZX';

    header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Member Order Invoice <?php echo e($formattedOrderId); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Inter', Arial, sans-serif;
            background: #eef2f7;
            color: #1e293b;
            min-height: 100vh;
            padding: 24px 16px 40px;
        }

        .action-bar {
            max-width: 860px;
            margin: 0 auto 16px;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            flex-wrap: wrap;
        }
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 9px 18px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
            border: none;
            text-decoration: none;
            transition: opacity .15s;
        }
        .btn:hover { opacity: .85; }
        .btn-primary   { background: #103258; color: #fff; }
        .btn-secondary { background: #e2e8f0; color: #334155; }

        .invoice-card {
            max-width: 860px;
            margin: 0 auto;
            background: #fff;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 4px 24px rgba(0,0,0,.10);
        }

        .inv-header {
            background: linear-gradient(135deg, #0d2742 0%, #1a4d80 100%);
            padding: 22px 28px;
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 20px;
            flex-wrap: wrap;
        }

        .inv-header-left {
            display: flex;
            flex-direction: column;
            gap: 12px;
            flex: 1;
            min-width: 220px;
        }
        .inv-brand-top {
            display: flex;
            align-items: center;
            gap: 14px;
        }
        .inv-logo-box {
            height: 44px;
            display: flex; align-items: center; justify-content: center;
            font-size: 11px; font-weight: 800; color: #fff;
            flex-shrink: 0;
        }
        .inv-logo-box img { height: 100%; width: auto; object-fit: contain; }
        .inv-title {
            font-size: 20px;
            font-weight: 800;
            color: #fff;
            letter-spacing: .02em;
        }
        .inv-company-info {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        .inv-ci {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 11.5px;
            color: rgba(200, 223, 240, 0.95);
            font-weight: 500;
        }
        .inv-ci svg { flex-shrink: 0; opacity: .85; }
        .inv-ci a { color: rgba(200, 223, 240, 0.95); text-decoration: none; }
        .inv-ci a:hover { color: #fff; text-decoration: underline; }
        .inv-gst-badge {
            display: inline-block;
            background: rgba(200, 151, 65, .22);
            border: 1px solid rgba(200, 151, 65, .45);
            color: #f5d98a;
            font-size: 10.5px;
            font-weight: 700;
            padding: 3px 12px;
            border-radius: 10px;
            letter-spacing: .4px;
            width: fit-content;
        }

        .inv-header-right {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 6px;
            flex-shrink: 0;
            text-align: right;
        }
        .inv-date-label  { font-size: 11px; color: #93c5fd; font-weight: 500; }
        .inv-date-val    { font-size: 13px; color: #fff; font-weight: 700; }
        .inv-orderid-label { font-size: 11px; color: #93c5fd; font-weight: 500; margin-top: 4px; }
        .inv-orderid-val   { font-size: 16px; color: #fbbf24; font-weight: 800; letter-spacing: .04em; }
        .inv-badges {
            display: flex;
            gap: 7px;
            justify-content: flex-end;
            flex-wrap: wrap;
            margin-top: 4px;
        }
        .badge {
            display: inline-flex; align-items: center; gap: 4px;
            padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 700;
        }
        .badge-type     { background: rgba(255,255,255,.15); color: #e0f2fe; border: 1px solid rgba(255,255,255,.2); }
        .badge-approved { background: rgba(34,197,94,.15); color: #86efac; border: 1px solid rgba(34,197,94,.3); letter-spacing: .06em; }

        /* Member */
        .inv-member { padding: 20px 28px 0; display: flex; align-items: center; gap: 14px; }
        .member-avatar {
            width: 46px; height: 46px; border-radius: 50%;
            background: linear-gradient(135deg, #103258, #1a6ea8);
            display: flex; align-items: center; justify-content: center;
            font-size: 20px; font-weight: 800; color: #fff; flex-shrink: 0;
        }
        .member-name { font-size: 18px; font-weight: 700; color: #1e293b; }
        .member-tags {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
            margin-top: 5px;
        }
        .member-id-tag {
            display: inline-flex; align-items: center; gap: 5px;
            padding: 3px 10px; border-radius: 20px;
            background: #f1f5f9; font-size: 12px; color: #64748b; font-weight: 600;
        }
        .member-mobile-tag {
            display: inline-flex; align-items: center; gap: 5px;
            padding: 3px 10px; border-radius: 20px;
            background: #f0fdf4; font-size: 12px; color: #16a34a; font-weight: 600;
            border: 1px solid #bbf7d0;
        }

        .inv-divider { border: none; border-top: 1.5px solid #f1f5f9; margin: 18px 28px; }

        .inv-body { padding: 0 28px 24px; }
        .section-label {
            font-size: 11px; font-weight: 700; color: #64748b;
            letter-spacing: .1em; text-transform: uppercase;
            margin-bottom: 14px;
        }
        .info-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 14px;
            margin-bottom: 24px;
        }
        @media (max-width: 620px) { .info-grid { grid-template-columns: 1fr 1fr; } }
        @media (max-width: 400px) { .info-grid { grid-template-columns: 1fr; } }
        .info-item .lbl { font-size: 11px; color: #94a3b8; font-weight: 600; text-transform: uppercase; letter-spacing: .06em; }
        .info-item .val { font-size: 14px; font-weight: 700; color: #1e293b; margin-top: 3px; }

        .table-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; min-width: 480px; }
        thead tr { background: #0d2742; }
        thead th {
            padding: 13px 16px; text-align: left;
            font-size: 12px; font-weight: 700; color: #cbd5e1;
            letter-spacing: .07em; text-transform: uppercase;
        }
        thead th:last-child { text-align: right; }
        tbody tr { border-bottom: 1px solid #f1f5f9; }
        tbody tr:last-child { border-bottom: none; }
        tbody tr:hover { background: #f8fafc; }
        tbody td { padding: 14px 16px; font-size: 14px; color: #334155; }
        tbody td:last-child { text-align: right; font-weight: 700; color: #1e293b; }
        td.serial { color: #94a3b8; font-weight: 600; }

        .totals-section { display: flex; justify-content: flex-end; margin-top: 20px; }
        .totals-box {
            width: 100%; max-width: 340px;
            border: 1.5px solid #e2e8f0; border-radius: 12px; overflow: hidden;
        }
        /* ── CHANGED: tax-inclusive note row ── */
        .totals-note-row {
            padding: 10px 18px;
            border-bottom: 1px solid #f1f5f9;
            font-size: 12px;
            color: #64748b;
            font-style: italic;
            text-align: center;
        }
        .totals-grand {
            background: linear-gradient(135deg, #0d2742, #1a4d80);
            padding: 15px 18px;
            display: flex; justify-content: space-between; align-items: center;
        }
        .totals-grand .t-label { color: #e0f2fe; font-size: 15px; font-weight: 700; }
        .totals-grand .t-val   { color: #fbbf24; font-size: 17px; font-weight: 800; }

        .inv-footer {
            background: #f8fafc;
            border-top: 1.5px solid #e2e8f0;
            padding: 16px 28px;
            display: flex; justify-content: space-between; align-items: center;
            flex-wrap: wrap; gap: 8px;
            font-size: 12px; color: #94a3b8;
        }
        .inv-footer strong { color: #475569; }

        @media print {
            .no-print { display: none !important; }
            body { background: #fff; padding: 0; }
            .invoice-card { box-shadow: none; border-radius: 0; }
        }
        @media (max-width: 600px) {
            .inv-header { padding: 16px 18px; gap: 14px; }
            .inv-header-right { align-items: flex-start; text-align: left; }
            .inv-body   { padding: 0 16px 20px; }
            .inv-member { padding: 16px 18px 0; }
            .inv-divider { margin: 14px 18px; }
            .inv-footer { padding: 14px 18px; }
            .inv-title  { font-size: 17px; }
            .inv-ci     { font-size: 10.5px; }
        }
    </style>
</head>
<body>

<div class="action-bar no-print">
    <button onclick="window.print()" class="btn btn-primary">🖨 Print / Save PDF</button>
    <a href="member_order_list.php" class="btn btn-secondary">← Back</a>
</div>

<div class="invoice-card">

    <div class="inv-header">
        <div class="inv-header-left">
            <div class="inv-brand-top">
                <div class="inv-logo-box">
                    <?php
                    $logoPath = __DIR__ . '/assets/electrinc-logo.png';
                    if (file_exists($logoPath)) {
                        echo '<img src="assets/electrinc-logo.png" alt="Logo">';
                    } else {
                        echo 'LOGO';
                    }
                    ?>
                </div>
                <div class="inv-title">MEMBER NETVEXA INVOICE</div>
            </div>

            <div class="inv-company-info">
                <span class="inv-ci">
                    <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                    <?php echo e($companyAddress); ?>
                </span>
                <span class="inv-ci">
                    <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                    </svg>
                    <a href="mailto:<?php echo e($companyEmail); ?>"><?php echo e($companyEmail); ?></a>
                </span>
                <span class="inv-ci">
                    <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
                    </svg>
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
                <span class="badge badge-type">📱 Member Order</span>
                <span class="badge badge-approved">APPROVED</span>
            </div>
        </div>
    </div>

    <!-- Member info -->
    <div class="inv-member">
        <div class="member-avatar"><?php echo e($memberInitial); ?></div>
        <div>
            <div class="member-name"><?php echo e($memberName ?: '-'); ?></div>
            <div class="member-tags">
                <span class="member-id-tag">🪪 <?php echo e($memberIdVal ?: '-'); ?></span>
                <?php if ($memberMobile !== '') : ?>
                <span class="member-mobile-tag">
                    <svg xmlns="http://www.w3.org/2000/svg" width="11" height="11" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
                    </svg>
                    <?php echo e($memberMobile); ?>
                </span>
                <?php endif; ?>
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
                <div class="val"><?php echo !empty($invoiceOrder['created_at']) ? e(date('d-m-Y', strtotime($invoiceOrder['created_at']))) : '-'; ?></div>
            </div>
            <div class="info-item">
                <div class="lbl">Payment Mode</div>
                <div class="val"><?php echo e($invoiceOrder['payment_method'] ?? '-'); ?></div>
            </div>
            <div class="info-item">
                <div class="lbl">Member Mobile</div>
                <div class="val"><?php echo $memberMobile !== '' ? e($memberMobile) : '-'; ?></div>
            </div>
            <?php if (!empty($invoiceOrder['utr_number'])) : ?>
            <div class="info-item">
                <div class="lbl">UTR Number</div>
                <div class="val"><?php echo e($invoiceOrder['utr_number']); ?></div>
            </div>
            <?php endif; ?>
            <?php if (!empty($invoiceOrder['bank_name'])) : ?>
            <div class="info-item">
                <div class="lbl">Bank Name</div>
                <div class="val"><?php echo e($invoiceOrder['bank_name']); ?></div>
            </div>
            <?php endif; ?>
            <?php if (!empty($invoiceOrder['distributor_id'])) : ?>
            <div class="info-item">
                <div class="lbl">Franchise ID</div>
                <div class="val"><?php echo e($invoiceOrder['distributor_id']); ?></div>
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
                        <th style="text-align:right">Total Price</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!empty($invoiceItems)) : ?>
                    <?php foreach ($invoiceItems as $idx => $item) : ?>
                    <tr>
                        <td class="serial"><?php echo $idx + 1; ?></td>
                        <td><?php echo e($item['product_name'] ?? '-'); ?></td>
                        <td><?php echo (int)($item['quantity'] ?? 0); ?></td>
                        <td>₹<?php echo number_format((float)($item['product_price'] ?? 0), 2); ?></td>
                        <td>₹<?php echo number_format((float)($item['amount'] ?? 0), 2); ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php else : ?>
                    <tr><td colspan="5" style="text-align:center;color:#94a3b8;padding:24px">No products found.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- ── CHANGED: removed CGST/SGST rows, added tax-inclusive note ── -->
        <div class="totals-section">
            <div class="totals-box">
                <div class="totals-note-row">
                    All taxes included in the total amount
                </div>
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
   MEMBER ORDER LIST
   FIX: match distributor_id against BOTH
        franchise code AND franchise id (string)
───────────────────────────────────────────── */
$memberOrders = [];

if (tableExists($conn, 'member_orders')) {

    $franchiseIdStr = (string)$franchiseId;

    /*
     * distributor_id was saved as franchiseCode in the order form.
     * But some orders may have been saved with the numeric ID string.
     * We match BOTH to catch all orders for this franchise.
     */
    $conditions  = [];
    $bindTypes   = '';
    $bindValues  = [];

    if ($franchiseCode !== '') {
        $conditions[] = 'distributor_id = ?';
        $bindTypes   .= 's';
        $bindValues[] = $franchiseCode;
    }

    if ($franchiseIdStr !== '0' && $franchiseIdStr !== $franchiseCode) {
        $conditions[] = 'distributor_id = ?';
        $bindTypes   .= 's';
        $bindValues[] = $franchiseIdStr;
    }

    // Also check NULL distributor_id if we have no code (edge case: orders saved without distributor)
    if (empty($conditions)) {
        // No usable identifier — show nothing to avoid showing other franchise orders
        $memberOrders = [];
    } else {
        $whereClause = implode(' OR ', $conditions);
        $sql = "SELECT * FROM member_orders WHERE ($whereClause) ORDER BY id DESC LIMIT 200";

        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param($bindTypes, ...$bindValues);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($row = $res->fetch_assoc()) {
                $row['items'] = fetchMemberOrderItems($conn, (int)$row['id']);
                $memberOrders[] = $row;
            }
            $stmt->close();
        }
    }
}

$pageTitle = 'Member Order List';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo e($pageTitle); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        html, body { min-height: 100%; overflow-x: hidden; }
        .table-responsive { overflow-x: auto; -webkit-overflow-scrolling: touch; }
        @media (max-width: 1024px) { .ml-64 { margin-left: 0 !important; } }
    </style>
</head>
<body class="bg-[#f4f4f4] font-sans">

<?php if (file_exists(__DIR__ . '/includes/sidebar.php')) include __DIR__ . '/includes/sidebar.php'; ?>

<div class="min-h-screen lg:ml-64">
<?php if (file_exists(__DIR__ . '/includes/navbar.php')) include __DIR__ . '/includes/navbar.php'; ?>

<main class="px-3 py-4 sm:px-5 sm:py-6 lg:px-6">
    <div class="max-w-[1500px] mx-auto pb-10">

        <div class="mt-5 flex items-center gap-4">
            <div class="w-[36px] h-[36px] rounded-[8px] bg-[#f5eee3] flex items-center justify-center shrink-0">
                <i data-lucide="badge-check" class="w-[18px] h-[18px] text-[#cd9737]"></i>
            </div>
            <h2 class="whitespace-nowrap text-[20px] font-normal text-[#40352a]" style="font-family: Georgia, 'Times New Roman', serif;">
                MEMBER ORDER LIST
            </h2>
            <div class="w-full h-[2px] bg-[#d1a14b]"></div>
        </div>

        <div class="bg-white rounded-b-[20px] shadow-sm border border-gray-200 overflow-hidden">
            <div class="table-responsive overflow-x-auto">
                <table class="w-full min-w-[1100px]">
                    <thead>
                        <tr class="bg-[#c89741] text-white text-[12px]">
                            <th class="px-5 py-4 text-left text-white text-[13px] font-semibold">Sr</th>
                            <th class="px-5 py-4 text-left text-white text-[13px] font-semibold">Order ID</th>
                            <th class="px-5 py-4 text-left text-white text-[13px] font-semibold">Member</th>
                            <th class="px-5 py-4 text-left text-white text-[13px] font-semibold">Products</th>
                            <th class="px-5 py-4 text-left text-white text-[13px] font-semibold">Qty</th>
                            <th class="px-5 py-4 text-left text-white text-[13px] font-semibold">Amount</th>
                            <th class="px-5 py-4 text-left text-white text-[13px] font-semibold">Payment</th>
                            <th class="px-5 py-4 text-left text-white text-[13px] font-semibold">Date</th>
                            <th class="px-5 py-4 text-left text-white text-[13px] font-semibold">Invoice</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (!empty($memberOrders)) : ?>
                        <?php foreach ($memberOrders as $index => $order) :
                            $itemsJson = htmlspecialchars(json_encode($order['items'] ?? []), ENT_QUOTES, 'UTF-8');
                            $displayOrderId = formatOrderId((int)$order['id']);
                            $totalQty = array_sum(array_column($order['items'] ?? [], 'quantity'));
                        ?>
                            <tr class="border-b border-gray-200 hover:bg-gray-50">
                                <td class="px-5 py-4 text-[14px]"><?php echo $index + 1; ?></td>
                                <td class="px-5 py-4 text-[14px] font-semibold text-[#40352a]">
                                    <?php echo e($displayOrderId); ?>
                                </td>
                                <td class="px-5 py-4 text-[14px]">
                                    <div class="font-semibold text-[#2f2f2f]"><?php echo e($order['member_name'] ?? '-'); ?></div>
                                    <div class="text-[12px] text-gray-500"><?php echo e($order['member_id'] ?? '-'); ?></div>
                                </td>
                                <td class="px-5 py-4 text-[14px]">
                                    <button type="button" data-items="<?php echo $itemsJson; ?>" onclick="openMemberItems(this)"
                                        class="rounded-[7px] bg-slate-100 px-3 py-2 text-[12px] font-semibold text-slate-700 hover:bg-slate-200">
                                        View Items
                                    </button>
                                </td>
                                <td class="px-5 py-4 text-[14px] font-semibold"><?php echo (int)$totalQty; ?></td>
                                <td class="px-5 py-4 text-[14px] font-semibold text-green-600">₹<?php echo number_format((float)($order['total_amount'] ?? 0), 2); ?></td>
                                <td class="px-5 py-4 text-[14px]"><?php echo e($order['payment_method'] ?? '-'); ?></td>
                                <td class="px-5 py-4 text-[14px] text-gray-600"><?php echo !empty($order['created_at']) ? e(date('d M Y', strtotime($order['created_at']))) : '-'; ?></td>
                                <td class="px-5 py-4 text-[14px]">
                                    <a href="?view_invoice=<?php echo (int)$order['id']; ?>" target="_blank"
                                       class="inline-flex items-center gap-1 rounded-[7px] bg-[#0f2d56] px-4 py-2 text-white text-[12px] font-semibold hover:bg-[#173d70]">
                                        📄 Invoice
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <tr>
                            <td colspan="9" class="px-5 py-16 text-center">
                                <div class="flex flex-col items-center gap-3 text-gray-400">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-12 h-12 opacity-30" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                                    </svg>
                                    <span class="text-[15px] font-medium">No member orders found.</span>
                                    <a href="member-order.php" class="mt-1 inline-flex items-center gap-2 rounded-[8px] bg-[#0f2d56] px-5 py-2 text-white text-[13px] font-semibold hover:bg-[#173d70]">
                                        + Place New Order
                                    </a>
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

<!-- Items Modal (design UNCHANGED) -->
<div id="memberItemsModal" class="fixed inset-0 z-[9999] hidden items-center justify-center bg-black/50 px-3">
    <div class="w-full max-w-[680px] rounded-[14px] bg-white shadow-xl overflow-hidden max-h-[90vh] flex flex-col">
        <div class="flex items-center justify-between bg-[#103258] px-5 py-4 shrink-0">
            <h3 class="text-white text-[15px] font-semibold">Order Items</h3>
            <button onclick="closeMemberItems()" class="text-white text-[24px] leading-none hover:opacity-70">&times;</button>
        </div>
        <div class="p-4 overflow-auto" id="memberItemsBody"></div>
    </div>
</div>

<script>
if (typeof lucide !== 'undefined') lucide.createIcons();

function openMemberItems(btn) {
    let items = [];
    try { items = JSON.parse(btn.getAttribute('data-items') || '[]'); } catch(e) { items = []; }
    const body = document.getElementById('memberItemsBody');
    body.innerHTML = items.length ? items.map((item, i) => `
        <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 mb-3">
            <div class="font-bold text-slate-800">#${i+1} ${escapeHtml(item.product_name||'-')}</div>
            <div class="mt-2 text-sm text-slate-600">Qty: ${parseInt(item.quantity||0)} | Price: ₹${Number(item.product_price||0).toFixed(2)} | Amount: ₹${Number(item.amount||0).toFixed(2)}</div>
        </div>
    `).join('') : '<div class="text-center text-gray-500 py-6">No products found.</div>';
    const modal = document.getElementById('memberItemsModal');
    modal.classList.remove('hidden'); modal.classList.add('flex');
}

function closeMemberItems() {
    const modal = document.getElementById('memberItemsModal');
    modal.classList.add('hidden'); modal.classList.remove('flex');
}

function escapeHtml(str) {
    return String(str||'').replace(/[&<>"']/g, s => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[s]);
}

document.getElementById('memberItemsModal')?.addEventListener('click', function(e){ if(e.target===this) closeMemberItems(); });
</script>

</body>
</html>