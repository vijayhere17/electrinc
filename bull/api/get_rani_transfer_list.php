<?php

include '../includes/config.php';

header('Content-Type: application/json');

$response = [
    "data" => []
];

$sql = "
SELECT
    rt.id,
    rt.entry_date,
    rt.narration,
    a.account_name,
    IFNULL(SUM(rti.gross), 0) AS total_gross,
    IFNULL(SUM(rti.changed_fine), 0) AS total_fine
FROM rani_transfer rt
LEFT JOIN accounts a ON a.id = rt.party_id
LEFT JOIN rani_transfer_items rti ON rti.transfer_id = rt.id
GROUP BY rt.id
ORDER BY rt.id DESC
";

$query = $conn->query($sql);

while ($row = $query->fetch_assoc()) {

    $edit_btn = '
        <a
            href="rani-transfer.php?id='.$row['id'].'"
            class="btn btn-info btn-sm"
        >
            <i class="fa fa-edit"></i>
        </a>
    ';

    $delete_btn = '
        <button
            type="button"
            class="btn btn-danger btn-sm delete_rani_transfer"
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
        number_format($row['total_fine'], 3),
        $row['narration']
    ];

}

echo json_encode($response);