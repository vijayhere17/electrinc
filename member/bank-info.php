<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config/db.php';

if (!isset($_SESSION['customer_id']) && !isset($_SESSION['member_id'])) {
    header("Location: login.php");
    exit();
}

if (!isset($conn) || !($conn instanceof mysqli)) {
    die("Database connection failed.");
}

$conn->set_charset("utf8mb4");

$customerId = $_SESSION['customer_id'] ?? $_SESSION['member_id'];
$message = "";
$error = "";

/* ─── Helper: check / ensure column ────────────────────────────────────── */
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

/* ─── Create / migrate table ────────────────────────────────────────────── */
$conn->query("
    CREATE TABLE IF NOT EXISTS member_bank_kyc_details (
        id INT AUTO_INCREMENT PRIMARY KEY,
        customer_id VARCHAR(50) NOT NULL UNIQUE,
        account_beneficiary_name VARCHAR(150) NULL,
        account_no VARCHAR(50) NULL,
        ifsc_code VARCHAR(50) NULL,
        bank_name VARCHAR(150) NULL,
        aadhar_number VARCHAR(20) NULL,
        aadhar_photo TEXT NULL,
        pan_number VARCHAR(20) NULL,
        pan_photo TEXT NULL,
        is_bank_locked TINYINT(1) DEFAULT 0,
        kyc_status ENUM('Pending','Approved','Rejected') NOT NULL DEFAULT 'Pending',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )
");

ensureColumn($conn, 'member_bank_kyc_details', 'account_beneficiary_name', "VARCHAR(150) NULL");
ensureColumn($conn, 'member_bank_kyc_details', 'account_no',               "VARCHAR(50) NULL");
ensureColumn($conn, 'member_bank_kyc_details', 'ifsc_code',                "VARCHAR(50) NULL");
ensureColumn($conn, 'member_bank_kyc_details', 'bank_name',                "VARCHAR(150) NULL");
ensureColumn($conn, 'member_bank_kyc_details', 'aadhar_number',            "VARCHAR(20) NULL");
ensureColumn($conn, 'member_bank_kyc_details', 'aadhar_photo',             "TEXT NULL");
ensureColumn($conn, 'member_bank_kyc_details', 'pan_number',               "VARCHAR(20) NULL");
ensureColumn($conn, 'member_bank_kyc_details', 'pan_photo',                "TEXT NULL");
ensureColumn($conn, 'member_bank_kyc_details', 'is_bank_locked',           "TINYINT(1) DEFAULT 0");
ensureColumn($conn, 'member_bank_kyc_details', 'kyc_status',               "ENUM('Pending','Approved','Rejected') NOT NULL DEFAULT 'Pending'");

/* ─── Upload helpers ────────────────────────────────────────────────────── */
$uploadDir = __DIR__ . '/uploads/kyc/';
$uploadUrl = 'uploads/kyc/';

if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

function cleanFileName($name) {
    return preg_replace('/[^A-Za-z0-9_\.-]/', '_', $name);
}

function uploadAadharFiles($files, $customerId, $uploadDir, $uploadUrl, &$error) {
    $uploaded = [];
    if (!isset($files['name']) || !is_array($files['name'])) return $uploaded;

    $allowed = ['jpg', 'jpeg', 'png', 'webp', 'pdf'];

    for ($i = 0; $i < count($files['name']); $i++) {
        if ($files['error'][$i] === UPLOAD_ERR_NO_FILE) continue;
        if ($files['error'][$i] !== UPLOAD_ERR_OK) { $error = "Aadhar file upload error."; continue; }

        $ext = strtolower(pathinfo($files['name'][$i], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed, true)) { $error = "Only JPG, JPEG, PNG, WEBP and PDF files allowed."; continue; }

        $fileName   = cleanFileName('aadhar_' . $customerId . '_' . time() . '_' . ($i + 1) . '.' . $ext);
        $targetPath = $uploadDir . $fileName;

        if (move_uploaded_file($files['tmp_name'][$i], $targetPath)) {
            $uploaded[] = $uploadUrl . $fileName;
        } else {
            $error = "Aadhar photo not uploaded. Check uploads/kyc folder permission.";
        }
    }
    return $uploaded;
}

function uploadPanFile($file, $customerId, $uploadDir, $uploadUrl, &$error) {
    if (!isset($file) || $file['error'] === UPLOAD_ERR_NO_FILE) return "";
    if ($file['error'] !== UPLOAD_ERR_OK) { $error = "PAN file upload error."; return ""; }

    $allowed = ['jpg', 'jpeg', 'png', 'webp', 'pdf'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed, true)) { $error = "Only JPG, JPEG, PNG, WEBP and PDF files allowed."; return ""; }

    $fileName   = cleanFileName('pan_' . $customerId . '_' . time() . '.' . $ext);
    $targetPath = $uploadDir . $fileName;

    if (move_uploaded_file($file['tmp_name'], $targetPath)) return $uploadUrl . $fileName;
    $error = "PAN photo not uploaded. Check uploads/kyc folder permission.";
    return "";
}

/* ─── Fetch existing record ─────────────────────────────────────────────── */
$stmt = $conn->prepare("SELECT * FROM member_bank_kyc_details WHERE customer_id = ? LIMIT 1");
$stmt->bind_param("s", $customerId);
$stmt->execute();
$oldData = $stmt->get_result()->fetch_assoc();
$stmt->close();

/* ─── Current KYC status ────────────────────────────────────────────────── */
$kycStatus   = $oldData['kyc_status'] ?? 'Pending';   // 'Pending' | 'Approved' | 'Rejected'
$isApproved  = ($kycStatus === 'Approved');
$isRejected  = ($kycStatus === 'Rejected');

/* ─── Handle POST (only allowed when NOT approved) ─────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$isApproved) {

    $accountBeneficiaryName = trim($_POST['account_beneficiary_name'] ?? '');
    $accountNo              = trim($_POST['account_no']               ?? '');
    $ifscCode               = strtoupper(trim($_POST['ifsc_code']     ?? ''));
    $bankName               = trim($_POST['bank_name']                ?? '');
    $aadharNumber           = trim($_POST['aadhar_number']            ?? '');
    $panNumber              = strtoupper(trim($_POST['pan_number']    ?? ''));

    $oldAadharPhoto = $oldData['aadhar_photo'] ?? '';
    $oldPanPhoto    = $oldData['pan_photo']    ?? '';

    $uploadedAadharFiles = uploadAadharFiles($_FILES['aadhar_photo'] ?? [], $customerId, $uploadDir, $uploadUrl, $error);
    $uploadedPanFile     = uploadPanFile($_FILES['pan_photo'] ?? null, $customerId, $uploadDir, $uploadUrl, $error);

    $finalAadharPhoto = !empty($uploadedAadharFiles) ? json_encode($uploadedAadharFiles) : $oldAadharPhoto;
    $finalPanPhoto    = ($uploadedPanFile !== '')     ? $uploadedPanFile                 : $oldPanPhoto;

    if ($aadharNumber === '' || $panNumber === '') {
        $error = "Aadhar Number and PAN Number are required.";
    }

    if ($error === "") {
        /*
         * When member re-submits after Rejected → reset status back to Pending
         * so admin can review again.
         */
        $newStatus = $isRejected ? 'Pending' : $kycStatus;

        $stmt = $conn->prepare("
            INSERT INTO member_bank_kyc_details
                (customer_id, account_beneficiary_name, account_no, ifsc_code, bank_name,
                 aadhar_number, aadhar_photo, pan_number, pan_photo, is_bank_locked, kyc_status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?)
            ON DUPLICATE KEY UPDATE
                account_beneficiary_name = VALUES(account_beneficiary_name),
                account_no               = VALUES(account_no),
                ifsc_code                = VALUES(ifsc_code),
                bank_name                = VALUES(bank_name),
                aadhar_number            = VALUES(aadhar_number),
                aadhar_photo             = VALUES(aadhar_photo),
                pan_number               = VALUES(pan_number),
                pan_photo                = VALUES(pan_photo),
                is_bank_locked           = 1,
                kyc_status               = VALUES(kyc_status),
                updated_at               = CURRENT_TIMESTAMP
        ");

        if (!$stmt) {
            $error = "SQL Error: " . $conn->error;
        } else {
            $stmt->bind_param("ssssssssss",
                $customerId, $accountBeneficiaryName, $accountNo, $ifscCode, $bankName,
                $aadharNumber, $finalAadharPhoto, $panNumber, $finalPanPhoto, $newStatus
            );

            if ($stmt->execute()) {
                $message = "KYC updated successfully. Waiting for admin approval.";
            } else {
                $error = "Data not saved: " . $stmt->error;
            }
            $stmt->close();
        }
    }

    /* Refresh record */
    $stmt = $conn->prepare("SELECT * FROM member_bank_kyc_details WHERE customer_id = ? LIMIT 1");
    $stmt->bind_param("s", $customerId);
    $stmt->execute();
    $oldData   = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $kycStatus  = $oldData['kyc_status'] ?? 'Pending';
    $isApproved = ($kycStatus === 'Approved');
    $isRejected = ($kycStatus === 'Rejected');
}

/* ─── Values for rendering ──────────────────────────────────────────────── */
$accountBeneficiaryName = $oldData['account_beneficiary_name'] ?? '';
$accountNo              = $oldData['account_no']               ?? '';
$ifscCode               = $oldData['ifsc_code']                ?? '';
$bankName               = $oldData['bank_name']                ?? '';
$aadharNumber           = $oldData['aadhar_number']            ?? '';
$panNumber              = $oldData['pan_number']               ?? '';

$aadharFiles = [];
if (!empty($oldData['aadhar_photo'])) {
    $decoded = json_decode($oldData['aadhar_photo'], true);
    $aadharFiles = is_array($decoded) ? $decoded : [$oldData['aadhar_photo']];
}
$panFile = $oldData['pan_photo'] ?? '';

/* Readonly attribute string — applied to all inputs when approved */
$ro  = $isApproved ? 'readonly'  : '';
$dis = $isApproved ? 'disabled'  : '';
$inputCls = $isApproved
    ? 'w-full bg-[#f0f4f8] border-0 border-b border-gray-200 outline-none py-3 text-[#546f8f] text-[15px] sm:text-[17px] font-semibold cursor-not-allowed select-none placeholder:text-gray-400 placeholder:font-normal'
    : 'w-full bg-transparent border-0 border-b border-gray-300 outline-none py-3 text-[#546f8f] text-[15px] sm:text-[17px] font-semibold placeholder:text-gray-400 placeholder:font-normal';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bank Info</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        /* Lock overlay on file inputs when approved */
        .file-locked { pointer-events:none; opacity:.55; }
    </style>
</head>
<body class="bg-[#f3f4f6] font-sans">

<?php include __DIR__ . '/includes/sidebar.php'; ?>

<div class="lg:ml-64 min-h-screen">
    <?php include __DIR__ . '/includes/navbar.php'; ?>

    <div class="p-4 sm:p-7">

        <!-- ── KYC STATUS BANNER ─────────────────────────────────────── -->
        <?php if ($isApproved): ?>
        <div class="mb-5 flex items-start sm:items-center gap-3 bg-green-50 border border-green-300 text-green-800 px-4 sm:px-5 py-4 rounded-xl shadow-sm">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 text-green-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <div>
                <p class="font-bold text-[15px] sm:text-[16px]">KYC Approved ✓</p>
                <p class="text-[13px] sm:text-[14px] text-green-700 mt-0.5">Your KYC has been verified and approved by the admin. Details are now locked and cannot be changed.</p>
            </div>
        </div>

        <?php elseif ($isRejected): ?>
        <div class="mb-5 flex items-start sm:items-center gap-3 bg-red-50 border border-red-300 text-red-800 px-4 sm:px-5 py-4 rounded-xl shadow-sm">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 text-red-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <div>
                <p class="font-bold text-[15px] sm:text-[16px]">KYC Rejected ✗</p>
                <p class="text-[13px] sm:text-[14px] text-red-700 mt-0.5">Your KYC was rejected by the admin. Please correct your details and re-submit for review.</p>
            </div>
        </div>

        <?php elseif ($kycStatus === 'Pending' && !empty($oldData)): ?>
        <div class="mb-5 flex items-start sm:items-center gap-3 bg-yellow-50 border border-yellow-300 text-yellow-800 px-4 sm:px-5 py-4 rounded-xl shadow-sm">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 text-yellow-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <div>
                <p class="font-bold text-[15px] sm:text-[16px]">KYC Pending Review</p>
                <p class="text-[13px] sm:text-[14px] text-yellow-700 mt-0.5">Your KYC details have been submitted and are awaiting admin approval.</p>
            </div>
        </div>
        <?php endif; ?>

        <!-- ── Success / Error messages ──────────────────────────────── -->
        <?php if ($message !== ""): ?>
            <div class="mb-4 bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <?php if ($error !== ""): ?>
            <div class="mb-4 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <!-- ── FORM ──────────────────────────────────────────────────── -->
        <form method="POST" enctype="multipart/form-data" class="bg-[#f7f7f7] rounded-md p-4 sm:p-7">

            <div class="flex flex-wrap items-center gap-3 sm:gap-6 mb-8 sm:mb-10">
                <div class="w-14 h-14 rounded-xl bg-[#f4eadc] flex items-center justify-center text-[#d1972e] shrink-0">
                    <i data-lucide="badge-check" class="w-6 h-6"></i>
                </div>
                <h1 class="whitespace-nowrap text-[18px] sm:text-[20px] font-normal text-[#000000CC]"
                    style="font-family: Georgia, 'Times New Roman', serif;">KYC DETAILS</h1>
                <div class="hidden sm:block h-[1px] bg-[#c99435] flex-1"></div>

                <!-- KYC Status badge in header -->
                <?php
                $badgeCls = match($kycStatus) {
                    'Approved' => 'bg-green-100 text-green-700 border border-green-300',
                    'Rejected' => 'bg-red-100 text-red-700 border border-red-300',
                    default    => 'bg-yellow-100 text-yellow-700 border border-yellow-300',
                };
                ?>
                <span class="shrink-0 ml-auto sm:ml-0 px-4 py-1 rounded-full text-[12px] sm:text-[13px] font-semibold <?php echo $badgeCls; ?>">
                    <?php echo htmlspecialchars($kycStatus); ?>
                </span>
            </div>

            <div class="space-y-6 sm:space-y-10">

                <!-- Account Beneficiary Name -->
                <div class="grid grid-cols-1 sm:grid-cols-[350px_1fr] gap-1.5 sm:gap-6 items-start sm:items-center">
                    <label class="text-[#2f4d6b] text-[15px] sm:text-[18px] font-semibold">Account Beneficiary Name</label>
                    <input type="text" name="account_beneficiary_name"
                           value="<?php echo htmlspecialchars($accountBeneficiaryName); ?>"
                           placeholder="Enter Name as per Bank Passbook"
                           <?php echo $ro; ?>
                           class="<?php echo $inputCls; ?>">
                </div>

                <!-- Account No -->
                <div class="grid grid-cols-1 sm:grid-cols-[350px_1fr] gap-1.5 sm:gap-6 items-start sm:items-center">
                    <label class="text-[#2f4d6b] text-[15px] sm:text-[18px] font-semibold">Account No</label>
                    <input type="<?php echo $isApproved ? 'password' : 'text'; ?>"
                           name="account_no"
                           value="<?php echo htmlspecialchars($accountNo); ?>"
                           placeholder="Enter your Bank Account Number"
                           <?php echo $ro; ?>
                           class="<?php echo $inputCls; ?>">
                </div>

                <!-- IFSC Code -->
                <div class="grid grid-cols-1 sm:grid-cols-[350px_1fr] gap-1.5 sm:gap-6 items-start sm:items-center">
                    <label class="text-[#2f4d6b] text-[15px] sm:text-[18px] font-semibold">IFSC Code</label>
                    <input type="text" name="ifsc_code"
                           value="<?php echo htmlspecialchars($ifscCode); ?>"
                           placeholder="e.g. SBIN0001234"
                           <?php echo $ro; ?>
                           class="<?php echo $inputCls; ?>">
                </div>

                <!-- Bank Name -->
                <div class="grid grid-cols-1 sm:grid-cols-[350px_1fr] gap-1.5 sm:gap-6 items-start sm:items-center">
                    <label class="text-[#2f4d6b] text-[15px] sm:text-[18px] font-semibold">Bank Name</label>
                    <input type="text" name="bank_name"
                           value="<?php echo htmlspecialchars($bankName); ?>"
                           placeholder="e.g. State Bank of India, Surat Branch"
                           <?php echo $ro; ?>
                           class="<?php echo $inputCls; ?>">
                </div>

                <!-- Aadhar Number -->
                <div class="grid grid-cols-1 sm:grid-cols-[350px_1fr] gap-1.5 sm:gap-6 items-start sm:items-center">
                    <label class="text-[#2f4d6b] text-[15px] sm:text-[18px] font-semibold">
                        Aadhar Number<span class="text-red-500">*</span>
                    </label>
                    <input type="text" name="aadhar_number"
                           value="<?php echo htmlspecialchars($aadharNumber); ?>"
                           placeholder="Enter 12-digit Aadhaar number"
                           <?php echo $ro; ?>
                           <?php echo (!$isApproved ? 'required' : ''); ?>
                           class="<?php echo $inputCls; ?>">
                </div>

                <!-- Aadhar Upload -->
                <div class="grid grid-cols-1 sm:grid-cols-[350px_1fr] gap-1.5 sm:gap-6 items-start">
                    <label class="text-[#2f4d6b] text-[15px] sm:text-[18px] font-semibold leading-normal sm:leading-9">
                        Upload Aadhaar Card Front And Back<br class="hidden sm:inline"> Page Photo<span class="text-red-500">*</span>
                    </label>
                    <div>
                        <?php if (!$isApproved): ?>
                        <input type="file" name="aadhar_photo[]" multiple
                               accept=".jpg,.jpeg,.png,.webp,.pdf"
                               class="w-full border border-gray-200 rounded-lg p-3 text-[#8a96a8] bg-[#f7f7fb] text-sm">
                        <p class="text-[#70829b] text-sm mt-3">
                            Select 2 files together: Aadhaar front and Aadhaar back.
                        </p>
                        <?php else: ?>
                        <p class="text-[#70829b] text-sm italic">File upload is disabled after KYC approval.</p>
                        <?php endif; ?>

                        <?php foreach ($aadharFiles as $index => $file): ?>
                            <?php if ($file !== ''): ?>
                                <a href="<?php echo htmlspecialchars($file); ?>" target="_blank"
                                   class="inline-flex items-center gap-1 mt-3 text-[#b97816] underline text-sm">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                    View uploaded file <?php echo $index + 1; ?>
                                </a>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- PAN Number -->
                <div class="grid grid-cols-1 sm:grid-cols-[350px_1fr] gap-1.5 sm:gap-6 items-start sm:items-center">
                    <label class="text-[#2f4d6b] text-[15px] sm:text-[18px] font-semibold">
                        PAN Number<span class="text-red-500">*</span>
                    </label>
                    <input type="text" name="pan_number"
                           value="<?php echo htmlspecialchars($panNumber); ?>"
                           placeholder="e.g. ABCDE1234F"
                           <?php echo $ro; ?>
                           <?php echo (!$isApproved ? 'required' : ''); ?>
                           class="<?php echo $inputCls; ?>">
                </div>

                <!-- PAN Upload -->
                <div class="grid grid-cols-1 sm:grid-cols-[350px_1fr] gap-1.5 sm:gap-6 items-start">
                    <label class="text-[#2f4d6b] text-[15px] sm:text-[18px] font-semibold leading-normal sm:leading-9">
                        Upload PAN Card Front Page Photo<span class="text-red-500">*</span>
                    </label>
                    <div>
                        <?php if (!$isApproved): ?>
                        <input type="file" name="pan_photo"
                               accept=".jpg,.jpeg,.png,.webp,.pdf"
                               class="w-full border border-gray-200 rounded-lg p-3 text-[#8a96a8] bg-[#f7f7fb] text-sm">
                        <?php else: ?>
                        <p class="text-[#70829b] text-sm italic">File upload is disabled after KYC approval.</p>
                        <?php endif; ?>

                        <?php if ($panFile !== ''): ?>
                            <a href="<?php echo htmlspecialchars($panFile); ?>" target="_blank"
                               class="inline-flex items-center gap-1 mt-3 text-[#b97816] underline text-sm">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                View uploaded file
                            </a>
                        <?php endif; ?>
                    </div>
                </div>

            </div><!-- /space-y -->

            <!-- ── Submit button (hidden when approved) ───────────────── -->
            <div class="flex justify-center mt-8 sm:mt-12">
                <?php if ($isApproved): ?>
                    <!-- Approved: show lock message instead of button -->
                    <div class="flex items-center justify-center gap-2 text-green-700 bg-green-50 border border-green-200 px-5 sm:px-8 py-4 rounded-xl w-full sm:w-auto text-center">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                        </svg>
                        <span class="font-semibold text-[14px] sm:text-[16px]">KYC is Approved &amp; Locked — No changes allowed</span>
                    </div>
                <?php else: ?>
                    <button type="submit"
                            class="w-full sm:w-auto bg-[#cf952e] hover:bg-[#b8821f] transition-colors text-white text-[16px] sm:text-[18px] font-semibold px-8 sm:px-14 py-4 sm:py-5 rounded-xl">
                        <?php echo $isRejected ? 'Re-Submit KYC' : 'Update KYC'; ?>
                    </button>
                <?php endif; ?>
            </div>

        </form>
    </div>
</div>

<?php if ($isApproved): ?>
<!-- Extra safety: block form submission via JS as well -->
<script>
document.querySelector('form').addEventListener('submit', function(e) {
    e.preventDefault();
    alert('KYC is already approved. You cannot make changes.');
});
</script>
<?php endif; ?>

<script>
if (typeof lucide !== 'undefined') lucide.createIcons();
</script>
</body>
</html>