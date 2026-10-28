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

$conn->set_charset('utf8mb4');

function ensureStockTables(mysqli $conn): void
{
    $conn->query("CREATE TABLE IF NOT EXISTS products (
        id INT AUTO_INCREMENT PRIMARY KEY,
        product_name VARCHAR(255) NOT NULL,
        product_price DECIMAL(12,2) DEFAULT 0.00,
        status VARCHAR(50) DEFAULT 'Active',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $conn->query("CREATE TABLE IF NOT EXISTS franchise_stock (
        id INT AUTO_INCREMENT PRIMARY KEY,
        franchise_type ENUM('state','city','mobile') NOT NULL,
        franchise_id VARCHAR(80) NOT NULL,
        franchise_code VARCHAR(50) DEFAULT NULL,
        product_id INT NOT NULL,
        product_name VARCHAR(255) NOT NULL,
        stock_qty INT NOT NULL DEFAULT 0,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_franchise_stock (franchise_type, franchise_id, product_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $conn->query("CREATE TABLE IF NOT EXISTS stock_requests (
        request_id INT AUTO_INCREMENT PRIMARY KEY,
        from_franchise_id VARCHAR(80) DEFAULT NULL,
        to_franchise_id VARCHAR(80) DEFAULT NULL,
        product_id INT DEFAULT NULL,
        quantity INT NOT NULL DEFAULT 0,
        status VARCHAR(50) DEFAULT 'Pending',
        request_type VARCHAR(50) DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $conn->query("CREATE TABLE IF NOT EXISTS stock_history (
        history_id INT AUTO_INCREMENT PRIMARY KEY,
        from_user VARCHAR(255) DEFAULT NULL,
        to_user VARCHAR(255) DEFAULT NULL,
        product_id INT DEFAULT NULL,
        quantity INT NOT NULL DEFAULT 0,
        transfer_type VARCHAR(50) DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

ensureStockTables($conn);

function detectStateStockTable(mysqli $conn): ?array
{
    $candidates = [
        [
            'table'       => 'franchies_product_stock_request',
            'balance_col' => 'product_balance',
            'id_col'      => 'franchise_id',
            'status_col'  => null,
        ],
        [
            'table'       => 'franchies_product_request',
            'balance_col' => 'product_balance',
            'id_col'      => 'franchise_id',
            'status_col'  => null,
        ],
    ];

    foreach ($candidates as $c) {
        if (!tableExists($conn, $c['table'])) continue;
        if (!colExists($conn, $c['table'], $c['balance_col'])) {
            foreach (['total_products', 'product_balance', 'balance', 'stock_qty', 'quantity'] as $altCol) {
                if (colExists($conn, $c['table'], $altCol)) {
                    $c['balance_col'] = $altCol;
                    break;
                }
            }
        }
        if (!colExists($conn, $c['table'], $c['balance_col'])) continue;
        if ($c['status_col'] !== null && !colExists($conn, $c['table'], $c['status_col'])) {
            $c['status_col'] = null;
        }
        return $c;
    }
    return null;
}

function detectCityStockTable(mysqli $conn): ?array
{
    $candidates = [
        [
            'table'       => 'city_send_product_request',
            'balance_col' => 'total_products',
            'id_col'      => 'franchise_id',
            'status_col'  => 'status',
        ],
        [
            'table'       => 'city_franchies_product_request',
            'balance_col' => 'product_balance',
            'id_col'      => 'franchise_id',
            'status_col'  => null,
        ],
        [
            'table'       => 'city_product_stock_request',
            'balance_col' => 'product_balance',
            'id_col'      => 'franchise_id',
            'status_col'  => null,
        ],
    ];

    foreach ($candidates as $c) {
        if (!tableExists($conn, $c['table'])) continue;
        foreach (['total_products', 'product_balance', 'balance', 'stock_qty', 'quantity'] as $altCol) {
            if (colExists($conn, $c['table'], $altCol)) {
                $c['balance_col'] = $altCol;
                break;
            }
        }
        if (!colExists($conn, $c['table'], $c['balance_col'])) continue;
        if ($c['status_col'] !== null && !colExists($conn, $c['table'], $c['status_col'])) {
            $c['status_col'] = null;
        }
        return $c;
    }
    return null;
}

function e($str): string
{
    return htmlspecialchars((string)($str ?? ''), ENT_QUOTES, 'UTF-8');
}

function tableExists(mysqli $conn, string $table): bool
{
    $result = $conn->query("SHOW TABLES LIKE '" . $conn->real_escape_string($table) . "'");
    return $result !== false && $result->num_rows > 0;
}

function colExists(mysqli $conn, string $table, string $col): bool
{
    if (!tableExists($conn, $table)) return false;
    $result = $conn->query(
        "SHOW COLUMNS FROM `" . $conn->real_escape_string($table) . "`
         LIKE '" . $conn->real_escape_string($col) . "'"
    );
    return $result !== false && $result->num_rows > 0;
}

function getPaymentTableForType(string $type): string
{
    return match (strtolower(trim($type))) {
        'mobile' => 'mobile_franchies_payment',
        'city'   => 'city_franchies_payment',
        default  => 'franchies_payment',
    };
}

function getReceiptUrl(string $receipt): string
{
    $receipt = trim($receipt);
    if ($receipt === '') return '';
    $base  = basename(str_replace('\\', '/', $receipt));
    $paths = [
        [__DIR__ . '/' . ltrim($receipt, '/'), ltrim($receipt, '/')],
        [__DIR__ . '/uploads/receipts/' . $base, 'uploads/receipts/' . $base],
        [__DIR__ . '/../uploads/receipts/' . $base, '../uploads/receipts/' . $base],
    ];
    foreach ($paths as [$abs, $rel]) {
        if (file_exists($abs)) return $rel;
    }
    return ltrim($receipt, '/');
}

function isJsonRequest(): bool
{
    return (
        isset($_SERVER['HTTP_ACCEPT']) &&
        str_contains(strtolower((string)$_SERVER['HTTP_ACCEPT']), 'application/json')
    ) || (
        isset($_SERVER['HTTP_X_REQUESTED_WITH']) &&
        strtolower((string)$_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest'
    );
}

function sendJsonResponse(array $payload, int $statusCode = 200): void
{
    header('Content-Type: application/json; charset=utf-8');
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function normalizeProductName(string $name): string
{
    return strtolower(trim(preg_replace('/\s+/', ' ', $name)));
}

function resolveProductId(mysqli $conn, string $productName): int
{
    $name = trim($productName);
    if ($name === '') return 0;

    $candidateTables = [
        'franchies_add_product'    => ['id', ['product_name', 'name', 'title']],
        'franchies_member_product' => ['id', ['product_name', 'name', 'title']],
    ];

    foreach ($candidateTables as $table => [$idCol, $nameCols]) {
        if (!tableExists($conn, $table)) continue;
        foreach ($nameCols as $nameCol) {
            if (!colExists($conn, $table, $nameCol)) continue;
            $stmt = $conn->prepare(
                "SELECT `$idCol` AS pid FROM `$table`
                 WHERE LOWER(TRIM(`$nameCol`)) = LOWER(TRIM(?)) LIMIT 1"
            );
            if (!$stmt) continue;
            $stmt->bind_param('s', $name);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if (!empty($row['pid'])) return (int)$row['pid'];
        }
    }

    $hash      = hash('crc32b', normalizeProductName($name));
    $hashValue = hexdec((string)$hash) % 1000000000;
    return -(int)($hashValue === 0 ? 1 : $hashValue);
}

function createStockRequestRecord(mysqli $conn, string $fromFranchiseId, string $toFranchiseId, int $productId, int $quantity, string $status, string $requestType): bool
{
    if ($fromFranchiseId === '' || $toFranchiseId === '' || $productId === 0 || $quantity <= 0) return false;
    $stmt = $conn->prepare(
        'INSERT INTO stock_requests (from_franchise_id, to_franchise_id, product_id, quantity, status, request_type, created_at)
         VALUES (?, ?, ?, ?, ?, ?, NOW())'
    );
    if (!$stmt) return false;
    $stmt->bind_param('ssiiss', $fromFranchiseId, $toFranchiseId, $productId, $quantity, $status, $requestType);
    $ok = $stmt->execute();
    $stmt->close();
    return (bool)$ok;
}

function createStockHistoryRecord(mysqli $conn, string $fromUser, string $toUser, int $productId, int $quantity, string $transferType): bool
{
    if ($fromUser === '' || $toUser === '' || $productId === 0 || $quantity <= 0) return false;
    $stmt = $conn->prepare(
        'INSERT INTO stock_history (from_user, to_user, product_id, quantity, transfer_type, created_at)
         VALUES (?, ?, ?, ?, ?, NOW())'
    );
    if (!$stmt) return false;
    $stmt->bind_param('ssiss', $fromUser, $toUser, $productId, $quantity, $transferType);
    $ok = $stmt->execute();
    $stmt->close();
    return (bool)$ok;
}

function syncFranchiseStockRecord(mysqli $conn, string $franchiseType, string $franchiseId, string $franchiseCode, string $productName, int $quantity): bool
{
    if ($franchiseType === '' || $franchiseId === '' || $productName === '' ||
        $quantity < 0 || !tableExists($conn, 'franchise_stock')) {
        return false;
    }

    $productId     = resolveProductId($conn, $productName);
    $franchiseCode = trim($franchiseCode);
    $useCode       = colExists($conn, 'franchise_stock', 'franchise_code');

    $selectSql = $useCode
        ? 'SELECT id FROM franchise_stock WHERE franchise_type=? AND (franchise_id=? OR franchise_code=?) AND LOWER(TRIM(product_name))=LOWER(TRIM(?)) LIMIT 1 FOR UPDATE'
        : 'SELECT id FROM franchise_stock WHERE franchise_type=? AND franchise_id=? AND LOWER(TRIM(product_name))=LOWER(TRIM(?)) LIMIT 1 FOR UPDATE';

    $stmt = $conn->prepare($selectSql);
    if (!$stmt) return false;

    if ($useCode) {
        $stmt->bind_param('ssss', $franchiseType, $franchiseId, $franchiseCode, $productName);
    } else {
        $stmt->bind_param('sss', $franchiseType, $franchiseId, $productName);
    }
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($row) {
        $updateSql = $useCode
            ? 'UPDATE franchise_stock SET stock_qty=?, franchise_code=?, updated_at=NOW() WHERE id=?'
            : 'UPDATE franchise_stock SET stock_qty=?, updated_at=NOW() WHERE id=?';
        $upd = $conn->prepare($updateSql);
        if (!$upd) return false;
        if ($useCode) {
            $upd->bind_param('isi', $quantity, $franchiseCode, $row['id']);
        } else {
            $upd->bind_param('ii', $quantity, $row['id']);
        }
        $ok = $upd->execute();
        $upd->close();
        return (bool)$ok;
    }

    $ins = $conn->prepare(
        'INSERT INTO franchise_stock (franchise_type, franchise_id, franchise_code, product_id, product_name, stock_qty)
         VALUES (?, ?, ?, ?, ?, ?)'
    );
    if (!$ins) return false;
    $ins->bind_param('sssisi', $franchiseType, $franchiseId, $franchiseCode, $productId, $productName, $quantity);
    $ok = $ins->execute();
    $ins->close();
    return (bool)$ok;
}

function getFranchiseStockBalance(mysqli $conn, string $franchiseType, string $franchiseId, string $franchiseCode, string $productName): int
{
    $franchiseType = strtolower(trim($franchiseType));
    $franchiseId   = trim($franchiseId);
    $franchiseCode = trim($franchiseCode);
    $productName   = trim($productName);

    if ($franchiseType === '' || ($franchiseId === '' && $franchiseCode === '') || $productName === '') return 0;

    if (tableExists($conn, 'franchise_stock')) {
        $useCode = colExists($conn, 'franchise_stock', 'franchise_code');
        $sql     = $useCode
            ? 'SELECT IFNULL(SUM(stock_qty),0) AS qty FROM franchise_stock WHERE franchise_type=? AND (franchise_id=? OR franchise_code=?) AND LOWER(TRIM(product_name))=LOWER(TRIM(?))'
            : 'SELECT IFNULL(SUM(stock_qty),0) AS qty FROM franchise_stock WHERE franchise_type=? AND franchise_id=? AND LOWER(TRIM(product_name))=LOWER(TRIM(?))';
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            if ($useCode) { $stmt->bind_param('ssss', $franchiseType, $franchiseId, $franchiseCode, $productName); }
            else          { $stmt->bind_param('sss', $franchiseType, $franchiseId, $productName); }
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if ((int)($row['qty'] ?? 0) > 0) return (int)$row['qty'];
        }
    }

    if (tableExists($conn, 'franchise_stock_wallet')) {
        $useCode = colExists($conn, 'franchise_stock_wallet', 'franchise_code');
        $sql     = $useCode
            ? 'SELECT IFNULL(SUM(stock_qty),0) AS qty FROM franchise_stock_wallet WHERE franchise_type=? AND (franchise_id=? OR franchise_code=?) AND LOWER(TRIM(product_name))=LOWER(TRIM(?))'
            : 'SELECT IFNULL(SUM(stock_qty),0) AS qty FROM franchise_stock_wallet WHERE franchise_type=? AND franchise_id=? AND LOWER(TRIM(product_name))=LOWER(TRIM(?))';
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            if ($useCode) { $stmt->bind_param('ssss', $franchiseType, $franchiseId, $franchiseCode, $productName); }
            else          { $stmt->bind_param('sss', $franchiseType, $franchiseId, $productName); }
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if ((int)($row['qty'] ?? 0) > 0) return (int)$row['qty'];
        }
    }

    if ($franchiseType === 'state') {
        $qty = getStateStockFromRequestTable($conn, $franchiseId, $productName);
        if ($qty > 0) return $qty;
    }

    return 0;
}

function getStateStockFromRequestTable(mysqli $conn, string $franchiseId, string $productName): int
{
    $franchiseId = trim($franchiseId);
    $productName = trim($productName);
    if ($franchiseId === '' || $productName === '') return 0;

    $tableInfo = detectStateStockTable($conn);
    if ($tableInfo === null) return 0;

    $table      = $tableInfo['table'];
    $balanceCol = $tableInfo['balance_col'];
    $statusCol  = $tableInfo['status_col'];
    $idCol      = $tableInfo['id_col'];

    $statusWhere = '';
    if ($statusCol !== null) {
        $statusWhere = " AND LOWER(TRIM(`$statusCol`)) IN ('approved','completed','approve','pending') ";
    }

    $sql = "SELECT IFNULL(SUM(`$balanceCol`), 0) AS qty FROM `$table`
            WHERE `$idCol` = ? AND LOWER(TRIM(product_name)) = LOWER(TRIM(?)) $statusWhere";

    $stmt = $conn->prepare($sql);
    if (!$stmt) return 0;

    if (is_numeric($franchiseId)) {
        $intId = (int)$franchiseId;
        $stmt->bind_param('is', $intId, $productName);
    } else {
        $stmt->bind_param('ss', $franchiseId, $productName);
    }

    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return max(0, (int)($row['qty'] ?? 0));
}

function getFranchiseStockBalanceFromStockTable(mysqli $conn, string $franchiseType, string $franchiseId, string $franchiseCode, string $productName): int
{
    if (!tableExists($conn, 'franchise_stock')) return 0;

    $franchiseType = strtolower(trim($franchiseType));
    $franchiseId   = trim($franchiseId);
    $franchiseCode = trim($franchiseCode);
    $productName   = trim($productName);

    if ($franchiseType === '' || ($franchiseId === '' && $franchiseCode === '') || $productName === '') return 0;

    $useCode = colExists($conn, 'franchise_stock', 'franchise_code');
    $sql     = $useCode
        ? 'SELECT IFNULL(SUM(stock_qty),0) AS qty FROM franchise_stock WHERE franchise_type=? AND (franchise_id=? OR franchise_code=?) AND LOWER(TRIM(product_name))=LOWER(TRIM(?))'
        : 'SELECT IFNULL(SUM(stock_qty),0) AS qty FROM franchise_stock WHERE franchise_type=? AND franchise_id=? AND LOWER(TRIM(product_name))=LOWER(TRIM(?))';

    $stmt = $conn->prepare($sql);
    if (!$stmt) return 0;
    if ($useCode) { $stmt->bind_param('ssss', $franchiseType, $franchiseId, $franchiseCode, $productName); }
    else          { $stmt->bind_param('sss', $franchiseType, $franchiseId, $productName); }
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return max(0, (int)($row['qty'] ?? 0));
}

function syncFranchiseWalletFromStock(mysqli $conn, string $franchiseType, string $franchiseId, string $franchiseCode, string $productName): bool
{
    if (!tableExists($conn, 'franchise_stock') || !tableExists($conn, 'franchise_stock_wallet')) return false;

    $stockQty = getFranchiseStockBalanceFromStockTable($conn, $franchiseType, $franchiseId, $franchiseCode, $productName);
    if ($stockQty <= 0) return false;

    $useCode   = colExists($conn, 'franchise_stock_wallet', 'franchise_code');
    $selectSql = $useCode
        ? 'SELECT id, stock_qty FROM franchise_stock_wallet WHERE franchise_type=? AND (franchise_id=? OR franchise_code=?) AND LOWER(TRIM(product_name))=LOWER(TRIM(?)) LIMIT 1 FOR UPDATE'
        : 'SELECT id, stock_qty FROM franchise_stock_wallet WHERE franchise_type=? AND franchise_id=? AND LOWER(TRIM(product_name))=LOWER(TRIM(?)) LIMIT 1 FOR UPDATE';

    $stmt = $conn->prepare($selectSql);
    if (!$stmt) return false;
    if ($useCode) { $stmt->bind_param('ssss', $franchiseType, $franchiseId, $franchiseCode, $productName); }
    else          { $stmt->bind_param('sss', $franchiseType, $franchiseId, $productName); }
    $stmt->execute();
    $walletRow = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($walletRow) {
        if ((int)$walletRow['stock_qty'] >= $stockQty) return true;
        $upd = $conn->prepare('UPDATE franchise_stock_wallet SET stock_qty=? WHERE id=?');
        if (!$upd) return false;
        $upd->bind_param('ii', $stockQty, $walletRow['id']);
        $ok = $upd->execute();
        $upd->close();
        return (bool)$ok;
    }

    $productId = resolveProductId($conn, $productName);
    $ins       = $conn->prepare(
        'INSERT INTO franchise_stock_wallet (franchise_type, franchise_id, product_id, product_name, stock_qty) VALUES (?, ?, ?, ?, ?)'
    );
    if (!$ins) return false;
    $ins->bind_param('ssisi', $franchiseType, $franchiseId, $productId, $productName, $stockQty);
    $ok = $ins->execute();
    $ins->close();
    return (bool)$ok;
}

function hasSufficientFranchiseStock(mysqli $conn, string $franchiseType, string $franchiseId, string $franchiseCode, string $productName, int $requiredQuantity): bool
{
    if ($requiredQuantity <= 0) return true;
    if ($franchiseType === '' || ($franchiseId === '' && $franchiseCode === '') || trim($productName) === '') return false;
    return getFranchiseStockBalance($conn, $franchiseType, $franchiseId, $franchiseCode, $productName) >= $requiredQuantity;
}

function fetchHolderRow(mysqli $conn, string $table, string $franchiseId, string $franchiseCode): array
{
    $franchiseId   = trim($franchiseId);
    $franchiseCode = trim($franchiseCode);

    if ($franchiseId !== '' && is_numeric($franchiseId) && tableExists($conn, $table)) {
        $stmt = $conn->prepare("SELECT * FROM `$table` WHERE id=? LIMIT 1");
        if ($stmt) {
            $id = (int)$franchiseId;
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc() ?: [];
            $stmt->close();
            if (!empty($row)) return $row;
        }
    }

    if ($franchiseCode === '' || !tableExists($conn, $table)) return [];

    $tryCols = [
        'franchies_id','franchise_id','franchise_code',
        'mobile_franchies_id','mobile_franchise_id',
        'city_franchies_id','city_franchise_id',
        'city_franchise_code','mobile_franchise_code',
    ];
    foreach ($tryCols as $col) {
        if (!colExists($conn, $table, $col)) continue;
        $stmt = $conn->prepare("SELECT * FROM `$table` WHERE `$col`=? LIMIT 1");
        if (!$stmt) continue;
        $stmt->bind_param('s', $franchiseCode);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc() ?: [];
        $stmt->close();
        if (!empty($row)) return $row;
    }

    return [];
}

function getCityFranchisePrimaryCode(array $cityRow): string
{
    foreach (['city_franchies_id','city_franchise_id','city_franchise_code','franchies_id','franchise_id','franchise_code'] as $col) {
        if (!empty($cityRow[$col])) return trim((string)$cityRow[$col]);
    }
    return '';
}

function cityFranchiseHasAvailableStock(mysqli $conn, array $cityRow): bool
{
    if (empty($cityRow)) return false;

    $cityId        = (int)($cityRow['id'] ?? 0);
    $cityCode      = getCityFranchisePrimaryCode($cityRow);
    $cityLookupVal = $cityId > 0 ? (string)$cityId : $cityCode;
    $franchiseType = 'city';

    if ($cityLookupVal === '') return false;

    foreach (['franchise_stock', 'franchise_stock_wallet'] as $tbl) {
        if (!tableExists($conn, $tbl)) continue;
        $useCode = colExists($conn, $tbl, 'franchise_code');
        $sql     = $useCode
            ? "SELECT 1 FROM `$tbl` WHERE franchise_type=? AND (franchise_id=? OR franchise_code=?) AND stock_qty>0 LIMIT 1"
            : "SELECT 1 FROM `$tbl` WHERE franchise_type=? AND franchise_id=? AND stock_qty>0 LIMIT 1";
        $stmt = $conn->prepare($sql);
        if (!$stmt) continue;
        if ($useCode) { $stmt->bind_param('sss', $franchiseType, $cityLookupVal, $cityCode); }
        else          { $stmt->bind_param('ss', $franchiseType, $cityLookupVal); }
        $stmt->execute();
        $has = (bool)$stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($has) return true;
    }

    return false;
}

function findFallbackCityFranchiseWithAvailableStock(mysqli $conn, string $cityName, string $stateField, int $excludeId = 0): array
{
    $cityName   = trim($cityName);
    $stateField = trim($stateField);

    if ($cityName === '' || !tableExists($conn, 'city_franchies_holder')) return [];

    $useStateCode = colExists($conn, 'city_franchies_holder', 'state_code');

    if ($stateField !== '' && $useStateCode) {
        $sql = 'SELECT * FROM city_franchies_holder WHERE LOWER(TRIM(city))=LOWER(TRIM(?)) AND (LOWER(TRIM(state))=LOWER(TRIM(?)) OR LOWER(TRIM(state_code))=LOWER(TRIM(?)))'
            . ($excludeId > 0 ? ' AND id!=?' : '') . ' ORDER BY id ASC';
    } elseif ($stateField !== '') {
        $sql = 'SELECT * FROM city_franchies_holder WHERE LOWER(TRIM(city))=LOWER(TRIM(?)) AND LOWER(TRIM(state))=LOWER(TRIM(?))'
            . ($excludeId > 0 ? ' AND id!=?' : '') . ' ORDER BY id ASC';
    } else {
        $sql = 'SELECT * FROM city_franchies_holder WHERE LOWER(TRIM(city))=LOWER(TRIM(?))'
            . ($excludeId > 0 ? ' AND id!=?' : '') . ' ORDER BY id ASC';
    }

    $stmt = $conn->prepare($sql);
    if (!$stmt) return [];

    if ($stateField !== '' && $useStateCode) {
        $excludeId > 0 ? $stmt->bind_param('sssi', $cityName, $stateField, $stateField, $excludeId)
                       : $stmt->bind_param('sss', $cityName, $stateField, $stateField);
    } elseif ($stateField !== '') {
        $excludeId > 0 ? $stmt->bind_param('ssi', $cityName, $stateField, $excludeId)
                       : $stmt->bind_param('ss', $cityName, $stateField);
    } else {
        $excludeId > 0 ? $stmt->bind_param('si', $cityName, $excludeId)
                       : $stmt->bind_param('s', $cityName);
    }

    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        if (cityFranchiseHasAvailableStock($conn, $row)) {
            $stmt->close();
            return $row;
        }
    }
    $stmt->close();
    return [];
}

function resolveCityFranchiseForOrder(mysqli $conn, string $franchiseId, string $franchiseCode): array
{
    $row = fetchHolderRow($conn, 'mobile_franchies_holder', $franchiseId, $franchiseCode);
    if (empty($row)) return ['city_id' => 0, 'city_code' => ''];

    $parentCols = [
        'city_franchies_id','city_franchise_id','city_franchies_holder_id',
        'city_holder_id','city_franchies_code','city_franchise_code',
        'city_code','under_city_id','parent_id','city_id',
        'franchies_id','franchise_id','franchise_code',
    ];
    $parentValue = '';
    foreach ($parentCols as $col) {
        if (isset($row[$col]) && trim((string)$row[$col]) !== '') {
            $parentValue = trim((string)$row[$col]);
            break;
        }
    }
    if ($parentValue === '' && !empty($row['city'])) {
        $parentValue = trim((string)$row['city']);
    }
    if ($parentValue === '') return ['city_id' => 0, 'city_code' => ''];

    if (is_numeric($parentValue) && tableExists($conn, 'city_franchies_holder')) {
        $stmt = $conn->prepare('SELECT * FROM city_franchies_holder WHERE id=? LIMIT 1');
        if ($stmt) {
            $id = (int)$parentValue;
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $cityRow = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if ($cityRow) {
                if (!cityFranchiseHasAvailableStock($conn, $cityRow)) {
                    $fb = findFallbackCityFranchiseWithAvailableStock($conn, $cityRow['city'] ?? '', $cityRow['state'] ?? '', (int)($cityRow['id'] ?? 0));
                    if (!empty($fb)) $cityRow = $fb;
                }
                return [
                    'city_id'   => (int)($cityRow['id'] ?? 0),
                    'city_code' => trim((string)($cityRow['franchies_id'] ?? $cityRow['franchise_id'] ?? $cityRow['franchise_code'] ?? '')),
                ];
            }
        }
    }

    $codeCols = ['city_franchies_id','city_franchise_id','city_franchies_code','city_franchise_code','city_holder_id','franchies_id','franchise_id','franchise_code'];
    foreach ($codeCols as $col) {
        if (!colExists($conn, 'city_franchies_holder', $col)) continue;
        $stmt = $conn->prepare("SELECT * FROM city_franchies_holder WHERE `$col`=? LIMIT 1");
        if (!$stmt) continue;
        $stmt->bind_param('s', $parentValue);
        $stmt->execute();
        $cityRow = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($cityRow) {
            if (!cityFranchiseHasAvailableStock($conn, $cityRow)) {
                $fb = findFallbackCityFranchiseWithAvailableStock($conn, $cityRow['city'] ?? '', $cityRow['state'] ?? '', (int)($cityRow['id'] ?? 0));
                if (!empty($fb)) $cityRow = $fb;
            }
            return [
                'city_id'   => (int)($cityRow['id'] ?? 0),
                'city_code' => trim((string)($cityRow['city_franchies_id'] ?? $cityRow['city_franchise_id'] ?? $cityRow['city_franchise_code'] ?? $cityRow['franchies_id'] ?? $cityRow['franchise_id'] ?? $cityRow['franchise_code'] ?? '')),
            ];
        }
    }

    return ['city_id' => 0, 'city_code' => ''];
}

function resolveStateFranchiseForOrder(mysqli $conn, string $type, string $franchiseId, string $franchiseCode): array
{
    $type          = strtolower(trim($type));
    $franchiseId   = trim($franchiseId);
    $franchiseCode = trim($franchiseCode);
    $stateId       = 0;
    $stateCode     = '';

    $resolveParent = function ($pv) use ($conn, &$stateId, &$stateCode) {
        $pv = trim((string)$pv);
        if ($pv === '' || !tableExists($conn, 'franchies_holder')) return;

        if (is_numeric($pv)) {
            $stmt = $conn->prepare('SELECT * FROM franchies_holder WHERE id=? LIMIT 1');
            if ($stmt) {
                $id = (int)$pv;
                $stmt->bind_param('i', $id);
                $stmt->execute();
                $row = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                if (!empty($row)) {
                    $stateId   = (int)($row['id'] ?? 0);
                    $stateCode = trim((string)($row['franchise_code'] ?? ''));
                    return;
                }
            }
        }

        if (colExists($conn, 'franchies_holder', 'franchise_code')) {
            $stmt = $conn->prepare('SELECT * FROM franchies_holder WHERE franchise_code=? LIMIT 1');
            if ($stmt) {
                $stmt->bind_param('s', $pv);
                $stmt->execute();
                $row = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                if (!empty($row)) {
                    $stateId   = (int)($row['id'] ?? 0);
                    $stateCode = trim((string)($row['franchise_code'] ?? $pv));
                    return;
                }
            }
        }

        if ($stateCode === '') $stateCode = $pv;
    };

    $resolveByName = function ($name) use ($conn, &$stateId, &$stateCode) {
        $name = trim((string)$name);
        if ($name === '' || !tableExists($conn, 'franchies_holder')) return;
        foreach ([
            'SELECT * FROM franchies_holder WHERE LOWER(TRIM(franchise_code))=LOWER(TRIM(?)) LIMIT 1',
            'SELECT * FROM franchies_holder WHERE LOWER(TRIM(state))=LOWER(TRIM(?)) LIMIT 1',
            'SELECT * FROM franchies_holder WHERE LOWER(TRIM(name))=LOWER(TRIM(?)) LIMIT 1',
        ] as $q) {
            $stmt = $conn->prepare($q);
            if (!$stmt) continue;
            $stmt->bind_param('s', $name);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if (!empty($row)) {
                $stateId   = (int)($row['id'] ?? 0);
                $stateCode = trim((string)($row['franchise_code'] ?? ''));
                break;
            }
        }
    };

    if ($type === 'city') {
        $row = fetchHolderRow($conn, 'city_franchies_holder', $franchiseId, $franchiseCode);
        if (!empty($row)) {
            foreach (['state_franchies_id','state_franchise_id','state_franchies_holder_id','state_holder_id','state_code','state_franchise_code','under_state_id','parent_id','state_id'] as $col) {
                if (!empty($row[$col])) { $resolveParent($row[$col]); break; }
            }
            if ($stateId <= 0 && $stateCode === '' && !empty($row['state'])) {
                $resolveByName($row['state']);
            }
        }
    } elseif ($type === 'mobile') {
        $row        = fetchHolderRow($conn, 'mobile_franchies_holder', $franchiseId, $franchiseCode);
        $cityParent = '';
        if (!empty($row)) {
            foreach (['city_franchies_id','city_franchise_id','city_franchies_holder_id','city_holder_id','city_code','city_franchise_code','under_city_id','parent_id','city_id'] as $col) {
                if (!empty($row[$col])) { $cityParent = trim((string)$row[$col]); break; }
            }
            if ($cityParent === '' && !empty($row['city'])) $cityParent = trim((string)$row['city']);
        }

        $cityRow = [];
        if ($cityParent !== '' && tableExists($conn, 'city_franchies_holder')) {
            if (is_numeric($cityParent)) {
                $stmt = $conn->prepare('SELECT * FROM city_franchies_holder WHERE id=? LIMIT 1');
                if ($stmt) {
                    $id = (int)$cityParent;
                    $stmt->bind_param('i', $id);
                    $stmt->execute();
                    $cityRow = $stmt->get_result()->fetch_assoc() ?: [];
                    $stmt->close();
                }
            }
            if (empty($cityRow)) {
                foreach (['city_franchies_id','city_franchise_id','city_franchies_code','franchies_id','franchise_id','franchise_code'] as $col) {
                    if (!colExists($conn, 'city_franchies_holder', $col)) continue;
                    $stmt = $conn->prepare("SELECT * FROM city_franchies_holder WHERE `$col`=? LIMIT 1");
                    if (!$stmt) continue;
                    $stmt->bind_param('s', $cityParent);
                    $stmt->execute();
                    $cityRow = $stmt->get_result()->fetch_assoc() ?: [];
                    $stmt->close();
                    if (!empty($cityRow)) break;
                }
            }
            if (!empty($cityRow) && !cityFranchiseHasAvailableStock($conn, $cityRow)) {
                $fb = findFallbackCityFranchiseWithAvailableStock($conn, $cityRow['city'] ?? '', $cityRow['state'] ?? '', (int)($cityRow['id'] ?? 0));
                if (!empty($fb)) $cityRow = $fb;
            }
        }

        if (!empty($cityRow)) {
            foreach (['state_franchies_id','state_franchise_id','state_franchies_holder_id','state_holder_id','state_code','state_franchise_code','under_state_id','parent_id','state_id'] as $col) {
                if (!empty($cityRow[$col])) { $resolveParent($cityRow[$col]); break; }
            }
        }
        if ($stateId <= 0 && $stateCode === '' && !empty($row['state'])) {
            $resolveByName($row['state']);
        }
    }

    return ['state_id' => $stateId, 'state_code' => $stateCode];
}

function resolveOrderSupplier(mysqli $conn, string $franchiseType, string $franchiseId, string $franchiseCode): array
{
    $franchiseType = strtolower(trim($franchiseType));
    if ($franchiseType === 'city') {
        $info = resolveStateFranchiseForOrder($conn, $franchiseType, $franchiseId, $franchiseCode);
        return ['supplier_type' => 'state', 'supplier_id' => (string)($info['state_id'] ?? ''), 'supplier_code' => trim((string)($info['state_code'] ?? ''))];
    }
    if ($franchiseType === 'mobile') {
        $info = resolveCityFranchiseForOrder($conn, $franchiseId, $franchiseCode);
        return ['supplier_type' => 'city', 'supplier_id' => (string)($info['city_id'] ?? ''), 'supplier_code' => trim((string)($info['city_code'] ?? ''))];
    }
    return ['supplier_type' => '', 'supplier_id' => '', 'supplier_code' => ''];
}

function resolveAllStateCandidatesForCity(mysqli $conn, string $franchiseId, string $franchiseCode): array
{
    $candidates = [];
    $cityRow    = fetchHolderRow($conn, 'city_franchies_holder', $franchiseId, $franchiseCode);
    if (empty($cityRow)) return $candidates;

    $parentCols = [
        'state_franchies_id','state_franchise_id','state_franchies_holder_id',
        'state_holder_id','state_code','state_franchise_code',
        'under_state_id','parent_id','state_id',
    ];
    $parentValues = [];
    foreach ($parentCols as $col) {
        if (!empty($cityRow[$col])) $parentValues[] = trim((string)$cityRow[$col]);
    }
    if (!empty($cityRow['state'])) $parentValues[] = trim((string)$cityRow['state']);

    if (!tableExists($conn, 'franchies_holder')) {
        foreach ($parentValues as $pv) {
            if ($pv !== '') $candidates[] = ['id' => 0, 'code' => $pv];
        }
        return $candidates;
    }

    foreach ($parentValues as $pv) {
        if ($pv === '') continue;

        if (is_numeric($pv)) {
            $stmt = $conn->prepare('SELECT * FROM franchies_holder WHERE id=? LIMIT 1');
            if ($stmt) {
                $id = (int)$pv;
                $stmt->bind_param('i', $id);
                $stmt->execute();
                $row = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                if (!empty($row)) {
                    $candidates[] = ['id' => (int)$row['id'], 'code' => trim((string)($row['franchise_code'] ?? ''))];
                    continue;
                }
            }
        }

        if (colExists($conn, 'franchies_holder', 'franchise_code')) {
            $stmt = $conn->prepare('SELECT * FROM franchies_holder WHERE franchise_code=? LIMIT 1');
            if ($stmt) {
                $stmt->bind_param('s', $pv);
                $stmt->execute();
                $row = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                if (!empty($row)) {
                    $candidates[] = ['id' => (int)$row['id'], 'code' => trim((string)($row['franchise_code'] ?? $pv))];
                    continue;
                }
            }
        }

        foreach (['state', 'name'] as $nameCol) {
            if (!colExists($conn, 'franchies_holder', $nameCol)) continue;
            $stmt = $conn->prepare("SELECT * FROM franchies_holder WHERE LOWER(TRIM(`$nameCol`))=LOWER(TRIM(?)) LIMIT 1");
            if ($stmt) {
                $stmt->bind_param('s', $pv);
                $stmt->execute();
                $row = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                if (!empty($row)) {
                    $candidates[] = ['id' => (int)$row['id'], 'code' => trim((string)($row['franchise_code'] ?? $pv))];
                    break;
                }
            }
        }

        $candidates[] = ['id' => 0, 'code' => $pv];
    }

    $seen   = [];
    $unique = [];
    foreach ($candidates as $c) {
        $key = $c['id'] . '|' . $c['code'];
        if (!isset($seen[$key])) { $seen[$key] = true; $unique[] = $c; }
    }
    return $unique;
}

function getTotalStateStockForCity(mysqli $conn, string $cityFranchiseId, string $cityFranchiseCode, string $productName): int
{
    $candidates = resolveAllStateCandidatesForCity($conn, $cityFranchiseId, $cityFranchiseCode);
    $total      = 0;
    $seen       = [];

    foreach ($candidates as $c) {
        $stateId   = (int)$c['id'];
        $stateCode = trim($c['code']);
        $lookupId  = $stateId > 0 ? (string)$stateId : $stateCode;
        if ($lookupId === '') continue;

        $key = $lookupId . '|' . $stateCode;
        if (isset($seen[$key])) continue;
        $seen[$key] = true;

        $total += getFranchiseStockBalance($conn, 'state', $lookupId, $stateCode, $productName);
    }

    return $total;
}

function deductFranchiseWalletStockDetailed(mysqli $conn, string $franchiseType, string $franchiseId, string $franchiseCode, string $productName, int $quantity): array
{
    $franchiseType = strtolower(trim($franchiseType));
    $franchiseId   = trim($franchiseId);
    $franchiseCode = trim($franchiseCode);
    $productName   = trim($productName);
    $quantity      = max(0, $quantity);

    if ($franchiseType === '' || $franchiseId === '' || $productName === '' || $quantity <= 0) {
        return ['ok' => false, 'stock_before' => 0, 'stock_after' => 0, 'message' => 'Unable to verify wallet stock.'];
    }

    if (tableExists($conn, 'franchise_stock_wallet')) {
        $useCode   = colExists($conn, 'franchise_stock_wallet', 'franchise_code');
        $attempt   = 0;
        $rows      = [];
        $available = 0;

        while (true) {
            $sql = $useCode
                ? "SELECT id, stock_qty FROM franchise_stock_wallet WHERE franchise_type=? AND (franchise_id=? OR franchise_code=?) AND LOWER(TRIM(product_name))=LOWER(TRIM(?)) AND stock_qty>0 ORDER BY id ASC FOR UPDATE"
                : "SELECT id, stock_qty FROM franchise_stock_wallet WHERE franchise_type=? AND franchise_id=? AND LOWER(TRIM(product_name))=LOWER(TRIM(?)) AND stock_qty>0 ORDER BY id ASC FOR UPDATE";

            $stmt = $conn->prepare($sql);
            if (!$stmt) break;
            if ($useCode) { $stmt->bind_param('ssss', $franchiseType, $franchiseId, $franchiseCode, $productName); }
            else          { $stmt->bind_param('sss', $franchiseType, $franchiseId, $productName); }
            $stmt->execute();
            $result    = $stmt->get_result();
            $rows      = [];
            $available = 0;
            while ($row = $result->fetch_assoc()) {
                $qty = max(0, (int)($row['stock_qty'] ?? 0));
                if ($qty <= 0) continue;
                $rows[]    = ['id' => (int)$row['id'], 'stock_qty' => $qty];
                $available += $qty;
            }
            $stmt->close();

            if ($available >= $quantity) break;
            if ($attempt > 0 || !tableExists($conn, 'franchise_stock')) break;

            $stockQty = getFranchiseStockBalanceFromStockTable($conn, $franchiseType, $franchiseId, $franchiseCode, $productName);
            if ($stockQty < $quantity) break;
            if (!syncFranchiseWalletFromStock($conn, $franchiseType, $franchiseId, $franchiseCode, $productName)) break;
            $attempt++;
        }

        if ($available >= $quantity && !empty($rows)) {
            $upd = $conn->prepare('UPDATE franchise_stock_wallet SET stock_qty=stock_qty-? WHERE id=? AND stock_qty>=?');
            if ($upd) {
                $remaining = $quantity;
                foreach ($rows as $row) {
                    if ($remaining <= 0) break;
                    $deduct = min($row['stock_qty'], $remaining);
                    $upd->bind_param('iii', $deduct, $row['id'], $deduct);
                    $upd->execute();
                    if ($upd->affected_rows > 0) $remaining -= $deduct;
                }
                $upd->close();

                if ($remaining <= 0) {
                    if (tableExists($conn, 'franchise_stock')) {
                        syncFranchiseStockRecord($conn, $franchiseType, $franchiseId, $franchiseCode, $productName, $available - $quantity);
                    }
                    return ['ok' => true, 'stock_before' => $available, 'stock_after' => $available - $quantity, 'message' => 'Stock deducted.'];
                }
            }
        }
    }

    if (tableExists($conn, 'franchise_stock')) {
        $useCode = colExists($conn, 'franchise_stock', 'franchise_code');
        $sql     = $useCode
            ? 'SELECT id, stock_qty FROM franchise_stock WHERE franchise_type=? AND (franchise_id=? OR franchise_code=?) AND LOWER(TRIM(product_name))=LOWER(TRIM(?)) AND stock_qty>0 ORDER BY id ASC FOR UPDATE'
            : 'SELECT id, stock_qty FROM franchise_stock WHERE franchise_type=? AND franchise_id=? AND LOWER(TRIM(product_name))=LOWER(TRIM(?)) AND stock_qty>0 ORDER BY id ASC FOR UPDATE';

        $stmt = $conn->prepare($sql);
        if ($stmt) {
            if ($useCode) { $stmt->bind_param('ssss', $franchiseType, $franchiseId, $franchiseCode, $productName); }
            else          { $stmt->bind_param('sss', $franchiseType, $franchiseId, $productName); }
            $stmt->execute();
            $result    = $stmt->get_result();
            $rows      = [];
            $available = 0;
            while ($row = $result->fetch_assoc()) {
                $qty = max(0, (int)($row['stock_qty'] ?? 0));
                if ($qty > 0) { $rows[] = ['id' => (int)$row['id'], 'stock_qty' => $qty]; $available += $qty; }
            }
            $stmt->close();

            if ($available >= $quantity && !empty($rows)) {
                $upd = $conn->prepare('UPDATE franchise_stock SET stock_qty=stock_qty-? WHERE id=? AND stock_qty>=?');
                if ($upd) {
                    $remaining = $quantity;
                    foreach ($rows as $row) {
                        if ($remaining <= 0) break;
                        $deduct = min($row['stock_qty'], $remaining);
                        $upd->bind_param('iii', $deduct, $row['id'], $deduct);
                        $upd->execute();
                        if ($upd->affected_rows > 0) $remaining -= $deduct;
                    }
                    $upd->close();
                    if ($remaining <= 0) {
                        return ['ok' => true, 'stock_before' => $available, 'stock_after' => $available - $quantity, 'message' => 'Stock deducted.'];
                    }
                }
            }
        }
    }

    return ['ok' => false, 'stock_before' => 0, 'stock_after' => 0, 'message' => 'Insufficient Stock'];
}

function deductFranchiseWalletStock(mysqli $conn, string $franchiseType, string $franchiseId, string $franchiseCode, string $productName, int $quantity): bool
{
    return !empty(deductFranchiseWalletStockDetailed($conn, $franchiseType, $franchiseId, $franchiseCode, $productName, $quantity)['ok']);
}

function deductFranchiseProductStockRequest(mysqli $conn, int $franchiseId, string $productName, int $quantity): int
{
    $franchiseId = max(0, $franchiseId);
    $productName = trim($productName);
    $quantity    = max(0, $quantity);

    if ($franchiseId <= 0 || $productName === '' || $quantity <= 0) return $quantity;

    $tableInfo = detectStateStockTable($conn);
    if ($tableInfo === null) return $quantity;

    $table      = $tableInfo['table'];
    $balanceCol = $tableInfo['balance_col'];
    $statusCol  = $tableInfo['status_col'];
    $idCol      = $tableInfo['id_col'];

    $hasProductUsed = colExists($conn, $table, 'product_used');

    $statusWhere = '';
    if ($statusCol !== null) {
        $statusWhere = " AND LOWER(TRIM(`$statusCol`)) IN ('approved','completed','approve','pending') ";
    }

    $selectSql = "SELECT id, `$balanceCol` AS bal FROM `$table`
                  WHERE `$idCol` = ? AND LOWER(TRIM(product_name)) = LOWER(TRIM(?)) AND `$balanceCol` > 0
                  $statusWhere ORDER BY id ASC FOR UPDATE";

    $stmt = $conn->prepare($selectSql);
    if (!$stmt) return $quantity;
    $stmt->bind_param('is', $franchiseId, $productName);
    $stmt->execute();
    $result = $stmt->get_result();
    $rows   = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = ['id' => (int)$row['id'], 'bal' => (int)$row['bal']];
    }
    $stmt->close();

    if (empty($rows)) return $quantity;

    $updateSql = $hasProductUsed
        ? "UPDATE `$table` SET `$balanceCol` = `$balanceCol` - ?, product_used = COALESCE(product_used, 0) + ? WHERE id = ? AND `$balanceCol` >= ?"
        : "UPDATE `$table` SET `$balanceCol` = `$balanceCol` - ? WHERE id = ? AND `$balanceCol` >= ?";

    $upd = $conn->prepare($updateSql);
    if (!$upd) return $quantity;

    $remaining = $quantity;
    foreach ($rows as $row) {
        if ($remaining <= 0) break;
        $deduct = min($row['bal'], $remaining);
        if ($hasProductUsed) { $upd->bind_param('iiii', $deduct, $deduct, $row['id'], $deduct); }
        else                 { $upd->bind_param('iii', $deduct, $row['id'], $deduct); }
        $upd->execute();
        if ($upd->affected_rows > 0) $remaining -= $deduct;
    }
    $upd->close();

    if ($remaining <= 0 && tableExists($conn, 'franchise_stock')) {
        $statusWhere2 = $statusCol !== null ? " AND LOWER(TRIM(`$statusCol`)) IN ('approved','completed','approve') " : '';
        $chkStmt = $conn->prepare(
            "SELECT IFNULL(SUM(`$balanceCol`), 0) AS qty FROM `$table`
             WHERE `$idCol` = ? AND LOWER(TRIM(product_name)) = LOWER(TRIM(?)) $statusWhere2"
        );
        if ($chkStmt) {
            $chkStmt->bind_param('is', $franchiseId, $productName);
            $chkStmt->execute();
            $chkRow     = $chkStmt->get_result()->fetch_assoc();
            $chkStmt->close();
            $newBalance = max(0, (int)($chkRow['qty'] ?? 0));
            syncFranchiseStockRecord($conn, 'state', (string)$franchiseId, '', $productName, $newBalance);
        }
    }

    return $remaining;
}

function deductStateStockForCity(mysqli $conn, string $cityFranchiseId, string $cityFranchiseCode, string $productName, int $quantity): int
{
    $candidates = resolveAllStateCandidatesForCity($conn, $cityFranchiseId, $cityFranchiseCode);
    $remaining  = $quantity;

    foreach ($candidates as $c) {
        if ($remaining <= 0) break;

        $stateId   = (int)$c['id'];
        $stateCode = trim($c['code']);
        $lookupId  = $stateId > 0 ? (string)$stateId : $stateCode;
        if ($lookupId === '') continue;

        if ($stateId > 0) {
            $remaining = deductFranchiseProductStockRequest($conn, $stateId, $productName, $remaining);
        }
        if ($remaining <= 0) break;

        $walletResult = deductFranchiseWalletStockDetailed($conn, 'state', $lookupId, $stateCode, $productName, $remaining);
        if (!empty($walletResult['ok'])) {
            $remaining = 0;
            break;
        }
    }

    return $remaining;
}

function upsertFranchiseStock(mysqli $conn, string $franchiseType, string $franchiseId, string $franchiseCode, string $productName, int $quantity): bool
{
    $franchiseType = strtolower(trim($franchiseType));
    $franchiseId   = trim($franchiseId);
    $franchiseCode = trim($franchiseCode);
    $productName   = trim($productName);
    $quantity      = max(0, $quantity);

    if ($franchiseType === '' || $franchiseId === '' || $productName === '' || $quantity <= 0) return false;

    if (tableExists($conn, 'franchise_stock_wallet')) {
        $useCode   = colExists($conn, 'franchise_stock_wallet', 'franchise_code');
        $selectSql = $useCode
            ? "SELECT id, stock_qty FROM franchise_stock_wallet WHERE franchise_type=? AND (franchise_id=? OR franchise_code=?) AND LOWER(TRIM(product_name))=LOWER(TRIM(?)) LIMIT 1 FOR UPDATE"
            : "SELECT id, stock_qty FROM franchise_stock_wallet WHERE franchise_type=? AND franchise_id=? AND LOWER(TRIM(product_name))=LOWER(TRIM(?)) LIMIT 1 FOR UPDATE";

        $stmt = $conn->prepare($selectSql);
        if ($stmt) {
            if ($useCode) { $stmt->bind_param('ssss', $franchiseType, $franchiseId, $franchiseCode, $productName); }
            else          { $stmt->bind_param('sss', $franchiseType, $franchiseId, $productName); }
            $stmt->execute();
            $existingRow = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($existingRow) {
                $currentStock = max(0, (int)($existingRow['stock_qty'] ?? 0));
                $upd          = $conn->prepare('UPDATE franchise_stock_wallet SET stock_qty=stock_qty+? WHERE id=?');
                if ($upd) {
                    $upd->bind_param('ii', $quantity, $existingRow['id']);
                    $ok = $upd->execute();
                    $upd->close();
                    if ($ok) {
                        syncFranchiseStockRecord($conn, $franchiseType, $franchiseId, $franchiseCode, $productName, $currentStock + $quantity);
                        return true;
                    }
                }
            } else {
                $productId = resolveProductId($conn, $productName);
                $ins       = $conn->prepare(
                    'INSERT INTO franchise_stock_wallet (franchise_type, franchise_id, product_id, product_name, stock_qty) VALUES (?, ?, ?, ?, ?)'
                );
                if ($ins) {
                    $ins->bind_param('ssisi', $franchiseType, $franchiseId, $productId, $productName, $quantity);
                    $ok = $ins->execute();
                    $ins->close();
                    if ($ok) {
                        syncFranchiseStockRecord($conn, $franchiseType, $franchiseId, $franchiseCode, $productName, $quantity);
                        return true;
                    }
                }
            }
        }
    }

    return syncFranchiseStockRecord($conn, $franchiseType, $franchiseId, $franchiseCode, $productName, $quantity);
}

function fetchOrderSummary(mysqli $conn, string $orderId): array
{
    $stmt = $conn->prepare(
        'SELECT franchise_type, franchise_id, franchise_code, status FROM city_mobile_order WHERE order_id=? LIMIT 1'
    );
    if (!$stmt) return [];
    $stmt->bind_param('s', $orderId);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $result ?: [];
}

function fetchOrderProducts(mysqli $conn, string $orderId): array
{
    $stmt = $conn->prepare(
        'SELECT TRIM(product_name) AS product_name, SUM(quantity) AS total_quantity
         FROM city_mobile_order WHERE order_id=?
         GROUP BY LOWER(TRIM(product_name)), TRIM(product_name)'
    );
    if (!$stmt) return [];
    $stmt->bind_param('s', $orderId);
    $stmt->execute();
    $result   = $stmt->get_result();
    $products = [];
    while ($row = $result->fetch_assoc()) {
        $products[] = [
            'product_name' => (string)($row['product_name'] ?? ''),
            'quantity'     => max(0, (int)($row['total_quantity'] ?? 0)),
        ];
    }
    $stmt->close();
    return $products;
}

function getTransferLabel(string $franchiseType): string
{
    return match (strtolower(trim($franchiseType))) {
        'city'   => 'StateToCity',
        'mobile' => 'CityToMobile',
        default  => 'Transfer',
    };
}

function syncPaymentStatus(mysqli $conn, string $franchiseType, string $orderId, string $newStatus): void
{
    $paymentTable = getPaymentTableForType($franchiseType);
    if ($paymentTable === '' || !tableExists($conn, $paymentTable)) return;

    $stmt = $conn->prepare("UPDATE `$paymentTable` SET status=? WHERE order_id=?");
    if (!$stmt) return;
    $stmt->bind_param('ss', $newStatus, $orderId);
    $stmt->execute();
    $stmt->close();
}

/* =========================================================
   CORE: PROCESS APPROVAL / REJECTION
   FIX 1: removed undefined $totalStateStock in mobile branch
   FIX 2: removed duplicate payment sync at bottom
========================================================= */
function processApprovalAction(mysqli $conn, string $action, string $orderId): array
{
    $action  = strtolower(trim($action));
    $orderId = trim($orderId);

    if ($orderId === '' || !in_array($action, ['approve', 'reject'], true)) {
        return ['success' => false, 'message' => 'Invalid request.'];
    }

    $summary = fetchOrderSummary($conn, $orderId);
    if (empty($summary)) {
        return ['success' => false, 'message' => 'Order not found.'];
    }

    $currentStatus      = strtolower(trim((string)$summary['status']));
    $franchiseType      = strtolower(trim((string)$summary['franchise_type']));
    $franchiseIdValue   = trim((string)$summary['franchise_id']);
    $franchiseCodeValue = trim((string)$summary['franchise_code']);

    if ($currentStatus !== 'pending') {
        return ['success' => false, 'message' => 'Only pending requests can be processed.'];
    }

    $newStatus    = $action === 'approve' ? 'Approved' : 'Rejected';
    $paymentStatus = $newStatus;

    $conn->begin_transaction();
    try {
        // Update order status
        $upd = $conn->prepare('UPDATE city_mobile_order SET status=? WHERE order_id=?');
        if (!$upd) throw new RuntimeException($conn->error);
        $upd->bind_param('ss', $newStatus, $orderId);
        $upd->execute();
        $upd->close();

        // Sync payment table status
        syncPaymentStatus($conn, $franchiseType, $orderId, $paymentStatus);

        if ($action === 'approve') {
            $products = fetchOrderProducts($conn, $orderId);
            if (empty($products)) throw new RuntimeException('Order has no products to approve.');

            $transferType  = getTransferLabel($franchiseType);
            $toFranchiseId = $franchiseIdValue !== '' ? $franchiseIdValue : $franchiseCodeValue;

            foreach ($products as $product) {
                $requiredQty = (int)$product['quantity'];
                $productName = $product['product_name'];

                if ($requiredQty <= 0 || $productName === '') {
                    throw new RuntimeException('Invalid product data in order.');
                }

if ($franchiseType === 'mobile') {

    // ==================================================
    // STEP 1 : Try to get parent city franchise
    // ==================================================
    $cityInfo = resolveCityFranchiseForOrder(
        $conn,
        $franchiseIdValue,
        $franchiseCodeValue
    );

    $cityId   = (int)($cityInfo['city_id'] ?? 0);
    $cityCode = trim((string)($cityInfo['city_code'] ?? ''));

    $stockType = 'city';
    $fromId    = '';
    $fromCode  = '';

    // ==================================================
    // STEP 2 : City exists
    // ==================================================
    if ($cityId > 0 || $cityCode !== '') {

        $fromId   = $cityId > 0
            ? (string)$cityId
            : $cityCode;

        $fromCode = $cityCode;
    }

    // ==================================================
    // STEP 3 : No city franchise exists
    //          → automatically use state franchise
    // ==================================================
    else {

        $stateInfo = resolveStateFranchiseForOrder(
            $conn,
            'mobile',
            $franchiseIdValue,
            $franchiseCodeValue
        );

        $stateId   = (int)($stateInfo['state_id'] ?? 0);
        $stateCode = trim((string)($stateInfo['state_code'] ?? ''));

        $fromId   = $stateId > 0
            ? (string)$stateId
            : $stateCode;

        $fromCode = $stateCode;
        $stockType = 'state';
    }

    // ==================================================
    // STEP 4 : Supplier not found
    // ==================================================
    if ($fromId === '') {
        throw new RuntimeException(
            'No parent city/state franchise found for mobile franchise.'
        );
    }

    // ==================================================
    // STEP 5 : Check available stock
    // ==================================================
   if ($stockType === 'state') {

    $availableStock = getStateStockFromRequestTable(
        $conn,
        $fromId,
        $productName
    );

} else {

    $availableStock = getFranchiseStockBalance(
        $conn,
        'city',
        $fromId,
        $fromCode,
        $productName
    );
}

    if ($availableStock < $requiredQty) {
        throw new RuntimeException(
            'PRODUCT=' . $productName .
            ' | REQUIRED=' . $requiredQty .
            ' | AVAILABLE=' . $availableStock .
            ' | TYPE=' . strtoupper($stockType) .
            ' | ID=' . $fromId .
            ' | CODE=' . $fromCode
        );
    }

    // ==================================================
    // STEP 6 : Create stock history
    // ==================================================
    $productId = resolveProductId($conn, $productName);

    if ($fromId !== '' && $toFranchiseId !== '') {

        createStockRequestRecord(
            $conn,
            $fromId,
            $toFranchiseId,
            $productId,
            $requiredQty,
            'Approved',
            $transferType
        );

        createStockHistoryRecord(
            $conn,
            $stockType . ':' . $fromId,
            'mobile:' . $toFranchiseId,
            $productId,
            $requiredQty,
            $transferType
        );
    }

    // ==================================================
    // STEP 7 : Add stock to mobile
    // ==================================================
    if (!upsertFranchiseStock(
        $conn,
        'mobile',
        $franchiseIdValue,
        $franchiseCodeValue,
        $productName,
        $requiredQty
    )) {
        throw new RuntimeException(
            'Unable to update mobile franchise stock for: ' .
            $productName
        );
    }

  // STEP 8 : Deduct from supplier

if ($stockType === 'state') {

    $remaining = deductFranchiseProductStockRequest(
        $conn,
        (int)$fromId,
        $productName,
        $requiredQty
    );

    if ($remaining > 0) {
        throw new RuntimeException(
            'Unable to deduct state franchise stock for: ' .
            $productName
        );
    }

} else {

    if (!deductFranchiseWalletStock(
        $conn,
        'city',
        $fromId,
        $fromCode,
        $productName,
        $requiredQty
    )) {
        throw new RuntimeException(
            'Unable to deduct city franchise stock for: ' .
            $productName
        );
    }
}
} else {
                    // Supplier = parent state franchise (city order)
                    $totalStateStock = getTotalStateStockForCity($conn, $franchiseIdValue, $franchiseCodeValue, $productName);

                    syncFranchiseStockRecord($conn, 'state', $franchiseIdValue, $franchiseCodeValue, $productName, $totalStateStock);

                    if ($totalStateStock < $requiredQty) {
                        throw new RuntimeException(
                            'PRODUCT=' . $productName .
                            ' | REQUIRED=' . $requiredQty .
                            ' | AVAILABLE=' . $totalStateStock .
                            ' | CITY_ID=' . $franchiseIdValue .
                            ' | CITY_CODE=' . $franchiseCodeValue
                        );
                    }

                    $stateInfo = resolveStateFranchiseForOrder($conn, 'city', $franchiseIdValue, $franchiseCodeValue);
                    $stateId   = (int)($stateInfo['state_id'] ?? 0);
                    $stateCode = trim((string)($stateInfo['state_code'] ?? ''));
                    $fromId    = $stateId > 0 ? (string)$stateId : $stateCode;
                    $fromCode  = $stateCode;

                    $productId = resolveProductId($conn, $productName);
                    if ($fromId !== '' && $toFranchiseId !== '') {
                        createStockRequestRecord($conn, $fromId, $toFranchiseId, $productId, $requiredQty, 'Approved', $transferType);
                        createStockHistoryRecord($conn, 'state:' . $fromId, 'city:' . $toFranchiseId, $productId, $requiredQty, $transferType);
                    }

                    if (!upsertFranchiseStock($conn, 'city', $franchiseIdValue, $franchiseCodeValue, $productName, $requiredQty)) {
                        throw new RuntimeException('Unable to update city franchise stock for: ' . $productName);
                    }

                    $leftover = deductStateStockForCity($conn, $franchiseIdValue, $franchiseCodeValue, $productName, $requiredQty);
                    if ($leftover > 0) {
                        throw new RuntimeException('Unable to deduct state franchise stock for: ' . $productName);
                    }
                }
            }

        } elseif ($action === 'reject') {
            $products = fetchOrderProducts($conn, $orderId);
            if (!empty($products)) {
                $supplier     = resolveOrderSupplier($conn, $franchiseType, $franchiseIdValue, $franchiseCodeValue);
                $fromId       = trim((string)($supplier['supplier_id'] ?? '')) ?: trim((string)($supplier['supplier_code'] ?? ''));
                $toId         = $franchiseIdValue !== '' ? $franchiseIdValue : $franchiseCodeValue;
                $transferType = getTransferLabel($franchiseType);
                if ($fromId !== '' && $toId !== '') {
                    foreach ($products as $product) {
                        $productId = resolveProductId($conn, $product['product_name']);
                        createStockRequestRecord($conn, $fromId, $toId, $productId, $product['quantity'], 'Rejected', $transferType);
                    }
                }
            }
        }

        $conn->commit();
        return ['success' => true, 'message' => 'Order ' . $newStatus . ' successfully.'];

    } catch (Throwable $ex) {
        $conn->rollback();
        return ['success' => false, 'message' => 'Approval failed: ' . $ex->getMessage()];
    }
}

function getPostValue(string $key): string
{
    return trim((string)($_POST[$key] ?? ''));
}

function getRequestBodyJson(): array
{
    $input = file_get_contents('php://input');
    if ($input === false || $input === '') return [];
    $data = json_decode($input, true);
    return is_array($data) ? $data : [];
}

function renderJsonOrRedirect(array $result): void
{
    if (isJsonRequest()) {
        sendJsonResponse($result, $result['success'] ? 200 : 400);
    }
    if (!empty($result['message'])) {
        $_SESSION[$result['success'] ? 'success' : 'error'] = $result['message'];
    }
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

function getActionFromRequest(): array
{
    if (isJsonRequest()) {
        $body = getRequestBodyJson();
        return [trim((string)($body['action'] ?? '')), trim((string)($body['order_id'] ?? ''))];
    }
    return [getPostValue('action'), getPostValue('order_id')];
}

function getFranchiseHolderInfo(mysqli $conn, string $type, $franchiseId, string $franchiseCode): array
{
    $type = strtolower(trim($type));
    $map  = [
        'mobile' => ['table' => 'mobile_franchies_holder', 'codeCols' => ['mobile_franchies_id','franchise_code'], 'nameCols' => ['owner_name','shop_name','full_name','name']],
        'city'   => ['table' => 'city_franchies_holder',   'codeCols' => ['city_franchies_id','franchise_code'],   'nameCols' => ['full_name','owner_name','shop_name','name']],
    ];
    if (!isset($map[$type])) return ['code' => '', 'name' => ''];

    $table    = $map[$type]['table'];
    $codeCols = $map[$type]['codeCols'];
    $nameCols = $map[$type]['nameCols'];
    $lookupRow = null;

    if (is_numeric($franchiseId) && (int)$franchiseId > 0) {
        $stmt = $conn->prepare("SELECT * FROM `$table` WHERE id=? LIMIT 1");
        if ($stmt) {
            $id = (int)$franchiseId;
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $lookupRow = $stmt->get_result()->fetch_assoc();
            $stmt->close();
        }
    }

    if (!$lookupRow && $franchiseCode !== '') {
        foreach ($codeCols as $col) {
            if (!colExists($conn, $table, $col)) continue;
            $stmt = $conn->prepare("SELECT * FROM `$table` WHERE `$col`=? LIMIT 1");
            if (!$stmt) continue;
            $stmt->bind_param('s', $franchiseCode);
            $stmt->execute();
            $lookupRow = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if ($lookupRow) break;
        }
    }

    if (!$lookupRow) return ['code' => trim($franchiseCode), 'name' => ''];

    $code = '';
    foreach ($codeCols as $col) {
        if (!empty($lookupRow[$col])) { $code = trim((string)$lookupRow[$col]); break; }
    }
    $name = '';
    foreach ($nameCols as $col) {
        if (!empty($lookupRow[$col])) { $name = trim((string)$lookupRow[$col]); break; }
    }

    return ['code' => $code, 'name' => $name];
}

/* =========================================================
   HANDLE POST
========================================================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    [$action, $orderId] = getActionFromRequest();
    if ($orderId !== '' && $action !== '') {
        $result = processApprovalAction($conn, $action, $orderId);
        renderJsonOrRedirect($result);
    }
    renderJsonOrRedirect(['success' => false, 'message' => 'Invalid request.']);
}

/* =========================================================
   FETCH ORDERS FOR DISPLAY
   FIX 3: State franchise sees only CITY-level orders (not mobile).
           City franchise sees only MOBILE orders under it.
           Mobile franchise has no access to this page (redirect).
========================================================= */

$typeFilter = strtolower(trim($_GET['type'] ?? 'all'));

$sessionFranchiseType = strtolower(trim(
    (string)($_SESSION['franchise_type'] ?? $_SESSION['franchies_type'] ?? '')
));
$franchiseState = trim((string)($_SESSION['franchise_state'] ?? $_SESSION['state'] ?? ''));

// Determine session franchise identifiers
$sessionFranchiseId   = trim((string)(
    $_SESSION['franchise_id'] ?? $_SESSION['franchies_id'] ??
    $_SESSION['franchise_db_id'] ?? $_SESSION['city_franchies_holder_id'] ?? ''
));
$sessionFranchiseCode = trim((string)(
    $_SESSION['franchise_code'] ?? $_SESSION['franchise_login_id'] ?? ''
));

// Mobile franchise has no orders to approve — redirect away
if (str_contains($sessionFranchiseType, 'mobile')) {
    header('Location: index.php');
    exit;
}

$isCityFranchise  = str_contains($sessionFranchiseType, 'city') || !empty($_SESSION['city_franchies_holder_id']);
$isStateFranchise = str_contains($sessionFranchiseType, 'state') || !empty($_SESSION['state_franchies_holder_id']);

// For city franchise, force filter to mobile only (they approve mobile orders)
if ($isCityFranchise) {
    $typeFilter = 'mobile';
}

// For state franchise, default to city orders (they approve city orders)
if ($isStateFranchise && $typeFilter === 'all') {
    $typeFilter = 'all';
}

$where  = [];
$params = [];
$types  = '';

// Type filter
if ($typeFilter !== 'all' && in_array($typeFilter, ['city', 'mobile'], true)) {
    $where[]  = 'franchise_type = ?';
    $params[] = $typeFilter;
    $types   .= 's';
}

// State franchise
if ($isStateFranchise && $franchiseState !== '') {

    $sessionStateCode = trim(
        (string)(
            $_SESSION['franchise_code']
            ?? $_SESSION['franchise_login_id']
            ?? ''
        )
    );

    $where[] = "(
        (
            franchise_type = 'city'
            AND franchise_id IN (
                SELECT id
                FROM city_franchies_holder
                WHERE LOWER(TRIM(state)) = LOWER(TRIM(?))
            )
        )
        OR
        (
            franchise_type = 'mobile'
            AND franchise_id IN (
                SELECT m.id
                FROM mobile_franchies_holder m
                LEFT JOIN city_franchies_holder c
    ON (
        c.franchies_id = m.city_franchise_code
        OR c.franchise_code = m.city_franchise_code
    )
                WHERE
                    LOWER(TRIM(m.state_franchise_code))
                        = LOWER(TRIM(?))
                    AND c.id IS NULL
                    AND c.franchies_id IS NULL
                    AND c.franchise_code IS NULL
            )
        )
    )";

    // 1st ? -> city query (MP)
    $params[] = $franchiseState;

    // 2nd ? -> mobile query (FRD2426)
    $params[] = $sessionStateCode;

    $types .= 'ss';
}

// City franchise: show only mobile orders that belong under this city
if ($isCityFranchise && ($sessionFranchiseId !== '' || $sessionFranchiseCode !== '')) {
    // Try to find mobile franchises linked to this city by various possible link columns
    $mobileLinkCols = [
        'city_franchise_code', 'city_franchies_id', 'city_franchise_id',
        'city_holder_id', 'city_code', 'under_city_id', 'parent_id',
    ];
    $cityLinkCol = '';
    foreach ($mobileLinkCols as $col) {
        if (colExists($conn, 'mobile_franchies_holder', $col)) {
            $cityLinkCol = $col;
            break;
        }
    }

    if ($cityLinkCol !== '') {
        // Match by franchise_code first, then id
        $cityLinkValue = $sessionFranchiseCode !== '' ? $sessionFranchiseCode : $sessionFranchiseId;
        $where[]  = "franchise_id IN (
            SELECT id FROM mobile_franchies_holder
            WHERE `$cityLinkCol` = ?
        )";
        $params[] = $cityLinkValue;
        $types   .= 's';
    } elseif ($sessionFranchiseId !== '') {
        // Fallback: match by numeric city id stored in mobile holder
        $where[]  = "franchise_id IN (
            SELECT id FROM mobile_franchies_holder
            WHERE city_id = ? OR parent_id = ?
        )";
        $params[] = $sessionFranchiseId;
        $params[] = $sessionFranchiseId;
        $types   .= 'ss';
    }
}

$whereSql = !empty($where) ? ' WHERE ' . implode(' AND ', $where) : '';

$sql = "SELECT order_id, franchise_id, franchise_type, franchise_code, status, created_at,
               SUM(quantity) AS total_qty, SUM(total_amount) AS grand_total
        FROM city_mobile_order
        $whereSql
        GROUP BY order_id
        ORDER BY id DESC";

$stmt = $conn->prepare($sql);
if (!empty($params)) $stmt->bind_param($types, ...$params);
$stmt->execute();
$orders    = $stmt->get_result();
$orderData = [];

while ($row = $orders->fetch_assoc()) {
    $orderId      = $row['order_id'];
    $paymentTable = getPaymentTableForType((string)$row['franchise_type']);
    $receipt      = '';

    if ($paymentTable !== '' && $orderId !== '') {
        $rstmt = $conn->prepare("SELECT receipt FROM `$paymentTable` WHERE order_id=? LIMIT 1");
        if ($rstmt) {
            $rstmt->bind_param('s', $orderId);
            $rstmt->execute();
            $rrow    = $rstmt->get_result()->fetch_assoc();
            $receipt = (string)($rrow['receipt'] ?? '');
            $rstmt->close();
        }
    }

    $products = [];
    $pstmt    = $conn->prepare('SELECT product_name, quantity, offer_price, total_amount FROM city_mobile_order WHERE order_id=?');
    $pstmt->bind_param('s', $orderId);
    $pstmt->execute();
    $pres = $pstmt->get_result();
    while ($p = $pres->fetch_assoc()) {
        $products[] = [
            'name'       => $p['product_name'],
            'qty'        => (int)$p['quantity'],
            'unit_price' => (float)$p['offer_price'],
            'line_total' => (float)$p['total_amount'],
        ];
    }
    $pstmt->close();

    $holderInfo  = getFranchiseHolderInfo($conn, (string)$row['franchise_type'], $row['franchise_id'], (string)$row['franchise_code']);
    $orderData[] = [
        'order_id'          => $row['order_id'],
        'franchise_id'      => $row['franchise_id'],
        'type'              => strtolower($row['franchise_type']),
        'franchise_code'    => trim((string)$row['franchise_code']),
        'franchise_display' => $holderInfo['code'] ?: trim((string)$row['franchise_code']),
        'franchise_name'    => $holderInfo['name'],
        'qty'               => (int)$row['total_qty'],
        'amount'            => (float)$row['grand_total'],
        'status'            => strtolower($row['status']),
        'created_at'        => $row['created_at'],
        'receipt'           => $receipt,
        'products'          => $products,
    ];
}
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Franchise Product Request</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
.page-header{display:flex;align-items:center;gap:10px;margin-bottom:22px;}
.page-header h1{font-size:18px;font-weight:800;}
.divider{flex:1;height:2px;background:linear-gradient(90deg,#c9952a,transparent);}
.filter-card{background:#fff;border-radius:12px;box-shadow:0 1px 6px rgba(0,0,0,.06);padding:18px 22px;margin-bottom:22px;display:flex;align-items:center;gap:16px;flex-wrap:wrap;}
.filter-label{font-size:13px;font-weight:600;}
.filter-select{padding:9px 14px;border:1px solid #e2e8f0;border-radius:8px;font-size:13px;min-width:180px;}
.btn-filter{padding:9px 24px;background:#c9952a;color:#fff;border:none;border-radius:8px;font-size:13px;font-weight:700;cursor:pointer;}
.btn-reset{padding:9px 20px;background:#fff;border:1px solid #e2e8f0;border-radius:8px;font-size:13px;cursor:pointer;text-decoration:none;color:#475569;}
.table-card{background:#fff;border-radius:12px;box-shadow:0 1px 6px rgba(0,0,0,.06);overflow:hidden;}
.data-table{width:100%;border-collapse:collapse;}
.data-table thead th{padding:13px 16px;color:#94a3b8;font-size:12px;text-align:left;border-bottom:2px solid #e2e8f0;}
.data-table tbody td{padding:16px;border-bottom:1px solid #f1f5f9;font-size:13px;}
.center{text-align:center;}
.order-id{font-weight:800;}
.type-badge{display:inline-block;padding:4px 10px;border-radius:6px;font-size:11px;font-weight:800;}
.type-mobile{background:#ede9fe;color:#7c3aed;}
.type-city{background:#dbeafe;color:#1d4ed8;}
.franchise-code{font-size:13px;font-weight:700;}
.franchise-name{font-size:11px;color:#94a3b8;margin-top:2px;}
.amount{font-weight:700;}
.status-pill{display:inline-block;padding:5px 12px;border-radius:7px;font-size:12px;font-weight:700;}
.status-pending{background:#fef9c3;color:#854d0e;}
.status-approved{background:#dcfce7;color:#166534;}
.status-rejected{background:#fee2e2;color:#991b1b;}
.action-btns{display:flex;gap:6px;flex-wrap:wrap;}
.btn-action{padding:7px 16px;border:none;border-radius:8px;font-size:12px;font-weight:700;cursor:pointer;}
.btn-approve{background:#16a34a;color:#fff;}
.btn-reject{background:#dc2626;color:#fff;}
.modal-overlay{display:none;position:fixed;inset:0;background:rgba(15,31,61,.5);z-index:300;align-items:center;justify-content:center;padding:24px;}
.modal-overlay.open{display:flex;}
.modal-box{background:#fff;border-radius:14px;width:600px;max-width:100%;max-height:80vh;overflow-y:auto;}
.modal-header{padding:18px 22px;border-bottom:1px solid #e2e8f0;display:flex;align-items:center;justify-content:space-between;}
.modal-title{font-size:15px;font-weight:800;}
.modal-close{background:none;border:none;cursor:pointer;font-size:24px;}
.modal-body{padding:22px;}
.modal-table{width:100%;border-collapse:collapse;}
.modal-table th{background:#0f1f3d;color:#fff;padding:10px 14px;text-align:left;}
.modal-table td{padding:12px 14px;border-bottom:1px solid #e2e8f0;}
.modal-total-row{display:flex;justify-content:space-between;padding:8px 14px;}
.modal-total-row.grand{background:#0f1f3d;color:#fff;font-weight:800;}
</style>
</head>
<body class="bg-[#f1f5f9] text-[#1e293b] overflow-x-hidden">

<?php include __DIR__ . '/includes/sidebar.php'; ?>

<div class="ml-64 min-h-screen">

<?php include __DIR__ . '/includes/navbar.php'; ?>

<main class="px-4 sm:px-6 lg:px-8 py-6">
<div class="max-w-[1440px] mx-auto">

<?php if (!empty($_SESSION['success'])): ?>
<div style="background:#dcfce7;color:#166534;padding:12px 18px;border-radius:8px;margin-bottom:16px;font-weight:600;">
    <?= e($_SESSION['success']) ?> <?php unset($_SESSION['success']); ?>
</div>
<?php endif; ?>
<?php if (!empty($_SESSION['error'])): ?>
<div style="background:#fee2e2;color:#991b1b;padding:12px 18px;border-radius:8px;margin-bottom:16px;font-weight:600;">
    <?= e($_SESSION['error']) ?> <?php unset($_SESSION['error']); ?>
</div>
<?php endif; ?>

<div class="page-header">
    <h1>PRODUCT REQUESTS</h1>
    <div class="divider"></div>
</div>

<?php if ($isStateFranchise): ?>
<div class="filter-card">
    <form method="GET" style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;">
        <div class="filter-label">Franchise Type</div>
        <select name="type" class="filter-select">
            <option value="city"   <?= $typeFilter === 'city'   ? 'selected' : '' ?>>City</option>
        </select>
        <button class="btn-filter" type="submit">Filter</button>
        <a href="<?= e($_SERVER['PHP_SELF']) ?>" class="btn-reset">Reset</a>
    </form>
</div>
<?php endif; ?>

<div class="table-card">
<div style="overflow-x:auto;">
<table class="data-table">
<thead>
<tr>
    <th>#</th><th>Order ID</th><th>Type</th><th>Franchise</th>
    <th>Receipt</th><th class="center">Total Qty</th><th>Amount</th>
    <th>Status</th><th>Date</th><th>Action</th>
</tr>
</thead>
<tbody>
<?php if (!empty($orderData)): ?>
<?php foreach ($orderData as $i => $row): ?>
<?php
$typeBadge   = $row['type'] === 'city'
    ? '<span class="type-badge type-city">CITY</span>'
    : '<span class="type-badge type-mobile">MOBILE</span>';
$statusClass = match($row['status']) {
    'approved' => 'status-approved',
    'rejected' => 'status-rejected',
    default    => 'status-pending',
};
$receiptUrl = getReceiptUrl((string)($row['receipt'] ?? ''));
?>
<tr>
<td><?= $i + 1 ?></td>
<td><span class="order-id"><?= e($row['order_id']) ?></span></td>
<td><?= $typeBadge ?></td>
<td>
    <div class="franchise-code"><?= e($row['franchise_display'] ?: $row['franchise_code'] ?: $row['franchise_id']) ?></div>
    <div class="franchise-name">Franchise ID : <?= e($row['franchise_display'] ?: '-') ?></div>
    <div class="franchise-name">Name : <?= e($row['franchise_name'] ?: '-') ?></div>
</td>
<td>
    <?php if ($receiptUrl !== ''): ?>
        <a href="<?= e($receiptUrl) ?>" target="_blank" style="color:#2563eb;text-decoration:underline;font-weight:700;font-size:12px;">View</a>
    <?php else: ?>-<?php endif; ?>
</td>
<td class="center"><?= (int)$row['qty'] ?></td>
<td><span class="amount">₹<?= number_format((float)$row['amount'], 2) ?></span></td>
<td><span class="status-pill <?= $statusClass ?>"><?= ucfirst(e($row['status'])) ?></span></td>
<td><?= date('d M Y h:i A', strtotime($row['created_at'])) ?></td>
<td>
<?php if ($row['status'] === 'pending'): ?>
<div class="action-btns">
    <form method="POST" class="stock-action-form">
        <input type="hidden" name="order_id" value="<?= e($row['order_id']) ?>">
        <input type="hidden" name="action" value="approve">
        <button class="btn-action btn-approve" type="submit">Approve</button>
    </form>
    <form method="POST" class="stock-action-form">
        <input type="hidden" name="order_id" value="<?= e($row['order_id']) ?>">
        <input type="hidden" name="action" value="reject">
        <button class="btn-action btn-reject" type="submit">Reject</button>
    </form>
    <?php if (!empty($row['products'])): ?>
    <button class="btn-action" style="background:#64748b;color:#fff;"
        onclick='openModal(<?= json_encode($row["products"]) ?>, <?= json_encode($row["order_id"]) ?>)'>
        Products
    </button>
    <?php endif; ?>
</div>
<?php else: ?>
<span style="font-size:12px;color:#94a3b8;">Completed</span>
<?php if (!empty($row['products'])): ?>
<button class="btn-action" style="background:#64748b;color:#fff;margin-top:4px;"
    onclick='openModal(<?= json_encode($row["products"]) ?>, <?= json_encode($row["order_id"]) ?>)'>
    Products
</button>
<?php endif; ?>
<?php endif; ?>
</td>
</tr>
<?php endforeach; ?>
<?php else: ?>
<tr>
<td colspan="10" style="text-align:center;padding:48px;color:#94a3b8;">No records found.</td>
</tr>
<?php endif; ?>
</tbody>
</table>
</div>
</div>

</div>
</main>
</div>

<div class="modal-overlay" id="productsModal">
<div class="modal-box">
<div class="modal-header">
    <div class="modal-title" id="modalOrderId">Products</div>
    <button class="modal-close" onclick="closeModal()">&times;</button>
</div>
<div class="modal-body">
    <table class="modal-table">
    <thead><tr><th>#</th><th>Product Name</th><th class="center">Qty</th><th class="center">Unit Price</th><th class="center">Total</th></tr></thead>
    <tbody id="modalProducts"></tbody>
    </table>
    <div id="modalTotals" style="margin-top:16px;"></div>
</div>
</div>
</div>

<script>
function fmt(n){ return '₹' + Number(n).toLocaleString('en-IN',{minimumFractionDigits:2,maximumFractionDigits:2}); }

function openModal(products, orderId){
    document.getElementById('modalOrderId').textContent = 'Products — ' + orderId;
    let subtotal = 0;
    const rows = products.map((p,i) => {
        const line = Number(p.line_total); subtotal += line;
        return `<tr><td>${i+1}</td><td style="font-weight:600;">${p.name}</td><td class="center">${p.qty}</td><td class="center">${fmt(p.unit_price)}</td><td class="center" style="font-weight:700;">${fmt(line)}</td></tr>`;
    }).join('');
    document.getElementById('modalProducts').innerHTML = rows;
    document.getElementById('modalTotals').innerHTML = `
    <div class="modal-total-row grand">
        <span>Grand Total (Inclusive of GST)</span>
        <span>${fmt(subtotal)}</span>
    </div>

    <div style="
        margin-top:10px;
        padding:10px;
        background:#f8f9fa;
        border-radius:8px;
        font-size:13px;
        color:#666;
        text-align:center;
    ">
        All product prices are inclusive of GST.
    </div>`;
    document.getElementById('productsModal').classList.add('open');
}

function closeModal(){ document.getElementById('productsModal').classList.remove('open'); }

document.getElementById('productsModal').addEventListener('click', function(e){
    if(e.target === this) closeModal();
});

document.querySelectorAll('.stock-action-form').forEach(form => {
    form.addEventListener('submit', function(e){
        e.preventDefault();
        const orderId = this.querySelector('[name="order_id"]').value.trim();
        const action  = this.querySelector('[name="action"]').value.trim();
        if(!orderId || !action){ alert('Invalid order data.'); return; }

        const btn = this.querySelector('button[type="submit"]');
        const originalText = btn.textContent;
        btn.disabled = true;
        btn.textContent = 'Processing...';

        fetch(window.location.href, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify({ order_id: orderId, action: action })
        })
        .then(async r => {
            const data = await r.json();
            if(!r.ok || !data.success) throw new Error(data.message || 'Unable to update order status.');
            alert(data.message || 'Order updated successfully.');
            window.location.reload();
        })
        .catch(err => {
            alert(err.message);
            btn.disabled = false;
            btn.textContent = originalText;
        });
    });
});
</script>
</body>
</html>