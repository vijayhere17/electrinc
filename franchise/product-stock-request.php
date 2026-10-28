<?php
/**
 * PRODUCT STOCK REPORT — FULLY FIXED v4
 *
 * KEY FIXES vs v3:
 * ─────────────────────────────────────────────────────────────────────
 * FIX-STATE-1  State franchise: franchies_holder columns resolved more
 *              aggressively — tries franchies_id, franchise_id,
 *              franchise_code, id columns to get $franchiseCode.
 *
 * FIX-STATE-2  getSoldQty() — added more state-specific identity cols:
 *              'distributor_id', 'state_distributor_id', 'franchies_id',
 *              'state_franchies_id', 'state_franchise_code'.
 *              Also added 'franchies_member_order' explicitly with
 *              'distributor_id' matching.
 *
 * FIX-STATE-3  getSoldQty() now also tries matching by BOTH numeric id
 *              AND string code in every table — previously the OR logic
 *              was skipping string-based identity for state type.
 *
 * FIX-STATE-4  getApprovedQty() — now also tries franchise_code col
 *              fallback when franchise_code col doesn't exist but
 *              franchies_id col does.
 *
 * FIX-STATE-5  resolveStock() P1 — also checks by franchies_id col
 *              (not just franchise_id) in franchies_product_stock_request.
 *
 * FIX-STATE-6  Debug mode: add ?debug=1 to URL to see raw SQL + results
 *              for each sale table (remove in production).
 * ─────────────────────────────────────────────────────────────────────
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

if (session_status() === PHP_SESSION_NONE) session_start();

include __DIR__ . '/config/db.php';
if (!isset($conn) || !($conn instanceof mysqli)) die("Database connection failed.");

$debugMode = isset($_GET['debug']) && $_GET['debug'] === '1';

/* ═══════════════════════════════════════════════════════════════════════
 * HELPERS
 * ═══════════════════════════════════════════════════════════════════════ */
function e($str): string
{
    return htmlspecialchars((string)($str ?? ''), ENT_QUOTES, 'UTF-8');
}

$_tableCache = [];
$_colCache   = [];

function tableExists(mysqli $conn, string $table): bool
{
    global $_tableCache;
    if (isset($_tableCache[$table])) return $_tableCache[$table];
    $t   = $conn->real_escape_string($table);
    $res = $conn->query("SHOW TABLES LIKE '$t'");
    return $_tableCache[$table] = ($res && $res->num_rows > 0);
}

function colExists(mysqli $conn, string $table, string $col): bool
{
    global $_colCache;
    $key = "$table.$col";
    if (isset($_colCache[$key])) return $_colCache[$key];
    $t   = $conn->real_escape_string($table);
    $c   = $conn->real_escape_string($col);
    $res = $conn->query("SHOW COLUMNS FROM `$t` LIKE '$c'");
    return $_colCache[$key] = ($res && $res->num_rows > 0);
}

function firstCol(mysqli $conn, string $table, array $candidates): ?string
{
    foreach ($candidates as $c) {
        if (colExists($conn, $table, $c)) return $c;
    }
    return null;
}

/* ═══════════════════════════════════════════════════════════════════════
 * getSoldQty
 * ═══════════════════════════════════════════════════════════════════════ */
function getSoldQty(
    mysqli $conn,
    int    $franchiseId,
    string $franchiseCode,
    string $productName,
    string $franchiseType,
    bool   $debug = false
): int {

    $saleTables = [
        'franchise_order_sale',
        'franchies_member_order',
        'member_repurchase_order',
        'state_mobile_order',
        'state_franchise_order',
        'city_member_order',
        'mobile_member_order',
        'member_product_order',
        'product_order',
        'franchise_member_order',
        'member_activation',
        'member_activation_log',
        'franchise_activation',
        'member_join',
        'member_order',
    ];

    /*
     * Code-based identity columns (matched against $franchiseCode)
     * Expanded for state franchise: distributor_id, franchies_id,
     * state_franchies_id, state_franchise_code, state_distributor_id
     */
    $codeIdCols = [
        'franchise_code',
        'distributor_id',
        'franchies_id',
        'city_franchise_code',
        'mobile_franchise_code',
        'state_franchise_code',
        'state_distributor_id',
        'state_franchies_id',
        'franchies_code',
    ];

    /*
     * Numeric identity columns (matched against $franchiseId)
     */
    $numIdCols = [
        'franchise_id',
        'state_franchise_id',
        'state_id',
        'from_franchise_id',
        'seller_id',
        'city_franchise_id',
        'mobile_franchise_id',
        'city_franchies_id',
        'mobile_franchies_id',
        'franchies_holder_id',
        'distributor_franchise_id',
        'state_franchies_id',     // sometimes numeric
        'franchies_id',           // sometimes numeric
    ];

    $okStatuses = "'approved','complete','completed','success','active','1','yes'";

    $totalSold = 0;

    if ($franchiseType === 'state') {
        $stateProductRequestQty = getStateCityMobileOrderSoldQty($conn, $franchiseId, $franchiseCode, $productName, $debug);
        if ($stateProductRequestQty > 0) {
            if ($debug) {
                echo "<p style='color:orange;font-family:monospace;font-size:12px'>".
                     "✓ city_mobile_order state dispatch → sold={$stateProductRequestQty}</p>";
            }
            $totalSold += $stateProductRequestQty;
        }
    }

    if ($franchiseType === 'city') {
        $cityMobileSoldQty = getCityMobileOrderSoldQty($conn, $franchiseId, $franchiseCode, $productName, $debug);
        if ($cityMobileSoldQty > 0) {
            if ($debug) {
                echo "<p style='color:orange;font-family:monospace;font-size:12px'>".
                     "✓ city_mobile_order city supply → sold={$cityMobileSoldQty}</p>";
            }
            $totalSold += $cityMobileSoldQty;
        }
    }

    $memberOrderQty = getMemberOrdersSoldQty($conn, $franchiseId, $franchiseCode, $productName, $debug);
    if ($memberOrderQty > 0) {
        if ($debug) {
            echo "<p style='color:orange;font-family:monospace;font-size:12px'>".
                 "✓ member_orders → sold={$memberOrderQty}</p>";
        }
        $totalSold += $memberOrderQty;
    }

    foreach ($saleTables as $tbl) {

        if ($tbl === 'franchise_order_sale' && $memberOrderQty > 0) {
            if ($debug) {
                echo "<p style='color:yellow;font-family:monospace;font-size:12px'>".
                     "✓ skipping franchise_order_sale because member_orders already counted</p>";
            }
            continue;
        }

        if (!tableExists($conn, $tbl)) continue;

        $qtyCol = firstCol($conn, $tbl, [
            'quantity','qty','product_qty','order_qty',
            'no_of_product','product_count'
        ]);
        if (!$qtyCol) $qtyCol = '__count__';

        $prodCol = firstCol($conn, $tbl, [
            'product_name','product','name','item_name','product_title'
        ]);
        if (!$prodCol) continue;

        $statusSql = '';
        if (colExists($conn, $tbl, 'status')) {
            $statusSql = "AND LOWER(TRIM(`status`)) IN ($okStatuses)";
        }

        /* Build WHERE conditions */
        $whereCode = [];
        $whereNum  = [];

        if ($franchiseCode !== '') {
            foreach ($codeIdCols as $c) {
                if (colExists($conn, $tbl, $c)) {
                    $safe = $conn->real_escape_string($franchiseCode);
                    $whereCode[] = "`$c` = '$safe'";
                }
            }
        }

        if ($franchiseId > 0) {
            foreach ($numIdCols as $c) {
                if (colExists($conn, $tbl, $c)) {
                    $whereNum[] = "`$c` = $franchiseId";
                }
            }
        }

        $allWhere = array_merge($whereCode, $whereNum);
        if (empty($allWhere)) continue;

        $whereSql = implode(' OR ', $allWhere);
        $hasId    = colExists($conn, $tbl, 'id');
        $safeProd = $conn->real_escape_string($productName);

        if ($qtyCol === '__count__') {
            $sql = $hasId
                ? "SELECT COUNT(DISTINCT `id`) AS total FROM `$tbl` WHERE LOWER(TRIM(`$prodCol`)) = LOWER(TRIM('$safeProd')) AND ($whereSql) $statusSql"
                : "SELECT COUNT(*) AS total FROM `$tbl` WHERE LOWER(TRIM(`$prodCol`)) = LOWER(TRIM('$safeProd')) AND ($whereSql) $statusSql";
        } else {
            $sql = $hasId
                ? "SELECT IFNULL(SUM(sub.`$qtyCol`),0) AS total FROM (SELECT DISTINCT `id`,`$qtyCol` FROM `$tbl` WHERE LOWER(TRIM(`$prodCol`)) = LOWER(TRIM('$safeProd')) AND ($whereSql) $statusSql) sub"
                : "SELECT IFNULL(SUM(`$qtyCol`),0) AS total FROM `$tbl` WHERE LOWER(TRIM(`$prodCol`)) = LOWER(TRIM('$safeProd')) AND ($whereSql) $statusSql";
        }

        $res = $conn->query($sql);
        if (!$res) {
            if ($debug) echo "<p style='color:red'>SQL ERROR in $tbl: ".$conn->error."<br>SQL: ".e($sql)."</p>";
            continue;
        }

        $row  = $res->fetch_assoc();
        $sold = max(0, (int)($row['total'] ?? 0));
        $totalSold += $sold;

        if ($debug && $sold > 0) {
            echo "<p style='color:lime;font-family:monospace;font-size:12px'>
                ✓ $tbl → sold=$sold | SQL: ".e($sql)."</p>";
        }
    }

    return $totalSold;
}

/* ═══════════════════════════════════════════════════════════════════════
 * getApprovedQty
 * ═══════════════════════════════════════════════════════════════════════ */
function getApprovedQty(
    mysqli $conn,
    int    $franchiseId,
    string $franchiseCode,
    string $requestTable,
    string $productName
): int {
    if ($franchiseId <= 0 || !tableExists($conn, $requestTable)) return 0;

    $safeProd = $conn->real_escape_string($productName);
    $idStr    = (string)$franchiseId;
    $safeCode = $conn->real_escape_string($franchiseCode);

    /* Build WHERE parts with multiple identity matching */
    $identWhere = [];
    
    /* Try numeric franchise_id match (both INT and VARCHAR) */
    if ($franchiseId > 0) {
        $identWhere[] = "franchise_id = $franchiseId";
        $identWhere[] = "franchise_id = '$idStr'";
    }
    
    /* Try code-based matches */
    $codeColumns = ['franchise_code', 'franchies_id', 'franchies_code', 'city_franchies_id', 'city_franchise_code'];
    foreach ($codeColumns as $col) {
        if ($franchiseCode !== '' && colExists($conn, $requestTable, $col)) {
            $identWhere[] = "`$col` = '$safeCode'";
        }
    }

    if (empty($identWhere)) return 0;

    $identSql = implode(' OR ', $identWhere);
    $sql = "
        SELECT IFNULL(SUM(quantity), 0) AS qty
        FROM `$requestTable`
        WHERE LOWER(TRIM(product_name)) = LOWER(TRIM('$safeProd'))
          AND LOWER(TRIM(status))        = 'approved'
          AND ($identSql)
    ";

    $res = $conn->query($sql);
    if (!$res) return 0;
    return max(0, (int)($res->fetch_assoc()['qty'] ?? 0));
}

/* ═══════════════════════════════════════════════════════════════════════
 * getCityMobileApprovedQty
 * ═══════════════════════════════════════════════════════════════════════ */
function getCityMobileApprovedQty(
    mysqli $conn,
    int    $franchiseId,
    string $franchiseCode,
    string $franchiseType,
    string $productName
): int {
    if ($franchiseId <= 0) return 0;
    if (!in_array($franchiseType, ['city','mobile'], true)) return 0;
    if (!tableExists($conn, 'city_mobile_order')) return 0;

    $safeProd  = $conn->real_escape_string($productName);
    $safeType  = $conn->real_escape_string($franchiseType);
    $safeCode  = $conn->real_escape_string($franchiseCode);
    $idStr     = (string)$franchiseId;
    
    /* Build WHERE parts with multiple identity matching */
    $identWhere = [];
    
    /* Numeric franchise_id match */
    if ($franchiseId > 0) {
        $identWhere[] = "franchise_id = $franchiseId";
        $identWhere[] = "franchise_id = '$idStr'";
    }
    
    /* Code-based franchise_code match */
    if ($franchiseCode !== '' && colExists($conn, 'city_mobile_order', 'franchise_code')) {
        $identWhere[] = "franchise_code = '$safeCode'";
    }

    if (empty($identWhere)) return 0;

    $identSql = implode(' OR ', $identWhere);
    $sql = "
        SELECT IFNULL(SUM(quantity), 0) AS qty
        FROM city_mobile_order
        WHERE LOWER(TRIM(product_name)) = LOWER(TRIM('$safeProd'))
          AND LOWER(TRIM(status))        = 'approved'
          AND franchise_type             = '$safeType'
          AND ($identSql)
    ";

    $res = $conn->query($sql);
    if (!$res) return 0;
    return max(0, (int)($res->fetch_assoc()['qty'] ?? 0));
}

function fetchHolderRow(mysqli $conn, string $table, string $franchiseId, string $franchiseCode): array
{
    if (!tableExists($conn, $table)) {
        return [];
    }

    $franchiseId   = trim($franchiseId);
    $franchiseCode = trim($franchiseCode);

    $idCols = ['id', 'franchise_id', 'franchies_id', 'mobile_franchies_id', 'city_franchies_id', 'state_franchies_id', 'state_franchise_id'];
    $codeCols = ['franchise_code', 'franchies_id', 'city_franchise_code', 'city_franchies_id', 'mobile_franchise_code', 'mobile_franchies_id', 'state_franchise_code', 'state_franchies_code'];

    if ($franchiseId !== '' && ctype_digit($franchiseId)) {
        $id = (int)$franchiseId;
        foreach ($idCols as $col) {
            if (!colExists($conn, $table, $col)) continue;
            $stmt = $conn->prepare("SELECT * FROM `$table` WHERE `$col` = ? LIMIT 1");
            if (!$stmt) continue;
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if (!empty($row)) return $row;
        }
    }

    if ($franchiseCode !== '') {
        foreach ($codeCols as $col) {
            if (!colExists($conn, $table, $col)) continue;
            $stmt = $conn->prepare("SELECT * FROM `$table` WHERE `$col` = ? LIMIT 1");
            if (!$stmt) continue;
            $stmt->bind_param('s', $franchiseCode);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if (!empty($row)) return $row;
        }
    }

    return [];
}

function resolveStateFromValue(mysqli $conn, string $value): array
{
    $value = trim($value);
    if ($value === '' || !tableExists($conn, 'franchies_holder')) {
        return ['state_id' => 0, 'state_code' => ''];
    }

    if (ctype_digit($value)) {
        $stmt = $conn->prepare('SELECT * FROM franchies_holder WHERE id = ? LIMIT 1');
        if ($stmt) {
            $id = (int)$value;
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if (!empty($row)) {
                return ['state_id' => (int)$row['id'], 'state_code' => trim((string)($row['franchise_code'] ?? ''))];
            }
        }
    }

    if (colExists($conn, 'franchies_holder', 'franchise_code')) {
        $stmt = $conn->prepare('SELECT * FROM franchies_holder WHERE franchise_code = ? LIMIT 1');
        if ($stmt) {
            $stmt->bind_param('s', $value);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if (!empty($row)) {
                return ['state_id' => (int)$row['id'], 'state_code' => trim((string)($row['franchise_code'] ?? $value))];
            }
        }
    }

    foreach (['state', 'name', 'franchies_id', 'franchise_id'] as $col) {
        if (!colExists($conn, 'franchies_holder', $col)) continue;
        $stmt = $conn->prepare("SELECT * FROM franchies_holder WHERE LOWER(TRIM(`$col`)) = LOWER(TRIM(?)) LIMIT 1");
        if (!$stmt) continue;
        $stmt->bind_param('s', $value);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!empty($row)) {
            return ['state_id' => (int)$row['id'], 'state_code' => trim((string)($row['franchise_code'] ?? ''))];
        }
    }

    return ['state_id' => 0, 'state_code' => ''];
}

function resolveStateSupplierForCityOrder(mysqli $conn, string $cityFranchiseId, string $cityFranchiseCode): array
{
    $cityRow = fetchHolderRow($conn, 'city_franchies_holder', $cityFranchiseId, $cityFranchiseCode);
    if (empty($cityRow)) {
        return ['state_id' => 0, 'state_code' => ''];
    }

    $parentCols = [
        'state_franchies_id', 'state_franchise_id', 'state_franchies_holder_id',
        'state_holder_id', 'state_code', 'state_franchise_code',
        'under_state_id', 'parent_id', 'state_id', 'state'
    ];

    foreach ($parentCols as $col) {
        if (empty($cityRow[$col])) continue;
        $resolved = resolveStateFromValue($conn, trim((string)$cityRow[$col]));
        if ($resolved['state_id'] > 0 || $resolved['state_code'] !== '') {
            return $resolved;
        }
    }

    return ['state_id' => 0, 'state_code' => ''];
}

function getStateCityMobileOrderSoldQty(
    mysqli $conn,
    int    $franchiseId,
    string $franchiseCode,
    string $productName,
    bool   $debug = false
): int {
    if (!tableExists($conn, 'city_mobile_order')) {
        return 0;
    }

    $safeProd = $conn->real_escape_string($productName);
    $sql = "SELECT franchise_id, franchise_code, franchise_type, product_name, quantity, status FROM city_mobile_order
            WHERE LOWER(TRIM(product_name)) = LOWER(TRIM('$safeProd'))
              AND LOWER(TRIM(status)) = 'approved'";
    $res = $conn->query($sql);
    if (!$res) {
        if ($debug) echo "<p style='color:red'>SQL ERROR city_mobile_order: " . e($conn->error) . "</p>";
        return 0;
    }

    $total = 0;
    while ($row = $res->fetch_assoc()) {
        $frType = strtolower(trim((string)($row['franchise_type'] ?? '')));
        if ($frType !== 'city' && $frType !== 'mobile') {
            continue;
        }

        $supplier = resolveStateSupplierForCityOrder(
            $conn,
            trim((string)($row['franchise_id'] ?? '')),
            trim((string)($row['franchise_code'] ?? ''))
        );

        if ($supplier['state_id'] === $franchiseId || ($franchiseCode !== '' && $supplier['state_code'] === $franchiseCode)) {
            $total += max(0, (int)($row['quantity'] ?? 0));
        }
    }

    return $total;
}

function resolveCitySupplierForMobileOrder(mysqli $conn, string $mobileFranchiseId, string $mobileFranchiseCode): array
{
    $row = fetchHolderRow($conn, 'mobile_franchies_holder', $mobileFranchiseId, $mobileFranchiseCode);
    if (empty($row)) {
        return ['city_id' => 0, 'city_code' => ''];
    }

    $parentCols = [
        'city_franchies_id', 'city_franchise_id', 'city_franchies_holder_id',
        'city_holder_id', 'city_franchies_code', 'city_franchise_code',
        'city_code', 'city_id', 'franchies_id', 'franchise_id', 'franchise_code'
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
    if ($parentValue === '') {
        return ['city_id' => 0, 'city_code' => ''];
    }

    if (ctype_digit($parentValue) && tableExists($conn, 'city_franchies_holder')) {
        $stmt = $conn->prepare('SELECT * FROM city_franchies_holder WHERE id = ? LIMIT 1');
        if ($stmt) {
            $id = (int)$parentValue;
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $cityRow = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if (!empty($cityRow)) {
                return [
                    'city_id'   => (int)($cityRow['id'] ?? 0),
                    'city_code' => trim((string)($cityRow['city_franchies_id'] ?? $cityRow['city_franchise_id'] ?? $cityRow['franchies_id'] ?? $cityRow['franchise_id'] ?? $cityRow['franchise_code'] ?? '')),
                ];
            }
        }
    }

    if (tableExists($conn, 'city_franchies_holder')) {
        $cityRow = fetchHolderRow($conn, 'city_franchies_holder', $parentValue, $parentValue);
        if (!empty($cityRow)) {
            return [
                'city_id'   => (int)($cityRow['id'] ?? 0),
                'city_code' => trim((string)($cityRow['city_franchies_id'] ?? $cityRow['city_franchise_id'] ?? $cityRow['franchies_id'] ?? $cityRow['franchise_id'] ?? $cityRow['franchise_code'] ?? '')),
            ];
        }
    }

    return ['city_id' => 0, 'city_code' => ''];
}

function getCityMobileOrderSoldQty(
    mysqli $conn,
    int    $franchiseId,
    string $franchiseCode,
    string $productName,
    bool   $debug = false
): int {
    if ($franchiseId <= 0) return 0;
    if (!tableExists($conn, 'city_mobile_order')) return 0;
    if (!tableExists($conn, 'city_franchies_holder')) return 0;
    if (!tableExists($conn, 'mobile_franchies_holder')) return 0;

    $safeProd = $conn->real_escape_string($productName);
    $sql = "SELECT franchise_id, franchise_code, quantity FROM city_mobile_order
            WHERE LOWER(TRIM(product_name)) = LOWER(TRIM('$safeProd'))
              AND LOWER(TRIM(status)) = 'approved'
              AND LOWER(TRIM(franchise_type)) = 'mobile'";

    $res = $conn->query($sql);
    if (!$res) {
        if ($debug) echo "<p style='color:red'>SQL ERROR city_mobile_order city supply: " . e($conn->error) . "</p>";
        return 0;
    }

    $total = 0;
    while ($row = $res->fetch_assoc()) {
        $mobileId   = trim((string)($row['franchise_id'] ?? ''));
        $mobileCode = trim((string)($row['franchise_code'] ?? ''));
        if ($mobileId === '' && $mobileCode === '') {
            continue;
        }

        $citySupplier = resolveCitySupplierForMobileOrder($conn, $mobileId, $mobileCode);
        if ($citySupplier['city_id'] === $franchiseId || ($franchiseCode !== '' && $citySupplier['city_code'] === $franchiseCode)) {
            $total += max(0, (int)($row['quantity'] ?? 0));
        }
    }

    return $total;
}

function getMemberOrdersSoldQty(
    mysqli $conn,
    int    $franchiseId,
    string $franchiseCode,
    string $productName,
    bool   $debug = false
): int {
    if (!tableExists($conn, 'member_orders') || !tableExists($conn, 'member_order_items')) {
        return 0;
    }

    $safeProd = $conn->real_escape_string($productName);
    $safeCode = $conn->real_escape_string($franchiseCode);
    $idString = (string)$franchiseId;

    $where = [];
    if ($franchiseCode !== '') {
        $where[] = "mo.distributor_id = '$safeCode'";
    }
    if ($idString !== '') {
        $where[] = "mo.distributor_id = '$idString'";
    }
    if (empty($where)) {
        return 0;
    }

    $sql = "SELECT IFNULL(SUM(mi.quantity),0) AS total
            FROM member_orders mo
            INNER JOIN member_order_items mi ON mi.order_id = mo.id
            WHERE LOWER(TRIM(mi.product_name)) = LOWER(TRIM('$safeProd'))
              AND (" . implode(' OR ', $where) . ")";

    $res = $conn->query($sql);
    if (!$res) {
        if ($debug) echo "<p style='color:red'>SQL ERROR member_orders: " . e($conn->error) . "</p>";
        return 0;
    }

    $row = $res->fetch_assoc();
    return max(0, (int)($row['total'] ?? 0));
}

/* ═══════════════════════════════════════════════════════════════════════
 * getWalletStock
 * ═══════════════════════════════════════════════════════════════════════ */
function getWalletStock(
    mysqli $conn,
    string $franchiseType,
    int    $franchiseId,
    string $franchiseCode,
    string $productName
): int {
    $tables = [];
    if (tableExists($conn, 'franchise_stock'))        $tables[] = 'franchise_stock';
    if (tableExists($conn, 'franchise_stock_wallet')) $tables[] = 'franchise_stock_wallet';
    if (empty($tables)) return -1;

    $safeProd  = $conn->real_escape_string($productName);
    $safeType  = $conn->real_escape_string($franchiseType);
    $safeCode  = $conn->real_escape_string($franchiseCode);
    $idStr     = (string)$franchiseId;

    foreach ($tables as $table) {
        $hasFrCode = colExists($conn, $table, 'franchise_code') && $franchiseCode !== '';
        $hasFrType = colExists($conn, $table, 'franchise_type');
        $typeSql   = $hasFrType ? "AND franchise_type = '$safeType'" : '';

        /* Build WHERE clause with multiple identity matching */
        $whereParts = [];
        
        /* Match by numeric franchise_id */
        if ($franchiseId > 0) {
            $whereParts[] = "(franchise_id = $franchiseId)";
            $whereParts[] = "(franchise_id = '$idStr')";
        }
        
        /* Match by code-based franchise_code */
        if ($hasFrCode && $franchiseCode !== '') {
            $whereParts[] = "(franchise_code = '$safeCode')";
        }

        if (empty($whereParts)) continue;

        $whereCondition = '(' . implode(' OR ', $whereParts) . ')';
        $sql = "SELECT stock_qty FROM `$table`
                WHERE LOWER(TRIM(product_name)) = LOWER(TRIM('$safeProd'))
                  $typeSql
                  AND $whereCondition
                LIMIT 1";

        $res = $conn->query($sql);
        if ($res && ($row = $res->fetch_assoc()) !== null) {
            return max(0, (int)($row['stock_qty'] ?? 0));
        }
    }
    return -1;
}

/* ═══════════════════════════════════════════════════════════════════════
 * resolveStock
 *
 * PRIORITY (first match wins):
 *  P1  franchies_product_stock_request.product_balance  (running balance)
 *  P2  franchise_stock / franchise_stock_wallet          (total received)
 *  P3  requestTable (approved only)
 *  P4  city_mobile_order (approved only, city/mobile only)
 *  P5  zeros
 * ═══════════════════════════════════════════════════════════════════════ */
function resolveStock(
    mysqli $conn,
    int    $franchiseId,
    string $franchiseCode,
    string $franchiseType,
    string $requestTable,
    string $productName,
    bool   $debug = false
): array {

    $soldQty = getSoldQty($conn, $franchiseId, $franchiseCode, $productName, $franchiseType, $debug);

    /* ── P1: running balance ──────────────────────────────────────────── */
    if (
        tableExists($conn, 'franchies_product_stock_request') &&
        colExists($conn, 'franchies_product_stock_request', 'product_balance')
    ) {
        $safeProd = $conn->real_escape_string($productName);

        /*
         * FIX-STATE-5: Try both franchise_id (numeric) AND franchies_id
         * (code-based) column, whichever exists in the table.
         */
        $identParts = [];
        if (colExists($conn, 'franchies_product_stock_request', 'franchise_id') && $franchiseId > 0)
            $identParts[] = "franchise_id = $franchiseId";
        if (colExists($conn, 'franchies_product_stock_request', 'franchies_id') && $franchiseCode !== '') {
            $sc = $conn->real_escape_string($franchiseCode);
            $identParts[] = "franchies_id = '$sc'";
        }
        if (colExists($conn, 'franchies_product_stock_request', 'franchise_code') && $franchiseCode !== '') {
            $sc = $conn->real_escape_string($franchiseCode);
            $identParts[] = "franchise_code = '$sc'";
        }

        if (!empty($identParts)) {
            $identSql = implode(' OR ', $identParts);
            $res = $conn->query("
    SELECT
        COALESCE(product_balance,0) AS bal,
        COALESCE(product_used,0) AS used
    FROM franchies_product_stock_request
    WHERE ($identSql)
      AND LOWER(TRIM(product_name)) = LOWER(TRIM('$safeProd'))
    ORDER BY id DESC
    LIMIT 1
");
            if ($res && ($r = $res->fetch_assoc()) !== null) {
                $balance = max(0, (int)$r['bal']);
$used    = max(0, (int)$r['used']);

return [
    'available_stock' => $balance,
    'sold_stock'      => $used,
    'total_stock'     => $balance + $used,
];
            }
        }
    }

    /* ── P2: wallet ───────────────────────────────────────────────────── */
    $walletQty = getWalletStock($conn, $franchiseType, $franchiseId, $franchiseCode, $productName);
    if ($walletQty >= 0) {
        return [
            'available_stock' => max(0, $walletQty - $soldQty),
            'sold_stock'      => $soldQty,
            'total_stock'     => $walletQty,
        ];
    }

    /* ── P3: request table (approved) ────────────────────────────────── */
    $approvedQty = getApprovedQty($conn, $franchiseId, $franchiseCode, $requestTable, $productName);
    if ($approvedQty > 0) {
        return [
            'available_stock' => max(0, $approvedQty - $soldQty),
            'sold_stock'      => $soldQty,
            'total_stock'     => $approvedQty,
        ];
    }

    /* ── P4: city_mobile_order (approved) ────────────────────────────── */
    $orderQty = getCityMobileApprovedQty($conn, $franchiseId, $franchiseCode, $franchiseType, $productName);
    if ($orderQty > 0) {
        return [
            'available_stock' => max(0, $orderQty - $soldQty),
            'sold_stock'      => $soldQty,
            'total_stock'     => $orderQty,
        ];
    }

    /* ── P5: nothing ─────────────────────────────────────────────────── */
    return [
        'available_stock' => 0,
        'sold_stock'      => $soldQty,
        'total_stock'     => $soldQty,
    ];
}

/* ═══════════════════════════════════════════════════════════════════════
 * DB SETUP
 * ═══════════════════════════════════════════════════════════════════════ */
$conn->query("
    CREATE TABLE IF NOT EXISTS franchise_order_sale (
        id             INT AUTO_INCREMENT PRIMARY KEY,
        distributor_id VARCHAR(100)  DEFAULT NULL,
        member_id      VARCHAR(100)  DEFAULT NULL,
        member_name    VARCHAR(255)  DEFAULT NULL,
        product_name   VARCHAR(255)  DEFAULT NULL,
        quantity       INT           DEFAULT 0,
        price          DECIMAL(10,2) DEFAULT 0.00,
        total_amount   DECIMAL(10,2) DEFAULT 0.00,
        created_at     TIMESTAMP     DEFAULT CURRENT_TIMESTAMP
    )
");
if (!colExists($conn, 'franchise_order_sale', 'distributor_id'))
    $conn->query("ALTER TABLE franchise_order_sale ADD distributor_id VARCHAR(100) DEFAULT NULL AFTER id");

/* ═══════════════════════════════════════════════════════════════════════
 * LOGIN CHECK
 * ═══════════════════════════════════════════════════════════════════════ */
if (empty($_SESSION['franchise_id']) || empty($_SESSION['franchise_type'])) {
    header("Location: index.php");
    exit;
}

/* ═══════════════════════════════════════════════════════════════════════
 * DETECT FRANCHISE TYPE & LOAD RECORD
 * ═══════════════════════════════════════════════════════════════════════ */
$typeRaw  = strtolower(trim((string)($_SESSION['franchise_type'] ?? '')));
$isMobile = strpos($typeRaw, 'mobile') !== false;
$isCity   = strpos($typeRaw, 'city') !== false;

/* ── Set session identifiers based on franchise type ────────────────── */
$sessionId   = (int)($_SESSION['franchise_id'] ?? 0);
$sessionCode = trim($_SESSION['franchise_code'] ?? '');

$franchiseId   = 0;
$franchiseCode = '';
$franchiseName = '';

/* ── Mobile ─────────────────────────────────────────────────────────── */
if ($isMobile) {
    $row = null;
    if ($sessionId > 0 && tableExists($conn,'mobile_franchies_holder')) {
        $res = $conn->query("SELECT * FROM mobile_franchies_holder WHERE id=$sessionId LIMIT 1");
        if ($res) $row = $res->fetch_assoc();
    }
    if (!$row && $sessionCode !== '' && tableExists($conn,'mobile_franchies_holder')) {
        foreach (['mobile_franchies_id','mobile_franchise_id','franchies_id','franchise_id'] as $c) {
            if (!colExists($conn,'mobile_franchies_holder',$c)) continue;
            $sc  = $conn->real_escape_string($sessionCode);
            $res = $conn->query("SELECT * FROM mobile_franchies_holder WHERE `$c`='$sc' LIMIT 1");
            if ($res && ($row = $res->fetch_assoc())) break;
        }
    }
    if ($row) {
        $franchiseId   = (int)$row['id'];
        $franchiseCode = $row['mobile_franchies_id'] ?? $row['mobile_franchise_id'] ?? $row['franchies_id'] ?? $row['franchise_id'] ?? $sessionCode;
        $franchiseName = $row['owner_name'] ?? $row['full_name'] ?? $row['name'] ?? '';
    }
    $requestTable = 'mobile_franchies_product_request';

/* ── City ───────────────────────────────────────────────────────────── */
} elseif ($isCity) {
    $row = null;
    if ($sessionId > 0 && tableExists($conn,'city_franchies_holder')) {
        $res = $conn->query("SELECT * FROM city_franchies_holder WHERE id=$sessionId LIMIT 1");
        if ($res) $row = $res->fetch_assoc();
    }
    if (!$row && $sessionCode !== '' && tableExists($conn,'city_franchies_holder')) {
        foreach (['franchies_id', 'city_franchies_id', 'city_franchise_code', 'franchise_code'] as $c) {
            if (!colExists($conn,'city_franchies_holder',$c)) continue;
            $sc  = $conn->real_escape_string($sessionCode);
            $res = $conn->query("SELECT * FROM city_franchies_holder WHERE `$c`='$sc' LIMIT 1");
            if ($res && ($row = $res->fetch_assoc())) break;
        }
    }
    if ($row) {
        $franchiseId   = (int)($row['id'] ?? 0);
        $franchiseCode = $row['franchies_id'] ?? $row['city_franchies_id'] ?? $row['city_franchise_code'] ?? $row['franchise_code'] ?? $sessionCode;
        $franchiseName = $row['name'] ?? $row['full_name'] ?? '';
    }
    $requestTable = 'city_franchies_product_request';

/* ── State ──────────────────────────────────────────────────────────── */
} else {
    $row = null;

    /*
     * FIX-STATE-1: Try session franchise_id first (numeric),
     * then fall back to franchies_id (string code).
     */
    if ($sessionId > 0 && tableExists($conn,'franchies_holder')) {
        $res = $conn->query("SELECT * FROM franchies_holder WHERE id=$sessionId LIMIT 1");
        if ($res) $row = $res->fetch_assoc();
    }
    if (!$row && $sessionCode !== '' && tableExists($conn,'franchies_holder')) {
        /* Try multiple code columns */
        foreach (['franchies_id','franchise_id','franchise_code','distributor_id'] as $c) {
            if (!colExists($conn,'franchies_holder',$c)) continue;
            $sc  = $conn->real_escape_string($sessionCode);
            $res = $conn->query("SELECT * FROM franchies_holder WHERE `$c`='$sc' LIMIT 1");
            if ($res && ($row = $res->fetch_assoc())) break;
        }
    }

    if ($row) {
        $franchiseId = (int)($row['id'] ?? 0);

        /*
         * FIX-STATE-1 (cont): Resolve code from multiple possible columns.
         * Order: franchies_id → franchise_id → franchise_code → distributor_id
         */
        $franchiseCode = '';
        foreach (['franchies_id','franchise_id','franchise_code','distributor_id'] as $c) {
            if (!empty($row[$c])) { $franchiseCode = (string)$row[$c]; break; }
        }

        $franchiseName = $row['full_name'] ?? $row['owner_name'] ?? $row['name'] ?? '';
    }

    $requestTable = 'franchies_product_request';
}

$franchiseType = $isMobile ? 'mobile' : ($isCity ? 'city' : 'state');

if ($franchiseId <= 0) {
    die("Franchise login data not found. Please logout and login again.");
}

/* ═══════════════════════════════════════════════════════════════════════
 * ENSURE TABLES
 * ═══════════════════════════════════════════════════════════════════════ */
$conn->query("CREATE TABLE IF NOT EXISTS franchies_member_product (
    id INT AUTO_INCREMENT PRIMARY KEY, product_name VARCHAR(255) NOT NULL,
    product_price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    status VARCHAR(50) DEFAULT 'Active', created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");

$conn->query("CREATE TABLE IF NOT EXISTS franchies_product_request (
    id INT AUTO_INCREMENT PRIMARY KEY, order_id VARCHAR(100) DEFAULT NULL,
    franchise_id INT DEFAULT 0, franchise_code VARCHAR(50) DEFAULT NULL,
    category VARCHAR(255) DEFAULT NULL, product_name VARCHAR(255) NOT NULL,
    offer_price DECIMAL(10,2) DEFAULT 0.00, quantity INT DEFAULT 0,
    total_amount DECIMAL(10,2) DEFAULT 0.00, status VARCHAR(50) DEFAULT 'Pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");

$conn->query("CREATE TABLE IF NOT EXISTS city_franchies_product_request (
    id INT AUTO_INCREMENT PRIMARY KEY, order_id VARCHAR(100) DEFAULT NULL,
    franchise_id INT DEFAULT 0, franchise_code VARCHAR(50) DEFAULT NULL,
    category VARCHAR(255) DEFAULT NULL, product_name VARCHAR(255) NOT NULL,
    offer_price DECIMAL(10,2) DEFAULT 0.00, quantity INT DEFAULT 0,
    total_amount DECIMAL(10,2) DEFAULT 0.00, status VARCHAR(50) DEFAULT 'Pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");

if (!colExists($conn,'franchies_product_request','franchise_code'))
    $conn->query("ALTER TABLE franchies_product_request ADD franchise_code VARCHAR(50) DEFAULT NULL AFTER franchise_id");
if (!colExists($conn,'city_franchies_product_request','franchise_code'))
    $conn->query("ALTER TABLE city_franchies_product_request ADD franchise_code VARCHAR(50) DEFAULT NULL AFTER franchise_id");

/* ═══════════════════════════════════════════════════════════════════════
 * FETCH ACTIVE PRODUCTS
 * ═══════════════════════════════════════════════════════════════════════ */
$products = [];

if (tableExists($conn,'franchies_member_product')) {
    $q = $conn->query("
        SELECT id, product_name, product_price FROM franchies_member_product
        WHERE product_name IS NOT NULL AND product_name != ''
          AND (status IS NULL OR status='' OR LOWER(TRIM(status)) IN ('active','approved','approve','1','yes'))
        ORDER BY id ASC
    ");
    while ($q && $r = $q->fetch_assoc()) {
        $key = strtolower(trim($r['product_name']));
        $products[$key] = ['id'=>(int)$r['id'],'product_name'=>$r['product_name'],'product_price'=>(float)$r['product_price']];
    }
}

if (tableExists($conn,'franchies_add_product')) {
    $idCol    = firstCol($conn,'franchies_add_product',['id']);
    $nameCol  = firstCol($conn,'franchies_add_product',['product_name','name','title']);
    $priceCol = firstCol($conn,'franchies_add_product',['product_price','price','offer_price','amount','mrp']);
    $statCol  = firstCol($conn,'franchies_add_product',['status']);
    if ($idCol && $nameCol && $priceCol) {
        $sql = "SELECT `$idCol` AS id, `$nameCol` AS product_name, `$priceCol` AS product_price FROM franchies_add_product WHERE `$nameCol` IS NOT NULL AND `$nameCol`!=''";
        if ($statCol) $sql .= " AND (`$statCol` IS NULL OR `$statCol`='' OR LOWER(TRIM(`$statCol`)) IN ('active','approved','approve','1','yes'))";
        $sql .= " ORDER BY `$idCol` ASC";
        $q = $conn->query($sql);
        while ($q && $r = $q->fetch_assoc()) {
            $key = strtolower(trim($r['product_name']));
            if (!isset($products[$key]))
                $products[$key] = ['id'=>(int)$r['id'],'product_name'=>$r['product_name'],'product_price'=>(float)$r['product_price']];
        }
    }
}

/* ═══════════════════════════════════════════════════════════════════════
 * BUILD STOCK DATA
 * ═══════════════════════════════════════════════════════════════════════ */
$stockData      = [];
$totalStock     = 0;
$totalSold      = 0;
$totalAvailable = 0;

foreach ($products as $product) {
    $result = resolveStock(
        $conn, $franchiseId, $franchiseCode,
        $franchiseType, $requestTable, $product['product_name'],
        $debugMode
    );
    $row = [
        'id'              => $product['id'],
        'product_name'    => $product['product_name'],
        'product_price'   => $product['product_price'],
        'total_stock'     => (int)$result['total_stock'],
        'sold_stock'      => (int)$result['sold_stock'],
        'available_stock' => (int)$result['available_stock'],
    ];
    $stockData[]    = $row;
    $totalStock     += $row['total_stock'];
    $totalSold      += $row['sold_stock'];
    $totalAvailable += $row['available_stock'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Product Stock Report</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body class="bg-[#f4f4f4] font-sans overflow-x-hidden">

<?php include __DIR__ . '/includes/sidebar.php'; ?>

<div class="ml-64 min-h-screen">
<?php include __DIR__ . '/includes/navbar.php'; ?>

<main class="p-5">
<div class="max-w-[1600px] mx-auto">

<?php if ($debugMode): ?>
<div style="background:#111;color:#eee;padding:12px;border-radius:8px;margin-bottom:16px;font-family:monospace;font-size:13px;">
    <b style="color:#ffbe2e">DEBUG INFO</b><br>
    Franchise ID: <b><?= $franchiseId ?></b> | Code: <b><?= e($franchiseCode) ?></b> |
    Type: <b><?= $franchiseType ?></b> | Request Table: <b><?= $requestTable ?></b><br>
    Session franchise_id: <?= e($_SESSION['franchise_id'] ?? '-') ?> |
    Session franchies_id: <?= e($_SESSION['franchies_id'] ?? '-') ?>
</div>
<?php endif; ?>

    <div class="bg-[#0f2d56] rounded-t-[20px] px-6 py-5 flex items-center gap-3">
        <i data-lucide="package-search" class="w-6 h-6 text-[#ffbe2e]"></i>
        <h2 class="text-[#ffbe2e] text-[28px] font-bold">PRODUCT STOCK REPORT</h2>
    </div>

    <div class="bg-white px-6 py-4 border-x border-[#ddd]">
        <p class="text-[16px]">
            Franchise: <b><?= e($franchiseName) ?></b>
            &nbsp;·&nbsp; ID: <b><?= e($franchiseCode) ?></b>
            &nbsp;·&nbsp; Type: <b><?= $isCity ? 'City Franchise' : ($isMobile ? 'Mobile Franchise' : 'State Franchise') ?></b>
        </p>
    </div>

    <div class="bg-white border border-[#ddd] rounded-b-[20px] overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full">
                <thead class="bg-[#f5f2eb]">
                    <tr class="border-b border-[#ddd]">
                        <th class="px-5 py-5 text-left   text-[#c7921e] text-[17px] font-bold">#</th>
                        <th class="px-5 py-5 text-left   text-[#c7921e] text-[17px] font-bold">PRODUCT NAME</th>
                        <th class="px-5 py-5 text-left   text-[#c7921e] text-[17px] font-bold">PRICE</th>
                        <th class="px-5 py-5 text-center text-[#c7921e] text-[17px] font-bold">TOTAL STOCK</th>
                        <th class="px-5 py-5 text-center text-[#c7921e] text-[17px] font-bold">SOLD STOCK</th>
                        <th class="px-5 py-5 text-center text-[#c7921e] text-[17px] font-bold">AVAILABLE STOCK</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (count($stockData) > 0): ?>
                    <?php foreach ($stockData as $index => $item): ?>
                    <tr class="border-b border-[#eee] hover:bg-[#fafafa]">
                        <td class="px-5 py-5">
                            <div class="w-[38px] h-[38px] rounded-[10px] bg-[#ece7df] flex items-center justify-center text-[16px] font-bold text-[#6d675f]">
                                <?= $index + 1 ?>
                            </div>
                        </td>
                        <td class="px-5 py-5 text-[18px] font-semibold text-[#222] break-words"><?= e($item['product_name']) ?></td>
                        <td class="px-5 py-5 text-[18px] font-bold text-[#222]">₹<?= number_format((float)$item['product_price'], 2) ?></td>
                        <td class="px-5 py-5 text-center">
                            <span class="inline-flex min-w-[70px] justify-center rounded-[10px] bg-blue-100 px-4 py-2 text-[17px] font-bold text-blue-700">
                                <?= $item['total_stock'] ?>
                            </span>
                        </td>
                        <td class="px-5 py-5 text-center">
                            <span class="inline-flex min-w-[70px] justify-center rounded-[10px] bg-red-100 px-4 py-2 text-[17px] font-bold text-red-700">
                                <?= $item['sold_stock'] ?>
                            </span>
                        </td>
                        <td class="px-5 py-5 text-center">
                            <span class="inline-flex min-w-[70px] justify-center rounded-[10px] bg-green-100 px-4 py-2 text-[17px] font-bold text-green-700">
                                <?= $item['available_stock'] ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <tr class="bg-[#f5f2eb] border-t-2 border-[#d1a14a]">
                        <td colspan="3" class="px-5 py-5 text-right text-[20px] font-bold text-[#0f2d56]">TOTAL</td>
                        <td class="px-5 py-5 text-center text-[20px] font-bold text-blue-700"><?= $totalStock ?></td>
                        <td class="px-5 py-5 text-center text-[20px] font-bold text-red-700"><?= $totalSold ?></td>
                        <td class="px-5 py-5 text-center text-[20px] font-bold text-green-700"><?= $totalAvailable ?></td>
                    </tr>
                <?php else: ?>
                    <tr>
                        <td colspan="6" class="px-5 py-10 text-center text-gray-500 text-[18px]">No product stock found.</td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>
</main>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    if (typeof lucide !== "undefined") lucide.createIcons();
});
</script>
</body>
</html>