<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../includes/franchise_finance.php';
if (!isset($conn) || !($conn instanceof mysqli)) die('Database connection failed.');

$fid = (int)$franchiseId;                       // authenticated franchise PK — never from URL
$sum = frf_cashback_summary($conn, $fid);

$model = null;
if (!empty($franchise['investment_model_id'])) {
    $model = frf_model($conn, (int)$franchise['investment_model_id']);
}
$startDate = $franchise['investment_start_date'] ?? null;

/* schedule (scoped) */
$sched = [];
$s = $conn->prepare("SELECT * FROM franchise_cashback_schedule WHERE franchise_id = ? ORDER BY month_no ASC");
$s->bind_param('i', $fid); $s->execute();
$rs = $s->get_result();
while ($x = $rs->fetch_assoc()) $sched[] = $x;
$s->close();

/* received ledger (scoped) */
$ledger = [];
$s = $conn->prepare("SELECT * FROM franchise_cashback_ledger WHERE franchise_id = ? ORDER BY id DESC LIMIT 200");
$s->bind_param('i', $fid); $s->execute();
$rs = $s->get_result();
while ($x = $rs->fetch_assoc()) $ledger[] = $x;
$s->close();

$progressPct = $sum['months_total'] > 0 ? round($sum['months_done'] / $sum['months_total'] * 100) : 0;
function cb_m($n) { return '₹' . number_format((float)$n, 2); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Cashback</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <script src="assets/report-export.js"></script>
</head>
<body class="bg-[#f3f3f3] font-sans overflow-x-hidden">
<?php include __DIR__ . '/includes/sidebar.php'; ?>
<div class="ml-64 min-h-screen bg-[#f3f3f3]">
    <?php include __DIR__ . '/includes/navbar.php'; ?>
    <main class="px-4 sm:px-5 py-6">
        <div class="max-w-7xl mx-auto space-y-6">

            <div class="bg-[#17457a] rounded-xl px-5 sm:px-6 py-4 text-white shadow flex items-center justify-between flex-wrap gap-3">
                <div>
                    <div class="text-[15px] font-semibold"><?php echo e($franchiseName); ?> — Cashback</div>
                    <div class="text-[12px] text-white/70"><?php echo e($franchiseCode); ?>
                        <?php if ($model): ?> · <?php echo e($model['model_name']); ?><?php endif; ?>
                        <?php if ($startDate): ?> · start <?php echo e($startDate); ?><?php endif; ?>
                    </div>
                </div>
                <a href="dashboard.php" class="text-[12px] bg-white/10 hover:bg-white/20 rounded-lg px-3 py-1.5">← Dashboard</a>
            </div>

            <?php if (!$model): ?>
                <div class="rounded-xl border border-amber-200 bg-amber-50 px-5 py-4 text-[13px] text-amber-800">
                    No investment model is assigned to this franchise yet. Cashback begins once the admin assigns a model and an investment start date.
                </div>
            <?php endif; ?>

            <!-- summary cards -->
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                <?php
                $cards = [
                    ['Total Entitlement', cb_m($sum['scheduled']), 'wallet'],
                    ['Received', cb_m($sum['paid']), 'check-circle'],
                    ['Pending', cb_m($sum['pending']), 'clock'],
                    ['Due Now', cb_m($sum['due_now']), 'alarm-clock'],
                ];
                foreach ($cards as $c): ?>
                    <div class="bg-white rounded-xl border border-gray-200 p-4">
                        <div class="flex items-center gap-2 text-gray-400 text-[11px] uppercase tracking-wide">
                            <i data-lucide="<?php echo $c[2]; ?>" class="w-4 h-4"></i><?php echo $c[0]; ?>
                        </div>
                        <div class="mt-2 text-[19px] font-semibold text-[#17457a]"><?php echo $c[1]; ?></div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- progress -->
            <div class="bg-white rounded-xl border border-gray-200 p-5">
                <div class="flex items-center justify-between text-[13px] text-gray-600">
                    <span>Cashback progress</span>
                    <span><?php echo (int)$sum['months_done']; ?> / <?php echo (int)$sum['months_total']; ?> months</span>
                </div>
                <div class="mt-2 h-2.5 rounded-full bg-gray-100 overflow-hidden">
                    <div class="h-full bg-[#17457a]" style="width: <?php echo $progressPct; ?>%"></div>
                </div>
            </div>

            <!-- schedule -->
            <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
                <div class="px-5 py-3 border-b border-gray-100 flex items-center justify-between flex-wrap gap-2">
                    <span class="text-[14px] font-semibold text-[#313131]">Payment Schedule</span>
                    <div class="flex gap-2 no-print">
                        <button type="button" onclick="frExportTableToExcel('cashbackScheduleTable', 'Cashback_Schedule_<?php echo e($franchiseCode); ?>.xlsx', 'Schedule')"
                                class="bg-green-600 hover:bg-green-700 text-white px-3 py-1.5 rounded-lg text-[12px] font-semibold flex items-center gap-1.5 transition">
                            <i data-lucide="file-spreadsheet" class="w-4 h-4"></i> Excel
                        </button>
                        <button type="button" onclick="window.print()"
                                class="bg-gray-500 hover:bg-gray-600 text-white px-3 py-1.5 rounded-lg text-[12px] font-semibold flex items-center gap-1.5 transition">
                            <i data-lucide="printer" class="w-4 h-4"></i> Print / PDF
                        </button>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm" id="cashbackScheduleTable">
                        <thead class="bg-gray-50 text-gray-500 text-[12px]"><tr>
                            <th class="text-right px-4 py-2">Month</th><th class="text-left px-4 py-2">Due Date</th>
                            <th class="text-right px-4 py-2">Amount</th><th class="text-left px-4 py-2">Status</th><th class="text-left px-4 py-2">Paid On</th>
                        </tr></thead>
                        <tbody class="divide-y divide-gray-100">
                            <?php if (empty($sched)): ?><tr><td colspan="5" class="px-4 py-6 text-center text-gray-400">No cashback schedule yet.</td></tr><?php endif; ?>
                            <?php foreach ($sched as $r): ?>
                                <tr>
                                    <td class="px-4 py-2 text-right"><?php echo (int)$r['month_no']; ?></td>
                                    <td class="px-4 py-2"><?php echo e($r['due_date']); ?></td>
                                    <td class="px-4 py-2 text-right"><?php echo cb_m($r['amount']); ?></td>
                                    <td class="px-4 py-2">
                                        <span class="text-[11px] rounded-full px-2 py-0.5 <?php echo $r['status'] === 'Paid' ? 'bg-green-50 text-green-700' : 'bg-amber-50 text-amber-700'; ?>"><?php echo e($r['status']); ?></span>
                                    </td>
                                    <td class="px-4 py-2 text-gray-500"><?php echo e(substr((string)($r['paid_at'] ?? ''), 0, 16) ?: '—'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- received -->
            <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
                <div class="px-5 py-3 border-b border-gray-100 flex items-center justify-between flex-wrap gap-2">
                    <span class="text-[14px] font-semibold text-[#313131]">Received Cashback</span>
                    <div class="flex gap-2 no-print">
                        <button type="button" onclick="frExportTableToExcel('cashbackReceivedTable', 'Cashback_Received_<?php echo e($franchiseCode); ?>.xlsx', 'Received')"
                                class="bg-green-600 hover:bg-green-700 text-white px-3 py-1.5 rounded-lg text-[12px] font-semibold flex items-center gap-1.5 transition">
                            <i data-lucide="file-spreadsheet" class="w-4 h-4"></i> Excel
                        </button>
                        <button type="button" onclick="window.print()"
                                class="bg-gray-500 hover:bg-gray-600 text-white px-3 py-1.5 rounded-lg text-[12px] font-semibold flex items-center gap-1.5 transition">
                            <i data-lucide="printer" class="w-4 h-4"></i> Print / PDF
                        </button>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm" id="cashbackReceivedTable">
                        <thead class="bg-gray-50 text-gray-500 text-[12px]"><tr>
                            <th class="text-left px-4 py-2">Date</th><th class="text-right px-4 py-2">Month</th>
                            <th class="text-right px-4 py-2">Amount</th><th class="text-left px-4 py-2">Note</th>
                        </tr></thead>
                        <tbody class="divide-y divide-gray-100">
                            <?php if (empty($ledger)): ?><tr><td colspan="4" class="px-4 py-6 text-center text-gray-400">Nothing received yet.</td></tr><?php endif; ?>
                            <?php foreach ($ledger as $r): ?>
                                <tr>
                                    <td class="px-4 py-2 text-gray-500"><?php echo e(substr((string)$r['created_at'], 0, 16)); ?></td>
                                    <td class="px-4 py-2 text-right"><?php echo (int)$r['month_no']; ?></td>
                                    <td class="px-4 py-2 text-right text-green-700"><?php echo cb_m($r['amount']); ?></td>
                                    <td class="px-4 py-2 text-gray-600"><?php echo e($r['note']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <p class="text-[11px] text-gray-400">Cashback is credited by the company on each month's due date. Amounts and schedule are set by the admin from the assigned investment model.</p>

        </div>
    </main>
</div>
<script>if (typeof lucide !== 'undefined') lucide.createIcons();</script>
</body>
</html>
