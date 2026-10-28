<?php

include '../includes/config.php';

header('Content-Type: application/json');

$id =
intval($_POST['id'] ?? 0);

$opening_stock =
trim($_POST['opening_stock'] ?? '');

if($id <= 0){

    echo json_encode([

        "success" => false

    ]);

    exit;

}

$opening_stock =
$conn->real_escape_string($opening_stock);

$sql = "

UPDATE items

SET

opening_stock = '$opening_stock'

WHERE id = '$id'

";

$query = $conn->query($sql);

if($query){

    echo json_encode([

        "success" => true

    ]);

} else {

    echo json_encode([

        "success" => false

    ]);

}

?>