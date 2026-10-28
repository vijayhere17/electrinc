<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['customer_id']) && !isset($_SESSION['member_id'])) {
    header("Location: login.php");
    exit();
}

$memberName   = $_SESSION['full_name'] ?? "Member";
$customerId   = $_SESSION['customer_id'] ?? $_SESSION['member_id'];
$navTitle     = "Referral Bonus";
$navIcon      = "gift";

include __DIR__ . '/config/db.php';

if (!isset($conn) || !($conn instanceof mysqli)) {
    die("Database connection failed.");
}
$conn->set_charset("utf8mb4");

/* ─── ensure table & columns ─────────────────────────────── */
function columnExists($conn, $table, $column) {
    $stmt = $conn->prepare("SELECT COUNT(*) AS c FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?");
    $stmt->bind_param("ss", $table, $column);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return !empty($row['c']);
}
function ensureColumn($conn, $table, $column, $definition) {
    if (!columnExists($conn, $table, $column)) {
        $conn->query("ALTER TABLE `$table` ADD COLUMN `$column` $definition");
    }
}

$conn->query("
    CREATE TABLE IF NOT EXISTS referral_bonus_income_report (
        id          INT AUTO_INCREMENT PRIMARY KEY,
        customer_id VARCHAR(50)   NOT NULL,
        report_date DATE          DEFAULT CURRENT_DATE,
        club_no     INT           DEFAULT 1,
        description VARCHAR(255)  DEFAULT 'Referral Bonus',
        income_type VARCHAR(50)   DEFAULT 'Referral',
        credit      DECIMAL(12,2) DEFAULT 0,
        debit       DECIMAL(12,2) DEFAULT 0,
        balance     DECIMAL(12,2) DEFAULT 0,
        status      VARCHAR(50)   DEFAULT 'Pending',
        created_at  DATETIME      DEFAULT CURRENT_TIMESTAMP
    )
");
ensureColumn($conn, 'referral_bonus_income_report', 'club_no',     "INT DEFAULT 1");
ensureColumn($conn, 'referral_bonus_income_report', 'income_type', "VARCHAR(50) DEFAULT 'Referral'");

/* ─── filter inputs ──────────────────────────────────────── */
$filterStatus = $_GET['status']      ?? '';
$filterType   = $_GET['income_type'] ?? '';
$filterClub   = $_GET['club_no']     ?? '';
$filterFrom   = $_GET['date_from']   ?? '';
$filterTo     = $_GET['date_to']     ?? '';

/* ─── build WHERE ────────────────────────────────────────── */
$where  = ["customer_id = ?"];
$params = [$customerId];
$types  = "s";

if ($filterStatus !== '') { $where[] = "status = ?";      $params[] = $filterStatus; $types .= "s"; }
if ($filterType   !== '') { $where[] = "income_type = ?"; $params[] = $filterType;   $types .= "s"; }
if ($filterClub   !== '') { $where[] = "club_no = ?";     $params[] = (int)$filterClub; $types .= "i"; }
if ($filterFrom   !== '') { $where[] = "report_date >= ?";$params[] = $filterFrom;   $types .= "s"; }
if ($filterTo     !== '') { $where[] = "report_date <= ?";$params[] = $filterTo;     $types .= "s"; }

$whereStr = implode(' AND ', $where);

/* ─── fetch rows ─────────────────────────────────────────── */
$rows       = [];
$totalRef   = 0;
$totalL1    = 0;
$totalL2    = 0;
$srCounter  = 1;

$sql = "SELECT id, report_date, club_no, description, income_type, credit, debit, balance, status
        FROM referral_bonus_income_report
        WHERE $whereStr
        ORDER BY id DESC";

$stmt = $conn->prepare($sql);
if ($stmt) {
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $res = $stmt->get_result();
   while ($r = $res->fetch_assoc()) {

    $c = (float)$r['credit'];
    $t = trim($r['income_type']);

    if (
        $t == 'Referral' ||
        $t == 'Direct Referral'
    ){
        $totalRef += $c;
    }

    if (
        $t == 'Senior1' ||
        $t == 'Level 1' ||
        $t == 'Senior 1'
    ){
        $totalL1 += $c;
    }

    if (
        $t == 'Senior2' ||
        $t == 'Level 2' ||
        $t == 'Senior 2'
    ){
        $totalL2 += $c;
    }
        $rows[] = [
            'sr'          => $srCounter++,
            'date'        => !empty($r['report_date']) ? date('d-m-Y', strtotime($r['report_date'])) : '-',
            'club_no'     => (int)$r['club_no'],
            'description' => $r['description'] ?: 'Referral Bonus',
            'income_type' => $r['income_type'] ?: 'Referral',
            'credit'      => $c,
            'debit'       => (float)$r['debit'],
            'balance'     => (float)$r['balance'],
            'status'      => $r['status'] ?: 'Pending',
        ];
    }
    $stmt->close();
}

$totalAll = $totalRef + $totalL1 + $totalL2;

/* ─── club color map ─────────────────────────────────────── */
$clubColors = [
    1=>'#C89741',2=>'#9b59b6',3=>'#e74c3c',4=>'#2ecc71',
    5=>'#3498db',6=>'#e67e22',7=>'#1abc9c',8=>'#e91e63',
    9=>'#00bcd4',10=>'#ff5722',11=>'#6c3483',12=>'#1a5276',
    13=>'#117a65',14=>'#784212',15=>'#2e4057',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Referral Bonus Report</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        :root {
            --gold:       #C89741;
            --gold-light: #f5eee3;
            --gold-dark:  #9e7428;
            --bg:         #f3f3f3;
        }
        body { background: var(--bg); }

        @media (min-width: 1024px) { .main-wrap { margin-left: 256px; } }

        /* ── type badge colors ── */
        .type-referral { background:#fff3e0; color:#b45309; }
        .type-senior1  { background:#e8f4fd; color:#1565c0; }
        .type-senior2  { background:#f3e8ff; color:#6b21a8; }

        /* ── status chips ── */
        .chip-credit   { background:#e8f7ee; color:#1e7a48; }
        .chip-pending  { background:#fef7e0; color:#b07d00; }
        .chip-debit    { background:#fde8e8; color:#9b1c1c; }

        /* ── summary card hover ── */
        .sum-card { transition: box-shadow .2s, transform .2s; }
        .sum-card:hover { box-shadow:0 6px 24px rgba(200,151,65,.18); transform:translateY(-2px); }

        /* ── table row hover ── */
        .data-row { transition: background .15s; }
        .data-row:hover { background:#fdf6ea !important; }

        /* ── mobile card ── */
        .mob-card { background:#fff; border:1px solid #e8e0d0; border-radius:14px; padding:14px 16px; }
        .mob-card + .mob-card { margin-top:10px; }
        .mob-label { font-size:10px; text-transform:uppercase; letter-spacing:.06em; color:#9e8f7e; font-weight:600; margin-bottom:2px; }
        .mob-value { font-size:13px; color:#2f2f2f; font-weight:500; }

        /* ── filter section ── */
        .filter-wrap select, .filter-wrap input {
            border:1px solid #e2d8c8;
            border-radius:8px;
            padding:7px 10px;
            font-size:13px;
            background:#fff;
            color:#3d3228;
            outline:none;
            width:100%;
        }
        .filter-wrap select:focus, .filter-wrap input:focus {
            border-color:#C89741;
            box-shadow:0 0 0 2px rgba(200,151,65,.15);
        }
    </style>
</head>
<body class="bg-[#f3f3f3]">

<?php include __DIR__ . '/includes/sidebar.php'; ?>

<div class="main-wrap min-h-screen">
    <?php include __DIR__ . '/includes/navbar.php'; ?>

    <div class="px-3 sm:px-5 py-3">

        <!-- ── PAGE TITLE ──────────────────────────────────── -->
        <div class="mt-4 sm:mt-5 flex items-center gap-3">
            <div class="w-[34px] h-[34px] rounded-[8px] bg-[#f5eee3] flex items-center justify-center shrink-0">
                <i data-lucide="gift" class="w-[17px] h-[17px] text-[#cd9737]"></i>
            </div>
            <h2 class="whitespace-nowrap text-[20px] font-normal text-[#40352a]" style="font-family: Georgia, 'Times New Roman', serif;">
                    REFERRAL BONUS
                </h2>
            <div class="w-full h-[2px] bg-[#d1a14b]"></div>
        </div>

        <!-- ── SUMMARY CARDS ───────────────────────────────── -->
        <div class="mt-4 sm:mt-5 grid grid-cols-2 lg:grid-cols-4 gap-3">

            <!-- Total Referral Income -->
            <div class="sum-card bg-white rounded-2xl border border-[#e2d8c8] px-4 py-3 sm:px-5 sm:py-4">
                <div class="flex items-center gap-2 mb-1">
                    <div class="w-8 h-8 rounded-lg bg-[#fff3e0] flex items-center justify-center shrink-0">
                        <i data-lucide="user-plus" class="w-4 h-4 text-[#b45309]"></i>
                    </div>
                    <p class="text-[10px] sm:text-[11px] uppercase tracking-widest text-[#9e8f7e] font-semibold leading-tight">Referral Income</p>
                </div>
                <p class="text-[17px] sm:text-[20px] font-bold text-[#2a2218]">₹<?php echo number_format($totalRef, 2); ?></p>
            </div>

            <!-- Level 1 Income -->
            <div class="sum-card bg-white rounded-2xl border border-[#e2d8c8] px-4 py-3 sm:px-5 sm:py-4">
                <div class="flex items-center gap-2 mb-1">
                    <div class="w-8 h-8 rounded-lg bg-[#e8f4fd] flex items-center justify-center shrink-0">
                        <i data-lucide="layers" class="w-4 h-4 text-[#1565c0]"></i>
                    </div>
                    <p class="text-[10px] sm:text-[11px] uppercase tracking-widest text-[#9e8f7e] font-semibold leading-tight">Level 1 Income</p>
                </div>
                <p class="text-[17px] sm:text-[20px] font-bold text-[#2a2218]">₹<?php echo number_format($totalL1, 2); ?></p>
            </div>

            <!-- Level 2 Income -->
            <div class="sum-card bg-white rounded-2xl border border-[#e2d8c8] px-4 py-3 sm:px-5 sm:py-4">
                <div class="flex items-center gap-2 mb-1">
                    <div class="w-8 h-8 rounded-lg bg-[#f3e8ff] flex items-center justify-center shrink-0">
                        <i data-lucide="layers-3" class="w-4 h-4 text-[#6b21a8]"></i>
                    </div>
                    <p class="text-[10px] sm:text-[11px] uppercase tracking-widest text-[#9e8f7e] font-semibold leading-tight">Level 2 Income</p>
                </div>
                <p class="text-[17px] sm:text-[20px] font-bold text-[#2a2218]">₹<?php echo number_format($totalL2, 2); ?></p>
            </div>

            <!-- Total Earned -->
            <div class="sum-card col-span-2 lg:col-span-1 rounded-2xl px-4 py-3 sm:px-5 sm:py-4"
                 style="background:linear-gradient(135deg,#f5eee3 0%,#fff9ef 100%); border:2px solid #C89741;">
                <div class="flex items-center gap-2 mb-1">
                    <div class="w-8 h-8 rounded-lg bg-[#C89741] flex items-center justify-center shrink-0">
                        <i data-lucide="indian-rupee" class="w-4 h-4 text-white"></i>
                    </div>
                    <p class="text-[10px] sm:text-[11px] uppercase tracking-widest text-[#9e8f7e] font-semibold leading-tight">Total Earned</p>
                </div>
                <p class="text-[17px] sm:text-[20px] font-bold text-[#C89741]">₹<?php echo number_format($totalAll, 2); ?></p>
            </div>
        </div>

        <!-- ── FILTERS ──────────────────────────────────────── -->
        <div class="mt-4 bg-white rounded-2xl border border-[#e2d8c8] px-4 sm:px-5 py-4">
            <div class="flex items-center gap-2 mb-3">
                <i data-lucide="sliders-horizontal" class="w-4 h-4 text-[#C89741]"></i>
                <span class="text-[13px] font-semibold text-[#3d3228]">Filters</span>
            </div>
            <form method="GET" class="filter-wrap">
                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">

                    <!-- Income Type -->
                    <div>
                        <label class="mob-label mb-1 block">Income Type</label>
                        <select name="income_type">
                            <option value="">All Types</option>
                            <option value="Referral" <?php echo $filterType==='Referral'?'selected':''; ?>>Referral</option>
                            <option value="Senior1"  <?php echo $filterType==='Senior1' ?'selected':''; ?>>Level 1 Senior</option>
                            <option value="Senior2"  <?php echo $filterType==='Senior2' ?'selected':''; ?>>Level 2 Senior</option>
                        </select>
                    </div>

                 
                    <!-- Club No -->
                    <div>
                        <label class="mob-label mb-1 block">Club No</label>
                        <select name="club_no">
                            <option value="">All Clubs</option>
                            <?php for ($c=1;$c<=15;$c++): ?>
                            <option value="<?php echo $c; ?>" <?php echo $filterClub==(string)$c?'selected':''; ?>>Club <?php echo $c; ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>

                    <!-- Date From -->
                    <!--<div>-->
                    <!--    <label class="mob-label mb-1 block">Date From</label>-->
                    <!--    <input type="date" name="date_from" value="<?php echo htmlspecialchars($filterFrom); ?>">-->
                    <!--</div>-->

                    <!-- Date To -->
                    <!--<div>-->
                    <!--    <label class="mob-label mb-1 block">Date To</label>-->
                    <!--    <input type="date" name="date_to" value="<?php echo htmlspecialchars($filterTo); ?>">-->
                    <!--</div>-->

                    <!-- Buttons -->
                    <div class="flex items-end gap-2">
                        <button type="submit"
                                class="flex-1 bg-[#C89741] hover:bg-[#a87c30] text-white text-[12px] font-semibold rounded-[8px] px-3 py-[9px] transition-colors">
                            Apply
                        </button>
                        <a href="referral-bonus.php"
                           class="flex-1 bg-[#f5eee3] hover:bg-[#eedfc8] text-[#7a5c28] text-[12px] font-semibold rounded-[8px] px-3 py-[9px] text-center transition-colors">
                            Reset
                        </a>
                    </div>

                </div>
            </form>
        </div>

        <!-- ── TABLE + MOBILE CARDS ─────────────────────────── -->
        <div class="mt-4 rounded-[14px] border border-[#dddddd] bg-[#f7f7f7] p-3 sm:p-4">

            <?php if (count($rows) > 0): ?>

            <!-- DESKTOP TABLE -->
            <div class="hidden sm:block overflow-x-auto">
                <table class="w-full border-separate border-spacing-0">
                    <thead>
                        <tr class="bg-[#C89741] text-white text-[13px] font-semibold">
                            <th class="px-4 py-4 text-center rounded-l-[10px] min-w-[60px]">Sr No</th>
                            <th class="px-4 py-4 text-left min-w-[110px]">Date</th>
                            <th class="px-4 py-4 text-center min-w-[80px]">Club</th>
                            <th class="px-4 py-4 text-left min-w-[160px]">Description</th>
                            <th class="px-4 py-4 text-center min-w-[130px]">Income Type</th>
                            <th class="px-4 py-4 text-right min-w-[110px]">Credit (₹)</th>
                            <th class="px-4 py-4 text-right min-w-[110px]">Debit (₹)</th>
                            <th class="px-4 py-4 text-right min-w-[120px]">Balance (₹)</th>
                            <th class="px-4 py-4 text-center rounded-r-[10px] min-w-[100px]">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $i => $r):
                            $s = strtolower($r['status']);
                            $chipCls = str_contains($s,'credit') ? 'chip-credit' : (str_contains($s,'debit') ? 'chip-debit' : 'chip-pending');
                            $typeCls = $r['income_type']==='Referral' ? 'type-referral' : ($r['income_type']==='Senior1' ? 'type-senior1' : 'type-senior2');
                            $typeLabel = $r['income_type']==='Referral' ? 'Referral' : ($r['income_type']==='Senior1' ? 'Level 1' : 'Level 2');
                            $clubColor = $clubColors[$r['club_no']] ?? '#C89741';
                            $rowBg = ($i%2===0) ? 'bg-white' : 'bg-[#fdfaf4]';
                        ?>
                        <tr class="data-row text-[13px] text-[#2f2f2f] <?php echo $rowBg; ?>">
                            <td class="px-4 py-3.5 border-b border-[#e8e0d0] align-middle text-center text-[#9e8f7e]">
                                <?php echo $r['sr']; ?>
                            </td>
                            <td class="px-4 py-3.5 border-b border-[#e8e0d0] align-middle text-[#555]">
                                <?php echo htmlspecialchars($r['date']); ?>
                            </td>
                            <td class="px-4 py-3.5 border-b border-[#e8e0d0] align-middle text-center">
                                <span class="inline-flex items-center justify-center w-7 h-7 rounded-full text-[11px] font-bold text-white"
                                      style="background:<?php echo $clubColor; ?>;">
                                    <?php echo $r['club_no']; ?>
                                </span>
                            </td>
                            <td class="px-4 py-3.5 border-b border-[#e8e0d0] align-middle font-medium text-[#393939]">
                                <?php echo htmlspecialchars($r['description']); ?>
                            </td>
                            <td class="px-4 py-3.5 border-b border-[#e8e0d0] align-middle text-center">
                                <span class="<?php echo $typeCls; ?> px-2.5 py-1 rounded-full text-[11px] font-semibold">
                                    <?php echo $typeLabel; ?>
                                </span>
                            </td>
                            <td class="px-4 py-3.5 border-b border-[#e8e0d0] align-middle text-right font-semibold text-green-700">
                                <?php echo $r['credit'] > 0 ? '₹'.number_format($r['credit'],2) : '-'; ?>
                            </td>
                            <td class="px-4 py-3.5 border-b border-[#e8e0d0] align-middle text-right font-semibold text-red-600">
                                <?php echo $r['debit'] > 0 ? '₹'.number_format($r['debit'],2) : '-'; ?>
                            </td>
                            <td class="px-4 py-3.5 border-b border-[#e8e0d0] align-middle text-right font-semibold text-[#2a2218]">
                                ₹<?php echo number_format($r['balance'],2); ?>
                            </td>
                            <td class="px-4 py-3.5 border-b border-[#e8e0d0] align-middle text-center">
                                <span class="<?php echo $chipCls; ?> px-2.5 py-1 rounded-full text-[11px] font-semibold uppercase tracking-wide">
                                    <?php echo htmlspecialchars($r['status']); ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>

                        <!-- TOTAL ROW -->
                        <tr class="text-[13px] font-bold">
                            <td colspan="5" class="px-4 py-4 rounded-bl-[10px] text-right text-[#3d3228] uppercase tracking-wider bg-[#f5eee3] border-t-2 border-[#C89741]">
                                Total Earned
                            </td>
                            <td class="px-4 py-4 text-right text-green-700 text-[14px] bg-[#f5eee3] border-t-2 border-[#C89741]">
                                ₹<?php echo number_format($totalRef+$totalL1+$totalL2, 2); ?>
                            </td>
                            <td class="px-4 py-4 text-right text-[#C89741] text-[14px] bg-[#f5eee3] border-t-2 border-[#C89741]"></td>
                            <td colspan="2" class="px-4 py-4 rounded-br-[10px] bg-[#f5eee3] border-t-2 border-[#C89741]"></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- MOBILE CARDS -->
            <div class="block sm:hidden">
                <?php foreach ($rows as $r):
                    $s = strtolower($r['status']);
                    $chipCls  = str_contains($s,'credit') ? 'chip-credit' : (str_contains($s,'debit') ? 'chip-debit' : 'chip-pending');
                    $typeCls  = $r['income_type']==='Referral' ? 'type-referral' : ($r['income_type']==='Senior1' ? 'type-senior1' : 'type-senior2');
                    $typeLabel= $r['income_type']==='Referral' ? 'Referral' : ($r['income_type']==='Senior1' ? 'Level 1' : 'Level 2');
                    $clubColor= $clubColors[$r['club_no']] ?? '#C89741';
                ?>
                <div class="mob-card">
                    <!-- Top row -->
                    <div class="flex items-center justify-between mb-3">
                        <div class="flex items-center gap-2">
                            <span class="inline-flex items-center justify-center w-8 h-8 rounded-full text-[11px] font-bold text-white shrink-0"
                                  style="background:<?php echo $clubColor; ?>;">
                                <?php echo $r['club_no']; ?>
                            </span>
                            <div>
                                <p class="mob-label">Club / Sr</p>
                                <p class="text-[12px] font-semibold text-[#2a2218]">Club <?php echo $r['club_no']; ?> &nbsp;·&nbsp; #<?php echo $r['sr']; ?></p>
                            </div>
                        </div>
                        <div class="flex flex-col items-end gap-1">
                            <span class="<?php echo $chipCls; ?> px-2.5 py-0.5 rounded-full text-[10px] font-semibold uppercase">
                                <?php echo htmlspecialchars($r['status']); ?>
                            </span>
                            <span class="<?php echo $typeCls; ?> px-2.5 py-0.5 rounded-full text-[10px] font-semibold">
                                <?php echo $typeLabel; ?>
                            </span>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-x-4 gap-y-2.5">
                        <div>
                            <p class="mob-label">Date</p>
                            <p class="mob-value"><?php echo htmlspecialchars($r['date']); ?></p>
                        </div>
                        <div>
                            <p class="mob-label">Credit</p>
                            <p class="mob-value font-bold text-green-700">
                                <?php echo $r['credit']>0 ? '₹'.number_format($r['credit'],2) : '-'; ?>
                            </p>
                        </div>
                        <div>
                            <p class="mob-label">Debit</p>
                            <p class="mob-value font-semibold text-red-600">
                                <?php echo $r['debit']>0 ? '₹'.number_format($r['debit'],2) : '-'; ?>
                            </p>
                        </div>
                        <div>
                            <p class="mob-label">Balance</p>
                            <p class="mob-value font-bold text-[#C89741]">₹<?php echo number_format($r['balance'],2); ?></p>
                        </div>
                        <div class="col-span-2">
                            <p class="mob-label">Description</p>
                            <p class="mob-value text-[#555]"><?php echo htmlspecialchars($r['description']); ?></p>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>

                <!-- Mobile total -->
                <div class="mt-3 rounded-xl bg-[#f5eee3] border-2 border-[#C89741] px-4 py-3">
                    <div class="flex justify-between items-center">
                        <p class="text-[11px] font-bold text-[#3d3228] uppercase tracking-wider">Total Earned</p>
                        <p class="text-[16px] font-bold text-[#C89741]">₹<?php echo number_format($totalAll,2); ?></p>
                    </div>
                    <div class="mt-2 pt-2 border-t border-[#e2d8c8] grid grid-cols-3 gap-2 text-center">
                        <div>
                            <p class="text-[9px] uppercase tracking-wide text-[#9e8f7e] font-semibold">Referral</p>
                            <p class="text-[12px] font-bold text-[#b45309]">₹<?php echo number_format($totalRef,2); ?></p>
                        </div>
                        <div>
                            <p class="text-[9px] uppercase tracking-wide text-[#9e8f7e] font-semibold">Level 1</p>
                            <p class="text-[12px] font-bold text-[#1565c0]">₹<?php echo number_format($totalL1,2); ?></p>
                        </div>
                        <div>
                            <p class="text-[9px] uppercase tracking-wide text-[#9e8f7e] font-semibold">Level 2</p>
                            <p class="text-[12px] font-bold text-[#6b21a8]">₹<?php echo number_format($totalL2,2); ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <?php else: ?>
            <!-- EMPTY STATE -->
            <div class="flex flex-col items-center justify-center py-16 gap-3 text-[#c2b49a]">
                <i data-lucide="inbox" class="w-12 h-12 opacity-40"></i>
                <p class="text-[14px] font-semibold">No Records Found</p>
                <p class="text-[12px] opacity-70 text-center">Referral bonus entries will appear here once generated.</p>
            </div>
            <?php endif; ?>

        </div><!-- /table wrapper -->

        <div class="pb-6"></div>
    </div>
</div>

<script>lucide.createIcons();</script>
</body>
</html>