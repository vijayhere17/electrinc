<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/config.php';

if (!isset($conn) || !($conn instanceof mysqli)) {
    die("Database connection failed.");
}

function e($v) {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function money($v) {
    return '₹' . number_format((float)$v, 2);
}

function tableExists($conn, $table) {
    $stmt = $conn->prepare("
        SELECT COUNT(*) total 
        FROM INFORMATION_SCHEMA.TABLES 
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?
    ");
    $stmt->bind_param("s", $table);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return (int)$row['total'] > 0;
}

function colExists($conn, $table, $col) {
    if (!tableExists($conn, $table)) return false;

    $stmt = $conn->prepare("
        SELECT COUNT(*) total 
        FROM INFORMATION_SCHEMA.COLUMNS 
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?
    ");
    $stmt->bind_param("ss", $table, $col);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return (int)$row['total'] > 0;
}

function findCol($conn, $table, $cols) {
    foreach ($cols as $c) {
        if (colExists($conn, $table, $c)) return $c;
    }
    return '';
}

$conn->query("
CREATE TABLE IF NOT EXISTS franchise_stock_transaction (
    id INT AUTO_INCREMENT PRIMARY KEY,
    request_no VARCHAR(80) NOT NULL UNIQUE,
    requester_type ENUM('state','city','mobile') NOT NULL,
    requester_id VARCHAR(80) NOT NULL,
    requester_name VARCHAR(150) DEFAULT NULL,
    supplier_type ENUM('admin','state','city') NOT NULL,
    supplier_id VARCHAR(80) DEFAULT NULL,
    product_id INT NOT NULL,
    product_name VARCHAR(180) NOT NULL,
    quantity INT NOT NULL DEFAULT 0,
    price DECIMAL(12,2) NOT NULL DEFAULT 0,
    total_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    status ENUM('Pending','Approved','Rejected','Handover','Activated') NOT NULL DEFAULT 'Pending',
    remark TEXT DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

$conn->query("
CREATE TABLE IF NOT EXISTS franchise_stock_wallet (
    id INT AUTO_INCREMENT PRIMARY KEY,
    franchise_type ENUM('state','city','mobile') NOT NULL,
    franchise_id VARCHAR(80) NOT NULL,
    product_id INT NOT NULL,
    product_name VARCHAR(180) NOT NULL,
    stock_qty INT NOT NULL DEFAULT 0,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_stock (franchise_type, franchise_id, product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

$conn->query("
CREATE TABLE IF NOT EXISTS franchise_commission_ledger (
    id INT AUTO_INCREMENT PRIMARY KEY,
    request_no VARCHAR(80) NOT NULL,
    franchise_type ENUM('state','city','mobile') NOT NULL,
    franchise_id VARCHAR(80) NOT NULL,
    commission_percent DECIMAL(8,2) NOT NULL DEFAULT 0,
    turnover_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    commission_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

function resolveLoggedFranchise($conn) {
    $typeRaw = strtolower(trim((string)($_SESSION['franchise_type'] ?? $_SESSION['franchies_type'] ?? '')));

    $sessionValues = [
        $_SESSION['city_franchies_holder_id'] ?? '',
        $_SESSION['mobile_franchies_holder_id'] ?? '',
        $_SESSION['state_franchies_holder_id'] ?? '',
        $_SESSION['franchise_db_id'] ?? '',
        $_SESSION['franchise_id'] ?? '',
        $_SESSION['franchies_id'] ?? '',
        $_SESSION['franchise_code'] ?? '',
        $_SESSION['franchise_login_id'] ?? '',
        $_SESSION['customer_id'] ?? '',
    ];

    $map = [
        'city' => ['table' => 'city_franchies_holder', 'codeCols' => ['franchies_id'], 'nameCols' => ['full_name']],
        'mobile' => ['table' => 'mobile_franchies_holder', 'codeCols' => ['mobile_franchies_id'], 'nameCols' => ['owner_name','shop_name']],
        'state' => ['table' => 'franchies_holder', 'codeCols' => ['franchies_id'], 'nameCols' => ['name']],
    ];

    $order = ['state','city','mobile'];
    if (strpos($typeRaw, 'city') !== false || !empty($_SESSION['city_franchies_holder_id'])) $order = ['city','state','mobile'];
    if (strpos($typeRaw, 'mobile') !== false || !empty($_SESSION['mobile_franchies_holder_id'])) $order = ['mobile','city','state'];

    foreach ($order as $type) {
        $table = $map[$type]['table'];
        if (!tableExists($conn, $table)) continue;

        foreach ($sessionValues as $v) {
            $v = trim((string)$v);
            if ($v === '') continue;

            if (is_numeric($v) && colExists($conn, $table, 'id')) {
                $id = (int)$v;
                $stmt = $conn->prepare("SELECT * FROM `$table` WHERE id = ? LIMIT 1");
                $stmt->bind_param("i", $id);
                $stmt->execute();
                $row = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                if ($row) {
                    $codeCol = findCol($conn, $table, $map[$type]['codeCols']);
                    $nameCol = findCol($conn, $table, $map[$type]['nameCols']);
                    return [
                        'type' => $type,
                        'id' => (string)($row[$codeCol] ?? $row['id']),
                        'db_id' => (int)$row['id'],
                        'name' => (string)($row[$nameCol] ?? '')
                    ];
                }
            }

            foreach ($map[$type]['codeCols'] as $codeCol) {
                if (!colExists($conn, $table, $codeCol)) continue;
                $stmt = $conn->prepare("SELECT * FROM `$table` WHERE `$codeCol` = ? LIMIT 1");
                $stmt->bind_param("s", $v);
                $stmt->execute();
                $row = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                if ($row) {
                    $nameCol = findCol($conn, $table, $map[$type]['nameCols']);
                    return [
                        'type' => $type,
                        'id' => (string)($row[$codeCol] ?? $v),
                        'db_id' => (int)$row['id'],
                        'name' => (string)($row[$nameCol] ?? '')
                    ];
                }
            }
        }
    }

    return null;
}

$loginFranchise = resolveLoggedFranchise($conn);
if (!$loginFranchise) {
    header("Location: index.php");
    exit;
}

$franchiseType = $loginFranchise['type'];
$franchiseId = $loginFranchise['id'];
$franchiseDbId = $loginFranchise['db_id'];
$franchiseName = $loginFranchise['name'];

function getFranchiseName($conn, $type, $id) {
    $tables = [
        'state' => 'franchies_holder',
        'city' => 'city_franchies_holder',
        'mobile' => 'mobile_franchies_holder'
    ];

    $table = $tables[$type] ?? '';
    if ($table === '' || !tableExists($conn, $table)) return $id;

    $codeCol = findCol($conn, $table, ['franchies_id', 'franchise_id', 'franchise_code']);
    $nameCol = findCol($conn, $table, ['name', 'full_name', 'franchise_name']);

    if ($codeCol === '' || $nameCol === '') return $id;

    $stmt = $conn->prepare("SELECT `$nameCol` name FROM `$table` WHERE `$codeCol` = ? LIMIT 1");
    $stmt->bind_param("s", $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $row['name'] ?? $id;
}

function getParentInfo($conn, $type, $id) {
    if ($type === 'state') {
        return ['type' => 'admin', 'id' => 'ADMIN'];
    }

    if ($type === 'city') {
        $table = 'city_franchies_holder';
        if (!tableExists($conn, $table)) return ['type' => 'state', 'id' => ''];

        $codeCol = findCol($conn, $table, ['franchies_id', 'franchise_id', 'franchise_code']);
        $parentCol = findCol($conn, $table, ['state_franchies_id', 'state_franchise_id', 'parent_id', 'state_id']);

        if ($codeCol === '' || $parentCol === '') return ['type' => 'state', 'id' => ''];

        $stmt = $conn->prepare("SELECT `$parentCol` parent_id FROM `$table` WHERE `$codeCol` = ? LIMIT 1");
        $stmt->bind_param("s", $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return ['type' => 'state', 'id' => $row['parent_id'] ?? ''];
    }

    if ($type === 'mobile') {
        $table = 'mobile_franchies_holder';
        if (!tableExists($conn, $table)) return ['type' => 'city', 'id' => ''];

        $codeCol = findCol($conn, $table, ['franchies_id', 'franchise_id', 'franchise_code']);
        $parentCol = findCol($conn, $table, ['city_franchies_id', 'city_franchise_id', 'parent_id', 'city_id']);

        if ($codeCol === '' || $parentCol === '') return ['type' => 'city', 'id' => ''];

        $stmt = $conn->prepare("SELECT `$parentCol` parent_id FROM `$table` WHERE `$codeCol` = ? LIMIT 1");
        $stmt->bind_param("s", $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return ['type' => 'city', 'id' => $row['parent_id'] ?? ''];
    }

    return ['type' => 'admin', 'id' => 'ADMIN'];
}

function addStock($conn, $type, $id, $productId, $productName, $qty) {
    $stmt = $conn->prepare("
        INSERT INTO franchise_stock_wallet 
        (franchise_type, franchise_id, product_id, product_name, stock_qty)
        VALUES (?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE stock_qty = stock_qty + VALUES(stock_qty)
    ");
    $stmt->bind_param("ssisi", $type, $id, $productId, $productName, $qty);
    $stmt->execute();
    $stmt->close();
}

function minusStock($conn, $type, $id, $productId, $qty) {
    $stmt = $conn->prepare("
        UPDATE franchise_stock_wallet
        SET stock_qty = stock_qty - ?
        WHERE franchise_type = ?
        AND franchise_id = ?
        AND product_id = ?
        AND stock_qty >= ?
    ");
    $stmt->bind_param("issii", $qty, $type, $id, $productId, $qty);
    $stmt->execute();
    $ok = $stmt->affected_rows > 0;
    $stmt->close();
    return $ok;
}

function addCommission($conn, $requestNo, $type, $id, $percent, $turnover) {
    if ($id === '' || $id === 'ADMIN') return;

    $commission = round(($turnover * $percent) / 100, 2);

    $stmt = $conn->prepare("
        INSERT INTO franchise_commission_ledger
        (request_no, franchise_type, franchise_id, commission_percent, turnover_amount, commission_amount)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param("sssddd", $requestNo, $type, $id, $percent, $turnover, $commission);
    $stmt->execute();
    $stmt->close();
}

function giveCommissionByFlow($conn, $requestNo, $requesterType, $requesterId, $supplierType, $supplierId, $turnover) {
    if ($requesterType === 'state') {
        addCommission($conn, $requestNo, 'state', $requesterId, 12, $turnover);
        return;
    }

    if ($requesterType === 'city') {
        addCommission($conn, $requestNo, 'city', $requesterId, 10, $turnover);
        addCommission($conn, $requestNo, 'state', $supplierId, 2, $turnover);
        return;
    }

    if ($requesterType === 'mobile') {
        addCommission($conn, $requestNo, 'mobile', $requesterId, 7, $turnover);
        addCommission($conn, $requestNo, 'city', $supplierId, 3, $turnover);

        $cityParent = getParentInfo($conn, 'city', $supplierId);
        addCommission($conn, $requestNo, 'state', $cityParent['id'], 2, $turnover);
    }
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_request'])) {
    $productId = (int)($_POST['product_id'] ?? 0);
    $qty = (int)($_POST['quantity'] ?? 0);

    if ($productId <= 0 || $qty <= 0) {
        $error = "Please select product and quantity.";
    } else {
        $productTable = tableExists($conn, 'franchies_member_product') ? 'franchies_member_product' : 'products';
        $idCol = findCol($conn, $productTable, ['id']);
        $nameCol = findCol($conn, $productTable, ['product_name', 'name', 'title']);
        $priceCol = findCol($conn, $productTable, ['offer_price', 'price', 'amount']);

        $stmt = $conn->prepare("SELECT `$nameCol` product_name, `$priceCol` price FROM `$productTable` WHERE `$idCol` = ? LIMIT 1");
        $stmt->bind_param("i", $productId);
        $stmt->execute();
        $product = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$product) {
            $error = "Product not found.";
        } else {
            $parent = getParentInfo($conn, $franchiseType, $franchiseId);
            $requestNo = 'STK-' . strtoupper(bin2hex(random_bytes(6)));
            $productName = $product['product_name'];
            $price = (float)$product['price'];
            $total = $price * $qty;
            $name = getFranchiseName($conn, $franchiseType, $franchiseId);

            $stmt = $conn->prepare("
                INSERT INTO franchise_stock_transaction
                (
                    request_no, requester_type, requester_id, requester_name,
                    supplier_type, supplier_id,
                    product_id, product_name, quantity, price, total_amount, status
                )
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending')
            ");

            $stmt->bind_param(
                "ssssssisidd",
                $requestNo,
                $franchiseType,
                $franchiseId,
                $name,
                $parent['type'],
                $parent['id'],
                $productId,
                $productName,
                $qty,
                $price,
                $total
            );

            if ($stmt->execute()) {
                $message = "Product stock request created successfully.";
            } else {
                $error = "Request failed.";
            }

            $stmt->close();
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_request'])) {
    $requestId = (int)($_POST['request_id'] ?? 0);
    $status = $_POST['status'] ?? '';

    if (!in_array($status, ['Approved', 'Rejected', 'Handover', 'Activated'], true)) {
        $error = "Invalid status.";
    } else {
        $stmt = $conn->prepare("SELECT * FROM franchise_stock_transaction WHERE id = ? LIMIT 1");
        $stmt->bind_param("i", $requestId);
        $stmt->execute();
        $req = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$req) {
            $error = "Request not found.";
        } elseif ($req['supplier_type'] !== $franchiseType || $req['supplier_id'] !== $franchiseId) {
            $error = "You cannot update this request.";
        } else {
            $canUpdate = true;

            if ($status === 'Activated') {
                if ($franchiseType !== 'admin') {
                    $canUpdate = minusStock($conn, $franchiseType, $franchiseId, (int)$req['product_id'], (int)$req['quantity']);
                }

                if ($canUpdate) {
                    addStock($conn, $req['requester_type'], $req['requester_id'], (int)$req['product_id'], $req['product_name'], (int)$req['quantity']);

                    giveCommissionByFlow(
                        $conn,
                        $req['request_no'],
                        $req['requester_type'],
                        $req['requester_id'],
                        $req['supplier_type'],
                        $req['supplier_id'],
                        (float)$req['total_amount']
                    );
                }
            }

            if (!$canUpdate) {
                $error = "Not enough stock to activate this request.";
            } else {
                $stmt = $conn->prepare("UPDATE franchise_stock_transaction SET status = ? WHERE id = ?");
                $stmt->bind_param("si", $status, $requestId);
                $stmt->execute();
                $stmt->close();

                $message = "Request status updated successfully.";
            }
        }
    }
}

$productTable = tableExists($conn, 'franchies_member_product') ? 'franchies_member_product' : 'products';
$products = [];

if (tableExists($conn, $productTable)) {
    $idCol = findCol($conn, $productTable, ['id']);
    $nameCol = findCol($conn, $productTable, ['product_name', 'name', 'title']);
    $priceCol = findCol($conn, $productTable, ['offer_price', 'price', 'amount']);

    if ($idCol && $nameCol && $priceCol) {
        $res = $conn->query("SELECT `$idCol` id, `$nameCol` product_name, `$priceCol` price FROM `$productTable` ORDER BY `$idCol` DESC");
        while ($r = $res->fetch_assoc()) $products[] = $r;
    }
}

$myStock = $conn->prepare("
    SELECT * FROM franchise_stock_wallet
    WHERE franchise_type = ? AND franchise_id = ?
    ORDER BY id DESC
");
$myStock->bind_param("ss", $franchiseType, $franchiseId);
$myStock->execute();
$stockRows = $myStock->get_result();

$myRequests = $conn->prepare("
    SELECT * FROM franchise_stock_transaction
    WHERE requester_type = ? AND requester_id = ?
    ORDER BY id DESC
");
$myRequests->bind_param("ss", $franchiseType, $franchiseId);
$myRequests->execute();
$myRequestRows = $myRequests->get_result();

$childRequests = $conn->prepare("
    SELECT * FROM franchise_stock_transaction
    WHERE supplier_type = ? AND supplier_id = ?
    ORDER BY id DESC
");
$childRequests->bind_param("ss", $franchiseType, $franchiseId);
$childRequests->execute();
$childRequestRows = $childRequests->get_result();

$commissionRows = $conn->prepare("
    SELECT * FROM franchise_commission_ledger
    WHERE franchise_type = ? AND franchise_id = ?
    ORDER BY id DESC
");
$commissionRows->bind_param("ss", $franchiseType, $franchiseId);
$commissionRows->execute();
$commissionResult = $commissionRows->get_result();

$totalCommission = 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Product Stock Transaction</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<script src="https://cdn.tailwindcss.com"></script>
<script src="https://unpkg.com/lucide@latest"></script>
</head>

<body class="bg-[#f3f4f6] font-sans overflow-x-hidden">

<?php include __DIR__ . '/includes/sidebar.php'; ?>

<div class="ml-64 min-h-screen">
<?php include __DIR__ . '/includes/navbar.php'; ?>

<main class="px-5 py-5">
<div class="max-w-[1600px] mx-auto">

    <div class="mt-5 flex items-center gap-3 mb-6">
        <div class="w-[36px] h-[36px] rounded-[8px] bg-[#f5eee3] flex items-center justify-center shrink-0">
            <i data-lucide="package-check" class="w-[18px] h-[18px] text-[#cd9737]"></i>
        </div>

        <h2 class="whitespace-nowrap text-[20px] font-normal text-[#40352a]" style="font-family: Georgia, 'Times New Roman', serif;">
            PRODUCT STOCK TRANSACTION
        </h2>

        <div class="w-full h-[2px] bg-[#d1a14b]"></div>
    </div>

    <?php if ($message): ?>
        <div class="mb-5 rounded-lg bg-green-100 text-green-700 px-5 py-3"><?= e($message); ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="mb-5 rounded-lg bg-red-100 text-red-700 px-5 py-3"><?= e($error); ?></div>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5 mb-6">
        <div class="bg-white rounded-xl shadow border p-5">
            <h3 class="font-bold text-lg mb-4">Create Product Request</h3>

            <form method="POST" class="space-y-4">
                <div>
                    <label class="block mb-1 font-semibold">Product</label>
                    <select name="product_id" class="w-full border rounded-lg px-4 py-2" required>
                        <option value="">Select Product</option>
                        <?php foreach ($products as $p): ?>
                            <option value="<?= (int)$p['id']; ?>">
                                <?= e($p['product_name']); ?> - <?= money($p['price']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block mb-1 font-semibold">Quantity</label>
                    <input type="number" name="quantity" min="1" class="w-full border rounded-lg px-4 py-2" required>
                </div>

                <button name="create_request" class="w-full bg-[#12395f] text-white px-5 py-2 rounded-lg">
                    Send Request
                </button>
            </form>
        </div>

        <div class="bg-white rounded-xl shadow border p-5">
            <h3 class="font-bold text-lg mb-4">Commission Rule</h3>
            <div class="space-y-3 text-sm">
                <div class="p-3 rounded-lg bg-gray-50">State: <b>12%</b> on total turnover</div>
                <div class="p-3 rounded-lg bg-gray-50">City: <b>10%</b>, remaining <b>2%</b> to State</div>
                <div class="p-3 rounded-lg bg-gray-50">Mobile: <b>7%</b>, <b>3%</b> to City, <b>2%</b> to State</div>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow border p-5">
            <h3 class="font-bold text-lg mb-4">My Stock</h3>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-100">
                    <tr>
                        <th class="px-3 py-2 text-left">Product</th>
                        <th class="px-3 py-2 text-left">Qty</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if ($stockRows->num_rows > 0): ?>
                        <?php while ($s = $stockRows->fetch_assoc()): ?>
                            <tr class="border-b">
                                <td class="px-3 py-2"><?= e($s['product_name']); ?></td>
                                <td class="px-3 py-2 font-bold"><?= (int)$s['stock_qty']; ?></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="2" class="px-3 py-4 text-center text-gray-500">No stock found</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow border overflow-hidden mb-6">
        <div class="px-5 py-4 bg-[#12395f] text-white font-bold">Requests Received From Downline</div>
        <div class="overflow-x-auto">
            <table class="w-full min-w-[1200px]">
                <thead class="bg-gray-100">
                <tr>
                    <th class="px-4 py-3 text-left">Request No</th>
                    <th class="px-4 py-3 text-left">Requester</th>
                    <th class="px-4 py-3 text-left">Product</th>
                    <th class="px-4 py-3 text-left">Qty</th>
                    <th class="px-4 py-3 text-left">Amount</th>
                    <th class="px-4 py-3 text-left">Status</th>
                    <th class="px-4 py-3 text-left">Action</th>
                </tr>
                </thead>
                <tbody>
                <?php if ($childRequestRows->num_rows > 0): ?>
                    <?php while ($r = $childRequestRows->fetch_assoc()): ?>
                        <tr class="border-b">
                            <td class="px-4 py-3 font-semibold"><?= e($r['request_no']); ?></td>
                            <td class="px-4 py-3"><?= e(strtoupper($r['requester_type']) . ' - ' . $r['requester_id']); ?></td>
                            <td class="px-4 py-3"><?= e($r['product_name']); ?></td>
                            <td class="px-4 py-3"><?= (int)$r['quantity']; ?></td>
                            <td class="px-4 py-3"><?= money($r['total_amount']); ?></td>
                            <td class="px-4 py-3"><?= e($r['status']); ?></td>
                            <td class="px-4 py-3">
                                <form method="POST" class="flex gap-2">
                                    <input type="hidden" name="request_id" value="<?= (int)$r['id']; ?>">
                                    <select name="status" class="border rounded px-3 py-2">
                                        <option value="Approved">Approved</option>
                                        <option value="Rejected">Rejected</option>
                                        <option value="Handover">Handover</option>
                                        <option value="Activated">Activated</option>
                                    </select>
                                    <button name="update_request" class="bg-[#12395f] text-white px-4 py-2 rounded">Update</button>
                                </form>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="7" class="text-center py-6 text-gray-500">No received request found</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow border overflow-hidden mb-6">
        <div class="px-5 py-4 bg-[#12395f] text-white font-bold">My Sent Product Requests</div>
        <div class="overflow-x-auto">
            <table class="w-full min-w-[1000px]">
                <thead class="bg-gray-100">
                <tr>
                    <th class="px-4 py-3 text-left">Request No</th>
                    <th class="px-4 py-3 text-left">Supplier</th>
                    <th class="px-4 py-3 text-left">Product</th>
                    <th class="px-4 py-3 text-left">Qty</th>
                    <th class="px-4 py-3 text-left">Amount</th>
                    <th class="px-4 py-3 text-left">Status</th>
                </tr>
                </thead>
                <tbody>
                <?php if ($myRequestRows->num_rows > 0): ?>
                    <?php while ($r = $myRequestRows->fetch_assoc()): ?>
                        <tr class="border-b">
                            <td class="px-4 py-3 font-semibold"><?= e($r['request_no']); ?></td>
                            <td class="px-4 py-3"><?= e(strtoupper($r['supplier_type']) . ' - ' . $r['supplier_id']); ?></td>
                            <td class="px-4 py-3"><?= e($r['product_name']); ?></td>
                            <td class="px-4 py-3"><?= (int)$r['quantity']; ?></td>
                            <td class="px-4 py-3"><?= money($r['total_amount']); ?></td>
                            <td class="px-4 py-3"><?= e($r['status']); ?></td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="6" class="text-center py-6 text-gray-500">No sent request found</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow border overflow-hidden">
        <div class="px-5 py-4 bg-[#12395f] text-white font-bold">My Commission Ledger</div>
        <div class="overflow-x-auto">
            <table class="w-full min-w-[900px]">
                <thead class="bg-gray-100">
                <tr>
                    <th class="px-4 py-3 text-left">Request No</th>
                    <th class="px-4 py-3 text-left">Turnover</th>
                    <th class="px-4 py-3 text-left">%</th>
                    <th class="px-4 py-3 text-left">Commission</th>
                    <th class="px-4 py-3 text-left">Date</th>
                </tr>
                </thead>
                <tbody>
                <?php if ($commissionResult->num_rows > 0): ?>
                    <?php while ($c = $commissionResult->fetch_assoc()): ?>
                        <?php $totalCommission += (float)$c['commission_amount']; ?>
                        <tr class="border-b">
                            <td class="px-4 py-3 font-semibold"><?= e($c['request_no']); ?></td>
                            <td class="px-4 py-3"><?= money($c['turnover_amount']); ?></td>
                            <td class="px-4 py-3"><?= e($c['commission_percent']); ?>%</td>
                            <td class="px-4 py-3 font-bold text-green-700"><?= money($c['commission_amount']); ?></td>
                            <td class="px-4 py-3"><?= e(date('d-m-Y h:i A', strtotime($c['created_at']))); ?></td>
                        </tr>
                    <?php endwhile; ?>
                    <tr class="bg-gray-100 font-bold">
                        <td colspan="3" class="px-4 py-3 text-right">TOTAL COMMISSION</td>
                        <td class="px-4 py-3 text-green-700"><?= money($totalCommission); ?></td>
                        <td></td>
                    </tr>
                <?php else: ?>
                    <tr><td colspan="5" class="text-center py-6 text-gray-500">No commission found</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>
</main>
</div>

<script>
document.addEventListener("DOMContentLoaded", function () {
    if (typeof lucide !== "undefined") lucide.createIcons();
});
</script>

</body>
</html>