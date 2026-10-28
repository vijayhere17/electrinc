<?php

include '../includes/config.php';

header('Content-Type: application/json');

$id = intval($_POST['id'] ?? 0);

if ($id <= 0) {
    echo json_encode([
        "errors" => ["ID Missing"]
    ]);
    exit;
}

$conn->query("DELETE FROM rani_items WHERE rani_id = $id");

$delete = $conn->query("DELETE FROM rani WHERE id = $id");

if ($delete) {
    echo json_encode([
        "success" => "Deleted",
        "msg" => "Rani Deleted Successfully"
    ]);
} else {
    echo json_encode([
        "errors" => ["Delete Failed"]
    ]);
}
