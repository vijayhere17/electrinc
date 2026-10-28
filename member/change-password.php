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

$memberName   = $_SESSION['full_name'] ?? "Member";
$customerId   = $_SESSION['customer_id'] ?? $_SESSION['member_id'];
$managerName  = "Swapnil Solanki";
$managerRole  = "MLM Manager";
$profileImage = "https://i.pravatar.cc/80?img=12";
$navTitle     = "My Profile";
$navIcon      = "user-round";

$success = false;
$error = "";

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

if (!columnExists($conn, 'signup_users', 'password')) {
    $conn->query("ALTER TABLE signup_users ADD COLUMN password VARCHAR(255) NOT NULL");
}

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
        $stmt = $conn->prepare("SELECT password FROM signup_users WHERE customer_id = ? LIMIT 1");

        if (!$stmt) {
            $error = "SQL Error: " . $conn->error;
        } else {
            $stmt->bind_param("s", $customerId);
            $stmt->execute();
            $user = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$user) {
                $error = "User not found.";
            } else {
                $dbPassword = $user['password'] ?? '';
                $currentOk = false;

                if (password_verify($currentPassword, $dbPassword)) {
                    $currentOk = true;
                }

                if (!$currentOk && $currentPassword === $dbPassword) {
                    $currentOk = true;
                }

                if (!$currentOk) {
                    $error = "Current password is incorrect.";
                } else {
                    $newHashPassword = password_hash($newPassword, PASSWORD_DEFAULT);

                    $stmt = $conn->prepare("UPDATE signup_users SET password = ? WHERE customer_id = ? LIMIT 1");

                    if (!$stmt) {
                        $error = "SQL Error: " . $conn->error;
                    } else {
                        $stmt->bind_param("ss", $newHashPassword, $customerId);

                        if ($stmt->execute()) {
                            $success = true;
                        } else {
                            $error = "Password not updated. Please try again.";
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