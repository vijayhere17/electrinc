<?php

include '../includes/config.php';

header('Content-Type: application/json');

$id =
intval($_POST['id'] ?? 0);

$entry_date_raw =
$_POST['entry_date'] ?? '';

$entry_date =
date(
    'Y-m-d',
    strtotime($entry_date_raw)
);

if(
    $entry_date == '1970-01-01'
    ||
    $entry_date == '0000-00-00'
){

    $entry_date = date('Y-m-d');

}

$from_account =
intval($_POST['from_party_id'] ?? 0);

$to_account =
intval($_POST['to_party_id'] ?? 0);

$amount =
(float)($_POST['amount'] ?? 0);

$narration =
$conn->real_escape_string(
    trim($_POST['narration'] ?? '')
);

$errors = [];

if($from_account <= 0){

    $errors[] = "From Account Required";

}

if($to_account <= 0){

    $errors[] = "To Account Required";

}

if($amount <= 0){

    $errors[] = "Valid Amount Required";

}

if($from_account == $to_account){

    $errors[] = "From and To account cannot be same";

}

if(count($errors) > 0){

    echo json_encode([

        "status" => false,
        "message" => implode(', ', $errors)

    ]);

    exit;

}

$conn->begin_transaction();

try{

    if($id == 0){

        $sql = "

        INSERT INTO bank_contra(

            entry_date,
            from_account,
            to_account,
            amount,
            narration,
            created_at

        ) VALUES (

            '$entry_date',
            '$from_account',
            '$to_account',
            '$amount',
            '$narration',
            NOW()

        )

        ";

        $save = $conn->query($sql);

        if(!$save){

            throw new Exception($conn->error);

        }

        $conn->query("

            UPDATE accounts

            SET opening_amount = opening_amount - $amount

            WHERE id = '$from_account'

        ");

        $conn->query("

            UPDATE accounts

            SET opening_amount = opening_amount + $amount

            WHERE id = '$to_account'

        ");

        $conn->commit();

        echo json_encode([

            "status" => true,
            "type" => "add",
            "message" => "Bank Contra Added Successfully"

        ]);

    } else {

        $old =
        $conn->query("

            SELECT *

            FROM bank_contra

            WHERE id = '$id'

        ")->fetch_assoc();

        if($old){

            $old_amount =
            (float)$old['amount'];

            $old_from =
            intval($old['from_account']);

            $old_to =
            intval($old['to_account']);

            $conn->query("

                UPDATE accounts

                SET opening_amount = opening_amount + $old_amount

                WHERE id = '$old_from'

            ");

            $conn->query("

                UPDATE accounts

                SET opening_amount = opening_amount - $old_amount

                WHERE id = '$old_to'

            ");

        }

        $sql = "

        UPDATE bank_contra SET

            entry_date = '$entry_date',
            from_account = '$from_account',
            to_account = '$to_account',
            amount = '$amount',
            narration = '$narration'

        WHERE id = '$id'

        ";

        $update = $conn->query($sql);

        if(!$update){

            throw new Exception($conn->error);

        }

        $conn->query("

            UPDATE accounts

            SET opening_amount = opening_amount - $amount

            WHERE id = '$from_account'

        ");

        $conn->query("

            UPDATE accounts

            SET opening_amount = opening_amount + $amount

            WHERE id = '$to_account'

        ");

        $conn->commit();

        echo json_encode([

            "status" => true,
            "type" => "update",
            "message" => "Bank Contra Updated Successfully"

        ]);

    }

} catch(Exception $e){

    $conn->rollback();

    echo json_encode([

        "status" => false,
        "message" => "Something went wrong"

    ]);

}

?>