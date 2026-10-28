<?php
session_start();

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/config/db.php';

/** @var mysqli $conn */
if (!isset($conn) || !($conn instanceof mysqli)) {
    die('Database connection failed. Please check config/db.php');
}

function e($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function columnExists(mysqli $conn, string $table, string $column): bool {
    $stmt = $conn->prepare("
        SELECT COUNT(*) AS total
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = ?
        AND COLUMN_NAME = ?
    ");
    $stmt->bind_param("ss", $table, $column);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return (int)$row['total'] > 0;
}

function addColumnIfMissing(mysqli $conn, string $table, string $column, string $definition): void {
    if (!columnExists($conn, $table, $column)) {
        $conn->query("ALTER TABLE `$table` ADD COLUMN `$column` $definition");
    }
}

$conn->query("CREATE TABLE IF NOT EXISTS mobile_franchies_holder (id INT AUTO_INCREMENT PRIMARY KEY)");

addColumnIfMissing($conn, 'mobile_franchies_holder', 'mobile_franchies_id', "VARCHAR(20) DEFAULT NULL");
addColumnIfMissing($conn, 'mobile_franchies_holder', 'owner_name', "VARCHAR(150) DEFAULT NULL");
addColumnIfMissing($conn, 'mobile_franchies_holder', 'email', "VARCHAR(150) DEFAULT NULL");
addColumnIfMissing($conn, 'mobile_franchies_holder', 'mobile', "VARCHAR(20) DEFAULT NULL");
addColumnIfMissing($conn, 'mobile_franchies_holder', 'date_of_birth', "DATE DEFAULT NULL");
addColumnIfMissing($conn, 'mobile_franchies_holder', 'gst_no', "VARCHAR(50) DEFAULT NULL");
addColumnIfMissing($conn, 'mobile_franchies_holder', 'address_1', "VARCHAR(255) DEFAULT NULL");
addColumnIfMissing($conn, 'mobile_franchies_holder', 'address_2', "VARCHAR(255) DEFAULT NULL");
addColumnIfMissing($conn, 'mobile_franchies_holder', 'city', "VARCHAR(100) DEFAULT NULL");
addColumnIfMissing($conn, 'mobile_franchies_holder', 'state', "VARCHAR(100) DEFAULT NULL");
addColumnIfMissing($conn, 'mobile_franchies_holder', 'city_franchise_code', "VARCHAR(50) DEFAULT NULL");
addColumnIfMissing($conn, 'mobile_franchies_holder', 'state_franchise_code', "VARCHAR(50) DEFAULT NULL");
addColumnIfMissing($conn, 'mobile_franchies_holder', 'country', "VARCHAR(100) DEFAULT 'India'");
addColumnIfMissing($conn, 'mobile_franchies_holder', 'postal_code', "VARCHAR(20) DEFAULT NULL");
addColumnIfMissing($conn, 'mobile_franchies_holder', 'details', "TEXT DEFAULT NULL");
addColumnIfMissing($conn, 'mobile_franchies_holder', 'mobile_franchies_password', "VARCHAR(255) DEFAULT NULL");
addColumnIfMissing($conn, 'mobile_franchies_holder', 'plain_password', "VARCHAR(50) DEFAULT NULL");
addColumnIfMissing($conn, 'mobile_franchies_holder', 'status', "VARCHAR(20) DEFAULT 'active'");
addColumnIfMissing($conn, 'mobile_franchies_holder', 'created_date', "TIMESTAMP DEFAULT CURRENT_TIMESTAMP");

function generateMobileFranchiesId($conn) {
    do {
        $id = 'FRM' . rand(10000, 99999);
        $stmt = $conn->prepare("SELECT id FROM mobile_franchies_holder WHERE mobile_franchies_id = ? LIMIT 1");
        $stmt->bind_param("s", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $exists = $result && $result->num_rows > 0;
        $stmt->close();
    } while ($exists);
    return $id;
}

function generatePassword() {
    return (string)rand(100000, 999999);
}

/* AJAX REGISTER */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_register'])) {
    header('Content-Type: application/json');

    $owner_name    = trim($_POST['owner_name'] ?? '');
    $mobile        = trim($_POST['mobile'] ?? '');
    $email         = trim($_POST['email'] ?? '');
    $date_of_birth = trim($_POST['date_of_birth'] ?? '');
    $gst_no        = trim($_POST['gst_no'] ?? '');
    $address_1     = trim($_POST['address_1'] ?? '');
    $address_2     = trim($_POST['address_2'] ?? '');
    $city_franchise_code = trim($_POST['city'] ?? '');
    $state_franchise_code = trim($_POST['state'] ?? '');
    if ($city_franchise_code !== '') {
    $city = $city_franchise_code;
} else {
    $city = null;
}
    $state = $state_franchise_code;
    $country       = trim($_POST['country'] ?? 'India');
    $postal_code   = trim($_POST['postal_code'] ?? '');
    $details       = trim($_POST['details'] ?? '');

    if ($owner_name === '' || $mobile === '' || $state === '') {
    echo json_encode([
        'status' => 'error',
        'message' => 'Please fill Owner Name, Mobile Number and State Franchise ID.'
    ]);
    exit;
}

    $mobile_franchies_id = generateMobileFranchiesId($conn);
    $plain_password      = generatePassword();
    $hashed_password     = password_hash($plain_password, PASSWORD_DEFAULT);
    $dob_value           = $date_of_birth !== '' ? $date_of_birth : null;

    $stmt = $conn->prepare("
        INSERT INTO mobile_franchies_holder
        (mobile_franchies_id, owner_name, email, mobile, date_of_birth, gst_no,
         address_1, address_2, city, state, city_franchise_code, state_franchise_code,
         country, postal_code, details, mobile_franchies_password, plain_password, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')
    ");

    if (!$stmt) {
        echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $conn->error]);
        exit;
    }

    $stmt->bind_param(
        "sssssssssssssssss",
        $mobile_franchies_id, $owner_name, $email, $mobile, $dob_value, $gst_no,
        $address_1, $address_2, $city, $state, $city_franchise_code, $state_franchise_code,
        $country, $postal_code, $details, $hashed_password, $plain_password
    );

    if ($stmt->execute()) {
        $new_id = $stmt->insert_id;
        echo json_encode([
            'status'  => 'success',
            'message' => "Mobile Franchies Registered Successfully. ID: $mobile_franchies_id | Password: $plain_password",
            'data'    => [
                'id'                  => $new_id,
                'mobile_franchies_id' => $mobile_franchies_id,
                'plain_password'      => $plain_password,
                'owner_name'          => $owner_name,
                'mobile'              => $mobile,
                'email'               => $email,
                'date_of_birth'       => $dob_value,
                'gst_no'              => $gst_no,
                'city'                => $city,
                'state'               => $state,
                'status'              => 'active',
                'created_date'        => date('d-m-Y')
            ]
        ]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Insert failed: ' . $stmt->error]);
    }

    $stmt->close();
    exit;
}

$franchies_list = [];
$result = $conn->query("SELECT * FROM mobile_franchies_holder ORDER BY id DESC");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $franchies_list[] = $row;
    }
}

$state_franchies = [];
$stateResult = $conn->query("SELECT franchise_code, state, name FROM franchies_holder ORDER BY id DESC");
if ($stateResult) {
    while ($row = $stateResult->fetch_assoc()) {
        $state_franchies[] = $row;
    }
}

$city_franchies = [];
$cityResult = $conn->query("SELECT * FROM city_franchies_holder ORDER BY id DESC");
if ($cityResult) {
    while ($row = $cityResult->fetch_assoc()) {
        $city_franchies[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mobile Franchies Registration</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        .password-text {
            font-family: 'Courier New', monospace;
            font-weight: 700;
            letter-spacing: 0.05em;
        }
        .copy-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 22px;
            height: 22px;
            margin-left: 6px;
            border-radius: 4px;
            background: #f5eee3;
            border: 1px solid #d1a14b;
            cursor: pointer;
            vertical-align: middle;
            transition: background 0.15s;
        }
        .copy-btn:hover { background: #d1a14b; }
        .copy-btn:hover svg { color: #fff; }
        .copy-btn svg { width: 12px; height: 12px; color: #c9932f; pointer-events: none; }
    </style>
</head>

<body class="bg-[#f3f4f6] font-sans overflow-x-hidden">

<?php
$sidebarPath = __DIR__ . '/includes/sidebar.php';
$navbarPath  = __DIR__ . '/includes/navbar.php';
if (file_exists($sidebarPath)) { include $sidebarPath; }
?>

<div class="lg:ml-64 min-h-screen">

<?php if (file_exists($navbarPath)) { include $navbarPath; } ?>

<main class="px-3 sm:px-5 py-4 sm:py-5">
<div class="max-w-[1500px] mx-auto">

    <!-- Page Header -->
    <div class="mt-4 sm:mt-5 flex flex-wrap items-center gap-3 mb-5 sm:mb-6">
        <div class="w-[36px] h-[36px] rounded-[8px] bg-[#f5eee3] flex items-center justify-center shrink-0">
            <i data-lucide="smartphone" class="w-[18px] h-[18px] text-[#cd9737]"></i>
        </div>
        <h2 class="whitespace-nowrap text-[16px] sm:text-[20px] font-normal text-[#40352a]"
            style="font-family: Georgia, 'Times New Roman', serif;">
            MOBILE FRANCHIES REGISTRATION
        </h2>
        <div class="hidden sm:block flex-1 h-[2px] bg-[#d1a14b]"></div>
    </div>

    <!-- Message Box -->
    <div id="messageBox" class="hidden mb-4 sm:mb-6 rounded-lg px-4 sm:px-5 py-3 sm:py-4 font-semibold text-sm sm:text-base"></div>

    <!-- Registration Form -->
    <form id="mobileFranchiesForm" class="bg-white rounded-[16px] shadow-sm border border-gray-100 p-4 sm:p-6 md:p-10 mb-6 sm:mb-10">

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 sm:gap-6">

            <div>
                <label class="block text-[14px] sm:text-[16px] font-semibold text-gray-700 mb-1.5 sm:mb-2">Owner Name *</label>
                <input type="text" name="owner_name" required
                    class="w-full h-[46px] sm:h-[52px] px-4 sm:px-5 border border-gray-300 rounded-[8px] text-[14px] sm:text-[15px] focus:outline-none focus:border-[#c9932f] focus:ring-1 focus:ring-[#c9932f]"
                    placeholder="Enter owner name">
            </div>

            <div>
                <label class="block text-[14px] sm:text-[16px] font-semibold text-gray-700 mb-1.5 sm:mb-2">Mobile Number *</label>
                <input type="text" name="mobile" required
                    class="w-full h-[46px] sm:h-[52px] px-4 sm:px-5 border border-gray-300 rounded-[8px] text-[14px] sm:text-[15px] focus:outline-none focus:border-[#c9932f] focus:ring-1 focus:ring-[#c9932f]"
                    placeholder="Enter mobile number">
            </div>

            <div>
                <label class="block text-[14px] sm:text-[16px] font-semibold text-gray-700 mb-1.5 sm:mb-2">Email</label>
                <input type="email" name="email"
                    class="w-full h-[46px] sm:h-[52px] px-4 sm:px-5 border border-gray-300 rounded-[8px] text-[14px] sm:text-[15px] focus:outline-none focus:border-[#c9932f] focus:ring-1 focus:ring-[#c9932f]"
                    placeholder="Enter email">
            </div>

            <div>
                <label class="block text-[14px] sm:text-[16px] font-semibold text-gray-700 mb-1.5 sm:mb-2">Date Of Birth</label>
                <input type="date" name="date_of_birth"
                    class="w-full h-[46px] sm:h-[52px] px-4 sm:px-5 border border-gray-300 rounded-[8px] text-[14px] sm:text-[15px] focus:outline-none focus:border-[#c9932f] focus:ring-1 focus:ring-[#c9932f]">
            </div>

            <div>
                <label class="block text-[14px] sm:text-[16px] font-semibold text-gray-700 mb-1.5 sm:mb-2">GST Number</label>
                <input type="text" name="gst_no"
                    class="w-full h-[46px] sm:h-[52px] px-4 sm:px-5 border border-gray-300 rounded-[8px] text-[14px] sm:text-[15px] focus:outline-none focus:border-[#c9932f] focus:ring-1 focus:ring-[#c9932f]"
                    placeholder="Enter GST number">
            </div>

            <div>
                <label class="block text-[14px] sm:text-[16px] font-semibold text-gray-700 mb-1.5 sm:mb-2">Address Line 1</label>
                <input type="text" name="address_1"
                    class="w-full h-[46px] sm:h-[52px] px-4 sm:px-5 border border-gray-300 rounded-[8px] text-[14px] sm:text-[15px] focus:outline-none focus:border-[#c9932f] focus:ring-1 focus:ring-[#c9932f]"
                    placeholder="Address line 1">
            </div>

            <div>
                <label class="block text-[14px] sm:text-[16px] font-semibold text-gray-700 mb-1.5 sm:mb-2">Address Line 2</label>
                <input type="text" name="address_2"
                    class="w-full h-[46px] sm:h-[52px] px-4 sm:px-5 border border-gray-300 rounded-[8px] text-[14px] sm:text-[15px] focus:outline-none focus:border-[#c9932f] focus:ring-1 focus:ring-[#c9932f]"
                    placeholder="Address line 2">
            </div>

            <div>
                <label class="block text-[14px] sm:text-[16px] font-semibold text-gray-700 mb-1.5 sm:mb-2">State Franchies ID *</label>
                <select name="state" id="stateSelect" required
                    class="w-full h-[46px] sm:h-[52px] px-4 sm:px-5 border border-gray-300 rounded-[8px] bg-white text-[14px] sm:text-[15px] focus:outline-none focus:border-[#c9932f] focus:ring-1 focus:ring-[#c9932f]">
                    <option value="">Select State Franchies</option>
                    <?php foreach ($state_franchies as $sf): ?>
                        <option value="<?= e($sf['franchise_code']) ?>"><?= e($sf['franchise_code'] . ' - ' . ($sf['state'] ?: $sf['name'])) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="block text-[14px] sm:text-[16px] font-semibold text-gray-700 mb-1.5 sm:mb-2">City Franchies ID (Optional)</label>
                <select name="city" id="citySelect"
                    class="w-full h-[46px] sm:h-[52px] px-4 sm:px-5 border border-gray-300 rounded-[8px] bg-white text-[14px] sm:text-[15px] focus:outline-none focus:border-[#c9932f] focus:ring-1 focus:ring-[#c9932f]">
                    <option value="">Select City Franchies</option>
                    <?php foreach ($city_franchies as $cf): ?>
                        <option value="<?= e($cf['franchies_id']) ?>" data-state="<?= e($cf['franchise_code']) ?>"><?= e($cf['franchies_id'] . ' - ' . $cf['city']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="block text-[14px] sm:text-[16px] font-semibold text-gray-700 mb-1.5 sm:mb-2">Country</label>
                <input type="text" name="country" value="India"
                    class="w-full h-[46px] sm:h-[52px] px-4 sm:px-5 border border-gray-300 rounded-[8px] text-[14px] sm:text-[15px] focus:outline-none focus:border-[#c9932f] focus:ring-1 focus:ring-[#c9932f]">
            </div>

            <div>
                <label class="block text-[14px] sm:text-[16px] font-semibold text-gray-700 mb-1.5 sm:mb-2">Postal Code</label>
                <input type="text" name="postal_code"
                    class="w-full h-[46px] sm:h-[52px] px-4 sm:px-5 border border-gray-300 rounded-[8px] text-[14px] sm:text-[15px] focus:outline-none focus:border-[#c9932f] focus:ring-1 focus:ring-[#c9932f]"
                    placeholder="Postal code">
            </div>

            <div class="sm:col-span-2">
                <label class="block text-[14px] sm:text-[16px] font-semibold text-gray-700 mb-1.5 sm:mb-2">Details</label>
                <textarea name="details" rows="4"
                    class="w-full px-4 sm:px-5 py-3 border border-gray-300 rounded-[8px] text-[14px] sm:text-[15px] focus:outline-none focus:border-[#c9932f] focus:ring-1 focus:ring-[#c9932f]"
                    placeholder="Additional details"></textarea>
            </div>

            <div class="sm:col-span-2">
                <div class="bg-yellow-50 border border-yellow-200 rounded-lg px-4 sm:px-5 py-3 sm:py-4 text-yellow-800 text-[13px] sm:text-[14px] flex items-start sm:items-center gap-2 sm:gap-3">
                    <i data-lucide="info" class="w-[16px] h-[16px] sm:w-[18px] sm:h-[18px] shrink-0 mt-0.5 sm:mt-0"></i>
                    Mobile Franchies ID and Password will be generated automatically after submit.
                </div>
            </div>

            <div class="sm:col-span-2 flex justify-center pt-2 sm:pt-4">
                <button id="submitBtn" type="submit"
                    class="w-full sm:w-auto bg-[#c9932f] hover:bg-[#b88426] text-white px-8 sm:px-16 py-3 rounded-[8px] text-[15px] sm:text-[17px] font-semibold transition-colors duration-150">
                    Register Mobile Franchies
                </button>
            </div>

        </div>
    </form>

    <!-- Franchies List Table -->
    <div class="bg-white rounded-[16px] shadow-sm border border-gray-100 p-4 sm:p-6 mb-8 sm:mb-10">

        <div class="flex flex-wrap items-center gap-3 mb-4 sm:mb-5">
            <div class="w-[32px] h-[32px] rounded-[8px] bg-[#f5eee3] flex items-center justify-center shrink-0">
                <i data-lucide="list" class="w-[16px] h-[16px] text-[#cd9737]"></i>
            </div>
            <h3 class="text-[16px] sm:text-[20px] font-semibold text-[#40352a]">Mobile Franchies List</h3>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full min-w-[1200px] border border-gray-200 text-[12px] sm:text-[14px]">
                <thead class="bg-[#f5eee3]">
                    <tr>
                        <th class="border px-3 sm:px-4 py-2 sm:py-3 text-left text-[12px] sm:text-[13px] font-semibold text-[#40352a]">#</th>
                        <th class="border px-3 sm:px-4 py-2 sm:py-3 text-left text-[12px] sm:text-[13px] font-semibold text-[#40352a]">Franchies ID</th>
                        <th class="border px-3 sm:px-4 py-2 sm:py-3 text-left text-[12px] sm:text-[13px] font-semibold text-[#40352a]">Owner Name</th>
                        <th class="border px-3 sm:px-4 py-2 sm:py-3 text-left text-[12px] sm:text-[13px] font-semibold text-[#40352a]">Mobile</th>
                        <th class="border px-3 sm:px-4 py-2 sm:py-3 text-left text-[12px] sm:text-[13px] font-semibold text-[#40352a]">Email</th>
                        <th class="border px-3 sm:px-4 py-2 sm:py-3 text-left text-[12px] sm:text-[13px] font-semibold text-[#40352a]">DOB</th>
                        <th class="border px-3 sm:px-4 py-2 sm:py-3 text-left text-[12px] sm:text-[13px] font-semibold text-[#40352a]">GST</th>
                        <th class="border px-3 sm:px-4 py-2 sm:py-3 text-left text-[12px] sm:text-[13px] font-semibold text-[#40352a]">City ID</th>
                        <th class="border px-3 sm:px-4 py-2 sm:py-3 text-left text-[12px] sm:text-[13px] font-semibold text-[#40352a]">State ID</th>
                        <th class="border px-3 sm:px-4 py-2 sm:py-3 text-left text-[12px] sm:text-[13px] font-semibold text-[#40352a] bg-orange-50">
                            <span class="flex items-center gap-1">
                                <i data-lucide="key-round" class="w-[12px] h-[12px] sm:w-[13px] sm:h-[13px] text-orange-500"></i>
                                Admin Password
                            </span>
                        </th>
                        <th class="border px-3 sm:px-4 py-2 sm:py-3 text-left text-[12px] sm:text-[13px] font-semibold text-[#40352a]">Status</th>
                        <th class="border px-3 sm:px-4 py-2 sm:py-3 text-left text-[12px] sm:text-[13px] font-semibold text-[#40352a]">Created Date</th>
                    </tr>
                </thead>

                <tbody id="franchiesTableBody">
                <?php if (!empty($franchies_list)): ?>
                    <?php foreach ($franchies_list as $index => $row): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="border px-3 sm:px-4 py-2 sm:py-3 text-gray-500"><?= $index + 1 ?></td>
                            <td class="border px-3 sm:px-4 py-2 sm:py-3 font-bold text-blue-600 whitespace-nowrap"><?= e($row['mobile_franchies_id'] ?? '') ?></td>
                            <td class="border px-3 sm:px-4 py-2 sm:py-3"><?= e($row['owner_name'] ?? '') ?></td>
                            <td class="border px-3 sm:px-4 py-2 sm:py-3 whitespace-nowrap"><?= e($row['mobile'] ?? '') ?></td>
                            <td class="border px-3 sm:px-4 py-2 sm:py-3"><?= e($row['email'] ?? '') ?></td>
                            <td class="border px-3 sm:px-4 py-2 sm:py-3 whitespace-nowrap"><?= e($row['date_of_birth'] ?? '') ?></td>
                            <td class="border px-3 sm:px-4 py-2 sm:py-3"><?= e($row['gst_no'] ?? '') ?></td>
                            <td class="border px-3 sm:px-4 py-2 sm:py-3 whitespace-nowrap"><?= e($row['city'] ?? '') ?></td>
                            <td class="border px-3 sm:px-4 py-2 sm:py-3 whitespace-nowrap"><?= e($row['state'] ?? '') ?></td>

                            <td class="border px-3 sm:px-4 py-2 sm:py-3 bg-orange-50">
                                <?php if (!empty($row['plain_password'])): ?>
                                    <span class="password-text text-orange-700"><?= e($row['plain_password']) ?></span>
                                    <button type="button" class="copy-btn" title="Copy password"
                                        onclick="copyPassword(this, '<?= e($row['plain_password']) ?>')">
                                        <i data-lucide="copy"></i>
                                    </button>
                                <?php else: ?>
                                    <span class="text-gray-400 text-xs">—</span>
                                <?php endif; ?>
                            </td>

                            <td class="border px-3 sm:px-4 py-2 sm:py-3">
                                <span class="px-2 sm:px-3 py-1 rounded-full text-xs font-semibold bg-green-100 text-green-700 whitespace-nowrap">
                                    <?= e($row['status'] ?? 'active') ?>
                                </span>
                            </td>
                            <td class="border px-3 sm:px-4 py-2 sm:py-3 text-gray-500 whitespace-nowrap">
                                <?= !empty($row['created_date']) ? e(date('d-m-Y', strtotime($row['created_date']))) : '' ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr id="emptyRow">
                        <td colspan="12" class="border px-4 py-8 text-center text-gray-400">
                            <div class="flex flex-col items-center gap-2">
                                <i data-lucide="inbox" class="w-8 h-8 text-gray-300"></i>
                                No mobile franchies registered yet.
                            </div>
                        </td>
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
    if (typeof lucide !== "undefined") {
        lucide.createIcons();
    }

    /* ── State → City filter ── */
    const stateSelect = document.getElementById("stateSelect");
    const citySelect  = document.getElementById("citySelect");
    const cityOptions = Array.from(citySelect.querySelectorAll("option[data-state]"));

function filterCityOptions(selectedState) {
    citySelect.disabled = selectedState === "";

    if (selectedState === "") {
        citySelect.innerHTML =
            '<option value="">Select City Franchies</option>';
        return;
    }

    // Get cities of selected state
    const filteredCities = cityOptions.filter(
        option => option.dataset.state === selectedState
    );

    citySelect.innerHTML = '';

    // No city franchise available
    if (filteredCities.length === 0) {
        citySelect.innerHTML =
            '<option value="">No City Franchise (Register Under State)</option>';
        return;
    }

    // Cities available
    citySelect.innerHTML =
        '<option value="">Select City Franchies</option>';

    filteredCities.forEach(option => {
        citySelect.appendChild(option.cloneNode(true));
    });
}

    if (stateSelect && citySelect) {
        citySelect.disabled = true;
        stateSelect.addEventListener("change", function () {
            filterCityOptions(this.value);
        });
    }

    /* ── Form submit ── */
    const form       = document.getElementById("mobileFranchiesForm");
    const btn        = document.getElementById("submitBtn");
    const messageBox = document.getElementById("messageBox");
    const tableBody  = document.getElementById("franchiesTableBody");

    function escapeHtml(value) {
        if (value === null || value === undefined) return "";
        return String(value)
            .replaceAll("&",  "&amp;")
            .replaceAll("<",  "&lt;")
            .replaceAll(">",  "&gt;")
            .replaceAll('"',  "&quot;")
            .replaceAll("'",  "&#039;");
    }

    form.addEventListener("submit", function (e) {
        e.preventDefault();
        btn.disabled  = true;
        btn.innerText = "Registering...";

        const formData = new FormData(form);
        formData.append("ajax_register", "1");

        fetch(window.location.href, { method: "POST", body: formData })
            .then(r => r.json())
            .then(result => {
                messageBox.classList.remove("hidden");

                if (result.status === "success") {
                    messageBox.className = "mb-4 sm:mb-6 rounded-lg border border-green-200 bg-green-50 px-4 sm:px-5 py-3 sm:py-4 text-green-700 font-semibold text-sm sm:text-base";
                    messageBox.innerText = result.message;

                    const emptyRow = document.getElementById("emptyRow");
                    if (emptyRow) emptyRow.remove();

                    const totalRows = tableBody.querySelectorAll("tr").length + 1;
                    const d = result.data;

                    const newRow = document.createElement("tr");
                    newRow.className = "hover:bg-gray-50";
                    newRow.innerHTML = `
                        <td class="border px-3 sm:px-4 py-2 sm:py-3 text-gray-500">${totalRows}</td>
                        <td class="border px-3 sm:px-4 py-2 sm:py-3 font-bold text-blue-600 whitespace-nowrap">${escapeHtml(d.mobile_franchies_id)}</td>
                        <td class="border px-3 sm:px-4 py-2 sm:py-3">${escapeHtml(d.owner_name)}</td>
                        <td class="border px-3 sm:px-4 py-2 sm:py-3 whitespace-nowrap">${escapeHtml(d.mobile)}</td>
                        <td class="border px-3 sm:px-4 py-2 sm:py-3">${escapeHtml(d.email)}</td>
                        <td class="border px-3 sm:px-4 py-2 sm:py-3 whitespace-nowrap">${escapeHtml(d.date_of_birth)}</td>
                        <td class="border px-3 sm:px-4 py-2 sm:py-3">${escapeHtml(d.gst_no)}</td>
                        <td class="border px-3 sm:px-4 py-2 sm:py-3 whitespace-nowrap">${escapeHtml(d.city)}</td>
                        <td class="border px-3 sm:px-4 py-2 sm:py-3 whitespace-nowrap">${escapeHtml(d.state)}</td>
                        <td class="border px-3 sm:px-4 py-2 sm:py-3 bg-orange-50">
                            <span class="password-text text-orange-700">${escapeHtml(d.plain_password)}</span>
                            <button type="button" class="copy-btn" title="Copy password"
                                onclick="copyPassword(this, '${escapeHtml(d.plain_password)}')">
                                <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24"
                                    fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
                                    <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
                                </svg>
                            </button>
                        </td>
                        <td class="border px-3 sm:px-4 py-2 sm:py-3">
                            <span class="px-2 sm:px-3 py-1 rounded-full text-xs font-semibold bg-green-100 text-green-700 whitespace-nowrap">
                                ${escapeHtml(d.status)}
                            </span>
                        </td>
                        <td class="border px-3 sm:px-4 py-2 sm:py-3 text-gray-500 whitespace-nowrap">${escapeHtml(d.created_date)}</td>
                    `;

                    tableBody.prepend(newRow);
                    form.reset();

                    const countryInput = form.querySelector('[name="country"]');
                    if (countryInput) countryInput.value = "India";

                    citySelect.disabled = true;

                    tableBody.closest('.bg-white').scrollIntoView({ behavior: 'smooth', block: 'start' });

                } else {
                    messageBox.className = "mb-4 sm:mb-6 rounded-lg border border-red-200 bg-red-50 px-4 sm:px-5 py-3 sm:py-4 text-red-700 font-semibold text-sm sm:text-base";
                    messageBox.innerText = result.message;
                }
            })
            .catch(() => {
                messageBox.classList.remove("hidden");
                messageBox.className = "mb-4 sm:mb-6 rounded-lg border border-red-200 bg-red-50 px-4 sm:px-5 py-3 sm:py-4 text-red-700 font-semibold text-sm sm:text-base";
                messageBox.innerText = "Something went wrong. Please try again.";
            })
            .finally(() => {
                btn.disabled  = false;
                btn.innerText = "Register Mobile Franchies";
            });
    });
});

/* ── Copy password to clipboard ── */
function copyPassword(btn, password) {
    navigator.clipboard.writeText(password).then(() => {
        btn.style.background = '#22c55e';
        btn.style.borderColor = '#22c55e';
        setTimeout(() => {
            btn.style.background = '';
            btn.style.borderColor = '';
        }, 1200);
    }).catch(() => {
        const ta = document.createElement('textarea');
        ta.value = password;
        document.body.appendChild(ta);
        ta.select();
        document.execCommand('copy');
        document.body.removeChild(ta);
    });
}
</script>

</body>
</html>