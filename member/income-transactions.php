<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['customer_id']) && !isset($_SESSION['member_id'])) {
    header("Location: login.php");
    exit();
}
$memberName = $_SESSION['full_name'] ?? "Member";
$customerId = $_SESSION['customer_id'] ?? $_SESSION['member_id'];

include __DIR__ . '/config/db.php';
if (!isset($conn) || !($conn instanceof mysqli)) {
    die("Database connection failed.");
}
$conn->set_charset("utf8mb4");
require_once __DIR__ . '/../includes/income_common.php';

$hasLedger = income_table_exists($conn, 'member_income_ledger');

$fType   = trim($_GET['type'] ?? '');
$fStatus = trim($_GET['status'] ?? '');
$fFrom   = trim($_GET['date_from'] ?? '');
$fTo     = trim($_GET['date_to'] ?? '');

$types = [];
$totals = ['gross' => 0.0, 'paid' => 0.0, 'flushed' => 0.0, 'capped' => 0.0];
$rows = [];

if ($hasLedger) {
    $tr = $conn->query("SELECT DISTINCT income_type FROM member_income_ledger WHERE customer_id = '" . $conn->real_escape_string($customerId) . "' ORDER BY income_type");
    while ($tr && ($x = $tr->fetch_assoc())) $types[] = $x['income_type'];

    $where = ["customer_id = ?"];
    $params = [$customerId];
    $tps = "s";
    if ($fType !== '')   { $where[] = "income_type = ?"; $params[] = $fType;   $tps .= "s"; }
    if ($fStatus !== '') { $where[] = "status = ?";      $params[] = $fStatus; $tps .= "s"; }
    if ($fFrom !== '')   { $where[] = "report_date >= ?"; $params[] = $fFrom;  $tps .= "s"; }
    if ($fTo !== '')     { $where[] = "report_date <= ?"; $params[] = $fTo;    $tps .= "s"; }
    $ws = implode(' AND ', $where);

    $stmt = $conn->prepare("SELECT id, created_at, report_date, income_type, source, source_ref, from_member_id, from_member_name,
                                   base_amount, percentage, gross_amount, paid_amount, flushed_amount,
                                   fund_amount, withdrawable_amount, wallet_balance_after, cycle_key, counts_to_cap, status
                            FROM member_income_ledger WHERE $ws ORDER BY id DESC");
    if ($stmt) {
        $stmt->bind_param($tps, ...$params);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($r = $res->fetch_assoc()) {
            $totals['gross']   += (float)$r['gross_amount'];
            $totals['paid']    += (float)$r['paid_amount'];
            $totals['flushed'] += (float)$r['flushed_amount'];
            if ((int)$r['counts_to_cap'] === 1) $totals['capped'] += (float)$r['paid_amount'];
            $rows[] = $r;
        }
        $stmt->close();
    }
}

$withdrawable = 0.0;
$ws = $conn->prepare("SELECT wallet_balance FROM signup_users WHERE customer_id = ? LIMIT 1");
if ($ws) {
    $ws->bind_param("s", $customerId);
    $ws->execute();
    $withdrawable = (float)($ws->get_result()->fetch_assoc()['wallet_balance'] ?? 0);
    $ws->close();
}
function m($n) { return '₹' . number_format((float)$n, 2); }
function txnRef($id) { return 'TXN-' . str_pad((string)$id, 8, '0', STR_PAD_LEFT); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Income Transactions</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        body { background:#f3f3f3; }
        @media (min-width: 1024px) { .main-wrap { margin-left: 256px; } }
        .data-row:hover { background:#fdf6ea !important; }
        .fw select, .fw input { border:1px solid #e2d8c8; border-radius:8px; padding:7px 10px; font-size:13px; background:#fff; }
    </style>
</head>
<body>
<?php include __DIR__ . '/includes/sidebar.php'; ?>
<div class="main-wrap min-h-screen">
    <?php include __DIR__ . '/includes/navbar.php'; ?>
    <div class="px-3 sm:px-5 py-3">

        <div class="mt-4 sm:mt-5 flex items-center gap-3">
            <div class="w-[34px] h-[34px] rounded-[8px] bg-[#f5eee3] flex items-center justify-center shrink-0">
                <i data-lucide="receipt-text" class="w-[17px] h-[17px] text-[#cd9737]"></i>
            </div>
            <h2 class="whitespace-nowrap text-[20px] font-normal text-[#40352a]" style="font-family: Georgia, 'Times New Roman', serif;">INCOME TRANSACTIONS</h2>
            <div class="w-full h-[2px] bg-[#d1a14b]"></div>
        </div>

        <div class="mt-4 grid grid-cols-2 lg:grid-cols-5 gap-3">
            <div class="bg-white rounded-2xl border border-[#e2d8c8] px-4 py-3"><p class="text-[10px] uppercase tracking-widest text-[#9e8f7e] font-semibold">Total Income</p><p class="mt-1 text-[17px] font-semibold"><?php echo m($totals['paid']); ?></p></div>
            <div class="bg-white rounded-2xl border border-[#e2d8c8] px-4 py-3"><p class="text-[10px] uppercase tracking-widest text-[#9e8f7e] font-semibold">Capped Income</p><p class="mt-1 text-[17px] font-semibold"><?php echo m($totals['capped']); ?></p></div>
            <div class="bg-white rounded-2xl border border-[#e2d8c8] px-4 py-3"><p class="text-[10px] uppercase tracking-widest text-[#9e8f7e] font-semibold">Flushed Income</p><p class="mt-1 text-[17px] font-semibold text-[#b91c1c]"><?php echo m($totals['flushed']); ?></p></div>
            <div class="bg-white rounded-2xl border border-[#e2d8c8] px-4 py-3"><p class="text-[10px] uppercase tracking-widest text-[#9e8f7e] font-semibold">Gross Generated</p><p class="mt-1 text-[17px] font-semibold"><?php echo m($totals['gross']); ?></p></div>
            <div class="bg-[#1e7a48] rounded-2xl px-4 py-3"><p class="text-[10px] uppercase tracking-widest text-[#bfe3c9] font-semibold">Withdrawable Balance</p><p class="mt-1 text-[17px] font-semibold text-white"><?php echo m($withdrawable); ?></p></div>
        </div>

        <form method="get" class="fw mt-4 flex flex-wrap items-end gap-3">
            <div>
                <label class="block text-[11px] text-[#9e8f7e] mb-1">Type</label>
                <select name="type">
                    <option value="">All types</option>
                    <?php foreach ($types as $t): ?>
                        <option value="<?php echo htmlspecialchars($t); ?>" <?php echo $fType === $t ? 'selected' : ''; ?>><?php echo htmlspecialchars($t); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-[11px] text-[#9e8f7e] mb-1">Status</label>
                <select name="status">
                    <?php foreach (['' => 'All', 'Credit' => 'Credit', 'Partial' => 'Partial', 'Flushed' => 'Flushed'] as $sv => $sl): ?>
                        <option value="<?php echo $sv; ?>" <?php echo $fStatus === $sv ? 'selected' : ''; ?>><?php echo $sl; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div><label class="block text-[11px] text-[#9e8f7e] mb-1">From</label><input type="date" name="date_from" value="<?php echo htmlspecialchars($fFrom); ?>"></div>
            <div><label class="block text-[11px] text-[#9e8f7e] mb-1">To</label><input type="date" name="date_to" value="<?php echo htmlspecialchars($fTo); ?>"></div>
            <button class="rounded-lg bg-[#c79435] px-5 py-2 text-white text-[13px] hover:bg-[#b3822c]">Filter</button>
            <a href="income-transactions.php" class="rounded-lg border border-[#e2d8c8] bg-white px-4 py-2 text-[13px] text-[#6b5d48]">Reset</a>
        </form>

        <div class="mt-4 bg-white rounded-2xl border border-[#e2d8c8] overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-[13px]">
                    <thead class="bg-[#faf5ec] text-[#9e8f7e] text-[11px] uppercase tracking-wider">
                        <tr>
                            <th class="text-left px-4 py-3">Ref ID</th>
                            <th class="text-left px-4 py-3">Date / Time</th>
                            <th class="text-left px-4 py-3">Income Type</th>
                            <th class="text-left px-4 py-3">Source / Member</th>
                            <th class="text-right px-4 py-3">Business</th>
                            <th class="text-right px-4 py-3">%</th>
                            <th class="text-right px-4 py-3">Income</th>
                            <th class="text-right px-4 py-3">Paid</th>
                            <th class="text-right px-4 py-3">Flushed</th>
                            <th class="text-left px-4 py-3">Cycle</th>
                            <th class="text-left px-4 py-3">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#f0e9db]">
                        <?php if (!$hasLedger || empty($rows)): ?>
                            <tr><td colspan="11" class="px-4 py-10 text-center text-[#b3a78f]">
                                <i data-lucide="inbox" class="w-10 h-10 opacity-40 mx-auto mb-2"></i><div>No income transactions.</div>
                            </td></tr>
                        <?php else: foreach ($rows as $r): ?>
                            <tr class="data-row">
                                <td class="px-4 py-3 font-mono text-[12px]"><?php echo txnRef($r['id']); ?></td>
                                <td class="px-4 py-3 whitespace-nowrap"><?php echo htmlspecialchars((string)$r['created_at']); ?></td>
                                <td class="px-4 py-3 font-medium"><?php echo htmlspecialchars($r['income_type']); ?></td>
                                <td class="px-4 py-3">
                                    <?php
                                    $src = $r['from_member_id'] ?: $r['source_ref'] ?: $r['source'];
                                    echo htmlspecialchars((string)$src);
                                    if ($r['from_member_name']) echo '<span class="text-[#a99c85]"> · ' . htmlspecialchars($r['from_member_name']) . '</span>';
                                    ?>
                                </td>
                                <td class="px-4 py-3 text-right"><?php echo m($r['base_amount']); ?></td>
                                <td class="px-4 py-3 text-right"><?php echo number_format((float)$r['percentage'], 2); ?></td>
                                <td class="px-4 py-3 text-right"><?php echo m($r['gross_amount']); ?></td>
                                <td class="px-4 py-3 text-right font-semibold"><?php echo m($r['paid_amount']); ?></td>
                                <td class="px-4 py-3 text-right text-[#b91c1c]"><?php echo m($r['flushed_amount']); ?></td>
                                <td class="px-4 py-3 text-[#8a7c68]"><?php echo htmlspecialchars((string)$r['cycle_key']); ?></td>
                                <td class="px-4 py-3">
                                    <?php $st = $r['status']; $cls = $st === 'Credit' ? 'bg-[#e8f7ee] text-[#1e7a48]' : ($st === 'Flushed' ? 'bg-[#fde8e8] text-[#9b1c1c]' : 'bg-[#fef3e0] text-[#b07d00]'); ?>
                                    <span class="rounded-full <?php echo $cls; ?> px-2 py-0.5 text-[11px]"><?php echo htmlspecialchars($st); ?></span>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<script>lucide.createIcons();</script>
</body>
</html>
