<?php

include '../includes/config.php';

header('Content-Type: application/json');

try {

    $transaction_date_raw =
    $_POST['transaction_date'] ?? '';

    if(!empty($transaction_date_raw)){

        $transaction_date = date(
            'Y-m-d',
            strtotime(
                str_replace(
                    '/',
                    '-',
                    $transaction_date_raw
                )
            )
        );

    } else {

        $transaction_date = date('Y-m-d');

    }

    $party_id =
    intval($_POST['party_id'] ?? 0);

    $cash_bank_id =
    intval($_POST['cash_bank_id'] ?? 0);

    $item_id =
    intval($_POST['item_id'] ?? 1);

    $rate =
    floatval($_POST['rate'] ?? 0);

    $qty =
    floatval($_POST['qty'] ?? 0);

    $mcx =
    floatval($_POST['mcx'] ?? 0);

    $narration =
    $conn->real_escape_string(
        trim($_POST['narration'] ?? '')
    );

    $amount =
    floatval($_POST['amount'] ?? 0);

    if($qty > 0){

        $amount = -abs($amount);

    } else {

        $amount = abs($amount);

    }

    if(empty($party_id)){

        echo json_encode([

            "success" => false,
            "msg" => "Please Select Account"

        ]);

        exit;

    }

    $conn->begin_transaction();

    $insert_sql = "

    INSERT INTO transactions
    (

        transaction_date,
        party_id,
        cash_bank_id,
        amount,
        item_id,
        rate,
        qty,
        mcx,
        narration

    )

    VALUES
    (

        '$transaction_date',
        '$party_id',
        '$cash_bank_id',
        '$amount',
        '$item_id',
        '$rate',
        '$qty',
        '$mcx',
        '$narration'

    )

    ";

    $insert_result =
    $conn->query($insert_sql);

    if(!$insert_result){

        throw new Exception("Insert Failed");

    }

    $transaction_id =
    $conn->insert_id;

    $total_tds = 0;

    if(isset($_POST['line_items_data'])){

        $items = json_decode(
            $_POST['line_items_data'],
            true
        );

        if(is_array($items)){

            foreach($items as $item){

                $entry_id =
                intval($item['id'] ?? 0);

                $settle_qty =
floatval(
    $item['entry_trans_qty'] ?? 0
);

                $settle_amount =
                floatval(
                    $item['checked_amount'] ?? 0
                );

                $conn->query("

                INSERT INTO transaction_line_items
                (

                    transaction_id,
                    entry_id,
                    settle_qty,
                    settle_amount

                )

                VALUES
                (

                    '$transaction_id',
                    '$entry_id',
                    '$settle_qty',
                    '$settle_amount'

                )

                ");

                $getEntry = $conn->query("

                SELECT
                    pending_qty,
                    tds

                FROM entries

                WHERE id = '$entry_id'

                ");

                $current_pending = 0;

                $tds_percent = 0;

                if(
                    $getEntry
                    &&
                    $entryRow =
                    $getEntry->fetch_assoc()
                ){

                    $current_pending =
                    floatval(
                        $entryRow['pending_qty']
                    );

                    $tds_percent =
                    floatval(
                        $entryRow['tds']
                    );

                }

                $line_tds =
                abs($settle_amount)
                *
                $tds_percent
                / 100;

                $total_tds += $line_tds;

                if($current_pending < 0){

                    $new_pending =
                    $current_pending + $settle_qty;

                } else {

                    $new_pending =
                    $current_pending - $settle_qty;

                }

                if(abs($new_pending) < 0.0001){

                    $new_pending = 0;

                }

                $conn->query("

                UPDATE entries

                SET pending_qty = '$new_pending'

                WHERE id = '$entry_id'

                ");
                
                $entry_type_id =
intval($item['type_id'] ?? 1);

$entry_item_id =
intval($item['item_id'] ?? 1);

$entry_rate =
floatval($item['rate'] ?? 0);

$entry_mcx =
floatval($item['mcx'] ?? 0);

$entry_narration =
$conn->real_escape_string(
    $item['narration'] ?? ''
);

$new_qty = -1 * $settle_qty;

$conn->query("

INSERT INTO entries
(

    entry_date,
    party_id,
    item_id,
    bill_cash,
    type_id,
    rate,
    qty,
    pending_qty,
    mcx,
    narration,
    tds,
    created_at

)

VALUES
(

    '$transaction_date',
    '$party_id',
    '$entry_item_id',
    '".($entry_type_id == 2 ? 'Bill' : 'Cash')."',
    '$entry_type_id',
    '$entry_rate',
    '$new_qty',
    '0',
    '$entry_mcx',
    '$entry_narration',
    '$tds_percent',
    NOW()

)

");

            }

        }

    }

    if($total_tds > 0){

        $tds_account = 0;

        $tdsQuery = $conn->query("

        SELECT id

        FROM accounts

        WHERE account_name = 'TDS Account'

        LIMIT 1

        ");

        if(
            $tdsQuery
            &&
            $tdsRow =
            $tdsQuery->fetch_assoc()
        ){

            $tds_account =
            intval($tdsRow['id']);

        }

        if($tds_account > 0){

            $tds_amount =
            abs($total_tds);

            $conn->query("

            INSERT INTO transactions
            (

                transaction_date,
                party_id,
                cash_bank_id,
                amount,
                item_id,
                rate,
                qty,
                mcx,
                narration

            )

            VALUES
            (

                '$transaction_date',
                '$party_id',
                '$tds_account',
                '$tds_amount',
                '$item_id',
                '0',
                '0',
                '0',
                'TDS Entry'

            )

            ");

        }

    }

    $party_name = '';

    $partyQuery = $conn->query("

    SELECT
        account_name,
        opening_amount,
        opening_gold,
        opening_silver

    FROM accounts

    WHERE id = '$party_id'

    ");

    $amount_balance = 0;

    $gold_balance = 0;

    $silver_balance = 0;

    if(
        $partyQuery
        &&
        $partyRow =
        $partyQuery->fetch_assoc()
    ){

        $party_name =
        $partyRow['account_name'];

        $amount_balance =
        floatval($partyRow['opening_amount']);

        $gold_balance =
        floatval($partyRow['opening_gold']);

        $silver_balance =
        floatval($partyRow['opening_silver']);

    }

    $getEntries = $conn->query("

    SELECT

        item_id,
        qty,
        rate

    FROM entries

    WHERE party_id = '$party_id'

    ");

    while($entry = $getEntries->fetch_assoc()){

        $entry_qty =
        floatval($entry['qty']);

        $entry_amount =
        abs(
            $entry_qty *
            floatval($entry['rate'])
        );

        if($entry['item_id'] == 1){

            $gold_balance += $entry_qty;

        }

        if($entry['item_id'] == 2){

            $silver_balance += $entry_qty;

        }

        if($entry_qty > 0){

            $amount_balance -= $entry_amount;

        } else {

            $amount_balance += $entry_amount;

        }

    }

    $getTransactions = $conn->query("

    SELECT amount

    FROM transactions

    WHERE party_id = '$party_id'

    ");

    while($txn = $getTransactions->fetch_assoc()){

        $amount_balance +=
        floatval($txn['amount']);

    }

    $conn->commit();

    echo json_encode([

        "success" => "Added",

        "msg" => "Transaction Successfully Added",

        "party_name" => $party_name,

        "gold_balance" =>
        rtrim(
            rtrim(
                number_format($gold_balance,4,'.',''),
                '0'
            ),
            '.'
        ),

        "silver_balance" =>
        rtrim(
            rtrim(
                number_format($silver_balance,4,'.',''),
                '0'
            ),
            '.'
        ),

        "amount_balance" =>
        rtrim(
            rtrim(
                number_format($amount_balance,2,'.',''),
                '0'
            ),
            '.'
        ),

        "transaction_id" =>
        $transaction_id

    ]);

} catch(Exception $e){

    $conn->rollback();

    echo json_encode([

        "success" => false,

        "msg" => $e->getMessage()

    ]);

}

?>