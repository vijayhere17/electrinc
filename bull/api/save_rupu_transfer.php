<?php

include '../includes/config.php';

header('Content-Type: application/json');

function send_error($msg){

    echo json_encode([

        'success' => 'Error',
        'msg' => $msg

    ]);

    exit;

}

$entry_date =
trim($_POST['rupu_transfer_date'] ?? '');

$party_id =
intval($_POST['party_id'] ?? 0);

$narration =
$conn->real_escape_string(
    trim($_POST['narration'] ?? '')
);

$badlo =
trim($_POST['badlo'] ?? '0');

$after_badlo =
trim($_POST['after_badlo'] ?? '0');

$fine_badlo =
trim($_POST['fine_badlo'] ?? '0');

$transfer_id =
intval($_POST['id'] ?? 0);

if ($entry_date == '') {

    send_error('Select Date !');

}

if ($party_id <= 0) {

    send_error('Select Party required !');

}

$sql_date = date(
    'Y-m-d',
    strtotime($entry_date)
);

$selected_items = [];

if (
    isset($_POST['rupu_items'])
    &&
    is_array($_POST['rupu_items'])
){

    foreach ($_POST['rupu_items'] as $item_key => $item) {

        if (
            isset($item['cheked'])
            &&
            $item['cheked'] != ''
        ){

            $selected_items[] = [

                'rupu_item_id' =>
                intval($item_key),

                'rupu_id' =>
                intval($item['rupu_id'] ?? 0),

                'gross' =>
                $item['rupu_gross'] ?? 0,

                'actual_touch' =>
                $item['actual_touch'] ?? 0,

                'changed_touch' =>
                $item['changed_touch'] ?? 0,

                'actual_fine' =>
                $item['actual_fine'] ?? 0,

                'changed_fine' =>
                $item['changed_fine'] ?? 0,

                'narration' =>
                $conn->real_escape_string(
                    $item['narration'] ?? ''
                )

            ];

        }

    }

}

if (count($selected_items) == 0) {

    send_error('Tick atleast One Rupu !');

}

$conn->begin_transaction();

try {

    if ($transfer_id == 0) {

        $stmt = $conn->prepare("

        INSERT INTO rupu_transfer
        (

            entry_date,
            party_id,
            narration,
            badlo,
            after_badlo,
            fine_badlo,
            created_at

        )

        VALUES
        (

            ?, ?, ?, ?, ?, ?, NOW()

        )

        ");

        $stmt->bind_param(

            "sissss",

            $sql_date,
            $party_id,
            $narration,
            $badlo,
            $after_badlo,
            $fine_badlo

        );

        $stmt->execute();

        $transfer_id_to_use =
        $conn->insert_id;

        $stmt->close();

        $action = 'Added';

    } else {

        $stmt = $conn->prepare("

        UPDATE rupu_transfer SET

            entry_date = ?,
            party_id = ?,
            narration = ?,
            badlo = ?,
            after_badlo = ?,
            fine_badlo = ?

        WHERE id = ?

        ");

        $stmt->bind_param(

            "sissssi",

            $sql_date,
            $party_id,
            $narration,
            $badlo,
            $after_badlo,
            $fine_badlo,
            $transfer_id

        );

        $stmt->execute();

        $stmt->close();

        $conn->query("

        DELETE FROM rupu_transfer_items

        WHERE transfer_id = '$transfer_id'

        ");

        $transfer_id_to_use =
        $transfer_id;

        $action = 'Updated';

    }

    $item_stmt = $conn->prepare("

    INSERT INTO rupu_transfer_items
    (

        transfer_id,
        rupu_id,
        rupu_item_id,
        gross,
        actual_touch,
        changed_touch,
        actual_fine,
        changed_fine,
        narration,
        created_at

    )

    VALUES
    (

        ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW()

    )

    ");

    foreach ($selected_items as $si) {

        $item_stmt->bind_param(

            "iiiddddss",

            $transfer_id_to_use,
            $si['rupu_id'],
            $si['rupu_item_id'],
            $si['gross'],
            $si['actual_touch'],
            $si['changed_touch'],
            $si['actual_fine'],
            $si['changed_fine'],
            $si['narration']

        );

        $item_stmt->execute();

    }

    $item_stmt->close();

    $conn->commit();

    echo json_encode([

        'success' => $action,
        'msg' => 'Rupu Transfer saved successfully'

    ]);

} catch (Exception $e) {

    $conn->rollback();

    send_error('Something error occurred');

}

?>