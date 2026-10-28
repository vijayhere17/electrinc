<?php
/**
 * Franchise Dashboard — main overview for the logged-in franchise owner.
 * Every value is read from real DB records and scoped to the authenticated
 * franchise (session id/code only — never a URL parameter).
 */

require_once __DIR__ . '/auth.php';
// auth.php provides: $conn, $franchiseId (int PK), $franchiseType, $franchiseCode,
// $franchiseName, $franchise (holder row), and redirects if not logged in.

if (!isset($conn) || !($conn instanceof mysqli)) {
    die('Database connection failed.');
}

$fid   = (int)$franchiseId;
$fidS  = (string)$fid;
$fcode = (string)$franchiseCode;
$ftype = strtolower(trim((string)$franchiseType));
$typeLabel = ($ftype === 'city') ? 'City Franchise' : (($ftype === 'mobile') ? 'Mobile Franchise' : 'State Franchise');

$franchiseState = trim((string)($franchise['state'] ?? ($_SESSION['franchise_state'] ?? '')));
$franchiseCity  = trim((string)($franchise['city']  ?? ($_SESSION['franchise_city']  ?? '')));

/* ---------- small safe helpers ---------- */
function fr_tbl(mysqli $c, string $t): bool {
    $t = $c->real_escape_string($t);
    $r = $c->query("SHOW TABLES LIKE '$t'");
    return $r instanceof mysqli_result && $r->num_rows > 0;
}
function fr_col(mysqli $c, string $t, string $col): bool {
    if (!fr_tbl($c, $t)) return false;
    $t = preg_replace('/[^a-zA-Z0-9_]/', '', $t);
    $col = $c->real_escape_string($col);
    $r = $c->query("SHOW COLUMNS FROM `$t` LIKE '$col'");
    return $r instanceof mysqli_result && $r->num_rows > 0;
}
function fr_num(mysqli $c, string $sql, string $types = '', array $params = []): float {
    try {
        $s = $c->prepare($sql);
        if (!$s) return 0.0;
        if ($types !== '') { $s->bind_param($types, ...$params); }
        $s->execute();
        $row = $s->get_result()->fetch_row();
        $s->close();
        return $row ? (float)$row[0] : 0.0;
    } catch (\Throwable $e) { return 0.0; }
}
function fr_setting(mysqli $c, string $k, float $d): float {
    if (!fr_tbl($c, 'franchise_settings')) return $d;
    $s = $c->prepare("SELECT setting_value FROM franchise_settings WHERE setting_key = ? LIMIT 1");
    if (!$s) return $d;
    $s->bind_param('s', $k); $s->execute();
    $r = $s->get_result()->fetch_assoc(); $s->close();
    return (isset($r['setting_value']) && is_numeric($r['setting_value'])) ? (float)$r['setting_value'] : $d;
}
function money($n) { return '₹' . number_format((float)$n, 2); }
function qty($n)   { return number_format((float)$n, 0); }

/* ---------- config rates (admin-managed in franchise_settings) ---------- */
$sponsorRate    = fr_setting($conn, 'sponsor_income_rate', 5.00);
$turnoverRate   = fr_setting($conn, 'turnover_income_rate', 0.50);
$cashbackMonths = (int)fr_setting($conn, 'cashback_total_months', 36);

/* ============================================================
 * SUMMARY CARD VALUES
 * ============================================================ */

/* stock figures — prefer the franchise inventory (fr_products + fr_units),
   fall back to the legacy franchise_stock / stock-request tables. */
$frInvTotal = $frInvAvail = $frInvReserved = $frInvSold = 0.0;
if (fr_tbl($conn, 'fr_products')) {
    $frInvAvail    = fr_num($conn, "SELECT COALESCE(SUM(qty_available),0) FROM fr_products WHERE franchise_id = ?", 'i', [$fid]);
    $frInvReserved = fr_num($conn, "SELECT COALESCE(SUM(qty_reserved),0) FROM fr_products WHERE franchise_id = ?", 'i', [$fid]);
    $frInvSold     = fr_num($conn, "SELECT COALESCE(SUM(qty_sold),0)     FROM fr_products WHERE franchise_id = ?", 'i', [$fid]);
    $nonSerTot     = fr_num($conn, "SELECT COALESCE(SUM(qty_available + qty_reserved + qty_sold + qty_returned + qty_damaged),0) FROM fr_products WHERE franchise_id = ? AND is_serialized = 0", 'i', [$fid]);
    if (fr_tbl($conn, 'fr_units')) {
        $frInvAvail    += fr_num($conn, "SELECT COUNT(*) FROM fr_units WHERE franchise_id = ? AND status = 'Available'", 'i', [$fid]);
        $frInvReserved += fr_num($conn, "SELECT COUNT(*) FROM fr_units WHERE franchise_id = ? AND status = 'Reserved'", 'i', [$fid]);
        $frInvSold     += fr_num($conn, "SELECT COUNT(*) FROM fr_units WHERE franchise_id = ? AND status IN ('Sold','Delivered')", 'i', [$fid]);
        $serTot         = fr_num($conn, "SELECT COUNT(*) FROM fr_units WHERE franchise_id = ?", 'i', [$fid]);
        $frInvTotal     = $nonSerTot + $serTot;
    } else {
        $frInvTotal = $nonSerTot;
    }
}

/* 1. Total Products / Scooters received by this franchise */
$totalProducts = $frInvTotal;
if ($totalProducts <= 0 && fr_col($conn, 'franchies_product_stock_request', 'product_received')) {
    $totalProducts = fr_num($conn, "SELECT COALESCE(SUM(product_received),0) FROM franchies_product_stock_request WHERE franchise_id = ?", 'i', [$fid]);
}
if ($totalProducts <= 0 && fr_tbl($conn, 'franchise_stock')) {
    $curr = fr_num($conn, "SELECT COALESCE(SUM(stock_qty),0) FROM franchise_stock WHERE franchise_id = ? OR franchise_id = ? OR franchise_code = ?", 'sss', [$fidS, $fcode, $fcode]);
    $used = fr_col($conn, 'franchies_product_stock_request', 'product_used')
        ? fr_num($conn, "SELECT COALESCE(SUM(product_used),0) FROM franchies_product_stock_request WHERE franchise_id = ?", 'i', [$fid]) : 0.0;
    $totalProducts = $curr + $used;
}

/* 2. Available Stock — live franchise stock */
$availableStock = $frInvAvail;
if ($availableStock <= 0 && fr_tbl($conn, 'franchise_stock')) {
    $availableStock = fr_num($conn, "SELECT COALESCE(SUM(stock_qty),0) FROM franchise_stock WHERE franchise_id = ? OR franchise_id = ? OR franchise_code = ?", 'sss', [$fidS, $fcode, $fcode]);
}
if ($availableStock <= 0 && fr_col($conn, 'franchies_product_stock_request', 'product_balance')) {
    $availableStock = fr_num($conn, "SELECT COALESCE(SUM(product_balance),0) FROM franchies_product_stock_request WHERE franchise_id = ?", 'i', [$fid]);
}

/* 3. Sold Products — from the franchise sales ledger (fr_customer_purchases) */
$soldProducts = 0.0;
if (fr_tbl($conn, 'fr_customer_purchases')) {
    $soldProducts = fr_num($conn, "SELECT COALESCE(SUM(quantity),0) FROM fr_customer_purchases WHERE franchise_id = ?", 'i', [$fid]);
}
if ($soldProducts <= 0 && fr_tbl($conn, 'franchise_order_sale')) {
    $soldProducts = fr_num($conn, "SELECT COALESCE(SUM(quantity),0) FROM franchise_order_sale WHERE distributor_id = ? OR distributor_id = ?", 'ss', [$fidS, $fcode]);
}
if ($soldProducts <= 0 && fr_col($conn, 'franchies_product_stock_request', 'product_used')) {
    $soldProducts = fr_num($conn, "SELECT COALESCE(SUM(product_used),0) FROM franchies_product_stock_request WHERE franchise_id = ?", 'i', [$fid]);
}

/* 4. Total Turnover — franchise showroom sales (fr_customer_purchases) */
$turnover = 0.0;
if (fr_tbl($conn, 'fr_customer_purchases')) {
    $turnover = fr_num($conn, "SELECT COALESCE(SUM(sale_amount),0) FROM fr_customer_purchases WHERE franchise_id = ?", 'i', [$fid]);
}
if ($turnover <= 0 && fr_tbl($conn, 'franchise_order_sale')) {
    $turnover = fr_num($conn, "SELECT COALESCE(SUM(total_amount),0) FROM franchise_order_sale WHERE distributor_id = ? OR distributor_id = ?", 'ss', [$fidS, $fcode]);
}

/* 5/6/7. Franchise finance
 *  - Sponsor Income  : what THIS franchise earned by sponsoring other franchises'
 *                      model investments (commission_ledger, FRANCHISE_INVESTMENT,
 *                      receiver = this franchise code). Paid once per sponsored franchise.
 *  - Turnover Income : earned by the COMPANY (0.5% of showroom turnover) — shown here
 *                      only as the amount this franchise's own sales contributed.
 *  - Total Commission: the franchise's own earning = Sponsor Income.                     */
$sponsorIncome = 0.0; $otherCommission = 0.0; $todayCommission = 0.0; $sponsorPending = 0.0;
if (fr_tbl($conn, 'commission_ledger')) {
    $s = $conn->prepare("SELECT commission_amount, from_sale_type, LOWER(COALESCE(remarks,'')) AS rk,
                                UPPER(COALESCE(payout_status,'')) AS pst,
                                (DATE(created_at) = CURDATE()) AS is_today
                         FROM commission_ledger
                         WHERE receiver_id = ? OR receiver_id = ?");
    if ($s) {
        $s->bind_param('ss', $fidS, $fcode);
        $s->execute();
        $rs = $s->get_result();
        while ($row = $rs->fetch_assoc()) {
            $amt  = (float)$row['commission_amount'];
            $type = strtoupper((string)$row['from_sale_type']);
            if ($type === 'FRANCHISE_INVESTMENT' || strpos((string)$row['rk'], 'sponsor') !== false) {
                $sponsorIncome += $amt;
                if ($row['pst'] !== 'PAID') $sponsorPending += $amt;
                if ((int)$row['is_today'] === 1) $todayCommission += $amt;
            } else {
                $otherCommission += $amt;
            }
        }
        $s->close();
    }
}
$turnoverToCompany  = round($turnover * $turnoverRate / 100, 2);   // company's 0.5%, informational
$totalCommissionCard = $sponsorIncome;

/* 8. Total Cashback Received — read only if a cashback table already exists */
$cashbackReceived = 0.0; $cashbackTableUsed = '';
foreach (['franchise_cashback_ledger', 'franchise_cashback', 'franchise_cashbacks', 'franchise_cashback_payout', 'franchise_cashback_report'] as $ct) {
    if (!fr_tbl($conn, $ct)) continue;
    $amtCol = fr_col($conn, $ct, 'amount') ? 'amount'
        : (fr_col($conn, $ct, 'cashback_amount') ? 'cashback_amount'
        : (fr_col($conn, $ct, 'paid_amount') ? 'paid_amount'
        : (fr_col($conn, $ct, 'credit') ? 'credit' : '')));
    $keyCol = fr_col($conn, $ct, 'franchise_id') ? 'franchise_id'
        : (fr_col($conn, $ct, 'franchise_code') ? 'franchise_code' : '');
    if ($amtCol !== '' && $keyCol !== '') {
        $cashbackReceived = fr_num($conn, "SELECT COALESCE(SUM(`$amtCol`),0) FROM `$ct` WHERE `$keyCol` = ? OR `$keyCol` = ?", 'ss', [$fidS, $fcode]);
        $cashbackTableUsed = $ct;
    }
    break;
}

/* ---------- Investment model selected by this franchise ---------- */
$modelId = (int)($franchise['investment_model_id'] ?? 0);
$model = null;
if ($modelId > 0 && fr_tbl($conn, 'franchise_investment_models')) {
    $s = $conn->prepare("SELECT * FROM franchise_investment_models WHERE id = ? LIMIT 1");
    $s->bind_param('i', $modelId); $s->execute();
    $model = $s->get_result()->fetch_assoc() ?: null; $s->close();
}
$investStart  = trim((string)($franchise['investment_start_date'] ?? ''));
$periodMonths = $model ? (int)$model['period_months'] : $cashbackMonths;

/* 9. Pending Cashback  (safe: full entitlement minus received; 0 if no model) */
$pendingCashback = 0.0; $cashbackEntitlement = 0.0;
if ($model) {
    $cashbackEntitlement = (float)$model['investment_amount'] * (float)$model['cashback_percent'] / 100;
    $pendingCashback = max(0.0, $cashbackEntitlement - $cashbackReceived);
}

/* 10. Cashback Progress (completed months of period) */
$monthsDone = 0;
if ($investStart !== '' && $investStart !== '0000-00-00') {
    $monthsDone = (int)fr_num($conn, "SELECT LEAST(GREATEST(TIMESTAMPDIFF(MONTH, ?, CURDATE()), 0), ?)", 'si', [$investStart, $periodMonths]);
}

/* ---------- Stock Summary ---------- */
$reservedStock = $frInvReserved;
if ($reservedStock <= 0 && fr_tbl($conn, 'franchise_stock_wallet')) {
    $reservedStock = fr_num($conn, "SELECT COALESCE(SUM(stock_qty),0) FROM franchise_stock_wallet WHERE franchise_id = ? OR franchise_id = ?", 'ss', [$fidS, $fcode]);
}
$totalStock = $totalProducts;
$soldStock  = ($frInvSold > 0) ? $frInvSold : $soldProducts;

/* ---------- Pending items ---------- */
$pendReqCount = 0; $pendReqAmt = 0.0;
if (fr_col($conn, 'franchies_product_request', 'status')) {
    $pendReqCount = (int)fr_num($conn, "SELECT COUNT(*) FROM franchies_product_request WHERE franchise_id = ? AND LOWER(status) = 'pending'", 'i', [$fid]);
    $pendReqAmt   = fr_num($conn, "SELECT COALESCE(SUM(total_amount),0) FROM franchies_product_request WHERE franchise_id = ? AND LOWER(status) = 'pending'", 'i', [$fid]);
}
$pendDeliv = 0;
if (fr_col($conn, 'franchies_send_product_request', 'status')) {
    $pendDeliv = (int)fr_num($conn, "SELECT COUNT(*) FROM franchies_send_product_request WHERE franchise_id = ? AND LOWER(status) IN ('pending','approved','processing','dispatched','in transit')", 'i', [$fid]);
}
$pendPayCount = 0; $pendPayAmt = 0.0;
if (fr_col($conn, 'franchies_payment', 'status')) {
    $pendPayCount = (int)fr_num($conn, "SELECT COUNT(*) FROM franchies_payment WHERE franchise_id = ? AND LOWER(status) = 'pending'", 'i', [$fid]);
    $pendPayAmt   = fr_num($conn, "SELECT COALESCE(SUM(total_amount),0) FROM franchies_payment WHERE franchise_id = ? AND LOWER(status) = 'pending'", 'i', [$fid]);
}

/* ---------- Recent activity (merged, latest first) ---------- */
$activity = [];
if (fr_tbl($conn, 'franchies_product_stock_request')) {
    $r = $conn->query("SELECT created_at, product_name, product_received, balance_amount FROM franchies_product_stock_request WHERE franchise_id = $fid ORDER BY id DESC LIMIT 6");
    while ($r && ($x = $r->fetch_assoc())) {
        $activity[] = ['d' => $x['created_at'], 't' => 'Stock received', 'ref' => trim(($x['product_name'] ?? '') . ' × ' . qty($x['product_received'] ?? 0)), 'amt' => $x['balance_amount'] ?? null];
    }
}
if (fr_tbl($conn, 'franchies_product_request')) {
    $r = $conn->query("SELECT created_at, product_name, quantity, total_amount, status FROM franchies_product_request WHERE franchise_id = $fid ORDER BY id DESC LIMIT 6");
    while ($r && ($x = $r->fetch_assoc())) {
        $activity[] = ['d' => $x['created_at'], 't' => 'Product request · ' . ($x['status'] ?: 'Pending'), 'ref' => trim(($x['product_name'] ?? '') . ' × ' . qty($x['quantity'] ?? 0)), 'amt' => $x['total_amount'] ?? null];
    }
}
if (fr_tbl($conn, 'franchise_order_sale')) {
    $s = $conn->prepare("SELECT created_at, product_name, member_name, quantity, total_amount, order_type FROM franchise_order_sale WHERE distributor_id = ? OR distributor_id = ? ORDER BY id DESC LIMIT 8");
    if ($s) {
        $s->bind_param('ss', $fidS, $fcode); $s->execute(); $rs = $s->get_result();
        while ($x = $rs->fetch_assoc()) {
            $lbl = (strtoupper((string)$x['order_type']) === 'ACTIVATION') ? 'Member activation sale' : 'Repurchase sale';
            $activity[] = ['d' => $x['created_at'], 't' => $lbl, 'ref' => trim(($x['product_name'] ?? '') . ' — ' . ($x['member_name'] ?? '')), 'amt' => $x['total_amount'] ?? null];
        }
        $s->close();
    }
}
if (fr_tbl($conn, 'commission_ledger')) {
    $s = $conn->prepare("SELECT created_at, commission_amount, commission_percent, remarks FROM commission_ledger WHERE receiver_id = ? OR receiver_id = ? ORDER BY id DESC LIMIT 8");
    if ($s) {
        $s->bind_param('ss', $fidS, $fcode); $s->execute(); $rs = $s->get_result();
        while ($x = $rs->fetch_assoc()) {
            $ref = trim((string)($x['remarks'] ?? '')) ?: (number_format((float)$x['commission_percent'], 2) . '% commission');
            $activity[] = ['d' => $x['created_at'], 't' => 'Commission credited', 'ref' => $ref, 'amt' => $x['commission_amount'] ?? null];
        }
        $s->close();
    }
}
if ($cashbackTableUsed !== '') {
    $r = $conn->query("SELECT * FROM `$cashbackTableUsed` WHERE franchise_id = '" . $conn->real_escape_string($fidS) . "' OR franchise_code = '" . $conn->real_escape_string($fcode) . "' ORDER BY id DESC LIMIT 5");
    while ($r && ($x = $r->fetch_assoc())) {
        $activity[] = ['d' => $x['created_at'] ?? ($x['paid_at'] ?? date('Y-m-d')), 't' => 'Cashback credited', 'ref' => 'Franchise cashback', 'amt' => $x['amount'] ?? ($x['cashback_amount'] ?? null)];
    }
}
usort($activity, fn($a, $b) => strcmp((string)($b['d'] ?? ''), (string)($a['d'] ?? '')));
$activity = array_slice($activity, 0, 12);

/* ---------- summary cards (tone: saffron | green) ---------- */
$cards = [
    ['title' => 'Total Products / Scooters', 'value' => qty($totalProducts),  'sub' => 'Received / allocated',             'icon' => 'boxes',        'tone' => 'saffron'],
    ['title' => 'Available Stock',           'value' => qty($availableStock), 'sub' => 'In franchise stock now',           'icon' => 'package',      'tone' => 'green'],
    ['title' => 'Sold Products',             'value' => qty($soldProducts),   'sub' => 'Units sold (showroom sales)',       'icon' => 'shopping-bag', 'tone' => 'saffron'],
    ['title' => 'Total Turnover',            'value' => money($turnover),     'sub' => 'Showroom sales — see Turnover report', 'icon' => 'trending-up',  'tone' => 'green'],
    ['title' => 'Sponsor Income',            'value' => money($sponsorIncome),'sub' => 'From franchises you sponsor @ ' . rtrim(rtrim(number_format($sponsorRate, 2), '0'), '.') . '% of investment',  'icon' => 'user-check',  'tone' => 'green'],
    ['title' => 'Turnover Income (company)', 'value' => money($turnoverToCompany),'sub' => 'Company earns ' . rtrim(rtrim(number_format($turnoverRate, 2), '0'), '.') . '% of your turnover', 'icon' => 'percent',     'tone' => 'saffron'],
    ['title' => 'Total Commission',          'value' => money($totalCommissionCard), 'sub' => 'Your sponsor income earned', 'icon' => 'medal',        'tone' => 'green'],
    ['title' => 'Cashback Received',         'value' => money($cashbackReceived), 'sub' => $cashbackTableUsed !== '' ? 'Paid / credited' : 'Cashback engine pending', 'icon' => 'gift', 'tone' => 'green'],
    ['title' => 'Pending Cashback',          'value' => $model ? money($pendingCashback) : '—', 'sub' => $model ? 'Remaining in model' : 'No investment model', 'icon' => 'hourglass', 'tone' => 'saffron'],
    ['title' => 'Cashback Progress',         'value' => $monthsDone . ' / ' . $periodMonths, 'sub' => 'Months completed', 'icon' => 'calendar-check', 'tone' => 'saffron'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?php echo e($typeLabel); ?> Dashboard</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        :root{
            --saffron:#E0700C; --saffron-ink:#B25400; --saffron-soft:#FFF3E7; --saffron-line:#F3DAC0;
            --green:#0E7A3B; --green-ink:#0A5E2D; --green-soft:#EAF5EC; --green-line:#CDE7D3;
            --ink:#232323; --ink-2:#6A6A6A; --ink-3:#9A948C;
            --page:#FBFAF6; --card:#FFFFFF; --line:#ECE7DD; --line-soft:#F3EFE7;
        }
        body{ background:var(--page); }
        .fr-card{ position:relative; overflow:hidden; background:var(--card); border:1px solid var(--line); border-radius:18px;
            box-shadow:0 1px 2px rgba(20,16,10,.04); transition:transform .16s ease, box-shadow .16s ease; }
        /* stat tiles: tricolour gradient wash + premium silver-shine border */
        .fr-card-hover{
            background:linear-gradient(135deg, rgba(224,112,12,.10) 0%, #ffffff 48%, rgba(14,122,59,.10) 100%);
            border-width:1px; border-color:#D9DEE6;
            box-shadow:0 1px 3px rgba(20,16,10,.06), inset 0 1px 0 rgba(255,255,255,.95);
        }
        .fr-card-hover:hover{ transform:translateY(-2px); box-shadow:0 16px 34px -18px rgba(160,90,20,.28), inset 0 1px 0 rgba(255,255,255,.95); }
        .fr-card-hover::before{ content:""; position:absolute; left:0; top:0; bottom:0; width:4px; }
        .tone-saffron.fr-card-hover::before{ background:linear-gradient(180deg,var(--saffron),#F0A44F); }
        .tone-green.fr-card-hover::before{ background:linear-gradient(180deg,var(--green),#3FA05E); }
        .fr-card-body{ position:relative; z-index:1; }
        .fr-chip{ width:44px; height:44px; border-radius:13px; display:flex; align-items:center; justify-content:center; flex-shrink:0; position:relative; z-index:1; }
        .tone-saffron .fr-chip{ background:var(--saffron-soft); color:var(--saffron); border:1px solid var(--saffron-line); }
        .tone-green .fr-chip{ background:var(--green-soft); color:var(--green); border:1px solid var(--green-line); }
        .fr-kicker{ font-size:11px; letter-spacing:.06em; text-transform:uppercase; color:var(--ink-3); font-weight:600; }
        .fr-value{ color:var(--ink); font-weight:700; letter-spacing:-.01em; }
        .fr-sub{ color:var(--ink-3); font-size:11.5px; }
        .fr-accent-bar{ height:3px; background:linear-gradient(90deg,var(--saffron) 0%, #F0A44F 45%, #7FBF8E 55%, var(--green) 100%); }
        /* welcome banner (auto-dismiss) */
        .fr-welcome{ position:relative; overflow:hidden; border:1px solid var(--line); border-radius:16px;
            background:linear-gradient(100deg,#FFF4E9 0%,#FFFFFF 44%,#EEF7EF 100%);
            display:flex; align-items:center; gap:14px; padding:15px 18px;
            box-shadow:0 1px 2px rgba(20,16,10,.04);
            transition:opacity .45s ease, transform .45s ease, margin .45s ease, max-height .45s ease; max-height:120px; }
        .fr-welcome::before{ content:""; position:absolute; left:0; top:0; bottom:0; width:4px;
            background:linear-gradient(180deg,var(--saffron),var(--green)); }
        .fr-welcome.is-hiding{ opacity:0; transform:translateY(-6px); max-height:0; margin-top:-28px; padding-top:0; padding-bottom:0; border-color:transparent; }
        .fr-welcome .wtxt b{ color:var(--ink); font-weight:700; }
        .fr-welcome .wtxt{ color:var(--ink-2); font-size:13px; }
        .fr-welcome .wclose{ margin-left:auto; color:var(--ink-3); border:0; background:transparent; cursor:pointer; line-height:0; padding:4px; border-radius:8px; }
        .fr-welcome .wclose:hover{ background:#00000008; color:var(--ink); }
        .fr-progress{ background:var(--line-soft); border-radius:999px; overflow:hidden; }
        .fr-progress > span{ display:block; height:100%; background:linear-gradient(90deg,var(--saffron),var(--green)); border-radius:999px; }
        .fr-meta-pill{ display:inline-flex; align-items:center; gap:7px; background:#FFFFFFcc; border:1px solid var(--line);
            border-radius:999px; padding:5px 12px; font-size:12px; color:var(--ink-2); }
        .fr-meta-pill b{ color:var(--ink); font-weight:600; }
        .fr-dot{ width:7px; height:7px; border-radius:999px; }
        .fr-section-title{ font-size:14px; font-weight:700; color:var(--ink); display:flex; align-items:center; gap:10px; }
        .fr-row{ display:flex; align-items:center; justify-content:space-between; font-size:13px; padding:9px 0; border-bottom:1px dashed var(--line-soft); }
        .fr-row:last-child{ border-bottom:0; }
        .fr-row .k{ color:var(--ink-2); }
        .fr-row .v{ color:var(--ink); font-weight:600; }
        .fr-table thead th{ background:#FBF6EE; color:var(--ink-3); font-weight:600; font-size:12px; }
        .fr-table tbody tr{ border-top:1px solid var(--line-soft); }
        .fr-table tbody tr:hover{ background:#FCFAF5; }
    </style>
</head>
<body class="font-sans overflow-x-hidden">

<?php if (file_exists(__DIR__ . '/includes/sidebar.php')) include __DIR__ . '/includes/sidebar.php'; ?>

<div class="ml-64 min-h-screen">

    <?php if (file_exists(__DIR__ . '/includes/navbar.php')) include __DIR__ . '/includes/navbar.php'; ?>

    <main class="px-4 sm:px-6 py-7">
        <div class="max-w-7xl mx-auto space-y-7">

            <!-- WELCOME BANNER (auto-dismisses ~5s, once per login) -->
            <div id="frWelcome" class="fr-welcome" hidden>
                <span class="fr-chip" style="width:38px;height:38px;border-radius:11px;background:var(--saffron-soft);color:var(--saffron);border:1px solid var(--saffron-line);">
                    <i data-lucide="hand" class="w-4 h-4"></i>
                </span>
                <div class="wtxt">Welcome back, <b><?php echo e($franchiseName); ?></b> — here's your <?php echo e(strtolower($typeLabel)); ?> overview.</div>
                <button type="button" class="wclose" onclick="frHideWelcome(true)" aria-label="Dismiss">
                    <i data-lucide="x" class="w-4 h-4"></i>
                </button>
            </div>

            <!-- SUMMARY CARDS -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4 sm:gap-5">
                <?php foreach ($cards as $c): $tone = ($c['tone'] ?? 'saffron') === 'green' ? 'tone-green' : 'tone-saffron'; ?>
                    <div class="fr-card fr-card-hover <?php echo $tone; ?> p-5 sm:p-6">
                        <div class="fr-card-body">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="fr-kicker truncate"><?php echo e($c['title']); ?></p>
                                    <p class="mt-2.5 text-[25px] sm:text-[27px] fr-value leading-none break-words"><?php echo e($c['value']); ?></p>
                                    <p class="mt-2.5 fr-sub"><?php echo e($c['sub']); ?></p>
                                </div>
                                <span class="fr-chip"><i data-lucide="<?php echo e($c['icon']); ?>" class="w-5 h-5"></i></span>
                            </div>
                            <?php if ($c['title'] === 'Total Commission' && $sponsorPending > 0): ?>
                                <p class="mt-3 text-[11px] font-medium" style="color:var(--saffron-ink)">Pending payout: <?php echo money($sponsorPending); ?></p>
                            <?php endif; ?>
                            <?php if ($c['title'] === 'Total Commission' && $otherCommission > 0): ?>
                                <p class="mt-1 fr-sub">Other commission: <?php echo money($otherCommission); ?></p>
                            <?php endif; ?>
                            <?php if ($c['title'] === 'Total Commission' && $todayCommission > 0): ?>
                                <p class="mt-1 text-[11px] font-semibold" style="color:var(--green)">Today: <?php echo money($todayCommission); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-5 sm:gap-6">

                <!-- INVESTMENT MODEL -->
                <div class="fr-card p-6">
                    <h2 class="fr-section-title">
                        <span class="fr-chip" style="width:34px;height:34px;border-radius:10px;background:var(--saffron-soft);color:var(--saffron);border:1px solid var(--saffron-line);"><i data-lucide="briefcase" class="w-4 h-4"></i></span>
                        Investment Model
                    </h2>
                    <?php if ($model): ?>
                        <div class="mt-4">
                            <div class="fr-row"><span class="k">Model</span><span class="v"><?php echo e($model['model_name']); ?></span></div>
                            <div class="fr-row"><span class="k">Investment</span><span class="v"><?php echo money($model['investment_amount']); ?></span></div>
                            <div class="fr-row"><span class="k">Scooters</span><span class="v"><?php echo qty($model['scooters']); ?></span></div>
                            <div class="fr-row"><span class="k">Cashback</span><span class="v" style="color:var(--green-ink)"><?php echo rtrim(rtrim(number_format((float)$model['cashback_percent'], 2), '0'), '.'); ?>%</span></div>
                            <div class="fr-row"><span class="k">Period</span><span class="v"><?php echo (int)$model['period_months']; ?> Months</span></div>
                            <?php if ($investStart !== '' && $investStart !== '0000-00-00'): ?>
                                <div class="fr-row"><span class="k">Started</span><span class="v"><?php echo e($investStart); ?></span></div>
                            <?php endif; ?>
                        </div>
                        <div class="mt-4">
                            <div class="flex justify-between text-[11px] mb-1.5" style="color:var(--ink-2)"><span>Cashback progress</span><span><?php echo $monthsDone; ?> / <?php echo $periodMonths; ?> months</span></div>
                            <div class="fr-progress w-full" style="height:8px;">
                                <span style="width: <?php echo $periodMonths > 0 ? min(100, round($monthsDone / $periodMonths * 100)) : 0; ?>%"></span>
                            </div>
                        </div>
                        <p class="mt-3.5 fr-sub">Values are set by the company and cannot be edited here.</p>
                    <?php else: ?>
                        <div class="mt-6 text-center py-8" style="color:var(--ink-3)">
                            <span class="fr-chip mx-auto mb-3" style="background:var(--saffron-soft);color:var(--saffron);border:1px solid var(--saffron-line);"><i data-lucide="folder-open" class="w-5 h-5"></i></span>
                            <p class="text-[13px]">No Investment Model Selected</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- STOCK SUMMARY -->
                <div class="fr-card p-6">
                    <h2 class="fr-section-title">
                        <span class="fr-chip" style="width:34px;height:34px;border-radius:10px;background:var(--green-soft);color:var(--green);border:1px solid var(--green-line);"><i data-lucide="layers" class="w-4 h-4"></i></span>
                        Stock Summary
                    </h2>
                    <div class="mt-4">
                        <div class="fr-row"><span class="k">Total Stock</span><span class="v"><?php echo qty($totalStock); ?></span></div>
                        <div class="fr-row"><span class="k">Available Stock</span><span class="v" style="color:var(--green-ink)"><?php echo qty($availableStock); ?></span></div>
                        <div class="fr-row"><span class="k">Reserved Stock</span><span class="v" style="color:var(--saffron-ink)"><?php echo qty($reservedStock); ?></span></div>
                        <div class="fr-row"><span class="k">Sold Stock</span><span class="v"><?php echo qty($soldStock); ?></span></div>
                    </div>
                </div>

                <!-- PENDING ITEMS -->
                <div class="fr-card p-6">
                    <h2 class="fr-section-title">
                        <span class="fr-chip" style="width:34px;height:34px;border-radius:10px;background:var(--saffron-soft);color:var(--saffron);border:1px solid var(--saffron-line);"><i data-lucide="alarm-clock" class="w-4 h-4"></i></span>
                        Pending Items
                    </h2>
                    <div class="mt-4">
                        <div class="fr-row">
                            <span class="k">Product requests</span>
                            <span class="v"><?php echo (int)$pendReqCount; ?><?php if ($pendReqAmt > 0): ?> <span class="fr-sub">(<?php echo money($pendReqAmt); ?>)</span><?php endif; ?></span>
                        </div>
                        <div class="fr-row">
                            <span class="k">Deliveries in progress</span>
                            <span class="v"><?php echo (int)$pendDeliv; ?></span>
                        </div>
                        <div class="fr-row">
                            <span class="k">Pending payments</span>
                            <span class="v"><?php echo (int)$pendPayCount; ?><?php if ($pendPayAmt > 0): ?> <span class="fr-sub">(<?php echo money($pendPayAmt); ?>)</span><?php endif; ?></span>
                        </div>
                        <div class="fr-row">
                            <span class="k">Pending cashback</span>
                            <span class="v"><?php echo $model ? money($pendingCashback) : '—'; ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- RECENT ACTIVITY -->
            <div class="fr-card overflow-hidden">
                <div class="px-6 py-4 flex items-center gap-3" style="border-bottom:1px solid var(--line);">
                    <span class="fr-chip" style="width:32px;height:32px;border-radius:9px;background:var(--green-soft);color:var(--green);border:1px solid var(--green-line);"><i data-lucide="history" class="w-4 h-4"></i></span>
                    <h2 class="fr-section-title" style="gap:0;">Recent Activity</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="fr-table w-full text-[13px]">
                        <thead>
                            <tr>
                                <th class="text-left px-6 py-3 whitespace-nowrap">Date</th>
                                <th class="text-left px-6 py-3 whitespace-nowrap">Activity</th>
                                <th class="text-left px-6 py-3">Reference</th>
                                <th class="text-right px-6 py-3 whitespace-nowrap">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($activity)): ?>
                                <tr><td colspan="4" class="px-6 py-10 text-center" style="color:var(--ink-3)">No recent activity for this franchise yet.</td></tr>
                            <?php else: foreach ($activity as $a): ?>
                                <tr>
                                    <td class="px-6 py-3 whitespace-nowrap" style="color:var(--ink-3)"><?php echo e(substr((string)($a['d'] ?? ''), 0, 16)); ?></td>
                                    <td class="px-6 py-3 whitespace-nowrap font-medium" style="color:var(--ink)"><?php echo e($a['t']); ?></td>
                                    <td class="px-6 py-3" style="color:var(--ink-2)"><?php echo e($a['ref']); ?></td>
                                    <td class="px-6 py-3 text-right whitespace-nowrap font-semibold" style="color:var(--ink)"><?php echo ($a['amt'] === null || $a['amt'] === '') ? '—' : money($a['amt']); ?></td>
                                </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </main>
</div>

<script>
if (typeof lucide !== 'undefined') lucide.createIcons();

/* Welcome banner: show once per login session, then auto-hide after 5s */
(function () {
    var el = document.getElementById('frWelcome');
    if (!el) return;
    var KEY = 'frWelcomeShown';
    var seen = false;
    try { seen = sessionStorage.getItem(KEY) === '1'; } catch (e) {}
    if (seen) { el.remove(); return; }
    try { sessionStorage.setItem(KEY, '1'); } catch (e) {}
    el.hidden = false;
    window.frHideWelcome = function () {
        if (!el || el.classList.contains('is-hiding')) return;
        el.classList.add('is-hiding');
        setTimeout(function () { if (el) el.remove(); }, 500);
    };
    setTimeout(window.frHideWelcome, 5000);
})();
</script>
</body>
</html>
