<?php
if (session_status() === PHP_SESSION_NONE) {
	session_start();
}

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/config/db.php';

if (!isset($conn) || !($conn instanceof mysqli)) {
	die('Database connection failed.');
}

/* ─── AJAX: instant member lookup ─────────────────────────────────────────── */
if (isset($_GET['ajax_check']) && isset($_GET['member_id'])) {
	header('Content-Type: application/json');
	$id = trim((string)$_GET['member_id']);

	if ($id === '') {
		echo json_encode(['found' => false]);
		exit;
	}

	// Detect which ID column exists
	$colResult = $conn->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
		WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'signup_users'
		ORDER BY ORDINAL_POSITION");
	$cols = [];
	while ($r = $colResult->fetch_assoc()) $cols[] = $r['COLUMN_NAME'];

	$idCol = '';
	foreach (['customer_id','member_id','user_id'] as $c) {
		if (in_array($c, $cols, true)) { $idCol = $c; break; }
	}
	$statusCol = '';
	foreach (['registration_status','active_status','status'] as $c) {
		if (in_array($c, $cols, true)) { $statusCol = $c; break; }
	}

	if ($idCol === '') { echo json_encode(['found' => false]); exit; }

	$stmt = $conn->prepare("SELECT * FROM `signup_users` WHERE `{$idCol}` = ? LIMIT 1");
	$stmt->bind_param('s', $id);
	$stmt->execute();
	$row = $stmt->get_result()->fetch_assoc();

	if (!$row) { echo json_encode(['found' => false]); exit; }

	$status    = trim((string)($row[$statusCol] ?? ''));
	$full_name = trim((string)($row['full_name'] ?? ''));
	echo json_encode([
		'found'     => true,
		'status'    => $status,
		'full_name' => $full_name,
		'email'     => $row['email'] ?? '',
	]);
	exit;
}

/* ─── Helpers ─────────────────────────────────────────────────────────────── */
function e(string $value): string
{
	return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function tableExists(mysqli $conn, string $tableName): bool
{
	$stmt = $conn->prepare("SELECT COUNT(*) AS total FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?");
	$stmt->bind_param('s', $tableName);
	$stmt->execute();
	$row = $stmt->get_result()->fetch_assoc();
	return (int)($row['total'] ?? 0) > 0;
}

function getColumns(mysqli $conn, string $tableName): array
{
	$columns = [];
	$stmt = $conn->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? ORDER BY ORDINAL_POSITION");
	$stmt->bind_param('s', $tableName);
	$stmt->execute();
	$result = $stmt->get_result();
	while ($row = $result->fetch_assoc()) {
		$columns[] = $row['COLUMN_NAME'];
	}
	return $columns;
}

function firstExistingColumn(array $columns, array $candidates): string
{
	foreach ($candidates as $candidate) {
		if (in_array($candidate, $columns, true)) return $candidate;
	}
	return '';
}

function updateMemberStatusColumns(mysqli $conn, string $tableName, string $idColumn, string $memberId, string $status, ?string $activatedAtSql = null): bool
{
	$columns = getColumns($conn, $tableName);
	$setParts = [];

	foreach (['registration_status', 'active_status', 'status'] as $column) {
		if (in_array($column, $columns, true)) {
			$setParts[] = "`{$column}` = '" . $conn->real_escape_string($status) . "'";
		}
	}

	if ($activatedAtSql !== null && in_array('activated_at', $columns, true)) {
		$setParts[] = "activated_at = {$activatedAtSql}";
	}

	if (!$setParts) return false;

	$sql = "UPDATE `{$tableName}` SET " . implode(', ', $setParts) . " WHERE `{$idColumn}` = ? LIMIT 1";
	$stmt = $conn->prepare($sql);
	if (!$stmt) return false;

	$stmt->bind_param('s', $memberId);
	$ok = $stmt->execute();
	$stmt->close();
	return $ok;
}

/* ─── Page logic ──────────────────────────────────────────────────────────── */
$message        = isset($_GET['done'], $_GET['msg']) ? trim((string)$_GET['msg']) : '';
$error          = '';
$memberIdInput  = trim((string)($_POST['member_id'] ?? ''));
$selectedStatus = trim((string)($_POST['status'] ?? 'Active'));

if ($selectedStatus !== 'Blocked') {
	$selectedStatus = 'Active';
}

$signupTable   = 'signup_users';
$memberRecord  = null;
$memberColumns = [];
$idColumn      = '';
$statusColumn  = '';

if (tableExists($conn, $signupTable)) {
	$memberColumns = getColumns($conn, $signupTable);
	$idColumn      = firstExistingColumn($memberColumns, ['customer_id', 'member_id', 'user_id']);
	$statusColumn  = firstExistingColumn($memberColumns, ['registration_status', 'active_status', 'status']);

	if ($memberIdInput !== '' && $idColumn !== '') {
		$sql  = "SELECT * FROM `{$signupTable}` WHERE `{$idColumn}` = ? LIMIT 1";
		$stmt = $conn->prepare($sql);
		$stmt->bind_param('s', $memberIdInput);
		$stmt->execute();
		$memberRecord = $stmt->get_result()->fetch_assoc() ?: null;
	}
}

/* Current status before any update */
$currentStatus = '';
if (is_array($memberRecord) && $statusColumn !== '') {
	$currentStatus = trim((string)($memberRecord[$statusColumn] ?? ''));
}

/* POST handler */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_activation'])) {
	if ($memberIdInput === '') {
		$error = 'Please enter a member ID.';
	} elseif (!tableExists($conn, $signupTable)) {
		$error = 'signup_users table not found.';
	} elseif ($idColumn === '') {
		$error = 'No member ID column was found in signup_users.';
	} elseif ($statusColumn === '') {
		$error = 'No status column was found in signup_users.';
	} elseif ($memberRecord === null) {
		$error = "No member found with ID <strong>{$memberIdInput}</strong>. Please check and try again.";
	} elseif ($selectedStatus === 'Active' && strtolower($currentStatus) === 'active') {
		/* ── Already activated guard ── */
		$memberName = trim((string)($memberRecord['full_name'] ?? ''));
		$nameLabel  = $memberName !== '' ? " (<strong>{$memberName}</strong>)" : '';
		$error = "Member ID <strong>{$memberIdInput}</strong>{$nameLabel} is already activated. No changes were made.";
	} else {
		$updated = false;
		if ($selectedStatus === 'Active') {
			$updated = updateMemberStatusColumns($conn, $signupTable, $idColumn, $memberIdInput, 'Active', in_array('activated_at', $memberColumns, true) ? 'NOW()' : null);
		} else {
			$updated = updateMemberStatusColumns($conn, $signupTable, $idColumn, $memberIdInput, 'Blocked', in_array('activated_at', $memberColumns, true) ? 'NULL' : null);
		}

		if ($updated) {
			/* Generate MLM income when activated from Admin */
			if ($selectedStatus === 'Active') {
				$customer_id = $memberIdInput;
				include __DIR__ . '/includes/process-activation.php';

				// Income Plan — Direct Sponsor (#1) + Binary (#2)
				require_once __DIR__ . '/../includes/income_common.php';
				process_member_income_on_activation($conn, $customer_id, ['source' => 'Activation']);
			}

			$stmt = $conn->prepare("SELECT * FROM `{$signupTable}` WHERE `{$idColumn}` = ? LIMIT 1");
			$stmt->bind_param('s', $memberIdInput);
			$stmt->execute();
			$memberRecord = $stmt->get_result()->fetch_assoc() ?: null;
			$stmt->close();

			/* Refresh current status after update */
			if (is_array($memberRecord) && $statusColumn !== '') {
				$currentStatus = trim((string)($memberRecord[$statusColumn] ?? ''));
			}

			$memberName  = trim((string)($memberRecord['full_name'] ?? ''));
			$nameLabel   = $memberName !== '' ? " &mdash; " . htmlspecialchars($memberName, ENT_QUOTES, 'UTF-8') : '';
			$actionLabel = $selectedStatus === 'Active' ? 'activated' : 'blocked';

			/* PRG: redirect so refresh doesn't re-submit the form */
			$msg = urlencode("Member ID {$memberIdInput}{$nameLabel} has been {$actionLabel} successfully.");
			header("Location: " . strtok($_SERVER['REQUEST_URI'], '?') . "?done=1&msg={$msg}");
			exit;
		} else {
			$error = 'Status update failed. Please try again.';
		}
	}
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Activation ID Block</title>
	<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gradient-to-br from-slate-100 via-white to-slate-200 min-h-screen text-slate-800">
<?php include __DIR__ . '/includes/sidebar.php'; ?>

<div class="ml-64 min-h-screen">
	<?php include __DIR__ . '/includes/navbar.php'; ?>

	<div class="px-5 py-6 lg:px-8">

		<!-- Page heading -->
		<div class="flex items-center gap-4 mb-4">
			<div class="w-9 h-9 rounded-[8px] bg-[#f6f2ea] flex items-center justify-center shrink-0">
				<i data-lucide="landmark" class="w-4 h-4 text-[#c9983b]"></i>
			</div>
			<h2 class="text-[18px] leading-none text-[#2f2f2f] font-normal whitespace-nowrap"
				style="font-family: Georgia, 'Times New Roman', serif;">
				ID ACTIVATION
			</h2>
			<div class="h-[1px] bg-[#c9983b] flex-1"></div>
		</div>

		<div class="grid gap-6 lg:grid-cols-[minmax(0,1.15fr)_minmax(320px,0.85fr)]">

			<!-- ── Left card: form ── -->
			<div class="rounded-3xl border border-slate-200 bg-white/90 shadow-[0_20px_60px_rgba(15,23,42,0.08)] backdrop-blur overflow-hidden">
				<div class="border-b border-slate-100 px-6 py-5">
					<h2 class="text-lg font-semibold text-slate-900">Update member status</h2>
				
				</div>

				<form method="POST" class="space-y-5 px-6 py-6" id="activation-form">
					<!-- Member ID input -->
					<div>
						<label for="member_id" class="mb-2 block text-sm font-medium text-slate-700">Member ID</label>
						<input
							type="text"
							id="member_id"
							name="member_id"
							value="<?php echo e($memberIdInput); ?>"
							placeholder="Enter member ID"
							autocomplete="off"
							class="w-full rounded-2xl border border-slate-300 bg-white px-4 py-3 text-sm outline-none transition focus:border-slate-500 focus:ring-4 focus:ring-slate-200"
						>
						<!-- Live status badge injected here by JS -->
						<div id="live-status" class="mt-2 min-h-[28px] flex items-center"></div>
					</div>

					<!-- Status select -->
					<div>
						<label for="status" class="mb-2 block text-sm font-medium text-slate-700">Status</label>
						<select
							id="status"
							name="status"
							class="w-full rounded-2xl border border-slate-300 bg-white px-4 py-3 text-sm outline-none transition focus:border-slate-500 focus:ring-4 focus:ring-slate-200"
						>
							<option value="Active"  <?php echo $selectedStatus === 'Active'  ? 'selected' : ''; ?>>Active</option>
							<option value="Blocked" <?php echo $selectedStatus === 'Blocked' ? 'selected' : ''; ?>>Blocked</option>
						</select>
					</div>

					<button
						type="submit"
						name="submit_activation"
						class="inline-flex items-center justify-center rounded-2xl bg-slate-900 px-5 py-3 text-sm font-semibold text-white transition hover:bg-slate-700"
					>
						Update Status
					</button>
				</form>

				<!-- Success message -->
				<?php if ($message !== ''): ?>
					<div class="mx-6 mb-6 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 leading-6">
						<span class="inline-block mr-2 text-emerald-600">&#10003;</span><?php echo $message; ?>
					</div>
				<?php endif; ?>

				<!-- Error / already-activated message -->
				<?php if ($error !== ''): ?>
					<div class="mx-6 mb-6 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800 leading-6">
						<span class="inline-block mr-2 text-rose-500">&#9888;</span><?php echo $error; ?>
					</div>
				<?php endif; ?>
			</div>

			<!-- ── Right column: member info ── -->
			<div class="space-y-6">
				<?php if ($memberRecord): ?>
					<div class="mt-4 space-y-3 text-sm">
						<div class="flex items-start justify-between gap-4 border-b border-slate-100 pb-3">
							<span class="text-slate-500">Member ID</span>
							<span class="font-medium text-slate-900"><?php echo e($memberIdInput); ?></span>
						</div>
						<div class="flex items-start justify-between gap-4 border-b border-slate-100 pb-3">
							<span class="text-slate-500">Name</span>
							<span class="font-medium text-slate-900"><?php echo e((string)($memberRecord['full_name'] ?? '-')); ?></span>
						</div>
						<div class="flex items-start justify-between gap-4 border-b border-slate-100 pb-3">
							<span class="text-slate-500">Status</span>
							<span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold <?php echo strtolower($currentStatus) === 'blocked' ? 'bg-rose-100 text-rose-700' : 'bg-emerald-100 text-emerald-700'; ?>">
								<?php echo e($currentStatus !== '' ? $currentStatus : 'Unknown'); ?>
							</span>
						</div>
						<div class="flex items-start justify-between gap-4">
							<span class="text-slate-500">Email</span>
							<span class="font-medium text-slate-900"><?php echo e((string)($memberRecord['email'] ?? '-')); ?></span>
						</div>
					</div>
				<?php endif; ?>
			</div>

		</div>
	</div>
</div>

<script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
	if (typeof lucide !== 'undefined') lucide.createIcons();

	/* ── Live member status lookup ─────────────────────────────── */
	const input  = document.getElementById('member_id');
	const badge  = document.getElementById('live-status');
	let   timer  = null;

	function setBadge(html) {
		badge.innerHTML = html;
	}

	function lookup(id) {
		if (!id) { setBadge(''); return; }

		setBadge('<span class="text-xs text-slate-400 animate-pulse">Checking…</span>');

		fetch(window.location.pathname + '?ajax_check=1&member_id=' + encodeURIComponent(id))
			.then(function(r) { return r.json(); })
			.then(function(data) {
				if (!data.found) {
					setBadge(
						'<span class="inline-flex items-center gap-1.5 rounded-full border border-rose-200 bg-rose-50 px-3 py-1 text-xs font-semibold text-rose-700">' +
						'<svg xmlns="http://www.w3.org/2000/svg" class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>' +
						'Member not found</span>'
					);
					return;
				}

				var s = (data.status || '').toLowerCase();

				var name = data.full_name ? ' &mdash; <strong>' + data.full_name + '</strong>' : '';

				if (s === 'active') {
					setBadge(
						'<span class="inline-flex items-center gap-1.5 rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700">' +
						'<svg xmlns="http://www.w3.org/2000/svg" class="w-3 h-3 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>' +
						'Already Active' + name + '</span>'
					);
				} else if (s === 'blocked') {
					setBadge(
						'<span class="inline-flex items-center gap-1.5 rounded-full border border-rose-200 bg-rose-50 px-3 py-1 text-xs font-semibold text-rose-700">' +
						'<svg xmlns="http://www.w3.org/2000/svg" class="w-3 h-3 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/></svg>' +
						'Blocked' + name + '</span>'
					);
				} else {
					setBadge(
						'<span class="inline-flex items-center gap-1.5 rounded-full border border-amber-200 bg-amber-50 px-3 py-1 text-xs font-semibold text-amber-700">' +
						'<svg xmlns="http://www.w3.org/2000/svg" class="w-3 h-3 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>' +
						(data.status || 'Pending') + name + '</span>'
					);
				}
			})
			.catch(function() { setBadge(''); });
	}

	if (input) {
		/* Run immediately if value is pre-filled (e.g. after page reload / GET) */
		var initial = input.value.trim();
		if (initial) lookup(initial);

		input.addEventListener('input', function () {
			clearTimeout(timer);
			var val = this.value.trim();
			if (!val) { setBadge(''); return; }
			/* Debounce: wait 400 ms after the user stops typing */
			timer = setTimeout(function() { lookup(val); }, 400);
		});
	}
});
</script>
</body>
</html>