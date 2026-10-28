<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['customer_id']) && !isset($_SESSION['member_id'])) {
    header("Location: login.php");
    exit();
}
$memberName = $_SESSION['full_name'] ?? "Member";
$customerId = $_SESSION['customer_id'] ?? $_SESSION['member_id'];

include __DIR__ . '/config/db.php';
if (!isset($conn) || !($conn instanceof mysqli)) {
    die("Database connection failed.");
}
$conn->set_charset("utf8mb4");
require_once __DIR__ . '/../includes/income_common.php';

$msg = ''; $err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $r = withdrawal_request_create($conn, $customerId, (float)($_POST['amount'] ?? 0), trim((string)($_POST['note'] ?? '')));
    if (!empty($r['ok'])) { $msg = $r['message']; } else { $err = $r['message'] ?? 'Request failed.'; }
}

$wallet = wallet_balance_of($conn, $customerId);
$bankPct = income_setting_num($conn, 'wallet_bank_deduction_percent', 20);
$wdMin  = income_setting_num($conn, 'withdrawal_min', 1);
$wdDays = income_withdrawal_days($conn);
$allowedToday = withdrawal_allowed_today($conn);
$wdOn   = income_setting_bool($conn, 'withdrawal_enabled', true);
$nextNo = withdrawal_count($conn, $customerId) + 1;
$nextCap = withdrawal_cap_for($conn, $nextNo);
$c1 = withdrawal_cap_for($conn, 1);
$c2 = withdrawal_cap_for($conn, 2);
$c3 = withdrawal_cap_for($conn, 3);
$pending = $conn->query("SELECT id, gross_amount FROM withdrawal_requests WHERE customer_id='" . $conn->real_escape_string($customerId) . "' AND status='Pending' ORDER BY id DESC LIMIT 1")->fetch_assoc();

$rows = [];
$res = $conn->query("SELECT id, request_no, gross_amount, deduction_amount, deduction_percent, net_amount, status, note, admin_note, requested_at, processed_at
                     FROM withdrawal_requests WHERE customer_id='" . $conn->real_escape_string($customerId) . "'
                     ORDER BY id DESC LIMIT 100");
while ($res && ($x = $res->fetch_assoc())) $rows[] = $x;

$paidTot = 0.0;
foreach ($rows as $x) { if ($x['status'] === 'Paid') $paidTot += (float)$x['net_amount']; }

function m($n) { return '₹' . number_format((float)$n, 2); }
function ord2($n) {
    $s = ($n % 10 === 1 && $n % 100 !== 11) ? 'st' : (($n % 10 === 2 && $n % 100 !== 12) ? 'nd' : (($n % 10 === 3 && $n % 100 !== 13) ? 'rd' : 'th'));
    return $n . $s;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Wallet to Bank</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        body { background:#f3f3f3; }
        @media (min-width: 1024px) { .main-wrap { margin-left: 256px; } }
        .data-row:hover { background:#fdf6ea !important; }
        input { outline:none; }
    </style>
</head>
<body>
<?php include __DIR__ . '/includes/sidebar.php'; ?>
<div class="main-wrap min-h-screen">
    <?php include __DIR__ . '/includes/navbar.php'; ?>
    <div class="px-3 sm:px-5 py-3">

        <div class="mt-4 sm:mt-5 flex items-center gap-3">
            <div class="w-[34px] h-[34px] rounded-[8px] bg-[#f5eee3] flex items-center justify-center shrink-0">
                <i data-lucide="banknote" class="w-[17px] h-[17px] text-[#cd9737]"></i>
            </div>
            <h2 class="whitespace-nowrap text-[20px] font-normal text-[#40352a]" style="font-family: Georgia, 'Times New Roman', serif;">WALLET TO BANK</h2>
            <div class="w-full h-[2px] bg-[#d1a14b]"></div>
        </div>

        <?php if ($msg): ?><div class="mt-4 rounded-xl border border-[#bfe3c9] bg-[#eefaf1] px-4 py-3 text-[13px] text-[#1e7a48]"><?php echo htmlspecialchars($msg); ?></div><?php endif; ?>
        <?php if ($err): ?><div class="mt-4 rounded-xl border border-[#f3c9c9] bg-[#fdecec] px-4 py-3 text-[13px] text-[#9b1c1c]"><?php echo htmlspecialchars($err); ?></div><?php endif; ?>

        <div class="mt-4 grid grid-cols-2 lg:grid-cols-4 gap-3">
            <div class="bg-[#40352a] rounded-2xl px-5 py-4"><p class="text-[10px] uppercase tracking-widest text-[#e7d3ad] font-semibold">Wallet Balance</p><p class="mt-1 text-[22px] font-bold text-white"><?php echo m($wallet); ?></p></div>
            <div class="bg-white rounded-2xl border border-[#e2d8c8] px-5 py-4"><p class="text-[10px] uppercase tracking-widest text-[#9e8f7e] font-semibold">Next Withdrawal Cap</p><p class="mt-1 text-[22px] font-bold text-[#2f2f2f]"><?php echo m($nextCap); ?></p><p class="text-[10px] text-[#a99c85]">your <?php echo ord2($nextNo); ?> withdrawal</p></div>
            <div class="bg-white rounded-2xl border border-[#e2d8c8] px-5 py-4"><p class="text-[10px] uppercase tracking-widest text-[#9e8f7e] font-semibold">Total Paid Out</p><p class="mt-1 text-[22px] font-bold text-[#2f2f2f]"><?php echo m($paidTot); ?></p></div>
            <div class="bg-white rounded-2xl border border-[#e2d8c8] px-5 py-4"><p class="text-[10px] uppercase tracking-widest text-[#9e8f7e] font-semibold">Withdrawal Days</p><p class="mt-1 text-[16px] font-bold text-[#2f2f2f]"><?php echo htmlspecialchars(implode(', ', $wdDays)); ?></p><p class="text-[10px] <?php echo $allowedToday ? 'text-[#1e7a48]' : 'text-[#b07d00]'; ?>"><?php echo $allowedToday ? 'Open today' : 'Closed today'; ?></p></div>
        </div>

        <form method="post" class="mt-4 bg-white rounded-2xl border border-[#e2d8c8] p-5 max-w-[560px]" onsubmit="return confirm('Submit this withdrawal request?');">
            <h3 class="text-[14px] font-semibold text-[#40352a]">Request a bank withdrawal</h3>
            <p class="text-[11px] text-[#8a7c68] mt-1">
                <?php echo number_format($bankPct, 0); ?>% is deducted; you receive <?php echo number_format(100 - $bankPct, 0); ?>% to your bank.
                Limits: 1st <?php echo m($c1); ?> &middot; 2nd <?php echo m($c2); ?> &middot; 3rd onwards <?php echo m($c3); ?>.
            </p>
            <?php if (!$wdOn): ?>
                <p class="mt-3 text-[12px] text-[#b07d00]">Withdrawals are currently disabled by the admin.</p>
            <?php else: ?>
                <div class="mt-3 text-[12px] text-[#40352a] bg-[#faf5ec] rounded-lg px-3 py-2">
                    This will be your <strong><?php echo ord2($nextNo); ?></strong> withdrawal — max <strong><?php echo m($nextCap); ?></strong>.
                    <?php if (!$allowedToday): ?><span class="text-[#b07d00]"> Closed today — opens on <?php echo htmlspecialchars(implode(', ', $wdDays)); ?>.</span><?php endif; ?>
                    <?php if ($pending): ?><span class="text-[#b07d00]"> You already have a pending request of <?php echo m($pending['gross_amount']); ?>.</span><?php endif; ?>
                </div>
                <label class="block mt-3"><span class="text-[11px] text-[#9e8f7e]">Amount (from your wallet, gross)</span>
                    <input type="number" step="0.01" min="0" max="<?php echo htmlspecialchars((string)$nextCap); ?>" name="amount" id="wdAmt" required class="mt-1 w-full border border-[#e2d8c8] rounded-lg px-3 py-2 text-[13px] bg-white"></label>
                <label class="block mt-3"><span class="text-[11px] text-[#9e8f7e]">Note (optional)</span>
                    <input type="text" name="note" class="mt-1 w-full border border-[#e2d8c8] rounded-lg px-3 py-2 text-[13px] bg-white"></label>
                <p class="mt-2 text-[11px] text-[#8a7c68]">You will receive: <strong id="wdNet">₹0.00</strong> &middot; Deduction: <strong id="wdDed">₹0.00</strong></p>
                <button <?php echo (!$allowedToday || $pending) ? 'disabled' : ''; ?> class="mt-3 rounded-lg bg-[#c79435] px-6 py-2 text-white text-[13px] hover:bg-[#b3822c] disabled:opacity-40 disabled:cursor-not-allowed">Request Withdrawal</button>
            <?php endif; ?>
        </form>

        <div class="mt-5 bg-white rounded-2xl border border-[#e2d8c8] overflow-hidden">
            <div class="px-5 py-3 border-b border-[#f0e9db] text-[14px] font-semibold text-[#40352a]">My Withdrawals</div>
            <div class="overflow-x-auto">
                <table class="w-full text-[13px]">
                    <thead class="bg-[#faf5ec] text-[#9e8f7e] text-[11px] uppercase tracking-wider">
                        <tr>
                            <th class="text-left px-4 py-3">#</th>
                            <th class="text-left px-4 py-3">Date</th>
                            <th class="text-right px-4 py-3">Nth</th>
                            <th class="text-right px-4 py-3">Requested</th>
                            <th class="text-right px-4 py-3">Deduction</th>
                            <th class="text-right px-4 py-3">Payable</th>
                            <th class="text-left px-4 py-3">Status</th>
                            <th class="text-left px-4 py-3">Admin Note</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#f0e9db]">
                        <?php if (empty($rows)): ?>
                            <tr><td colspan="8" class="px-4 py-10 text-center text-[#b3a78f]">
                                <i data-lucide="inbox" class="w-10 h-10 opacity-40 mx-auto mb-2"></i><div>No withdrawals yet.</div>
                            </td></tr>
                        <?php else: foreach ($rows as $x): ?>
                            <tr class="data-row">
                                <td class="px-4 py-3 text-[#b3a78f]"><?php echo (int)$x['id']; ?></td>
                                <td class="px-4 py-3 whitespace-nowrap"><?php echo htmlspecialchars(substr((string)$x['requested_at'], 0, 16)); ?></td>
                                <td class="px-4 py-3 text-right"><?php echo (int)$x['request_no']; ?></td>
                                <td class="px-4 py-3 text-right"><?php echo m($x['gross_amount']); ?></td>
                                <td class="px-4 py-3 text-right text-[#b91c1c]"><?php echo m($x['deduction_amount']); ?> <span class="text-[#a99c85]">(<?php echo number_format((float)$x['deduction_percent'], 0); ?>%)</span></td>
                                <td class="px-4 py-3 text-right font-semibold"><?php echo m($x['net_amount']); ?></td>
                                <td class="px-4 py-3">
                                    <?php $st = $x['status']; $cls = $st === 'Paid' ? 'bg-[#e8f7ee] text-[#1e7a48]' : ($st === 'Rejected' ? 'bg-[#fde8e8] text-[#9b1c1c]' : ($st === 'Approved' ? 'bg-[#e8f1fd] text-[#1565c0]' : 'bg-[#fef3e0] text-[#b07d00]')); ?>
                                    <span class="rounded-full <?php echo $cls; ?> px-2 py-0.5 text-[11px]"><?php echo htmlspecialchars($st); ?></span>
                                </td>
                                <td class="px-4 py-3 text-[#8a7c68]"><?php echo htmlspecialchars((string)$x['admin_note']); ?></td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>
<script>
lucide.createIcons();
(function () {
    var pct = <?php echo json_encode((float)$bankPct); ?>;
    var a = document.getElementById('wdAmt'); if (!a) return;
    function upd() {
        var v = parseFloat(a.value) || 0, d = Math.round(v * pct) / 100;
        document.getElementById('wdDed').textContent = '₹' + d.toFixed(2);
        document.getElementById('wdNet').textContent = '₹' + (v - d).toFixed(2);
    }
    a.addEventListener('input', upd); upd();
})();
</script>
</body>
</html>
