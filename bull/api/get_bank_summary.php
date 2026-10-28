<?php

include '../includes/config.php';

header('Content-Type: application/json');

$cash_bank_id =
$_POST['cash_bank_id'] ?? '';

$draw =
$_POST['draw'] ?? 1;

$where = "
WHERE
(
    a.account_type = 'bank'
    OR
    a.account_type = 'cash'
)
";

if(
    $cash_bank_id != ''
    &&
    $cash_bank_id != 'null'
){

    $cash_bank_id =
    intval($cash_bank_id);

    $where .= "
    AND a.id = '$cash_bank_id'
    ";

}

$sql = "

SELECT

    a.id,
    a.account_name,

    IFNULL(
        a.opening_amount,
        0
    ) AS balance_amount

FROM accounts a

$where

ORDER BY a.account_name ASC

";

$query =
mysqli_query($conn, $sql);

$data = [];

$total_balance_amount = 0;

while($row = mysqli_fetch_assoc($query)){

    $balance_amount =
    (float)$row['balance_amount'];

    $total_balance_amount +=
    $balance_amount;

    $data[] = [

        'id' =>
        $row['id'],

        'account_name' =>
        $row['account_name'],

        'balance_amount' =>
        number_format(
            $balance_amount,
            2,
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

    "total_balance_amount" =>
    number_format(
        $total_balance_amount,
        2,
        '.',
        ''
    )

]);