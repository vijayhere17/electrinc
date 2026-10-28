<?php

include '../includes/config.php';

header('Content-Type: application/json');

$id = intval($_POST['id'] ?? 0);

$account_name =
$conn->real_escape_string(
    trim($_POST['account_name'] ?? '')
);

$account_group_id =
intval($_POST['account_group_id'] ?? 0);

$address =
$conn->real_escape_string(
    trim($_POST['address'] ?? '')
);

$mobile_no =
$conn->real_escape_string(
    trim($_POST['mobile_no'] ?? '')
);

$opening_gold =
trim($_POST['opening_gold'] ?? '');

$opening_silver =
trim($_POST['opening_silver'] ?? '');

$opening_amount =
trim($_POST['opening_amount'] ?? '');

$tds =
trim($_POST['tds'] ?? '');

$is_cash_bank =
isset($_POST['is_cash_bank'])
? 1
: 0;

$opening_gold =
$opening_gold == ''
? 0
: $opening_gold;

$opening_silver =
$opening_silver == ''
? 0
: $opening_silver;

$opening_amount =
$opening_amount == ''
? 0
: $opening_amount;

$tds =
$tds == ''
? 0
: $tds;

if ($id == 0) {

    $sql = "

    INSERT INTO accounts (

        account_name,
        account_type,
        account_group_id,
        address,
        mobile_no,
        opening_gold,
        opening_silver,
        opening_amount,
        tds,
        is_cash_bank

    ) VALUES (

        '$account_name',
        'party',
        '$account_group_id',
        '$address',
        '$mobile_no',
        '$opening_gold',
        '$opening_silver',
        '$opening_amount',
        '$tds',
        '$is_cash_bank'

    )

    ";

    $query = $conn->query($sql);

} else {

    $sql = "

    UPDATE accounts SET

        account_name = '$account_name',
        account_type = 'party',
        account_group_id = '$account_group_id',
        address = '$address',
        mobile_no = '$mobile_no',
        opening_gold = '$opening_gold',
        opening_silver = '$opening_silver',
        opening_amount = '$opening_amount',
        tds = '$tds',
        is_cash_bank = '$is_cash_bank'

    WHERE id = '$id'

    ";

    $query = $conn->query($sql);

}

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