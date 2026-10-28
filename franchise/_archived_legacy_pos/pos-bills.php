<?php
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/config/db.php';
if (!isset($conn) || !($conn instanceof mysqli)) die("DB connection failed.");

function e($s){ return htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8'); }

/* resolve franchise id */
$franchise_id = 0;
foreach (['franchise_id','franchies_id','franchise_db_id',
          'mobile_franchise_id','city_franchies_holder_id','state_franchies_holder_id'] as $k) {
    if (!empty($_SESSION[$k]) && is_numeric($_SESSION[$k])) {
        $franchise_id = (int)$_SESSION[$k]; break;
    }
}
if (!$franchise_id) { header('Location: index.php'); exit; }

$page     = max(1,(int)($_GET['page'] ?? 1));
$per_page = 20;
$offset   = ($page - 1) * $per_page;
$search   = trim($_GET['search']    ?? '');
$df       = trim($_GET['date_from'] ?? '');
$dt       = trim($_GET['date_to']   ?? '');

$where  = "WHERE distributor_id = ?";
$params = [$franchise_id];
$types  = 'i';
if ($search !== '') {
    $s = "%$search%";
    $where .= " AND (member_name LIKE ? OR member_id LIKE ?)";
    $params[] = $s; $params[] = $s; $types .= 'ss';
}
if ($df !== '') { $where .= " AND DATE(created_at) >= ?"; $params[] = $df; $types .= 's'; }
if ($dt !== '') { $where .= " AND DATE(created_at) <= ?"; $params[] = $dt; $types .= 's'; }

$stC = $conn->prepare("SELECT COUNT(*) FROM member_orders $where");
$stC->bind_param($types, ...$params); $stC->execute();
$total_rows  = (int)$stC->get_result()->fetch_row()[0]; $stC->close();
$total_pages = (int)ceil($total_rows / $per_page);

$pAll = array_merge($params, [$per_page, $offset]);
$tAll = $types . 'ii';
$stL  = $conn->prepare(
    "SELECT id, member_id, member_name, total_amount, payment_method, created_at
       FROM member_orders $where ORDER BY id DESC LIMIT ? OFFSET ?"
);
$stL->bind_param($tAll, ...$pAll); $stL->execute();
$orders = $stL->get_result()->fetch_all(MYSQLI_ASSOC); $stL->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>POS Bills — Netvexa</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
    body { background:#f1f2f4; font-family:'Inter',system-ui,sans-serif; }
    .bill-table { width:100%; border-collapse:collapse; font-size:13.5px; }
    .bill-table thead th { background:#103258; color:#fff; padding:11px 16px;
                           text-align:left; font-size:11.5px; font-weight:700;
                           letter-spacing:.04em; text-transform:uppercase; }
    .bill-table thead th:first-child { border-radius:10px 0 0 0; }
    .bill-table thead th:last-child  { border-radius:0 10px 0 0; text-align:center; }
    .bill-table tbody tr { border-bottom:1px solid #f0f3f7; transition:background .15s; }
    .bill-table tbody tr:hover { background:#f8fafc; }
    .bill-table td { padding:11px 16px; vertical-align:middle; }
    @media(max-width:1024px){ .ml-64{ margin-left:0!important; } }
    </style>
</head>
<body class="bg-[#f1f2f4] overflow-x-hidden">

<?php include __DIR__ . '/includes/sidebar.php'; ?>

<div class="ml-64 min-h-screen">
    <?php include __DIR__ . '/includes/navbar.php'; ?>

    <main class="px-5 py-6">

        <!-- Heading -->
        <div class="mt-5 mb-6 flex items-center gap-4">
            <div class="w-[36px] h-[36px] rounded-[8px] bg-[#f5eee3] flex items-center justify-center shrink-0">
                <i data-lucide="receipt-text" class="w-[18px] h-[18px] text-[#cd9737]"></i>
            </div>
            <h2 class="whitespace-nowrap text-[20px] font-normal text-[#40352a]"
                style="font-family:Georgia,'Times New Roman',serif;">
                POS BILLS
            </h2>
            <div class="w-full h-[2px] bg-[#d1a14b]"></div>
            <a href="pos-billing.php"
               class="shrink-0 flex items-center gap-2 bg-[#103258] hover:bg-[#1A3F66]
                      text-white text-sm px-4 py-2 rounded-xl transition font-medium">
                <i data-lucide="scan-line" class="w-4 h-4"></i> New Bill
            </a>
        </div>

        <!-- Filters -->
        <div class="bg-white rounded-2xl shadow-sm p-5 mb-5">
            <form method="GET" class="grid md:grid-cols-4 gap-4 items-end">
                <div>
                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">Search</label>
                    <input type="text" name="search" value="<?= e($search) ?>" placeholder="Name, ID…"
                           class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm
                                  bg-[#F8FAFC] focus:outline-none focus:ring-2 focus:ring-[#103258]">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">From</label>
                    <input type="date" name="date_from" value="<?= e($df) ?>"
                           class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm
                                  bg-[#F8FAFC] focus:outline-none focus:ring-2 focus:ring-[#103258]">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">To</label>
                    <input type="date" name="date_to" value="<?= e($dt) ?>"
                           class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm
                                  bg-[#F8FAFC] focus:outline-none focus:ring-2 focus:ring-[#103258]">
                </div>
                <div class="flex gap-2">
                    <button type="submit"
                            class="flex-1 bg-[#103258] hover:bg-[#1A3F66] text-white rounded-xl
                                   py-2 text-sm font-semibold transition">Filter</button>
                    <a href="pos-bills.php"
                       class="px-4 py-2 border border-gray-200 rounded-xl text-sm
                              text-gray-500 hover:bg-gray-50 transition">Reset</a>
                </div>
            </form>
        </div>

        <!-- Table -->
        <div class="bg-white rounded-2xl shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="bill-table">
                    <thead>
                        <tr>
                            <th>Bill No</th>
                            <th>Member</th>
                            <th>Amount</th>
                            <th>Payment</th>
                            <th>Date &amp; Time</th>
                            <th style="text-align:center">Print</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($orders)): ?>
                        <tr>
                            <td colspan="6" class="text-center py-16 text-gray-400">
                                <div class="flex flex-col items-center gap-2">
                                    <i data-lucide="inbox" class="w-10 h-10 opacity-25"></i>
                                    <span class="text-sm">No bills found</span>
                                </div>
                            </td>
                        </tr>
                    <?php else: foreach ($orders as $o): ?>
                        <tr>
                            <td class="font-mono font-bold text-[#103258] text-sm">
                                NTV-<?= str_pad($o['id'],6,'0',STR_PAD_LEFT) ?>
                            </td>
                            <td>
                                <div class="font-medium text-sm"><?= e($o['member_name']) ?></div>
                                <?php if ($o['member_id']): ?>
                                    <div class="text-xs text-gray-400"><?= e($o['member_id']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="font-bold text-[#103258] text-sm">
                                ₹ <?= number_format($o['total_amount'],2) ?>
                            </td>
                            <td>
                                <span class="bg-blue-50 text-blue-700 px-2.5 py-0.5 rounded-full
                                             text-xs font-semibold capitalize">
                                    <?= e($o['payment_method']) ?>
                                </span>
                            </td>
                            <td class="text-gray-500 text-sm">
                                <?= date('d M Y, h:i A', strtotime($o['created_at'])) ?>
                            </td>
                            <td class="text-center">
                                <a href="pos-invoice.php?order_id=<?= $o['id'] ?>" target="_blank"
                                   class="inline-flex items-center gap-1 text-[#103258] hover:text-[#1A3F66]
                                          border border-[#103258]/20 rounded-lg px-3 py-1 text-xs
                                          font-semibold hover:bg-[#103258]/5 transition">
                                    <i data-lucide="printer" class="w-3.5 h-3.5"></i> Print
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($total_pages > 1): ?>
            <div class="px-5 py-4 border-t border-gray-100 flex items-center justify-between text-sm text-gray-500">
                <span><?= $total_rows ?> bill(s)</span>
                <div class="flex gap-1.5">
                    <?php for ($p=1; $p<=$total_pages; $p++): ?>
                        <a href="?page=<?= $p ?>&search=<?= urlencode($search) ?>&date_from=<?= urlencode($df) ?>&date_to=<?= urlencode($dt) ?>"
                           class="px-3 py-1 rounded-lg text-sm <?= $p===$page ? 'bg-[#103258] text-white' : 'hover:bg-gray-100' ?> transition">
                            <?= $p ?>
                        </a>
                    <?php endfor; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>

    </main>
</div>

<script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
<script>document.addEventListener('DOMContentLoaded',()=>lucide.createIcons());</script>
</body>
</html>