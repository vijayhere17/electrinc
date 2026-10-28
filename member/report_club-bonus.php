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
$managerName  = "Swapnil Solanki";
$managerRole  = "MLM Manager";
$profileImage = "https://i.pravatar.cc/80?img=12";
$navTitle     = "My Profile";
$navIcon      = "user-round";

include __DIR__ . '/config/db.php';

if (!isset($conn) || !($conn instanceof mysqli)) {
    die("Database connection failed.");
}

$conn->set_charset("utf8mb4");

/* ─── helpers ─────────────────────────────────────────────── */
function columnExists($conn, $table, $column) {
    $stmt = $conn->prepare("
        SELECT COUNT(*) AS c
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = ?
        AND COLUMN_NAME = ?
    ");
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

/* ─── ensure table & columns ──────────────────────────────── */
$conn->query("
    CREATE TABLE IF NOT EXISTS club_bonus_income_report (
        id             INT AUTO_INCREMENT PRIMARY KEY,
        customer_id    VARCHAR(50)    NOT NULL,
        report_date    DATE           DEFAULT CURRENT_DATE,
        club_no        INT            DEFAULT 1,
        left_id        VARCHAR(50)    DEFAULT NULL,
        right_id       VARCHAR(50)    DEFAULT NULL,
        club_income    DECIMAL(12,2)  DEFAULT 0,
        description    VARCHAR(255)   DEFAULT 'Club Bonus',
        percentage     DECIMAL(10,2)  DEFAULT 0,
        credit         DECIMAL(12,2)  DEFAULT 0,
        debit          DECIMAL(12,2)  DEFAULT 0,
        balance        DECIMAL(12,2)  DEFAULT 0,
        status         VARCHAR(50)    DEFAULT 'Pending',
        created_at     DATETIME       DEFAULT CURRENT_TIMESTAMP,
        updated_at     DATETIME       DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )
");

$extraCols = [
    'club_no'     => "INT DEFAULT 1",
    'left_id'     => "VARCHAR(50) DEFAULT NULL",
    'right_id'    => "VARCHAR(50) DEFAULT NULL",
    'club_income' => "DECIMAL(12,2) DEFAULT 0",
    'customer_id' => "VARCHAR(50) NOT NULL",
    'report_date' => "DATE DEFAULT CURRENT_DATE",
    'description' => "VARCHAR(255) DEFAULT 'Club Bonus'",
    'percentage'  => "DECIMAL(10,2) DEFAULT 0",
    'credit'      => "DECIMAL(12,2) DEFAULT 0",
    'debit'       => "DECIMAL(12,2) DEFAULT 0",
    'balance'     => "DECIMAL(12,2) DEFAULT 0",
    'status'      => "VARCHAR(50) DEFAULT 'Pending'",
];
foreach ($extraCols as $col => $def) {
    ensureColumn($conn, 'club_bonus_income_report', $col, $def);
}

$clubLabels = [
    1  => ['name' => 'Club 1',  'color' => '#C89741', 'icon' => '🥉'],
    2  => ['name' => 'Club 2',  'color' => '#9b59b6', 'icon' => '🥈'],
    3  => ['name' => 'Club 3',  'color' => '#e74c3c', 'icon' => '🥇'],
    4  => ['name' => 'Club 4',  'color' => '#2ecc71', 'icon' => '💎'],
    5  => ['name' => 'Club 5',  'color' => '#3498db', 'icon' => '🏅'],
    6  => ['name' => 'Club 6',  'color' => '#e67e22', 'icon' => '⭐'],
    7  => ['name' => 'Club 7',  'color' => '#1abc9c', 'icon' => '🌟'],
    8  => ['name' => 'Club 8',  'color' => '#e91e63', 'icon' => '🎖️'],
    9  => ['name' => 'Club 9',  'color' => '#00bcd4', 'icon' => '🏆'],
    10 => ['name' => 'Club 10', 'color' => '#ff5722', 'icon' => '👑'],
];

/* ─── fetch club_members rows ─────────────────────────────── */
$achievedClubs = [];
$cmStmt = $conn->prepare("
    SELECT club_level, matched_pairs, left_count, right_count
    FROM club_members
    WHERE customer_id = ?
    ORDER BY club_level ASC
");
if ($cmStmt) {
    $cmStmt->bind_param("s", $customerId);
    $cmStmt->execute();
    $cmResult = $cmStmt->get_result();
    while ($cmRow = $cmResult->fetch_assoc()) {
        $achievedClubs[(int)$cmRow['club_level']] = [
            'matched_pairs' => (int)$cmRow['matched_pairs'],
            'left_count'    => (int)$cmRow['left_count'],
            'right_count'   => (int)$cmRow['right_count'],
        ];
    }
    $cmStmt->close();
}

$currentClub       = 0;
$justCompletedClub = 0;
$pairsPerClub      = 15;

foreach ($achievedClubs as $lvl => $data) {
    $currentClub = $lvl;
}

if (isset($achievedClubs[$currentClub])) {
    $workingTowardClub = ($achievedClubs[$currentClub]['matched_pairs'] >= 15)
        ? $currentClub + 1
        : $currentClub;
} else {
    $workingTowardClub = 1;
}

$pairsInThisClub = 0;
if (isset($achievedClubs[$workingTowardClub])) {
    $pairsInThisClub = (int)$achievedClubs[$workingTowardClub]['matched_pairs'];
} elseif ($currentClub === 0 && isset($achievedClubs[1])) {
    $pairsInThisClub = min($achievedClubs[1]['matched_pairs'], $pairsPerClub);
}

foreach ($achievedClubs as $lvl => $data) {
    if ($data['matched_pairs'] >= $pairsPerClub && $lvl > $justCompletedClub) {
        $justCompletedClub = $lvl;
    }
}
if ($currentClub === 0) $justCompletedClub = 0;

/* ─── Build list of unlocked clubs (achieved clubs with matched_pairs >= 15) ─ */
$unlockedClubs = [];
foreach ($achievedClubs as $lvl => $data) {
    if ($data['matched_pairs'] >= $pairsPerClub) {
        $unlockedClubs[] = $lvl;
    }
}
// If nothing unlocked yet, still let them see Club 1 tab (empty)
if (empty($unlockedClubs)) {
    $unlockedClubs = [1];
}

/* ─── fetch ALL club_bonus_income_report rows grouped by club_no ─────── */
$allClubRows   = []; // keyed by club_no
$clubTotals    = []; // club_no => total income
$totalIncome   = 0;

$sql = "
    SELECT id, report_date, club_no, left_id, right_id,
           club_income, description, status
    FROM club_bonus_income_report
    WHERE customer_id = ?
    ORDER BY id ASC
";
$stmt = $conn->prepare($sql);
if ($stmt) {
    $stmt->bind_param("s", $customerId);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $cNo    = (int)($row['club_no'] ?: 1);
        $income = (float)$row['club_income'];
        $totalIncome += $income;
        $allClubRows[$cNo][] = [
            'date'        => !empty($row['report_date']) ? date('d-m-Y', strtotime($row['report_date'])) : '-',
            'club_no'     => $cNo,
            'left_id'     => $row['left_id'] ?: '-',
            'right_id'    => $row['right_id'] ?: '-',
            'club_income' => $income,
            'description' => $row['description'] ?: 'Club Bonus',
            'status'      => $row['status'] ?: 'Pending',
        ];
        $clubTotals[$cNo] = ($clubTotals[$cNo] ?? 0) + $income;
    }
    $stmt->close();
}

$totalPairs = 0;
$clubRow = $conn->query("
    SELECT SUM(matched_pairs) total_pairs
    FROM club_members
    WHERE customer_id='$customerId'
");
if ($clubRow && $clubRow->num_rows > 0) {
    $totalPairs = (int)$clubRow->fetch_assoc()['total_pairs'];
}

$clubInfo = $currentClub > 0
    ? ($clubLabels[$currentClub] ?? ['name' => "Club $currentClub", 'color' => '#C89741', 'icon' => '🏆'])
    : null;

// Default selected tab = current (highest unlocked) club
$defaultTab = $currentClub > 0 ? $currentClub : 1;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Club Bonus Report</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        :root {
            --gold:       #C89741;
            --gold-light: #f5eee3;
            --gold-dark:  #9e7428;
            --border:     #e2d8c8;
            --bg:         #f3f3f3;
        }
        body { background: var(--bg); }

        @media (min-width: 1024px) {
            .main-wrap { margin-left: 256px; }
        }

        /* ── Animations ── */
        @keyframes shimmer {
            0%   { background-position: -400px 0; }
            100% { background-position:  400px 0; }
        }
        @keyframes popIn {
            0%   { transform: scale(0.85) translateY(16px); opacity: 0; }
            70%  { transform: scale(1.02) translateY(-2px); }
            100% { transform: scale(1)    translateY(0);    opacity: 1; }
        }
        @keyframes sparkle {
            0%,100% { opacity:1; transform:scale(1); }
            50%     { opacity:.5; transform:scale(1.4) rotate(20deg); }
        }
        @keyframes fillBar { from { width:0; } }
        @keyframes fadeSlide {
            from { opacity:0; transform:translateY(8px); }
            to   { opacity:1; transform:translateY(0); }
        }

        .congrats-banner  { animation: popIn .6s cubic-bezier(.34,1.56,.64,1) both; }
        .shimmer-text {
            background: linear-gradient(90deg, var(--gold-dark), #f0c060, var(--gold-dark));
            background-size: 400px 100%;
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            animation: shimmer 2.5s infinite linear;
        }
        .sparkle { animation: sparkle 1.4s ease-in-out infinite; }
        .sparkle:nth-child(2) { animation-delay:.3s; }
        .sparkle:nth-child(3) { animation-delay:.6s; }
        .progress-fill { animation: fillBar .9s cubic-bezier(.4,0,.2,1) .3s both; }

        /* ── Club badge card ── */
        .club-badge {
            border: 2px solid var(--gold);
            background: linear-gradient(135deg, var(--gold-light) 0%, #fff9ef 100%);
            box-shadow: 0 4px 18px rgba(200,151,65,.15);
        }

        /* ── Status chips ── */
        .chip-approved { background:#e8f7ee; color:#1e7a48; }
        .chip-pending  { background:#fef7e0; color:#b07d00; }
        .chip-rejected { background:#fde8e8; color:#9b1c1c; }

        /* ── Desktop table row hover ── */
        .bonus-row { transition: background .15s; }
        .bonus-row:hover { background: #fdf6ea !important; }

        /* ── Mobile card list ── */
        .mobile-card {
            background: #fff;
            border: 1px solid #e8e0d0;
            border-radius: 14px;
            padding: 14px 16px;
        }
        .mobile-card + .mobile-card { margin-top: 10px; }
        .mob-label {
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: .06em;
            color: #9e8f7e;
            font-weight: 600;
            margin-bottom: 2px;
        }
        .mob-value {
            font-size: 13px;
            color: #2f2f2f;
            font-weight: 500;
        }

        /* ── Club Tabs ── */
        .club-tabs-scroll {
            display: flex;
            gap: 8px;
            overflow-x: auto;
            padding-bottom: 4px;
            -ms-overflow-style: none;
            scrollbar-width: none;
        }
        .club-tabs-scroll::-webkit-scrollbar { display: none; }

        .club-tab {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 7px 14px;
            border-radius: 50px;
            font-size: 12px;
            font-weight: 600;
            white-space: nowrap;
            cursor: pointer;
            border: 1.5px solid #ddd;
            background: #fff;
            color: #7a6a58;
            transition: all .2s ease;
            flex-shrink: 0;
        }
        .club-tab:hover {
            border-color: var(--gold);
            color: var(--gold-dark);
            background: var(--gold-light);
        }
        .club-tab.active {
            border-color: var(--gold);
            background: var(--gold);
            color: #fff;
            box-shadow: 0 3px 10px rgba(200,151,65,.35);
        }
        .club-tab .tab-icon {
            font-size: 14px;
            line-height: 1;
        }

        /* ── Panel animation ── */
        .club-panel {
            display: none;
        }
        .club-panel.active {
            display: block;
            animation: fadeSlide .25s ease both;
        }

        /* ── Locked club tab style ── */
        .club-tab.locked {
            opacity: 0.45;
            cursor: not-allowed;
            border-style: dashed;
        }
        .club-tab.locked:hover {
            border-color: #ddd;
            background: #fff;
            color: #7a6a58;
        }
    </style>
</head>
<body class="bg-[#f3f3f3]">

<?php include __DIR__ . '/includes/sidebar.php'; ?>

<div class="main-wrap min-h-screen">
    <?php include __DIR__ . '/includes/navbar.php'; ?>

    <div class="px-3 sm:px-5 py-3">

        <!-- ── PAGE HEADING ── -->
        <div class="mt-4 sm:mt-5 flex items-center gap-3">
            <div class="w-[34px] h-[34px] sm:w-[36px] sm:h-[36px] rounded-[8px] bg-[#f5eee3] flex items-center justify-center shrink-0">
                <i data-lucide="badge-check" class="w-[17px] h-[17px] text-[#cd9737]"></i>
            </div>
            <h2 class="whitespace-nowrap text-[20px] font-normal text-[#40352a]" style="font-family: Georgia, 'Times New Roman', serif;">
                CLUB BONUS
            </h2>
            <div class="w-full h-[2px] bg-[#d1a14b]"></div>
        </div>

        <!-- ── SUMMARY CARDS ── -->
        <div class="mt-4 sm:mt-6 grid grid-cols-1 sm:grid-cols-3 gap-3 sm:gap-4">

            <?php if ($currentClub > 0 && $clubInfo): ?>
            <div class="club-badge rounded-2xl px-4 py-3 sm:px-5 sm:py-4 flex items-center gap-3 sm:gap-4">
                <div class="text-3xl sm:text-4xl"><?php echo $clubInfo['icon']; ?></div>
                <div>
                    <p class="text-[10px] sm:text-[11px] uppercase tracking-widest text-[#9e8f7e] font-semibold">Current Club</p>
                    <p class="text-[18px] sm:text-[22px] font-bold text-[#2a2218] leading-tight">
                        <?php echo htmlspecialchars($clubInfo['name']); ?>
                    </p>
                </div>
            </div>
            <?php else: ?>
            <div class="rounded-2xl px-4 py-3 sm:px-5 sm:py-4 flex items-center gap-3 sm:gap-4 border-2 border-dashed border-[#d8cbb5] bg-[#faf7f2]">
                <div class="text-3xl sm:text-4xl opacity-30">🏅</div>
                <div>
                    <p class="text-[10px] sm:text-[11px] uppercase tracking-widest text-[#9e8f7e] font-semibold">Current Club</p>
                    <p class="text-[13px] sm:text-[14px] font-semibold text-[#b0a090] leading-tight">Not Yet Achieved</p>
                    <p class="text-[10px] sm:text-[11px] text-[#c2b49a] mt-0.5">Complete 15 pairs to unlock Club 1</p>
                </div>
            </div>
            <?php endif; ?>

            <div class="bg-white rounded-2xl px-4 py-3 sm:px-5 sm:py-4 border border-[#e2d8c8] flex items-center gap-3 sm:gap-4">
                <div class="w-9 h-9 sm:w-10 sm:h-10 rounded-xl bg-[#f5eee3] flex items-center justify-center shrink-0">
                    <i data-lucide="users" class="w-4 h-4 sm:w-5 sm:h-5 text-[#C89741]"></i>
                </div>
                <div>
                    <p class="text-[10px] sm:text-[11px] uppercase tracking-widest text-[#9e8f7e] font-semibold">Total Pairs</p>
                    <p class="text-[18px] sm:text-[22px] font-bold text-[#2a2218]"><?php echo $totalPairs; ?></p>
                </div>
            </div>

            <div class="bg-white rounded-2xl px-4 py-3 sm:px-5 sm:py-4 border border-[#e2d8c8] flex items-center gap-3 sm:gap-4">
                <div class="w-9 h-9 sm:w-10 sm:h-10 rounded-xl bg-[#f5eee3] flex items-center justify-center shrink-0">
                    <i data-lucide="indian-rupee" class="w-4 h-4 sm:w-5 sm:h-5 text-[#C89741]"></i>
                </div>
                <div>
                    <p class="text-[10px] sm:text-[11px] uppercase tracking-widest text-[#9e8f7e] font-semibold">Total Club Income</p>
                    <p class="text-[18px] sm:text-[22px] font-bold text-[#2a2218]">
                        ₹<?php echo number_format($totalIncome, 2); ?>
                    </p>
                </div>
            </div>
        </div>

        <!-- ── PROGRESS BAR ── -->
        <?php
        $progressPct  = ($pairsPerClub > 0) ? min(100, round(($pairsInThisClub / $pairsPerClub) * 100)) : 0;
        $remaining    = $pairsPerClub - $pairsInThisClub;
        $nextClubInfo = $clubLabels[$workingTowardClub] ?? ['name' => "Club $workingTowardClub", 'color' => '#C89741', 'icon' => '🏆'];
        ?>
        <div class="mt-3 sm:mt-4 bg-white rounded-2xl border border-[#e2d8c8] px-4 sm:px-5 py-3 sm:py-4">
            <div class="flex flex-wrap items-center justify-between gap-1 mb-2">
                <span class="text-[12px] sm:text-[13px] font-semibold text-[#3d3228]">
                    Progress to <?php echo htmlspecialchars($nextClubInfo['icon'] . ' ' . $nextClubInfo['name']); ?>
                </span>
                <span class="text-[11px] sm:text-[12px] text-[#9e8f7e]">
                    <?php echo $pairsInThisClub; ?> / <?php echo $pairsPerClub; ?> pairs &nbsp;·&nbsp;
                    <?php if ($remaining > 0): ?>
                        <span class="text-[#C89741] font-semibold"><?php echo $remaining; ?> more to unlock</span>
                    <?php else: ?>
                        <span class="text-green-600 font-semibold">Unlocked!</span>
                    <?php endif; ?>
                </span>
            </div>
            <div class="w-full h-2.5 sm:h-3 bg-[#f5eee3] rounded-full overflow-hidden">
                <div class="progress-fill h-full rounded-full"
                     style="width:<?php echo $progressPct; ?>%;
                            background: linear-gradient(90deg, #C89741, #f0c060);">
                </div>
            </div>
        </div>

        <!-- ── CONGRATULATIONS BANNER ── -->
        <?php if ($justCompletedClub > 0): ?>
            <?php
            $completedInfo   = $clubLabels[$justCompletedClub] ?? ['name' => "Club $justCompletedClub", 'icon' => '🏆'];
            $nextAchieveInfo = $clubLabels[$justCompletedClub + 1] ?? null;
            ?>
            <div class="congrats-banner mt-4 sm:mt-5 rounded-2xl overflow-hidden border-2 border-[#C89741]"
                 style="background:linear-gradient(135deg,#2a1f0e 0%,#3d2d10 50%,#2a1f0e 100%);">
                <div class="px-4 sm:px-6 py-4 sm:py-5 flex flex-col sm:flex-row items-center gap-3 sm:gap-4">
                    <div class="flex gap-1 text-xl sm:text-2xl">
                        <span class="sparkle">✨</span>
                        <span class="sparkle">🏆</span>
                        <span class="sparkle">✨</span>
                    </div>
                    <div class="text-center sm:text-left flex-1">
                        <p class="text-[10px] sm:text-[11px] uppercase tracking-[.2em] text-[#c89741] font-semibold mb-1">
                            Achievement Unlocked
                        </p>
                        <p class="shimmer-text text-[18px] sm:text-[22px] font-bold leading-tight">
                            Congratulations, <?php echo htmlspecialchars($memberName); ?>!
                        </p>
                        <p class="text-[12px] sm:text-[13px] text-[#d4b87a] mt-1">
                            You have successfully completed
                            <strong class="text-white"><?php echo htmlspecialchars($completedInfo['name']); ?></strong>
                            with <?php echo $pairsPerClub; ?> pairs.
                            <?php if ($nextAchieveInfo): ?>
                                You are now eligible for
                                <strong class="text-[#f0c060]"><?php echo htmlspecialchars($nextAchieveInfo['name']); ?></strong>!
                            <?php endif; ?>
                        </p>
                    </div>
                    <div class="w-12 h-12 sm:w-14 sm:h-14 rounded-full border-2 border-[#f0c060] flex items-center justify-center text-2xl sm:text-3xl shrink-0">
                        <?php echo $completedInfo['icon']; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- ══════════════════════════════════════════════════
             CLUB TABS + FILTERED TABLE
        ══════════════════════════════════════════════════ -->
        <div class="mt-4 sm:mt-5">

            <!-- ── Tab header ── -->
            <div class="flex items-center gap-3 mb-3">
                <div class="w-[28px] h-[28px] rounded-[7px] bg-[#f5eee3] flex items-center justify-center shrink-0">
                    <i data-lucide="layout-list" class="w-[14px] h-[14px] text-[#cd9737]"></i>
                </div>
                <p class="text-[13px] font-semibold text-[#5c4d3c]">Select Club to View Entries</p>
            </div>

            <!-- ── Scrollable tab pills ── -->
            <div class="club-tabs-scroll" id="clubTabsBar">
                <?php
                // Show tabs for all unlocked clubs (1 through currentClub)
                // Also show the next locked club as greyed/locked
                $maxShowClub = max($currentClub, 1);

                for ($lvl = 1; $lvl <= $maxShowClub; $lvl++):
                    $tabInfo   = $clubLabels[$lvl] ?? ['name' => "Club $lvl", 'color' => '#C89741', 'icon' => '🏅'];
                    $isUnlocked = in_array($lvl, $unlockedClubs);
                    $isActive   = ($lvl === $defaultTab);
                    $rowCount   = count($allClubRows[$lvl] ?? []);
                ?>
                <button
                    class="club-tab <?php echo $isActive ? 'active' : ''; ?> <?php echo !$isUnlocked ? 'locked' : ''; ?>"
                    data-club="<?php echo $lvl; ?>"
                    data-unlocked="<?php echo $isUnlocked ? '1' : '0'; ?>"
                    onclick="switchClub(<?php echo $lvl; ?>, this)"
                    title="<?php echo $isUnlocked ? $tabInfo['name'] : $tabInfo['name'] . ' (Locked)'; ?>"
                >
                    <span class="tab-icon"><?php echo $tabInfo['icon']; ?></span>
                    <span><?php echo htmlspecialchars($tabInfo['name']); ?></span>
                    <?php if ($isUnlocked && $rowCount > 0): ?>
                        <span class="inline-flex items-center justify-center w-4 h-4 rounded-full text-[9px] font-bold"
                              style="background:rgba(255,255,255,0.3);">
                            <?php echo $rowCount; ?>
                        </span>
                    <?php elseif (!$isUnlocked): ?>
                        <i data-lucide="lock" style="width:10px;height:10px;opacity:.7;"></i>
                    <?php endif; ?>
                </button>
                <?php endfor; ?>

                <!-- Next locked club (if exists) -->
                <?php if ($maxShowClub < 10):
                    $nextLvl     = $maxShowClub + 1;
                    $nextTabInfo = $clubLabels[$nextLvl] ?? ['name' => "Club $nextLvl", 'color' => '#aaa', 'icon' => '🔒'];
                ?>
                <button class="club-tab locked" disabled title="Complete <?php echo $pairsPerClub; ?> pairs in Club <?php echo $maxShowClub; ?> to unlock">
                    <span class="tab-icon"><?php echo $nextTabInfo['icon']; ?></span>
                    <span><?php echo htmlspecialchars($nextTabInfo['name']); ?></span>
                    <i data-lucide="lock" style="width:10px;height:10px;opacity:.7;"></i>
                </button>
                <?php endif; ?>
            </div>

            <!-- ── Club Panels ── -->
            <div class="mt-3" id="clubPanelsContainer">
                <?php for ($lvl = 1; $lvl <= $maxShowClub; $lvl++):
                    $tabInfo    = $clubLabels[$lvl] ?? ['name' => "Club $lvl", 'color' => '#C89741', 'icon' => '🏅'];
                    $rows       = $allClubRows[$lvl] ?? [];
                    $clubTotal  = $clubTotals[$lvl] ?? 0;
                    $isActive   = ($lvl === $defaultTab);
                    $isUnlocked = in_array($lvl, $unlockedClubs);
                ?>
                <div class="club-panel <?php echo $isActive ? 'active' : ''; ?>" id="panel-club-<?php echo $lvl; ?>">

                    <!-- Panel heading with club color accent -->
                    <div class="flex items-center gap-2 mb-3">
                        <span class="text-xl"><?php echo $tabInfo['icon']; ?></span>
                        <h3 class="text-[14px] font-bold text-[#2a2218]">
                            <?php echo htmlspecialchars($tabInfo['name']); ?> Entries
                        </h3>
                        <?php if ($isUnlocked && count($rows) > 0): ?>
                        <span class="ml-auto text-[12px] font-semibold text-[#C89741]">
                            Total: ₹<?php echo number_format($clubTotal, 2); ?>
                        </span>
                        <?php endif; ?>
                    </div>

                    <div class="rounded-[14px] border border-[#dddddd] bg-[#f7f7f7] p-3 sm:p-4">

                        <?php if (!$isUnlocked): ?>
                        <!-- Locked state -->
                        <div class="flex flex-col items-center justify-center py-14 gap-3 text-[#c2b49a]">
                            <div class="w-14 h-14 rounded-full bg-[#f0e8d8] flex items-center justify-center">
                                <i data-lucide="lock" class="w-7 h-7 text-[#d4b87a]"></i>
                            </div>
                            <p class="text-[14px] font-semibold text-[#9e8f7e]">Club Locked</p>
                            <p class="text-[12px] opacity-70 text-center max-w-[220px]">
                                Complete 15 pairs to unlock <?php echo htmlspecialchars($tabInfo['name']); ?>.
                            </p>
                        </div>

                        <?php elseif (count($rows) === 0): ?>
                        <!-- Unlocked but no entries yet -->
                        <div class="flex flex-col items-center justify-center py-14 gap-3 text-[#c2b49a]">
                            <i data-lucide="inbox" class="w-12 h-12 opacity-40"></i>
                            <p class="text-[14px] font-semibold">No Records Found</p>
                            <p class="text-[12px] opacity-70 text-center">
                                <?php echo htmlspecialchars($tabInfo['name']); ?> bonus entries will appear here once generated.
                            </p>
                        </div>

                        <?php else: ?>

                        <!-- DESKTOP TABLE -->
                        <div class="hidden sm:block overflow-x-auto">
                            <table class="w-full border-separate border-spacing-0">
                                <thead>
                                    <tr style="background:<?php echo $tabInfo['color']; ?>;" class="text-white text-[13px] font-semibold">
                                        <th class="px-5 py-4 text-center rounded-l-[10px] min-w-[60px]">#</th>
                                        <th class="px-5 py-4 text-left min-w-[120px]">Date</th>
                                        <th class="px-5 py-4 text-left min-w-[120px]">Left ID</th>
                                        <th class="px-5 py-4 text-left min-w-[120px]">Right ID</th>
                                        <th class="px-5 py-4 text-left min-w-[140px]">Description</th>
                                        <th class="px-5 py-4 text-right min-w-[130px]">Club Income (₹)</th>
                                        <th class="px-5 py-4 text-center rounded-r-[10px] min-w-[110px]">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($rows as $i => $row):
                                        $s    = strtolower(trim($row['status']));
                                        $chip = (str_contains($s,'approved')||str_contains($s,'paid'))
                                                ? 'chip-approved'
                                                : ((str_contains($s,'reject')||str_contains($s,'fail')) ? 'chip-rejected' : 'chip-pending');
                                    ?>
                                    <tr class="bonus-row text-[13px] text-[#2f2f2f] <?php echo ($i%2===0)?'bg-white':'bg-[#fdfaf4]'; ?>">
                                        <td class="px-5 py-4 border-b border-[#e8e0d0] align-middle text-center text-[#9e8f7e] font-medium">
                                            <?php echo $i + 1; ?>
                                        </td>
                                        <td class="px-5 py-4 border-b border-[#e8e0d0] align-middle text-[#555]">
                                            <?php echo htmlspecialchars($row['date']); ?>
                                        </td>
                                        <td class="px-5 py-4 border-b border-[#e8e0d0] align-middle">
                                            <span class="flex items-center gap-1">
                                                <i data-lucide="arrow-left" class="w-3 h-3 text-blue-400 shrink-0"></i>
                                                <span class="font-medium text-[#393939]"><?php echo htmlspecialchars($row['left_id']); ?></span>
                                            </span>
                                        </td>
                                        <td class="px-5 py-4 border-b border-[#e8e0d0] align-middle">
                                            <span class="flex items-center gap-1">
                                                <i data-lucide="arrow-right" class="w-3 h-3 text-green-500 shrink-0"></i>
                                                <span class="font-medium text-[#393939]"><?php echo htmlspecialchars($row['right_id']); ?></span>
                                            </span>
                                        </td>
                                        <td class="px-5 py-4 border-b border-[#e8e0d0] align-middle text-[#555]">
                                            <?php echo htmlspecialchars($row['description']); ?>
                                        </td>
                                        <td class="px-5 py-4 border-b border-[#e8e0d0] align-middle text-right font-semibold text-[#2a2218]">
                                            ₹<?php echo number_format($row['club_income'], 2); ?>
                                        </td>
                                        <td class="px-5 py-4 border-b border-[#e8e0d0] align-middle text-center">
                                            <span class="<?php echo $chip; ?> px-3 py-1 rounded-full text-[11px] font-semibold uppercase tracking-wide">
                                                <?php echo htmlspecialchars($row['status']); ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>

                                    <!-- Total row -->
                                    <tr class="text-[13px] font-bold">
                                        <td colspan="5" class="px-5 py-4 rounded-bl-[10px] text-right text-[#3d3228] uppercase tracking-wider bg-[#f5eee3] border-t-2 border-[#C89741]">
                                            Total Club Income
                                        </td>
                                        <td class="px-5 py-4 text-right text-[#C89741] text-[15px] bg-[#f5eee3] border-t-2 border-[#C89741]">
                                            ₹<?php echo number_format($clubTotal, 2); ?>
                                        </td>
                                        <td class="px-5 py-4 rounded-br-[10px] bg-[#f5eee3] border-t-2 border-[#C89741]"></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- MOBILE CARDS -->
                        <div class="block sm:hidden">
                            <?php foreach ($rows as $i => $row):
                                $s    = strtolower(trim($row['status']));
                                $chip = (str_contains($s,'approved')||str_contains($s,'paid'))
                                        ? 'chip-approved'
                                        : ((str_contains($s,'reject')||str_contains($s,'fail')) ? 'chip-rejected' : 'chip-pending');
                            ?>
                            <div class="mobile-card">
                                <!-- Top row: serial + date + status -->
                                <div class="flex items-center justify-between mb-3">
                                    <div class="flex items-center gap-2">
                                        <span class="inline-flex items-center justify-center w-7 h-7 rounded-full text-[11px] font-bold text-white shrink-0"
                                              style="background:<?php echo $tabInfo['color']; ?>;">
                                            <?php echo $i + 1; ?>
                                        </span>
                                        <div>
                                            <p class="text-[10px] text-[#9e8f7e] font-semibold uppercase tracking-wide">Date</p>
                                            <p class="text-[13px] font-bold text-[#2a2218]"><?php echo htmlspecialchars($row['date']); ?></p>
                                        </div>
                                    </div>
                                    <span class="<?php echo $chip; ?> px-2.5 py-1 rounded-full text-[10px] font-semibold uppercase tracking-wide">
                                        <?php echo htmlspecialchars($row['status']); ?>
                                    </span>
                                </div>

                                <div class="grid grid-cols-2 gap-x-4 gap-y-3">
                                    <div>
                                        <p class="mob-label">Club Income</p>
                                        <p class="mob-value font-bold text-[#C89741]">₹<?php echo number_format($row['club_income'], 2); ?></p>
                                    </div>
                                    <div>
                                        <p class="mob-label">Description</p>
                                        <p class="mob-value text-[#555] truncate"><?php echo htmlspecialchars($row['description']); ?></p>
                                    </div>
                                    <div>
                                        <p class="mob-label">Left ID</p>
                                        <p class="mob-value flex items-center gap-1">
                                            <i data-lucide="arrow-left" class="w-3 h-3 text-blue-400 shrink-0"></i>
                                            <?php echo htmlspecialchars($row['left_id']); ?>
                                        </p>
                                    </div>
                                    <div>
                                        <p class="mob-label">Right ID</p>
                                        <p class="mob-value flex items-center gap-1">
                                            <i data-lucide="arrow-right" class="w-3 h-3 text-green-500 shrink-0"></i>
                                            <?php echo htmlspecialchars($row['right_id']); ?>
                                        </p>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>

                            <!-- Mobile Total -->
                            <div class="mt-3 rounded-xl bg-[#f5eee3] border-2 border-[#C89741] px-4 py-3 flex items-center justify-between">
                                <p class="text-[12px] font-bold text-[#3d3228] uppercase tracking-wider">Total Club Income</p>
                                <p class="text-[16px] font-bold text-[#C89741]">₹<?php echo number_format($clubTotal, 2); ?></p>
                            </div>
                        </div>

                        <?php endif; ?>
                    </div><!-- /panel inner -->
                </div><!-- /club-panel -->
                <?php endfor; ?>
            </div><!-- /panels container -->
        </div><!-- /tabs section -->

        <div class="pb-6"></div>

    </div><!-- /px -->
</div><!-- /main-wrap -->

<script>
    lucide.createIcons();

    function switchClub(lvl, btn) {
        // Don't switch if locked
        if (btn.dataset.unlocked === '0' || btn.disabled) return;

        // Deactivate all tabs
        document.querySelectorAll('.club-tab').forEach(t => t.classList.remove('active'));
        // Activate clicked tab
        btn.classList.add('active');

        // Hide all panels
        document.querySelectorAll('.club-panel').forEach(p => p.classList.remove('active'));
        // Show target panel
        const panel = document.getElementById('panel-club-' + lvl);
        if (panel) panel.classList.add('active');

        // Re-init lucide icons inside the panel (for arrow icons)
        lucide.createIcons();
    }
</script>
</body>
</html>