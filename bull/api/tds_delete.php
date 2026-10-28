<?php

include '../includes/config.php';

header('Content-Type: application/json');

$id =
intval($_POST['delete_havaalo_id'] ?? 0);

if($id <= 0){

    echo json_encode([

        "success" => false,
        "msg" => "Invalid ID"

    ]);

    exit;

}

$conn->begin_transaction();

try{

    mysqli_query(
        $conn,
        "

        DELETE FROM transaction_line_items

        WHERE transaction_id = '$id'

        "
    );

    mysqli_query(
        $conn,
        "

        DELETE FROM transactions

        WHERE id = '$id'

        "
    );

    $conn->commit();

    echo json_encode([

        "success" => true,
        "msg" => "Transaction Deleted Successfully"

    ]);

} catch(Exception $e){

    $conn->rollback();

    echo json_encode([

        "success" => false,
        "msg" => "Delete Failed"

    ]);

}

?>