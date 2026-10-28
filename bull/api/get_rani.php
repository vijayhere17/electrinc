<?php

include '../includes/config.php';

header('Content-Type: application/json');

$id = intval($_GET['id'] ?? 0);

if ($id <= 0) {
    echo json_encode([
        "success" => false
    ]);
    exit;
}

$query = $conn->query("SELECT * FROM rani WHERE id = $id");

$data = $query->fetch_assoc();

$items = [];

$item_query = $conn->query("SELECT * FROM rani_items WHERE rani_id = $id");

while ($row = $item_query->fetch_assoc()) {
    $items[] = $row;
}

echo json_encode([
    "success" => true,
    "data" => $data,
    "items" => $items
]);