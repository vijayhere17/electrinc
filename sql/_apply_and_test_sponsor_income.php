<?php
/**
 * One-shot: apply the franchise sponsor-income migration and run an
 * end-to-end check.  Run from the project root:
 *
 *     php sql/_apply_and_test_sponsor_income.php
 *
 * If your MySQL root now has a password:  set DBPW first, e.g. (PowerShell)
 *     $env:DBPW="yourpass"; php sql/_apply_and_test_sponsor_income.php
 *
 * Safe to run more than once — the migration is idempotent and the test
 * cleans up every row it creates.
 */
error_reporting(E_ALL);
$PW = getenv('DBPW') !== false ? getenv('DBPW') : '';
mysqli_report(MYSQLI_REPORT_OFF);
$conn = new mysqli('localhost', 'root', $PW, 'ind_new');
if ($conn->connect_errno) {
    fwrite(STDERR, "DB connect failed: {$conn->connect_error}\n" .
        "If root has a password now, run:  \$env:DBPW=\"...\"; php sql/_apply_and_test_sponsor_income.php\n");
    exit(1);
}
$conn->set_charset('utf8mb4');
function ln($s = '') { echo $s . "\n"; }

/* ---------- 1. migration ---------- */
ln('=== applying sql/2026_09_franchise_sponsor_income.sql ===');
$sql = file_get_contents(__DIR__ . '/2026_09_franchise_sponsor_income.sql');
if ($conn->multi_query($sql)) {
    do { if ($conn->error) ln('  note: ' . $conn->error); } while ($conn->more_results() && $conn->next_result());
}
$has = $conn->query("SHOW COLUMNS FROM franchies_holder LIKE 'sponsor_franchise_code'")->num_rows;
ln('  franchies_holder.sponsor_franchise_code: ' . ($has ? 'OK' : 'MISSING'));

require_once __DIR__ . '/../includes/franchise_finance.php';

/* ---------- 2. end-to-end ---------- */
ln("\n=== test: 5% sponsor income on model investment ===");

// pick two real franchises (investing + sponsor)
$fr = [];
$r = $conn->query("SELECT id, COALESCE(NULLIF(franchise_code,''), franchies_id) code, name FROM franchies_holder ORDER BY id LIMIT 2");
while ($x = $r->fetch_assoc()) $fr[] = $x;
if (count($fr) < 1) { ln('No franchises found — cannot test.'); exit(0); }
$invest = $fr[0];
$sponsorCode = isset($fr[1]) ? $fr[1]['code'] : ($invest['code'] . '-SPON');
ln("investing franchise : #{$invest['id']} {$invest['code']} ({$invest['name']})");
ln("sponsor franchise   : {$sponsorCode}");

// a throwaway model
$conn->query("INSERT INTO franchise_investment_models (model_no, model_name, investment_amount, scooters, cashback_percent, period_months, is_active)
              VALUES ('ZZTEST', 'ZZ Sponsor Test Model', 200000, 1, 100, 36, 1)");
$modelId = (int)$conn->insert_id;
ln("test model #{$modelId}  investment ₹2,00,000  → expect 5% = ₹10,000");

// remember prior state to restore
$prev = $conn->query("SELECT investment_model_id, investment_start_date, sponsor_franchise_code FROM franchies_holder WHERE id = {$invest['id']}")->fetch_assoc();

$conn->query("UPDATE franchies_holder SET sponsor_franchise_code = '" . $conn->real_escape_string($sponsorCode) . "' WHERE id = {$invest['id']}");

$r1 = frf_credit_franchise_sponsor_income($conn, (int)$invest['id'], $invest['code'], $modelId);
ln('credit #1 : ' . json_encode($r1));
$r2 = frf_credit_franchise_sponsor_income($conn, (int)$invest['id'], $invest['code'], $modelId);
ln('credit #2 : ' . json_encode($r2) . '   (must be skipped — no double pay)');

$rows = $conn->query("SELECT receiver_type, receiver_id, commission_percent, commission_amount, from_sale_type, payout_status, remarks
                      FROM commission_ledger WHERE from_sale_type = 'FRANCHISE_INVESTMENT' AND sale_id = {$invest['id']} AND member_order_id = {$modelId}");
ln('commission_ledger rows for this franchise+model:');
$n = 0;
while ($x = $rows->fetch_assoc()) { ln('  ' . json_encode($x)); $n++; }
ln("row count = {$n}  (expect 1)");

ln("\n=== test: 0.5% turnover income goes to COMPANY ===");
$before = (int)$conn->query("SELECT COUNT(*) c FROM commission_ledger WHERE receiver_id = 'COMPANY'")->fetch_assoc()['c'];
$rt = frf_credit_commission($conn, $invest['code'], 'state', 100000, ['sale_type' => 'SALE', 'reference' => 'ZZ-TURNOVER-TEST']);
ln('turnover credit : ' . json_encode($rt) . '   (expect ₹500 = 0.5% of 1,00,000)');
$company = $conn->query("SELECT receiver_type, receiver_id, commission_percent, commission_amount, remarks
                         FROM commission_ledger WHERE remarks LIKE '%ZZ-TURNOVER-TEST%'")->fetch_assoc();
ln('  row: ' . json_encode($company));
ln('  no 5% sponsor row created on the sale? ' .
   ((int)$conn->query("SELECT COUNT(*) c FROM commission_ledger WHERE remarks LIKE '%ZZ-TURNOVER-TEST%' AND commission_percent >= 1")->fetch_assoc()['c'] === 0 ? 'correct' : 'WRONG'));

/* ---------- cleanup ---------- */
ln("\n=== cleanup ===");
$conn->query("DELETE FROM commission_ledger WHERE from_sale_type = 'FRANCHISE_INVESTMENT' AND member_order_id = {$modelId}");
$conn->query("DELETE FROM commission_ledger WHERE remarks LIKE '%ZZ-TURNOVER-TEST%'");
$conn->query("DELETE FROM franchise_investment_models WHERE id = {$modelId}");
$mv = $prev['investment_model_id'] === null ? 'NULL' : (int)$prev['investment_model_id'];
$dv = $prev['investment_start_date'] === null ? 'NULL' : "'" . $conn->real_escape_string($prev['investment_start_date']) . "'";
$sv = $prev['sponsor_franchise_code'] === null ? 'NULL' : "'" . $conn->real_escape_string($prev['sponsor_franchise_code']) . "'";
$conn->query("UPDATE franchies_holder SET investment_model_id = {$mv}, investment_start_date = {$dv}, sponsor_franchise_code = {$sv} WHERE id = {$invest['id']}");
ln('  restored franchise #' . $invest['id'] . ' to its prior model/sponsor state.');
ln('  commission_ledger rows now: ' . $conn->query("SELECT COUNT(*) c FROM commission_ledger")->fetch_assoc()['c']);
ln("\nDONE.");
