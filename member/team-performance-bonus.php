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

$hasT = income_table_exists($conn, 'team_perf_pool');
$curPeriod = team_perf_period_key($conn);
$winners = max(1, (int)income_setting_num($conn, 'team_perf_winners', 3));

$isLeader = $hasT ? team_perf_is_leader($conn, $customerId) : false;
$dirL = count(income_active_directs_by_side($conn, $customerId, 'L'));
$dirR = count(income_active_directs_by_side($conn, $customerId, 'R'));

// my month left/right/matching
$myLeft = 0.0; $myRight = 0.0;
if ($hasT) {
    $r = $conn->query("SELECT
            COALESCE(SUM(CASE WHEN side='L' THEN amount ELSE 0 END),0) l,
            COALESCE(SUM(CASE WHEN side='R' THEN amount ELSE 0 END),0) r
        FROM team_business_contributions
        WHERE period_key='" . $conn->real_escape_string($curPeriod) . "' AND leader_id='" . $conn->real_escape_string($customerId) . "'")->fetch_assoc();
    $myLeft = (float)($r['l'] ?? 0);
    $myRight = (float)($r['r'] ?? 0);
}
$myMatch = min($myLeft, $myRight);

$board = $hasT ? team_perf_leaderboard($conn, $curPeriod, 500) : [];
$myRank = 0;
foreach ($board as $i => $b) { if ($b['customer_id'] === $customerId) { $myRank = $i + 1; break; } }
$curPool = $hasT ? $conn->query("SELECT * FROM team_perf_pool WHERE period_key='" . $conn->real_escape_string($curPeriod) . "' LIMIT 1")->fetch_assoc() : null;

$rows = []; $totalT = 0.0;
if ($hasT) {
    $stmt = $conn->prepare("SELECT period_key, rank_no, left_business, right_business, matching_business, percent, pool_final, amount, paid_amount, status, created_at
                            FROM team_perf_payouts WHERE customer_id = ? ORDER BY period_key DESC, rank_no ASC");
    $stmt->bind_param("s", $customerId);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) { $totalT += (float)$r['paid_amount']; $rows[] = $r; }
    $stmt->close();
}
function m($n) { return '₹' . number_format((float)$n, 2); }
$topN = array_slice($board, 0, $winners);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Team Performance Bonus</title>
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
                <i data-lucide="users-round" class="w-[17px] h-[17px] text-[#cd9737]"></i>
            </div>
            <h2 class="whitespace-nowrap text-[20px] font-normal text-[#40352a]" style="font-family: Georgia, 'Times New Roman', serif;">TEAM PERFORMANCE BONUS</h2>
            <div class="w-full h-[2px] bg-[#d1a14b]"></div>
        </div>

        <div class="mt-4 rounded-2xl border <?php echo $isLeader ? 'border-[#bfe3c9] bg-[#eefaf1]' : 'border-[#f0d9a8] bg-[#fdf6e6]'; ?> px-4 py-3 text-[13px]">
            <?php if ($isLeader): ?>
                <span class="font-semibold text-[#1e7a48]">You are a Business Leader.</span>
                <span class="text-[#40352a]"> Active directs — Left <?php echo $dirL; ?>, Right <?php echo $dirR; ?>. Your monthly matching business is ranked against other leaders.</span>
            <?php else: ?>
                <span class="font-semibold text-[#b07d00]">Not a Business Leader yet.</span>
                <span class="text-[#40352a]"> Needs <?php echo (int)income_setting_num($conn,'team_perf_leader_min_directs',2); ?>+ active directs<?php echo income_setting_bool($conn,'team_perf_leader_require_both_legs',true) ? ' with at least one on each leg' : ''; ?>. You have Left <?php echo $dirL; ?>, Right <?php echo $dirR; ?>.</span>
            <?php endif; ?>
        </div>

        <div class="mt-3 grid grid-cols-2 lg:grid-cols-4 gap-3">
            <div class="bg-white rounded-2xl border border-[#e2d8c8] px-4 py-3"><p class="text-[10px] uppercase tracking-widest text-[#9e8f7e] font-semibold">Left Business — <?php echo htmlspecialchars($curPeriod); ?></p><p class="mt-1 text-[18px] font-semibold"><?php echo m($myLeft); ?></p></div>
            <div class="bg-white rounded-2xl border border-[#e2d8c8] px-4 py-3"><p class="text-[10px] uppercase tracking-widest text-[#9e8f7e] font-semibold">Right Business</p><p class="mt-1 text-[18px] font-semibold"><?php echo m($myRight); ?></p></div>
            <div class="bg-white rounded-2xl border border-[#e2d8c8] px-4 py-3"><p class="text-[10px] uppercase tracking-widest text-[#9e8f7e] font-semibold">Matching Business</p><p class="mt-1 text-[18px] font-semibold"><?php echo m($myMatch); ?></p></div>
            <div class="bg-[#40352a] rounded-2xl px-4 py-3"><p class="text-[10px] uppercase tracking-widest text-[#e7d3ad] font-semibold">My Live Rank</p><p class="mt-1 text-[18px] font-semibold text-white"><?php echo $myRank ? '#' . $myRank . ($myRank <= $winners ? '' : ' (out)') : '—'; ?></p></div>
        </div>

        <div class="mt-3 grid grid-cols-2 lg:grid-cols-4 gap-3">
            <div class="bg-white rounded-2xl border border-[#e2d8c8] px-4 py-3"><p class="text-[10px] uppercase tracking-widest text-[#9e8f7e] font-semibold">Month Pool (computed)</p><p class="mt-1 text-[18px] font-semibold"><?php echo m(team_perf_pool_amount($conn, (float)($curPool['business_total'] ?? 0))); ?></p></div>
            <div class="bg-white rounded-2xl border border-[#e2d8c8] px-4 py-3"><p class="text-[10px] uppercase tracking-widest text-[#9e8f7e] font-semibold">Month Joining Business</p><p class="mt-1 text-[18px] font-semibold"><?php echo m($curPool['business_total'] ?? 0); ?></p></div>
            <div class="bg-white rounded-2xl border border-[#e2d8c8] px-4 py-3 col-span-2"><p class="text-[10px] uppercase tracking-widest text-[#9e8f7e] font-semibold">My Team Performance Bonus (paid)</p><p class="mt-1 text-[18px] font-semibold"><?php echo m($totalT); ?></p></div>
        </div>

        <div class="mt-4 bg-white rounded-2xl border border-[#e2d8c8] overflow-hidden">
            <div class="px-5 py-3 border-b border-[#f0e9db] text-[14px] font-semibold text-[#40352a]">This Month's Top <?php echo (int)$winners; ?> Leaders</div>
            <div class="overflow-x-auto">
                <table class="w-full text-[13px]">
                    <thead class="bg-[#faf5ec] text-[#9e8f7e] text-[11px] uppercase tracking-wider">
                        <tr><th class="text-left px-4 py-3">Rank</th><th class="text-left px-4 py-3">Leader</th><th class="text-right px-4 py-3">Left</th><th class="text-right px-4 py-3">Right</th><th class="text-right px-4 py-3">Matching</th></tr>
                    </thead>
                    <tbody class="divide-y divide-[#f0e9db]">
                        <?php if (empty($topN)): ?>
                            <tr><td colspan="5" class="px-4 py-8 text-center text-[#b3a78f]">No ranked leaders this month yet.</td></tr>
                        <?php else: foreach ($topN as $i => $b): ?>
                            <tr class="data-row <?php echo $b['customer_id'] === $customerId ? 'bg-[#fdf6ea]' : ''; ?>">
                                <td class="px-4 py-3 font-medium">#<?php echo $i + 1; ?></td>
                                <td class="px-4 py-3"><?php echo htmlspecialchars($b['customer_id']); ?><?php echo $b['customer_id'] === $customerId ? ' <span class="text-[#c79435]">(you)</span>' : ''; ?></td>
                                <td class="px-4 py-3 text-right"><?php echo m($b['left']); ?></td>
                                <td class="px-4 py-3 text-right"><?php echo m($b['right']); ?></td>
                                <td class="px-4 py-3 text-right font-semibold"><?php echo m($b['matching']); ?></td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="mt-4 bg-white rounded-2xl border border-[#e2d8c8] overflow-hidden">
            <div class="px-5 py-3 border-b border-[#f0e9db] text-[14px] font-semibold text-[#40352a]">My Team Performance Bonus History</div>
            <div class="overflow-x-auto">
                <table class="w-full text-[13px]">
                    <thead class="bg-[#faf5ec] text-[#9e8f7e] text-[11px] uppercase tracking-wider">
                        <tr>
                            <th class="text-left px-4 py-3">Month</th>
                            <th class="text-right px-4 py-3">Rank</th>
                            <th class="text-right px-4 py-3">Left</th>
                            <th class="text-right px-4 py-3">Right</th>
                            <th class="text-right px-4 py-3">Matching</th>
                            <th class="text-right px-4 py-3">%</th>
                            <th class="text-right px-4 py-3">Pool</th>
                            <th class="text-right px-4 py-3">Amount</th>
                            <th class="text-right px-4 py-3">Paid</th>
                            <th class="text-left px-4 py-3">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#f0e9db]">
                        <?php if (empty($rows)): ?>
                            <tr><td colspan="10" class="px-4 py-10 text-center text-[#b3a78f]">
                                <i data-lucide="inbox" class="w-10 h-10 opacity-40 mx-auto mb-2"></i><div>No Team Performance Bonus earned yet.</div>
                            </td></tr>
                        <?php else: foreach ($rows as $r): ?>
                            <tr class="data-row">
                                <td class="px-4 py-3 font-medium"><?php echo htmlspecialchars($r['period_key']); ?></td>
                                <td class="px-4 py-3 text-right">#<?php echo (int)$r['rank_no']; ?></td>
                                <td class="px-4 py-3 text-right"><?php echo m($r['left_business']); ?></td>
                                <td class="px-4 py-3 text-right"><?php echo m($r['right_business']); ?></td>
                                <td class="px-4 py-3 text-right font-semibold"><?php echo m($r['matching_business']); ?></td>
                                <td class="px-4 py-3 text-right"><?php echo number_format((float)$r['percent'], 2); ?></td>
                                <td class="px-4 py-3 text-right"><?php echo m($r['pool_final']); ?></td>
                                <td class="px-4 py-3 text-right"><?php echo m($r['amount']); ?></td>
                                <td class="px-4 py-3 text-right font-semibold"><?php echo m($r['paid_amount']); ?></td>
                                <td class="px-4 py-3">
                                    <?php $st = $r['status']; $cls = $st === 'Credit' ? 'bg-[#e8f7ee] text-[#1e7a48]' : ($st === 'Flushed' ? 'bg-[#fde8e8] text-[#9b1c1c]' : 'bg-[#fef3e0] text-[#b07d00]'); ?>
                                    <span class="rounded-full <?php echo $cls; ?> px-2 py-0.5 text-[11px]"><?php echo htmlspecialchars($st); ?></span>
                                </td>
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
