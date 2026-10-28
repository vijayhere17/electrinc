<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config/db.php';

if (!isset($conn) || !($conn instanceof mysqli)) {
    die("Database connection failed.");
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

function resolveProductRequestLogin($conn) {
    $typeRaw = strtolower(trim((string)(
        $_SESSION['franchise_type']  ??
        $_SESSION['franchies_type']  ??
        ''
    )));

    $values = array_filter(array_map('trim', [
        (string)($_SESSION['mobile_franchise_id']       ?? ''),
        (string)($_SESSION['mobile_franchise_code']     ?? ''),
        (string)($_SESSION['city_franchies_holder_id']  ?? ''),
        (string)($_SESSION['state_franchies_holder_id'] ?? ''),
        (string)($_SESSION['franchise_db_id']           ?? ''),
        (string)($_SESSION['franchise_id']              ?? ''),
        (string)($_SESSION['franchies_id']              ?? ''),
        (string)($_SESSION['franchise_code']            ?? ''),
        (string)($_SESSION['franchise_login_id']        ?? ''),
        (string)($_SESSION['customer_id']               ?? ''),
    ]), fn($v) => $v !== '');

    if (str_contains($typeRaw, 'mobile') || !empty($_SESSION['mobile_franchise_id'])) {
        $order = ['mobile', 'city', 'state'];
    } elseif (str_contains($typeRaw, 'city') || !empty($_SESSION['city_franchies_holder_id'])) {
        $order = ['city', 'state', 'mobile'];
    } else {
        $order = ['state', 'city', 'mobile'];
    }

    $tableMap = [
        'mobile' => [
            'table'   => 'mobile_franchies_holder',
            'codeCol' => 'mobile_franchies_id',
            'nameCol' => 'owner_name'
        ],
        'city' => [
            'table'   => 'city_franchies_holder',
            'codeCol' => 'franchies_id',
            'nameCol' => 'full_name'
        ],
        'state' => [
            'table'   => 'franchies_holder',
            'codeCol' => 'franchise_code',
            'nameCol' => 'name'
        ]
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
                    $stmt->bind_param("i", $id);
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

            if (!colExists($conn, $table, $codeCol)) continue;
            $stmt = $conn->prepare("SELECT * FROM `$table` WHERE `$codeCol` = ? LIMIT 1");
            if ($stmt) {
                $stmt->bind_param("s", $val);
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

$login = resolveProductRequestLogin($conn);
if (!$login) {
    header("Location: index.php");
    exit;
}

$franchiseType = $login['type'];
$franchiseId   = (int)$login['id'];
$franchiseCode = (string)$login['code'];
$user          = ['full_name' => (string)$login['name']];

switch ($franchiseType) {
    case 'mobile':
        $paymentTable = 'mobile_franchies_payment';
        $requestTable = 'city_mobile_order';
        break;
    case 'city':
        $paymentTable = 'city_franchies_payment';
        $requestTable = 'city_mobile_order';
        break;
    default:
        $paymentTable = 'franchies_payment';
        $requestTable = 'franchies_product_request';
        break;
}

$conn->query("
CREATE TABLE IF NOT EXISTS `$paymentTable` (
    id            INT(11)        NOT NULL AUTO_INCREMENT,
    order_id      VARCHAR(50)    NULL,
    franchise_id  INT(11)        NULL,
    total_amount  DECIMAL(12,2)  NULL DEFAULT 0.00,
    bank_name     VARCHAR(100)   NULL,
    payment_mode  VARCHAR(50)    NULL,
    utr_number    VARCHAR(100)   NULL,
    receipt       VARCHAR(255)   NULL,
    remark        TEXT           NULL,
    status        VARCHAR(50)    NULL DEFAULT 'Pending',
    created_at    DATETIME       NULL DEFAULT CURRENT_TIMESTAMP,
    franchise_code VARCHAR(50)   NULL,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

$conn->query("
CREATE TABLE IF NOT EXISTS `$requestTable` (
    id            INT(11)        NOT NULL AUTO_INCREMENT,
    order_id      VARCHAR(50)    NULL,
    franchise_id  INT(11)        NULL,
    franchise_type VARCHAR(20)   NULL,
    category      VARCHAR(150)   NULL,
    product_name  VARCHAR(255)   NULL,
    offer_price   DECIMAL(12,2)  NULL DEFAULT 0.00,
    quantity      INT(11)        NULL DEFAULT 1,
    total_amount  DECIMAL(12,2)  NULL DEFAULT 0.00,
    status        VARCHAR(50)    NULL DEFAULT 'Pending',
    created_at    DATETIME       NULL DEFAULT CURRENT_TIMESTAMP,
    franchise_code VARCHAR(50)   NULL,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

addColIfMissing($conn, $paymentTable, 'franchise_code', "VARCHAR(50) NULL");
addColIfMissing($conn, $requestTable, 'franchise_code', "VARCHAR(50) NULL");
addColIfMissing($conn, $requestTable, 'franchise_type', "VARCHAR(20) NULL");

function fetchFranchiseWalletStockMap(mysqli $conn, string $franchiseType, string $franchiseId, string $franchiseCode): array
{
    $stockMap = [];
    if ($franchiseType === '' || ($franchiseId === '' && $franchiseCode === '')) {
        return $stockMap;
    }

    $lookupId   = $franchiseId   !== '' ? $franchiseId   : $franchiseCode;
    $lookupCode = $franchiseCode !== '' ? $franchiseCode : $franchiseId;
    $tables     = [];

    if (tableExists($conn, 'franchise_stock'))        $tables[] = 'franchise_stock';
    if (tableExists($conn, 'franchise_stock_wallet')) $tables[] = 'franchise_stock_wallet';

    foreach ($tables as $table) {
        $hasCodeCol = colExists($conn, $table, 'franchise_code');

        if ($hasCodeCol) {
            $stmt = $conn->prepare("
                SELECT LOWER(TRIM(product_name)) AS product_key, stock_qty
                FROM `$table`
                WHERE franchise_type = ?
                  AND (franchise_id = ? OR franchise_code = ?)
            ");
            if (!$stmt) continue;
            $stmt->bind_param('sss', $franchiseType, $lookupId, $lookupCode);
        } else {
            $stmt = $conn->prepare("
                SELECT LOWER(TRIM(product_name)) AS product_key, stock_qty
                FROM `$table`
                WHERE franchise_type = ?
                  AND franchise_id = ?
            ");
            if (!$stmt) continue;
            $stmt->bind_param('ss', $franchiseType, $lookupId);
        }

        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $productKey = (string)($row['product_key'] ?? '');
            if ($productKey === '') continue;

            $qty = max(0, (int)($row['stock_qty'] ?? 0));
            if ($table === 'franchise_stock') {
                $stockMap[$productKey] = $qty;
            } elseif (!array_key_exists($productKey, $stockMap) || $stockMap[$productKey] === 0) {
                $stockMap[$productKey] = $qty;
            }
        }
        $stmt->close();
    }

    return $stockMap;
}

function fetchFranchiseHolderRow(mysqli $conn, string $table, int $id, string $code): array
{
    $row = [];
    if ($id > 0 && tableExists($conn, $table)) {
        $stmt = $conn->prepare("SELECT * FROM `$table` WHERE id = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc() ?: [];
            $stmt->close();
        }
    }

    if (!empty($row) || $code === '' || !tableExists($conn, $table)) {
        return $row ?: [];
    }

    $tryCols = ['franchies_id','franchise_id','franchise_code','mobile_franchies_id','mobile_franchise_id'];
    foreach ($tryCols as $codeCol) {
        if (!colExists($conn, $table, $codeCol)) continue;
        $stmt = $conn->prepare("SELECT * FROM `$table` WHERE `$codeCol` = ? LIMIT 1");
        if (!$stmt) continue;
        $stmt->bind_param('s', $code);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc() ?: [];
        $stmt->close();
        if (!empty($row)) break;
    }

    return $row;
}

function getCityFranchisePrimaryCode(array $cityRow): string
{
    foreach (['city_franchies_id','city_franchise_id','city_franchise_code','franchies_id','franchise_id','franchise_code'] as $codeCol) {
        if (!empty($cityRow[$codeCol])) {
            return trim((string)$cityRow[$codeCol]);
        }
    }
    return '';
}

function cityFranchiseHasAvailableStock(mysqli $conn, array $cityRow): bool
{
    if (empty($cityRow)) return false;

    $franchiseType   = 'city';
    $cityId          = (int)($cityRow['id'] ?? 0);
    $cityCode        = getCityFranchisePrimaryCode($cityRow);
    if ($cityId <= 0 && $cityCode === '') return false;

    $cityLookupValue = $cityId > 0 ? (string)$cityId : $cityCode;

    if (tableExists($conn, 'franchise_stock')) {
        $useCode = colExists($conn, 'franchise_stock', 'franchise_code');
        $sql = $useCode
            ? 'SELECT 1 FROM franchise_stock WHERE franchise_type = ? AND (franchise_id = ? OR franchise_code = ?) AND stock_qty > 0 LIMIT 1'
            : 'SELECT 1 FROM franchise_stock WHERE franchise_type = ? AND franchise_id = ? AND stock_qty > 0 LIMIT 1';
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            if ($useCode) {
                $stmt->bind_param('sss', $franchiseType, $cityLookupValue, $cityCode);
            } else {
                $stmt->bind_param('ss', $franchiseType, $cityLookupValue);
            }
            $stmt->execute();
            $hasStock = (bool)$stmt->get_result()->fetch_assoc();
            $stmt->close();
            if ($hasStock) return true;
        }
    }

    if (tableExists($conn, 'franchise_stock_wallet')) {
        $useCode = colExists($conn, 'franchise_stock_wallet', 'franchise_code');
        $sql = $useCode
            ? 'SELECT 1 FROM franchise_stock_wallet WHERE franchise_type = ? AND (franchise_id = ? OR franchise_code = ?) AND stock_qty > 0 LIMIT 1'
            : 'SELECT 1 FROM franchise_stock_wallet WHERE franchise_type = ? AND franchise_id = ? AND stock_qty > 0 LIMIT 1';
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            if ($useCode) {
                $stmt->bind_param('sss', $franchiseType, $cityLookupValue, $cityCode);
            } else {
                $stmt->bind_param('ss', $franchiseType, $cityLookupValue);
            }
            $stmt->execute();
            $hasStock = (bool)$stmt->get_result()->fetch_assoc();
            $stmt->close();
            if ($hasStock) return true;
        }
    }

    if (tableExists($conn, 'franchies_product_stock_request') && colExists($conn, 'franchies_product_stock_request', 'product_balance')) {
        if ($cityId > 0) {
            $stmt = $conn->prepare('SELECT 1 FROM franchies_product_stock_request WHERE franchise_id = ? AND product_balance > 0 LIMIT 1');
            if ($stmt) {
                $stmt->bind_param('i', $cityId);
                $stmt->execute();
                $hasStock = (bool)$stmt->get_result()->fetch_assoc();
                $stmt->close();
                if ($hasStock) return true;
            }
        }
    }

    return false;
}

function findFallbackCityFranchiseWithAvailableStock(mysqli $conn, string $cityName, string $stateField, int $excludeId = 0): array
{
    $cityName   = trim((string)$cityName);
    $stateField = trim((string)$stateField);
    if ($cityName === '' || !tableExists($conn, 'city_franchies_holder')) return [];

    $useStateCode = colExists($conn, 'city_franchies_holder', 'state_code');
    if ($stateField !== '' && $useStateCode) {
        $sql = 'SELECT * FROM city_franchies_holder WHERE LOWER(TRIM(city)) = LOWER(TRIM(?)) AND (LOWER(TRIM(state)) = LOWER(TRIM(?)) OR LOWER(TRIM(state_code)) = LOWER(TRIM(?)))' . ($excludeId > 0 ? ' AND id != ?' : '') . ' ORDER BY id ASC';
    } elseif ($stateField !== '') {
        $sql = 'SELECT * FROM city_franchies_holder WHERE LOWER(TRIM(city)) = LOWER(TRIM(?)) AND LOWER(TRIM(state)) = LOWER(TRIM(?))' . ($excludeId > 0 ? ' AND id != ?' : '') . ' ORDER BY id ASC';
    } else {
        $sql = 'SELECT * FROM city_franchies_holder WHERE LOWER(TRIM(city)) = LOWER(TRIM(?))' . ($excludeId > 0 ? ' AND id != ?' : '') . ' ORDER BY id ASC';
    }

    $stmt = $conn->prepare($sql);
    if (!$stmt) return [];

    if ($stateField !== '' && $useStateCode) {
        if ($excludeId > 0) $stmt->bind_param('sssi', $cityName, $stateField, $stateField, $excludeId);
        else                 $stmt->bind_param('sss',  $cityName, $stateField, $stateField);
    } elseif ($stateField !== '') {
        if ($excludeId > 0) $stmt->bind_param('ssi', $cityName, $stateField, $excludeId);
        else                 $stmt->bind_param('ss',  $cityName, $stateField);
    } else {
        if ($excludeId > 0) $stmt->bind_param('si', $cityName, $excludeId);
        else                 $stmt->bind_param('s',  $cityName);
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

function resolveStateFranchiseForMobile(mysqli $conn, string $franchiseId, string $franchiseCode): array
{
    $holder = fetchFranchiseHolderRow($conn, 'mobile_franchies_holder', (int)$franchiseId, $franchiseCode);
    if (empty($holder)) return ['state_id' => 0, 'state_code' => '', 'state_name' => ''];

    $stateCols = [
        'state_franchies_id','state_franchise_id','state_franchies_holder_id',
        'state_holder_id','state_code','state_franchise_code','under_state_id',
        'state_parent_id','state_id'
    ];
    $parentValue = '';
    foreach ($stateCols as $col) {
        if (!empty($holder[$col])) { $parentValue = trim((string)$holder[$col]); break; }
    }
    if ($parentValue === '' && !empty($holder['state'])) $parentValue = trim((string)$holder['state']);
    if ($parentValue === '') return ['state_id' => 0, 'state_code' => '', 'state_name' => ''];

    $stateRow = [];
    if (!tableExists($conn, 'franchies_holder')) return ['state_id' => 0, 'state_code' => '', 'state_name' => ''];

    if (is_numeric($parentValue)) {
        $sid  = (int)$parentValue;
        $stmt = $conn->prepare("SELECT * FROM franchies_holder WHERE id = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param('i', $sid);
            $stmt->execute();
            $stateRow = $stmt->get_result()->fetch_assoc() ?: [];
            $stmt->close();
        }
    }

    if (empty($stateRow)) {
        foreach (['franchies_id','franchise_id','franchise_code','state_franchies_id','state_franchise_id'] as $cc) {
            if (!colExists($conn, 'franchies_holder', $cc)) continue;
            $stmt = $conn->prepare("SELECT * FROM franchies_holder WHERE `$cc` = ? LIMIT 1");
            if (!$stmt) continue;
            $stmt->bind_param('s', $parentValue);
            $stmt->execute();
            $stateRow = $stmt->get_result()->fetch_assoc() ?: [];
            $stmt->close();
            if (!empty($stateRow)) break;
        }
    }

    if (empty($stateRow)) {
        $stmt = $conn->prepare("SELECT * FROM franchies_holder WHERE LOWER(TRIM(state)) = LOWER(TRIM(?)) OR LOWER(TRIM(name)) = LOWER(TRIM(?)) LIMIT 1");
        if ($stmt) {
            $stmt->bind_param('ss', $parentValue, $parentValue);
            $stmt->execute();
            $stateRow = $stmt->get_result()->fetch_assoc() ?: [];
            $stmt->close();
        }
    }

    if (!empty($stateRow)) {
        $stateCode = '';
        foreach (['franchies_id','franchise_id','franchise_code'] as $cc) {
            if (!empty($stateRow[$cc])) { $stateCode = (string)$stateRow[$cc]; break; }
        }
        return [
            'state_id'   => (int)($stateRow['id'] ?? 0),
            'state_code' => $stateCode,
            'state_name' => trim((string)($stateRow['state'] ?? $stateRow['name'] ?? '')),
        ];
    }

    return ['state_id' => 0, 'state_code' => '', 'state_name' => ''];
}

function resolveParentCityForMobile(mysqli $conn, string $franchiseId, string $franchiseCode): array
{
    $holder = fetchFranchiseHolderRow($conn, 'mobile_franchies_holder', (int)$franchiseId, $franchiseCode);
    if (empty($holder)) return ['city_id' => 0, 'city_code' => ''];

    $parentCols  = ['city_franchies_id','city_franchise_id','city_franchies_holder_id','city_holder_id','city_code','city_franchise_code','under_city_id','parent_id','city_id'];
    $parentValue = '';
    foreach ($parentCols as $col) {
        if (!empty($holder[$col])) { $parentValue = trim((string)$holder[$col]); break; }
    }
    if ($parentValue === '' && !empty($holder['city'])) $parentValue = trim((string)$holder['city']);
    if ($parentValue === '') return ['city_id' => 0, 'city_code' => ''];

    $cityRow = [];
    if (is_numeric($parentValue) && tableExists($conn, 'city_franchies_holder')) {
        $stmt = $conn->prepare('SELECT * FROM city_franchies_holder WHERE id = ? LIMIT 1');
        if ($stmt) {
            $id = (int)$parentValue;
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $cityRow = $stmt->get_result()->fetch_assoc() ?: [];
            $stmt->close();
        }
    }

    if (empty($cityRow) && tableExists($conn, 'city_franchies_holder')) {
        $codeCols = ['city_franchies_id','city_franchise_id','city_franchies_code','franchies_id','franchise_id','franchise_code'];
        foreach ($codeCols as $codeCol) {
            if (!colExists($conn, 'city_franchies_holder', $codeCol)) continue;
            $stmt = $conn->prepare("SELECT * FROM city_franchies_holder WHERE `$codeCol` = ? LIMIT 1");
            if (!$stmt) continue;
            $stmt->bind_param('s', $parentValue);
            $stmt->execute();
            $cityRow = $stmt->get_result()->fetch_assoc() ?: [];
            $stmt->close();
            if (!empty($cityRow)) break;
        }
    }

    if (!empty($cityRow) && !cityFranchiseHasAvailableStock($conn, $cityRow)) {
        $fallback = findFallbackCityFranchiseWithAvailableStock($conn, $cityRow['city'] ?? '', $cityRow['state'] ?? '', (int)($cityRow['id'] ?? 0));
        if (!empty($fallback)) $cityRow = $fallback;
    }

    if (!empty($cityRow)) {
        return [
            'city_id'   => (int)($cityRow['id'] ?? 0),
            'city_code' => getCityFranchisePrimaryCode($cityRow),
        ];
    }
    return ['city_id' => 0, 'city_code' => ''];
}

function buildFranchiseBalanceMap(
    mysqli $conn,
    string $franchiseId,
    string $franchiseCode
): array
{
    $balanceMap = [];
    if (!tableExists($conn, 'franchies_product_stock_request') || !colExists($conn, 'franchies_product_stock_request', 'product_balance')) {
        return $balanceMap;
    }

    if (colExists($conn, 'franchies_product_stock_request', 'franchise_code')) {
        $stmt = $conn->prepare("SELECT LOWER(TRIM(product_name)) AS product_key, COALESCE(SUM(product_balance), 0) AS available_stock FROM franchies_product_stock_request WHERE (franchise_id = ? OR franchise_code = ?) GROUP BY LOWER(TRIM(product_name))");
        if ($stmt) $stmt->bind_param('is', $franchiseId, $franchiseCode);
    } else {
        $stmt = $conn->prepare("SELECT LOWER(TRIM(product_name)) AS product_key, COALESCE(SUM(product_balance), 0) AS available_stock FROM franchies_product_stock_request WHERE franchise_id = ? GROUP BY LOWER(TRIM(product_name))");
        if ($stmt) $stmt->bind_param('i', $franchiseId);
    }

    if (!$stmt) return $balanceMap;

    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $productKey = (string)($row['product_key'] ?? '');
        if ($productKey === '') continue;
        $balanceMap[$productKey] = max(0, (int)($row['available_stock'] ?? 0));
    }
    $stmt->close();
    return $balanceMap;
}

$success = '';
$error   = '';

$stateFranchiseId    = 0;
$stateFranchiseCode  = '';
$stateFranchiseName  = '';
$stateParentResolved = false;
$parentCityStockMap  = [];
$parentCityResolved  = false;
$stateStockMap       = [];

$mobileUsesStateFallback = false;
$mobileStateFallbackId   = 0;
$mobileStateFallbackCode = '';
$mobileStateFallbackName = '';

if ($franchiseType === 'state') {
    $stateFranchiseId    = $franchiseId;
    $stateFranchiseCode  = $franchiseCode;
    $stateFranchiseName  = $user['full_name'] ?? '';
    $stateParentResolved = true;
}

if ($franchiseType === 'city' || $franchiseType === 'mobile') {

    $resolveRow = function($tbl, $id, $code) use ($conn) {
        $row = null;
        if (!tableExists($conn, $tbl)) return $row;

        if ($id > 0) {
            $stmt = $conn->prepare("SELECT * FROM `$tbl` WHERE id = ? LIMIT 1");
            if ($stmt) {
                $stmt->bind_param('i', $id);
                $stmt->execute();
                $row = $stmt->get_result()->fetch_assoc();
                $stmt->close();
            }
        }

        if (!$row && $code !== '') {
            $tryCols = ['franchies_id','franchise_id','franchise_code','mobile_franchies_id','mobile_franchise_id'];
            foreach ($tryCols as $c) {
                if (!colExists($conn, $tbl, $c)) continue;
                $stmt = $conn->prepare("SELECT * FROM `$tbl` WHERE `$c` = ? LIMIT 1");
                if (!$stmt) continue;
                $stmt->bind_param('s', $code);
                $stmt->execute();
                $row = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                if ($row) break;
            }
        }
        return $row;
    };

    $resolveStateParent = function($parentVal) use ($conn, &$stateFranchiseId, &$stateFranchiseCode, &$stateFranchiseName, &$stateParentResolved) {
        $parentVal = trim((string)$parentVal);
        if ($parentVal === '' || !tableExists($conn, 'franchies_holder')) return;

        if (is_numeric($parentVal)) {
            $sid  = (int)$parentVal;
            $stmt = $conn->prepare("SELECT * FROM franchies_holder WHERE id = ? LIMIT 1");
            if ($stmt) {
                $stmt->bind_param('i', $sid);
                $stmt->execute();
                $r = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                if ($r) {
                    $stateFranchiseId   = (int)($r['id'] ?? 0);
                    $stateFranchiseName = trim((string)($r['state'] ?? $r['name'] ?? ''));
                    $stateParentResolved = true;
                    foreach (['franchies_id','franchise_id','franchise_code'] as $cc) {
                        if (!empty($r[$cc])) { $stateFranchiseCode = (string)$r[$cc]; break; }
                    }
                    return;
                }
            }
        }

        foreach (['franchies_id','franchise_id','franchise_code','state_franchies_id','state_franchise_id'] as $cc) {
            if (!colExists($conn, 'franchies_holder', $cc)) continue;
            $stmt = $conn->prepare("SELECT * FROM franchies_holder WHERE `$cc` = ? LIMIT 1");
            if (!$stmt) continue;
            $stmt->bind_param('s', $parentVal);
            $stmt->execute();
            $r = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if ($r) {
                $stateFranchiseId   = (int)($r['id'] ?? 0);
                $stateFranchiseName = trim((string)($r['state'] ?? $r['name'] ?? ''));
                $stateParentResolved = true;
                foreach (['franchies_id','franchise_id','franchise_code'] as $codeCol) {
                    if (!empty($r[$codeCol])) { $stateFranchiseCode = (string)$r[$codeCol]; break; }
                }
                if ($stateFranchiseCode === '') $stateFranchiseCode = $parentVal;
                return;
            }
        }

        if ($stateFranchiseCode === '') $stateFranchiseCode = $parentVal;
    };

    $resolveStateByName = function($stateName) use ($conn, &$stateFranchiseId, &$stateFranchiseCode, &$stateFranchiseName, &$stateParentResolved) {
        $stateName = trim((string)$stateName);
        if ($stateName === '' || !tableExists($conn, 'franchies_holder')) return;

        $queries = [
            ["SELECT * FROM franchies_holder WHERE LOWER(TRIM(state)) = LOWER(TRIM(?)) LIMIT 1", 's'],
            ["SELECT * FROM franchies_holder WHERE LOWER(TRIM(name))  = LOWER(TRIM(?)) LIMIT 1", 's'],
            ["SELECT * FROM franchies_holder WHERE franchise_code = ? LIMIT 1", 's'],
        ];
        if (is_numeric($stateName)) {
            array_unshift($queries, ["SELECT * FROM franchies_holder WHERE id = ? LIMIT 1", 'i']);
        }

        foreach ($queries as [$sql, $type]) {
            $stmt = $conn->prepare($sql);
            if (!$stmt) continue;
            if ($type === 'i') { $id = (int)$stateName; $stmt->bind_param('i', $id); }
            else                { $stmt->bind_param('s', $stateName); }
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if (!$row) continue;

            $stateFranchiseId   = (int)($row['id'] ?? 0);
            $stateFranchiseName = trim((string)($row['state'] ?? $row['name'] ?? ''));
            $stateParentResolved = true;
            foreach (['franchies_id','franchise_id','franchise_code'] as $cc) {
                if (!empty($row[$cc])) { $stateFranchiseCode = (string)$row[$cc]; break; }
            }
            return;
        }
    };

    $sessionStateName = trim((string)($_SESSION['franchise_state'] ?? $_SESSION['state'] ?? ''));
    if ($sessionStateName !== '') $resolveStateByName($sessionStateName);

    if ($franchiseType === 'city') {
        $tbl = 'city_franchies_holder';
        $row = $resolveRow($tbl, $franchiseId, $franchiseCode);
        if ($row) {
            foreach (['state_franchies_id','state_franchise_id','state_franchies_holder_id','state_holder_id','state_code','state_franchise_code','under_state_id','parent_id','state_id'] as $pc) {
                if (isset($row[$pc]) && trim((string)$row[$pc]) !== '') {
                    $resolveStateParent(trim((string)$row[$pc]));
                    break;
                }
            }
        }
        if ($stateFranchiseId <= 0 && $stateFranchiseCode === '' && $row && !empty($row['state'])) {
            $resolveStateByName($row['state']);
        }

    } elseif ($franchiseType === 'mobile') {
        $cityInfo = resolveParentCityForMobile($conn, (string)$franchiseId, $franchiseCode);
        $cityId   = (int)($cityInfo['city_id']   ?? 0);
        $cityCode = trim((string)($cityInfo['city_code'] ?? ''));

        if ($cityId > 0 || $cityCode !== '') {
            $parentCityStockMap = fetchFranchiseWalletStockMap($conn, 'city', (string)$cityId, $cityCode);
            if (tableExists($conn, 'franchies_product_stock_request') && colExists($conn, 'franchies_product_stock_request', 'product_balance')) {
                $balanceMap = buildFranchiseBalanceMap($conn, 'city', (string)$cityId, $cityCode);
                foreach ($balanceMap as $productKey => $stockQty) {
                    if ($productKey === '') continue;
                    $parentCityStockMap[$productKey] = max(0, ($parentCityStockMap[$productKey] ?? 0) + $stockQty);
                }
            }

            $cityHasAnyStock = false;
            foreach ($parentCityStockMap as $qty) {
                if ($qty > 0) { $cityHasAnyStock = true; break; }
            }

            if ($cityHasAnyStock) {
                $parentCityResolved = true;
            } else {
                $parentCityStockMap = [];
            }
        }

        if (!$parentCityResolved) {
            $stateInfo = resolveStateFranchiseForMobile($conn, (string)$franchiseId, $franchiseCode);
            $sfId   = (int)($stateInfo['state_id']   ?? 0);
            $sfCode = trim((string)($stateInfo['state_code'] ?? ''));
            $sfName = trim((string)($stateInfo['state_name'] ?? ''));

            if ($sfId <= 0 && $sfCode === '') {
                $sessionStateName2 = trim((string)($_SESSION['franchise_state'] ?? $_SESSION['state'] ?? ''));
                if ($sessionStateName2 !== '' && tableExists($conn, 'franchies_holder')) {
                    $stmtFb = $conn->prepare("SELECT * FROM franchies_holder WHERE LOWER(TRIM(state)) = LOWER(TRIM(?)) OR LOWER(TRIM(name)) = LOWER(TRIM(?)) LIMIT 1");
                    if ($stmtFb) {
                        $stmtFb->bind_param('ss', $sessionStateName2, $sessionStateName2);
                        $stmtFb->execute();
                        $fbRow = $stmtFb->get_result()->fetch_assoc() ?: [];
                        $stmtFb->close();
                        if (!empty($fbRow)) {
                            $sfId   = (int)($fbRow['id'] ?? 0);
                            $sfName = trim((string)($fbRow['state'] ?? $fbRow['name'] ?? ''));
                            foreach (['franchies_id','franchise_id','franchise_code'] as $cc) {
                                if (!empty($fbRow[$cc])) { $sfCode = (string)$fbRow[$cc]; break; }
                            }
                        }
                    }
                }
            }

            if ($sfId > 0 || $sfCode !== '') {
               
$stateStockMapFallback = buildFranchiseBalanceMap(
    $conn,
    (string)$sfId,
    $sfCode
);

                $parentCityStockMap      = $stateStockMapFallback;
                $parentCityResolved      = true;
                $mobileUsesStateFallback = true;
                $mobileStateFallbackId   = $sfId;
                $mobileStateFallbackCode = $sfCode;
                $mobileStateFallbackName = $sfName;
            }
        }

        $stateFranchiseId   = 0;
        $stateFranchiseCode = '';
    }

    if ($franchiseType === 'city' && (($stateFranchiseId > 0) || $stateFranchiseCode !== '')) {
        $stateWalletStockMap = buildFranchiseBalanceMap(
    $conn,
    (string)$stateFranchiseId,
    $stateFranchiseCode
);
        foreach ($stateWalletStockMap as $productKey => $stockQty) {
            if ($productKey === '') continue;
            $stateStockMap[$productKey] = max(0, ($stateStockMap[$productKey] ?? 0) + $stockQty);
        }

        if (tableExists($conn, 'franchies_product_stock_request') && colExists($conn, 'franchies_product_stock_request', 'product_balance')) {
            $balanceMap = buildFranchiseBalanceMap(
    $conn,
    (string)$stateFranchiseId,
    $stateFranchiseCode
);
            foreach ($balanceMap as $productKey => $stockQty) {
    if ($productKey === '') continue;

    if (!isset($stateStockMap[$productKey])) {
        $stateStockMap[$productKey] = max(0, $stockQty);
    }
}
        }    

        if (tableExists($conn, 'franchies_add_product')) {
            $q = $conn->query("SELECT id, product_name FROM franchies_add_product WHERE product_name IS NOT NULL AND product_name != '' ORDER BY id ASC");
            while ($q && $r = $q->fetch_assoc()) {
                $pname = trim($r['product_name']);
                $key   = strtolower($pname);
                if (isset($stateStockMap[$key]) && $stateStockMap[$key] > 0) continue;

                $addedStock = 0;
                $soldStock  = 0;

                if (tableExists($conn, 'franchies_product_request')) {
                    if (colExists($conn, 'franchies_product_request', 'franchise_code')) {
                        $stmt = $conn->prepare("SELECT IFNULL(SUM(quantity),0) AS qty FROM `franchies_product_request` WHERE LOWER(TRIM(product_name)) = LOWER(TRIM(?)) AND LOWER(TRIM(status)) = 'approved' AND (franchise_id = ? OR franchise_code = ?)");
                        if ($stmt) {
                            $stmt->bind_param('sis', $pname, $stateFranchiseId, $stateFranchiseCode);
                            $stmt->execute();
                            $r2 = $stmt->get_result()->fetch_assoc();
                            $addedStock = (int)($r2['qty'] ?? 0);
                            $stmt->close();
                        }
                    } else {
                        $stmt = $conn->prepare("SELECT IFNULL(SUM(quantity),0) AS qty FROM `franchies_product_request` WHERE LOWER(TRIM(product_name)) = LOWER(TRIM(?)) AND LOWER(TRIM(status)) = 'approved' AND franchise_id = ?");
                        if ($stmt) {
                            $stmt->bind_param('si', $pname, $stateFranchiseId);
                            $stmt->execute();
                            $r2 = $stmt->get_result()->fetch_assoc();
                            $addedStock = (int)($r2['qty'] ?? 0);
                            $stmt->close();
                        }
                    }
                }

                if (tableExists($conn, 'franchise_order_sale')) {
                    if (colExists($conn, 'franchise_order_sale', 'distributor_id')) {
                        $fidStr = (string)$stateFranchiseId;
                        $stmt3  = $conn->prepare("SELECT IFNULL(SUM(quantity),0) AS qty FROM franchise_order_sale WHERE LOWER(TRIM(product_name)) = LOWER(TRIM(?)) AND (distributor_id = ? OR distributor_id = ?)");
                        if ($stmt3) {
                            $stmt3->bind_param('sss', $pname, $stateFranchiseCode, $fidStr);
                            $stmt3->execute();
                            $r3 = $stmt3->get_result()->fetch_assoc();
                            $soldStock = (int)($r3['qty'] ?? 0);
                            $stmt3->close();
                        }
                    } else {
                        $stmt3 = $conn->prepare("SELECT IFNULL(SUM(quantity),0) AS qty FROM franchise_order_sale WHERE LOWER(TRIM(product_name)) = LOWER(TRIM(?))");
                        if ($stmt3) {
                            $stmt3->bind_param('s', $pname);
                            $stmt3->execute();
                            $r3 = $stmt3->get_result()->fetch_assoc();
                            $soldStock = (int)($r3['qty'] ?? 0);
                            $stmt3->close();
                        }
                    }
                }

                $available = max(0, $addedStock - $soldStock);
                $stateStockMap[$key] = $available;
            }
        }
    }
}

$walletStockMap = fetchFranchiseWalletStockMap($conn, $franchiseType, (string)$franchiseId, $franchiseCode);

$products = [];
$productStmt = $conn->prepare("
    SELECT id, product_name, price, quantity, status
    FROM franchies_add_product
    WHERE status = 'Active'
    ORDER BY id ASC
");
if ($productStmt) {
    $productStmt->execute();
    $result = $productStmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $row['category'] = '';
        $row['amount']   = $row['price'];
        $row['stock']    = (int)$row['quantity'];
        $products[] = $row;
    }
    $productStmt->close();
}

$effectiveStockMap = [];
foreach ($products as $p) {
    $key = strtolower(trim($p['product_name']));

    if ($franchiseType === 'state') {
        $effectiveStockMap[$key] = array_key_exists($key, $walletStockMap)
            ? (int)$walletStockMap[$key]
            : (int)$p['stock'];
    } elseif ($franchiseType === 'city') {
        if (array_key_exists($key, $stateStockMap)) {
            $effectiveStockMap[$key] = $stateStockMap[$key] ?? 0;
        } elseif (array_key_exists($key, $walletStockMap)) {
            $effectiveStockMap[$key] = (int)$walletStockMap[$key];
        } else {
            $effectiveStockMap[$key] = 0;
        }
    } else {
        $effectiveStockMap[$key] = array_key_exists($key, $parentCityStockMap)
            ? ($parentCityStockMap[$key] ?? 0)
            : 0;
    }

    $effectiveStockMap['_id_' . $p['id']] = [
        'stock' => $effectiveStockMap[$key],
        'name'  => $p['product_name'],
    ];
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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['place_order'])) {

    $payment_mode      = trim($_POST['payment_mode']    ?? '');
    $transaction_no    = trim($_POST['transaction_no']  ?? '');
    $remark            = trim($_POST['remark']          ?? '');
    $selected_products = $_POST['products'] ?? [];

    $filtered_products = [];
    $total_amount      = 0;
    $stock_error       = '';

    foreach ($selected_products as $p) {
        $qty   = (int)($p['qty']   ?? 0);
        $price = (float)($p['price'] ?? 0);
        if ($qty <= 0 || $price <= 0) continue;

        $pid       = (int)($p['product_id'] ?? 0);
        $stockInfo = $effectiveStockMap['_id_' . $pid] ?? null;

        if (!$stockInfo) {
            $chk = $conn->prepare("SELECT quantity, product_name FROM franchies_add_product WHERE id = ? AND status = 'Active' LIMIT 1");
            if ($chk) {
                $chk->bind_param("i", $pid);
                $chk->execute();
                $chkRow = $chk->get_result()->fetch_assoc();
                $chk->close();
                if (!$chkRow) continue;
                $pNameRaw = $chkRow['product_name'];
                $key      = strtolower(trim($pNameRaw));
                if ($franchiseType === 'state') {
                    $availableStock = (int)$chkRow['quantity'];
                } elseif ($franchiseType === 'city') {
                    $availableStock = $stateStockMap[$key] ?? ($walletStockMap[$key] ?? 0);
                } else {
                    $availableStock = $parentCityStockMap[$key] ?? 0;
                }
            } else {
                continue;
            }
        } else {
            $availableStock = (int)$stockInfo['stock'];
            $pNameRaw       = $stockInfo['name'];
        }

        $pName = htmlspecialchars($pNameRaw ?? ($p['product_name'] ?? 'Product'));

        if ($availableStock <= 0) {
            $stock_error .= "\"$pName\" is out of stock. ";
            continue;
        }
        if ($qty > $availableStock) {
            $stock_error .= "\"$pName\" only has {$availableStock} unit(s) available. ";
            continue;
        }

        $filtered_products[] = [
            'id'           => $pid,
            'product_name' => $p['product_name'] ?? $pNameRaw,
            'category'     => $p['category']     ?? '',
            'price'        => $price,
            'qty'          => $qty,
        ];
        $total_amount += $price * $qty;
    }

    if ($stock_error !== '') {
        $error = $stock_error;
    } elseif (empty($filtered_products)) {
        $error = "Please select at least one product with valid quantity.";
    } elseif ($payment_mode === '') {
        $error = "Please select payment mode.";
    } elseif ($transaction_no === '') {
        $error = "Please enter transaction / UTR number.";
    } elseif (!isset($_FILES['receipt']) || $_FILES['receipt']['error'] !== UPLOAD_ERR_OK) {
        $error = "Please upload your payment receipt.";
    } else {
        $uploadDir = __DIR__ . '/uploads/receipts/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

        $ext     = strtolower(pathinfo($_FILES['receipt']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'pdf'];

        if (!in_array($ext, $allowed, true)) {
            $error = "Only JPG, PNG, PDF files allowed.";
        } elseif ($_FILES['receipt']['size'] > 5 * 1024 * 1024) {
            $error = "File size must be under 5 MB.";
        } else {
            $newName  = 'receipt_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
            $filePath = 'uploads/receipts/' . $newName;

            if (!move_uploaded_file($_FILES['receipt']['tmp_name'], __DIR__ . '/' . $filePath)) {
                $error = "Failed to upload receipt. Please try again.";
            } else {
                $order_id = 'ORD-' . date('YmdHis') . rand(100, 999);
                $conn->begin_transaction();

                try {
                    $payStmt = $conn->prepare("
                        INSERT INTO `$paymentTable`
                        (order_id, franchise_id, total_amount, payment_mode,
                         utr_number, receipt, remark, status, created_at, franchise_code)
                        VALUES (?, ?, ?, ?, ?, ?, ?, 'Pending', NOW(), ?)
                    ");
                    if (!$payStmt) throw new Exception("Payment stmt: " . $conn->error);
                    $payStmt->bind_param(
                        "sidsssss",
                        $order_id,
                        $franchiseId,
                        $total_amount,
                        $payment_mode,
                        $transaction_no,
                        $filePath,
                        $remark,
                        $franchiseCode
                    );
                    $payStmt->execute();
                    $payStmt->close();

                    $prodSql  = "INSERT INTO `$requestTable` (order_id, franchise_id, franchise_type, category, product_name, offer_price, quantity, total_amount, status, created_at, franchise_code) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Pending', NOW(), ?)";
                    $prodStmt = $conn->prepare($prodSql);
                    if (!$prodStmt) throw new Exception("Product stmt: " . $conn->error);

                    foreach ($filtered_products as $fp) {
                        $line_total = $fp['price'] * $fp['qty'];
                        $prodStmt->bind_param("sisssdids",
                            $order_id, $franchiseId,
                            $franchiseType, $fp['category'], $fp['product_name'],
                            $fp['price'], $fp['qty'],
                            $line_total, $franchiseCode
                        );
                        $prodStmt->execute();
                    }
                    $prodStmt->close();

                    $conn->commit();
                    $success = "Order placed successfully! Order ID: <strong>" . e($order_id) . "</strong>. We'll review your payment shortly.";

                } catch (Exception $ex) {
                    $conn->rollback();
                    @unlink(__DIR__ . '/' . $filePath);
                    $error = "Database error: " . $ex->getMessage();
                }
            }
        }
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Product Request</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
    :root {
        /* remapped to the franchise panel's saffron / green theme —
           was navy #0f2d52 / gold #c8962a. Every component below reads
           these variables, so the whole page follows automatically. */
        --navy:       #0E7A3B;
        --navy-2:     #0A5E2D;
        --navy-light: #EAF5EC;
        --gold:       #E0700C;
        --gold-2:     #F0A44F;
        --gold-light: #FFF3E7;
        --gold-bg:    #FFF8EF;
        --green:      #16a34a;
        --green-bg:   #f0fdf4;
        --red:        #dc2626;
        --red-bg:     #fef2f2;
        --border:     #e2ddd3;
        --border-2:   #ede8de;
        --bg:         #FBFAF6;
        --card:       #fffdf9;
        --text:       #1c1a17;
        --muted:      #78726a;
        --mono:       'JetBrains Mono', monospace;
        --shadow:     0 2px 16px rgba(14,58,42,0.08);
        --shadow-lg:  0 8px 40px rgba(14,58,42,0.13);
        --radius:     14px;
        --radius-sm:  8px;
    }
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    body { background: var(--bg); color: var(--text); min-height: 100vh; font-family: 'Inter', system-ui, sans-serif; }

    .badge { display: inline-flex; align-items: center; gap: 5px; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 700; letter-spacing: 0.03em; }
    .badge-state  { background: #fef3c7; color: #92400e; border: 1px solid #f9c74f; }
    .badge-city   { background: #dbeafe; color: #1e3a5f; border: 1px solid #93c5fd; }
    .badge-mobile { background: #d1fae5; color: #065f46; border: 1px solid #6ee7b7; }

    .alert { display: flex; align-items: flex-start; gap: 12px; padding: 14px 18px; border-radius: var(--radius-sm); font-size: 14px; font-weight: 600; margin-bottom: 20px; border: 1.5px solid; animation: slideDown 0.3s ease; }
    .alert-success { background: var(--green-bg); color: #15803d; border-color: #bbf7d0; }
    .alert-error   { background: var(--red-bg);   color: var(--red); border-color: #fecaca; }
    .alert svg     { width: 20px; height: 20px; flex-shrink: 0; margin-top: 1px; }

    .section-label { display: flex; align-items: center; gap: 10px; padding: 13px 18px; background: var(--navy); color: var(--gold-2); font-size: 12px; font-weight: 700; letter-spacing: 0.07em; text-transform: uppercase; border-radius: var(--radius) var(--radius) 0 0; }
    .section-label svg { width: 16px; height: 16px; opacity: 0.85; }

    .card { background: var(--card); border: 1px solid var(--border); border-radius: var(--radius); overflow: hidden; box-shadow: var(--shadow); animation: fadeUp 0.35s ease both; }

    .main-grid { display: grid; grid-template-columns: 1fr 320px; gap: 20px; margin-bottom: 20px; }
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

    .stock-badge { display: inline-flex; align-items: center; gap: 5px; padding: 3px 9px; border-radius: 20px; font-size: 11.5px; font-weight: 700; white-space: nowrap; }
    .stock-in  { background: #dcfce7; color: #15803d; }
    .stock-low { background: #fef9c3; color: #92400e; }
    .stock-out { background: #fee2e2; color: #991b1b; }
    .stock-dot { width: 6px; height: 6px; border-radius: 50%; flex-shrink: 0; }
    .stock-in  .stock-dot { background: #16a34a; }
    .stock-low .stock-dot { background: #d97706; }
    .stock-out .stock-dot { background: #dc2626; }
    .row-out-of-stock { opacity: 0.55; }
    .row-out-of-stock td { color: var(--muted); }

    .qty-wrap { display: flex; align-items: center; border: 1.5px solid var(--border); border-radius: var(--radius-sm); overflow: hidden; width: fit-content; background: white; }
    .qty-btn  { width: 30px; height: 34px; background: var(--bg); border: none; cursor: pointer; font-size: 16px; font-weight: 700; color: var(--navy); display: flex; align-items: center; justify-content: center; flex-shrink: 0; transition: background 0.15s; }
    .qty-btn:hover:not(:disabled) { background: var(--navy-light); }
    .qty-btn:disabled { opacity: 0.3; cursor: not-allowed; }
    .qty-input { width: 44px; height: 34px; text-align: center; border: none; border-left: 1px solid var(--border); border-right: 1px solid var(--border); outline: none; font-size: 13px; font-weight: 700; color: var(--navy); background: white; -moz-appearance: textfield; appearance: none; }
    .qty-input::-webkit-inner-spin-button, .qty-input::-webkit-outer-spin-button { -webkit-appearance: none; }
    .out-of-stock-label { font-size: 12px; font-weight: 700; color: var(--red); background: var(--red-bg); padding: 5px 10px; border-radius: 6px; border: 1px solid #fecaca; display: inline-block; }
    .row-amount { font-weight: 700; color: var(--navy); font-size: 13.5px; }
    .row-amount.has-amount { color: var(--green); }

    .grand-row { background: var(--gold-bg); border-top: 2px solid var(--border-2) !important; }
    .grand-row td { font-weight: 800; font-size: 14px; padding: 13px 14px; }
    .grand-total-label { text-align: right; color: var(--navy); font-size: 13px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em; }
    #grandTotal { font-family: var(--mono); font-size: 16px; font-weight: 800; color: var(--green); }

    .sidebar-stack { display: flex; flex-direction: column; gap: 16px; }
    .qr-card { background: var(--navy); border-radius: var(--radius); padding: 20px; text-align: center; box-shadow: var(--shadow-lg); }
    .qr-card-title { font-size: 13px; font-weight: 700; color: var(--gold-2); margin-bottom: 14px; text-transform: uppercase; letter-spacing: 0.05em; }
    .qr-img-wrap { width: 156px; height: 156px; background: white; border-radius: 10px; padding: 10px; margin: 0 auto 14px; display: flex; align-items: center; justify-content: center; }
    .qr-img-wrap img { width: 100%; height: 100%; object-fit: contain; }
    .qr-upi   { font-family: var(--mono); font-size: 12px; color: rgba(255,255,255,0.65); margin-bottom: 4px; }
    .qr-label { font-size: 12px; font-weight: 700; color: white; opacity: 0.85; }

    .bank-body { padding: 4px 0; }
    .bank-row  { display: flex; align-items: center; justify-content: space-between; gap: 10px; padding: 10px 16px; border-bottom: 1px solid var(--border-2); font-size: 13px; }
    .bank-row:last-child { border-bottom: none; }
    .bank-key  { color: var(--muted); font-weight: 600; font-size: 12px; min-width: 96px; flex-shrink: 0; }
    .bank-val  { font-family: var(--mono); font-weight: 700; color: var(--navy); font-size: 12.5px; text-align: right; word-break: break-all; }

    .steps-body { padding: 14px 18px; }
    .step-item  { display: flex; align-items: flex-start; gap: 12px; padding: 8px 0; font-size: 13px; color: var(--muted); font-weight: 500; border-bottom: 1px dashed var(--border-2); }
    .step-item:last-child { border-bottom: none; }
    .step-num   { flex-shrink: 0; width: 22px; height: 22px; border-radius: 50%; background: var(--navy); color: white; font-size: 11px; font-weight: 800; display: flex; align-items: center; justify-content: center; margin-top: 1px; }

    .form-body  { padding: 24px; }
    .form-grid  { display: grid; grid-template-columns: 200px 1fr; gap: 16px 20px; align-items: start; }
    .form-label { font-size: 13px; font-weight: 700; color: var(--navy); padding-top: 11px; }
    .form-label .req { color: var(--red); margin-left: 2px; }
    .field-wrap { display: flex; flex-direction: column; gap: 5px; }

    .f-input, .f-select, .f-textarea { width: 100%; padding: 10px 14px; border: 1.5px solid var(--border); border-radius: var(--radius-sm); font-family: inherit; font-size: 13.5px; color: var(--text); background: white; outline: none; transition: border-color .18s, box-shadow .18s; -webkit-appearance: none; appearance: none; }
    .f-input:focus, .f-select:focus, .f-textarea:focus { border-color: var(--navy-2); box-shadow: 0 0 0 3px rgba(26,66,120,0.10); }
    .f-input[readonly] { background: var(--gold-light); color: var(--gold); font-family: var(--mono); font-weight: 700; font-size: 15px; border-color: rgba(224,112,12,.4); cursor: default; }
    .f-textarea { min-height: 80px; resize: vertical; }

    .file-zone { border: 2px dashed var(--border); border-radius: var(--radius-sm); padding: 18px; text-align: center; cursor: pointer; transition: border-color .18s, background .18s; background: white; position: relative; }
    .file-zone:hover { border-color: var(--navy-2); background: var(--navy-light); }
    .file-zone input[type="file"] { position: absolute; inset: 0; opacity: 0; cursor: pointer; width: 100%; height: 100%; }
    .file-icon { width: 36px; height: 36px; border-radius: 10px; background: var(--navy-light); display: flex; align-items: center; justify-content: center; margin: 0 auto 8px; }
    .file-icon svg { width: 18px; height: 18px; color: var(--navy); }
    .file-text { font-size: 13px; font-weight: 600; color: var(--navy); }
    .file-sub  { font-size: 11.5px; color: var(--muted); margin-top: 3px; }
    .file-name { font-size: 12px; color: var(--green); font-weight: 600; margin-top: 6px; }
    .form-divider { grid-column: 1 / -1; height: 1px; background: var(--border-2); margin: 4px 0; }

    .submit-row  { display: flex; align-items: center; gap: 16px; margin-top: 24px; padding-top: 20px; border-top: 1px solid var(--border-2); }
    .btn-submit  { display: inline-flex; align-items: center; gap: 10px; padding: 13px 32px; background: var(--navy); color: white; font-family: inherit; font-size: 15px; font-weight: 700; border: none; border-radius: var(--radius-sm); cursor: pointer; transition: background .18s, transform .15s, box-shadow .18s; box-shadow: 0 4px 16px rgba(15,45,82,0.25); }
    .btn-submit:hover  { background: var(--navy-2); transform: translateY(-1px); box-shadow: 0 8px 24px rgba(15,45,82,0.30); }
    .btn-submit:active { transform: translateY(0); }
    .btn-submit svg { width: 18px; height: 18px; }
    .submit-note { font-size: 12px; color: var(--muted); font-weight: 500; }

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
                    <i data-lucide="badge-check" class="w-[18px] h-[18px] text-[#cd9737]"></i>
                </div>
                <h2 class="whitespace-nowrap text-[20px] font-normal text-[#40352a]"
                    style="font-family: Georgia, 'Times New Roman', serif;">
                    PRODUCT REQUEST
                </h2>
                <div class="w-full h-[2px] bg-[#d1a14b]"></div>
            </div>

            <div style="display:flex;align-items:center;gap:12px;margin-bottom:16px;flex-wrap:wrap;">
                <span style="font-size:14px;color:var(--muted);">
                    Welcome, <strong style="color:var(--text);"><?= e($user['full_name']) ?></strong>
                    &nbsp;·&nbsp;
                    ID: <span style="font-family:monospace;font-weight:700;color:var(--navy);"><?= e($franchiseCode ?: $franchiseId) ?></span>
                </span>
                <span class="badge <?= e($typeBadge['cls']) ?>"><?= e($typeBadge['label']) ?></span>
                <?php if (!empty($stateFranchiseName) && $franchiseType !== 'state' && $franchiseType !== 'mobile'): ?>
                <span style="font-size:14px;color:var(--muted);">
                    Parent State: <strong style="color:var(--text);"><?= e($stateFranchiseName) ?><?php if (!empty($stateFranchiseCode)): ?> (<?= e($stateFranchiseCode) ?>)<?php endif; ?></strong>
                </span>
                <?php endif; ?>
                <?php if ($franchiseType === 'mobile' && $mobileUsesStateFallback && !empty($mobileStateFallbackName)): ?>
                <span style="font-size:14px;color:var(--muted);">
                    Via State: <strong style="color:var(--text);"><?= e($mobileStateFallbackName) ?><?php if (!empty($mobileStateFallbackCode)): ?> (<?= e($mobileStateFallbackCode) ?>)<?php endif; ?></strong>
                </span>
                <?php endif; ?>
            </div>

            <?php if ($franchiseType === 'state'): ?>
            <div style="margin-bottom:10px;font-size:13px;color:#5b5b5b;">
                Available stock is shown from your state franchise inventory.
            </div>
            <?php elseif ($franchiseType === 'city' && $stateParentResolved): ?>
            <div style="margin-bottom:10px;font-size:13px;color:#5b5b5b;">
                Available stock is shown from your parent state franchise inventory.
            </div>
            <?php elseif ($franchiseType === 'mobile' && $parentCityResolved && !$mobileUsesStateFallback): ?>
            <div style="margin-bottom:10px;font-size:13px;color:#5b5b5b;">
                Available stock is shown from your parent city franchise inventory.
            </div>
            <?php elseif ($franchiseType === 'mobile' && $mobileUsesStateFallback): ?>
            <div style="margin-bottom:10px;font-size:13px;color:#b45309;background:#fef3c7;border:1px solid #fde68a;border-radius:6px;padding:10px 14px;">
                No city franchise found or city has no stock. Stock is being sourced directly from the state franchise.
            </div>
            <?php endif; ?>

            <?php if ($success): ?>
            <div class="alert alert-success">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                <?= $success ?>
            </div>
            <?php endif; ?>

            <?php if ($error): ?>
            <div class="alert alert-error">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                <?= $error ?>
            </div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data" id="activationForm">

                <div class="main-grid" style="margin-top:20px;">

                    <div class="card">
                        <div class="section-label">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
                            Step 1 — Select Products
                        </div>
                        <div class="table-scroll table-responsive overflow-x-auto">
                            <table class="products-table">
                                <thead>
                                    <tr>
                                        <th style="width:48px;">#</th>
                                        <th>Product Name</th>
                                        <th style="width:110px;">Price (₹)</th>
                                        <th style="width:130px;">Stock</th>
                                        <th style="width:150px;">Quantity</th>
                                        <th style="width:120px;">Amount (₹)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php if (!empty($products)): ?>
                                    <?php foreach ($products as $i => $p):
                                        $pid = (int)$p['id'];
                                        $key = strtolower(trim($p['product_name']));

                                        if ($franchiseType === 'state') {
                                            $stock = array_key_exists($key, $walletStockMap)
                                                ? (int)$walletStockMap[$key]
                                                : (int)$p['stock'];
                                        } elseif ($franchiseType === 'city') {
                                            if (array_key_exists($key, $stateStockMap)) {
                                                $stock = (int)$stateStockMap[$key];
                                            } elseif (array_key_exists($key, $walletStockMap)) {
                                                $stock = (int)$walletStockMap[$key];
                                            } else {
                                                $stock = 0;
                                            }
                                        } else {
                                            $stock = array_key_exists($key, $parentCityStockMap)
                                                ? (int)$parentCityStockMap[$key]
                                                : 0;
                                        }

                                        $isOut    = ($stock <= 0);
                                        $isLow    = (!$isOut && $stock <= 10);
                                        $rowClass = $isOut ? 'row-out-of-stock' : '';
                                        $badgeCls = $isOut ? 'stock-out' : ($isLow ? 'stock-low' : 'stock-in');
                                        $badgeTxt = $isOut ? 'Out of Stock' : ($isLow ? "Low ($stock left)" : "$stock in stock");
                                    ?>
                                    <tr class="<?= $rowClass ?>">
                                        <td><span class="product-num"><?= $i + 1 ?></span></td>
                                        <td class="product-name-cell break-words"><?= e($p['product_name']) ?></td>
                                        <td class="price-cell">
                                            <?= number_format((float)$p['amount'], 2) ?>
                                            <input type="hidden" name="products[<?= $pid ?>][product_id]"   value="<?= $pid ?>">
                                            <input type="hidden" name="products[<?= $pid ?>][product_name]" value="<?= e($p['product_name']) ?>">
                                            <input type="hidden" name="products[<?= $pid ?>][category]"     value="<?= e($p['category'] ?? '') ?>">
                                            <input type="hidden" name="products[<?= $pid ?>][price]"        value="<?= (float)$p['amount'] ?>">
                                            <input type="hidden" name="products[<?= $pid ?>][stock]"        value="<?= $stock ?>">
                                        </td>
                                        <td>
                                            <span class="stock-badge <?= $badgeCls ?>">
                                                <span class="stock-dot"></span>
                                                <?= $badgeTxt ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($isOut): ?>
                                                <span class="out-of-stock-label">Not Available</span>
                                                <input type="hidden" name="products[<?= $pid ?>][qty]" value="0">
                                            <?php else: ?>
                                                <div class="qty-wrap">
                                                    <button type="button" class="qty-btn qty-minus" tabindex="-1">−</button>
                                                    <input type="number" min="0" max="<?= $stock ?>" value="0"
                                                           class="qty-input qty-field"
                                                           name="products[<?= $pid ?>][qty]"
                                                           data-price="<?= (float)$p['amount'] ?>"
                                                           data-stock="<?= $stock ?>">
                                                    <button type="button" class="qty-btn qty-plus" tabindex="-1">+</button>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="row-amount">0.00</td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <tr class="grand-row">
                                        <td colspan="5" class="grand-total-label">Grand Total</td>
                                        <td id="grandTotal">₹0.00</td>
                                    </tr>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" style="text-align:center;padding:32px;color:var(--muted);font-size:14px;">
                                            No active products found.
                                        </td>
                                    </tr>
                                <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="sidebar-stack">

                        <div class="qr-card">
                            <div class="qr-card-title">Scan &amp; Pay via UPI</div>
                            <div class="qr-img-wrap">
                                <img src="https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=<?= urlencode($bankDetails['upi']) ?>" alt="UPI QR">
                            </div>
                            <div class="qr-upi"><?= e($bankDetails['upi']) ?></div>
                            <div class="qr-label">PhonePe · GPay · Paytm</div>
                        </div>

                        <div class="card">
                            <div class="section-label">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="2" width="18" height="20" rx="2"/><path d="M9 22v-4h6v4"/><path d="M3 11h18"/><path d="M3 7h18"/></svg>
                                Bank Transfer
                            </div>
                            <div class="bank-body">
                                <?php foreach ([
                                    'Account Name' => $bankDetails['account_name'],
                                    'Account No.'  => $bankDetails['account_no'],
                                    'IFSC'         => $bankDetails['ifsc'],
                                    'Branch'       => $bankDetails['branch'],
                                    'City'         => $bankDetails['city'],
                                    'Bank'         => $bankDetails['bank'],
                                ] as $k => $v): ?>
                                <div class="bank-row">
                                    <span class="bank-key"><?= e($k) ?></span>
                                    <span class="bank-val"><?= e($v) ?></span>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="card">
                            <div class="section-label">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
                                How It Works
                            </div>
                            <div class="steps-body">
                                <?php foreach ([
                                    'Select products &amp; set quantities.',
                                    'Scan QR or use bank transfer.',
                                    'Enter your transaction / UTR no.',
                                    'Upload your payment screenshot.',
                                    'Click <strong>Place Order</strong> to submit.',
                                ] as $n => $step): ?>
                                <div class="step-item">
                                    <span class="step-num"><?= $n + 1 ?></span>
                                    <?= $step ?>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                    </div>
                </div>

                <div class="card">
                    <div class="section-label">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
                        Step 2 — Payment Details
                    </div>
                    <div class="form-body">
                        <div class="form-grid">

                            <div class="form-label">Amount to Pay</div>
                            <div class="field-wrap">
                                <input type="text" id="amount_to_pay" name="amount_to_pay" class="f-input" readonly placeholder="₹0.00">
                            </div>

                            <div class="form-divider"></div>

                            <div class="form-label">Payment Mode <span class="req">*</span></div>
                            <div class="field-wrap">
                                <select name="payment_mode" class="f-select" required>
                                    <option value="">— Select Mode —</option>
                                    <?php foreach (['UPI','IMPS','NEFT','RTGS','Cash Deposit'] as $m): ?>
                                    <option value="<?= e($m) ?>" <?= (($_POST['payment_mode'] ?? '') === $m) ? 'selected' : '' ?>><?= e($m) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-label">Transaction / UTR No. <span class="req">*</span></div>
                            <div class="field-wrap">
                                <input type="text" name="transaction_no" class="f-input"
                                       placeholder="Enter transaction or UTR number"
                                       value="<?= e($_POST['transaction_no'] ?? '') ?>" required>
                            </div>

                            <div class="form-divider"></div>

                            <div class="form-label">Upload Receipt <span class="req">*</span></div>
                            <div class="field-wrap">
                                <div class="file-zone">
                                    <input type="file" name="receipt" id="receiptInput" accept=".jpg,.jpeg,.png,.pdf" required>
                                    <div class="file-icon">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                                    </div>
                                    <div class="file-text">Click or drag to upload</div>
                                    <div class="file-sub">JPG, PNG, PDF · Max 5 MB</div>
                                    <div class="file-name" id="fileName"></div>
                                </div>
                            </div>

                            <div class="form-label">Remark</div>
                            <div class="field-wrap">
                                <textarea name="remark" class="f-textarea" placeholder="Optional note..."><?= e($_POST['remark'] ?? '') ?></textarea>
                            </div>

                        </div>

                        <div class="submit-row">
                            <button type="submit" name="place_order" class="btn-submit">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                                Place Order
                            </button>
                            <p class="submit-note">Your request will be reviewed within 24 hours.</p>
                        </div>
                    </div>
                </div>

            </form>
        </main>
    </div>

<script>
document.querySelectorAll('.qty-wrap').forEach(wrap => {
    const input    = wrap.querySelector('.qty-input');
    const maxStock = parseInt(input.dataset.stock) || 9999;

    wrap.querySelector('.qty-minus').addEventListener('click', () => {
        const v = parseInt(input.value) || 0;
        if (v > 0) { input.value = v - 1; input.dispatchEvent(new Event('input')); }
    });

    wrap.querySelector('.qty-plus').addEventListener('click', () => {
        const v = parseInt(input.value) || 0;
        if (v < maxStock) {
            input.value = v + 1;
            input.dispatchEvent(new Event('input'));
        } else {
            input.style.color = '#dc2626';
            setTimeout(() => { input.style.color = ''; }, 600);
        }
    });

    input.addEventListener('change', () => {
        let v = parseInt(input.value) || 0;
        if (v < 0)        v = 0;
        if (v > maxStock) v = maxStock;
        input.value = v;
        input.dispatchEvent(new Event('input'));
    });
});

const qtyFields    = document.querySelectorAll('.qty-field');
const grandTotalEl = document.getElementById('grandTotal');
const amountField  = document.getElementById('amount_to_pay');

function updateTotals() {
    let grand = 0;
    qtyFields.forEach(field => {
        const qty   = parseInt(field.value)           || 0;
        const price = parseFloat(field.dataset.price) || 0;
        const row   = field.closest('tr');
        const amt   = qty * price;
        const cell  = row.querySelector('.row-amount');
        cell.textContent = amt.toFixed(2);
        cell.className   = 'row-amount' + (amt > 0 ? ' has-amount' : '');
        grand += amt;
    });
    grandTotalEl.textContent = '₹' + grand.toFixed(2);
    amountField.value        = grand.toFixed(2);
}

qtyFields.forEach(f => f.addEventListener('input', updateTotals));
updateTotals();

document.getElementById('receiptInput').addEventListener('change', function () {
    document.getElementById('fileName').textContent =
        this.files[0] ? '✓ ' + this.files[0].name : '';
});
</script>

<script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
<script>document.addEventListener('DOMContentLoaded', () => lucide.createIcons());</script>
</body>
</html>