<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (file_exists(__DIR__ . '/member_auth.php')) {
    include __DIR__ . '/member_auth.php';
}

include __DIR__ . '/config/db.php';

if (!isset($_SESSION['customer_id']) && !isset($_SESSION['member_id'])) {
    header("Location: login.php");
    exit();
}

if (!isset($conn) || !($conn instanceof mysqli)) {
    die("Database connection failed.");
}

$conn->set_charset("utf8mb4");

$memberName   = $_SESSION['full_name'] ?? "Member";
$customerId   = $_SESSION['customer_id'] ?? $_SESSION['member_id'];
$managerName  = "Swapnil Solanki";
$managerRole  = "MLM Manager";
$profileImage = "https://i.pravatar.cc/80?img=12";
$navTitle     = "Referral Bonus";
$navIcon      = "gift";

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

$conn->query("
    CREATE TABLE IF NOT EXISTS referral_bonus_status (
        id INT AUTO_INCREMENT PRIMARY KEY,
        customer_id VARCHAR(50) NOT NULL,
        bonus_date DATE DEFAULT CURRENT_DATE,
        transaction_id VARCHAR(100) NULL,
        club_no INT DEFAULT 0,
        referral_bonus DECIMAL(12,2) DEFAULT 0,
        senior1_bonus DECIMAL(12,2) DEFAULT 0,
        senior2_bonus DECIMAL(12,2) DEFAULT 0,
        earned DECIMAL(12,2) DEFAULT 0,
        status VARCHAR(50) DEFAULT 'Pending',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )
");

ensureColumn($conn, 'referral_bonus_status', 'customer_id', "VARCHAR(50) NOT NULL");
ensureColumn($conn, 'referral_bonus_status', 'bonus_date', "DATE DEFAULT CURRENT_DATE");
ensureColumn($conn, 'referral_bonus_status', 'transaction_id', "VARCHAR(100) NULL");
ensureColumn($conn, 'referral_bonus_status', 'club_no', "INT DEFAULT 0");
ensureColumn($conn, 'referral_bonus_status', 'referral_bonus', "DECIMAL(12,2) DEFAULT 0");
ensureColumn($conn, 'referral_bonus_status', 'senior1_bonus', "DECIMAL(12,2) DEFAULT 0");
ensureColumn($conn, 'referral_bonus_status', 'senior2_bonus', "DECIMAL(12,2) DEFAULT 0");
ensureColumn($conn, 'referral_bonus_status', 'earned', "DECIMAL(12,2) DEFAULT 0");
ensureColumn($conn, 'referral_bonus_status', 'status', "VARCHAR(50) DEFAULT 'Pending'");

$referralMaster = [
    1  => ['referral' => 200,      'senior1' => 50,       'senior2' => 50],
    2  => ['referral' => 500,      'senior1' => 200,      'senior2' => 100],
    3  => ['referral' => 1200,     'senior1' => 500,      'senior2' => 300],
    4  => ['referral' => 3000,     'senior1' => 1200,     'senior2' => 800],
    5  => ['referral' => 7000,     'senior1' => 3000,     'senior2' => 2000],
    6  => ['referral' => 18000,    'senior1' => 7000,     'senior2' => 5000],
    7  => ['referral' => 45000,    'senior1' => 18000,    'senior2' => 12000],
    8  => ['referral' => 110000,   'senior1' => 45000,    'senior2' => 30000],
    9  => ['referral' => 250000,   'senior1' => 110000,   'senior2' => 70000],
    10 => ['referral' => 650000,   'senior1' => 250000,   'senior2' => 170000],
    11 => ['referral' => 1500000,  'senior1' => 600000,   'senior2' => 350000],
    12 => ['referral' => 3600000,  'senior1' => 1500000,  'senior2' => 900000],
    13 => ['referral' => 9000000,  'senior1' => 3500000,  'senior2' => 2000000],
    14 => ['referral' => 21000000, 'senior1' => 8000000,  'senior2' => 5000000],
    15 => ['referral' => 50000000, 'senior1' => 20000000, 'senior2' => 12500000],
];

function formatDateValue($value) {
    if (empty($value) || $value === '0000-00-00' || $value === '0000-00-00 00:00:00') {
        return '-';
    }

    $timestamp = strtotime($value);
    return $timestamp ? date('d-m-Y', $timestamp) : '-';
}

$referralBonusRows = [];
$totalEarned = 0;
$totalRecords = 0;
$debugMessage = '';

$sql = "
    SELECT *
    FROM referral_bonus_status
    WHERE customer_id = ?
    ORDER BY id DESC
";

$stmt = $conn->prepare($sql);

if ($stmt) {
    $stmt->bind_param("s", $customerId);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $clubNo = (int)($row['club_no'] ?? 0);

        if ($clubNo <= 0 && !empty($row['club'])) {
            $clubNo = (int)$row['club'];
        }

        if ($clubNo <= 0 && !empty($row['club_level']) && is_numeric($row['club_level'])) {
            $clubNo = (int)$row['club_level'];
        }

        $masterReferral = $referralMaster[$clubNo]['referral'] ?? 0;
        $masterSenior1  = $referralMaster[$clubNo]['senior1'] ?? 0;
        $masterSenior2  = $referralMaster[$clubNo]['senior2'] ?? 0;

        $refBonus = (float)($row['referral_bonus'] ?? $masterReferral);
        $senior1  = (float)($row['senior1_bonus'] ?? $masterSenior1);
        $senior2  = (float)($row['senior2_bonus'] ?? $masterSenior2);
        $earned   = (float)($row['earned'] ?? 0);

        if ($earned <= 0) {
            $earned = $refBonus + $senior1 + $senior2;
        }

        $totalEarned += $earned;
        $totalRecords++;

        $referralBonusRows[] = [
            'sr_no' => $row['id'] ?? '-',
            'date' => formatDateValue($row['bonus_date'] ?? $row['created_at'] ?? ''),
            'transaction_id' => $row['transaction_id'] ?? '-',
            'target_business' => 'Club ' . $clubNo,
            'referral' => $refBonus,
            'senior1' => $senior1,
            'senior2' => $senior2,
            'earned' => $earned,
            'status' => $row['status'] ?? 'Pending',
        ];
    }

    $stmt->close();
} else {
    $debugMessage = 'Query prepare failed.';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Referral Bonus</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        body{
            font-family: Arial, Helvetica, sans-serif;
            background:#f3f3f3;
        }
    </style>
</head>

<body class="bg-[#f3f3f3]">

<?php include __DIR__ . '/includes/sidebar.php'; ?>

<div class="ml-64 min-h-screen">
    <?php include __DIR__ . '/includes/navbar.php'; ?>

    <div class="px-5 py-3">

        <div class="mt-5 flex items-center gap-4">
            <div class="w-[36px] h-[36px] rounded-[8px] bg-[#f5eee3] flex items-center justify-center shrink-0">
                <i data-lucide="gift" class="w-[18px] h-[18px] text-[#cd9737]"></i>
            </div>

            <h2 class="whitespace-nowrap text-[20px] font-normal text-[#40352a]" style="font-family: Georgia, 'Times New Roman', serif;">
                REFERRAL BONUS
            </h2>

            <div class="h-[2px] w-full bg-[#d1a04a]"></div>
        </div>

        <div class="mt-6 rounded-[20px] bg-white border border-[#e5e7eb] shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full min-w-[1100px] border-collapse">
                    <thead>
                        <tr class="bg-[#C89741] text-white text-[13px] font-semibold">
                            <th class="px-4 py-3 border-b border-[#e7edf3] text-left text-[13px] font-bold">Sr No.</th>
                            <th class="px-4 py-3 border-b border-[#e7edf3] text-left text-[13px] font-bold">Date</th>
                            <th class="px-4 py-3 border-b border-[#e7edf3] text-left text-[13px] font-bold">Transaction ID</th>
                            <th class="px-4 py-3 border-b border-[#e7edf3] text-left text-[13px] font-bold">Target Business</th>
                            <th class="px-4 py-3 border-b border-[#e7edf3] text-left text-[13px] font-bold">Referral</th>
                            <th class="px-4 py-3 border-b border-[#e7edf3] text-left text-[13px] font-bold">1 Senior</th>
                            <th class="px-4 py-3 border-b border-[#e7edf3] text-left text-[13px] font-bold">2 Senior</th>
                            <th class="px-4 py-3 border-b border-[#e7edf3] text-left text-[13px] font-bold">Earned</th>
                            <th class="px-4 py-3 border-b border-[#e7edf3] text-left text-[13px] font-bold">Status</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php if (!empty($referralBonusRows)): ?>
                            <?php foreach ($referralBonusRows as $row): ?>
                                <?php
                                    $statusLower = strtolower(trim((string)$row['status']));
                                    $statusClass = 'bg-yellow-50 text-yellow-700 border-yellow-200';

                                    if ($statusLower === 'paid' || $statusLower === 'approved' || $statusLower === 'success') {
                                        $statusClass = 'bg-green-50 text-green-700 border-green-200';
                                    } elseif ($statusLower === 'reject' || $statusLower === 'rejected' || $statusLower === 'failed') {
                                        $statusClass = 'bg-red-50 text-red-700 border-red-200';
                                    } elseif ($statusLower === 'hold') {
                                        $statusClass = 'bg-blue-50 text-blue-700 border-blue-200';
                                    }
                                ?>

                                <tr class="hover:bg-[#fafbfd] transition">
                                    <td class="px-4 py-3 border-b border-[#eef2f6] text-[14px] text-[#374151]">
                                        <?php echo htmlspecialchars((string)$row['sr_no']); ?>
                                    </td>

                                    <td class="px-4 py-3 border-b border-[#eef2f6] text-[14px] text-[#374151]">
                                        <?php echo htmlspecialchars((string)$row['date']); ?>
                                    </td>

                                    <td class="px-4 py-3 border-b border-[#eef2f6] text-[14px] text-[#374151]">
                                        <?php echo htmlspecialchars((string)$row['transaction_id']); ?>
                                    </td>

                                    <td class="px-4 py-3 border-b border-[#eef2f6] text-[14px] text-[#374151]">
                                        <?php echo htmlspecialchars((string)$row['target_business']); ?>
                                    </td>

                                    <td class="px-4 py-3 border-b border-[#eef2f6] text-[14px] text-[#374151]">
                                        ₹<?php echo number_format((float)$row['referral'], 2); ?>
                                    </td>

                                    <td class="px-4 py-3 border-b border-[#eef2f6] text-[14px] text-[#374151]">
                                        ₹<?php echo number_format((float)$row['senior1'], 2); ?>
                                    </td>

                                    <td class="px-4 py-3 border-b border-[#eef2f6] text-[14px] text-[#374151]">
                                        ₹<?php echo number_format((float)$row['senior2'], 2); ?>
                                    </td>

                                    <td class="px-4 py-3 border-b border-[#eef2f6] text-[14px] font-semibold text-[#1f5c34]">
                                        ₹<?php echo number_format((float)$row['earned'], 2); ?>
                                    </td>

                                    <td class="px-4 py-3 border-b border-[#eef2f6] text-[14px]">
                                        <span class="inline-flex px-3 py-1 rounded-full border text-[12px] font-semibold <?php echo $statusClass; ?>">
                                            <?php echo htmlspecialchars((string)$row['status']); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>

                            <tr class="bg-[#fafbfd]">
                                <td colspan="7" class="px-4 py-4 border-t border-[#e5e7eb] text-right text-[15px] font-bold text-[#374151]">
                                    Total Earn
                                </td>
                                <td class="px-4 py-4 border-t border-[#e5e7eb] text-[15px] font-bold text-[#1f5c34]">
                                    ₹<?php echo number_format((float)$totalEarned, 2); ?>
                                </td>
                                <td class="px-4 py-4 border-t border-[#e5e7eb] text-[14px] font-semibold text-[#374151]">
                                    <?php echo $totalRecords; ?> Records
                                </td>
                            </tr>
                        <?php else: ?>
                            <tr>
                                <td colspan="9" class="px-4 py-8 text-center text-[14px] text-[#7b8794]">
                                    <?php echo $debugMessage !== '' ? htmlspecialchars($debugMessage) : 'No referral bonus records found.'; ?>
                                </td>
                            </tr>

                            <tr class="bg-[#fafbfd]">
                                <td colspan="7" class="px-4 py-4 border-t border-[#e5e7eb] text-right text-[15px] font-bold text-[#374151]">
                                    Total Earn
                                </td>
                                <td class="px-4 py-4 border-t border-[#e5e7eb] text-[15px] font-bold text-[#1f5c34]">
                                    ₹0.00
                                </td>
                                <td class="px-4 py-4 border-t border-[#e5e7eb] text-[14px] font-semibold text-[#374151]">
                                    0 Records
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>

<script>
    lucide.createIcons();
</script>

</body>
</html>