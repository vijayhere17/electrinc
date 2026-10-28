<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

include __DIR__ . '/config/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (
    !isset($_SESSION['customer_id']) &&
    empty($_SESSION['admin_preview'])
) {
    header("Location: login.php");
    exit();
}

if (!isset($conn) || !($conn instanceof mysqli)) {
    die("Database connection failed.");
}

$conn->set_charset("utf8mb4");

$customerId   = $_SESSION['customer_id'] ?? '';
$defaultPhoto = 'https://i.pravatar.cc/80?img=12';
$message      = '';
$error        = '';

function formatDateForDb($date) {
    $date = trim((string)$date);
    if ($date === '') return null;
    $d = DateTime::createFromFormat('Y-m-d', $date);
    if ($d && $d->format('Y-m-d') === $date) return $d->format('Y-m-d');
    $ts = strtotime($date);
    return $ts ? date('Y-m-d', $ts) : null;
}

function tableExists(mysqli $conn, string $table): bool {
    $res = $conn->query("SHOW TABLES LIKE '$table'");
    return $res && $res->num_rows > 0;
}

function getProfileByCustomerId(mysqli $conn, string $customerId): ?array {
    $stmt = $conn->prepare("SELECT * FROM member_profiles WHERE customer_id = ? LIMIT 1");
    $stmt->bind_param("s", $customerId);
    $stmt->execute();
    $data = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $data ?: null;
}

if (!tableExists($conn, 'member_profiles')) {
    die("Table member_profiles not found.");
}

$profile = getProfileByCustomerId($conn, $customerId);

$stmt = $conn->prepare("SELECT * FROM signup_users WHERE customer_id = ? LIMIT 1");
$stmt->bind_param("s", $customerId);
$stmt->execute();
$userData = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$profile) {
    $stmt = $conn->prepare("INSERT INTO member_profiles (customer_id, full_name) VALUES (?, '')");
    $stmt->bind_param("s", $customerId);
    $stmt->execute();
    $stmt->close();
    $profile = getProfileByCustomerId($conn, $customerId);
}

function isProfileLocked(array $profile): bool {
    return !empty($profile['full_name'])
        && !empty($profile['birth_date'])
        && !empty($profile['gender'])
        && !empty($profile['mobile_no'])
        && !empty($profile['state_name'])
        && !empty($profile['city_name'])
        && !empty($profile['house_number'])
        && !empty($profile['street_name'])
        && !empty($profile['district_name'])
        && !empty($profile['pincode'])
        && !empty($profile['email'])
        && !empty($profile['nominee_name'])
        && !empty($profile['nominee_relation'])
        && !empty($profile['nominee_birthdate'])
        && !empty($profile['profile_photo']);
}

$profileLocked = isProfileLocked($profile);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$profileLocked) {

    $full_name        = trim($_POST['full_name']        ?? '');
    $birth_date       = formatDateForDb($_POST['birth_date'] ?? '');
    $gender           = trim($_POST['gender']           ?? '');
    $house_number     = trim($_POST['house_number']     ?? '');
    $street_name      = trim($_POST['street_name']      ?? '');
    $state_name       = trim($_POST['state_name']       ?? '');
    $district_name    = trim($_POST['district_name']    ?? '');
    $city_name        = trim($_POST['city_name']        ?? '');
    $pincode          = trim($_POST['pincode']          ?? '');
    $email            = trim($_POST['email']            ?? '');
    $mobile_no        = trim($_POST['mobile_no']        ?? '');
    $nominee_name     = trim($_POST['nominee_name']     ?? '');
    $nominee_relation = trim($_POST['nominee_relation'] ?? '');
    $nominee_birthdate = isset($_POST['nominee_birthdate']) && $_POST['nominee_birthdate'] !== ''
        ? formatDateForDb($_POST['nominee_birthdate'])
        : ($profile['nominee_birthdate'] ?? null);

    $photoPath = $profile['profile_photo'] ?? '';

    if (!empty($_FILES['profile_photo']['name'])) {
        $allowed = ['image/jpeg','image/png','image/gif'];
        if ($_FILES['profile_photo']['size'] > 2 * 1024 * 1024) {
            $error = "Max file size is 2MB.";
        } elseif (!in_array($_FILES['profile_photo']['type'], $allowed)) {
            $error = "Invalid image format.";
        } else {
            $dir = __DIR__ . '/uploads/profile/';
            if (!is_dir($dir)) mkdir($dir, 0777, true);
            $file = time() . '_' . uniqid() . '.' . pathinfo($_FILES['profile_photo']['name'], PATHINFO_EXTENSION);
            if (move_uploaded_file($_FILES['profile_photo']['tmp_name'], $dir . $file)) {
                $photoPath = 'uploads/profile/' . $file;
            }
        }
    }

    if ($error === '') {
        if ($full_name === '')        $error = "Full name is required.";
        elseif (!$birth_date)         $error = "Birth date is required.";
        elseif ($gender === '')       $error = "Gender is required.";
        elseif ($house_number === '') $error = "House number is required.";
        elseif ($street_name === '')  $error = "Street name is required.";
        elseif ($state_name === '')   $error = "State is required.";
        elseif ($district_name === '') $error = "District is required.";
        elseif ($city_name === '')    $error = "City is required.";
        elseif ($pincode === '')      $error = "Pincode is required.";
        elseif ($email === '')        $error = "Email is required.";
        elseif ($mobile_no === '')    $error = "Mobile number is required.";
        elseif ($nominee_name === '') $error = "Nominee name is required.";
        elseif ($nominee_relation === '') $error = "Nominee relation is required.";
        elseif (!$nominee_birthdate)  $error = "Nominee birth date is required.";
        elseif ($photoPath === '')    $error = "Profile photo is required.";
    }

    if ($error === '') {
        $stmt = $conn->prepare("UPDATE member_profiles SET
            full_name=?,birth_date=?,gender=?,house_number=?,street_name=?,
            state_name=?,district_name=?,city_name=?,pincode=?,email=?,mobile_no=?,
            nominee_name=?,nominee_relation=?,nominee_birthdate=?,profile_photo=?
            WHERE customer_id=?");

        $stmt->bind_param("ssssssssssssssss",
            $full_name, $birth_date, $gender, $house_number, $street_name,
            $state_name, $district_name, $city_name, $pincode, $email, $mobile_no,
            $nominee_name, $nominee_relation, $nominee_birthdate, $photoPath, $customerId
        );

        if ($stmt->execute()) {
            $stmt2 = $conn->prepare("UPDATE signup_users SET email=?,mobile=?,state=?,city=?,district=? WHERE customer_id=?");
            $stmt2->bind_param("ssssss", $email, $mobile_no, $state_name, $city_name, $district_name, $customerId);
            $stmt2->execute();
            $stmt2->close();
            $message = "Profile updated successfully.";
        } else {
            $error = $stmt->error;
        }
        $stmt->close();
        $profile = getProfileByCustomerId($conn, $customerId);
        $profileLocked = isProfileLocked($profile);
    }
}

$memberName   = !empty($profile['full_name']) ? $profile['full_name'] : ($userData['full_name'] ?? 'Member');
$currentPhoto = $profile['profile_photo'] ?? '';
$showPhoto    = $currentPhoto ?: $defaultPhoto;

$ro       = $profileLocked ? 'readonly' : '';
$inputCls = $profileLocked
    ? 'field-input locked-field'
    : 'field-input';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { font-family: Arial, Helvetica, sans-serif; background: #f3f3f3; }

        @media (max-width: 1023px) { .page-wrap { margin-left: 0 !important; } }
        @media (max-width: 420px)  { .section-line { display: none; } }

        .field-label {
            display: flex; align-items: center; gap: 6px; margin-bottom: 7px;
            font-size: 11px; font-weight: 700; text-transform: uppercase;
            letter-spacing: 0.06em; color: #8d8d8d;
        }

        .field-input {
            width: 100%; height: 46px; border-radius: 10px;
            border: 1px solid #d8dce2; background: #eef1f4;
            padding: 0 15px; font-size: 14px; color: #2f2f2f;
            outline: none; transition: border-color 0.18s, box-shadow 0.18s, background 0.18s;
            appearance: none;
        }
        .field-input::placeholder { color: #9aa1aa; font-size: 13px; }
        .field-input:focus { border-color: #c9973a; background: #fff; box-shadow: 0 0 0 3px rgba(201,151,58,0.12); }
        select.field-input { cursor: pointer; padding-right: 40px; }

        .locked-field {
            background: #f0f4f8 !important;
            border-color: #e0dbd0 !important;
            color: #546f8f !important;
            cursor: not-allowed !important;
            pointer-events: none;
            user-select: none;
        }

        .section-head { display: flex; align-items: center; gap: 12px; margin-bottom: 18px; flex-wrap: nowrap; }
        .section-icon { width: 36px; height: 36px; border-radius: 9px; background: #f5eee3; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
        .section-title { white-space: nowrap; font-size: 18px; font-weight: 400; color: #40352a; font-family: Georgia, serif; }
        .section-line { flex: 1; height: 2px; background: #d1a14b; min-width: 24px; }

        .subsection-wrap { margin-top: 22px; padding-top: 20px; border-top: 1px dashed #dfd5c4; }
        .subsection-head { display: flex; align-items: center; gap: 10px; margin-bottom: 16px; }
        .subsection-icon { width: 32px; height: 32px; border-radius: 8px; background: #fcf7ee; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
        .subsection-title { font-size: 15px; font-weight: 700; letter-spacing: 0.04em; color: #6e5430; text-transform: uppercase; }

        .name-bar {
            min-height: 46px; width: 100%; border-radius: 10px;
            border: 1px solid #d9dde2; background: #eef0f3;
            padding: 10px 14px; display: flex; align-items: center;
            gap: 8px; font-size: 14px; color: #666; margin-bottom: 16px; flex-wrap: wrap;
        }
        .name-bar input { flex: 1; min-width: 180px; background: transparent; font-weight: 600; color: #3d3d3d; outline: none; font-size: 14px; }
        .name-bar.locked-field input { color: #546f8f; cursor: not-allowed; pointer-events: none; }

        .photo-row {
            display: flex; align-items: center; gap: 16px; flex-wrap: wrap;
            margin-bottom: 22px; padding: 14px;
            border: 1px solid #eee4d3; background: #fcfbf8; border-radius: 14px;
        }
        .photo-meta { flex: 1; min-width: 180px; }
        .file-locked { pointer-events: none; opacity: .55; }
    </style>
</head>
<body>

<?php include __DIR__ . '/includes/sidebar.php'; ?>

<div class="page-wrap ml-0 lg:ml-64 min-h-screen">
    <?php include __DIR__ . '/includes/navbar.php'; ?>

    <div class="px-3 sm:px-4 md:px-6 py-4 sm:py-5 max-w-5xl mx-auto lg:mx-0 lg:max-w-none">

        <?php if ($message): ?>
        <div class="mb-4 flex items-start gap-3 rounded-lg bg-green-50 border border-green-200 px-4 py-3 text-green-700 text-sm font-semibold">
            <svg class="w-5 h-5 flex-shrink-0 mt-0.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
            <span><?= htmlspecialchars($message) ?></span>
        </div>
        <?php endif; ?>

        <?php if ($error): ?>
        <div class="mb-4 flex items-start gap-3 rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-red-700 text-sm font-semibold">
            <svg class="w-5 h-5 flex-shrink-0 mt-0.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            <span><?= htmlspecialchars($error) ?></span>
        </div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data">

            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 sm:p-5 md:p-6 mb-5">
                <div class="section-head">
                    <div class="section-icon">
                        <i data-lucide="badge-check" class="w-[18px] h-[18px] text-[#C89741]"></i>
                    </div>
                    <h2 class="section-title">PERSONAL DETAILS</h2>
                    <div class="section-line"></div>
                </div>

                <div class="name-bar <?= $profileLocked ? 'locked-field' : '' ?>">
                    <span class="flex-shrink-0 text-gray-400 text-sm font-medium">My Name:</span>
                    <input type="text" name="full_name"
                        value="<?= htmlspecialchars($memberName) ?>"
                        placeholder="Enter your full name"
                        <?= $ro ?>>
                </div>

                <div class="photo-row">
                    <div class="relative w-[68px] h-[68px] rounded-[12px] border-2 border-dashed border-[#d1a14b] bg-[#efe0b9] flex-shrink-0 overflow-hidden">
                        <img src="<?= htmlspecialchars($showPhoto) ?>" alt="Profile" class="w-full h-full object-cover" id="photoPreview">
                        <?php if (!$profileLocked): ?>
                        <div class="absolute -right-2 -bottom-2 w-[24px] h-[24px] rounded-full bg-[#c9973a] flex items-center justify-center border-2 border-white">
                            <i data-lucide="camera" class="w-[11px] h-[11px] text-white"></i>
                        </div>
                        <?php endif; ?>
                    </div>

                    <div class="photo-meta">
                        <p class="text-[13px] font-semibold text-gray-700 mb-2">Profile Photo</p>
                        <label class="h-[38px] px-4 rounded-[9px] border border-[#d9dde2] bg-[#f8f8f8] inline-flex items-center gap-2 text-[13px] font-semibold text-gray-700 <?= $profileLocked ? 'file-locked cursor-not-allowed' : 'cursor-pointer hover:bg-gray-100' ?> transition">
                            <i data-lucide="upload" class="w-[14px] h-[14px]"></i>
                            <span>Choose File</span>
                            <input type="file" name="profile_photo" class="hidden" accept=".jpg,.jpeg,.png,.gif" id="photoInput" <?= $profileLocked ? 'disabled' : '' ?>>
                        </label>
                        <p class="text-[12px] text-gray-400 mt-2">Upload JPG, PNG or GIF image up to 2MB.</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="field-label">
                            <i data-lucide="calendar" class="w-[13px] h-[13px] text-[#c9973a]"></i>
                            Birth Date
                        </label>
                        <input type="date" name="birth_date"
                            class="<?= $inputCls ?>"
                            value="<?= !empty($profile['birth_date']) ? date('Y-m-d', strtotime($profile['birth_date'])) : '' ?>"
                            <?= $ro ?>>
                    </div>

                    <div>
                        <label class="field-label">
                            <i data-lucide="user" class="w-[13px] h-[13px] text-[#c9973a]"></i>
                            Gender
                        </label>
                        <?php if ($profileLocked): ?>
                        <input type="text" class="<?= $inputCls ?>" value="<?= htmlspecialchars($profile['gender'] ?? '') ?>" readonly>
                        <?php else: ?>
                        <select name="gender" class="field-input">
                            <option value="">Select gender</option>
                            <option value="Male"   <?= ($profile['gender'] ?? '') === 'Male'   ? 'selected' : '' ?>>Male</option>
                            <option value="Female" <?= ($profile['gender'] ?? '') === 'Female' ? 'selected' : '' ?>>Female</option>
                            <option value="Other"  <?= ($profile['gender'] ?? '') === 'Other'  ? 'selected' : '' ?>>Other</option>
                        </select>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="subsection-wrap">
                    <div class="subsection-head">
                        <div class="subsection-icon">
                            <i data-lucide="heart-handshake" class="w-[16px] h-[16px] text-[#cd9737]"></i>
                        </div>
                        <h3 class="subsection-title">Nominee Details</h3>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div class="sm:col-span-2">
                            <label class="field-label">
                                <i data-lucide="user-round" class="w-[13px] h-[13px] text-[#c9973a]"></i>
                                Nominee Name
                            </label>
                            <input type="text" name="nominee_name"
                                class="<?= $inputCls ?>"
                                placeholder="Enter nominee full name"
                                value="<?= htmlspecialchars($profile['nominee_name'] ?? '') ?>"
                                <?= $ro ?>>
                        </div>

                        <div>
                            <label class="field-label">
                                <i data-lucide="users" class="w-[13px] h-[13px] text-[#c9973a]"></i>
                                Relation
                            </label>
                            <input type="text" name="nominee_relation"
                                class="<?= $inputCls ?>"
                                placeholder="Example: Father, Mother, Wife, Brother"
                                value="<?= htmlspecialchars($profile['nominee_relation'] ?? '') ?>"
                                <?= $ro ?>>
                        </div>

                        <div>
                            <label class="field-label">
                                <i data-lucide="calendar-heart" class="w-[13px] h-[13px] text-[#c9973a]"></i>
                                Nominee Birth Date
                            </label>
                            <input type="date" name="nominee_birthdate"
                                class="<?= $inputCls ?>"
                                value="<?= !empty($profile['nominee_birthdate']) ? date('Y-m-d', strtotime($profile['nominee_birthdate'])) : '' ?>"
                                <?= $ro ?>>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 sm:p-5 md:p-6 mb-5">
                <div class="section-head">
                    <div class="section-icon">
                        <i data-lucide="map-pin" class="w-[18px] h-[18px] text-[#cd9737]"></i>
                    </div>
                    <h2 class="section-title">ADDRESS & COMMUNICATION</h2>
                    <div class="section-line"></div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="field-label">House Number</label>
                        <input type="text" name="house_number"
                            class="<?= $inputCls ?>"
                            placeholder="Enter house number"
                            value="<?= htmlspecialchars($profile['house_number'] ?? '') ?>"
                            <?= $ro ?>>
                    </div>

                    <div>
                        <label class="field-label">Street Name</label>
                        <input type="text" name="street_name"
                            class="<?= $inputCls ?>"
                            placeholder="Enter street or society name"
                            value="<?= htmlspecialchars($profile['street_name'] ?? '') ?>"
                            <?= $ro ?>>
                    </div>

                    <div>
                        <label class="field-label">State</label>
                        <input type="text" name="state_name"
                            class="<?= $inputCls ?>"
                            placeholder="Enter state name"
                            value="<?= htmlspecialchars(!empty($profile['state_name']) ? $profile['state_name'] : ($userData['state'] ?? '')) ?>"
                            <?= $ro ?>>
                    </div>

                    <div>
                        <label class="field-label">District</label>
                        <input type="text" name="district_name"
                            class="<?= $inputCls ?>"
                            placeholder="Enter district name"
                            value="<?= htmlspecialchars($profile['district_name'] ?? '') ?>"
                            <?= $ro ?>>
                    </div>

                    <div>
                        <label class="field-label">City</label>
                        <input type="text" name="city_name"
                            class="<?= $inputCls ?>"
                            placeholder="Enter city name"
                            value="<?= htmlspecialchars(!empty($profile['city_name']) ? $profile['city_name'] : ($userData['city'] ?? '')) ?>"
                            <?= $ro ?>>
                    </div>

                    <div>
                        <label class="field-label">Pincode</label>
                        <input type="text" name="pincode"
                            class="<?= $inputCls ?>"
                            placeholder="Enter 6-digit pincode"
                            value="<?= htmlspecialchars($profile['pincode'] ?? '') ?>"
                            inputmode="numeric" maxlength="6"
                            <?= $profileLocked ? 'readonly' : 'oninput="this.value=this.value.replace(/\D/g,\'\').slice(0,6)"' ?>>
                    </div>

                    <div>
                        <label class="field-label">Email ID</label>
                        <input type="email" name="email"
                            class="<?= $inputCls ?>"
                            placeholder="Enter email address"
                            value="<?= htmlspecialchars(!empty($profile['email']) ? $profile['email'] : ($userData['email'] ?? '')) ?>"
                            <?= $ro ?>>
                    </div>

                    <div>
                        <label class="field-label">Mobile No.</label>
                        <input type="text" name="mobile_no"
                            class="<?= $inputCls ?>"
                            placeholder="Enter 10-digit mobile number"
                            value="<?= htmlspecialchars(!empty($profile['mobile_no']) ? $profile['mobile_no'] : ($userData['mobile'] ?? '')) ?>"
                            inputmode="numeric" maxlength="10"
                            <?= $profileLocked ? 'readonly' : 'oninput="this.value=this.value.replace(/\D/g,\'\').slice(0,10)"' ?>>
                    </div>
                </div>
            </div>

            <?php if (!$profileLocked): ?>
            <div class="flex justify-center pb-8">
                <button type="submit"
                    class="w-full sm:w-auto min-w-[180px] h-[46px] rounded-[10px] bg-[#9A7133] hover:bg-[#a77628] text-white text-[15px] font-semibold transition shadow-md px-8">
                    Update Profile
                </button>
            </div>
            <?php endif; ?>

        </form>
    </div>
</div>

<?php if ($profileLocked): ?>
<script>
document.querySelector('form').addEventListener('submit', function(e) { e.preventDefault(); });
</script>
<?php endif; ?>

<script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    lucide.createIcons();
    var photoInput   = document.getElementById('photoInput');
    var photoPreview = document.getElementById('photoPreview');
    if (photoInput && photoPreview) {
        photoInput.addEventListener('change', function () {
            if (this.files && this.files[0]) {
                var reader = new FileReader();
                reader.onload = function(e) { photoPreview.src = e.target.result; };
                reader.readAsDataURL(this.files[0]);
            }
        });
    }
});
</script>

</body>
</html>