<?php

include '../includes/config.php';

header('Content-Type: application/json');

$sql = "

SELECT

    rt.id,
    rt.entry_date,
    rt.party_id,
    rt.narration,
    rt.badlo,
    rt.after_badlo,
    rt.fine_badlo,
    rt.created_at,

    a.account_name AS party_name

FROM rupu_transfer rt

LEFT JOIN accounts a
ON rt.party_id = a.id

ORDER BY
rt.entry_date DESC,
rt.id DESC

";

$res = $conn->query($sql);

$data = [];

if ($res) {

    while ($row = $res->fetch_assoc()) {

        $data[] = [

            'id' =>
            $row['id'],

            'entry_date' =>
            ($row['entry_date'])
            ? date(
                'd-m-Y',
                strtotime($row['entry_date'])
            )
            : '',

            'party_name' =>
            $row['party_name'],

            'narration' =>
            $row['narration'],

            'badlo' =>
            $row['badlo'],

            'fine_badlo' =>
            $row['fine_badlo']

        ];

    }

}

echo json_encode([

    'data' => $data

]);

exit;

?>