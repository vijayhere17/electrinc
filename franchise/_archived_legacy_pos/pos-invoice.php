<?php
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/config/db.php';
if (!isset($conn) || !($conn instanceof mysqli)) die('DB error');

function e($s){ return htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8'); }

$franchise_id = 0;
foreach (['franchise_id','franchies_id','franchise_db_id',
          'mobile_franchise_id','city_franchies_holder_id','state_franchies_holder_id'] as $k) {
    if (!empty($_SESSION[$k]) && is_numeric($_SESSION[$k])) {
        $franchise_id = (int)$_SESSION[$k]; break;
    }
}
if (!$franchise_id) { http_response_code(403); exit('Unauthorised'); }

$order_id = (int)($_GET['order_id'] ?? 0);
if ($order_id <= 0) exit('Invalid order');

$st = $conn->prepare(
    "SELECT o.* FROM member_orders o
     WHERE o.id = ? AND o.distributor_id = ? LIMIT 1"
);
$st->bind_param('ii', $order_id, $franchise_id);
$st->execute();
$order = $st->get_result()->fetch_assoc(); $st->close();
if (!$order) exit('Order not found');

$franchise_code = 'FRANCHISE #' . $franchise_id;
$franchise_name = '';

$stFr = $conn->prepare(
    "SELECT franchise_code, name
     FROM franchies_holder
     WHERE id = ?
     LIMIT 1"
);

if ($stFr) {
    $stFr->bind_param('i', $franchise_id);
    $stFr->execute();

    $fr = $stFr->get_result()->fetch_assoc();

    if ($fr) {
        $franchise_code = $fr['franchise_code'];
        $franchise_name = $fr['name'];
    }

    $stFr->close();
}

$stI = $conn->prepare("SELECT * FROM member_order_items WHERE order_id = ? ORDER BY id");
$stI->bind_param('i', $order_id); $stI->execute();
$items = $stI->get_result()->fetch_all(MYSQLI_ASSOC); $stI->close();

$grand_total = array_sum(array_column($items,'amount'));
$total_bv    = array_sum(array_column($items,'bv'));
$total_items = array_sum(array_column($items,'quantity'));
$bill_date   = date('d M Y, h:i A', strtotime($order['created_at']));
$bill_no     = 'NTV-' . str_pad($order_id,6,'0',STR_PAD_LEFT);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Invoice <?= e($bill_no) ?></title>
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
body{font-family:'Segoe UI',system-ui,sans-serif;font-size:13px;color:#1a1a2e;background:#f5f5f5;}
.page{width:794px;min-height:1123px;margin:30px auto;background:#fff;border-radius:12px;
      overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,.12);display:flex;flex-direction:column;}
.inv-header{background:linear-gradient(135deg,#103258,#1a3f66);color:#fff;
            padding:32px 40px 24px;display:flex;justify-content:space-between;align-items:flex-start;}
.brand-name{font-size:22px;font-weight:700;}
.brand-tag{font-size:11px;opacity:.7;margin-top:2px;letter-spacing:.5px;text-transform:uppercase;}
.inv-meta{text-align:right;}
.bill-no{font-size:18px;font-weight:700;letter-spacing:1px;}
.bill-date{font-size:11px;opacity:.75;margin-top:4px;}
.gold-bar{height:4px;background:linear-gradient(90deg,#C9A84C,#F0D080,#C9A84C);}
.info-grid{display:grid;grid-template-columns:1fr 1fr;border-bottom:1px solid #e8edf3;}
.info-block{padding:20px 40px;border-right:1px solid #e8edf3;}
.info-block:last-child{border-right:none;}
.info-label{font-size:10px;text-transform:uppercase;letter-spacing:.7px;color:#8090a0;font-weight:600;margin-bottom:6px;}
.info-value{font-size:14px;font-weight:600;color:#103258;}
.info-sub{font-size:12px;color:#556;margin-top:2px;}
.pay-badge{display:inline-block;background:#e8f4e8;color:#2a7a2a;border-radius:20px;
           padding:2px 10px;font-size:11px;font-weight:600;text-transform:uppercase;margin-top:4px;}
.items-wrap{padding:24px 40px;flex:1;}
table{width:100%;border-collapse:collapse;}
thead tr{background:#103258;color:#fff;}
thead th{padding:10px 12px;text-align:left;font-size:11px;font-weight:600;
         letter-spacing:.4px;text-transform:uppercase;}
thead th:last-child{text-align:right;}
tbody tr{border-bottom:1px solid #f0f3f7;}
tbody tr:nth-child(even){background:#f9fafc;}
tbody td{padding:10px 12px;font-size:12.5px;}
tbody td:last-child{text-align:right;font-weight:600;}
.sr{color:#aaa;font-size:11px;}
.totals-wrap{display:flex;justify-content:flex-end;padding:0 40px 24px;}
.totals-box{width:280px;border:1px solid #e8edf3;border-radius:10px;overflow:hidden;}
.totals-row{display:flex;justify-content:space-between;padding:9px 14px;
            font-size:12.5px;border-bottom:1px solid #f0f3f7;}
.totals-row:last-child{border-bottom:none;}
.totals-row.grand{background:#103258;color:#fff;font-weight:700;font-size:14px;}
.totals-row.bv{background:#f8fafc;}
.label{color:#8090a0;}
.totals-row.grand .label{color:#c8d8e8;}
.inv-footer{background:#f8fafc;border-top:1px solid #e8edf3;padding:18px 40px;
            display:flex;justify-content:space-between;align-items:center;}
.thank-you{font-size:13px;color:#103258;font-weight:600;}
.footer-sub{font-size:11px;color:#99a;margin-top:2px;}
.stamp{width:80px;height:80px;border:2px solid #C9A84C;border-radius:50%;
       display:flex;flex-direction:column;align-items:center;justify-content:center;
       color:#C9A84C;font-size:9px;font-weight:700;letter-spacing:.5px;text-align:center;line-height:1.3;}
@media print{
    body{background:#fff;}
    .page{width:100%;margin:0;border-radius:0;box-shadow:none;}
    .no-print{display:none!important;}
}
</style>
</head>
<body>

<div class="no-print" style="text-align:center;padding:16px;background:#e8edf3;">
    <button onclick="window.print()"
            style="background:#103258;color:#fff;border:none;border-radius:8px;
                   padding:10px 28px;font-size:14px;cursor:pointer;font-weight:600;">
        🖨 Print Invoice
    </button>
    <button onclick="window.close()"
            style="background:#fff;color:#555;border:1px solid #ccc;border-radius:8px;
                   padding:10px 20px;font-size:14px;cursor:pointer;margin-left:10px;">
        ✕ Close
    </button>
</div>

<div class="page">

    <div class="inv-header">
        <div>
            <div class="brand-name">Netvexa Trading Company</div>
            <div class="brand-tag">Official Tax Invoice</div>
        </div>
        <div class="inv-meta">
            <div class="bill-no"><?= e($bill_no) ?></div>
            <div class="bill-date"><?= e($bill_date) ?></div>
        </div>
    </div>
    <div class="gold-bar"></div>

    <div class="info-grid">
        <div class="info-block">
    <div class="info-label">Franchise / Distributor</div>

    <div class="info-value">
        <?= e($franchise_code) ?>
    </div>

    <?php if (!empty($franchise_name)): ?>
        <div class="info-sub">
            <?= e($franchise_name) ?>
        </div>
    <?php endif; ?>
</div>
        <div class="info-block">
            <div class="info-label">Bill To</div>
            <div class="info-value"><?= e($order['member_name']) ?></div>
            <?php if ($order['member_id']): ?>
                <div class="info-sub">Member ID: <?= e($order['member_id']) ?></div>
            <?php endif; ?>
            <span class="pay-badge"><?= e($order['payment_method']) ?></span>
        </div>
    </div>

    <div class="items-wrap">
        <table>
            <thead>
                <tr>
                    <th style="width:30px">#</th>
                    <th>Product Name</th>
                    <th style="width:80px">Price</th>
                    <th style="width:60px">BV/PV</th>
                    <th style="width:50px">Qty</th>
                    <th style="width:90px">Amount</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $idx => $item): ?>
                <tr>
                    <td class="sr"><?= $idx+1 ?></td>
                    <td><?= e($item['product_name']) ?></td>
                    <td>₹ <?= number_format($item['product_price'],2) ?></td>
                    <td><?= number_format($item['bv'] ?? 0,2) ?></td>
                    <td><?= (int)$item['quantity'] ?></td>
                    <td>₹ <?= number_format($item['amount'],2) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="totals-wrap">
        <div class="totals-box">
            <div class="totals-row">
                <span class="label">Total Items</span>
                <span><?= $total_items ?></span>
            </div>
            <div class="totals-row bv">
                <span class="label">Total BV / PV</span>
                <span><?= number_format($total_bv,2) ?></span>
            </div>
            <div class="totals-row grand">
                <span class="label">Grand Total</span>
                <span>₹ <?= number_format($grand_total,2) ?></span>
            </div>
        </div>
    </div>

    <div class="inv-footer">
        <div>
            <div class="thank-you">Thank you for your purchase!</div>
            <div class="footer-sub">Netvexa Trading Company — Computer generated invoice.</div>
            <div class="footer-sub" style="margin-top:4px;">
                Bill No: <?= e($bill_no) ?> &nbsp;|&nbsp; <?= e($bill_date) ?>
            </div>
        </div>
        <div class="stamp">AUTHORISED<br>SIGNATORY<br><span style="font-size:7px;opacity:.6">NETVEXA</span></div>
    </div>

</div>

<script>window.addEventListener('load',()=>setTimeout(()=>window.print(),400));</script>
</body>
</html>