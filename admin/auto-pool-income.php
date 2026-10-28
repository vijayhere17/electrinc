<?php
include __DIR__ . '/config/db.php';
if (!isset($conn) || !($conn instanceof mysqli)) {
    die("Database connection failed.");
}
$conn->set_charset("utf8mb4");
require_once __DIR__ . '/../includes/income_common.php';

$status = income_table_exists($conn, 'auto_pool_state') ? auto_pool_status($conn) : [];
$cfg = auto_pool_config($conn);

$tot = ['cnt' => 0, 'income' => 0.0, 'paid' => 0.0, 'flushed' => 0.0];
$res = $conn->query("SELECT COUNT(*) c, COALESCE(SUM(income_amount),0) i, COALESCE(SUM(paid_amount),0) p, COALESCE(SUM(flushed_amount),0) f FROM auto_pool_completions");
if ($res && ($r = $res->fetch_assoc())) {
    $tot = ['cnt' => (int)$r['c'], 'income' => (float)$r['i'], 'paid' => (float)$r['p'], 'flushed' => (float)$r['f']];
}
$debitTot = (float)($conn->query("SELECT COALESCE(SUM(amount),0) s FROM auto_pool_debits")->fetch_assoc()['s'] ?? 0);

$rows = [];
$res = $conn->query("SELECT id, report_date, pool_level, head_customer_id, pool_total, income_amount, repool_amount,
                            repool_to_level, paid_amount, flushed_amount, created_at
                     FROM auto_pool_completions ORDER BY id DESC LIMIT 300");
if ($res) {
    while ($x = $res->fetch_assoc()) $rows[] = $x;
}
function money($n) { return '₹' . number_format((float)$n, 2); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Auto Pool Income</title>
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
                    <i data-lucide="layers" class="w-5 h-5 text-[#c79435]"></i>
                </div>
                <h2 class="text-[24px] font-normal text-[#313131] leading-none tracking-wide" style="font-family: Georgia, 'Times New Roman', serif;">AUTO POOL INCOME</h2>
                <div class="h-[1px] bg-[#c9963b] flex-1"></div>
                <a href="income-rules.php" class="text-[12px] text-[#c79435] hover:underline whitespace-nowrap">Configure &rarr;</a>
            </div>

            <div class="max-w-[1280px] mx-auto space-y-6">

                <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
                    <div class="rounded-xl bg-white border border-gray-200 p-4"><div class="text-[12px] text-gray-500">Completions</div><div class="text-[20px] font-semibold"><?php echo (int)$tot['cnt']; ?></div></div>
                    <div class="rounded-xl bg-white border border-gray-200 p-4"><div class="text-[12px] text-gray-500">Income Generated</div><div class="text-[20px] font-semibold"><?php echo money($tot['income']); ?></div></div>
                    <div class="rounded-xl bg-white border border-gray-200 p-4"><div class="text-[12px] text-gray-500">Paid</div><div class="text-[20px] font-semibold"><?php echo money($tot['paid']); ?></div></div>
                    <div class="rounded-xl bg-white border border-gray-200 p-4"><div class="text-[12px] text-gray-500">Flushed</div><div class="text-[20px] font-semibold text-[#b91c1c]"><?php echo money($tot['flushed']); ?></div></div>
                    <div class="rounded-xl bg-white border border-gray-200 p-4"><div class="text-[12px] text-gray-500">Entry Fees Collected</div><div class="text-[20px] font-semibold"><?php echo money($debitTot); ?></div></div>
                </div>

                <div class="rounded-xl bg-white border border-gray-200 overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-100 text-[15px] font-semibold text-[#313131]">
                        Pool Ladder
                        <span class="text-[12px] font-normal text-gray-400">— <?php echo (int)$cfg['per_pool']; ?> members per cycle, <?php echo number_format($cfg['split'], 0); ?>% income / <?php echo number_format(100 - $cfg['split'], 0); ?>% re-pool</span>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="bg-gray-50 text-gray-500 text-[12px]">
                                <tr>
                                    <th class="text-left px-4 py-3">Pool</th>
                                    <th class="text-right px-4 py-3">Per User</th>
                                    <th class="text-right px-4 py-3">Pool Total</th>
                                    <th class="text-right px-4 py-3">Income</th>
                                    <th class="text-right px-4 py-3">Re-Pool</th>
                                    <th class="text-left px-4 py-3">Current Cycle</th>
                                    <th class="text-right px-4 py-3">Waiting</th>
                                    <th class="text-right px-4 py-3">Completions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <?php foreach ($status as $s): ?>
                                    <tr>
                                        <td class="px-4 py-3 font-medium">Pool <?php echo (int)$s['level']; ?></td>
                                        <td class="px-4 py-3 text-right"><?php echo money($s['per_user']); ?></td>
                                        <td class="px-4 py-3 text-right"><?php echo money($s['pool_total']); ?></td>
                                        <td class="px-4 py-3 text-right font-semibold"><?php echo money($s['income']); ?></td>
                                        <td class="px-4 py-3 text-right text-gray-500"><?php echo (int)$s['level'] >= (int)$cfg['levels'] ? '—' : money($s['repool']); ?></td>
                                        <td class="px-4 py-3">
                                            <div class="w-40 bg-gray-100 rounded-full h-2 overflow-hidden">
                                                <div class="bg-[#c79435] h-2" style="width: <?php echo (float)$s['progress_pct']; ?>%"></div>
                                            </div>
                                            <span class="text-[11px] text-gray-400"><?php echo money($s['balance']); ?> / <?php echo money($s['pool_total']); ?></span>
                                        </td>
                                        <td class="px-4 py-3 text-right"><?php echo (int)$s['waiting']; ?></td>
                                        <td class="px-4 py-3 text-right"><?php echo (int)$s['completions']; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="rounded-xl bg-white border border-gray-200 overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-100 text-[15px] font-semibold text-[#313131]">Recent Completions</div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="bg-gray-50 text-gray-500 text-[12px]">
                                <tr>
                                    <th class="text-left px-4 py-3">#</th>
                                    <th class="text-left px-4 py-3">Date</th>
                                    <th class="text-left px-4 py-3">Pool</th>
                                    <th class="text-left px-4 py-3">Head Member</th>
                                    <th class="text-right px-4 py-3">Pool Total</th>
                                    <th class="text-right px-4 py-3">Income</th>
                                    <th class="text-right px-4 py-3">Paid</th>
                                    <th class="text-right px-4 py-3">Re-Pool</th>
                                    <th class="text-left px-4 py-3">To</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <?php if (empty($rows)): ?>
                                    <tr><td colspan="9" class="px-4 py-8 text-center text-gray-400">No pool completions yet.</td></tr>
                                <?php else: foreach ($rows as $i => $r): ?>
                                    <tr>
                                        <td class="px-4 py-3 text-gray-400"><?php echo count($rows) - $i; ?></td>
                                        <td class="px-4 py-3"><?php echo htmlspecialchars($r['report_date'] ?: substr((string)$r['created_at'], 0, 10)); ?></td>
                                        <td class="px-4 py-3">Pool <?php echo (int)$r['pool_level']; ?></td>
                                        <td class="px-4 py-3 font-medium"><?php echo htmlspecialchars($r['head_customer_id']); ?></td>
                                        <td class="px-4 py-3 text-right"><?php echo money($r['pool_total']); ?></td>
                                        <td class="px-4 py-3 text-right font-semibold"><?php echo money($r['income_amount']); ?></td>
                                        <td class="px-4 py-3 text-right"><?php echo money($r['paid_amount']); ?></td>
                                        <td class="px-4 py-3 text-right text-gray-500"><?php echo money($r['repool_amount']); ?></td>
                                        <td class="px-4 py-3"><?php echo $r['repool_to_level'] ? 'Pool ' . (int)$r['repool_to_level'] : '—'; ?></td>
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
