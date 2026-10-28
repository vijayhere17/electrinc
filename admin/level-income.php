<?php
include __DIR__ . '/config/db.php';
if (!isset($conn) || !($conn instanceof mysqli)) {
    die("Database connection failed.");
}
$conn->set_charset("utf8mb4");
require_once __DIR__ . '/../includes/income_common.php';

$filterLevel = (int)($_GET['level'] ?? 0);

$where = '1=1';
if ($filterLevel >= 1) { $where .= ' AND level_no = ' . $filterLevel; }

$tot = ['cnt' => 0, 'gross' => 0.0, 'paid' => 0.0, 'flushed' => 0.0];
$res = $conn->query("SELECT COUNT(*) c, COALESCE(SUM(gross_amount),0) g, COALESCE(SUM(paid_amount),0) p, COALESCE(SUM(flushed_amount),0) f FROM level_income WHERE $where");
if ($res && ($r = $res->fetch_assoc())) {
    $tot = ['cnt' => (int)$r['c'], 'gross' => (float)$r['g'], 'paid' => (float)$r['p'], 'flushed' => (float)$r['f']];
}

$byLevel = [];
$res = $conn->query("SELECT level_no, COUNT(*) c, COALESCE(SUM(paid_amount),0) p, COALESCE(SUM(flushed_amount),0) f FROM level_income GROUP BY level_no ORDER BY level_no");
while ($res && ($r = $res->fetch_assoc())) $byLevel[] = $r;

$rows = [];
$res = $conn->query("SELECT id, report_date, customer_id, from_member_id, from_member_name, level_no,
                            business_amount, percentage, gross_amount, paid_amount, flushed_amount,
                            fund_amount, withdrawable_amount, flush_reason, status, created_at
                     FROM level_income WHERE $where ORDER BY id DESC LIMIT 300");
if ($res) {
    while ($row = $res->fetch_assoc()) $rows[] = $row;
}
function money($n) { return '₹' . number_format((float)$n, 2); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Level Income</title>
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
                    <i data-lucide="list-tree" class="w-5 h-5 text-[#c79435]"></i>
                </div>
                <h2 class="text-[24px] font-normal text-[#313131] leading-none tracking-wide" style="font-family: Georgia, 'Times New Roman', serif;">LEVEL INCOME</h2>
                <div class="h-[1px] bg-[#c9963b] flex-1"></div>
                <a href="income-rules.php" class="text-[12px] text-[#c79435] hover:underline whitespace-nowrap">Configure &rarr;</a>
            </div>

            <div class="max-w-[1280px] mx-auto space-y-6">

                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <div class="rounded-xl bg-white border border-gray-200 p-4"><div class="text-[12px] text-gray-500">Entries</div><div class="text-[20px] font-semibold"><?php echo (int)$tot['cnt']; ?></div></div>
                    <div class="rounded-xl bg-white border border-gray-200 p-4"><div class="text-[12px] text-gray-500">Gross</div><div class="text-[20px] font-semibold"><?php echo money($tot['gross']); ?></div></div>
                    <div class="rounded-xl bg-white border border-gray-200 p-4"><div class="text-[12px] text-gray-500">Paid</div><div class="text-[20px] font-semibold"><?php echo money($tot['paid']); ?></div></div>
                    <div class="rounded-xl bg-white border border-gray-200 p-4"><div class="text-[12px] text-gray-500">Flushed</div><div class="text-[20px] font-semibold text-[#b91c1c]"><?php echo money($tot['flushed']); ?></div></div>
                </div>

                <div class="rounded-xl bg-white border border-gray-200 p-4">
                    <div class="flex flex-wrap gap-2 items-center">
                        <span class="text-[12px] text-gray-500 mr-2">Level:</span>
                        <a href="?level=0" class="px-3 py-1 rounded-full text-[12px] <?php echo $filterLevel === 0 ? 'bg-[#c79435] text-white' : 'bg-gray-100 text-gray-600'; ?>">All</a>
                        <?php foreach ($byLevel as $bl): ?>
                            <a href="?level=<?php echo (int)$bl['level_no']; ?>" class="px-3 py-1 rounded-full text-[12px] <?php echo $filterLevel === (int)$bl['level_no'] ? 'bg-[#c79435] text-white' : 'bg-gray-100 text-gray-600'; ?>">
                                L<?php echo (int)$bl['level_no']; ?> · <?php echo money($bl['p']); ?><?php if ((float)$bl['f'] > 0): ?> <span class="text-[#b91c1c]">(−<?php echo money($bl['f']); ?>)</span><?php endif; ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="rounded-xl bg-white border border-gray-200 overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="bg-gray-50 text-gray-500 text-[12px]">
                                <tr>
                                    <th class="text-left px-4 py-3">#</th>
                                    <th class="text-left px-4 py-3">Date</th>
                                    <th class="text-left px-4 py-3">Earner</th>
                                    <th class="text-left px-4 py-3">From (earned Binary)</th>
                                    <th class="text-right px-4 py-3">Level</th>
                                    <th class="text-right px-4 py-3">Binary Income</th>
                                    <th class="text-right px-4 py-3">%</th>
                                    <th class="text-right px-4 py-3">Gross</th>
                                    <th class="text-right px-4 py-3">Paid</th>
                                    <th class="text-right px-4 py-3">Flushed</th>
                                    <th class="text-left px-4 py-3">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <?php if (empty($rows)): ?>
                                    <tr><td colspan="11" class="px-4 py-8 text-center text-gray-400">No Level Income yet.</td></tr>
                                <?php else: foreach ($rows as $i => $r): ?>
                                    <tr>
                                        <td class="px-4 py-3 text-gray-400"><?php echo count($rows) - $i; ?></td>
                                        <td class="px-4 py-3"><?php echo htmlspecialchars($r['report_date'] ?: substr((string)$r['created_at'], 0, 10)); ?></td>
                                        <td class="px-4 py-3 font-medium"><?php echo htmlspecialchars($r['customer_id']); ?></td>
                                        <td class="px-4 py-3"><?php echo htmlspecialchars($r['from_member_id']); ?><?php if ($r['from_member_name']): ?><span class="text-gray-400"> · <?php echo htmlspecialchars($r['from_member_name']); ?></span><?php endif; ?></td>
                                        <td class="px-4 py-3 text-right">L<?php echo (int)$r['level_no']; ?></td>
                                        <td class="px-4 py-3 text-right"><?php echo money($r['business_amount']); ?></td>
                                        <td class="px-4 py-3 text-right"><?php echo number_format((float)$r['percentage'], 2); ?></td>
                                        <td class="px-4 py-3 text-right"><?php echo money($r['gross_amount']); ?></td>
                                        <td class="px-4 py-3 text-right font-semibold"><?php echo money($r['paid_amount']); ?></td>
                                        <td class="px-4 py-3 text-right text-[#b91c1c]"><?php echo money($r['flushed_amount']); ?><?php if ($r['flush_reason']): ?><span class="block text-[10px] text-gray-400"><?php echo htmlspecialchars($r['flush_reason']); ?></span><?php endif; ?></td>
                                        <td class="px-4 py-3">
                                            <?php $st = $r['status']; $cls = $st === 'Credit' ? 'bg-green-50 text-green-700' : ($st === 'Flushed' ? 'bg-red-50 text-red-700' : 'bg-amber-50 text-amber-700'); ?>
                                            <span class="rounded-full <?php echo $cls; ?> px-2 py-0.5 text-[11px]"><?php echo htmlspecialchars($st); ?></span>
                                        </td>
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
