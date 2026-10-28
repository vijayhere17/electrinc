<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

include '../includes/config.php';

header('Content-Type: application/json');

$cash_bank_id =
$_POST['cash_bank_id'] ?? '';

$from_date =
$_POST['from_date'] ?? date('d-m-Y');

$to_date =
$_POST['to_date'] ?? date('d-m-Y');

$draw =
$_POST['draw'] ?? 1;

$from_date =
date(
    'Y-m-d',
    strtotime($from_date)
);

$to_date =
date(
    'Y-m-d',
    strtotime($to_date)
);

$data = [];

$opening_balance = 0;
$running_balance = 0;

if($cash_bank_id == ''){

    echo json_encode([

        "draw" => intval($draw),

        "recordsTotal" => 0,

        "recordsFiltered" => 0,

        "data" => []

    ]);

    exit;

}

$cash_bank_id =
intval($cash_bank_id);

$account_sql = "

SELECT
    *
FROM accounts
WHERE id = '$cash_bank_id'
LIMIT 1

";

$account_query =
mysqli_query($conn,$account_sql);

$account_row =
mysqli_fetch_assoc($account_query);

$opening_balance =
(float)$account_row['opening_amount'];

$running_balance =
$opening_balance;

$data[] = [

    'action' => '',

    'row_date' => '',

    'account_name' => 'Opening',

    'credit' => $opening_balance > 0
    ? number_format($opening_balance,2,'.','')
    : '',

    'debit' => $opening_balance < 0
    ? number_format(abs($opening_balance),2,'.','')
    : '',

    'balance' =>
    number_format(
        abs($running_balance),
        2,
        '.',
        ''
    ),

    'narration' => ''

];

$sql = "

SELECT

    t.id,
    t.transaction_date,
    t.party_id,
    t.cash_bank_id,
    t.amount,
    t.narration,

    a.account_name

FROM transactions t

LEFT JOIN accounts a
ON a.id = t.party_id

WHERE
t.cash_bank_id = '$cash_bank_id'

AND
DATE(t.transaction_date)
BETWEEN '$from_date'
AND '$to_date'

ORDER BY t.transaction_date ASC

";

$query =
mysqli_query($conn,$sql);

while($row = mysqli_fetch_assoc($query)){

    $credit = 0;
    $debit = 0;

    if($row['amount'] >= 0){

        $credit =
        (float)$row['amount'];

        $running_balance +=
        $credit;

    }else{

        $debit =
        abs((float)$row['amount']);

        $running_balance -=
        $debit;

    }

    $particular =
    $row['account_name'];

    $data[] = [

        'action' => '

        <a href="transaction.php?id='.$row['id'].'"
        class="btn btn-info btn-sm">
        <i class="fa fa-edit"></i>
        </a>

        ',

        'row_date' =>
        date(
            'd-m-Y',
            strtotime($row['transaction_date'])
        ),

        'account_name' =>
        $particular,

        'credit' =>
        $credit > 0
        ?
        number_format(
            $credit,
            2,
            '.',
            ''
        )
        :
        '',

        'debit' =>
        $debit > 0
        ?
        number_format(
            $debit,
            2,
            '.',
            ''
        )
        :
        '',

        'balance' =>
        number_format(
            abs($running_balance),
            2,
            '.',
            ''
        ),

        'narration' =>
        $row['narration']

    ];

}

$data[] = [

    'action' => '',

    'row_date' => '',

    'account_name' => 'Closing',

    'credit' => '',

    'debit' => '',

    'balance' =>
    number_format(
        abs($running_balance),
        2,
        '.',
        ''
    ),

    'narration' => ''

];

echo json_encode([

    "draw" =>
    intval($draw),

    "recordsTotal" =>
    count($data),

    "recordsFiltered" =>
    count($data),

    "data" =>
    $data

]);