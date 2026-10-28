<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include __DIR__ . '/config/db.php';

if (!isset($conn) || !($conn instanceof mysqli)) {
    die("Database connection failed.");
}

$conn->set_charset("utf8mb4");

$memberName  = $_SESSION['full_name'] ?? 'Member';
$customerId  = $_SESSION['member_id'] ?? ($_SESSION['customer_id'] ?? '');

if ($customerId === '') {
    header("Location: login.php");
    exit;
}

$searchBy        = $_GET['search_by'] ?? '';
$searchValue     = trim($_GET['search_value'] ?? '');
$searchIncentive = trim($_GET['search_incentive'] ?? '');
$fromDate        = trim($_GET['from_date'] ?? '');
$toDate          = trim($_GET['to_date'] ?? '');

$rows = [];

function tableExists(mysqli $conn, string $table): bool {
    $table = $conn->real_escape_string($table);
    $res = $conn->query("SHOW TABLES LIKE '$table'");
    return $res instanceof mysqli_result && $res->num_rows > 0;
}

function getColumns(mysqli $conn, string $table): array {
    $cols = [];
    if (!tableExists($conn, $table)) return $cols;

    $res = $conn->query("SHOW COLUMNS FROM `$table`");
    if ($res instanceof mysqli_result) {
        while ($r = $res->fetch_assoc()) {
            $cols[strtolower($r['Field'])] = $r['Field'];
        }
    }
    return $cols;
}

function findCol(array $cols, array $names): string {
    foreach ($names as $n) {
        $key = strtolower($n);
        if (isset($cols[$key])) return $cols[$key];
    }
    return '';
}

function makeClubAchieverTable(mysqli $conn): void {
    $conn->query("
        CREATE TABLE IF NOT EXISTS club_achievers (
            id INT AUTO_INCREMENT PRIMARY KEY,
            sr_no INT NOT NULL DEFAULT 0,
            join_date DATE NULL,
            win_code VARCHAR(100) NOT NULL,
            name VARCHAR(255) NOT NULL,
            username VARCHAR(255) DEFAULT '',
            pool VARCHAR(100) DEFAULT 'Bonus',
            reward TEXT NULL,
            total_bonus DECIMAL(12,2) NOT NULL DEFAULT 0,
            state VARCHAR(100) DEFAULT '',
            district VARCHAR(100) DEFAULT '',
            city VARCHAR(100) DEFAULT '',
            mobile_no VARCHAR(30) DEFAULT '',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_win_code (win_code)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $needed = [
        'total_bonus' => "ALTER TABLE club_achievers ADD COLUMN total_bonus DECIMAL(12,2) NOT NULL DEFAULT 0",
        'updated_at'  => "ALTER TABLE club_achievers ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP",
        'entry_source' => "ALTER TABLE club_achievers ADD COLUMN entry_source VARCHAR(20) NOT NULL DEFAULT 'auto'"
    ];

    foreach ($needed as $col => $sql) {
        $check = $conn->query("SHOW COLUMNS FROM club_achievers LIKE '$col'");
        if ($check && $check->num_rows == 0) {
            $conn->query($sql);
        }
    }
}

function getMemberInfo(mysqli $conn, string $customerId): array {
    $info = [
        'join_date' => date('Y-m-d'),
        'name' => $customerId,
        'username' => $customerId,
        'state' => '',
        'district' => '',
        'city' => '',
        'mobile_no' => ''
    ];

    if (!tableExists($conn, 'signup_users')) return $info;

    $cols = getColumns($conn, 'signup_users');

    $idCol       = findCol($cols, ['customer_id', 'member_id', 'associate_id', 'login_id', 'user_id']);
    $nameCol     = findCol($cols, ['full_name', 'name', 'member_name']);
    $emailCol    = findCol($cols, ['email', 'username']);
    $mobileCol   = findCol($cols, ['mobile', 'mobile_no', 'phone']);
    $stateCol    = findCol($cols, ['state']);
    $districtCol = findCol($cols, ['district']);
    $cityCol     = findCol($cols, ['city']);
    $dateCol     = findCol($cols, ['join_date', 'joining_date', 'created_at', 'date']);

    if ($idCol === '') return $info;

    $select = "`$idCol` AS cid";
    $select .= $nameCol ? ", `$nameCol` AS full_name" : ", '' AS full_name";
    $select .= $emailCol ? ", `$emailCol` AS username" : ", '' AS username";
    $select .= $mobileCol ? ", `$mobileCol` AS mobile_no" : ", '' AS mobile_no";
    $select .= $stateCol ? ", `$stateCol` AS state" : ", '' AS state";
    $select .= $districtCol ? ", `$districtCol` AS district" : ", '' AS district";
    $select .= $cityCol ? ", `$cityCol` AS city" : ", '' AS city";
    $select .= $dateCol ? ", DATE(`$dateCol`) AS join_date" : ", CURDATE() AS join_date";

    $sql = "SELECT $select FROM signup_users WHERE `$idCol` = ? LIMIT 1";
    $stmt = $conn->prepare($sql);
    if (!$stmt) return $info;

    $stmt->bind_param("s", $customerId);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res && $res->num_rows > 0) {
        $r = $res->fetch_assoc();
        $info['join_date'] = $r['join_date'] ?: date('Y-m-d');
        $info['name'] = $r['full_name'] ?: $customerId;
        $info['username'] = $r['username'] ?: $customerId;
        $info['state'] = $r['state'] ?? '';
        $info['district'] = $r['district'] ?? '';
        $info['city'] = $r['city'] ?? '';
        $info['mobile_no'] = $r['mobile_no'] ?? '';
    }

    $stmt->close();
    return $info;
}

function getUserBonuses(mysqli $conn, string $customerId): array {
    $bonusTables = [
        'referral_bonus' => 'Referral Bonus',
        'referral_bonus_report' => 'Referral Bonus',
        'level_matching_bonus' => 'Level Matching Bonus',
        'level_matching_bonus_report' => 'Level Matching Bonus',
        'club_bonus' => 'Club Bonus',
        'club_bonus_report' => 'Club Bonus',
        'reward_bonus' => 'Reward Bonus',
        'reward_bonus_report' => 'Reward Bonus',
        'binary_bonus' => 'Binary Bonus',
        'binary_bonus_report' => 'Binary Bonus',
        'retail_profit_bonus' => 'Retail Profit Bonus',
        'retail_profit_bonus_report' => 'Retail Profit Bonus'
    ];

    $bonuses = [];
    $total = 0;

    foreach ($bonusTables as $table => $defaultName) {
        if (!tableExists($conn, $table)) continue;

        $cols = getColumns($conn, $table);

        $idCol = findCol($cols, [
            'customer_id', 'member_id', 'associate_id', 'user_id',
            'win_code', 'login_id'
        ]);

        $amountCol = findCol($cols, [
            'amount', 'bonus', 'bonus_amount', 'income', 'earning',
            'earn', 'total_bonus', 'credit', 'paid_amount'
        ]);

        $statusCol = findCol($cols, [
            'status', 'payment_status', 'bonus_status'
        ]);

        $dateCol = findCol($cols, [
            'created_at', 'date', 'bonus_date', 'closing_date', 'paid_date'
        ]);

        $typeCol = findCol($cols, [
            'bonus_type', 'income_type', 'type', 'reward_name', 'incentive'
        ]);

        if ($idCol === '' || $amountCol === '') continue;

        $selectType = $typeCol ? "`$typeCol` AS bonus_name" : "'$defaultName' AS bonus_name";
        $selectDate = $dateCol ? "DATE(`$dateCol`) AS bonus_date" : "CURDATE() AS bonus_date";

        $sql = "
            SELECT 
                `$amountCol` AS amount,
                $selectType,
                $selectDate
            FROM `$table`
            WHERE `$idCol` = ?
              AND `$amountCol` > 0
        ";

        if ($statusCol !== '') {
            $sql .= " AND (
                `$statusCol` = 'Paid'
                OR `$statusCol` = 'Approved'
                OR `$statusCol` = 'Success'
                OR `$statusCol` = 'Completed'
                OR `$statusCol` = 'Active'
                OR `$statusCol` = '1'
                OR `$statusCol` = ''
                OR `$statusCol` IS NULL
            )";
        }

        $stmt = $conn->prepare($sql);
        if (!$stmt) continue;

        $stmt->bind_param("s", $customerId);
        $stmt->execute();
        $res = $stmt->get_result();

        while ($r = $res->fetch_assoc()) {
            $amount = (float)$r['amount'];
            if ($amount <= 0) continue;

            $bonusName = trim($r['bonus_name'] ?? '');
            if ($bonusName === '' || is_numeric($bonusName)) {
                $bonusName = $defaultName;
            }

            $bonuses[] = [
                'name' => $bonusName,
                'amount' => $amount,
                'date' => $r['bonus_date'] ?? date('Y-m-d')
            ];

            $total += $amount;
        }

        $stmt->close();
    }

    return [
        'items' => $bonuses,
        'total' => $total
    ];
}

function getAllPotentialAchieverIds(mysqli $conn): array {
    $ids = [];

    if (tableExists($conn, 'signup_users')) {
        $cols = getColumns($conn, 'signup_users');
        $idCol = findCol($cols, ['customer_id', 'member_id', 'associate_id', 'login_id', 'user_id']);

        if ($idCol !== '') {
            $res = $conn->query("SELECT DISTINCT `$idCol` AS cid FROM signup_users WHERE `$idCol` IS NOT NULL AND `$idCol` <> ''");
            if ($res instanceof mysqli_result) {
                while ($r = $res->fetch_assoc()) {
                    $cid = trim((string)($r['cid'] ?? ''));
                    if ($cid !== '') {
                        $ids[$cid] = true;
                    }
                }
            }
        }
    }

    $bonusTables = [
        'referral_bonus',
        'referral_bonus_report',
        'level_matching_bonus',
        'level_matching_bonus_report',
        'club_bonus',
        'club_bonus_report',
        'reward_bonus',
        'reward_bonus_report',
        'binary_bonus',
        'binary_bonus_report',
        'retail_profit_bonus',
        'retail_profit_bonus_report'
    ];

    foreach ($bonusTables as $table) {
        if (!tableExists($conn, $table)) {
            continue;
        }

        $cols = getColumns($conn, $table);
        $idCol = findCol($cols, ['customer_id', 'member_id', 'associate_id', 'user_id', 'win_code', 'login_id']);

        if ($idCol === '') {
            continue;
        }

        $res = $conn->query("SELECT DISTINCT `$idCol` AS cid FROM `$table` WHERE `$idCol` IS NOT NULL AND `$idCol` <> ''");
        if ($res instanceof mysqli_result) {
            while ($r = $res->fetch_assoc()) {
                $cid = trim((string)($r['cid'] ?? ''));
                if ($cid !== '') {
                    $ids[$cid] = true;
                }
            }
        }
    }

    return array_keys($ids);
}

function syncLoggedInUserAchiever(mysqli $conn, string $customerId): void {
    makeClubAchieverTable($conn);

    $bonusData = getUserBonuses($conn, $customerId);

    if ($bonusData['total'] <= 0) {
        $stmt = $conn->prepare("DELETE FROM club_achievers WHERE win_code = ? AND entry_source <> 'manual'");
        if ($stmt) {
            $stmt->bind_param("s", $customerId);
            $stmt->execute();
            $stmt->close();
        }
        return;
    }

    $member = getMemberInfo($conn, $customerId);

    $rewardParts = [];
    foreach ($bonusData['items'] as $b) {
        $rewardParts[] = $b['name'] . ' ₹' . number_format($b['amount'], 2);
    }

    $rewardText = implode(', ', array_unique($rewardParts));
    $pool = 'Bonus Achiever';

    $maxSr = 1;
    $srRes = $conn->query("SELECT COALESCE(MAX(sr_no),0)+1 AS sr FROM club_achievers");
    if ($srRes instanceof mysqli_result) {
        $srRow = $srRes->fetch_assoc();
        $maxSr = (int)($srRow['sr'] ?? 1);
    }

    $check = $conn->prepare("SELECT id, entry_source FROM club_achievers WHERE win_code = ? LIMIT 1");
    $check->bind_param("s", $customerId);
    $check->execute();
    $exists = $check->get_result();
    $existingRow = $exists && $exists->num_rows > 0 ? $exists->fetch_assoc() : null;
    $hasRow = !empty($existingRow);
    $check->close();

    if ($hasRow && (($existingRow['entry_source'] ?? '') === 'manual')) {
        return;
    }

    if ($hasRow) {
        $stmt = $conn->prepare("
            UPDATE club_achievers 
            SET join_date=?, name=?, username=?, pool=?, reward=?, total_bonus=?, 
                state=?, district=?, city=?, mobile_no=?
            WHERE win_code=?
        ");

        $stmt->bind_param(
            "sssssdsssss",
            $member['join_date'],
            $member['name'],
            $member['username'],
            $pool,
            $rewardText,
            $bonusData['total'],
            $member['state'],
            $member['district'],
            $member['city'],
            $member['mobile_no'],
            $customerId
        );
    } else {
        $stmt = $conn->prepare("
            INSERT INTO club_achievers
            (sr_no, join_date, win_code, name, username, pool, reward, total_bonus, state, district, city, mobile_no, entry_source)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'auto')
        ");

        $stmt->bind_param(
            "issssssdssss",
            $maxSr,
            $member['join_date'],
            $customerId,
            $member['name'],
            $member['username'],
            $pool,
            $rewardText,
            $bonusData['total'],
            $member['state'],
            $member['district'],
            $member['city'],
            $member['mobile_no']
        );
    }

    if ($stmt) {
        $stmt->execute();
        $stmt->close();
    }
}

function syncAllAchievers(mysqli $conn): void {
    makeClubAchieverTable($conn);

    $ids = getAllPotentialAchieverIds($conn);
    foreach ($ids as $cid) {
        syncLoggedInUserAchiever($conn, $cid);
    }
}

syncAllAchievers($conn);

$sql = "
    SELECT 
        id, sr_no, join_date, win_code AS customer_id, name, username, 
        pool, reward, total_bonus, state, district, city, mobile_no
    FROM club_achievers
    WHERE 1=1
";

$params = [];
$types = "";

if ($searchValue !== '') {
    if ($searchBy === 'customer_id') {
        $sql .= " AND win_code LIKE ?";
    } elseif ($searchBy === 'name') {
        $sql .= " AND name LIKE ?";
    }
    if ($searchBy === 'customer_id' || $searchBy === 'name') {
        $types .= "s";
        $params[] = "%" . $searchValue . "%";
    }
}

if ($searchIncentive !== '') {
    $sql .= " AND reward LIKE ?";
    $types .= "s";
    $params[] = "%" . $searchIncentive . "%";
}

if ($fromDate !== '') {
    $sql .= " AND join_date >= ?";
    $types .= "s";
    $params[] = $fromDate;
}

if ($toDate !== '') {
    $sql .= " AND join_date <= ?";
    $types .= "s";
    $params[] = $toDate;
}

$sql .= " ORDER BY id DESC";

$stmt = $conn->prepare($sql);
if ($stmt) {
    if ($types !== '' && !empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) {
        $rows[] = $r;
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Club Achiever</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
</head>

<body class="m-0 bg-[#f3f4f6] font-sans overflow-x-hidden">

<?php include __DIR__ . '/includes/sidebar.php'; ?>

<div class="lg:ml-64 min-h-screen w-full lg:w-[calc(100%-16rem)]">
    <?php include __DIR__ . '/includes/navbar.php'; ?>

    <main class="px-3 sm:px-5 py-4">

        <div class="flex flex-wrap items-center gap-3 sm:gap-4 mb-4">
            <div class="w-9 h-9 rounded-[8px] bg-[#f6f2ea] flex items-center justify-center shrink-0">
                <i data-lucide="trophy" class="w-4 h-4 text-[#c9983b]"></i>
            </div>

            <h2 class="text-[16px] sm:text-[17px] md:text-[18px] leading-none text-[#2f2f2f] font-normal whitespace-nowrap"
                style="font-family: Georgia, 'Times New Roman', serif;">
                CLUB ACHIEVER
            </h2>

            <div class="hidden sm:block h-[1px] bg-[#c9983b] flex-1"></div>
        </div>

        <div class="relative bg-white border border-[#dfdfdf] rounded-[12px] min-h-[320px] px-3 py-4">

            <form method="GET" class="space-y-3">

                <div class="flex flex-wrap items-center gap-x-4 gap-y-3 text-[14px] sm:text-[16px] text-[#3a3a3a] font-medium">
                    <div class="flex flex-wrap items-center gap-2">
                        <label class="whitespace-nowrap">Search Associate by:</label>

                        <select name="search_by"
                            class="w-[110px] h-[34px] sm:h-[24px] rounded-[4px] border border-[#d8dce2] bg-[#f2f4f7] px-2 text-[14px] sm:text-[15px] text-[#555] outline-none">
                            <option value=""> </option>
                            <option value="customer_id" <?= $searchBy === 'customer_id' ? 'selected' : '' ?>>ID</option>
                            <option value="name" <?= $searchBy === 'name' ? 'selected' : '' ?>>Name</option>
                        </select>

                        <input type="text" name="search_value" value="<?= htmlspecialchars($searchValue) ?>"
                            class="w-[150px] h-[34px] sm:h-[24px] rounded-[4px] border border-[#d8dce2] bg-[#f2f4f7] px-2 text-[14px] sm:text-[15px] outline-none">
                    </div>

                    <div class="flex items-center gap-2">
                        <label class="whitespace-nowrap">Search Incentive:</label>
                        <input type="text" name="search_incentive" value="<?= htmlspecialchars($searchIncentive) ?>"
                            class="w-[150px] h-[34px] sm:h-[24px] rounded-[4px] border border-[#d8dce2] bg-[#f2f4f7] px-2 text-[14px] sm:text-[15px] outline-none">
                    </div>

                    <div class="flex items-center gap-2">
                        <span class="text-[#888]">From:</span>
                        <input type="date" name="from_date" value="<?= htmlspecialchars($fromDate) ?>"
                            class="w-[150px] h-[34px] sm:h-[24px] rounded-[4px] border border-[#d8dce2] bg-[#f2f4f7] px-2 text-[13px] sm:text-[14px] outline-none">
                    </div>

                    <div class="flex items-center gap-2">
                        <span class="text-[#888]">To:</span>
                        <input type="date" name="to_date" value="<?= htmlspecialchars($toDate) ?>"
                            class="w-[150px] h-[34px] sm:h-[24px] rounded-[4px] border border-[#d8dce2] bg-[#f2f4f7] px-2 text-[13px] sm:text-[14px] outline-none">
                    </div>

                    <button type="submit"
                        class="h-[36px] sm:h-[28px] px-5 rounded-[5px] bg-[#c9983b] text-white text-[14px] font-semibold">
                        Search
                    </button>

                    <a href="club_achiever.php"
                        class="h-[36px] sm:h-[28px] px-5 rounded-[5px] bg-gray-500 text-white text-[14px] font-semibold flex items-center">
                        Reset
                    </a>
                </div>
            </form>

            <div class="mt-5 overflow-x-auto">
                <table class="w-full min-w-[1100px] lg:min-w-0 border-collapse text-[12px] sm:text-[14px] text-[#333]">
                    <thead>
                        <tr class="bg-[#cd9737] text-white text-[13px] sm:text-[15px] h-[34px]">
                            <th class="border border-[#ddd] px-2 sm:px-3 py-2 text-left">Sr No</th>
                            <th class="border border-[#ddd] px-2 sm:px-3 py-2 text-left">Join Date</th>
                            <th class="border border-[#ddd] px-2 sm:px-3 py-2 text-left">Customer ID</th>
                            <th class="border border-[#ddd] px-2 sm:px-3 py-2 text-left">Name</th>
                            <th class="border border-[#ddd] px-2 sm:px-3 py-2 text-left">Username</th>
                            <th class="border border-[#ddd] px-2 sm:px-3 py-2 text-left">Pool</th>
                            <th class="border border-[#ddd] px-2 sm:px-3 py-2 text-left">Reward / Bonus</th>
                            <th class="border border-[#ddd] px-2 sm:px-3 py-2 text-left">Total Bonus</th>
                            <th class="border border-[#ddd] px-2 sm:px-3 py-2 text-left">State</th>
                            <th class="border border-[#ddd] px-2 sm:px-3 py-2 text-left">District</th>
                            <th class="border border-[#ddd] px-2 sm:px-3 py-2 text-left">City</th>
                            <th class="border border-[#ddd] px-2 sm:px-3 py-2 text-left">Mobile No</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php if (!empty($rows)): ?>
                            <?php foreach ($rows as $index => $row): ?>
                                <tr class="bg-white hover:bg-[#fafafa]">
                                    <td class="border border-[#ddd] px-2 sm:px-3 py-2"><?= $index + 1 ?></td>
                                    <td class="border border-[#ddd] px-2 sm:px-3 py-2"><?= htmlspecialchars($row['join_date']) ?></td>
                                    <td class="border border-[#ddd] px-2 sm:px-3 py-2"><?= htmlspecialchars($row['customer_id']) ?></td>
                                    <td class="border border-[#ddd] px-2 sm:px-3 py-2"><?= htmlspecialchars($row['name']) ?></td>
                                    <td class="border border-[#ddd] px-2 sm:px-3 py-2"><?= htmlspecialchars($row['username']) ?></td>
                                    <td class="border border-[#ddd] px-2 sm:px-3 py-2"><?= htmlspecialchars($row['pool']) ?></td>
                                    <td class="border border-[#ddd] px-2 sm:px-3 py-2"><?= htmlspecialchars($row['reward']) ?></td>
                                    <td class="border border-[#ddd] px-2 sm:px-3 py-2 font-semibold">
                                        ₹<?= number_format((float)$row['total_bonus'], 2) ?>
                                    </td>
                                    <td class="border border-[#ddd] px-2 sm:px-3 py-2"><?= htmlspecialchars($row['state']) ?></td>
                                    <td class="border border-[#ddd] px-2 sm:px-3 py-2"><?= htmlspecialchars($row['district']) ?></td>
                                    <td class="border border-[#ddd] px-2 sm:px-3 py-2"><?= htmlspecialchars($row['city']) ?></td>
                                    <td class="border border-[#ddd] px-2 sm:px-3 py-2"><?= htmlspecialchars($row['mobile_no']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="12" class="border border-[#ddd] px-3 py-8 text-center text-gray-500">
                                    No bonus achieved yet.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

        </div>

    </main>
</div>

<script>
    lucide.createIcons();
</script>

</body>
</html>