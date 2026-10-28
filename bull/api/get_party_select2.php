<?php

include '../includes/config.php';

$data = [];

$query = mysqli_query(
    $conn,
    "SELECT id, account_name
     FROM accounts
     WHERE account_type='party'
     ORDER BY account_name ASC"
);

while($row = mysqli_fetch_assoc($query)){

    $data[] = [
        'id' => $row['id'],
        'text' => $row['account_name']
    ];
}

echo json_encode([
    'results' => $data
]);