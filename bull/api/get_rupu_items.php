<?php

include '../includes/config.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');

$rupu_id = intval($_POST['rupu_id'] ?? 0);

$sql = "SELECT ri.id AS rupu_item_id, ri.rupu_id, ri.rupu_gross, ri.actual_touch, ri.actual_fine, ri.narration, ri.party_id, r.entry_date, a.name AS party_name
    FROM rupu_items ri
    LEFT JOIN rupu r ON ri.rupu_id = r.id
    LEFT JOIN accounts a ON ri.party_id = a.id";

if ($rupu_id > 0) {
    $sql .= " WHERE ri.rupu_id = $rupu_id";
}

$sql .= " ORDER BY r.entry_date DESC, ri.id DESC";

$res = $conn->query($sql);
if(!$res){
    die($conn->error);
}
$rows = [];

if ($res) {
    while ($row = $res->fetch_assoc()) {
        $rows[] = [
            'rupu_item_id' => $row['rupu_item_id'],
            'rupu_id' => $row['rupu_id'],
            'rupu_date' => ($row['entry_date']) ? date('d-m-Y', strtotime($row['entry_date'])) : '',
            'rupu_gross' => $row['rupu_gross'],
            'actual_touch' => $row['actual_touch'],
            'actual_fine' => $row['actual_fine'],
            'narration' => $row['narration'],
            'party_id' => $row['party_id'],
            'party_name' => $row['party_name']
        ];
    }
}

$data = [];

foreach($rows as $row){

    $data[] = [

        $row['rupu_gross'],
        $row['actual_touch'],
        $row['actual_fine'],
        $row['narration']

    ];

}

echo json_encode([
    "data" => $data
]);
