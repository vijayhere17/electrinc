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

function tableExists(mysqli $conn, string $table): bool
{
    $table = $conn->real_escape_string($table);
    $res = $conn->query("SHOW TABLES LIKE '$table'");
    return $res && $res->num_rows > 0;
}

function columnExists(mysqli $conn, string $table, string $column): bool
{
    if (!tableExists($conn, $table)) {
        return false;
    }

    $table = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
    $column = $conn->real_escape_string($column);

    $res = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
    return $res && $res->num_rows > 0;
}

function findMobileKycFile($dbFile)
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
        ($_SERVER['DOCUMENT_ROOT'] ?? '') . '/freshiya_main',
        ($_SERVER['DOCUMENT_ROOT'] ?? '') . '/freshiya',
    ];

    $folders = [
        '',
        'uploads',
        'uploads/franchise_kyc',
        'uploads/mobile_kyc',
        'uploads/mobile_franchise_kyc',
        'admin/uploads',
        'admin/uploads/kyc',
        'admin/uploads/franchise_kyc',
        'franchise/uploads',
        'franchise/uploads/kyc',
        'franchise/uploads/franchise_kyc',
        'franchise/uploads/mobile_kyc',
        'franchise/uploads/mobile_franchise_kyc',
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

    return '';
}

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

if (isset($_GET['view_doc'])) {
    $id = (int)$_GET['view_doc'];

    $stmt = $conn->prepare("SELECT document_file FROM mobile_franchies_kyc WHERE id = ? LIMIT 1");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row || empty($row['document_file'])) {
        http_response_code(404);
        echo "Document not found in database.";
        exit;
    }

    $file = findMobileKycFile($row['document_file']);

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
        $stmt = $conn->prepare("UPDATE mobile_franchies_kyc SET status = ? WHERE id = ? LIMIT 1");
        $stmt->bind_param("si", $status, $kycId);

        if ($stmt->execute()) {
            $message = "Mobile franchise KYC status updated successfully.";
        } else {
            $error = "Status update failed.";
        }

        $stmt->close();
    }
}

$documents = [];

$sql = "
    SELECT 
        mk.id,
        mk.franchise_code,
        mk.document_type,
        mk.document_file,
        mk.status,
        mk.uploaded_at,
        mf.shop_name,
        mf.owner_name,
        mf.mobile,
        mf.email,
        mf.state,
        mf.city
    FROM mobile_franchies_kyc mk
    LEFT JOIN mobile_franchies_holder mf
        ON mf.mobile_franchies_id = mk.franchise_code
    ORDER BY mk.id DESC
";

$result = $conn->query($sql);

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $documents[] = $row;
    }
} else {
    $error = "Fetch failed: " . $conn->error;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Mobile Franchise KYC</title>
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
                    MOBILE FRANCHISE KYC
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

            <div class="bg-white rounded-[12px] shadow border border-gray-200 p-6">
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[1200px] border border-gray-200">
                        <thead>
                        <tr class="bg-[#f3ecdf] text-black">
                            <th class="border px-4 py-4 text-left">#</th>
                            <th class="border px-4 py-4 text-left">Franchise ID</th>
                            <th class="border px-4 py-4 text-left">Shop Name</th>
                            <th class="border px-4 py-4 text-left">Owner Name</th>
                            <th class="border px-4 py-4 text-left">Mobile</th>
                            <th class="border px-4 py-4 text-left">Email</th>
                            <th class="border px-4 py-4 text-left">Document Type</th>
                            <th class="border px-4 py-4 text-left">Document</th>
                            <th class="border px-4 py-4 text-left">Status</th>
                            <th class="border px-4 py-4 text-left">Uploaded At</th>
                            <th class="border px-4 py-4 text-left">Action</th>
                        </tr>
                        </thead>

                        <tbody>
                        <?php if (!empty($documents)): ?>
                            <?php $sr = 1; ?>
                            <?php foreach ($documents as $doc): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="border px-4 py-4"><?= $sr++; ?></td>

                                    <td class="border px-4 py-4 font-bold text-blue-600">
                                        <?= e($doc['franchise_code']); ?>
                                    </td>

                                    <td class="border px-4 py-4">
                                        <?= e($doc['shop_name'] ?? ''); ?>
                                    </td>

                                    <td class="border px-4 py-4">
                                        <?= e($doc['owner_name'] ?? ''); ?>
                                    </td>

                                    <td class="border px-4 py-4">
                                        <?= e($doc['mobile'] ?? ''); ?>
                                    </td>

                                    <td class="border px-4 py-4">
                                        <?= e($doc['email'] ?? ''); ?>
                                    </td>

                                    <td class="border px-4 py-4 font-semibold">
                                        <?= e($doc['document_type']); ?>
                                    </td>

                                    <td class="border px-4 py-4">
                                        <a href="?view_doc=<?= (int)$doc['id']; ?>"
                                           target="_blank"
                                           class="inline-flex items-center justify-center bg-[#103258] text-white px-4 py-2 rounded-[6px] text-[14px]">
                                            View
                                        </a>
                                    </td>

                                    <td class="border px-4 py-4">
                                        <?php
                                        $status = $doc['status'];
                                        $statusClass = 'bg-yellow-100 text-yellow-700';

                                        if ($status === 'Approved') {
                                            $statusClass = 'bg-green-100 text-green-700';
                                        } elseif ($status === 'Rejected') {
                                            $statusClass = 'bg-red-100 text-red-700';
                                        }
                                        ?>

                                        <span class="px-4 py-2 rounded-full text-[14px] font-semibold <?= e($statusClass); ?>">
                                            <?= e($status); ?>
                                        </span>
                                    </td>

                                    <td class="border px-4 py-4">
                                        <?= e($doc['uploaded_at']); ?>
                                    </td>

                                    <td class="border px-4 py-4">
                                        <div class="flex flex-wrap gap-2">
                                            <form method="POST">
                                                <input type="hidden" name="kyc_id" value="<?= (int)$doc['id']; ?>">
                                                <input type="hidden" name="status" value="Approved">
                                                <button type="submit" class="bg-green-600 text-white px-3 py-2 rounded text-sm">
                                                    Approve
                                                </button>
                                            </form>

                                            <form method="POST">
                                                <input type="hidden" name="kyc_id" value="<?= (int)$doc['id']; ?>">
                                                <input type="hidden" name="status" value="Rejected">
                                                <button type="submit" class="bg-red-600 text-white px-3 py-2 rounded text-sm">
                                                    Reject
                                                </button>
                                            </form>

                                            <form method="POST">
                                                <input type="hidden" name="kyc_id" value="<?= (int)$doc['id']; ?>">
                                                <input type="hidden" name="status" value="Pending">
                                                <button type="submit" class="bg-yellow-500 text-white px-3 py-2 rounded text-sm">
                                                    Pending
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="11" class="border px-4 py-8 text-center text-gray-500">
                                    No Mobile Franchise KYC document found.
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