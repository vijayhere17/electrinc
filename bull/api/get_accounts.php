<?php

include '../includes/config.php';

header('Content-Type: application/json');

$data = [];

$sql = "
SELECT
    id,
    account_name
FROM accounts
ORDER BY account_name ASC
";

$query = mysqli_query($conn, $sql);

while($row = mysqli_fetch_assoc($query)){

    $data[] = [

        "id" =>
        $row['id'],

        "text" =>
        $row['account_name']

    ];

}

echo json_encode([
    "results" => $data
]);