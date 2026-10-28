<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

header("Content-Type: application/json");

require_once __DIR__ . '/../member/config/db.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);

$data = json_decode(file_get_contents("php://input"), true);

$customer_id = $data['customer_id'] ?? '';
$items = $data['items'] ?? [];

if (!$customer_id || empty($items)) {
    echo json_encode(["success" => false, "message" => "Invalid data"]);
    exit;
}

$total_amount = 0;
$total_pv = 0;

foreach ($items as $item) {
    $product_id = (int)$item['product_id'];
    $qty = (int)$item['quantity'];

    if ($product_id <= 0 || $qty <= 0) continue;

    $stmt = $conn->prepare("SELECT product_name, price, pv_bv FROM products WHERE id = ? LIMIT 1");
    $stmt->bind_param("i", $product_id);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($row = $res->fetch_assoc()) {
        $total_amount += ((float)$row['price'] * $qty);
        $total_pv += ((float)$row['pv_bv'] * $qty);
    }
}

if ($total_amount <= 0) {
    echo json_encode(["success" => false, "message" => "Invalid product data"]);
    exit;
}

$stmt = $conn->prepare("INSERT INTO orders (customer_id, total, total_pv, status, order_date) VALUES (?, ?, ?, 'Pending', NOW())");
$stmt->bind_param("sdd", $customer_id, $total_amount, $total_pv);

if (!$stmt->execute()) {
    echo json_encode(["success" => false, "message" => "Order insert failed", "error" => $stmt->error]);
    exit;
}

$order_id = $stmt->insert_id;

$stmtItem = $conn->prepare("INSERT INTO order_items (order_id, product_name, product_price, quantity, bv, created_at) VALUES (?, ?, ?, ?, ?, NOW())");

foreach ($items as $item) {
    $product_id = (int)$item['product_id'];
    $qty = (int)$item['quantity'];

    if ($product_id <= 0 || $qty <= 0) continue;

    $stmt2 = $conn->prepare("SELECT product_name, price, pv_bv FROM products WHERE id = ? LIMIT 1");
    $stmt2->bind_param("i", $product_id);
    $stmt2->execute();
    $res = $stmt2->get_result();

    if ($row = $res->fetch_assoc()) {
        $stmtItem->bind_param("isdid", $order_id, $row['product_name'], $row['price'], $qty, $row['pv_bv']);
        $stmtItem->execute();
    }
}

function processRepurchaseMatchingBonus($memberId, $bv, $conn)
{
    $stmtSelf = $conn->prepare("UPDATE signup_users SET self_bv = self_bv + ? WHERE customer_id = ?");
    $stmtSelf->bind_param("ds", $bv, $memberId);
    $stmtSelf->execute();

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
            $stmtUpdate = $conn->prepare("
                UPDATE signup_users 
                SET repurchase_left_team_bv  = repurchase_left_team_bv + ?,
                    repurchase_total_left_bv = repurchase_total_left_bv + ?
                WHERE customer_id = ?
            ");
        } else {
            $stmtUpdate = $conn->prepare("
                UPDATE signup_users 
                SET repurchase_right_team_bv  = repurchase_right_team_bv + ?,
                    repurchase_total_right_bv = repurchase_total_right_bv + ?
                WHERE customer_id = ?
            ");
        }
        $stmtUpdate->bind_param("dds", $bv, $bv, $parentId);
        $stmtUpdate->execute();

        calculateRepurchaseMatching($parentId, $memberId, $conn);

        $currentMember = $parentId;
    }
}

function calculateRepurchaseMatching($customerId, $fromMember, $conn)
{
    $stmt = $conn->prepare("
        SELECT repurchase_left_team_bv, repurchase_right_team_bv,
               repurchase_first_pair_completed
        FROM signup_users WHERE customer_id = ? LIMIT 1
    ");
    $stmt->bind_param("s", $customerId);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();

    if (!$user) return;

    $left  = (float)$user['repurchase_left_team_bv'];
    $right = (float)$user['repurchase_right_team_bv'];
    $income      = 0;
    $matching_bv = 0;
    $deduct_left  = 0;
    $deduct_right = 0;

    if (!$user['repurchase_first_pair_completed']) {

        if (($left >= 200 && $right >= 100) || ($left >= 100 && $right >= 200)) {
            $matching_bv  = min($left, $right);
            $income       = $matching_bv;
            $deduct_left  = $matching_bv;
            $deduct_right = $matching_bv;

            $stmtFlag = $conn->prepare("UPDATE signup_users SET repurchase_first_pair_completed = 1 WHERE customer_id = ?");
            $stmtFlag->bind_param("s", $customerId);
            $stmtFlag->execute();
        }

    } else {

        $matching_bv  = min($left, $right);
        $income       = $matching_bv;
        $deduct_left  = $matching_bv;
        $deduct_right = $matching_bv;
    }

    if ($income <= 0) return;

    $today = date('Y-m-d');
    $capStmt = $conn->prepare("
        SELECT COALESCE(SUM(income_amount), 0) AS earned_today
        FROM repurchase_matching_income_log
        WHERE customer_id = ? AND DATE(created_at) = ?
    ");
    $capStmt->bind_param("ss", $customerId, $today);
    $capStmt->execute();
    $earned_today = (float)$capStmt->get_result()->fetch_assoc()['earned_today'];

    $remaining_cap = 4000 - $earned_today;
    if ($remaining_cap <= 0) return;

    if ($income > $remaining_cap) {
        $income = $remaining_cap;
    }

    $carry_left  = $left  - $deduct_left;
    $carry_right = $right - $deduct_right;

    $stmtUpdate = $conn->prepare("
        UPDATE signup_users
        SET repurchase_left_team_bv    = repurchase_left_team_bv - ?,
            repurchase_right_team_bv   = repurchase_right_team_bv - ?,
            repurchase_matching_income = repurchase_matching_income + ?,
            wallet_balance             = wallet_balance + ?
        WHERE customer_id = ?
    ");
    $stmtUpdate->bind_param("dddds", $deduct_left, $deduct_right, $income, $income, $customerId);
    $stmtUpdate->execute();

    $stmtLog = $conn->prepare("
        INSERT INTO repurchase_matching_income_log
        (customer_id, from_member, left_bv, right_bv, carry_left_bv, carry_right_bv, matching_bv, income_amount, status, income_date, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Pending', CURDATE(), NOW())
    ");
    $stmtLog->bind_param("ssdddddd", $customerId, $fromMember, $left, $right, $carry_left, $carry_right, $matching_bv, $income);
    $stmtLog->execute();
}

processRepurchaseMatchingBonus($customer_id, $total_pv, $conn);

echo json_encode([
    "success"      => true,
    "order_id"     => $order_id,
    "total_amount" => $total_amount,
    "total_pv"     => $total_pv
]);