<?php
session_start();
include __DIR__ . '/config/db.php';
if (!isset($conn) || !($conn instanceof mysqli)) {
    die("Database connection failed.");
}
$conn->set_charset("utf8mb4");
require_once __DIR__ . '/../includes/income_common.php';

// monthly bonus auto-distribution (runs once on/after the configured day)
if (function_exists('income_maybe_run_monthly')) {
    income_maybe_run_monthly($conn);
}

/* ---- storage (self-healing) ---- */
$conn->query("CREATE TABLE IF NOT EXISTS income_settings (
    setting_key VARCHAR(60) NOT NULL, setting_value VARCHAR(255) NOT NULL,
    description VARCHAR(255) DEFAULT NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (setting_key)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$conn->query("CREATE TABLE IF NOT EXISTS income_settings_history (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT, setting_key VARCHAR(60) NOT NULL,
    old_value VARCHAR(255) DEFAULT NULL, new_value VARCHAR(255) DEFAULT NULL,
    changed_by VARCHAR(120) DEFAULT NULL, changed_by_id VARCHAR(50) DEFAULT NULL,
    effective_date DATE DEFAULT NULL, reason VARCHAR(255) DEFAULT NULL,
    changed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

/* ---- the full editable field catalogue ---- */
$FIELDS = [
    'A. Direct Sponsor (Referral) Income' => [
        ['direct_sponsor_percent',      'Direct Sponsor %',              'number', '5.00'],
        ['direct_sponsor_base_default', 'Qualifying booking amount (₹)', 'number', '10000.00'],
        ['direct_sponsor_instant',      'Instant credit on booking',     'bool',   '1'],
        ['direct_sponsor_cap_exempt',   'Cap exempt',                    'bool',   '1'],
    ],
    'B. Binary Income' => [
        ['binary_left_percent',          'Left %',                        'number', '2.50'],
        ['binary_right_percent',         'Right %',                       'number', '2.50'],
        ['binary_base_default',          'Qualifying booking amount (₹)', 'number', '10000.00'],
        ['binary_qualify_left_directs',  'Directs required on LEFT',      'number', '1'],
        ['binary_qualify_right_directs', 'Directs required on RIGHT',     'number', '1'],
        ['binary_cap_exempt',            'Cap exempt',                    'bool',   '0'],
    ],
    'C. Binary Cap' => [
        ['cap_directs_step',   'Directs per cap step',        'number', '2'],
        ['cap_per_2_directs',  'Cap added per step (₹)',      'number', '10000.00'],
        ['cap_max',            'Maximum cap (₹)',             'number', '50000.00'],
        ['withdrawal_days',    'Withdrawal days (of month)',  'text',   '5,15,25'],
        ['cap_excess_rule',    'Excess rule',                 'select', 'flush', ['flush' => 'Flush (lost)']],
    ],
    'D. Level Income' => [
        ['level_income_base',    'Calculated from',                 'select', 'binary', ['binary' => "Downline members' Binary Income"]],
        ['level_income_count',   'Number of levels',                'number', '5'],
        ['level_1_percent',      'Level 1 %',                       'number', '20.00'],
        ['level_2_percent',      'Level 2 %',                       'number', '10.00'],
        ['level_3_percent',      'Level 3 %',                       'number', '8.00'],
        ['level_4_percent',      'Level 4 %',                       'number', '7.00'],
        ['level_5_percent',      'Level 5 %',                       'number', '5.00'],
        ['level_base_default',   'Qualifying business amount (₹)',  'number', '10000.00'],
        ['level_require_active',  'Upline must be Active',          'bool',   '1'],
        ['level_missing_rule',   'Missing/inactive level',         'select', 'flush', ['flush' => 'Flush that level', 'compress' => 'Compress up']],
        ['level_cap_exempt',     'Cap exempt',                     'bool',   '1'],
    ],
    'E. Auto Pool Income' => [
        ['auto_pool_enabled',              'Auto Pool active',                'bool',   '1'],
        ['auto_pool_levels',               'Number of pool levels',          'number', '10'],
        ['auto_pool_users_per_pool',       'Members per pool cycle',         'number', '20'],
        ['auto_pool_entry_amount',         'Pool 1 entry amount (₹)',        'number', '100.00'],
        ['auto_pool_income_split_percent', 'Income split % (rest re-pools)', 'number', '50.00'],
        ['auto_pool_fund_from',            'Entry funded by',                'select', 'company', ['company' => 'Company (not from member)', 'booking' => 'Joining booking', 'wallet' => 'Wallet balance', 'product_fund' => 'Product fund']],
        ['auto_pool_cap_exempt',           'Cap exempt',                     'bool',   '1'],
    ],
    'F. Performance Bonus' => [
        ['perf_bonus_enabled',        'Performance Bonus active',              'bool',   '1'],
        ['perf_bonus_per_direct',     'Pool contribution per qualifying direct (₹)', 'number', '100.00'],
        ['perf_bonus_pool_override',  'Fixed pool amount (0 = use collected)', 'number', '0.00'],
        ['perf_bonus_period',         'Ranking period',                       'select', 'monthly', ['monthly' => 'Monthly']],
        ['perf_bonus_winners',        'Number of winning ranks',              'number', '3'],
        ['perf_bonus_min_directs',    'Min directs in month to qualify',      'number', '1'],
        ['perf_bonus_rank_1_percent', '1st Rank %',                           'number', '40.00'],
        ['perf_bonus_rank_2_percent', '2nd Rank %',                           'number', '30.00'],
        ['perf_bonus_rank_3_percent', '3rd Rank %',                           'number', '30.00'],
        ['perf_bonus_cap_exempt',     'Cap exempt',                           'bool',   '1'],
    ],
    'G. Team Performance / Matching Bonus' => [
        ['team_perf_pool_fixed',     'Monthly pool amount (₹)', 'number', '0.00'],
        ['team_perf_rank_1_percent', '1st Rank %',              'number', '40.00'],
        ['team_perf_rank_2_percent', '2nd Rank %',              'number', '30.00'],
        ['team_perf_rank_3_percent', '3rd Rank %',              'number', '30.00'],
    ],
    'H. Wallet & Withdrawals' => [
        ['monthly_distribute_day',        'Payout day — Performance Bonus & Team Performance Bonus (monthly)', 'number', '1'],
        ['withdrawal_days',               'Withdrawal days (of month)',        'text',   '5,15,25'],
        ['withdrawal_enforce_days',       'Restrict withdrawals to those days', 'bool',  '1'],
        ['wallet_p2p_deduction_percent',  'P2P transfer deduction %',          'number', '15.00'],
        ['wallet_bank_deduction_percent', 'Wallet → Bank deduction %',         'number', '20.00'],
        ['withdrawal_max_1',              '1st withdrawal max (₹)',            'number', '500.00'],
        ['withdrawal_max_2',              '2nd withdrawal max (₹)',            'number', '500.00'],
        ['withdrawal_max_3plus',          '3rd+ withdrawal max (₹)',           'number', '2000.00'],
        ['wallet_p2p_enabled',            'P2P transfers enabled',             'bool',   '1'],
        ['withdrawal_enabled',            'Withdrawals enabled',               'bool',   '1'],
    ],
    'I. Business & Plan-wide' => [
        ['business_qualifying_amount',  'Joining amount (₹)',                          'number', '10000.00'],
        ['joining_split_enabled',       'Split the joining amount at activation',      'bool',   '1'],
        ['joining_own_fund_percent',    "Joining split — % to member's own product fund (rest → Income Distribution Pool)", 'number', '50.00'],
        ['product_fund_split_percent',  'Product-fund split on income payouts % (0 = off)', 'number', '0.00'],
    ],
];

/* seed any missing */
foreach ($FIELDS as $group) {
    foreach ($group as $f) {
        [$k, , , $def] = $f;
        $stmt = $conn->prepare("INSERT IGNORE INTO income_settings (setting_key, setting_value) VALUES (?, ?)");
        $stmt->bind_param('ss', $k, $def);
        $stmt->execute();
        $stmt->close();
    }
}

$successMessage = '';
$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'rules') {
    $adminName = (string)($_SESSION['full_name'] ?? 'Admin');
    $adminId   = (string)($_SESSION['member_id'] ?? $_SESSION['user_id'] ?? '');
    $reason    = trim((string)($_POST['reason'] ?? ''));
    $effective = trim((string)($_POST['effective_date'] ?? ''));

    // only touch bool fields the browser actually rendered (guards against partial POSTs)
    $submittedBoolKeys = array_filter(array_map('trim', explode(',', (string)($_POST['bool_keys'] ?? ''))));

    $changed = 0;
    foreach ($FIELDS as $group) {
        foreach ($group as $f) {
            [$k, , $type] = $f;
            if ($type === 'bool') {
                if (!in_array($k, $submittedBoolKeys, true)) {
                    continue;
                }
                $val = isset($_POST['f'][$k]) ? '1' : '0';
            } else {
                if (!isset($_POST['f'][$k])) {
                    continue;
                }
                $val = trim((string)$_POST['f'][$k]);
            }
            if ($type === 'number' && $val !== '' && is_numeric($val)) {
                $val = rtrim(rtrim(number_format((float)$val, 2, '.', ''), '0'), '.');
                if ($val === '' || $val === '-') $val = '0';
                if (strpos($val, '.') === false && !in_array($k, ['level_income_count','cap_directs_step','binary_qualify_left_directs','binary_qualify_right_directs','auto_pool_levels','auto_pool_users_per_pool','perf_bonus_winners','perf_bonus_min_directs','team_perf_winners','team_perf_leader_min_directs','monthly_distribute_day'], true)) {
                    $val = number_format((float)$val, 2, '.', '');
                }
            }
            if (income_set_setting_with_history($conn, $k, $val, $adminName, $adminId, $reason, $effective)) {
                $changed++;
            }
        }
    }
    $successMessage = $changed > 0 ? "$changed setting(s) updated and logged to history." : "No changes.";
}

/* current values */
$cur = [];
$res = $conn->query("SELECT setting_key, setting_value FROM income_settings");
while ($r = $res->fetch_assoc()) {
    $cur[$r['setting_key']] = $r['setting_value'];
}

/* history */
$history = [];
$res = $conn->query("SELECT setting_key, old_value, new_value, changed_by, effective_date, reason, changed_at
                     FROM income_settings_history ORDER BY id DESC LIMIT 60");
while ($r = $res->fetch_assoc()) $history[] = $r;

$levelTotal = 0.0;
for ($i = 1; $i <= (int)($cur['level_income_count'] ?? 5); $i++) {
    $levelTotal += (float)($cur["level_{$i}_percent"] ?? 0);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Income & Business Rules</title>
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

            <div class="flex items-center gap-4 mb-8">
                <div class="w-9 h-9 rounded-[8px] border border-[#ead9b5] bg-[#f8f1e7] flex items-center justify-center shrink-0">
                    <i data-lucide="sliders-horizontal" class="w-5 h-5 text-[#c79435]"></i>
                </div>
                <h2 class="text-[24px] font-normal text-[#313131] leading-none tracking-wide" style="font-family: Georgia, 'Times New Roman', serif;">INCOME &amp; BUSINESS RULES</h2>
                <div class="h-[1px] bg-[#c9963b] flex-1"></div>
            </div>

            <form method="post" class="max-w-[1180px] mx-auto space-y-6">
                <input type="hidden" name="form" value="rules">
                <?php
                $boolKeys = [];
                foreach ($FIELDS as $g) { foreach ($g as $ff) { if (($ff[2] ?? '') === 'bool') $boolKeys[] = $ff[0]; } }
                ?>
                <input type="hidden" name="bool_keys" value="<?php echo htmlspecialchars(implode(',', $boolKeys)); ?>">

                <?php foreach ($FIELDS as $groupName => $fieldset): ?>
                    <div class="rounded-xl bg-white border border-gray-200 p-6">
                        <h3 class="text-[15px] font-semibold text-[#313131] mb-4"><?php echo htmlspecialchars($groupName); ?>
                            <?php if ($groupName === 'D. Level Income'): ?>
                                <span class="ml-2 text-[12px] font-normal <?php echo abs($levelTotal - 50) < 0.01 ? 'text-green-600' : 'text-amber-600'; ?>">
                                    (levels total <?php echo number_format($levelTotal, 2); ?>%)
                                </span>
                            <?php endif; ?>
                        </h3>
                        <?php if ($groupName === 'H. Wallet & Withdrawals'): ?>
                            <p class="-mt-2 mb-4 text-[12px] text-gray-500">
                                <strong>Monthly payout day</strong> only controls when the two monthly pool bonuses settle:
                                <strong>Performance Bonus</strong> and <strong>Team Performance / Matching Bonus</strong>.
                                Direct Sponsor, Binary, Level and Auto Pool are paid in real time and are not affected.
                            </p>
                        <?php endif; ?>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-x-6 gap-y-4">
                            <?php foreach ($fieldset as $f):
                                [$k, $label, $type, $def] = $f;
                                $opts = $f[4] ?? [];
                                $v = $cur[$k] ?? $def; ?>
                                <label class="block">
                                    <span class="text-[12px] text-gray-600"><?php echo htmlspecialchars($label); ?></span>
                                    <?php if ($type === 'bool'): ?>
                                        <span class="mt-1 flex items-center gap-2">
                                            <input type="checkbox" name="f[<?php echo $k; ?>]" value="1" <?php echo ((string)$v === '1') ? 'checked' : ''; ?>
                                                   class="w-4 h-4">
                                            <span class="text-[12px] text-gray-500"><?php echo ((string)$v === '1') ? 'On' : 'Off'; ?></span>
                                        </span>
                                    <?php elseif ($type === 'select'): ?>
                                        <select name="f[<?php echo $k; ?>]" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                                            <?php foreach ($opts as $ov => $ol): ?>
                                                <option value="<?php echo htmlspecialchars($ov); ?>" <?php echo ((string)$v === (string)$ov) ? 'selected' : ''; ?>><?php echo htmlspecialchars($ol); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    <?php elseif ($type === 'number'): ?>
                                        <input type="number" step="0.01" name="f[<?php echo $k; ?>]"
                                               value="<?php echo htmlspecialchars((string)$v); ?>"
                                               class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                                    <?php else: ?>
                                        <input type="text" name="f[<?php echo $k; ?>]"
                                               value="<?php echo htmlspecialchars((string)$v); ?>"
                                               class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                                    <?php endif; ?>
                                    <span class="text-[10px] text-gray-400"><?php echo htmlspecialchars($k); ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>

                <div class="rounded-xl bg-white border border-gray-200 p-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <label class="block">
                            <span class="text-[12px] text-gray-600">Reason / remark for this change</span>
                            <input type="text" name="reason" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm" placeholder="optional">
                        </label>
                        <label class="block">
                            <span class="text-[12px] text-gray-600">Effective date</span>
                            <input type="date" name="effective_date" value="<?php echo date('Y-m-d'); ?>" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                        </label>
                    </div>
                    <p class="mt-3 text-[11px] text-gray-400">Completed income transactions keep the percentages/rules that were in force when they were generated. Changes here affect future calculations only.</p>
                    <button type="submit" class="mt-4 rounded-lg bg-[#c79435] px-8 py-2.5 text-white text-sm hover:bg-[#b3822c]">Save All Rules</button>
                </div>
            </form>

            <div class="max-w-[1180px] mx-auto mt-8 rounded-xl bg-white border border-gray-200 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-100 text-[15px] font-semibold text-[#313131]">Configuration History</div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 text-gray-500 text-[12px]">
                            <tr>
                                <th class="text-left px-4 py-3">When</th>
                                <th class="text-left px-4 py-3">Setting</th>
                                <th class="text-left px-4 py-3">Old</th>
                                <th class="text-left px-4 py-3">New</th>
                                <th class="text-left px-4 py-3">By</th>
                                <th class="text-left px-4 py-3">Effective</th>
                                <th class="text-left px-4 py-3">Reason</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <?php if (empty($history)): ?>
                                <tr><td colspan="7" class="px-4 py-8 text-center text-gray-400">No changes recorded yet.</td></tr>
                            <?php else: foreach ($history as $h): ?>
                                <tr>
                                    <td class="px-4 py-2 whitespace-nowrap text-gray-500"><?php echo htmlspecialchars((string)$h['changed_at']); ?></td>
                                    <td class="px-4 py-2 font-mono text-[12px]"><?php echo htmlspecialchars($h['setting_key']); ?></td>
                                    <td class="px-4 py-2 text-gray-500"><?php echo htmlspecialchars((string)$h['old_value']); ?></td>
                                    <td class="px-4 py-2 font-semibold"><?php echo htmlspecialchars((string)$h['new_value']); ?></td>
                                    <td class="px-4 py-2"><?php echo htmlspecialchars((string)$h['changed_by']); ?></td>
                                    <td class="px-4 py-2 text-gray-500"><?php echo htmlspecialchars((string)$h['effective_date']); ?></td>
                                    <td class="px-4 py-2 text-gray-500"><?php echo htmlspecialchars((string)$h['reason']); ?></td>
                                </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </main>
    </div>
</div>
<script>lucide.createIcons();</script>
</body>
</html>
