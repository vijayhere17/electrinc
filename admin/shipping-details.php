<?php
session_start();
include __DIR__ . '/config/db.php';

if (!isset($conn) || !($conn instanceof mysqli)) {
    die('Database connection failed.');
}

$conn->set_charset('utf8mb4');

$customerId = trim($_GET['customer_id'] ?? '');
$search = trim($_GET['search'] ?? '');
$focusMode = $customerId !== '';

function e($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function tableExists(mysqli $conn, string $tableName): bool
{
    $safeName = $conn->real_escape_string($tableName);
    $result = $conn->query("SHOW TABLES LIKE '{$safeName}'");
    return $result instanceof mysqli_result && $result->num_rows > 0;
}

function getTableColumns(mysqli $conn, string $tableName): array
{
    $columns = [];
    $result = $conn->query("SHOW COLUMNS FROM `{$tableName}`");
    if ($result instanceof mysqli_result) {
        while ($row = $result->fetch_assoc()) {
            if (isset($row['Field'])) {
                $columns[] = strtolower((string)$row['Field']);
            }
        }
    }
    return $columns;
}

function hasColumn(array $columns, string $name): bool
{
    return in_array(strtolower($name), $columns, true);
}

function firstExistingColumn(array $columns, array $candidates): ?string
{
    foreach ($candidates as $candidate) {
        if (hasColumn($columns, $candidate)) {
            return $candidate;
        }
    }
    return null;
}

function fetchShippingRow(mysqli $conn, string $customerId): ?array
{
    if (!tableExists($conn, 'shipping_details')) {
        return null;
    }

    $stmt = $conn->prepare('SELECT * FROM shipping_details WHERE customer_id = ? LIMIT 1');
    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('s', $customerId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result instanceof mysqli_result ? $result->fetch_assoc() : null;
    $stmt->close();

    return $row ?: null;
}

$memberTable = tableExists($conn, 'signup_users') ? 'signup_users' : (tableExists($conn, 'member_profiles') ? 'member_profiles' : '');
$memberColumns = $memberTable ? getTableColumns($conn, $memberTable) : [];
$idColumn = $memberTable ? firstExistingColumn($memberColumns, ['customer_id', 'member_id', 'user_id']) : null;
$nameColumn = $memberTable ? firstExistingColumn($memberColumns, ['full_name', 'name']) : null;
$mobileColumn = $memberTable ? firstExistingColumn($memberColumns, ['mobile', 'mobile_no', 'phone']) : null;
$emailColumn = $memberTable ? firstExistingColumn($memberColumns, ['email']) : null;

$members = [];
if ($memberTable && $idColumn) {
    $sql = "SELECT `{$idColumn}` AS customer_id";
    $sql .= $nameColumn ? ", `{$nameColumn}` AS full_name" : ", '' AS full_name";
    $sql .= $mobileColumn ? ", `{$mobileColumn}` AS mobile" : ", '' AS mobile";
    $sql .= $emailColumn ? ", `{$emailColumn}` AS email" : ", '' AS email";
    $sql .= " FROM `{$memberTable}`";
    if ($search !== '') {
        $sql .= " WHERE `{$idColumn}` LIKE ?";
    }
    $sql .= " ORDER BY `{$idColumn}` ASC";

    $stmt = $conn->prepare($sql);
    if ($stmt) {
        if ($search !== '') {
            $like = '%' . $search . '%';
            $stmt->bind_param('s', $like);
        }
        if ($stmt->execute()) {
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $members[] = $row;
            }
        }
        $stmt->close();
    }
}

$selectedShipping = $customerId !== '' ? fetchShippingRow($conn, $customerId) : null;
$selectedMember = null;

if ($customerId !== '' && $memberTable && $idColumn) {
    foreach ($members as $member) {
        if ((string)($member['customer_id'] ?? '') === $customerId) {
            $selectedMember = $member;
            break;
        }
    }

    if (!$selectedMember) {
        $stmt = $conn->prepare("SELECT `{$idColumn}` AS customer_id" . ($nameColumn ? ", `{$nameColumn}` AS full_name" : ", '' AS full_name") . ($mobileColumn ? ", `{$mobileColumn}` AS mobile" : ", '' AS mobile") . ($emailColumn ? ", `{$emailColumn}` AS email" : ", '' AS email") . " FROM `{$memberTable}` WHERE `{$idColumn}` = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param('s', $customerId);
            $stmt->execute();
            $result = $stmt->get_result();
            $selectedMember = $result instanceof mysqli_result ? $result->fetch_assoc() : null;
            $stmt->close();
        }
    }
}

$shippingFields = [
    'name' => 'Name',
    'house_number' => 'House Number',
    'street_name' => 'Street Name',
    'landmark' => 'Landmark',
    'state_name' => 'State',
    'district_name' => 'District',
    'city_name' => 'City',
    'pincode' => 'Pincode',
    'email' => 'Email',
    'mobile_no' => 'Mobile No.',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shipping Details</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body class="m-0 bg-[#f3f4f6] font-sans overflow-x-hidden">

    <?php include __DIR__ . '/includes/sidebar.php'; ?>

    <div class="ml-64 min-h-screen w-[calc(100%-16rem)]">
        <?php include __DIR__ . '/includes/navbar.php'; ?>

        <main class="px-5 py-4">
            <div class="flex items-center gap-4 mb-4">
                <div class="w-9 h-9 rounded-[8px] bg-[#f6f2ea] flex items-center justify-center shrink-0">
                    <i data-lucide="truck" class="w-4 h-4 text-[#c9983b]"></i>
                </div>
                <h2 class="text-[17px] md:text-[18px] leading-none text-[#2f2f2f] font-normal whitespace-nowrap" style="font-family: Georgia, 'Times New Roman', serif;">
                    SHIPPING DETAILS
                </h2>
                <div class="h-[1px] bg-[#c9983b] flex-1"></div>
            </div>

            <div class="bg-white border border-[#dfdfdf] rounded-[12px] px-4 py-4">
                <?php if ($focusMode && $selectedMember): ?>
                    <div class="mb-6 rounded-[12px] border border-[#d6a246] bg-[#fffaf0] px-4 py-4">
                        <div class="mb-4 flex items-center justify-between gap-3">
                            <div class="flex items-center gap-3">
                                <i data-lucide="truck" class="w-5 h-5 text-[#c9983b]"></i>
                                <div>
                                    <h3 class="text-[16px] font-semibold text-[#2f2f2f]">Shipping Report</h3>
                                    <p class="text-[12px] text-[#8f7a4f]">Customer ID: <?php echo e($selectedMember['customer_id'] ?? ''); ?></p>
                                </div>
                            </div>

                            <a href="<?php echo e('shipping-details.php'); ?>" class="inline-flex items-center rounded bg-[#143a66] px-3 py-2 text-[12px] text-white">View All</a>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-[14px]">
                            <div><span class="font-semibold text-[#555]">Name:</span> <?php echo e($selectedMember['full_name'] ?? ''); ?></div>
                            <div><span class="font-semibold text-[#555]">Mobile:</span> <?php echo e($selectedMember['mobile'] ?? '-'); ?></div>
                            <div><span class="font-semibold text-[#555]">Email:</span> <?php echo e($selectedMember['email'] ?? '-'); ?></div>
                            <div><span class="font-semibold text-[#555]">Shipping Status:</span> <?php echo $selectedShipping ? '<span class="text-green-700 font-semibold">Available</span>' : '<span class="text-red-700 font-semibold">Not Found</span>'; ?></div>
                        </div>

                        <div class="mt-4 grid grid-cols-1 md:grid-cols-2 gap-4 text-[14px]">
                            <?php foreach ($shippingFields as $field => $label): ?>
                                <div class="rounded-lg border border-[#ead9b5] bg-white px-3 py-2">
                                    <div class="text-[12px] uppercase tracking-wide text-[#8f7a4f] font-semibold"><?php echo e($label); ?></div>
                                    <div class="mt-1 text-[#2f2f2f] font-medium">
                                        <?php echo e($selectedShipping[$field] ?? '-'); ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <form method="GET" class="mb-4 flex flex-wrap items-center gap-3">
                    <input type="text" name="search" value="<?php echo e($search); ?>" placeholder="Search customer id" class="h-10 w-full max-w-sm rounded-md border border-[#d1d5db] px-3 outline-none">
                    <button type="submit" class="h-10 rounded-md bg-[#143a66] px-4 text-white font-medium">Search</button>
                </form>

                <div class="overflow-x-auto rounded-[8px] border border-[#d6a246]">
                    <table class="w-full min-w-[980px] border-collapse">
                        <thead>
                            <tr class="bg-[#cd9737] text-white text-[15px] h-[34px]">
                                <th class="px-3 text-left">Customer ID</th>
                                <th class="px-3 text-left">Name</th>
                                <th class="px-3 text-left">Mobile</th>
                                <th class="px-3 text-left">Email</th>
                                <th class="px-3 text-left">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($members)): ?>
                                <?php foreach ($members as $member): ?>
                                    <?php $cid = (string)($member['customer_id'] ?? ''); ?>
                                    <tr class="border-t border-[#ececec] text-[15px] text-[#2d2d2d] h-[34px]">
                                        <td class="px-3 whitespace-nowrap"><?php echo e($cid); ?></td>
                                        <td class="px-3 whitespace-nowrap"><?php echo e($member['full_name'] ?? ''); ?></td>
                                        <td class="px-3 whitespace-nowrap"><?php echo e($member['mobile'] ?? ''); ?></td>
                                        <td class="px-3 whitespace-nowrap"><?php echo e($member['email'] ?? ''); ?></td>
                                        <td class="px-3 whitespace-nowrap">
                                            <?php if ($cid !== ''): ?>
                                                <a href="<?php echo e('shipping-details.php?customer_id=' . rawurlencode($cid)); ?>" target="_blank" rel="noopener noreferrer" class="inline-flex items-center rounded bg-[#143a66] px-3 py-1 text-[12px] text-white">View</a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="px-3 py-10 text-center text-[#6b7280]">No members found.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($customerId !== '' && !$selectedMember): ?>
                    <div class="mt-6 rounded-[12px] border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                        Shipping details not found for this member.
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script>
        if (typeof lucide !== 'undefined') {
            lucide.createIcons();
        }
    </script>
</body>
</html>
