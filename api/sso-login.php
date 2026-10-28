<?php
header("Access-Control-Allow-Origin: https://shop.scalpingtrading.live");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

header("Content-Type: application/json");

require_once __DIR__ . '/../config/db.php';

$data = json_decode(file_get_contents("php://input"), true);
$token = $data['token'] ?? '';

if (!$token) {
    echo json_encode(["success" => false, "message" => "Token missing"]);
    exit;
}

$stmt = $conn->prepare("SELECT customer_id FROM sso_tokens WHERE token = ? LIMIT 1");
$stmt->bind_param("s", $token);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 1) {

    $row = $result->fetch_assoc();
    $customer_id = $row['customer_id'];

    $delete = $conn->prepare("DELETE FROM sso_tokens WHERE token = ?");
    $delete->bind_param("s", $token);
    $delete->execute();

    echo json_encode([
        "success" => true,
        "customer_id" => $customer_id
    ]);

} else {
    echo json_encode([
        "success" => false,
        "message" => "Invalid or expired token"
    ]);
}