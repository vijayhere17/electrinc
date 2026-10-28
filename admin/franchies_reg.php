<?php

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config/db.php';

if (!isset($conn) || !($conn instanceof mysqli)) {
    die("Database connection failed.");
}

function e($v) {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

$conn->query("
    CREATE TABLE IF NOT EXISTS `franchies_holder` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `franchise_code` VARCHAR(50) UNIQUE NOT NULL,
        `franchies_password` VARCHAR(255) NOT NULL,
        `plain_password` VARCHAR(50) NULL,
        `name` VARCHAR(100) NOT NULL,
        `email` VARCHAR(100) NULL,
        `mobile` VARCHAR(20) NULL,
        `date_of_birth` DATE NULL,
        `gst_no` VARCHAR(50) NULL,
        `address_1` VARCHAR(255) NULL,
        `address_2` VARCHAR(255) NULL,
        `city` VARCHAR(100) NULL,
        `state` VARCHAR(100) NULL,
        `country` VARCHAR(100) NULL,
        `postal_code` VARCHAR(20) NULL,
        `details` TEXT NULL,
        `status` VARCHAR(20) DEFAULT 'active',
        `created_date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

$conn->query("ALTER TABLE `franchies_holder` ADD COLUMN IF NOT EXISTS `plain_password` VARCHAR(50) NULL AFTER `franchies_password`");

function generateFranchiesID($conn) {
    $prefix = "FRD";
    do {
        $randomNumber = random_int(1000, 9999);
        $franchies_id = $prefix . $randomNumber;

        $stmt = $conn->prepare("SELECT id FROM franchies_holder WHERE franchise_code = ? LIMIT 1");
        $stmt->bind_param("s", $franchies_id);
        $stmt->execute();
        $stmt->store_result();
        $exists = $stmt->num_rows > 0;
        $stmt->close();
    } while ($exists);

    return $franchies_id;
}

function generateRandomPassword(): string {
    return 'FR' . random_int(100000, 999999);
}

$success_message = '';
$error_message   = '';
$generated_id    = '';
$generated_password = '';

if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    $generated_id = $_SESSION['generated_id'];
    $generated_password = $_SESSION['generated_password'];

    unset($_SESSION['success_message']);
    unset($_SESSION['generated_id']);
    unset($_SESSION['generated_password']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name          = trim($_POST['name'] ?? '');
    $email         = trim($_POST['email'] ?? '');
    $mobile        = trim($_POST['mobile'] ?? '');
    $date_of_birth = trim($_POST['date_of_birth'] ?? '');
    $gst_no        = trim($_POST['gst_no'] ?? '');
    $address_1     = trim($_POST['address_1'] ?? '');
    $address_2     = trim($_POST['address_2'] ?? '');
    $city          = trim($_POST['city'] ?? '');
    $state         = trim($_POST['state'] ?? '');
    $country       = trim($_POST['country'] ?? '');
    $postal_code   = trim($_POST['postal_code'] ?? '');
    $details       = trim($_POST['details'] ?? '');

    if ($name === '') {
        $error_message = "Name is required.";
    } elseif ($state === '') {
        $error_message = "State is required.";
    } else {
        $franchies_id   = generateFranchiesID($conn);
        $plainPassword  = generateRandomPassword();
        $hashedPassword = password_hash($plainPassword, PASSWORD_BCRYPT);
        $status         = 'active';

        if ($date_of_birth === '') {
            $date_of_birth = null;
        }

        $stmt = $conn->prepare("
            INSERT INTO franchies_holder
            (
                franchise_code, franchies_password, plain_password,
                name, email, mobile, date_of_birth, gst_no,
                address_1, address_2, city, state, country,
                postal_code, details, status
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        if ($stmt) {
            $stmt->bind_param(
                "ssssssssssssssss",
                $franchies_id,
                $hashedPassword,
                $plainPassword,
                $name,
                $email,
                $mobile,
                $date_of_birth,
                $gst_no,
                $address_1,
                $address_2,
                $city,
                $state,
                $country,
                $postal_code,
                $details,
                $status
            );

            if ($stmt->execute()) {
                $_SESSION['success_message'] = "Franchies registered successfully.";
                $_SESSION['generated_id'] = $franchies_id;
                $_SESSION['generated_password'] = $plainPassword;

                header("Location: " . $_SERVER['PHP_SELF']);
                exit;
            } else {
                $error_message = "Insert failed: " . $stmt->error;
            }
            $stmt->close();
        } else {
            $error_message = "Prepare failed: " . $conn->error;
        }
    }
}

$franchies_list = [];
$result = $conn->query("SELECT * FROM franchies_holder ORDER BY id DESC");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $franchies_list[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Franchies Registration</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="assets/css/admin-responsive.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        .pwd-cell {
            position: relative;
            display: flex;
            align-items: center;
            gap: 8px;
            min-width: 150px;
        }
        .pwd-text {
            font-family: monospace;
            letter-spacing: .05em;
        }
        .pwd-dots {
            letter-spacing: .15em;
            color: #6b7280;
            font-size: 18px;
        }
        .eye-btn,
        .copy-btn {
            background: none;
            border: none;
            cursor: pointer;
            padding: 2px 4px;
            color: #9ca3af;
            transition: color .15s;
            flex-shrink: 0;
        }
        .eye-btn:hover { color: #c9932f; }
        .copy-btn:hover { color: #16a34a; }
        .copy-btn.copied { color: #16a34a; }
    </style>
</head>

<body class="bg-[#f3f4f6] font-sans overflow-x-hidden">

<?php if (file_exists(__DIR__ . "/includes/sidebar.php")) include __DIR__ . "/includes/sidebar.php"; ?>

<div class="<?php echo file_exists(__DIR__ . '/includes/sidebar.php') ? 'lg:ml-64 min-h-screen bg-[#f3f4f6]' : 'min-h-screen bg-[#f3f4f6]'; ?>">

    <?php if (file_exists(__DIR__ . "/includes/navbar.php")) include __DIR__ . "/includes/navbar.php"; ?>

    <main class="px-3 sm:px-4 md:px-6 lg:px-8 py-4 sm:py-6 md:py-8">
        <div class="max-w-[1200px] mx-auto">

            <div class="mb-6 sm:mb-8 md:mb-9 flex items-center gap-3 sm:gap-4 md:gap-5">
                <div class="flex h-[40px] w-[40px] sm:h-[44px] sm:w-[44px] shrink-0 items-center justify-center rounded-[10px] bg-[#f6eee2]">
                    <i data-lucide="badge-check" class="h-5 w-5 text-[#cf9a3c]"></i>
                </div>
                <h2 class="whitespace-nowrap text-[16px] sm:text-[20px] md:text-[25px] font-normal leading-none text-[#1f2937]"
                    style="font-family: Georgia, 'Times New Roman', serif;">
                    FRANCHIES REGISTRATION
                </h2>
                <div class="h-[2px] w-full bg-[#d1a14b]"></div>
            </div>

            <?php if ($success_message !== ''): ?>
                <div class="mb-5 sm:mb-6 rounded-lg border border-green-200 bg-green-50 px-4 sm:px-5 py-4 text-green-700">
                    <div class="flex items-start sm:items-center gap-2 mb-2">
                        <i data-lucide="circle-check-big" class="h-5 w-5 text-green-600 shrink-0 mt-0.5 sm:mt-0"></i>
                        <span class="font-semibold text-sm sm:text-base"><?php echo e($success_message); ?></span>
                    </div>
                    <div class="sm:ml-7 space-y-2 text-[14px] sm:text-[15px] break-words">
                        <div>
                            <span class="font-medium text-green-800">Franchies ID:</span>
                            <span class="ml-2 font-mono font-bold tracking-wide"><?php echo e($generated_id); ?></span>
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="font-medium text-green-800">Password:</span>
                            <span class="font-mono font-bold tracking-wide" id="alert-pwd"><?php echo e($generated_password); ?></span>
                            <button onclick="copyText('<?php echo e($generated_password); ?>', this)"
                                    type="button"
                                    class="copy-btn"
                                    title="Copy password">
                                <i data-lucide="copy" class="h-4 w-4"></i>
                            </button>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($error_message !== ''): ?>
                <div class="mb-5 sm:mb-6 rounded-lg border border-red-200 bg-red-50 px-4 sm:px-5 py-4 text-red-700">
                    <div class="flex items-start sm:items-center gap-2">
                        <i data-lucide="circle-x" class="h-5 w-5 text-red-500 shrink-0 mt-0.5 sm:mt-0"></i>
                        <span class="text-sm sm:text-base"><?php echo e($error_message); ?></span>
                    </div>
                </div>
            <?php endif; ?>

            <form method="POST" class="bg-white rounded-[16px] shadow-sm border border-gray-100 p-4 sm:p-6 md:p-8 lg:p-10 mb-8 sm:mb-10">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 sm:gap-5 md:gap-6">

                    <div class="md:col-span-2">
                        <label class="block text-[14px] sm:text-[15px] md:text-[16px] font-semibold text-gray-700 mb-2">
                            Name <span class="text-red-500">*</span>
                        </label>
                        <input type="text" name="name" required
                               class="w-full h-[46px] sm:h-[50px] md:h-[52px] px-4 sm:px-5 border border-gray-300 rounded-[8px] text-[15px] sm:text-[16px] md:text-[17px] focus:outline-none focus:ring-2 focus:ring-[#d1a14b]"
                               placeholder="Enter franchies owner name">
                    </div>

                    <div>
                        <label class="block text-[14px] sm:text-[15px] md:text-[16px] font-semibold text-gray-700 mb-2">Email</label>
                        <input type="email" name="email"
                               class="w-full h-[46px] sm:h-[50px] md:h-[52px] px-4 sm:px-5 border border-gray-300 rounded-[8px] text-[15px] sm:text-[16px] md:text-[17px] focus:outline-none focus:ring-2 focus:ring-[#d1a14b]"
                               placeholder="franchies@example.com">
                    </div>

                    <div>
                        <label class="block text-[14px] sm:text-[15px] md:text-[16px] font-semibold text-gray-700 mb-2">Mobile</label>
                        <input type="text" name="mobile"
                               class="w-full h-[46px] sm:h-[50px] md:h-[52px] px-4 sm:px-5 border border-gray-300 rounded-[8px] text-[15px] sm:text-[16px] md:text-[17px] focus:outline-none focus:ring-2 focus:ring-[#d1a14b]"
                               placeholder="Enter mobile number">
                    </div>

                    <div>
                        <label class="block text-[14px] sm:text-[15px] md:text-[16px] font-semibold text-gray-700 mb-2">Date Of Birth</label>
                        <input type="date" name="date_of_birth"
                               class="w-full h-[46px] sm:h-[50px] md:h-[52px] px-4 sm:px-5 border border-gray-300 rounded-[8px] text-[15px] sm:text-[16px] md:text-[17px] focus:outline-none focus:ring-2 focus:ring-[#d1a14b]">
                    </div>

                    <div>
                        <label class="block text-[14px] sm:text-[15px] md:text-[16px] font-semibold text-gray-700 mb-2">GST No</label>
                        <input type="text" name="gst_no"
                               class="w-full h-[46px] sm:h-[50px] md:h-[52px] px-4 sm:px-5 border border-gray-300 rounded-[8px] text-[15px] sm:text-[16px] md:text-[17px] focus:outline-none focus:ring-2 focus:ring-[#d1a14b]"
                               placeholder="Enter GST number">
                    </div>

                    <div class="md:col-span-2">
                        <label class="block text-[14px] sm:text-[15px] md:text-[16px] font-semibold text-gray-700 mb-2">Address Line 1</label>
                        <input type="text" name="address_1"
                               class="w-full h-[46px] sm:h-[50px] md:h-[52px] px-4 sm:px-5 border border-gray-300 rounded-[8px] text-[15px] sm:text-[16px] md:text-[17px] focus:outline-none focus:ring-2 focus:ring-[#d1a14b]"
                               placeholder="Street address">
                    </div>

                    <div class="md:col-span-2">
                        <label class="block text-[14px] sm:text-[15px] md:text-[16px] font-semibold text-gray-700 mb-2">Address Line 2</label>
                        <input type="text" name="address_2"
                               class="w-full h-[46px] sm:h-[50px] md:h-[52px] px-4 sm:px-5 border border-gray-300 rounded-[8px] text-[15px] sm:text-[16px] md:text-[17px] focus:outline-none focus:ring-2 focus:ring-[#d1a14b]"
                               placeholder="Apartment, suite, etc.">
                    </div>

                    <div>
                        <label class="block text-[14px] sm:text-[15px] md:text-[16px] font-semibold text-gray-700 mb-2">City</label>
                        <input type="text" name="city"
                               class="w-full h-[46px] sm:h-[50px] md:h-[52px] px-4 sm:px-5 border border-gray-300 rounded-[8px] text-[15px] sm:text-[16px] md:text-[17px] focus:outline-none focus:ring-2 focus:ring-[#d1a14b]"
                               placeholder="City">
                    </div>

                    <div>
                        <label class="block text-[14px] sm:text-[15px] md:text-[16px] font-semibold text-gray-700 mb-2">
                            State <span class="text-red-500">*</span>
                        </label>
                        <input type="text" name="state" required
                               class="w-full h-[46px] sm:h-[50px] md:h-[52px] px-4 sm:px-5 border border-gray-300 rounded-[8px] text-[15px] sm:text-[16px] md:text-[17px] focus:outline-none focus:ring-2 focus:ring-[#d1a14b]"
                               placeholder="State">
                    </div>

                    <div>
                        <label class="block text-[14px] sm:text-[15px] md:text-[16px] font-semibold text-gray-700 mb-2">Country</label>
                        <input type="text" name="country"
                               class="w-full h-[46px] sm:h-[50px] md:h-[52px] px-4 sm:px-5 border border-gray-300 rounded-[8px] text-[15px] sm:text-[16px] md:text-[17px] focus:outline-none focus:ring-2 focus:ring-[#d1a14b]"
                               placeholder="Country">
                    </div>

                    <div>
                        <label class="block text-[14px] sm:text-[15px] md:text-[16px] font-semibold text-gray-700 mb-2">Postal Code</label>
                        <input type="text" name="postal_code"
                               class="w-full h-[46px] sm:h-[50px] md:h-[52px] px-4 sm:px-5 border border-gray-300 rounded-[8px] text-[15px] sm:text-[16px] md:text-[17px] focus:outline-none focus:ring-2 focus:ring-[#d1a14b]"
                               placeholder="Postal code">
                    </div>

                    <div class="md:col-span-2">
                        <label class="block text-[14px] sm:text-[15px] md:text-[16px] font-semibold text-gray-700 mb-2">Details</label>
                        <textarea name="details" rows="4"
                                  class="w-full px-4 sm:px-5 py-3 border border-gray-300 rounded-[8px] text-[15px] sm:text-[16px] md:text-[17px] focus:outline-none focus:ring-2 focus:ring-[#d1a14b]"
                                  placeholder="Additional details"></textarea>
                    </div>

                    <div class="md:col-span-2 flex justify-center pt-2 sm:pt-4">
                        <button type="submit"
                                class="w-full sm:w-auto bg-[#c9932f] hover:bg-[#b88426] text-white px-8 sm:px-12 md:px-16 py-3 rounded-[8px] text-[15px] sm:text-[16px] md:text-[17px] font-semibold transition-colors">
                            Register
                        </button>
                    </div>

                </div>
            </form>

            <div class="bg-white rounded-[16px] shadow-sm border border-gray-100 overflow-hidden">
                <div class="px-4 sm:px-5 md:px-6 py-4 border-b border-gray-100 flex items-center gap-3">
                    <div class="flex h-[36px] w-[36px] items-center justify-center rounded-[8px] bg-[#f6eee2] shrink-0">
                        <i data-lucide="list" class="h-4 w-4 text-[#cf9a3c]"></i>
                    </div>
                    <h3 class="text-[15px] sm:text-[16px] md:text-[17px] font-semibold text-gray-700"
                        style="font-family: Georgia,'Times New Roman',serif;">
                        Registered Franchies
                    </h3>
                    <span class="ml-auto text-[11px] sm:text-xs font-medium bg-[#f6eee2] text-[#c9932f] px-2.5 sm:px-3 py-1 rounded-full whitespace-nowrap">
                        <?php echo count($franchies_list); ?> total
                    </span>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full min-w-[1050px] text-[13px] sm:text-[14px]">
                        <thead class="bg-gray-50 border-b border-gray-200">
                        <tr>
                            <th class="px-4 sm:px-6 py-4 text-left text-[11px] sm:text-xs font-bold text-gray-500 uppercase tracking-wide">#</th>
                            <th class="px-4 sm:px-6 py-4 text-left text-[11px] sm:text-xs font-bold text-gray-500 uppercase tracking-wide">Franchies ID</th>
                            <th class="px-4 sm:px-6 py-4 text-left text-[11px] sm:text-xs font-bold text-gray-500 uppercase tracking-wide">Name</th>
                            <th class="px-4 sm:px-6 py-4 text-left text-[11px] sm:text-xs font-bold text-gray-500 uppercase tracking-wide">Email</th>
                            <th class="px-4 sm:px-6 py-4 text-left text-[11px] sm:text-xs font-bold text-gray-500 uppercase tracking-wide">Mobile</th>
                            <th class="px-4 sm:px-6 py-4 text-left text-[11px] sm:text-xs font-bold text-gray-500 uppercase tracking-wide">City</th>
                            <th class="px-4 sm:px-6 py-4 text-left text-[11px] sm:text-xs font-bold text-gray-500 uppercase tracking-wide">
                                <div class="flex items-center gap-1">
                                    <i data-lucide="key-round" class="h-3.5 w-3.5 text-[#c9932f]"></i>
                                    Admin Password
                                </div>
                            </th>
                            <th class="px-4 sm:px-6 py-4 text-left text-[11px] sm:text-xs font-bold text-gray-500 uppercase tracking-wide">Status</th>
                            <th class="px-4 sm:px-6 py-4 text-left text-[11px] sm:text-xs font-bold text-gray-500 uppercase tracking-wide">Registered</th>
                        </tr>
                        </thead>

                        <tbody>
                        <?php if (!empty($franchies_list)): ?>
                            <?php foreach ($franchies_list as $i => $franchies): ?>
                                <tr class="border-b border-gray-100 hover:bg-amber-50/40 transition-colors">
                                    <td class="px-4 sm:px-6 py-4 text-sm text-gray-500 whitespace-nowrap"><?php echo e($franchies['id']); ?></td>

                                    <td class="px-4 sm:px-6 py-4 whitespace-nowrap">
                                        <span class="inline-flex items-center gap-1.5 bg-blue-50 text-blue-700 font-bold text-xs px-3 py-1.5 rounded-full font-mono">
                                            <?php echo e($franchies['franchise_code'] ?? '-'); ?>
                                        </span>
                                    </td>

                                    <td class="px-4 sm:px-6 py-4 text-sm font-medium text-gray-800 whitespace-nowrap"><?php echo e($franchies['name'] ?? '-'); ?></td>

                                    <td class="px-4 sm:px-6 py-4 text-sm text-gray-500"><?php echo e($franchies['email'] ?? '-'); ?></td>

                                    <td class="px-4 sm:px-6 py-4 text-sm text-gray-500 whitespace-nowrap"><?php echo e($franchies['mobile'] ?? '-'); ?></td>

                                    <td class="px-4 sm:px-6 py-4 text-sm text-gray-500 whitespace-nowrap"><?php echo e($franchies['city'] ?? '-'); ?></td>

                                    <td class="px-4 sm:px-6 py-4">
                                        <?php
                                            $rowId  = 'pwd-' . $franchies['id'];
                                            $pwdVal = !empty($franchies['plain_password']) ? $franchies['plain_password'] : '';
                                        ?>
                                        <?php if ($pwdVal !== ''): ?>
                                            <div class="pwd-cell">
                                                <span id="<?php echo e($rowId); ?>-dots" class="pwd-dots select-none">••••••••</span>

                                                <span id="<?php echo e($rowId); ?>-text"
                                                      class="pwd-text text-sm font-semibold text-gray-800 hidden whitespace-nowrap">
                                                    <?php echo e($pwdVal); ?>
                                                </span>

                                                <button type="button"
                                                        class="eye-btn"
                                                        title="Show / Hide password"
                                                        onclick="togglePwd('<?php echo e($rowId); ?>')">
                                                    <i data-lucide="eye" class="h-4 w-4" id="<?php echo e($rowId); ?>-eye"></i>
                                                </button>

                                                <button type="button"
                                                        class="copy-btn"
                                                        title="Copy password"
                                                        onclick="copyText('<?php echo e($pwdVal); ?>', this)">
                                                    <i data-lucide="copy" class="h-4 w-4"></i>
                                                </button>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-xs text-gray-400 italic">Not stored</span>
                                        <?php endif; ?>
                                    </td>

                                    <td class="px-4 sm:px-6 py-4 whitespace-nowrap">
                                        <?php
                                            $status    = strtolower($franchies['status'] ?? 'active');
                                            $statusCls = $status === 'active'
                                                ? 'bg-green-100 text-green-800'
                                                : 'bg-red-100 text-red-700';
                                        ?>
                                        <span class="px-3 py-1 rounded-full text-xs font-semibold <?php echo $statusCls; ?>">
                                            <?php echo e(ucfirst($status)); ?>
                                        </span>
                                    </td>

                                    <td class="px-4 sm:px-6 py-4 text-sm text-gray-500 whitespace-nowrap">
                                        <?php echo !empty($franchies['created_date'])
                                            ? e(date('d-m-Y', strtotime($franchies['created_date'])))
                                            : '-'; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="9" class="px-6 py-12 text-center">
                                    <div class="flex flex-col items-center gap-3 text-gray-400">
                                        <i data-lucide="folder-open" class="h-10 w-10 opacity-40"></i>
                                        <span class="text-sm">No franchies registered yet.</span>
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
});

function togglePwd(rowId) {
    const dots = document.getElementById(rowId + '-dots');
    const text = document.getElementById(rowId + '-text');
    const eye  = document.getElementById(rowId + '-eye');

    const isHidden = text.classList.contains('hidden');

    if (isHidden) {
        dots.classList.add('hidden');
        text.classList.remove('hidden');
        eye.setAttribute('data-lucide', 'eye-off');
    } else {
        dots.classList.remove('hidden');
        text.classList.add('hidden');
        eye.setAttribute('data-lucide', 'eye');
    }

    if (typeof lucide !== "undefined") {
        lucide.createIcons();
    }
}

function copyText(val, btn) {
    navigator.clipboard.writeText(val).then(() => {
        btn.classList.add('copied');
        const icon = btn.querySelector('i');

        if (icon) {
            icon.setAttribute('data-lucide', 'check');
            if (typeof lucide !== "undefined") {
                lucide.createIcons();
            }
        }

        setTimeout(() => {
            btn.classList.remove('copied');
            if (icon) {
                icon.setAttribute('data-lucide', 'copy');
                if (typeof lucide !== "undefined") {
                    lucide.createIcons();
                }
            }
        }, 1800);
    });
}
</script>

</body>
</html>