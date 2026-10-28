<?php
session_start();

if (!isset($_SESSION['customer_id'])) {
    header("Location: ../login.php");
    exit();
}

require_once __DIR__ . '/../config/db.php';

if (!isset($conn) || !($conn instanceof mysqli)) {
    die("Database connection failed. Please check config/db.php");
}

$conn->set_charset("utf8mb4");

function tableExists(mysqli $conn, string $table): bool
{
    $stmt = $conn->prepare("SELECT COUNT(*) AS c FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?");
    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('s', $table);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    return !empty($row['c']);
}

function getTableColumns(mysqli $conn, string $table): array
{
    $columns = [];
    $stmt = $conn->prepare("SHOW COLUMNS FROM `{$table}`");

    if (!$stmt) {
        return $columns;
    }

    $stmt->execute();
    $result = $stmt->get_result();

    while ($result && ($row = $result->fetch_assoc())) {
        $columns[] = $row['Field'];
    }

    $stmt->close();
    return $columns;
}

function columnExistsInList(array $columns, string $column): bool
{
    return in_array($column, $columns, true);
}

function buildDescriptors(array $columns, string $prefix, string $section): array
{
    $descriptors = [];

    foreach ($columns as $column) {
        $descriptors[] = [
            'key' => $prefix . $column,
            'column' => $column,
            'section' => $section,
            'label' => $section . '.' . $column,
        ];
    }

    return $descriptors;
}

function bindDynamicParams(mysqli_stmt $stmt, string $types, array &$values): bool
{
    $params = [$types];

    foreach ($values as $index => &$value) {
        $params[] = &$value;
    }
    unset($value);

    return call_user_func_array([$stmt, 'bind_param'], $params);
}

function formatCellValue(string $column, $value): string
{
    $value = (string)$value;

    if ($value === '') {
        return '<span class="text-gray-400">NULL</span>';
    }

    if ($column === 'password') {
        return '<span class="font-mono text-gray-500">******</span>';
    }

    if (strpos($value, 'http://') !== false || strpos($value, 'https://') !== false) {
        return '<span class="break-all text-blue-700">' . htmlspecialchars($value) . '</span>';
    }

    return htmlspecialchars($value);
}

function buildSelectParts(string $alias, array $descriptors): array
{
    $parts = [];

    foreach ($descriptors as $descriptor) {
        $parts[] = sprintf('%s.`%s` AS `%s`', $alias, $descriptor['column'], $descriptor['key']);
    }

    return $parts;
}

$search = trim($_GET['q'] ?? '');
$signupTable = 'signup_users';
$profileTable = 'member_profiles';
$signupExists = tableExists($conn, $signupTable);
$profileExists = tableExists($conn, $profileTable);

$signupColumns = $signupExists ? getTableColumns($conn, $signupTable) : [];
$profileColumns = $profileExists ? getTableColumns($conn, $profileTable) : [];

$signupIdColumn = columnExistsInList($signupColumns, 'customer_id') ? 'customer_id' : (columnExistsInList($signupColumns, 'member_id') ? 'member_id' : '');
$orderColumn = columnExistsInList($signupColumns, 'id') ? 'su.id' : ($signupIdColumn !== '' ? 'su.`' . $signupIdColumn . '`' : '1');

$signupDescriptors = buildDescriptors($signupColumns, 'signup__', 'Signup');
$profileDescriptors = $profileExists ? buildDescriptors($profileColumns, 'profile__', 'Profile') : [];
$allDescriptors = array_merge($signupDescriptors, $profileDescriptors);

$rows = [];
$totalUsers = 0;
$activeUsers = 0;
$totalProfiles = 0;

if ($signupExists) {
    $countStmt = $conn->prepare("SELECT COUNT(*) AS total_count FROM `{$signupTable}`");
    if ($countStmt) {
        $countStmt->execute();
        $countResult = $countStmt->get_result();
        $countRow = $countResult ? $countResult->fetch_assoc() : null;
        $totalUsers = (int)($countRow['total_count'] ?? 0);
        $countStmt->close();
    }

    if (columnExistsInList($signupColumns, 'registration_status')) {
        $activeStmt = $conn->prepare("SELECT COUNT(*) AS active_count FROM `{$signupTable}` WHERE registration_status = 'Active'");
        if ($activeStmt) {
            $activeStmt->execute();
            $activeResult = $activeStmt->get_result();
            $activeRow = $activeResult ? $activeResult->fetch_assoc() : null;
            $activeUsers = (int)($activeRow['active_count'] ?? 0);
            $activeStmt->close();
        }
    }

    if ($profileExists) {
        $profileCountStmt = $conn->prepare("SELECT COUNT(*) AS profile_count FROM `{$profileTable}`");
        if ($profileCountStmt) {
            $profileCountStmt->execute();
            $profileCountResult = $profileCountStmt->get_result();
            $profileCountRow = $profileCountResult ? $profileCountResult->fetch_assoc() : null;
            $totalProfiles = (int)($profileCountRow['profile_count'] ?? 0);
            $profileCountStmt->close();
        }
    }

    $selectParts = buildSelectParts('su', $signupDescriptors);

    if ($profileExists) {
        $selectParts = array_merge($selectParts, buildSelectParts('mp', $profileDescriptors));
    }

    $sql = 'SELECT ' . implode(', ', $selectParts) . ' FROM `' . $signupTable . '` su';

    if ($profileExists) {
        $sql .= ' LEFT JOIN `' . $profileTable . '` mp ON mp.customer_id = su.' . $signupIdColumn;
    }

    $whereParts = [];
    $bindValues = [];
    $bindTypes = '';

    if ($search !== '') {
        foreach (['customer_id', 'member_id', 'full_name', 'email', 'mobile', 'placement_id', 'registration_status'] as $column) {
            if (columnExistsInList($signupColumns, $column)) {
                $whereParts[] = 'su.`' . $column . '` LIKE ?';
                $bindValues[] = '%' . $search . '%';
                $bindTypes .= 's';
            }
        }

        if ($profileExists) {
            foreach (['customer_id', 'full_name', 'email', 'mobile_no', 'house_number', 'street_name', 'state_name', 'district_name', 'city_name', 'pincode', 'aadhar_number', 'pan_number', 'gst_number'] as $column) {
                if (columnExistsInList($profileColumns, $column)) {
                    $whereParts[] = 'mp.`' . $column . '` LIKE ?';
                    $bindValues[] = '%' . $search . '%';
                    $bindTypes .= 's';
                }
            }
        }

        if (!empty($whereParts)) {
            $sql .= ' WHERE ' . implode(' OR ', $whereParts);
        }
    }

    $sql .= ' ORDER BY ' . $orderColumn . ' DESC LIMIT 200';

    $stmt = $conn->prepare($sql);
    if ($stmt) {
        if (!empty($bindValues)) {
            bindDynamicParams($stmt, $bindTypes, $bindValues);
        }

        $stmt->execute();
        $result = $stmt->get_result();

        while ($result && ($row = $result->fetch_assoc())) {
            $rows[] = $row;
        }

        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Signup Users Panel</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body class="min-h-screen bg-gradient-to-br from-slate-100 via-white to-slate-200 text-slate-800">
    <div class="max-w-[1600px] mx-auto px-4 sm:px-6 lg:px-8 py-6 sm:py-8">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-xs uppercase tracking-[0.3em] text-[#8a6b2d] font-semibold">Admin Data Panel</p>
                <h1 class="mt-2 text-2xl sm:text-3xl font-bold text-slate-900">Joined Member Data</h1>
                <p class="mt-2 text-sm text-slate-600">Dynamic view of <span class="font-semibold">signup_users</span> joined with <span class="font-semibold">member_profiles</span>.</p>
            </div>

            <div class="grid grid-cols-2 gap-3 sm:min-w-[320px]">
                <div class="rounded-2xl bg-white border border-slate-200 shadow-sm px-4 py-3">
                    <div class="text-xs uppercase tracking-wide text-slate-500">Total Users</div>
                    <div class="mt-1 text-2xl font-bold text-slate-900"><?php echo number_format($totalUsers); ?></div>
                </div>
                <div class="rounded-2xl bg-white border border-slate-200 shadow-sm px-4 py-3">
                    <div class="text-xs uppercase tracking-wide text-slate-500">Active Users</div>
                    <div class="mt-1 text-2xl font-bold text-slate-900"><?php echo number_format($activeUsers); ?></div>
                </div>
            </div>
        </div>

        <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div class="rounded-2xl bg-white border border-slate-200 shadow-sm px-4 py-3">
                <div class="text-xs uppercase tracking-wide text-slate-500">Profile Rows</div>
                <div class="mt-1 text-2xl font-bold text-slate-900"><?php echo number_format($totalProfiles); ?></div>
            </div>
            <div class="rounded-2xl bg-white border border-slate-200 shadow-sm px-4 py-3">
                <div class="text-xs uppercase tracking-wide text-slate-500">Join Type</div>
                <div class="mt-1 text-lg font-semibold text-slate-900"><?php echo $profileExists ? 'LEFT JOIN' : 'SIGNUP ONLY'; ?></div>
            </div>
        </div>

        <form method="GET" class="mt-6 rounded-2xl bg-white border border-slate-200 shadow-sm p-4 sm:p-5">
            <div class="flex flex-col gap-3 lg:flex-row lg:items-center">
                <div class="flex-1 relative">
                    <i data-lucide="search" class="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2"></i>
                    <input
                        type="text"
                        name="q"
                        value="<?php echo htmlspecialchars($search); ?>"
                        placeholder="Search customer id, name, email, mobile, PAN, Aadhaar"
                        class="w-full h-11 rounded-xl border border-slate-300 pl-10 pr-4 outline-none focus:border-[#8a6b2d]"
                    >
                </div>
                <div class="flex gap-3">
                    <button type="submit" class="h-11 px-5 rounded-xl bg-[#173a67] text-white font-semibold hover:opacity-95 transition">Search</button>
                    <a href="signup-users.php" class="h-11 px-5 rounded-xl border border-slate-300 bg-slate-50 text-slate-700 font-semibold flex items-center justify-center">Reset</a>
                </div>
            </div>
        </form>

        <?php if (!$signupExists): ?>
            <div class="mt-6 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-amber-900">
                signup_users table was not found in the database.
            </div>
        <?php elseif (empty($rows)): ?>
            <div class="mt-6 rounded-2xl border border-slate-200 bg-white px-4 py-10 text-center text-slate-500 shadow-sm">
                No matching users found.
            </div>
        <?php else: ?>
            <div class="mt-6 lg:hidden space-y-4">
                <?php foreach ($rows as $row): ?>
                    <div class="rounded-2xl bg-white border border-slate-200 shadow-sm p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <div class="text-sm font-semibold text-slate-900"><?php echo htmlspecialchars($row['signup__full_name'] ?? ''); ?></div>
                                <div class="text-xs text-slate-500 mt-1"><?php echo htmlspecialchars($row['signup__customer_id'] ?? ($row['signup__member_id'] ?? '')); ?></div>
                            </div>
                            <div class="text-xs rounded-full px-3 py-1 bg-slate-100 text-slate-700">
                                <?php echo htmlspecialchars($row['signup__registration_status'] ?? ''); ?>
                            </div>
                        </div>

                        <div class="mt-4 space-y-4 text-sm">
                            <div>
                                <div class="text-xs font-semibold uppercase tracking-wide text-[#173a67] mb-2">Signup Table</div>
                                <div class="grid grid-cols-1 gap-3">
                                    <?php foreach ($signupDescriptors as $descriptor): ?>
                                        <div class="rounded-xl bg-slate-50 border border-slate-200 p-3">
                                            <div class="text-[11px] uppercase tracking-wide text-slate-500"><?php echo htmlspecialchars($descriptor['label']); ?></div>
                                            <div class="mt-1 break-words"><?php echo formatCellValue($descriptor['column'], $row[$descriptor['key']] ?? ''); ?></div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <div>
                                <div class="text-xs font-semibold uppercase tracking-wide text-[#8a6b2d] mb-2">Member Profile Table</div>
                                <div class="grid grid-cols-1 gap-3">
                                    <?php if ($profileExists): ?>
                                        <?php foreach ($profileDescriptors as $descriptor): ?>
                                            <div class="rounded-xl bg-amber-50 border border-amber-200 p-3">
                                                <div class="text-[11px] uppercase tracking-wide text-amber-700"><?php echo htmlspecialchars($descriptor['label']); ?></div>
                                                <div class="mt-1 break-words"><?php echo formatCellValue($descriptor['column'], $row[$descriptor['key']] ?? ''); ?></div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <div class="rounded-xl bg-amber-50 border border-amber-200 p-3 text-amber-800">member_profiles table was not found.</div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="mt-6 hidden lg:block rounded-2xl bg-white border border-slate-200 shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-max w-full border-collapse">
                        <thead class="sticky top-0 bg-[#173a67] text-white">
                            <tr>
                                <?php foreach ($allDescriptors as $descriptor): ?>
                                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide whitespace-nowrap border-r border-white/10">
                                        <?php echo htmlspecialchars($descriptor['label']); ?>
                                    </th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rows as $index => $row): ?>
                                <tr class="<?php echo $index % 2 === 0 ? 'bg-white' : 'bg-slate-50'; ?> border-b border-slate-200">
                                    <?php foreach ($allDescriptors as $descriptor): ?>
                                        <td class="px-4 py-3 text-sm align-top border-r border-slate-100 max-w-[260px] whitespace-normal break-words">
                                            <?php echo formatCellValue($descriptor['column'], $row[$descriptor['key']] ?? ''); ?>
                                        </td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script>
        lucide.createIcons();
    </script>
</body>
</html>