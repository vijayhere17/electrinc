<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include __DIR__ . '/config/db.php';
include_once __DIR__ . '/includes/admin_access_control.php';
if (!isset($conn) || !($conn instanceof mysqli)) die('Database connection failed.');
if (function_exists('adminRequireAccessOrRedirect')) adminRequireAccessOrRedirect();
$conn->set_charset('utf8mb4');

function e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function tableExists(mysqli $c, string $t): bool {
    $t = $c->real_escape_string($t);
    $r = $c->query("SHOW TABLES LIKE '$t'");
    return $r instanceof mysqli_result && $r->num_rows > 0;
}

$hasVendors   = tableExists($conn, 'fr_vendors');
$hasCustomers = tableExists($conn, 'fr_customers');

/* franchise code -> name map, for display only (read-only directory, no franchise scoping needed — admin sees all) */
$franchiseNames = [];
if (tableExists($conn, 'franchies_holder')) {
    $r = $conn->query("SELECT COALESCE(franchise_code, franchies_id) AS code, name FROM franchies_holder");
    while ($r && ($x = $r->fetch_assoc())) $franchiseNames[$x['code']] = $x['name'];
}
if (tableExists($conn, 'city_franchies_holder')) {
    $r = $conn->query("SELECT COALESCE(franchise_code, franchies_id) AS code, full_name AS name FROM city_franchies_holder");
    while ($r && ($x = $r->fetch_assoc())) $franchiseNames[$x['code']] = $x['name'];
}

$tab = (string)($_GET['tab'] ?? 'vendors');
if (!in_array($tab, ['vendors', 'customers'], true)) $tab = 'vendors';

$vq = trim((string)($_GET['vq'] ?? ''));
$vendors = [];
if ($hasVendors) {
    $sql = "SELECT * FROM fr_vendors WHERE 1=1";
    $params = []; $types = '';
    if ($vq !== '') {
        $sql .= " AND (vendor_name LIKE ? OR company_name LIKE ? OR mobile LIKE ? OR email LIKE ? OR gst_no LIKE ? OR franchise_code LIKE ?)";
        $like = "%$vq%"; array_push($params, $like, $like, $like, $like, $like, $like); $types .= 'ssssss';
    }
    $sql .= " ORDER BY id DESC LIMIT 500";
    $st = $conn->prepare($sql);
    if ($types !== '') $st->bind_param($types, ...$params);
    $st->execute();
    $rs = $st->get_result();
    while ($row = $rs->fetch_assoc()) $vendors[] = $row;
    $st->close();
}

$cq = trim((string)($_GET['cq'] ?? ''));
$customers = [];
if ($hasCustomers) {
    $sql = "SELECT * FROM fr_customers WHERE 1=1";
    $params = []; $types = '';
    if ($cq !== '') {
        $sql .= " AND (customer_name LIKE ? OR customer_code LIKE ? OR mobile LIKE ? OR email LIKE ? OR franchise_code LIKE ?)";
        $like = "%$cq%"; array_push($params, $like, $like, $like, $like, $like); $types .= 'sssss';
    }
    $sql .= " ORDER BY id DESC LIMIT 500";
    $st = $conn->prepare($sql);
    if ($types !== '') $st->bind_param($types, ...$params);
    $st->execute();
    $rs = $st->get_result();
    while ($row = $rs->fetch_assoc()) $customers[] = $row;
    $st->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vendor &amp; Customer Directory</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <script src="assets/report-export.js"></script>
</head>
<body class="m-0 bg-[#f3f4f6] font-sans">
<div class="ml-64 min-h-screen w-[calc(100%-16rem)]">
    <?php include __DIR__ . '/includes/sidebar.php'; ?>
    <div class="flex-1 min-w-0 flex flex-col">
        <?php include __DIR__ . '/includes/navbar.php'; ?>
        <main class="px-4 sm:px-8 py-5">

            <div class="flex items-center gap-4 mb-6">
                <div class="w-9 h-9 rounded-[8px] border border-[#ead9b5] bg-[#f8f1e7] flex items-center justify-center shrink-0">
                    <i data-lucide="contact" class="w-5 h-5 text-[#c79435]"></i>
                </div>
                <h2 class="text-[22px] sm:text-[24px] font-normal text-[#313131] tracking-wide" style="font-family: Georgia, serif;">VENDOR &amp; CUSTOMER DIRECTORY</h2>
                <div class="h-[1px] bg-[#c9963b] flex-1 hidden sm:block"></div>
            </div>

            <div class="flex gap-2 mb-5">
                <a href="?tab=vendors" class="px-4 py-2 rounded-lg text-[13px] font-semibold <?php echo $tab === 'vendors' ? 'bg-[#17457a] text-white' : 'bg-white border border-gray-300 text-gray-600'; ?>">Vendors / Suppliers</a>
                <a href="?tab=customers" class="px-4 py-2 rounded-lg text-[13px] font-semibold <?php echo $tab === 'customers' ? 'bg-[#17457a] text-white' : 'bg-white border border-gray-300 text-gray-600'; ?>">Customers / Buyers</a>
            </div>

            <?php if ($tab === 'vendors'): ?>

                <?php if (!$hasVendors): ?>
                    <div class="rounded-xl border border-amber-200 bg-amber-50 px-5 py-4 text-[13px] text-amber-800">No vendor data yet.</div>
                <?php else: ?>
                    <form method="get" class="bg-white border border-gray-200 rounded-xl p-4 flex gap-2 mb-5">
                        <input type="hidden" name="tab" value="vendors">
                        <input name="vq" value="<?php echo e($vq); ?>" placeholder="Search vendor / company / mobile / email / GST / franchise code" class="flex-1 border border-gray-300 rounded-lg px-3 py-2 text-[13px]">
                        <button class="bg-[#17457a] text-white rounded-lg px-4 py-2 text-[13px]">Search</button>
                        <?php if ($vq !== ''): ?><a href="?tab=vendors" class="border border-gray-300 bg-white rounded-lg px-4 py-2 text-[13px]">Clear</a><?php endif; ?>
                    </form>

                    <div class="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden">
                        <div class="px-5 py-3 border-b border-gray-100 flex items-center justify-between flex-wrap gap-2">
                            <span class="text-[12px] text-gray-400"><?php echo count($vendors); ?> vendors</span>
                            <button type="button" onclick="frExportTableToExcel('vendorsTable', 'Vendors_Directory.xlsx', 'Vendors')" class="bg-green-600 hover:bg-green-700 text-white px-3 py-1.5 rounded-lg text-[12px] font-semibold flex items-center gap-1.5">
                                <i data-lucide="file-spreadsheet" class="w-4 h-4"></i> Excel
                            </button>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-[13px]" id="vendorsTable">
                                <thead class="bg-gray-50 text-gray-500 text-[12px]">
                                    <tr>
                                        <th class="text-left px-5 py-2.5">Franchise</th>
                                        <th class="text-left px-5 py-2.5">Vendor</th>
                                        <th class="text-left px-5 py-2.5">Company</th>
                                        <th class="text-left px-5 py-2.5">Mobile</th>
                                        <th class="text-left px-5 py-2.5">Email</th>
                                        <th class="text-left px-5 py-2.5">GST</th>
                                        <th class="text-left px-5 py-2.5">City / State</th>
                                        <th class="text-left px-5 py-2.5">Status</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    <?php if (empty($vendors)): ?><tr><td colspan="8" class="px-5 py-8 text-center text-gray-400">No vendors found.</td></tr><?php endif; ?>
                                    <?php foreach ($vendors as $v): ?>
                                        <tr>
                                            <td class="px-5 py-2.5 text-gray-700">
                                                <?php echo e($franchiseNames[$v['franchise_code']] ?? ('Franchise #' . (int)$v['franchise_id'])); ?>
                                                <div class="text-[11px] text-gray-400"><?php echo e($v['franchise_code'] ?: '-'); ?></div>
                                            </td>
                                            <td class="px-5 py-2.5 font-medium text-gray-800"><?php echo e($v['vendor_name']); ?></td>
                                            <td class="px-5 py-2.5 text-gray-600"><?php echo e($v['company_name'] ?: '—'); ?></td>
                                            <td class="px-5 py-2.5 text-gray-600"><?php echo e($v['mobile'] ?: '—'); ?></td>
                                            <td class="px-5 py-2.5 text-gray-600"><?php echo e($v['email'] ?: '—'); ?></td>
                                            <td class="px-5 py-2.5 text-gray-600"><?php echo e($v['gst_no'] ?: '—'); ?></td>
                                            <td class="px-5 py-2.5 text-gray-600"><?php echo e(trim(($v['city'] ?: '') . ($v['state'] ? ', ' . $v['state'] : '')) ?: '—'); ?></td>
                                            <td class="px-5 py-2.5"><span class="text-[11px] rounded-full px-2 py-0.5 <?php echo $v['status'] === 'Active' ? 'bg-green-50 text-green-700' : 'bg-gray-100 text-gray-500'; ?>"><?php echo e($v['status']); ?></span></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>

            <?php else: ?>

                <?php if (!$hasCustomers): ?>
                    <div class="rounded-xl border border-amber-200 bg-amber-50 px-5 py-4 text-[13px] text-amber-800">No customer data yet.</div>
                <?php else: ?>
                    <form method="get" class="bg-white border border-gray-200 rounded-xl p-4 flex gap-2 mb-5">
                        <input type="hidden" name="tab" value="customers">
                        <input name="cq" value="<?php echo e($cq); ?>" placeholder="Search customer / code / mobile / email / franchise code" class="flex-1 border border-gray-300 rounded-lg px-3 py-2 text-[13px]">
                        <button class="bg-[#17457a] text-white rounded-lg px-4 py-2 text-[13px]">Search</button>
                        <?php if ($cq !== ''): ?><a href="?tab=customers" class="border border-gray-300 bg-white rounded-lg px-4 py-2 text-[13px]">Clear</a><?php endif; ?>
                    </form>

                    <div class="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden">
                        <div class="px-5 py-3 border-b border-gray-100 flex items-center justify-between flex-wrap gap-2">
                            <span class="text-[12px] text-gray-400"><?php echo count($customers); ?> customers</span>
                            <button type="button" onclick="frExportTableToExcel('customersTable', 'Customers_Directory.xlsx', 'Customers')" class="bg-green-600 hover:bg-green-700 text-white px-3 py-1.5 rounded-lg text-[12px] font-semibold flex items-center gap-1.5">
                                <i data-lucide="file-spreadsheet" class="w-4 h-4"></i> Excel
                            </button>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-[13px]" id="customersTable">
                                <thead class="bg-gray-50 text-gray-500 text-[12px]">
                                    <tr>
                                        <th class="text-left px-5 py-2.5">Franchise</th>
                                        <th class="text-left px-5 py-2.5">Customer</th>
                                        <th class="text-left px-5 py-2.5">Code</th>
                                        <th class="text-left px-5 py-2.5">Mobile</th>
                                        <th class="text-left px-5 py-2.5">Email</th>
                                        <th class="text-left px-5 py-2.5">City / State</th>
                                        <th class="text-left px-5 py-2.5">Registered</th>
                                        <th class="text-left px-5 py-2.5">Status</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    <?php if (empty($customers)): ?><tr><td colspan="8" class="px-5 py-8 text-center text-gray-400">No customers found.</td></tr><?php endif; ?>
                                    <?php foreach ($customers as $c): ?>
                                        <tr>
                                            <td class="px-5 py-2.5 text-gray-700">
                                                <?php echo e($franchiseNames[$c['franchise_code']] ?? ('Franchise #' . (int)$c['franchise_id'])); ?>
                                                <div class="text-[11px] text-gray-400"><?php echo e($c['franchise_code'] ?: '-'); ?></div>
                                            </td>
                                            <td class="px-5 py-2.5 font-medium text-gray-800"><?php echo e($c['customer_name']); ?></td>
                                            <td class="px-5 py-2.5 text-gray-600"><?php echo e($c['customer_code'] ?: '—'); ?></td>
                                            <td class="px-5 py-2.5 text-gray-600"><?php echo e($c['mobile'] ?: '—'); ?></td>
                                            <td class="px-5 py-2.5 text-gray-600"><?php echo e($c['email'] ?: '—'); ?></td>
                                            <td class="px-5 py-2.5 text-gray-600"><?php echo e(trim(($c['city'] ?: '') . ($c['state'] ? ', ' . $c['state'] : '')) ?: '—'); ?></td>
                                            <td class="px-5 py-2.5 text-gray-500"><?php echo e($c['registered_date'] ?: substr((string)$c['created_at'], 0, 10)); ?></td>
                                            <td class="px-5 py-2.5"><span class="text-[11px] rounded-full px-2 py-0.5 <?php echo $c['status'] === 'Active' ? 'bg-green-50 text-green-700' : 'bg-gray-100 text-gray-500'; ?>"><?php echo e($c['status']); ?></span></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>

            <?php endif; ?>

            <p class="mt-3 text-[11px] text-gray-400">Read-only company-wide view. Vendor/customer records are managed by each franchise from their own panel.</p>

        </main>
    </div>
</div>
<script>if (typeof lucide !== 'undefined') lucide.createIcons();</script>
</body>
</html>
