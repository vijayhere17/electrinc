<?php

include '../includes/config.php';

header('Content-Type: application/json');

$id = intval($_POST['id'] ?? 0);

if ($id <= 0) {
    echo json_encode([]);
    exit;
}

$result = $conn->query("
SELECT
    a.*,
    ag.account_group_name as group_name
FROM accounts a
LEFT JOIN account_groups ag ON ag.id = a.account_group_id
WHERE a.id = $id
");

if ($result && $result->num_rows > 0) {
    $row = $result->fetch_assoc();
    echo json_encode($row);
} else {
    echo json_encode([]);
}