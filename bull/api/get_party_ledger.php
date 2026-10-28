<?php

include '../includes/config.php';

header('Content-Type: application/json');

$party_id  = $_POST['party_id']  ?? '';
$from_date = $_POST['from_date'] ?? date('Y-m-d');
$to_date   = $_POST['to_date']   ?? date('Y-m-d');
$draw      = $_POST['draw']      ?? 1;

// Convert dd-mm-yyyy to yyyy-mm-dd if needed
function parseDate($d) {
    if (preg_match('/^\d{2}-\d{2}-\d{4}$/', trim($d))) {
        return date('Y-m-d', strtotime(str_replace('-', '/', $d)));
    }
    return $d;
}
$from_date = parseDate($from_date);
$to_date   = parseDate($to_date);

if ($party_id == '') {
    echo json_encode([
        "draw"            => intval($draw),
        "recordsTotal"    => 0,
        "recordsFiltered" => 0,
        "data"            => [],
        "total_tr_amount" => '',
        "total_net_amount"=> '',
    ]);
    exit;
}

$party_id = intval($party_id);

// ─────────────────────────────────────────────────────────────
// STEP 1: Opening balance = everything BEFORE from_date
// Includes: opening fields on account + all entries + all transactions before range
// ─────────────────────────────────────────────────────────────
$acct_sql = "SELECT opening_amount, opening_gold, opening_silver FROM accounts WHERE id = '$party_id' LIMIT 1";
$acct_res = mysqli_query($conn, $acct_sql);
$acct_row = mysqli_fetch_assoc($acct_res);

$opening_amount = (float)($acct_row['opening_amount'] ?? 0);
$opening_gold   = (float)($acct_row['opening_gold']   ?? 0);
$opening_silver = (float)($acct_row['opening_silver'] ?? 0);

// Entries before from_date
$pre_entries_sql = "
    SELECT e.item_id, e.qty, e.rate, i.item_name
    FROM entries e
    LEFT JOIN items i ON i.id = e.item_id
    WHERE e.party_id = '$party_id'
    AND DATE(e.entry_date) < '$from_date'
";
$pre_entries_res = mysqli_query($conn, $pre_entries_sql);
while ($r = mysqli_fetch_assoc($pre_entries_res)) {
    $qty  = (float)$r['qty'];
    $rate = (float)$r['rate'];
    if (strtolower($r['item_name']) == 'gold')   $opening_gold   += $qty;
    if (strtolower($r['item_name']) == 'silver')  $opening_silver += $qty;
    // amount: positive qty = we gave gold/silver = party owes us = reduce amount balance
    if ($qty > 0) $opening_amount -= abs($qty * $rate);
    else          $opening_amount += abs($qty * $rate);
}

// Transactions (cash payments) before from_date
$pre_txn_sql = "
    SELECT amount FROM transactions
    WHERE party_id = '$party_id'
    AND DATE(transaction_date) < '$from_date'
";
$pre_txn_res = mysqli_query($conn, $pre_txn_sql);
while ($r = mysqli_fetch_assoc($pre_txn_res)) {
    $opening_amount += (float)$r['amount'];
}

// ─────────────────────────────────────────────────────────────
// STEP 2: Entries IN the date range
// ─────────────────────────────────────────────────────────────
$entries_sql = "
    SELECT
        e.id,
        e.entry_date,
        e.item_id,
        e.bill_cash,
        e.type_id,
        e.rate,
        e.qty,
        e.narration,
        e.tds,
        i.item_name,
        'entry' AS source_type
    FROM entries e
    LEFT JOIN items i ON i.id = e.item_id
    WHERE e.party_id = '$party_id'
    AND DATE(e.entry_date) BETWEEN '$from_date' AND '$to_date'
    ORDER BY e.entry_date ASC, e.id ASC
";
$entries_res = mysqli_query($conn, $entries_sql);

// ─────────────────────────────────────────────────────────────
// STEP 3: Transactions (cash/bank payments) IN the date range
// ─────────────────────────────────────────────────────────────
$txn_sql = "
    SELECT
        t.id,
        t.transaction_date AS entry_date,
        t.amount,
        t.narration,
        COALESCE(a.account_name, '') AS cash_bank_name,
        'transaction' AS source_type
    FROM transactions t
    LEFT JOIN accounts a ON a.id = t.cash_bank_id
    WHERE t.party_id = '$party_id'
    AND DATE(t.transaction_date) BETWEEN '$from_date' AND '$to_date'
    ORDER BY t.transaction_date ASC, t.id ASC
";
$txn_res = mysqli_query($conn, $txn_sql);

// ─────────────────────────────────────────────────────────────
// STEP 4: Merge all rows sorted by date + id
// ─────────────────────────────────────────────────────────────
$all_rows = [];

while ($r = mysqli_fetch_assoc($entries_res)) {
    $r['sort_date'] = $r['entry_date'];
    $r['sort_id']   = (int)$r['id'];
    $all_rows[] = $r;
}
while ($r = mysqli_fetch_assoc($txn_res)) {
    $r['sort_date'] = $r['entry_date'];
    $r['sort_id']   = (int)$r['id'] + 1000000; // offset so entries come first on same date/id
    $r['item_name'] = '';
    $all_rows[] = $r;
}

usort($all_rows, function($a, $b) {
    if ($a['sort_date'] !== $b['sort_date']) return strcmp($a['sort_date'], $b['sort_date']);
    return $a['sort_id'] - $b['sort_id'];
});

// ─────────────────────────────────────────────────────────────
// STEP 5: Build ledger with running balance
// ─────────────────────────────────────────────────────────────
$data = [];

$net_amount = $opening_amount;
$net_gold   = $opening_gold;
$net_silver = $opening_silver;

// Opening row
$data[] = [
    'checkbox'       => '',
    'action'         => '',
    'row_date'       => '',
    'particular'     => 'Opening',
    'row_amount'     => number_format($opening_amount, 2, '.', ''),
    'balance_amount' => number_format($opening_amount, 2, '.', ''),
    'row_gold'       => number_format($opening_gold,   3, '.', ''),
    'balance_gold'   => number_format($opening_gold,   3, '.', ''),
    'row_silver'     => number_format($opening_silver, 3, '.', ''),
    'balance_silver' => number_format($opening_silver, 3, '.', ''),
];

foreach ($all_rows as $row) {

    if ($row['source_type'] === 'entry') {
        $qty    = (float)$row['qty'];
        $rate   = (float)$row['rate'];
        $amount = $qty * $rate;

        // Update running balances
        if (strtolower($row['item_name']) == 'gold')   $net_gold   += $qty;
        if (strtolower($row['item_name']) == 'silver')  $net_silver += $qty;
        if ($qty > 0) $net_amount -= abs($amount);
        else          $net_amount += abs($amount);

        $type      = ($row['type_id'] == 1) ? 'Cash' : 'Bill';
        $item_name = $row['item_name'] ?? '';

        // Sign for display: positive qty = we gave item = debit on party = negative amount displayed
        $display_amount = ($qty > 0) ? -abs($amount) : abs($amount);

        $data[] = [
            'checkbox'       => '<input type="checkbox" class="ledger_row_checkbox" data-amount="'.number_format($display_amount,2,'.','').'" data-gold="'.($item_name=='Gold'||$item_name=='gold'?number_format($qty,3,'.',''):'0').'" data-silver="'.($item_name=='Silver'||$item_name=='silver'?number_format($qty,3,'.',''):'0').'">',
            'action'         => '<a href="transaction.php?id='.$row['id'].'" class="btn btn-info btn-sm"><i class="fa fa-edit"></i></a>',
            'row_date'       => date('d-m-Y', strtotime($row['entry_date'])),
            'particular'     => ucfirst($type) . ' ' . $item_name,
            'row_amount'     => number_format($display_amount, 2, '.', ''),
            'balance_amount' => number_format($net_amount,    2, '.', ''),
            'row_gold'       => (strtolower($item_name) == 'gold')   ? number_format($qty, 3, '.', '') : '0.000',
            'balance_gold'   => number_format($net_gold,   3, '.', ''),
            'row_silver'     => (strtolower($item_name) == 'silver') ? number_format($qty, 3, '.', '') : '0.000',
            'balance_silver' => number_format($net_silver, 3, '.', ''),
        ];

    } else {
        // Transaction (cash/bank payment)
        $amount     = (float)$row['amount'];
        $net_amount += $amount;

        $display_amount = $amount;
        $cash_bank_name = $row['cash_bank_name'] ?? 'Cash/Bank';
        $narration      = $row['narration'] ?? '';

        $particular = 'Payment';
        if ($cash_bank_name) $particular .= ' (' . $cash_bank_name . ')';
        if ($narration)      $particular .= ' - ' . $narration;

        $data[] = [
            'checkbox'       => '<input type="checkbox" class="ledger_row_checkbox" data-amount="'.number_format($display_amount,2,'.','').'" data-gold="0" data-silver="0">',
            'action'         => '<a href="transaction.php?id='.$row['id'].'" class="btn btn-info btn-sm"><i class="fa fa-edit"></i></a>',
            'row_date'       => date('d-m-Y', strtotime($row['entry_date'])),
            'particular'     => $particular,
            'row_amount'     => number_format($display_amount, 2, '.', ''),
            'balance_amount' => number_format($net_amount,    2, '.', ''),
            'row_gold'       => '0.000',
            'balance_gold'   => number_format($net_gold,   3, '.', ''),
            'row_silver'     => '0.000',
            'balance_silver' => number_format($net_silver, 3, '.', ''),
        ];
    }
}

// Closing row
$data[] = [
    'checkbox'       => '',
    'action'         => '',
    'row_date'       => '',
    'particular'     => 'Closing',
    'row_amount'     => number_format($net_amount,  2, '.', ''),
    'balance_amount' => number_format($net_amount,  2, '.', ''),
    'row_gold'       => number_format($net_gold,    3, '.', ''),
    'balance_gold'   => number_format($net_gold,    3, '.', ''),
    'row_silver'     => number_format($net_silver,  3, '.', ''),
    'balance_silver' => number_format($net_silver,  3, '.', ''),
];

echo json_encode([
    "draw"             => intval($draw),
    "recordsTotal"     => count($data),
    "recordsFiltered"  => count($data),
    "data"             => $data,
    "total_tr_amount"  => number_format($net_amount, 2, '.', ''),
    "total_net_amount" => number_format($net_amount, 2, '.', ''),
]);
