<?php

include '../includes/config.php';

header('Content-Type: application/json');

$search = $_GET['q'] ?? '';

$sql = "SELECT id, account_name FROM accounts WHERE (account_type = 'cash' OR account_type = 'bank')";

if ($search != '') {
    $search = $conn->real_escape_string($search);
    $sql .= " AND account_name LIKE '%$search%'";
}

$sql .= " ORDER BY account_name ASC";

$result = $conn->query($sql);

$data = [];

while ($row = $result->fetch_assoc()) {
    $data[] = [
        "id" => $row['id'],
        "text" => $row['account_name']
    ];
}

echo json_encode([
    "results" => $data
]);