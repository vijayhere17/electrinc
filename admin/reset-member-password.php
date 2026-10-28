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

$successMessage = '';
$errorMessage   = '';

$searchMemberId = trim($_GET['member_id'] ?? '');

$member = null;

if ($searchMemberId !== '') {
    $stmt = $conn->prepare("
        SELECT
            customer_id,
            full_name,
            email,
            mobile
        FROM signup_users
        WHERE customer_id = ?
        LIMIT 1
    ");

    if ($stmt) {
        $stmt->bind_param("s", $searchMemberId);
        $stmt->execute();

        $result = $stmt->get_result();

        if ($result && $result->num_rows > 0) {
            $member = $result->fetch_assoc();
        } else {
            $errorMessage = "Member not found.";
        }

        $stmt->close();
    } else {
        $errorMessage = "Unable to process search request.";
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_password'])) {
    $customerId  = trim($_POST['customer_id'] ?? '');
    $newPassword = trim($_POST['new_password'] ?? '');

    if ($customerId === '') {
        $errorMessage = "Member ID is required.";
    } elseif ($newPassword === '') {
        $errorMessage = "Please enter new password.";
    } elseif (strlen($newPassword) < 6) {
        $errorMessage = "Password must be at least 6 characters.";
    } else {
        $checkStmt = $conn->prepare("
            SELECT customer_id
            FROM signup_users
            WHERE customer_id = ?
            LIMIT 1
        ");

        if ($checkStmt) {
            $checkStmt->bind_param("s", $customerId);
            $checkStmt->execute();

            $checkResult = $checkStmt->get_result();

            if ($checkResult->num_rows === 0) {
                $errorMessage = "Member not found.";
            } else {
                $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

                $updateStmt = $conn->prepare("
                    UPDATE signup_users
                    SET password = ?
                    WHERE customer_id = ?
                    LIMIT 1
                ");

                if ($updateStmt) {
                    $updateStmt->bind_param("ss", $hashedPassword, $customerId);

                    if ($updateStmt->execute()) {
                        $successMessage = "Password reset successfully for Member ID : " . $customerId;

                        $stmt = $conn->prepare("
                            SELECT
                                customer_id,
                                full_name,
                                email,
                                mobile
                            FROM signup_users
                            WHERE customer_id = ?
                            LIMIT 1
                        ");

                        if ($stmt) {
                            $stmt->bind_param("s", $customerId);
                            $stmt->execute();

                            $result = $stmt->get_result();
                            $member = $result->fetch_assoc();

                            $stmt->close();
                        }
                    } else {
                        $errorMessage = "Unable to reset password.";
                    }

                    $updateStmt->close();
                } else {
                    $errorMessage = "Unable to prepare password update query.";
                }
            }

            $checkStmt->close();
        } else {
            $errorMessage = "Unable to verify member.";
        }
    }
}

$navTitle     = "Reset Member Password";
$navIcon      = "key-round";
$managerName  = $_SESSION['full_name'] ?? 'Admin';
$managerRole  = 'Administrator';
$profileImage = "https://i.pravatar.cc/80?img=12";
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
<body class="bg-[#f5f5f5] font-sans overflow-x-hidden">

<?php if (file_exists(__DIR__ . '/includes/sidebar.php')) include __DIR__ . '/includes/sidebar.php'; ?>

<div class="<?php echo file_exists(__DIR__ . '/includes/sidebar.php') ? 'lg:ml-64 min-h-screen' : 'min-h-screen'; ?>">
    <?php if (file_exists(__DIR__ . '/includes/navbar.php')) include __DIR__ . '/includes/navbar.php'; ?>

    <div class="px-3 sm:px-4 md:px-6 lg:px-8 py-4">
        <?php if ($successMessage !== ''): ?>
            <div class="mb-4 rounded-[10px] border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">
                <?= e($successMessage); ?>
            </div>
        <?php endif; ?>

        <?php if ($errorMessage !== ''): ?>
            <div class="mb-4 rounded-[10px] border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                <?= e($errorMessage); ?>
            </div>
        <?php endif; ?>

        <div class="mb-6 sm:mb-8 md:mb-9 flex items-center gap-3 sm:gap-4 md:gap-5">
            <div class="flex h-[40px] w-[40px] sm:h-[44px] sm:w-[44px] shrink-0 items-center justify-center rounded-[10px] bg-[#f6eee2]">
                <i data-lucide="key-round" class="h-5 w-5 text-[#cf9a3c]"></i>
            </div>

            <h2 class="whitespace-nowrap text-[16px] sm:text-[18px] md:text-[20px] font-normal leading-none text-[#3b3329]"
                style="font-family: Georgia, 'Times New Roman', serif;">
                RESET MEMBER PASSWORD
            </h2>

            <div class="h-[2px] w-full bg-[#d1a14b]"></div>
        </div>

        <div class="rounded-xl border border-[#e5e7eb] bg-white p-4 sm:p-5 md:p-6 shadow-sm">
            <form method="GET" class="mb-6 sm:mb-8">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 md:gap-5">
                    <div class="md:col-span-2 lg:col-span-1">
                        <label class="mb-2 sm:mb-3 flex items-center gap-2 sm:gap-3 text-[13px] sm:text-[14px] md:text-[15px] font-semibold uppercase text-[#9e9e9e]">
                            <i data-lucide="search" class="h-[18px] w-[18px] text-[#cf982f]"></i>
                            <span>Member ID</span>
                        </label>

                        <input
                            type="text"
                            name="member_id"
                            value="<?= e($searchMemberId); ?>"
                            placeholder="Enter Member ID"
                            class="h-[48px] sm:h-[52px] md:h-[56px] w-full rounded-[12px] border border-[#d9dde2] bg-[#eef0f3] px-4 sm:px-5 text-[15px] sm:text-[16px] outline-none focus:border-[#c9983c] focus:bg-white"
                        >
                    </div>

                    <div class="flex items-end">
                        <button
                            type="submit"
                            class="h-[48px] sm:h-[52px] md:h-[56px] w-full md:w-auto rounded-[12px] bg-[#12395f] px-6 sm:px-8 text-white text-[14px] sm:text-[15px] font-semibold hover:bg-[#0d2c49] transition"
                        >
                            Search Member
                        </button>
                    </div>
                </div>
            </form>

            <?php if ($member): ?>
                <div class="mb-6 sm:mb-8 rounded-xl border border-[#d9dde2] bg-[#fafafa] p-4 sm:p-5 md:p-6">
                    <h3 class="mb-4 sm:mb-5 text-[16px] sm:text-[17px] md:text-lg font-semibold text-[#12395f]">
                        Member Details
                    </h3>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 sm:gap-5">
                        <div>
                            <label class="text-sm text-gray-500">Member ID</label>
                            <div class="mt-1 font-semibold text-[#12395f] break-words">
                                <?= e($member['customer_id']); ?>
                            </div>
                        </div>

                        <div>
                            <label class="text-sm text-gray-500">Full Name</label>
                            <div class="mt-1 font-semibold break-words">
                                <?= e($member['full_name']); ?>
                            </div>
                        </div>

                        <div>
                            <label class="text-sm text-gray-500">Email</label>
                            <div class="mt-1 break-words">
                                <?= e($member['email']); ?>
                            </div>
                        </div>

                        <div>
                            <label class="text-sm text-gray-500">Mobile</label>
                            <div class="mt-1 break-words">
                                <?= e($member['mobile']); ?>
                            </div>
                        </div>
                    </div>
                </div>

                <form method="POST">
                    <input
                        type="hidden"
                        name="customer_id"
                        value="<?= e($member['customer_id']); ?>"
                    >

                    <div class="w-full max-w-xl">
                        <label class="mb-2 sm:mb-3 flex items-center gap-2 sm:gap-3 text-[13px] sm:text-[14px] md:text-[15px] font-semibold uppercase text-[#9e9e9e]">
                            <i data-lucide="lock" class="h-[18px] w-[18px] text-[#cf982f]"></i>
                            <span>New Password</span>
                        </label>

                        <input
                            type="text"
                            name="new_password"
                            placeholder="Enter New Password"
                            required
                            class="h-[48px] sm:h-[52px] md:h-[56px] w-full rounded-[12px] border border-[#d9dde2] bg-[#eef0f3] px-4 sm:px-5 text-[15px] sm:text-[16px] outline-none focus:border-[#c9983c] focus:bg-white"
                        >
                    </div>

                    <div class="mt-6 sm:mt-8">
                        <button
                            type="submit"
                            name="reset_password"
                            class="h-[50px] sm:h-[54px] w-full sm:w-auto sm:min-w-[220px] rounded-[12px] bg-[#bf8b35] px-6 sm:px-8 text-[15px] sm:text-[16px] font-semibold text-white transition hover:bg-[#a77627]"
                        >
                            Reset Password
                        </button>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }
</script>
</body>
</html>