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

$conn->query("CREATE TABLE IF NOT EXISTS direct_sponsor_income (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    sponsor_id VARCHAR(50) NOT NULL,
    from_member_id VARCHAR(50) NOT NULL,
    from_member_name VARCHAR(255) DEFAULT NULL,
    report_date DATE DEFAULT NULL,
    base_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    percentage DECIMAL(6,2) NOT NULL DEFAULT 0.00,
    income_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    fund_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    withdrawable_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    credit DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    debit DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    balance DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    source VARCHAR(30) NOT NULL DEFAULT 'Activation',
    ref_id INT DEFAULT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'Credit',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_direct_pay (sponsor_id, from_member_id, source),
    KEY idx_sponsor (sponsor_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

/* ---- filters ---- */
$dateFrom = trim($_GET['date_from'] ?? '');
$dateTo   = trim($_GET['date_to'] ?? '');

$where  = ["sponsor_id = ?"];
$params = [$customerId];
$types  = "s";
if ($dateFrom !== '') { $where[] = "report_date >= ?"; $params[] = $dateFrom; $types .= "s"; }
if ($dateTo   !== '') { $where[] = "report_date <= ?"; $params[] = $dateTo;   $types .= "s"; }
$whereStr = implode(' AND ', $where);

$rows = [];
$sumIncome = 0.0; $sumFund = 0.0; $sumWd = 0.0;

$stmt = $conn->prepare("SELECT report_date, from_member_id, from_member_name, base_amount, percentage,
                               income_amount, fund_amount, withdrawable_amount, balance, source, status, created_at
                        FROM direct_sponsor_income
                        WHERE $whereStr
                        ORDER BY id DESC");
if ($stmt) {
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) {
        $sumIncome += (float)$r['income_amount'];
        $sumFund   += (float)$r['fund_amount'];
        $sumWd     += (float)$r['withdrawable_amount'];
        $rows[] = $r;
    }
    $stmt->close();
}

/* member fund / wallet snapshot */
$fundBal = 0.0; $walletBal = 0.0; $productPurchased = 0;
$stmt = $conn->prepare("SELECT wallet_balance, product_fund_balance, product_purchased FROM signup_users WHERE customer_id = ? LIMIT 1");
if ($stmt) {
    $stmt->bind_param("s", $customerId);
    $stmt->execute();
    if ($sr = $stmt->get_result()->fetch_assoc()) {
        $walletBal = (float)($sr['wallet_balance'] ?? 0);
        $fundBal   = (float)($sr['product_fund_balance'] ?? 0);
        $productPurchased = (int)($sr['product_purchased'] ?? 0);
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
    <title>Direct Sponsor Income</title>
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
                <i data-lucide="hand-coins" class="w-[17px] h-[17px] text-[#cd9737]"></i>
            </div>
            <h2 class="whitespace-nowrap text-[20px] font-normal text-[#40352a]" style="font-family: Georgia, 'Times New Roman', serif;">
                DIRECT SPONSOR INCOME
            </h2>
            <div class="w-full h-[2px] bg-[#d1a14b]"></div>
        </div>

        <p class="mt-3 text-[12px] text-[#8a7c68]">
            You earn a confirmed <strong>5%</strong> (configurable) of the booking amount whenever a member you directly sponsor is activated.
            Withdrawals open on the 5th, 15th and 25th.
        </p>

        <div class="mt-4 sm:mt-5 grid grid-cols-2 lg:grid-cols-3 gap-3">
            <div class="sum-card bg-white rounded-2xl border border-[#e2d8c8] px-4 py-3 sm:px-5 sm:py-4">
                <p class="text-[10px] uppercase tracking-widest text-[#9e8f7e] font-semibold">Total Direct Income</p>
                <p class="mt-1 text-[18px] font-semibold text-[#2f2f2f]"><?php echo m($sumIncome); ?></p>
            </div>
            <div class="sum-card bg-white rounded-2xl border border-[#e2d8c8] px-4 py-3 sm:px-5 sm:py-4">
                <p class="text-[10px] uppercase tracking-widest text-[#9e8f7e] font-semibold">Withdrawable</p>
                <p class="mt-1 text-[18px] font-semibold text-[#2f2f2f]"><?php echo m($sumWd); ?></p>
            </div>
            <div class="sum-card bg-white rounded-2xl border border-[#e2d8c8] px-4 py-3 sm:px-5 sm:py-4">
                <p class="text-[10px] uppercase tracking-widest text-[#9e8f7e] font-semibold">Payouts</p>
                <p class="mt-1 text-[18px] font-semibold text-[#2f2f2f]"><?php echo count($rows); ?></p>
            </div>
        </div>

        <form method="get" class="mt-5 flex flex-wrap items-end gap-3">
            <div>
                <label class="block text-[11px] text-[#9e8f7e] mb-1">From</label>
                <input type="date" name="date_from" value="<?php echo htmlspecialchars($dateFrom); ?>"
                       class="border border-[#e2d8c8] rounded-lg px-3 py-2 text-[13px] bg-white">
            </div>
            <div>
                <label class="block text-[11px] text-[#9e8f7e] mb-1">To</label>
                <input type="date" name="date_to" value="<?php echo htmlspecialchars($dateTo); ?>"
                       class="border border-[#e2d8c8] rounded-lg px-3 py-2 text-[13px] bg-white">
            </div>
            <button class="rounded-lg bg-[#c79435] px-5 py-2 text-white text-[13px] hover:bg-[#b3822c]">Filter</button>
            <?php if ($dateFrom !== '' || $dateTo !== ''): ?>
                <a href="direct-sponsor-income.php" class="rounded-lg border border-[#e2d8c8] px-4 py-2 text-[13px] text-[#6b5d48] bg-white">Reset</a>
            <?php endif; ?>
        </form>

        <div class="mt-4 bg-white rounded-2xl border border-[#e2d8c8] overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-[13px]">
                    <thead class="bg-[#faf5ec] text-[#9e8f7e] text-[11px] uppercase tracking-wider">
                        <tr>
                            <th class="text-left px-4 py-3">#</th>
                            <th class="text-left px-4 py-3">Date</th>
                            <th class="text-left px-4 py-3">From Member</th>
                            <th class="text-right px-4 py-3">Booking Amt</th>
                            <th class="text-right px-4 py-3">%</th>
                            <th class="text-right px-4 py-3">Income</th>
                            <th class="text-right px-4 py-3">Withdrawable</th>
                            <th class="text-left px-4 py-3">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#f0e9db]">
                        <?php if (empty($rows)): ?>
                            <tr><td colspan="8" class="px-4 py-10 text-center text-[#b3a78f]">
                                <i data-lucide="inbox" class="w-10 h-10 opacity-40 mx-auto mb-2"></i>
                                <div>No Direct Sponsor Income yet.</div>
                            </td></tr>
                        <?php else: foreach ($rows as $i => $r): ?>
                            <tr class="data-row">
                                <td class="px-4 py-3 text-[#b3a78f]"><?php echo count($rows) - $i; ?></td>
                                <td class="px-4 py-3"><?php echo htmlspecialchars($r['report_date'] ?: substr((string)$r['created_at'], 0, 10)); ?></td>
                                <td class="px-4 py-3">
                                    <span class="font-medium"><?php echo htmlspecialchars($r['from_member_id']); ?></span>
                                    <?php if (!empty($r['from_member_name'])): ?>
                                        <span class="text-[#a99c85]"> · <?php echo htmlspecialchars($r['from_member_name']); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-3 text-right"><?php echo m($r['base_amount']); ?></td>
                                <td class="px-4 py-3 text-right"><?php echo number_format((float)$r['percentage'], 2); ?></td>
                                <td class="px-4 py-3 text-right font-semibold"><?php echo m($r['income_amount']); ?></td>
                                <td class="px-4 py-3 text-right text-[#8a7c68]"><?php echo m($r['withdrawable_amount']); ?></td>
                                <td class="px-4 py-3">
                                    <span class="rounded-full bg-[#e8f7ee] text-[#1e7a48] px-2 py-0.5 text-[11px]"><?php echo htmlspecialchars($r['status']); ?></span>
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
