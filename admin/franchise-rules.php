<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include __DIR__ . '/config/db.php';
include_once __DIR__ . '/includes/admin_access_control.php';
if (!isset($conn) || !($conn instanceof mysqli)) die('Database connection failed.');
if (function_exists('adminRequireAccessOrRedirect')) adminRequireAccessOrRedirect();
$conn->set_charset('utf8mb4');
require_once __DIR__ . '/../includes/franchise_finance.php';

$adminName = (string)($_SESSION['full_name'] ?? 'Admin');
$msg = ''; $err = '';

function frr_set(mysqli $c, string $k, string $v): void {
    $s = $c->prepare("INSERT INTO franchise_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
    $s->bind_param('ss', $k, $v); $s->execute(); $s->close();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = (string)($_POST['action'] ?? '');
    if ($act === 'save_rates') {
        frr_set($conn, 'sponsor_income_rate',  number_format((float)($_POST['sponsor_income_rate'] ?? 5), 2, '.', ''));
        frr_set($conn, 'turnover_income_rate', number_format((float)($_POST['turnover_income_rate'] ?? 0.5), 2, '.', ''));
        frr_set($conn, 'cashback_total_months', (string)max(1, (int)($_POST['cashback_total_months'] ?? 36)));
        frr_set($conn, 'commission_on_sale_enabled', isset($_POST['commission_on_sale_enabled']) ? '1' : '0');
        $msg = 'Rates saved.';
    } elseif ($act === 'save_model') {
        $mid = (int)($_POST['model_id'] ?? 0);
        $mno = trim((string)($_POST['model_no'] ?? ''));
        $nm  = trim((string)($_POST['model_name'] ?? ''));
        $inv = round((float)($_POST['investment_amount'] ?? 0), 2);
        $sc  = (int)($_POST['scooters'] ?? 0);
        $cb  = round((float)($_POST['cashback_percent'] ?? 0), 2);
        $pm  = max(1, (int)($_POST['period_months'] ?? 36));
        $ia  = isset($_POST['is_active']) ? 1 : 0;
        if ($mno === '' || $nm === '') { $err = 'Model no. and name are required.'; }
        else {
            try {
                if ($mid > 0) {
                    $s = $conn->prepare("UPDATE franchise_investment_models SET model_no=?, model_name=?, investment_amount=?, scooters=?, cashback_percent=?, period_months=?, is_active=? WHERE id=?");
                    $s->bind_param('ssdidiii', $mno, $nm, $inv, $sc, $cb, $pm, $ia, $mid);
                } else {
                    $s = $conn->prepare("INSERT INTO franchise_investment_models (model_no, model_name, investment_amount, scooters, cashback_percent, period_months, is_active) VALUES (?,?,?,?,?,?,?)");
                    $s->bind_param('ssdidii', $mno, $nm, $inv, $sc, $cb, $pm, $ia);
                }
                $s->execute(); $s->close();
                $msg = 'Model saved.';
            } catch (\Throwable $e) { $err = strpos($e->getMessage(), 'uniq_model_no') !== false ? 'Model no. already exists.' : $e->getMessage(); }
        }
    } elseif ($act === 'del_model') {
        $mid = (int)($_POST['model_id'] ?? 0);
        $used = (int)($conn->query("SELECT COUNT(*) c FROM franchies_holder WHERE investment_model_id = " . $mid)->fetch_assoc()['c'] ?? 0);
        if ($used > 0) { $err = "Model is assigned to {$used} franchise(s); unassign first."; }
        else { $conn->query("DELETE FROM franchise_investment_models WHERE id = " . $mid); $msg = 'Model deleted.'; }
    } elseif ($act === 'assign') {
        $fh = (int)($_POST['franchise_holder_id'] ?? 0);
        $mid = (int)($_POST['assign_model_id'] ?? 0);
        $sd  = trim((string)($_POST['start_date'] ?? ''));
        $sponsorCode = trim((string)($_POST['sponsor_franchise_code'] ?? ''));
        $row = $conn->query("SELECT id, franchise_code, franchies_id FROM franchies_holder WHERE id = " . $fh . " LIMIT 1")->fetch_assoc();
        if (!$row) { $err = 'Franchise not found.'; }
        else {
            $fcode = (string)($row['franchise_code'] ?: $row['franchies_id']);
            $sdSql = ($sd !== '' && $sd !== '0000-00-00') ? $sd : null;
            $sponsorSql = ($sponsorCode !== '' && strcasecmp($sponsorCode, $fcode) !== 0) ? $sponsorCode : null;
            $s = $conn->prepare("UPDATE franchies_holder SET investment_model_id = ?, investment_start_date = ?, sponsor_franchise_code = ? WHERE id = ?");
            $midN = $mid > 0 ? $mid : null;
            $s->bind_param('issi', $midN, $sdSql, $sponsorSql, $fh);
            $s->execute(); $s->close();

            $parts = [];
            if ($mid > 0 && $sdSql !== null) {
                $g = frf_cashback_generate($conn, $fh, $fcode, $mid, $sdSql);
                $parts[] = $g['ok'] ? $g['message'] : ('Schedule: ' . $g['message']);
                // 5% Sponsor Income — once, on the model investment, to the sponsor franchise
                $sp = frf_credit_franchise_sponsor_income($conn, $fh, $fcode, $mid, (string)$sponsorSql);
                $parts[] = $sp['message'];
            } elseif ($mid > 0) {
                $parts[] = 'Model assigned (set a start date to build the cashback schedule and pay Sponsor Income).';
            } else {
                $parts[] = 'Model unassigned.';
            }
            $msg = 'Saved. ' . implode(' ', $parts);
        }
    } elseif ($act === 'run_cashback') {
        $r = frf_cashback_run_due($conn, 0, $adminName);
        $msg = $r['message'] ?? 'Done.';
    }
}

$cur = [];
$r = $conn->query("SELECT setting_key, setting_value FROM franchise_settings");
while ($r && ($x = $r->fetch_assoc())) $cur[$x['setting_key']] = $x['setting_value'];

$models = [];
$r = $conn->query("SELECT * FROM franchise_investment_models ORDER BY model_no ASC");
while ($r && ($x = $r->fetch_assoc())) $models[] = $x;

$holders = [];
$r = $conn->query("SELECT h.id, h.franchies_id, h.franchise_code, h.name, h.state, h.city, h.investment_model_id, h.investment_start_date, h.sponsor_franchise_code, m.model_name
                   FROM franchies_holder h LEFT JOIN franchise_investment_models m ON m.id = h.investment_model_id
                   ORDER BY h.id ASC");
while ($r && ($x = $r->fetch_assoc())) $holders[] = $x;

/* franchise sponsor income already credited (commission_ledger, keyed by sponsored holder id) */
$sponsorPaid = [];
$r = $conn->query("SELECT sale_id, member_order_id, receiver_id, commission_amount
                   FROM commission_ledger WHERE from_sale_type = 'FRANCHISE_INVESTMENT'");
while ($r && ($x = $r->fetch_assoc())) $sponsorPaid[(int)$x['sale_id']] = $x;

$cbRows = [];
$r = $conn->query("SELECT s.*, h.name AS fname FROM franchise_cashback_schedule s LEFT JOIN franchies_holder h ON h.id = s.franchise_id ORDER BY s.franchise_id, s.month_no LIMIT 200");
while ($r && ($x = $r->fetch_assoc())) $cbRows[] = $x;

$editModel = null;
if (($eid = (int)($_GET['edit'] ?? 0)) > 0) $editModel = frf_model($conn, $eid);
function frr_m($n) { return '₹' . number_format((float)$n, 2); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Franchise Rules &amp; Models</title>
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
                    <i data-lucide="sliders-horizontal" class="w-5 h-5 text-[#c79435]"></i>
                </div>
                <h2 class="text-[24px] font-normal text-[#313131] tracking-wide" style="font-family: Georgia, serif;">FRANCHISE RULES &amp; MODELS</h2>
                <div class="h-[1px] bg-[#c9963b] flex-1"></div>
            </div>

            <div class="max-w-[1180px] mx-auto space-y-8">

                <!-- RATES -->
                <form method="post" class="rounded-xl bg-white border border-gray-200 p-6">
                    <input type="hidden" name="action" value="save_rates">
                    <h3 class="text-[15px] font-semibold text-[#313131] mb-1">Franchise Income Rates &amp; Cashback</h3>
                    <p class="text-[11px] text-gray-500 mb-4"><strong>Sponsor Income</strong> — paid once to a franchise's sponsor franchise on its model investment. <strong>Turnover Income</strong> — earned by the company on showroom sale turnover.</p>
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-5 text-sm">
                        <label class="block"><span class="text-[12px] text-gray-600">Sponsor Income % (of model investment)</span>
                            <input name="sponsor_income_rate" type="number" step="0.01" value="<?php echo htmlspecialchars($cur['sponsor_income_rate'] ?? '5.00'); ?>" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2"></label>
                        <label class="block"><span class="text-[12px] text-gray-600">Turnover Income % (company, on sales)</span>
                            <input name="turnover_income_rate" type="number" step="0.01" value="<?php echo htmlspecialchars($cur['turnover_income_rate'] ?? '0.50'); ?>" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2"></label>
                        <label class="block"><span class="text-[12px] text-gray-600">Cashback total months</span>
                            <input name="cashback_total_months" type="number" value="<?php echo htmlspecialchars($cur['cashback_total_months'] ?? '36'); ?>" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2"></label>
                        <label class="flex items-center gap-2 mt-6"><input type="checkbox" name="commission_on_sale_enabled" value="1" <?php echo (($cur['commission_on_sale_enabled'] ?? '1') === '1') ? 'checked' : ''; ?>> Credit turnover income on franchise sale</label>
                    </div>
                    <button class="mt-4 rounded-lg bg-[#c79435] px-6 py-2 text-white text-sm">Save Rates</button>
                </form>

                <!-- MODELS -->
                <div class="rounded-xl bg-white border border-gray-200 overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-100 text-[15px] font-semibold text-[#313131]">Investment Models</div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="bg-gray-50 text-gray-500 text-[12px]"><tr>
                                <th class="text-left px-4 py-2">No.</th><th class="text-left px-4 py-2">Name</th>
                                <th class="text-right px-4 py-2">Investment</th><th class="text-right px-4 py-2">Scooters</th>
                                <th class="text-right px-4 py-2">Cashback %</th><th class="text-right px-4 py-2">Months</th>
                                <th class="text-left px-4 py-2">Active</th><th class="px-4 py-2"></th>
                            </tr></thead>
                            <tbody class="divide-y divide-gray-100">
                                <?php if (empty($models)): ?><tr><td colspan="8" class="px-4 py-6 text-center text-gray-400">No models.</td></tr><?php endif; ?>
                                <?php foreach ($models as $m): ?>
                                    <tr>
                                        <td class="px-4 py-2 font-medium"><?php echo htmlspecialchars($m['model_no']); ?></td>
                                        <td class="px-4 py-2"><?php echo htmlspecialchars($m['model_name']); ?></td>
                                        <td class="px-4 py-2 text-right"><?php echo frr_m($m['investment_amount']); ?></td>
                                        <td class="px-4 py-2 text-right"><?php echo (int)$m['scooters']; ?></td>
                                        <td class="px-4 py-2 text-right"><?php echo rtrim(rtrim(number_format((float)$m['cashback_percent'], 2), '0'), '.'); ?>%</td>
                                        <td class="px-4 py-2 text-right"><?php echo (int)$m['period_months']; ?></td>
                                        <td class="px-4 py-2"><?php echo (int)$m['is_active'] === 1 ? 'Yes' : 'No'; ?></td>
                                        <td class="px-4 py-2 text-right whitespace-nowrap">
                                            <a href="?edit=<?php echo (int)$m['id']; ?>" class="text-[12px] text-[#c79435] hover:underline">Edit</a>
                                            <form method="post" class="inline" onsubmit="return confirm('Delete this model?');">
                                                <input type="hidden" name="action" value="del_model"><input type="hidden" name="model_id" value="<?php echo (int)$m['id']; ?>">
                                                <button class="ml-2 text-[12px] text-red-500 hover:underline">Delete</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <form method="post" class="px-6 py-4 border-t border-gray-100 grid grid-cols-1 md:grid-cols-4 lg:grid-cols-7 gap-3 text-sm items-end">
                        <input type="hidden" name="action" value="save_model">
                        <input type="hidden" name="model_id" value="<?php echo (int)($editModel['id'] ?? 0); ?>">
                        <label class="block"><span class="text-[11px] text-gray-500">Model No.</span><input name="model_no" required value="<?php echo htmlspecialchars($editModel['model_no'] ?? ''); ?>" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2"></label>
                        <label class="block lg:col-span-2"><span class="text-[11px] text-gray-500">Name</span><input name="model_name" required value="<?php echo htmlspecialchars($editModel['model_name'] ?? ''); ?>" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2"></label>
                        <label class="block"><span class="text-[11px] text-gray-500">Investment ₹</span><input name="investment_amount" type="number" step="0.01" value="<?php echo htmlspecialchars($editModel['investment_amount'] ?? '0'); ?>" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2"></label>
                        <label class="block"><span class="text-[11px] text-gray-500">Scooters</span><input name="scooters" type="number" value="<?php echo htmlspecialchars($editModel['scooters'] ?? '0'); ?>" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2"></label>
                        <label class="block"><span class="text-[11px] text-gray-500">Cashback %</span><input name="cashback_percent" type="number" step="0.01" value="<?php echo htmlspecialchars($editModel['cashback_percent'] ?? '0'); ?>" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2"></label>
                        <label class="block"><span class="text-[11px] text-gray-500">Months</span><input name="period_months" type="number" value="<?php echo htmlspecialchars($editModel['period_months'] ?? '36'); ?>" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2"></label>
                        <label class="flex items-center gap-2"><input type="checkbox" name="is_active" value="1" <?php echo (!$editModel || (int)$editModel['is_active'] === 1) ? 'checked' : ''; ?>> Active</label>
                        <div class="lg:col-span-7"><button class="rounded-lg bg-[#17457a] text-white px-6 py-2 text-sm"><?php echo $editModel ? 'Update Model' : 'Add Model'; ?></button>
                            <?php if ($editModel): ?><a href="franchise-rules.php" class="ml-2 text-[12px] text-gray-500">cancel</a><?php endif; ?></div>
                    </form>
                </div>

                <!-- ASSIGN -->
                <div class="rounded-xl bg-white border border-gray-200 overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                        <span class="text-[15px] font-semibold text-[#313131]">Franchise → Investment Model &amp; Sponsor</span>
                        <form method="post"><input type="hidden" name="action" value="run_cashback">
                            <button class="rounded-lg border border-[#c79435] text-[#a9781f] px-4 py-1.5 text-[12px] hover:bg-[#fdf6ea]">Credit due cashback now</button></form>
                    </div>
                    <p class="px-6 pt-3 text-[11px] text-gray-500">Assigning a model with a start date builds the cashback schedule and pays the sponsor franchise a one-time <strong>5% Sponsor Income</strong> on the model investment (into Commission Report, status Pending). The 0.5% Turnover Income is earned by the company on showroom sales, not here.</p>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="bg-gray-50 text-gray-500 text-[12px]"><tr>
                                <th class="text-left px-4 py-2">Franchise</th><th class="text-left px-4 py-2">Code</th>
                                <th class="text-left px-4 py-2">State / City</th><th class="text-left px-4 py-2">Current Model</th>
                                <th class="text-left px-4 py-2">Sponsor Income</th>
                                <th class="text-left px-4 py-2">Assign model / start / sponsor</th>
                            </tr></thead>
                            <tbody class="divide-y divide-gray-100">
                                <?php foreach ($holders as $h):
                                    $hcode = (string)($h['franchise_code'] ?: $h['franchies_id']);
                                    $paid  = $sponsorPaid[(int)$h['id']] ?? null; ?>
                                    <tr>
                                        <td class="px-4 py-2 font-medium"><?php echo htmlspecialchars($h['name'] ?: '—'); ?></td>
                                        <td class="px-4 py-2"><?php echo htmlspecialchars($hcode); ?></td>
                                        <td class="px-4 py-2 text-gray-500"><?php echo htmlspecialchars(trim(($h['state'] ?: '—') . ' / ' . ($h['city'] ?: '—'))); ?></td>
                                        <td class="px-4 py-2"><?php echo htmlspecialchars($h['model_name'] ?: '—'); ?><?php if ($h['investment_start_date']): ?> <span class="text-[11px] text-gray-400">(from <?php echo htmlspecialchars($h['investment_start_date']); ?>)</span><?php endif; ?></td>
                                        <td class="px-4 py-2 text-[12px]">
                                            <?php if ($paid): ?>
                                                <span class="text-green-700"><?php echo frr_m($paid['commission_amount']); ?> → <?php echo htmlspecialchars($paid['receiver_id']); ?></span>
                                            <?php elseif ($h['sponsor_franchise_code']): ?>
                                                <span class="text-gray-400">sponsor <?php echo htmlspecialchars($h['sponsor_franchise_code']); ?>, not yet paid</span>
                                            <?php else: ?><span class="text-gray-300">no sponsor</span><?php endif; ?>
                                        </td>
                                        <td class="px-4 py-2">
                                            <form method="post" class="flex flex-wrap items-center gap-2">
                                                <input type="hidden" name="action" value="assign">
                                                <input type="hidden" name="franchise_holder_id" value="<?php echo (int)$h['id']; ?>">
                                                <select name="assign_model_id" class="border border-gray-300 rounded px-2 py-1 text-[12px]">
                                                    <option value="0">— no model —</option>
                                                    <?php foreach ($models as $m): ?>
                                                        <option value="<?php echo (int)$m['id']; ?>" <?php echo (int)$h['investment_model_id'] === (int)$m['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($m['model_name']); ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <input type="date" name="start_date" value="<?php echo htmlspecialchars($h['investment_start_date'] ?: ''); ?>" class="border border-gray-300 rounded px-2 py-1 text-[12px]">
                                                <select name="sponsor_franchise_code" class="border border-gray-300 rounded px-2 py-1 text-[12px]">
                                                    <option value="">— sponsor franchise —</option>
                                                    <?php foreach ($holders as $sp):
                                                        $spCode = (string)($sp['franchise_code'] ?: $sp['franchies_id']);
                                                        if ((int)$sp['id'] === (int)$h['id']) continue; ?>
                                                        <option value="<?php echo htmlspecialchars($spCode); ?>" <?php echo strcasecmp((string)$h['sponsor_franchise_code'], $spCode) === 0 ? 'selected' : ''; ?>><?php echo htmlspecialchars(($sp['name'] ?: $spCode) . ' (' . $spCode . ')'); ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <button class="bg-[#17457a] text-white rounded px-3 py-1 text-[12px]">Save</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (empty($holders)): ?><tr><td colspan="6" class="px-4 py-6 text-center text-gray-400">No franchises.</td></tr><?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- CASHBACK SCHEDULE -->
                <div class="rounded-xl bg-white border border-gray-200 overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-100 text-[15px] font-semibold text-[#313131]">Cashback Schedule</div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="bg-gray-50 text-gray-500 text-[12px]"><tr>
                                <th class="text-left px-4 py-2">Franchise</th><th class="text-right px-4 py-2">Month</th>
                                <th class="text-left px-4 py-2">Due</th><th class="text-right px-4 py-2">Amount</th><th class="text-left px-4 py-2">Status</th><th class="text-left px-4 py-2">Paid</th>
                            </tr></thead>
                            <tbody class="divide-y divide-gray-100">
                                <?php if (empty($cbRows)): ?><tr><td colspan="6" class="px-4 py-6 text-center text-gray-400">No cashback schedule yet. Assign a model with a start date.</td></tr><?php endif; ?>
                                <?php foreach ($cbRows as $c): ?>
                                    <tr>
                                        <td class="px-4 py-2"><?php echo htmlspecialchars($c['fname'] ?: $c['franchise_code']); ?></td>
                                        <td class="px-4 py-2 text-right"><?php echo (int)$c['month_no']; ?></td>
                                        <td class="px-4 py-2"><?php echo htmlspecialchars($c['due_date']); ?></td>
                                        <td class="px-4 py-2 text-right"><?php echo frr_m($c['amount']); ?></td>
                                        <td class="px-4 py-2"><span class="text-[11px] rounded-full px-2 py-0.5 <?php echo $c['status'] === 'Paid' ? 'bg-green-50 text-green-700' : 'bg-amber-50 text-amber-700'; ?>"><?php echo htmlspecialchars($c['status']); ?></span></td>
                                        <td class="px-4 py-2 text-gray-500"><?php echo htmlspecialchars(substr((string)($c['paid_at'] ?? ''), 0, 16) ?: '—'); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </main>
    </div>
</div>
<script>if (typeof lucide !== 'undefined') lucide.createIcons();</script>
</body>
</html>
