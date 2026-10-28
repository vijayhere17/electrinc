<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include __DIR__ . '/config/db.php';
include_once __DIR__ . '/includes/admin_access_control.php';
if (!isset($conn) || !($conn instanceof mysqli)) die('Database connection failed.');
if (function_exists('adminRequireAccessOrRedirect')) adminRequireAccessOrRedirect();
$conn->set_charset('utf8mb4');

function e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function fsr_money($n) { return '₹' . number_format((float)$n, 2); }
function tableExists(mysqli $c, string $t): bool {
    $t = $c->real_escape_string($t);
    $r = $c->query("SHOW TABLES LIKE '$t'");
    return $r instanceof mysqli_result && $r->num_rows > 0;
}

$hasData = tableExists($conn, 'fr_customer_purchases');

/* franchise dropdown — state franchises only for now (city/mobile franchise inventory not wired up yet) */
$franchises = [];
if (tableExists($conn, 'franchies_holder')) {
    $r = $conn->query("SELECT id, COALESCE(franchise_code, franchies_id) AS code, name FROM franchies_holder ORDER BY name ASC");
    while ($r && ($x = $r->fetch_assoc())) $franchises[(int)$x['id']] = $x;
}

$from     = trim((string)($_GET['from'] ?? date('Y-m-01')));
$to       = trim((string)($_GET['to'] ?? date('Y-m-d')));
$fid      = (int)($_GET['franchise_id'] ?? 0);
$payment  = trim((string)($_GET['payment'] ?? ''));
$q        = trim((string)($_GET['q'] ?? ''));

$rows = [];
$byFranchise = [];
$sum = ['sales' => 0, 'units' => 0, 'turnover' => 0.0, 'paid' => 0.0, 'pending_amt' => 0.0];

if ($hasData) {
    $where = "cp.sale_date BETWEEN ? AND ?";
    $params = [$from, $to];
    $types = 'ss';
    if ($fid > 0) { $where .= " AND cp.franchise_id = ?"; $params[] = $fid; $types .= 'i'; }
    if ($payment !== '') { $where .= " AND cp.payment_status = ?"; $params[] = $payment; $types .= 's'; }
    if ($q !== '') {
        $where .= " AND (cp.customer_name LIKE ? OR cp.product_name LIKE ? OR cp.invoice_no LIKE ? OR cp.sale_ref LIKE ?)";
        $like = "%$q%"; array_push($params, $like, $like, $like, $like); $types .= 'ssss';
    }

    $sql = "SELECT cp.*, COALESCE(fh.franchise_code, fh.franchies_id) AS f_code, fh.name AS f_name
            FROM fr_customer_purchases cp
            LEFT JOIN franchies_holder fh ON fh.id = cp.franchise_id
            WHERE $where
            ORDER BY cp.sale_date DESC, cp.id DESC
            LIMIT 1000";
    $st = $conn->prepare($sql);
    $st->bind_param($types, ...$params);
    $st->execute();
    $rs = $st->get_result();
    while ($row = $rs->fetch_assoc()) {
        $rows[] = $row;
        $sum['sales']++;
        $sum['units'] += (int)$row['quantity'];
        $sum['turnover'] += (float)$row['sale_amount'];
        $sum['paid'] += (float)$row['amount_paid'];
        if ($row['payment_status'] !== 'Paid') $sum['pending_amt'] += ((float)$row['sale_amount'] - (float)$row['amount_paid']);

        $fk = (int)$row['franchise_id'];
        if (!isset($byFranchise[$fk])) {
            $byFranchise[$fk] = ['name' => $row['f_name'] ?: ('Franchise #' . $fk), 'code' => $row['f_code'] ?: '-', 'sales' => 0, 'units' => 0, 'turnover' => 0.0];
        }
        $byFranchise[$fk]['sales']++;
        $byFranchise[$fk]['units'] += (int)$row['quantity'];
        $byFranchise[$fk]['turnover'] += (float)$row['sale_amount'];
    }
    $st->close();
    usort($byFranchise, fn($a, $b) => $b['turnover'] <=> $a['turnover']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Franchise Sales &amp; Turnover Report</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <script src="assets/report-export.js"></script>
    <style>@media print{ .no-print{ display:none !important; } .layout-sidebar{ display:none !important; } .ml-64{ margin-left:0 !important; } }</style>
</head>
<body class="m-0 bg-[#f3f4f6] font-sans">
<div class="ml-64 min-h-screen w-[calc(100%-16rem)]">
    <?php include __DIR__ . '/includes/sidebar.php'; ?>
    <div class="flex-1 min-w-0 flex flex-col">
        <?php include __DIR__ . '/includes/navbar.php'; ?>
        <main class="px-4 sm:px-8 py-5">

            <div class="flex items-center gap-4 mb-6">
                <div class="w-9 h-9 rounded-[8px] border border-[#ead9b5] bg-[#f8f1e7] flex items-center justify-center shrink-0">
                    <i data-lucide="trending-up" class="w-5 h-5 text-[#c79435]"></i>
                </div>
                <h2 class="text-[22px] sm:text-[24px] font-normal text-[#313131] tracking-wide" style="font-family: Georgia, serif;">FRANCHISE SALES &amp; TURNOVER REPORT</h2>
                <div class="h-[1px] bg-[#c9963b] flex-1 hidden sm:block"></div>
            </div>

            <?php if (!$hasData): ?>
                <div class="rounded-xl border border-amber-200 bg-amber-50 px-5 py-4 text-[13px] text-amber-800">
                    No franchise sales data yet — this report reads from <code>fr_customer_purchases</code>, populated once franchises start billing through their Billing POS.
                </div>
            <?php else: ?>

            <form method="get" class="no-print bg-white border border-gray-200 rounded-xl p-4 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-3 text-[13px] items-end mb-5">
                <label class="block"><span class="text-gray-500 text-[11px]">From</span>
                    <input type="date" name="from" value="<?php echo e($from); ?>" class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2"></label>
                <label class="block"><span class="text-gray-500 text-[11px]">To</span>
                    <input type="date" name="to" value="<?php echo e($to); ?>" class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2"></label>
                <label class="block lg:col-span-2"><span class="text-gray-500 text-[11px]">Franchise</span>
                    <select name="franchise_id" class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2 bg-white">
                        <option value="0">All franchises</option>
                        <?php foreach ($franchises as $fidOpt => $f): ?>
                            <option value="<?php echo (int)$fidOpt; ?>" <?php echo $fid === $fidOpt ? 'selected' : ''; ?>><?php echo e($f['name']); ?> (<?php echo e($f['code']); ?>)</option>
                        <?php endforeach; ?>
                    </select></label>
                <label class="block"><span class="text-gray-500 text-[11px]">Payment</span>
                    <select name="payment" class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2 bg-white">
                        <option value="">Any</option>
                        <?php foreach (['Paid', 'Pending', 'Partial'] as $ps): ?>
                            <option <?php echo $payment === $ps ? 'selected' : ''; ?>><?php echo $ps; ?></option>
                        <?php endforeach; ?>
                    </select></label>
                <div class="flex gap-2">
                    <button class="bg-[#17457a] text-white rounded-lg px-4 py-2 text-[13px] flex-1">Apply</button>
                    <a href="franchise-sales-report.php" class="border border-gray-300 bg-white rounded-lg px-4 py-2 text-[13px]">Reset</a>
                </div>
                <label class="block sm:col-span-2 lg:col-span-6"><span class="text-gray-500 text-[11px]">Search (customer / product / invoice)</span>
                    <input name="q" value="<?php echo e($q); ?>" placeholder="Search…" class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2"></label>
            </form>

            <div class="grid grid-cols-2 md:grid-cols-5 gap-3 sm:gap-4 mb-5">
                <?php
                $tiles = [
                    ['Sales', (string)$sum['sales'], 'receipt'],
                    ['Units Sold', (string)$sum['units'], 'package-check'],
                    ['Turnover', fsr_money($sum['turnover']), 'trending-up'],
                    ['Payment Received', fsr_money($sum['paid']), 'wallet'],
                    ['Payment Pending', fsr_money($sum['pending_amt']), 'hourglass'],
                ];
                foreach ($tiles as $tl): ?>
                    <div class="bg-white border border-gray-200 rounded-xl p-4">
                        <div class="flex items-center gap-2 text-gray-400 text-[11px] uppercase tracking-wide">
                            <i data-lucide="<?php echo $tl[2]; ?>" class="w-4 h-4"></i><?php echo $tl[0]; ?>
                        </div>
                        <div class="mt-2 text-[17px] sm:text-[18px] font-semibold text-[#17457a]"><?php echo $tl[1]; ?></div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- turnover by franchise -->
            <div class="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden mb-6">
                <div class="px-5 py-3 border-b border-gray-100 flex items-center justify-between flex-wrap gap-2">
                    <h3 class="text-[14px] font-semibold text-[#173a67]">Turnover by Franchise</h3>
                    <div class="flex gap-2 no-print">
                        <button type="button" onclick="frExportTableToExcel('byFranchiseTable', 'Turnover_By_Franchise.xlsx', 'By Franchise')" class="bg-green-600 hover:bg-green-700 text-white px-3 py-1.5 rounded-lg text-[12px] font-semibold flex items-center gap-1.5">
                            <i data-lucide="file-spreadsheet" class="w-4 h-4"></i> Excel
                        </button>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-[13px]" id="byFranchiseTable">
                        <thead class="bg-gray-50 text-gray-500 text-[12px]"><tr>
                            <th class="text-left px-5 py-2.5">Franchise</th><th class="text-left px-5 py-2.5">Code</th>
                            <th class="text-right px-5 py-2.5">Sales</th><th class="text-right px-5 py-2.5">Units</th><th class="text-right px-5 py-2.5">Turnover</th>
                        </tr></thead>
                        <tbody class="divide-y divide-gray-100">
                            <?php if (empty($byFranchise)): ?><tr><td colspan="5" class="px-5 py-6 text-center text-gray-400">No sales in this range.</td></tr><?php endif; ?>
                            <?php foreach ($byFranchise as $bf): ?>
                                <tr>
                                    <td class="px-5 py-2.5 font-medium text-gray-800"><?php echo e($bf['name']); ?></td>
                                    <td class="px-5 py-2.5 text-gray-500"><?php echo e($bf['code']); ?></td>
                                    <td class="px-5 py-2.5 text-right"><?php echo (int)$bf['sales']; ?></td>
                                    <td class="px-5 py-2.5 text-right"><?php echo (int)$bf['units']; ?></td>
                                    <td class="px-5 py-2.5 text-right font-semibold"><?php echo fsr_money($bf['turnover']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- sale-line detail -->
            <div class="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden">
                <div class="px-5 py-3 border-b border-gray-100 flex items-center justify-between flex-wrap gap-2">
                    <div>
                        <h3 class="text-[14px] font-semibold text-[#173a67]">Sale Records</h3>
                        <span class="text-[12px] text-gray-400"><?php echo count($rows); ?> shown</span>
                    </div>
                    <div class="flex gap-2 no-print">
                        <button type="button" onclick="frExportTableToExcel('salesDetailTable', 'Franchise_Sales_<?php echo e($from); ?>_<?php echo e($to); ?>.xlsx', 'Sales')" class="bg-green-600 hover:bg-green-700 text-white px-3 py-1.5 rounded-lg text-[12px] font-semibold flex items-center gap-1.5">
                            <i data-lucide="file-spreadsheet" class="w-4 h-4"></i> Excel
                        </button>
                        <button type="button" onclick="window.print()" class="bg-gray-500 hover:bg-gray-600 text-white px-3 py-1.5 rounded-lg text-[12px] font-semibold flex items-center gap-1.5">
                            <i data-lucide="printer" class="w-4 h-4"></i> Print
                        </button>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-[13px]" id="salesDetailTable">
                        <thead class="bg-gray-50 text-gray-500 text-[12px]">
                            <tr>
                                <th class="text-left px-5 py-2.5">Date</th>
                                <th class="text-left px-5 py-2.5">Franchise</th>
                                <th class="text-left px-5 py-2.5">Invoice</th>
                                <th class="text-left px-5 py-2.5">Customer</th>
                                <th class="text-left px-5 py-2.5">Product</th>
                                <th class="text-right px-5 py-2.5">Qty</th>
                                <th class="text-right px-5 py-2.5">Amount</th>
                                <th class="text-left px-5 py-2.5">Payment</th>
                                <th class="text-left px-5 py-2.5">Delivery</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <?php if (empty($rows)): ?>
                                <tr><td colspan="9" class="px-5 py-8 text-center text-gray-400">No sales in this range.</td></tr>
                            <?php endif; ?>
                            <?php foreach ($rows as $r): ?>
                                <tr>
                                    <td class="px-5 py-2.5 text-gray-500 whitespace-nowrap"><?php echo e($r['sale_date'] ?: substr((string)$r['created_at'], 0, 10)); ?></td>
                                    <td class="px-5 py-2.5 text-gray-700"><?php echo e($r['f_name'] ?: ('Franchise #' . (int)$r['franchise_id'])); ?> <span class="text-gray-400 text-[11px]">(<?php echo e($r['f_code'] ?: '-'); ?>)</span></td>
                                    <td class="px-5 py-2.5 text-gray-600"><?php echo e($r['invoice_no'] ?: '—'); ?></td>
                                    <td class="px-5 py-2.5"><?php echo e($r['customer_name'] ?: 'Walk-in'); ?></td>
                                    <td class="px-5 py-2.5 font-medium text-gray-800"><?php echo e($r['product_name']); ?><?php if ($r['model']): ?> <span class="text-gray-400 text-[11px]">/ <?php echo e($r['model']); ?></span><?php endif; ?></td>
                                    <td class="px-5 py-2.5 text-right"><?php echo (int)$r['quantity']; ?></td>
                                    <td class="px-5 py-2.5 text-right font-semibold"><?php echo fsr_money($r['sale_amount']); ?></td>
                                    <td class="px-5 py-2.5"><span class="text-[11px] rounded-full px-2 py-0.5 <?php echo $r['payment_status'] === 'Paid' ? 'bg-green-50 text-green-700' : 'bg-amber-50 text-amber-700'; ?>"><?php echo e($r['payment_status']); ?></span></td>
                                    <td class="px-5 py-2.5"><span class="text-[11px] rounded-full px-2 py-0.5 <?php echo $r['delivery_status'] === 'Delivered' ? 'bg-green-50 text-green-700' : 'bg-gray-100 text-gray-600'; ?>"><?php echo e($r['delivery_status']); ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <p class="mt-3 text-[11px] text-gray-400">Company-wide view of showroom sales recorded via the franchise Billing POS. Currently covers State franchises — City/Mobile franchise inventory is not wired up yet.</p>

            <?php endif; ?>
        </main>
    </div>
</div>
<script>if (typeof lucide !== 'undefined') lucide.createIcons();</script>
</body>
</html>
