
<?php

include '../includes/config.php';

header('Content-Type: application/json');

$party_id = $_POST['party_id'] ?? '';

$as_on_date = $_POST['as_on_date'] ?? '';

if($as_on_date != ''){

    $as_on_date = date(
        'Y-m-d',
        strtotime($as_on_date)
    );

}
else{

    $as_on_date = date('Y-m-d');

}

if(
    $as_on_date == '1970-01-01'
    ||
    $as_on_date == ''
){

    $as_on_date = date('Y-m-d');

}

$where = "
WHERE a.account_type = 'party'
";

if(
    $party_id != ''
    &&
    $party_id != 'null'
    &&
    $party_id != 0
){

    $party_id = intval($party_id);

    $where .= "
    AND a.id = '$party_id'
    ";

}

$sql = "

SELECT

    a.id,
    a.account_name,

    a.opening_amount,
    a.opening_gold,
    a.opening_silver,

    /* ---------------- GOLD ---------------- */

    COALESCE(SUM(
        CASE
            WHEN e.item_id = 1
            THEN e.qty
            ELSE 0
        END
    ),0) as gold_qty,

    COALESCE(SUM(
        CASE
            WHEN e.item_id = 1
            THEN (e.qty * e.rate)
            ELSE 0
        END
    ),0) as gold_amount,

    COALESCE(SUM(
        CASE
            WHEN e.item_id = 1
            AND e.bill_cash = 'Bill'
            THEN e.qty
            ELSE 0
        END
    ),0) as gold_bill_qty,

    COALESCE(SUM(
        CASE
            WHEN e.item_id = 1
            AND e.bill_cash = 'Bill'
            THEN (e.qty * e.rate)
            ELSE 0
        END
    ),0) as gold_bill_amount,

    COALESCE(SUM(
        CASE
            WHEN e.item_id = 1
            AND e.bill_cash = 'Cash'
            THEN e.qty
            ELSE 0
        END
    ),0) as gold_cash_qty,

    COALESCE(SUM(
        CASE
            WHEN e.item_id = 1
            AND e.bill_cash = 'Cash'
            THEN (e.qty * e.rate)
            ELSE 0
        END
    ),0) as gold_cash_amount,

    /* ---------------- SILVER ---------------- */

    COALESCE(SUM(
        CASE
            WHEN e.item_id = 2
            THEN e.qty
            ELSE 0
        END
    ),0) as silver_qty,

    COALESCE(SUM(
        CASE
            WHEN e.item_id = 2
            THEN (e.qty * e.rate)
            ELSE 0
        END
    ),0) as silver_amount,

    COALESCE(SUM(
        CASE
            WHEN e.item_id = 2
            AND e.bill_cash = 'Bill'
            THEN e.qty
            ELSE 0
        END
    ),0) as silver_bill_qty,

    COALESCE(SUM(
        CASE
            WHEN e.item_id = 2
            AND e.bill_cash = 'Bill'
            THEN (e.qty * e.rate)
            ELSE 0
        END
    ),0) as silver_bill_amount,

    COALESCE(SUM(
        CASE
            WHEN e.item_id = 2
            AND e.bill_cash = 'Cash'
            THEN e.qty
            ELSE 0
        END
    ),0) as silver_cash_qty,

    COALESCE(SUM(
        CASE
            WHEN e.item_id = 2
            AND e.bill_cash = 'Cash'
            THEN (e.qty * e.rate)
            ELSE 0
        END
    ),0) as silver_cash_amount

FROM accounts a

LEFT JOIN entries e
ON e.party_id = a.id
AND e.entry_date <= '$as_on_date'

$where

GROUP BY a.id

ORDER BY MAX(e.created_at) DESC

";

$query = mysqli_query($conn, $sql);

$data = [];

$total_balance_amount = 0;
$total_balance_gold = 0;
$total_balance_silver = 0;

$total_net_gold_soda_qty = 0;
$total_net_gold_bill_soda_qty = 0;
$total_net_gold_cash_soda_qty = 0;

$total_net_silver_soda_qty = 0;
$total_net_silver_bill_soda_qty = 0;
$total_net_silver_cash_soda_qty = 0;

$total_net_gold_soda_amount = 0;
$total_net_gold_bill_soda_amount = 0;
$total_net_gold_cash_soda_amount = 0;

$total_net_silver_soda_amount = 0;
$total_net_silver_bill_soda_amount = 0;
$total_net_silver_cash_soda_amount = 0;

while($row = mysqli_fetch_assoc($query)){

    $balance_amount = floatval($row['opening_amount']);
    $balance_gold = floatval($row['opening_gold']);
    $balance_silver = floatval($row['opening_silver']);

    $net_gold_soda_qty = floatval($row['gold_qty']);
    $net_gold_bill_soda_qty = floatval($row['gold_bill_qty']);
    $net_gold_cash_soda_qty = floatval($row['gold_cash_qty']);

    $net_silver_soda_qty = floatval($row['silver_qty']);
    $net_silver_bill_soda_qty = floatval($row['silver_bill_qty']);
    $net_silver_cash_soda_qty = floatval($row['silver_cash_qty']);

    $net_gold_soda_amount = floatval($row['gold_amount']);
    $net_gold_bill_soda_amount = floatval($row['gold_bill_amount']);
    $net_gold_cash_soda_amount = floatval($row['gold_cash_amount']);

    $net_silver_soda_amount = floatval($row['silver_amount']);
    $net_silver_bill_soda_amount = floatval($row['silver_bill_amount']);
    $net_silver_cash_soda_amount = floatval($row['silver_cash_amount']);

    $total_balance_amount += $balance_amount;
    $total_balance_gold += $balance_gold;
    $total_balance_silver += $balance_silver;

    $total_net_gold_soda_qty += $net_gold_soda_qty;
    $total_net_gold_bill_soda_qty += $net_gold_bill_soda_qty;
    $total_net_gold_cash_soda_qty += $net_gold_cash_soda_qty;

    $total_net_silver_soda_qty += $net_silver_soda_qty;
    $total_net_silver_bill_soda_qty += $net_silver_bill_soda_qty;
    $total_net_silver_cash_soda_qty += $net_silver_cash_soda_qty;

    $total_net_gold_soda_amount += $net_gold_soda_amount;
    $total_net_gold_bill_soda_amount += $net_gold_bill_soda_amount;
    $total_net_gold_cash_soda_amount += $net_gold_cash_soda_amount;

    $total_net_silver_soda_amount += $net_silver_soda_amount;
    $total_net_silver_bill_soda_amount += $net_silver_bill_soda_amount;
    $total_net_silver_cash_soda_amount += $net_silver_cash_soda_amount;

    $data[] = [

        "id" => $row['id'],

        "account_name" => $row['account_name'],

        "balance_amount" => number_format(
            $balance_amount,
            2,
            '.',
            ''
        ),

        "balance_gold" => number_format(
            $balance_gold,
            3,
            '.',
            ''
        ),

        "balance_silver" => number_format(
            $balance_silver,
            3,
            '.',
            ''
        ),

        "net_gold_soda_qty" =>
        $net_gold_soda_qty,

        "net_gold_bill_soda_qty" =>
        $net_gold_bill_soda_qty,

        "net_gold_cash_soda_qty" =>
        $net_gold_cash_soda_qty,

        "net_silver_soda_qty" =>
        $net_silver_soda_qty,

        "net_silver_bill_soda_qty" =>
        $net_silver_bill_soda_qty,

        "net_silver_cash_soda_qty" =>
        $net_silver_cash_soda_qty,

        "net_gold_soda_amount" =>
        $net_gold_soda_amount,

        "net_gold_bill_soda_amount" =>
        $net_gold_bill_soda_amount,

        "net_gold_cash_soda_amount" =>
        $net_gold_cash_soda_amount,

        "net_silver_soda_amount" =>
        $net_silver_soda_amount,

        "net_silver_bill_soda_amount" =>
        $net_silver_bill_soda_amount,

        "net_silver_cash_soda_amount" =>
        $net_silver_cash_soda_amount

    ];

}

echo json_encode([

    "draw" => 1,

    "recordsTotal" => count($data),

    "recordsFiltered" => count($data),

    "data" => $data,

    "total_balance_amount" => number_format(
        $total_balance_amount,
        2,
        '.',
        ''
    ),

    "total_balance_gold" => number_format(
        $total_balance_gold,
        3,
        '.',
        ''
    ),

    "total_balance_silver" => number_format(
        $total_balance_silver,
        3,
        '.',
        ''
    ),

    "total_net_gold_soda_qty" =>
    $total_net_gold_soda_qty,

    "total_net_gold_bill_soda_qty" =>
    $total_net_gold_bill_soda_qty,

    "total_net_gold_cash_soda_qty" =>
    $total_net_gold_cash_soda_qty,

    "total_net_silver_soda_qty" =>
    $total_net_silver_soda_qty,

    "total_net_silver_bill_soda_qty" =>
    $total_net_silver_bill_soda_qty,

    "total_net_silver_cash_soda_qty" =>
    $total_net_silver_cash_soda_qty,

    "total_net_gold_soda_amount" =>
    $total_net_gold_soda_amount,

    "total_net_gold_bill_soda_amount" =>
    $total_net_gold_bill_soda_amount,

    "total_net_gold_cash_soda_amount" =>
    $total_net_gold_cash_soda_amount,

    "total_net_silver_soda_amount" =>
    $total_net_silver_soda_amount,

    "total_net_silver_bill_soda_amount" =>
    $total_net_silver_bill_soda_amount,

    "total_net_silver_cash_soda_amount" =>
    $total_net_silver_cash_soda_amount

]);

?>
