<?php

include '../includes/config.php';

header('Content-Type: application/json');

$id =
intval($_POST['id'] ?? 0);

if($id <= 0){

    echo json_encode([

        "success" => false,
        "msg" => "Invalid ID"

    ]);

    exit;

}

$date_raw =
$_POST['havaalo_date'] ?? '';

$dateObj =
DateTime::createFromFormat(
    'd-m-Y',
    $date_raw
);

$date =
$dateObj
? $dateObj->format('Y-m-d')
: date('Y-m-d');

$party_id =
intval($_POST['party_id'] ?? 0);

$item_id =
intval($_POST['item_id'] ?? 0);

$rate =
floatval($_POST['rate'] ?? 0);

$qty =
floatval($_POST['qty'] ?? 0);

$narration =
$conn->real_escape_string(
    $_POST['narration'] ?? ''
);

$amount =
floatval($_POST['tds_val'] ?? 0);

$query = mysqli_query($conn, "

UPDATE transactions SET

    transaction_date = '$date',
    party_id = '$party_id',
    item_id = '$item_id',
    rate = '$rate',
    qty = '$qty',
    amount = '$amount',
    narration = '$narration'

WHERE id = '$id'

");

if($query){

    echo json_encode([

        "success" => "Updated",

        "msg" => "TDS Updated Successfully"

    ]);

} else {

    echo json_encode([

        "success" => false,

        "msg" => "Update Failed"

    ]);

}

?>