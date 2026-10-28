<?php

include '../includes/config.php';

header('Content-Type: application/json');

$draw  = intval($_POST['draw'] ?? 1);
$start = intval($_POST['start'] ?? 0);
$length = intval($_POST['length'] ?? 10);

$from_date = $_POST['from_date'] ?? '';
$to_date   = $_POST['to_date'] ?? '';
$party_id  = intval($_POST['party_id'] ?? 0);

$where = " WHERE a.account_type = 'party' ";

if (!empty($from_date)) {
    $from_date = date('Y-m-d', strtotime(str_replace('/', '-', $from_date)));
    $from_date = $conn->real_escape_string($from_date);
    $where .= " AND e.entry_date >= '$from_date' ";
}

if (!empty($to_date)) {
    $to_date = date('Y-m-d', strtotime(str_replace('/', '-', $to_date)));
    $to_date = $conn->real_escape_string($to_date);
    $where .= " AND e.entry_date <= '$to_date' ";
}

if ($party_id > 0) {
    $where .= " AND a.id = $party_id ";
}

$totalQuery = "
SELECT COUNT(DISTINCT a.id) AS total
FROM accounts a
LEFT JOIN entries e ON e.party_id = a.id
$where
";

$totalResult = $conn->query($totalQuery);
$totalRow = $totalResult->fetch_assoc();

$totalRecords = $totalRow['total'];

$query = "
SELECT
    a.id,
    a.account_name,
    ROUND(IFNULL(a.opening_gold, 0) + IFNULL(SUM(e.pending_qty), 0), 3) AS gold_pending,
    ROUND(IFNULL(SUM(CASE WHEN LOWER(e.bill_cash) = 'bill' THEN e.qty ELSE 0 END), 0), 3) AS gold_bill_soda_amount,
    ROUND(IFNULL(SUM(CASE WHEN LOWER(e.bill_cash) = 'cash' THEN e.qty ELSE 0 END), 0), 3) AS gold_cash_soda_amount
FROM accounts a
LEFT JOIN entries e ON e.party_id = a.id
$where
GROUP BY a.id
ORDER BY a.account_name ASC
LIMIT $start, $length
";

$result = $conn->query($query);

$data = [];

while ($row = $result->fetch_assoc()) {
    $data[] = [
        "id" => $row['id'],
        "account_name" => $row['account_name'],
        "gold_pending" => $row['gold_pending'],
        "gold_bill_soda_amount" => $row['gold_bill_soda_amount'],
        "gold_cash_soda_amount" => $row['gold_cash_soda_amount']
    ];
}

echo json_encode([
    "draw" => $draw,
    "recordsTotal" => $totalRecords,
    "recordsFiltered" => $totalRecords,
    "data" => $data
]);