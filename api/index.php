<?php
/**
 * FRESHIYA TRADING COMPANY - Complete Backend API
 * Single entry point: api/index.php
 * All React frontend calls go to: /api/index.php?route=MODULE/ACTION
 */

// ============================================================
// CORS & HEADERS
// ============================================================
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// ============================================================
// DATABASE CONFIG — change these to match your XAMPP setup
// ============================================================
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'ind_new');
define('JWT_SECRET', 'freshiya_secret_key_2024');

// ============================================================
// DATABASE CONNECTION
// ============================================================
function getDB() {
    static $conn = null;
    if ($conn === null) {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        $conn->set_charset("utf8mb4");
        if ($conn->connect_error) {
            respond(500, ['error' => 'DB connection failed: ' . $conn->connect_error]);
        }
    }
    return $conn;
}

// ============================================================
// HELPERS
// ============================================================
function respond($code, $data) {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit();
}

function body() {
    return json_decode(file_get_contents('php://input'), true) ?? [];
}

function required_fields($data, $fields) {
    foreach ($fields as $f) {
        if (!isset($data[$f]) || $data[$f] === '') {
            respond(400, ['error' => "Field '$f' is required"]);
        }
    }
}

function verifyToken($token) {
    $parts = explode('.', $token);
    if (count($parts) !== 3) return false;
    [$header, $payload, $sig] = $parts;
    $expected = base64_encode(hash_hmac('sha256', "$header.$payload", JWT_SECRET, true));
    if (!hash_equals($expected, $sig)) return false;
    $data = json_decode(base64_decode($payload), true);
    if ($data['exp'] < time()) return false;
    return $data;
}

function authMember() {
    $headers = getallheaders();
    $auth = $headers['Authorization'] ?? $headers['authorization'] ?? '';
    $token = str_replace('Bearer ', '', $auth);
    $data = verifyToken($token);
    if (!$data || $data['role'] !== 'member') {
        respond(401, ['error' => 'Unauthorized']);
    }
    return $data['user_id'];
}

function authAdmin() {
    $headers = getallheaders();
    $auth = $headers['Authorization'] ?? $headers['authorization'] ?? '';
    $token = str_replace('Bearer ', '', $auth);
    $data = verifyToken($token);
    if (!$data || $data['role'] !== 'admin') {
        respond(401, ['error' => 'Admin access required']);
    }
    return $data['user_id'];
}

function generateCustomerId($db) {
    $res = $db->query("SELECT MAX(CAST(SUBSTRING(customer_id, 4) AS UNSIGNED)) as max_id FROM signup_users WHERE customer_id LIKE 'FRS%'");
    $row = $res->fetch_assoc();
    $next = ($row['max_id'] ?? 0) + 1;
    return 'FRS' . str_pad($next, 5, '0', STR_PAD_LEFT);
}

function generateReferralCode($name) {
    return strtoupper(substr($name, 0, 3)) . rand(1000, 9999);
}

// ============================================================
// ROUTER
// ============================================================
$route  = trim($_GET['route'] ?? '', '/');
$method = $_SERVER['REQUEST_METHOD'];

$routes = [
    // AUTH
    'auth/register'              => 'auth_register',
    'auth/login'                 => 'auth_login',
    'auth/admin-login'           => 'auth_admin_login',

    // MEMBER
    
    'member/update-profile'      => 'member_update_profile',
    'member/change-password'     => 'member_change_password',
    'member/change-txn-password' => 'member_change_txn_password',
    'member/bank-kyc'            => 'member_bank_kyc',
    'member/upload-kyc'          => 'member_upload_kyc',
    'member/dashboard'           => 'member_dashboard',

    // NETWORK
    'network/direct'             => 'network_direct',
    'network/team-business'      => 'network_team_business',
    'network/team-volume'        => 'network_team_volume',


   

    // ORDERS
    'orders/create'              => 'orders_create',
    'orders/list'                => 'orders_list',
    'orders/detail'              => 'orders_detail',
    'orders/status'              => 'orders_status',

    // REPURCHASE
    'repurchase/create'          => 'repurchase_create',
    'repurchase/list'            => 'repurchase_list',

    // WALLET
    'wallet/balance'             => 'wallet_balance',
    'wallet/withdraw'            => 'wallet_withdraw',
    'wallet/history'             => 'wallet_history',
    'wallet/cheque-request'      => 'wallet_cheque_request',

    // INCOME REPORTS
    'income/level-matching'      => 'income_level_matching',
    'income/club-bonus'          => 'income_club_bonus',
    'income/referral-bonus'      => 'income_referral_bonus',
    'income/retail-profit'       => 'income_retail_profit',
    'income/reward'              => 'income_reward',
    'income/repurchase-matching' => 'income_repurchase_matching',

    // CLUB & REWARDS
    'club/status'                => 'club_status',
    'club/achievers'             => 'club_achievers',
    'reward/status'              => 'reward_status',
    'reward/master'              => 'reward_master',

    // MESSAGES / INBOX
    'messages/inbox'             => 'messages_inbox',
    'messages/send'              => 'messages_send',
    'messages/thread'            => 'messages_thread',

    // PAYOUT
    'payout/processing'          => 'payout_processing',
    'payout/closing'             => 'payout_closing',

    // ---- ADMIN ROUTES ----
    'admin/members'              => 'admin_members',
    'admin/member-detail'        => 'admin_member_detail',
    'admin/activate-member'      => 'admin_activate_member',
    'admin/orders'               => 'admin_orders',
    'admin/update-order-status'  => 'admin_update_order_status',
    'admin/products/add'         => 'admin_products_add',
    'admin/products/update'      => 'admin_products_update',
    'admin/products/delete'      => 'admin_products_delete',
    'admin/cheque-requests'      => 'admin_cheque_requests',
    'admin/approve-cheque'       => 'admin_approve_cheque',
    'admin/run-level-matching'   => 'admin_run_level_matching',
    'admin/run-club-bonus'       => 'admin_run_club_bonus',
    'admin/run-referral-bonus'   => 'admin_run_referral_bonus',
    'admin/run-repurchase'       => 'admin_run_repurchase',
    'admin/payout-close'         => 'admin_payout_close',
    'admin/kyc-verify'           => 'admin_kyc_verify',
    'admin/dispatch'             => 'admin_dispatch',
    'admin/dashboard'            => 'admin_dashboard',
    'admin/send-message'         => 'admin_send_message',
];

if (!isset($routes[$route])) {
    respond(404, ['error' => "Route not found: $route"]);
}

$fn = $routes[$route];
$fn();



// ============================================================
// =================== NETWORK MODULE =========================
// ============================================================

function network_direct() {
    $db  = getDB();
    $uid = authMember();

    $stmt = $db->prepare("SELECT customer_id, name, mobile, email, joining_date, is_active, position, sponsor_id 
        FROM signup_users WHERE sponsor_id = ? ORDER BY joining_date DESC");
    $stmt->bind_param("s", $uid);
    $stmt->execute();
    $members = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    respond(200, ['success' => true, 'data' => $members, 'count' => count($members)]);
}

function network_team_business() {
    $db  = getDB();
    $uid = authMember();

    $stmt = $db->prepare("SELECT * FROM team_business_status WHERE customer_id = ? ORDER BY created_at DESC LIMIT 30");
    $stmt->bind_param("s", $uid);
    $stmt->execute();
    respond(200, ['success' => true, 'data' => $stmt->get_result()->fetch_all(MYSQLI_ASSOC)]);
}

function network_team_volume() {
    $db  = getDB();
    $uid = authMember();

    $stmt = $db->prepare("SELECT * FROM volume_status WHERE customer_id = ? ORDER BY created_at DESC LIMIT 30");
    $stmt->bind_param("s", $uid);
    $stmt->execute();
    respond(200, ['success' => true, 'data' => $stmt->get_result()->fetch_all(MYSQLI_ASSOC)]);
}



// ============================================================
// =================== ORDERS MODULE ==========================
// ============================================================

function orders_create() {
    $db   = getDB();
    $uid  = authMember();
    $data = body();
    required_fields($data, ['items', 'shipping_address']);

    if (empty($data['items'])) respond(400, ['error' => 'Cart is empty']);

    $db->begin_transaction();
    try {
        $total = 0;
        $total_bv = 0;
        $items_validated = [];

        foreach ($data['items'] as $item) {
            $stmt = $db->prepare("SELECT id, name, price, member_price, bv, stock FROM products WHERE id = ? AND is_active = 1");
            $stmt->bind_param("i", $item['product_id']);
            $stmt->execute();
            $product = $stmt->get_result()->fetch_assoc();
            if (!$product) throw new Exception("Product ID {$item['product_id']} not found");
            if ($product['stock'] < $item['quantity']) throw new Exception("{$product['name']} is out of stock");

            // Member gets discounted price (retail profit logic)
            $price = $product['member_price'] ?? $product['price'];
            $retail_profit = ($product['price'] - $price) * $item['quantity'];
            $subtotal = $price * $item['quantity'];
            $bv = ($product['bv'] ?? 0) * $item['quantity'];

            $total    += $subtotal;
            $total_bv += $bv;
            $items_validated[] = [
                'product_id'    => $product['id'],
                'name'          => $product['name'],
                'quantity'      => $item['quantity'],
                'price'         => $price,
                'mrp'           => $product['price'],
                'retail_profit' => $retail_profit,
                'bv'            => $bv,
                'subtotal'      => $subtotal,
            ];

            // Reduce stock
            $db->prepare("UPDATE products SET stock = stock - ? WHERE id = ?")->execute() ||
            $db->query("UPDATE products SET stock = stock - {$item['quantity']} WHERE id = {$product['id']}");
        }

        $order_id = 'ORD' . time() . rand(100, 999);

        $stmt = $db->prepare("INSERT INTO orders (order_id, customer_id, total_amount, total_bv, payment_method, payment_status, shipping_address, status, created_at) VALUES (?,?,?,?,?,?,?,?,NOW())");
        $payment_method = $data['payment_method'] ?? 'online';
        $payment_status = $data['payment_status'] ?? 'pending';
        $status = 'pending';
        $shipping_address = json_encode($data['shipping_address']);
        $stmt->bind_param("ssddsss s", $order_id, $uid, $total, $total_bv, $payment_method, $payment_status, $shipping_address, $status);
        $stmt->execute();
        $order_db_id = $db->insert_id;

        foreach ($items_validated as $item) {
            $stmt = $db->prepare("INSERT INTO order_items (order_id, product_id, product_name, quantity, price, mrp, bv, subtotal, retail_profit) VALUES (?,?,?,?,?,?,?,?,?)");
            $stmt->bind_param("sisidddd d", $order_id, $item['product_id'], $item['name'], $item['quantity'], $item['price'], $item['mrp'], $item['bv'], $item['subtotal'], $item['retail_profit']);
            $stmt->execute();
        }

        // Create order status record
        $db->query("INSERT INTO order_status (order_id, status, updated_at) VALUES ('$order_id', 'pending', NOW())");

        // Shipping details
        $stmt = $db->prepare("INSERT INTO shipping_details (order_id, customer_id, address, created_at) VALUES (?,?,?,NOW())");
        $stmt->bind_param("sss", $order_id, $uid, $shipping_address);
        $stmt->execute();

        // Retail profit entry (pending until order delivered)
        if ($total > 0) {
            foreach ($items_validated as $item) {
                if ($item['retail_profit'] > 0) {
                    $stmt = $db->prepare("INSERT INTO retail_profit_income_report (customer_id, order_id, product_id, amount, status, created_at) VALUES (?,?,?,?,'pending',NOW())");
                    $stmt->bind_param("ssid", $uid, $order_id, $item['product_id'], $item['retail_profit']);
                    $stmt->execute();
                }
            }
        }

        // Add to my_order_list
        $stmt = $db->prepare("INSERT INTO my_order_list (customer_id, order_id, total_amount, total_bv, status, created_at) VALUES (?,?,?,?,'pending',NOW())");
        $stmt->bind_param("ssdd", $uid, $order_id, $total, $total_bv);
        $stmt->execute();

        $db->commit();

        respond(201, [
            'success'  => true,
            'message'  => 'Order placed successfully',
            'order_id' => $order_id,
            'total'    => $total,
            'total_bv' => $total_bv,
        ]);
    } catch (Exception $e) {
        $db->rollback();
        respond(500, ['error' => $e->getMessage()]);
    }
}

function orders_list() {
    $db  = getDB();
    $uid = authMember();
    $page = (int)($_GET['page'] ?? 1);
    $limit = 10;
    $offset = ($page - 1) * $limit;

    $stmt = $db->prepare("SELECT o.*, os.status as current_status FROM orders o 
        LEFT JOIN order_status os ON o.order_id = os.order_id
        WHERE o.customer_id = ? ORDER BY o.created_at DESC LIMIT ? OFFSET ?");
    $stmt->bind_param("sii", $uid, $limit, $offset);
    $stmt->execute();
    $orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    $count_stmt = $db->prepare("SELECT COUNT(*) as total FROM orders WHERE customer_id = ?");
    $count_stmt->bind_param("s", $uid);
    $count_stmt->execute();
    $total = $count_stmt->get_result()->fetch_assoc()['total'];

    respond(200, ['success' => true, 'data' => $orders, 'total' => $total, 'page' => $page]);
}

function orders_detail() {
    $db  = getDB();
    $uid = authMember();
    $order_id = $_GET['order_id'] ?? '';
    if (!$order_id) respond(400, ['error' => 'order_id required']);

    $stmt = $db->prepare("SELECT * FROM orders WHERE order_id = ? AND customer_id = ?");
    $stmt->bind_param("ss", $order_id, $uid);
    $stmt->execute();
    $order = $stmt->get_result()->fetch_assoc();
    if (!$order) respond(404, ['error' => 'Order not found']);

    $stmt2 = $db->prepare("SELECT * FROM order_items WHERE order_id = ?");
    $stmt2->bind_param("s", $order_id);
    $stmt2->execute();
    $order['items'] = $stmt2->get_result()->fetch_all(MYSQLI_ASSOC);

    $stmt3 = $db->prepare("SELECT * FROM shipping_details WHERE order_id = ?");
    $stmt3->bind_param("s", $order_id);
    $stmt3->execute();
    $order['shipping'] = $stmt3->get_result()->fetch_assoc();

    respond(200, ['success' => true, 'data' => $order]);
}

function orders_status() {
    $db  = getDB();
    $uid = authMember();
    $order_id = $_GET['order_id'] ?? '';
    if (!$order_id) respond(400, ['error' => 'order_id required']);

    $stmt = $db->prepare("SELECT os.*, d.tracking_number, d.courier FROM order_status os 
        LEFT JOIN dispatches d ON os.order_id = d.order_id
        WHERE os.order_id = ?");
    $stmt->bind_param("s", $order_id);
    $stmt->execute();
    respond(200, ['success' => true, 'data' => $stmt->get_result()->fetch_all(MYSQLI_ASSOC)]);
}

// ============================================================
// =================== REPURCHASE MODULE ======================
// ============================================================

function repurchase_create() {
    $db   = getDB();
    $uid  = authMember();
    $data = body();
    required_fields($data, ['items']);

    // Same as normal order but logged as repurchase for BV tracking
    $total_bv = 0;
    $total    = 0;

    $db->begin_transaction();
    try {
        $order_id = 'RPO' . time() . rand(100, 999);

        foreach ($data['items'] as $item) {
            $stmt = $db->prepare("SELECT id, name, price, member_price, bv FROM products WHERE id = ? AND is_active = 1");
            $stmt->bind_param("i", $item['product_id']);
            $stmt->execute();
            $product = $stmt->get_result()->fetch_assoc();
            if (!$product) throw new Exception("Product not found");

            $price    = $product['member_price'] ?? $product['price'];
            $bv       = ($product['bv'] ?? 0) * $item['quantity'];
            $subtotal = $price * $item['quantity'];
            $total   += $subtotal;
            $total_bv += $bv;
        }

        $stmt = $db->prepare("INSERT INTO repurchase_orders (order_id, customer_id, total_amount, total_bv, status, created_at) VALUES (?,?,?,?,'pending',NOW())");
        $stmt->bind_param("ssdd", $order_id, $uid, $total, $total_bv);
        $stmt->execute();

        // Update volume status (for repurchase matching bonus)
        $stmt2 = $db->prepare("INSERT INTO volume_status (customer_id, bv, order_id, cycle, created_at) VALUES (?,?,?,?,NOW())");
        $cycle = date('Y-m') . '-' . (date('d') <= 15 ? '1' : '2');
        $stmt2->bind_param("sdss", $uid, $total_bv, $order_id, $cycle);
        $stmt2->execute();

        $db->commit();
        respond(201, ['success' => true, 'message' => 'Repurchase order placed', 'order_id' => $order_id, 'total_bv' => $total_bv]);
    } catch (Exception $e) {
        $db->rollback();
        respond(500, ['error' => $e->getMessage()]);
    }
}

function repurchase_list() {
    $db  = getDB();
    $uid = authMember();
    $stmt = $db->prepare("SELECT * FROM repurchase_orders WHERE customer_id = ? ORDER BY created_at DESC");
    $stmt->bind_param("s", $uid);
    $stmt->execute();
    respond(200, ['success' => true, 'data' => $stmt->get_result()->fetch_all(MYSQLI_ASSOC)]);
}

// ============================================================
// =================== WALLET MODULE ==========================
// ============================================================

function wallet_balance() {
    $db  = getDB();
    $uid = authMember();
    $stmt = $db->prepare("SELECT * FROM wallet_balance WHERE customer_id = ?");
    $stmt->bind_param("s", $uid);
    $stmt->execute();
    respond(200, ['success' => true, 'data' => $stmt->get_result()->fetch_assoc()]);
}

function wallet_withdraw() {
    $db   = getDB();
    $uid  = authMember();
    $data = body();
    required_fields($data, ['amount', 'txn_password']);

    // Verify txn password
    $stmt = $db->prepare("SELECT txn_password FROM signup_users WHERE customer_id = ?");
    $stmt->bind_param("s", $uid);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    if (!password_verify($data['txn_password'], $user['txn_password'])) {
        respond(400, ['error' => 'Invalid transaction password']);
    }

    $amount = (float)$data['amount'];
    if ($amount < 100) respond(400, ['error' => 'Minimum withdrawal is ₹100']);

    $bal = $db->prepare("SELECT balance FROM wallet_balance WHERE customer_id = ?");
    $bal->bind_param("s", $uid);
    $bal->execute();
    $balance = $bal->get_result()->fetch_assoc()['balance'] ?? 0;

    if ($balance < $amount) respond(400, ['error' => 'Insufficient balance']);

    // TDS deduction (5% if PAN verified, 30% if not)
    $kyc = $db->prepare("SELECT kyc_status, pan_number FROM member_bank_kyc_details WHERE customer_id = ?");
    $kyc->bind_param("s", $uid);
    $kyc->execute();
    $kyc_data = $kyc->get_result()->fetch_assoc();

    $tds_rate = ($kyc_data && $kyc_data['kyc_status'] === 'verified' && $kyc_data['pan_number']) ? 0.05 : 0.30;
    $tds      = $amount * $tds_rate;
    $net      = $amount - $tds;

    $db->begin_transaction();
    try {
        $db->prepare("UPDATE wallet_balance SET balance = balance - ?, updated_at = NOW() WHERE customer_id = ?")->execute();
        $db->query("UPDATE wallet_balance SET balance = balance - $amount, updated_at = NOW() WHERE customer_id = '$uid'");

        $stmt = $db->prepare("INSERT INTO wallet_requests (customer_id, amount, tds_amount, net_amount, tds_rate, status, requested_at) VALUES (?,?,?,?,?,'pending',NOW())");
        $stmt->bind_param("sdddd", $uid, $amount, $tds, $net, $tds_rate);
        $stmt->execute();

        $db->commit();
        respond(200, ['success' => true, 'message' => 'Withdrawal request submitted', 'amount' => $amount, 'tds' => $tds, 'net_payable' => $net]);
    } catch (Exception $e) {
        $db->rollback();
        respond(500, ['error' => $e->getMessage()]);
    }
}

function wallet_history() {
    $db  = getDB();
    $uid = authMember();
    $stmt = $db->prepare("SELECT * FROM wallet_requests WHERE customer_id = ? ORDER BY requested_at DESC");
    $stmt->bind_param("s", $uid);
    $stmt->execute();
    respond(200, ['success' => true, 'data' => $stmt->get_result()->fetch_all(MYSQLI_ASSOC)]);
}

function wallet_cheque_request() {
    $db   = getDB();
    $uid  = authMember();

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $stmt = $db->prepare("SELECT * FROM cheque_requests WHERE customer_id = ? ORDER BY created_at DESC");
        $stmt->bind_param("s", $uid);
        $stmt->execute();
        respond(200, ['success' => true, 'data' => $stmt->get_result()->fetch_all(MYSQLI_ASSOC)]);
    }

    $data = body();
    required_fields($data, ['amount']);
    $stmt = $db->prepare("INSERT INTO cheque_requests (customer_id, amount, status, created_at) VALUES (?,?,'pending',NOW())");
    $stmt->bind_param("sd", $uid, $data['amount']);
    $stmt->execute();
    respond(201, ['success' => true, 'message' => 'Cheque request submitted']);
}

// ============================================================
// =================== INCOME REPORTS =========================
// ============================================================

function income_level_matching() {
    $db  = getDB();
    $uid = authMember();
    $stmt = $db->prepare("SELECT * FROM level_matching_income_report WHERE customer_id = ? ORDER BY created_at DESC");
    $stmt->bind_param("s", $uid);
    $stmt->execute();
    $data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $total = array_sum(array_column($data, 'amount'));
    respond(200, ['success' => true, 'data' => $data, 'total' => $total]);
}

function income_club_bonus() {
    $db  = getDB();
    $uid = authMember();
    $stmt = $db->prepare("SELECT * FROM club_bonus_income_report WHERE customer_id = ? ORDER BY created_at DESC");
    $stmt->bind_param("s", $uid);
    $stmt->execute();
    $data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    respond(200, ['success' => true, 'data' => $data, 'total' => array_sum(array_column($data, 'amount'))]);
}

function income_referral_bonus() {
    $db  = getDB();
    $uid = authMember();
    $stmt = $db->prepare("SELECT * FROM referral_bonus_income_report WHERE customer_id = ? ORDER BY created_at DESC");
    $stmt->bind_param("s", $uid);
    $stmt->execute();
    $data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    respond(200, ['success' => true, 'data' => $data, 'total' => array_sum(array_column($data, 'amount'))]);
}

function income_retail_profit() {
    $db  = getDB();
    $uid = authMember();
    $stmt = $db->prepare("SELECT * FROM retail_profit_income_report WHERE customer_id = ? ORDER BY created_at DESC");
    $stmt->bind_param("s", $uid);
    $stmt->execute();
    $data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    respond(200, ['success' => true, 'data' => $data, 'total' => array_sum(array_column($data, 'amount'))]);
}

function income_reward() {
    $db  = getDB();
    $uid = authMember();
    $stmt = $db->prepare("SELECT * FROM reward_income_report WHERE customer_id = ? ORDER BY created_at DESC");
    $stmt->bind_param("s", $uid);
    $stmt->execute();
    $data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    respond(200, ['success' => true, 'data' => $data]);
}

function income_repurchase_matching() {
    $db  = getDB();
    $uid = authMember();
    $stmt = $db->prepare("SELECT * FROM reward_bonus_income_report WHERE customer_id = ? ORDER BY created_at DESC");
    $stmt->bind_param("s", $uid);
    $stmt->execute();
    $data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    respond(200, ['success' => true, 'data' => $data, 'total' => array_sum(array_column($data, 'amount'))]);
}

// ============================================================
// =================== CLUB & REWARD ==========================
// ============================================================

function club_status() {
    $db  = getDB();
    $uid = authMember();
    $stmt = $db->prepare("SELECT * FROM club_bonus_status WHERE customer_id = ?");
    $stmt->bind_param("s", $uid);
    $stmt->execute();
    respond(200, ['success' => true, 'data' => $stmt->get_result()->fetch_assoc()]);
}

function club_achievers() {
    $db = getDB();
    $club = $_GET['club'] ?? '';
    $sql = "SELECT ca.*, u.name, u.customer_id FROM club_achievers ca 
            LEFT JOIN signup_users u ON ca.customer_id = u.customer_id";
    if ($club) $sql .= " WHERE ca.club_number = " . (int)$club;
    $sql .= " ORDER BY ca.achieved_date DESC LIMIT 50";
    $result = $db->query($sql);
    respond(200, ['success' => true, 'data' => $result->fetch_all(MYSQLI_ASSOC)]);
}

function reward_status() {
    $db  = getDB();
    $uid = authMember();
    $stmt = $db->prepare("SELECT rs.*, rm.reward_name, rm.budget FROM reward_status rs 
        LEFT JOIN reward_master rm ON rs.club_number = rm.club_number
        WHERE rs.customer_id = ?");
    $stmt->bind_param("s", $uid);
    $stmt->execute();
    respond(200, ['success' => true, 'data' => $stmt->get_result()->fetch_all(MYSQLI_ASSOC)]);
}

function reward_master() {
    $db = getDB();
    $result = $db->query("SELECT * FROM reward_master ORDER BY club_number ASC");
    respond(200, ['success' => true, 'data' => $result->fetch_all(MYSQLI_ASSOC)]);
}

// ============================================================
// =================== MESSAGES MODULE ========================
// ============================================================

function messages_inbox() {
    $db  = getDB();
    $uid = authMember();
    $stmt = $db->prepare("SELECT * FROM inbox_messages WHERE receiver_id = ? ORDER BY created_at DESC");
    $stmt->bind_param("s", $uid);
    $stmt->execute();
    respond(200, ['success' => true, 'data' => $stmt->get_result()->fetch_all(MYSQLI_ASSOC)]);
}

function messages_send() {
    $db   = getDB();
    $uid  = authMember();
    $data = body();
    required_fields($data, ['receiver_id', 'message']);

    $stmt = $db->prepare("INSERT INTO member_messages (sender_id, receiver_id, message, is_read, created_at) VALUES (?,?,?,0,NOW())");
    $stmt->bind_param("sss", $uid, $data['receiver_id'], $data['message']);
    $stmt->execute();

    $stmt2 = $db->prepare("INSERT INTO inbox_messages (sender_id, receiver_id, message, is_read, created_at) VALUES (?,?,?,0,NOW())");
    $stmt2->bind_param("sss", $uid, $data['receiver_id'], $data['message']);
    $stmt2->execute();

    respond(201, ['success' => true, 'message' => 'Message sent']);
}

function messages_thread() {
    $db  = getDB();
    $uid = authMember();
    $with = $_GET['with'] ?? '';
    if (!$with) respond(400, ['error' => 'with parameter required']);

    $stmt = $db->prepare("SELECT * FROM member_messages 
        WHERE (sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?)
        ORDER BY created_at ASC");
    $stmt->bind_param("ssss", $uid, $with, $with, $uid);
    $stmt->execute();
    respond(200, ['success' => true, 'data' => $stmt->get_result()->fetch_all(MYSQLI_ASSOC)]);
}

// ============================================================
// =================== PAYOUT MODULE ==========================
// ============================================================

function payout_processing() {
    $db  = getDB();
    $uid = authMember();
    $stmt = $db->prepare("SELECT * FROM payout_processing WHERE customer_id = ? ORDER BY created_at DESC");
    $stmt->bind_param("s", $uid);
    $stmt->execute();
    respond(200, ['success' => true, 'data' => $stmt->get_result()->fetch_all(MYSQLI_ASSOC)]);
}

function payout_closing() {
    $db  = getDB();
    $uid = authMember();
    $stmt = $db->prepare("SELECT * FROM payout_closing WHERE customer_id = ? ORDER BY created_at DESC");
    $stmt->bind_param("s", $uid);
    $stmt->execute();
    respond(200, ['success' => true, 'data' => $stmt->get_result()->fetch_all(MYSQLI_ASSOC)]);
}

// ============================================================
// =================== ADMIN MODULE ===========================
// ============================================================

function admin_dashboard() {
    authAdmin();
    $db = getDB();

    $stats = [
        'total_members'  => $db->query("SELECT COUNT(*) as c FROM signup_users")->fetch_assoc()['c'],
        'active_members' => $db->query("SELECT COUNT(*) as c FROM signup_users WHERE is_active=1")->fetch_assoc()['c'],
        'total_orders'   => $db->query("SELECT COUNT(*) as c FROM orders")->fetch_assoc()['c'],
        'pending_orders' => $db->query("SELECT COUNT(*) as c FROM orders WHERE status='pending'")->fetch_assoc()['c'],
        'total_products' => $db->query("SELECT COUNT(*) as c FROM products WHERE is_active=1")->fetch_assoc()['c'],
        'total_revenue'  => $db->query("SELECT COALESCE(SUM(total_amount),0) as s FROM orders WHERE payment_status='paid'")->fetch_assoc()['s'],
        'pending_kyc'    => $db->query("SELECT COUNT(*) as c FROM member_bank_kyc_details WHERE kyc_status='pending'")->fetch_assoc()['c'],
        'pending_withdrawal' => $db->query("SELECT COUNT(*) as c FROM wallet_requests WHERE status='pending'")->fetch_assoc()['c'],
    ];

    respond(200, ['success' => true, 'data' => $stats]);
}

function admin_members() {
    authAdmin();
    $db = getDB();
    $page   = (int)($_GET['page'] ?? 1);
    $limit  = 20;
    $offset = ($page - 1) * $limit;
    $search = $_GET['search'] ?? '';
    $active = $_GET['is_active'] ?? '';

    $sql = "SELECT customer_id, name, mobile, email, sponsor_id, joining_date, is_active, referral_code FROM signup_users WHERE 1=1";
    if ($search) $sql .= " AND (name LIKE '%$search%' OR customer_id LIKE '%$search%' OR mobile LIKE '%$search%')";
    if ($active !== '') $sql .= " AND is_active = " . (int)$active;
    $sql .= " ORDER BY joining_date DESC LIMIT $limit OFFSET $offset";

    $result = $db->query($sql);
    $total  = $db->query("SELECT COUNT(*) as c FROM signup_users")->fetch_assoc()['c'];
    respond(200, ['success' => true, 'data' => $result->fetch_all(MYSQLI_ASSOC), 'total' => $total, 'page' => $page]);
}

function admin_member_detail() {
    authAdmin();
    $db  = getDB();
    $cid = $_GET['customer_id'] ?? '';
    if (!$cid) respond(400, ['error' => 'customer_id required']);

    $stmt = $db->prepare("SELECT u.*, p.*, b.balance, k.kyc_status, k.bank_name FROM signup_users u 
        LEFT JOIN member_profiles p ON u.customer_id = p.customer_id
        LEFT JOIN wallet_balance b ON u.customer_id = b.customer_id
        LEFT JOIN member_bank_kyc_details k ON u.customer_id = k.customer_id
        WHERE u.customer_id = ?");
    $stmt->bind_param("s", $cid);
    $stmt->execute();
    $member = $stmt->get_result()->fetch_assoc();
    if (!$member) respond(404, ['error' => 'Member not found']);
    unset($member['password'], $member['txn_password']);
    respond(200, ['success' => true, 'data' => $member]);
}

function admin_activate_member() {
    authAdmin();
    $db   = getDB();
    $data = body();
    required_fields($data, ['customer_id', 'is_active']);

    $stmt = $db->prepare("UPDATE signup_users SET is_active = ?, activated_at = NOW() WHERE customer_id = ?");
    $stmt->bind_param("is", $data['is_active'], $data['customer_id']);
    $stmt->execute();

    if ($data['is_active'] == 1) {
        // Create wallet if not exists
        $db->query("INSERT IGNORE INTO wallet_balance (customer_id, balance, updated_at) VALUES ('{$data['customer_id']}', 0, NOW())");
        // Initialize club bonus status
        $db->query("INSERT IGNORE INTO club_bonus_status (customer_id, current_club, left_pairs, right_pairs, total_pairs, updated_at) VALUES ('{$data['customer_id']}', 0, 0, 0, 0, NOW())");
    }

    respond(200, ['success' => true, 'message' => 'Member status updated']);
}

function admin_orders() {
    authAdmin();
    $db     = getDB();
    $page   = (int)($_GET['page'] ?? 1);
    $limit  = 20;
    $offset = ($page - 1) * $limit;
    $status = $_GET['status'] ?? '';

    $sql = "SELECT o.*, u.name as member_name FROM orders o LEFT JOIN signup_users u ON o.customer_id = u.customer_id WHERE 1=1";
    if ($status) $sql .= " AND o.status = '" . $db->real_escape_string($status) . "'";
    $sql .= " ORDER BY o.created_at DESC LIMIT $limit OFFSET $offset";

    $result = $db->query($sql);
    respond(200, ['success' => true, 'data' => $result->fetch_all(MYSQLI_ASSOC)]);
}

function admin_update_order_status() {
    authAdmin();
    $db   = getDB();
    $data = body();
    required_fields($data, ['order_id', 'status']);

    $allowed_statuses = ['pending', 'confirmed', 'packed', 'dispatched', 'delivered', 'cancelled'];
    if (!in_array($data['status'], $allowed_statuses)) respond(400, ['error' => 'Invalid status']);

    $stmt = $db->prepare("UPDATE orders SET status = ?, updated_at = NOW() WHERE order_id = ?");
    $stmt->bind_param("ss", $data['status'], $data['order_id']);
    $stmt->execute();

    $db->prepare("UPDATE order_status SET status = ?, updated_at = NOW() WHERE order_id = ?")->execute();
    $db->query("UPDATE order_status SET status = '{$data['status']}', updated_at = NOW() WHERE order_id = '{$data['order_id']}'");

    // If delivered → release retail profit
    if ($data['status'] === 'delivered') {
        $db->query("UPDATE retail_profit_income_report SET status='approved' WHERE order_id='{$data['order_id']}'");
        // Add retail profit to wallet
        $rp = $db->query("SELECT customer_id, SUM(amount) as total FROM retail_profit_income_report WHERE order_id='{$data['order_id']}' GROUP BY customer_id")->fetch_assoc();
        if ($rp && $rp['total'] > 0) {
            $db->query("UPDATE wallet_balance SET balance=balance+{$rp['total']}, updated_at=NOW() WHERE customer_id='{$rp['customer_id']}'");
        }
    }

    // If dispatched → add tracking
    if ($data['status'] === 'dispatched' && isset($data['tracking_number'])) {
        $stmt2 = $db->prepare("INSERT INTO dispatches (order_id, tracking_number, courier, dispatched_at) VALUES (?,?,?,NOW()) ON DUPLICATE KEY UPDATE tracking_number=?, courier=?");
        $courier = $data['courier'] ?? '';
        $stmt2->bind_param("sssss", $data['order_id'], $data['tracking_number'], $courier, $data['tracking_number'], $courier);
        $stmt2->execute();
    }

    respond(200, ['success' => true, 'message' => 'Order status updated']);
}

function admin_products_add() {
    authAdmin();
    $db   = getDB();
    $data = body();
    required_fields($data, ['name', 'price', 'bv']);

    $stmt = $db->prepare("INSERT INTO products (name, description, price, member_price, bv, stock, category, image_url, is_active, created_at) VALUES (?,?,?,?,?,?,?,?,1,NOW())");
    $member_price = $data['member_price'] ?? $data['price'];
    $stmt->bind_param("ssdddiis",
        $data['name'], $data['description'] ?? '', $data['price'], $member_price,
        $data['bv'], $data['stock'] ?? 0, $data['category'] ?? 'general', $data['image_url'] ?? ''
    );
    $stmt->execute();
    respond(201, ['success' => true, 'message' => 'Product added', 'id' => $db->insert_id]);
}

function admin_products_update() {
    authAdmin();
    $db   = getDB();
    $data = body();
    required_fields($data, ['id']);

    $fields = ['name', 'description', 'price', 'member_price', 'bv', 'stock', 'category', 'image_url', 'is_active'];
    $sets = []; $params = []; $types = '';
    foreach ($fields as $f) {
        if (isset($data[$f])) {
            $sets[]   = "$f = ?";
            $params[] = $data[$f];
            $types   .= (in_array($f, ['price','member_price','bv']) ? 'd' : (in_array($f, ['stock','is_active']) ? 'i' : 's'));
        }
    }
    if (empty($sets)) respond(400, ['error' => 'No fields to update']);
    $params[] = $data['id']; $types .= 'i';
    $stmt = $db->prepare("UPDATE products SET " . implode(', ', $sets) . " WHERE id = ?");
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    respond(200, ['success' => true, 'message' => 'Product updated']);
}

function admin_products_delete() {
    authAdmin();
    $db   = getDB();
    $data = body();
    required_fields($data, ['id']);
    $stmt = $db->prepare("UPDATE products SET is_active = 0 WHERE id = ?");
    $stmt->bind_param("i", $data['id']);
    $stmt->execute();
    respond(200, ['success' => true, 'message' => 'Product deactivated']);
}

function admin_cheque_requests() {
    authAdmin();
    $db = getDB();
    $status = $_GET['status'] ?? 'pending';
    $result = $db->query("SELECT cr.*, u.name, u.mobile FROM cheque_requests cr LEFT JOIN signup_users u ON cr.customer_id = u.customer_id WHERE cr.status = '" . $db->real_escape_string($status) . "' ORDER BY cr.created_at DESC");
    respond(200, ['success' => true, 'data' => $result->fetch_all(MYSQLI_ASSOC)]);
}

function admin_approve_cheque() {
    authAdmin();
    $db   = getDB();
    $data = body();
    required_fields($data, ['id', 'status']); // status: approved / rejected

    $stmt = $db->prepare("UPDATE cheque_requests SET status = ?, processed_at = NOW() WHERE id = ?");
    $stmt->bind_param("si", $data['status'], $data['id']);
    $stmt->execute();
    respond(200, ['success' => true, 'message' => 'Cheque request updated']);
}

function admin_kyc_verify() {
    authAdmin();
    $db   = getDB();
    $data = body();
    required_fields($data, ['customer_id', 'kyc_status']); // verified / rejected

    $stmt = $db->prepare("UPDATE member_bank_kyc_details SET kyc_status = ?, verified_at = NOW() WHERE customer_id = ?");
    $stmt->bind_param("ss", $data['kyc_status'], $data['customer_id']);
    $stmt->execute();
    respond(200, ['success' => true, 'message' => 'KYC status updated']);
}

function admin_dispatch() {
    authAdmin();
    $db   = getDB();
    $data = body();
    required_fields($data, ['order_id', 'tracking_number', 'courier']);

    $stmt = $db->prepare("INSERT INTO dispatches (order_id, tracking_number, courier, dispatched_at) VALUES (?,?,?,NOW())
        ON DUPLICATE KEY UPDATE tracking_number=?, courier=?, dispatched_at=NOW()");
    $stmt->bind_param("sssss", $data['order_id'], $data['tracking_number'], $data['courier'], $data['tracking_number'], $data['courier']);
    $stmt->execute();

    $db->query("UPDATE orders SET status='dispatched' WHERE order_id='{$data['order_id']}'");
    respond(200, ['success' => true, 'message' => 'Dispatch info saved']);
}

function admin_send_message() {
    authAdmin();
    $db   = getDB();
    $data = body();
    required_fields($data, ['receiver_id', 'message']);

    $sender = 'ADMIN';
    $stmt = $db->prepare("INSERT INTO inbox_messages (sender_id, receiver_id, message, is_read, created_at) VALUES (?,?,?,0,NOW())");
    $stmt->bind_param("sss", $sender, $data['receiver_id'], $data['message']);
    $stmt->execute();

    // Compose messages (broadcast support)
    $stmt2 = $db->prepare("INSERT INTO compose_messages (sender_id, receiver_id, message, created_at) VALUES (?,?,?,NOW())");
    $stmt2->bind_param("sss", $sender, $data['receiver_id'], $data['message']);
    $stmt2->execute();

    respond(201, ['success' => true, 'message' => 'Message sent to member']);
}

// ============================================================
// =================== BONUS CALCULATION ENGINES ==============
// ============================================================

/**
 * LEVEL MATCHING BONUS ENGINE
 * Binary tree: every matching pair (L+R) = ₹100
 * Rule: pairs counted up to 15 per day, minus club/referral/reward deductions
 */
function admin_run_level_matching() {
    authAdmin();
    $db    = getDB();
    $data  = body();
    $cycle = $data['cycle'] ?? date('Y-m-d');

    // Get all active members
    $members = $db->query("SELECT customer_id FROM signup_users WHERE is_active = 1")->fetch_all(MYSQLI_ASSOC);

    $db->begin_transaction();
    try {
        $processed = 0;
        foreach ($members as $m) {
            $cid = $m['customer_id'];

            // Get left and right BV accumulated
            $stmt = $db->prepare("SELECT 
                SUM(CASE WHEN u.position='L' THEN v.bv ELSE 0 END) as left_bv,
                SUM(CASE WHEN u.position='R' THEN v.bv ELSE 0 END) as right_bv
                FROM signup_users u
                LEFT JOIN volume_status v ON u.customer_id = v.customer_id
                WHERE u.placement_under = ? AND v.cycle = ?");
            $stmt->bind_param("ss", $cid, $cycle);
            $stmt->execute();
            $bv = $stmt->get_result()->fetch_assoc();

            $left_bv  = (float)($bv['left_bv']  ?? 0);
            $right_bv = (float)($bv['right_bv'] ?? 0);
            $pairs    = min($left_bv, $right_bv); // matching volume

            if ($pairs <= 0) continue;

            // 1 pair = 1 BV match = ₹100 per 1 BV match? No:
            // Per plan: EXT L - EXT R = 1 PER = 100 RS (1 pair = ₹100)
            // So if left has 5 new members and right has 3 = 3 pairs = ₹300
            // But we use BV for repurchase. For new joining: count new joins
            $left_joins  = (int)$db->query("SELECT COUNT(*) as c FROM signup_users WHERE placement_under='$cid' AND position='L' AND DATE(joining_date)='$cycle' AND is_active=1")->fetch_assoc()['c'];
            $right_joins = (int)$db->query("SELECT COUNT(*) as c FROM signup_users WHERE placement_under='$cid' AND position='R' AND DATE(joining_date)='$cycle' AND is_active=1")->fetch_assoc()['c'];

            $new_pairs = min($left_joins, $right_joins);
            if ($new_pairs <= 0) continue;

            // Cap at 15 pairs per day (per plan)
            $club_status = $db->query("SELECT current_club FROM club_bonus_status WHERE customer_id='$cid'")->fetch_assoc();
            $current_club = $club_status['current_club'] ?? 0;
            // Deduct 3,6,9,12,15 pairs for club/referral/reward
            $deduct_pairs = in_array($current_club, [3,6,9,12,15]) ? $current_club : 0;
            $eligible_pairs = max(0, min($new_pairs, 15) - $deduct_pairs);

            if ($eligible_pairs <= 0) continue;

            $amount = $eligible_pairs * 100;

            // Record income
            $stmt2 = $db->prepare("INSERT INTO level_matching_income_report (customer_id, pairs, amount, cycle, status, created_at) VALUES (?,?,?,'$cycle','pending',NOW())");
            $stmt2->bind_param("sid", $cid, $eligible_pairs, $amount);
            $stmt2->execute();

            // Update matching status
            $stmt3 = $db->prepare("INSERT INTO level_matching_bonus_status (customer_id, total_pairs, total_income, updated_at) VALUES (?,?,?,NOW())
                ON DUPLICATE KEY UPDATE total_pairs=total_pairs+?, total_income=total_income+?, updated_at=NOW()");
            $stmt3->bind_param("sdddd", $cid, $eligible_pairs, $amount, $eligible_pairs, $amount);
            $stmt3->execute();

            // Update club bonus status (cumulative pairs)
            $db->query("UPDATE club_bonus_status SET total_pairs=total_pairs+$eligible_pairs, left_pairs=left_pairs+$left_joins, right_pairs=right_pairs+$right_joins, updated_at=NOW() WHERE customer_id='$cid'");

            $processed++;
        }

        // Check and award club bonuses after updating pairs
        admin_check_club_bonus_internal($db);

        $db->commit();
        respond(200, ['success' => true, 'message' => "Level matching processed for $processed members", 'cycle' => $cycle]);
    } catch (Exception $e) {
        $db->rollback();
        respond(500, ['error' => $e->getMessage()]);
    }
}

/**
 * CLUB BONUS MASTER DATA (from PPT)
 */
function getClubBonusMaster() {
    return [
        1  => ['pairs' => 300,      'income' => 3000],
        2  => ['pairs' => 800,      'income' => 8000],
        3  => ['pairs' => 2000,     'income' => 20000],
        4  => ['pairs' => 5000,     'income' => 50000],
        5  => ['pairs' => 12000,    'income' => 120000],
        6  => ['pairs' => 30000,    'income' => 300000],
        7  => ['pairs' => 75000,    'income' => 750000],
        8  => ['pairs' => 185000,   'income' => 1850000],
        9  => ['pairs' => 450000,   'income' => 4500000],
        10 => ['pairs' => 1100000,  'income' => 11000000],
        11 => ['pairs' => 2500000,  'income' => 25000000],
        12 => ['pairs' => 6000000,  'income' => 60000000],
        13 => ['pairs' => 15000000, 'income' => 150000000],
        14 => ['pairs' => 37000000, 'income' => 370000000],
        15 => ['pairs' => 90000000, 'income' => 900000000],
    ];
}

function getReferralBonusMaster() {
    return [
        1  => ['referral' => 200,      'senior1' => 50,       'senior2' => 50],
        2  => ['referral' => 500,      'senior1' => 200,      'senior2' => 100],
        3  => ['referral' => 1200,     'senior1' => 500,      'senior2' => 300],
        4  => ['referral' => 3000,     'senior1' => 1200,     'senior2' => 800],
        5  => ['referral' => 7000,     'senior1' => 3000,     'senior2' => 2000],
        6  => ['referral' => 18000,    'senior1' => 7000,     'senior2' => 5000],
        7  => ['referral' => 45000,    'senior1' => 18000,    'senior2' => 12000],
        8  => ['referral' => 110000,   'senior1' => 45000,    'senior2' => 30000],
        9  => ['referral' => 250000,   'senior1' => 110000,   'senior2' => 70000],
        10 => ['referral' => 650000,   'senior1' => 250000,   'senior2' => 170000],
        11 => ['referral' => 1500000,  'senior1' => 600000,   'senior2' => 350000],
        12 => ['referral' => 3600000,  'senior1' => 1500000,  'senior2' => 900000],
        13 => ['referral' => 9000000,  'senior1' => 3500000,  'senior2' => 2000000],
        14 => ['referral' => 21000000, 'senior1' => 8000000,  'senior2' => 5000000],
        15 => ['referral' => 50000000, 'senior1' => 20000000, 'senior2' => 12500000],
    ];
}

function getRewardMaster() {
    return [
        1  => ['reward' => 'Book, Pen, Bench Kit',     'budget' => 400],
        2  => ['reward' => 'Leather Bag & Kit Bag',    'budget' => 1200],
        3  => ['reward' => 'Travel Bag',               'budget' => 3000],
        4  => ['reward' => 'Goa Tour',                 'budget' => 8000],
        5  => ['reward' => 'Mobile Phone',             'budget' => 18000],
        6  => ['reward' => 'Laptop',                   'budget' => 45000],
        7  => ['reward' => 'Bike',                     'budget' => 115000],
        8  => ['reward' => 'Gold',                     'budget' => 290000],
        9  => ['reward' => 'Car Nexon',                'budget' => 700000],
        10 => ['reward' => 'Car KIA',                  'budget' => 1900000],
        11 => ['reward' => 'Car Mercedes Benz',        'budget' => 4000000],
        12 => ['reward' => 'Home',                     'budget' => 9000000],
        13 => ['reward' => 'Bungalow',                 'budget' => 23000000],
        14 => ['reward' => 'Farm House',               'budget' => 58000000],
        15 => ['reward' => 'Balance',                  'budget' => 135000000],
    ];
}

function admin_check_club_bonus_internal($db) {
    $club_master    = getClubBonusMaster();
    $referral_master = getReferralBonusMaster();
    $reward_master  = getRewardMaster();

    $members = $db->query("SELECT cbs.customer_id, cbs.total_pairs, cbs.current_club 
        FROM club_bonus_status cbs 
        INNER JOIN signup_users u ON cbs.customer_id = u.customer_id WHERE u.is_active=1")->fetch_all(MYSQLI_ASSOC);

    foreach ($members as $m) {
        $cid          = $m['customer_id'];
        $total_pairs  = (int)$m['total_pairs'];
        $current_club = (int)$m['current_club'];

        for ($club = $current_club + 1; $club <= 15; $club++) {
            if (!isset($club_master[$club])) break;
            if ($total_pairs < $club_master[$club]['pairs']) break;

            $club_income = $club_master[$club]['income'];

            // Award club bonus
            $db->query("INSERT INTO club_bonus_income_report (customer_id, club_number, amount, status, created_at) 
                VALUES ('$cid', $club, $club_income, 'approved', NOW())");
            $db->query("UPDATE wallet_balance SET balance=balance+$club_income, updated_at=NOW() WHERE customer_id='$cid'");
            $db->query("UPDATE club_bonus_status SET current_club=$club, updated_at=NOW() WHERE customer_id='$cid'");
            $db->query("INSERT INTO club_achievers (customer_id, club_number, achieved_date) VALUES ('$cid', $club, NOW())");

            // Award referral bonus to member + 2 uplines
            $ref_data = $referral_master[$club];
            $db->query("INSERT INTO referral_bonus_income_report (customer_id, club_number, amount, type, status, created_at) 
                VALUES ('$cid', $club, {$ref_data['referral']}, 'self', 'approved', NOW())");
            $db->query("UPDATE wallet_balance SET balance=balance+{$ref_data['referral']}, updated_at=NOW() WHERE customer_id='$cid'");

            // Senior 1 (direct sponsor)
            $s1 = $db->query("SELECT sponsor_id FROM signup_users WHERE customer_id='$cid'")->fetch_assoc();
            if ($s1 && $s1['sponsor_id']) {
                $db->query("INSERT INTO referral_bonus_income_report (customer_id, club_number, amount, type, from_member, status, created_at) 
                    VALUES ('{$s1['sponsor_id']}', $club, {$ref_data['senior1']}, 'senior1', '$cid', 'approved', NOW())");
                $db->query("UPDATE wallet_balance SET balance=balance+{$ref_data['senior1']}, updated_at=NOW() WHERE customer_id='{$s1['sponsor_id']}'");

                // Senior 2 (sponsor's sponsor)
                $s2 = $db->query("SELECT sponsor_id FROM signup_users WHERE customer_id='{$s1['sponsor_id']}'")->fetch_assoc();
                if ($s2 && $s2['sponsor_id']) {
                    $db->query("INSERT INTO referral_bonus_income_report (customer_id, club_number, amount, type, from_member, status, created_at) 
                        VALUES ('{$s2['sponsor_id']}', $club, {$ref_data['senior2']}, 'senior2', '$cid', 'approved', NOW())");
                    $db->query("UPDATE wallet_balance SET balance=balance+{$ref_data['senior2']}, updated_at=NOW() WHERE customer_id='{$s2['sponsor_id']}'");
                }
            }

            // Award Reward
            $reward = $reward_master[$club];
            $db->query("INSERT INTO reward_status (customer_id, club_number, reward_name, budget, status, created_at) 
                VALUES ('$cid', $club, '{$reward['reward']}', {$reward['budget']}, 'pending', NOW())");
            $db->query("INSERT INTO reward_income_report (customer_id, club_number, reward_name, amount, status, created_at) 
                VALUES ('$cid', $club, '{$reward['reward']}', {$reward['budget']}, 'approved', NOW())");

            // Update referral bonus status
            $db->query("INSERT INTO referral_bonus_status (customer_id, current_club, updated_at) VALUES ('$cid', $club, NOW())
                ON DUPLICATE KEY UPDATE current_club=$club, updated_at=NOW()");
        }
    }
}

function admin_run_club_bonus() {
    authAdmin();
    $db = getDB();
    $db->begin_transaction();
    try {
        admin_check_club_bonus_internal($db);
        $db->commit();
        respond(200, ['success' => true, 'message' => 'Club bonus calculation completed']);
    } catch (Exception $e) {
        $db->rollback();
        respond(500, ['error' => $e->getMessage()]);
    }
}

function admin_run_referral_bonus() {
    authAdmin();
    // Referral bonus is triggered automatically during club bonus
    respond(200, ['success' => true, 'message' => 'Referral bonus is auto-calculated during club bonus run']);
}

/**
 * REPURCHASE MATCHING BONUS ENGINE
 * Rule: 1 BV L + 1 BV R = 1 matching BV
 * FAST: 100 BV L - 200 BV R (or vice versa) = 100 matching BV
 * Daily cap: 4000 BV
 * Payout: every 15 days (1-15 and 16-end)
 */
function admin_run_repurchase() {
    authAdmin();
    $db   = getDB();
    $data = body();
    $cycle = $data['cycle'] ?? (date('Y-m') . '-' . (date('d') <= 15 ? '1' : '2'));

    $members = $db->query("SELECT customer_id FROM signup_users WHERE is_active=1")->fetch_all(MYSQLI_ASSOC);

    $db->begin_transaction();
    try {
        $processed = 0;
        foreach ($members as $m) {
            $cid = $m['customer_id'];

            // Get left and right BV for this cycle
            $stmt = $db->prepare("SELECT 
                SUM(CASE WHEN u.position='L' THEN vs.bv ELSE 0 END) as left_bv,
                SUM(CASE WHEN u.position='R' THEN vs.bv ELSE 0 END) as right_bv
                FROM signup_users u
                INNER JOIN volume_status vs ON u.customer_id = vs.customer_id
                WHERE u.placement_under = ? AND vs.cycle = ?");
            $stmt->bind_param("ss", $cid, $cycle);
            $stmt->execute();
            $bv = $stmt->get_result()->fetch_assoc();

            $left_bv  = (float)($bv['left_bv']  ?? 0);
            $right_bv = (float)($bv['right_bv'] ?? 0);

            if ($left_bv <= 0 && $right_bv <= 0) continue;

            // Matching logic
            $matching_bv = min($left_bv, $right_bv);

            // Daily cap: 4000 BV (but this is per cycle, so spread across days)
            // Cap for half-month cycle: 4000 * 15 = 60000 BV max
            $cycle_cap = 60000;
            $matching_bv = min($matching_bv, $cycle_cap);

            if ($matching_bv <= 0) continue;

            $amount = $matching_bv * 1; // 1 BV = 1 RS

            $stmt2 = $db->prepare("INSERT INTO reward_bonus_income_report (customer_id, matching_bv, left_bv, right_bv, amount, cycle, status, created_at) 
                VALUES (?,?,?,?,?,'$cycle','pending',NOW())");
            $stmt2->bind_param("sdddd", $cid, $matching_bv, $left_bv, $right_bv, $amount);
            $stmt2->execute();

            $processed++;
        }

        $db->commit();
        respond(200, ['success' => true, 'message' => "Repurchase matching processed for $processed members", 'cycle' => $cycle]);
    } catch (Exception $e) {
        $db->rollback();
        respond(500, ['error' => $e->getMessage()]);
    }
}

/**
 * PAYOUT CLOSE — releases all pending income to wallets
 */
function admin_payout_close() {
    authAdmin();
    $db   = getDB();
    $data = body();
    $cycle = $data['cycle'] ?? date('Y-m');

    $db->begin_transaction();
    try {
        // Level matching
        $lm = $db->query("SELECT customer_id, SUM(amount) as total FROM level_matching_income_report WHERE status='pending' GROUP BY customer_id");
        while ($row = $lm->fetch_assoc()) {
            $db->query("UPDATE wallet_balance SET balance=balance+{$row['total']}, updated_at=NOW() WHERE customer_id='{$row['customer_id']}'");
            $db->query("INSERT INTO payout_processing (customer_id, type, amount, cycle, status, created_at) VALUES ('{$row['customer_id']}','level_matching',{$row['total']},'$cycle','processed',NOW())");
        }
        $db->query("UPDATE level_matching_income_report SET status='paid' WHERE status='pending'");

        // Repurchase matching
        $rpm = $db->query("SELECT customer_id, SUM(amount) as total FROM reward_bonus_income_report WHERE status='pending' GROUP BY customer_id");
        while ($row = $rpm->fetch_assoc()) {
            $db->query("UPDATE wallet_balance SET balance=balance+{$row['total']}, updated_at=NOW() WHERE customer_id='{$row['customer_id']}'");
            $db->query("INSERT INTO payout_processing (customer_id, type, amount, cycle, status, created_at) VALUES ('{$row['customer_id']}','repurchase_matching',{$row['total']},'$cycle','processed',NOW())");
        }
        $db->query("UPDATE reward_bonus_income_report SET status='paid' WHERE status='pending'");

        // Retail profit (only for delivered orders)
        $rp = $db->query("SELECT customer_id, SUM(amount) as total FROM retail_profit_income_report WHERE status='approved' GROUP BY customer_id");
        while ($row = $rp->fetch_assoc()) {
            $db->query("INSERT INTO payout_processing (customer_id, type, amount, cycle, status, created_at) VALUES ('{$row['customer_id']}','retail_profit',{$row['total']},'$cycle','processed',NOW())");
        }
        $db->query("UPDATE retail_profit_income_report SET status='paid' WHERE status='approved'");

        // Create payout_closing record
        $total_payout = (float)$db->query("SELECT COALESCE(SUM(amount),0) as t FROM payout_processing WHERE cycle='$cycle' AND status='processed'")->fetch_assoc()['t'];
        $db->query("INSERT INTO payout_closing (cycle, total_payout, closed_at, status) VALUES ('$cycle', $total_payout, NOW(), 'closed')");

        $db->commit();
        respond(200, ['success' => true, 'message' => 'Payout closed successfully', 'cycle' => $cycle, 'total_payout' => $total_payout]);
    } catch (Exception $e) {
        $db->rollback();
        respond(500, ['error' => $e->getMessage()]);
    }
}