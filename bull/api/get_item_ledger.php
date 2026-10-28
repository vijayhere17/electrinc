<?php

include '../includes/config.php';

header('Content-Type: application/json');

$item_id =
$_POST['item_id'] ?? '';

$from_date =
$_POST['from_date'] ?? '';

$to_date =
$_POST['to_date'] ?? '';

$draw =
$_POST['draw'] ?? 1;

$where = " WHERE 1 ";

if($item_id != ''){

    $item_id = intval($item_id);

    $where .= "
    AND e.item_id = '$item_id'
    ";

}

if($from_date != ''){

    $from_date =
    date(
        'Y-m-d',
        strtotime($from_date)
    );

    $where .= "
    AND e.entry_date >= '$from_date'
    ";

}

if($to_date != ''){

    $to_date =
    date(
        'Y-m-d',
        strtotime($to_date)
    );

    $where .= "
    AND e.entry_date <= '$to_date'
    ";

}

$sql = "

SELECT

    e.*,
    a.account_name

FROM entries e

LEFT JOIN accounts a
ON a.id = e.party_id

$where

ORDER BY e.entry_date DESC,
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

    $action = '
    <a href="transaction.php?id='.$row['id'].'"
    class="btn btn-info btn-sm">
    <i class="fa fa-edit"></i>
    </a>

    <button
    class="btn btn-danger btn-sm delete_button"
    data-href="api/delete_transaction.php"
    data-delete_id_name="id"
    data-delete_id="'.$row['id'].'">
    <i class="fa fa-times"></i>
    </button>
    ';

    $data[] = [

        'action' =>
        $action,

        'party_id' =>
        $row['party_id'],

        'row_date' =>
        date(
            'd-m-Y',
            strtotime($row['entry_date'])
        ),

        'account_name' =>
        $row['account_name'],

        'row_type' =>
        'Transaction',

        'row_qty' =>
        number_format(
            $qty,
            3,
            '.',
            ''
        )

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

    "opening_qty" =>
    number_format(
        $total_qty,
        3,
        '.',
        ''
    ),

    "closing_qty" =>
    number_format(
        $total_qty,
        3,
        '.',
        ''
    )

]);