<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

ini_set('display_errors', 1);
error_reporting(E_ALL);

$dbPaths = [
    __DIR__ . '/config/db.php',
    __DIR__ . '/../config/db.php',
    dirname(__DIR__) . '/config/db.php'
];

$dbLoaded = false;
foreach ($dbPaths as $path) {
    if (file_exists($path)) {
        require_once $path;
        $dbLoaded = true;
        break;
    }
}

if (!$dbLoaded || !isset($conn) || !($conn instanceof mysqli)) {
    die("Database connection file not found.");
}

function e($v) {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function tableExists($conn, $table) {
    $table = $conn->real_escape_string($table);
    $res = $conn->query("SHOW TABLES LIKE '$table'");
    return ($res && $res->num_rows > 0);
}

function findReceiptFile($receipt) {
    $receipt = trim((string)$receipt);
    if ($receipt === '') return '';

    $receipt = str_replace('\\', '/', $receipt);
    $base = basename($receipt);

    $roots = [
        __DIR__,
        dirname(__DIR__),
        $_SERVER['DOCUMENT_ROOT'] ?? '',
        ($_SERVER['DOCUMENT_ROOT'] ?? '') . '/freshiya',
        ($_SERVER['DOCUMENT_ROOT'] ?? '') . '/freshiya_main',
        ($_SERVER['DOCUMENT_ROOT'] ?? '') . '/freshya_member'
    ];

    $paths = [];

    foreach ($roots as $root) {
        if ($root === '') continue;

        $paths[] = $root . '/' . ltrim($receipt, '/');
        $paths[] = $root . '/uploads/' . $base;
        $paths[] = $root . '/uploads/receipts/' . $base;
        $paths[] = $root . '/uploads/payment/' . $base;
        $paths[] = $root . '/uploads/payments/' . $base;
        $paths[] = $root . '/admin/uploads/' . $base;
        $paths[] = $root . '/admin/uploads/receipts/' . $base;
        $paths[] = $root . '/member/uploads/' . $base;
        $paths[] = $root . '/member/uploads/receipts/' . $base;
        $paths[] = $root . '/franchies/uploads/' . $base;
        $paths[] = $root . '/franchies/uploads/receipts/' . $base;
        $paths[] = $root . '/franchise/uploads/' . $base;
        $paths[] = $root . '/franchise/uploads/receipts/' . $base;
    }

    foreach ($paths as $path) {
        $real = realpath($path);
        if ($real && is_file($real)) {
            return $real;
        }
    }

    return '';
}

/* RECEIPT VIEWER */
if (isset($_GET['view_receipt'], $_GET['from'])) {
    $orderId = trim($_GET['view_receipt']);
    $from = trim($_GET['from']);

    if ($from === 'city') {
        $paymentTable = 'city_franchies_payment';
    } elseif ($from === 'mobile') {
        $paymentTable = 'mobile_franchies_payment';
    } else {
        $paymentTable = 'franchies_payment';
    }

    if (!tableExists($conn, $paymentTable)) {
        http_response_code(404);
        echo "Payment table not found.";
        exit;
    }

    $stmt = $conn->prepare("SELECT receipt FROM `$paymentTable` WHERE order_id = ? LIMIT 1");
    $stmt->bind_param("s", $orderId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row || empty($row['receipt'])) {
        http_response_code(404);
        echo "Receipt not found in database.";
        exit;
    }

    $file = findReceiptFile($row['receipt']);

    if ($file === '') {
        http_response_code(404);
        echo "Receipt file not found on server. DB value: " . e($row['receipt']);
        exit;
    }

    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));

    $types = [
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png'  => 'image/png',
        'webp' => 'image/webp',
        'gif'  => 'image/gif',
        'pdf'  => 'application/pdf'
    ];

    header("Content-Type: " . ($types[$ext] ?? 'application/octet-stream'));
    header("Content-Disposition: inline; filename=\"" . basename($file) . "\"");
    header("Content-Length: " . filesize($file));
    readfile($file);
    exit;
}

/* STATUS UPDATE */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['order_id'], $_POST['status'])) {
    $orderId = trim($_POST['order_id']);
    $status  = trim($_POST['status']);
    $from    = trim($_POST['from'] ?? '');

    if (in_array($status, ['Pending', 'Approved', 'Rejected'], true)) {
        $conn->begin_transaction();

        try {
            if ($from === 'city') {
                if (tableExists($conn, 'city_franchies_payment')) {
                    $stmt = $conn->prepare("UPDATE city_franchies_payment SET status=? WHERE order_id=?");
                    $stmt->bind_param("ss", $status, $orderId);
                    $stmt->execute();
                    $stmt->close();
                }

                if (tableExists($conn, 'city_franchies_product_request')) {
                    $stmt = $conn->prepare("UPDATE city_franchies_product_request SET status=? WHERE order_id=?");
                    $stmt->bind_param("ss", $status, $orderId);
                    $stmt->execute();
                    $stmt->close();
                }
            } elseif ($from === 'mobile') {
                if (tableExists($conn, 'mobile_franchies_payment')) {
                    $stmt = $conn->prepare("UPDATE mobile_franchies_payment SET status=? WHERE order_id=?");
                    $stmt->bind_param("ss", $status, $orderId);
                    $stmt->execute();
                    $stmt->close();
                }

                if (tableExists($conn, 'mobile_franchies_product_request')) {
                    $stmt = $conn->prepare("UPDATE mobile_franchies_product_request SET status=? WHERE order_id=?");
                    $stmt->bind_param("ss", $status, $orderId);
                    $stmt->execute();
                    $stmt->close();
                }
            } else {
                if (tableExists($conn, 'franchies_payment')) {
                    $stmt = $conn->prepare("UPDATE franchies_payment SET status=? WHERE order_id=?");
                    $stmt->bind_param("ss", $status, $orderId);
                    $stmt->execute();
                    $stmt->close();
                }

                if (tableExists($conn, 'franchies_product_request')) {
                    $stmt = $conn->prepare("UPDATE franchies_product_request SET status=? WHERE order_id=?");
                    $stmt->bind_param("ss", $status, $orderId);
                    $stmt->execute();
                    $stmt->close();
                }
            }

            /* -------------------------------------------------------
             * STOCK DEDUCTION — runs for BOTH state & city on Approve
             * Deducts quantity from franchies_add_product.
             * Uses GREATEST(..., 0) so stock never goes negative.
             * ------------------------------------------------------- */
            if ($status === 'Approved' && tableExists($conn, 'franchies_add_product')) {

    if ($from === 'city') {
        $productTable = 'city_franchies_product_request';
        $paymentTable = 'city_franchies_payment';
    } elseif ($from === 'mobile') {
        $productTable = 'mobile_franchies_product_request';
        $paymentTable = 'mobile_franchies_payment';
    } else {
        $productTable = 'franchies_product_request';
        $paymentTable = 'franchies_payment';
    }

    if (tableExists($conn, $productTable)) {

        $franchiseId = 0;

        $fstmt = $conn->prepare("
            SELECT franchise_id
            FROM `$paymentTable`
            WHERE order_id = ?
            LIMIT 1
        ");

        if ($fstmt) {

            $fstmt->bind_param("s", $orderId);
            $fstmt->execute();

            $frData = $fstmt->get_result()->fetch_assoc();

            $fstmt->close();

            $franchiseId = (int)($frData['franchise_id'] ?? 0);
        }

        $stmt = $conn->prepare("
            SELECT product_name, quantity
            FROM `$productTable`
            WHERE order_id = ?
        ");

        $stmt->bind_param("s", $orderId);
        $stmt->execute();

        $items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        $stmt->close();

        foreach ($items as $item) {

            $productName = trim($item['product_name']);
            $qty = (int)$item['quantity'];

            if ($productName === '' || $qty <= 0) {
                continue;
            }

            $stmt1 = $conn->prepare("
                UPDATE franchies_add_product
                SET quantity = GREATEST(quantity - ?, 0)
                WHERE product_name = ?
                AND status = 'Active'
            ");

            $stmt1->bind_param("is", $qty, $productName);
            $stmt1->execute();
            $stmt1->close();
            
            // Get stock before & after
$stockBefore = 0;
$stockAfter  = 0;

$stockStmt = $conn->prepare("
    SELECT quantity
    FROM franchies_add_product
    WHERE product_name = ?
    AND status = 'Active'
    LIMIT 1
");

if ($stockStmt) {

    $stockStmt->bind_param("s", $productName);
    $stockStmt->execute();

    $stockData = $stockStmt->get_result()->fetch_assoc();

    $stockAfter = (int)($stockData['quantity'] ?? 0);
    $stockBefore = $stockAfter + $qty;

    $stockStmt->close();
}

// franchise code
$franchiseCode = '';

$fcStmt = $conn->prepare("
    SELECT franchise_code
    FROM franchies_holder
    WHERE id = ?
    LIMIT 1
");

if ($fcStmt) {

    $fcStmt->bind_param("i", $franchiseId);
    $fcStmt->execute();

    $fcData = $fcStmt->get_result()->fetch_assoc();

    $franchiseCode = $fcData['franchise_code'] ?? '';

    $fcStmt->close();
}

$reportStmt = $conn->prepare("
    INSERT IGNORE INTO admin_stock_reduce_report
    (
        order_id,
        request_from,
        franchise_id,
        franchise_code,
        product_name,
        requested_qty,
        reduced_qty,
        stock_before,
        stock_after,
        status_applied,
        reduced_at
    )
    VALUES
    (
        ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Approved', NOW()
    )
");

if ($reportStmt) {

    $reportStmt->bind_param(
        'ssissiiii',
        $orderId,
        $from,
        $franchiseId,
        $franchiseCode,
        $productName,
        $qty,
        $qty,
        $stockBefore,
        $stockAfter
    );

    $reportStmt->execute();
    $reportStmt->close();
}

            if ($franchiseId <= 0) {
                continue;
            }

            $checkStmt = $conn->prepare("
                SELECT id
                FROM franchies_product_stock_request
                WHERE franchise_id = ?
                AND product_name = ?
                LIMIT 1
            ");

            $checkStmt->bind_param(
                "is",
                $franchiseId,
                $productName
            );

            $checkStmt->execute();

            $existing = $checkStmt
                ->get_result()
                ->fetch_assoc();

            $checkStmt->close();

            if ($existing) {

                $stmt2 = $conn->prepare("
                    UPDATE franchies_product_stock_request

                    SET
                        product_received = product_received + ?,
                        product_balance = product_balance + ?,
                        balance_amount = offer_price * (product_balance + ?)

                    WHERE id = ?
                ");

                $stmt2->bind_param(
                    "iiii",
                    $qty,
                    $qty,
                    $qty,
                    $existing['id']
                );

                $stmt2->execute();
                $stmt2->close();

            } else {

               
                $mrp = 0;
$offerPrice = 0;
$pv = 0;

$pstmt = $conn->prepare("
    SELECT mrp, price
FROM franchies_add_product
WHERE product_name = ?
LIMIT 1
");

if ($pstmt) {

    $pstmt->bind_param("s", $productName);
    $pstmt->execute();

    $prod = $pstmt
        ->get_result()
        ->fetch_assoc();

    $pstmt->close();

    $mrp = (float)($prod['mrp'] ?? 0);
    $offerPrice = (float)($prod['price'] ?? 0);
    $pv = 0;
}

                $balanceAmount = $offerPrice * $qty;

                $stmt3 = $conn->prepare("
                    INSERT INTO franchies_product_stock_request (

                        franchise_id,
                        product_name,
                        mrp,
                        offer_price,
                        pv,
                        product_received,
                        product_used,
                        product_balance,
                        balance_amount,
                        status,
                        created_at

                    ) VALUES (

                        ?, ?, ?, ?, ?,
                        ?, 0, ?, ?,
                        'Active',
                        NOW()
                    )
                ");

                $stmt3->bind_param(
                    "isdddiii",
                    $franchiseId,
                    $productName,
                    $mrp,
                    $offerPrice,
                    $pv,
                    $qty,
                    $qty,
                    $balanceAmount
                );

                $stmt3->execute();
                $stmt3->close();
            }
        }
    }
}
            /* ------------------------------------------------------- */

            $conn->commit();
            $_SESSION['success_msg'] = "Order status updated successfully.";
        } catch (Exception $ex) {
            $conn->rollback();
            $_SESSION['error_msg'] = "Status update failed: " . $ex->getMessage();
        }
    }

    header("Location: franchies_product_request.php");
    exit;
}

/* FETCH ORDERS */
$orders = [];

/* STATE FRANCHISE ORDERS */
if (tableExists($conn, 'franchies_payment')) {
    $sql = "
        SELECT 
            fp.id,
            fp.order_id,
            fp.franchise_id,
            fp.total_amount,
            fp.bank_name,
            fp.payment_mode,
            fp.utr_number,
            fp.receipt,
            fp.remark,
            fp.status,
            fp.created_at,
            fp.franchise_code,
            fh.name AS franchise_name,
            'state' AS request_from
        FROM franchies_payment fp
        LEFT JOIN franchies_holder fh 
            ON fp.franchise_id = fh.id
        ORDER BY fp.id DESC
    ";

    $result = $conn->query($sql);

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $orders[] = $row;
        }
    }
}

/* CITY FRANCHISE ORDERS */
if (tableExists($conn, 'city_franchies_payment')) {
    $sql = "
        SELECT 
            cp.id,
            cp.order_id,
            cp.franchise_id,
            cp.total_amount,
            cp.bank_name,
            cp.payment_mode,
            cp.utr_number,
            cp.receipt,
            cp.remark,
            cp.status,
            cp.created_at,
            cp.franchise_code,
            ch.full_name AS franchise_name,
            'city' AS request_from
        FROM city_franchies_payment cp
        LEFT JOIN city_franchies_holder ch 
            ON cp.franchise_id = ch.id
        ORDER BY cp.id DESC
    ";

    $result = $conn->query($sql);

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $orders[] = $row;
        }
    }
}

/* MOBILE FRANCHISE ORDERS */
if (tableExists($conn, 'mobile_franchies_payment')) {
    $sql = "
        SELECT
            mp.id,
            mp.order_id,
            mp.franchise_id,
            mp.total_amount,
            mp.bank_name,
            mp.payment_mode,
            mp.utr_number,
            mp.receipt,
            mp.remark,
            mp.status,
            mp.created_at,
            mp.franchise_code,
            mh.owner_name AS franchise_name,
            'mobile' AS request_from
        FROM mobile_franchies_payment mp
        LEFT JOIN mobile_franchies_holder mh
            ON mp.franchise_id = mh.id
        ORDER BY mp.id DESC
    ";

    $result = $conn->query($sql);

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $orders[] = $row;
        }
    }
}

/* SORT ALL ORDERS BY DATE/ID */
usort($orders, function ($a, $b) {
    $dateA = strtotime($a['created_at'] ?? '') ?: 0;
    $dateB = strtotime($b['created_at'] ?? '') ?: 0;

    if ($dateA === $dateB) {
        return (int)($b['id'] ?? 0) <=> (int)($a['id'] ?? 0);
    }

    return $dateB <=> $dateA;
});

/* FETCH ORDER ITEMS */
foreach ($orders as $key => $row) {
    $items = [];
    $qtyTotal = 0;

    if ($row['request_from'] === 'city') {
        $productTable = 'city_franchies_product_request';
    } elseif ($row['request_from'] === 'mobile') {
        $productTable = 'mobile_franchies_product_request';
    } else {
        $productTable = 'franchies_product_request';
    }

    if (tableExists($conn, $productTable)) {
        $stmt = $conn->prepare("
            SELECT category, product_name, offer_price, quantity, total_amount
            FROM `$productTable`
            WHERE order_id = ?
            ORDER BY id ASC
        ");

        if ($stmt) {
            $stmt->bind_param("s", $row['order_id']);
            $stmt->execute();
            $itemResult = $stmt->get_result();

            while ($item = $itemResult->fetch_assoc()) {
                $items[] = $item;
                $qtyTotal += (int)$item['quantity'];
            }

            $stmt->close();
        }
    }

    $orders[$key]['items'] = $items;
    $orders[$key]['total_qty'] = $qtyTotal;
}

$success = $_SESSION['success_msg'] ?? '';
$error   = $_SESSION['error_msg'] ?? '';
unset($_SESSION['success_msg'], $_SESSION['error_msg']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Franchies Product Request</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="assets/css/admin-responsive.css">

<script src="https://cdn.tailwindcss.com"></script>
<script src="https://unpkg.com/lucide@latest"></script>
</head>

<body class="bg-gray-100 min-h-screen overflow-x-hidden">

<?php
if (file_exists(__DIR__ . '/includes/sidebar.php')) {
    include __DIR__ . '/includes/sidebar.php';
}
?>

<div class="ml-64 min-h-screen bg-gray-100">

<?php
if (file_exists(__DIR__ . '/includes/navbar.php')) {
    include __DIR__ . '/includes/navbar.php';
}
?>

<main class="px-5 py-8 pb-3">

    <div class="mb-9 flex items-center gap-5">
        <div class="flex h-[44px] w-[44px] shrink-0 items-center justify-center rounded-[10px] bg-[#f6eee2]">
            <i data-lucide="badge-check" class="h-5 w-5 text-[#cf9a3c]"></i>
        </div>

        <h2 class="whitespace-nowrap text-[20px] font-normal leading-none text-[#3b3329]" style="font-family: Georgia, 'Times New Roman', serif;">
            PRODUCT REQUESTS
        </h2>

        <div class="h-[2px] w-full bg-[#d09a3d]"></div>
    </div>

    <?php if ($success): ?>
        <div class="mb-5 rounded-xl bg-green-100 text-green-700 px-5 py-4 text-[16px]">
            <?= e($success); ?>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="mb-5 rounded-xl bg-red-100 text-red-700 px-5 py-4 text-[16px]">
            <?= e($error); ?>
        </div>
    <?php endif; ?>

    <div class="bg-white rounded-2xl shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full border-collapse">
                <thead class="bg-slate-50">
                <tr>
                    <th class="px-4 py-4 text-left text-sm font-bold text-slate-700">#</th>
                    <th class="px-4 py-4 text-left text-sm font-bold text-slate-700">Order ID</th>
                    <th class="px-4 py-4 text-left text-sm font-bold text-slate-700">Type</th>
                    <th class="px-4 py-4 text-left text-sm font-bold text-slate-700">Franchise</th>
                    <th class="px-4 py-4 text-left text-sm font-bold text-slate-700">Products</th>
                    <th class="px-4 py-4 text-left text-sm font-bold text-slate-700">Total Qty</th>
                    <th class="px-4 py-4 text-left text-sm font-bold text-slate-700">Amount</th>
                    <th class="px-4 py-4 text-left text-sm font-bold text-slate-700">Payment Mode</th>
                    <th class="px-4 py-4 text-left text-sm font-bold text-slate-700">UTR / Transaction</th>
                    <th class="px-4 py-4 text-left text-sm font-bold text-slate-700">Receipt</th>
                    <th class="px-4 py-4 text-left text-sm font-bold text-slate-700">Remark</th>
                    <th class="px-4 py-4 text-left text-sm font-bold text-slate-700">Status</th>
                    <th class="px-4 py-4 text-left text-sm font-bold text-slate-700">Action</th>
                </tr>
                </thead>

                <tbody>
                <?php if (!empty($orders)): ?>
                    <?php $i = 1; foreach ($orders as $row): ?>
                        <?php
                        $itemsJson = htmlspecialchars(json_encode($row['items']), ENT_QUOTES, 'UTF-8');
                        $status = $row['status'] ?: 'Pending';

                        $statusClass = 'bg-yellow-100 text-yellow-700';
                        if ($status === 'Approved') $statusClass = 'bg-green-100 text-green-700';
                        if ($status === 'Rejected') $statusClass = 'bg-red-100 text-red-700';

                        $receiptUrl = 'franchies_product_request.php?view_receipt=' . urlencode($row['order_id']) . '&from=' . urlencode($row['request_from']);
                        ?>

                        <tr class="border-t border-gray-200 align-top hover:bg-slate-50">
                            <td class="px-4 py-4 text-sm"><?= $i++; ?></td>

                            <td class="px-4 py-4 text-sm font-bold text-black whitespace-nowrap">
                                <?= e($row['order_id']); ?>
                            </td>

                            <td class="px-4 py-4 text-sm">
                                <span class="rounded-lg px-3 py-2 text-xs font-bold <?= $row['request_from'] === 'city' ? 'bg-blue-100 text-blue-700' : 'bg-purple-100 text-purple-700'; ?>">
                                    <?= e(strtoupper($row['request_from'])); ?>
                                </span>
                            </td>

                            <td class="px-4 py-4 text-sm whitespace-nowrap">
                                <span class="font-semibold"><?= e($row['franchise_code'] ?: $row['franchise_id']); ?></span>
                                <?php if (!empty($row['franchise_name'])): ?>
                                    <div class="text-xs text-gray-500"><?= e($row['franchise_name']); ?></div>
                                <?php endif; ?>
                            </td>

                            <td class="px-4 py-4 text-sm">
                                <button type="button"
                                        data-items="<?= $itemsJson; ?>"
                                        onclick="openProductModal(this)"
                                        class="rounded-md bg-slate-100 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-200">
                                    View Products
                                </button>
                            </td>

                            <td class="px-4 py-4 text-sm font-semibold"><?= (int)$row['total_qty']; ?></td>

                            <td class="px-4 py-4 text-sm font-bold whitespace-nowrap">
                                ₹<?= number_format((float)$row['total_amount'], 2); ?>
                            </td>

                            <td class="px-4 py-4 text-sm"><?= e($row['payment_mode']); ?></td>
                            <td class="px-4 py-4 text-sm"><?= e($row['utr_number']); ?></td>

                            <td class="px-4 py-4 text-sm">
                                <?php if (!empty($row['receipt'])): ?>
                                    <button type="button"
                                            onclick="openReceiptModal('<?= e($receiptUrl); ?>')"
                                            class="text-blue-600 underline font-semibold">
                                        View
                                    </button>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>

                            <td class="px-4 py-4 text-sm"><?= e($row['remark'] ?: '-'); ?></td>

                            <td class="px-4 py-4 text-sm">
                                <span class="inline-block rounded-lg px-3 py-2 text-sm font-semibold <?= $statusClass; ?>">
                                    <?= e($status); ?>
                                </span>
                            </td>

                            <td class="px-4 py-4 text-sm whitespace-nowrap">
                                <?php if ($status !== 'Approved'): ?>
                                    <form method="POST" class="inline-block">
                                        <input type="hidden" name="order_id" value="<?= e($row['order_id']); ?>">
                                        <input type="hidden" name="from" value="<?= e($row['request_from']); ?>">
                                        <input type="hidden" name="status" value="Approved">
                                        <button type="submit" class="rounded-lg bg-green-600 px-3 py-2 text-xs font-semibold text-white hover:bg-green-700">
                                            Approve
                                        </button>
                                    </form>
                                <?php endif; ?>

                                <?php if ($status !== 'Rejected'): ?>
                                    <form method="POST" class="inline-block">
                                        <input type="hidden" name="order_id" value="<?= e($row['order_id']); ?>">
                                        <input type="hidden" name="from" value="<?= e($row['request_from']); ?>">
                                        <input type="hidden" name="status" value="Rejected">
                                        <button type="submit" class="rounded-lg bg-red-600 px-3 py-2 text-xs font-semibold text-white hover:bg-red-700">
                                            Reject
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>

                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="13" class="px-4 py-10 text-center text-gray-500">
                            No product requests found.
                        </td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</main>
</div>

<div id="productModal" class="fixed inset-0 z-[9999] hidden items-center justify-center bg-black/50 px-4">
    <div class="w-full max-w-3xl rounded-2xl bg-white shadow-2xl">
        <div class="flex items-center justify-between border-b px-6 py-4">
            <h2 class="text-xl font-bold text-slate-800">Product Details</h2>
            <button onclick="closeProductModal()" class="rounded-full bg-red-50 px-3 py-1 text-xl font-bold text-red-600">×</button>
        </div>
        <div id="productModalBody" class="max-h-[70vh] overflow-y-auto p-6 space-y-3"></div>
    </div>
</div>

<div id="receiptModal" class="fixed inset-0 z-[99999] hidden items-center justify-center bg-black/60 px-4">
    <div class="w-full max-w-5xl rounded-2xl bg-white shadow-2xl overflow-hidden">
        <div class="flex items-center justify-between border-b px-5 py-4">
            <h2 class="text-xl font-bold text-slate-800">Transaction Slip</h2>
            <button onclick="closeReceiptModal()" class="rounded-full bg-red-50 px-3 py-1 text-xl font-bold text-red-600 hover:bg-red-100">×</button>
        </div>

        <div class="p-5 bg-gray-100 max-h-[78vh] overflow-auto text-center">
            <iframe id="receiptFrame"
                    src=""
                    class="h-[72vh] w-full rounded-xl border bg-white">
            </iframe>
        </div>

        <div class="border-t px-5 py-4 text-right">
            <a id="receiptOpenNew"
               href="#"
               target="_blank"
               class="inline-block rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">
                Open in New Tab
            </a>
        </div>
    </div>
</div>

<script>
function money(v) {
    return '₹' + Number(v || 0).toLocaleString('en-IN', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });
}

function openProductModal(btn) {
    let items = [];
    try {
        items = JSON.parse(btn.getAttribute('data-items') || '[]');
    } catch (e) {
        items = [];
    }

    let html = '';

    if (items.length === 0) {
        html = '<div class="text-center text-gray-500 py-6">No products found.</div>';
    } else {
        items.forEach((item, index) => {
            html += `
                <div class="rounded-xl border border-gray-200 bg-gray-50 p-4">
                    <div class="font-bold text-slate-800">#${index + 1} ${escapeHtml(item.product_name || '-')}</div>
                    <div class="mt-2 text-sm text-slate-600">
                        Category: ${escapeHtml(item.category || '-')} |
                        Qty: ${parseInt(item.quantity || 0)} |
                        Price: ${money(item.offer_price || 0)} |
                        Total: ${money(item.total_amount || 0)}
                    </div>
                </div>
            `;
        });
    }

    document.getElementById('productModalBody').innerHTML = html;
    document.getElementById('productModal').classList.remove('hidden');
    document.getElementById('productModal').classList.add('flex');
}

function closeProductModal() {
    document.getElementById('productModal').classList.add('hidden');
    document.getElementById('productModal').classList.remove('flex');
}

function openReceiptModal(url) {
    document.getElementById('receiptFrame').src = url;
    document.getElementById('receiptOpenNew').href = url;
    document.getElementById('receiptModal').classList.remove('hidden');
    document.getElementById('receiptModal').classList.add('flex');
}

function closeReceiptModal() {
    document.getElementById('receiptFrame').src = '';
    document.getElementById('receiptModal').classList.add('hidden');
    document.getElementById('receiptModal').classList.remove('flex');
}

function escapeHtml(str) {
    return String(str).replace(/[&<>"']/g, function(s) {
        return ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#39;'
        })[s];
    });
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeReceiptModal();
        closeProductModal();
    }
});

document.addEventListener("DOMContentLoaded", function () {
    if (window.lucide) {
        lucide.createIcons();
    }
});
</script>

</body>
</html>