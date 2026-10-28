<?php

include '../includes/config.php';

header('Content-Type: application/json');

$id = intval($_GET['id'] ?? 0);

$response = [];

if ($id <= 0) {
    echo json_encode($response);
    exit;
}

$main = $conn->query("SELECT * FROM rani_transfer WHERE id = $id");

$main_row = $main->fetch_assoc();

if (!$main_row) {
    echo json_encode($response);
    exit;
}

$response['main'] = [
    "id" => $main_row['id'],
    "entry_date" => date('d-m-Y', strtotime($main_row['entry_date'])),
    "party_id" => $main_row['party_id'],
    "narration" => $main_row['narration']
];

$items = [];

$query = $conn->query("
SELECT
    rti.*,
    a.account_name
FROM rani_transfer_items rti
LEFT JOIN rani_transfer rt ON rt.id = rti.transfer_id
LEFT JOIN accounts a ON a.id = rt.party_id
WHERE rti.transfer_id = $id
");

while ($row = $query->fetch_assoc()) {
    $items[] = [
        "id" => $row['rani_item_id'],
        "rani_date" => "",
        "rani_gross" => $row['gross'],
        "actual_touch" => $row['actual_touch'],
        "changed_touch" => $row['changed_touch'],
        "actual_fine" => $row['actual_fine'],
        "changed_fine" => $row['changed_fine'],
        "party_name" => $row['account_name'],
        "narration" => $row['narration']
    ];
}

$response['items'] = $items;

echo json_encode($response);