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

$hasPB = income_table_exists($conn, 'performance_bonus_pool');
$curPeriod = perf_bonus_period_key($conn);
$winners = max(1, (int)income_setting_num($conn, 'perf_bonus_winners', 3));
$perDirect = income_setting_num($conn, 'perf_bonus_per_direct', 100);

// my directs this month + my live rank
$myDirects = 0; $myRank = 0; $board = [];
if ($hasPB) {
    $board = perf_bonus_leaderboard($conn, $curPeriod, 500);
    foreach ($board as $i => $b) {
        if ($b['customer_id'] === $customerId) { $myDirects = $b['directs']; $myRank = $i + 1; break; }
    }
}
$curPool = null;
if ($hasPB) {
    $curPool = $conn->query("SELECT * FROM performance_bonus_pool WHERE period_key='" . $conn->real_escape_string($curPeriod) . "' LIMIT 1")->fetch_assoc();
}

// my payout history
$rows = [];
$totalPB = 0.0;
if ($hasPB) {
    $stmt = $conn->prepare("SELECT period_key, rank_no, directs_count, percent, pool_final, amount, paid_amount, flushed_amount, status, created_at
                            FROM performance_bonus_payouts WHERE customer_id = ? ORDER BY period_key DESC, rank_no ASC");
    $stmt->bind_param("s", $customerId);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) { $totalPB += (float)$r['paid_amount']; $rows[] = $r; }
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
    <title>Performance Bonus</title>
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
                <i data-lucide="trophy" class="w-[17px] h-[17px] text-[#cd9737]"></i>
            </div>
            <h2 class="whitespace-nowrap text-[20px] font-normal text-[#40352a]" style="font-family: Georgia, 'Times New Roman', serif;">PERFORMANCE BONUS</h2>
            <div class="w-full h-[2px] bg-[#d1a14b]"></div>
        </div>

        <p class="mt-3 text-[12px] text-[#8a7c68]">
            Every qualifying direct that joins under you adds <?php echo m($perDirect); ?> to this month's Performance Bonus pool.
            At month-end the top <?php echo (int)$winners; ?> members by direct count share it &mdash; 1st 40%, 2nd 30%, 3rd 30%.
        </p>

        <div class="mt-4 grid grid-cols-2 lg:grid-cols-4 gap-3">
            <div class="bg-white rounded-2xl border border-[#e2d8c8] px-4 py-3"><p class="text-[10px] uppercase tracking-widest text-[#9e8f7e] font-semibold">My Directs — <?php echo htmlspecialchars($curPeriod); ?></p><p class="mt-1 text-[18px] font-semibold"><?php echo (int)$myDirects; ?></p></div>
            <div class="bg-white rounded-2xl border border-[#e2d8c8] px-4 py-3"><p class="text-[10px] uppercase tracking-widest text-[#9e8f7e] font-semibold">My Live Rank</p><p class="mt-1 text-[18px] font-semibold"><?php echo $myRank ? '#' . $myRank . ($myRank <= $winners ? '' : ' (out)') : '—'; ?></p></div>
            <div class="bg-white rounded-2xl border border-[#e2d8c8] px-4 py-3"><p class="text-[10px] uppercase tracking-widest text-[#9e8f7e] font-semibold">Month Pool</p><p class="mt-1 text-[18px] font-semibold"><?php echo m($curPool['pool_collected'] ?? 0); ?></p></div>
            <div class="bg-[#40352a] rounded-2xl px-4 py-3"><p class="text-[10px] uppercase tracking-widest text-[#e7d3ad] font-semibold">My Performance Bonus (paid)</p><p class="mt-1 text-[18px] font-semibold text-white"><?php echo m($totalPB); ?></p></div>
        </div>

        <div class="mt-4 bg-white rounded-2xl border border-[#e2d8c8] overflow-hidden">
            <div class="px-5 py-3 border-b border-[#f0e9db] text-[14px] font-semibold text-[#40352a]">This Month's Top <?php echo (int)$winners; ?></div>
            <div class="overflow-x-auto">
                <table class="w-full text-[13px]">
                    <thead class="bg-[#faf5ec] text-[#9e8f7e] text-[11px] uppercase tracking-wider">
                        <tr><th class="text-left px-4 py-3">Rank</th><th class="text-left px-4 py-3">Member</th><th class="text-right px-4 py-3">Directs</th></tr>
                    </thead>
                    <tbody class="divide-y divide-[#f0e9db]">
                        <?php if (empty($topN)): ?>
                            <tr><td colspan="3" class="px-4 py-8 text-center text-[#b3a78f]">No qualifying directs this month yet.</td></tr>
                        <?php else: foreach ($topN as $i => $b): ?>
                            <tr class="data-row <?php echo $b['customer_id'] === $customerId ? 'bg-[#fdf6ea]' : ''; ?>">
                                <td class="px-4 py-3 font-medium">#<?php echo $i + 1; ?></td>
                                <td class="px-4 py-3"><?php echo htmlspecialchars($b['customer_id']); ?><?php echo $b['customer_id'] === $customerId ? ' <span class="text-[#c79435]">(you)</span>' : ''; ?></td>
                                <td class="px-4 py-3 text-right"><?php echo (int)$b['directs']; ?></td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="mt-4 bg-white rounded-2xl border border-[#e2d8c8] overflow-hidden">
            <div class="px-5 py-3 border-b border-[#f0e9db] text-[14px] font-semibold text-[#40352a]">My Performance Bonus History</div>
            <div class="overflow-x-auto">
                <table class="w-full text-[13px]">
                    <thead class="bg-[#faf5ec] text-[#9e8f7e] text-[11px] uppercase tracking-wider">
                        <tr>
                            <th class="text-left px-4 py-3">Month</th>
                            <th class="text-right px-4 py-3">Rank</th>
                            <th class="text-right px-4 py-3">My Directs</th>
                            <th class="text-right px-4 py-3">%</th>
                            <th class="text-right px-4 py-3">Pool</th>
                            <th class="text-right px-4 py-3">Amount</th>
                            <th class="text-right px-4 py-3">Paid</th>
                            <th class="text-left px-4 py-3">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#f0e9db]">
                        <?php if (empty($rows)): ?>
                            <tr><td colspan="8" class="px-4 py-10 text-center text-[#b3a78f]">
                                <i data-lucide="inbox" class="w-10 h-10 opacity-40 mx-auto mb-2"></i><div>No Performance Bonus earned yet.</div>
                            </td></tr>
                        <?php else: foreach ($rows as $r): ?>
                            <tr class="data-row">
                                <td class="px-4 py-3 font-medium"><?php echo htmlspecialchars($r['period_key']); ?></td>
                                <td class="px-4 py-3 text-right">#<?php echo (int)$r['rank_no']; ?></td>
                                <td class="px-4 py-3 text-right"><?php echo (int)$r['directs_count']; ?></td>
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
