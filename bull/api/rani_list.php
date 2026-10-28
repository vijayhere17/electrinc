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

    a.account_name AS party_name,

    IFNULL(SUM(ri.gross), 0) AS total_gross,

    IFNULL(SUM(ri.fine_after_badlo), 0) AS total_fine

FROM rani r

LEFT JOIN accounts a
ON a.id = r.party_id

LEFT JOIN rani_items ri
ON ri.rani_id = r.id

GROUP BY r.id

ORDER BY r.id DESC

";

$query = $conn->query($sql);

if($query){

    while($row = $query->fetch_assoc()){

        $edit_btn = '

            <a
                href="rani.php?id='.$row['id'].'"
                class="btn btn-info btn-sm"
            >
                <i class="fa fa-edit"></i>
            </a>

        ';

        $delete_btn = '

            <button
                type="button"
                class="btn btn-danger btn-sm delete_rani"
                data-id="'.$row['id'].'"
            >
                <i class="fa fa-times"></i>
            </button>

        ';

        $response['data'][] = [

            $edit_btn . ' ' . $delete_btn,

            date(
                'd-m-Y',
                strtotime($row['entry_date'])
            ),

            $row['party_name'],

            number_format(
                $row['total_gross'],
                2
            ),

            number_format(
                $row['total_fine'],
                2
            )

        ];

    }

}

echo json_encode($response);

?>