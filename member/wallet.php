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

$wallet = wallet_balance_of($conn, $customerId);
$fund = 0.0;
$fr = $conn->query("SELECT product_fund_balance FROM signup_users WHERE customer_id='" . $conn->real_escape_string($customerId) . "' LIMIT 1")->fetch_assoc();
if ($fr) $fund = (float)($fr['product_fund_balance'] ?? 0);
$joinSplit = $conn->query("SELECT joining_amount, own_fund_amount, income_pool_amount FROM joining_splits WHERE customer_id='" . $conn->real_escape_string($customerId) . "' LIMIT 1")->fetch_assoc();

$wdDays  = income_withdrawal_days($conn);
$allowedToday = withdrawal_allowed_today($conn);

$hist = [];
$res = $conn->query("SELECT created_at, direction, category, amount, balance_after, counterparty_id, note
                     FROM wallet_transactions WHERE customer_id='" . $conn->real_escape_string($customerId) . "'
                     ORDER BY id DESC LIMIT 200");
while ($res && ($x = $res->fetch_assoc())) $hist[] = $x;

function m($n) { return '₹' . number_format((float)$n, 2); }
$catLabel = [
    'p2p_send' => 'Sent (P2P)', 'p2p_receive' => 'Received (P2P)',
    'withdrawal_hold' => 'Withdrawal hold', 'withdrawal_refund' => 'Withdrawal refund', 'adjustment' => 'Adjustment',
];
$basePath = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/\\');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Wallet Overview</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        body { background:#f3f3f3; }
        @media (min-width: 1024px) { .main-wrap { margin-left: 256px; } }
        .data-row:hover { background:#fdf6ea !important; }
    </style>
</head>
<body>
<?php include __DIR__ . '/includes/sidebar.php'; ?>
<div class="main-wrap min-h-screen">
    <?php include __DIR__ . '/includes/navbar.php'; ?>
    <div class="px-3 sm:px-5 py-3">

        <div class="mt-4 sm:mt-5 flex items-center gap-3">
            <div class="w-[34px] h-[34px] rounded-[8px] bg-[#f5eee3] flex items-center justify-center shrink-0">
                <i data-lucide="wallet" class="w-[17px] h-[17px] text-[#cd9737]"></i>
            </div>
            <h2 class="whitespace-nowrap text-[20px] font-normal text-[#40352a]" style="font-family: Georgia, 'Times New Roman', serif;">WALLET OVERVIEW</h2>
            <div class="w-full h-[2px] bg-[#d1a14b]"></div>
        </div>

        <div class="mt-4 grid grid-cols-2 lg:grid-cols-3 gap-3">
            <div class="bg-[#40352a] rounded-2xl px-5 py-4"><p class="text-[10px] uppercase tracking-widest text-[#e7d3ad] font-semibold">Wallet Balance</p><p class="mt-1 text-[24px] font-bold text-white"><?php echo m($wallet); ?></p><p class="text-[10px] text-[#a99c85]">withdrawable</p></div>
            <div class="bg-white rounded-2xl border border-[#e2d8c8] px-5 py-4"><p class="text-[10px] uppercase tracking-widest text-[#9e8f7e] font-semibold">My Product Fund</p><p class="mt-1 text-[24px] font-bold text-[#2f2f2f]"><?php echo m($fund); ?></p><p class="text-[10px] text-[#a99c85]">held for your EV purchase</p></div>
            <div class="bg-white rounded-2xl border border-[#e2d8c8] px-5 py-4"><p class="text-[10px] uppercase tracking-widest text-[#9e8f7e] font-semibold">Withdrawal Days</p><p class="mt-1 text-[18px] font-bold text-[#2f2f2f]"><?php echo htmlspecialchars(implode(', ', $wdDays)); ?></p><p class="text-[10px] <?php echo $allowedToday ? 'text-[#1e7a48]' : 'text-[#b07d00]'; ?>"><?php echo $allowedToday ? 'Open today' : 'Closed today'; ?></p></div>
        </div>

        <?php if ($joinSplit): ?>
        <p class="mt-2 text-[11px] text-[#8a7c68]">
            Your &#8377;<?php echo number_format((float)$joinSplit['joining_amount'], 0); ?> joining was split:
            <strong>&#8377;<?php echo number_format((float)$joinSplit['own_fund_amount'], 0); ?></strong> to your product fund (held toward your EV) &middot;
            <strong>&#8377;<?php echo number_format((float)$joinSplit['income_pool_amount'], 0); ?></strong> to the income distribution pool.
        </p>
        <?php endif; ?>

        <div class="mt-4 flex flex-wrap gap-3">
            <a href="<?php echo $basePath; ?>/wallet-p2p.php" class="rounded-xl bg-white border border-[#e2d8c8] px-5 py-3 text-[13px] font-medium text-[#40352a] hover:bg-[#fdf6ea] flex items-center gap-2">
                <i data-lucide="arrow-left-right" class="w-4 h-4 text-[#c79435]"></i> P2P Transfer
            </a>
            <a href="<?php echo $basePath; ?>/wallet-bank.php" class="rounded-xl bg-white border border-[#e2d8c8] px-5 py-3 text-[13px] font-medium text-[#40352a] hover:bg-[#fdf6ea] flex items-center gap-2">
                <i data-lucide="banknote" class="w-4 h-4 text-[#c79435]"></i> Wallet to Bank
            </a>
        </div>

        <div class="mt-4 bg-white rounded-2xl border border-[#e2d8c8] overflow-hidden">
            <div class="px-5 py-3 border-b border-[#f0e9db] text-[14px] font-semibold text-[#40352a]">Wallet Activity</div>
            <div class="overflow-x-auto">
                <table class="w-full text-[13px]">
                    <thead class="bg-[#faf5ec] text-[#9e8f7e] text-[11px] uppercase tracking-wider">
                        <tr><th class="text-left px-4 py-3">Date</th><th class="text-left px-4 py-3">Activity</th><th class="text-left px-4 py-3">With</th><th class="text-right px-4 py-3">Amount</th><th class="text-right px-4 py-3">Balance</th><th class="text-left px-4 py-3">Note</th></tr>
                    </thead>
                    <tbody class="divide-y divide-[#f0e9db]">
                        <?php if (empty($hist)): ?>
                            <tr><td colspan="6" class="px-4 py-8 text-center text-[#b3a78f]">No wallet activity yet.</td></tr>
                        <?php else: foreach ($hist as $x): ?>
                            <tr class="data-row">
                                <td class="px-4 py-3 whitespace-nowrap"><?php echo htmlspecialchars(substr((string)$x['created_at'], 0, 16)); ?></td>
                                <td class="px-4 py-3"><?php echo htmlspecialchars($catLabel[$x['category']] ?? $x['category']); ?></td>
                                <td class="px-4 py-3"><?php echo htmlspecialchars((string)($x['counterparty_id'] ?: '—')); ?></td>
                                <td class="px-4 py-3 text-right font-semibold <?php echo $x['direction'] === 'credit' ? 'text-[#1e7a48]' : 'text-[#9b1c1c]'; ?>"><?php echo ($x['direction'] === 'credit' ? '+' : '−') . m($x['amount']); ?></td>
                                <td class="px-4 py-3 text-right text-[#8a7c68]"><?php echo m($x['balance_after']); ?></td>
                                <td class="px-4 py-3 text-[#8a7c68]"><?php echo htmlspecialchars((string)$x['note']); ?></td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>
<script>lucide.createIcons();</script>
</body>
</html>
