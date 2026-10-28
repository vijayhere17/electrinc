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

/* ── Helpers ──────────────────────────────────────────────────────────────── */
function e($v): string {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function money($v): string {
    return '₹' . number_format((float)$v, 2);
}

function safeDate(string $date, string $default): string {
    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ? $date : $default;
}

/**
 * Resolve franchise code + name for a given receiver_type / receiver_id.
 * Tables assumed:
 *   STATE  → franchies_holder        (code=franchise_code, name=franchise_name)
 *   CITY   → city_franchies_holder   (code=franchise_code, name=franchise_name)
 *   MOBILE → mobile_franchies_holder (code=franchise_code, name=franchise_name)
 * Adjust column names below if your schema differs.
 *
 * IMPORTANT: receiver_id stored in commission_ledger may be the table's
 * numeric primary key (id), NOT franchise_code/franchies_id — franchies_id
 * is frequently NULL in franchies_holder / city_franchies_holder. So every
 * lookup below also matches on the raw `id` column as a fallback.
 *
 * 'found' tells the caller whether we actually resolved a real franchise,
 * so callers can refuse to create a payout for something we can't identify.
 */
function getFranchiseInfo(mysqli $conn, string $type, string $receiverId): array
{
    $type = strtoupper($type);

    if ($type == 'STATE') {

        $stmt = $conn->prepare("
    SELECT franchise_code, name
    FROM franchies_holder
    WHERE id = ?
       OR franchise_code = ?
       OR franchies_id = ?
    LIMIT 1
");

        $stmt->bind_param("sss", $receiverId, $receiverId, $receiverId);
        $stmt->execute();

        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return [
            'code'  => $row['franchise_code'] ?? $receiverId,
            'name'  => $row['name'] ?? 'Unknown',
            'found' => $row !== null,
        ];
    }

   if ($type == 'CITY') {

    $stmt = $conn->prepare("
        SELECT franchies_id, full_name
        FROM city_franchies_holder
        WHERE franchies_id = ?
           OR franchise_code = ?
           OR id = ?
        LIMIT 1
    ");

    $stmt->bind_param("sss", $receiverId, $receiverId, $receiverId);
    $stmt->execute();

    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return [
        'code'  => $row['franchies_id'] ?? $receiverId,
        'name'  => $row['full_name'] ?? 'Unknown',
        'found' => $row !== null,
    ];
}

    if ($type == 'MOBILE') {

        $stmt = $conn->prepare("
            SELECT mobile_franchies_id, owner_name
            FROM mobile_franchies_holder
            WHERE mobile_franchies_id = ?
               OR id = ?
            LIMIT 1
        ");

        $stmt->bind_param("ss", $receiverId, $receiverId);
        $stmt->execute();

        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return [
            'code'  => $row['mobile_franchies_id'] ?? $receiverId,
            'name'  => $row['owner_name'] ?? 'Unknown',
            'found' => $row !== null,
        ];
    }

    return [
        'code'  => $receiverId,
        'name'  => 'Unknown',
        'found' => false,
    ];
}

/* ── Input sanitisation ───────────────────────────────────────────────────── */
$from = safeDate(
    isset($_POST['from'])  ? $_POST['from']  : (isset($_GET['from'])  ? $_GET['from']  : date('Y-m-01')),
    date('Y-m-01')
);
$to = safeDate(
    isset($_POST['to'])    ? $_POST['to']    : (isset($_GET['to'])    ? $_GET['to']    : date('Y-m-d')),
    date('Y-m-d')
);

$commissionPercent = (float)(
    isset($_POST['commission']) ? $_POST['commission'] :
    (isset($_GET['commission']) ? $_GET['commission']  : 10)
);
if ($commissionPercent <= 0) $commissionPercent = 10;

$filterType = strtoupper(trim(
    isset($_POST['type']) ? $_POST['type'] : (isset($_GET['type']) ? $_GET['type'] : '')
));
$allowedTypes = ['STATE', 'CITY', 'MOBILE', ''];
if (!in_array($filterType, $allowedTypes, true)) $filterType = '';

$message      = '';
$messageClass = 'green';   // green | red


if (isset($_POST['mark_all_paid'])) {

    $stmt = $conn->prepare("
        UPDATE franchise_payout_closing
        SET status = 'Paid'
        WHERE status = 'Pending'
    ");

    $stmt->execute();
    $stmt->close();

    $message = "All payouts marked as paid.";
}


if (isset($_POST['update_status'])) {

    $closingId = (int)($_POST['payout_id'] ?? 0);
    $newStatus = $_POST['status'] ?? '';

    if (
        $closingId > 0 &&
        in_array($newStatus, ['Pending', 'Paid', 'Hold'], true)
    ) {

        $upd = $conn->prepare(
            "UPDATE franchise_payout_closing
             SET status = ?
             WHERE id = ?"
        );

        if ($upd) {

            $upd->bind_param(
                'si',
                $newStatus,
                $closingId
            );

            if ($upd->execute()) {

                $message = "Status updated successfully.";

            } else {

                $message = "Failed to update status: " . $conn->error;
                $messageClass = 'red';

            }

            $upd->close();
        }
    }
}

if (isset($_POST['generate_closing'])) {

    /* Step A — Aggregate pending commissions in date range */
$gcTypeWhere = '';
$gcTypeParam = [];
$gcTypeTypes = '';

if ($filterType != '') {
    $gcTypeWhere = ' AND UPPER(receiver_type)=? ';
    $gcTypeParam = [$filterType];
    $gcTypeTypes = 's';
}

    $gcSql = "
    SELECT
        id,
        receiver_type,
        receiver_id,
        commission_amount
    FROM commission_ledger
    WHERE payout_status='Pending'
      AND DATE(created_at) BETWEEN ? AND ?
      $gcTypeWhere
    ORDER BY id ASC
";
    $gcStmt = $conn->prepare($gcSql);
    $gcBind = array_merge([$from, $to], $gcTypeParam);
    $gcStmt->bind_param('ss' . $gcTypeTypes, ...$gcBind);
    $gcStmt->execute();
    $gcResult = $gcStmt->get_result();
    $gcStmt->close();

   $mergedRows = [];

while ($r = $gcResult->fetch_assoc()) {

    $fi = getFranchiseInfo(
        $conn,
        $r['receiver_type'],
        $r['receiver_id']
    );

    if (!$fi['found']) {
        continue;
    }

    $key = strtoupper($r['receiver_type']) . '_' . $fi['code'];

    if (!isset($mergedRows[$key])) {

        $mergedRows[$key] = [
            'receiver_type' => strtoupper($r['receiver_type']),
            'receiver_id'   => $fi['code'],
            'total_commission' => 0,
            'ledger_ids' => []
        ];

    }

    $mergedRows[$key]['total_commission'] += $r['commission_amount'];
    $mergedRows[$key]['ledger_ids'][] = $r['id'];
}

$pendingRows = [];

foreach ($mergedRows as $row) {

    $row['ledger_ids'] = implode(',', $row['ledger_ids']);

    $pendingRows[] = $row;
}

    if (empty($pendingRows)) {
        $message      = "No pending commissions found for this period.";
        $messageClass = 'red';
    } else {
        $conn->begin_transaction();
        try {
            $inserted  = 0;
            $skipped   = 0;
            $unmapped  = 0;
            $unmappedList = [];
            $totalLedgerUpdated = 0;

            /* Prepare reusable statements */
            $dupCheck = $conn->prepare(
                "SELECT id FROM franchise_payout_closing
                 WHERE franchise_code = ? AND closing_from = ? AND closing_to = ?
                 LIMIT 1"
            );
            if (!$dupCheck) {
                throw new RuntimeException("Prepare dupCheck failed: " . $conn->error);
            }
            $insClosing = $conn->prepare(
                "INSERT INTO franchise_payout_closing
                 (franchise_type, franchise_code, franchise_name,
                  closing_from, closing_to,
                  gross_commission, tds_amount, admin_charge, net_payable,
                  status, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending', NOW())"
            );
            if (!$insClosing) {
                throw new RuntimeException("Prepare insClosing failed: " . $conn->error);
            }
            /* NOTE: ledger UPDATE is prepared fresh per row (dynamic IN list) — see loop below */

            foreach ($pendingRows as $pr) {
                $rType       = strtoupper((string)$pr['receiver_type']);
                $rId         = (string)$pr['receiver_id'];
                $gross       = round((float)$pr['total_commission'], 2);
                $tds         = round($gross * 0.02, 2);
                $admin       = round($gross * 0.05, 2);
                $net         = $gross - $tds - $admin;
                $ledgerIds   = $pr['ledger_ids'];   // "1,2,3,4"
                $fi          = getFranchiseInfo($conn, $rType, $rId);

                /* Safety check: refuse to create a payout for a franchise we
                   can't actually identify in the holder tables. */
                if (!$fi['found']) {
                    $unmapped++;
                    $unmappedList[] = "$rType #$rId";
                    continue;
                }

                $fCode       = $fi['code'];
                $fName       = $fi['name'];
                $typeLabel   = match($rType) {
                    'STATE'  => 'State Franchise',
                    'CITY'   => 'City Franchise',
                    'MOBILE' => 'Mobile Franchise',
                    default  => ucfirst(strtolower($rType))
                };

                $dupCheck->bind_param('sss', $fCode, $from, $to);
$dupCheck->execute();
$dupCheck->store_result();

if ($dupCheck->num_rows > 0) {
    $dupCheck->free_result();

    // Already exists for this franchise code
    $skipped++;
    continue;
}

$dupCheck->free_result();

/*
 * Old payout may have been created before franchise lookup was fixed.
 * Repair it automatically.
 */
$repair = $conn->prepare("
    UPDATE franchise_payout_closing
    SET franchise_code = ?,
        franchise_name = ?,
        franchise_type = ?
    WHERE franchise_code = ?
       OR franchise_code = ?
");

$repair->bind_param(
    "sssss",
    $fCode,
    $fName,
    $typeLabel,
    $rId,
    $fCode
);

$repair->execute();
$repair->close();

                /* Insert closing record */
                $insClosing->bind_param(
                    'sssssdddd',
                    $typeLabel, $fCode, $fName,
                    $from, $to,
                    $gross, $tds, $admin, $net
                );
                if (!$insClosing->execute()) {
                    throw new RuntimeException("Insert closing failed: " . $conn->error);
                }
                $newClosingId = (int)$conn->insert_id;
                $inserted++;

                /* Update commission_ledger rows — dynamic IN list */
                $idList = implode(',', array_map('intval', explode(',', $ledgerIds)));
                $updSql = "UPDATE commission_ledger
                           SET payout_status = 'Paid', payout_id = ?
                           WHERE id IN ($idList)";
                $updStmt = $conn->prepare($updSql);
                if (!$updStmt) {
                    throw new RuntimeException("Prepare ledger update failed: " . $conn->error);
                }
                $updStmt->bind_param('i', $newClosingId);
                if (!$updStmt->execute()) {
                    throw new RuntimeException("Ledger update failed: " . $conn->error);
                }
                $totalLedgerUpdated += $updStmt->affected_rows;
                $updStmt->close();
            }

            $dupCheck->close();
            $insClosing->close();

            $conn->commit();

            $parts = [];
            if ($inserted > 0)  $parts[] = "$inserted closing(s) generated";
            if ($skipped > 0)   $parts[] = "$skipped duplicate(s) skipped";
            if ($unmapped > 0)  $parts[] = "$unmapped record(s) skipped (franchise not found: " . implode(', ', $unmappedList) . ")";
            $message = implode(', ', $parts) . ". Ledger rows marked Paid: $totalLedgerUpdated.";
            if ($unmapped > 0) $messageClass = 'red';

        } catch (Throwable $ex) {
            $conn->rollback();
            $message      = "Transaction failed & rolled back: " . $ex->getMessage();
            $messageClass = 'red';
        }
    }
}

/* ═══════════════════════════════════════════════════════════════════════
 * READ — franchise_payout_closing
 * ═══════════════════════════════════════════════════════════════════════ */

$sql = "
SELECT *
FROM franchise_payout_closing
WHERE closing_from = ?
  AND closing_to = ?
";

$params = [$from, $to];
$types = "ss";

if ($filterType != '') {

    $labelMap = [
        'STATE'  => 'State Franchise',
        'CITY'   => 'City Franchise',
        'MOBILE' => 'Mobile Franchise'
    ];

    $sql .= " AND franchise_type = ? ";

    $params[] = $labelMap[$filterType];
    $types .= "s";
}

$statusFilter = $_GET['status_filter'] ?? 'Pending';

if (!in_array($statusFilter, ['Pending','Paid','Hold','All'])) {
    $statusFilter = 'Pending';
}

if ($statusFilter !== 'All') {
    $sql .= " AND status = ? ";
    $params[] = $statusFilter;
    $types .= "s";
}

$sql .= " ORDER BY id DESC ";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();

$result = $stmt->get_result();
$stmt->close();

$rows = [];

$totalGross = 0;
$totalTds = 0;
$totalAdmin = 0;
$totalNet = 0;

$i = 1;

while ($r = $result->fetch_assoc()) {

    /* Flag rows whose franchise_name/code look unresolved (legacy bad data
       created before the getFranchiseInfo lookup fix, or genuinely orphaned
       commission entries) so the admin can spot them at a glance. */
    $looksUnresolved = ($r['franchise_name'] === 'Unknown' || $r['franchise_name'] === '' || $r['franchise_name'] === null);

    $rows[] = [

        'hash_id' => $r['id'],

        'serial' => $i++,

        'type' => $r['franchise_type'],

        'raw_type' => strtoupper(
            str_replace(' Franchise', '', $r['franchise_type'])
        ),

        'receiver_id' => $r['franchise_code'],

        'franchise_name' => $r['franchise_name'],

        'unresolved' => $looksUnresolved,

        'period_from' => $r['closing_from'],

        'period_to' => $r['closing_to'],

        'gross' => (float)$r['gross_commission'],

        'tds' => (float)$r['tds_amount'],

        'admin' => (float)$r['admin_charge'],

        'net' => (float)$r['net_payable'],

        'status' => $r['status']
    ];

    $totalGross += (float)$r['gross_commission'];
    $totalTds += (float)$r['tds_amount'];
    $totalAdmin += (float)$r['admin_charge'];
    $totalNet += (float)$r['net_payable'];
}

/* ── Count pending commissions to show "Generate Closing" readiness ─────── */
$pendingCountSql = "
    SELECT COUNT(*) AS cnt
    FROM commission_ledger
    WHERE payout_status = 'Pending'
      AND DATE(created_at) BETWEEN ? AND ?
";
if ($filterType !== '') {
    $pendingCountSql .= " AND UPPER(receiver_type) = ?";
}
$pcStmt = $conn->prepare($pendingCountSql);
if ($filterType !== '') {
    $pcStmt->bind_param('sss', $from, $to, $filterType);
} else {
    $pcStmt->bind_param('ss', $from, $to);
}
$pcStmt->execute();
$pendingCount = (int)$pcStmt->get_result()->fetch_assoc()['cnt'];
$pcStmt->close();

/* ── Count how many EXISTING closing rows still look unresolved (legacy) ── */
$unresolvedCountSql = "
    SELECT COUNT(*) AS cnt
    FROM franchise_payout_closing
    WHERE franchise_name = 'Unknown' OR franchise_name = '' OR franchise_name IS NULL
";
$ucRes = $conn->query($unresolvedCountSql);
$unresolvedCount = $ucRes ? (int)$ucRes->fetch_assoc()['cnt'] : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Franchies Payout Report</title>
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

    <!-- Page Header -->
    <div class="mt-5 flex items-center gap-3 mb-6">
        <div class="w-[36px] h-[36px] rounded-[8px] bg-[#f5eee3] flex items-center justify-center shrink-0">
            <i data-lucide="wallet" class="w-[18px] h-[18px] text-[#cd9737]"></i>
        </div>
        <h2 class="whitespace-nowrap text-[20px] font-normal text-[#40352a]"
            style="font-family: Georgia, 'Times New Roman', serif;">
            FRANCHIES PAYOUT REPORT
        </h2>
        <div class="w-full h-[2px] bg-[#d1a14b]"></div>
    </div>

    <!-- Alert -->
    <?php if ($message !== ''): ?>
        <div class="mb-5 rounded-lg <?= $messageClass === 'red' ? 'bg-red-100 text-red-700' : 'bg-green-100 text-green-700'; ?> px-5 py-3 text-sm">
            <?= e($message); ?>
        </div>
    <?php endif; ?>

    <?php if ($unresolvedCount > 0): ?>
        <div class="mb-5 rounded-lg bg-amber-100 text-amber-800 px-5 py-3 text-sm flex items-center gap-2">
            <i data-lucide="alert-triangle" class="w-4 h-4 shrink-0"></i>
            <span>
                <strong><?= $unresolvedCount; ?></strong> existing payout closing record(s) in the database still show "Unknown" franchise name / a raw ID as the code.
                These were created before the franchise lookup was fixed and need a one-time data repair — ask me for the backfill script if you'd like these corrected.
            </span>
        </div>
    <?php endif; ?>

    <!-- Filter Card -->
    <div class="bg-white rounded-xl shadow border p-5 mb-6">
        <form method="GET" class="flex flex-wrap items-end gap-4">
            <div>
                <label class="block mb-1 font-semibold text-sm text-gray-700">From Date</label>
                <input type="date" name="from" value="<?= e($from); ?>"
                       class="border rounded-lg px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#12395f]">
            </div>
            <div>
                <label class="block mb-1 font-semibold text-sm text-gray-700">To Date</label>
                <input type="date" name="to" value="<?= e($to); ?>"
                       class="border rounded-lg px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#12395f]">
            </div>
            <div>
                <label class="block mb-1 font-semibold text-sm text-gray-700">Commission %</label>
                <input type="number" step="0.01" name="commission" value="<?= e($commissionPercent); ?>"
                       class="border rounded-lg px-4 py-2 w-[130px] text-sm focus:outline-none focus:ring-2 focus:ring-[#12395f]">
            </div>
            <div>
    <label class="block mb-1 font-semibold text-sm text-gray-700">
        Franchise Type
    </label>

    <select name="type"
            class="border rounded-lg px-4 py-2 text-sm bg-white min-w-[160px]">
        <option value="">All Types</option>
        <option value="STATE" <?= $filterType==='STATE'?'selected':''; ?>>
            State Franchise
        </option>
        <option value="CITY" <?= $filterType==='CITY'?'selected':''; ?>>
            City Franchise
        </option>
        <option value="MOBILE" <?= $filterType==='MOBILE'?'selected':''; ?>>
            Mobile Franchise
        </option>
    </select>
</div>

<div>
    <label class="block mb-1 font-semibold text-sm text-gray-700">
        Status
    </label>

    <select name="status_filter"
            class="border rounded-lg px-4 py-2 text-sm bg-white min-w-[140px]">
        <option value="Pending" <?= $statusFilter==='Pending'?'selected':''; ?>>
            Pending
        </option>
        <option value="Paid" <?= $statusFilter==='Paid'?'selected':''; ?>>
            Paid
        </option>
        <option value="Hold" <?= $statusFilter==='Hold'?'selected':''; ?>>
            Hold
        </option>
        <option value="All" <?= $statusFilter==='All'?'selected':''; ?>>
            All
        </option>
    </select>
</div>
            <button type="submit"
                    class="bg-[#12395f] text-white px-5 py-2 rounded-lg text-sm flex items-center gap-2 hover:bg-[#0e2d4d] transition">
                <i data-lucide="search" class="w-4 h-4"></i>
                Search Report
            </button>
            <a href="franchies-payout-report.php"
               class="bg-gray-200 text-gray-700 px-5 py-2 rounded-lg text-sm flex items-center gap-2 hover:bg-gray-300 transition">
                <i data-lucide="x" class="w-4 h-4"></i>
                Clear
            </a>
        </form>
        
        <?php
if ($filterType != '') {
    $rows = array_filter($rows, function($row) use ($filterType) {
        return strtoupper($row['raw_type']) == strtoupper($filterType);
    });
}
?>

        <!-- Summary Badges -->
        <?php if (!empty($rows)): ?>
        <div class="mt-4 flex flex-wrap gap-3 text-sm">
            <span class="bg-blue-50 text-blue-700 border border-blue-200 px-4 py-2 rounded-lg font-semibold">
                Total Records: <?= count($rows); ?>
            </span>
            <span class="bg-green-50 text-green-700 border border-green-200 px-4 py-2 rounded-lg font-semibold">
                Gross: <?= money($totalGross); ?>
            </span>
            <span class="bg-red-50 text-red-700 border border-red-200 px-4 py-2 rounded-lg font-semibold">
                TDS (2%): <?= money($totalTds); ?>
            </span>
            <span class="bg-orange-50 text-orange-700 border border-orange-200 px-4 py-2 rounded-lg font-semibold">
                Admin (5%): <?= money($totalAdmin); ?>
            </span>
            <span class="bg-emerald-50 text-emerald-700 border border-emerald-200 px-4 py-2 rounded-lg font-semibold">
                Net Payable: <?= money($totalNet); ?>
            </span>
        </div>
        <?php endif; ?>

        <!-- Pending commissions notice + Generate Closing button -->
        <?php if ($pendingCount > 0): ?>
        <div class="mt-4 flex items-center gap-4 p-3 bg-amber-50 border border-amber-200 rounded-lg">
            <i data-lucide="alert-circle" class="w-5 h-5 text-amber-600 shrink-0"></i>
            <p class="text-sm text-amber-800 font-medium flex-1">
                <strong><?= $pendingCount; ?></strong> pending commission record(s) found for this period. Click <strong>Generate Closing</strong> to create payout entries.
                Records whose franchise can't be identified will be skipped and reported, not paid out blindly.
            </p>
            <form method="POST">
                <input type="hidden" name="from"       value="<?= e($from); ?>">
                <input type="hidden" name="to"         value="<?= e($to); ?>">
                <input type="hidden" name="commission" value="<?= e($commissionPercent); ?>">
                <input type="hidden" name="type"       value="<?= e($filterType); ?>">
                <button type="submit" name="generate_closing" value="1"
                        onclick="return confirm('Generate closing for this period? This will mark pending commissions as Paid and cannot be undone.')"
                        class="bg-amber-600 hover:bg-amber-700 text-white px-5 py-2 rounded-lg text-sm flex items-center gap-2 transition whitespace-nowrap">
                    <i data-lucide="zap" class="w-4 h-4"></i>
                    Generate Closing
                </button>
            </form>
        </div>
        <?php endif; ?>
    </div>

    <!-- Table Card -->
    <div class="bg-white rounded-xl shadow border overflow-hidden">

        <!-- Toolbar -->
        <div class="flex items-center justify-between px-5 py-3 border-b bg-gray-50 flex-wrap gap-3">
            <p class="text-sm text-gray-600 font-semibold">
                <?= !empty($rows) ? count($rows) . ' record(s) found' : 'No records'; ?>
                <?php if ($filterType !== ''): ?>
                    &mdash; Filtered:
                    <span class="text-[#12395f]">
                        <?php
                        if ($filterType === 'STATE')       echo 'State Franchise';
                        elseif ($filterType === 'CITY')    echo 'City Franchise';
                        elseif ($filterType === 'MOBILE')  echo 'Mobile Franchise';
                        ?>
                    </span>
                <?php endif; ?>
            </p>

            <?php if (!empty($rows)): ?>
            <div class="flex gap-2 flex-wrap">
                <button onclick="downloadExcel()"
                        class="flex items-center gap-2 bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg text-sm transition">
                    <i data-lucide="file-spreadsheet" class="w-4 h-4"></i>
                    Download Excel
                </button>
                <button onclick="downloadPDF()"
                        class="flex items-center gap-2 bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg text-sm transition">
                    <i data-lucide="file-text" class="w-4 h-4"></i>
                    Download PDF
                </button>
                <form method="post" class="inline">
    <input type="hidden" name="mark_all_paid" value="1">
    <input type="hidden" name="from" value="<?= e($from); ?>">
    <input type="hidden" name="to" value="<?= e($to); ?>">
    <input type="hidden" name="type" value="<?= e($filterType); ?>">

    <button type="submit"
            class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm">
        Mark All Paid
    </button>
</form>
            </div>
            <?php endif; ?>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm" id="payoutTable">
                <thead class="bg-[#12395f] text-white">
                    <tr>
                        <th class="px-4 py-4 text-left">#</th>
                        <th class="px-4 py-4 text-left">Franchise Type</th>
                        <th class="px-4 py-4 text-left">Franchise Code</th>
                        <th class="px-4 py-4 text-left">Franchise Name</th>
                        <th class="px-4 py-4 text-left">Closing Date</th>
                        <th class="px-4 py-4 text-left">Gross Commission</th>
                        <th class="px-4 py-4 text-left">TDS (2%)</th>
                        <th class="px-4 py-4 text-left">Admin (5%)</th>
                        <th class="px-4 py-4 text-left">Net Payable</th>
                        <th class="px-4 py-4 text-left">Status</th>
                        <th class="px-4 py-4 text-left">Action</th>
                    </tr>
                </thead>
                <tbody>

                <?php if (!empty($rows)): ?>
                    <?php foreach ($rows as $row): ?>
                    <tr class="border-b hover:bg-gray-50 transition <?= $row['unresolved'] ? 'bg-red-50/40' : ''; ?>">
                        <td class="px-4 py-3 text-gray-600"><?= (int)$row['serial']; ?></td>

                        <td class="px-4 py-3">
                            <?php
                            $badgeClass = 'bg-gray-100 text-gray-700 border border-gray-200';
                            if ($row['raw_type'] === 'STATE')       $badgeClass = 'bg-purple-100 text-purple-700 border border-purple-200';
                            elseif ($row['raw_type'] === 'CITY')    $badgeClass = 'bg-blue-100 text-blue-700 border border-blue-200';
                            elseif ($row['raw_type'] === 'MOBILE')  $badgeClass = 'bg-orange-100 text-orange-700 border border-orange-200';
                            ?>
                            <span class="<?= $badgeClass; ?> px-2 py-1 rounded-full text-xs font-semibold whitespace-nowrap">
                                <?= e($row['type']); ?>
                            </span>
                        </td>

                        <td class="px-4 py-3 font-semibold text-blue-600">
                            <?= e($row['receiver_id']); ?>
                            <?php if ($row['unresolved']): ?>
                                <span title="Franchise not found in holder table — this code/name may be a raw ID left over from before the lookup fix." class="ml-1 text-red-500 cursor-help">&#9888;</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-3 text-gray-700"><?= e($row['franchise_name']); ?></td>

                        <td class="px-4 py-3 text-gray-600 whitespace-nowrap">
                            <?= e(date('d-m-Y', strtotime($row['period_from']))); ?>
                            <span class="text-gray-400 mx-1">→</span>
                            <?= e(date('d-m-Y', strtotime($row['period_to']))); ?>
                        </td>

                        <td class="px-4 py-3 font-bold text-green-700"><?= money($row['gross']); ?></td>
                        <td class="px-4 py-3 text-red-500"><?= money($row['tds']); ?></td>
                        <td class="px-4 py-3 text-red-500"><?= money($row['admin']); ?></td>
                        <td class="px-4 py-3 font-bold text-emerald-700"><?= money($row['net']); ?></td>

                       <td class="px-4 py-3">
    <?php if ($row['status'] === 'Not Closed'): ?>
        <span class="bg-gray-100 text-gray-700 border border-gray-200 px-3 py-1 rounded-full text-xs font-semibold">
            Not Closed
        </span>

    <?php elseif ($row['status'] === 'Paid'): ?>
        <span class="bg-green-100 text-green-700 border border-green-200 px-3 py-1 rounded-full text-xs font-semibold">
            Paid
        </span>

    <?php elseif ($row['status'] === 'Hold'): ?>
        <span class="bg-red-100 text-red-700 border border-red-200 px-3 py-1 rounded-full text-xs font-semibold">
            Hold
        </span>

    <?php else: ?>
        <span class="bg-yellow-100 text-yellow-700 border border-yellow-200 px-3 py-1 rounded-full text-xs font-semibold">
            Pending
        </span>
    <?php endif; ?>
</td>

                        <td class="px-4 py-3">
                            <!-- Status update writes to franchise_payout_closing.status -->
                            <form method="POST" class="flex gap-2 items-center">
                                <input type="hidden" name="payout_id"  value="<?= (int)$row['hash_id']; ?>">
                                <input type="hidden" name="from"       value="<?= e($from); ?>">
                                <input type="hidden" name="to"         value="<?= e($to); ?>">
                                <input type="hidden" name="commission" value="<?= e($commissionPercent); ?>">
                                <input type="hidden" name="type"       value="<?= e($filterType); ?>">
                                <select name="status"
                                        class="border rounded-lg px-2 py-1 text-xs focus:outline-none focus:ring-1 focus:ring-[#12395f]">
                                    <option value="Pending" <?= $row['status'] === 'Pending' ? 'selected' : ''; ?>>Pending</option>
                                    <option value="Paid"    <?= $row['status'] === 'Paid'    ? 'selected' : ''; ?>>Paid</option>
                                    <option value="Hold"    <?= $row['status'] === 'Hold'    ? 'selected' : ''; ?>>Hold</option>
                                </select>
                                <button type="submit" name="update_status" value="1"
                                        class="bg-[#12395f] hover:bg-[#0e2d4d] text-white px-3 py-1 rounded-lg text-xs transition">
                                    Update
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>

                    <!-- Totals Row -->
                    <tr class="bg-[#f0f4f8] font-bold text-sm border-t-2 border-[#12395f]">
                        <td colspan="5" class="px-4 py-4 text-right text-gray-700 uppercase tracking-wide">Grand Total</td>
                        <td class="px-4 py-4 text-green-700"><?= money($totalGross); ?></td>
                        <td class="px-4 py-4 text-red-600"><?= money($totalTds); ?></td>
                        <td class="px-4 py-4 text-red-600"><?= money($totalAdmin); ?></td>
                        <td class="px-4 py-4 text-emerald-700"><?= money($totalNet); ?></td>
                        <td colspan="2"></td>
                    </tr>

                <?php else: ?>
                    <tr>
                        <td colspan="11" class="text-center py-16 text-gray-400">
                            <div class="flex flex-col items-center gap-3">
                                <i data-lucide="inbox" class="w-12 h-12 text-gray-300"></i>
                                <p class="text-base font-medium">No payout closings found</p>
                                <p class="text-xs">
                                    Period: <?= e(date('d M Y', strtotime($from))); ?>
                                    to <?= e(date('d M Y', strtotime($to))); ?>
                                    <?php if ($filterType !== ''): ?>
                                        &mdash; Type: <?= e($filterType); ?>
                                    <?php endif; ?>
                                </p>
                                <?php if ($pendingCount > 0): ?>
                                    <p class="text-xs text-amber-600 font-medium">
                                        <?= $pendingCount; ?> pending commission(s) available — use <strong>Generate Closing</strong> above.
                                    </p>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>

                </tbody>
            </table>
        </div>
    </div>

</div>
</main>
</div>

<script>
document.addEventListener("DOMContentLoaded", function () {
    if (typeof lucide !== "undefined") lucide.createIcons();
});

function downloadExcel() {
    const from = "<?= e($from); ?>";
    const to   = "<?= e($to); ?>";
    const type = "<?= e($filterType !== '' ? $filterType : 'All'); ?>";

    const data = [
        ["Franchies Payout Report"],
        ["Period: " + from + " to " + to + " | Type: " + type],
        [],
        ["#","Franchise Type","Franchise Code","Franchise Name","Period",
         "Gross Commission","TDS (2%)","Admin (5%)","Net Payable","Status"]
    ];

    <?php foreach ($rows as $r): ?>
    data.push([
        <?= (int)$r['serial']; ?>,
        "<?= e($r['type']); ?>",
        "<?= e($r['receiver_id']); ?>",
        "<?= e($r['franchise_name']); ?>",
        "<?= e(date('d-m-Y', strtotime($r['period_from']))); ?> to <?= e(date('d-m-Y', strtotime($r['period_to']))); ?>",
        <?= (float)$r['gross']; ?>,
        <?= (float)$r['tds']; ?>,
        <?= (float)$r['admin']; ?>,
        <?= (float)$r['net']; ?>,
        "<?= e($r['status']); ?>"
    ]);
    <?php endforeach; ?>

    data.push(["","","","","GRAND TOTAL",
        <?= (float)$totalGross; ?>,
        <?= (float)$totalTds; ?>,
        <?= (float)$totalAdmin; ?>,
        <?= (float)$totalNet; ?>, ""
    ]);

    const ws = XLSX.utils.aoa_to_sheet(data);
    ws['!cols'] = [
        {wch:4},{wch:18},{wch:14},{wch:22},{wch:26},
        {wch:16},{wch:12},{wch:12},{wch:14},{wch:10}
    ];
    ws['!merges'] = [
        {s:{r:0,c:0},e:{r:0,c:9}},
        {s:{r:1,c:0},e:{r:1,c:9}}
    ];
    const wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, ws, "Payout Report");
    XLSX.writeFile(wb, "Franchies_Payout_" + from + "_to_" + to + ".xlsx");
}

function downloadPDF() {
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF({ orientation: 'landscape', unit: 'pt', format: 'a4' });

    const from = "<?= e($from); ?>";
    const to   = "<?= e($to); ?>";
    const type = "<?= e($filterType !== '' ? $filterType : 'All'); ?>";

    doc.setFontSize(14);
    doc.setTextColor(18, 57, 95);
    doc.text("FRANCHIES PAYOUT REPORT", 40, 40);

    doc.setFontSize(9);
    doc.setTextColor(100);
    doc.text("Period: " + from + " to " + to +
             "   |   Type: " + type +
             "   |   Generated: " + new Date().toLocaleDateString('en-IN'), 40, 58);

    const head = [["#","Franchise Type","Code","Franchise Name","Period",
                "Gross","TDS","Admin","Net Payable","Status"]];
    const body = [];

    <?php foreach ($rows as $r): ?>
    body.push([
    <?= (int)$r['serial']; ?>,
    "<?= e($r['type']); ?>",
    "<?= e($r['receiver_id']); ?>",
    "<?= e($r['franchise_name']); ?>",
    "<?= e(date('d-m-Y', strtotime($r['period_from']))); ?> to <?= e(date('d-m-Y', strtotime($r['period_to']))); ?>",
    "<?= number_format($r['gross'],2); ?>",
    "<?= number_format($r['tds'],2); ?>",
    "<?= number_format($r['admin'],2); ?>",
    "<?= number_format($r['net'],2); ?>",
    "<?= e($r['status']); ?>"
]);
    <?php endforeach; ?>

    body.push(["","","","","GRAND TOTAL",
    "<?= number_format($totalGross,2); ?>",
    "<?= number_format($totalTds,2); ?>",
    "<?= number_format($totalAdmin,2); ?>",
    "<?= number_format($totalNet,2); ?>", ""
]);

    doc.autoTable({
        head: head,
        body: body,
        startY: 70,
        styles: { fontSize: 8, cellPadding: 5 },
        headStyles: { fillColor: [18, 57, 95], textColor: 255, fontStyle: 'bold' },
        alternateRowStyles: { fillColor: [245, 247, 250] },
        columnStyles: {
            0: { cellWidth: 22 },
            1: { cellWidth: 68 },
            2: { cellWidth: 52 },
            3: { cellWidth: 68 },
            4: { cellWidth: 70 },
            5: { cellWidth: 55, halign: 'right' },
            6: { cellWidth: 45, halign: 'right' },
            7: { cellWidth: 45, halign: 'right' },
            8: { cellWidth: 60, halign: 'right' },
            9: { cellWidth: 40 }
        },
        didParseCell: function(data) {
            if (data.row.index === body.length - 1) {
                data.cell.styles.fontStyle = 'bold';
                data.cell.styles.fillColor = [240, 244, 248];
            }
            if (data.column.index === 9 && data.section === 'body') {
                const v = data.cell.raw;
                if (v === 'Paid')    data.cell.styles.textColor = [22,  163, 74];
                if (v === 'Hold')    data.cell.styles.textColor = [220, 38,  38];
                if (v === 'Pending') data.cell.styles.textColor = [161, 98,  7];
            }
        }
    });

    doc.save("Franchies_Payout_" + from + "_to_" + to + ".pdf");
}
</script>

</body>
</html>