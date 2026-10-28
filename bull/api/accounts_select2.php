<?php

include '../includes/config.php';

header('Content-Type: application/json');

$search = $_GET['q'] ?? '';
$type = $_GET['type'] ?? '';

$sql = "SELECT id, account_name FROM accounts WHERE 1=1";

if ($type == 'party') {
    $sql .= " AND account_type = 'party'";
}

if ($type == 'cashbank') {
    $sql .= " AND (account_type = 'cash' OR account_type = 'bank')";
}

if ($search != '') {
    $search = $conn->real_escape_string($search);
    $sql .= " AND account_name LIKE '%$search%'";
}

$sql .= " ORDER BY account_name ASC LIMIT 20";

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