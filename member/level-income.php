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

$conn->query("CREATE TABLE IF NOT EXISTS level_income (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT, customer_id VARCHAR(50) NOT NULL,
    from_member_id VARCHAR(50) NOT NULL, from_member_name VARCHAR(255) DEFAULT NULL,
    level_no INT NOT NULL, report_date DATE DEFAULT NULL,
    business_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00, percentage DECIMAL(6,2) NOT NULL DEFAULT 0.00,
    gross_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00, paid_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    flushed_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00, fund_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    withdrawable_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00, flush_reason VARCHAR(40) DEFAULT NULL,
    source VARCHAR(30) NOT NULL DEFAULT 'Activation', ref_id INT DEFAULT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'Credit', created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id), UNIQUE KEY uniq_level_pay (customer_id, from_member_id, level_no, source)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$levelCount = max(1, (int)income_setting_num($conn, 'level_income_count', 5));
$perLevel = [];
for ($i = 1; $i <= $levelCount; $i++) {
    $perLevel[$i] = ['pct' => income_setting_num($conn, "level_{$i}_percent", 0), 'paid' => 0.0, 'flushed' => 0.0, 'cnt' => 0];
}

$rows = [];
$sumPaid = 0.0; $sumFlushed = 0.0; $sumFund = 0.0; $sumWd = 0.0;
$stmt = $conn->prepare("SELECT report_date, from_member_id, from_member_name, level_no, business_amount, percentage,
                               gross_amount, paid_amount, flushed_amount, fund_amount, withdrawable_amount,
                               flush_reason, status, created_at
                        FROM level_income WHERE customer_id = ? ORDER BY id DESC");
if ($stmt) {
    $stmt->bind_param("s", $customerId);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) {
        $ln = (int)$r['level_no'];
        if (isset($perLevel[$ln])) {
            $perLevel[$ln]['paid'] += (float)$r['paid_amount'];
            $perLevel[$ln]['flushed'] += (float)$r['flushed_amount'];
            $perLevel[$ln]['cnt']++;
        }
        $sumPaid += (float)$r['paid_amount'];
        $sumFlushed += (float)$r['flushed_amount'];
        $sumFund += (float)$r['fund_amount'];
        $sumWd += (float)$r['withdrawable_amount'];
        $rows[] = $r;
    }
    $stmt->close();
}
function m($n) { return '₹' . number_format((float)$n, 2); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Level Income</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        body { background:#f3f3f3; }
        @media (min-width: 1024px) { .main-wrap { margin-left: 256px; } }
        .sum-card:hover { box-shadow:0 6px 24px rgba(200,151,65,.18); }
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
                <i data-lucide="list-tree" class="w-[17px] h-[17px] text-[#cd9737]"></i>
            </div>
            <h2 class="whitespace-nowrap text-[20px] font-normal text-[#40352a]" style="font-family: Georgia, 'Times New Roman', serif;">LEVEL INCOME</h2>
            <div class="w-full h-[2px] bg-[#d1a14b]"></div>
        </div>

        <p class="mt-3 text-[12px] text-[#8a7c68]">
            You earn a % of the <strong>Binary Income</strong> your downline members earn &mdash; Level 1 = 20% of your direct's Binary Income, Level 2 = 10%, and so on. You only need to be <strong>Active</strong>.
            This is separate from the &#8377;10,000 joining amount. If a level's upline is missing or inactive, that level's share is flushed (never moved to another level).
        </p>

        <div class="mt-4 grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
            <?php foreach ($perLevel as $lv => $d): ?>
                <div class="sum-card bg-white rounded-2xl border border-[#e2d8c8] px-4 py-3 transition">
                    <p class="text-[10px] uppercase tracking-widest text-[#9e8f7e] font-semibold">Level <?php echo $lv; ?> · <?php echo number_format($d['pct'], 2); ?>%</p>
                    <p class="mt-1 text-[16px] font-semibold text-[#2f2f2f]"><?php echo m($d['paid']); ?></p>
                    <?php if ($d['flushed'] > 0): ?><p class="text-[10px] text-[#b91c1c]">flushed <?php echo m($d['flushed']); ?></p><?php endif; ?>
                </div>
            <?php endforeach; ?>
            <div class="sum-card bg-[#40352a] rounded-2xl px-4 py-3">
                <p class="text-[10px] uppercase tracking-widest text-[#e7d3ad] font-semibold">Level Total</p>
                <p class="mt-1 text-[16px] font-semibold text-white"><?php echo m($sumPaid); ?></p>
            </div>
        </div>

        <div class="mt-3 grid grid-cols-3 gap-3">
            <div class="bg-white rounded-2xl border border-[#e2d8c8] px-4 py-3"><p class="text-[10px] uppercase tracking-widest text-[#9e8f7e] font-semibold">Total Paid</p><p class="mt-1 text-[18px] font-semibold"><?php echo m($sumPaid); ?></p></div>
            <div class="bg-white rounded-2xl border border-[#e2d8c8] px-4 py-3"><p class="text-[10px] uppercase tracking-widest text-[#9e8f7e] font-semibold">Withdrawable</p><p class="mt-1 text-[18px] font-semibold"><?php echo m($sumWd); ?></p></div>
            <div class="bg-white rounded-2xl border border-[#e2d8c8] px-4 py-3"><p class="text-[10px] uppercase tracking-widest text-[#9e8f7e] font-semibold">Flushed</p><p class="mt-1 text-[18px] font-semibold text-[#b91c1c]"><?php echo m($sumFlushed); ?></p></div>
        </div>

        <div class="mt-4 bg-white rounded-2xl border border-[#e2d8c8] overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-[13px]">
                    <thead class="bg-[#faf5ec] text-[#9e8f7e] text-[11px] uppercase tracking-wider">
                        <tr>
                            <th class="text-left px-4 py-3">Level</th>
                            <th class="text-left px-4 py-3">Date</th>
                            <th class="text-left px-4 py-3">From Member (earned Binary)</th>
                            <th class="text-right px-4 py-3">Binary Income</th>
                            <th class="text-right px-4 py-3">%</th>
                            <th class="text-right px-4 py-3">Gross</th>
                            <th class="text-right px-4 py-3">Paid</th>
                            <th class="text-right px-4 py-3">Flushed</th>
                            <th class="text-left px-4 py-3">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#f0e9db]">
                        <?php if (empty($rows)): ?>
                            <tr><td colspan="9" class="px-4 py-10 text-center text-[#b3a78f]">
                                <i data-lucide="inbox" class="w-10 h-10 opacity-40 mx-auto mb-2"></i><div>No Level Income yet.</div>
                            </td></tr>
                        <?php else: foreach ($rows as $r): ?>
                            <tr class="data-row">
                                <td class="px-4 py-3 font-medium">L<?php echo (int)$r['level_no']; ?></td>
                                <td class="px-4 py-3"><?php echo htmlspecialchars($r['report_date'] ?: substr((string)$r['created_at'], 0, 10)); ?></td>
                                <td class="px-4 py-3"><?php echo htmlspecialchars($r['from_member_id']); ?><?php if ($r['from_member_name']): ?><span class="text-[#a99c85]"> · <?php echo htmlspecialchars($r['from_member_name']); ?></span><?php endif; ?></td>
                                <td class="px-4 py-3 text-right"><?php echo m($r['business_amount']); ?></td>
                                <td class="px-4 py-3 text-right"><?php echo number_format((float)$r['percentage'], 2); ?></td>
                                <td class="px-4 py-3 text-right"><?php echo m($r['gross_amount']); ?></td>
                                <td class="px-4 py-3 text-right font-semibold"><?php echo m($r['paid_amount']); ?></td>
                                <td class="px-4 py-3 text-right text-[#b91c1c]"><?php echo m($r['flushed_amount']); ?><?php if ($r['flush_reason']): ?><span class="block text-[10px] text-[#a99c85]"><?php echo htmlspecialchars($r['flush_reason']); ?></span><?php endif; ?></td>
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
