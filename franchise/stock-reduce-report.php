<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config/db.php';

if (!isset($conn) || !($conn instanceof mysqli)) {
    die('Database connection failed.');
}

function e($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function tableExists(mysqli $conn, string $table): bool
{
    $table = $conn->real_escape_string($table);
    $res = $conn->query("SHOW TABLES LIKE '$table'");
    return ($res && $res->num_rows > 0);
}

function colExists(mysqli $conn, string $table, string $column): bool
{
    if (!tableExists($conn, $table)) return false;
    $table = $conn->real_escape_string($table);
    $column = $conn->real_escape_string($column);
    $res = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
    return ($res && $res->num_rows > 0);
}

function resolveLoggedFranchise(mysqli $conn): ?array
{
    $typeRaw = strtolower(trim((string)($_SESSION['franchise_type'] ?? $_SESSION['franchies_type'] ?? '')));
    $values = array_filter(array_map('trim', [
        (string)($_SESSION['mobile_franchise_id'] ?? ''),
        (string)($_SESSION['mobile_franchise_code'] ?? ''),
        (string)($_SESSION['city_franchies_holder_id'] ?? ''),
        (string)($_SESSION['state_franchies_holder_id'] ?? ''),
        (string)($_SESSION['franchise_db_id'] ?? ''),
        (string)($_SESSION['franchise_id'] ?? ''),
        (string)($_SESSION['franchies_id'] ?? ''),
        (string)($_SESSION['franchise_code'] ?? ''),
        (string)($_SESSION['franchise_login_id'] ?? ''),
    ]), fn($v) => $v !== '');

    if (str_contains($typeRaw, 'mobile') || !empty($_SESSION['mobile_franchise_id'])) {
        $order = ['mobile', 'city', 'state'];
    } elseif (str_contains($typeRaw, 'city') || !empty($_SESSION['city_franchies_holder_id'])) {
        $order = ['city', 'state', 'mobile'];
    } else {
        $order = ['state', 'city', 'mobile'];
    }

   $map = [
     'mobile' => [
        'table' => 'mobile_franchies_holder',
        'code_col' => 'mobile_franchies_id',
        'name_col' => 'owner_name'
    ],
    'city'   => ['table' => 'city_franchies_holder',   'code_col' => 'franchies_id', 'name_col' => 'full_name'],
    'state'  => ['table' => 'franchies_holder',        'code_col' => 'franchise_code', 'name_col' => 'name'],
];

    foreach ($order as $type) {
        $cfg = $map[$type];
        if (!tableExists($conn, $cfg['table'])) continue;
        foreach ($values as $value) {
            if (is_numeric($value)) {
                $id = (int)$value;
                $stmt = $conn->prepare("SELECT * FROM `{$cfg['table']}` WHERE id = ? LIMIT 1");
                if ($stmt) {
                    $stmt->bind_param('i', $id);
                    $stmt->execute();
                    $row = $stmt->get_result()->fetch_assoc();
                    $stmt->close();
                    if ($row) return ['type' => $type, 'id' => (int)$row['id'], 'code' => (string)($row[$cfg['code_col']] ?? $value), 'name' => (string)($row[$cfg['name_col']] ?? 'Franchise')];
                }
            }
            if (!colExists($conn, $cfg['table'], $cfg['code_col'])) continue;
            $stmt = $conn->prepare("SELECT * FROM `{$cfg['table']}` WHERE `{$cfg['code_col']}` = ? LIMIT 1");
            if ($stmt) {
                $stmt->bind_param('s', $value);
                $stmt->execute();
                $row = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                if ($row) return ['type' => $type, 'id' => (int)$row['id'], 'code' => (string)($row[$cfg['code_col']] ?? $value), 'name' => (string)($row[$cfg['name_col']] ?? 'Franchise')];
            }
        }
    }
    return null;
}

$franchise = resolveLoggedFranchise($conn);
if (!$franchise) { header('Location: index.php'); exit; }

$franchiseId   = (int)$franchise['id'];
$franchiseCode = trim((string)$franchise['code']);
$franchiseType = trim((string)$franchise['type']);


$search    = trim((string)($_GET['search']    ?? ''));
$source    = trim((string)($_GET['source']    ?? 'all'));
$fromDate  = trim((string)($_GET['from_date'] ?? ''));
$toDate    = trim((string)($_GET['to_date']   ?? ''));
$allowedSource = ['all', 'member_activation', 'member_repurchase_order'];
if (!in_array($source, $allowedSource, true)) $source = 'all';

$events = [];

// 1) Member Activation
if (tableExists($conn, 'member_activation')) {
    $sql = "SELECT id, customer_id, products_json, product_name, created_at FROM member_activation WHERE franchise_id = ?";
    if (colExists($conn, 'member_activation', 'franchise_code') && $franchiseCode !== '') {
        $sql .= " OR franchise_code = ?";
    }
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        if (colExists($conn, 'member_activation', 'franchise_code') && $franchiseCode !== '') {
            $stmt->bind_param('is', $franchiseId, $franchiseCode);
        } else {
            $stmt->bind_param('i', $franchiseId);
        }
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $orderRef = 'ACT-' . (int)$row['id'];
            $items = [];
            $decoded = json_decode((string)($row['products_json'] ?? ''), true);
            if (is_array($decoded)) $items = $decoded;
            elseif (!empty($row['product_name'])) $items[] = ['product_name' => (string)$row['product_name'], 'qty' => 1];
            foreach ($items as $item) {
                $productName = trim((string)($item['product_name'] ?? ''));
                $qty = (int)($item['qty'] ?? 1);
                if ($productName === '' || $qty <= 0) continue;
                $events[] = [
                    'event_key'    => 'MA|' . $orderRef . '|' . strtolower($productName),
                    'source'       => 'member_activation',
                    'source_label' => 'Member Activation',
                    'ref_no'       => $orderRef,
                    'member_id'    => (string)($row['customer_id'] ?? '-'),
                    'product_name' => $productName,
                    'reduced_qty'  => $qty,
                    'created_at'   => (string)($row['created_at'] ?? ''),
                ];
            }
        }
        $stmt->close();
    }
}



// 3) Member Repurchase Order
if (tableExists($conn, 'member_orders') && tableExists($conn, 'member_order_items')) {
    


   $stmt = $conn->prepare("
    SELECT
        mo.id,
        mo.member_id,
        mo.created_at,
        mi.product_name,
        mi.quantity
    FROM member_orders mo
    INNER JOIN member_order_items mi
        ON mi.order_id = mo.id
    WHERE (
        mo.distributor_id = ?
        OR mo.distributor_id = ?
    )
    ORDER BY mo.created_at DESC, mo.id DESC
");
    if ($stmt) {
        $franchiseIdStr = (string)$franchiseId;

$stmt->bind_param(
    'ss',
    $franchiseCode,
    $franchiseIdStr
);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $productName = trim((string)($row['product_name'] ?? ''));
            $qty = (int)($row['quantity'] ?? 0);
            if ($productName === '' || $qty <= 0) continue;
            $refNo = 'ORD' . str_pad((string)((int)($row['id'] ?? 0)), 4, '0', STR_PAD_LEFT);
            $events[] = [
                'event_key'    => 'MR|' . $refNo . '|' . strtolower($productName),
                'source'       => 'member_repurchase_order',
                'source_label' => 'Member Repurchase Order',
                'ref_no'       => $refNo,
                'member_id'    => (string)($row['member_id'] ?? '-'),
                'product_name' => $productName,
                'reduced_qty'  => $qty,
                'created_at'   => (string)($row['created_at'] ?? ''),
            ];
        }
        $stmt->close();
    }
}

// De-duplicate
$unique = [];
foreach ($events as $event) {
    $key = $event['event_key'];
    if (!isset($unique[$key])) $unique[$key] = $event;
}
$events = array_values($unique);

// Filter
$events = array_values(array_filter($events, function (array $row) use ($search, $source, $fromDate, $toDate): bool {
    if ($source !== 'all' && $row['source'] !== $source) return false;
    if ($search !== '') {
        $haystack = strtolower($row['ref_no'] . ' ' . $row['member_id'] . ' ' . $row['product_name']);
        if (strpos($haystack, strtolower($search)) === false) return false;
    }
    $eventDate = substr((string)$row['created_at'], 0, 10);
    if ($fromDate !== '' && $eventDate !== '' && $eventDate < $fromDate) return false;
    if ($toDate   !== '' && $eventDate !== '' && $eventDate > $toDate)   return false;
    return true;
}));

usort($events, fn($a, $b) => strcmp((string)$b['created_at'], (string)$a['created_at']));

// ── Total Reduced Qty ──
$totalReduced = array_sum(array_column($events, 'reduced_qty'));

// ── Total Available Stock: sum of all approved stock received minus totalReduced ──
// We read current stock from franchise_stock (or whichever table stores it)
$totalAvailable = 0;

if (tableExists($conn, 'franchies_product_stock_request')) {

    $stmt = $conn->prepare("
        SELECT COALESCE(SUM(product_balance),0) AS total_stock
        FROM franchies_product_stock_request
        WHERE franchise_id = ?
    ");

    if ($stmt) {
        $stmt->bind_param('i', $franchiseId);
        $stmt->execute();

        $row = $stmt->get_result()->fetch_assoc();

        $totalAvailable = (int)($row['total_stock'] ?? 0);

        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stock Reduce Report</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <!-- jsPDF for PDF download -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.8.2/jspdf.plugin.autotable.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <script src="assets/report-export.js"></script>
</head>
<body class="bg-[#f3f4f6] min-h-screen overflow-x-hidden">

<?php include __DIR__ . '/includes/sidebar.php'; ?>

<div class="ml-64 min-h-screen">
    <?php include __DIR__ . '/includes/navbar.php'; ?>

    <main class="px-5 py-6">
        <div class="max-w-[1400px] mx-auto">

            <!-- Page Title -->
            <div class="mb-8 flex items-center gap-5">
                <div class="flex h-[44px] w-[44px] shrink-0 items-center justify-center rounded-[10px] bg-[#f6eee2]">
                    <i data-lucide="file-bar-chart-2" class="h-5 w-5 text-[#cf9a3c]"></i>
                </div>
                <h2 class="whitespace-nowrap text-[24px] font-normal leading-none text-[#1f2937]"
                    style="font-family: Georgia,'Times New Roman',serif;">
                    STOCK REDUCE REPORT
                </h2>
                <div class="h-[2px] w-full bg-[#d1a14b]"></div>
            </div>

            <!-- Filter Form -->
            <form method="GET" class="mb-4 rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                <div class="grid grid-cols-1 gap-3 md:grid-cols-5">
                    <div>
                        <label class="mb-1 block text-sm font-semibold text-gray-700">Search</label>
                        <input type="text" name="search" value="<?= e($search) ?>"
                               class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
                               placeholder="Ref, Member, Product">
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-semibold text-gray-700">Source</label>
                        <select name="source" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm">
                            <option value="all"                      <?= $source === 'all'                      ? 'selected' : '' ?>>All</option>
                            <option value="member_activation"        <?= $source === 'member_activation'        ? 'selected' : '' ?>>Member Activation</option>
                            <option value="product_request"          <?= $source === 'product_request'          ? 'selected' : '' ?>>Product Request</option>
                            <option value="member_repurchase_order"  <?= $source === 'member_repurchase_order'  ? 'selected' : '' ?>>Member Repurchase Order</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-semibold text-gray-700">From Date</label>
                        <input type="date" name="from_date" value="<?= e($fromDate) ?>"
                               class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-semibold text-gray-700">To Date</label>
                        <input type="date" name="to_date" value="<?= e($toDate) ?>"
                               class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm">
                    </div>
                    <div class="flex items-end">
                        <button type="submit"
                                class="w-full rounded-md bg-[#103258] px-4 py-2 text-sm font-semibold text-white hover:bg-[#0f2b49]">
                            Apply Filter
                        </button>
                    </div>
                </div>
            </form>

            <!-- Table -->
            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                <div class="mb-3 flex justify-end gap-2 no-print">
                    <button type="button" onclick="frExportTableToExcel('stockTable', 'Stock_Reduce_Report.xlsx', 'Stock Reduce Report')"
                            class="bg-green-600 hover:bg-green-700 text-white px-3 py-1.5 rounded-lg text-[12px] font-semibold flex items-center gap-1.5 transition">
                        <i data-lucide="file-spreadsheet" class="w-4 h-4"></i> Excel
                    </button>
                    <button type="button" onclick="window.print()"
                            class="bg-gray-500 hover:bg-gray-600 text-white px-3 py-1.5 rounded-lg text-[12px] font-semibold flex items-center gap-1.5 transition">
                        <i data-lucide="printer" class="w-4 h-4"></i> Print / PDF
                    </button>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[1100px] border-collapse text-sm" id="stockTable">
                        <thead>
                        <tr class="bg-[#cd9737] text-white">
                            <th class="border border-[#b98225] px-3 py-2 text-left">#</th>
                            <th class="border border-[#b98225] px-3 py-2 text-left">Date</th>
                            <th class="border border-[#b98225] px-3 py-2 text-left">Source</th>
                            <th class="border border-[#b98225] px-3 py-2 text-left">Reference</th>
                            <th class="border border-[#b98225] px-3 py-2 text-left">Member ID</th>
                            <th class="border border-[#b98225] px-3 py-2 text-left">Product Name</th>
                            <th class="border border-[#b98225] px-3 py-2 text-left">Reduced Qty</th>
                            <th class="border border-[#b98225] px-3 py-2 text-center">Report</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php if (!empty($events)): ?>
                            <?php foreach ($events as $index => $row): ?>
                                <?php
                                    // Build a safe JSON object for JS
                                    $jsRow = json_encode([
                                        'sr'           => $index + 1,
                                        'date'         => $row['created_at'],
                                        'source'       => $row['source_label'],
                                        'ref_no'       => $row['ref_no'],
                                        'member_id'    => $row['member_id'],
                                        'product_name' => $row['product_name'],
                                        'reduced_qty'  => $row['reduced_qty'],
                                    ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
                                ?>
                                <tr class="border-b border-gray-100 hover:bg-[#fdf8f0]">
                                    <td class="border border-gray-200 px-3 py-2"><?= $index + 1 ?></td>
                                    <td class="border border-gray-200 px-3 py-2 whitespace-nowrap"><?= e($row['created_at']) ?></td>
                                    <td class="border border-gray-200 px-3 py-2"><?= e($row['source_label']) ?></td>
                                    <td class="border border-gray-200 px-3 py-2 font-semibold"><?= e($row['ref_no']) ?></td>
                                    <td class="border border-gray-200 px-3 py-2"><?= e($row['member_id']) ?></td>
                                    <td class="border border-gray-200 px-3 py-2"><?= e($row['product_name']) ?></td>
                                    <td class="border border-gray-200 px-3 py-2 font-semibold text-red-600"><?= (int)$row['reduced_qty'] ?></td>
                                    <td class="border border-gray-200 px-3 py-2 text-center">
                                        <button type="button"
                                                onclick='openReportModal(<?= $jsRow ?>)'
                                                class="inline-flex items-center gap-1 rounded-[6px] bg-[#143a66] px-3 py-1
                                                       text-[12px] font-semibold text-white hover:bg-[#0e2e52] transition-colors">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" fill="none"
                                                 viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                      d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                      d="M2.458 12C3.732 7.943 7.523 5 12 5c4.477 0
                                                      8.268 2.943 9.542 7-1.274 4.057-5.065 7-9.542 7
                                                      -4.477 0-8.268-2.943-9.542-7z"/>
                                            </svg>
                                            View
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>

                            <!-- ── TOTAL ROW ── -->
                            <tr class="bg-[#f6eee2] font-bold text-[#1f2937]">
                                <td colspan="6"
                                    class="border border-[#d1a14b] px-3 py-2 text-right text-[13px] tracking-wide uppercase">
                                    Total Stock Reduced
                                </td>
                                <td class="border border-[#d1a14b] px-3 py-2 text-red-700 text-[14px]">
                                    <?= $totalReduced ?>
                                </td>
                                <td class="border border-[#d1a14b] px-3 py-2"></td>
                            </tr>
                            <tr class="bg-[#eaf4ea] font-bold text-[#1f2937]">
                                <td colspan="6"
                                    class="border border-[#8bc48b] px-3 py-2 text-right text-[13px] tracking-wide uppercase">
                                    Total Available Stock
                                </td>
                                <td class="border border-[#8bc48b] px-3 py-2 text-green-700 text-[14px]">
                                    <?= $totalAvailable ?>
                                </td>
                                <td class="border border-[#8bc48b] px-3 py-2"></td>
                            </tr>

                        <?php else: ?>
                            <tr>
                                <td colspan="8"
                                    class="border border-gray-200 py-10 text-center text-gray-500">
                                    No stock reduction records found.
                                </td>
                            </tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>
</div>


<!-- ════════════════════════════════════════════════
     REPORT DETAIL MODAL
════════════════════════════════════════════════ -->
<div id="reportModal"
     class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 p-4">
    <div class="w-full max-w-[500px] rounded-[16px] bg-white shadow-2xl overflow-hidden">

        <!-- Modal Header -->
        <div class="flex items-center justify-between bg-[#103258] px-6 py-4">
            <div class="flex items-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-white" fill="none"
                     viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0
                          01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293
                          l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                <h3 class="text-[17px] font-bold text-white">Stock Reduction Report</h3>
            </div>
            <button onclick="closeReportModal()"
                    class="text-white/70 hover:text-white text-[26px] leading-none">&times;</button>
        </div>

        <!-- Modal Body -->
        <div class="px-6 py-5 space-y-3" id="reportModalBody">

            <div class="grid grid-cols-2 gap-3">
                <div class="rounded-[10px] bg-[#f0f4fa] border border-[#c5d5ea] px-4 py-3">
                    <p class="text-[10px] font-bold text-[#103258] uppercase tracking-widest mb-1">Reference No</p>
                    <p id="rm_ref" class="text-[15px] font-mono font-bold text-[#103258]">—</p>
                </div>
                <div class="rounded-[10px] bg-[#f0f4fa] border border-[#c5d5ea] px-4 py-3">
                    <p class="text-[10px] font-bold text-[#103258] uppercase tracking-widest mb-1">Date</p>
                    <p id="rm_date" class="text-[14px] font-semibold text-[#2d2d2d]">—</p>
                </div>
            </div>

            <div class="rounded-[10px] bg-[#f6f2ea] border border-[#e8d8b0] px-4 py-3">
                <p class="text-[10px] font-bold text-[#c9983b] uppercase tracking-widest mb-1">Source</p>
                <p id="rm_source" class="text-[14px] font-semibold text-[#2d2d2d]">—</p>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div class="rounded-[10px] bg-[#f0f4fa] border border-[#c5d5ea] px-4 py-3">
                    <p class="text-[10px] font-bold text-[#103258] uppercase tracking-widest mb-1">Member ID</p>
                    <p id="rm_member" class="text-[14px] font-semibold text-[#2d2d2d]">—</p>
                </div>
                <div class="rounded-[10px] bg-[#f0f4fa] border border-[#c5d5ea] px-4 py-3">
                    <p class="text-[10px] font-bold text-[#103258] uppercase tracking-widest mb-1">Reduced Qty</p>
                    <p id="rm_qty" class="text-[16px] font-bold text-red-600">—</p>
                </div>
            </div>

            <div class="rounded-[10px] bg-[#f0f4fa] border border-[#c5d5ea] px-4 py-3">
                <p class="text-[10px] font-bold text-[#103258] uppercase tracking-widest mb-1">Product Name</p>
                <p id="rm_product" class="text-[14px] font-semibold text-[#2d2d2d]">—</p>
            </div>

        </div>

        <!-- Modal Footer -->
        <div class="flex justify-end gap-3 border-t px-6 py-4 bg-[#fafafa]">
            <button type="button" onclick="closeReportModal()"
                    class="rounded-lg border border-gray-300 px-5 py-2 text-[14px] font-medium hover:bg-gray-100">
                Close
            </button>
            <button type="button" onclick="downloadReportPDF()"
                    class="rounded-lg bg-[#c9983b] px-5 py-2 text-[14px] font-semibold text-white
                           hover:bg-[#b8862d] inline-flex items-center gap-1.5">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none"
                     viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1
                          m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                </svg>
                Download PDF
            </button>
        </div>
    </div>
</div>


<script>
/* ─────────────────────────────────────────
   Lucide Icons
───────────────────────────────────────── */
document.addEventListener('DOMContentLoaded', function () {
    if (window.lucide) lucide.createIcons();
});

/* ─────────────────────────────────────────
   Report Modal
───────────────────────────────────────── */
let _currentReport = null;

function openReportModal(data) {
    _currentReport = data;

    document.getElementById('rm_ref').textContent     = data.ref_no       || '—';
    document.getElementById('rm_date').textContent    = data.date         || '—';
    document.getElementById('rm_source').textContent  = data.source       || '—';
    document.getElementById('rm_member').textContent  = data.member_id    || '—';
    document.getElementById('rm_product').textContent = data.product_name || '—';
    document.getElementById('rm_qty').textContent     = data.reduced_qty  ?? '—';

    const m = document.getElementById('reportModal');
    m.classList.remove('hidden');
    m.classList.add('flex');
}

function closeReportModal() {
    const m = document.getElementById('reportModal');
    m.classList.add('hidden');
    m.classList.remove('flex');
    _currentReport = null;
}

// Close on backdrop click
document.getElementById('reportModal').addEventListener('click', function (e) {
    if (e.target === this) closeReportModal();
});

/* ─────────────────────────────────────────
   Download PDF via jsPDF
───────────────────────────────────────── */
function downloadReportPDF() {
    if (!_currentReport) return;

    const { jsPDF } = window.jspdf;
    const doc = new jsPDF({ orientation: 'portrait', unit: 'mm', format: 'a4' });

    const pageW = doc.internal.pageSize.getWidth();

    // ── Header bar ──
    doc.setFillColor(16, 50, 88);           // #103258
    doc.rect(0, 0, pageW, 28, 'F');

    doc.setTextColor(255, 255, 255);
    doc.setFontSize(16);
    doc.setFont('helvetica', 'bold');
    doc.text('Stock Reduction Report', pageW / 2, 13, { align: 'center' });
    doc.setFontSize(9);
    doc.setFont('helvetica', 'normal');
    doc.text('Generated: ' + new Date().toLocaleString(), pageW / 2, 21, { align: 'center' });

    // ── Detail table ──
    const rows = [
        ['Reference No',  _currentReport.ref_no       || '—'],
        ['Date',          _currentReport.date          || '—'],
        ['Source',        _currentReport.source        || '—'],
        ['Member ID',     _currentReport.member_id     || '—'],
        ['Product Name',  _currentReport.product_name  || '—'],
        ['Reduced Qty',   String(_currentReport.reduced_qty ?? '—')],
    ];

    doc.autoTable({
        startY: 35,
        head: [['Field', 'Value']],
        body: rows,
        theme: 'grid',
        headStyles: {
            fillColor: [205, 151, 55],   // #cd9737
            textColor: 255,
            fontStyle: 'bold',
            fontSize: 11,
        },
        bodyStyles: { fontSize: 10, textColor: [31, 41, 55] },
        alternateRowStyles: { fillColor: [246, 242, 234] },
        columnStyles: {
            0: { fontStyle: 'bold', cellWidth: 55 },
            1: { cellWidth: 'auto' },
        },
        margin: { left: 14, right: 14 },
    });

    // ── Footer note ──
    const finalY = doc.lastAutoTable.finalY + 8;
    doc.setFontSize(8);
    doc.setTextColor(150);
    doc.text('This is a system-generated report.', 14, finalY);

    doc.save('stock-reduce-' + (_currentReport.ref_no || 'report') + '.pdf');
}
</script>
</body>
</html>