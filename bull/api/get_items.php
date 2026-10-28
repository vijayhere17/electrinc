<?php

include '../includes/config.php';

header('Content-Type: application/json');

$sql = "SELECT * FROM items ORDER BY id ASC";

$result = $conn->query($sql);

$data = [];

while ($row = $result->fetch_assoc()) {
    $data[] = [
        "id" => $row['id'],
        "item_name" => $row['item_name'],
        "opening_stock" => number_format($row['opening_stock'], 3),
        "current_stock" => number_format($row['current_stock'], 3)
    ];
}

echo json_encode([
    "data" => $data
]);