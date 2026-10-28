<?php
include __DIR__ . '/config/db.php';

if (!isset($conn) || !($conn instanceof mysqli)) {
    die("Database connection failed.");
}
$conn->set_charset("utf8mb4");

require_once __DIR__ . '/../includes/income_common.php';

/* ---------- ensure storage exists (self-healing) ---------- */
$conn->query("CREATE TABLE IF NOT EXISTS income_settings (
    setting_key VARCHAR(60) NOT NULL,
    setting_value VARCHAR(255) NOT NULL,
    description VARCHAR(255) DEFAULT NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$defaults = [
    'direct_sponsor_percent'      => ['5.00', 'Direct Sponsor Income % of booking amount'],
    'direct_sponsor_base_default' => ['10000.00', 'Fallback booking amount when none is recorded on activation / product'],
    'product_fund_split_percent'  => ['50.00', '% of every income routed to the member product-collection fund until product is purchased'],
    'withdrawal_days'             => ['5,15,25', 'Days of the month withdrawals are allowed'],
];
foreach ($defaults as $k => [$v, $d]) {
    $stmt = $conn->prepare("INSERT IGNORE INTO income_settings (setting_key, setting_value, description) VALUES (?, ?, ?)");
    $stmt->bind_param('sss', $k, $v, $d);
    $stmt->execute();
    $stmt->close();
}

$successMessage = '';
$errorMessage = '';

/* Settings for every income now live in Admin > Income & Business Rules
   (with change history). This page is a read-only ledger. */

/* ---------- current values ---------- */
$cfgPercent = income_setting_num($conn, 'direct_sponsor_percent', 5);
$cfgBase    = income_setting_num($conn, 'direct_sponsor_base_default', 10000);
$cfgSplit   = income_setting_num($conn, 'product_fund_split_percent', 50);
$cfgDays    = income_setting($conn, 'withdrawal_days', '5,15,25');

/* ---------- ledger + totals ---------- */
$totals = ['cnt' => 0, 'income' => 0.0, 'fund' => 0.0, 'wd' => 0.0];
$res = $conn->query("SELECT COUNT(*) c, COALESCE(SUM(income_amount),0) i, COALESCE(SUM(fund_amount),0) f, COALESCE(SUM(withdrawable_amount),0) w FROM direct_sponsor_income");
if ($res && ($r = $res->fetch_assoc())) {
    $totals = ['cnt' => (int)$r['c'], 'income' => (float)$r['i'], 'fund' => (float)$r['f'], 'wd' => (float)$r['w']];
}

$rows = [];
$res = $conn->query("SELECT id, report_date, sponsor_id, from_member_id, from_member_name,
                            base_amount, percentage, income_amount, fund_amount, withdrawable_amount,
                            balance, source, status, created_at
                     FROM direct_sponsor_income
                     ORDER BY id DESC LIMIT 200");
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
    <title>Direct Sponsor Income</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body class="m-0 bg-[#f3f4f6] font-sans">

<div class="ml-64 min-h-screen w-[calc(100%-16rem)]">

    <?php include __DIR__ . '/includes/sidebar.php'; ?>

    <div class="flex-1 min-w-0 flex flex-col">
        <?php include __DIR__ . '/includes/navbar.php'; ?>

        <main class="px-8 py-5">

            <?php if ($successMessage !== ''): ?>
                <div class="mb-5 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-green-700 text-sm"><?php echo htmlspecialchars($successMessage); ?></div>
            <?php endif; ?>
            <?php if ($errorMessage !== ''): ?>
                <div class="mb-5 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-red-700 text-sm"><?php echo htmlspecialchars($errorMessage); ?></div>
            <?php endif; ?>

            <div class="flex items-center gap-4 mb-10">
                <div class="w-9 h-9 rounded-[8px] border border-[#ead9b5] bg-[#f8f1e7] flex items-center justify-center shrink-0">
                    <i data-lucide="hand-coins" class="w-5 h-5 text-[#c79435]"></i>
                </div>
                <h2 class="text-[24px] font-normal text-[#313131] leading-none tracking-wide" style="font-family: Georgia, 'Times New Roman', serif;">
                    DIRECT SPONSOR INCOME
                </h2>
                <div class="h-[1px] bg-[#c9963b] flex-1"></div>
            </div>

            <div class="max-w-[1280px] mx-auto space-y-8">

                <!-- summary -->
                <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                    <div class="rounded-xl bg-white border border-gray-200 p-4">
                        <div class="text-[12px] text-gray-500">Payouts</div>
                        <div class="text-[20px] font-semibold text-[#313131]"><?php echo (int)$totals['cnt']; ?></div>
                    </div>
                    <div class="rounded-xl bg-white border border-gray-200 p-4">
                        <div class="text-[12px] text-gray-500">Total Income</div>
                        <div class="text-[20px] font-semibold text-[#313131]"><?php echo money($totals['income']); ?></div>
                    </div>
                    <div class="rounded-xl bg-white border border-gray-200 p-4">
                        <div class="text-[12px] text-gray-500">Withdrawable</div>
                        <div class="text-[20px] font-semibold text-[#313131]"><?php echo money($totals['wd']); ?></div>
                    </div>
                </div>

                <!-- settings moved to Income & Business Rules -->
                <div class="rounded-xl bg-white border border-gray-200 p-6 flex items-center justify-between">
                    <div>
                        <h3 class="text-[15px] font-semibold text-[#313131]">Plan Settings</h3>
                        <p class="text-[12px] text-gray-500 mt-1">Direct Sponsor %: <strong><?php echo number_format($cfgPercent,2); ?>%</strong> &middot; Booking base: <strong>&#8377;<?php echo number_format($cfgBase,2); ?></strong> &middot; Fund split: <strong><?php echo number_format($cfgSplit,2); ?>%</strong> &middot; Withdrawal days: <strong><?php echo htmlspecialchars($cfgDays); ?></strong></p>
                        <p class="text-[11px] text-gray-400 mt-1">Per-product override: Product Master (sponsor %).</p>
                    </div>
                    <a href="income-rules.php" class="rounded-lg bg-[#c79435] px-5 py-2 text-white text-sm hover:bg-[#b3822c] whitespace-nowrap">Edit in Income &amp; Business Rules</a>
                </div>

                <!-- ledger -->
                <div class="rounded-xl bg-white border border-gray-200 overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-100 text-[15px] font-semibold text-[#313131]">Recent Payouts</div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="bg-gray-50 text-gray-500 text-[12px]">
                                <tr>
                                    <th class="text-left px-4 py-3">#</th>
                                    <th class="text-left px-4 py-3">Date</th>
                                    <th class="text-left px-4 py-3">Sponsor</th>
                                    <th class="text-left px-4 py-3">From Member</th>
                                    <th class="text-right px-4 py-3">Base</th>
                                    <th class="text-right px-4 py-3">%</th>
                                    <th class="text-right px-4 py-3">Income</th>
                                    <th class="text-right px-4 py-3">Withdrawable</th>
                                    <th class="text-left px-4 py-3">Source</th>
                                    <th class="text-left px-4 py-3">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <?php if (empty($rows)): ?>
                                    <tr><td colspan="9" class="px-4 py-8 text-center text-gray-400">No Direct Sponsor Income has been paid yet.</td></tr>
                                <?php else: foreach ($rows as $i => $r): ?>
                                    <tr>
                                        <td class="px-4 py-3 text-gray-400"><?php echo count($rows) - $i; ?></td>
                                        <td class="px-4 py-3"><?php echo htmlspecialchars($r['report_date'] ?: substr((string)$r['created_at'], 0, 10)); ?></td>
                                        <td class="px-4 py-3 font-medium"><?php echo htmlspecialchars($r['sponsor_id']); ?></td>
                                        <td class="px-4 py-3">
                                            <?php echo htmlspecialchars($r['from_member_id']); ?>
                                            <?php if (!empty($r['from_member_name'])): ?>
                                                <span class="text-gray-400"> · <?php echo htmlspecialchars($r['from_member_name']); ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-4 py-3 text-right"><?php echo money($r['base_amount']); ?></td>
                                        <td class="px-4 py-3 text-right"><?php echo number_format((float)$r['percentage'], 2); ?></td>
                                        <td class="px-4 py-3 text-right font-semibold"><?php echo money($r['income_amount']); ?></td>
                                        <td class="px-4 py-3 text-right text-gray-500"><?php echo money($r['withdrawable_amount']); ?></td>
                                        <td class="px-4 py-3"><?php echo htmlspecialchars($r['source']); ?></td>
                                        <td class="px-4 py-3">
                                            <span class="rounded-full bg-green-50 text-green-700 px-2 py-0.5 text-[11px]"><?php echo htmlspecialchars($r['status']); ?></span>
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
