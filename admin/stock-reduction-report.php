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

$conn->query("
    CREATE TABLE IF NOT EXISTS admin_stock_reduce_report (
        id INT AUTO_INCREMENT PRIMARY KEY,
        order_id VARCHAR(80) NOT NULL,
        request_from VARCHAR(20) NOT NULL,
        franchise_id INT NOT NULL DEFAULT 0,
        franchise_code VARCHAR(80) NOT NULL DEFAULT '',
        product_name VARCHAR(190) NOT NULL,
        requested_qty INT NOT NULL DEFAULT 0,
        reduced_qty INT NOT NULL DEFAULT 0,
        stock_before INT NOT NULL DEFAULT 0,
        stock_after INT NOT NULL DEFAULT 0,
        status_applied VARCHAR(20) NOT NULL DEFAULT 'Approved',
        reduced_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_reduce_entry (order_id, request_from, franchise_id, product_name),
        KEY idx_reduce_date (reduced_at),
        KEY idx_reduce_product (product_name),
        KEY idx_reduce_franchise (franchise_id, franchise_code)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

$search     = trim((string)($_GET['search']    ?? ''));
$fromFilter = trim((string)($_GET['from']      ?? 'all'));
$fromDate   = trim((string)($_GET['from_date'] ?? ''));
$toDate     = trim((string)($_GET['to_date']   ?? ''));

$allowedFrom = ['all', 'state', 'city', 'mobile'];
if (!in_array($fromFilter, $allowedFrom, true)) {
    $fromFilter = 'all';
}

$conditions = [];
$params     = [];
$types      = '';

if ($search !== '') {
    $conditions[] = '(r.order_id LIKE ? OR r.product_name LIKE ? OR r.franchise_code LIKE ?)';
    $like = '%' . $search . '%';
    $params[] = $like; $params[] = $like; $params[] = $like;
    $types .= 'sss';
}
if ($fromFilter !== 'all') {
    $conditions[] = 'r.request_from = ?';
    $params[] = $fromFilter;
    $types .= 's';
}
if ($fromDate !== '') {
    $conditions[] = 'DATE(r.reduced_at) >= ?';
    $params[] = $fromDate;
    $types .= 's';
}
if ($toDate !== '') {
    $conditions[] = 'DATE(r.reduced_at) <= ?';
    $params[] = $toDate;
    $types .= 's';
}

$sql = "
    SELECT
        r.id, r.order_id, r.request_from, r.franchise_id,
        COALESCE(NULLIF(r.franchise_code,''), fh.franchise_code, CONCAT('ID-', r.franchise_id)) AS franchise_display,
        r.product_name, r.requested_qty, r.reduced_qty, r.stock_before, r.stock_after, r.reduced_at
    FROM admin_stock_reduce_report r
    LEFT JOIN franchies_holder fh ON fh.id = r.franchise_id
";
if (!empty($conditions)) {
    $sql .= ' WHERE ' . implode(' AND ', $conditions);
}
$sql .= ' ORDER BY r.reduced_at DESC, r.id DESC';

$rows = [];
$stmt = $conn->prepare($sql);
if ($stmt) {
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }
    $stmt->close();
}

$grouped = [];
foreach ($rows as $r) {
    $oid = $r['order_id'];
    if (!isset($grouped[$oid])) {
        $grouped[$oid] = [
            'order_id'          => $r['order_id'],
            'reduced_at'        => $r['reduced_at'],
            'request_from'      => $r['request_from'],
            'franchise_display' => $r['franchise_display'],
            'total_requested'   => 0,
            'total_reduced'     => 0,
            'products'          => [],
        ];
    }
    $grouped[$oid]['total_requested'] += (int)$r['requested_qty'];
    $grouped[$oid]['total_reduced']   += (int)$r['reduced_qty'];
    $grouped[$oid]['products'][]       = [
        'product_name'  => $r['product_name'],
        'requested_qty' => (int)$r['requested_qty'],
        'reduced_qty'   => (int)$r['reduced_qty'],
        'stock_before'  => (int)$r['stock_before'],
        'stock_after'   => (int)$r['stock_after'],
    ];
}
$grouped = array_values($grouped);

$totalRequested = 0;
$totalReduced   = 0;
foreach ($grouped as $g) {
    $totalRequested += $g['total_requested'];
    $totalReduced   += $g['total_reduced'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stock Reduction Report</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link rel="stylesheet" href="assets/css/admin-responsive.css">
    <style>
        .main-wrapper {
            margin-left: 256px;
            min-height: 100vh;
            transition: margin-left 0.3s ease;
        }
        @media (max-width: 1023px) {
            .main-wrapper { margin-left: 0 !important; }
        }

        #reportModal {
            display: none;
            position: fixed;
            inset: 0;
            z-index: 1060;
            background: rgba(0,0,0,0.5);
            overflow-y: auto;
            padding: 20px;
        }
        #reportModal.active { display: block; }

        .totals-row td {
            background: #fefce8;
            border-top: 2px solid #d1a14b !important;
            font-weight: 700;
            font-size: 13.5px;
        }
        .totals-row td.total-label { color: #92400e; letter-spacing: .4px; }
        .totals-row td.total-reduced { color: #dc2626; }

        .tbl-scroll { overflow-x: auto; -webkit-overflow-scrolling: touch; }

        select.filter-select {
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%236b7280' stroke-width='2'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 10px center;
            padding-right: 30px;
        }

        @media print {
            body > * { display: none !important; }
            #reportModal { display: block !important; position: static !important; background: none !important; padding: 0 !important; }
            #modal-inner { box-shadow: none !important; border-radius: 0 !important; }
            #modal-footer-bar { display: none !important; }
        }
    </style>
</head>
<body class="bg-[#f3f4f6] min-h-screen overflow-x-hidden">

<?php include __DIR__ . '/includes/sidebar.php'; ?>

<div class="main-wrapper">
    <?php include __DIR__ . '/includes/navbar.php'; ?>

    <main class="px-4 py-5" style="max-width:1400px;margin:0 auto;">

        <div class="flex items-center gap-4 mb-7">
            <div class="flex h-[44px] w-[44px] shrink-0 items-center justify-center rounded-[10px] bg-[#f6eee2]">
                <i data-lucide="file-bar-chart-2" class="h-5 w-5 text-[#cf9a3c]"></i>
            </div>
            <h2 class="whitespace-nowrap text-[20px] sm:text-[24px] font-normal leading-none text-[#1f2937]" style="font-family: Georgia,'Times New Roman',serif;">
                STOCK REDUCTION REPORT
            </h2>
            <div class="h-[2px] flex-1 bg-[#d1a14b]"></div>
        </div>

        <form method="GET" class="mb-4 rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
            <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-5 gap-3 items-end">
                <div class="col-span-2 sm:col-span-4 lg:col-span-2">
                    <label class="block text-[13px] font-semibold text-gray-700 mb-1">Search</label>
                    <input type="text" name="search" value="<?= e($search) ?>" placeholder="Order ID, Product, Franchise Code"
                        class="w-full h-[38px] rounded-lg border border-gray-300 px-3 text-[13px] text-gray-700 outline-none focus:border-[#cf9a3c] focus:ring-1 focus:ring-[#cf9a3c]">
                </div>
                <div>
                    <label class="block text-[13px] font-semibold text-gray-700 mb-1">Type</label>
                    <select name="from" class="filter-select w-full h-[38px] rounded-lg border border-gray-300 px-3 text-[13px] text-gray-700 outline-none focus:border-[#cf9a3c] bg-white">
                        <option value="all"    <?= $fromFilter === 'all'    ? 'selected' : '' ?>>All</option>
                        <option value="state"  <?= $fromFilter === 'state'  ? 'selected' : '' ?>>State</option>
                        <option value="city"   <?= $fromFilter === 'city'   ? 'selected' : '' ?>>City</option>
                        <option value="mobile" <?= $fromFilter === 'mobile' ? 'selected' : '' ?>>Mobile</option>
                    </select>
                </div>
                <div>
                    <label class="block text-[13px] font-semibold text-gray-700 mb-1">From</label>
                    <input type="date" name="from_date" value="<?= e($fromDate) ?>"
                        class="w-full h-[38px] rounded-lg border border-gray-300 px-3 text-[13px] text-gray-700 outline-none focus:border-[#cf9a3c]">
                </div>
                <div>
                    <label class="block text-[13px] font-semibold text-gray-700 mb-1">To</label>
                    <input type="date" name="to_date" value="<?= e($toDate) ?>"
                        class="w-full h-[38px] rounded-lg border border-gray-300 px-3 text-[13px] text-gray-700 outline-none focus:border-[#cf9a3c]">
                </div>
                <div class="col-span-2 sm:col-span-1">
                    <button type="submit" class="w-full h-[38px] rounded-lg text-[13px] font-semibold text-white" style="background:#cf9a3c;">
                        Filter
                    </button>
                </div>
            </div>
        </form>

        <div class="rounded-xl border border-gray-200 bg-white p-3 shadow-sm">
            <div class="tbl-scroll">
                <table style="width:100%;min-width:750px;border-collapse:collapse;font-size:13px;">
                    <thead>
                        <tr style="background:#fef3c7;">
                            <th style="padding:10px 12px;text-align:left;font-weight:600;color:#92400e;border-bottom:2px solid #fde68a;">#</th>
                            <th style="padding:10px 12px;text-align:left;font-weight:600;color:#92400e;border-bottom:2px solid #fde68a;">Date</th>
                            <th style="padding:10px 12px;text-align:left;font-weight:600;color:#92400e;border-bottom:2px solid #fde68a;">Order ID</th>
                            <th style="padding:10px 12px;text-align:left;font-weight:600;color:#92400e;border-bottom:2px solid #fde68a;">Type</th>
                            <th style="padding:10px 12px;text-align:left;font-weight:600;color:#92400e;border-bottom:2px solid #fde68a;">Franchise</th>
                            <th style="padding:10px 12px;text-align:center;font-weight:600;color:#92400e;border-bottom:2px solid #fde68a;">Total Products</th>
                            <th style="padding:10px 12px;text-align:center;font-weight:600;color:#92400e;border-bottom:2px solid #fde68a;">Total Requested</th>
                            <th style="padding:10px 12px;text-align:center;font-weight:600;color:#92400e;border-bottom:2px solid #fde68a;">Total Reduced</th>
                            <th style="padding:10px 12px;text-align:center;font-weight:600;color:#92400e;border-bottom:2px solid #fde68a;">Report</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (!empty($grouped)): ?>
                        <?php foreach ($grouped as $index => $g): ?>
                            <tr style="border-bottom:1px solid #f3f4f6;" onmouseover="this.style.background='#fafafa'" onmouseout="this.style.background=''">
                                <td style="padding:10px 12px;color:#6b7280;"><?= $index + 1 ?></td>
                                <td style="padding:10px 12px;color:#374151;"><?= e($g['reduced_at']) ?></td>
                                <td style="padding:10px 12px;font-weight:600;color:#1f2937;"><?= e($g['order_id']) ?></td>
                                <td style="padding:10px 12px;">
                                    <?php
                                    $type = strtoupper($g['request_from']);
                                    $typeStyles = [
                                        'STATE'  => 'background:#dbeafe;color:#1d4ed8',
                                        'CITY'   => 'background:#dcfce7;color:#15803d',
                                        'MOBILE' => 'background:#fef9c3;color:#854d0e',
                                    ];
                                    $ts = $typeStyles[$type] ?? 'background:#f3f4f6;color:#374151';
                                    ?>
                                    <span style="display:inline-block;padding:2px 10px;border-radius:20px;font-size:11px;font-weight:600;<?= $ts ?>"><?= e($type) ?></span>
                                </td>
                                <td style="padding:10px 12px;color:#374151;"><?= e($g['franchise_display']) ?></td>
                                <td style="padding:10px 12px;text-align:center;">
                                    <span style="display:inline-block;padding:2px 10px;border-radius:20px;font-size:11px;font-weight:600;background:#f3f4f6;color:#374151;"><?= count($g['products']) ?></span>
                                </td>
                                <td style="padding:10px 12px;text-align:center;color:#1d4ed8;font-weight:600;"><?= $g['total_requested'] ?></td>
                                <td style="padding:10px 12px;text-align:center;color:#dc2626;font-weight:700;"><?= $g['total_reduced'] ?></td>
                                <td style="padding:10px 12px;text-align:center;">
                                    <button
                                        class="view-report-btn"
                                        style="display:inline-flex;align-items:center;gap:4px;padding:5px 12px;border-radius:6px;border:1.5px solid #cf9a3c;background:#fff;color:#92400e;font-size:12px;font-weight:600;cursor:pointer;"
                                        onmouseover="this.style.background='#fef3c7'" onmouseout="this.style.background='#fff'"
                                        data-order='<?= e(json_encode([
                                            'order_id'  => $g['order_id'],
                                            'date'      => $g['reduced_at'],
                                            'type'      => strtoupper($g['request_from']),
                                            'franchise' => $g['franchise_display'],
                                            'products'  => $g['products'],
                                            'total_req' => $g['total_requested'],
                                            'total_red' => $g['total_reduced'],
                                        ])) ?>'>
                                        <i data-lucide="eye" style="width:13px;height:13px;"></i> View
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>

                        <tr class="totals-row">
                            <td colspan="6" class="total-label" style="padding:10px 12px;text-align:right;">
                                <i data-lucide="sigma" style="width:14px;height:14px;vertical-align:middle;margin-right:4px;"></i>
                                TOTALS
                            </td>
                            <td style="padding:10px 12px;text-align:center;color:#1d4ed8;"><?= $totalRequested ?></td>
                            <td class="total-reduced" style="padding:10px 12px;text-align:center;"><?= $totalReduced ?></td>
                            <td></td>
                        </tr>

                    <?php else: ?>
                        <tr>
                            <td colspan="9" style="padding:48px 12px;text-align:center;color:#9ca3af;">No stock reduction report data found.</td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </main>
</div>

<div id="reportModal">
    <div id="modal-inner" style="max-width:720px;margin:40px auto;border-radius:14px;overflow:hidden;background:#fff;box-shadow:0 20px 60px rgba(0,0,0,0.3);">

        <div style="background:#d1a14b;padding:18px 24px 14px;display:flex;align-items:flex-start;justify-content:space-between;">
            <div style="display:flex;align-items:center;gap:14px;">
                <div style="width:40px;height:40px;border-radius:10px;background:rgba(255,255,255,.2);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                    <i data-lucide="clipboard-list" style="width:20px;height:20px;color:#fff;"></i>
                </div>
                <div>
                    <p style="font-size:15px;font-weight:600;color:#fff;letter-spacing:.3px;margin:0;line-height:1.3;">ORDER STOCK REDUCTION DETAIL</p>
                    <p id="rpt-order-id" style="font-size:12px;color:rgba(255,255,255,.8);margin:2px 0 0;"></p>
                </div>
            </div>
            <button onclick="closeReportModal()" style="background:none;border:none;cursor:pointer;width:28px;height:28px;display:flex;align-items:center;justify-content:center;border-radius:6px;opacity:.8;" onmouseover="this.style.opacity='1'" onmouseout="this.style.opacity='.8'">
                <i data-lucide="x" style="width:18px;height:18px;color:#fff;"></i>
            </button>
        </div>

        <div style="padding:12px 24px;background:#fafafa;border-bottom:1px solid #f0f0f0;display:flex;flex-wrap:wrap;gap:16px;align-items:center;">
            <div style="display:flex;align-items:center;gap:6px;font-size:13px;color:#6b7280;">
                <i data-lucide="calendar" style="width:14px;height:14px;"></i>
                <span id="rpt-date"></span>
            </div>
            <div style="display:flex;align-items:center;gap:6px;font-size:13px;color:#6b7280;">
                <i data-lucide="map-pin" style="width:14px;height:14px;"></i>
                <span>Franchise: <strong id="rpt-franchise" style="color:#1f2937;"></strong></span>
            </div>
            <div style="display:flex;align-items:center;gap:6px;font-size:13px;">
                <i data-lucide="tag" style="width:14px;height:14px;color:#6b7280;"></i>
                <span id="rpt-type-badge"></span>
            </div>
            <div style="display:flex;align-items:center;gap:5px;font-size:13px;color:#15803d;font-weight:500;">
                <i data-lucide="check-circle" style="width:14px;height:14px;"></i>
                Approved
            </div>
        </div>

        <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:1px;background:#e5e7eb;border-bottom:1px solid #e5e7eb;">
            <div style="background:#fff;padding:16px 18px;text-align:center;">
                <div id="rpt-total-products" style="font-size:28px;font-weight:700;line-height:1;color:#15803d;"></div>
                <div style="font-size:11px;color:#6b7280;margin-top:4px;letter-spacing:.4px;text-transform:uppercase;">Products</div>
            </div>
            <div style="background:#fff;padding:16px 18px;text-align:center;">
                <div id="rpt-total-req" style="font-size:28px;font-weight:700;line-height:1;color:#1d4ed8;"></div>
                <div style="font-size:11px;color:#6b7280;margin-top:4px;letter-spacing:.4px;text-transform:uppercase;">Requested</div>
            </div>
            <div style="background:#fff;padding:16px 18px;text-align:center;">
                <div id="rpt-total-red" style="font-size:28px;font-weight:700;line-height:1;color:#dc2626;"></div>
                <div style="font-size:11px;color:#6b7280;margin-top:4px;letter-spacing:.4px;text-transform:uppercase;">Reduced</div>
            </div>
            <div style="background:#fff;padding:16px 18px;text-align:center;">
                <div id="rpt-avg-after" style="font-size:28px;font-weight:700;line-height:1;color:#b45309;"></div>
                <div style="font-size:11px;color:#6b7280;margin-top:4px;letter-spacing:.4px;text-transform:uppercase;">Avg Remaining</div>
            </div>
        </div>

        <div style="padding:18px 24px;">
            <p style="font-size:11px;text-transform:uppercase;letter-spacing:.6px;color:#6b7280;font-weight:600;margin:0 0 12px;display:flex;align-items:center;gap:6px;">
                <i data-lucide="package" style="width:14px;height:14px;"></i>
                Product Breakdown
            </p>
            <div style="border-radius:10px;overflow:hidden;border:1px solid #e5e7eb;overflow-x:auto;">
                <table style="width:100%;min-width:520px;border-collapse:collapse;font-size:13px;">
                    <thead>
                        <tr style="background:#fef9ee;">
                            <th style="padding:9px 12px;font-size:11px;font-weight:600;letter-spacing:.4px;text-transform:uppercase;color:#92400e;border-bottom:1px solid #fde68a;text-align:left;">#</th>
                            <th style="padding:9px 12px;font-size:11px;font-weight:600;letter-spacing:.4px;text-transform:uppercase;color:#92400e;border-bottom:1px solid #fde68a;text-align:left;">Product</th>
                            <th style="padding:9px 12px;font-size:11px;font-weight:600;letter-spacing:.4px;text-transform:uppercase;color:#92400e;border-bottom:1px solid #fde68a;text-align:center;">Requested</th>
                            <th style="padding:9px 12px;font-size:11px;font-weight:600;letter-spacing:.4px;text-transform:uppercase;color:#92400e;border-bottom:1px solid #fde68a;text-align:center;">Reduced</th>
                            <th style="padding:9px 12px;font-size:11px;font-weight:600;letter-spacing:.4px;text-transform:uppercase;color:#92400e;border-bottom:1px solid #fde68a;text-align:center;">Stock Before</th>
                            <th style="padding:9px 12px;font-size:11px;font-weight:600;letter-spacing:.4px;text-transform:uppercase;color:#92400e;border-bottom:1px solid #fde68a;text-align:center;">Stock After</th>
                            <th style="padding:9px 12px;font-size:11px;font-weight:600;letter-spacing:.4px;text-transform:uppercase;color:#92400e;border-bottom:1px solid #fde68a;text-align:center;">Diff</th>
                        </tr>
                    </thead>
                    <tbody id="rpt-products-body"></tbody>
                    <tfoot>
                        <tr style="background:#fefce8;border-top:1.5px solid #fbbf24;">
                            <td colspan="2" style="padding:10px 12px;font-weight:600;color:#92400e;font-size:13px;">Totals</td>
                            <td id="rft-tot-req"    style="padding:10px 12px;text-align:center;font-weight:700;color:#1d4ed8;font-size:14px;"></td>
                            <td id="rft-tot-red"    style="padding:10px 12px;text-align:center;font-weight:700;color:#dc2626;font-size:14px;"></td>
                            <td id="rft-tot-before" style="padding:10px 12px;text-align:center;font-weight:700;color:#15803d;font-size:14px;"></td>
                            <td id="rft-tot-after"  style="padding:10px 12px;text-align:center;font-weight:700;color:#b45309;font-size:14px;"></td>
                            <td id="rft-tot-diff"   style="padding:10px 12px;text-align:center;font-weight:700;color:#dc2626;font-size:14px;"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <div id="modal-footer-bar" style="padding:14px 24px;background:#f9f9f9;border-top:1px solid #e5e7eb;display:flex;align-items:center;justify-content:space-between;">
            <div style="font-size:12px;color:#9ca3af;display:flex;align-items:center;gap:5px;">
                <i data-lucide="clock" style="width:13px;height:13px;"></i>
                <span id="rpt-footer-date"></span>
            </div>
            <div style="display:flex;gap:8px;">
                <button onclick="closeReportModal()" style="display:inline-flex;align-items:center;gap:5px;padding:6px 14px;border-radius:6px;border:1px solid #d1d5db;background:#fff;color:#374151;font-size:13px;font-weight:500;cursor:pointer;">
                    <i data-lucide="x" style="width:13px;height:13px;"></i> Close
                </button>
                <button onclick="printModalReport()" style="display:inline-flex;align-items:center;gap:5px;padding:6px 14px;border-radius:6px;border:none;background:#d1a14b;color:#fff;font-size:13px;font-weight:600;cursor:pointer;">
                    <i data-lucide="printer" style="width:13px;height:13px;"></i> Print Report
                </button>
            </div>
        </div>

    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    if (window.lucide) lucide.createIcons();

    document.querySelectorAll('.view-report-btn').forEach(btn => {
        btn.addEventListener('click', function () {
            const d = JSON.parse(this.dataset.order);

            document.getElementById('rpt-order-id').textContent    = d.order_id;
            document.getElementById('rpt-date').textContent        = d.date;
            document.getElementById('rpt-franchise').textContent   = d.franchise;
            document.getElementById('rpt-footer-date').textContent = 'Generated on ' + d.date;

            const badgeMap = {
                STATE:  'background:#dbeafe;color:#1d4ed8',
                CITY:   'background:#dcfce7;color:#15803d',
                MOBILE: 'background:#fef9c3;color:#854d0e'
            };
            const bc = badgeMap[d.type] || 'background:#dbeafe;color:#1d4ed8';
            document.getElementById('rpt-type-badge').innerHTML =
                `<span style="display:inline-block;padding:2px 10px;border-radius:20px;font-size:11px;font-weight:600;letter-spacing:.5px;${bc}">${d.type}</span>`;

            document.getElementById('rpt-total-products').textContent = d.products.length;
            document.getElementById('rpt-total-req').textContent      = d.total_req;
            document.getElementById('rpt-total-red').textContent      = d.total_red;
            const avgAfter = d.products.length > 0
                ? Math.round(d.products.reduce((s, p) => s + p.stock_after, 0) / d.products.length)
                : 0;
            document.getElementById('rpt-avg-after').textContent = avgAfter;

            const tbody = document.getElementById('rpt-products-body');
            tbody.innerHTML = '';
            let totReq = 0, totRed = 0, totBefore = 0, totAfter = 0;
            d.products.forEach((p, i) => {
                const diff = p.stock_before - p.stock_after;
                totReq    += p.requested_qty;
                totRed    += p.reduced_qty;
                totBefore += p.stock_before;
                totAfter  += p.stock_after;
                const rowBg = i % 2 === 1 ? 'background:#fafafa;' : '';
                tbody.innerHTML += `<tr style="${rowBg}">
                    <td style="padding:10px 12px;color:#9ca3af;">${i + 1}</td>
                    <td style="padding:10px 12px;font-weight:600;color:#1f2937;">${p.product_name}</td>
                    <td style="padding:10px 12px;text-align:center;"><span style="display:inline-block;padding:2px 8px;border-radius:12px;font-size:12px;font-weight:500;background:#eff6ff;color:#1d4ed8;">${p.requested_qty}</span></td>
                    <td style="padding:10px 12px;text-align:center;"><span style="display:inline-block;padding:2px 8px;border-radius:12px;font-size:12px;font-weight:500;background:#fef2f2;color:#dc2626;">${p.reduced_qty}</span></td>
                    <td style="padding:10px 12px;text-align:center;"><span style="display:inline-block;padding:2px 8px;border-radius:12px;font-size:12px;font-weight:500;background:#f0fdf4;color:#15803d;">${p.stock_before}</span></td>
                    <td style="padding:10px 12px;text-align:center;"><span style="display:inline-block;padding:2px 8px;border-radius:12px;font-size:12px;font-weight:500;background:#fefce8;color:#b45309;">${p.stock_after}</span></td>
                    <td style="padding:10px 12px;text-align:center;font-weight:600;color:#dc2626;">&#8722;${diff}</td>
                </tr>`;
            });

            document.getElementById('rft-tot-req').textContent    = totReq;
            document.getElementById('rft-tot-red').textContent    = totRed;
            document.getElementById('rft-tot-before').textContent = totBefore;
            document.getElementById('rft-tot-after').textContent  = totAfter;
            document.getElementById('rft-tot-diff').textContent   = '\u2212' + (totBefore - totAfter);

            if (window.lucide) lucide.createIcons();

            document.getElementById('reportModal').classList.add('active');
            document.body.style.overflow = 'hidden';
        });
    });

    document.getElementById('reportModal').addEventListener('click', function (e) {
        if (e.target === this) closeReportModal();
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') closeReportModal();
    });
});

function closeReportModal() {
    document.getElementById('reportModal').classList.remove('active');
    document.body.style.overflow = '';
}

function printModalReport() {
    window.print();
}
</script>

<script>
document.addEventListener('DOMContentLoaded', function () {
    lucide.createIcons();
});
</script>
</body>
</html>