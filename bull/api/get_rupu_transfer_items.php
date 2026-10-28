<?php

include '../includes/config.php';

header('Content-Type: application/json');

$response = [];

$party_id = intval($_GET['party_id'] ?? 0);

$where = "";

if ($party_id > 0) {
    $where = " WHERE r.party_id = $party_id ";
}

$sql = "
SELECT
    ri.id,
    ri.rupu_id,
    ri.gross,
    ri.touch_percent,
    ri.fine,
    ri.narration,
    r.entry_date,
    a.id AS party_id,
    a.account_name
FROM rupu_items ri
LEFT JOIN rupu r ON r.id = ri.rupu_id
LEFT JOIN accounts a ON a.id = r.party_id
$where
ORDER BY ri.id DESC
";

$query = $conn->query($sql);

while ($row = $query->fetch_assoc()) {
    $response[] = [
        "id" => $row['id'],
        "rupu_id" => $row['rupu_id'],
        "rupu_date" => date('d-m-Y', strtotime($row['entry_date'])),
        "rupu_gross" => $row['gross'],
        "actual_touch" => $row['touch_percent'],
        "changed_touch" => $row['touch_percent'],
        "actual_fine" => $row['fine'],
        "changed_fine" => $row['fine'],
        "party_id" => $row['party_id'],
        "party_name" => $row['account_name'],
        "narration" => $row['narration']
    ];
}

echo json_encode([
    "data" => $response
]);