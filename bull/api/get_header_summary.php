<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

include '../includes/config.php';

header('Content-Type: application/json');

$response = [];

$gold_query = mysqli_query(
    $conn,
    "SELECT current_stock
     FROM items
     WHERE id='1'
     LIMIT 1"
);

$gold = mysqli_fetch_assoc($gold_query);

$response['gold'] =
number_format(
    (float)$gold['current_stock'],
    3
);

$silver_query = mysqli_query(
    $conn,
    "SELECT current_stock
     FROM items
     WHERE id='2'
     LIMIT 1"
);

$silver = mysqli_fetch_assoc($silver_query);

$response['silver'] =
number_format(
    (float)$silver['current_stock'],
    3
);

$cash_query = mysqli_query(
    $conn,
    "SELECT opening_amount
     FROM accounts
     WHERE id='1'
     LIMIT 1"
);

$cash = mysqli_fetch_assoc($cash_query);

$response['cash'] =
number_format(
    (float)$cash['opening_amount'],
    2
);

$bank_query = mysqli_query(
    $conn,
    "SELECT opening_amount
     FROM accounts
     WHERE id='2'
     LIMIT 1"
);

$bank = mysqli_fetch_assoc($bank_query);

$response['bank_total'] =
number_format(
    (float)$bank['opening_amount'],
    2
);

echo json_encode($response);