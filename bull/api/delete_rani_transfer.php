<?php

include '../includes/config.php';

header('Content-Type: application/json');

$id = intval($_POST['id'] ?? 0);

if ($id <= 0) {
    echo json_encode([
        "msg" => "ID Missing"
    ]);
    exit;
}

$conn->query("DELETE FROM rani_transfer_items WHERE transfer_id = $id");

$delete = $conn->query("DELETE FROM rani_transfer WHERE id = $id");

if ($delete) {
    echo json_encode([
        "success" => "Deleted",
        "msg" => "Rani Transfer Deleted Successfully"
    ]);
} else {
    echo json_encode([
        "msg" => "Delete Failed"
    ]);
}
