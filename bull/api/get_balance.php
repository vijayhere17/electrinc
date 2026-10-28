<?php

include '../includes/config.php';

header('Content-Type: application/json');

$account_id = intval($_GET['account_id'] ?? 0);

if ($account_id <= 0) {
    echo json_encode([
        "account_data" => [
            "amount" => "0.00",
            "gold_fine" => "0.0000",
            "silver_fine" => "0.0000"
        ]
    ]);
    exit;
}

$amount_balance = 0;
$gold_balance = 0;
$silver_balance = 0;

$getEntries = $conn->query("SELECT item_id, qty, rate FROM entries WHERE party_id = $account_id");

while ($entry = $getEntries->fetch_assoc()) {
    $entry_qty = (float)$entry['qty'];
    $entry_rate = (float)$entry['rate'];
    $entry_amount = $entry_qty * $entry_rate;

    /*
    |--------------------------------------------------------------------------
    | GOLD
    |--------------------------------------------------------------------------
    */

    if ($entry['item_id'] == 1) {
        $gold_balance += $entry_qty;
    }

    /*
    |--------------------------------------------------------------------------
    | SILVER
    |--------------------------------------------------------------------------
    */

    if ($entry['item_id'] == 2) {
        $silver_balance += $entry_qty;
    }

    /*
    |--------------------------------------------------------------------------
    | AMOUNT
    |--------------------------------------------------------------------------
    */

    if ($entry_qty > 0) {
        $amount_balance -= abs($entry_amount);
    } else {
        $amount_balance += abs($entry_amount);
    }
}

echo json_encode([
    "account_data" => [
        "amount" => number_format($amount_balance, 2, '.', ''),
        "gold_fine" => number_format($gold_balance, 4, '.', ''),
        "silver_fine" => number_format($silver_balance, 4, '.', '')
    ]
]);