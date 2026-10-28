<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/helpers.php';

if (!function_exists('e')) {
    function e($v) {
        return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    }
}

function resolveProductRequestLogin($conn) {
    $typeRaw = strtolower(trim((string)(
        $_SESSION['franchise_type']  ??
        $_SESSION['franchies_type']  ??
        ''
    )));

    $values = array_filter(array_map('trim', [
        (string)($_SESSION['mobile_franchise_id']       ?? ''),
        (string)($_SESSION['mobile_franchise_code']     ?? ''),
        (string)($_SESSION['mobile_franchies_holder_id']?? ''),
        (string)($_SESSION['mobile_franchies_id']       ?? ''),
        (string)($_SESSION['city_franchies_holder_id']  ?? ''),
        (string)($_SESSION['state_franchies_holder_id'] ?? ''),
        (string)($_SESSION['franchise_db_id']           ?? ''),
        (string)($_SESSION['franchise_id']              ?? ''),
        (string)($_SESSION['franchies_id']              ?? ''),
        (string)($_SESSION['franchise_code']            ?? ''),
        (string)($_SESSION['franchise_login_id']        ?? ''),
        (string)($_SESSION['customer_id']               ?? ''),
    ]), fn($v) => $v !== '');

    if (str_contains($typeRaw, 'mobile') || !empty($_SESSION['mobile_franchise_id']) || !empty($_SESSION['mobile_franchies_id']) || !empty($_SESSION['mobile_franchies_holder_id'])) {
        $order = ['mobile', 'city', 'state'];
    } elseif (str_contains($typeRaw, 'city') || !empty($_SESSION['city_franchies_holder_id'])) {
        $order = ['city', 'state', 'mobile'];
    } else {
        $order = ['state', 'city', 'mobile'];
    }

    $tableMap = [
        'mobile' => ['table' => 'mobile_franchies_holder', 'codeCol' => 'franchies_id', 'nameCol' => 'full_name'],
        'city'   => ['table' => 'city_franchies_holder',   'codeCol' => 'franchies_id', 'nameCol' => 'full_name'],
        'state'  => ['table' => 'franchies_holder',        'codeCol' => 'franchies_id', 'nameCol' => 'name'],
    ];

    foreach ($order as $type) {
        $map     = $tableMap[$type];
        $table   = $map['table'];
        $codeCol = $map['codeCol'];
        $nameCol = $map['nameCol'];

        $res = $conn->query("SHOW TABLES LIKE '" . $conn->real_escape_string($table) . "'");
        if (!$res || $res->num_rows === 0) continue;

        foreach ($values as $val) {
            if (is_numeric($val)) {
                $id = (int)$val;
                $stmt = $conn->prepare("SELECT * FROM `$table` WHERE id = ? LIMIT 1");
                if ($stmt) {
                    $stmt->bind_param("i", $id);
                    $stmt->execute();
                    $row = $stmt->get_result()->fetch_assoc();
                    $stmt->close();
                    if ($row) {
                        return ['type'=>$type,'id'=>(int)$row['id'],'code'=>(string)($row[$codeCol] ?? $val),'name'=>(string)($row[$nameCol] ?? 'Franchise')];
                    }
                }
            }
            $stmt = $conn->prepare("SELECT * FROM `$table` WHERE `$codeCol` = ? LIMIT 1");
            if ($stmt) {
                $stmt->bind_param("s", $val);
                $stmt->execute();
                $row = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                if ($row) {
                    return ['type'=>$type,'id'=>(int)$row['id'],'code'=>(string)($row[$codeCol] ?? $val),'name'=>(string)($row[$nameCol] ?? 'Franchise')];
                }
            }
        }
    }
    return null;
}

$loginInfo = resolveProductRequestLogin($conn);
$franchiseType = 'state';
$franchiseId = 0;
$franchiseMemberName = '';
if (!$loginInfo) {
    $sessType = strtolower(trim((string)($_SESSION['franchise_type'] ?? $_SESSION['franchies_type'] ?? '')));
    if ($sessType === '') {
        if (!empty($_SESSION['mobile_franchise_id']) || !empty($_SESSION['mobile_franchies_id']) || !empty($_SESSION['mobile_franchies_holder_id'])) {
            $sessType = 'mobile';
        } elseif (!empty($_SESSION['city_franchies_holder_id']) || !empty($_SESSION['city_franchise_id'])) {
            $sessType = 'city';
        } elseif (!empty($_SESSION['franchise_id']) || !empty($_SESSION['franchies_id'])) {
            $sessType = 'state';
        }
    }
    $possibleIds = [
        $_SESSION['mobile_franchise_id']       ?? null,
        $_SESSION['mobile_franchies_id']       ?? null,
        $_SESSION['mobile_franchies_holder_id']?? null,
        $_SESSION['city_franchies_holder_id']  ?? null,
        $_SESSION['state_franchies_holder_id'] ?? null,
        $_SESSION['franchise_id']              ?? null,
        $_SESSION['franchies_id']              ?? null,
        $_SESSION['franchise_db_id']           ?? null,
    ];
    $fallbackId = 0;
    foreach ($possibleIds as $v) {
        if ($v === null) continue;
        if (is_numeric($v)) { $fallbackId = (int)$v; break; }
        if (preg_match('/(\d+)/', (string)$v, $m)) { $fallbackId = (int)$m[1]; break; }
    }
    if ($sessType !== '' && $fallbackId > 0) {
        $franchiseType = $sessType;
        $franchiseId   = $fallbackId;
        $franchiseMemberName = (string)($_SESSION['franchise_name'] ?? $_SESSION['full_name'] ?? $_SESSION['name'] ?? 'Franchise');
        $_SESSION['franchise_id']   = $_SESSION['franchise_id'] ?? $franchiseId;
        $_SESSION['franchise_type'] = $_SESSION['franchise_type'] ?? $franchiseType;
    } else {
        if (file_exists(__DIR__ . '/auth.php')) {
            require_once __DIR__ . '/auth.php';
        } else {
            require_once __DIR__ . '/includes/auth.php';
        }
    }
} else {
    $franchiseType = $loginInfo['type'];
    $franchiseId   = (int)$loginInfo['id'];
    $franchiseMemberName = (string)$loginInfo['name'];
    $_SESSION['franchise_id']       = $_SESSION['franchise_id']       ?? $franchiseId;
    $_SESSION['franchise_type']     = $_SESSION['franchise_type']     ?? $franchiseType;
    $_SESSION['franchise_login_id'] = $_SESSION['franchise_login_id'] ?? ($loginInfo['code'] ?? $franchiseId);
    $_SESSION['franchise_code']     = $_SESSION['franchise_code']     ?? ($loginInfo['code'] ?? $franchiseId);
    $_SESSION['franchise_name']     = $_SESSION['franchise_name']     ?? $franchiseMemberName;
}

$_SESSION['franchise_id']       = $_SESSION['franchise_id']       ?? $franchiseId;
$_SESSION['franchise_type']     = $_SESSION['franchise_type']     ?? $franchiseType;
$_SESSION['franchise_login_id'] = $_SESSION['franchise_login_id'] ?? ($loginInfo['code'] ?? $franchiseId);
$_SESSION['franchise_code']     = $_SESSION['franchise_code']     ?? ($loginInfo['code'] ?? $franchiseId);
$_SESSION['franchise_name']     = $_SESSION['franchise_name']     ?? $franchiseMemberName;

if (!isset($conn) || !($conn instanceof mysqli)) {
    die("Database connection failed.");
}

switch ($franchiseType) {
    case 'mobile':
        $paymentTable        = 'mobile_franchies_payment';
        $productRequestTable = 'city_mobile_order';
        $franchiseTable      = 'mobile_franchies_holder';
        break;
    case 'city':
        $paymentTable        = 'city_franchies_payment';
        $productRequestTable = 'city_mobile_order';
        $franchiseTable      = 'city_franchies_holder';
        break;
    default:
        $paymentTable        = 'franchies_payment';
        $productRequestTable = 'franchies_product_request';
        $franchiseTable      = 'franchies_holder';
        break;
}


function detectFranchiseIdColumn($conn, string $table): string {
    $res = $conn->query("SHOW COLUMNS FROM `" . $conn->real_escape_string($table) . "`");
    if (!$res) return 'franchise_id';
    $priority = ['franchise_id','mobile_franchies_id','city_franchies_id','franchies_id','member_id','id'];
    $cols = [];
    while ($r = $res->fetch_assoc()) $cols[] = $r['Field'];
    foreach ($priority as $c) {
        if (in_array($c, $cols, true)) return $c;
    }
    return 'franchise_id';
}

$paymentFranchiseCol = 'franchise_id';
$requestFranchiseCol = 'franchise_id';

$payTblExists = $conn->query("SHOW TABLES LIKE '" . $conn->real_escape_string($paymentTable) . "'")->num_rows > 0;
$reqTblExists = $conn->query("SHOW TABLES LIKE '" . $conn->real_escape_string($productRequestTable) . "'")->num_rows > 0;


$franchiseMemberName = '';
$franchiseMemberId   = '';

$tableCheckRes = $conn->query("SHOW TABLES LIKE '" . $conn->real_escape_string($franchiseTable) . "'");
if ($tableCheckRes && $tableCheckRes->num_rows > 0) {
    $colRes   = $conn->query("SHOW COLUMNS FROM `" . $conn->real_escape_string($franchiseTable) . "`");
    $colNames = [];
    $pkCol    = 'id';
    if ($colRes) {
        while ($colRow = $colRes->fetch_assoc()) {
            $colNames[] = $colRow['Field'];
            if ($colRow['Key'] === 'PRI') { $pkCol = $colRow['Field']; }
        }
    }
    $nameCol = '';
    foreach (['full_name', 'owner_name', 'name', 'franchise_name', 'contact_name'] as $c) {
        if (in_array($c, $colNames)) { $nameCol = $c; break; }
    }
    $memberIdCol = '';
    foreach (['franchies_id', 'franchise_id', 'mobile_franchies_id', 'city_franchies_id', 'member_id', 'customer_id', 'id'] as $c) {
        if (in_array($c, $colNames)) { $memberIdCol = $c; break; }
    }
    if ($nameCol !== '' || $memberIdCol !== '') {
        $selectParts = array_filter([
            $nameCol     !== '' ? "`$nameCol`"     : '',
            $memberIdCol !== '' ? "`$memberIdCol`" : '',
        ]);
        $selectCols = implode(', ', $selectParts);
        $franchiseInfo = null;
        $fi = $conn->prepare("SELECT $selectCols FROM `$franchiseTable` WHERE `$pkCol` = ? LIMIT 1");
        if ($fi) {
            $fi->bind_param('i', $franchiseId);
            $fi->execute();
            $franchiseInfo = $fi->get_result()->fetch_assoc();
            $fi->close();
        }
        if (!$franchiseInfo) {
            foreach (['franchise_id', 'mobile_franchies_id', 'city_franchies_id'] as $altCol) {
                if (in_array($altCol, $colNames)) {
                    $fi2 = $conn->prepare("SELECT $selectCols FROM `$franchiseTable` WHERE `$altCol` = ? LIMIT 1");
                    if ($fi2) {
                        $fi2->bind_param('i', $franchiseId);
                        $fi2->execute();
                        $franchiseInfo = $fi2->get_result()->fetch_assoc();
                        $fi2->close();
                        if ($franchiseInfo) break;
                    }
                }
            }
        }
        if ($franchiseInfo) {
            $franchiseMemberName = (string)($franchiseInfo[$nameCol]     ?? '');
            $franchiseMemberId   = (string)($franchiseInfo[$memberIdCol] ?? '');
        }
    }
}

if ($franchiseMemberName === '') {
    $franchiseMemberName = (string)($_SESSION['franchise_name'] ?? $_SESSION['full_name'] ?? $_SESSION['name'] ?? 'Franchise Member');
}
if ($franchiseMemberId === '') {
    $franchiseMemberId = (string)($_SESSION['franchise_code'] ?? $_SESSION['franchise_login_id'] ?? $_SESSION['member_id'] ?? $_SESSION['franchise_member_id'] ?? $franchiseId);
}

// Create tables if missing
foreach ([
    "CREATE TABLE IF NOT EXISTS `mobile_franchies_payment` (
        `id` INT(11) NOT NULL AUTO_INCREMENT, `order_id` VARCHAR(50) NULL,
        `franchise_id` INT(11) NULL, `total_amount` DECIMAL(12,2) NULL DEFAULT 0.00,
        `bank_name` VARCHAR(100) NULL, `payment_mode` VARCHAR(50) NULL,
        `utr_number` VARCHAR(100) NULL, `receipt` VARCHAR(255) NULL,
        `remark` TEXT NULL, `status` VARCHAR(50) NULL DEFAULT 'Pending',
        `created_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
        `franchise_code` VARCHAR(50) NULL, PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    "CREATE TABLE IF NOT EXISTS `mobile_franchies_product_request` (
        `id` INT(11) NOT NULL AUTO_INCREMENT, `order_id` VARCHAR(50) NULL,
        `franchise_id` INT(11) NULL, `category` VARCHAR(150) NULL,
        `product_name` VARCHAR(255) NULL, `offer_price` DECIMAL(12,2) NULL DEFAULT 0.00,
        `quantity` INT(11) NULL DEFAULT 1, `total_amount` DECIMAL(12,2) NULL DEFAULT 0.00,
        `status` VARCHAR(50) NULL DEFAULT 'Pending',
        `created_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
        `franchise_code` VARCHAR(50) NULL, PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    "CREATE TABLE IF NOT EXISTS `city_franchies_payment` (
        `id` INT(11) NOT NULL AUTO_INCREMENT, `order_id` VARCHAR(50) NULL,
        `franchise_id` INT(11) NULL, `total_amount` DECIMAL(12,2) NULL DEFAULT 0.00,
        `bank_name` VARCHAR(100) NULL, `payment_mode` VARCHAR(50) NULL,
        `utr_number` VARCHAR(100) NULL, `receipt` VARCHAR(255) NULL,
        `remark` TEXT NULL, `status` VARCHAR(50) NULL DEFAULT 'Pending',
        `created_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
        `franchise_code` VARCHAR(50) NULL, PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    "CREATE TABLE IF NOT EXISTS `city_franchies_product_request` (
        `id` INT(11) NOT NULL AUTO_INCREMENT, `order_id` VARCHAR(50) NULL,
        `franchise_id` INT(11) NULL, `category` VARCHAR(150) NULL,
        `product_name` VARCHAR(255) NULL, `offer_price` DECIMAL(12,2) NULL DEFAULT 0.00,
        `quantity` INT(11) NULL DEFAULT 1, `total_amount` DECIMAL(12,2) NULL DEFAULT 0.00,
        `status` VARCHAR(50) NULL DEFAULT 'Pending',
        `created_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
        `franchise_code` VARCHAR(50) NULL, PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    "CREATE TABLE IF NOT EXISTS `city_mobile_order` (
        `id` INT(11) NOT NULL AUTO_INCREMENT, `order_id` VARCHAR(50) NULL,
        `franchise_id` INT(11) NULL, `franchise_type` VARCHAR(20) NULL,
        `category` VARCHAR(150) NULL,
        `product_name` VARCHAR(255) NULL, `offer_price` DECIMAL(12,2) NULL DEFAULT 0.00,
        `quantity` INT(11) NULL DEFAULT 1, `total_amount` DECIMAL(12,2) NULL DEFAULT 0.00,
        `status` VARCHAR(50) NULL DEFAULT 'Pending',
        `created_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
        `franchise_code` VARCHAR(50) NULL, PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
] as $sql) { $conn->query($sql); }

// NOW detect real column names after tables guaranteed to exist
$paymentFranchiseCol = detectFranchiseIdColumn($conn, $paymentTable);
$requestFranchiseCol = detectFranchiseIdColumn($conn, $productRequestTable);


$allFranchiseIds = array_unique(array_filter(array_map('intval', [
    $franchiseId,
    (int)($_SESSION['mobile_franchise_id']        ?? 0),
    (int)($_SESSION['mobile_franchies_id']         ?? 0),
    (int)($_SESSION['mobile_franchies_holder_id']  ?? 0),
    (int)($_SESSION['city_franchies_holder_id']    ?? 0),
    (int)($_SESSION['state_franchies_holder_id']   ?? 0),
    (int)($_SESSION['franchise_id']                ?? 0),
    (int)($_SESSION['franchies_id']                ?? 0),
    (int)($_SESSION['franchise_db_id']             ?? 0),
])));
if (empty($allFranchiseIds)) $allFranchiseIds = [$franchiseId];

// Build IN clause placeholders
$inPlaceholders = implode(',', array_fill(0, count($allFranchiseIds), '?'));
$inTypes        = str_repeat('i', count($allFranchiseIds));

// -----------------------------------------------------------------------

function statusClass(string $status): string {
    $n = strtolower(trim($status));
    if ($n === 'approved') return 'text-[#0ca44f]';
    if ($n === 'pending')  return 'text-[#f97316]';
    if ($n === 'rejected') return 'text-[#ef4444]';
    return 'text-[#5b6470]';
}

function getReceiptUrl($receipt) {
    $receipt = trim((string)$receipt);
    if ($receipt === '') return '';
    $base  = basename($receipt);
    $paths = [
        [__DIR__ . '/' . ltrim($receipt, '/'),        ltrim($receipt, '/')],
        [__DIR__ . '/uploads/receipts/' . $base,      'uploads/receipts/' . $base],
        [__DIR__ . '/../uploads/receipts/' . $base,   '../uploads/receipts/' . $base],
    ];
    foreach ($paths as $p) {
        if (file_exists($p[0])) return $p[1];
    }
    return ltrim($receipt, '/');
}


function fetchOrderProducts($conn, $productRequestTable, $orderId) {
    $products = [];
    $stmt = $conn->prepare("
        SELECT product_name, offer_price, quantity, total_amount
        FROM `$productRequestTable`
        WHERE order_id = ?
        ORDER BY id ASC
    ");
    if ($stmt) {
        $stmt->bind_param("s", $orderId);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $products[] = [
                'product_name' => $row['product_name'] ?? '',
                'quantity'     => (int)($row['quantity']   ?? 0),
                'price'        => (float)($row['offer_price']  ?? 0),
                'total_price'  => (float)($row['total_amount'] ?? 0),
            ];
        }
        $stmt->close();
    }
    return $products;
}


if (isset($_GET['view_invoice']) && $_GET['view_invoice'] !== '') {

    $invoiceOrderId = trim($_GET['view_invoice']);

    $placeholders = implode(',', array_fill(0, count($allFranchiseIds), '?'));
    $stmt = $conn->prepare("SELECT * FROM `$paymentTable` WHERE order_id = ? AND `$paymentFranchiseCol` IN ($placeholders) LIMIT 1");
    if (!$stmt) die("Invoice error: " . $conn->error);
    $bindParams = array_merge([$invoiceOrderId], $allFranchiseIds);
    $bindTypes  = 's' . str_repeat('i', count($allFranchiseIds));
    $bindRef    = [$bindTypes];
    foreach ($bindParams as $k => $v) $bindRef[] = &$bindParams[$k];
    call_user_func_array([$stmt, 'bind_param'], $bindRef);
    $stmt->execute();
    $order = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$order) {
        $stmt2 = $conn->prepare("SELECT * FROM `$paymentTable` WHERE order_id = ? LIMIT 1");
        if ($stmt2) {
            $stmt2->bind_param("s", $invoiceOrderId);
            $stmt2->execute();
            $order = $stmt2->get_result()->fetch_assoc();
            $stmt2->close();
        }
    }

    if (!$order) die("Invoice not found.");

    $products    = fetchOrderProducts($conn, $productRequestTable, $invoiceOrderId);
    // ── CHANGED: Use total_amount directly — no CGST/SGST calculation ──
    $grandTotal  = round((float)($order['total_amount'] ?? 0), 2);

    $companyAddress = '123, Business Hub, Ring Road, Surat - 395002, Gujarat, India';
    $companyEmail   = 'support@netavexa.com';
    $companyPhone   = '+91 98765 43210';
    $companyGST     = 'GSTIN: 24XXXXX0000X1ZZ';

    $typeBadgeMap = [
        'mobile' => ['label' => '📱 Mobile Franchise', 'color' => '#065f46', 'bg' => '#d1fae5'],
        'city'   => ['label' => '🏙 City Franchise',   'color' => '#1e3a5f', 'bg' => '#dbeafe'],
        'state'  => ['label' => '🏛 State Franchise',  'color' => '#92400e', 'bg' => '#fef3c7'],
    ];
    $typeBadge = $typeBadgeMap[$franchiseType] ?? $typeBadgeMap['state'];

    header("Content-Type: text/html; charset=UTF-8");
    header("Cache-Control: no-cache");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Invoice — <?php echo e($invoiceOrderId); ?></title>
<style>
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Inter',Arial,sans-serif;background:#f0f2f5;color:#1a1a2e;min-height:100vh;padding:20px 12px 60px}
.invoice-wrap{max-width:820px;margin:0 auto;background:#fff;border-radius:16px;box-shadow:0 8px 40px rgba(0,0,0,.10);overflow:hidden}
.inv-header{background:linear-gradient(135deg,#103258 0%,#1a4d80 100%);padding:24px 28px 22px;display:flex;justify-content:space-between;align-items:flex-start;gap:20px;flex-wrap:wrap}
.inv-brand{display:flex;flex-direction:column;gap:12px;flex:1;min-width:220px}
.inv-brand-top{display:flex;align-items:center;gap:14px}
.inv-logo{height:44px;width:auto;max-width:170px;object-fit:contain;flex-shrink:0}
.inv-logo-text{height:52px;min-width:110px;background:rgba(255,255,255,.15);border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:17px;font-weight:800;color:#c89741;letter-spacing:1px;padding:0 14px;flex-shrink:0}
.inv-brand-title{font-size:22px;font-weight:700;color:#fff;letter-spacing:-.5px;line-height:1.2}
.inv-company-info{display:flex;flex-direction:column;gap:6px}
.inv-ci{display:inline-flex;align-items:center;gap:6px;font-size:11.5px;color:rgba(200,223,240,0.95);font-weight:500}
.inv-ci svg{flex-shrink:0;opacity:.85}
.inv-ci a{color:rgba(200,223,240,0.95);text-decoration:none}
.inv-ci a:hover{color:#fff;text-decoration:underline}
.inv-gst-badge{display:inline-block;background:rgba(200,151,65,.22);border:1px solid rgba(200,151,65,.45);color:#f5d98a;font-size:10.5px;font-weight:700;padding:3px 12px;border-radius:10px;letter-spacing:.4px;width:fit-content}
.inv-meta{display:flex;flex-direction:column;align-items:flex-end;gap:8px;flex-shrink:0;text-align:right}
.inv-meta-row{font-size:12.5px;color:#c8dff0}
.inv-meta-row strong{color:#fff}
.status-badge{display:inline-block;padding:5px 14px;border-radius:20px;font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.5px}
.status-badge.approved{background:#dcfce7;color:#16a34a}
.status-badge.pending{background:#fef9c3;color:#b45309}
.status-badge.rejected{background:#fee2e2;color:#dc2626}
.type-badge{display:inline-block;padding:5px 14px;border-radius:16px;font-size:11px;font-weight:700;letter-spacing:.3px}
.inv-body{padding:26px 28px}
.member-section{display:flex;align-items:center;gap:14px;margin-bottom:22px;padding-bottom:18px;border-bottom:2px solid #f0f2f5;flex-wrap:wrap}
.member-avatar{width:46px;height:46px;border-radius:50%;background:linear-gradient(135deg,#103258,#c89741);display:flex;align-items:center;justify-content:center;font-size:19px;font-weight:700;color:#fff;flex-shrink:0}
.member-info h3{font-size:17px;font-weight:700;color:#103258;line-height:1.2}
.member-info .id-badge{display:inline-flex;align-items:center;gap:4px;background:#f0f6ff;border:1px solid #c3d9f5;color:#103258;font-size:11px;font-weight:700;padding:3px 10px;border-radius:20px;margin-top:5px;letter-spacing:.5px}
.divider{height:1px;background:linear-gradient(90deg,#c89741,#e8d5a3);margin:20px 0}
.order-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:12px 24px;margin-bottom:22px}
.order-item label{font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.7px;color:#8a9ab0}
.order-item p{font-size:14px;font-weight:600;color:#1a1a2e;margin-top:2px;word-break:break-all}
.section-label{font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.8px;color:#103258;margin-bottom:10px;display:flex;align-items:center;gap:8px}
.section-label::after{content:'';flex:1;height:1px;background:#e2e8f0}
.table-wrap{overflow-x:auto;-webkit-overflow-scrolling:touch}
table{width:100%;border-collapse:collapse;font-size:13px;min-width:400px}
thead tr{background:linear-gradient(90deg,#103258,#1a4d80);color:#fff}
thead th{padding:11px 12px;text-align:left;font-weight:600;font-size:11px;white-space:nowrap}
thead th:first-child{border-radius:8px 0 0 0}
thead th:last-child{border-radius:0 8px 0 0;text-align:right}
tbody tr{border-bottom:1px solid #f0f2f5}
tbody tr:hover{background:#f8fafc}
tbody td{padding:11px 12px;color:#374151;vertical-align:middle}
tbody td:last-child{text-align:right;font-weight:600}
.totals-wrap{display:flex;justify-content:flex-end;margin-top:20px}
.totals-table{width:100%;max-width:300px;border:1px solid #e2e8f0;border-radius:12px;overflow:hidden}
/* ── CHANGED: simplified total rows — no tax rows ── */
.t-row{display:flex;justify-content:space-between;align-items:center;padding:10px 16px;font-size:13px;border-bottom:1px solid #f0f2f5;color:#374151}
.t-row:last-child{border-bottom:none}
.t-row.tax-note{background:#fffbf0;color:#92400e;font-size:11.5px;font-style:italic;justify-content:center;text-align:center;padding:8px 16px}
.t-row.grand{background:linear-gradient(135deg,#103258,#1a4d80);color:#fff;font-size:15px;font-weight:700;padding:13px 16px}
.t-row.grand span:last-child{color:#c89741;font-size:17px}
.inv-footer{background:#f8fafc;border-top:1px dashed #d1d5db;padding:16px 28px;text-align:center;font-size:11px;color:#9ca3af;line-height:1.6}
.inv-footer strong{color:#103258}
.print-bar{max-width:820px;margin:0 auto 12px;display:flex;justify-content:flex-end;padding:0 4px}
.btn-print{background:#103258;color:#fff;border:none;border-radius:8px;padding:9px 20px;font-size:13px;font-weight:600;cursor:pointer;display:inline-flex;align-items:center;gap:6px}
.btn-print:hover{background:#1a4d80}
@media(max-width:600px){body{padding:10px 6px 40px}.inv-header{padding:16px 14px;gap:14px}.inv-meta{align-items:flex-start;text-align:left}.inv-logo{height:38px}.inv-body{padding:16px 14px}.order-grid{grid-template-columns:1fr;gap:10px}.totals-wrap{justify-content:stretch}.totals-table{max-width:100%}.print-bar{justify-content:stretch}.btn-print{width:100%;justify-content:center}.inv-ci{font-size:10.5px}.inv-brand-title{font-size:18px}}
@media print{body{background:#fff;padding:0}.invoice-wrap{box-shadow:none;border-radius:0}.no-print{display:none!important}.inv-header{-webkit-print-color-adjust:exact;print-color-adjust:exact}}
</style>
</head>
<body>
<div class="print-bar no-print">
    <button class="btn-print" onclick="window.print()">🖨️ Print / Save PDF</button>
</div>
<div class="invoice-wrap">
    <div class="inv-header">
        <div class="inv-brand">
            <div class="inv-brand-top">
                <img src="../assets/electrinc-logo.png" alt="Electrinc Logo" class="inv-logo"
                     onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
                <div class="inv-logo-text" style="display:none;">ELECTRINC</div>
                <span class="inv-brand-title">ELECTRINC INVOICE</span>
            </div>
            <div class="inv-company-info">
                <span class="inv-ci">
                    <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    <?php echo e($companyAddress); ?>
                </span>
                <span class="inv-ci">
                    <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                    <a href="mailto:<?php echo e($companyEmail); ?>"><?php echo e($companyEmail); ?></a>
                </span>
                <span class="inv-ci">
                    <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                    <?php echo e($companyPhone); ?>
                </span>
                <span class="inv-gst-badge"><?php echo e($companyGST); ?></span>
            </div>
        </div>
        <div class="inv-meta">
            <div class="inv-meta-row"><strong>Invoice Date:</strong> <?php echo date('d-m-Y'); ?></div>
            <div class="inv-meta-row"><strong>Franchise Type:</strong></div>
            <span class="type-badge" style="background:<?php echo $typeBadge['bg']; ?>;color:<?php echo $typeBadge['color']; ?>;"><?php echo e($typeBadge['label']); ?></span>
            <?php
            $st    = strtolower(trim($order['status'] ?? 'pending'));
            $stCls = $st === 'approved' ? 'approved' : ($st === 'rejected' ? 'rejected' : 'pending');
            ?>
            <span class="status-badge <?php echo $stCls; ?>"><?php echo e($order['status'] ?? 'Pending'); ?></span>
        </div>
    </div>

    <div class="inv-body">
        <div class="member-section">
            <div class="member-avatar"><?php echo strtoupper(substr($franchiseMemberName ?: 'F', 0, 1)); ?></div>
            <div class="member-info">
                <h3><?php echo e($franchiseMemberName ?: 'Franchise Member'); ?></h3>
                <span class="id-badge">
                    <svg xmlns="http://www.w3.org/2000/svg" width="10" height="10" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0"/></svg>
                    <?php echo e($franchiseMemberId ?: 'N/A'); ?>
                </span>
            </div>
        </div>

        <p class="section-label">Order Information</p>
        <div class="order-grid">
            <div class="order-item"><label>Order ID</label><p><?php echo e($order['order_id'] ?? '-'); ?></p></div>
            <div class="order-item"><label>Order Date</label><p><?php echo !empty($order['created_at']) ? e(date('d-m-Y', strtotime($order['created_at']))) : '-'; ?></p></div>
            <div class="order-item"><label>Payment Mode</label><p><?php echo e($order['payment_mode'] ?? '-'); ?></p></div>
            <div class="order-item"><label>UTR Number</label><p><?php echo e($order['utr_number'] ?? '-'); ?></p></div>
            <?php if (!empty($order['bank_name'])): ?><div class="order-item"><label>Bank Name</label><p><?php echo e($order['bank_name']); ?></p></div><?php endif; ?>
            <?php if (!empty($order['remark'])): ?><div class="order-item"><label>Remark</label><p><?php echo e($order['remark']); ?></p></div><?php endif; ?>
        </div>

        <div class="divider"></div>

        <p class="section-label">Products</p>
        <div class="table-wrap">
            <table>
                <thead><tr><th>#</th><th>Product Name</th><th>Quantity</th><th>Unit Price</th><th>Total Price</th></tr></thead>
                <tbody>
                <?php if (!empty($products)): ?>
                    <?php foreach ($products as $i => $p): ?>
                    <tr>
                        <td><?php echo $i + 1; ?></td>
                        <td><?php echo e($p['product_name']); ?></td>
                        <td><?php echo (int)$p['quantity']; ?></td>
                        <td>&#8377;<?php echo number_format((float)$p['price'], 2); ?></td>
                        <td>&#8377;<?php echo number_format((float)$p['total_price'], 2); ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="5" style="text-align:center;color:#9ca3af;padding:24px;">No products found.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- ── CHANGED: Removed CGST/SGST rows. Shows total + "All taxes included" note ── -->
        <div class="totals-wrap">
            <div class="totals-table">
                <div class="t-row tax-note">&#9432;&nbsp; All taxes included in the total amount</div>
                <div class="t-row grand"><span>Total Amount</span><span>&#8377;<?php echo number_format($grandTotal, 2); ?></span></div>
            </div>
        </div>
    </div>

    <div class="inv-footer">
        Thank you for choosing <strong>NETAVEXA</strong> Franchise Services. &nbsp;|&nbsp; This is a system-generated invoice.
    </div>
</div>
</body>
</html>
<?php
    exit();
}

/* =========================================================
   PAGINATION SETUP
========================================================= */
$statusFilter = trim((string)($_GET['status'] ?? 'All'));
$search       = trim((string)($_GET['search'] ?? ''));
$currentPage  = max(1, (int)($_GET['page'] ?? 1));
$perPage      = 10;

$baseWhere      = "fp.`$paymentFranchiseCol` IN ($inPlaceholders)";
$baseTypes      = $inTypes;
$baseParams     = $allFranchiseIds;

$countSql    = "SELECT COUNT(DISTINCT fp.id) AS total
                FROM `$paymentTable` fp
                LEFT JOIN `$productRequestTable` fpr ON fp.order_id = fpr.order_id
                WHERE $baseWhere";
$countParams = $baseParams;
$countTypes  = $baseTypes;

if ($statusFilter !== '' && strtolower($statusFilter) !== 'all') {
    $countSql   .= " AND LOWER(fp.status) = ?";
    $countParams[] = strtolower($statusFilter);
    $countTypes   .= "s";
}
if ($search !== '') {
    $like          = '%' . $search . '%';
    $countSql     .= " AND (fp.order_id LIKE ? OR fp.utr_number LIKE ? OR fp.payment_mode LIKE ? OR fpr.product_name LIKE ?)";
    $countParams[] = $like; $countParams[] = $like; $countParams[] = $like; $countParams[] = $like;
    $countTypes   .= "ssss";
}
$totalRows = 0;
$stmtCount = $conn->prepare($countSql);
if ($stmtCount) {
    $bindC = [$countTypes];
    foreach ($countParams as $k => $v) $bindC[] = &$countParams[$k];
    call_user_func_array([$stmtCount, 'bind_param'], $bindC);
    $stmtCount->execute();
    $totalRows = (int)($stmtCount->get_result()->fetch_assoc()['total'] ?? 0);
    $stmtCount->close();
}
$totalPages  = max(1, (int)ceil($totalRows / $perPage));
if ($currentPage > $totalPages) $currentPage = $totalPages;
$offset = ($currentPage - 1) * $perPage;

$rows  = [];
$sql   = "
    SELECT fp.id, fp.order_id, fp.`$paymentFranchiseCol` AS franchise_id,
       fp.total_amount, fp.payment_mode, fp.utr_number,
       fp.receipt, fp.remark,
       MAX(fpr.status) AS status,
       fp.created_at,
       GROUP_CONCAT(fpr.product_name SEPARATOR ', ') AS product_names,
       COALESCE(SUM(fpr.quantity), 0) AS total_products
    FROM `$paymentTable` fp
    LEFT JOIN `$productRequestTable` fpr ON fp.order_id = fpr.order_id
    WHERE $baseWhere
";
$params = $baseParams;
$types  = $baseTypes;

if ($statusFilter !== '' && strtolower($statusFilter) !== 'all') {
    $sql    .= " AND LOWER(fp.status) = ?";
    $params[] = strtolower($statusFilter);
    $types   .= "s";
}
if ($search !== '') {
    $like     = '%' . $search . '%';
    $sql     .= " AND (fp.order_id LIKE ? OR fp.utr_number LIKE ? OR fp.payment_mode LIKE ? OR fpr.product_name LIKE ?)";
    $params[] = $like; $params[] = $like; $params[] = $like; $params[] = $like;
    $types   .= "ssss";
}
$sql .= " GROUP BY fp.id, fp.order_id, fp.`$paymentFranchiseCol`,
         fp.total_amount, fp.payment_mode, fp.utr_number,
         fp.receipt, fp.remark, fp.created_at
          ORDER BY fp.id DESC LIMIT ? OFFSET ?";
$params[] = $perPage;
$params[] = $offset;
$types   .= "ii";

$stmtRows = $conn->prepare($sql);
if ($stmtRows) {
    $bv = [$types];
    foreach ($params as $k => $v) $bv[] = &$params[$k];
    call_user_func_array([$stmtRows, 'bind_param'], $bv);
    $stmtRows->execute();
    $res = $stmtRows->get_result();
    while ($r = $res->fetch_assoc()) $rows[] = $r;
    $stmtRows->close();
}

$productDetails = [];
foreach ($rows as $row) {
    $oid = (string)$row['order_id'];
    $productDetails[$oid] = fetchOrderProducts($conn, $productRequestTable, $oid);
}

$tabs = ['All' => 'All', 'Pending' => 'Pending', 'Approved' => 'Approved', 'Rejected' => 'Rejected'];

function pageUrl($page, $status, $search) {
    $q = ['page' => $page, 'status' => $status];
    if ($search !== '') $q['search'] = $search;
    return '?' . http_build_query($q);
}

$typeBadgeMap = [
    'mobile' => ['label' => '📱 Mobile Franchise', 'cls' => 'bg-green-100 text-green-800 border border-green-300'],
    'city'   => ['label' => '🏙 City Franchise',   'cls' => 'bg-blue-100 text-blue-800 border border-blue-300'],
    'state'  => ['label' => '🏛 State Franchise',  'cls' => 'bg-yellow-100 text-yellow-800 border border-yellow-300'],
];
$typeBadge = $typeBadgeMap[$franchiseType] ?? $typeBadgeMap['state'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Product Requests</title>
<script src="https://cdn.tailwindcss.com"></script>
<script src="https://unpkg.com/lucide@latest"></script>
<style>
@media(max-width:768px){.desk-table{display:none!important}.mob-cards{display:block!important}}
@media(min-width:769px){.desk-table{display:block!important}.mob-cards{display:none!important}}
.mob-card{background:#fff;border-radius:12px;box-shadow:0 1px 6px rgba(0,0,0,.07);padding:14px;margin-bottom:10px}
.mob-row{display:flex;justify-content:space-between;align-items:flex-start;padding:5px 0;border-bottom:1px solid #f3f4f6;font-size:13px;gap:8px}
.mob-row:last-child{border-bottom:none}
.mob-label{color:#8a9ab0;font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.5px;white-space:nowrap;flex-shrink:0;padding-top:1px}
.mob-val{color:#1a1a2e;font-weight:600;text-align:right;word-break:break-all}
.pg-wrap{display:flex;flex-wrap:wrap;align-items:center;justify-content:center;gap:5px;margin-top:18px}
.pg-btn{min-width:34px;height:34px;padding:0 9px;border-radius:8px;font-size:13px;font-weight:600;border:1px solid #ddd;background:#fff;color:#40352a;cursor:pointer;display:inline-flex;align-items:center;justify-content:center;text-decoration:none;transition:all .15s}
.pg-btn:hover{background:#f5eee3;border-color:#c89741;color:#c89741}
.pg-btn.active{background:#c89741;color:#fff;border-color:#c89741}
.pg-btn.disabled{opacity:.4;pointer-events:none}
.pg-dots{font-size:13px;color:#aaa;padding:0 2px;line-height:34px}
</style>
</head>
<body class="bg-[#f1f2f4] font-sans overflow-x-hidden">

<?php include __DIR__ . '/includes/sidebar.php'; ?>

<div class="ml-64 min-h-screen max-lg:ml-0">
    <?php include __DIR__ . '/includes/navbar.php'; ?>

    <main class="px-4 py-5 sm:px-5 sm:py-6">
        <div class="max-w-[1500px] mx-auto">

            <div class="mt-4 mb-4 flex items-center gap-3">
                <div class="w-[34px] h-[34px] rounded-[8px] bg-[#f5eee3] flex items-center justify-center shrink-0">
                    <i data-lucide="badge-check" class="w-[17px] h-[17px] text-[#cd9737]"></i>
                </div>
                <h2 class="whitespace-nowrap text-[16px] sm:text-[20px] font-normal text-[#40352a]"
                    style="font-family:Georgia,'Times New Roman',serif;">
                    PRODUCT REQUESTS
                </h2>
                <div class="w-full h-[2px] bg-[#d1a14b]"></div>
            </div>

            <div class="flex flex-wrap items-center gap-2 mb-4 text-[13px]">
                <span class="text-[#6b7280]">
                    Welcome, <strong class="text-[#1a1a2e]"><?php echo e($franchiseMemberName); ?></strong>
                    &nbsp;·&nbsp;
                    ID: <span class="font-mono font-bold text-[#103258]"><?php echo e($franchiseMemberId); ?></span>
                </span>
                <span class="px-3 py-1 rounded-full text-[11px] font-bold <?php echo $typeBadge['cls']; ?>">
                    <?php echo e($typeBadge['label']); ?>
                </span>
            </div>

            <div class="rounded-[16px] border border-[#e4e4e4] bg-[#f7f7f7] p-4 sm:p-6">

                <div class="flex flex-col gap-3 mb-4">
                    <div class="flex flex-wrap gap-2">
                        <?php foreach ($tabs as $tv => $tl): ?>
                            <a href="<?php echo e(pageUrl(1, $tv, $search)); ?>"
                               class="px-4 py-2 rounded-[8px] text-[12px] sm:text-[13px] font-semibold border
                               <?php echo strtolower($statusFilter) === strtolower($tv) || ($statusFilter === '' && $tv === 'All')
                                   ? 'bg-[#c89741] text-white border-[#c89741]'
                                   : 'bg-white text-[#40352a] border-[#dddddd]'; ?>">
                                <?php echo e($tl); ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                    <form method="get" class="flex flex-wrap sm:flex-nowrap items-center gap-2">
                        <input type="hidden" name="status" value="<?php echo e($statusFilter ?: 'All'); ?>">
                        <input type="hidden" name="page" value="1">
                        <input type="text" name="search" value="<?php echo e($search); ?>"
                            placeholder="Search order, UTR, product..."
                            class="flex-1 min-w-[180px] rounded-[8px] border border-[#ddd] bg-white px-4 py-2 text-[13px] outline-none focus:border-[#c89741]">
                        <button type="submit"
                            class="rounded-[8px] bg-[#103258] px-5 py-2 text-white text-[13px] font-semibold whitespace-nowrap hover:bg-[#1a4d80]">
                            Search
                        </button>
                        <?php if ($search !== ''): ?>
                        <a href="<?php echo e(pageUrl(1, $statusFilter, '')); ?>"
                           class="rounded-[8px] bg-[#ef4444] px-4 py-2 text-white text-[13px] font-semibold whitespace-nowrap hover:bg-red-600">
                            Clear
                        </a>
                        <?php endif; ?>
                    </form>
                    <div class="text-[12px] text-[#7a7a7a]">
                        Total <strong class="text-[#40352a]"><?php echo $totalRows; ?></strong> records
                        <?php if ($totalRows > 0): ?>
                        &nbsp;·&nbsp; Showing
                        <strong class="text-[#40352a]"><?php echo $offset + 1; ?></strong>–<strong class="text-[#40352a]"><?php echo min($offset + $perPage, $totalRows); ?></strong>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- DESKTOP TABLE -->
                <div class="desk-table overflow-x-auto">
                    <table class="w-full min-w-[1050px] border-separate border-spacing-y-3">
                        <thead>
                            <tr class="bg-[#c89741] text-white text-[12px]">
                                <th class="px-4 py-3 text-left rounded-l-[10px]">Sr</th>
                                <th class="px-4 py-3 text-left">Order ID</th>
                                <th class="px-4 py-3 text-left">Products</th>
                                <th class="px-4 py-3 text-left">Total Qty</th>
                                <th class="px-4 py-3 text-left">Amount</th>
                                <th class="px-4 py-3 text-left">Payment Mode</th>
                                <th class="px-4 py-3 text-left">UTR Number</th>
                                <th class="px-4 py-3 text-left">Receipt</th>
                                <th class="px-4 py-3 text-left">Invoice</th>
                                <th class="px-4 py-3 text-left">Status</th>
                                <th class="px-4 py-3 text-left rounded-r-[10px]">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (!empty($rows)): ?>
                            <?php foreach ($rows as $idx => $row): ?>
                            <?php
                                $oid    = (string)$row['order_id'];
                                $recUrl = getReceiptUrl($row['receipt'] ?? '');
                                $invUrl = '?view_invoice=' . urlencode($oid) . '&status=' . urlencode($statusFilter) . '&search=' . urlencode($search);
                            ?>
                            <tr class="bg-white text-[13px] text-[#2f2f2f] shadow-sm">
                                <td class="px-4 py-3 rounded-l-[10px]"><?php echo $offset + $idx + 1; ?></td>
                                <td class="px-4 py-3 font-semibold text-[#103258]"><?php echo e($oid); ?></td>
                                <td class="px-4 py-3">
                                    <button onclick='openProductsModal(<?php echo json_encode($oid); ?>)'
                                        class="rounded-[7px] bg-[#103258] px-3 py-1.5 text-white text-[11px] font-semibold hover:bg-[#1a4d80]">
                                        View
                                    </button>
                                </td>
                                <td class="px-4 py-3"><?php echo e($row['total_products'] ?? '0'); ?></td>
                                <td class="px-4 py-3 font-semibold">&#8377;<?php echo number_format((float)($row['total_amount'] ?? 0), 2); ?></td>
                                <td class="px-4 py-3"><?php echo e($row['payment_mode'] ?? '-'); ?></td>
                                <td class="px-4 py-3"><?php echo e($row['utr_number'] ?? '-'); ?></td>
                                <td class="px-4 py-3">
                                    <?php if ($recUrl !== ''): ?>
                                        <a href="<?php echo e($recUrl); ?>" target="_blank"
                                           class="text-blue-600 font-semibold hover:underline text-[12px]">View</a>
                                    <?php else: ?>—<?php endif; ?>
                                </td>
                                <td class="px-4 py-3">
                                    <a href="<?php echo e($invUrl); ?>" target="_blank"
                                       class="inline-flex items-center gap-1 rounded-[7px] bg-[#16a34a] px-3 py-1.5 text-white text-[11px] font-semibold hover:bg-[#15803d]">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="10" height="10" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                                        Open
                                    </a>
                                </td>
                                <td class="px-4 py-3 font-semibold <?php echo e(statusClass((string)($row['status'] ?? 'Pending'))); ?>">
                                    <?php echo e($row['status'] ?? 'Pending'); ?>
                                </td>
                                <td class="px-4 py-3 rounded-r-[10px]">
                                    <button onclick='openOrderModal(<?php echo json_encode($oid); ?>)'
                                        class="rounded-[7px] bg-[#103258] px-3 py-1.5 text-white text-[11px] font-semibold hover:bg-[#1a4d80]">
                                        Details
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="11" class="h-[100px] text-center text-[14px] font-semibold text-[#b0b0b0]">
                                    No records found.
                                </td>
                            </tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- MOBILE CARDS -->
                <div class="mob-cards">
                    <?php if (!empty($rows)): ?>
                        <?php foreach ($rows as $idx => $row): ?>
                        <?php
                            $oid    = (string)$row['order_id'];
                            $recUrl = getReceiptUrl($row['receipt'] ?? '');
                            $invUrl = '?view_invoice=' . urlencode($oid) . '&status=' . urlencode($statusFilter) . '&search=' . urlencode($search);
                            $st     = strtolower($row['status'] ?? 'pending');
                            $sBadge = $st === 'approved'
                                ? 'bg-green-100 text-green-700'
                                : ($st === 'rejected' ? 'bg-red-100 text-red-700' : 'bg-orange-100 text-orange-700');
                        ?>
                        <div class="mob-card">
                            <div class="flex items-center justify-between mb-3 pb-2 border-b border-gray-100">
                                <div>
                                    <div class="text-[10px] text-[#8a9ab0] font-semibold uppercase tracking-wide">Order ID</div>
                                    <div class="text-[14px] font-bold text-[#103258]"><?php echo e($oid); ?></div>
                                </div>
                                <span class="px-3 py-1 rounded-full text-[11px] font-bold <?php echo $sBadge; ?>">
                                    <?php echo e($row['status'] ?? 'Pending'); ?>
                                </span>
                            </div>
                            <div class="mob-row"><span class="mob-label">Amount</span><span class="mob-val text-[#103258]">&#8377;<?php echo number_format((float)($row['total_amount'] ?? 0), 2); ?></span></div>
                            <div class="mob-row"><span class="mob-label">Total Qty</span><span class="mob-val"><?php echo e($row['total_products'] ?? '0'); ?></span></div>
                            <div class="mob-row"><span class="mob-label">Payment</span><span class="mob-val"><?php echo e($row['payment_mode'] ?? '-'); ?></span></div>
                            <div class="mob-row"><span class="mob-label">UTR</span><span class="mob-val"><?php echo e($row['utr_number'] ?? '-'); ?></span></div>
                            <div class="mob-row"><span class="mob-label">Date</span><span class="mob-val"><?php echo !empty($row['created_at']) ? e(date('d-m-Y', strtotime($row['created_at']))) : '-'; ?></span></div>
                            <div class="flex flex-wrap gap-2 mt-3 pt-3 border-t border-gray-100">
                                <button onclick='openProductsModal(<?php echo json_encode($oid); ?>)'
                                    class="flex-1 min-w-[70px] rounded-[8px] bg-[#103258] py-2 text-white text-[11px] font-semibold text-center">
                                    Products
                                </button>
                                <button onclick='openOrderModal(<?php echo json_encode($oid); ?>)'
                                    class="flex-1 min-w-[70px] rounded-[8px] bg-[#103258] py-2 text-white text-[11px] font-semibold text-center">
                                    Details
                                </button>
                                <a href="<?php echo e($invUrl); ?>" target="_blank"
                                    class="flex-1 min-w-[70px] rounded-[8px] bg-[#16a34a] py-2 text-white text-[11px] font-semibold text-center">
                                    Invoice
                                </a>
                                <?php if ($recUrl !== ''): ?>
                                <a href="<?php echo e($recUrl); ?>" target="_blank"
                                    class="flex-1 min-w-[70px] rounded-[8px] bg-blue-600 py-2 text-white text-[11px] font-semibold text-center">
                                    Receipt
                                </a>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-center py-10 text-[14px] font-semibold text-[#b0b0b0]">No records found.</div>
                    <?php endif; ?>
                </div>

                <!-- PAGINATION -->
                <?php if ($totalPages > 1): ?>
                <div class="pg-wrap">
                    <a href="<?php echo e(pageUrl($currentPage - 1, $statusFilter, $search)); ?>"
                       class="pg-btn <?php echo $currentPage <= 1 ? 'disabled' : ''; ?>">&#8592; Prev</a>
                    <?php
                    $sp = max(1, $currentPage - 2);
                    $ep = min($totalPages, $currentPage + 2);
                    if ($sp > 1): ?>
                        <a href="<?php echo e(pageUrl(1, $statusFilter, $search)); ?>" class="pg-btn">1</a>
                        <?php if ($sp > 2): ?><span class="pg-dots">…</span><?php endif; ?>
                    <?php endif; ?>
                    <?php for ($p = $sp; $p <= $ep; $p++): ?>
                        <a href="<?php echo e(pageUrl($p, $statusFilter, $search)); ?>"
                           class="pg-btn <?php echo $p === $currentPage ? 'active' : ''; ?>"><?php echo $p; ?></a>
                    <?php endfor; ?>
                    <?php if ($ep < $totalPages):
                        if ($ep < $totalPages - 1): ?><span class="pg-dots">…</span><?php endif; ?>
                        <a href="<?php echo e(pageUrl($totalPages, $statusFilter, $search)); ?>" class="pg-btn"><?php echo $totalPages; ?></a>
                    <?php endif; ?>
                    <a href="<?php echo e(pageUrl($currentPage + 1, $statusFilter, $search)); ?>"
                       class="pg-btn <?php echo $currentPage >= $totalPages ? 'disabled' : ''; ?>">Next &#8594;</a>
                </div>
                <div class="text-center text-[12px] text-[#9ca3af] mt-2">
                    Page <?php echo $currentPage; ?> of <?php echo $totalPages; ?>
                </div>
                <?php endif; ?>

            </div>
        </div>
    </main>
</div>

<!-- PRODUCTS MODAL -->
<div id="productsModal" class="fixed inset-0 z-[9999] hidden items-center justify-center bg-black/50 px-3">
    <div class="w-full max-w-[680px] rounded-[14px] bg-white shadow-xl overflow-hidden max-h-[90vh] flex flex-col">
        <div class="flex items-center justify-between bg-[#103258] px-5 py-4 shrink-0">
            <h3 class="text-white text-[15px] font-semibold">Product Details</h3>
            <button onclick="closeProductsModal()" class="text-white text-[24px] leading-none hover:opacity-70">&times;</button>
        </div>
        <div class="p-4 overflow-auto">
            <div class="overflow-x-auto">
                <table class="w-full border border-gray-200 min-w-[360px]">
                    <thead>
                        <tr class="bg-[#c89741] text-white text-[12px]">
                            <th class="px-3 py-2 text-left">Product Name</th>
                            <th class="px-3 py-2 text-left">Qty</th>
                            <th class="px-3 py-2 text-left">Price</th>
                            <th class="px-3 py-2 text-left">Total</th>
                        </tr>
                    </thead>
                    <tbody id="productsModalBody"></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- ORDER MODAL -->
<div id="orderModal" class="fixed inset-0 z-[9999] hidden items-center justify-center bg-black/50 px-3">
    <div class="w-full max-w-[520px] rounded-[14px] bg-white shadow-xl overflow-hidden max-h-[90vh] flex flex-col">
        <div class="flex items-center justify-between bg-[#103258] px-5 py-4 shrink-0">
            <h3 class="text-white text-[15px] font-semibold">Order Details</h3>
            <button onclick="closeOrderModal()" class="text-white text-[24px] leading-none hover:opacity-70">&times;</button>
        </div>
        <div id="orderModalBody" class="p-5 overflow-auto"></div>
    </div>
</div>

<script>
if (typeof lucide !== 'undefined') lucide.createIcons();
const productDetails = <?php echo json_encode($productDetails); ?>;
const orderRows      = <?php echo json_encode($rows); ?>;

function openProductsModal(orderId) {
    const body = document.getElementById('productsModalBody');
    const rows = productDetails[orderId] || [];
    body.innerHTML = rows.length
        ? rows.map(item => `
            <tr class="border-b border-gray-200 text-[12px]">
                <td class="px-3 py-2">${esc(item.product_name)}</td>
                <td class="px-3 py-2">${item.quantity}</td>
                <td class="px-3 py-2">&#8377;${Number(item.price).toFixed(2)}</td>
                <td class="px-3 py-2 font-semibold">&#8377;${Number(item.total_price).toFixed(2)}</td>
            </tr>`).join('')
        : `<tr><td colspan="4" class="px-3 py-5 text-center text-gray-400 text-[13px]">No products found.</td></tr>`;
    show('productsModal');
}
function closeProductsModal() { hide('productsModal'); }

function openOrderModal(orderId) {
    const body = document.getElementById('orderModalBody');
    const row  = orderRows.find(r => String(r.order_id) === String(orderId));
    body.innerHTML = row ? `
        <div class="grid grid-cols-2 gap-3 text-[13px]">
            <div><div class="text-[10px] text-gray-400 font-semibold uppercase tracking-wide mb-1">Order ID</div>
                 <div class="font-semibold text-[#103258]">${esc(row.order_id)}</div></div>
            <div><div class="text-[10px] text-gray-400 font-semibold uppercase tracking-wide mb-1">Total Qty</div>
                 <div class="font-semibold">${esc(row.total_products)}</div></div>
            <div><div class="text-[10px] text-gray-400 font-semibold uppercase tracking-wide mb-1">Amount</div>
                 <div class="font-semibold text-[#103258]">&#8377;${Number(row.total_amount||0).toFixed(2)}</div></div>
            <div><div class="text-[10px] text-gray-400 font-semibold uppercase tracking-wide mb-1">Payment Mode</div>
                 <div class="font-semibold">${esc(row.payment_mode||'-')}</div></div>
            <div><div class="text-[10px] text-gray-400 font-semibold uppercase tracking-wide mb-1">UTR Number</div>
                 <div class="font-semibold">${esc(row.utr_number||'-')}</div></div>
            <div><div class="text-[10px] text-gray-400 font-semibold uppercase tracking-wide mb-1">Status</div>
                 <div class="font-semibold">${esc(row.status||'Pending')}</div></div>
            <div><div class="text-[10px] text-gray-400 font-semibold uppercase tracking-wide mb-1">Date</div>
                 <div class="font-semibold">${esc(row.created_at||'-')}</div></div>
            <div><div class="text-[10px] text-gray-400 font-semibold uppercase tracking-wide mb-1">Remark</div>
                 <div class="font-semibold">${esc(row.remark||'-')}</div></div>
        </div>` : `<p class="text-red-500 font-semibold text-[13px]">Order not found.</p>`;
    show('orderModal');
}
function closeOrderModal() { hide('orderModal'); }

function show(id) { const el = document.getElementById(id); el.classList.remove('hidden'); el.classList.add('flex'); }
function hide(id) { document.getElementById(id).classList.replace('flex','hidden'); }
function esc(t) {
    return String(t??'').replaceAll('&','&amp;').replaceAll('<','&lt;').replaceAll('>','&gt;').replaceAll('"','&quot;').replaceAll("'",'&#039;');
}
['productsModal','orderModal'].forEach(id => {
    document.getElementById(id).addEventListener('click', function(e){ if(e.target===this) hide(id); });
});
</script>
</body>
</html>