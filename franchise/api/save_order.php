<?php
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../config/db.php';

function processRepurchaseMatchingBonus($memberId, $bv, $conn)
{
    $stmtSelf = $conn->prepare(
        "UPDATE signup_users
         SET self_bv = self_bv + ?
         WHERE customer_id = ?"
    );
    $stmtSelf->bind_param("ds", $bv, $memberId);
    $stmtSelf->execute();

    $stmtWeak = $conn->prepare(
        "SELECT
            repurchase_left_team_bv,
            repurchase_right_team_bv
         FROM signup_users
         WHERE customer_id = ?
         LIMIT 1"
    );
    $stmtWeak->bind_param("s", $memberId);
    $stmtWeak->execute();

    $weakData = $stmtWeak->get_result()->fetch_assoc();

    if ($weakData) {
        $leftBV = (float)$weakData['repurchase_left_team_bv'];
        $rightBV = (float)$weakData['repurchase_right_team_bv'];

        if ($leftBV < $rightBV) {
            $stmtAdd = $conn->prepare(
                "UPDATE signup_users
                 SET repurchase_left_team_bv =
                     repurchase_left_team_bv + ?
                 WHERE customer_id = ?"
            );
        } else {
            $stmtAdd = $conn->prepare(
                "UPDATE signup_users
                 SET repurchase_right_team_bv =
                     repurchase_right_team_bv + ?
                 WHERE customer_id = ?"
            );
        }

        $stmtAdd->bind_param(
            "ds",
            $bv,
            $memberId
        );

        $stmtAdd->execute();
    }
}


if (!isset($conn) || !($conn instanceof mysqli)) {
    echo json_encode(['success'=>false,'message'=>'DB connection failed']); exit;
}

/* resolve franchise */
$franchise_id = 0;
foreach (['franchise_id','franchies_id','franchise_db_id',
          'mobile_franchise_id','city_franchies_holder_id','state_franchies_holder_id'] as $k) {
    if (!empty($_SESSION[$k]) && is_numeric($_SESSION[$k])) {
        $franchise_id = (int)$_SESSION[$k]; break;
    }
}
if (!$franchise_id) { echo json_encode(['success'=>false,'message'=>'Not logged in']); exit; }

$input = json_decode(file_get_contents('php://input'), true);
if (!$input || empty($input['cart'])) {
    echo json_encode(['success'=>false,'message'=>'Cart is empty']); exit;
}

$member_id      = trim($input['member_id']     ?? '');
$member_name    = trim($input['member_name']   ?? 'Walk-in Customer');
$payment_method = trim($input['payment_method'] ?? 'cash');
$cart           = $input['cart'];

/* validate items */
$validated = [];
foreach ($cart as $item) {
    $pid   = (int)   ($item['product_id']  ?? 0);
    $pname = trim(    $item['product_name'] ?? '');
    $price = (float) ($item['price']        ?? 0);
    $bv    = (float) ($item['bv']           ?? 0);
    $qty   = (int)   ($item['qty']          ?? 0);
    if ($pid <= 0 || $qty <= 0) continue;
    $validated[] = compact('pid','pname','price','bv','qty');
}
if (empty($validated)) {
    echo json_encode(['success'=>false,'message'=>'No valid items']); exit;
}

$total_amount = array_sum(array_map(fn($i) => $i['price'] * $i['qty'], $validated));
$totalBV = 0;

foreach ($validated as $item) {
    $totalBV +=
        $item['bv'] *
        $item['qty'];
}
$created_at   = date('Y-m-d H:i:s');

$conn->begin_transaction();
try {
    /* server-side stock check */
    foreach ($validated as $item) {
        $st = $conn->prepare(
    "SELECT COALESCE(SUM(product_balance),0) AS bal
     FROM franchies_product_stock_request
     WHERE franchise_id = ?
     AND product_name = ?
     AND status = 'Active'"
);
        $st->bind_param('is', $franchise_id, $item['pname']);
        $st->execute();
        $r = $st->get_result()->fetch_assoc(); $st->close();
        if (!$r || (int)$r['bal'] < $item['qty'])
            throw new Exception("Insufficient stock for: {$item['pname']}");
    }

    /* insert member_orders */
    $st = $conn->prepare(
        "INSERT INTO member_orders
         (distributor_id, member_id, member_name, total_amount, payment_method, created_at)
         VALUES (?,?,?,?,?,?)"
    );
    $st->bind_param('issdss', $franchise_id, $member_id, $member_name,
                               $total_amount, $payment_method, $created_at);
    $st->execute();
    $order_id = (int)$conn->insert_id;
    $st->close();
    if (!$order_id) throw new Exception('Failed to create order');

    /* insert items + deduct stock */
    $stItem = $conn->prepare(
        "INSERT INTO member_order_items
         (order_id, product_id, product_name, product_price, quantity, amount, bv, created_at)
         VALUES (?,?,?,?,?,?,?,?)"
    );
    $stSale = $conn->prepare(
    "INSERT INTO franchise_order_sale
    (
        distributor_id,
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
    (?,?,?,?,?,?,?,?,?)"
);
    $stStock = $conn->prepare(
    "UPDATE franchies_product_stock_request
        SET product_used = product_used + ?,
            product_balance = product_balance - ?
      WHERE franchise_id = ?
      AND product_name = ?
      AND status = 'Active'"
);
    foreach ($validated as $item) {
        $amount = $item['price'] * $item['qty'];
        $stItem->bind_param('iisdiids',
            $order_id, $item['pid'], $item['pname'],
            $item['price'], $item['qty'], $amount, $item['bv'], $created_at);
        $stItem->execute();
        $amount =
    $item['price'] *
    $item['qty'];

$stSale->bind_param(
    'isssiddss',
    $franchise_id,
    $member_id,
    $member_name,
    $item['pname'],
    $item['qty'],
    $item['price'],
    $amount,
    $payment_method,
    $created_at
);

$stSale->execute();

        $stStock->bind_param('diis',
            $item['qty'], $item['qty'], $franchise_id, $item['pname']);
        $stStock->execute();
    }
   $stItem->close();
$stStock->close();
$stSale->close();

if (
    !empty($member_id) &&
    $totalBV > 0
) {
    processRepurchaseMatchingBonus(
        $member_id,
        $totalBV,
        $conn
    );
}

$conn->commit();

echo json_encode([
    'success' => true,
    'order_id' => $order_id
]);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success'=>false, 'message'=>$e->getMessage()]);
}