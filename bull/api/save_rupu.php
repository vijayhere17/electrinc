<?php

header('Content-Type: application/json');

include '../includes/config.php';

$date               = $_POST['date'] ?? '';
$party_id           = $_POST['party_id'] ?? '';

$silver             = $_POST['silver_fine'] ?? 0; // FIXED
$silver_qty         = $_POST['silver_qty'] ?? 0;
$silver_rate        = $_POST['silver_rate'] ?? 0;
$silver_rate_amount = $_POST['silver_rate_amount'] ?? 0;

$cash_bank_id       = $_POST['cash_bank_id'] ?? '';
$amount             = $_POST['amount'] ?? 0;
$badlo              = $_POST['badlo'] ?? 0;
$after_badlo        = $_POST['after_badlo'] ?? 0;
$fine_badlo         = $_POST['fine_badlo'] ?? 0;
$narration          = $_POST['narration'] ?? '';

if ($date == '') {

    echo json_encode([
        "msg" => "Date Required"
    ]);
    exit;
}

if ($party_id == '') {

    echo json_encode([
        "msg" => "Party Required"
    ]);
    exit;
}

$date = date('Y-m-d', strtotime($date));

$conn->begin_transaction();

try {

    $insert = $conn->query("

        INSERT INTO rupu (

            entry_date,
            party_id,
            silver,
            silver_qty,
            silver_rate,
            silver_rate_amount,
            cash_bank_id,
            amount,
            badlo,
            after_badlo,
            fine_badlo,
            narration

        ) VALUES (

            '$date',
            '$party_id',
            '$silver',
            '$silver_qty',
            '$silver_rate',
            '$silver_rate_amount',
            '$cash_bank_id',
            '$amount',
            '$badlo',
            '$after_badlo',
            '$fine_badlo',
            '".$conn->real_escape_string($narration)."'

        )

    ");

    if (!$insert) {

        throw new Exception($conn->error);
    }

    $rupu_id = $conn->insert_id;

    /*
    |--------------------------------------------------------------------------
    | LINE ITEMS
    |--------------------------------------------------------------------------
    */

    $line_items_json = $_POST['line_items_data'] ?? '';

    if ($line_items_json == '') {

        throw new Exception('Add Atleast One Lineitem');
    }

    $items = json_decode($line_items_json, true);

    if (!is_array($items) || count($items) == 0) {

        throw new Exception('Invalid Lineitem Data');
    }

    foreach ($items as $it) {

        $gross_value = $conn->real_escape_string($it['rupu_gross'] ?? 0);
        $touch_value = $conn->real_escape_string($it['touch'] ?? 0);
        $fine_value  = $conn->real_escape_string($it['fine'] ?? 0);

        $narration_value =
        $conn->real_escape_string(
            $it['rupu_narration'] ?? ''
        );

        $item_insert = $conn->query("

            INSERT INTO rupu_items (

                rupu_id,
                gross,
                touch_percent,
                fine,
                narration

            ) VALUES (

                '$rupu_id',
                '$gross_value',
                '$touch_value',
                '$fine_value',
                '$narration_value'

            )

        ");

        if (!$item_insert) {

            throw new Exception($conn->error);
        }
    }
    
    $silver_effect = (float)$silver;

$amount_effect =
(float)$amount +
(float)$silver_rate_amount;

$sql_entry = "
INSERT INTO entries(
    entry_date,
    party_id,
    item_id,
    bill_cash,
    type_id,
    rate,
    qty,
    pending_qty,
    narration
)
VALUES(
    '$date',
    '$party_id',
    2,
    'Bill',
    2,
    '$silver_rate',
    '$silver_effect',
    0,
    'Rupu Entry #$rupu_id'
)
";

if(!$conn->query($sql_entry)){
    throw new Exception($conn->error);
}

$sql_transaction = "
INSERT INTO transactions(
    transaction_date,
    party_id,
    cash_bank_id,
    amount,
    narration
)
VALUES(
    '$date',
    '$party_id',
    '$cash_bank_id',
    '$amount_effect',
    'Rupu Entry #$rupu_id'
)
";

if(!$conn->query($sql_transaction)){
    throw new Exception($conn->error);
}

    $conn->commit();

    echo json_encode([

        "success" => "Added",
        "msg" => "Rupu Saved Successfully",
        "id" => $rupu_id

    ]);

} catch (Exception $e) {

    $conn->rollback();

    echo json_encode([

        "msg" => $e->getMessage()

    ]);
}