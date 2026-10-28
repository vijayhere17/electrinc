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
    bc.*,
    fa.account_name AS from_account_name,
    ta.account_name AS to_account_name
FROM bank_contra bc
LEFT JOIN accounts fa ON fa.id = bc.from_account
LEFT JOIN accounts ta ON ta.id = bc.to_account
WHERE bc.id = $id
LIMIT 1
";

$result = $conn->query($sql);

if ($result && $result->num_rows > 0) {
    $row = $result->fetch_assoc();
    echo json_encode($row);
} else {
    echo json_encode([]);
}