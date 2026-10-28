<?php

include '../includes/config.php';

header('Content-Type: application/json');

$search = $_GET['q'] ?? '';

$sql = "

SELECT

    id,
    account_name

FROM accounts

WHERE

(
    account_type = 'party'
)

";

if($search != ''){

    $sql .= "

    AND account_name LIKE '%$search%'

    ";

}

$sql .= "

ORDER BY account_name ASC

LIMIT 50

";

$query = mysqli_query($conn, $sql);

$data = [];

while($row = mysqli_fetch_assoc($query)){

    $data[] = [

        'id' => $row['id'],

        'text' => $row['account_name']

    ];

}

echo json_encode([

    'results' => $data

]);

?>