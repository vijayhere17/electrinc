<?php

include '../includes/config.php';

header('Content-Type: application/json');

$data = [];

$query = mysqli_query($conn,"
    SELECT *
    FROM rupu_transfer
    ORDER BY id DESC
");

while($row = mysqli_fetch_assoc($query)){

    $data[] = [

        'id' => $row['id'],

        'rupu_transfer_date_new' =>
        date('d-m-Y', strtotime($row['entry_date'])),

        'party_name' =>
        $row['party_name'],

        'total_rupu_gross' =>
        number_format($row['total_gross'],3,'.',''),

        'total_fine' =>
        number_format($row['total_changed_fine'],3,'.',''),

        'narration' =>
        $row['narration']

    ];
}

echo json_encode([
    "data" => $data
]);