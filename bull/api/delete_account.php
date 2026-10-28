<?php

include '../includes/config.php';

header('Content-Type: application/json');

$id = intval($_POST['id'] ?? 0);

if ($id <= 0) {
    echo json_encode(["success" => false, "msg" => "Invalid ID"]);
    exit;
}

$conn->query("DELETE FROM accounts WHERE id = $id");

echo json_encode(["success" => true, "msg" => "Deleted"]);