<?php

include '../includes/config.php';

header('Content-Type: application/json');

$id = intval($_POST['id'] ?? 0);

if ($id <= 0) {
    echo json_encode([
        "status" => 0,
        "msg" => "ID Missing"
    ]);
    exit;
}

$delete = $conn->query("DELETE FROM bank_contra WHERE id = $id");

if ($delete) {
    echo json_encode([
        "status" => 1,
        "msg" => "Deleted"
    ]);
} else {
    echo json_encode([
        "status" => 0,
        "msg" => "Delete Failed"
    ]);
}