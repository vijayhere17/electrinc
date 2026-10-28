<?php

include '../includes/config.php';

header('Content-Type: application/json');

$party_id =
$_POST['filter_party_id'] ?? '';

$item_id =
$_POST['item_id'] ?? '';

$from_date =
$_POST['filter_from_date'] ?? '';

$to_date =
$_POST['filter_to_date'] ?? '';

$draw =
$_POST['draw'] ?? 1;

if($from_date != ''){
    $from_date =
    date('Y-m-d', strtotime($from_date));
}

if($to_date != ''){
    $to_date =
    date('Y-m-d', strtotime($to_date));
}

$where = " WHERE 1 ";

if(
    $from_date != ''
    &&
    $to_date != ''
){

    $where .= "
    AND DATE(e.entry_date)
    BETWEEN '$from_date'
    AND '$to_date'
    ";

}

if(
    $party_id != ''
    &&
    $party_id != 'null'
){

    $party_id = intval($party_id);

    $where .= "
    AND e.party_id = '$party_id'
    ";

}

if(
    $item_id != ''
    &&
    $item_id != 'null'
){

    $item_id = intval($item_id);

    $where .= "
    AND e.item_id = '$item_id'
    ";

}

$sql = "

SELECT

    e.id,
    e.party_id,
    e.entry_date,
    e.qty,
    e.rate,
    e.mcx,
    e.narration,
    e.type_id,

    a.account_name

FROM entries e

LEFT JOIN accounts a
ON a.id = e.party_id

$where

ORDER BY
e.entry_date DESC,
e.id DESC

";

$query =
mysqli_query($conn, $sql);

$data = [];

$total_qty = 0;

while($row = mysqli_fetch_assoc($query)){

    $qty =
    (float)$row['qty'];

    $total_qty += $qty;

    $cash_bill =
    $row['type_id'] == 1
    ? 'Cash'
    : 'Bill';

    $data[] = [

        "id" =>
        $row['id'],

        "party_id" =>
        $row['party_id'],

        "account_name" =>
        $row['account_name'],

        "entry_date" =>
        $row['entry_date'],

        "qty" =>
        number_format(
            $qty,
            3,
            '.',
            ''
        ),

        "cash_bill" =>
        $cash_bill,

        "rate" =>
        number_format(
            (float)$row['rate'],
            2,
            '.',
            ''
        ),

        "mcx" =>
        number_format(
            (float)$row['mcx'],
            2,
            '.',
            ''
        ),

        "narration" =>
        $row['narration']

    ];

}

echo json_encode([

    "draw" =>
    intval($draw),

    "recordsTotal" =>
    count($data),

    "recordsFiltered" =>
    count($data),

    "data" =>
    $data,

    "total_qty" =>
    number_format(
        $total_qty,
        3,
        '.',
        ''
    )

]);

?>