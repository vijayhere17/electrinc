<?php
session_start();
include __DIR__ . '/config/db.php';
if (!isset($conn) || !($conn instanceof mysqli)) {
    die("Database connection failed.");
}
$conn->set_charset("utf8mb4");
require_once __DIR__ . '/../includes/income_common.php';

$adminName = (string)($_SESSION['full_name'] ?? 'Admin');
$adminId   = (string)($_SESSION['member_id'] ?? $_SESSION['user_id'] ?? '');

$msg = ''; $err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rid = (int)($_POST['request_id'] ?? 0);
    $action = (string)($_POST['action'] ?? '');
    $anote = trim((string)($_POST['admin_note'] ?? ''));
    $r = withdrawal_process($conn, $rid, $action, $adminName, $adminId, $anote);
    if (!empty($r['ok'])) {
        $msg = "Request #$rid → " . ($r['status'] ?? 'updated') . (isset($r['refunded']) ? " (₹" . number_format((float)$r['refunded'], 2) . " refunded to wallet)" : '');
    } else {
        $err = $r['message'] ?? 'Could not process request.';
    }
}

$filter = trim($_GET['status'] ?? 'Pending');
$valid = ['Pending', 'Approved', 'Paid', 'Rejected', 'All'];
if (!in_array($filter, $valid, true)) $filter = 'Pending';

$where = $filter === 'All' ? '1=1' : "status = '" . $conn->real_escape_string($filter) . "'";

$sum = ['pending' => 0, 'pending_net' => 0.0, 'paid_net' => 0.0];
$r = $conn->query("SELECT
    SUM(status='Pending') p,
    COALESCE(SUM(CASE WHEN status='Pending' THEN net_amount ELSE 0 END),0) pn,
    COALESCE(SUM(CASE WHEN status='Paid' THEN net_amount ELSE 0 END),0) paid
    FROM withdrawal_requests")->fetch_assoc();
if ($r) $sum = ['pending' => (int)$r['p'], 'pending_net' => (float)$r['pn'], 'paid_net' => (float)$r['paid']];

$rows = [];
$res = $conn->query("SELECT * FROM withdrawal_requests WHERE $where ORDER BY id DESC LIMIT 300");
while ($res && ($x = $res->fetch_assoc())) $rows[] = $x;

function money($n) { return '₹' . number_format((float)$n, 2); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Withdrawal Requests</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body class="m-0 bg-[#f3f4f6] font-sans">
<div class="ml-64 min-h-screen w-[calc(100%-16rem)]">
    <?php include __DIR__ . '/includes/sidebar.php'; ?>
    <div class="flex-1 min-w-0 flex flex-col">
        <?php include __DIR__ . '/includes/navbar.php'; ?>
        <main class="px-8 py-5">

            <?php if ($msg): ?><div class="mb-5 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-green-700 text-sm"><?php echo htmlspecialchars($msg); ?></div><?php endif; ?>
            <?php if ($err): ?><div class="mb-5 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-red-700 text-sm"><?php echo htmlspecialchars($err); ?></div><?php endif; ?>

            <div class="flex items-center gap-4 mb-8">
                <div class="w-9 h-9 rounded-[8px] border border-[#ead9b5] bg-[#f8f1e7] flex items-center justify-center shrink-0">
                    <i data-lucide="banknote" class="w-5 h-5 text-[#c79435]"></i>
                </div>
                <h2 class="text-[24px] font-normal text-[#313131] leading-none tracking-wide" style="font-family: Georgia, 'Times New Roman', serif;">WITHDRAWAL REQUESTS</h2>
                <div class="h-[1px] bg-[#c9963b] flex-1"></div>
                <a href="income-rules.php" class="text-[12px] text-[#c79435] hover:underline whitespace-nowrap">Rules &rarr;</a>
            </div>

            <div class="max-w-[1280px] mx-auto space-y-6">

                <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                    <div class="rounded-xl bg-white border border-gray-200 p-4"><div class="text-[12px] text-gray-500">Pending Requests</div><div class="text-[20px] font-semibold"><?php echo (int)$sum['pending']; ?></div></div>
                    <div class="rounded-xl bg-white border border-gray-200 p-4"><div class="text-[12px] text-gray-500">Pending Payable</div><div class="text-[20px] font-semibold"><?php echo money($sum['pending_net']); ?></div></div>
                    <div class="rounded-xl bg-white border border-gray-200 p-4"><div class="text-[12px] text-gray-500">Total Paid</div><div class="text-[20px] font-semibold"><?php echo money($sum['paid_net']); ?></div></div>
                </div>

                <div class="flex flex-wrap gap-2">
                    <?php foreach ($valid as $s): ?>
                        <a href="?status=<?php echo $s; ?>" class="px-3 py-1 rounded-full text-[12px] <?php echo $filter === $s ? 'bg-[#c79435] text-white' : 'bg-gray-100 text-gray-600'; ?>"><?php echo $s; ?></a>
                    <?php endforeach; ?>
                </div>

                <div class="rounded-xl bg-white border border-gray-200 overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="bg-gray-50 text-gray-500 text-[12px]">
                                <tr>
                                    <th class="text-left px-4 py-3">#</th>
                                    <th class="text-left px-4 py-3">Date</th>
                                    <th class="text-left px-4 py-3">Member</th>
                                    <th class="text-right px-4 py-3">Nth</th>
                                    <th class="text-right px-4 py-3">Requested</th>
                                    <th class="text-right px-4 py-3">Deduction</th>
                                    <th class="text-right px-4 py-3">Payable</th>
                                    <th class="text-left px-4 py-3">Bank</th>
                                    <th class="text-left px-4 py-3">Status</th>
                                    <th class="text-left px-4 py-3">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <?php if (empty($rows)): ?>
                                    <tr><td colspan="10" class="px-4 py-8 text-center text-gray-400">No requests.</td></tr>
                                <?php else: foreach ($rows as $x): ?>
                                    <tr>
                                        <td class="px-4 py-3 text-gray-400"><?php echo (int)$x['id']; ?></td>
                                        <td class="px-4 py-3 whitespace-nowrap"><?php echo htmlspecialchars(substr((string)$x['requested_at'], 0, 16)); ?></td>
                                        <td class="px-4 py-3 font-medium"><?php echo htmlspecialchars($x['customer_id']); ?></td>
                                        <td class="px-4 py-3 text-right"><?php echo (int)$x['request_no']; ?></td>
                                        <td class="px-4 py-3 text-right"><?php echo money($x['gross_amount']); ?></td>
                                        <td class="px-4 py-3 text-right text-[#b91c1c]"><?php echo money($x['deduction_amount']); ?> <span class="text-gray-400">(<?php echo number_format((float)$x['deduction_percent'], 0); ?>%)</span></td>
                                        <td class="px-4 py-3 text-right font-semibold"><?php echo money($x['net_amount']); ?></td>
                                        <td class="px-4 py-3 max-w-[220px] truncate" title="<?php echo htmlspecialchars((string)$x['bank_snapshot']); ?>"><?php echo htmlspecialchars((string)($x['bank_snapshot'] ?: '—')); ?></td>
                                        <td class="px-4 py-3">
                                            <?php $st = $x['status']; $cls = $st === 'Paid' ? 'bg-green-50 text-green-700' : ($st === 'Rejected' ? 'bg-red-50 text-red-700' : ($st === 'Approved' ? 'bg-blue-50 text-blue-700' : 'bg-amber-50 text-amber-700')); ?>
                                            <span class="rounded-full <?php echo $cls; ?> px-2 py-0.5 text-[11px]"><?php echo htmlspecialchars($st); ?></span>
                                        </td>
                                        <td class="px-4 py-3">
                                            <?php if (in_array($x['status'], ['Pending', 'Approved'], true)): ?>
                                                <form method="post" class="flex flex-wrap items-center gap-1">
                                                    <input type="hidden" name="request_id" value="<?php echo (int)$x['id']; ?>">
                                                    <input type="text" name="admin_note" placeholder="note" class="w-24 rounded border border-gray-300 px-2 py-1 text-[11px]">
                                                    <?php if ($x['status'] === 'Pending'): ?>
                                                        <button name="action" value="approve" class="rounded bg-blue-600 text-white px-2 py-1 text-[11px]">Approve</button>
                                                    <?php endif; ?>
                                                    <button name="action" value="pay" class="rounded bg-green-600 text-white px-2 py-1 text-[11px]">Mark Paid</button>
                                                    <button name="action" value="reject" onclick="return confirm('Reject and refund the held amount to the member wallet?');" class="rounded bg-red-600 text-white px-2 py-1 text-[11px]">Reject</button>
                                                </form>
                                            <?php else: ?>
                                                <span class="text-gray-400 text-[12px]"><?php echo htmlspecialchars((string)($x['processed_by'] ?: '')); ?></span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </main>
    </div>
</div>
<script>lucide.createIcons();</script>
</body>
</html>
