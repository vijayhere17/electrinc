<?php

include '../includes/config.php';

header('Content-Type: application/json');

$transaction_id = intval($_POST['transaction_id'] ?? 0);

if (empty($transaction_id)) {
    echo json_encode([
        "success" => false,
        "msg" => "Invalid Transaction"
    ]);
    exit;
}

/*
|--------------------------------------------------------------------------
| RESTORE PENDING QTY
|--------------------------------------------------------------------------
*/

$getItems = $conn->query("SELECT entry_id, settle_qty FROM transaction_line_items WHERE transaction_id = $transaction_id");

while ($item = $getItems->fetch_assoc()) {
    $entry_id = (int)$item['entry_id'];
    $settle_qty = (float)$item['settle_qty'];

    $getEntry = $conn->query("SELECT pending_qty FROM entries WHERE id = $entry_id");

    if ($getEntry && $entry = $getEntry->fetch_assoc()) {
        $current_pending = (float)$entry['pending_qty'];

        /*
        |--------------------------------------------------------------------------
        | GET ORIGINAL ENTRY QTY
        |--------------------------------------------------------------------------
        */

        $getOriginal = $conn->query("SELECT qty FROM entries WHERE id = $entry_id");

        $original_qty = 0;

        if ($getOriginal && $original = $getOriginal->fetch_assoc()) {
            $original_qty = (float)$original['qty'];
        }

        /*
        |--------------------------------------------------------------------------
        | RESTORE
        |--------------------------------------------------------------------------
        */

        if ($original_qty < 0) {
            $new_pending = $current_pending - $settle_qty;
        } else {
            $new_pending = $current_pending + $settle_qty;
        }

        $conn->query("UPDATE entries SET pending_qty = $new_pending WHERE id = $entry_id");
    }
}

/*
|--------------------------------------------------------------------------
| DELETE LINE ITEMS
|--------------------------------------------------------------------------
*/

$conn->query("DELETE FROM transaction_line_items WHERE transaction_id = $transaction_id");

/*
|--------------------------------------------------------------------------
| DELETE TRANSACTION
|--------------------------------------------------------------------------
*/

$conn->query("DELETE FROM transactions WHERE id = $transaction_id");

echo json_encode([
    "success" => true,
    "msg" => "Transaction Deleted Successfully"
]);