<?php

include '../includes/config.php';

header('Content-Type: application/json');

$id = intval($_GET['id'] ?? 0);

if ($id <= 0) {
    echo json_encode([
        "success" => false,
        "msg" => "Invalid ID"
    ]);
    exit;
}

$sql = "DELETE FROM entries WHERE id = $id";

$query = $conn->query($sql);

if ($query) {
    echo json_encode([
        "success" => "Deleted",
        "msg" => "Entry Deleted"
    ]);
} else {
    echo json_encode([
        "success" => false,
        "msg" => "Delete Failed"
    ]);
}