<?php
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../config/db.php';
if (!isset($conn) || !($conn instanceof mysqli)) {
    echo json_encode(['success'=>false,'message'=>'DB connection failed']); exit;
}

/* resolve franchise id */
$franchise_id = 0;
foreach (['franchise_id','franchies_id','franchise_db_id',
          'mobile_franchise_id','city_franchies_holder_id','state_franchies_holder_id'] as $k) {
    if (!empty($_SESSION[$k]) && is_numeric($_SESSION[$k])) {
        $franchise_id = (int)$_SESSION[$k]; break;
    }
}
if (!$franchise_id) { 
    echo json_encode(['success'=>false,'message'=>'Not logged in']); exit; 
}

$barcode = trim($_GET['barcode'] ?? '');
if ($barcode === '') { 
    echo json_encode(['success'=>false,'message'=>'Barcode required']); exit; 
}

/* 1. Find product by barcode from products table */
$stmt = $conn->prepare(
    "SELECT id, product_name, price, pv_bv, image 
     FROM products
     WHERE barcode = ? LIMIT 1"
);
if (!$stmt) { echo json_encode(['success'=>false,'message'=>'Query error: '.$conn->error]); exit; }
$stmt->bind_param('s', $barcode);
$stmt->execute();
$product = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$product) { 
    echo json_encode(['success'=>false,'message'=>'Product not found for barcode: '.$barcode]); exit; 
}

/* 2. Check franchise stock — status = Active */
$stmt2 = $conn->prepare(
    "SELECT COALESCE(SUM(product_balance),0) AS balance
     FROM franchies_product_stock_request
     WHERE franchise_id = ? 
       AND LOWER(TRIM(product_name)) = LOWER(TRIM(?)) 
       AND status = 'Active'"
);
if (!$stmt2) { echo json_encode(['success'=>false,'message'=>'Stock query error']); exit; }
$stmt2->bind_param('is', $franchise_id, $product['product_name']);
$stmt2->execute();
$stock = $stmt2->get_result()->fetch_assoc();
$stmt2->close();

$balance = (int)($stock['balance'] ?? 0);
if ($balance <= 0) { 
    echo json_encode(['success'=>false,'message'=>'Out of Stock']); exit; 
}

echo json_encode([
    'success' => true,
    'product' => [
        'product_id'    => $product['id'],
        'product_name'  => $product['product_name'],
        'price'         => $product['price'],
        'bv_pv'         => $product['pv_bv'] ?? 0,
        'image'         => $product['image'] ?? '',
        'stock_balance' => $balance,
    ]
]);