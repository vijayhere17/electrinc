<?php

include '../includes/config.php';

header('Content-Type: application/json');

$date = $_GET['date'] ?? '';
$party_id = intval($_GET['party_id'] ?? 0);
$cash_bank_id = intval($_GET['cash_bank_id'] ?? 0);

$where = [];

if ($date != '') {
    $date = $conn->real_escape_string($date);
    $where[] = "DATE(t.transaction_date) = '$date'";
}

if ($party_id > 0) {
    $where[] = "t.party_id = $party_id";
}

if ($cash_bank_id > 0) {
    $where[] = "t.cash_bank_id = $cash_bank_id";
}

$whereSql = '';

if (count($where) > 0) {
    $whereSql = " WHERE " . implode(" AND ", $where);
}

$sql = "
SELECT
    t.*,
    a.account_name as party_name,
    b.account_name as cash_bank_name
FROM transactions t
LEFT JOIN accounts a ON a.id = t.party_id
LEFT JOIN accounts b ON b.id = t.cash_bank_id
$whereSql
ORDER BY t.id DESC
";

$result = $conn->query($sql);

$data = [];

while ($row = $result->fetch_assoc()) {
    $data[] = [
        "id" => $row['id'],
        "transaction_date" => date('d-m-Y', strtotime($row['transaction_date'])),
        "party_name" => $row['party_name'],
        "cash_bank_name" => $row['cash_bank_name'],
        "amount" => number_format($row['amount'], 2)
    ];
}

echo json_encode([
    "data" => $data
]);