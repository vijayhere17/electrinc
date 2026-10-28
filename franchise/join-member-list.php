<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../admin/config/db.php';

if (!isset($conn) || !($conn instanceof mysqli)) {
    die("Database connection failed.");
}

function e($v) {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function tableExists($conn, $table) {
    $table = $conn->real_escape_string($table);
    $res = $conn->query("SHOW TABLES LIKE '$table'");
    return ($res && $res->num_rows > 0);
}

function colExists($conn, $table, $col) {
    $table = $conn->real_escape_string($table);
    $col = $conn->real_escape_string($col);
    $res = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$col'");
    return ($res && $res->num_rows > 0);
}

function value($row, $cols) {
    foreach ($cols as $c) {
        if (isset($row[$c]) && trim((string)$row[$c]) !== '') {
            return $row[$c];
        }
    }
    return '';
}

function findLoginRow($conn, $table, $idCols, $sessionValues) {
    if (!tableExists($conn, $table)) {
        return null;
    }

    foreach ($sessionValues as $val) {
        $val = trim((string)$val);
        if ($val === '') continue;

        if (is_numeric($val) && colExists($conn, $table, 'id')) {
            $stmt = $conn->prepare("SELECT * FROM `$table` WHERE id = ? LIMIT 1");
            if ($stmt) {
                $id = (int)$val;
                $stmt->bind_param("i", $id);
                $stmt->execute();
                $row = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                if ($row) return $row;
            }
        }

        foreach ($idCols as $col) {
            if (!colExists($conn, $table, $col)) continue;

            $stmt = $conn->prepare("SELECT * FROM `$table` WHERE `$col` = ? LIMIT 1");
            if ($stmt) {
                $stmt->bind_param("s", $val);
                $stmt->execute();
                $row = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                if ($row) return $row;
            }
        }
    }

    return null;
}

function fetchByState($conn, $table, $state) {
    $rows = [];

    if (!tableExists($conn, $table) || !colExists($conn, $table, 'state')) {
        return $rows;
    }

    if ($state === '') {
        $res = $conn->query("SELECT * FROM `$table` ORDER BY id DESC");
        while ($res && $row = $res->fetch_assoc()) {
            $rows[] = $row;
        }
        return $rows;
    }

    $stmt = $conn->prepare("
        SELECT * FROM `$table`
        WHERE LOWER(TRIM(state)) = LOWER(TRIM(?))
        ORDER BY id DESC
    ");

    if ($stmt) {
        $stmt->bind_param("s", $state);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $rows[] = $row;
        }
        $stmt->close();
    }

    return $rows;
}

function fetchByCityState($conn, $table, $city, $state) {
    $rows = [];

    if (!tableExists($conn, $table)) {
        return $rows;
    }

    if ($city === '') {
        return fetchByState($conn, $table, $state);
    }

    if (!colExists($conn, $table, 'city')) {
        return $rows;
    }

    $sql = "
        SELECT * FROM `$table`
        WHERE LOWER(TRIM(city)) = LOWER(TRIM(?))
    ";

    if ($state !== '' && colExists($conn, $table, 'state')) {
        $sql .= " AND LOWER(TRIM(state)) = LOWER(TRIM(?))";
        $stmt = $conn->prepare($sql . " ORDER BY id DESC");
        if ($stmt) {
            $stmt->bind_param("ss", $city, $state);
        }
    } else {
        $stmt = $conn->prepare($sql . " ORDER BY id DESC");
        if ($stmt) {
            $stmt->bind_param("s", $city);
        }
    }

    if ($stmt) {
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $rows[] = $row;
        }
        $stmt->close();
    }

    return $rows;
}

function fetchMobileByFranchise($conn, $stateCode, $cityCode = '')
{
    $rows = [];

    $sql = "
        SELECT *
        FROM mobile_franchies_holder
        WHERE state_franchise_code = ?
    ";

    if ($cityCode !== '') {
        $sql .= "
            AND city_franchise_code = ?
        ";
    }

    $sql .= " ORDER BY id DESC";

    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        return $rows;
    }

    if ($cityCode !== '') {
        $stmt->bind_param("ss", $stateCode, $cityCode);
    } else {
        $stmt->bind_param("s", $stateCode);
    }

    $stmt->execute();

    $res = $stmt->get_result();

    while ($row = $res->fetch_assoc()) {
        $rows[] = $row;
    }

    $stmt->close();

    return $rows;
}

$sessionValues = [
    $_SESSION['franchise_id'] ?? '',
    $_SESSION['franchies_id'] ?? '',
    $_SESSION['state_franchies_holder_id'] ?? '',
    $_SESSION['city_franchies_holder_id'] ?? '',
    $_SESSION['mobile_franchies_holder_id'] ?? '',
    $_SESSION['franchise_db_id'] ?? '',
    $_SESSION['id'] ?? ''
];

$loginId = value($_SESSION, ['franchies_id', 'franchise_id']);
$typeRaw = strtolower(trim($_SESSION['franchise_type'] ?? ''));

$franchiseType = '';
$selfState = '';
$selfCity = '';
$selfStateCode = '';
$selfCityCode = '';

$stateRow = findLoginRow($conn, 'franchies_holder', ['franchies_id'], $sessionValues);
$cityRow = findLoginRow($conn, 'city_franchies_holder', ['franchies_id'], $sessionValues);
$mobileRow = findLoginRow($conn, 'mobile_franchies_holder', ['mobile_franchies_id'], $sessionValues);

if (strpos($typeRaw, 'state') !== false || $stateRow) {
    $franchiseType = 'state';
    $selfState = trim((string)value($stateRow ?? [], ['state']));
    $selfStateCode = trim((string)value($stateRow ?? [], ['franchise_code']));
} elseif (strpos($typeRaw, 'city') !== false || $cityRow) {
    $franchiseType = 'city';
    $selfState = trim((string)value($cityRow ?? [], ['state']));
    $selfCity = trim((string)value($cityRow ?? [], ['city']));
    $selfStateCode = trim((string)value($cityRow ?? [], ['franchise_code']));
    $selfCityCode = trim((string)value($cityRow ?? [], ['franchies_id']));
} elseif (strpos($typeRaw, 'mobile') !== false || $mobileRow) {
    $franchiseType = 'mobile';
    $selfState = trim((string)value($mobileRow ?? [], ['state']));
    $selfCity = trim((string)value($mobileRow ?? [], ['city']));
}

if ($franchiseType === '') {
    $franchiseType = 'state';
}

$cityFranchies = [];
$mobileFranchies = [];

if ($franchiseType === 'state') {
    $cityFranchies = fetchByState(
        $conn,
        'city_franchies_holder',
        $selfState
    );

    $mobileFranchies = fetchMobileByFranchise(
        $conn,
        $selfStateCode
    );
}

if ($franchiseType === 'city') {
    $mobileFranchies = fetchMobileByFranchise(
        $conn,
        $selfStateCode,
        $selfCityCode
    );
}

if ($franchiseType === 'mobile' && $mobileRow) {
    $mobileFranchies[] = $mobileRow;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Join Member List</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
</head>

<body class="bg-[#f3f4f6] font-sans overflow-x-hidden">

<?php include __DIR__ . '/includes/sidebar.php'; ?>

<div class="ml-64 min-h-screen">
<?php include __DIR__ . '/includes/navbar.php'; ?>

<main class="px-5 py-5">
<div class="max-w-[1600px] mx-auto">

    <div class="mt-5 flex items-center gap-3 mb-6">
        <div class="w-[36px] h-[36px] rounded-[8px] bg-[#f5eee3] flex items-center justify-center shrink-0">
            <i data-lucide="users" class="w-[18px] h-[18px] text-[#cd9737]"></i>
        </div>
        <h2 class="whitespace-nowrap text-[20px] font-normal text-[#40352a]" style="font-family: Georgia, 'Times New Roman', serif;">
            JOIN MEMBER LIST
        </h2>
        <div class="w-full h-[2px] bg-[#d1a14b]"></div>
    </div>

    <?php if ($franchiseType === 'state'): ?>
    <div class="bg-white rounded-[16px] shadow-sm border border-gray-100 p-6 mb-10">
        <h3 class="text-[20px] font-semibold text-[#40352a] mb-5">City Franchies List</h3>

        <div class="overflow-x-auto">
            <table class="w-full min-w-[1200px] border border-gray-200">
                <thead class="bg-[#C89741] text-white text-[13px] font-semibold">
                    <tr>
                        <th class="border px-4 py-3 text-left">#</th>
                        <th class="border px-4 py-3 text-left">Franchies ID</th>
                        <th class="border px-4 py-3 text-left">Full Name</th>
                        <th class="border px-4 py-3 text-left">Mobile</th>
                        <th class="border px-4 py-3 text-left">Email</th>
                        <th class="border px-4 py-3 text-left">City</th>
                        <th class="border px-4 py-3 text-left">State</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!empty($cityFranchies)): ?>
                    <?php foreach ($cityFranchies as $i => $row): ?>
                    <tr>
                        <td class="border px-4 py-3"><?php echo $i + 1; ?></td>
                        <td class="border px-4 py-3 font-bold text-blue-600"><?php echo e(value($row, ['franchies_id'])); ?></td>
                        <td class="border px-4 py-3"><?php echo e(value($row, ['full_name', 'name', 'owner_name'])); ?></td>
                        <td class="border px-4 py-3"><?php echo e(value($row, ['mobile'])); ?></td>
                        <td class="border px-4 py-3"><?php echo e(value($row, ['email'])); ?></td>
                        <td class="border px-4 py-3"><?php echo e(value($row, ['city'])); ?></td>
                        <td class="border px-4 py-3"><?php echo e(value($row, ['state'])); ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" class="border px-4 py-5 text-center text-gray-500">No city franchies found.</td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <div class="bg-white rounded-[16px] shadow-sm border border-gray-100 p-6 mb-10">
        <h3 class="text-[20px] font-semibold text-[#40352a] mb-5">Mobile Franchies List</h3>

        <div class="overflow-x-auto">
            <table class="w-full min-w-[1400px] border border-gray-200">
                <thead class="bg-[#C89741] text-white text-[13px] font-semibold">
                    <tr>
                        <th class="border px-4 py-3 text-left">#</th>
                        <th class="border px-4 py-3 text-left">Mobile Franchies ID</th>
                        <!-- <th class="border px-4 py-3 text-left">Shop Name</th> -->
                        <th class="border px-4 py-3 text-left">Owner Name</th>
                        <th class="border px-4 py-3 text-left">Mobile</th>
                        <th class="border px-4 py-3 text-left">Email</th>
                        <th class="border px-4 py-3 text-left">City</th>
                        <th class="border px-4 py-3 text-left">State</th>
                        <th class="border px-4 py-3 text-left">GST</th>
                        <th class="border px-4 py-3 text-left">Status</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!empty($mobileFranchies)): ?>
                    <?php foreach ($mobileFranchies as $i => $row): ?>
                    <tr>
                        <td class="border px-4 py-3"><?php echo $i + 1; ?></td>
                        <td class="border px-4 py-3 font-bold text-blue-600"><?php echo e(value($row, ['mobile_franchies_id'])); ?></td>
                        <!-- <td class="border px-4 py-3"><?php echo e(value($row, ['shop_name'])); ?></td> -->
                        <td class="border px-4 py-3"><?php echo e(value($row, ['owner_name'])); ?></td>
                        <td class="border px-4 py-3"><?php echo e(value($row, ['mobile'])); ?></td>
                        <td class="border px-4 py-3"><?php echo e(value($row, ['email'])); ?></td>
                        <td class="border px-4 py-3"><?php echo e(value($row, ['city'])); ?></td>
                        <td class="border px-4 py-3"><?php echo e(value($row, ['state'])); ?></td>
                        <td class="border px-4 py-3"><?php echo e(value($row, ['gst_no'])); ?></td>
                        <td class="border px-4 py-3"><?php echo e(value($row, ['status'])); ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="10" class="border px-4 py-5 text-center text-gray-500">No mobile franchies found.</td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>
</main>
</div>

<script>
document.addEventListener("DOMContentLoaded", function () {
    if (typeof lucide !== "undefined") lucide.createIcons();
});
</script>

</body>
</html>