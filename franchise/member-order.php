<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include __DIR__ . '/config/db.php';

if (!isset($conn) || !($conn instanceof mysqli)) {
    die("Database connection failed.");
}

function e($str) {
    return htmlspecialchars((string)($str ?? ''), ENT_QUOTES, 'UTF-8');
}

function tableExists($conn, $table) {
    $table = $conn->real_escape_string($table);
    $res = $conn->query("SHOW TABLES LIKE '$table'");
    return ($res && $res->num_rows > 0);
}

function colExists($conn, $table, $col) {
    $table = $conn->real_escape_string($table);
    $col = $conn->real_escape_string($col);
    $res = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$col'");
    return ($res && $res->num_rows > 0);
}

function firstCol($conn, $table, $cols) {
    foreach ($cols as $c) {
        if (colExists($conn, $table, $c)) return $c;
    }
    return null;
}

// ─── AJAX: member lookup ──────────────────────────────────────────────────────
if (isset($_GET['lookup_member'])) {
    header('Content-Type: application/json');
    $mid = trim($_GET['lookup_member'] ?? '');
    if ($mid === '') { echo json_encode(['found' => false]); exit; }
    $stmt = $conn->prepare("SELECT customer_id, full_name, mobile, email FROM signup_users WHERE customer_id = ? LIMIT 1");
    $stmt->bind_param('s', $mid);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($row) {
        echo json_encode(['found' => true, 'name' => $row['full_name'], 'mobile' => $row['mobile'], 'email' => $row['email'], 'id' => $row['customer_id']]);
    } else {
        echo json_encode(['found' => false]);
    }
    exit;
}
// ─────────────────────────────────────────────────────────────────────────────

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
        'mobile' => ['table' => 'mobile_franchies_holder', 'codeCol' => 'mobile_franchies_id', 'nameCol' => 'owner_name'],
        'city'   => ['table' => 'city_franchies_holder',   'codeCol' => 'franchies_id',        'nameCol' => 'full_name'],
        'state'  => ['table' => 'franchies_holder',        'codeCol' => 'franchise_code',      'nameCol' => 'name'],
    ];

    foreach ($order as $type) {
        $map = $tableMap[$type];
        $table = $map['table']; $codeCol = $map['codeCol']; $nameCol = $map['nameCol'];
        if (!tableExists($conn, $table)) continue;
        foreach ($values as $val) {
            if (is_numeric($val)) {
                $id = (int)$val;
                $stmt = $conn->prepare("SELECT * FROM `$table` WHERE id = ? LIMIT 1");
                if ($stmt) { $stmt->bind_param('i', $id); $stmt->execute(); $row = $stmt->get_result()->fetch_assoc(); $stmt->close(); if ($row) return ['type' => $type, 'id' => (int)$row['id'], 'code' => (string)($row[$codeCol] ?? $val), 'name' => (string)($row[$nameCol] ?? 'Franchise')]; }
            }
            $stmt = $conn->prepare("SELECT * FROM `$table` WHERE `$codeCol` = ? LIMIT 1");
            if ($stmt) { $stmt->bind_param('s', $val); $stmt->execute(); $row = $stmt->get_result()->fetch_assoc(); $stmt->close(); if ($row) return ['type' => $type, 'id' => (int)$row['id'], 'code' => (string)($row[$codeCol] ?? $val), 'name' => (string)($row[$nameCol] ?? 'Franchise')]; }
        }
    }
    return null;
}

function resolveFranchiseType(): string {
    $typeRaw = strtolower(trim((string)($_SESSION['franchise_type'] ?? $_SESSION['franchies_type'] ?? '')));
    if (str_contains($typeRaw, 'mobile') || !empty($_SESSION['mobile_franchise_id']) || !empty($_SESSION['mobile_franchies_id'])) return 'mobile';
    if (str_contains($typeRaw, 'city') || !empty($_SESSION['city_franchies_holder_id'])) return 'city';
    return 'state';
}

function fetchAvailableStockMap(mysqli $conn, int $franchiseId): array {
    $stockMap = [];
    if ($franchiseId <= 0) return $stockMap;
    if (!tableExists($conn, 'franchies_product_stock_request') || !colExists($conn, 'franchies_product_stock_request', 'product_balance')) {
        return $stockMap;
    }
    $stmt = $conn->prepare("SELECT LOWER(TRIM(product_name)) AS product_key, COALESCE(SUM(product_balance), 0) AS available_stock FROM franchies_product_stock_request WHERE franchise_id = ? GROUP BY LOWER(TRIM(product_name))");
    if (!$stmt) return $stockMap;
    $stmt->bind_param('i', $franchiseId);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $productKey = (string)($row['product_key'] ?? '');
        if ($productKey === '') continue;
        $stockMap[$productKey] = max(0, (int)($row['available_stock'] ?? 0));
    }
    $stmt->close();
    return $stockMap;
}

function deductFranchiseProductStockForOrder(mysqli $conn, int $franchiseId, string $productName, int $quantity): array {
    if ($franchiseId <= 0 || $quantity <= 0) return ['ok' => false, 'stock_before' => 0, 'stock_after' => 0, 'message' => 'Invalid deduction request.'];
    if (!tableExists($conn, 'franchies_product_stock_request') || !colExists($conn, 'franchies_product_stock_request', 'product_balance')) {
        return ['ok' => false, 'stock_before' => 0, 'stock_after' => 0, 'message' => 'Product stock table unavailable.'];
    }

    $selectSql = "SELECT id, product_balance FROM franchies_product_stock_request WHERE franchise_id = ? AND LOWER(TRIM(product_name)) = LOWER(TRIM(?)) AND product_balance > 0 ORDER BY id ASC FOR UPDATE";
    $stockRows = [];
    $selectStmt = $conn->prepare($selectSql);
    if ($selectStmt) {
        $selectStmt->bind_param('is', $franchiseId, $productName);
        $selectStmt->execute();
        $res = $selectStmt->get_result();
        while ($r = $res->fetch_assoc()) $stockRows[] = $r;
        $selectStmt->close();
    }

    if (empty($stockRows)) {
        return ['ok' => false, 'stock_before' => 0, 'stock_after' => 0, 'message' => 'Out Of Stock'];
    }

    $stockBeforeTotal = 0;
    foreach ($stockRows as $r) $stockBeforeTotal += (int)($r['product_balance'] ?? 0);

    if ($stockBeforeTotal < $quantity) {
        return ['ok' => false, 'stock_before' => $stockBeforeTotal, 'stock_after' => $stockBeforeTotal, 'message' => 'Out Of Stock'];
    }

    $qtyRemaining = $quantity;
    $updateStmt = $conn->prepare("UPDATE franchies_product_stock_request SET product_balance = product_balance - ?, product_used = COALESCE(product_used,0) + ? WHERE id = ? AND product_balance >= ?");
    if (!$updateStmt) return ['ok' => false, 'stock_before' => $stockBeforeTotal, 'stock_after' => $stockBeforeTotal, 'message' => 'Unable to prepare stock update.'];

    foreach ($stockRows as $r) {
        if ($qtyRemaining <= 0) break;
        $id = (int)($r['id'] ?? 0);
        $bal = (int)($r['product_balance'] ?? 0);
        if ($bal <= 0) continue;
        $take = $bal >= $qtyRemaining ? $qtyRemaining : $bal;
        $updateStmt->bind_param('iiii', $take, $take, $id, $take);
        $updateStmt->execute();
        if ($updateStmt->affected_rows <= 0) {
            $updateStmt->close();
            return ['ok' => false, 'stock_before' => $stockBeforeTotal, 'stock_after' => $stockBeforeTotal, 'message' => 'Product stock not available (concurrent update).'];
        }
        $qtyRemaining -= $take;
    }
    $updateStmt->close();

    if ($qtyRemaining > 0) {
        return ['ok' => false, 'stock_before' => $stockBeforeTotal, 'stock_after' => $stockBeforeTotal - ($quantity - $qtyRemaining), 'message' => 'Out Of Stock'];
    }

    return ['ok' => true, 'stock_before' => $stockBeforeTotal, 'stock_after' => $stockBeforeTotal - $quantity, 'message' => 'Stock updated.'];
}

// ============================================================
// REPURCHASE MATCHING BONUS
// ============================================================
function processRepurchaseMatchingBonus($memberId, $bv, $conn) {
    $stmtSelf = $conn->prepare("UPDATE signup_users SET self_bv = self_bv + ? WHERE customer_id = ?");
    $stmtSelf->bind_param("ds", $bv, $memberId);
    $stmtSelf->execute();

    $stmtWeak = $conn->prepare("SELECT repurchase_left_team_bv, repurchase_right_team_bv FROM signup_users WHERE customer_id = ? LIMIT 1");
    $stmtWeak->bind_param("s", $memberId);
    $stmtWeak->execute();
    $weakData = $stmtWeak->get_result()->fetch_assoc();

    if ($weakData) {
        $leftBV  = (float)$weakData['repurchase_left_team_bv'];
        $rightBV = (float)$weakData['repurchase_right_team_bv'];
        if ($leftBV < $rightBV) {
            $stmtAdd = $conn->prepare("UPDATE signup_users SET repurchase_left_team_bv = repurchase_left_team_bv + ? WHERE customer_id = ?");
        } else {
            $stmtAdd = $conn->prepare("UPDATE signup_users SET repurchase_right_team_bv = repurchase_right_team_bv + ? WHERE customer_id = ?");
        }
        $stmtAdd->bind_param("ds", $bv, $memberId);
        $stmtAdd->execute();
        calculateRepurchaseMatching($memberId, $memberId, $conn);
    }

    $currentMember = $memberId;
    while (true) {
        $stmt = $conn->prepare("SELECT placement_id, position FROM signup_users WHERE customer_id = ? LIMIT 1");
        $stmt->bind_param("s", $currentMember);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        if (!$user || empty($user['placement_id'])) break;
        $parentId = $user['placement_id'];
        $side = strtoupper($user['position']);
        if ($side == 'L') {
            $stmtUpdate = $conn->prepare("UPDATE signup_users SET repurchase_left_team_bv = repurchase_left_team_bv + ?, repurchase_total_left_bv = repurchase_total_left_bv + ? WHERE customer_id = ?");
        } else {
            $stmtUpdate = $conn->prepare("UPDATE signup_users SET repurchase_right_team_bv = repurchase_right_team_bv + ?, repurchase_total_right_bv = repurchase_total_right_bv + ? WHERE customer_id = ?");
        }
        $stmtUpdate->bind_param("dds", $bv, $bv, $parentId);
        $stmtUpdate->execute();
        calculateRepurchaseMatching($parentId, $memberId, $conn);
        $currentMember = $parentId;
    }
}

function calculateRepurchaseMatching($customerId, $fromMember, $conn) {
    $stmt = $conn->prepare("SELECT repurchase_left_team_bv, repurchase_right_team_bv, repurchase_first_pair_completed FROM signup_users WHERE customer_id = ? LIMIT 1");
    $stmt->bind_param("s", $customerId);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    if (!$user) return;
    $left = (float)$user['repurchase_left_team_bv'];
    $right = (float)$user['repurchase_right_team_bv'];
    $income = 0; $matching_bv = 0; $deduct_left = 0; $deduct_right = 0;
    if (!$user['repurchase_first_pair_completed']) {
        if (($left >= 200 && $right >= 100) || ($left >= 100 && $right >= 200)) {
            $matching_bv = min($left, $right);
            $income = $matching_bv; $deduct_left = $matching_bv; $deduct_right = $matching_bv;
            $stmtFlag = $conn->prepare("UPDATE signup_users SET repurchase_first_pair_completed = 1 WHERE customer_id = ?");
            $stmtFlag->bind_param("s", $customerId);
            $stmtFlag->execute();
        }
    } else {
        $matching_bv = min($left, $right);
        $income = $matching_bv; $deduct_left = $matching_bv; $deduct_right = $matching_bv;
    }
    if ($income <= 0) return;
    $today = date('Y-m-d');
    $capStmt = $conn->prepare("SELECT COALESCE(SUM(income_amount), 0) AS earned_today FROM repurchase_matching_income_log WHERE customer_id = ? AND DATE(created_at) = ?");
    $capStmt->bind_param("ss", $customerId, $today);
    $capStmt->execute();
    $earned_today = (float)$capStmt->get_result()->fetch_assoc()['earned_today'];
    $remaining_cap = 4000 - $earned_today;
    if ($remaining_cap <= 0) return;
    if ($income > $remaining_cap) $income = $remaining_cap;
    $carry_left = $left - $deduct_left;
    $carry_right = $right - $deduct_right;
    $stmtUpdate = $conn->prepare("UPDATE signup_users SET repurchase_left_team_bv = repurchase_left_team_bv - ?, repurchase_right_team_bv = repurchase_right_team_bv - ?, repurchase_matching_income = repurchase_matching_income + ?, wallet_balance = wallet_balance + ? WHERE customer_id = ?");
    $stmtUpdate->bind_param("dddds", $deduct_left, $deduct_right, $income, $income, $customerId);
    $stmtUpdate->execute();
    $stmtLog = $conn->prepare("INSERT INTO repurchase_matching_income_log (customer_id, from_member, left_bv, right_bv, carry_left_bv, carry_right_bv, matching_bv, income_amount, status, income_date, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Pending', CURDATE(), NOW())");
    $stmtLog->bind_param("ssdddddd", $customerId, $fromMember, $left, $right, $carry_left, $carry_right, $matching_bv, $income);
    $stmtLog->execute();
}
// ============================================================

if (isset($_GET['view_invoice']) && $_GET['view_invoice'] !== '') {
    header('Location: member_order_list.php?view_invoice=' . urlencode((string)$_GET['view_invoice']));
    exit;
}

$login = resolveFranchiseLogin($conn);
if (!$login) { header('Location: index.php'); exit; }

$franchiseType = $login['type'];
$franchiseId   = (int)$login['id'];
$franchiseCode = (string)$login['code'];
$userName      = (string)$login['name'];

$conn->query("CREATE TABLE IF NOT EXISTS franchies_member_product (id INT AUTO_INCREMENT PRIMARY KEY, product_name VARCHAR(255) NOT NULL, product_price DECIMAL(10,2) NOT NULL DEFAULT 0.00, status VARCHAR(50) DEFAULT 'Active', created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");
$conn->query("CREATE TABLE IF NOT EXISTS member_orders (id INT AUTO_INCREMENT PRIMARY KEY, distributor_id VARCHAR(100) DEFAULT NULL, member_id VARCHAR(100) NOT NULL, member_name VARCHAR(255) DEFAULT NULL, total_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00, payment_method VARCHAR(100) DEFAULT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");
if (!colExists($conn, 'member_orders', 'distributor_id')) $conn->query("ALTER TABLE member_orders ADD distributor_id VARCHAR(100) DEFAULT NULL AFTER id");
if (!colExists($conn, 'member_orders', 'payment_method')) $conn->query("ALTER TABLE member_orders ADD payment_method VARCHAR(100) DEFAULT NULL AFTER total_amount");
$conn->query("CREATE TABLE IF NOT EXISTS member_order_items (id INT AUTO_INCREMENT PRIMARY KEY, order_id INT NOT NULL, product_id INT NOT NULL, product_name VARCHAR(255) NOT NULL, product_price DECIMAL(10,2) NOT NULL DEFAULT 0.00, quantity INT NOT NULL DEFAULT 0, amount DECIMAL(10,2) NOT NULL DEFAULT 0.00, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");
$conn->query("CREATE TABLE IF NOT EXISTS franchise_order_sale (id INT AUTO_INCREMENT PRIMARY KEY, distributor_id VARCHAR(100) DEFAULT NULL, member_id VARCHAR(100) NOT NULL, member_name VARCHAR(255) DEFAULT NULL, product_name VARCHAR(255) DEFAULT NULL, quantity INT DEFAULT 0, price DECIMAL(10,2) DEFAULT 0.00, total_amount DECIMAL(10,2) DEFAULT 0.00, payment_method VARCHAR(100) DEFAULT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");
if (!colExists($conn, 'franchise_order_sale', 'payment_method')) $conn->query("ALTER TABLE franchise_order_sale ADD payment_method VARCHAR(100) DEFAULT NULL AFTER total_amount");

$products = [];
if (tableExists($conn, 'franchies_member_product')) {
    $q = $conn->query("SELECT id, product_name, product_price FROM franchies_member_product WHERE product_name IS NOT NULL AND product_name != '' AND (status IS NULL OR status = '' OR LOWER(TRIM(status)) IN ('active','approved','approve','1','yes')) ORDER BY id ASC");
    while ($q && $r = $q->fetch_assoc()) $products[] = ['key' => 'm_' . $r['id'], 'id' => (int)$r['id'], 'product_name' => $r['product_name'], 'product_price' => (float)$r['product_price'], 'product_bv' => 0];
}
if (tableExists($conn, 'franchies_add_product')) {
    $idCol = firstCol($conn, 'franchies_add_product', ['id']);
    $nameCol = firstCol($conn, 'franchies_add_product', ['product_name', 'name', 'title']);
    $priceCol = firstCol($conn, 'franchies_add_product', ['product_price', 'price', 'offer_price', 'amount', 'mrp']);
    $bvCol = firstCol($conn, 'franchies_add_product', ['bv', 'pv_bv', 'pv']);
    $statusCol = firstCol($conn, 'franchies_add_product', ['status']);
    if ($idCol && $nameCol && $priceCol) {
        $bvSelect = $bvCol ? ", `$bvCol` product_bv" : ", 0 product_bv";
        $sql = "SELECT `$idCol` id,
       `$nameCol` product_name,
       `$priceCol` product_price{$bvSelect}
FROM franchies_add_product
WHERE product_type IN ('repurchase','both')";
        if ($statusCol) {
    $sql .= "
        AND `$nameCol` IS NOT NULL
        AND `$nameCol` != ''
        AND (`$statusCol` IS NULL
        OR `$statusCol` = ''
        OR LOWER(TRIM(`$statusCol`))
        IN ('active','approved','approve','1','yes'))
    ";
} else { $sql .= " WHERE `$nameCol` IS NOT NULL AND `$nameCol` != ''"; }
        $sql .= " ORDER BY `$idCol` ASC";
        $q = $conn->query($sql);
        while ($q && $r = $q->fetch_assoc()) $products[] = ['key' => 'a_' . $r['id'], 'id' => (int)$r['id'], 'product_name' => $r['product_name'], 'product_price' => (float)$r['product_price'], 'product_bv' => (float)($r['product_bv'] ?? 0)];
    }
}

$franchiseStockMap = fetchAvailableStockMap($conn, (int)$franchiseId);
$productStockMap = [];
foreach ($products as $product) {
    $productKey = (string)($product['key'] ?? '');
    $productNameKey = strtolower(trim((string)($product['product_name'] ?? '')));
    $productStockMap[$productKey] = $franchiseStockMap[$productNameKey] ?? 0;
}

// PRG pattern: read success message from GET param (set after redirect)
$success = isset($_GET['order_ok']) ? htmlspecialchars(urldecode($_GET['order_ok']), ENT_QUOTES, 'UTF-8') : '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $member_id      = trim($_POST['member_id'] ?? '');
    $payment_method = trim($_POST['payment_method'] ?? 'Cash');

    if ($member_id === '') {
        $error = "Please enter Member ID.";
    } elseif ($payment_method === '') {
        $error = "Please select a payment method.";
    } else {
        $memberStmt = $conn->prepare("SELECT full_name FROM signup_users WHERE customer_id = ? LIMIT 1");
        $memberStmt->bind_param("s", $member_id);
        $memberStmt->execute();
        $member = $memberStmt->get_result()->fetch_assoc();
        $memberStmt->close();

        if (!$member) {
            $error = "Member ID not found.";
        } else {
            $memberName = $member['full_name'];
            $totalOrderAmount = 0;
            $totalOrderBV = 0;
            $selectedProducts = [];

            foreach ($products as $product) {
                $key = $product['key'];
                $qty = (int)($_POST['qty_' . $key] ?? 0);
                $price = (float)$product['product_price'];
                if ($qty > 0) {
                    $amount = $qty * $price;
                    $bv = (float)($product['product_bv'] ?? 0);
                    $totalOrderAmount += $amount;
                    $totalOrderBV += ($bv * $qty);
                    $selectedProducts[] = ['product_id' => $product['id'], 'product_name' => $product['product_name'], 'price' => $price, 'qty' => $qty, 'amount' => $amount, 'bv' => $bv];
                }
            }

            if (empty($selectedProducts)) {
                $error = "Please select at least one product.";
            } else {
                $conn->begin_transaction();
                try {
                    $orderStmt = $conn->prepare("INSERT INTO member_orders (distributor_id, member_id, member_name, total_amount, payment_method) VALUES (?, ?, ?, ?, ?)");
                    $orderStmt->bind_param("sssds", $franchiseCode, $member_id, $memberName, $totalOrderAmount, $payment_method);
                    $orderStmt->execute();
                    $orderId = $conn->insert_id;
                    $orderStmt->close();

                    foreach ($selectedProducts as $item) {
                        $deductRes = deductFranchiseProductStockForOrder($conn, (int)$franchiseId, $item['product_name'], (int)$item['qty']);
                        if (empty($deductRes['ok'])) throw new Exception('Stock deduction failed: ' . ($deductRes['message'] ?? ''));
                    }

                    foreach ($selectedProducts as $item) {
                        $itemStmt = $conn->prepare("INSERT INTO member_order_items (order_id, product_id, product_name, product_price, quantity, amount, bv) VALUES (?, ?, ?, ?, ?, ?, ?)");
                        $itemStmt->bind_param("iisdidd", $orderId, $item['product_id'], $item['product_name'], $item['price'], $item['qty'], $item['amount'], $item['bv']);
                        $itemStmt->execute();
                        $itemStmt->close();

                        $saleStmt = $conn->prepare("INSERT INTO franchise_order_sale (distributor_id, member_id, member_name, product_name, quantity, price, total_amount, payment_method) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                        $saleStmt->bind_param("ssssidds", $franchiseCode, $member_id, $memberName, $item['product_name'], $item['qty'], $item['price'], $item['amount'], $payment_method);
                        $saleStmt->execute();
                        $saleId = $conn->insert_id;
                        $saleStmt->close();

                        $soldByType = strtoupper($franchiseType);
                        if ($soldByType === 'STATE') {
                            $commissionAmount = ($item['amount'] * 12) / 100;
                            $remarks = 'State franchise commission';
                            $commStmt = $conn->prepare("INSERT INTO commission_ledger (sale_id, receiver_type, receiver_id, commission_percent, commission_amount, from_sale_type, remarks) VALUES (?, 'STATE', ?, 12, ?, 'STATE', ?)");
                            $commStmt->bind_param('isds', $saleId, $franchiseCode, $commissionAmount, $remarks);
                            $commStmt->execute(); $commStmt->close();
                        }
                        if ($soldByType === 'CITY') {
                            $cityStmt = $conn->prepare("SELECT franchise_code FROM city_franchies_holder WHERE franchies_id = ? OR id = ? LIMIT 1");
                            $franchiseIdString = (string)$franchiseId;
                            $cityStmt->bind_param("ss", $franchiseCode, $franchiseIdString);
                            $cityStmt->execute();
                            $cityData = $cityStmt->get_result()->fetch_assoc();
                            $parentStateId = $cityData['franchise_code'] ?? '';
                            $cityStmt->close();
                            $cityCommission = ($item['amount'] * 10) / 100;
                            $remarks = 'City franchise commission';
                            $commStmt = $conn->prepare("INSERT INTO commission_ledger (sale_id, receiver_type, receiver_id, commission_percent, commission_amount, from_sale_type, remarks) VALUES (?, 'CITY', ?, 10, ?, 'CITY', ?)");
                            $commStmt->bind_param('isds', $saleId, $franchiseCode, $cityCommission, $remarks);
                            $commStmt->execute(); $commStmt->close();
                            if ($parentStateId !== '') {
                                $stateCommission = ($item['amount'] * 2) / 100;
                                $remarks = 'State override from city sale';
                                $commStmt = $conn->prepare("INSERT INTO commission_ledger (sale_id, receiver_type, receiver_id, commission_percent, commission_amount, from_sale_type, remarks) VALUES (?, 'STATE', ?, 2, ?, 'CITY', ?)");
                                $commStmt->bind_param('isds', $saleId, $parentStateId, $stateCommission, $remarks);
                                $commStmt->execute(); $commStmt->close();
                            }
                        }
                        if ($soldByType === 'MOBILE') {
                            $mobileStmt = $conn->prepare("SELECT city_franchise_code, state_franchise_code FROM mobile_franchies_holder WHERE mobile_franchies_id = ? OR id = ? LIMIT 1");
                            $franchiseIdString = (string)$franchiseId;
                            $mobileStmt->bind_param("ss", $franchiseCode, $franchiseIdString);
                            $mobileStmt->execute();
                            $mobileData = $mobileStmt->get_result()->fetch_assoc();
                            $parentCityId  = $mobileData['city_franchise_code'] ?? '';
                            $parentStateId = $mobileData['state_franchise_code'] ?? '';
                            $mobileStmt->close();
                            $mobileCommission = ($item['amount'] * 7) / 100;
                            $remarks = 'Mobile franchise commission';
                            $commStmt = $conn->prepare("INSERT INTO commission_ledger (sale_id, receiver_type, receiver_id, commission_percent, commission_amount, from_sale_type, remarks) VALUES (?, 'MOBILE', ?, 7, ?, 'MOBILE', ?)");
                            $commStmt->bind_param('isds', $saleId, $franchiseCode, $mobileCommission, $remarks);
                            $commStmt->execute(); $commStmt->close();
                            if ($parentCityId !== '') {
                                $cityCommission = ($item['amount'] * 3) / 100;
                                $remarks = 'City override from mobile sale';
                                $commStmt = $conn->prepare("INSERT INTO commission_ledger (sale_id, receiver_type, receiver_id, commission_percent, commission_amount, from_sale_type, remarks) VALUES (?, 'CITY', ?, 3, ?, 'MOBILE', ?)");
                                $commStmt->bind_param('isds', $saleId, $parentCityId, $cityCommission, $remarks);
                                $commStmt->execute(); $commStmt->close();
                            }
                            if ($parentStateId !== '') {
                                $stateCommission = ($item['amount'] * 2) / 100;
                                $remarks = 'State override from mobile sale';
                                $commStmt = $conn->prepare("INSERT INTO commission_ledger (sale_id, receiver_type, receiver_id, commission_percent, commission_amount, from_sale_type, remarks) VALUES (?, 'STATE', ?, 2, ?, 'MOBILE', ?)");
                                $commStmt->bind_param('isds', $saleId, $parentStateId, $stateCommission, $remarks);
                                $commStmt->execute(); $commStmt->close();
                            }
                        }
                    }

                    $conn->commit();
                    processRepurchaseMatchingBonus($member_id, $totalOrderBV, $conn);
                    // PRG — redirect after success so F5/refresh won't resubmit
                   $msg = urlencode(
    "✅ Order Completed Successfully! Member ID: {$member_id} | Member: {$memberName} | Total Amount: ₹" .
    number_format($totalOrderAmount, 2)
);

header("Location: " . strtok($_SERVER['REQUEST_URI'], '?') . "?order_ok=" . $msg);
                    exit;

                } catch (Exception $ex) {
                    $conn->rollback();
                    $error = "Order failed: " . $ex->getMessage();
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Member Order</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        html, body { min-height:100%; overflow-x:hidden; overflow-y:auto !important; background:#f0ede6; }
        #member-card.hidden { display:none; }
        .stepper { display:inline-flex; align-items:stretch; border:1.5px solid #cbd0d8; border-radius:8px; overflow:hidden; height:34px; width:120px; flex-shrink:0; }
        .stepper .s-btn { width:34px; min-width:34px; background:#f0ede6; border:none; cursor:pointer; font-size:17px; line-height:1; font-weight:700; color:#0f2d56; flex-shrink:0; display:flex; align-items:center; justify-content:center; user-select:none; transition:background .12s; padding:0; }
        .stepper .s-btn:hover { background:#e2dbd0; }
        .stepper .s-btn:active { background:#d1c9bc; }
        .stepper .s-inp { flex:1; width:100%; text-align:center; font-size:13px; font-weight:700; color:#0f2d56; border:none; border-left:1px solid #cbd0d8; border-right:1px solid #cbd0d8; outline:none; -moz-appearance:textfield; background:#fff; padding:0; }
        .stepper .s-inp::-webkit-inner-spin-button, .stepper .s-inp::-webkit-outer-spin-button { -webkit-appearance:none; margin:0; }
        .spill { display:inline-flex; align-items:center; gap:3px; font-size:10px; font-weight:700; padding:2px 7px; border-radius:20px; white-space:nowrap; }
        .sp-ok   { background:#d1fae5; color:#065f46; }
        .sp-low  { background:#fef3c7; color:#92400e; }
        .sp-none { background:#fee2e2; color:#991b1b; }
        .ptable { width:100%; border-collapse:collapse; }
        .ptable th { background:#f5f1eb; color:#b8820e; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.06em; padding:9px 14px; text-align:left; border-bottom:2px solid #e6dfd3; }
        .ptable td { padding:9px 14px; border-bottom:1px solid #f0ebe3; vertical-align:middle; font-size:13px; color:#1e2433; }
        .ptable tbody tr:hover { background:#fafaf6; }
        .ptable tbody tr:last-child td { border-bottom:none; }
        .total-row td { background:#0f2d56; padding:11px 14px; }
        .minfo-item { display:flex; align-items:center; gap:8px; padding:0 16px 0 0; }
        .minfo-item:not(:last-child) { border-right:1px solid #e5e7eb; margin-right:16px; }
        .sel-wrap select { appearance:none; -webkit-appearance:none; background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='14' height='14' fill='none' viewBox='0 0 24 24'%3E%3Cpath stroke='%230f2d56' stroke-linecap='round' stroke-linejoin='round' stroke-width='2.5' d='M6 9l6 6 6-6'/%3E%3C/svg%3E"); background-repeat:no-repeat; background-position:right 12px center; padding-right:36px; }
        .product-scroll { overflow-x:auto; -webkit-overflow-scrolling:touch; }
        @media (max-width:767px) {
            .ptable, .ptable thead, .ptable tbody, .ptable th, .ptable td, .ptable tr { display:block; width:100%; }
            .ptable thead { display:none; }
            .ptable tbody tr { border:1px solid #e6dfd3; border-radius:10px; margin:8px; background:#fff; overflow:hidden; }
            .ptable td { display:flex; align-items:center; justify-content:space-between; padding:10px 14px !important; border-bottom:1px solid #f3efe8; font-size:13px !important; }
            .ptable td:last-child { border-bottom:none; }
            .ptable td::before { content:attr(data-label); font-weight:700; color:#b8820e; font-size:11px; min-width:80px; }
            .total-row td { display:block !important; text-align:right !important; }
            .total-row td::before { display:none; }
        }
    </style>
</head>
<body class="font-sans">
<?php if (file_exists(__DIR__ . '/includes/sidebar.php')) include __DIR__ . '/includes/sidebar.php'; ?>
<div class="min-h-screen lg:ml-64">
<?php if (file_exists(__DIR__ . '/includes/navbar.php')) include __DIR__ . '/includes/navbar.php'; ?>

<main class="px-3 py-4 sm:px-4 lg:px-5">
<div class="max-w-[1400px] mx-auto pb-8">

<?php if ($success !== ''): ?>
<div class="mb-4 rounded-2xl border border-green-300 bg-gradient-to-r from-green-50 to-green-100 px-6 py-5 shadow-md">

    <div class="flex items-start gap-4">

        <div class="w-12 h-12 rounded-full bg-green-500 flex items-center justify-center shrink-0">
            <i class="fa-solid fa-check text-white text-xl"></i>
        </div>

        <div>
            <h3 class="text-[20px] font-bold text-green-800">
                Order Completed Successfully
            </h3>

            <p class="mt-1 text-[15px] text-green-700 font-medium">
                <?= $success ?>
            </p>
        </div>

    </div>

</div>
<?php endif; ?>
<?php if ($error !== ''): ?>
<div class="flex items-center gap-2 bg-red-50 border border-red-300 text-red-800 px-4 py-3 rounded-xl mb-3 text-sm shadow-sm">
    <i class="fa-solid fa-circle-exclamation text-red-500"></i> <?= e($error) ?>
</div>
<?php endif; ?>

<div class="bg-white rounded-2xl shadow border border-[#e6dfd3] overflow-hidden">

    <!-- ── HEADER ── -->
    <div class="bg-[#0f2d56] px-5 py-3.5 flex items-center justify-between">
        <div class="flex items-center gap-2.5">
            <div class="w-8 h-8 rounded-lg bg-white/10 flex items-center justify-center">
                <i class="fa-solid fa-cart-shopping text-[#ffbe2e] text-[14px]"></i>
            </div>
            <div>
                <h2 class="text-[#ffbe2e] font-bold text-[16px] sm:text-[18px] leading-tight">Member Order</h2>
                <p class="text-blue-300 text-[11px]">Create a new product order for a member</p>
            </div>
        </div>
        <a href="member_order_list.php" class="flex items-center gap-1.5 text-blue-200 hover:text-white text-[12px] font-medium transition">
            <i class="fa-solid fa-list-ul text-[11px]"></i> Order History
        </a>
    </div>

    <form method="POST" id="orderForm">

        <!-- ── TOP BAR: member + payment ── -->
        <div class="px-4 sm:px-6 py-4 bg-[#faf8f4] border-b border-[#ede8e0]">
            <div class="flex flex-col sm:flex-row gap-3 items-start sm:items-end">

                <!-- Member ID -->
                <div class="flex-1 min-w-0">
                    <label class="block text-[11px] font-bold text-gray-400 uppercase tracking-widest mb-1">
                        <i class="fa-solid fa-id-badge mr-1"></i>Member ID
                    </label>
                    <div class="relative">
                        <input
                            type="text"
                            id="member_id_input"
                            name="member_id"
                            placeholder="e.g. ABC1234"
                            autocomplete="off"
                            value="<?= e($_POST['member_id'] ?? '') ?>"
                            class="w-full h-[42px] border border-gray-300 rounded-[10px] pl-4 pr-10 text-[14px] font-medium focus:outline-none focus:ring-2 focus:ring-[#0f2d56] focus:border-transparent transition bg-white"
                        >
                        <span id="lookup-spinner" class="hidden absolute right-3 top-1/2 -translate-y-1/2">
                            <i class="fa-solid fa-circle-notch fa-spin text-[#c7921e] text-[14px]"></i>
                        </span>
                        <span id="lookup-ok" class="hidden absolute right-3 top-1/2 -translate-y-1/2">
                            <i class="fa-solid fa-circle-check text-green-500 text-[15px]"></i>
                        </span>
                        <span id="lookup-err" class="hidden absolute right-3 top-1/2 -translate-y-1/2">
                            <i class="fa-solid fa-circle-xmark text-red-400 text-[15px]"></i>
                        </span>
                    </div>
                    <p id="member-not-found" class="hidden mt-1 text-red-500 text-[11px] font-semibold"><i class="fa-solid fa-triangle-exclamation mr-1"></i>No member found with this ID.</p>
                </div>

                <!-- Payment -->
                <div class="w-full sm:w-[180px] shrink-0">
                    <label class="block text-[11px] font-bold text-gray-400 uppercase tracking-widest mb-1">
                        <i class="fa-solid fa-credit-card mr-1"></i>Payment
                    </label>
                    <div class="sel-wrap">
                        <select name="payment_method" class="w-full h-[42px] border border-gray-300 rounded-[10px] px-3 text-[14px] font-medium focus:outline-none focus:ring-2 focus:ring-[#0f2d56] transition bg-white cursor-pointer">
                            <option value="Cash"   <?= (($_POST['payment_method'] ?? 'Cash') === 'Cash')   ? 'selected' : '' ?>>Cash</option>
                            <option value="Online" <?= (($_POST['payment_method'] ?? '')     === 'Online') ? 'selected' : '' ?>>Online</option>
                        </select>
                    </div>
                </div>

                <!-- Submit button -->
                <div class="w-full sm:w-auto shrink-0">
                    <label class="block text-[11px] font-bold text-gray-400 uppercase tracking-widest mb-1 invisible">Submit</label>
                    <button type="submit" class="w-full sm:w-auto h-[42px] px-6 rounded-[10px] bg-[#0f2d56] hover:bg-[#173d70] text-white text-[13px] font-bold flex items-center justify-center gap-2 transition active:scale-95 shadow">
                        <i class="fa-solid fa-paper-plane text-[12px]"></i> Place Order
                    </button>
                </div>
            </div>

            <!-- Member info strip (shows after lookup) -->
            <div id="member-card" class="hidden mt-3 bg-white border border-[#d4f0de] rounded-xl px-4 py-3 flex flex-wrap items-center gap-y-2">
                <div class="minfo-item">
                    <div class="w-7 h-7 rounded-full bg-[#0f2d56] flex items-center justify-center shrink-0">
                        <i class="fa-solid fa-user text-white text-[11px]"></i>
                    </div>
                    <div>
                        <p class="text-[10px] text-gray-400 font-semibold uppercase leading-none">Name</p>
                        <p id="mc-name" class="text-[13px] font-bold text-[#0f2d56] leading-tight">—</p>
                    </div>
                </div>
                <div class="minfo-item">
                    <div class="w-7 h-7 rounded-lg bg-amber-100 flex items-center justify-center shrink-0">
                        <i class="fa-solid fa-fingerprint text-[#c7921e] text-[11px]"></i>
                    </div>
                    <div>
                        <p class="text-[10px] text-gray-400 font-semibold uppercase leading-none">Member ID</p>
                        <p id="mc-id" class="text-[13px] font-bold text-gray-700 leading-tight">—</p>
                    </div>
                </div>
                <div class="minfo-item">
                    <div class="w-7 h-7 rounded-lg bg-blue-50 flex items-center justify-center shrink-0">
                        <i class="fa-solid fa-phone text-blue-500 text-[11px]"></i>
                    </div>
                    <div>
                        <p class="text-[10px] text-gray-400 font-semibold uppercase leading-none">Mobile</p>
                        <p id="mc-mobile" class="text-[13px] font-bold text-gray-700 leading-tight">—</p>
                    </div>
                </div>
                <div class="minfo-item" style="border-right:none; margin-right:0; padding-right:0;">
                    <div class="w-7 h-7 rounded-lg bg-purple-50 flex items-center justify-center shrink-0">
                        <i class="fa-solid fa-envelope text-purple-500 text-[11px]"></i>
                    </div>
                    <div>
                        <p class="text-[10px] text-gray-400 font-semibold uppercase leading-none">Email</p>
                        <p id="mc-email" class="text-[13px] font-bold text-gray-700 leading-tight break-all">—</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- ── PRODUCTS TABLE ── -->
        <div class="product-scroll">
            <table class="ptable">
                <thead>
                    <tr>
                        <th style="width:42px">#</th>
                        <th>Product Name</th>
                        <th>Price (₹)</th>
                        <th>Stock</th>
                        <th>Quantity</th>
                        <th>Amount (₹)</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (count($products) > 0): ?>
                    <?php foreach ($products as $i => $p):
                        $k      = $p['key'];
                        $pname  = $p['product_name'];
                        $price  = (float)$p['product_price'];
                        $stock  = (int)($productStockMap[$k] ?? 0);
                        $noStock= $stock <= 0;
                        $lowStk = !$noStock && $stock <= 10;
                        $spCls  = $noStock ? 'sp-none' : ($lowStk ? 'sp-low' : 'sp-ok');
                        $spIcon = $noStock ? 'fa-circle-xmark' : ($lowStk ? 'fa-triangle-exclamation' : 'fa-circle-check');
                        $spTxt  = $noStock ? 'Out of Stock' : ($lowStk ? "Low: $stock" : $stock);
                        $rowBg  = ($i % 2 === 0) ? '' : 'background:#faf8f4;';
                    ?>
                    <tr style="<?= $rowBg ?>">
                        <td data-label="#">
                            <span class="w-6 h-6 rounded-md bg-[#f0ede6] flex items-center justify-center text-[11px] font-bold text-[#6d675f]"><?= $i + 1 ?></span>
                        </td>
                        <td data-label="Product" class="font-semibold text-[#1e2433] text-[13px]"><?= e($pname) ?></td>
                        <td data-label="Price" class="font-bold text-[#0f2d56] text-[13px]">₹<?= number_format($price, 2) ?></td>
                        <td data-label="Stock">
                            <span class="spill <?= $spCls ?>">
                                <i class="fa-solid <?= $spIcon ?>" style="font-size:9px"></i>
                                <?= e($spTxt) ?>
                            </span>
                        </td>
                        <td data-label="Qty">
                            <div class="stepper">
                                <button type="button" class="s-btn" onclick='dec(<?= json_encode($k) ?>,<?= $price ?>)'>&#8722;</button>
                                <input type="number" value="0" min="0" class="s-inp"
                                    id="qty_<?= e($k) ?>"
                                    name="qty_<?= e($k) ?>"
                                    data-stock="<?= $stock ?>"
                                    oninput='manQty(<?= json_encode($k) ?>,<?= $price ?>)'>
                                <button type="button" class="s-btn" onclick='inc(<?= json_encode($k) ?>,<?= $price ?>)'>&#43;</button>
                            </div>
                        </td>
                        <td data-label="Amount" class="font-bold text-[13px] text-[#1e2433]">₹<span id="amount_<?= e($k) ?>">0.00</span></td>
                    </tr>
                    <?php endforeach; ?>
                    <!-- Grand total row -->
                   <tr class="total-row">
    <td colspan="5"
        class="text-right text-[13px] font-bold uppercase tracking-widest pr-4"
        style="color:#ffffff !important;">
        <i class="fa-solid fa-receipt mr-2 text-[#ffbe2e]"></i>
        Grand Total
    </td>

    <td class="font-bold text-[20px]"
        style="color:#ffffff !important;">
        ₹<span id="grand_total" style="color:#ffffff !important;">0.00</span>
    </td>
</tr>
                <?php else: ?>
                    <tr>
                        <td colspan="6" class="text-center py-12">
                            <i class="fa-solid fa-box-open text-4xl text-gray-300 mb-3 block"></i>
                            <p class="text-gray-400 text-[14px] font-medium">No products found.</p>
                            <p class="text-gray-300 text-[12px]">Add products from the admin panel first.</p>
                        </td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

    </form>
</div><!-- /card -->
</div>
</main>
</div>

<script>
function inc(k,p){let i=document.getElementById('qty_'+k),q=(parseInt(i.value)||0)+1;i.value=q;upd(k,q,p);}
function dec(k,p){let i=document.getElementById('qty_'+k),q=Math.max(0,(parseInt(i.value)||0)-1);i.value=q;upd(k,q,p);}
function manQty(k,p){let i=document.getElementById('qty_'+k),q=parseInt(i.value);if(isNaN(q)||q<0)q=0;i.value=q;upd(k,q,p);}
function upd(k,q,p){document.getElementById('amount_'+k).innerText=(q*p).toFixed(2);gt();}
function gt(){let t=0;document.querySelectorAll('[id^="amount_"]').forEach(e=>{t+=parseFloat(e.innerText)||0;});document.getElementById('grand_total').innerText=t.toFixed(2);}

let _lt=null;
const _mi=document.getElementById('member_id_input');
const _mc=document.getElementById('member-card');
const _nf=document.getElementById('member-not-found');
const _sp=document.getElementById('lookup-spinner');
const _ok=document.getElementById('lookup-ok');
const _er=document.getElementById('lookup-err');
function hideIco(){[_sp,_ok,_er].forEach(e=>e.classList.add('hidden'));}
function showCard(d){
    document.getElementById('mc-name').textContent=d.name||'—';
    document.getElementById('mc-id').textContent=d.id||'—';
    document.getElementById('mc-mobile').textContent=d.mobile||'—';
    document.getElementById('mc-email').textContent=d.email||'—';
    _mc.classList.remove('hidden');
}
function doLookup(v){
    hideIco(); _mc.classList.add('hidden'); _nf.classList.add('hidden');
    if(!v) return;
    _sp.classList.remove('hidden');
    fetch('?lookup_member='+encodeURIComponent(v))
        .then(r=>r.json())
        .then(d=>{
            hideIco();
            if(d.found){_ok.classList.remove('hidden');showCard(d);}
            else{_er.classList.remove('hidden');_nf.classList.remove('hidden');}
        }).catch(()=>hideIco());
}
_mi.addEventListener('input',function(){
    clearTimeout(_lt);
    const v=this.value.trim();
    hideIco(); _mc.classList.add('hidden'); _nf.classList.add('hidden');
    if(!v) return;
    _sp.classList.remove('hidden');
    _lt=setTimeout(()=>doLookup(v),500);
});
window.addEventListener('DOMContentLoaded',()=>{
    const v=_mi.value.trim();
    if(v) doLookup(v);
});
</script>
</body>
</html>