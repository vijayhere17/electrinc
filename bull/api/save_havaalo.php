<?php

include '../includes/config.php';

header('Content-Type: application/json');

$id = $_POST['id'] ?? '';

$entry_date = $_POST['entry_date'] ?? '';

if($entry_date != ''){

    $entry_date = date(
        'Y-m-d',
        strtotime($entry_date)
    );

}

$from_account = $_POST['from_account'] ?? '';

$to_account = $_POST['to_account'] ?? '';

$gold = $_POST['gold'] ?? 0;

$silver = $_POST['silver'] ?? 0;

$amount = $_POST['amount'] ?? 0;

$narration = $_POST['narration'] ?? '';

$errors = [];

if ($entry_date == '') {

    $errors[] = "Date Required";

}

if ($from_account == '') {

    $errors[] = "From Account Required";

}

if ($to_account == '') {

    $errors[] = "To Account Required";

}

if ($from_account == $to_account) {

    $errors[] = "From and To account cannot be same";

}

if (
    $gold == '' &&
    $silver == '' &&
    $amount == ''
) {

    $errors[] = "Please enter Gold, Silver or Amount";

}

if (count($errors) > 0) {

    echo json_encode([

        "errors" => $errors

    ]);

    exit;

}

$id = intval($id);

$from_account = intval($from_account);

$to_account = intval($to_account);

$gold = floatval($gold);

$silver = floatval($silver);

$amount = floatval($amount);

$narration =
$conn->real_escape_string($narration);

if ($id == 0) {

    $sql = "

    INSERT INTO havaalo(

        entry_date,
        from_account,
        to_account,
        gold,
        silver,
        amount,
        narration,
        created_at

    ) VALUES (

        '$entry_date',
        '$from_account',
        '$to_account',
        '$gold',
        '$silver',
        '$amount',
        '$narration',
        NOW()

    )

    ";

    $query = $conn->query($sql);

    if ($query) {

        echo json_encode([

            "success" => "Added",
            "msg" => "Havaalo Saved Successfully"

        ]);

    } else {

        echo json_encode([

            "errors" => [

                "Save Failed"

            ]

        ]);

    }

} else {

    $sql = "

    UPDATE havaalo SET

        entry_date = '$entry_date',
        from_account = '$from_account',
        to_account = '$to_account',
        gold = '$gold',
        silver = '$silver',
        amount = '$amount',
        narration = '$narration'

    WHERE id = '$id'

    ";

    $query = $conn->query($sql);

    if ($query) {

        echo json_encode([

            "success" => "Updated",
            "msg" => "Havaalo Updated Successfully"

        ]);

    } else {

        echo json_encode([

            "errors" => [

                "Update Failed"

            ]

        ]);

    }

}

?>