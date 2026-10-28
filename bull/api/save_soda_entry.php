<?php

include '../includes/config.php';

header('Content-Type: application/json');

$id =
intval($_POST['id'] ?? 0);

$entry_date_raw =
$_POST['entry_date'] ?? '';

$entry_date =
date(
    'Y-m-d',
    strtotime($entry_date_raw)
);

if(
    $entry_date == '1970-01-01'
    ||
    $entry_date == '0000-00-00'
){

    $entry_date = date('Y-m-d');

}

$party_id =
intval($_POST['party_id'] ?? 0);

$item_id =
intval($_POST['item_id'] ?? 0);

$type_id =
intval($_POST['type_id'] ?? 0);

$type_id =
intval($_POST['type_id'] ?? 0);

$bill_cash = '';

if($type_id == 1){

    $bill_cash = 'Cash';

}
else if($type_id == 2){

    $bill_cash = 'Bill';

}

$rate =
floatval($_POST['rate'] ?? 0);

$qty =
floatval($_POST['qty'] ?? 0);

$mcx =
floatval($_POST['mcx'] ?? 0);

$range_min =
floatval($_POST['range_min'] ?? 0);

$range_max =
floatval($_POST['range_max'] ?? 0);

$narration =
$conn->real_escape_string(
    $_POST['narration'] ?? ''
);

$tds =
floatval($_POST['tds'] ?? 0);

$pending_qty = 0;

if($party_id <= 0 || $item_id <= 0){

    echo json_encode([

        "errors" => [

            "Party and Item required"

        ]

    ]);

    exit;

}

if($id == 0){

    $sql = "

    INSERT INTO entries(

        entry_date,
        party_id,
        item_id,
        type_id,
        bill_cash,
        rate,
        qty,
        pending_qty,
        mcx,
        range_min,
        range_max,
        narration,
        tds,
        created_at

    ) VALUES (

        '$entry_date',
        '$party_id',
        '$item_id',
        '$type_id',
        '$bill_cash',
        '$rate',
        '$qty',
        '$pending_qty',
        '$mcx',
        '$range_min',
        '$range_max',
        '$narration',
        '$tds',
        NOW()

    )

    ";

    $query = $conn->query($sql);

    if($query){

        echo json_encode([

            "success" => "Added",

            "msg" => "Entry Saved"

        ]);

    } else {

        echo json_encode([

            "errors" => [

                "Save Failed"

            ]

        ]);

    }

} else {

    $sql = "

    UPDATE entries SET

        entry_date = '$entry_date',
        party_id = '$party_id',
        item_id = '$item_id',
        type_id = '$type_id',
        bill_cash = '$bill_cash',
        rate = '$rate',
        qty = '$qty',
        mcx = '$mcx',
        range_min = '$range_min',
        range_max = '$range_max',
        narration = '$narration',
        tds = '$tds'

    WHERE id = '$id'

    ";

    $query = $conn->query($sql);

    if($query){

        echo json_encode([

            "success" => "Updated",

            "msg" => "Entry Updated"

        ]);

    } else {

        echo json_encode([

            "errors" => [

                "Update Failed"

            ]

        ]);

    }

}

?>