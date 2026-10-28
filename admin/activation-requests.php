<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config/db.php';

if (!isset($conn) || !($conn instanceof mysqli)) {
    die("Database connection failed.");
}

function e($v) {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function ar_col_exists($conn, $table, $column) {
    $stmt = $conn->prepare("
        SELECT COUNT(*) total 
        FROM INFORMATION_SCHEMA.COLUMNS 
        WHERE TABLE_SCHEMA = DATABASE() 
        AND TABLE_NAME = ? 
        AND COLUMN_NAME = ?
    ");
    $stmt->bind_param("ss", $table, $column);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return (int)$row['total'] > 0;
}

function ar_update_member_status($conn, $customer_id, $status, $activatedAtSql = null) {
    $setParts = [];
    foreach (['registration_status', 'active_status', 'status'] as $column) {
        if (ar_col_exists($conn, 'signup_users', $column)) {
            $setParts[] = "`{$column}` = '" . $conn->real_escape_string($status) . "'";
        }
    }

    if ($activatedAtSql !== null && ar_col_exists($conn, 'signup_users', 'activated_at')) {
        $setParts[] = "activated_at = {$activatedAtSql}";
    }

    if (!$setParts) {
        return false;
    }

    $stmt = $conn->prepare("UPDATE signup_users SET " . implode(', ', $setParts) . " WHERE customer_id = ?");
    if (!$stmt) {
        return false;
    }

    $stmt->bind_param("s", $customer_id);
    $ok = $stmt->execute();
    $stmt->close();

    return $ok;
}

/* ── Auto add missing columns ── */
if (!ar_col_exists($conn, 'activation_requests', 'status')) {
    $conn->query("ALTER TABLE activation_requests ADD status VARCHAR(30) NOT NULL DEFAULT 'Pending'");
}
if (!ar_col_exists($conn, 'signup_users', 'activated_at')) {
    $conn->query("ALTER TABLE signup_users ADD activated_at DATETIME NULL");
}

/* ── ACTION ── */
$action_msg   = '';
$action_error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_id'], $_POST['action'])) {
    $request_id = (int)$_POST['request_id'];
    $action     = $_POST['action'];

    /* Fetch the request row first */
    $stmt = $conn->prepare("SELECT customer_id, status FROM activation_requests WHERE id = ? LIMIT 1");
    $stmt->bind_param("i", $request_id);
    $stmt->execute();
    $request = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$request) {
        $action_error = "Request #$request_id not found.";
    } else {
        $customer_id    = $request['customer_id'];
        $current_status = strtolower(trim($request['status'] ?? 'pending'));

        if ($action === 'approve') {
            if ($current_status === 'approved') {
                $action_error = "Request is already approved.";
            } else {
                /* 1. Mark request as Approved */
                $stmt = $conn->prepare("UPDATE activation_requests SET status = 'Approved' WHERE id = ?");
                $stmt->bind_param("i", $request_id);
                $stmt->execute();
                $stmt->close();

                /* 2. Activate the user account */
                ar_update_member_status($conn, $customer_id, 'Active', 'NOW()');

                /* 3. Income Plan — Direct Sponsor (#1) + Binary (#2) */
                require_once __DIR__ . '/../includes/income_common.php';
                process_member_income_on_activation($conn, $customer_id, [
                    'source'      => 'Activation',
                    'ref_id'      => (int)$request_id,
                    'base_amount' => (float)($request['amount'] ?? 0),
                ]);

                $action_msg = "Activation request approved successfully.";
            }

        } elseif ($action === 'reject') {
            if ($current_status === 'rejected') {
                $action_error = "Request is already rejected.";
            } else {
                /* 1. Mark request as Rejected */
                $stmt = $conn->prepare("UPDATE activation_requests SET status = 'Rejected' WHERE id = ?");
                $stmt->bind_param("i", $request_id);
                $stmt->execute();
                $stmt->close();

                /* 2. Reject / deactivate the user account
                   — also clears activated_at so no "ghost" active timestamp remains */
                ar_update_member_status($conn, $customer_id, 'Rejected', 'NULL');

                $action_msg = "Activation request rejected.";
            }
        }
    }

    /* Redirect to avoid form resubmission; carry flash message via session */
    $_SESSION['ar_msg']   = $action_msg;
    $_SESSION['ar_error'] = $action_error;
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

/* Pull flash messages set above */
$action_msg   = $_SESSION['ar_msg']   ?? '';
$action_error = $_SESSION['ar_error'] ?? '';
unset($_SESSION['ar_msg'], $_SESSION['ar_error']);

/* ── FETCH ── */
$result = $conn->query("
    SELECT 
        ar.*,
        COALESCE(u.full_name, ar.customer_id) AS full_name
    FROM activation_requests ar
    LEFT JOIN signup_users u ON ar.customer_id = u.customer_id
    ORDER BY ar.id DESC
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Activation Requests</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        /* Flash messages */
        .flash-success { background:#dcfce7; color:#15803d; border:1px solid #bbf7d0; border-radius:8px; padding:10px 16px; font-size:13px; font-weight:600; margin-bottom:16px; display:flex; align-items:center; gap:8px; }
        .flash-error   { background:#fee2e2; color:#dc2626; border:1px solid #fecaca; border-radius:8px; padding:10px 16px; font-size:13px; font-weight:600; margin-bottom:16px; display:flex; align-items:center; gap:8px; }
    </style>
</head>

<body class="bg-gray-100">

<?php include __DIR__ . '/includes/sidebar.php'; ?>

<div class="ml-64 min-h-screen">

<?php include __DIR__ . '/includes/navbar.php'; ?>

<div class="px-5 py-4">

    <div class="mb-5">
        <h1 class="text-xl font-bold text-gray-800">Activation Requests</h1>
        <p class="text-sm text-gray-500">Approve or reject user activation</p>
    </div>

    <?php if ($action_msg !== ''): ?>
        <div class="flash-success">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
            <?= e($action_msg) ?>
        </div>
    <?php endif; ?>

    <?php if ($action_error !== ''): ?>
        <div class="flash-error">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            <?= e($action_error) ?>
        </div>
    <?php endif; ?>

    <div class="bg-white rounded-xl shadow border overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">

                <thead class="bg-gray-50 text-gray-600">
                    <tr>
                        <th class="px-4 py-3 text-left">#</th>
                        <th class="px-4 py-3 text-left">User ID</th>
                        <th class="px-4 py-3 text-left">Name</th>
                        <th class="px-4 py-3 text-left">Amount</th>
                        <th class="px-4 py-3 text-left">Transaction</th>
                        <th class="px-4 py-3 text-left">Slip</th>
                        <th class="px-4 py-3 text-left">Status</th>
                        <th class="px-4 py-3 text-left">Action</th>
                    </tr>
                </thead>

                <tbody class="divide-y">

                <?php if ($result && $result->num_rows > 0): ?>
                    <?php $i = 1; ?>
                    <?php while ($row = $result->fetch_assoc()): ?>

                        <?php
                        $status = trim($row['status'] ?? 'Pending');
                        if ($status === '') $status = 'Pending';
                        $statusLower = strtolower($status);

                        $slip = trim($row['payment_slip'] ?? '');
                        ?>

                        <tr class="hover:bg-gray-50">

                            <td class="px-4 py-3 text-gray-500"><?= $i++ ?></td>

                            <td class="px-4 py-3 text-blue-600 font-medium">
                                <?= e($row['customer_id'] ?? '-') ?>
                            </td>

                            <td class="px-4 py-3 font-medium text-gray-800">
                                <?= e($row['full_name'] ?? '-') ?>
                            </td>

                            <td class="px-4 py-3 font-semibold text-gray-800">
                                ₹<?= number_format((float)($row['amount'] ?? 0), 2) ?>
                            </td>

                            <td class="px-4 py-3 text-gray-600">
                                <?= e($row['transaction_id'] ?? '-') ?>
                            </td>

                            <td class="px-4 py-3">
                                <?php if ($slip !== ''): ?>
                                    <a href="../member/uploads/receipts/<?= e(basename($slip)) ?>"
                                       target="_blank"
                                       class="text-blue-600 underline hover:text-blue-800">View</a>
                                <?php else: ?>
                                    <span class="text-gray-400">-</span>
                                <?php endif; ?>
                            </td>

                            <!-- Status badge -->
                            <td class="px-4 py-3">
                                <?php if ($statusLower === 'approved'): ?>
                                    <span class="inline-flex items-center gap-1 px-2 py-1 bg-green-100 text-green-700 rounded text-xs font-semibold">
                                        <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                                        Approved
                                    </span>
                                <?php elseif ($statusLower === 'rejected'): ?>
                                    <span class="inline-flex items-center gap-1 px-2 py-1 bg-red-100 text-red-600 rounded text-xs font-semibold">
                                        <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                                        Rejected
                                    </span>
                                <?php else: ?>
                                    <span class="inline-flex items-center gap-1 px-2 py-1 bg-yellow-100 text-yellow-700 rounded text-xs font-semibold">
                                        <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                                        Pending
                                    </span>
                                <?php endif; ?>
                            </td>

                            <!-- Action buttons -->
                            <td class="px-4 py-3">
                                <?php if ($statusLower === 'pending'): ?>
                                    <!-- Show both buttons only when Pending -->
                                    <div class="flex gap-2">
                                        <form method="POST"
                                              onsubmit="return confirm('Approve this activation request for <?= e(addslashes($row['full_name'] ?? $row['customer_id'])) ?>?');">
                                            <input type="hidden" name="request_id" value="<?= (int)$row['id'] ?>">
                                            <input type="hidden" name="action"     value="approve">
                                            <button type="submit"
                                                    class="bg-green-600 hover:bg-green-700 text-white px-3 py-1 rounded text-xs font-semibold transition-colors">
                                                Approve
                                            </button>
                                        </form>

                                        <form method="POST"
                                              onsubmit="return confirm('Reject this activation request for <?= e(addslashes($row['full_name'] ?? $row['customer_id'])) ?>?');">
                                            <input type="hidden" name="request_id" value="<?= (int)$row['id'] ?>">
                                            <input type="hidden" name="action"     value="reject">
                                            <button type="submit"
                                                    class="bg-red-600 hover:bg-red-700 text-white px-3 py-1 rounded text-xs font-semibold transition-colors">
                                                Reject
                                            </button>
                                        </form>
                                    </div>

                                <?php elseif ($statusLower === 'approved'): ?>
                                    <!-- Approved → only allow Reject (to revoke access) -->
                                    <form method="POST"
                                          onsubmit="return confirm('Revoke / reject the already-approved account of <?= e(addslashes($row['full_name'] ?? $row['customer_id'])) ?>? This will deactivate their account.');">
                                        <input type="hidden" name="request_id" value="<?= (int)$row['id'] ?>">
                                        <input type="hidden" name="action"     value="reject">
                                        <button type="submit"
                                                class="bg-red-600 hover:bg-red-700 text-white px-3 py-1 rounded text-xs font-semibold transition-colors">
                                            Revoke
                                        </button>
                                    </form>

                                <?php elseif ($statusLower === 'rejected'): ?>
                                    <!-- Rejected → only allow Re-Approve -->
                                    <form method="POST"
                                          onsubmit="return confirm('Re-approve and activate the account of <?= e(addslashes($row['full_name'] ?? $row['customer_id'])) ?>?');">
                                        <input type="hidden" name="request_id" value="<?= (int)$row['id'] ?>">
                                        <input type="hidden" name="action"     value="approve">
                                        <button type="submit"
                                                class="bg-green-600 hover:bg-green-700 text-white px-3 py-1 rounded text-xs font-semibold transition-colors">
                                            Re-Approve
                                        </button>
                                    </form>

                                <?php else: ?>
                                    <span class="text-gray-400 text-xs">—</span>
                                <?php endif; ?>
                            </td>

                        </tr>

                    <?php endwhile; ?>

                <?php else: ?>
                    <tr>
                        <td colspan="8" class="text-center py-10 text-gray-400 text-sm">
                            No activation requests found.
                        </td>
                    </tr>
                <?php endif; ?>

                </tbody>
            </table>
        </div>
    </div>

</div><!-- /px-5 -->
</div><!-- /ml-64 -->

<script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (typeof lucide !== "undefined") lucide.createIcons();
});
</script>

</body>
</html>