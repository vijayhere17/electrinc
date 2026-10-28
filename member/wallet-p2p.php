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
    $r = wallet_p2p_transfer($conn, $customerId, trim((string)($_POST['to_id'] ?? '')), (float)($_POST['amount'] ?? 0), trim((string)($_POST['note'] ?? '')));
    if (!empty($r['ok'])) { $msg = $r['message']; } else { $err = $r['message'] ?? 'Transfer failed.'; }
}

$wallet = wallet_balance_of($conn, $customerId);
$p2pPct = income_setting_num($conn, 'wallet_p2p_deduction_percent', 15);
$p2pMin = income_setting_num($conn, 'wallet_p2p_min', 1);
$p2pOn  = income_setting_bool($conn, 'wallet_p2p_enabled', true);

$rows = [];
$res = $conn->query("SELECT id, from_customer_id, to_customer_id, gross_amount, deduction_percent, deduction_amount, net_amount, status, note, created_at
                     FROM p2p_transfers
                     WHERE from_customer_id='" . $conn->real_escape_string($customerId) . "'
                        OR to_customer_id='" . $conn->real_escape_string($customerId) . "'
                     ORDER BY id DESC LIMIT 100");
while ($res && ($x = $res->fetch_assoc())) $rows[] = $x;

$sentTot = 0.0; $recvTot = 0.0;
foreach ($rows as $x) {
    if ($x['from_customer_id'] === $customerId) $sentTot += (float)$x['gross_amount'];
    else $recvTot += (float)$x['net_amount'];
}
function m($n) { return '₹' . number_format((float)$n, 2); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>P2P Transfer</title>
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
                <i data-lucide="arrow-left-right" class="w-[17px] h-[17px] text-[#cd9737]"></i>
            </div>
            <h2 class="whitespace-nowrap text-[20px] font-normal text-[#40352a]" style="font-family: Georgia, 'Times New Roman', serif;">P2P TRANSFER</h2>
            <div class="w-full h-[2px] bg-[#d1a14b]"></div>
        </div>

        <?php if ($msg): ?><div class="mt-4 rounded-xl border border-[#bfe3c9] bg-[#eefaf1] px-4 py-3 text-[13px] text-[#1e7a48]"><?php echo htmlspecialchars($msg); ?></div><?php endif; ?>
        <?php if ($err): ?><div class="mt-4 rounded-xl border border-[#f3c9c9] bg-[#fdecec] px-4 py-3 text-[13px] text-[#9b1c1c]"><?php echo htmlspecialchars($err); ?></div><?php endif; ?>

        <div class="mt-4 grid grid-cols-1 lg:grid-cols-3 gap-4">
            <div class="bg-[#40352a] rounded-2xl px-5 py-4"><p class="text-[10px] uppercase tracking-widest text-[#e7d3ad] font-semibold">Wallet Balance</p><p class="mt-1 text-[22px] font-bold text-white"><?php echo m($wallet); ?></p></div>
            <div class="bg-white rounded-2xl border border-[#e2d8c8] px-5 py-4"><p class="text-[10px] uppercase tracking-widest text-[#9e8f7e] font-semibold">Total Sent</p><p class="mt-1 text-[22px] font-bold text-[#2f2f2f]"><?php echo m($sentTot); ?></p></div>
            <div class="bg-white rounded-2xl border border-[#e2d8c8] px-5 py-4"><p class="text-[10px] uppercase tracking-widest text-[#9e8f7e] font-semibold">Total Received (net)</p><p class="mt-1 text-[22px] font-bold text-[#2f2f2f]"><?php echo m($recvTot); ?></p></div>
        </div>

        <form method="post" class="mt-4 bg-white rounded-2xl border border-[#e2d8c8] p-5 max-w-[560px]">
            <h3 class="text-[14px] font-semibold text-[#40352a]">Send to another member</h3>
            <p class="text-[11px] text-[#8a7c68] mt-1"><?php echo number_format($p2pPct, 0); ?>% is deducted on every transfer. The receiver gets <?php echo number_format(100 - $p2pPct, 0); ?>%. Minimum <?php echo m($p2pMin); ?>.</p>
            <?php if (!$p2pOn): ?>
                <p class="mt-3 text-[12px] text-[#b07d00]">P2P transfers are currently disabled by the admin.</p>
            <?php else: ?>
                <label class="block mt-3"><span class="text-[11px] text-[#9e8f7e]">Receiver Customer ID</span>
                    <input type="text" name="to_id" required class="mt-1 w-full border border-[#e2d8c8] rounded-lg px-3 py-2 text-[13px] bg-white"></label>
                <label class="block mt-3"><span class="text-[11px] text-[#9e8f7e]">Amount (from your wallet)</span>
                    <input type="number" step="0.01" min="0" name="amount" id="p2pAmt" required class="mt-1 w-full border border-[#e2d8c8] rounded-lg px-3 py-2 text-[13px] bg-white"></label>
                <label class="block mt-3"><span class="text-[11px] text-[#9e8f7e]">Note (optional)</span>
                    <input type="text" name="note" class="mt-1 w-full border border-[#e2d8c8] rounded-lg px-3 py-2 text-[13px] bg-white"></label>
                <p class="mt-2 text-[11px] text-[#8a7c68]">Receiver will get: <strong id="p2pNet">₹0.00</strong> &middot; Deduction: <strong id="p2pDed">₹0.00</strong></p>
                <button onclick="return confirm('Send this transfer?');" class="mt-3 rounded-lg bg-[#c79435] px-6 py-2 text-white text-[13px] hover:bg-[#b3822c]">Send</button>
            <?php endif; ?>
        </form>

        <div class="mt-5 bg-white rounded-2xl border border-[#e2d8c8] overflow-hidden">
            <div class="px-5 py-3 border-b border-[#f0e9db] text-[14px] font-semibold text-[#40352a]">My P2P Transfers</div>
            <div class="overflow-x-auto">
                <table class="w-full text-[13px]">
                    <thead class="bg-[#faf5ec] text-[#9e8f7e] text-[11px] uppercase tracking-wider">
                        <tr>
                            <th class="text-left px-4 py-3">Date</th>
                            <th class="text-left px-4 py-3">Direction</th>
                            <th class="text-left px-4 py-3">Counterparty</th>
                            <th class="text-right px-4 py-3">Gross</th>
                            <th class="text-right px-4 py-3">Deduction</th>
                            <th class="text-right px-4 py-3">Net</th>
                            <th class="text-left px-4 py-3">Note</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#f0e9db]">
                        <?php if (empty($rows)): ?>
                            <tr><td colspan="7" class="px-4 py-10 text-center text-[#b3a78f]">
                                <i data-lucide="inbox" class="w-10 h-10 opacity-40 mx-auto mb-2"></i><div>No P2P transfers yet.</div>
                            </td></tr>
                        <?php else: foreach ($rows as $x):
                            $out = $x['from_customer_id'] === $customerId; ?>
                            <tr class="data-row">
                                <td class="px-4 py-3 whitespace-nowrap"><?php echo htmlspecialchars(substr((string)$x['created_at'], 0, 16)); ?></td>
                                <td class="px-4 py-3">
                                    <span class="rounded-full px-2 py-0.5 text-[11px] <?php echo $out ? 'bg-[#fde8e8] text-[#9b1c1c]' : 'bg-[#e8f7ee] text-[#1e7a48]'; ?>"><?php echo $out ? 'Sent' : 'Received'; ?></span>
                                </td>
                                <td class="px-4 py-3 font-medium"><?php echo htmlspecialchars($out ? $x['to_customer_id'] : $x['from_customer_id']); ?></td>
                                <td class="px-4 py-3 text-right"><?php echo m($x['gross_amount']); ?></td>
                                <td class="px-4 py-3 text-right text-[#b91c1c]"><?php echo m($x['deduction_amount']); ?> <span class="text-[#a99c85]">(<?php echo number_format((float)$x['deduction_percent'], 0); ?>%)</span></td>
                                <td class="px-4 py-3 text-right font-semibold"><?php echo m($x['net_amount']); ?></td>
                                <td class="px-4 py-3 text-[#8a7c68]"><?php echo htmlspecialchars((string)$x['note']); ?></td>
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
    var pct = <?php echo json_encode((float)$p2pPct); ?>;
    var a = document.getElementById('p2pAmt'); if (!a) return;
    function upd() {
        var v = parseFloat(a.value) || 0, d = Math.round(v * pct) / 100;
        document.getElementById('p2pDed').textContent = '₹' + d.toFixed(2);
        document.getElementById('p2pNet').textContent = '₹' + (v - d).toFixed(2);
    }
    a.addEventListener('input', upd); upd();
})();
</script>
</body>
</html>
