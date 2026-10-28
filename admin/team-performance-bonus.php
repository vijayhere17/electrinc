<?php
session_start();
include __DIR__ . '/config/db.php';
if (!isset($conn) || !($conn instanceof mysqli)) {
    die("Database connection failed.");
}
$conn->set_charset("utf8mb4");
require_once __DIR__ . '/../includes/income_common.php';

$adminName = (string)($_SESSION['full_name'] ?? 'Admin');
$adminId   = (string)($_SESSION['member_id'] ?? $_SESSION['user_id'] ?? '');

$msg = ''; $err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $period = trim((string)($_POST['period_key'] ?? ''));
    $force  = ($_POST['action'] ?? '') === 'force';
    if (!preg_match('/^\d{4}-\d{2}$/', $period)) {
        $err = 'Invalid period.';
    } else {
        $r = team_perf_distribute($conn, $period, $adminName, $adminId, $force);
        if (!empty($r['ok']) && in_array($r['status'], ['distributed', 'already_distributed'], true)) {
            $msg = $r['status'] === 'already_distributed'
                ? "Period $period was already distributed."
                : "Distributed $period — pool ₹" . number_format((float)$r['pool_final'], 2) . " across " . count($r['payouts']) . " rank(s).";
        } else {
            $err = "Could not distribute $period: " . ($r['message'] ?? $r['status'] ?? 'unknown');
        }
    }
}

$curPeriod = team_perf_period_key($conn);
team_perf_ensure_pool($conn, $curPeriod);

$pools = [];
$res = $conn->query("SELECT * FROM team_perf_pool ORDER BY period_key DESC LIMIT 36");
while ($res && ($x = $res->fetch_assoc())) $pools[$x['period_key']] = $x;

$payoutsByPeriod = [];
$res = $conn->query("SELECT * FROM team_perf_payouts ORDER BY period_key DESC, rank_no ASC");
while ($res && ($x = $res->fetch_assoc())) $payoutsByPeriod[$x['period_key']][] = $x;

$winners = max(1, (int)income_setting_num($conn, 'team_perf_winners', 3));
$curPool = $pools[$curPeriod] ?? null;
$curPercents = [];
for ($i = 1; $i <= $winners; $i++) $curPercents[$i] = income_setting_num($conn, "team_perf_rank_{$i}_percent", 0);
$liveBoard = team_perf_leaderboard($conn, $curPeriod, max(10, $winners));
$curPoolFinal = team_perf_pool_amount($conn, (float)($curPool['business_total'] ?? 0));

function money($n) { return '₹' . number_format((float)$n, 2); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Team Performance Bonus</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body class="m-0 bg-[#f3f4f6] font-sans">
<div class="ml-64 min-h-screen w-[calc(100%-16rem)]">
    <?php include __DIR__ . '/includes/sidebar.php'; ?>
    <div class="flex-1 min-w-0 flex flex-col">
        <?php include __DIR__ . '/includes/navbar.php'; ?>
        <main class="px-8 py-5">

            <?php if ($msg): ?><div class="mb-5 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-green-700 text-sm"><?php echo htmlspecialchars($msg); ?></div><?php endif; ?>
            <?php if ($err): ?><div class="mb-5 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-red-700 text-sm"><?php echo htmlspecialchars($err); ?></div><?php endif; ?>

            <div class="flex items-center gap-4 mb-8">
                <div class="w-9 h-9 rounded-[8px] border border-[#ead9b5] bg-[#f8f1e7] flex items-center justify-center shrink-0">
                    <i data-lucide="users-round" class="w-5 h-5 text-[#c79435]"></i>
                </div>
                <h2 class="text-[24px] font-normal text-[#313131] leading-none tracking-wide" style="font-family: Georgia, 'Times New Roman', serif;">TEAM PERFORMANCE BONUS</h2>
                <div class="h-[1px] bg-[#c9963b] flex-1"></div>
                <a href="income-rules.php" class="text-[12px] text-[#c79435] hover:underline whitespace-nowrap">Configure &rarr;</a>
            </div>

            <div class="max-w-[1280px] mx-auto space-y-8">

                <div class="rounded-xl bg-white border border-gray-200 p-6">
                    <div class="flex items-start justify-between gap-4 flex-wrap">
                        <div>
                            <h3 class="text-[15px] font-semibold text-[#313131]">Current Month — <?php echo htmlspecialchars($curPeriod); ?></h3>
                            <div class="mt-3 flex flex-wrap gap-8 text-sm">
                                <div><div class="text-[12px] text-gray-500">Bonus Pool</div><div class="text-[20px] font-semibold"><?php echo money($curPoolFinal); ?></div></div>
                                <div><div class="text-[12px] text-gray-500">Qualified Leaders</div><div class="text-[20px] font-semibold"><?php echo count($liveBoard); ?></div></div>
                                <div><div class="text-[12px] text-gray-500">Status</div><div class="text-[20px] font-semibold capitalize"><?php echo htmlspecialchars($curPool['status'] ?? 'open'); ?></div></div>
                            </div>
                        </div>
                        <?php if (($curPool['status'] ?? 'open') === 'open'): ?>
                        <form method="post" onsubmit="return confirm('Force-distribute the current month now?');">
                            <input type="hidden" name="period_key" value="<?php echo htmlspecialchars($curPeriod); ?>">
                            <input type="hidden" name="action" value="force">
                            <button class="rounded-lg border border-[#c79435] text-[#a9781f] px-4 py-2 text-sm hover:bg-[#fdf6ea]">Force distribute now</button>
                        </form>
                        <?php endif; ?>
                    </div>

                    <div class="mt-5 overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="bg-gray-50 text-gray-500 text-[12px]">
                                <tr>
                                    <th class="text-left px-4 py-2">Rank</th>
                                    <th class="text-left px-4 py-2">Business Leader</th>
                                    <th class="text-right px-4 py-2">Left Business</th>
                                    <th class="text-right px-4 py-2">Right Business</th>
                                    <th class="text-right px-4 py-2">Matching</th>
                                    <th class="text-right px-4 py-2">Would get</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <?php if (empty($liveBoard)): ?>
                                    <tr><td colspan="6" class="px-4 py-6 text-center text-gray-400">No qualified Business Leaders with matching business this month.</td></tr>
                                <?php else: foreach ($liveBoard as $i => $b): $rank = $i + 1;
                                    $would = $rank <= $winners ? round($curPoolFinal * ($curPercents[$rank] ?? 0) / 100, 2) : 0; ?>
                                    <tr class="<?php echo $rank > $winners ? 'text-gray-400' : ''; ?>">
                                        <td class="px-4 py-2 font-medium">#<?php echo $rank; ?><?php if ($rank <= $winners): ?> <span class="text-gray-400">(<?php echo number_format($curPercents[$rank] ?? 0, 0); ?>%)</span><?php endif; ?></td>
                                        <td class="px-4 py-2"><?php echo htmlspecialchars($b['customer_id']); ?></td>
                                        <td class="px-4 py-2 text-right"><?php echo money($b['left']); ?></td>
                                        <td class="px-4 py-2 text-right"><?php echo money($b['right']); ?></td>
                                        <td class="px-4 py-2 text-right font-semibold"><?php echo money($b['matching']); ?></td>
                                        <td class="px-4 py-2 text-right font-semibold"><?php echo $rank <= $winners ? money($would) : '—'; ?></td>
                                    </tr>
                                <?php endforeach; endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="rounded-xl bg-white border border-gray-200 overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-100 text-[15px] font-semibold text-[#313131]">Monthly History &amp; Audit</div>
                    <div class="divide-y divide-gray-100">
                        <?php foreach ($pools as $pk => $p): ?>
                            <div class="px-6 py-4">
                                <div class="flex items-center justify-between flex-wrap gap-3">
                                    <div class="text-sm">
                                        <span class="font-semibold text-[#313131]"><?php echo htmlspecialchars($pk); ?></span>
                                        <span class="text-gray-500"> · pool <?php echo money($p['status'] === 'distributed' ? $p['pool_final'] : team_perf_pool_amount($conn, (float)$p['business_total'])); ?></span>
                                        <span class="ml-2 rounded-full px-2 py-0.5 text-[11px] <?php echo $p['status'] === 'distributed' ? 'bg-green-50 text-green-700' : 'bg-amber-50 text-amber-700'; ?>"><?php echo htmlspecialchars($p['status']); ?></span>
                                        <?php if ($p['status'] === 'distributed' && $p['distributed_by']): ?>
                                            <span class="text-gray-400 text-[12px]" title="<?php echo htmlspecialchars('%: ' . (string)$p['rank_percents_json'] . '  ·  leader rule: ' . (string)$p['leader_rule_json']); ?>"> — <?php echo htmlspecialchars((string)$p['distributed_by']); ?>, <?php echo htmlspecialchars(substr((string)$p['distributed_at'], 0, 10)); ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <?php if ($p['status'] === 'open' && $pk < $curPeriod): ?>
                                        <form method="post"><input type="hidden" name="period_key" value="<?php echo htmlspecialchars($pk); ?>">
                                            <button class="rounded-lg bg-[#c79435] px-4 py-1.5 text-white text-[13px] hover:bg-[#b3822c]">Distribute <?php echo htmlspecialchars($pk); ?></button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                                <?php if (!empty($payoutsByPeriod[$pk])): ?>
                                    <table class="w-full text-sm mt-3">
                                        <thead class="text-gray-400 text-[11px]"><tr>
                                            <th class="text-left py-1">Rank</th><th class="text-left py-1">Leader</th>
                                            <th class="text-right py-1">Left</th><th class="text-right py-1">Right</th>
                                            <th class="text-right py-1">Matching</th><th class="text-right py-1">%</th>
                                            <th class="text-right py-1">Amount</th><th class="text-right py-1">Paid</th>
                                            <th class="text-left py-1 pl-4">Status</th>
                                        </tr></thead>
                                        <tbody>
                                        <?php foreach ($payoutsByPeriod[$pk] as $po): ?>
                                            <tr class="border-t border-gray-50">
                                                <td class="py-1">#<?php echo (int)$po['rank_no']; ?></td>
                                                <td class="py-1"><?php echo htmlspecialchars($po['customer_id']); ?></td>
                                                <td class="py-1 text-right"><?php echo money($po['left_business']); ?></td>
                                                <td class="py-1 text-right"><?php echo money($po['right_business']); ?></td>
                                                <td class="py-1 text-right font-semibold"><?php echo money($po['matching_business']); ?></td>
                                                <td class="py-1 text-right"><?php echo number_format((float)$po['percent'], 2); ?></td>
                                                <td class="py-1 text-right"><?php echo money($po['amount']); ?></td>
                                                <td class="py-1 text-right font-semibold"><?php echo money($po['paid_amount']); ?></td>
                                                <td class="py-1 pl-4"><?php echo htmlspecialchars($po['status']); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                        <?php if (empty($pools)): ?><div class="px-6 py-8 text-center text-gray-400">No months yet.</div><?php endif; ?>
                    </div>
                </div>

            </div>
        </main>
    </div>
</div>
<script>lucide.createIcons();</script>
</body>
</html>
