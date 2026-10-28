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

$conn->query("DELETE FROM rupu_items WHERE rupu_id = $id");

$delete = $conn->query("DELETE FROM rupu WHERE id = $id");

if ($delete) {
    echo json_encode([
        "success" => "Deleted",
        "msg" => "Rupu Deleted Successfully"
    ]);
} else {
    echo json_encode([
        "msg" => "Delete Failed"
    ]);
}
