<?php

include '../includes/config.php';

header('Content-Type: application/json');

$data = [];

$sql = "
SELECT
    a.*,
    ag.account_group_name as group_name
FROM accounts a
LEFT JOIN account_groups ag ON ag.id = a.account_group_id
ORDER BY a.id DESC
";

$result = $conn->query($sql);

while ($row = $result->fetch_assoc()) {
    $data[] = [
        "id" => $row['id'],
        "account_name" => $row['account_name'],
        "group_name" => $row['group_name'],
        "mobile_no" => $row['mobile_no'],
        "address" => $row['address'],
        "tds" => $row['tds'],
        "is_cash_bank" => $row['is_cash_bank'] == 1 ? 'Yes' : 'No'
    ];
}

echo json_encode([
    "data" => $data
]);