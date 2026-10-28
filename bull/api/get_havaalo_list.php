<?php

include '../includes/config.php';

header('Content-Type: application/json');

$response = [
    "data" => []
];

$date = $_GET['date'] ?? '';
$from_account = intval($_GET['from_account'] ?? 0);
$to_account = intval($_GET['to_account'] ?? 0);

$where = " WHERE 1=1 ";

if ($date != '') {

    $date_obj = DateTime::createFromFormat('d-m-Y', $date);

    if($date_obj){
        $date = $date_obj->format('Y-m-d');
        $where .= " AND h.entry_date = '$date' ";
    }
}

if ($from_account > 0) {
    $where .= " AND h.from_account = $from_account ";
}

if ($to_account > 0) {
    $where .= " AND h.to_account = $to_account ";
}

$sql = "
SELECT
    h.*,
    fa.account_name AS from_account_name,
    ta.account_name AS to_account_name
FROM havaalo h
LEFT JOIN accounts fa ON fa.id = h.from_account
LEFT JOIN accounts ta ON ta.id = h.to_account
$where
ORDER BY h.id DESC
";

$query = $conn->query($sql);

while ($row = $query->fetch_assoc()) {

    $edit_btn = '
        <a
            href="havaalo.php?id='.$row['id'].'"
            class="btn btn-info btn-sm"
        >
            <i class="fa fa-edit"></i>
        </a>
    ';

    $delete_btn = '
        <button
            type="button"
            class="btn btn-danger btn-sm delete_havaalo"
            data-id="'.$row['id'].'"
        >
            <i class="fa fa-times"></i>
        </button>
    ';

    $response['data'][] = [
        $edit_btn . ' ' . $delete_btn,
        date('d-m-Y', strtotime($row['entry_date'])),
        $row['from_account_name'],
        $row['to_account_name'],
        number_format($row['gold'], 3),
        number_format($row['silver'], 3),
        number_format($row['amount'], 2)
    ];

}

echo json_encode($response);