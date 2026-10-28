<?php

include '../includes/config.php';

header('Content-Type: application/json');

$account_id = $_GET['account_id'] ?? '';

if ($account_id == '') {

    echo json_encode([

        'success' => false,

        'account_data' => [

            'amount' => 0,

            'gold_fine' => 0,

            'silver_fine' => 0,

            'account_group_id' => 0

        ]

    ]);

    exit;
}

$account_id = intval($account_id);

/*
|--------------------------------------------------------------------------
| ACCOUNT DETAILS
|--------------------------------------------------------------------------
*/

$sql = "

SELECT

    id,
    account_group_id,
    opening_amount,
    opening_gold,
    opening_silver,
    tds

FROM accounts

WHERE id = '$account_id'

LIMIT 1

";

$query = mysqli_query($conn, $sql);

$row = mysqli_fetch_assoc($query);

/*
|--------------------------------------------------------------------------
| START BALANCE
|--------------------------------------------------------------------------
*/

$amount =
(float)($row['opening_amount'] ?? 0);

$gold =
(float)($row['opening_gold'] ?? 0);

$silver =
(float)($row['opening_silver'] ?? 0);

/*
|--------------------------------------------------------------------------
| TRANSACTIONS AMOUNT
|--------------------------------------------------------------------------
*/

$transaction_query = mysqli_query(

    $conn,

    "SELECT amount FROM transactions
     WHERE party_id = '$account_id'"

);

while($transaction = mysqli_fetch_assoc($transaction_query)){

    $amount +=
    (float)$transaction['amount'];
}

/*
|--------------------------------------------------------------------------
| ENTRY GOLD/SILVER
|--------------------------------------------------------------------------
*/

$entry_query = mysqli_query(

    $conn,

    "SELECT item_id, qty
     FROM entries
     WHERE party_id = '$account_id'"

);

while($entry = mysqli_fetch_assoc($entry_query)){

    if($entry['item_id'] == 1){

        $gold +=
        (float)$entry['qty'];

    }

    if($entry['item_id'] == 2){

        $silver +=
        (float)$entry['qty'];
    }
}

/*
|--------------------------------------------------------------------------
| FINAL RESPONSE
|--------------------------------------------------------------------------
*/

echo json_encode([

    'success' => true,

    'account_data' => [

        'amount' =>
        round($amount, 2),

        'gold_fine' =>
        round($gold, 4),

        'silver_fine' =>
        round($silver, 4),

        'account_group_id' =>
        $row['account_group_id'] ?? 0,

        'tds' =>
        $row['tds'] ?? 0

    ]

]);