<?php

include '../includes/config.php';

header('Content-Type: application/json');

$id = intval($_GET['id'] ?? 0);

if ($id <= 0) {
    echo json_encode([]);
    exit;
}

$sql = "
SELECT
    h.*,
    fa.account_name AS from_account_name,
    ta.account_name AS to_account_name
FROM havaalo h
LEFT JOIN accounts fa ON fa.id = h.from_account
LEFT JOIN accounts ta ON ta.id = h.to_account
WHERE h.id = $id
LIMIT 1
";

$query = $conn->query($sql);

if ($query && $query->num_rows > 0) {
    $row = $query->fetch_assoc();
    echo json_encode($row);
} else {
    echo json_encode([]);
}