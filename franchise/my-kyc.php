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

$franchiseId   = (int)$_SESSION['franchise_id'];
$franchiseType = strtolower(trim((string)$_SESSION['franchise_type']));

if ($franchiseType === 'city') {
    $franchiseTable = 'city_franchies_holder';
    $kycTable = 'city_franchies_kyc';
} elseif ($franchiseType === 'mobile') {
    $franchiseTable = 'mobile_franchies_holder';
    $kycTable = 'mobile_franchies_kyc';
} else {
    $franchiseTable = 'franchies_holder';
    $kycTable = 'franchise_kyc_documents';
}

$conn->query("
    CREATE TABLE IF NOT EXISTS `city_franchies_kyc` (
      `id` INT(11) NOT NULL AUTO_INCREMENT,
      `franchise_code` VARCHAR(50) NOT NULL,
      `document_type` VARCHAR(100) NOT NULL,
      `document_file` VARCHAR(255) NOT NULL,
      `status` ENUM('Pending','Approved','Rejected') NOT NULL DEFAULT 'Pending',
      `uploaded_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      INDEX (`franchise_code`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
");

$conn->query("
    CREATE TABLE IF NOT EXISTS `mobile_franchies_kyc` (
      `id` INT(11) NOT NULL AUTO_INCREMENT,
      `franchise_code` VARCHAR(50) NOT NULL,
      `document_type` VARCHAR(100) NOT NULL,
      `document_file` VARCHAR(255) NOT NULL,
      `status` ENUM('Pending','Approved','Rejected') NOT NULL DEFAULT 'Pending',
      `uploaded_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      INDEX (`franchise_code`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
");

function columnExists(mysqli $conn, string $table, string $column): bool
{
    $table = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
    $column = $conn->real_escape_string($column);
    $res = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
    return $res && $res->num_rows > 0;
}

$stmt = $conn->prepare("SELECT * FROM `$franchiseTable` WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $franchiseId);
$stmt->execute();
$result = $stmt->get_result();

if (!$result || $result->num_rows === 0) {
    header("Location: logout.php");
    exit();
}

$franchise = $result->fetch_assoc();
$stmt->close();

$franchiseCode = '';

$possibleCodeColumns = [
    'mobile_franchies_id',
    'city_franchies_id',
    'franchies_id',
    'franchises_id',
    'franchise_id'
];

foreach ($possibleCodeColumns as $col) {
    if (isset($franchise[$col]) && trim((string)$franchise[$col]) !== '') {
        $franchiseCode = trim((string)$franchise[$col]);
        break;
    }
}

if ($franchiseCode === '') {
    $franchiseCode = $_SESSION['franchise_code'] ?? $_SESSION['franchise_login_id'] ?? '';
}

$_SESSION['franchise_code'] = $franchiseCode;

$message = '';
$error = '';

$uploadDir = __DIR__ . '/uploads/franchise_kyc/';
$uploadUrl = 'uploads/franchise_kyc/';

if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

$allowedTypes = ['PAN CARD', 'AADHAR CARD', 'PASSPORT', 'DRIVING LICENSE'];
$allowedExt = ['jpg', 'jpeg', 'png', 'pdf'];
$maxSize = 2 * 1024 * 1024;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $documentType = trim($_POST['document_type'] ?? '');

    if ($documentType === '' || !in_array($documentType, $allowedTypes, true)) {
        $error = "Please select valid document type.";
    } elseif (!isset($_FILES['document_file']) || $_FILES['document_file']['error'] !== UPLOAD_ERR_OK) {
        $error = "Please upload document file.";
    } elseif ($franchiseCode === '') {
        $error = "Franchise code not found. Please login again.";
    } else {
        $file = $_FILES['document_file'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if (!in_array($ext, $allowedExt, true)) {
            $error = "Only JPG, JPEG, PNG and PDF files are allowed.";
        } elseif ((int)$file['size'] > $maxSize) {
            $error = "Max file size allowed is 2 MB.";
        } else {
            $safeType = strtolower(str_replace(' ', '_', $documentType));
            $safeCode = preg_replace('/[^a-zA-Z0-9_-]/', '_', $franchiseCode);
            $newFileName = $safeType . '_' . $safeCode . '_' . time() . '.' . $ext;
            $targetPath = $uploadDir . $newFileName;

            if (move_uploaded_file($file['tmp_name'], $targetPath)) {
                $stmt = $conn->prepare("
                    INSERT INTO `$kycTable`
                    (`franchise_code`, `document_type`, `document_file`, `status`, `uploaded_at`)
                    VALUES (?, ?, ?, 'Pending', NOW())
                ");

                if ($stmt) {
                    $stmt->bind_param("sss", $franchiseCode, $documentType, $newFileName);

                    if ($stmt->execute()) {
                        $message = "KYC document uploaded successfully.";
                    } else {
                        $error = "Database insert failed: " . $stmt->error;
                    }

                    $stmt->close();
                } else {
                    $error = "Database prepare failed: " . $conn->error;
                }
            } else {
                $error = "File upload failed.";
            }
        }
    }
}

$documents = [];

$stmt = $conn->prepare("
    SELECT id, franchise_code, document_type, document_file, status, uploaded_at
    FROM `$kycTable`
    WHERE franchise_code = ?
    ORDER BY id DESC
");

if ($stmt) {
    $stmt->bind_param("s", $franchiseCode);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $documents[] = $row;
    }

    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My KYC</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
</head>

<body class="bg-[#f3f4f6] font-sans overflow-x-hidden">

<?php include __DIR__ . '/includes/sidebar.php'; ?>

<div class="ml-64 min-h-screen">
    <?php include __DIR__ . '/includes/navbar.php'; ?>

    <main class="px-5 py-5">
        <div class="max-w-[1500px] mx-auto">

            <div class="mt-5 flex items-center gap-3 mb-6">
                <div class="w-[36px] h-[36px] rounded-[8px] bg-[#f5eee3] flex items-center justify-center shrink-0">
                    <i data-lucide="badge-check" class="w-[18px] h-[18px] text-[#cd9737]"></i>
                </div>

                <h2 class="whitespace-nowrap text-[20px] font-normal text-[#40352a]" style="font-family: Georgia, 'Times New Roman', serif;">
                    MY KYC
                </h2>

                <div class="w-full h-[2px] bg-[#d1a14b]"></div>
            </div>

            <?php if ($message !== ''): ?>
                <div class="mb-5 rounded-lg border border-green-200 bg-green-50 px-5 py-3 text-green-700">
                    <?php echo e($message); ?>
                </div>
            <?php endif; ?>

            <?php if ($error !== ''): ?>
                <div class="mb-5 rounded-lg border border-red-200 bg-red-50 px-5 py-3 text-red-700">
                    <?php echo e($error); ?>
                </div>
            <?php endif; ?>

            <div class="bg-white rounded-[12px] shadow border border-gray-200 overflow-hidden mb-7">

                <div class="bg-[#103258] text-white px-7 py-5 rounded-t-[10px]">
                    <h2 class="text-[20px] font-bold">
                        Upload your Authorized Person KYC Documents
                    </h2>
                    <p class="text-[16px] mt-1">
                        Please update your valid KYC Documents
                    </p>
                </div>

                <form method="POST" enctype="multipart/form-data" class="px-7 py-6">

                    <div class="grid grid-cols-1 md:grid-cols-[200px_1fr] items-center gap-4 mb-7">
                        <label class="text-[17px] text-[#0f1f45]">
                            Select Document Type
                        </label>

                        <select name="document_type"
                                required
                                class="w-full h-[58px] rounded-[9px] border border-[#ff5b5b] px-5 text-[17px] focus:outline-none focus:ring-2 focus:ring-[#ff5b5b]">
                            <option value="">Select document type</option>
                            <option value="PAN CARD">PAN CARD</option>
                            <option value="AADHAR CARD">AADHAR CARD</option>
                            <option value="PASSPORT">PASSPORT</option>
                            <option value="DRIVING LICENSE">DRIVING LICENSE</option>
                        </select>
                    </div>

                    <div class="border-t border-dashed border-gray-300 pt-7">
                        <label class="block text-[17px] text-[#0f1f45] mb-4">
                            Upload Document :
                        </label>

                        <label class="inline-flex items-center gap-2 px-5 py-3 border border-gray-300 rounded-[8px] bg-[#f9fafb] cursor-pointer hover:bg-gray-100">
                            <i data-lucide="upload" class="w-5 h-5 text-gray-600"></i>
                            <span class="text-[16px]">Choose File</span>
                            <input type="file"
                                   name="document_file"
                                   required
                                   accept=".jpg,.jpeg,.png,.pdf"
                                   class="hidden"
                                   onchange="document.getElementById('fileName').innerText = this.files[0] ? this.files[0].name : '';">
                        </label>

                        <span id="fileName" class="ml-3 text-[15px] text-gray-600"></span>

                        <p class="mt-2 text-[16px] text-black">
                            Max. file size allowed : 2 MB | Only JPG, PNG, PDF allowed
                        </p>
                    </div>

                    <div class="border-t border-gray-300 mt-8 pt-7">
                        <button type="submit"
                                class="bg-[#103258] text-white px-9 py-3 rounded-[8px] text-[16px] font-semibold hover:opacity-90">
                            Submit
                        </button>
                    </div>

                </form>
            </div>

            <div class="bg-white rounded-[12px] shadow border border-gray-200 p-7">
                <div class="overflow-x-auto">
                    <table class="w-full border border-gray-200 rounded-[8px] overflow-hidden">
                        <thead>
                        <tr class="bg-[#103258] text-white">
                            <th class="py-4 px-4 text-center text-[16px] font-bold">Sr No</th>
                            <th class="py-4 px-4 text-center text-[16px] font-bold">Upload date</th>
                            <th class="py-4 px-4 text-center text-[16px] font-bold">Title</th>
                            <th class="py-4 px-4 text-center text-[16px] font-bold">Document</th>
                            <th class="py-4 px-4 text-center text-[16px] font-bold">Status</th>
                        </tr>
                        </thead>

                        <tbody>
                        <?php if (!empty($documents)): ?>
                            <?php $sr = 1; ?>
                            <?php foreach ($documents as $doc): ?>
                                <tr class="border-b border-gray-200">
                                    <td class="py-4 px-4 text-center text-[15px]">
                                        <?php echo $sr++; ?>
                                    </td>

                                    <td class="py-4 px-4 text-center text-[15px]">
                                        <?php echo e($doc['uploaded_at']); ?>
                                    </td>

                                    <td class="py-4 px-4 text-center text-[15px] font-semibold">
                                        <?php echo e($doc['document_type']); ?>
                                    </td>

                                    <td class="py-4 px-4 text-center text-[15px]">
                                        <a href="<?php echo e($uploadUrl . $doc['document_file']); ?>"
                                           target="_blank"
                                           class="inline-flex items-center justify-center bg-[#103258] text-white px-4 py-2 rounded-[6px] text-[14px]">
                                            View
                                        </a>
                                    </td>

                                    <td class="py-4 px-4 text-center text-[15px]">
                                        <?php
                                        $status = $doc['status'];
                                        $statusClass = 'bg-yellow-100 text-yellow-700';

                                        if ($status === 'Approved') {
                                            $statusClass = 'bg-green-100 text-green-700';
                                        } elseif ($status === 'Rejected') {
                                            $statusClass = 'bg-red-100 text-red-700';
                                        }
                                        ?>

                                        <span class="px-4 py-2 rounded-full text-[14px] font-semibold <?php echo e($statusClass); ?>">
                                            <?php echo e($status); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="py-6 px-4 text-center text-gray-500">
                                    No KYC document found.
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
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }
</script>

</body>
</html>