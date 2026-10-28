<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/config/db.php';

if (!isset($conn) || !($conn instanceof mysqli)) {
    die("Database connection failed.");
}

function e($v)
{
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function money($v)
{
    return '₹' . number_format((float)$v, 2);
}

function safeDate($date, $default)
{
    return preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$date) ? $date : $default;
}

function computePeriodContaining(string $date): array
{
    $year    = (int)date('Y', strtotime($date));
    $month   = (int)date('m', strtotime($date));
    $day     = (int)date('d', strtotime($date));
    $lastDay = date('t', strtotime($date));

    if ($day <= 15) {
        $from = sprintf('%04d-%02d-01', $year, $month);
        $to   = sprintf('%04d-%02d-15', $year, $month);
    } else {
        $from = sprintf('%04d-%02d-16', $year, $month);
        $to   = sprintf('%04d-%02d-%02d', $year, $month, $lastDay);
    }

    return ['from' => $from, 'to' => $to];
}

function getFixedPeriods(): array
{
    return computePeriodContaining(date('Y-m-d'));
}

function computeNextPeriod(string $lastClosingTo): array
{
    $nextDay = date('Y-m-d', strtotime($lastClosingTo . ' +1 day'));
    return computePeriodContaining($nextDay);
}

function getEarliestIncomeDate(mysqli $conn): ?string
{
    $dates = [];

    $r1 = $conn->query("SELECT MIN(DATE(created_at)) AS d FROM level_matching_income_report");
    if ($r1 && $row = $r1->fetch_assoc()) {
        if (!empty($row['d'])) $dates[] = $row['d'];
    }

    $r2 = $conn->query("SELECT MIN(DATE(income_date)) AS d FROM repurchase_matching_income_log");
    if ($r2 && $row = $r2->fetch_assoc()) {
        if (!empty($row['d'])) $dates[] = $row['d'];
    }

    if (empty($dates)) return null;

    sort($dates);
    return $dates[0];
}

/**
 * Insert a closing cycle row if it doesn't already exist for this from/to.
 * Never mutates an existing row - purely idempotent creation.
 */
function insertCycleIfNotExists(mysqli $conn, string $from, string $to): void
{
    $stmt = $conn->prepare("SELECT id FROM payout_closing_cycle WHERE closing_from=? AND closing_to=? LIMIT 1");
    $stmt->bind_param("ss", $from, $to);
    $stmt->execute();
    $exists = $stmt->get_result()->num_rows > 0;
    $stmt->close();

    if (!$exists) {
        $ins = $conn->prepare("INSERT INTO payout_closing_cycle (closing_from, closing_to, generated, paid) VALUES (?, ?, 'No', 'No')");
        $ins->bind_param("ss", $from, $to);
        $ins->execute();
        $ins->close();
    }
}

/**
 * REQUIREMENT 1: Generation and payment are now completely independent.
 *
 * Instead of waiting for the previous cycle to be generated AND paid,
 * this walks forward from whatever the latest existing cycle is, creating
 * every missing bi-monthly period up through the period that contains
 * today's date. Payment status is never consulted.
 */
function ensureAllCyclesExist(mysqli $conn): void
{
    $res  = $conn->query("SELECT closing_from, closing_to FROM payout_closing_cycle ORDER BY closing_to DESC LIMIT 1");
    $last = $res ? $res->fetch_assoc() : null;

    if (!$last) {
        $earliestIncome = getEarliestIncomeDate($conn);
        $period = $earliestIncome ? computePeriodContaining($earliestIncome) : getFixedPeriods();
        insertCycleIfNotExists($conn, $period['from'], $period['to']);
        $lastTo = $period['to'];
    } else {
        $lastTo = $last['closing_to'];
    }

    $currentPeriod = computePeriodContaining(date('Y-m-d'));

    // Keep creating the next period after the latest one until we reach
    // (or pass) the period that contains today. This is what lets July,
    // August, etc. show up automatically without waiting on June's payment.
    $guard = 0; // safety valve against any pathological infinite loop
    while ($lastTo < $currentPeriod['to'] && $guard < 1000) {
        $next = computeNextPeriod($lastTo);
        insertCycleIfNotExists($conn, $next['from'], $next['to']);
        $lastTo = $next['to'];
        $guard++;
    }
}

/**
 * REQUIREMENT 2 & 3: Replaces the old single-cycle getCycleState().
 *
 * Returns every cycle that is not yet "done" (i.e. not both generated AND
 * paid) ordered oldest-first, each annotated with a simple status label:
 *   - 'not_generated'    -> ready for admin to click Generate
 *   - 'payment_pending'  -> generated, waiting on payment
 *
 * Fully generated + fully paid cycles are historical and are intentionally
 * left out of this "action needed" list; they still exist untouched in
 * payout_closing_cycle and remain visible/searchable in the main table.
 */
function getPendingCycles(mysqli $conn): array
{
    $res = $conn->query("
        SELECT id, closing_from, closing_to, generated, paid, generated_at, paid_at
        FROM payout_closing_cycle
        WHERE generated = 'No' OR paid = 'No'
        ORDER BY closing_from ASC
    ");

    $cycles = [];
    while ($res && $row = $res->fetch_assoc()) {
        $status = ($row['generated'] === 'Yes') ? 'payment_pending' : 'not_generated';
        $cycles[] = [
            'id'           => $row['id'],
            'from'         => $row['closing_from'],
            'to'           => $row['closing_to'],
            'generated'    => $row['generated'],
            'paid'         => $row['paid'],
            'status'       => $status,
        ];
    }
    return $cycles;
}

/**
 * Every closing cycle, most recent first, each labeled with a simple
 * status: 'not_generated', 'payment_pending', or 'paid'. Used to populate
 * the single "Closing Period" dropdown selector.
 */
function getAllCycles(mysqli $conn): array
{
    $res = $conn->query("
        SELECT id, closing_from, closing_to, generated, paid
        FROM payout_closing_cycle
        ORDER BY closing_from DESC
    ");

    $cycles = [];
    while ($res && $row = $res->fetch_assoc()) {
        if ($row['generated'] !== 'Yes') {
            $status = 'not_generated';
        } elseif ($row['paid'] !== 'Yes') {
            $status = 'payment_pending';
        } else {
            $status = 'paid';
        }
        $cycles[] = [
            'from'   => $row['closing_from'],
            'to'     => $row['closing_to'],
            'status' => $status,
        ];
    }
    return $cycles;
}

function getLastPaidClosing(mysqli $conn): ?array
{
    $res = $conn->query("SELECT closing_from, closing_to, paid_at FROM payout_closing_cycle WHERE paid='Yes' ORDER BY closing_to DESC LIMIT 1");
    if ($res && $row = $res->fetch_assoc()) {
        return $row;
    }
    return null;
}

function getPendingIncomePreview(mysqli $conn, string $from, string $to): array
{
    $sql = "
        SELECT COUNT(*) AS member_count, COALESCE(SUM(total_income),0) AS total_amount
        FROM (
            SELECT customer_id, SUM(total_income) AS total_income
            FROM (
                SELECT CAST(customer_id AS CHAR) AS customer_id, SUM(credit) AS total_income
                FROM level_matching_income_report
                WHERE DATE(created_at) BETWEEN ? AND ?
                GROUP BY customer_id

                UNION ALL

                SELECT CAST(customer_id AS CHAR) AS customer_id, SUM(income_amount) AS total_income
                FROM repurchase_matching_income_log
                WHERE DATE(income_date) BETWEEN ? AND ?
                GROUP BY customer_id
            ) income_data
            GROUP BY customer_id
            HAVING SUM(total_income) > 0
        ) grouped
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ssss', $from, $to, $from, $to);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return [
        'member_count' => (int)($row['member_count'] ?? 0),
        'total_amount' => (float)($row['total_amount'] ?? 0),
    ];
}

function getPendingSummary(mysqli $conn): array
{
    $res = $conn->query("SELECT COUNT(DISTINCT customer_id) AS cnt, COALESCE(SUM(net_payable),0) AS amt FROM member_payout_closing WHERE status='Pending'");
    $row = $res ? $res->fetch_assoc() : null;
    return [
        'count'  => (int)($row['cnt'] ?? 0),
        'amount' => (float)($row['amt'] ?? 0),
    ];
}

/**
 * REQUIREMENT 6/9/10: Generates payout rows for exactly one explicit
 * closing period. Never touches any other cycle. Guards against
 * double-generation and never creates duplicate cycle rows.
 *
 * Returns ['ok' => bool, 'message' => string]
 */
function generateClosingPayout(mysqli $conn, string $genFrom, string $genTo): array
{
    $lockStmt = $conn->prepare("SELECT generated FROM payout_closing_cycle WHERE closing_from=? AND closing_to=? LIMIT 1");
    $lockStmt->bind_param('ss', $genFrom, $genTo);
    $lockStmt->execute();
    $lockRow = $lockStmt->get_result()->fetch_assoc();
    $lockStmt->close();

    if (!$lockRow) {
        return ['ok' => false, 'message' => "Unknown closing period ($genFrom to $genTo)."];
    }

    if ($lockRow['generated'] === 'Yes') {
        return ['ok' => false, 'message' => "Closing $genFrom to $genTo is already generated."];
    }

    $checkStmt = $conn->prepare("
        SELECT
            (SELECT COALESCE(SUM(credit),0) FROM level_matching_income_report
             WHERE DATE(created_at) BETWEEN ? AND ?) +
            (SELECT COALESCE(SUM(income_amount),0) FROM repurchase_matching_income_log
             WHERE DATE(income_date) BETWEEN ? AND ?)
        AS total_amount
    ");
    $checkStmt->bind_param('ssss', $genFrom, $genTo, $genFrom, $genTo);
    $checkStmt->execute();
    $checkRow    = $checkStmt->get_result()->fetch_assoc();
    $checkStmt->close();
    $totalAmount = $checkRow ? (float)$checkRow['total_amount'] : 0;

    if ($totalAmount <= 0) {
        return ['ok' => false, 'message' => "No income found for the period ($genFrom to $genTo)."];
    }

    try {
        $conn->begin_transaction();

        // Collation-safe join to signup_users: income_data.customer_id comes from
        // CAST(... AS CHAR) and can carry a different effective collation than
        // signup_users.customer_id.
        $incomeSql = "
            SELECT
                income_data.customer_id,
                COALESCE(su.full_name, '-') AS full_name,
                ROUND(SUM(income_data.total_income), 2) AS gross_income
            FROM (
                SELECT CAST(customer_id AS CHAR) AS customer_id,
                       SUM(credit) AS total_income
                FROM level_matching_income_report
                WHERE DATE(created_at) BETWEEN ? AND ?
                GROUP BY customer_id

                UNION ALL

                SELECT CAST(customer_id AS CHAR) AS customer_id,
                       SUM(income_amount) AS total_income
                FROM repurchase_matching_income_log
                WHERE DATE(income_date) BETWEEN ? AND ?
                GROUP BY customer_id
            ) AS income_data
            LEFT JOIN signup_users su
                ON su.customer_id COLLATE utf8mb4_unicode_ci = income_data.customer_id COLLATE utf8mb4_unicode_ci
            GROUP BY income_data.customer_id, su.full_name
            HAVING ROUND(SUM(income_data.total_income), 2) > 0
            ORDER BY income_data.customer_id ASC
        ";
        $incomeStmt = $conn->prepare($incomeSql);
        if (!$incomeStmt) throw new Exception('Unable to prepare income query.');
        $incomeStmt->bind_param('ssss', $genFrom, $genTo, $genFrom, $genTo);
        $incomeStmt->execute();
        $incomeResult = $incomeStmt->get_result();

        $existsCheck = $conn->prepare("
            SELECT id, status
            FROM member_payout_closing
            WHERE customer_id  = ?
            AND   closing_from = ?
            AND   closing_to   = ?
            ORDER BY id DESC
            LIMIT 1
        ");
        if (!$existsCheck) throw new Exception('Unable to prepare exists check.');

        $updateStmt = $conn->prepare("
            UPDATE member_payout_closing
            SET full_name    = ?,
                gross_income = ?,
                tds_amount   = ?,
                admin_charge = ?,
                net_payable  = ?
            WHERE customer_id  = ?
            AND   closing_from = ?
            AND   closing_to   = ?
            AND   status       = 'Pending'
        ");
        if (!$updateStmt) throw new Exception('Unable to prepare update query.');

        $insertStmt = $conn->prepare("
            INSERT INTO member_payout_closing
                (customer_id, full_name, closing_from, closing_to,
                 gross_income, tds_amount, admin_charge, net_payable,
                 status, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Pending', NOW())
        ");
        if (!$insertStmt) throw new Exception('Unable to prepare insert query.');

        $inserted = 0;
        $updated  = 0;

        while ($row = $incomeResult->fetch_assoc()) {
            $customerId  = (string)$row['customer_id'];
            $fullName    = trim((string)$row['full_name']) ?: '-';
            $grossIncome = round((float)$row['gross_income'], 2);
            if ($grossIncome <= 0) continue;

            $tdsAmount   = round($grossIncome * 0.02, 2);
            $adminCharge = round($grossIncome * 0.05, 2);
            $netPayable  = round($grossIncome - $tdsAmount - $adminCharge, 2);

            $existsCheck->bind_param('sss', $customerId, $genFrom, $genTo);
            $existsCheck->execute();
            $existsResult = $existsCheck->get_result();
            $existingRow  = $existsResult ? $existsResult->fetch_assoc() : null;

            if ($existingRow && $existingRow['status'] === 'Pending') {
                $updateStmt->bind_param(
                    'sddddsss',
                    $fullName, $grossIncome, $tdsAmount, $adminCharge, $netPayable,
                    $customerId, $genFrom, $genTo
                );
                $updateStmt->execute();
                $updated++;
            } else {
                $insertStmt->bind_param(
                    'ssssdddd',
                    $customerId, $fullName,
                    $genFrom, $genTo,
                    $grossIncome, $tdsAmount, $adminCharge, $netPayable
                );
                $insertStmt->execute();
                $inserted++;
            }
        }

        $incomeStmt->close();
        $existsCheck->close();
        $updateStmt->close();
        $insertStmt->close();

        $conn->commit();

        // REQUIREMENT 6: mark only this closing as generated. No other row touched.
        $upd = $conn->prepare("
            UPDATE payout_closing_cycle
            SET generated='Yes', generated_at=NOW()
            WHERE closing_from=? AND closing_to=?
        ");
        $upd->bind_param("ss", $genFrom, $genTo);
        $upd->execute();
        $upd->close();

        $parts = [];
        if ($inserted > 0) $parts[] = "$inserted new member(s) added";
        if ($updated  > 0) $parts[] = "$updated member(s) refreshed";

        return [
            'ok'      => true,
            'message' => "Payout generated for $genFrom to $genTo: " . implode(', ', $parts ?: ['no changes needed']) . ".",
        ];

    } catch (Throwable $ex) {
        $conn->rollback();
        return ['ok' => false, 'message' => "Failed to generate payout: " . $ex->getMessage()];
    }
}

// ---------------------------------------------------------------------
// Main flow
// ---------------------------------------------------------------------

// REQUIREMENT 1: this now backfills every missing closing period up to
// today on every page load, independent of any payment status.
ensureAllCyclesExist($conn);

$pendingCycles = getPendingCycles($conn);
$lastPaid      = getLastPaidClosing($conn);

$lastClosingFrom = $lastPaid['closing_from'] ?? null;
$lastClosingTo   = $lastPaid['closing_to'] ?? null;

$defaultFrom = '2000-01-01';
$defaultTo   = date('Y-m-d');

$hasFilterInput = isset($_GET['from']) || isset($_GET['to']) || isset($_GET['status_filter']) || isset($_GET['member_search']) || isset($_POST['from']);

$from         = safeDate($_POST['from'] ?? $_GET['from'] ?? $defaultFrom, $defaultFrom);
$to           = safeDate($_POST['to'] ?? $_GET['to'] ?? $defaultTo, $defaultTo);
$memberSearch = trim($_POST['member_search'] ?? $_GET['member_search'] ?? '');
$statusFilter = trim($_POST['status_filter'] ?? $_GET['status_filter'] ?? ($hasFilterInput ? 'All' : 'Pending'));

if (!in_array($statusFilter, ['Pending', 'Paid', 'All'], true)) {
    $statusFilter = 'All';
}

$message     = '';
$messageType = 'success';

if (isset($_POST['update_status'])) {
    $updateCustomer = trim($_POST['payout_customer_id'] ?? '');
    $updateStatus   = in_array($_POST['status'] ?? '', ['Pending', 'Paid'], true)
                      ? $_POST['status'] : 'Pending';
    $uFrom = safeDate($_POST['from'] ?? $defaultFrom, $defaultFrom);
    $uTo   = safeDate($_POST['to']   ?? $defaultTo,   $defaultTo);

    if ($updateCustomer !== '') {
        $stmt = $conn->prepare("
            UPDATE member_payout_closing
            SET status = ?
            WHERE customer_id  = ?
            AND   closing_from >= ?
            AND   closing_from <= ?
        ");
        $stmt->bind_param('ssss', $updateStatus, $updateCustomer, $uFrom, $uTo);
        $stmt->execute();
        $stmt->close();
    }

    $message     = "Status updated successfully.";
    $messageType = 'success';
}

// REQUIREMENT 5: Marking a specific closing as paid only ever touches
// member_payout_closing + payout_closing_cycle for that exact period.
// It never affects, blocks, or depends on any other cycle.
if (isset($_POST['mark_all_paid'])) {

    $mFrom = safeDate($_POST['from'] ?? $defaultFrom, $defaultFrom);
    $mTo   = safeDate($_POST['to']   ?? $defaultTo,   $defaultTo);

    $stmt = $conn->prepare("
        UPDATE member_payout_closing
        SET status = 'Paid'
        WHERE status = 'Pending'
          AND closing_from = ?
          AND closing_to = ?
    ");
    $stmt->bind_param('ss', $mFrom, $mTo);
    $stmt->execute();
    $affected = $stmt->affected_rows;
    $stmt->close();

    $cycleStmt = $conn->prepare("
        UPDATE payout_closing_cycle
        SET paid = 'Yes',
            paid_at = NOW()
        WHERE closing_from = ?
          AND closing_to = ?
    ");
    $cycleStmt->bind_param("ss", $mFrom, $mTo);
    $cycleStmt->execute();
    $cycleStmt->close();

    $uStmt = $conn->prepare("
        SELECT COUNT(DISTINCT customer_id) AS cnt
        FROM member_payout_closing
        WHERE status = 'Paid'
          AND closing_from = ?
          AND closing_to = ?
    ");
    $uStmt->bind_param('ss', $mFrom, $mTo);
    $uStmt->execute();
    $uRes = $uStmt->get_result()->fetch_assoc();
    $userCount = (int)($uRes['cnt'] ?? 0);
    $uStmt->close();

    $message = "$userCount member(s) marked as Paid ($affected row(s) updated) for $mFrom to $mTo.";
    $messageType = 'success';

    ensureAllCyclesExist($conn);
    $pendingCycles   = getPendingCycles($conn);
    $lastPaid        = getLastPaidClosing($conn);
    $lastClosingFrom = $lastPaid['closing_from'] ?? null;
    $lastClosingTo   = $lastPaid['closing_to'] ?? null;
}

// REQUIREMENT 6/7: Generate now always targets one explicit closing period
// (posted via hidden gen_from/gen_to fields on that closing's own card),
// never a single global "current cycle". This is what makes a period like
// 1-15 Jul generatable and immediately searchable even while 16-30 Jun is
// still sitting unpaid.
if (isset($_POST['generate_payout'])) {
    $genFrom = safeDate($_POST['gen_from'] ?? '', '');
    $genTo   = safeDate($_POST['gen_to'] ?? '', '');

    if ($genFrom === '' || $genTo === '') {
        $message     = "Invalid closing period selected.";
        $messageType = 'error';
    } else {
        $result      = generateClosingPayout($conn, $genFrom, $genTo);
        $message     = $result['message'];
        $messageType = $result['ok'] ? 'success' : 'error';
    }

    ensureAllCyclesExist($conn);
    $pendingCycles   = getPendingCycles($conn);
    $lastPaid        = getLastPaidClosing($conn);
    $lastClosingFrom = $lastPaid['closing_from'] ?? null;
    $lastClosingTo   = $lastPaid['closing_to'] ?? null;
}

$pendingSummary = getPendingSummary($conn);

// The single "headline" cycle shown in the top summary card - the oldest
// item still needing action, if any.
$headlineCycle = $pendingCycles[0] ?? null;

// All closing periods, for the single "Closing Period" selector.
$allCycles = getAllCycles($conn);

// If the current from/to exactly matches one known cycle, that's the
// cycle the admin has selected in the dropdown. A preview (member count
// and income) is only computed for that one selected cycle, and only if
// it still needs generating - keeps the page light regardless of how
// many historical cycles exist.
$selectedCycle = null;
foreach ($allCycles as $c) {
    if ($c['from'] === $from && $c['to'] === $to) {
        $selectedCycle = $c;
        break;
    }
}

$selectedPreview = ['member_count' => 0, 'total_amount' => 0];
if ($selectedCycle && $selectedCycle['status'] === 'not_generated') {
    $selectedPreview = getPendingIncomePreview($conn, $selectedCycle['from'], $selectedCycle['to']);
}

$rows       = [];
$totalGross = 0;
$totalTds   = 0;
$totalAdmin = 0;
$totalNet   = 0;
$i          = 1;

// COLLATE utf8mb4_unicode_ci on both sides of the join condition is what fixes the
// "Illegal mix of collations" fatal error: it forces MySQL to compare mpc.customer_id
// and k.customer_id under one explicit collation instead of each column's own default.
$sql = "
SELECT
    mpc.customer_id,
    MAX(mpc.full_name) AS full_name,

    MAX(k.account_beneficiary_name) AS beneficiary_name,
    MAX(k.bank_name) AS bank_name,
    MAX(k.account_no) AS account_no,
    MAX(k.ifsc_code) AS ifsc_code,
    MAX(su.mobile) AS mobile,

    MIN(mpc.closing_from) AS closing_from,
    MAX(mpc.closing_to) AS closing_to,
    ROUND(SUM(mpc.gross_income),2) AS gross_income,
    ROUND(SUM(mpc.tds_amount),2) AS tds_amount,
    ROUND(SUM(mpc.admin_charge),2) AS admin_charge,
    ROUND(SUM(mpc.net_payable),2) AS net_payable,

    MAX(CASE WHEN mpc.status='Pending' THEN 1 ELSE 0 END) AS has_pending,
    GROUP_CONCAT(DISTINCT mpc.status ORDER BY mpc.status) AS all_statuses,
    MIN(mpc.id) AS min_id

FROM member_payout_closing mpc

LEFT JOIN member_bank_kyc_details k
ON k.customer_id COLLATE utf8mb4_unicode_ci = mpc.customer_id COLLATE utf8mb4_unicode_ci

LEFT JOIN signup_users su
ON su.customer_id COLLATE utf8mb4_unicode_ci = mpc.customer_id COLLATE utf8mb4_unicode_ci

WHERE 1=1

";

$params = [];
$types  = "";

if ($statusFilter === 'Pending') {

    $sql .= " AND mpc.status = 'Pending' ";

} elseif ($statusFilter === 'Paid') {

    $sql .= " AND mpc.status = 'Paid'
              AND mpc.closing_from >= ?
              AND mpc.closing_from <= ? ";

    $params[] = $from;
    $params[] = $to;
    $types .= "ss";

    $sql .= " AND mpc.customer_id NOT IN (
                    SELECT DISTINCT customer_id
                    FROM member_payout_closing
                    WHERE closing_from >= ?
                      AND closing_from <= ?
                      AND status = 'Pending'
                 ) ";

    $params[] = $from;
    $params[] = $to;
    $types .= "ss";

} else {

    $sql .= " AND mpc.closing_from >= ?
              AND mpc.closing_from <= ? ";

    $params[] = $from;
    $params[] = $to;
    $types .= "ss";
}

if ($memberSearch !== '') {

    $sql .= " AND (
                mpc.customer_id LIKE ?
                OR mpc.full_name LIKE ?
              ) ";

    $like = '%' . $memberSearch . '%';

    $params[] = $like;
    $params[] = $like;
    $types .= "ss";
}

$sql .= " GROUP BY mpc.customer_id ORDER BY MIN(mpc.id) DESC";

$stmt = $conn->prepare($sql);
if ($stmt) {
    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();

    while ($r = $result->fetch_assoc()) {
        $gross = (float)$r['gross_income'];
        $tds   = (float)$r['tds_amount'];
        $admin = (float)$r['admin_charge'];
        $net   = (float)$r['net_payable'];

        $totalGross += $gross;
        $totalTds   += $tds;
        $totalAdmin += $admin;
        $totalNet   += $net;

        $consolidatedStatus = ((int)$r['has_pending'] === 1) ? 'Pending' : 'Paid';

        $rows[] = [
            'serial'        => $i++,
            'id'            => $r['min_id'],
            'member_id'     => $r['customer_id'],
            'name'          => $r['full_name'],
            'mobile'        => $r['mobile'] ?: '-',

            'beneficiary_name' => $r['beneficiary_name'] ?: '-',
            'bank_name'        => $r['bank_name'] ?: '-',
            'account_no'       => $r['account_no'] ?: '-',
            'ifsc_code'        => $r['ifsc_code'] ?: '-',

            'closing_from'  => $r['closing_from'],
            'closing_to'    => $r['closing_to'],
            'gross_income'  => $gross,
            'tds'           => $tds,
            'admin'         => $admin,
            'net'           => $net,
            'status'        => $consolidatedStatus,
        ];
    }

    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Member Payout Report</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<script src="https://cdn.tailwindcss.com"></script>
<script src="https://unpkg.com/lucide@latest"></script>
<script src="https://unpkg.com/xlsx/dist/xlsx.full.min.js"></script>
<script src="https://unpkg.com/jspdf@latest/dist/jspdf.umd.min.js"></script>
<script src="https://unpkg.com/jspdf-autotable@latest/dist/jspdf.plugin.autotable.min.js"></script>
</head>
<body class="bg-[#f3f4f6] font-sans overflow-x-hidden">
<?php include __DIR__ . '/includes/sidebar.php'; ?>
<div class="ml-64 min-h-screen">
<?php include __DIR__ . '/includes/navbar.php'; ?>
<main class="px-5 py-5">
<div class="max-w-[1700px] mx-auto">

<div class="mt-5 flex items-center gap-3 mb-6">
    <div class="w-[36px] h-[36px] rounded-[8px] bg-[#f5eee3] flex items-center justify-center shrink-0">
        <i data-lucide="wallet" class="w-[18px] h-[18px] text-[#cd9737]"></i>
    </div>
    <h2 class="whitespace-nowrap text-[20px] font-normal text-[#40352a]" style="font-family: Georgia, 'Times New Roman', serif;">MEMBER PAYOUT REPORT</h2>
    <div class="w-full h-[2px] bg-[#d1a14b]"></div>
</div>

<?php if ($message !== ''): ?>
<div class="mb-5 rounded-lg px-5 py-3 text-sm <?= $messageType === 'success' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'; ?>">
    <?= e($message); ?>
</div>
<?php endif; ?>

<div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">

    <div class="bg-orange-50 border border-orange-200 rounded-lg px-5 py-3">
        <div class="text-xs text-orange-500 uppercase tracking-wide mb-1">Pending Members (All Periods)</div>
        <div class="font-semibold text-orange-700 text-lg"><?= (int)$pendingSummary['count']; ?> members &middot; <?= money($pendingSummary['amount']); ?></div>
    </div>

    <div class="bg-slate-50 border border-slate-200 rounded-lg px-5 py-3">
        <div class="text-xs text-gray-500 uppercase tracking-wide mb-1">Last Paid Closing</div>
        <div class="font-semibold text-gray-700">
            <?php if ($lastClosingFrom && $lastClosingTo): ?>
                <?= e(date('d M Y', strtotime($lastClosingFrom))); ?> &ndash; <?= e(date('d M Y', strtotime($lastClosingTo))); ?>
            <?php else: ?>
                &mdash;
            <?php endif; ?>
        </div>
    </div>

    <div class="bg-yellow-50 border border-yellow-200 rounded-lg px-5 py-3">
        <div class="text-xs text-yellow-600 uppercase tracking-wide mb-1">
            <?php if (!$headlineCycle): ?>
                All Closings Up To Date
            <?php elseif ($headlineCycle['status'] === 'payment_pending'): ?>
                Payment Pending
            <?php else: ?>
                Next Closing To Generate
            <?php endif; ?>
        </div>
        <div class="font-semibold text-yellow-800">
            <?php if ($headlineCycle): ?>
                <?= e(date('d M Y', strtotime($headlineCycle['from']))); ?> &ndash; <?= e(date('d M Y', strtotime($headlineCycle['to']))); ?>
                <?php if (count($pendingCycles) > 1): ?>
                    <span class="text-xs font-normal text-yellow-700">(+<?= count($pendingCycles) - 1; ?> more &mdash; pick from Closing Period below)</span>
                <?php endif; ?>
            <?php else: ?>
                &mdash;
            <?php endif; ?>
        </div>
    </div>

</div>

<div class="bg-white rounded-xl shadow border p-5 mb-6">
    <form method="GET" id="filterForm" class="flex flex-wrap items-end gap-4">
        <div>
            <label class="block mb-1 font-semibold text-sm text-gray-700">Closing Period</label>
            <select onchange="selectClosingPeriod(this)" class="border rounded-lg px-4 py-2 text-sm min-w-[260px]">
                <option value="<?= e($defaultFrom); ?>|<?= e($defaultTo); ?>|Pending" <?= !$selectedCycle ? 'selected' : ''; ?>>All Periods</option>
                <?php foreach ($allCycles as $c): ?>
                <?php
                    $label = date('d M Y', strtotime($c['from'])) . ' - ' . date('d M Y', strtotime($c['to']));
                    $statusLabel = $c['status'] === 'not_generated'
                        ? 'Ready To Generate'
                        : ($c['status'] === 'payment_pending' ? 'Payment Pending' : 'Paid');
                    $isSel = $selectedCycle && $selectedCycle['from'] === $c['from'] && $selectedCycle['to'] === $c['to'];
                ?>
                <option value="<?= e($c['from']); ?>|<?= e($c['to']); ?>|All" <?= $isSel ? 'selected' : ''; ?>><?= e($label); ?> - <?= e($statusLabel); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="block mb-1 font-semibold text-sm text-gray-700">From Date</label>
            <input type="date" name="from" id="fromField" value="<?= e($from); ?>" class="border rounded-lg px-4 py-2 text-sm">
        </div>
        <div>
            <label class="block mb-1 font-semibold text-sm text-gray-700">To Date</label>
            <input type="date" name="to" id="toField" value="<?= e($to); ?>" class="border rounded-lg px-4 py-2 text-sm">
        </div>
        <div>
            <label class="block mb-1 font-semibold text-sm text-gray-700">Member ID / Name</label>
            <input type="search" name="member_search" value="<?= e($memberSearch); ?>" placeholder="Search member ID or name" class="border rounded-lg px-4 py-2 min-w-[220px] text-sm">
        </div>
        <div>
            <label class="block mb-1 font-semibold text-sm text-gray-700">Status</label>
            <select name="status_filter" id="statusField" class="border rounded-lg px-4 py-2 text-sm">
                <option value="All"     <?= $statusFilter === 'All'     ? 'selected' : ''; ?>>All</option>
                <option value="Pending" <?= $statusFilter === 'Pending' ? 'selected' : ''; ?>>Pending</option>
                <option value="Paid"    <?= $statusFilter === 'Paid'    ? 'selected' : ''; ?>>Paid</option>
            </select>
        </div>
        <button type="submit" class="bg-[#12395f] text-white px-5 py-2 rounded-lg text-sm">Search Report</button>
        <a href="member-payout-report.php" class="bg-gray-200 text-gray-700 px-5 py-2 rounded-lg text-sm">Clear</a>
    </form>

    <?php if ($selectedCycle): ?>
    <div class="mt-4 flex flex-wrap items-center justify-between gap-4 rounded-lg border px-5 py-3 <?php
            if ($selectedCycle['status'] === 'not_generated') echo 'bg-blue-50 border-blue-200';
            elseif ($selectedCycle['status'] === 'payment_pending') echo 'bg-yellow-50 border-yellow-200';
            else echo 'bg-green-50 border-green-200';
        ?>">
        <div>
            <span class="text-xs uppercase tracking-wide font-semibold <?php
                    if ($selectedCycle['status'] === 'not_generated') echo 'text-blue-500';
                    elseif ($selectedCycle['status'] === 'payment_pending') echo 'text-yellow-600';
                    else echo 'text-green-600';
                ?>">Selected Closing</span>
            <div class="font-semibold text-[#12395f]">
                <?= e(date('d M Y', strtotime($selectedCycle['from']))); ?> &ndash; <?= e(date('d M Y', strtotime($selectedCycle['to']))); ?>
            </div>
            <?php if ($selectedCycle['status'] === 'not_generated'): ?>
                <div class="text-xs text-blue-700 mt-1">
                    Not generated yet
                    <?php if ($selectedPreview['member_count'] > 0): ?>
                        &middot; <?= (int)$selectedPreview['member_count']; ?> member(s) &middot; <?= money($selectedPreview['total_amount']); ?> waiting
                    <?php endif; ?>
                </div>
            <?php elseif ($selectedCycle['status'] === 'payment_pending'): ?>
                <div class="text-xs text-yellow-700 mt-1">Generated &middot; payment still pending</div>
            <?php else: ?>
                <div class="text-xs text-green-700 mt-1">Fully paid</div>
            <?php endif; ?>
        </div>

        <?php if ($selectedCycle['status'] === 'not_generated'): ?>
            <form method="post" class="inline">
                <input type="hidden" name="generate_payout" value="1">
                <input type="hidden" name="gen_from" value="<?= e($selectedCycle['from']); ?>">
                <input type="hidden" name="gen_to"   value="<?= e($selectedCycle['to']); ?>">
                <button type="submit" class="bg-emerald-600 hover:bg-emerald-700 text-white px-6 py-2 rounded-lg text-sm font-semibold">Generate</button>
            </form>
        <?php elseif ($selectedCycle['status'] === 'payment_pending'): ?>
            <form method="post" class="inline">
                <input type="hidden" name="mark_all_paid" value="1">
                <input type="hidden" name="from" value="<?= e($selectedCycle['from']); ?>">
                <input type="hidden" name="to"   value="<?= e($selectedCycle['to']); ?>">
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-5 py-2 rounded-lg text-sm font-semibold">Mark Paid</button>
            </form>
        <?php else: ?>
            <span class="text-green-700 font-semibold text-sm">Paid</span>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<script>
function selectClosingPeriod(sel) {
    var parts = sel.value.split('|');
    document.getElementById('fromField').value = parts[0];
    document.getElementById('toField').value = parts[1];
    document.getElementById('statusField').value = parts[2];
    sel.form.submit();
}
</script>

<div class="bg-white rounded-xl shadow border overflow-hidden">
    <div class="flex items-center justify-between px-5 py-3 border-b bg-gray-50 flex-wrap gap-3">
        <p class="text-sm text-gray-600 font-semibold"><?= !empty($rows) ? count($rows) . ' record(s) found' : 'No records'; ?></p>
        <?php if (!empty($rows)): ?>
        <div class="flex gap-2 flex-wrap">
            <button type="button" onclick="downloadExcel(); return false;" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg text-sm">Download Excel</button>
            <button type="button" onclick="downloadPDF(); return false;" class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg text-sm">Download PDF</button>
            <?php if ($statusFilter !== 'Paid'): ?>
            <form method="post" class="inline">
                <input type="hidden" name="mark_all_paid" value="1">
                <input type="hidden" name="from" value="<?= e($from); ?>">
                <input type="hidden" name="to"   value="<?= e($to); ?>">
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm">Mark All Paid</button>
            </form>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-sm" id="payoutTable">
            <thead class="bg-[#12395f] text-white">
                <tr>
                    <th class="px-4 py-4 text-left">Sr No</th>
                    <th class="px-4 py-4 text-left">Member ID</th>
                    <th class="px-4 py-4 text-left">Name</th>
                    <th class="px-4 py-4 text-left">Mobile</th>
                    <th class="px-4 py-4 text-left">Beneficiary</th>
                    <th class="px-4 py-4 text-left">Bank Name</th>
                    <th class="px-4 py-4 text-left">Account No</th>
                    <th class="px-4 py-4 text-left">IFSC</th>
                    <th class="px-4 py-4 text-left">Closing Date</th>
                    <th class="px-4 py-4 text-left">Gross Income</th>
                    <th class="px-4 py-4 text-left">TDS (2%)</th>
                    <th class="px-4 py-4 text-left">Admin (5%)</th>
                    <th class="px-4 py-4 text-left">Net Payable</th>
                    <th class="px-4 py-4 text-left">Action</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!empty($rows)): ?>
                <?php foreach ($rows as $row): ?>
                <tr class="border-b hover:bg-gray-50 transition">
                    <td class="px-4 py-3"><?= (int)$row['serial']; ?></td>
                    <td class="px-4 py-3 font-semibold text-blue-600"><?= e($row['member_id']); ?></td>
                    <td class="px-4 py-3"><?= e($row['name']); ?></td>
                    <td class="px-4 py-3 font-mono"><?= e($row['mobile']); ?></td>

                    <td class="px-4 py-3"><?= e($row['beneficiary_name']); ?></td>
                    <td class="px-4 py-3"><?= e($row['bank_name']); ?></td>
                    <td class="px-4 py-3 font-mono"><?= e($row['account_no']); ?></td>
                    <td class="px-4 py-3 font-mono"><?= e($row['ifsc_code']); ?></td>

                    <td class="px-4 py-3 text-gray-600 whitespace-nowrap"><?= e(date('d M Y', strtotime($row['closing_from']))); ?> &ndash; <?= e(date('d M Y', strtotime($row['closing_to']))); ?></td>
                    <td class="px-4 py-3 font-bold text-emerald-700"><?= money($row['gross_income']); ?></td>
                    <td class="px-4 py-3 text-red-600"><?= money($row['tds']); ?></td>
                    <td class="px-4 py-3 text-orange-600"><?= money($row['admin']); ?></td>
                    <td class="px-4 py-3 font-bold text-green-700"><?= money($row['net']); ?></td>
                    <td class="px-4 py-3">
                        <form method="post" class="flex items-center gap-2 flex-wrap">
                            <input type="hidden" name="payout_customer_id" value="<?= e($row['member_id']); ?>">
                            <input type="hidden" name="from" value="<?= e($from); ?>">
                            <input type="hidden" name="to"   value="<?= e($to); ?>">
                            <select name="status" class="border rounded px-2 py-1 text-sm">
                                <option value="Pending" <?= $row['status'] === 'Pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="Paid"    <?= $row['status'] === 'Paid'    ? 'selected' : ''; ?>>Paid</option>
                            </select>
                            <button type="submit" name="update_status" class="bg-[#12395f] text-white px-3 py-1 rounded">Update</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
                <tr class="bg-[#f0f4f8] font-bold text-sm border-t-2 border-[#12395f]">
                    <td colspan="9" class="px-4 py-4 text-right text-gray-700 uppercase tracking-wide">Grand Total</td>
                    <td class="px-4 py-4 text-emerald-700"><?= money($totalGross); ?></td>
                    <td class="px-4 py-4 text-red-600"><?= money($totalTds); ?></td>
                    <td class="px-4 py-4 text-orange-600"><?= money($totalAdmin); ?></td>
                    <td class="px-4 py-4 text-green-700"><?= money($totalNet); ?></td>
                    <td></td>
                </tr>
            <?php else: ?>
                <tr><td colspan="14" class="text-center py-16 text-gray-400">No payout records found</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

</div>
</main>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    if (typeof lucide !== 'undefined') lucide.createIcons();
});

function downloadExcel() {
    if (typeof XLSX === 'undefined') { alert('Excel library not loaded. Please refresh.'); return; }

    const from   = <?= json_encode($from); ?>;
    const to     = <?= json_encode($to); ?>;
    const status = <?= json_encode($statusFilter); ?>;

    const data = [
        ['Member Payout Report'],
        ['Period: ' + from + ' to ' + to + ' | Status: ' + status],
        [],
        [
            '#',
            'Member ID',
            'Name',
            'Mobile',
            'Beneficiary',
            'Bank Name',
            'Account No',
            'IFSC',
            'Closing Date',
            'Gross Income',
            'TDS (2%)',
            'Admin (5%)',
            'Net Payable'
        ]
    ];

    <?php foreach ($rows as $r): ?>
    data.push([
        <?= (int)$r['serial']; ?>,
        <?= json_encode((string)$r['member_id']); ?>,
        <?= json_encode((string)$r['name']); ?>,
        <?= json_encode((string)$r['mobile']); ?>,
        <?= json_encode((string)$r['beneficiary_name']); ?>,
        <?= json_encode((string)$r['bank_name']); ?>,
        <?= json_encode((string)$r['account_no']); ?>,
        <?= json_encode((string)$r['ifsc_code']); ?>,
        <?= json_encode(date('d M Y', strtotime($r['closing_from'])) . ' - ' . date('d M Y', strtotime($r['closing_to']))); ?>,
        <?= (float)$r['gross_income']; ?>,
        <?= (float)$r['tds']; ?>,
        <?= (float)$r['admin']; ?>,
        <?= (float)$r['net']; ?>
    ]);
    <?php endforeach; ?>

    data.push([
        '', '', 'GRAND TOTAL', '', '', '', '', '', '',
        <?= (float)$totalGross; ?>,
        <?= (float)$totalTds; ?>,
        <?= (float)$totalAdmin; ?>,
        <?= (float)$totalNet; ?>
    ]);

    const ws = XLSX.utils.aoa_to_sheet(data);
    ws['!cols'] = [
        {wch:5}, {wch:16}, {wch:22}, {wch:14}, {wch:22},
        {wch:18}, {wch:20}, {wch:14}, {wch:22},
        {wch:16}, {wch:12}, {wch:12}, {wch:16}
    ];
    ws['!merges'] = [
        { s:{r:0,c:0}, e:{r:0,c:12} },
        { s:{r:1,c:0}, e:{r:1,c:12} }
    ];

    const wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, ws, 'Payout Report');
    XLSX.writeFile(wb, 'Member_Payout_' + from + '_to_' + to + '.xlsx');
}

function downloadPDF() {
    if (!window.jspdf || !window.jspdf.jsPDF) { alert('PDF library not loaded. Please refresh.'); return; }

    const { jsPDF } = window.jspdf;
    const doc = new jsPDF({ orientation: 'landscape', unit: 'pt', format: 'a4' });

    const from   = <?= json_encode($from); ?>;
    const to     = <?= json_encode($to); ?>;
    const status = <?= json_encode($statusFilter); ?>;

    doc.setFontSize(14);
    doc.setTextColor(18, 57, 95);
    doc.text('MEMBER PAYOUT REPORT', 40, 40);

    doc.setFontSize(9);
    doc.setTextColor(100);
    doc.text('Period: ' + from + ' to ' + to + ' | Status: ' + status + ' | Generated: ' + new Date().toLocaleDateString('en-IN'), 40, 58);

    const head = [[
        '#',
        'Member ID',
        'Name',
        'Mobile',
        'Beneficiary',
        'Bank Name',
        'Account No',
        'IFSC',
        'Closing Date',
        'Gross Income',
        'TDS (2%)',
        'Admin (5%)',
        'Net Payable'
    ]];
    const body = [];

    <?php foreach ($rows as $r): ?>
    body.push([
        <?= (int)$r['serial']; ?>,
        <?= json_encode((string)$r['member_id']); ?>,
        <?= json_encode((string)$r['name']); ?>,
        <?= json_encode((string)$r['mobile']); ?>,
        <?= json_encode((string)$r['beneficiary_name']); ?>,
        <?= json_encode((string)$r['bank_name']); ?>,
        <?= json_encode((string)$r['account_no']); ?>,
        <?= json_encode((string)$r['ifsc_code']); ?>,
        <?= json_encode(date('d M Y', strtotime($r['closing_from'])) . ' - ' . date('d M Y', strtotime($r['closing_to']))); ?>,
        "Rs. <?= number_format((float)$r['gross_income'],2); ?>",
        "Rs. <?= number_format((float)$r['tds'],2); ?>",
        "Rs. <?= number_format((float)$r['admin'],2); ?>",
        "Rs. <?= number_format((float)$r['net'],2); ?>"
    ]);
    <?php endforeach; ?>

    body.push([
        '', '', 'GRAND TOTAL', '', '', '', '', '', '',
        'Rs. <?= number_format($totalGross,2); ?>',
        'Rs. <?= number_format($totalTds,2); ?>',
        'Rs. <?= number_format($totalAdmin,2); ?>',
        'Rs. <?= number_format($totalNet,2); ?>'
    ]);

    if (typeof doc.autoTable === 'function') {
        doc.autoTable({
            head: head,
            body: body,
            startY: 70,
            styles: { fontSize: 6.5, cellPadding: 3 },
            headStyles: { fillColor: [18, 57, 95], textColor: 255, fontStyle: 'bold' },
            alternateRowStyles: { fillColor: [245, 247, 250] }
        });
    }

    doc.save('Member_Payout_' + from + '_to_' + to + '.pdf');
}
</script>
</body>
</html>