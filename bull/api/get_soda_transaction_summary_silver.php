<?php

header('Content-Type: application/json');

include '../includes/config.php';

$draw  = intval($_POST['draw'] ?? 1);

$start = intval($_POST['start'] ?? 0);

$length = intval($_POST['length'] ?? 10);

$from_date = $_POST['from_date'] ?? '';

$to_date   = $_POST['to_date'] ?? '';

$party_id  = $_POST['party_id'] ?? '';

$where = " WHERE a.account_type = 'party' ";

if (!empty($from_date)) {

    $from_date = DateTime::createFromFormat(
        'd-m-Y',
        $from_date
    )->format('Y-m-d');

    $where .= " AND e.entry_date >= '$from_date' ";
}

if (!empty($to_date)) {

    $to_date = DateTime::createFromFormat(
        'd-m-Y',
        $to_date
    )->format('Y-m-d');

    $where .= " AND e.entry_date <= '$to_date' ";
}

if (!empty($party_id)) {

    $where .= " AND a.id = '$party_id' ";
}

$totalQuery = "

SELECT COUNT(DISTINCT a.id) AS total

FROM accounts a

LEFT JOIN entries e
ON e.party_id = a.id

$where

";

$totalResult = mysqli_query($conn, $totalQuery);

$totalRow = mysqli_fetch_assoc($totalResult);

$totalRecords = $totalRow['total'];

$query = "

SELECT

    a.id,

    a.account_name,

    ROUND(
        IFNULL(a.opening_silver,0)
        +
        IFNULL(SUM(e.pending_qty),0),
        3
    ) AS silver_pending,

    ROUND(
        IFNULL(SUM(

            CASE

                WHEN LOWER(e.bill_cash) = 'bill'

                THEN e.qty

                ELSE 0

            END

        ),0),
        3
    ) AS silver_bill_soda_amount,

    ROUND(
        IFNULL(SUM(

            CASE

                WHEN LOWER(e.bill_cash) = 'cash'

                THEN e.qty

                ELSE 0

            END

        ),0),
        3
    ) AS silver_cash_soda_amount

FROM accounts a

LEFT JOIN entries e
ON e.party_id = a.id

$where

GROUP BY a.id

ORDER BY a.account_name ASC

LIMIT $start, $length

";

$result = mysqli_query($conn, $query);

$data = [];

while($row = mysqli_fetch_assoc($result)){

    $data[] = [

        "id" => $row['id'],

        "account_name" => $row['account_name'],

        "silver_pending" => $row['silver_pending'],

        "silver_bill_soda_amount" =>
            $row['silver_bill_soda_amount'],

        "silver_cash_soda_amount" =>
            $row['silver_cash_soda_amount']

    ];
}

echo json_encode([

    "draw" => $draw,

    "recordsTotal" => $totalRecords,

    "recordsFiltered" => $totalRecords,

    "data" => $data

]);