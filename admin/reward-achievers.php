<?php
// ─────────────────────────────────────────────────────────────
//  admin/reward-achievers.php
//  Reward Achievers Management — MLM Admin Panel
// ─────────────────────────────────────────────────────────────
session_start();


include __DIR__ . '/config/db.php';

if (!isset($conn) || !($conn instanceof mysqli)) {
    die("Database connection failed.");
}
$conn->set_charset("utf8mb4");

// ── Ensure table exists ──────────────────────────────────────
$conn->query("
    CREATE TABLE IF NOT EXISTS reward_achievers (
        id            INT AUTO_INCREMENT PRIMARY KEY,
        customer_id   VARCHAR(50)    NOT NULL,
        club_no       INT            NOT NULL DEFAULT 1,
        reward_name   VARCHAR(255)   NOT NULL,
        reward_budget DECIMAL(12,2)  NOT NULL DEFAULT 0,
        status        ENUM('Pending','Approved','Delivered','Rejected') NOT NULL DEFAULT 'Pending',
        achieved_date DATE           DEFAULT NULL,
        approved_date DATE           DEFAULT NULL,
        remarks       TEXT           DEFAULT NULL,
        created_at    DATETIME       DEFAULT CURRENT_TIMESTAMP
    )
");

// ── AJAX / POST actions ──────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    $action = trim($_POST['action']);
    $id     = (int)($_POST['id'] ?? 0);

    if ($id <= 0) {
        echo json_encode(['success' => false, 'msg' => 'Invalid ID.']);
        exit();
    }

    if ($action === 'approve') {
        $stmt = $conn->prepare("UPDATE reward_achievers SET status='Approved', approved_date=CURDATE() WHERE id=? AND status='Pending'");
        $stmt->bind_param("i", $id);
        $ok = $stmt->execute() && $stmt->affected_rows > 0;
        $stmt->close();
        echo json_encode(['success' => $ok, 'msg' => $ok ? 'Reward approved successfully.' : 'Could not approve.']);

    } elseif ($action === 'reject') {
        $remarks = trim($_POST['remarks'] ?? '');
        $stmt = $conn->prepare("UPDATE reward_achievers SET status='Rejected', remarks=? WHERE id=? AND status='Pending'");
        $stmt->bind_param("si", $remarks, $id);
        $ok = $stmt->execute() && $stmt->affected_rows > 0;
        $stmt->close();
        echo json_encode(['success' => $ok, 'msg' => $ok ? 'Reward rejected.' : 'Could not reject.']);

    } elseif ($action === 'deliver') {
        $stmt = $conn->prepare("UPDATE reward_achievers SET status='Delivered' WHERE id=? AND status='Approved'");
        $stmt->bind_param("i", $id);
        $ok = $stmt->execute() && $stmt->affected_rows > 0;
        $stmt->close();
        echo json_encode(['success' => $ok, 'msg' => $ok ? 'Marked as delivered.' : 'Could not update.']);

    } else {
        echo json_encode(['success' => false, 'msg' => 'Unknown action.']);
    }
    exit();
}

// ── GET: fetch single record for view modal ──────────────────
if (isset($_GET['fetch_id'])) {
    header('Content-Type: application/json');
    $fid  = (int)$_GET['fetch_id'];
    $stmt = $conn->prepare("SELECT * FROM reward_achievers WHERE id=?");
    $stmt->bind_param("i", $fid);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    echo json_encode($row ?: []);
    exit();
}

// ── Filters ──────────────────────────────────────────────────
$fCid     = trim($_GET['cid']    ?? '');
$fName    = trim($_GET['rname']  ?? '');
$fClub    = (int)($_GET['club']  ?? 0);
$fStatus  = trim($_GET['status'] ?? '');
$fFrom    = trim($_GET['from']   ?? '');
$fTo      = trim($_GET['to']     ?? '');
$page     = max(1, (int)($_GET['page'] ?? 1));
$perPage  = 15;
$offset   = ($page - 1) * $perPage;

// Build WHERE
$where   = [];
$params  = [];
$types   = '';

if ($fCid !== '') {
    $where[]  = "customer_id LIKE ?";
    $params[] = "%$fCid%";
    $types   .= 's';
}
if ($fName !== '') {
    $where[]  = "reward_name LIKE ?";
    $params[] = "%$fName%";
    $types   .= 's';
}
if ($fClub > 0) {
    $where[]  = "club_no = ?";
    $params[] = $fClub;
    $types   .= 'i';
}
if (in_array($fStatus, ['Pending','Approved','Delivered','Rejected'])) {
    $where[]  = "status = ?";
    $params[] = $fStatus;
    $types   .= 's';
}
if ($fFrom !== '') {
    $where[]  = "achieved_date >= ?";
    $params[] = $fFrom;
    $types   .= 's';
}
if ($fTo !== '') {
    $where[]  = "achieved_date <= ?";
    $params[] = $fTo;
    $types   .= 's';
}

$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

// ── Summary counts ───────────────────────────────────────────
$summary = ['Total'=>0,'Pending'=>0,'Approved'=>0,'Delivered'=>0,'Rejected'=>0,'Budget'=>0];
$sumRes  = $conn->query("
    SELECT
        COUNT(*) total,
        SUM(status='Pending')   pending,
        SUM(status='Approved')  approved,
        SUM(status='Delivered') delivered,
        SUM(status='Rejected')  rejected,
        SUM(reward_budget)      budget
    FROM reward_achievers
");
if ($sumRes) {
    $sr = $sumRes->fetch_assoc();
    $summary['Total']     = (int)$sr['total'];
    $summary['Pending']   = (int)$sr['pending'];
    $summary['Approved']  = (int)$sr['approved'];
    $summary['Delivered'] = (int)$sr['delivered'];
    $summary['Rejected']  = (int)$sr['rejected'];
    $summary['Budget']    = (float)$sr['budget'];
}

// ── Total count for pagination ───────────────────────────────
$totalRows = 0;
$cntSql    = "SELECT COUNT(*) c FROM reward_achievers $whereSql";
$cntStmt   = $conn->prepare($cntSql);
if ($types && $cntStmt) {
    $cntStmt->bind_param($types, ...$params);
}
if ($cntStmt) {
    $cntStmt->execute();
    $totalRows = (int)$cntStmt->get_result()->fetch_assoc()['c'];
    $cntStmt->close();
}
$totalPages = max(1, (int)ceil($totalRows / $perPage));

// ── Fetch page rows ──────────────────────────────────────────
$rows   = [];
$dataSql = "
    SELECT id, customer_id, club_no, reward_name, reward_budget,
           status, achieved_date, approved_date, remarks, created_at
    FROM reward_achievers
    $whereSql
    ORDER BY id DESC
    LIMIT ? OFFSET ?
";
$dataStmt = $conn->prepare($dataSql);
if ($dataStmt) {
    $allTypes  = $types . 'ii';
    $allParams = array_merge($params, [$perPage, $offset]);
    $dataStmt->bind_param($allTypes, ...$allParams);
    $dataStmt->execute();
    $rows = $dataStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $dataStmt->close();
}

// ── Distinct clubs for filter dropdown ───────────────────────
$clubOptions = [];
$cr = $conn->query("SELECT DISTINCT club_no FROM reward_achievers ORDER BY club_no ASC");
if ($cr) {
    while ($c = $cr->fetch_assoc()) $clubOptions[] = (int)$c['club_no'];
}

// ── Export helpers ───────────────────────────────────────────
// Export action — runs before HTML output
if (isset($_GET['export'])) {
    $expType = $_GET['export'];
    $expSql  = "SELECT id,customer_id,club_no,reward_name,reward_budget,status,achieved_date,approved_date,remarks,created_at FROM reward_achievers $whereSql ORDER BY id DESC";
    $expStmt = $conn->prepare($expSql);
    if ($types && $expStmt) {
        $expStmt->bind_param($types, ...$params);
    }
    $expStmt->execute();
    $expRows = $expStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $expStmt->close();

    if ($expType === 'csv') {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="reward_achievers_' . date('Ymd') . '.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['Sr','Customer ID','Club No','Reward Name','Budget (₹)','Status','Achieved Date','Approved Date','Remarks','Created At']);
        foreach ($expRows as $i => $r) {
            fputcsv($out, [
                $i+1, $r['customer_id'], $r['club_no'], $r['reward_name'],
                number_format($r['reward_budget'],2), $r['status'],
                $r['achieved_date'] ?? '-', $r['approved_date'] ?? '-',
                $r['remarks'] ?? '', $r['created_at']
            ]);
        }
        fclose($out);
        exit();
    }
}

// helper fns
function fmtDate($d) {
    return $d ? date('d M Y', strtotime($d)) : '—';
}
function statusClass($s) {
    return match($s) {
        'Approved'  => 'badge-approved',
        'Delivered' => 'badge-delivered',
        'Rejected'  => 'badge-rejected',
        default     => 'badge-pending',
    };
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Reward Achievers Management — Admin</title>
<script src="https://cdn.tailwindcss.com"></script>
<script src="https://unpkg.com/lucide@latest"></script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@400;600;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
/* ── Root Tokens ─────────────────────────────────────── */
:root {
    --gold:        #C89741;
    --gold-2:      #e0b45a;
    --gold-light:  #f8f1e3;
    --gold-dark:   #9e7428;
    --bg:          #f1f0ee;
    --card:        #ffffff;
    --border:      #e4ddd0;
    --text-main:   #1e1a15;
    --text-muted:  #7a6e60;
    --text-light:  #b5a898;
    --radius:      14px;
    --shadow:      0 2px 16px rgba(180,140,60,.10);
    --shadow-lg:   0 8px 40px rgba(180,140,60,.16);
}

body {
    background: var(--bg);
    font-family: 'DM Sans', sans-serif;
    color: var(--text-main);
}

/* ── Sidebar push ────────────────────────────────────── */
@media (min-width: 1024px) { .main-wrap { margin-left: 256px; } }

/* ── Animations ──────────────────────────────────────── */
@keyframes fadeUp {
    from { opacity:0; transform:translateY(12px); }
    to   { opacity:1; transform:translateY(0); }
}
@keyframes shimmer {
    0%   { background-position:-600px 0; }
    100% { background-position: 600px 0; }
}
@keyframes spin { to { transform:rotate(360deg); } }

.fade-up { animation: fadeUp .35s ease both; }
.fade-up:nth-child(1){animation-delay:.05s}
.fade-up:nth-child(2){animation-delay:.10s}
.fade-up:nth-child(3){animation-delay:.15s}
.fade-up:nth-child(4){animation-delay:.20s}
.fade-up:nth-child(5){animation-delay:.25s}

/* ── Summary Cards ───────────────────────────────────── */
.sum-card {
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    box-shadow: var(--shadow);
    padding: 18px 20px;
    display: flex;
    align-items: center;
    gap: 14px;
    transition: box-shadow .2s, transform .2s;
}
.sum-card:hover { box-shadow: var(--shadow-lg); transform: translateY(-2px); }
.sum-icon {
    width: 44px; height: 44px; border-radius: 10px;
    display: flex; align-items: center; justify-content: center; flex-shrink: 0;
}
.sum-label { font-size: 10px; letter-spacing:.08em; text-transform:uppercase; font-weight:600; color:var(--text-light); }
.sum-value { font-family:'Cormorant Garamond',serif; font-size:26px; font-weight:700; color:var(--text-main); line-height:1; }
.sum-card.gold-card {
    background: linear-gradient(135deg, #2a1f0e 0%, #3d2d10 60%, #2a1f0e 100%);
    border-color: var(--gold);
}
.sum-card.gold-card .sum-label  { color: #c4a96a; }
.sum-card.gold-card .sum-value  { color: #f0c060; }

/* ── Filter panel ────────────────────────────────────── */
.filter-panel {
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    box-shadow: var(--shadow);
    padding: 18px 20px;
}
.filter-input {
    width: 100%;
    border: 1.5px solid var(--border);
    border-radius: 8px;
    padding: 8px 12px;
    font-size: 13px;
    font-family: 'DM Sans', sans-serif;
    color: var(--text-main);
    background: #faf9f7;
    transition: border-color .2s, box-shadow .2s;
    outline: none;
}
.filter-input:focus {
    border-color: var(--gold);
    box-shadow: 0 0 0 3px rgba(200,151,65,.12);
}
.filter-label { font-size: 11px; font-weight: 600; color: var(--text-muted); margin-bottom:4px; letter-spacing:.04em; }

/* ── Table wrapper ───────────────────────────────────── */
.table-card {
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    box-shadow: var(--shadow);
    overflow: hidden;
}
.tbl { width: 100%; border-collapse: separate; border-spacing: 0; font-size: 13px; }
.tbl thead tr { background: var(--gold); }
.tbl thead th {
    padding: 13px 16px;
    color: #fff;
    font-weight: 600;
    font-size: 12px;
    letter-spacing: .04em;
    text-transform: uppercase;
    white-space: nowrap;
    text-align: left;
}
.tbl thead th:first-child { border-radius: 0; }
.tbl tbody tr {
    border-bottom: 1px solid #f0ebe0;
    transition: background .15s;
}
.tbl tbody tr:last-child { border-bottom: none; }
.tbl tbody tr:hover { background: #fdf8ee; }
.tbl tbody tr:nth-child(even) { background: #fafaf8; }
.tbl tbody tr:nth-child(even):hover { background: #fdf8ee; }
.tbl td { padding: 11px 16px; color: var(--text-main); vertical-align: middle; white-space: nowrap; }

/* ── Status Badges ───────────────────────────────────── */
.badge {
    display: inline-flex; align-items: center; gap: 4px;
    padding: 3px 10px; border-radius: 50px;
    font-size: 11px; font-weight: 700; letter-spacing:.04em; text-transform:uppercase;
}
.badge::before { content:''; width:5px; height:5px; border-radius:50%; flex-shrink:0; }
.badge-pending  { background:#fef3c7; color:#92400e; } .badge-pending::before  { background:#f59e0b; }
.badge-approved { background:#dbeafe; color:#1e40af; } .badge-approved::before { background:#3b82f6; }
.badge-delivered{ background:#dcfce7; color:#166534; } .badge-delivered::before{ background:#22c55e; }
.badge-rejected { background:#fee2e2; color:#991b1b; } .badge-rejected::before { background:#ef4444; }

/* ── Action Buttons ──────────────────────────────────── */
.btn { display:inline-flex; align-items:center; gap:5px; padding:5px 11px; border-radius:6px; font-size:12px; font-weight:600; cursor:pointer; transition:all .15s; border:none; }
.btn-approve  { background:#dbeafe; color:#1e40af; } .btn-approve:hover  { background:#bfdbfe; }
.btn-reject   { background:#fee2e2; color:#991b1b; } .btn-reject:hover   { background:#fecaca; }
.btn-deliver  { background:#dcfce7; color:#166534; } .btn-deliver:hover  { background:#bbf7d0; }
.btn-view     { background:#f5eee3; color:#9e7428; } .btn-view:hover     { background:#ead9b8; }
.btn-gold     { background:var(--gold); color:#fff; } .btn-gold:hover     { background:var(--gold-dark); }
.btn-outline  { background:#fff; border:1.5px solid var(--border); color:var(--text-muted); }
.btn-outline:hover { border-color:var(--gold); color:var(--gold-dark); background:var(--gold-light); }

/* ── Pagination ──────────────────────────────────────── */
.pg-btn {
    display:inline-flex; align-items:center; justify-content:center;
    width:32px; height:32px; border-radius:7px; font-size:12px; font-weight:600;
    border: 1.5px solid var(--border); background:#fff; color:var(--text-muted); cursor:pointer; transition:all .15s;
}
.pg-btn:hover  { border-color:var(--gold); color:var(--gold-dark); background:var(--gold-light); }
.pg-btn.active { background:var(--gold); border-color:var(--gold); color:#fff; }
.pg-btn.disabled { opacity:.4; cursor:not-allowed; pointer-events:none; }

/* ── Modal ───────────────────────────────────────────── */
.modal-overlay {
    position:fixed; inset:0; background:rgba(20,15,8,.55); backdrop-filter:blur(3px);
    display:flex; align-items:center; justify-content:center; z-index:9999; padding:16px;
    opacity:0; pointer-events:none; transition:opacity .25s;
}
.modal-overlay.open { opacity:1; pointer-events:all; }
.modal-box {
    background:#fff; border-radius:18px; box-shadow:0 24px 80px rgba(0,0,0,.2);
    width:100%; max-width:520px; max-height:90vh; overflow-y:auto;
    transform:scale(.95) translateY(12px); transition:transform .25s, opacity .25s; opacity:0;
}
.modal-overlay.open .modal-box { transform:scale(1) translateY(0); opacity:1; }
.modal-header {
    background:linear-gradient(135deg,#2a1f0e,#3d2d10);
    border-radius:18px 18px 0 0; padding:18px 22px;
    display:flex; align-items:center; justify-content:space-between;
}

/* ── Confirm Dialog ──────────────────────────────────── */
.confirm-overlay {
    position:fixed; inset:0; background:rgba(20,15,8,.6); backdrop-filter:blur(4px);
    display:flex; align-items:center; justify-content:center; z-index:10000; padding:16px;
    opacity:0; pointer-events:none; transition:opacity .2s;
}
.confirm-overlay.open { opacity:1; pointer-events:all; }
.confirm-box {
    background:#fff; border-radius:16px; box-shadow:0 20px 60px rgba(0,0,0,.25);
    width:100%; max-width:380px; padding:28px 26px;
    transform:scale(.9); transition:transform .2s; text-align:center;
}
.confirm-overlay.open .confirm-box { transform:scale(1); }

/* ── Reject remarks textarea ─────────────────────────── */
.reject-area { display:none; margin-top:12px; }
.reject-area textarea {
    width:100%; border:1.5px solid var(--border); border-radius:8px; padding:8px 12px;
    font-size:13px; font-family:'DM Sans',sans-serif; resize:vertical; min-height:80px; outline:none;
}
.reject-area textarea:focus { border-color:var(--gold); box-shadow:0 0 0 3px rgba(200,151,65,.12); }

/* ── Toast ───────────────────────────────────────────── */
.toast {
    position:fixed; bottom:24px; right:24px; z-index:20000;
    background:#1e1a15; color:#fff; border-left:4px solid var(--gold);
    border-radius:10px; padding:12px 18px; font-size:13px; font-weight:500;
    box-shadow:0 8px 32px rgba(0,0,0,.3); max-width:320px;
    transform:translateY(80px); opacity:0; transition:all .3s ease;
}
.toast.show { transform:translateY(0); opacity:1; }
.toast.error { border-color:#ef4444; }
.toast.success { border-color:#22c55e; }

/* ── Loader ─────────────────────────────────────────── */
.loader {
    width:16px; height:16px; border:2.5px solid rgba(200,151,65,.3);
    border-top-color:var(--gold); border-radius:50%;
    animation:spin .7s linear infinite; display:inline-block;
}

/* ── Mobile adjustments ──────────────────────────────── */
@media (max-width:640px) {
    .tbl td, .tbl th { padding:10px 10px; }
    .sum-value { font-size:22px; }
}

/* ── Section heading ─────────────────────────────────── */
.sec-heading {
    display:flex; align-items:center; gap:10px; margin-bottom:0;
}
.sec-heading-icon {
    width:34px; height:34px; border-radius:8px; background:var(--gold-light);
    display:flex; align-items:center; justify-content:center; flex-shrink:0;
}
.sec-heading-line { flex:1; height:2px; background:linear-gradient(90deg,var(--gold),transparent); }

/* ── Scrollable table on mobile ─────────────────────── */
.table-scroll { overflow-x:auto; -webkit-overflow-scrolling:touch; }
</style>
</head>
<body>

<?php include __DIR__ . '/includes/sidebar.php'; ?>

<div class="main-wrap min-h-screen">
<?php include __DIR__ . '/includes/navbar.php'; ?>

<div class="px-3 sm:px-5 py-4 max-w-[1600px] mx-auto">

<!-- ── Page Heading ──────────────────────────────────────────── -->
<div class="sec-heading mt-2 sm:mt-3 mb-5">
    <div class="sec-heading-icon">
        <i data-lucide="gift" style="width:17px;height:17px;color:var(--gold);"></i>
    </div>
    <h1 style="font-family:'Cormorant Garamond',serif; font-size:22px; font-weight:700; color:#40352a; white-space:nowrap;">
        Reward Achievers Management
    </h1>
    <div class="sec-heading-line"></div>
    <div class="flex items-center gap-2 ml-2 flex-shrink-0">
        <a href="?<?php echo http_build_query(array_merge($_GET, ['export'=>'csv'])); ?>"
           class="btn btn-outline text-[11px]" title="Export CSV">
            <i data-lucide="download" style="width:12px;height:12px;"></i>
            <span class="hidden sm:inline">CSV</span>
        </a>
        <button onclick="printTable()" class="btn btn-outline text-[11px]" title="Print">
            <i data-lucide="printer" style="width:12px;height:12px;"></i>
            <span class="hidden sm:inline">Print</span>
        </button>
    </div>
</div>

<!-- ── Summary Cards ─────────────────────────────────────────── -->
<div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3 mb-5">
    <?php
    $cards = [
        ['label'=>'Total Rewards',  'val'=>$summary['Total'],     'icon'=>'gift',         'bg'=>'#f5eee3', 'ic'=>'#C89741'],
        ['label'=>'Pending',        'val'=>$summary['Pending'],   'icon'=>'clock',        'bg'=>'#fef3c7', 'ic'=>'#d97706'],
        ['label'=>'Approved',       'val'=>$summary['Approved'],  'icon'=>'check-circle', 'bg'=>'#dbeafe', 'ic'=>'#2563eb'],
        ['label'=>'Delivered',      'val'=>$summary['Delivered'], 'icon'=>'truck',        'bg'=>'#dcfce7', 'ic'=>'#16a34a'],
        ['label'=>'Total Budget',   'val'=>'₹'.number_format($summary['Budget'],2), 'icon'=>'indian-rupee', 'bg'=>'#2a1f0e', 'ic'=>'#f0c060', 'gold'=>true],
    ];
    foreach ($cards as $c):
    ?>
    <div class="sum-card fade-up <?php echo !empty($c['gold']) ? 'gold-card' : ''; ?>">
        <div class="sum-icon" style="background:<?php echo $c['bg']; ?>;">
            <i data-lucide="<?php echo $c['icon']; ?>" style="width:18px;height:18px;color:<?php echo $c['ic']; ?>;"></i>
        </div>
        <div>
            <div class="sum-label"><?php echo $c['label']; ?></div>
            <div class="sum-value"><?php echo $c['val']; ?></div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- ── Filters ───────────────────────────────────────────────── -->
<form method="GET" id="filterForm">
<div class="filter-panel mb-5 fade-up">
    <div class="flex items-center gap-2 mb-3">
        <i data-lucide="sliders-horizontal" style="width:15px;height:15px;color:var(--gold);"></i>
        <span style="font-size:13px;font-weight:600;color:var(--text-muted);">Filters</span>
    </div>
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
        <div>
            <div class="filter-label">Customer ID</div>
            <input type="text" name="cid" value="<?php echo htmlspecialchars($fCid); ?>" placeholder="Search..." class="filter-input">
        </div>
        <div>
            <div class="filter-label">Reward Name</div>
            <input type="text" name="rname" value="<?php echo htmlspecialchars($fName); ?>" placeholder="Search..." class="filter-input">
        </div>
        <div>
            <div class="filter-label">Club Number</div>
            <select name="club" class="filter-input">
                <option value="0">All Clubs</option>
                <?php foreach ($clubOptions as $co): ?>
                <option value="<?php echo $co; ?>" <?php echo ($fClub===$co)?'selected':''; ?>>Club <?php echo $co; ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <div class="filter-label">Status</div>
            <select name="status" class="filter-input">
                <option value="">All Status</option>
                <?php foreach (['Pending','Approved','Delivered','Rejected'] as $s): ?>
                <option value="<?php echo $s; ?>" <?php echo ($fStatus===$s)?'selected':''; ?>><?php echo $s; ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <div class="filter-label">From Date</div>
            <input type="date" name="from" value="<?php echo htmlspecialchars($fFrom); ?>" class="filter-input">
        </div>
        <div>
            <div class="filter-label">To Date</div>
            <input type="date" name="to" value="<?php echo htmlspecialchars($fTo); ?>" class="filter-input">
        </div>
    </div>
    <div class="flex items-center gap-2 mt-3">
        <button type="submit" class="btn btn-gold px-4 py-2 text-[12px]">
            <i data-lucide="search" style="width:13px;height:13px;"></i> Search
        </button>
        <a href="reward-achievers.php" class="btn btn-outline px-4 py-2 text-[12px]">
            <i data-lucide="rotate-ccw" style="width:13px;height:13px;"></i> Reset
        </a>
        <span class="ml-auto text-[12px] text-[var(--text-muted)]">
            Showing <strong><?php echo count($rows); ?></strong> of <strong><?php echo $totalRows; ?></strong> records
        </span>
    </div>
</div>
</form>

<!-- ── Table ─────────────────────────────────────────────────── -->
<div class="table-card fade-up" id="printSection">

    <!-- Print-only header -->
    <div class="hidden print:block px-6 py-4 border-b border-[#e4ddd0]">
        <h2 style="font-family:'Cormorant Garamond',serif;font-size:20px;font-weight:700;">Reward Achievers Report</h2>
        <p style="font-size:12px;color:#666;">Generated: <?php echo date('d M Y, h:i A'); ?></p>
    </div>

    <div class="table-scroll">
    <table class="tbl" id="rewardTable">
        <thead>
            <tr>
                <th style="border-radius:0 0 0 0;">Sr</th>
                <th>Customer ID</th>
                <th>Club No</th>
                <th>Reward Name</th>
                <th>Budget (₹)</th>
                <th>Achieved Date</th>
                <th>Approved Date</th>
                <th>Status</th>
                <th class="print:hidden">Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($rows)): ?>
            <tr>
                <td colspan="9" class="text-center py-16">
                    <div style="display:flex;flex-direction:column;align-items:center;gap:8px;color:var(--text-light);">
                        <i data-lucide="inbox" style="width:40px;height:40px;opacity:.35;"></i>
                        <p style="font-size:14px;font-weight:600;">No records found</p>
                        <p style="font-size:12px;opacity:.7;">Try adjusting your filters.</p>
                    </div>
                </td>
            </tr>
        <?php else: ?>
            <?php foreach ($rows as $i => $r):
                $sr     = $offset + $i + 1;
                $sClass = statusClass($r['status']);
            ?>
            <tr id="row-<?php echo $r['id']; ?>">
                <td style="color:var(--text-light);font-size:12px;font-weight:600;"><?php echo $sr; ?></td>
                <td>
                    <span style="font-weight:600;color:var(--gold-dark);"><?php echo htmlspecialchars($r['customer_id']); ?></span>
                </td>
                <td>
                    <span style="display:inline-flex;align-items:center;justify-content:center;width:26px;height:26px;border-radius:50%;background:var(--gold-light);color:var(--gold-dark);font-size:11px;font-weight:700;">
                        <?php echo (int)$r['club_no']; ?>
                    </span>
                </td>
                <td style="max-width:160px;">
                    <div style="font-weight:500;overflow:hidden;text-overflow:ellipsis;" title="<?php echo htmlspecialchars($r['reward_name']); ?>">
                        <?php echo htmlspecialchars($r['reward_name']); ?>
                    </div>
                </td>
                <td style="font-weight:700;color:var(--gold-dark);">
                    ₹<?php echo number_format($r['reward_budget'],2); ?>
                </td>
                <td style="color:var(--text-muted);font-size:12px;"><?php echo fmtDate($r['achieved_date']); ?></td>
                <td style="color:var(--text-muted);font-size:12px;"><?php echo fmtDate($r['approved_date']); ?></td>
                <td>
                    <span class="badge <?php echo $sClass; ?> status-badge-<?php echo $r['id']; ?>">
                        <?php echo htmlspecialchars($r['status']); ?>
                    </span>
                </td>
                <td class="print:hidden">
                    <div class="flex items-center gap-1.5 action-cell-<?php echo $r['id']; ?>">
                    <?php if ($r['status'] === 'Pending'): ?>
                        <button class="btn btn-approve" onclick="confirmAction('approve', <?php echo $r['id']; ?>)">
                            <i data-lucide="check" style="width:11px;height:11px;"></i> Approve
                        </button>
                        <button class="btn btn-reject" onclick="confirmAction('reject', <?php echo $r['id']; ?>)">
                            <i data-lucide="x" style="width:11px;height:11px;"></i> Reject
                        </button>
                    <?php elseif ($r['status'] === 'Approved'): ?>
                        <button class="btn btn-deliver" onclick="confirmAction('deliver', <?php echo $r['id']; ?>)">
                            <i data-lucide="truck" style="width:11px;height:11px;"></i> Delivered
                        </button>
                    <?php elseif ($r['status'] === 'Delivered'): ?>
                        <button class="btn btn-view" onclick="viewRecord(<?php echo $r['id']; ?>)">
                            <i data-lucide="eye" style="width:11px;height:11px;"></i> View
                        </button>
                    <?php else: ?>
                        <button class="btn btn-view" onclick="viewRecord(<?php echo $r['id']; ?>)">
                            <i data-lucide="eye" style="width:11px;height:11px;"></i> View
                        </button>
                    <?php endif; ?>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
    </div>
</div>

<!-- ── Pagination ────────────────────────────────────────────── -->
<?php if ($totalPages > 1): ?>
<div class="flex items-center justify-between mt-4 flex-wrap gap-3">
    <p style="font-size:12px;color:var(--text-muted);">
        Page <strong><?php echo $page; ?></strong> of <strong><?php echo $totalPages; ?></strong>
    </p>
    <div style="display:flex;gap:5px;flex-wrap:wrap;">
        <?php
        $qp = $_GET; unset($qp['page']);
        // Prev
        $prevClass = ($page <= 1) ? 'pg-btn disabled' : 'pg-btn';
        echo '<a href="?'.http_build_query(array_merge($qp,['page'=>$page-1])).'" class="'.$prevClass.'"><i data-lucide="chevron-left" style="width:14px;height:14px;"></i></a>';

        // Page numbers (window of 5)
        $start = max(1, $page - 2);
        $end   = min($totalPages, $page + 2);
        if ($start > 1) echo '<span class="pg-btn disabled">…</span>';
        for ($p2 = $start; $p2 <= $end; $p2++) {
            $cls = ($p2 === $page) ? 'pg-btn active' : 'pg-btn';
            echo '<a href="?'.http_build_query(array_merge($qp,['page'=>$p2])).'" class="'.$cls.'">'.$p2.'</a>';
        }
        if ($end < $totalPages) echo '<span class="pg-btn disabled">…</span>';

        // Next
        $nextClass = ($page >= $totalPages) ? 'pg-btn disabled' : 'pg-btn';
        echo '<a href="?'.http_build_query(array_merge($qp,['page'=>$page+1])).'" class="'.$nextClass.'"><i data-lucide="chevron-right" style="width:14px;height:14px;"></i></a>';
        ?>
    </div>
</div>
<?php endif; ?>

<div class="pb-8"></div>
</div><!-- /px -->
</div><!-- /main-wrap -->

<!-- ══════════════════════════════════════════════════════════════
     VIEW MODAL
══════════════════════════════════════════════════════════════ -->
<div class="modal-overlay" id="viewModal">
    <div class="modal-box">
        <div class="modal-header">
            <div style="display:flex;align-items:center;gap:10px;">
                <div style="width:34px;height:34px;border-radius:8px;background:rgba(200,151,65,.25);display:flex;align-items:center;justify-content:center;">
                    <i data-lucide="gift" style="width:16px;height:16px;color:#f0c060;"></i>
                </div>
                <div>
                    <p style="font-size:10px;letter-spacing:.1em;text-transform:uppercase;color:#c4a96a;font-weight:600;">Reward Details</p>
                    <p style="font-family:'Cormorant Garamond',serif;font-size:18px;font-weight:700;color:#fff;" id="modalTitle">—</p>
                </div>
            </div>
            <button onclick="closeModal('viewModal')" style="width:30px;height:30px;border-radius:7px;background:rgba(255,255,255,.1);display:flex;align-items:center;justify-content:center;cursor:pointer;border:none;">
                <i data-lucide="x" style="width:15px;height:15px;color:#fff;"></i>
            </button>
        </div>
        <div class="px-6 py-5" id="modalBody">
            <div style="display:flex;justify-content:center;padding:30px;"><span class="loader"></span></div>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════════
     CONFIRM DIALOG
══════════════════════════════════════════════════════════════ -->
<div class="confirm-overlay" id="confirmDialog">
    <div class="confirm-box">
        <div id="confirmIcon" style="width:52px;height:52px;border-radius:50%;margin:0 auto 14px;display:flex;align-items:center;justify-content:center;"></div>
        <h3 id="confirmTitle" style="font-family:'Cormorant Garamond',serif;font-size:20px;font-weight:700;color:var(--text-main);margin-bottom:6px;"></h3>
        <p id="confirmMsg" style="font-size:13px;color:var(--text-muted);margin-bottom:16px;line-height:1.5;"></p>

        <!-- Reject remarks (only for reject action) -->
        <div class="reject-area" id="rejectArea">
            <label class="filter-label" style="display:block;text-align:left;margin-bottom:4px;">Remarks (optional)</label>
            <textarea id="rejectRemarks" placeholder="Reason for rejection..."></textarea>
        </div>

        <div style="display:flex;gap:10px;justify-content:center;margin-top:6px;">
            <button id="confirmCancelBtn" onclick="closeModal('confirmDialog')" class="btn btn-outline px-5 py-2">Cancel</button>
            <button id="confirmOkBtn" class="btn btn-gold px-5 py-2">Confirm</button>
        </div>
    </div>
</div>

<!-- Toast -->
<div class="toast" id="toast"></div>

<!-- ══════════════════════════════════════════════════════════════
     JAVASCRIPT
══════════════════════════════════════════════════════════════ -->
<script>
lucide.createIcons();

// ── Modal helpers ──────────────────────────────────────────────
function openModal(id)  { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }

// Close on overlay click
['viewModal','confirmDialog'].forEach(id => {
    document.getElementById(id).addEventListener('click', function(e) {
        if (e.target === this) closeModal(id);
    });
});

// ── Toast ──────────────────────────────────────────────────────
function showToast(msg, type='success') {
    const t = document.getElementById('toast');
    t.textContent = msg;
    t.className = `toast ${type} show`;
    setTimeout(() => t.classList.remove('show'), 3500);
}

// ── View record ────────────────────────────────────────────────
function viewRecord(id) {
    document.getElementById('modalTitle').textContent = 'Loading…';
    document.getElementById('modalBody').innerHTML = '<div style="display:flex;justify-content:center;padding:30px;"><span class="loader"></span></div>';
    openModal('viewModal');

    fetch('?fetch_id=' + id)
        .then(r => r.json())
        .then(d => {
            if (!d || !d.id) { document.getElementById('modalBody').innerHTML = '<p style="text-align:center;color:#999;padding:20px;">Record not found.</p>'; return; }
            document.getElementById('modalTitle').textContent = d.reward_name || 'Reward Details';

            const statusClasses = {Pending:'badge-pending',Approved:'badge-approved',Delivered:'badge-delivered',Rejected:'badge-rejected'};
            const sc = statusClasses[d.status] || 'badge-pending';

            document.getElementById('modalBody').innerHTML = `
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
                ${detailRow('Customer ID', d.customer_id, true)}
                ${detailRow('Club Number', 'Club ' + d.club_no)}
                ${detailRow('Reward Name', d.reward_name)}
                ${detailRow('Reward Budget', '₹' + parseFloat(d.reward_budget).toLocaleString('en-IN',{minimumFractionDigits:2}))}
                ${detailRow('Achieved Date', fmtDate(d.achieved_date))}
                ${detailRow('Approved Date', fmtDate(d.approved_date))}
                ${detailRow('Created At', fmtDate(d.created_at))}
                <div style="display:flex;flex-direction:column;gap:3px;">
                    <span style="font-size:10px;letter-spacing:.06em;text-transform:uppercase;color:#b5a898;font-weight:600;">Status</span>
                    <span class="badge ${sc}">${d.status}</span>
                </div>
            </div>
            ${d.remarks ? `
            <div style="margin-top:14px;padding:12px;background:#faf9f7;border-radius:8px;border:1px solid #e4ddd0;">
                <p style="font-size:10px;text-transform:uppercase;letter-spacing:.06em;font-weight:600;color:#b5a898;margin-bottom:4px;">Remarks</p>
                <p style="font-size:13px;color:#3d3228;">${escHtml(d.remarks)}</p>
            </div>` : ''}
            `;
        });
}

function detailRow(label, value, gold=false) {
    return `<div style="display:flex;flex-direction:column;gap:3px;">
        <span style="font-size:10px;letter-spacing:.06em;text-transform:uppercase;color:#b5a898;font-weight:600;">${label}</span>
        <span style="font-size:13px;font-weight:600;color:${gold?'#9e7428':'#1e1a15'};">${escHtml(String(value||'—'))}</span>
    </div>`;
}
function fmtDate(d) {
    if (!d || d === '0000-00-00' || d === 'null') return '—';
    const dt = new Date(d); if (isNaN(dt)) return '—';
    return dt.toLocaleDateString('en-IN',{day:'2-digit',month:'short',year:'numeric'});
}
function escHtml(s) {
    return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// ── Confirm action ─────────────────────────────────────────────
let pendingAction = null;
let pendingId     = null;

const actionConfig = {
    approve: {
        title: 'Approve Reward?',
        msg: 'Are you sure you want to approve this reward? The approved date will be set to today.',
        icon: '✓', iconBg: '#dbeafe', iconColor: '#2563eb',
        btnClass: 'btn-approve', btnText: 'Yes, Approve'
    },
    reject: {
        title: 'Reject Reward?',
        msg: 'Are you sure you want to reject this reward? You can optionally provide a reason below.',
        icon: '✕', iconBg: '#fee2e2', iconColor: '#dc2626',
        btnClass: 'btn-reject', btnText: 'Yes, Reject', showRemarks: true
    },
    deliver: {
        title: 'Mark as Delivered?',
        msg: 'Confirm that this reward has been physically delivered to the member.',
        icon: '🚚', iconBg: '#dcfce7', iconColor: '#16a34a',
        btnClass: 'btn-deliver', btnText: 'Yes, Delivered'
    }
};

function confirmAction(action, id) {
    pendingAction = action;
    pendingId     = id;
    const cfg = actionConfig[action];

    document.getElementById('confirmTitle').textContent = cfg.title;
    document.getElementById('confirmMsg').textContent   = cfg.msg;

    const iconEl = document.getElementById('confirmIcon');
    iconEl.style.background = cfg.iconBg;
    iconEl.textContent = cfg.icon;
    iconEl.style.fontSize = '20px';

    const okBtn = document.getElementById('confirmOkBtn');
    okBtn.className = `btn ${cfg.btnClass} px-5 py-2`;
    okBtn.textContent = cfg.btnText;

    const ra = document.getElementById('rejectArea');
    ra.style.display = cfg.showRemarks ? 'block' : 'none';
    document.getElementById('rejectRemarks').value = '';

    openModal('confirmDialog');
}

document.getElementById('confirmOkBtn').addEventListener('click', function() {
    if (!pendingAction || !pendingId) return;
    closeModal('confirmDialog');
    executeAction(pendingAction, pendingId);
});

function executeAction(action, id) {
    // Show spinner in action cell
    const cell = document.querySelector('.action-cell-' + id);
    if (cell) cell.innerHTML = '<span class="loader"></span>';

    const body = new FormData();
    body.append('action', action);
    body.append('id', id);
    if (action === 'reject') {
        body.append('remarks', document.getElementById('rejectRemarks').value);
    }

    fetch(window.location.pathname, { method:'POST', body })
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                showToast(d.msg, 'success');
                setTimeout(() => window.location.reload(), 800);
            } else {
                showToast(d.msg || 'Action failed.', 'error');
                if (cell) cell.innerHTML = '<button class="btn btn-outline text-[11px]" onclick="location.reload()">Refresh</button>';
            }
        })
        .catch(() => {
            showToast('Network error. Please try again.', 'error');
        });
}

// ── Print ──────────────────────────────────────────────────────
function printTable() {
    const style = `
        <style>
            body{font-family:sans-serif;font-size:12px;color:#111;}
            table{width:100%;border-collapse:collapse;}
            th{background:#C89741;color:#fff;padding:8px 10px;text-align:left;font-size:11px;}
            td{padding:7px 10px;border-bottom:1px solid #e4ddd0;}
            tr:nth-child(even) td{background:#faf9f7;}
            .badge{display:inline-block;padding:2px 8px;border-radius:50px;font-size:10px;font-weight:700;}
            .badge-pending{background:#fef3c7;color:#92400e;}
            .badge-approved{background:#dbeafe;color:#1e40af;}
            .badge-delivered{background:#dcfce7;color:#166534;}
            .badge-rejected{background:#fee2e2;color:#991b1b;}
            h2{font-size:18px;margin-bottom:4px;}p{font-size:11px;color:#666;margin-bottom:16px;}
        </style>`;
    const section = document.getElementById('printSection').cloneNode(true);
    // Remove action column cells
    section.querySelectorAll('.print\\:hidden').forEach(el => el.remove());
    section.querySelectorAll('[class*="action-cell"]').forEach(el => el.remove());
    const w = window.open('','_blank','width=900,height=600');
    w.document.write(`<!DOCTYPE html><html><head>${style}</head><body>
        <h2>Reward Achievers Report</h2>
        <p>Generated: ${new Date().toLocaleString('en-IN')}</p>
        ${section.innerHTML}</body></html>`);
    w.document.close();
    w.focus();
    setTimeout(() => { w.print(); w.close(); }, 400);
}
</script>
</body>
</html>