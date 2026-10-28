<?php

include '../includes/config.php';

header('Content-Type: application/json');

$draw  = intval($_POST['draw'] ?? 1);
$start = intval($_POST['start'] ?? 0);
$length = intval($_POST['length'] ?? 10);

$from_date = $_POST['from_date'] ?? '';
$to_date   = $_POST['to_date'] ?? '';
$party_id  = intval($_POST['party_id'] ?? 0);

$where = " WHERE 1=1 ";

if (!empty($from_date)) {
    $from_date = DateTime::createFromFormat('d-m-Y', $from_date)->format('Y-m-d');
    $from_date = $conn->real_escape_string($from_date);
    $where .= " AND t.transaction_date >= '$from_date' ";
}

if (!empty($to_date)) {
    $to_date = DateTime::createFromFormat('d-m-Y', $to_date)->format('Y-m-d');
    $to_date = $conn->real_escape_string($to_date);
    $where .= " AND t.transaction_date <= '$to_date' ";
}

if ($party_id > 0) {
    $where .= " AND t.party_id = $party_id ";
}

$totalQuery = "
SELECT COUNT(*) AS total
FROM transactions t
$where
";

$totalResult = $conn->query($totalQuery);
$totalRow = $totalResult->fetch_assoc();
$totalRecords = $totalRow['total'];

$query = "
SELECT
    t.id,
    DATE_FORMAT(t.transaction_date, '%d-%m-%Y') AS havaalo_date_new,
    p.account_name AS from_name,
    'TDS Account' AS to_name,
    CASE WHEN t.amount > 0 THEN t.amount ELSE 0 END AS amount_in,
    CASE WHEN t.amount < 0 THEN ABS(t.amount) ELSE 0 END AS amount_out,
    t.narration
FROM transactions t
LEFT JOIN accounts p ON p.id = t.party_id
$where
ORDER BY t.transaction_date DESC, t.id DESC
LIMIT $start, $length
";

$result = $conn->query($query);

$data = [];
$total_amount_in = 0;
$total_amount_out = 0;

while ($row = $result->fetch_assoc()) {
    $total_amount_in += $row['amount_in'];
    $total_amount_out += $row['amount_out'];

    $data[] = [
        "id" => $row['id'],
        "havaalo_date_new" => $row['havaalo_date_new'],
        "from_name" => $row['from_name'],
        "to_name" => $row['to_name'],
        "amount_in" => number_format($row['amount_in'], 2),
        "amount_out" => number_format($row['amount_out'], 2),
        "narration" => $row['narration']
    ];
}

echo json_encode([
    "draw" => $draw,
    "recordsTotal" => $totalRecords,
    "recordsFiltered" => $totalRecords,
    "total_amount_in" => number_format($total_amount_in, 2),
    "total_amount_out" => number_format($total_amount_out, 2),
    "data" => $data
]);