<?php

include '../includes/config.php';

header('Content-Type: application/json');

$id = intval($_POST['id'] ?? 0);

if ($id <= 0) {
    echo json_encode([
        "success" => false,
        "message" => "Invalid ID"
    ]);
    exit;
}

$delete = $conn->query("DELETE FROM havaalo WHERE id = $id");

if ($delete) {
    echo json_encode([
        "success" => true,
        "message" => "Havaalo Deleted Successfully"
    ]);
} else {
    echo json_encode([
        "success" => false,
        "message" => "Delete Failed"
    ]);
}