<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config/db.php';

if (!isset($conn) || !($conn instanceof mysqli)) {
    die("Database connection is not working. Please check config/db.php");
}

$conn->set_charset("utf8mb4");

function tableExists(mysqli $conn, string $tableName): bool {
    $safeName = $conn->real_escape_string($tableName);
    $result = $conn->query("SHOW TABLES LIKE '{$safeName}'");
    return $result instanceof mysqli_result && $result->num_rows > 0;
}

function getExistingColumns(mysqli $conn, string $tableName): array {
    $columns = [];
    $result = $conn->query("SHOW COLUMNS FROM `{$tableName}`");
    if (!($result instanceof mysqli_result)) {
        return $columns;
    }

    while ($row = $result->fetch_assoc()) {
        if (isset($row['Field'])) {
            $columns[] = $row['Field'];
        }
    }

    return $columns;
}

function findAccountByCustomerId(mysqli $conn, string $customerId, array $tables): ?array {
    foreach ($tables as $tableName) {
        if (!tableExists($conn, $tableName)) {
            continue;
        }

        $existingColumns = getExistingColumns($conn, $tableName);
        if (!in_array('customer_id', $existingColumns, true) || !in_array('password', $existingColumns, true)) {
            continue;
        }

        $nameColumn = in_array('full_name', $existingColumns, true)
            ? 'full_name'
            : (in_array('name', $existingColumns, true) ? 'name' : 'customer_id');

        $sql = "SELECT id, customer_id, {$nameColumn} AS full_name, password FROM `{$tableName}` WHERE customer_id = ? LIMIT 1";
        $stmt = $conn->prepare($sql);

        if (!$stmt) {
            continue;
        }

        $stmt->bind_param('s', $customerId);
        $stmt->execute();
        $result = $stmt->get_result();
        $account = ($result && $result->num_rows === 1) ? $result->fetch_assoc() : null;
        $stmt->close();

        if ($account) {
            return [
                'table' => $tableName,
                'user' => $account,
            ];
        }
    }

    return null;
}

/*
|--------------------------------------------------------------------------
| Accept both session names so page does not redirect wrongly
|--------------------------------------------------------------------------
*/
$sessionCustomerId = $_SESSION['customer_id'] ?? $_SESSION['member_id'] ?? '';

if ($sessionCustomerId === '') {
    header("Location: login.php");
    exit();
}

/* keep session unified */
if (!isset($_SESSION['customer_id']) && isset($_SESSION['member_id'])) {
    $_SESSION['customer_id'] = $_SESSION['member_id'];
}
if (!isset($_SESSION['member_id']) && isset($_SESSION['customer_id'])) {
    $_SESSION['member_id'] = $_SESSION['customer_id'];
}

$memberName   = $_SESSION['full_name'] ?? "Member";
$customerId   = $_SESSION['customer_id'] ?? $_SESSION['member_id'];
$managerName  = "Swapnil Solanki";
$managerRole  = "MLM Manager";
$profileImage = "https://i.pravatar.cc/80?img=12";
$navTitle     = "Change Password";
$navIcon      = "key-round";

$success = false;
$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $currentPassword = trim($_POST['current_password'] ?? '');
    $newPassword     = trim($_POST['new_password'] ?? '');
    $confirmPassword = trim($_POST['confirm_password'] ?? '');

    if ($currentPassword === '' || $newPassword === '' || $confirmPassword === '') {
        $error = "All fields are required.";
    } elseif (strlen($newPassword) < 6) {
        $error = "New password must be at least 6 characters.";
    } elseif ($newPassword !== $confirmPassword) {
        $error = "New password and confirm password do not match.";
    } else {
        $account = findAccountByCustomerId($conn, $customerId, ['signup_admin', 'signup_users']);

        if ($account) {
            $tableName = $account['table'];
            $user = $account['user'];
            $dbPassword = $user['password'] ?? '';

            $isCurrentPasswordValid = false;

            if ($dbPassword !== '') {
                if (password_verify($currentPassword, $dbPassword)) {
                    $isCurrentPasswordValid = true;
                } elseif ($currentPassword === $dbPassword) {
                    // fallback for old plain-text passwords already stored
                    $isCurrentPasswordValid = true;
                }
            }

            if (!$isCurrentPasswordValid) {
                $error = "Current password is incorrect.";
            } elseif ($currentPassword === $newPassword) {
                $error = "New password must be different from current password.";
            } else {
                $newHashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                $updateSql = "UPDATE `{$tableName}` SET password = ? WHERE customer_id = ? LIMIT 1";
                $updateStmt = $conn->prepare($updateSql);

                if (!$updateStmt) {
                    $error = "Database prepare failed: " . $conn->error;
                } else {
                    $updateStmt->bind_param("ss", $newHashedPassword, $customerId);

                    if ($updateStmt->execute()) {
                        if ($updateStmt->affected_rows >= 0) {
                            $success = true;
                        } else {
                            $error = "Password update failed.";
                        }
                    } else {
                        $error = "Password update failed: " . $updateStmt->error;
                    }

                    $updateStmt->close();
                }
            }
        } else {
            $error = "User not found.";
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
        lucide.createIcons();
    </script>
</body>
</html>