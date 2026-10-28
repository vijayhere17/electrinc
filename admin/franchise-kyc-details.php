<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

ini_set('display_errors', 1);
error_reporting(E_ALL);

$dbPaths = [
    __DIR__ . '/config/db.php',
    __DIR__ . '/../config/db.php',
    dirname(__DIR__) . '/config/db.php'
];

$dbLoaded = false;

foreach ($dbPaths as $path) {
    if (file_exists($path)) {
        require_once $path;
        $dbLoaded = true;
        break;
    }
}

if (!$dbLoaded || !isset($conn) || !($conn instanceof mysqli)) {
    die("Database connection file not found.");
}

if (!function_exists('e')) {
    function e($value)
    {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

function tableHasColumn($conn, $table, $column) {
    $table = $conn->real_escape_string($table);
    $column = $conn->real_escape_string($column);
    $result = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
    return ($result && $result->num_rows > 0);
}

function tableExists($conn, $table) {
    $table = $conn->real_escape_string($table);
    $result = $conn->query("SHOW TABLES LIKE '$table'");
    return ($result && $result->num_rows > 0);
}

function findKycFile($dbFile) {
    $dbFile = trim((string)$dbFile);
    if ($dbFile === '') return '';

    $dbFile = str_replace('\\', '/', $dbFile);
    $base = basename($dbFile);
    $nameOnly = pathinfo($base, PATHINFO_FILENAME);

    $roots = [
        __DIR__,
        dirname(__DIR__),
        $_SERVER['DOCUMENT_ROOT'] ?? '',
        ($_SERVER['DOCUMENT_ROOT'] ?? '') . '/freshiya',
        ($_SERVER['DOCUMENT_ROOT'] ?? '') . '/freshiya_main',
        ($_SERVER['DOCUMENT_ROOT'] ?? '') . '/freshya_member',
    ];

    $folders = [
        '',
        'uploads',
        'uploads/kyc',
        'uploads/franchise_kyc',
        'uploads/franchies_kyc',
        'admin/uploads',
        'admin/uploads/kyc',
        'admin/uploads/franchise_kyc',
        'admin/uploads/franchies_kyc',
        'franchise/uploads',
        'franchise/uploads/kyc',
        'franchise/uploads/franchise_kyc',
        'franchies/uploads',
        'franchies/uploads/kyc',
        'franchies/uploads/franchise_kyc',
        'member/uploads',
        'member/uploads/kyc'
    ];

    foreach ($roots as $root) {
        if ($root === '') continue;

        foreach ($folders as $folder) {
            $dir = rtrim($root . '/' . $folder, '/');

            $try = $dir . '/' . $base;
            if (is_file($try)) return realpath($try);

            foreach (glob($dir . '/' . $nameOnly . '.*') ?: [] as $match) {
                if (is_file($match)) return realpath($match);
            }
        }
    }

    foreach ($roots as $root) {
        if ($root === '' || !is_dir($root)) continue;

        try {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
            );

            foreach ($iterator as $file) {
                if (!$file->isFile()) continue;

                $fileBase = $file->getBasename();
                $fileNameOnly = pathinfo($fileBase, PATHINFO_FILENAME);

                if (strcasecmp($fileBase, $base) === 0 || strcasecmp($fileNameOnly, $nameOnly) === 0) {
                    return $file->getRealPath();
                }
            }
        } catch (Exception $e) {}
    }

    return '';
}

$conn->query("
    CREATE TABLE IF NOT EXISTS `franchise_kyc_documents` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `franchise_code` VARCHAR(50) NOT NULL,
        `document_type` VARCHAR(100) NOT NULL,
        `document_file` VARCHAR(255) NOT NULL,
        `status` ENUM('Pending','Approved','Rejected') NOT NULL DEFAULT 'Pending',
        `uploaded_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        INDEX (`franchise_code`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

if (isset($_GET['view_doc'])) {

    $id = (int)$_GET['view_doc'];
    $source = $_GET['source'] ?? 'state';

   if ($source === 'city') {

    $stmt = $conn->prepare("
        SELECT document_file
        FROM city_franchies_kyc
        WHERE id = ?
        LIMIT 1
    ");

} elseif ($source === 'mobile') {

    $stmt = $conn->prepare("
        SELECT document_file
        FROM mobile_franchies_kyc
        WHERE id = ?
        LIMIT 1
    ");

} else {

    $stmt = $conn->prepare("
        SELECT document_file
        FROM franchise_kyc_documents
        WHERE id = ?
        LIMIT 1
    ");

}
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row || empty($row['document_file'])) {
        http_response_code(404);
        echo "Document not found in database.";
        exit;
    }

    $file = findKycFile($row['document_file']);

    if ($file === '') {
        http_response_code(404);
        echo "File not found on server. DB path: " . e($row['document_file']);
        exit;
    }

    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));

    $mime = [
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png'  => 'image/png',
        'webp' => 'image/webp',
        'gif'  => 'image/gif',
        'pdf'  => 'application/pdf'
    ];

    header("Content-Type: " . ($mime[$ext] ?? 'application/octet-stream'));
    header("Content-Disposition: inline; filename=\"" . basename($file) . "\"");
    header("Content-Length: " . filesize($file));
    readfile($file);
    exit;
}

$message = "";
$error = "";

if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    isset($_POST["kyc_id"], $_POST["status"], $_POST["source_table"])
) {

    $kycId  = (int)$_POST["kyc_id"];
    $status = trim($_POST["status"]);
    $source = trim($_POST["source_table"]);

    if (in_array($status, ["Pending", "Approved", "Rejected"], true)) {

        if ($source === 'city') {

            $stmt = $conn->prepare("
                UPDATE city_franchies_kyc
                SET status = ?
                WHERE id = ?
                LIMIT 1
            ");

        } elseif ($source === 'mobile') {

            $stmt = $conn->prepare("
                UPDATE mobile_franchies_kyc
                SET status = ?
                WHERE id = ?
                LIMIT 1
            ");

        } else {

            $stmt = $conn->prepare("
                UPDATE franchise_kyc_documents
                SET status = ?
                WHERE id = ?
                LIMIT 1
            ");
        }

        $stmt->bind_param("si", $status, $kycId);

        if ($stmt->execute()) {

    if ($stmt->affected_rows > 0) {
        $message = "KYC status updated successfully.";
    } else {
        $error = "No record updated.";
    }

} else {

    $error = "Status update failed.";
}

        $stmt->close();
    }
}

function getFranchiseName($conn, $franchiseCode) {
    if (!tableExists($conn, "franchies_holder")) {
        return "N/A";
    }

    $codeColumn = "";
    foreach (["franchies_id", "franchise_id", "franchises_id"] as $col) {
        if (tableHasColumn($conn, "franchies_holder", $col)) {
            $codeColumn = $col;
            break;
        }
    }

    $nameColumn = "";
    foreach (["name", "full_name", "franchise_name"] as $col) {
        if (tableHasColumn($conn, "franchies_holder", $col)) {
            $nameColumn = $col;
            break;
        }
    }

    if ($codeColumn === "" || $nameColumn === "") {
        return "N/A";
    }

    $stmt = $conn->prepare("SELECT `$nameColumn` FROM franchies_holder WHERE `$codeColumn` = ? LIMIT 1");
    $stmt->bind_param("s", $franchiseCode);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $row[$nameColumn] ?? "N/A";
}

$kycRows = [];

/* STATE FRANCHISE */
$result = $conn->query("
SELECT
    id,
    franchise_code,
    document_type,
    document_file,
    status,
    uploaded_at,
    'State Franchise' AS franchise_type,
    'state' AS source_table
FROM franchise_kyc_documents
");

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $row["franchise_name"] = getFranchiseName($conn, $row["franchise_code"]);
        $kycRows[] = $row;
    }
}


/* CITY FRANCHISE */
$result = $conn->query("
   SELECT
    ck.id,
    ck.franchise_code,
    ck.document_type,
    ck.document_file,
    ck.status,
    ck.uploaded_at,
    cf.full_name,
    'City Franchise' AS franchise_type,
    'city' AS source_table
    FROM city_franchies_kyc ck
    LEFT JOIN city_franchies_holder cf
        ON cf.franchise_code = ck.franchise_code
");

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $row["franchise_name"] = $row["full_name"] ?? 'N/A';
        $kycRows[] = $row;
    }
}

/* MOBILE FRANCHISE */
$result = $conn->query("
    SELECT
        mk.id,
        mk.franchise_code,
        mk.document_type,
        mk.document_file,
        mk.status,
        mk.uploaded_at,
        mf.owner_name,
        'Mobile Franchise' AS franchise_type,
        'mobile' AS source_table
    FROM mobile_franchies_kyc mk
    LEFT JOIN mobile_franchies_holder mf
        ON mf.mobile_franchies_id = mk.franchise_code
");

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $row["franchise_name"] = $row["owner_name"] ?? 'N/A';
        $kycRows[] = $row;
    }
}

/* SORT LATEST FIRST */
usort($kycRows, function ($a, $b) {
    return strtotime($b['uploaded_at']) - strtotime($a['uploaded_at']);
});


?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Franchise KYC Details</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<script src="https://cdn.tailwindcss.com"></script>
<script src="https://unpkg.com/lucide@latest"></script>
</head>

<body class="bg-[#f3f4f6] font-sans overflow-x-hidden">

<?php include __DIR__ . "/includes/sidebar.php"; ?>

<div class="lg:ml-64 min-h-screen">
<?php include __DIR__ . "/includes/navbar.php"; ?>

<div class="mt-5 px-3 sm:px-7 flex flex-wrap items-center gap-3 sm:gap-4">
    <div class="w-[36px] h-[36px] rounded-[8px] bg-[#f5eee3] flex items-center justify-center shrink-0">
        <i data-lucide="badge-check" class="w-[18px] h-[18px] text-[#cd9737]"></i>
    </div>

    <h2 class="whitespace-nowrap text-[18px] sm:text-[20px] font-normal text-[#40352a]" style="font-family: Georgia, 'Times New Roman', serif;">
        FRANCHIES KYC DETAILS
    </h2>

    <div class="hidden sm:block flex-1 h-[2px] bg-[#d1a14b]"></div>
</div>

<main class="px-3 sm:px-7 py-4 sm:py-7">
<div class="max-w-[1500px] mx-auto">

<?php if ($message !== ""): ?>
    <div class="mb-4 sm:mb-6 rounded-lg border border-green-200 bg-green-50 px-4 sm:px-5 py-3 sm:py-4 text-sm sm:text-base text-green-700">
        <?= e($message); ?>
    </div>
<?php endif; ?>

<?php if ($error !== ""): ?>
    <div class="mb-4 sm:mb-6 rounded-lg border border-red-200 bg-red-50 px-4 sm:px-5 py-3 sm:py-4 text-sm sm:text-base text-red-700">
        <?= e($error); ?>
    </div>
<?php endif; ?>

<div class="bg-white rounded-[14px] shadow border border-gray-200 overflow-hidden">
    <div class="bg-[#12395f] px-4 sm:px-7 py-5 sm:py-7">
        <h1 class="text-white text-[18px] sm:text-[24px] font-bold">Franchise Member KYC Documents</h1>
        <p class="text-white text-[13px] sm:text-[17px] mt-2">View uploaded franchise KYC documents and approve or reject status.</p>
    </div>

    <div class="p-3 sm:p-7 overflow-x-auto">
        <table class="w-full min-w-[1050px]">
            <thead>
            <tr class="bg-[#d19a2e] text-white">
                <th class="px-3 sm:px-5 py-3 sm:py-5 text-left text-sm sm:text-base rounded-l-lg">ID</th>
                <th class="px-3 sm:px-5 py-3 sm:py-5 text-left text-sm sm:text-base">Franchise Code</th>
                <th class="px-3 sm:px-5 py-3 sm:py-5 text-left text-sm sm:text-base">Franchise Member</th>
<th class="px-3 sm:px-5 py-3 sm:py-5 text-left text-sm sm:text-base">Franchise Type</th>
<th class="px-3 sm:px-5 py-3 sm:py-5 text-left text-sm sm:text-base">Document Type</th>
                <th class="px-3 sm:px-5 py-3 sm:py-5 text-left text-sm sm:text-base">Upload Date</th>
                <th class="px-3 sm:px-5 py-3 sm:py-5 text-left text-sm sm:text-base">View Document</th>
                <th class="px-3 sm:px-5 py-3 sm:py-5 text-left text-sm sm:text-base">Status</th>
                <th class="px-3 sm:px-5 py-3 sm:py-5 text-left text-sm sm:text-base rounded-r-lg">Action</th>
            </tr>
            </thead>

            <tbody>
            <?php if (!empty($kycRows)): ?>
                <?php foreach ($kycRows as $row): ?>
                    <?php
                    $statusClass = "bg-yellow-100 text-yellow-700";
                    if ($row["status"] === "Approved") $statusClass = "bg-green-100 text-green-700";
                    if ($row["status"] === "Rejected") $statusClass = "bg-red-100 text-red-700";

                    $docUrl =
    basename($_SERVER['PHP_SELF'])
    . "?view_doc="
    . urlencode($row["id"])
    . "&source="
    . urlencode($row["source_table"]);
                    ?>

                    <tr class="border-b border-gray-100">
                        <td class="px-3 sm:px-5 py-4 sm:py-6 text-[13px] sm:text-[17px]"><?= e($row["id"]); ?></td>

                        <td class="px-3 sm:px-5 py-4 sm:py-6 text-[13px] sm:text-[17px] font-semibold text-[#12395f]">
                            <?= e($row["franchise_code"]); ?>
                        </td>

                       <td class="px-3 sm:px-5 py-4 sm:py-6 text-[13px] sm:text-[17px]">
    <?= e($row["franchise_name"]); ?>
</td>

<td class="px-3 sm:px-5 py-4 sm:py-6">
    <?php
   $typeClass = 'bg-blue-100 text-blue-700';

if ($row['franchise_type'] === 'City Franchise') {
    $typeClass = 'bg-green-100 text-green-700';
}

if ($row['franchise_type'] === 'Mobile Franchise') {
    $typeClass = 'bg-orange-100 text-orange-700';
}
    ?>
    
    <span class="px-2.5 sm:px-3 py-1 rounded-full text-[11px] sm:text-xs font-semibold whitespace-nowrap <?= $typeClass; ?>">
        <?= e($row['franchise_type']); ?>
    </span>
</td>

<td class="px-3 sm:px-5 py-4 sm:py-6 text-[13px] sm:text-[17px]">
    <?= e($row["document_type"]); ?>
</td>

                        <td class="px-3 sm:px-5 py-4 sm:py-6 text-[13px] sm:text-[17px] whitespace-nowrap">
                            <?= e(date("d-m-Y h:i A", strtotime($row["uploaded_at"]))); ?>
                        </td>

                        <td class="px-3 sm:px-5 py-4 sm:py-6 text-[13px] sm:text-[17px]">
                            <button type="button" onclick="openDocModal('<?= e($docUrl); ?>')" class="text-blue-600 hover:underline whitespace-nowrap">
                                View Document
                            </button>
                        </td>

                        <td class="px-3 sm:px-5 py-4 sm:py-6">
                            <span class="px-3 sm:px-4 py-1.5 sm:py-2 rounded-full text-[12px] sm:text-[14px] font-semibold whitespace-nowrap <?= e($statusClass); ?>">
                                <?= e($row["status"]); ?>
                            </span>
                        </td>

                        <td class="px-3 sm:px-5 py-4 sm:py-6">
                            <form method="POST" class="flex items-center gap-2">
                                <input type="hidden" name="kyc_id" value="<?= e($row["id"]); ?>">
                                <input type="hidden"
       name="source_table"
       value="<?= e($row["source_table"]); ?>">

                                <select name="status" class="h-[38px] sm:h-[44px] border border-gray-300 rounded-md px-3 sm:px-4 text-[13px] sm:text-[15px]">
                                    <option value="Pending" <?= $row["status"] === "Pending" ? "selected" : ""; ?>>Pending</option>
                                    <option value="Approved" <?= $row["status"] === "Approved" ? "selected" : ""; ?>>Approved</option>
                                    <option value="Rejected" <?= $row["status"] === "Rejected" ? "selected" : ""; ?>>Rejected</option>
                                </select>

                                <button type="submit" class="h-[38px] sm:h-[44px] px-3 sm:px-5 rounded-md bg-[#12395f] text-white text-[13px] sm:text-[15px] hover:opacity-90 whitespace-nowrap">
                                    Update
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                   <td colspan="9" class="py-8 text-center text-gray-500">
                        No franchise KYC documents found.
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

<div id="docModal" class="fixed inset-0 z-[99999] hidden items-center justify-center bg-black/60 px-2 sm:px-4">
    <div class="w-full max-w-5xl rounded-2xl bg-white shadow-2xl overflow-hidden">
        <div class="flex items-center justify-between border-b px-4 sm:px-5 py-3 sm:py-4">
            <h2 class="text-base sm:text-xl font-bold text-slate-800">KYC Document</h2>
            <button onclick="closeDocModal()" class="rounded-full bg-red-50 px-3 py-1 text-xl font-bold text-red-600">×</button>
        </div>

        <div class="p-3 sm:p-5 bg-gray-100 max-h-[78vh] overflow-auto text-center">
            <iframe id="docFrame" src="" class="h-[65vh] sm:h-[72vh] w-full rounded-xl border bg-white"></iframe>
        </div>

        <div class="border-t px-4 sm:px-5 py-3 sm:py-4 text-right">
            <a id="docOpenNew" href="#" target="_blank" class="inline-block rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white">
                Open in New Tab
            </a>
        </div>
    </div>
</div>

<script>
function openDocModal(url) {
    document.getElementById('docFrame').src = url;
    document.getElementById('docOpenNew').href = url;
    document.getElementById('docModal').classList.remove('hidden');
    document.getElementById('docModal').classList.add('flex');
}

function closeDocModal() {
    document.getElementById('docFrame').src = '';
    document.getElementById('docModal').classList.add('hidden');
    document.getElementById('docModal').classList.remove('flex');
}

document.addEventListener("DOMContentLoaded", function () {
    if (typeof lucide !== "undefined") {
        lucide.createIcons();
    }
});

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeDocModal();
});
</script>

</body>
</html>