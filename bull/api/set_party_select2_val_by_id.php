<?php

include '../includes/config.php';

header('Content-Type: application/json');

$id =
intval($_GET['id'] ?? 0);

if($id <= 0){

    echo json_encode([

        "success" => false

    ]);

    exit;

}

$query = mysqli_query($conn, "

SELECT

    id,
    account_name

FROM accounts

WHERE id = '$id'

LIMIT 1

");

$row = mysqli_fetch_assoc($query);

if($row){

    echo json_encode([

        "success" => true,

        "id" => $row['id'],

        "text" => $row['account_name']

    ]);

} else {

    echo json_encode([

        "success" => false

    ]);

}

?>