<?php
include __DIR__ . '/config/db.php';
if (!isset($conn) || !($conn instanceof mysqli)) {
    die("Database connection failed.");
}
$conn->set_charset("utf8mb4");
require_once __DIR__ . '/../includes/income_common.php';

$collected = (float)($conn->query("SELECT COALESCE(SUM(amount),0) s FROM income_pool_ledger WHERE direction='credit'")->fetch_assoc()['s'] ?? 0);
$distributed = (float)($conn->query("SELECT COALESCE(SUM(amount),0) s FROM income_pool_ledger WHERE direction='debit'")->fetch_assoc()['s'] ?? 0);
$balance = round($collected - $distributed, 2);

$byType = [];
$res = $conn->query("SELECT income_type, COALESCE(SUM(amount),0) s FROM income_pool_ledger WHERE direction='debit' AND income_type <> '' GROUP BY income_type ORDER BY s DESC");
while ($res && ($r = $res->fetch_assoc())) $byType[] = $r;

$splitTot = $conn->query("SELECT COUNT(*) c, COALESCE(SUM(joining_amount),0) j, COALESCE(SUM(own_fund_amount),0) f, COALESCE(SUM(income_pool_amount),0) p FROM joining_splits")->fetch_assoc();

$rows = [];
$res = $conn->query("SELECT id, direction, category, amount, balance_after, customer_id, income_type, ref, created_at
                     FROM income_pool_ledger ORDER BY id DESC LIMIT 300");
while ($res && ($x = $res->fetch_assoc())) $rows[] = $x;

$splits = [];
$res = $conn->query("SELECT customer_id, joining_amount, own_fund_percent, own_fund_amount, income_pool_amount, created_at
                     FROM joining_splits ORDER BY id DESC LIMIT 200");
while ($res && ($x = $res->fetch_assoc())) $splits[] = $x;

function money($n) { return '₹' . number_format((float)$n, 2); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Income Distribution Pool</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body class="m-0 bg-[#f3f4f6] font-sans">
<div class="ml-64 min-h-screen w-[calc(100%-16rem)]">
    <?php include __DIR__ . '/includes/sidebar.php'; ?>
    <div class="flex-1 min-w-0 flex flex-col">
        <?php include __DIR__ . '/includes/navbar.php'; ?>
        <main class="px-8 py-5">

            <div class="flex items-center gap-4 mb-8">
                <div class="w-9 h-9 rounded-[8px] border border-[#ead9b5] bg-[#f8f1e7] flex items-center justify-center shrink-0">
                    <i data-lucide="piggy-bank" class="w-5 h-5 text-[#c79435]"></i>
                </div>
                <h2 class="text-[24px] font-normal text-[#313131] leading-none tracking-wide" style="font-family: Georgia, 'Times New Roman', serif;">INCOME DISTRIBUTION POOL</h2>
                <div class="h-[1px] bg-[#c9963b] flex-1"></div>
                <a href="income-rules.php" class="text-[12px] text-[#c79435] hover:underline whitespace-nowrap">Rules &rarr;</a>
            </div>

            <div class="max-w-[1280px] mx-auto space-y-6">
                <p class="text-[13px] text-gray-500">
                    50% of every joining amount feeds this pool; every income/bonus payout is drawn from it.
                    (The other 50% of each joining is reserved in the member's own product fund.)
                </p>

                <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                    <div class="rounded-xl bg-white border border-gray-200 p-4"><div class="text-[12px] text-gray-500">Collected (from joinings)</div><div class="text-[22px] font-semibold text-[#1e7a48]"><?php echo money($collected); ?></div></div>
                    <div class="rounded-xl bg-white border border-gray-200 p-4"><div class="text-[12px] text-gray-500">Distributed (payouts)</div><div class="text-[22px] font-semibold text-[#b91c1c]"><?php echo money($distributed); ?></div></div>
                    <div class="rounded-xl bg-[#40352a] p-4"><div class="text-[12px] text-[#e7d3ad]">Pool Balance</div><div class="text-[22px] font-semibold text-white"><?php echo money($balance); ?></div></div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <div class="rounded-xl bg-white border border-gray-200 overflow-hidden">
                        <div class="px-6 py-4 border-b border-gray-100 text-[15px] font-semibold text-[#313131]">Distributed by Income Type</div>
                        <table class="w-full text-sm">
                            <tbody class="divide-y divide-gray-100">
                                <?php if (empty($byType)): ?>
                                    <tr><td class="px-6 py-4 text-gray-400">Nothing distributed yet.</td></tr>
                                <?php else: foreach ($byType as $b): ?>
                                    <tr><td class="px-6 py-2"><?php echo htmlspecialchars($b['income_type']); ?></td><td class="px-6 py-2 text-right font-semibold"><?php echo money($b['s']); ?></td></tr>
                                <?php endforeach; endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="rounded-xl bg-white border border-gray-200 overflow-hidden">
                        <div class="px-6 py-4 border-b border-gray-100 text-[15px] font-semibold text-[#313131]">Joining Splits</div>
                        <table class="w-full text-sm">
                            <tbody class="divide-y divide-gray-100">
                                <tr><td class="px-6 py-2 text-gray-500">Members joined</td><td class="px-6 py-2 text-right font-semibold"><?php echo (int)($splitTot['c'] ?? 0); ?></td></tr>
                                <tr><td class="px-6 py-2 text-gray-500">Total joining collected</td><td class="px-6 py-2 text-right font-semibold"><?php echo money($splitTot['j'] ?? 0); ?></td></tr>
                                <tr><td class="px-6 py-2 text-gray-500">To members' product funds</td><td class="px-6 py-2 text-right font-semibold"><?php echo money($splitTot['f'] ?? 0); ?></td></tr>
                                <tr><td class="px-6 py-2 text-gray-500">To Income Distribution Pool</td><td class="px-6 py-2 text-right font-semibold"><?php echo money($splitTot['p'] ?? 0); ?></td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="rounded-xl bg-white border border-gray-200 overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-100 text-[15px] font-semibold text-[#313131]">Pool Ledger</div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="bg-gray-50 text-gray-500 text-[12px]">
                                <tr>
                                    <th class="text-left px-4 py-3">#</th>
                                    <th class="text-left px-4 py-3">Date</th>
                                    <th class="text-left px-4 py-3">Category</th>
                                    <th class="text-left px-4 py-3">Member</th>
                                    <th class="text-left px-4 py-3">Income Type</th>
                                    <th class="text-right px-4 py-3">Credit</th>
                                    <th class="text-right px-4 py-3">Debit</th>
                                    <th class="text-right px-4 py-3">Balance</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <?php if (empty($rows)): ?>
                                    <tr><td colspan="8" class="px-4 py-8 text-center text-gray-400">No pool activity yet.</td></tr>
                                <?php else: foreach ($rows as $x): ?>
                                    <tr>
                                        <td class="px-4 py-2 text-gray-400"><?php echo (int)$x['id']; ?></td>
                                        <td class="px-4 py-2 whitespace-nowrap"><?php echo htmlspecialchars(substr((string)$x['created_at'], 0, 16)); ?></td>
                                        <td class="px-4 py-2"><?php echo htmlspecialchars($x['category']); ?></td>
                                        <td class="px-4 py-2"><?php echo htmlspecialchars((string)($x['customer_id'] ?: '—')); ?></td>
                                        <td class="px-4 py-2"><?php echo htmlspecialchars((string)($x['income_type'] ?: '—')); ?></td>
                                        <td class="px-4 py-2 text-right text-[#1e7a48]"><?php echo $x['direction'] === 'credit' ? money($x['amount']) : ''; ?></td>
                                        <td class="px-4 py-2 text-right text-[#b91c1c]"><?php echo $x['direction'] === 'debit' ? money($x['amount']) : ''; ?></td>
                                        <td class="px-4 py-2 text-right text-gray-500"><?php echo money($x['balance_after']); ?></td>
                                    </tr>
                                <?php endforeach; endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </main>
    </div>
</div>
<script>lucide.createIcons();</script>
</body>
</html>
