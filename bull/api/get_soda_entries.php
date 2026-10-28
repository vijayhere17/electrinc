<?php

include '../includes/config.php';

header('Content-Type: application/json');

$item_id = intval($_POST['item_id'] ?? 0);

if ($item_id <= 0) {
    echo json_encode([
        "data" => [],
        "recordsTotal" => 0,
        "recordsFiltered" => 0,
        "total_qty" => 0
    ]);
    exit;
}

$sql = "
SELECT
    e.*,
    a.account_name
FROM entries e
LEFT JOIN accounts a ON a.id = e.party_id
WHERE e.item_id = $item_id
AND (
    e.narration IS NULL
    OR (
        e.narration NOT LIKE 'Rani Entry%'
        AND e.narration NOT LIKE 'Rupu Entry%'
    )
)
ORDER BY e.id DESC
";

$result = $conn->query($sql);

$data = [];
$total_qty = 0;

while ($row = $result->fetch_assoc()) {
    $total_qty += $row['qty'];

    $cash_bill = $row['type_id'] == 1 ? 'Cash' : 'Bill';

    $data[] = [
        "id" => $row['id'],
        "party_id" => $row['party_id'],
        "account_name" => $row['account_name'],
        "entry_date" => $row['entry_date'],
        "qty" => number_format($row['qty'], 3),
        "cash_bill" => $cash_bill,
        "rate" => number_format($row['rate'], 2),
        "mcx" => number_format($row['mcx'], 2),
        "narration" => $row['narration']
    ];
}

echo json_encode([
    "data" => $data,
    "recordsTotal" => count($data),
    "recordsFiltered" => count($data),
    "total_qty" => number_format($total_qty, 3)
]);