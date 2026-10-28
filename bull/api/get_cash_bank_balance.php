<?php

include '../includes/config.php';

header('Content-Type: application/json');

$account_id = intval($_GET['account_id'] ?? 0);

if ($account_id <= 0) {
    echo json_encode([
        "amount" => 0
    ]);
    exit;
}

$result = $conn->query("SELECT opening_amount FROM accounts WHERE id = $account_id");

if (!$result) {
    echo json_encode([
        "amount" => 0
    ]);
    exit;
}

$row = $result->fetch_assoc();
$opening_amount = $row['opening_amount'] ?? 0;

echo json_encode([
    "amount" => $opening_amount
]);