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

$conn->query("CREATE TABLE IF NOT EXISTS binary_income (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    customer_id VARCHAR(50) NOT NULL,
    left_member_id VARCHAR(50) NOT NULL,
    right_member_id VARCHAR(50) NOT NULL,
    pair_no INT NOT NULL DEFAULT 0,
    report_date DATE DEFAULT NULL,
    left_base DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    right_base DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    percentage DECIMAL(6,2) NOT NULL DEFAULT 0.00,
    gross_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    paid_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    flushed_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    fund_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    withdrawable_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    status VARCHAR(20) NOT NULL DEFAULT 'Credit',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_jodi (customer_id, left_member_id, right_member_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$leftDirects  = income_active_directs_by_side($conn, $customerId, 'L');
$rightDirects = income_active_directs_by_side($conn, $customerId, 'R');
$qualified    = (count($leftDirects) >= 1 && count($rightDirects) >= 1);
$activeDirects = income_active_direct_count($conn, $customerId);

$cycle    = income_current_cycle($conn);
$ceiling  = income_cap_ceiling($conn, $customerId);
$usedCap  = income_cycle_capped_paid($conn, $customerId, $cycle['key']);
$roomCap  = max(0.0, $ceiling - $usedCap);

$rows = [];
$sumGross = 0.0; $sumPaid = 0.0; $sumFlushed = 0.0; $sumFund = 0.0; $sumWd = 0.0;
$stmt = $conn->prepare("SELECT report_date, left_member_id, right_member_id, pair_no, left_base, right_base,
                               percentage, gross_amount, paid_amount, flushed_amount, fund_amount, withdrawable_amount,
                               status, created_at
                        FROM binary_income WHERE customer_id = ? ORDER BY id DESC");
if ($stmt) {
    $stmt->bind_param("s", $customerId);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) {
        $sumGross   += (float)$r['gross_amount'];
        $sumPaid    += (float)$r['paid_amount'];
        $sumFlushed += (float)$r['flushed_amount'];
        $sumFund    += (float)$r['fund_amount'];
        $sumWd      += (float)$r['withdrawable_amount'];
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
    <title>Binary Income</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        body { background:#f3f3f3; }
        @media (min-width: 1024px) { .main-wrap { margin-left: 256px; } }
        .sum-card { transition: box-shadow .2s, transform .2s; }
        .sum-card:hover { box-shadow:0 6px 24px rgba(200,151,65,.18); transform:translateY(-2px); }
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
                <i data-lucide="git-fork" class="w-[17px] h-[17px] text-[#cd9737]"></i>
            </div>
            <h2 class="whitespace-nowrap text-[20px] font-normal text-[#40352a]" style="font-family: Georgia, 'Times New Roman', serif;">
                BINARY INCOME
            </h2>
            <div class="w-full h-[2px] bg-[#d1a14b]"></div>
        </div>

        <!-- qualification -->
        <div class="mt-4 rounded-2xl border <?php echo $qualified ? 'border-[#bfe3c9] bg-[#eefaf1]' : 'border-[#f0d9a8] bg-[#fdf6e6]'; ?> px-4 py-3 text-[13px]">
            <?php if ($qualified): ?>
                <span class="font-semibold text-[#1e7a48]">Qualified.</span>
                <span class="text-[#40352a]">You have <?php echo count($leftDirects); ?> active Left direct(s) and <?php echo count($rightDirects); ?> active Right direct(s). Each Left+Right pairing forms a Jodi worth <?php echo number_format((float)income_setting_num($conn,'binary_percent',2.5)*2,2); ?>% of one booking unit.</span>
            <?php else: ?>
                <span class="font-semibold text-[#b07d00]">Not qualified yet.</span>
                <span class="text-[#40352a]">Binary Income needs at least <strong>1 active direct on Left</strong> and <strong>1 on Right</strong>. You have Left: <?php echo count($leftDirects); ?>, Right: <?php echo count($rightDirects); ?>.</span>
            <?php endif; ?>
        </div>

        <!-- cap status -->
        <div class="mt-3 grid grid-cols-2 lg:grid-cols-4 gap-3">
            <div class="sum-card bg-white rounded-2xl border border-[#e2d8c8] px-4 py-3">
                <p class="text-[10px] uppercase tracking-widest text-[#9e8f7e] font-semibold">Active Directs</p>
                <p class="mt-1 text-[18px] font-semibold text-[#2f2f2f]"><?php echo (int)$activeDirects; ?></p>
            </div>
            <div class="sum-card bg-white rounded-2xl border border-[#e2d8c8] px-4 py-3">
                <p class="text-[10px] uppercase tracking-widest text-[#9e8f7e] font-semibold">Cycle Cap</p>
                <p class="mt-1 text-[18px] font-semibold text-[#2f2f2f]"><?php echo m($ceiling); ?></p>
            </div>
            <div class="sum-card bg-white rounded-2xl border border-[#e2d8c8] px-4 py-3">
                <p class="text-[10px] uppercase tracking-widest text-[#9e8f7e] font-semibold">Cap Used (this cycle)</p>
                <p class="mt-1 text-[18px] font-semibold text-[#2f2f2f]"><?php echo m($usedCap); ?></p>
            </div>
            <div class="sum-card bg-white rounded-2xl border border-[#e2d8c8] px-4 py-3">
                <p class="text-[10px] uppercase tracking-widest text-[#9e8f7e] font-semibold">Cap Remaining</p>
                <p class="mt-1 text-[18px] font-semibold text-[#2f2f2f]"><?php echo m($roomCap); ?></p>
            </div>
        </div>
        <p class="mt-2 text-[11px] text-[#8a7c68]">Cycle <?php echo htmlspecialchars($cycle['start']); ?> &rarr; <?php echo htmlspecialchars($cycle['end']); ?>. Income above the cap in a cycle is flushed. Withdrawals open on the 5th, 15th and 25th.</p>

        <!-- income totals -->
        <div class="mt-3 grid grid-cols-2 lg:grid-cols-3 gap-3">
            <div class="sum-card bg-white rounded-2xl border border-[#e2d8c8] px-4 py-3">
                <p class="text-[10px] uppercase tracking-widest text-[#9e8f7e] font-semibold">Total Binary (Paid)</p>
                <p class="mt-1 text-[18px] font-semibold text-[#2f2f2f]"><?php echo m($sumPaid); ?></p>
            </div>
            <div class="sum-card bg-white rounded-2xl border border-[#e2d8c8] px-4 py-3">
                <p class="text-[10px] uppercase tracking-widest text-[#9e8f7e] font-semibold">Withdrawable</p>
                <p class="mt-1 text-[18px] font-semibold text-[#2f2f2f]"><?php echo m($sumWd); ?></p>
            </div>
            <div class="sum-card bg-white rounded-2xl border border-[#e2d8c8] px-4 py-3">
                <p class="text-[10px] uppercase tracking-widest text-[#9e8f7e] font-semibold">Flushed (capped)</p>
                <p class="mt-1 text-[18px] font-semibold text-[#b91c1c]"><?php echo m($sumFlushed); ?></p>
            </div>
        </div>

        <div class="mt-4 bg-white rounded-2xl border border-[#e2d8c8] overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-[13px]">
                    <thead class="bg-[#faf5ec] text-[#9e8f7e] text-[11px] uppercase tracking-wider">
                        <tr>
                            <th class="text-left px-4 py-3">Jodi</th>
                            <th class="text-left px-4 py-3">Date</th>
                            <th class="text-left px-4 py-3">Left Member</th>
                            <th class="text-left px-4 py-3">Right Member</th>
                            <th class="text-right px-4 py-3">%</th>
                            <th class="text-right px-4 py-3">Gross</th>
                            <th class="text-right px-4 py-3">Paid</th>
                            <th class="text-right px-4 py-3">Withdrawable</th>
                            <th class="text-right px-4 py-3">Flushed</th>
                            <th class="text-left px-4 py-3">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#f0e9db]">
                        <?php if (empty($rows)): ?>
                            <tr><td colspan="10" class="px-4 py-10 text-center text-[#b3a78f]">
                                <i data-lucide="inbox" class="w-10 h-10 opacity-40 mx-auto mb-2"></i>
                                <div>No Jodis formed yet.</div>
                            </td></tr>
                        <?php else: foreach ($rows as $r): ?>
                            <tr class="data-row">
                                <td class="px-4 py-3 font-medium"><?php echo (int)$r['pair_no']; ?></td>
                                <td class="px-4 py-3"><?php echo htmlspecialchars($r['report_date'] ?: substr((string)$r['created_at'], 0, 10)); ?></td>
                                <td class="px-4 py-3"><?php echo htmlspecialchars($r['left_member_id']); ?></td>
                                <td class="px-4 py-3"><?php echo htmlspecialchars($r['right_member_id']); ?></td>
                                <td class="px-4 py-3 text-right"><?php echo number_format((float)$r['percentage'], 2); ?></td>
                                <td class="px-4 py-3 text-right"><?php echo m($r['gross_amount']); ?></td>
                                <td class="px-4 py-3 text-right font-semibold"><?php echo m($r['paid_amount']); ?></td>
                                <td class="px-4 py-3 text-right text-[#8a7c68]"><?php echo m($r['withdrawable_amount']); ?></td>
                                <td class="px-4 py-3 text-right text-[#b91c1c]"><?php echo m($r['flushed_amount']); ?></td>
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
