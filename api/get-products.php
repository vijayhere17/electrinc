<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");

require_once __DIR__ . '/../member/config/db.php';

$result = $conn->query("
    SELECT id, product_name, price, image, category 
    FROM products 
    WHERE status = 'Active'
");

$products = [];

while ($row = $result->fetch_assoc()) {
    $products[] = $row;
}

echo json_encode([
    "success" => true,
    "data" => $products
]);