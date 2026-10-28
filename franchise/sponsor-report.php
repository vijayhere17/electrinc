<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../includes/franchise_finance.php';
if (!isset($conn) || !($conn instanceof mysqli)) die('Database connection failed.');

$rows = frf_sponsored_franchises($conn, (string)$franchiseCode);   // authenticated franchise code — never from URL

$sponsoredCount = count($rows);
$totalCredited  = 0.0;
$totalPending   = 0.0;
$totalEarned    = 0.0;
foreach ($rows as $r) {
    if ($r['commission_amount'] === null) continue;
    $totalEarned += (float)$r['commission_amount'];
    if (strcasecmp((string)$r['payout_status'], 'Paid') === 0) {
        $totalCredited += (float)$r['commission_amount'];
    } else {
        $totalPending += (float)$r['commission_amount'];
    }
}

function sr_money($n) { return '₹' . number_format((float)$n, 2); }
function sr_date($d) { return ($d && $d !== '0000-00-00') ? date('d M Y', strtotime((string)$d)) : '—'; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Sponsor Franchise Report</title>
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
                    <h1 class="text-[18px] sm:text-[20px] font-semibold">Sponsor Franchise Report</h1>
                    <p class="text-[12px] text-white/80 mt-1"><?php echo e($franchiseName); ?> · <?php echo e($franchiseCode); ?> · franchises you directed, and the Sponsor Income earned</p>
                </div>
                <a href="dashboard.php" class="bg-white/15 hover:bg-white/25 rounded-lg px-3 py-2 text-[12px]">← Dashboard</a>
            </div>

            <!-- summary tiles -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <?php
                $tiles = [
                    ['Franchises Sponsored', (string)$sponsoredCount, 'users'],
                    ['Total Sponsor Income', sr_money($totalEarned), 'badge-indian-rupee'],
                    ['Credited', sr_money($totalCredited), 'check-circle'],
                    ['Pending Payout', sr_money($totalPending), 'hourglass'],
                ];
                foreach ($tiles as $tl): ?>
                    <div class="bg-white border border-gray-200 rounded-xl p-4">
                        <div class="flex items-center gap-2 text-gray-400 text-[11px] uppercase tracking-wide">
                            <i data-lucide="<?php echo $tl[2]; ?>" class="w-4 h-4"></i><?php echo $tl[0]; ?>
                        </div>
                        <div class="mt-2 text-[18px] font-semibold text-[#17457a]"><?php echo $tl[1]; ?></div>
                    </div>
                <?php endforeach; ?>
            </div>

            <p class="text-[11px] text-gray-400 -mt-3">
                The 5% Sponsor Income is a <strong>one-time</strong> credit, paid when a franchise you sponsored takes up its investment model — it is not a recurring commission.
                A franchise becomes "sponsored by you" when the company assigns your franchise code as its sponsor while setting up its investment model.
            </p>

            <!-- table -->
            <div class="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden">
                <div class="px-5 py-3 border-b border-gray-100 flex items-center justify-between flex-wrap gap-2">
                    <div>
                        <h2 class="text-[14px] font-semibold text-[#173a67]">Franchises You've Sponsored</h2>
                        <span class="text-[12px] text-gray-400"><?php echo count($rows); ?> franchise<?php echo count($rows) === 1 ? '' : 's'; ?></span>
                    </div>
                    <div class="flex gap-2 no-print">
                        <button type="button" onclick="frExportTableToExcel('sponsorTable', 'Sponsor_Report_<?php echo e($franchiseCode); ?>.xlsx', 'Sponsor Report')"
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
                    <table class="w-full text-[13px]" id="sponsorTable">
                        <thead class="bg-gray-50 text-gray-500 text-[12px]">
                            <tr>
                                <th class="text-left px-5 py-2.5">Type</th>
                                <th class="text-left px-5 py-2.5">Franchise</th>
                                <th class="text-left px-5 py-2.5">Location</th>
                                <th class="text-left px-5 py-2.5">Investment Model</th>
                                <th class="text-right px-5 py-2.5">Investment</th>
                                <th class="text-left px-5 py-2.5">Start Date</th>
                                <th class="text-right px-5 py-2.5">Sponsor Income</th>
                                <th class="text-left px-5 py-2.5">Payout Status</th>
                                <th class="text-left px-5 py-2.5">Credited On</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <?php if (empty($rows)): ?>
                                <tr><td colspan="9" class="px-5 py-8 text-center text-gray-400">You haven't sponsored any franchise yet. This report fills in once the company assigns your franchise code as a sponsor for another franchise's investment model.</td></tr>
                            <?php endif; ?>
                            <?php foreach ($rows as $r): ?>
                                <tr>
                                    <td class="px-5 py-2.5">
                                        <span class="text-[11px] rounded-full px-2 py-0.5 <?php echo $r['type'] === 'State' ? 'bg-blue-50 text-blue-700' : 'bg-purple-50 text-purple-700'; ?>"><?php echo e($r['type']); ?></span>
                                    </td>
                                    <td class="px-5 py-2.5">
                                        <div class="font-medium text-gray-800"><?php echo e($r['name']); ?></div>
                                        <div class="text-[11px] text-gray-400"><?php echo e($r['code']); ?></div>
                                    </td>
                                    <td class="px-5 py-2.5 text-gray-600"><?php echo e(trim(($r['city'] ?: '') . ($r['state'] ? ', ' . $r['state'] : '')) ?: '—'); ?></td>
                                    <td class="px-5 py-2.5 text-gray-600"><?php echo e($r['model_name'] ?? '—'); ?></td>
                                    <td class="px-5 py-2.5 text-right text-gray-600"><?php echo $r['investment_amount'] !== null ? sr_money($r['investment_amount']) : '—'; ?></td>
                                    <td class="px-5 py-2.5 text-gray-500"><?php echo sr_date($r['investment_start_date']); ?></td>
                                    <td class="px-5 py-2.5 text-right font-semibold <?php echo $r['commission_amount'] !== null ? 'text-green-700' : 'text-gray-400'; ?>">
                                        <?php echo $r['commission_amount'] !== null ? sr_money($r['commission_amount']) . ($r['commission_percent'] !== null ? ' <span class="text-[11px] text-gray-400">(' . rtrim(rtrim(number_format($r['commission_percent'], 2), '0'), '.') . '%)</span>' : '') : '—'; ?>
                                    </td>
                                    <td class="px-5 py-2.5">
                                        <span class="text-[11px] rounded-full px-2 py-0.5 <?php echo strcasecmp((string)$r['payout_status'], 'Paid') === 0 ? 'bg-green-50 text-green-700' : (strcasecmp((string)$r['payout_status'], 'Pending') === 0 ? 'bg-amber-50 text-amber-700' : 'bg-gray-100 text-gray-500'); ?>"><?php echo e($r['payout_status']); ?></span>
                                    </td>
                                    <td class="px-5 py-2.5 text-gray-500"><?php echo $r['credited_at'] ? e(substr((string)$r['credited_at'], 0, 16)) : '—'; ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (!empty($rows)): ?>
                                <tr class="bg-[#eef4fb] border-t-2 border-[#17457a] font-bold">
                                    <td colspan="6" class="px-5 py-3 text-right text-gray-700 uppercase text-[11px] tracking-widest">Total Sponsor Income</td>
                                    <td class="px-5 py-3 text-right text-green-700"><?php echo sr_money($totalEarned); ?></td>
                                    <td colspan="2" class="px-5 py-3 text-[11px] text-gray-500">Credited <?php echo sr_money($totalCredited); ?> · Pending <?php echo sr_money($totalPending); ?></td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </main>
</div>
<script>if (typeof lucide !== 'undefined') lucide.createIcons();</script>
</body>
</html>
