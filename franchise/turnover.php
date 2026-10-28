<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/includes/inventory.php';   // pulls customers.php + franchise_finance.php
if (!isset($conn) || !($conn instanceof mysqli)) die('Database connection failed.');

$fid = (int)$franchiseId;                           // authenticated franchise PK — never from URL

$months = max(1, min(36, (int)($_GET['months'] ?? 12)));
$from   = trim((string)($_GET['from'] ?? ''));
$to     = trim((string)($_GET['to'] ?? ''));
$range  = ($from !== '' || $to !== '') ? ['from' => $from, 'to' => $to] : [];

$turnoverRate = function_exists('frf_setting_num') ? frf_setting_num($conn, 'turnover_income_rate', 0.5) : 0.5;

$sum        = frc_sales_summary($conn, $fid, $range);
$byMonth    = frc_turnover_by_month($conn, $fid, $months);
$byProduct  = frc_turnover_by_product($conn, $fid, $range, 15);

$companyCut = round($sum['turnover'] * $turnoverRate / 100, 2);
$avgSale    = $sum['sales'] > 0 ? $sum['turnover'] / $sum['sales'] : 0.0;
$peakMonth  = 0.0;
foreach ($byMonth as $m) $peakMonth = max($peakMonth, (float)$m['turnover']);

function tv_money($n) { return '₹' . number_format((float)$n, 2); }
function tv_mlabel($ym) { $t = strtotime($ym . '-01'); return $t ? date('M Y', $t) : $ym; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Turnover Report</title>
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
                    <h1 class="text-[18px] sm:text-[20px] font-semibold">Turnover Report</h1>
                    <p class="text-[12px] text-white/80 mt-1"><?php echo e($franchiseName); ?> · <?php echo e($franchiseCode); ?> · showroom business turnover</p>
                </div>
                <a href="sales.php" class="bg-white/15 hover:bg-white/25 rounded-lg px-3 py-2 text-[12px]">Sales Records</a>
            </div>

            <form method="get" class="bg-white border border-gray-200 rounded-xl p-4 shadow-sm flex flex-wrap gap-3 text-[13px] items-end">
                <label class="block"><span class="text-gray-500 text-[11px]">Range from</span>
                    <input type="date" name="from" value="<?php echo e($from); ?>" class="mt-1 border border-gray-300 rounded-lg px-3 py-2"></label>
                <label class="block"><span class="text-gray-500 text-[11px]">to</span>
                    <input type="date" name="to" value="<?php echo e($to); ?>" class="mt-1 border border-gray-300 rounded-lg px-3 py-2"></label>
                <label class="block"><span class="text-gray-500 text-[11px]">Monthly chart — months</span>
                    <input type="number" name="months" min="1" max="36" value="<?php echo (int)$months; ?>" class="mt-1 w-24 border border-gray-300 rounded-lg px-3 py-2"></label>
                <button class="bg-[#17457a] text-white rounded-lg px-4 py-2 text-[13px]">Apply</button>
                <a href="turnover.php" class="border border-gray-300 bg-white rounded-lg px-4 py-2 text-[13px]">Reset</a>
                <span class="text-[11px] text-gray-400 ml-auto self-center">Range filter applies to totals &amp; product breakdown. Leave blank for all-time.</span>
            </form>

            <!-- headline tiles -->
            <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
                <?php
                $tiles = [
                    ['Total Turnover', tv_money($sum['turnover']), 'trending-up'],
                    ['Sales', (string)$sum['sales'], 'receipt'],
                    ['Units Sold', (string)$sum['units'], 'package-check'],
                    ['Avg / Sale', tv_money($avgSale), 'calculator'],
                    ['Company Income @ ' . rtrim(rtrim(number_format($turnoverRate, 2), '0'), '.') . '%', tv_money($companyCut), 'building-2'],
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
            <p class="text-[11px] text-gray-400 -mt-3">The <strong>Company Income</strong> figure is what the company earns on your turnover (<?php echo rtrim(rtrim(number_format($turnoverRate, 2), '0'), '.'); ?>%) — it is not paid to the franchise.</p>

            <!-- monthly breakdown -->
            <div class="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden">
                <div class="px-5 py-3 border-b border-gray-100 flex items-center justify-between flex-wrap gap-2">
                    <span class="text-[14px] font-semibold text-[#173a67]">Monthly Turnover — last <?php echo (int)$months; ?> months</span>
                    <div class="flex gap-2 no-print">
                        <button type="button" onclick="frExportTableToExcel('monthlyTurnoverTable', 'Turnover_Monthly_<?php echo e($franchiseCode); ?>.xlsx', 'Monthly Turnover')"
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
                    <table class="w-full text-[13px]" id="monthlyTurnoverTable">
                        <thead class="bg-gray-50 text-gray-500 text-[12px]"><tr>
                            <th class="text-left px-5 py-2.5">Month</th>
                            <th class="text-right px-5 py-2.5">Sales</th>
                            <th class="text-right px-5 py-2.5">Units</th>
                            <th class="text-right px-5 py-2.5">Turnover</th>
                            <th class="text-left px-5 py-2.5 w-[40%] no-export">&nbsp;</th>
                        </tr></thead>
                        <tbody class="divide-y divide-gray-100">
                            <?php if (empty($byMonth)): ?>
                                <tr><td colspan="5" class="px-5 py-8 text-center text-gray-400">No sales recorded yet.</td></tr>
                            <?php endif; ?>
                            <?php foreach ($byMonth as $m):
                                $w = $peakMonth > 0 ? round((float)$m['turnover'] / $peakMonth * 100) : 0; ?>
                                <tr>
                                    <td class="px-5 py-2.5 font-medium text-gray-800"><?php echo e(tv_mlabel($m['ym'])); ?></td>
                                    <td class="px-5 py-2.5 text-right text-gray-600"><?php echo (int)$m['sales']; ?></td>
                                    <td class="px-5 py-2.5 text-right text-gray-600"><?php echo (int)$m['units']; ?></td>
                                    <td class="px-5 py-2.5 text-right font-semibold"><?php echo tv_money($m['turnover']); ?></td>
                                    <td class="px-5 py-2.5 no-export">
                                        <div class="h-2.5 rounded-full bg-gray-100 overflow-hidden"><div class="h-full bg-[#17457a]" style="width: <?php echo $w; ?>%"></div></div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- by product -->
            <div class="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden">
                <div class="px-5 py-3 border-b border-gray-100 flex items-center justify-between flex-wrap gap-2">
                    <span class="text-[14px] font-semibold text-[#173a67]">Turnover by Product<?php echo $range ? ' (selected range)' : ''; ?></span>
                    <div class="flex gap-2 no-print">
                        <button type="button" onclick="frExportTableToExcel('productTurnoverTable', 'Turnover_By_Product_<?php echo e($franchiseCode); ?>.xlsx', 'By Product')"
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
                    <table class="w-full text-[13px]" id="productTurnoverTable">
                        <thead class="bg-gray-50 text-gray-500 text-[12px]"><tr>
                            <th class="text-left px-5 py-2.5">Product / Model</th>
                            <th class="text-right px-5 py-2.5">Sales</th>
                            <th class="text-right px-5 py-2.5">Units</th>
                            <th class="text-right px-5 py-2.5">Turnover</th>
                        </tr></thead>
                        <tbody class="divide-y divide-gray-100">
                            <?php if (empty($byProduct)): ?>
                                <tr><td colspan="4" class="px-5 py-8 text-center text-gray-400">Nothing to show.</td></tr>
                            <?php endif; ?>
                            <?php foreach ($byProduct as $p): ?>
                                <tr>
                                    <td class="px-5 py-2.5 font-medium text-gray-800"><?php echo e($p['pname']); ?><?php if ($p['model']): ?> <span class="text-gray-400 text-[11px]">/ <?php echo e($p['model']); ?></span><?php endif; ?></td>
                                    <td class="px-5 py-2.5 text-right text-gray-600"><?php echo (int)$p['sales']; ?></td>
                                    <td class="px-5 py-2.5 text-right text-gray-600"><?php echo (int)$p['units']; ?></td>
                                    <td class="px-5 py-2.5 text-right font-semibold"><?php echo tv_money($p['turnover']); ?></td>
                                </tr>
                            <?php endforeach; ?>
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
