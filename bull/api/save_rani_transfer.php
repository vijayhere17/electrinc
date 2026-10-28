<?php

include '../includes/config.php';

header('Content-Type: application/json');

$date =
$_POST['date'] ?? '';

if($date != ''){

    $date = date(
        'Y-m-d',
        strtotime($date)
    );

}

$party_id =
intval($_POST['party_id'] ?? 0);

$narration =
$conn->real_escape_string(
    $_POST['narration'] ?? ''
);

$checked_ids =
$_POST['checked_ids'] ?? [];

if($date == ''){

    echo json_encode([

        "msg" => "Date Required"

    ]);

    exit;

}

if($party_id <= 0){

    echo json_encode([

        "msg" => "Party Required"

    ]);

    exit;

}

if(empty($checked_ids)){

    echo json_encode([

        "msg" => "Select Atleast One Entry"

    ]);

    exit;

}

$conn->begin_transaction();

try{

    $insert = $conn->query("

    INSERT INTO rani_transfer
    (

        entry_date,
        party_id,
        narration

    )

    VALUES
    (

        '$date',
        '$party_id',
        '$narration'

    )

    ");

    if(!$insert){

        throw new Exception($conn->error);

    }

    $rani_transfer_id =
    $conn->insert_id;

    foreach($checked_ids as $item_id){

        $item_id = intval($item_id);

        $changed_touch =
        floatval(
            $_POST['changed_touch'][$item_id] ?? 0
        );

        $changed_fine =
        floatval(
            $_POST['changed_fine'][$item_id] ?? 0
        );

        $details = $conn->query("

        SELECT
            *
        FROM rani_items
        WHERE id = '$item_id'

        ");

        $row =
        $details->fetch_assoc();

        if(!$row){

            continue;

        }

        $save_item = $conn->query("

        INSERT INTO rani_transfer_items
        (

            transfer_id,
            rani_item_id,
            rani_id,
            gross,
            actual_touch,
            changed_touch,
            actual_fine,
            changed_fine,
            narration

        )

        VALUES
        (

            '".$rani_transfer_id."',
            '".$row['id']."',
            '".$row['rani_id']."',
            '".$row['gross']."',
            '".$row['touch_percent']."',
            '".$changed_touch."',
            '".$row['fine']."',
            '".$changed_fine."',
            '".$row['narration']."'

        )

        ");

        if(!$save_item){

            throw new Exception($conn->error);

        }

    }

    $conn->commit();

    echo json_encode([

        "success" => "Added",

        "msg" =>
        "Rani Transfer Saved Successfully"

    ]);

} catch(Exception $e){

    $conn->rollback();

    echo json_encode([

        "msg" => "Something went wrong"

    ]);

}

?>