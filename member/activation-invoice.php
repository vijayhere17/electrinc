<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config/db.php';
if (!isset($conn) || !($conn instanceof mysqli)) {
    die('Database connection failed.');
}
$conn->set_charset('utf8mb4');

if (!isset($_SESSION['customer_id']) || trim((string)$_SESSION['customer_id']) === '') {
    header('Location: login.php');
    exit;
}
$customer_id = trim((string)$_SESSION['customer_id']);   // authenticated member — never from URL
$reqId = (int)($_GET['id'] ?? 0);

function e($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function aiv_inr($n) { return '₹' . number_format((float)$n, 2); }
function aiv_words($num) {
    $num = (int) round((float)$num);
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

/* strictly scoped to this member's own row — franchise/customer isolation pattern, same rule here */
$st = $conn->prepare("SELECT * FROM activation_requests WHERE id = ? AND customer_id = ? AND status = 'Approved' LIMIT 1");
$st->bind_param('is', $reqId, $customer_id);
$st->execute();
$req = $st->get_result()->fetch_assoc();
$st->close();
if (!$req) {
    die('Invoice not found.');
}

$member = null;
$st = $conn->prepare("SELECT full_name, email, mobile, address FROM signup_users WHERE customer_id = ? LIMIT 1");
$st->bind_param('s', $customer_id);
$st->execute();
$member = $st->get_result()->fetch_assoc() ?: [];
$st->close();

$items = [];
$decoded = json_decode((string)($req['products_json'] ?? ''), true);
if (is_array($decoded) && !empty($decoded)) {
    $items = $decoded;
} else {
    $items = [['item' => 'Joining Booking', 'amount' => (float)$req['amount'], 'qty' => 1]];
}
$invNo = 'ACT-' . str_pad((string)$req['id'], 5, '0', STR_PAD_LEFT);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Invoice <?php echo e($invNo); ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<script src="https://cdn.tailwindcss.com"></script>
<script src="https://unpkg.com/lucide@latest"></script>
<style>
    body{ background:#f3f3f3; }
    @media print{
        .no-print{ display:none !important; }
        body{ background:#fff !important; }
        .invoice-card{ box-shadow:none !important; border:none !important; }
    }
</style>
</head>
<body class="font-sans">
<div class="max-w-[820px] mx-auto py-6 px-4">

    <div class="no-print flex justify-end gap-2 mb-4">
        <button onclick="window.print()" class="inline-flex items-center gap-2 bg-[#0E7A3B] hover:bg-[#0A5E2D] text-white px-4 py-2.5 rounded-lg text-[13px] font-semibold">
            <i data-lucide="printer" class="w-4 h-4"></i> Download / Print
        </button>
        <a href="activation.php" class="inline-flex items-center gap-2 border border-gray-300 bg-white px-4 py-2.5 rounded-lg text-[13px] font-semibold">
            <i data-lucide="arrow-left" class="w-4 h-4"></i> Back
        </a>
    </div>

    <div class="invoice-card bg-white rounded-2xl border border-gray-200 shadow-sm p-6 sm:p-8">

        <div class="flex items-start justify-between gap-4 pb-5 border-b border-gray-100">
            <div>
                <img src="assets/electrinc-logo.png" alt="ELECTRINC" class="h-8 w-auto object-contain" style="filter:brightness(0) saturate(100%);">
                <p class="mt-2 text-[11px] text-gray-500">a venture of Incredible Group of Companies</p>
            </div>
            <div class="text-right">
                <h1 class="text-[20px] font-extrabold text-[#0E7A3B]">BOOKING RECEIPT</h1>
                <p class="text-[12px] text-gray-500 mt-1">Invoice No: <strong class="text-gray-800"><?php echo e($invNo); ?></strong></p>
                <p class="text-[12px] text-gray-500">Date: <?php echo e(date('d M Y', strtotime((string)$req['created_at']))); ?></p>
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mt-5">
            <div>
                <p class="text-[10px] uppercase tracking-wide text-gray-400 font-semibold">Billed To</p>
                <p class="mt-1 text-[14px] font-semibold text-gray-800"><?php echo e($member['full_name'] ?? $customer_id); ?></p>
                <p class="text-[12px] text-gray-500">Member ID: <?php echo e($customer_id); ?></p>
                <?php if (!empty($member['mobile'])): ?><p class="text-[12px] text-gray-500"><?php echo e($member['mobile']); ?></p><?php endif; ?>
                <?php if (!empty($member['email'])): ?><p class="text-[12px] text-gray-500"><?php echo e($member['email']); ?></p><?php endif; ?>
            </div>
            <div class="sm:text-right">
                <p class="text-[10px] uppercase tracking-wide text-gray-400 font-semibold">Payment Details</p>
                <p class="mt-1 text-[12px] text-gray-600">Mode: <strong class="text-gray-800"><?php echo e($req['payment_mode']); ?></strong></p>
                <p class="text-[12px] text-gray-600">Transaction / UTR: <strong class="text-gray-800"><?php echo e($req['transaction_id']); ?></strong></p>
                <p class="mt-1 text-[11px] rounded-full inline-block px-2.5 py-1 bg-green-50 text-green-700 font-semibold">Approved</p>
            </div>
        </div>

        <div class="mt-6 overflow-x-auto">
            <table class="w-full text-[13px]">
                <thead class="bg-gray-50 text-gray-500 text-[12px]">
                    <tr>
                        <th class="text-left px-4 py-2.5">Description</th>
                        <th class="text-right px-4 py-2.5">Qty</th>
                        <th class="text-right px-4 py-2.5">Amount</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php foreach ($items as $it): ?>
                        <tr>
                            <td class="px-4 py-2.5 text-gray-700"><?php echo e($it['item'] ?? 'Joining Booking'); ?></td>
                            <td class="px-4 py-2.5 text-right text-gray-600"><?php echo (int)($it['qty'] ?? 1); ?></td>
                            <td class="px-4 py-2.5 text-right font-semibold text-gray-800"><?php echo aiv_inr($it['amount'] ?? $req['amount']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <tr class="bg-[#EAF5EC] font-bold">
                        <td colspan="2" class="px-4 py-3 text-right text-gray-700">Total</td>
                        <td class="px-4 py-3 text-right text-[#0E7A3B] text-[15px]"><?php echo aiv_inr($req['amount']); ?></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <p class="mt-4 text-[12px] text-gray-500">Amount in words: <span class="font-semibold text-gray-700"><?php echo e(aiv_words($req['amount'])); ?></span></p>

        <?php if (!empty($req['remark'])): ?>
            <p class="mt-3 text-[12px] text-gray-500">Remark: <?php echo e($req['remark']); ?></p>
        <?php endif; ?>

        <div class="mt-8 pt-5 border-t border-gray-100 text-center text-[11px] text-gray-400">
            This is a system-generated receipt for your one-time joining booking. No signature required.
        </div>
    </div>
</div>
<script>if (typeof lucide !== 'undefined') lucide.createIcons();</script>
</body>
</html>
