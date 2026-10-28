<?php

include '../includes/config.php';

header('Content-Type: application/json');

$rani_id = intval($_POST['rani_id'] ?? 0);

$data = [];

$query = $conn->query("
SELECT
    gross AS rani_gross,
    touch_percent AS touch,
    fine_after_badlo AS fine,
    narration AS rani_narration
FROM rani_items
WHERE rani_id = $rani_id
");

while ($row = $query->fetch_assoc()) {
    $data[] = $row;
}

echo json_encode([
    "data" => $data
]);