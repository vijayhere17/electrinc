<?php
session_start();

$memberName   = $_SESSION['full_name'] ?? 'Swapnil Solanki';
$customerId   = $_SESSION['member_id'] ?? 'MLM-00484';
$managerName  = $_SESSION['full_name'] ?? 'Swapnil Solanki';
$managerRole  = 'MLM Manager';
$profileImage = 'https://i.pravatar.cc/80?img=12';

$associateId = $_POST['associate_id'] ?? '';
$uplineData = null;
$errorMessage = '';

include __DIR__ . '/config/db.php';

function hasColumn(array $columns, string $name): bool {
    return in_array(strtolower($name), $columns, true);
}

function tableExists(mysqli $conn, string $table): bool {
    $escaped = $conn->real_escape_string($table);
    $result = $conn->query("SHOW TABLES LIKE '{$escaped}'");
    return $result instanceof mysqli_result && $result->num_rows > 0;
}

function getTableColumns(mysqli $conn, string $table): array {
    $columns = [];
    if (!tableExists($conn, $table)) {
        return $columns;
    }

    $result = $conn->query("SHOW COLUMNS FROM `{$table}`");
    if ($result instanceof mysqli_result) {
        while ($row = $result->fetch_assoc()) {
            if (isset($row['Field'])) {
                $columns[] = strtolower((string)$row['Field']);
            }
        }
    }

    return $columns;
}

function firstExistingColumn(array $columns, array $candidates): ?string {
    foreach ($candidates as $candidate) {
        if (hasColumn($columns, $candidate)) {
            return $candidate;
        }
    }

    return null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $associateId !== '') {
    $sourceTable = null;
    $columns = [];

    if (tableExists($conn, 'associates')) {
        $assocCount = 0;
        $countRes = $conn->query('SELECT COUNT(*) AS total FROM associates');
        if ($countRes instanceof mysqli_result) {
            $assocCount = (int)($countRes->fetch_assoc()['total'] ?? 0);
        }

        if ($assocCount > 0) {
            $sourceTable = 'associates';
            $columns = getTableColumns($conn, 'associates');
        }
    }

    if ($sourceTable === null && tableExists($conn, 'signup_users')) {
        $sourceTable = 'signup_users';
        $columns = getTableColumns($conn, 'signup_users');
    }

    if ($sourceTable) {
        if ($sourceTable === 'associates') {
            $idCol = firstExistingColumn($columns, ['login_win_code', 'win_code']);
            $nameCol = firstExistingColumn($columns, ['name', 'full_name']);
            $positionCol = firstExistingColumn($columns, ['position', 'side']);
            $uplineCol = firstExistingColumn($columns, ['upline_win_code', 'upline_id', 'sponsor_id']);
            $introCol = firstExistingColumn($columns, ['introducer_win_code', 'introducer_id', 'referral_id', 'refferal_id']);
        } else {
            $idCol = firstExistingColumn($columns, ['customer_id', 'member_id', 'associate_id', 'login_id']);
            $nameCol = firstExistingColumn($columns, ['full_name', 'name']);
            $positionCol = firstExistingColumn($columns, ['position', 'side']);
            $uplineCol = firstExistingColumn($columns, ['placement_id', 'upline_id', 'sponsor_id', 'parent_id']);
            $introCol = firstExistingColumn($columns, ['sponsor_id', 'referral_id', 'refferal_id', 'left_referral_code', 'right_referral_code']);
        }

        if ($idCol && $uplineCol) {
            $sql = 'SELECT '
                . "a.{$idCol} AS login_win_code, "
                . ($nameCol ? "a.{$nameCol} AS name, " : "'' AS name, ")
                . ($positionCol ? "a.{$positionCol} AS position, " : "'' AS position, ")
                . "a.{$uplineCol} AS upline_win_code, "
                . ($introCol ? "a.{$introCol} AS introducer_win_code, " : "'' AS introducer_win_code, ")
                . ($nameCol ? "u.{$nameCol} AS upline_name, i.{$nameCol} AS introducer_name " : "'' AS upline_name, '' AS introducer_name ")
                . "FROM {$sourceTable} a "
                . "LEFT JOIN {$sourceTable} u ON a.{$uplineCol} = u.{$idCol} "
                . ($introCol ? "LEFT JOIN {$sourceTable} i ON a.{$introCol} = i.{$idCol} " : "LEFT JOIN {$sourceTable} i ON 1 = 0 ")
                . "WHERE a.{$idCol} = ? LIMIT 1";

            $stmt = $conn->prepare($sql);
            if ($stmt) {
                $stmt->bind_param('s', $associateId);
                $stmt->execute();
                $result = $stmt->get_result();
                $uplineData = $result ? $result->fetch_assoc() : null;
                $stmt->close();
            }
        } else {
            $errorMessage = 'Upline mapping columns are missing in database schema.';
        }
    } else {
        $errorMessage = 'No member table found in database.';
    }

    if (!$uplineData && $errorMessage === '') {
        $errorMessage = 'No upline data found for this associate ID.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upline View</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body class="m-0 bg-[#f3f4f6] font-sans overflow-x-hidden">

    <?php include __DIR__ . '/includes/sidebar.php'; ?>

    <div class="ml-64 min-h-screen w-[calc(100%-16rem)]">
        <?php include __DIR__ . '/includes/navbar.php'; ?>

        <main class="px-5 py-4">

            <!-- Section Heading -->
            <div class="flex items-center gap-4 mb-6">
                <div class="w-9 h-9 rounded-[8px] bg-[#f6f2ea] flex items-center justify-center shrink-0">
                    <i data-lucide="users-round" class="w-4 h-4 text-[#c9983b]"></i>
                </div>

                <h2 class="text-[17px] md:text-[18px] leading-none text-[#2f2f2f] font-normal whitespace-nowrap"
                    style="font-family: Georgia, 'Times New Roman', serif;">
                    UPLINE VIEW
                </h2>

                <div class="h-[1px] bg-[#c9983b] flex-1"></div>
            </div>

            <!-- Main Box -->
            <div class="bg-white border border-[#dfdfdf] rounded-[12px] min-h-[112px] px-6 py-8">

                <form method="POST">
                    <div class="flex items-center justify-center gap-3 pt-3">
                        <label for="associate_id" class="text-[16px] md:text-[16px] text-[#3a3a3a] font-medium whitespace-nowrap">
                            Enter Associate Id to view Upline
                        </label>

                        <input
                            type="text"
                            id="associate_id"
                            name="associate_id"
                            value="<?php echo htmlspecialchars($associateId); ?>"
                            class="w-[118px] h-[24px] rounded-[8px] border border-[#d4d7dc] bg-[#f3f5f8] px-3 text-[15px] text-[#222] outline-none focus:border-[#c79335]"
                        >

                        <button
                            type="submit"
                            class="h-[30px] min-w-[61px] rounded-[10px] bg-[#c79335] text-white text-[15px] font-medium px-4 hover:opacity-95 transition"
                        >
                            View
                        </button>
                    </div>
                </form>

                <?php if ($errorMessage !== ''): ?>
                    <div class="mt-5 text-center text-[13px] text-red-600">
                        <?php echo htmlspecialchars($errorMessage); ?>
                    </div>
                <?php endif; ?>

                <?php if ($uplineData): ?>
                    <div class="mt-6 overflow-x-auto">
                        <table class="w-full border-separate border-spacing-0">
                            <thead>
                                <tr class="bg-[#c9983b] text-white text-[12px]">
                                    <th class="px-4 py-3 text-left">Associate ID</th>
                                    <th class="px-4 py-3 text-left">Name</th>
                                    <th class="px-4 py-3 text-left">Position</th>
                                    <th class="px-4 py-3 text-left">Upline ID</th>
                                    <th class="px-4 py-3 text-left">Upline Name</th>
                                    <th class="px-4 py-3 text-left">Introducer ID</th>
                                    <th class="px-4 py-3 text-left">Introducer Name</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr class="bg-white text-[12px] text-[#2f2f2f]">
                                    <td class="px-4 py-3 border-b border-[#ececec]"><?php echo htmlspecialchars($uplineData['login_win_code'] ?? ''); ?></td>
                                    <td class="px-4 py-3 border-b border-[#ececec]"><?php echo htmlspecialchars($uplineData['name'] ?? ''); ?></td>
                                    <td class="px-4 py-3 border-b border-[#ececec]"><?php echo htmlspecialchars($uplineData['position'] ?? ''); ?></td>
                                    <td class="px-4 py-3 border-b border-[#ececec]"><?php echo htmlspecialchars($uplineData['upline_win_code'] ?? ''); ?></td>
                                    <td class="px-4 py-3 border-b border-[#ececec]"><?php echo htmlspecialchars($uplineData['upline_name'] ?? ''); ?></td>
                                    <td class="px-4 py-3 border-b border-[#ececec]"><?php echo htmlspecialchars($uplineData['introducer_win_code'] ?? ''); ?></td>
                                    <td class="px-4 py-3 border-b border-[#ececec]"><?php echo htmlspecialchars($uplineData['introducer_name'] ?? ''); ?></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>

            </div>

        </main>
    </div>

    <script>
        lucide.createIcons();
    </script>
</body>
</html>