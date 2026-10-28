<?php
session_start();

if (!isset($_SESSION['member_id'])) {
    header('Location: login.php');
    exit();
}

include __DIR__ . '/config/db.php';

function tableExists(mysqli $conn, string $tableName): bool {
    $safeName = $conn->real_escape_string($tableName);
    $result = $conn->query("SHOW TABLES LIKE '{$safeName}'");
    return $result instanceof mysqli_result && $result->num_rows > 0;
}

$search = trim($_GET['search'] ?? '');
$sourceFilter = trim($_GET['source'] ?? 'All');
$memberRows = [];
$errorMessage = '';

$tables = [];
if (tableExists($conn, 'signup_users')) {
    $tables[] = 'signup_users';
}

if (empty($tables)) {
    $errorMessage = 'No member tables found in the database.';
} else {
    foreach ($tables as $table) {
        if ($sourceFilter !== 'All' && $sourceFilter !== $table) {
            continue;
        }

        $sql = "SELECT '{$table}' AS source_table, id, customer_id, full_name, dob, gender, email, mobile, address, pincode, state, city, district, created_at FROM `{$table}` WHERE 1=1";
        $params = [];
        $types = '';

        if ($search !== '') {
            $sql .= ' AND (customer_id LIKE ? OR full_name LIKE ? OR email LIKE ? OR mobile LIKE ?)';
            $types .= 'ssss';
            $likeSearch = '%' . $search . '%';
            $params[] = $likeSearch;
            $params[] = $likeSearch;
            $params[] = $likeSearch;
            $params[] = $likeSearch;
        }

        $sql .= ' ORDER BY created_at DESC, id DESC';

        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            $errorMessage = 'Database query preparation failed.';
            continue;
        }

        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }

        if ($stmt->execute()) {
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $memberRows[] = $row;
            }
        }

        $stmt->close();
    }

    usort($memberRows, static function (array $left, array $right): int {
        return strcmp((string)($right['created_at'] ?? ''), (string)($left['created_at'] ?? ''))
            ?: ((int)($right['id'] ?? 0) <=> (int)($left['id'] ?? 0));
    });
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All Members</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body class="m-0 bg-[#f3f4f6] font-sans overflow-x-hidden">

    <?php include __DIR__ . '/includes/sidebar.php'; ?>

    <div class="ml-64 min-h-screen w-[calc(100%-16rem)]">
        <?php include __DIR__ . '/includes/navbar.php'; ?>

        <main class="px-8 py-5">
            <div class="flex items-center gap-4 mb-8">
                <div class="w-9 h-9 rounded-[8px] border border-[#ead9b5] bg-[#f8f1e7] flex items-center justify-center shrink-0">
                    <i data-lucide="users" class="w-5 h-5 text-[#c79435]"></i>
                </div>

                <h2 class="text-[24px] font-normal text-[#313131] leading-none tracking-wide whitespace-nowrap" style="font-family: Georgia, 'Times New Roman', serif;">ALL MEMBERS</h2>
                <div class="h-[1px] bg-[#c9963b] flex-1"></div>
            </div>

            <?php if ($errorMessage !== ''): ?>
                <div class="mb-5 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                    <?php echo htmlspecialchars($errorMessage); ?>
                </div>
            <?php endif; ?>

            <div class="w-full rounded-[18px] border border-[#dddddd] bg-white px-6 py-6">
                <form method="GET" class="mb-4 flex flex-wrap items-center gap-3 text-sm text-[#374151]">
                    <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search by ID, name, email, mobile" class="h-10 w-full max-w-md rounded-md border border-[#d1d5db] px-3 outline-none">
                    <select name="source" class="h-10 rounded-md border border-[#d1d5db] px-3 outline-none bg-white">
                        <option value="All" <?php echo $sourceFilter === 'All' ? 'selected' : ''; ?>>All Sources</option>
                        <option value="signup_users" <?php echo $sourceFilter === 'signup_users' ? 'selected' : ''; ?>>signup_users</option>
                    </select>
                    <button type="submit" class="h-10 rounded-md bg-[#143a66] px-4 text-white font-medium">Search</button>
                </form>

                <div class="overflow-x-auto rounded-[8px] border border-[#d6a246]">
                    <table class="w-full min-w-[1200px] border-collapse">
                        <thead>
                            <tr class="bg-[#cd9737] text-white text-[15px] h-[34px]">
                                <!-- <th class="px-3 text-left">Source</th> -->
                                <th class="px-3 text-left">Login ID</th>
                                <th class="px-3 text-left">Name</th>
                                <th class="px-3 text-left">DOB</th>
                                <th class="px-3 text-left">Gender</th>
                                <th class="px-3 text-left">Email</th>
                                <th class="px-3 text-left">Mobile</th>
                                <th class="px-3 text-left">City</th>
                                <th class="px-3 text-left">State</th>
                                <th class="px-3 text-left">District</th>
                                <th class="px-3 text-left">Pincode</th>
                                <th class="px-3 text-left">Created At</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($memberRows)): ?>
                                <?php foreach ($memberRows as $row): ?>
                                    <tr class="border-t border-[#ececec] text-[15px] text-[#2d2d2d] h-[34px]">
                                        <!-- <td class="px-3 whitespace-nowrap"><?php echo htmlspecialchars($row['source_table'] ?? ''); ?></td> -->
                                        <td class="px-3 whitespace-nowrap"><?php echo htmlspecialchars($row['customer_id'] ?? ''); ?></td>
                                        <td class="px-3 whitespace-nowrap"><?php echo htmlspecialchars($row['full_name'] ?? ''); ?></td>
                                        <td class="px-3 whitespace-nowrap"><?php echo htmlspecialchars($row['dob'] ?? ''); ?></td>
                                        <td class="px-3 whitespace-nowrap"><?php echo htmlspecialchars($row['gender'] ?? ''); ?></td>
                                        <td class="px-3 whitespace-nowrap"><?php echo htmlspecialchars($row['email'] ?? ''); ?></td>
                                        <td class="px-3 whitespace-nowrap"><?php echo htmlspecialchars($row['mobile'] ?? ''); ?></td>
                                        <td class="px-3 whitespace-nowrap"><?php echo htmlspecialchars($row['city'] ?? ''); ?></td>
                                        <td class="px-3 whitespace-nowrap"><?php echo htmlspecialchars($row['state'] ?? ''); ?></td>
                                        <td class="px-3 whitespace-nowrap"><?php echo htmlspecialchars($row['district'] ?? ''); ?></td>
                                        <td class="px-3 whitespace-nowrap"><?php echo htmlspecialchars($row['pincode'] ?? ''); ?></td>
                                        <td class="px-3 whitespace-nowrap"><?php echo htmlspecialchars($row['created_at'] ?? ''); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="12" class="h-[180px] text-center text-[#64748b]">No members found.</td>
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