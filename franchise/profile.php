<?php
require_once __DIR__ . '/includes/auth.php';

if (!function_exists('e')) {
    function e($value) {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

if (empty($_SESSION['franchise_id']) || empty($_SESSION['franchise_type'])) {
    header("Location: index.php");
    exit();
}

if (!isset($conn) || !($conn instanceof mysqli)) {
    die("Database connection failed.");
}

function tableExists(mysqli $conn, string $table): bool {
    $safeTable = $conn->real_escape_string($table);
    $result = $conn->query("SHOW TABLES LIKE '$safeTable'");
    return ($result && $result->num_rows > 0);
}

function tableHasColumn(mysqli $conn, string $table, string $column): bool {
    if (!tableExists($conn, $table)) {
        return false;
    }

    $safeTable = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
    $safeCol   = $conn->real_escape_string($column);

    $result = $conn->query("SHOW COLUMNS FROM `$safeTable` LIKE '$safeCol'");
    return ($result && $result->num_rows > 0);
}

function getExistingColumn(mysqli $conn, string $table, array $columns): string {
    foreach ($columns as $column) {
        if (tableHasColumn($conn, $table, $column)) {
            return $column;
        }
    }
    return '';
}

function getValue(array $row, array $columns, string $fallback = ''): string {
    foreach ($columns as $column) {
        if (isset($row[$column]) && trim((string)$row[$column]) !== '') {
            return (string)$row[$column];
        }
    }
    return $fallback;
}

$franchiseId   = (int)$_SESSION['franchise_id'];
$franchiseType = strtolower(trim((string)$_SESSION['franchise_type']));

if ($franchiseType === 'state') {
    $tableName = 'franchies_holder';
} elseif ($franchiseType === 'city') {
    $tableName = 'city_franchies_holder';
} elseif ($franchiseType === 'mobile') {
    $tableName = 'mobile_franchies_holder';
} else {
    session_destroy();
    header("Location: index.php");
    exit();
}

if (!tableExists($conn, $tableName)) {
    die("Table not found: " . e($tableName));
}

$nameColumn     = getExistingColumn($conn, $tableName, ['owner_name', 'shop_name', 'name', 'full_name']);
$dobColumn      = getExistingColumn($conn, $tableName, ['date_of_birth', 'dob']);
$gstColumn      = getExistingColumn($conn, $tableName, ['gst_no', 'gst_number']);
$codeColumn     = getExistingColumn($conn, $tableName, ['mobile_franchies_id', 'city_franchies_id', 'franchies_id', 'franchises_id', 'franchise_id']);
$addressColumn  = getExistingColumn($conn, $tableName, ['shop_address', 'address_1', 'address', 'full_address']);
$address2Column = getExistingColumn($conn, $tableName, ['address_2', 'address2']);
$districtColumn = getExistingColumn($conn, $tableName, ['district', 'details']);
$pincodeColumn  = getExistingColumn($conn, $tableName, ['pincode', 'postal_code', 'pin_code']);
$countryColumn  = getExistingColumn($conn, $tableName, ['country']);

$stmt = $conn->prepare("SELECT * FROM `$tableName` WHERE id = ? LIMIT 1");
if (!$stmt) {
    die("SQL Error: " . e($conn->error));
}

$stmt->bind_param("i", $franchiseId);
$stmt->execute();
$result = $stmt->get_result();

if (!$result || $result->num_rows === 0) {
    header('Location: logout.php');
    exit();
}

$franchise = $result->fetch_assoc();
$stmt->close();

$formMessage = '';
$formError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['profile_submit'])) {
    $firstNamePost = trim($_POST['first_name'] ?? '');
    $lastNamePost  = trim($_POST['last_name'] ?? '');
    $name          = trim($firstNamePost . ' ' . $lastNamePost);

    $email       = trim($_POST['email'] ?? '');
    $mobile      = trim($_POST['mobile'] ?? '');
    $dateOfBirth = trim($_POST['date_of_birth'] ?? '');
    $gstNumber   = trim($_POST['gst_no'] ?? '');

    $address1     = trim($_POST['address_1'] ?? '');
    $address2     = trim($_POST['address_2'] ?? '');
    $cityPost     = trim($_POST['city'] ?? '');
    $statePost    = trim($_POST['state'] ?? '');
    $districtPost = trim($_POST['district'] ?? '');
    $postalCode   = trim($_POST['postal_code'] ?? '');
    $countryPost  = trim($_POST['country'] ?? '');

    if ($name === '') {
        $formError = 'Name is required.';
    } elseif ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $formError = 'Please enter a valid email address.';
    } else {
        $updateParts = [];
        $updateTypes = '';
        $updateValues = [];

        if ($nameColumn !== '') {
            $updateParts[] = "`$nameColumn` = ?";
            $updateTypes .= 's';
            $updateValues[] = $name;
        }

        $safeFields = [
            'email'  => $email,
            'mobile' => $mobile,
            'city'   => $cityPost,
            'state'  => $statePost,
        ];

        foreach ($safeFields as $column => $value) {
            if (tableHasColumn($conn, $tableName, $column)) {
                $updateParts[] = "`$column` = ?";
                $updateTypes .= 's';
                $updateValues[] = $value;
            }
        }

        if ($addressColumn !== '') {
            $updateParts[] = "`$addressColumn` = ?";
            $updateTypes .= 's';
            $updateValues[] = $address1;
        }

        if ($address2Column !== '') {
            $updateParts[] = "`$address2Column` = ?";
            $updateTypes .= 's';
            $updateValues[] = $address2;
        }

        if ($districtColumn !== '') {
            $updateParts[] = "`$districtColumn` = ?";
            $updateTypes .= 's';
            $updateValues[] = $districtPost;
        }

        if ($pincodeColumn !== '') {
            $updateParts[] = "`$pincodeColumn` = ?";
            $updateTypes .= 's';
            $updateValues[] = $postalCode;
        }

        if ($countryColumn !== '') {
            $updateParts[] = "`$countryColumn` = ?";
            $updateTypes .= 's';
            $updateValues[] = $countryPost;
        }

        if ($dobColumn !== '') {
            $updateParts[] = "`$dobColumn` = ?";
            $updateTypes .= 's';
            $updateValues[] = $dateOfBirth;
        }

        if ($gstColumn !== '') {
            $updateParts[] = "`$gstColumn` = ?";
            $updateTypes .= 's';
            $updateValues[] = $gstNumber;
        }

        if (empty($updateParts)) {
            $formError = 'No valid database columns found for update.';
        } else {
            $updateTypes .= 'i';
            $updateValues[] = $franchiseId;

            $sql = "UPDATE `$tableName` SET " . implode(', ', $updateParts) . " WHERE id = ? LIMIT 1";
            $stmt = $conn->prepare($sql);

            if ($stmt) {
                $bindValues = [$updateTypes];

                foreach ($updateValues as $key => $value) {
                    $bindValues[] = &$updateValues[$key];
                }

                call_user_func_array([$stmt, 'bind_param'], $bindValues);

                if ($stmt->execute()) {
                    $_SESSION['franchise_name'] = $name;
                    $_SESSION['franchise_email'] = $email;
                    $_SESSION['franchise_mobile'] = $mobile;
                    $_SESSION['franchise_address'] = $address1;
                    $_SESSION['franchise_dob'] = $dateOfBirth;
                    $_SESSION['franchise_gst_no'] = $gstNumber;
                    $_SESSION['franchise_city'] = $cityPost;
                    $_SESSION['franchise_state'] = $statePost;

                    $formMessage = 'Profile updated successfully.';

                    $stmtReload = $conn->prepare("SELECT * FROM `$tableName` WHERE id = ? LIMIT 1");
                    $stmtReload->bind_param("i", $franchiseId);
                    $stmtReload->execute();
                    $reloadResult = $stmtReload->get_result();

                    if ($reloadResult && $reloadResult->num_rows > 0) {
                        $franchise = $reloadResult->fetch_assoc();
                    }

                    $stmtReload->close();
                } else {
                    $formError = 'Failed to update profile: ' . $stmt->error;
                }

                $stmt->close();
            } else {
                $formError = 'Failed to prepare update query: ' . $conn->error;
            }
        }
    }
}

$fullName = trim($nameColumn !== '' ? ($franchise[$nameColumn] ?? '') : '');
if ($fullName === '') {
    $fullName = $_SESSION['franchise_name'] ?? '';
}

$nameParts = preg_split('/\s+/', trim($fullName));
$firstName = $nameParts[0] ?? '';
$lastName = count($nameParts) > 1 ? implode(' ', array_slice($nameParts, 1)) : '';

$franchiseEmail  = $franchise['email'] ?? $_SESSION['franchise_email'] ?? '';
$franchiseMobile = $franchise['mobile'] ?? $_SESSION['franchise_mobile'] ?? '';

$addressLine1 = $addressColumn !== '' ? ($franchise[$addressColumn] ?? '') : '';
$addressLine2 = $address2Column !== '' ? ($franchise[$address2Column] ?? '') : '';

$city     = $franchise['city'] ?? '';
$state    = $franchise['state'] ?? '';
$district = $districtColumn !== '' ? ($franchise[$districtColumn] ?? '') : '';
$pincode  = $pincodeColumn !== '' ? ($franchise[$pincodeColumn] ?? '') : '';
$country  = $countryColumn !== '' ? ($franchise[$countryColumn] ?? '') : '';

$dob = $dobColumn !== '' ? ($franchise[$dobColumn] ?? '') : '';
$gstNo = $gstColumn !== '' ? ($franchise[$gstColumn] ?? '') : '';

$franchiseCode = $codeColumn !== '' ? ($franchise[$codeColumn] ?? '') : ($_SESSION['franchise_code'] ?? '');

$locationText = implode(', ', array_filter([$city, $state, $country]));
if ($locationText === '') {
    $locationText = 'Not available';
}

$_SESSION['franchise_code'] = $franchiseCode;
$_SESSION['franchise_name'] = $fullName;
$_SESSION['franchise_email'] = $franchiseEmail;
$_SESSION['franchise_mobile'] = $franchiseMobile;
$_SESSION['franchise_state'] = $state;
$_SESSION['franchise_city'] = $city;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Franchise Profile</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
</head>

<body class="bg-[#f3f4f6] font-sans overflow-x-hidden">
<?php include __DIR__ . '/includes/sidebar.php'; ?>

<div class="ml-64 min-h-screen">
    <?php include __DIR__ . '/includes/navbar.php'; ?>

    <main class="px-5 py-6">
        <div class="max-w-7xl mx-auto space-y-6">

            <div class="mt-5 flex items-center gap-4">
                <div class="w-[36px] h-[36px] rounded-[8px] bg-[#f5eee3] flex items-center justify-center shrink-0">
                    <i data-lucide="badge-check" class="w-[18px] h-[18px] text-[#cd9737]"></i>
                </div>

                <h2 class="whitespace-nowrap text-[20px] font-normal text-[#40352a]" style="font-family: Georgia, 'Times New Roman', serif;">
                    PROFILE
                </h2>

                <div class="w-full h-[2px] bg-[#d1a14b]"></div>
            </div>

            <?php if ($formMessage !== ''): ?>
                <div class="rounded-[12px] border border-green-200 bg-green-50 px-4 py-3 text-green-800">
                    <?php echo e($formMessage); ?>
                </div>
            <?php endif; ?>

            <?php if ($formError !== ''): ?>
                <div class="rounded-[12px] border border-red-200 bg-red-50 px-4 py-3 text-red-700">
                    <?php echo e($formError); ?>
                </div>
            <?php endif; ?>

            <form method="post" action="" class="space-y-6">

                <div class="rounded-[20px] bg-white border border-[#e7e7e7] shadow-sm px-6 py-8">
                    <div class="max-w-md">
                        <div class="text-[20px] md:text-[22px] font-semibold text-[#2353f1]">
                            <?php echo e($fullName !== '' ? $fullName : 'Not available'); ?>
                        </div>

                        <div class="mt-2 text-[15px] text-[#7a7f87]">
                            <?php echo e($locationText); ?>
                        </div>

                        <?php if ($franchiseCode !== ''): ?>
                            <div class="mt-2 text-[14px] text-[#7a7f87]">
                                Franchise ID: <?php echo e($franchiseCode); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="rounded-[20px] bg-white border border-[#e7e7e7] shadow-sm px-6 py-8">
                    <div class="text-[20px] font-bold text-[#2353f1] mb-8">
                        Personal Information
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-y-8 gap-x-12">
                        <div>
                            <p class="text-[15px] font-bold text-[#7a7f87] mb-2">First Name</p>
                            <input type="text" name="first_name" value="<?php echo e($firstName); ?>" required class="w-full border border-[#d7dce3] rounded-[8px] px-3 py-2 text-[16px] text-[#1f2937] focus:outline-none focus:ring-2 focus:ring-[#2353f1]">
                        </div>

                        <div>
                            <p class="text-[15px] font-bold text-[#7a7f87] mb-2">Last Name</p>
                            <input type="text" name="last_name" value="<?php echo e($lastName); ?>" class="w-full border border-[#d7dce3] rounded-[8px] px-3 py-2 text-[16px] text-[#1f2937] focus:outline-none focus:ring-2 focus:ring-[#2353f1]">
                        </div>

                        <div>
                            <p class="text-[15px] font-bold text-[#7a7f87] mb-2">Date of Birth</p>
                            <input type="date" name="date_of_birth" value="<?php echo e($dob); ?>" class="w-full border border-[#d7dce3] rounded-[8px] px-3 py-2 text-[16px] text-[#1f2937] focus:outline-none focus:ring-2 focus:ring-[#2353f1]">
                        </div>

                        <div>
                            <p class="text-[15px] font-bold text-[#7a7f87] mb-2">Email Address</p>
                            <input type="email" name="email" value="<?php echo e($franchiseEmail); ?>" class="w-full border border-[#d7dce3] rounded-[8px] px-3 py-2 text-[16px] text-[#1f2937] focus:outline-none focus:ring-2 focus:ring-[#2353f1]">
                        </div>

                        <div>
                            <p class="text-[15px] font-bold text-[#7a7f87] mb-2">Phone Number</p>
                            <input type="text" name="mobile" value="<?php echo e($franchiseMobile); ?>" class="w-full border border-[#d7dce3] rounded-[8px] px-3 py-2 text-[16px] text-[#1f2937] focus:outline-none focus:ring-2 focus:ring-[#2353f1]">
                        </div>

                        <div>
                            <p class="text-[15px] font-bold text-[#7a7f87] mb-2">GST NO</p>
                            <input type="text" name="gst_no" value="<?php echo e($gstNo); ?>" class="w-full border border-[#d7dce3] rounded-[8px] px-3 py-2 text-[16px] text-[#1f2937] focus:outline-none focus:ring-2 focus:ring-[#2353f1]">
                        </div>
                    </div>
                </div>

                <div class="rounded-[20px] bg-white border border-[#e7e7e7] shadow-sm px-6 py-8">
                    <div class="text-[20px] font-bold text-[#2353f1] mb-8">
                        Shipping Address
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-4 gap-y-8 gap-x-10">
                        <div>
                            <p class="text-[15px] font-bold text-[#7a7f87] mb-2">Address</p>
                            <textarea name="address_1" rows="2" class="w-full border border-[#d7dce3] rounded-[8px] px-3 py-2 text-[16px] text-[#1f2937] focus:outline-none focus:ring-2 focus:ring-[#2353f1]"><?php echo e($addressLine1); ?></textarea>
                        </div>

                        <div>
                            <p class="text-[15px] font-bold text-[#7a7f87] mb-2">State</p>
                            <input type="text" name="state" value="<?php echo e($state); ?>" class="w-full border border-[#d7dce3] rounded-[8px] px-3 py-2 text-[16px] text-[#1f2937] focus:outline-none focus:ring-2 focus:ring-[#2353f1]">
                        </div>

                        <div>
                            <p class="text-[15px] font-bold text-[#7a7f87] mb-2">City</p>
                            <input type="text" name="city" value="<?php echo e($city); ?>" class="w-full border border-[#d7dce3] rounded-[8px] px-3 py-2 text-[16px] text-[#1f2937] focus:outline-none focus:ring-2 focus:ring-[#2353f1]">
                        </div>

                        <div>
                            <p class="text-[15px] font-bold text-[#7a7f87] mb-2">District</p>
                            <input type="text" name="district" value="<?php echo e($district); ?>" class="w-full border border-[#d7dce3] rounded-[8px] px-3 py-2 text-[16px] text-[#1f2937] focus:outline-none focus:ring-2 focus:ring-[#2353f1]">
                        </div>

                        <div>
                            <p class="text-[15px] font-bold text-[#7a7f87] mb-2">Pincode</p>
                            <input type="text" name="postal_code" value="<?php echo e($pincode); ?>" class="w-full border border-[#d7dce3] rounded-[8px] px-3 py-2 text-[16px] text-[#1f2937] focus:outline-none focus:ring-2 focus:ring-[#2353f1]">
                        </div>

                        <div>
                            <p class="text-[15px] font-bold text-[#7a7f87] mb-2">Country</p>
                            <input type="text" name="country" value="<?php echo e($country); ?>" class="w-full border border-[#d7dce3] rounded-[8px] px-3 py-2 text-[16px] text-[#1f2937] focus:outline-none focus:ring-2 focus:ring-[#2353f1]">
                        </div>
                    </div>

                    <div class="mt-8 flex justify-center">
                        <button type="submit" name="profile_submit" value="1" class="inline-flex items-center justify-center rounded-[6px] bg-[#c04e32] px-10 py-3 text-white text-[15px] font-medium shadow-sm hover:opacity-95">
                            Submit
                        </button>
                    </div>
                </div>

            </form>
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