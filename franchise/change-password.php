<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);


if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/helpers.php';

if (!isset($conn) || !($conn instanceof mysqli)) {
    die("Database connection failed.");
}

$conn->set_charset("utf8mb4");

function tableExists(mysqli $conn, string $table): bool
{
    $safeTable = $conn->real_escape_string($table);
    $result = $conn->query("SHOW TABLES LIKE '" . $safeTable . "'");
    return $result instanceof mysqli_result && $result->num_rows > 0;
}

function columnExists(mysqli $conn, string $table, string $column): bool
{
    if (!tableExists($conn, $table)) {
        return false;
    }

    $safeTable = $conn->real_escape_string($table);
    $safeColumn = $conn->real_escape_string($column);
    $result = $conn->query("SHOW COLUMNS FROM `" . $safeTable . "` LIKE '" . $safeColumn . "'");
    return $result instanceof mysqli_result && $result->num_rows > 0;
}

function resolveFranchiseTable(string $sessionTable, string $sessionType): string
{
    if ($sessionTable !== '') {
        return $sessionTable;
    }

    switch (strtolower(trim($sessionType))) {
        case 'state':
            return 'franchies_holder';
        case 'city':
            return 'city_franchies_holder';
        case 'mobile':
            return 'mobile_franchies_holder';
        default:
            return '';
    }
}

function getFranchiseLoginColumns(string $table): array
{
   $common = [
    'franchise_code',
    'franchies_id',
    'franchises_id',
    'franchise_id'
];

    if ($table === 'mobile_franchies_holder') {
        return array_merge(['mobile_franchies_id', 'mobile_franchise_id'], $common);
    }

    if ($table === 'city_franchies_holder') {
        return array_merge(['city_franchies_id', 'city_franchise_id'], $common);
    }

    return $common;
}

function fetchFranchiseRow(mysqli $conn, string $table, string $loginId): ?array
{
    $columns = getFranchiseLoginColumns($table);

    foreach ($columns as $column) {
        if (!columnExists($conn, $table, $column)) {
            continue;
        }

        $stmt = $conn->prepare("SELECT * FROM `{$table}` WHERE `{$column}` = ? LIMIT 1");
        if (!$stmt) {
            continue;
        }

        $stmt->bind_param('s', $loginId);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = ($result && $result->num_rows === 1) ? $result->fetch_assoc() : null;
        $stmt->close();

        if ($row !== null) {
            $row['_login_column'] = $column;
            return $row;
        }
    }

    return null;
}

function verifyFranchisePassword(array $row, string $password): bool
{
    $passwordColumns = [
        'mobile_franchies_password',
        'plain_password',
        'city_franchies_password',
        'franchies_password',
        'franchises_password',
        'password_hash',
        'password',
        'password_text',
        'franchies_password_text',
        'franchises_password_text',
    ];

    foreach ($passwordColumns as $column) {
        if (!isset($row[$column])) {
            continue;
        }

        $dbPass = trim((string)$row[$column]);
        if ($dbPass === '') {
            continue;
        }

        if (password_get_info($dbPass)['algo'] !== 0) {
            if (password_verify($password, $dbPass)) {
                return true;
            }
        }

        if ($dbPass === $password) {
            return true;
        }
    }

    return false;
}

function findPasswordColumn(mysqli $conn, string $table): ?string
{
    $columns = [
        'mobile_franchies_password',
        'city_franchies_password',
        'franchies_password',
        'franchises_password',
        'password_hash',
        'password',
        'password_text',
        'franchies_password_text',
        'franchises_password_text',
        'plain_password',
    ];

    foreach ($columns as $column) {
        if (columnExists($conn, $table, $column)) {
            return $column;
        }
    }

    return null;
}

$franchiseLoginId = trim((string)(
    $_SESSION['franchise_login_id']
    ?? $_SESSION['franchise_code']
    ?? $_SESSION['franchise_id']
    ?? ''
));
$franchiseTable = resolveFranchiseTable(trim((string)($_SESSION['franchise_table'] ?? '')), trim((string)($_SESSION['franchise_type'] ?? '')));
$franchiseName = $_SESSION['franchise_name'] ?? 'Franchise';
$franchiseCode = $_SESSION['franchise_code'] ?? $_SESSION['franchise_login_id'] ?? $_SESSION['franchise_id'] ?? '';

if ($franchiseLoginId === '' || $franchiseTable === '') {
    header('Location: login.php');
    exit();
}

$success = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $currentPassword = trim($_POST['current_password'] ?? '');
    $newPassword = trim($_POST['new_password'] ?? '');
    $confirmPassword = trim($_POST['confirm_password'] ?? '');

    if ($currentPassword === '' || $newPassword === '' || $confirmPassword === '') {
        $error = 'All fields are required.';
    } elseif (strlen($newPassword) < 6) {
        $error = 'New password must be at least 6 characters.';
    } elseif ($newPassword !== $confirmPassword) {
        $error = 'New password and confirm password do not match.';
    } else {
        $account = fetchFranchiseRow($conn, $franchiseTable, $franchiseLoginId);

        if (!$account) {
            $error = 'User not found.';
        } elseif (!verifyFranchisePassword($account, $currentPassword)) {
            $error = 'Current password is incorrect.';
        } elseif ($currentPassword === $newPassword) {
            $error = 'New password must be different from current password.';
        } else {
            $passwordColumn = findPasswordColumn($conn, $franchiseTable);

if ($passwordColumn === null) {

    $error = 'No valid password column was found for your franchise account.';

} else {

    $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
    $accountId = (int)$account['id'];

    // Check if plain_password column exists
    if (columnExists($conn, $franchiseTable, 'plain_password')) {

        $stmt = $conn->prepare("
            UPDATE `{$franchiseTable}`
            SET
                `{$passwordColumn}` = ?,
                `plain_password` = ?
            WHERE `id` = ?
            LIMIT 1
        ");

        if (!$stmt) {

            $error = 'Database prepare failed: ' . $conn->error;

        } else {

            $stmt->bind_param("ssi", $newHash, $newPassword, $accountId);

            if ($stmt->execute()) {
                $success = true;
            } else {
                $error = 'Password update failed: ' . $stmt->error;
            }

            $stmt->close();
        }

    } else {

        $stmt = $conn->prepare("
            UPDATE `{$franchiseTable}`
            SET `{$passwordColumn}` = ?
            WHERE `id` = ?
            LIMIT 1
        ");

        if (!$stmt) {

            $error = 'Database prepare failed: ' . $conn->error;

        } else {

            $stmt->bind_param("si", $newHash, $accountId);

            if ($stmt->execute()) {
                $success = true;
            } else {
                $error = 'Password update failed: ' . $stmt->error;
            }

            $stmt->close();
        }
    }
}
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Change Password</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body class="bg-[#f5f5f5] font-sans">

    <?php include __DIR__ . '/includes/sidebar.php'; ?>

    <div class="ml-64 min-h-screen">
        <?php include __DIR__ . '/includes/navbar.php'; ?>

        <div class="px-8 py-4">

            <?php if ($success): ?>
                <div class="mt-5 rounded-[10px] border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">
                    Password updated successfully.
                </div>
            <?php endif; ?>

            <?php if ($error !== ""): ?>
                <div class="mt-5 rounded-[10px] border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <form method="POST" class="mt-6">
                <section>
                    <div class="mb-9 flex items-center gap-5">
                        <div class="flex h-[44px] w-[44px] shrink-0 items-center justify-center rounded-[10px] bg-[#f6eee2]">
                            <i data-lucide="badge-check" class="h-5 w-5 text-[#cf9a3c]"></i>
                        </div>

                        <h2 class="whitespace-nowrap text-[20px] font-normal leading-none text-[#3b3329]" style="font-family: Georgia, 'Times New Roman', serif;">
                            CHANGE PASSWORD
                        </h2>

                        <div class="h-[2px] w-full bg-[#d1a14b]"></div>
                    </div>

                    <div class="space-y-5">
                        <div>
                            <label class="mb-3 flex items-center gap-3 text-[15px] font-semibold uppercase text-[#9e9e9e]">
                                <i data-lucide="key-round" class="h-[18px] w-[18px] text-[#cf982f]"></i>
                                <span>CURRENT PASSWORD <span class="text-red-400">*</span></span>
                            </label>
                            <input
                                type="password"
                                name="current_password"
                                placeholder="Enter Password"
                                class="h-[56px] w-full rounded-[12px] border border-[#d9dde2] bg-[#eef0f3] px-5 text-[16px] text-[#5b6470] outline-none placeholder:text-[#72777f] focus:border-[#c9983c] focus:bg-white"
                                required
                            >
                        </div>

                        <div>
                            <label class="mb-3 flex items-center gap-3 text-[15px] font-semibold uppercase text-[#9e9e9e]">
                                <i data-lucide="key-round" class="h-[18px] w-[18px] text-[#cf982f]"></i>
                                <span>NEW PASSWORD <span class="text-red-400">*</span></span>
                            </label>
                            <input
                                type="password"
                                name="new_password"
                                placeholder="Enter Password"
                                class="h-[56px] w-full rounded-[12px] border border-[#d9dde2] bg-[#eef0f3] px-5 text-[16px] text-[#5b6470] outline-none placeholder:text-[#72777f] focus:border-[#c9983c] focus:bg-white"
                                required
                            >
                        </div>

                        <div>
                            <label class="mb-3 flex items-center gap-3 text-[15px] font-semibold uppercase text-[#9e9e9e]">
                                <i data-lucide="key-round" class="h-[18px] w-[18px] text-[#cf982f]"></i>
                                <span>CONFIRM PASSWORD <span class="text-red-400">*</span></span>
                            </label>
                            <input
                                type="password"
                                name="confirm_password"
                                placeholder="Enter Password"
                                class="h-[56px] w-full rounded-[12px] border border-[#d9dde2] bg-[#eef0f3] px-5 text-[16px] text-[#5b6470] outline-none placeholder:text-[#72777f] focus:border-[#c9983c] focus:bg-white"
                                required
                            >
                        </div>
                    </div>
                </section>

                <div class="mt-12 flex justify-center">
                    <button
                        type="submit"
                        class="h-[54px] min-w-[168px] rounded-[12px] bg-[#bf8b35] px-8 text-[16px] font-semibold text-white transition hover:bg-[#a77627]"
                    >
                        Update
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        if (typeof lucide !== 'undefined') {
            lucide.createIcons();
        }
    </script>
</body>
</html>
