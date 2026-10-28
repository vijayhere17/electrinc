<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config/db.php';

if (!isset($conn) || !($conn instanceof mysqli)) {
    die('Database connection failed.');
}

function e($str) {
    return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8');
}

function tableExists($conn, $table) {
    $table = $conn->real_escape_string($table);
    $res = $conn->query("SHOW TABLES LIKE '$table'");
    return ($res && $res->num_rows > 0);
}

function colExists($conn, $table, $col) {
    if (!tableExists($conn, $table)) return false;
    $table = $conn->real_escape_string($table);
    $col   = $conn->real_escape_string($col);
    $res   = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$col'");
    return ($res && $res->num_rows > 0);
}

function addColIfMissing($conn, $table, $col, $def) {
    if (tableExists($conn, $table) && !colExists($conn, $table, $col)) {
        $conn->query("ALTER TABLE `$table` ADD `$col` $def");
    }
}

function jsonResponse(array $payload, int $statusCode = 200): void {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($payload);
    exit;
}

function getActivationRequestTable(mysqli $conn): string {
    if (!tableExists($conn, 'member_activation')) {
        $conn->query("
CREATE TABLE IF NOT EXISTS member_activation (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    customer_id VARCHAR(50) NOT NULL,
    member_name VARCHAR(255) DEFAULT NULL,
    franchise_id INT(11) DEFAULT NULL,
    franchise_code VARCHAR(50) DEFAULT NULL,
    product_id INT(11) DEFAULT NULL,
    product_name VARCHAR(255) DEFAULT NULL,
    amount DECIMAL(12,2) DEFAULT 0.00,
    payment_mode VARCHAR(50) DEFAULT NULL,
    deposited_bank VARCHAR(50) DEFAULT NULL,
    transaction_id VARCHAR(100) DEFAULT NULL,
    payment_slip VARCHAR(255) DEFAULT NULL,
    remark TEXT DEFAULT NULL,
    products_json LONGTEXT DEFAULT NULL,
    member_status VARCHAR(50) DEFAULT NULL,
    status VARCHAR(50) DEFAULT 'Approved',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX(customer_id),
    INDEX(franchise_id),
    INDEX(product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");
    }

    return 'member_activation';
}

function detectActivationRequestTable(): string {
    $typeRaw = strtolower(trim((string)(
        $_SESSION['franchise_type'] ??
        $_SESSION['franchies_type'] ??
        ''
    )));

    if (str_contains($typeRaw, 'mobile') || !empty($_SESSION['mobile_franchise_id'])) {
        return 'city_mobile_order';
    }

    if (str_contains($typeRaw, 'city') || !empty($_SESSION['city_franchies_holder_id'])) {
        return 'city_mobile_order';
    }

    return 'franchies_product_request';
}

function getRequestBasedAvailableStock(mysqli $conn, string $franchiseId, string $franchiseCode, string $requestTable, string $productName): int {
    if (empty($franchiseId) || !tableExists($conn, $requestTable)) {
        return 0;
    }

    if (colExists($conn, $requestTable, 'franchise_code')) {
        $stmt = $conn->prepare("
    SELECT IFNULL(SUM(quantity), 0) AS qty
    FROM `$requestTable`
    WHERE LOWER(TRIM(product_name)) = LOWER(TRIM(?))
      AND LOWER(TRIM(status)) = 'approved'
      AND (
            franchise_id = ?
            OR franchise_code = ?
            OR franchise_id = ?
            OR franchise_code = ?
      )
");

        if (!$stmt) {
            return 0;
        }

        $franchiseIdString = (string)$franchiseId;

        $stmt->bind_param(
            'sssss',
            $productName,
            $franchiseId,
            $franchiseCode,
            $franchiseCode,
            $franchiseIdString
        );
    } else {
        $stmt = $conn->prepare("
            SELECT IFNULL(SUM(quantity), 0) AS qty
            FROM `$requestTable`
            WHERE LOWER(TRIM(product_name)) = LOWER(TRIM(?))
              AND LOWER(TRIM(status)) = 'approved'
              AND franchise_id = ?
        ");
        if (!$stmt) {
            return 0;
        }
        $stmt->bind_param('ss', $productName, $franchiseId);
    }

    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $addedStock = (int)($result['qty'] ?? 0);
    if ($addedStock <= 0) {
        return 0;
    }

    $soldStock = 0;
    if (tableExists($conn, 'franchise_order_sale')) {
        if (colExists($conn, 'franchise_order_sale', 'distributor_id')) {
            $soldStmt = $conn->prepare("
                SELECT IFNULL(SUM(quantity), 0) AS qty
                FROM franchise_order_sale
                WHERE LOWER(TRIM(product_name)) = LOWER(TRIM(?))
                  AND (distributor_id = ? OR distributor_id = ?)
            ");
            if ($soldStmt) {
                $franchiseIdString = (string)$franchiseId;
                $soldStmt->bind_param('sss', $productName, $franchiseCode, $franchiseIdString);
            }
        } else {
            $soldStmt = $conn->prepare("
                SELECT IFNULL(SUM(quantity), 0) AS qty
                FROM franchise_order_sale
                WHERE LOWER(TRIM(product_name)) = LOWER(TRIM(?))
            ");
            if ($soldStmt) {
                $soldStmt->bind_param('s', $productName);
            }
        }

        if (!empty($soldStmt)) {
            $soldStmt->execute();
            $soldRow = $soldStmt->get_result()->fetch_assoc();
            $soldStmt->close();
            $soldStock = (int)($soldRow['qty'] ?? 0);
        }
    }

    $available = $addedStock - $soldStock;
    return $available > 0 ? $available : 0;
}

function ensureFranchiseProductStockBalance(mysqli $conn, int $franchiseId, string $productName, int $availableStock): bool {
    if ($franchiseId <= 0 || $availableStock <= 0) {
        return false;
    }

    if (!tableExists($conn, 'franchies_product_stock_request') || !colExists($conn, 'franchies_product_stock_request', 'product_balance')) {
        return false;
    }

    $selectStmt = $conn->prepare("
        SELECT id
        FROM franchies_product_stock_request
        WHERE franchise_id = ?
          AND LOWER(TRIM(product_name)) = LOWER(TRIM(?))
        ORDER BY id ASC
        LIMIT 1
    ");
    if (!$selectStmt) {
        return false;
    }

    $selectStmt->bind_param('is', $franchiseId, $productName);
    $selectStmt->execute();
    $row = $selectStmt->get_result()->fetch_assoc();
    $selectStmt->close();

    if ($row) {
        $updateStmt = $conn->prepare("
            UPDATE franchies_product_stock_request
            SET product_balance = ?
            WHERE id = ?
        ");
        if (!$updateStmt) {
            return false;
        }

        $stockId = (int)($row['id'] ?? 0);
        $updateStmt->bind_param('ii', $availableStock, $stockId);
        $ok = $updateStmt->execute();
        $updateStmt->close();
        return (bool)$ok;
    }

    $insertStmt = $conn->prepare("
        INSERT INTO franchies_product_stock_request
            (franchise_id, product_name, product_received, product_used, product_balance, status, created_at)
        VALUES (?, ?, ?, 0, ?, 'Available', NOW())
    ");
    if (!$insertStmt) {
        return false;
    }

    $insertStmt->bind_param('isii', $franchiseId, $productName, $availableStock, $availableStock);
    $ok = $insertStmt->execute();
    $insertStmt->close();
    return (bool)$ok;
}

function fetchFranchiseProductStockMap(mysqli $conn, int $franchiseId, string $franchiseCode, string $requestTable): array {
    $stockMap = [];

    if ($franchiseId <= 0) {
        return $stockMap;
    }

    if (!colExists($conn, 'franchies_product_stock_request', 'product_balance')) {
        return $stockMap;
    }

    $stmt = $conn->prepare("
        SELECT LOWER(TRIM(product_name)) AS product_key, COALESCE(SUM(product_balance), 0) AS available_stock
        FROM franchies_product_stock_request
        WHERE franchise_id = ?
        GROUP BY LOWER(TRIM(product_name))
    ");

    if (!$stmt) {
        return $stockMap;
    }

    $stmt->bind_param('i', $franchiseId);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $stockMap[(string)($row['product_key'] ?? '')] = (int)($row['available_stock'] ?? 0);
    }

    $stmt->close();

    $productStmt = $conn->prepare("
        SELECT LOWER(TRIM(product_name)) AS product_key, product_name
        FROM franchies_add_product
        WHERE status = 'Active'
        ORDER BY id ASC
    ");

    if (!$productStmt) {
        return $stockMap;
    }

    $productStmt->execute();
    $productResult = $productStmt->get_result();

    while ($productRow = $productResult->fetch_assoc()) {
        $productKey = (string)($productRow['product_key'] ?? '');
        $productName = (string)($productRow['product_name'] ?? '');

        if ($productKey === '' || $productName === '') {
            continue;
        }

        $currentStock = (int)($stockMap[$productKey] ?? 0);
        if ($currentStock > 0) {
            continue;
        }

        $availableStock = getRequestBasedAvailableStock($conn, $franchiseId, $franchiseCode, $requestTable, $productName);

        if ($availableStock > 0) {
            $stockMap[$productKey] = $availableStock;
        }
    }

    $productStmt->close();

    return $stockMap;
}

function getProductStockBalance(mysqli $conn, int $franchiseId, string $franchiseCode, string $requestTable, string $productName): int {
    $stockMap = fetchFranchiseProductStockMap($conn, $franchiseId, $franchiseCode, $requestTable);
    $key = strtolower(trim($productName));
    return (int)($stockMap[$key] ?? 0);
}

function deductFranchiseProductStock(mysqli $conn, int $franchiseId, string $franchiseCode, string $requestTable, string $productName): array {
    if ($franchiseId <= 0) {
        return ['ok' => false, 'stock_before' => 0, 'stock_after' => 0, 'message' => 'Franchise not found.'];
    }

    if (!tableExists($conn, 'franchies_product_stock_request') || !colExists($conn, 'franchies_product_stock_request', 'product_balance')) {
        return ['ok' => false, 'stock_before' => 0, 'stock_after' => 0, 'message' => 'Product stock not available.'];
    }

    $selectStmt = $conn->prepare("
        SELECT id, product_balance
        FROM franchies_product_stock_request
        WHERE franchise_id = ?
          AND LOWER(TRIM(product_name)) = LOWER(TRIM(?))
          AND product_balance > 0
        ORDER BY id ASC
        LIMIT 1
        FOR UPDATE
    ");

    if (!$selectStmt) {
        return ['ok' => false, 'stock_before' => 0, 'stock_after' => 0, 'message' => 'Unable to verify stock.'];
    }

    $selectStmt->bind_param('is', $franchiseId, $productName);
    $selectStmt->execute();
    $stockRow = $selectStmt->get_result()->fetch_assoc();
    $selectStmt->close();

    if (!$stockRow) {
        $availableStock = getRequestBasedAvailableStock($conn, $franchiseId, $franchiseCode, $requestTable, $productName);
        if ($availableStock > 0 && ensureFranchiseProductStockBalance($conn, $franchiseId, $productName, $availableStock)) {
            $selectStmt = $conn->prepare("
                SELECT id, product_balance
                FROM franchies_product_stock_request
                WHERE franchise_id = ?
                  AND LOWER(TRIM(product_name)) = LOWER(TRIM(?))
                  AND product_balance > 0
                ORDER BY id ASC
                LIMIT 1
                FOR UPDATE
            ");

            if ($selectStmt) {
                $selectStmt->bind_param('is', $franchiseId, $productName);
                $selectStmt->execute();
                $stockRow = $selectStmt->get_result()->fetch_assoc();
                $selectStmt->close();
            }
        }
    }

    if (!$stockRow) {
        return ['ok' => false, 'stock_before' => 0, 'stock_after' => 0, 'message' => 'Product stock not available.'];
    }

    $stockBefore = (int)($stockRow['product_balance'] ?? 0);
    if ($stockBefore <= 0) {
        return ['ok' => false, 'stock_before' => 0, 'stock_after' => 0, 'message' => 'Out Of Stock'];
    }

    $stockAfter = $stockBefore - 1;
    $stockId = (int)($stockRow['id'] ?? 0);

    $updateStmt = $conn->prepare("
        UPDATE franchies_product_stock_request
        SET product_balance = product_balance - 1,
            product_used = CASE WHEN product_used IS NULL THEN 1 ELSE product_used + 1 END
        WHERE id = ? AND product_balance > 0
    ");

    if (!$updateStmt) {
        return ['ok' => false, 'stock_before' => 0, 'stock_after' => 0, 'message' => 'Unable to update stock.'];
    }

    $updateStmt->bind_param('i', $stockId);
    $updateStmt->execute();
    $affected = $updateStmt->affected_rows;
    $updateStmt->close();

    if ($affected <= 0) {
        return ['ok' => false, 'stock_before' => 0, 'stock_after' => 0, 'message' => 'Product stock not available.'];
    }

    return ['ok' => true, 'stock_before' => $stockBefore, 'stock_after' => $stockAfter, 'message' => 'Stock updated.'];
}

function resolveFranchiseLogin($conn) {
    $typeRaw = strtolower(trim((string)(
        $_SESSION['franchise_type'] ??
        $_SESSION['franchies_type'] ??
        ''
    )));

    $values = array_filter(array_map('trim', [
        (string)($_SESSION['mobile_franchise_id']       ?? ''),
        (string)($_SESSION['mobile_franchise_code']     ?? ''),
        (string)($_SESSION['city_franchies_holder_id']   ?? ''),
        (string)($_SESSION['state_franchies_holder_id']  ?? ''),
        (string)($_SESSION['franchise_db_id']            ?? ''),
        (string)($_SESSION['franchise_id']               ?? ''),
        (string)($_SESSION['franchies_id']               ?? ''),
        (string)($_SESSION['franchise_code']             ?? ''),
        (string)($_SESSION['franchise_login_id']         ?? ''),
    ]), fn($v) => $v !== '');

    if (str_contains($typeRaw, 'mobile') || !empty($_SESSION['mobile_franchise_id'])) {
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

        if (!tableExists($conn, $table)) continue;

        foreach ($values as $val) {
            if (is_numeric($val)) {
                $id   = (int)$val;
                $stmt = $conn->prepare("SELECT * FROM `$table` WHERE id = ? LIMIT 1");
                if ($stmt) {
                    $stmt->bind_param('i', $id);
                    $stmt->execute();
                    $row = $stmt->get_result()->fetch_assoc();
                    $stmt->close();
                    if ($row) {
                        return [
                            'type' => $type,
                            'id'   => (int)$row['id'],
                            'code' => (string)($row[$codeCol] ?? $val),
                            'name' => (string)($row[$nameCol] ?? 'Franchise'),
                        ];
                    }
                }
            }

            $stmt = $conn->prepare("SELECT * FROM `$table` WHERE `$codeCol` = ? LIMIT 1");
            if ($stmt) {
                $stmt->bind_param('s', $val);
                $stmt->execute();
                $row = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                if ($row) {
                    return [
                        'type' => $type,
                        'id'   => (int)$row['id'],
                        'code' => (string)($row[$codeCol] ?? $val),
                        'name' => (string)($row[$nameCol] ?? 'Franchise'),
                    ];
                }
            }
        }
    }

    return null;
}

$login = resolveFranchiseLogin($conn);
if (!$login) {
    header('Location: index.php');
    exit;
}

$franchiseType = $login['type'];
$franchiseId   = (int)$login['id'];
$franchiseCode = (string)$login['code'];
$userName      = (string)$login['name'];

$conn->query("
CREATE TABLE IF NOT EXISTS activation_requests (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    customer_id VARCHAR(50) NOT NULL,
    amount DECIMAL(12,2) DEFAULT 0.00,
    deposited_bank VARCHAR(50) DEFAULT NULL,
    payment_mode VARCHAR(50) DEFAULT NULL,
    transaction_id VARCHAR(100) DEFAULT NULL,
    payment_slip VARCHAR(255) DEFAULT NULL,
    remark TEXT DEFAULT NULL,
    products_json LONGTEXT DEFAULT NULL,
    status VARCHAR(50) DEFAULT 'Pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX(customer_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

addColIfMissing($conn, 'activation_requests', 'franchise_id', 'INT(11) NULL AFTER amount');
addColIfMissing($conn, 'activation_requests', 'franchise_code', 'VARCHAR(50) NULL AFTER franchise_id');
addColIfMissing($conn, 'activation_requests', 'member_name', 'VARCHAR(255) NULL AFTER customer_id');
addColIfMissing($conn, 'activation_requests', 'member_status', 'VARCHAR(50) NULL AFTER member_name');
addColIfMissing($conn, 'member_activation', 'deposited_bank', 'VARCHAR(50) NULL AFTER payment_mode');
addColIfMissing($conn, 'member_activation', 'remark', 'TEXT NULL AFTER payment_slip');
addColIfMissing($conn, 'member_activation', 'products_json', 'LONGTEXT NULL AFTER remark');
addColIfMissing($conn, 'signup_users', 'activated_at', 'DATETIME NULL');
addColIfMissing($conn, 'signup_users', 'activated_by_franchise_id', 'INT(11) NULL');
addColIfMissing($conn, 'signup_users', 'activated_by_franchise_code', 'VARCHAR(50) NULL');
addColIfMissing($conn, 'signup_users', 'status', 'VARCHAR(50) NULL');
addColIfMissing($conn, 'signup_users', 'active_status', 'VARCHAR(50) NULL');

$memberActivationTable = getActivationRequestTable($conn);
$activationRequestTable = detectActivationRequestTable();

$success = '';
$error   = '';
$isAjax  = false;

$stockMap = fetchFranchiseProductStockMap($conn, $franchiseId, $franchiseCode, $activationRequestTable);

$products = [];
$productStmt = $conn->prepare("
    SELECT id, product_name, price, quantity, status
    FROM franchies_add_product
    WHERE status = 'Active'
      AND product_type IN ('activation','both')
    ORDER BY id ASC
");
if ($productStmt) {
    $productStmt->execute();
    $result = $productStmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $row['amount'] = $row['price'];
        $row['available_stock'] = (int)($stockMap[strtolower(trim((string)$row['product_name']))] ?? 0);
        $products[] = $row;
    }
    $productStmt->close();
}

$bankDetails = [
    'account_name' => 'Rajesh P',
    'account_no'   => '3224265XXXX',
    'ifsc'         => 'SBIN000XXXX',
    'branch'       => 'KoparK',
    'city'         => 'Navi Mumbai',
    'bank'         => 'State Bank of India',
    'upi'          => 'rajesh@phonepe',
];

$memberNumber = trim((string)($_POST['member_number'] ?? ''));
$memberData   = null;

if (isset($_GET['lookup_member'])) {
    $q = trim((string)($_GET['lookup_member'] ?? ''));
    header('Content-Type: application/json');
    if ($q === '') {
        echo json_encode(['found' => false]);
        exit;
    }
    $stmt = $conn->prepare("SELECT customer_id, full_name, registration_status FROM signup_users WHERE customer_id = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param('s', $q);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($row) {
            $isAlreadyActive = strtolower(trim((string)($row['registration_status'] ?? ''))) === 'active';
            echo json_encode([
                'found' => true,
                'data' => $row,
                'already_active' => $isAlreadyActive
            ]);
            exit;
        }
    }
    echo json_encode(['found' => false]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['activate_member'])) {
    $isAjax = strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest';
    $memberNumber      = trim($_POST['member_number'] ?? '');
    $payment_mode      = trim($_POST['payment_mode'] ?? '');
    $transaction_no    = '';
    $selectedProductId = (int)($_POST['selected_product_id'] ?? 0);

    if ($memberNumber === '') {
        $error = 'Please enter the member number.';
    } elseif ($selectedProductId <= 0) {
        $error = 'Please select one product.';
    } elseif ($payment_mode === '') {
        $error = 'Please select payment mode.';
    } else {
        $memberStmt = $conn->prepare("SELECT customer_id, full_name, registration_status FROM signup_users WHERE customer_id = ? LIMIT 1");
        if ($memberStmt) {
            $memberStmt->bind_param('s', $memberNumber);
            $memberStmt->execute();
            $memberData = $memberStmt->get_result()->fetch_assoc();
            $memberStmt->close();
        }

        if (!$memberData) {
            $error = 'Member number not found.';
        } elseif (strtolower(trim((string)($memberData['registration_status'] ?? ''))) === 'active') {
            $error = 'This member is already activated. No further action is needed.';
            if ($isAjax) {
                jsonResponse([
                    'success'        => false,
                    'already_active' => true,
                    'message'        => 'This member is already activated. No further action is needed.',
                    'member_name'    => (string)($memberData['full_name'] ?? ''),
                    'member_status'  => 'Active'
                ], 422);
            }
        } else {
            $productStmt = $conn->prepare("
    SELECT id, product_name, price
    FROM franchies_add_product
    WHERE id = ?
      AND status = 'Active'
      AND product_type IN ('activation','both')
    LIMIT 1
");
            $productRow = null;
            if ($productStmt) {
                $productStmt->bind_param('i', $selectedProductId);
                $productStmt->execute();
                $productRow = $productStmt->get_result()->fetch_assoc();
                $productStmt->close();
            }

            if (!$productRow) {
                $error = 'Please select a valid product.';
            } else {
                $productName = (string)($productRow['product_name'] ?? '');
                $availableStock = getProductStockBalance($conn, $franchiseId, $franchiseCode, $activationRequestTable, $productName);

                if ($availableStock <= 0) {
                    $error = 'Product stock not available.';
                } else {
                    $filePath = '';
                    $total_amount = (float)($productRow['price'] ?? 0);
                    $productsJson = json_encode([[ 'id' => (int)$productRow['id'], 'product_name' => $productName, 'price' => $total_amount, 'qty' => 1 ]]);

                    $conn->begin_transaction();

                    try {
                        $stockResult = deductFranchiseProductStock($conn, $franchiseId, $franchiseCode, $activationRequestTable, $productName);
                        if (empty($stockResult['ok'])) {
                            throw new Exception((string)($stockResult['message'] ?? 'Product stock not available.'));
                        }

                        $memberStatus = (string)($memberData['registration_status'] ?? 'Inactive');

                        $insertTables = ['activation_requests', $memberActivationTable];
                        foreach ($insertTables as $tbl) {
                            $stmt = $conn->prepare("
                                        INSERT INTO `$tbl`
                                            (customer_id, member_name, amount, deposited_bank, payment_mode, transaction_id, payment_slip, remark, products_json, status, created_at, franchise_id, franchise_code, member_status)
                                        VALUES (?, ?, ?, ?, ?, ?, ?, '', ?, 'Approved', NOW(), ?, ?, ?)
                                    ");
                            if (!$stmt) {
                                throw new Exception('Activation insert stmt: ' . $conn->error);
                            }

                            $emptyRemark = '';
                            $stmt->bind_param(
                                'ssdssssssis',
                                $memberNumber,
                                $memberData['full_name'],
                                $total_amount,
                                $emptyRemark,
                                $payment_mode,
                                $transaction_no,
                                $filePath,
                                $productsJson,
                                $franchiseId,
                                $franchiseCode,
                                $memberStatus
                            );
                            $stmt->execute();
                            $stmt->close();
                        }

                        $updateStmt = $conn->prepare("
                                    UPDATE signup_users
                                    SET registration_status = 'Active',
                                        status = 'Active',
                                        active_status = 'Active',
                                        activated_at = NOW(),
                                        activated_by_franchise_id = ?,
                                        activated_by_franchise_code = ?
                                    WHERE customer_id = ?
                                ");
                        if (!$updateStmt) {
                            throw new Exception('Member status stmt: ' . $conn->error);
                        }

                        $updateStmt->bind_param('iss', $franchiseId, $franchiseCode, $memberNumber);
                        $updateStmt->execute();
                        $updateStmt->close();

                        $customer_id = $memberNumber;
                        require_once __DIR__ . '/includes/process-activation.php';

                        // Income Plan — Direct Sponsor (#1) + Binary (#2)
                        require_once __DIR__ . '/../includes/income_common.php';
                        process_member_income_on_activation($conn, $customer_id, [
                            'source'      => 'Activation',
                            'base_amount' => (float)($total_amount ?? 0),
                        ]);

                        $updatedStock = max(0, $availableStock - 1);
                        $stockMap[strtolower(trim($productName))] = $updatedStock;

                        foreach ($products as &$renderProduct) {
                            if ((int)$renderProduct['id'] === (int)$productRow['id']) {
                                $renderProduct['available_stock'] = $updatedStock;
                                break;
                            }
                        }
                        unset($renderProduct);

                        $soldByType = strtoupper($franchiseType);

                        $saleStmt = $conn->prepare("
    INSERT INTO franchise_order_sale
(
    distributor_id,
    sold_by_type,
    sold_by_id,
    order_type,
    member_id,
    member_name,
    product_name,
    quantity,
    price,
    total_amount,
    payment_method,
    created_at
)
VALUES
(
    ?, ?, ?, 'ACTIVATION', ?, ?, ?, ?, ?, ?, ?, NOW()
)
");

                        if (!$saleStmt) {
                            throw new Exception('Sale insert failed: ' . $conn->error);
                        }

                        $qty = 1;
                        $price = (float)$total_amount;

                        $saleStmt->bind_param(
                            'ssssssidss',
                            $franchiseCode,
                            $soldByType,
                            $franchiseCode,
                            $memberNumber,
                            $memberData['full_name'],
                            $productName,
                            $qty,
                            $price,
                            $total_amount,
                            $payment_mode
                        );

                        $saleStmt->execute();

                        $saleId = $saleStmt->insert_id;

                        if ($soldByType === 'STATE') {
                            $commissionAmount = ($total_amount * 12) / 100;
                            $commStmt = $conn->prepare("
        INSERT INTO commission_ledger
        (
            sale_id,
            receiver_type,
            receiver_id,
            commission_percent,
            commission_amount,
            from_sale_type,
            remarks
        )
        VALUES
        (?, 'STATE', ?, 12, ?, 'STATE', ?)
    ");
                            $remarks = 'State franchise commission';
                            $commStmt->bind_param('isds', $saleId, $franchiseCode, $commissionAmount, $remarks);
                            $commStmt->execute();
                            $commStmt->close();
                        }

                        if ($soldByType === 'CITY') {
                            $cityStmt = $conn->prepare("
    SELECT franchise_code
    FROM city_franchies_holder
    WHERE franchies_id = ?
       OR id = ?
    LIMIT 1
");
                            $franchiseIdString = (string)$franchiseId;
                            $cityStmt->bind_param("ss", $franchiseCode, $franchiseIdString);
                            $cityStmt->execute();
                            $cityResult = $cityStmt->get_result();
                            $cityData = $cityResult->fetch_assoc();
                            $parentStateId = $cityData['franchise_code'] ?? '';
                            $cityStmt->close();

                            $cityCommission = ($total_amount * 10) / 100;
                            $remarks = 'City franchise commission';
                            $commStmt = $conn->prepare("
        INSERT INTO commission_ledger
        (
            sale_id,
            receiver_type,
            receiver_id,
            commission_percent,
            commission_amount,
            from_sale_type,
            remarks
        )
        VALUES
        (?, 'CITY', ?, 10, ?, 'CITY', ?)
    ");
                            $commStmt->bind_param('isds', $saleId, $franchiseCode, $cityCommission, $remarks);
                            $commStmt->execute();
                            $commStmt->close();

                            if ($parentStateId !== '') {
                                $stateCommission = ($total_amount * 2) / 100;
                                $remarks = 'State override from city sale';
                                $commStmt = $conn->prepare("
            INSERT INTO commission_ledger
            (
                sale_id,
                receiver_type,
                receiver_id,
                commission_percent,
                commission_amount,
                from_sale_type,
                remarks
            )
            VALUES
            (?, 'STATE', ?, 2, ?, 'CITY', ?)
        ");
                                $commStmt->bind_param('isds', $saleId, $parentStateId, $stateCommission, $remarks);
                                $commStmt->execute();
                                $commStmt->close();
                            }
                        }

                        if ($soldByType === 'MOBILE') {
                            $mobileStmt = $conn->prepare("
    SELECT city_franchise_code, state_franchise_code
    FROM mobile_franchies_holder
    WHERE mobile_franchies_id = ?
       OR id = ?
    LIMIT 1
");
                            $franchiseIdString = (string)$franchiseId;
                            $mobileStmt->bind_param("ss", $franchiseCode, $franchiseIdString);
                            $mobileStmt->execute();
                            $mobileResult = $mobileStmt->get_result();
                            $mobileData = $mobileResult->fetch_assoc();
                            $parentCityId = $mobileData['city_franchise_code'] ?? '';
                            $parentStateId = $mobileData['state_franchise_code'] ?? '';
                            $mobileStmt->close();

                            $mobileCommission = ($total_amount * 7) / 100;
                            $remarks = 'Mobile franchise commission';
                            $commStmt = $conn->prepare("
        INSERT INTO commission_ledger
        (
            sale_id,
            receiver_type,
            receiver_id,
            commission_percent,
            commission_amount,
            from_sale_type,
            remarks
        )
        VALUES
        (?, 'MOBILE', ?, 7, ?, 'MOBILE', ?)
    ");
                            $commStmt->bind_param('isds', $saleId, $franchiseCode, $mobileCommission, $remarks);
                            $commStmt->execute();
                            $commStmt->close();

                            if ($parentCityId !== '') {
                                $cityCommission = ($total_amount * 3) / 100;
                                $remarks = 'City override from mobile sale';
                                $commStmt = $conn->prepare("
            INSERT INTO commission_ledger
            (
                sale_id,
                receiver_type,
                receiver_id,
                commission_percent,
                commission_amount,
                from_sale_type,
                remarks
            )
            VALUES
            (?, 'CITY', ?, 3, ?, 'MOBILE', ?)
        ");
                                $commStmt->bind_param('isds', $saleId, $parentCityId, $cityCommission, $remarks);
                                $commStmt->execute();
                                $commStmt->close();
                            }

                            if ($parentStateId !== '') {
                                $stateCommission = ($total_amount * 2) / 100;
                                $remarks = 'State override from mobile sale';
                                $commStmt = $conn->prepare("
            INSERT INTO commission_ledger
            (
                sale_id,
                receiver_type,
                receiver_id,
                commission_percent,
                commission_amount,
                from_sale_type,
                remarks
            )
            VALUES
            (?, 'STATE', ?, 2, ?, 'MOBILE', ?)
        ");
                                $commStmt->bind_param('isds', $saleId, $parentStateId, $stateCommission, $remarks);
                                $commStmt->execute();
                                $commStmt->close();
                            }
                        }

                        $saleStmt->close();

                        $conn->commit();

                        $memberDisplayName = trim((string)($memberData['full_name'] ?? ''));
                        $success = 'Member activated successfully: ' . ($memberDisplayName !== '' ? $memberDisplayName . ' (' . $memberNumber . ')' : $memberNumber) . '.';

                        if ($isAjax) {
                            jsonResponse([
                                'success'         => true,
                                'message'         => $success,
                                'stock_remaining' => $updatedStock,
                                'product_id'      => (int)$productRow['id'],
                                'product_name'    => $productName,
                                'member_status'   => 'Active'
                            ]);
                        }
                    } catch (Exception $ex) {
                        $conn->rollback();
                        @unlink(__DIR__ . '/' . $filePath);
                        $error = 'Database error: ' . $ex->getMessage();
                    }
                }
            }
        }
    }

    if ($isAjax) {
        jsonResponse([
            'success' => false,
            'message' => $error !== '' ? $error : 'Activation failed.'
        ], 422);
    }
}

if ($memberNumber !== '') {
    $memberStmt = $conn->prepare("SELECT customer_id, full_name, registration_status FROM signup_users WHERE customer_id = ? LIMIT 1");
    if ($memberStmt) {
        $memberStmt->bind_param('s', $memberNumber);
        $memberStmt->execute();
        $memberData = $memberStmt->get_result()->fetch_assoc();
        $memberStmt->close();
    }
}

$typeBadgeMap = [
    'mobile' => ['label' => '📱 Mobile Franchise', 'cls' => 'badge-mobile'],
    'city'   => ['label' => '🏙 City Franchise',   'cls' => 'badge-city'],
    'state'  => ['label' => '🏛 State Franchise',  'cls' => 'badge-state'],
];
$typeBadge = $typeBadgeMap[$franchiseType] ?? ['label' => 'Franchise', 'cls' => 'badge-state'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Member Activation</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
    :root {
        --navy:       #0f2d52;
        --navy-2:     #1a4278;
        --navy-light: #e8f2ff;
        --gold:       #c8962a;
        --gold-2:     #e8b84b;
        --gold-light: #fef7e6;
        --gold-bg:    #fffbf0;
        --green:      #16a34a;
        --green-bg:   #f0fdf4;
        --red:        #dc2626;
        --red-bg:     #fef2f2;
        --border:     #e2ddd3;
        --border-2:   #ede8de;
        --bg:         #f5f1ea;
        --card:       #fffdf9;
        --text:       #1c1a17;
        --muted:      #78726a;
        --mono:       'JetBrains Mono', monospace;
        --shadow:     0 2px 16px rgba(15,45,82,0.08);
        --shadow-lg:  0 8px 40px rgba(15,45,82,0.13);
        --radius:     14px;
        --radius-sm:  8px;
    }
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    body { background: var(--bg); color: var(--text); min-height: 100vh; font-family: 'Inter', system-ui, sans-serif; }
    .badge { display: inline-flex; align-items: center; gap: 5px; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 700; letter-spacing: 0.03em; }
    .badge-state  { background: #fef3c7; color: #92400e; border: 1px solid #f9c74f; }
    .badge-city   { background: #dbeafe; color: #1e3a5f; border: 1px solid #93c5fd; }
    .badge-mobile { background: #d1fae5; color: #065f46; border: 1px solid #6ee7b7; }
    .alert { display: flex; align-items: center; gap: 12px; padding: 8px 12px; border-radius: var(--radius-sm); font-size: 13px; font-weight: 700; margin-bottom: 12px; border: 1px solid; animation: slideDown 0.2s ease; }
    .alert svg { width: 20px; height: 20px; flex-shrink: 0; }
    .alert-success { background: var(--green-bg); color: #15803d; border-color: #bbf7d0; }
    .alert-error   { background: var(--red-bg);   color: var(--red); border-color: #fecaca; }
    .alert-warning { background: #fffbeb; color: #92400e; border-color: #fde68a; }
    .section-label { display: flex; align-items: center; gap: 10px; padding: 13px 18px; background: var(--navy); color: var(--gold-2); font-size: 12px; font-weight: 700; letter-spacing: 0.07em; text-transform: uppercase; border-radius: var(--radius) var(--radius) 0 0; }
    .section-label svg { width: 16px; height: 16px; opacity: 0.85; }
    .card { background: var(--card); border: 1px solid var(--border); border-radius: var(--radius); overflow: hidden; box-shadow: var(--shadow); animation: fadeUp 0.35s ease both; }
    .main-grid { display: grid; grid-template-columns: 1fr; gap: 20px; margin-bottom: 20px; }
    @media (max-width: 1100px) { .main-grid { grid-template-columns: 1fr; } }
    .table-scroll { overflow-x: auto; }
    .products-table { width: 100%; border-collapse: collapse; font-size: 13.5px; }
    .products-table thead th { background: var(--gold-light); color: var(--gold); font-size: 11.5px; font-weight: 700; letter-spacing: 0.05em; text-transform: uppercase; padding: 11px 14px; text-align: left; border-bottom: 1.5px solid var(--border-2); white-space: nowrap; }
    .products-table tbody tr { border-bottom: 1px solid var(--border-2); transition: background 0.15s; }
    .products-table tbody tr:last-child { border-bottom: none; }
    .products-table tbody tr:hover:not(.grand-row) { background: #fffbf0; }
    .products-table td { padding: 11px 14px; font-size: 13px; vertical-align: middle; color: var(--text); }
    .product-num  { font-size: 11px; color: var(--muted); font-weight: 700; background: var(--bg); border-radius: 6px; padding: 3px 7px; display: inline-block; }
    .product-name-cell { font-weight: 600; }
    .price-cell   { font-weight: 700; color: var(--navy-2); }
    .product-select { display: inline-flex; align-items: center; gap: 8px; font-size: 13px; font-weight: 700; color: var(--navy); }
    .product-select input { width: 16px; height: 16px; accent-color: var(--navy); }
    .row-selected { background: #f3f8ff; }
    .row-selected td { color: var(--navy); }
    .price-only { font-weight: 800; color: var(--navy-2); }
    .form-body  { padding: 24px; }
    .form-grid  { display: grid; grid-template-columns: 200px 1fr; gap: 16px 20px; align-items: start; }
    .form-label { font-size: 13px; font-weight: 700; color: var(--navy); padding-top: 11px; }
    .form-label .req { color: var(--red); margin-left: 2px; }
    .field-wrap { display: flex; flex-direction: column; gap: 5px; }
    .f-input, .f-select, .f-textarea { width: 100%; padding: 10px 14px; border: 1.5px solid var(--border); border-radius: var(--radius-sm); font-family: inherit; font-size: 13.5px; color: var(--text); background: white; outline: none; transition: border-color .18s, box-shadow .18s; -webkit-appearance: none; appearance: none; }
    .f-input:focus, .f-select:focus, .f-textarea:focus { border-color: var(--navy-2); box-shadow: 0 0 0 3px rgba(26,66,120,0.10); }
    .f-input[readonly] { background: var(--gold-light); color: var(--gold); font-family: var(--mono); font-weight: 700; font-size: 15px; border-color: #e8c96a; cursor: default; }
    .f-textarea { min-height: 80px; resize: vertical; }
    .form-divider { grid-column: 1 / -1; height: 1px; background: var(--border-2); margin: 4px 0; }
    .submit-row  { display: flex; align-items: center; gap: 16px; margin-top: 24px; padding-top: 20px; border-top: 1px solid var(--border-2); }
    .btn-submit  { display: inline-flex; align-items: center; gap: 10px; padding: 13px 32px; background: var(--navy); color: white; font-family: inherit; font-size: 15px; font-weight: 700; border: none; border-radius: var(--radius-sm); cursor: pointer; transition: background .18s, transform .15s, box-shadow .18s; box-shadow: 0 4px 16px rgba(15,45,82,0.25); }
    .btn-submit:hover  { background: var(--navy-2); transform: translateY(-1px); box-shadow: 0 8px 24px rgba(15,45,82,0.30); }
    .btn-submit:active { transform: translateY(0); }
    .btn-submit:disabled { background: #94a3b8; cursor: not-allowed; transform: none; box-shadow: none; }
    .btn-submit svg { width: 18px; height: 18px; }
    .submit-note { font-size: 12px; color: var(--muted); font-weight: 500; }
    .already-active-banner { display: flex; align-items: center; gap: 14px; background: #fffbeb; border: 1.5px solid #fbbf24; border-radius: 10px; padding: 14px 18px; margin-bottom: 16px; }
    .already-active-banner svg { width: 26px; height: 26px; color: #d97706; flex-shrink: 0; }
    .already-active-banner .banner-title { font-size: 14px; font-weight: 800; color: #92400e; }
    .already-active-banner .banner-sub   { font-size: 12.5px; color: #a16207; margin-top: 2px; }
    @media (max-width: 1024px) { .ml-64 { margin-left: 0 !important; } }
    @media (max-width: 640px) {
        .form-grid { grid-template-columns: 1fr; }
        .form-label { padding-top: 0; }
        .submit-row { flex-direction: column; align-items: flex-start; }
        .products-table thead th, .products-table td { padding: 9px 10px; font-size: 12px; }
    }
    @keyframes slideDown { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
    @keyframes fadeUp   { from { opacity: 0; transform: translateY(12px); } to { opacity: 1; transform: translateY(0); } }
    </style>
</head>
<body class="bg-[#f1f2f4] overflow-x-hidden">

    <?php include __DIR__ . '/includes/sidebar.php'; ?>

    <div class="ml-64 min-h-screen">
        <?php include __DIR__ . '/includes/navbar.php'; ?>

        <main class="px-5 py-6">
            <div class="mt-5 mb-4 flex items-center gap-4">
                <div class="w-[36px] h-[36px] rounded-[8px] bg-[#f5eee3] flex items-center justify-center shrink-0">
                    <i data-lucide="user-check" class="w-[18px] h-[18px] text-[#cd9737]"></i>
                </div>
                <h2 class="whitespace-nowrap text-[20px] font-normal text-[#40352a]" style="font-family: Georgia, 'Times New Roman', serif;">
                    MEMBER ACTIVATION
                </h2>
                <div class="w-full h-[2px] bg-[#d1a14b]"></div>
            </div>

            <div style="display:flex;align-items:center;gap:12px;margin-bottom:16px;flex-wrap:wrap;">
                <span style="font-size:14px;color:var(--muted);">
                    Welcome, <strong style="color:var(--text);"><?= e($userName) ?></strong>
                    &nbsp;·&nbsp;
                    Franchise ID: <span style="font-family:monospace;font-weight:700;color:var(--navy);"><?= e($franchiseCode ?: $franchiseId) ?></span>
                </span>
                <span class="badge <?= e($typeBadge['cls']) ?>"><?= e($typeBadge['label']) ?></span>
            </div>

            <?php if ($success): ?>
            <div class="alert alert-success">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                <?= e($success) ?>
            </div>
            <?php endif; ?>

            <?php if ($error): ?>
            <div class="alert alert-error">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                <?= e((strlen($error) > 120) ? substr($error, 0, 120) . '...' : $error) ?>
            </div>
            <?php endif; ?>

            <div id="activationAlert"></div>

            <form method="POST" enctype="multipart/form-data" id="activationForm">
                <div class="main-grid" style="margin-top:20px;">
                    <div class="card">
                        <div class="section-label">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
                            Step 1 — Select Product
                        </div>
                        <div class="table-scroll overflow-x-auto">
                            <table class="products-table">
                                <thead>
                                    <tr>
                                        <th style="width:48px;">#</th>
                                        <th>Product Name</th>
                                        <th style="width:110px;">Price (₹)</th>
                                        <th style="width:160px;">Select</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php if (!empty($products)): ?>
                                    <?php
                                    $defaultProductId = 0;
                                    foreach ($products as $defaultCandidate) {
                                        if ((int)($defaultCandidate['available_stock'] ?? 0) > 0) {
                                            $defaultProductId = (int)($defaultCandidate['id'] ?? 0);
                                            break;
                                        }
                                    }
                                    ?>
                                    <?php foreach ($products as $i => $p):
                                        $pid      = (int)$p['id'];
                                        $availableStock = (int)($p['available_stock'] ?? 0);
                                        $isOutOfStock = $availableStock <= 0;
                                        $checked  = !$isOutOfStock && $pid === $defaultProductId;
                                        $rowClass = $isOutOfStock ? 'opacity-55' : '';
                                    ?>
                                    <tr class="<?= $checked ? 'row-selected' : '' ?> <?= $rowClass ?>" data-product-id="<?= $pid ?>" data-stock-row="<?= $availableStock ?>">
                                        <td><span class="product-num"><?= $i + 1 ?></span></td>
                                        <td class="product-name-cell break-words"><?= e($p['product_name']) ?></td>
                                        <td class="price-cell price-only">
                                            <?= number_format((float)$p['amount'], 2) ?>
                                        </td>
                                        <td>
                                            <label class="product-select">
                                                <input type="radio" name="selected_product_id" value="<?= $pid ?>" data-price="<?= (float)$p['amount'] ?>" data-stock="<?= $availableStock ?>" <?= $checked ? 'checked' : '' ?> <?= $isOutOfStock ? 'disabled' : '' ?>>
                                                <span class="select-copy"><?= $isOutOfStock ? 'Out Of Stock' : 'Select product' ?></span>
                                            </label>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="4" style="text-align:center;padding:32px;color:var(--muted);font-size:14px;">No active products found.</td>
                                    </tr>
                                <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="section-label">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
                        Step 2 — Member and Payment Details
                    </div>
                    <div class="form-body">
                        <div id="alreadyActiveBanner" style="display:none;" class="already-active-banner">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                                <circle cx="12" cy="12" r="10"/>
                                <polyline points="9 12 11 14 15 10"/>
                            </svg>
                            <div>
                                <div class="banner-title">Member Already Activated</div>
                                <div class="banner-sub" id="alreadyActiveBannerSub">This member's account is already active. No action is required.</div>
                            </div>
                        </div>

                        <div id="step2SuccessMessage" style="display:none;margin-bottom:16px;"></div>

                        <div class="form-grid">
                            <div class="form-label">Member ID <span class="req">*</span></div>
                            <div class="field-wrap">
                                <input type="text" id="member_number" name="member_number" class="f-input" value="<?= e($memberNumber) ?>" placeholder="Enter member number" required>
                            </div>

                            <div class="form-label">Member Name</div>
                            <div class="field-wrap">
                                <input type="text" id="member_name" class="f-input" value="<?= e($memberData['full_name'] ?? '') ?>" readonly placeholder="Member name will appear here">
                            </div>

                            <div class="form-label">Member Status</div>
                            <div class="field-wrap">
                                <input type="text" id="member_status" class="f-input" value="<?= e($memberData['registration_status'] ?? '') ?>" readonly placeholder="Status">
                            </div>

                            <div class="form-divider"></div>

                            <div class="form-label">Amount to Pay</div>
                            <div class="field-wrap">
                                <input type="text" id="amount_to_pay" class="f-input" readonly placeholder="₹0.00">
                            </div>

                            <div class="form-label">Payment Mode <span class="req">*</span></div>
                            <div class="field-wrap">
                                <select name="payment_mode" id="payment_mode" class="f-select" required>
                                    <option value="">— Select Mode —</option>
                                    <?php foreach (['Cash','UPI'] as $m): ?>
                                    <option value="<?= e($m) ?>" <?= (($_POST['payment_mode'] ?? '') === $m) ? 'selected' : '' ?>><?= e($m) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-divider"></div>

                            <div class="form-label"></div>
                            <div class="submit-row">
                                <button type="submit" name="activate_member" id="submitBtn" class="btn-submit">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
                                    Activate Member
                                </button>
                                <div class="submit-note">Saving this form will mark the member as Active in the member panel.</div>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </main>
    </div>

    <script>
    const activationForm   = document.getElementById('activationForm');
    const activationAlert  = document.getElementById('activationAlert');
    const submitBtn        = document.getElementById('submitBtn');
    const memberNumberEl   = document.getElementById('member_number');
    const memberNameEl     = document.getElementById('member_name');
    const memberStatusEl   = document.getElementById('member_status');
    const alreadyActiveBanner    = document.getElementById('alreadyActiveBanner');
    const alreadyActiveBannerSub = document.getElementById('alreadyActiveBannerSub');
    const step2SuccessMessage    = document.getElementById('step2SuccessMessage');

    let memberIsAlreadyActive = false;

    function setAlreadyActiveBanner(show, memberName) {
        memberIsAlreadyActive = show;
        if (!alreadyActiveBanner) return;
        if (show) {
            alreadyActiveBanner.style.display = 'flex';
            const sub = memberName
                ? 'Member <strong>' + escapeHtml(memberName) + '</strong> is already active. No further action is needed.'
                : 'This member\'s account is already active. No action is required.';
            if (alreadyActiveBannerSub) alreadyActiveBannerSub.innerHTML = sub;
            if (submitBtn) submitBtn.disabled = true;
        } else {
            alreadyActiveBanner.style.display = 'none';
            if (submitBtn) submitBtn.disabled = false;
        }
    }

    function escapeHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function showActivationAlert(type, message) {
        if (!activationAlert) return;
        const className = type === 'success' ? 'alert alert-success' : (type === 'warning' ? 'alert alert-warning' : 'alert alert-error');
        const iconPath  = type === 'success'
            ? '<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>'
            : '<circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>';
        activationAlert.innerHTML = '<div class="' + className + '"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">' + iconPath + '</svg><span>' + escapeHtml(message) + '</span></div>';
    }

    function getSelectedRadio() {
        return document.querySelector('input[name="selected_product_id"]:checked');
    }

    function syncSelectedProductAmount() {
        const checked     = getSelectedRadio();
        const amountToPay = document.getElementById('amount_to_pay');
        if (checked && amountToPay && !checked.disabled) {
            amountToPay.value = '₹' + (parseFloat(checked.dataset.price || '0') || 0).toFixed(2);
        } else if (amountToPay) {
            amountToPay.value = '₹0.00';
        }
    }

    document.addEventListener('change', function (e) {
        if (!e.target.matches('input[name="selected_product_id"]')) return;
        if (e.target.disabled) return;
        document.querySelectorAll('.products-table tbody tr').forEach(function (row) {
            row.classList.remove('row-selected');
        });
        const currentRow = e.target.closest('tr');
        if (currentRow) currentRow.classList.add('row-selected');
        syncSelectedProductAmount();
    });

    function selectNextAvailableProduct() {
        const firstEnabled = document.querySelector('input[name="selected_product_id"]:not(:disabled)');
        if (firstEnabled) {
            firstEnabled.checked = true;
            const nextRow = firstEnabled.closest('tr');
            document.querySelectorAll('.products-table tbody tr').forEach(function (row) {
                row.classList.remove('row-selected');
            });
            if (nextRow) nextRow.classList.add('row-selected');
        }
        syncSelectedProductAmount();
    }

    syncSelectedProductAmount();

    function updateProductStockRow(productId, remainingStock) {
        const row = document.querySelector('.products-table tbody tr[data-product-id="' + productId + '"]');
        if (!row) return;
        row.dataset.stockRow = String(remainingStock);
        const radio = row.querySelector('input[name="selected_product_id"]');
        const copy  = row.querySelector('.select-copy');
        if (remainingStock <= 0) {
            row.classList.remove('row-selected');
            row.style.opacity = '0.55';
            if (radio) { radio.disabled = true; radio.checked = false; }
            if (copy)  copy.textContent = 'Out Of Stock';
        } else {
            row.style.opacity = '';
            if (radio) { radio.disabled = false; radio.dataset.stock = String(remainingStock); }
            if (copy)  copy.textContent = 'Select product';
        }
    }

    if (activationForm) {
        activationForm.addEventListener('submit', function (event) {
            event.preventDefault();

            if (memberIsAlreadyActive) {
                showActivationAlert('warning', 'This member is already activated. No further action is needed.');
                return;
            }

            const selected = getSelectedRadio();
            if (!selected) {
                showActivationAlert('error', 'Please select one product.');
                return;
            }
            if (selected.disabled) {
                showActivationAlert('error', 'Product stock not available.');
                return;
            }

            const formData = new FormData(activationForm);
            formData.append('activate_member', '1');

            if (submitBtn) submitBtn.disabled = true;

            fetch(window.location.href, {
                method: 'POST',
                body: formData,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(function (response) {
                return response.json().then(function (data) {
                    return { ok: response.ok, data: data };
                });
            })
            .then(function (result) {
                const data = result.data || {};

                if (data.already_active) {
                    setAlreadyActiveBanner(true, data.member_name || '');
                    showActivationAlert('warning', data.message || 'This member is already activated.');
                    if (memberStatusEl) memberStatusEl.value = 'Active';
                    return;
                }

                if (!result.ok || !data.success) {
                    showActivationAlert('error', data.message || 'Activation failed.');
                    if (submitBtn) submitBtn.disabled = false;
                    return;
                }

                showActivationAlert('success', data.message || 'Member activated successfully.');

                if (step2SuccessMessage) {
                    step2SuccessMessage.style.display = 'block';
                    step2SuccessMessage.innerHTML = '<div style="background:#dcfce7;border:1px solid #16a34a;color:#166534;padding:15px;border-radius:10px;font-weight:600;">✅ ' + escapeHtml(data.message) + '</div>';
                }

                if (memberStatusEl) memberStatusEl.value = data.member_status || 'Active';

                setAlreadyActiveBanner(true, memberNameEl ? memberNameEl.value : '');

                updateProductStockRow(parseInt(data.product_id || '0', 10), parseInt(data.stock_remaining || '0', 10));
                if (parseInt(data.stock_remaining || '0', 10) <= 0) {
                    selectNextAvailableProduct();
                } else {
                    syncSelectedProductAmount();
                }
            })
            .catch(function () {
                showActivationAlert('error', 'Unexpected server error.');
                if (submitBtn) submitBtn.disabled = false;
            });
        });
    }

    (function () {
        if (!memberNumberEl) return;
        let t;
        memberNumberEl.addEventListener('input', function () {
            clearTimeout(t);
            setAlreadyActiveBanner(false, '');
            if (step2SuccessMessage) step2SuccessMessage.style.display = 'none';
            activationAlert.innerHTML = '';

            t = setTimeout(function () {
                const v = memberNumberEl.value.trim();
                if (!v) {
                    if (memberNameEl)   memberNameEl.value   = '';
                    if (memberStatusEl) memberStatusEl.value = '';
                    return;
                }
                fetch('member-activation.php?lookup_member=' + encodeURIComponent(v))
                    .then(r => r.json())
                    .then(j => {
                        if (j && j.found && j.data) {
                            if (memberNameEl)   memberNameEl.value   = j.data.full_name || '';
                            if (memberStatusEl) memberStatusEl.value = j.data.registration_status || '';
                            if (j.already_active) {
                                setAlreadyActiveBanner(true, j.data.full_name || '');
                            } else {
                                setAlreadyActiveBanner(false, '');
                            }
                        } else {
                            if (memberNameEl)   memberNameEl.value   = '';
                            if (memberStatusEl) memberStatusEl.value = '';
                            setAlreadyActiveBanner(false, '');
                        }
                    }).catch(() => {});
            }, 400);
        });
    })();
    </script>

</body>
</html>