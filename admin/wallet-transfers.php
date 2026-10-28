<?php
include __DIR__ . '/config/db.php';
if (!isset($conn) || !($conn instanceof mysqli)) {
    die("Database connection failed.");
}
$conn->set_charset("utf8mb4");
require_once __DIR__ . '/../includes/income_common.php';

$sum = $conn->query("SELECT COUNT(*) c, COALESCE(SUM(gross_amount),0) g, COALESCE(SUM(deduction_amount),0) d, COALESCE(SUM(net_amount),0) n FROM p2p_transfers")->fetch_assoc();
$rows = [];
$res = $conn->query("SELECT * FROM p2p_transfers ORDER BY id DESC LIMIT 300");
while ($res && ($x = $res->fetch_assoc())) $rows[] = $x;
function money($n) { return '₹' . number_format((float)$n, 2); }
$pct = income_setting_num($conn, 'wallet_p2p_deduction_percent', 15);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>P2P Wallet Transfers</title>
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
                    <i data-lucide="arrow-left-right" class="w-5 h-5 text-[#c79435]"></i>
                </div>
                <h2 class="text-[24px] font-normal text-[#313131] leading-none tracking-wide" style="font-family: Georgia, 'Times New Roman', serif;">P2P WALLET TRANSFERS</h2>
                <div class="h-[1px] bg-[#c9963b] flex-1"></div>
                <a href="income-rules.php" class="text-[12px] text-[#c79435] hover:underline whitespace-nowrap">Rules &rarr;</a>
            </div>

            <div class="max-w-[1180px] mx-auto space-y-6">
                <p class="text-[13px] text-gray-500">Member-to-member wallet transfers. <?php echo number_format($pct, 0); ?>% is deducted from every transfer; the receiver gets the rest.</p>

                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <div class="rounded-xl bg-white border border-gray-200 p-4"><div class="text-[12px] text-gray-500">Transfers</div><div class="text-[20px] font-semibold"><?php echo (int)($sum['c'] ?? 0); ?></div></div>
                    <div class="rounded-xl bg-white border border-gray-200 p-4"><div class="text-[12px] text-gray-500">Gross Sent</div><div class="text-[20px] font-semibold"><?php echo money($sum['g'] ?? 0); ?></div></div>
                    <div class="rounded-xl bg-white border border-gray-200 p-4"><div class="text-[12px] text-gray-500">Total Deducted</div><div class="text-[20px] font-semibold text-[#b91c1c]"><?php echo money($sum['d'] ?? 0); ?></div></div>
                    <div class="rounded-xl bg-white border border-gray-200 p-4"><div class="text-[12px] text-gray-500">Net Received</div><div class="text-[20px] font-semibold"><?php echo money($sum['n'] ?? 0); ?></div></div>
                </div>

                <div class="rounded-xl bg-white border border-gray-200 overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="bg-gray-50 text-gray-500 text-[12px]">
                                <tr>
                                    <th class="text-left px-4 py-3">#</th>
                                    <th class="text-left px-4 py-3">Date</th>
                                    <th class="text-left px-4 py-3">From</th>
                                    <th class="text-left px-4 py-3">To</th>
                                    <th class="text-right px-4 py-3">Gross</th>
                                    <th class="text-right px-4 py-3">Deduction</th>
                                    <th class="text-right px-4 py-3">Net</th>
                                    <th class="text-left px-4 py-3">Note</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <?php if (empty($rows)): ?>
                                    <tr><td colspan="8" class="px-4 py-8 text-center text-gray-400">No transfers yet.</td></tr>
                                <?php else: foreach ($rows as $x): ?>
                                    <tr>
                                        <td class="px-4 py-3 text-gray-400"><?php echo (int)$x['id']; ?></td>
                                        <td class="px-4 py-3 whitespace-nowrap"><?php echo htmlspecialchars(substr((string)$x['created_at'], 0, 16)); ?></td>
                                        <td class="px-4 py-3 font-medium"><?php echo htmlspecialchars($x['from_customer_id']); ?></td>
                                        <td class="px-4 py-3 font-medium"><?php echo htmlspecialchars($x['to_customer_id']); ?></td>
                                        <td class="px-4 py-3 text-right"><?php echo money($x['gross_amount']); ?></td>
                                        <td class="px-4 py-3 text-right text-[#b91c1c]"><?php echo money($x['deduction_amount']); ?> <span class="text-gray-400">(<?php echo number_format((float)$x['deduction_percent'], 0); ?>%)</span></td>
                                        <td class="px-4 py-3 text-right font-semibold"><?php echo money($x['net_amount']); ?></td>
                                        <td class="px-4 py-3 text-gray-500"><?php echo htmlspecialchars((string)$x['note']); ?></td>
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
