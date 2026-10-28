<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['customer_id'])) {
    header("Location: login.php");
    exit();
}

$memberName   = $_SESSION['full_name'] ?? "Member";
$customerId   = $_SESSION['customer_id'];
$managerName  = "Swapnil Solanki";
$managerRole  = "MLM Manager";
$profileImage = "https://i.pravatar.cc/80?img=12";
$navTitle     = "My Profile";
$navIcon      = "user-round";

$success = false;
$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $currentPassword = trim($_POST['current_transaction_password'] ?? '');
    $newPassword     = trim($_POST['new_transaction_password'] ?? '');
    $confirmPassword = trim($_POST['confirm_transaction_password'] ?? '');

    if ($currentPassword === '' || $newPassword === '' || $confirmPassword === '') {
        $error = "All fields are required.";
    } elseif ($newPassword !== $confirmPassword) {
        $error = "New transaction password and confirm transaction password do not match.";
    } else {
        $success = true;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Change Transaction Password</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body class="bg-[#f5f5f5] font-sans">

    <?php include __DIR__ . '/includes/sidebar.php'; ?>

    <div class="ml-64 min-h-screen">
        <?php include __DIR__ . '/includes/navbar.php'; ?>

        <div class="px-8 py-4">
            <!-- Top heading -->
            <!-- <div class="flex items-center gap-3 text-[#203a5f]">
                <i data-lucide="panel-left-close" class="w-[18px] h-[18px] text-[#717784]"></i>
                <h1 class="text-[22px] font-semibold leading-none">My Profile</h1>
            </div>

            <div class="mt-2 text-[13px] font-semibold uppercase text-[#b8b8b8]">
                Customer ID: <?php echo htmlspecialchars($customerId); ?>
            </div> -->

            <?php if ($success): ?>
                <div class="mt-5 rounded-[10px] border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">
                    Transaction password updated successfully.
                </div>
            <?php endif; ?>

            <?php if ($error !== ""): ?>
                <div class="mt-5 rounded-[10px] border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <form method="POST" class="mt-6">
                <!-- Section title -->
                <section>
                    <div class="mb-9 flex items-center gap-5">
                        <div class="flex h-[44px] w-[44px] shrink-0 items-center justify-center rounded-[10px] bg-[#f6eee2]">
                            <i data-lucide="badge-check" class="h-5 w-5 text-[#cf9a3c]"></i>
                        </div>

                        <h2 class="whitespace-nowrap text-[20px] font-normal leading-none text-[#3b3329]" style="font-family: Georgia, 'Times New Roman', serif;">
                            CHANGE TRANSACTION PASSWORD
                        </h2>

                        <div class="h-[2px] w-full bg-[#d1a14b]"></div>
                    </div>

                    <div class="space-y-5">
                        <!-- Current Transaction Password -->
                        <div>
                            <label class="mb-3 flex items-center gap-3 text-[15px] font-semibold uppercase text-[#9e9e9e]">
                                <i data-lucide="ellipsis" class="h-[18px] w-[18px] text-[#cf982f]"></i>
                                <span>CURRENT TRANSACTION PASSWORD <span class="text-red-400">*</span></span>
                            </label>
                            <input
                                type="password"
                                name="current_transaction_password"
                                placeholder="Enter Password"
                                class="h-[56px] w-full rounded-[12px] border border-[#d9dde2] bg-[#eef0f3] px-5 text-[16px] text-[#5b6470] outline-none placeholder:text-[#72777f] focus:border-[#c9983c] focus:bg-white"
                            >
                        </div>

                        <!-- New Transaction Password -->
                        <div>
                            <label class="mb-3 flex items-center gap-3 text-[15px] font-semibold uppercase text-[#9e9e9e]">
                                <i data-lucide="ellipsis" class="h-[18px] w-[18px] text-[#cf982f]"></i>
                                <span>NEW TRANSACTION PASSWORD <span class="text-red-400">*</span></span>
                            </label>
                            <input
                                type="password"
                                name="new_transaction_password"
                                placeholder="Enter Password"
                                class="h-[56px] w-full rounded-[12px] border border-[#d9dde2] bg-[#eef0f3] px-5 text-[16px] text-[#5b6470] outline-none placeholder:text-[#72777f] focus:border-[#c9983c] focus:bg-white"
                            >
                        </div>

                        <!-- Confirm Transaction Password -->
                        <div>
                            <label class="mb-3 flex items-center gap-3 text-[15px] font-semibold uppercase text-[#9e9e9e]">
                                <i data-lucide="ellipsis" class="h-[18px] w-[18px] text-[#cf982f]"></i>
                                <span>CONFIRM TRANSACTION PASSWORD <span class="text-red-400">*</span></span>
                            </label>
                            <input
                                type="password"
                                name="confirm_transaction_password"
                                placeholder="Enter Password"
                                class="h-[56px] w-full rounded-[12px] border border-[#d9dde2] bg-[#eef0f3] px-5 text-[16px] text-[#5b6470] outline-none placeholder:text-[#72777f] focus:border-[#c9983c] focus:bg-white"
                            >
                        </div>
                    </div>
                </section>

                <!-- Update button -->
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