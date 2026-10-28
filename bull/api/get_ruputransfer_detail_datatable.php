<?php

include '../includes/config.php';

header('Content-Type: application/json');

$transfer_id =
intval($_POST['ruputransfer_id'] ?? 0);

$data = [];

$query = mysqli_query($conn,"
    SELECT *
    FROM rupu_transfer_items
    WHERE transfer_id = '$transfer_id'
");

while($row = mysqli_fetch_assoc($query)){

    $data[] = [

        'rupu_gross' =>
        number_format($row['gross'],3,'.',''),

        'changed_touch' =>
        number_format($row['changed_touch'],3,'.',''),

        'changed_fine' =>
        number_format($row['changed_fine'],3,'.',''),

        'party_name' =>
        $row['party_name']

    ];
}

echo json_encode([
    "data" => $data
]);