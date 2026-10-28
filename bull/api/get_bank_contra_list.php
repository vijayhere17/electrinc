<?php

include '../includes/config.php';

header('Content-Type: application/json');

$date = $_GET['date'] ?? '';
$from_id = intval($_GET['from_id'] ?? 0);
$to_id = intval($_GET['to_id'] ?? 0);

$where = " WHERE 1=1 ";

if ($date != '') {
    $date = date('Y-m-d', strtotime($date));
    $date = $conn->real_escape_string($date);
    $where .= " AND bc.entry_date = '$date' ";
}

if ($from_id > 0) {
    $where .= " AND bc.from_account = $from_id ";
}

if ($to_id > 0) {
    $where .= " AND bc.to_account = $to_id ";
}

$query = "
SELECT
    bc.id,
    bc.entry_date,
    bc.amount,
    bc.narration,
    from_acc.account_name AS from_account_name,
    to_acc.account_name AS to_account_name
FROM bank_contra bc
LEFT JOIN accounts from_acc ON from_acc.id = bc.from_account
LEFT JOIN accounts to_acc ON to_acc.id = bc.to_account
$where
ORDER BY bc.id DESC
";

$result = $conn->query($query);

$data = [];

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $data[] = [
            "id" => $row['id'],
            "entry_date" => date('d-m-Y', strtotime($row['entry_date'])),
            "from_account" => $row['from_account_name'],
            "to_account" => $row['to_account_name'],
            "amount" => number_format($row['amount'], 2),
            "narration" => $row['narration']
        ];
    }
}

echo json_encode([
    "data" => $data
]);