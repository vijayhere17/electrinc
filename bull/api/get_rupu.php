<?php

include '../includes/config.php';

header('Content-Type: application/json');

$response = [
    "data" => []
];

$sql = "
SELECT
    r.id,
    r.entry_date,
    a.account_name,
    IFNULL(SUM(ri.gross), 0) AS total_gross,
    IFNULL(SUM(ri.fine), 0) AS total_fine
FROM rupu r
LEFT JOIN accounts a ON a.id = r.party_id
LEFT JOIN rupu_items ri ON ri.rupu_id = r.id
GROUP BY r.id
ORDER BY r.id DESC
";

$query = $conn->query($sql);

while ($row = $query->fetch_assoc()) {

    $edit_btn = '
        <a
            href="rupu.php?id='.$row['id'].'"
            class="btn btn-info btn-sm"
        >
            <i class="fa fa-edit"></i>
        </a>
    ';

    $delete_btn = '
        <button
            type="button"
            class="btn btn-danger btn-sm delete_rupu"
            data-id="'.$row['id'].'"
        >
            <i class="fa fa-times"></i>
        </button>
    ';

    $response['data'][] = [
        $edit_btn . ' ' . $delete_btn,
        date('d-m-Y', strtotime($row['entry_date'])),
        $row['account_name'],
        number_format($row['total_gross'], 3),
        number_format($row['total_fine'], 3)
    ];

}

echo json_encode($response);