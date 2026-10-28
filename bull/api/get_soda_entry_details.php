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
    e.*,
    a.account_name
FROM entries e
LEFT JOIN accounts a ON a.id = e.party_id
WHERE e.id = $id
";

$result = $conn->query($sql);

if (!$result) {
    echo json_encode([]);
    exit;
}

$row = $result->fetch_assoc();

if (!$row) {
    echo json_encode([]);
    exit;
}

$row['account_name'] = $row['account_name'] ?? '';
$row['entry_date'] = date('d-m-Y', strtotime($row['entry_date']));

echo json_encode($row);