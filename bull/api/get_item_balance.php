<?php

include '../includes/config.php';

header('Content-Type: application/json');

$item_id = intval($_GET['item_id'] ?? 0);

$balance = 0;

$result = mysqli_query(
    $conn,
    "SELECT qty FROM entries WHERE item_id = '$item_id'"
);

while($row = mysqli_fetch_assoc($result)){

    $balance += (float)$row['qty'];

}

echo json_encode([
    'balance' => round($balance, 4)
]);