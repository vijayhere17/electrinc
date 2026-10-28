<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/config/db.php';

if (!isset($conn) || !($conn instanceof mysqli)) {
    die("Database connection failed.");
}

function e($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function findCityKycFile($dbFile)
{
    $dbFile = trim((string)$dbFile);
    if ($dbFile === '') {
        return '';
    }

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
        'uploads/city_kyc',
        'uploads/city_franchise_kyc',
        'uploads/franchise_kyc',
        'admin/uploads',
        'admin/uploads/kyc',
        'admin/uploads/city_kyc',
        'admin/uploads/city_franchise_kyc',
        'franchise/uploads',
        'franchise/uploads/kyc',
        'franchise/uploads/city_kyc',
        'franchise/uploads/city_franchise_kyc',
        'franchise/uploads/franchise_kyc',
        'franchies/uploads',
        'franchies/uploads/kyc',
        'franchies/uploads/city_kyc',
        'franchies/uploads/city_franchise_kyc',
        'city_franchies/uploads',
        'city_franchies/uploads/kyc',
        'member/uploads',
        'member/uploads/kyc'
    ];

    foreach ($roots as $root) {
        if ($root === '') continue;

        foreach ($folders as $folder) {
            $dir = rtrim($root . '/' . $folder, '/');

            $try = $dir . '/' . $base;
            if (is_file($try)) {
                return realpath($try);
            }

            foreach (glob($dir . '/' . $nameOnly . '.*') ?: [] as $match) {
                if (is_file($match)) {
                    return realpath($match);
                }
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
        } catch (Exception $ex) {
        }
    }

    return '';
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

/* VIEW DOCUMENT */
if (isset($_GET['view_doc'])) {
    $id = (int)$_GET['view_doc'];

    $stmt = $conn->prepare("SELECT document_file FROM city_franchies_kyc WHERE id = ? LIMIT 1");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row || empty($row['document_file'])) {
        http_response_code(404);
        echo "Document not found in database.";
        exit;
    }

    $file = findCityKycFile($row['document_file']);

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

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['kyc_id'], $_POST['status'])) {
    $kycId = (int)$_POST['kyc_id'];
    $status = trim($_POST['status']);

    if ($kycId <= 0 || !in_array($status, ['Pending', 'Approved', 'Rejected'], true)) {
        $error = "Invalid action.";
    } else {
        $stmt = $conn->prepare("UPDATE city_franchies_kyc SET status = ? WHERE id = ? LIMIT 1");
        $stmt->bind_param("si", $status, $kycId);

        if ($stmt->execute()) {
            $message = "KYC status updated successfully.";
        } else {
            $error = "Status update failed.";
        }

        $stmt->close();
    }
}

$documents = [];

$sql = "
    SELECT 
        ck.id,
        ck.franchise_code,
        ck.document_type,
        ck.document_file,
        ck.status,
        ck.uploaded_at,
        cf.full_name,
        cf.mobile,
        cf.email,
        cf.state,
        cf.city
    FROM city_franchies_kyc ck
    LEFT JOIN city_franchies_holder cf 
        ON cf.franchise_code = ck.franchise_code
    ORDER BY ck.id DESC
";

$result = $conn->query($sql);

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $documents[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>City Franchise KYC</title>
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
                    CITY FRANCHISE KYC
                </h2>

                <div class="w-full h-[2px] bg-[#d1a14b]"></div>
            </div>

            <?php if ($message !== ''): ?>
                <div class="mb-5 rounded-lg border border-green-200 bg-green-50 px-5 py-3 text-green-700">
                    <?= e($message); ?>
                </div>
            <?php endif; ?>

            <?php if ($error !== ''): ?>
                <div class="mb-5 rounded-lg border border-red-200 bg-red-50 px-5 py-3 text-red-700">
                    <?= e($error); ?>
                </div>
            <?php endif; ?>

            <div class="bg-white rounded-[12px] shadow border border-gray-200 p-7">
                <div class="overflow-x-auto">

                    <table class="w-full border border-gray-200 rounded-[8px] overflow-hidden">
                        <thead>
                            <tr class="bg-[#103258] text-white">
                                <th class="py-4 px-4 text-center text-[16px] font-bold">Sr No</th>
                                <th class="py-4 px-4 text-center text-[16px] font-bold">Franchise ID</th>
                                <th class="py-4 px-4 text-center text-[16px] font-bold">Name</th>
                                <th class="py-4 px-4 text-center text-[16px] font-bold">Mobile</th>
                                <th class="py-4 px-4 text-center text-[16px] font-bold">City</th>
                                <th class="py-4 px-4 text-center text-[16px] font-bold">Upload Date</th>
                                <th class="py-4 px-4 text-center text-[16px] font-bold">Title</th>
                                <th class="py-4 px-4 text-center text-[16px] font-bold">Document</th>
                                <th class="py-4 px-4 text-center text-[16px] font-bold">Status</th>
                                <th class="py-4 px-4 text-center text-[16px] font-bold">Action</th>
                            </tr>
                        </thead>

                        <tbody>
                        <?php if (!empty($documents)): ?>
                            <?php $sr = 1; ?>
                            <?php foreach ($documents as $doc): ?>
                                <?php
                                $status = $doc['status'];
                                $statusClass = 'text-yellow-600';

                                if ($status === 'Approved') {
                                    $statusClass = 'text-green-600';
                                } elseif ($status === 'Rejected') {
                                    $statusClass = 'text-red-600';
                                }

                                $docUrl = basename($_SERVER['PHP_SELF']) . '?view_doc=' . urlencode($doc['id']);
                                ?>

                                <tr class="border-b border-gray-200">
                                    <td class="py-5 px-4 text-center text-[16px]">
                                        <?= str_pad((string)$sr, 2, '0', STR_PAD_LEFT); ?>
                                    </td>

                                    <td class="py-5 px-4 text-center text-[16px] font-semibold">
                                        <?= e($doc['franchise_code']); ?>
                                    </td>

                                    <td class="py-5 px-4 text-center text-[16px]">
                                        <?= e($doc['full_name'] ?? '-'); ?>
                                    </td>

                                    <td class="py-5 px-4 text-center text-[16px]">
                                        <?= e($doc['mobile'] ?? '-'); ?>
                                    </td>

                                    <td class="py-5 px-4 text-center text-[16px]">
                                        <?= e($doc['city'] ?? '-'); ?>
                                    </td>

                                    <td class="py-5 px-4 text-center text-[16px]">
                                        <?= e(date('d-m-Y', strtotime($doc['uploaded_at']))); ?>
                                    </td>

                                    <td class="py-5 px-4 text-center text-[16px] font-medium">
                                        <?= e($doc['document_type']); ?>
                                    </td>

                                    <td class="py-5 px-4 text-center text-[16px]">
                                        <button type="button"
                                                onclick="openDocModal('<?= e($docUrl); ?>')"
                                                class="text-blue-600 hover:underline">
                                            View
                                        </button>
                                    </td>

                                    <td class="py-5 px-4 text-center text-[16px]">
                                        <span class="<?= e($statusClass); ?> font-semibold">
                                            <?= e($status); ?>
                                        </span>
                                    </td>

                                    <td class="py-5 px-4 text-center text-[16px]">
                                        <form method="POST" class="flex items-center justify-center gap-2">
                                            <input type="hidden" name="kyc_id" value="<?= (int)$doc['id']; ?>">

                                            <select name="status"
                                                    class="border border-gray-300 rounded-[6px] px-3 py-2 text-[14px] focus:outline-none">
                                                <option value="Pending" <?= $doc['status'] === 'Pending' ? 'selected' : ''; ?>>Pending</option>
                                                <option value="Approved" <?= $doc['status'] === 'Approved' ? 'selected' : ''; ?>>Approved</option>
                                                <option value="Rejected" <?= $doc['status'] === 'Rejected' ? 'selected' : ''; ?>>Rejected</option>
                                            </select>

                                            <button type="submit"
                                                    class="bg-[#103258] text-white px-4 py-2 rounded-[6px] text-[14px] font-semibold hover:opacity-90">
                                                Update
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                                <?php $sr++; ?>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="10" class="py-6 text-center text-gray-500">
                                    No City Franchise KYC documents found.
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

<div id="docModal" class="fixed inset-0 z-[99999] hidden items-center justify-center bg-black/60 px-4">
    <div class="w-full max-w-5xl rounded-2xl bg-white shadow-2xl overflow-hidden">
        <div class="flex items-center justify-between border-b px-5 py-4">
            <h2 class="text-xl font-bold text-slate-800">KYC Document</h2>
            <button onclick="closeDocModal()" class="rounded-full bg-red-50 px-3 py-1 text-xl font-bold text-red-600">×</button>
        </div>

        <div class="p-5 bg-gray-100 max-h-[78vh] overflow-auto text-center">
            <iframe id="docFrame" src="" class="h-[72vh] w-full rounded-xl border bg-white"></iframe>
        </div>

        <div class="border-t px-5 py-4 text-right">
            <a id="docOpenNew" href="#" target="_blank"
               class="inline-block rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white">
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

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeDocModal();
    }
});

if (typeof lucide !== 'undefined') {
    lucide.createIcons();
}
</script>

</body>
</html>