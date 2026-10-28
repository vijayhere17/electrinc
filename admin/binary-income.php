<?php
include __DIR__ . '/config/db.php';
if (!isset($conn) || !($conn instanceof mysqli)) {
    die("Database connection failed.");
}
$conn->set_charset("utf8mb4");
require_once __DIR__ . '/../includes/income_common.php';

$conn->query("CREATE TABLE IF NOT EXISTS income_settings (
    setting_key VARCHAR(60) NOT NULL,
    setting_value VARCHAR(255) NOT NULL,
    description VARCHAR(255) DEFAULT NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$defaults = [
    'binary_percent'    => ['2.50', 'Binary Income % applied to EACH side booking amount per Jodi'],
    'cap_per_2_directs' => ['10000.00', 'Capping unlocked per 2 active direct sponsors'],
    'cap_max'           => ['50000.00', 'Maximum income capping per withdrawal cycle'],
    'withdrawal_days'   => ['5,15,25', 'Days of the month withdrawals are allowed'],
];
foreach ($defaults as $k => [$v, $d]) {
    $stmt = $conn->prepare("INSERT IGNORE INTO income_settings (setting_key, setting_value, description) VALUES (?, ?, ?)");
    $stmt->bind_param('sss', $k, $v, $d);
    $stmt->execute();
    $stmt->close();
}

$successMessage = '';
$errorMessage = '';

/* Settings now live in Admin > Income & Business Rules (with history). Read-only ledger here. */

$cfgBp   = income_setting_num($conn, 'binary_percent', 2.5);
$cfgCap2 = income_setting_num($conn, 'cap_per_2_directs', 10000);
$cfgCapM = income_setting_num($conn, 'cap_max', 50000);
$cycle   = income_current_cycle($conn);

$tot = ['cnt' => 0, 'gross' => 0.0, 'paid' => 0.0, 'flushed' => 0.0];
$res = $conn->query("SELECT COUNT(*) c, COALESCE(SUM(gross_amount),0) g, COALESCE(SUM(paid_amount),0) p, COALESCE(SUM(flushed_amount),0) f FROM binary_income");
if ($res && ($r = $res->fetch_assoc())) {
    $tot = ['cnt' => (int)$r['c'], 'gross' => (float)$r['g'], 'paid' => (float)$r['p'], 'flushed' => (float)$r['f']];
}

$rows = [];
$res = $conn->query("SELECT id, report_date, customer_id, left_member_id, right_member_id, pair_no,
                            left_base, right_base, percentage, gross_amount, paid_amount, flushed_amount,
                            fund_amount, withdrawable_amount, status, created_at
                     FROM binary_income ORDER BY id DESC LIMIT 200");
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
    <title>Binary Income</title>
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
                    <i data-lucide="git-fork" class="w-5 h-5 text-[#c79435]"></i>
                </div>
                <h2 class="text-[24px] font-normal text-[#313131] leading-none tracking-wide" style="font-family: Georgia, 'Times New Roman', serif;">BINARY INCOME</h2>
                <div class="h-[1px] bg-[#c9963b] flex-1"></div>
            </div>

            <div class="max-w-[1280px] mx-auto space-y-8">

                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <div class="rounded-xl bg-white border border-gray-200 p-4">
                        <div class="text-[12px] text-gray-500">Jodis Paid</div>
                        <div class="text-[20px] font-semibold text-[#313131]"><?php echo (int)$tot['cnt']; ?></div>
                    </div>
                    <div class="rounded-xl bg-white border border-gray-200 p-4">
                        <div class="text-[12px] text-gray-500">Gross Binary</div>
                        <div class="text-[20px] font-semibold text-[#313131]"><?php echo money($tot['gross']); ?></div>
                    </div>
                    <div class="rounded-xl bg-white border border-gray-200 p-4">
                        <div class="text-[12px] text-gray-500">Paid</div>
                        <div class="text-[20px] font-semibold text-[#313131]"><?php echo money($tot['paid']); ?></div>
                    </div>
                    <div class="rounded-xl bg-white border border-gray-200 p-4">
                        <div class="text-[12px] text-gray-500">Flushed (capped)</div>
                        <div class="text-[20px] font-semibold text-[#b91c1c]"><?php echo money($tot['flushed']); ?></div>
                    </div>
                </div>

                <div class="rounded-xl bg-white border border-gray-200 p-6 flex items-center justify-between">
                    <div>
                        <h3 class="text-[15px] font-semibold text-[#313131]">Plan Settings</h3>
                        <p class="text-[12px] text-gray-500 mt-1">Left %: <strong><?php echo number_format(income_setting_num($conn,'binary_left_percent',income_setting_num($conn,'binary_percent',2.5)),2); ?>%</strong> &middot; Right %: <strong><?php echo number_format(income_setting_num($conn,'binary_right_percent',income_setting_num($conn,'binary_percent',2.5)),2); ?>%</strong> &middot; Cap/step: <strong>&#8377;<?php echo number_format($cfgCap2,2); ?></strong> &middot; Max cap: <strong>&#8377;<?php echo number_format($cfgCapM,2); ?></strong></p>
                        <p class="text-[12px] text-gray-400 mt-1">Cycle: <?php echo htmlspecialchars($cycle['start']); ?> &rarr; <?php echo htmlspecialchars($cycle['end']); ?></p>
                    </div>
                    <a href="income-rules.php" class="rounded-lg bg-[#c79435] px-5 py-2 text-white text-sm hover:bg-[#b3822c] whitespace-nowrap">Edit in Income &amp; Business Rules</a>
                </div>

                <div class="rounded-xl bg-white border border-gray-200 overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-100 text-[15px] font-semibold text-[#313131]">Recent Jodis</div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="bg-gray-50 text-gray-500 text-[12px]">
                                <tr>
                                    <th class="text-left px-4 py-3">#</th>
                                    <th class="text-left px-4 py-3">Date</th>
                                    <th class="text-left px-4 py-3">Member</th>
                                    <th class="text-left px-4 py-3">Left</th>
                                    <th class="text-left px-4 py-3">Right</th>
                                    <th class="text-right px-4 py-3">Pair</th>
                                    <th class="text-right px-4 py-3">%</th>
                                    <th class="text-right px-4 py-3">Gross</th>
                                    <th class="text-right px-4 py-3">Paid</th>
                                    <th class="text-right px-4 py-3">Flushed</th>
                                    <th class="text-left px-4 py-3">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <?php if (empty($rows)): ?>
                                    <tr><td colspan="11" class="px-4 py-8 text-center text-gray-400">No Binary Income paid yet.</td></tr>
                                <?php else: foreach ($rows as $i => $r): ?>
                                    <tr>
                                        <td class="px-4 py-3 text-gray-400"><?php echo count($rows) - $i; ?></td>
                                        <td class="px-4 py-3"><?php echo htmlspecialchars($r['report_date'] ?: substr((string)$r['created_at'], 0, 10)); ?></td>
                                        <td class="px-4 py-3 font-medium"><?php echo htmlspecialchars($r['customer_id']); ?></td>
                                        <td class="px-4 py-3"><?php echo htmlspecialchars($r['left_member_id']); ?></td>
                                        <td class="px-4 py-3"><?php echo htmlspecialchars($r['right_member_id']); ?></td>
                                        <td class="px-4 py-3 text-right"><?php echo (int)$r['pair_no']; ?></td>
                                        <td class="px-4 py-3 text-right"><?php echo number_format((float)$r['percentage'], 2); ?></td>
                                        <td class="px-4 py-3 text-right"><?php echo money($r['gross_amount']); ?></td>
                                        <td class="px-4 py-3 text-right font-semibold"><?php echo money($r['paid_amount']); ?></td>
                                        <td class="px-4 py-3 text-right text-[#b91c1c]"><?php echo money($r['flushed_amount']); ?></td>
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
