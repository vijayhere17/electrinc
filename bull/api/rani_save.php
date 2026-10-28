<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

include '../includes/config.php';

header('Content-Type: application/json');

$id               = $_POST['id'] ?? '';
$entry_date       = $_POST['rani_date'] ?? '';
$party_id         = $_POST['party_id'] ?? '';
$gold             = $_POST['gold_fine'] ?? 0;
$gold_qty         = $_POST['gold_qty'] ?? 0;
$gold_rate        = $_POST['gold_rate'] ?? 0;
$gold_rate_amount = $_POST['gold_rate_amount'] ?? 0;
$cash_bank_id     = $_POST['cash_bank_id'] ?? '';
$amount           = $_POST['amount'] ?? 0;
$badlo            = $_POST['badlo'] ?? 0;
$narration        = $_POST['narration'] ?? '';
$total_rani       = $_POST['total_rani_gross_input'] ?? 0;
$total_fine       = $_POST['total_fine_input'] ?? 0;
$pending          = $_POST['total_pending_input'] ?? 0;
$line_items_data  = $_POST['line_items_data'] ?? '';

$errors = [];

if($entry_date == ''){
    $errors[] = "Date Required";
}
if($party_id == ''){
    $errors[] = "Party Required";
}
if($line_items_data == ''){
    $errors[] = "Add Atleast One Lineitem";
}

if(count($errors) > 0){
    echo json_encode(["errors" => $errors]);
    exit;
}

$id           = intval($id);
$party_id     = intval($party_id);
$cash_bank_id = intval($cash_bank_id);

// ✅ CORRECT - explicitly parse dd-mm-yyyy
$date_obj = DateTime::createFromFormat('d-m-Y', $entry_date);
$entry_date = $date_obj ? $date_obj->format('Y-m-d') : date('Y-m-d');

$narration = $conn->real_escape_string($narration);

$conn->begin_transaction();

try {

    if($id == 0){

        $sql = "
            INSERT INTO rani(
                entry_date, party_id, gold, gold_qty, gold_rate,
                gold_rate_amount, cash_bank_id, amount, badlo, narration
            ) VALUES (
                '$entry_date', '$party_id', '$gold', '$gold_qty', '$gold_rate',
                '$gold_rate_amount', '$cash_bank_id', '$amount', '$badlo', '$narration'
            )
        ";

        $save = $conn->query($sql);

        if(!$save){
            throw new Exception($conn->error);
        }

        $rani_id = $conn->insert_id;

    } else {

        $sql = "
            UPDATE rani SET
                entry_date       = '$entry_date',
                party_id         = '$party_id',
                gold             = '$gold',
                gold_qty         = '$gold_qty',
                gold_rate        = '$gold_rate',
                gold_rate_amount = '$gold_rate_amount',
                cash_bank_id     = '$cash_bank_id',
                amount           = '$amount',
                badlo            = '$badlo',
                narration        = '$narration'
            WHERE id = '$id'
        ";

        $update = $conn->query($sql);

        if(!$update){
            throw new Exception($conn->error);
        }

        $rani_id = $id;

        $conn->query("DELETE FROM rani_items WHERE rani_id = '$rani_id'");
    }

    // Decode line items JSON
    $line_items = json_decode($line_items_data, true);

    if(!is_array($line_items) || count($line_items) === 0){
        throw new Exception("No line items found");
    }

    foreach($line_items as $item){

        $gross          = $item['rani_gross'] ?? 0;
        $touch          = $item['touch'] ?? 0;
        $fine_after     = $item['fine'] ?? 0;  // fine after badlo
        $fine_before    = round(($gross * $touch) / 100, 3); // fine before badlo
        $line_narration = $conn->real_escape_string($item['rani_narration'] ?? '');

        $sql_item = "
            INSERT INTO rani_items(
                rani_id, gross, touch_percent, fine, fine_after_badlo, narration
            ) VALUES (
                '$rani_id', '$gross', '$touch',
                '$fine_before', '$fine_after', '$line_narration'
            )
        ";

        $save_item = $conn->query($sql_item);

        if(!$save_item){
            throw new Exception($conn->error);
        }
    }
    
$gold_effect = (float)$gold;

$amount_effect =
(float)$amount +
(float)$gold_rate_amount;

if($id == 0){

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
        '$entry_date',
        '$party_id',
        1,
        'Bill',
        2,
        '$gold_rate',
        '$gold_effect',
        0,
        'Rani Entry #$rani_id'
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
        '$entry_date',
        '$party_id',
        '$cash_bank_id',
        '$amount_effect',
        'Rani Entry #$rani_id'
    )
    ";

    if(!$conn->query($sql_transaction)){
        throw new Exception($conn->error);
    }
}

    $conn->commit(); // ✅ COMMIT the transaction

    if($id == 0){
        echo json_encode([
            "success" => "Added",
            "msg"     => "Rani Saved Successfully"
        ]);
    } else {
        echo json_encode([
            "success" => "Updated",
            "msg"     => "Rani Updated Successfully"
        ]);
    }

} catch(Exception $e){

    $conn->rollback();

    echo json_encode([
        "errors" => [$e->getMessage()]
    ]);
}
?>