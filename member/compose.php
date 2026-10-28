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

include __DIR__ . '/config/db.php';

$successMessage = '';
$errorMessage = '';

$sendTo = '';
$subject = '';
$messageDetails = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sendTo = trim($_POST['send_to'] ?? 'Admin');
    $subject = trim($_POST['subject'] ?? '');
    $messageDetails = trim($_POST['message_details'] ?? '');

    if ($subject === '' || $messageDetails === '') {
        $errorMessage = 'Please fill all required fields (Subject and Message).';
    } else {
        // Insert into compose_messages table for legacy compatibility
        $stmt = $conn->prepare("
            INSERT INTO compose_messages (customer_id, send_to, subject, message_details)
            VALUES (?, ?, ?, ?)
        ");

        if ($stmt) {
            $stmt->bind_param("ssss", $customerId, $sendTo, $subject, $messageDetails);
            $stmt->execute();
            $stmt->close();
        }

        // Also insert into member_messages for conversation threading
        $stmt2 = $conn->prepare("
            INSERT INTO member_messages (customer_id, from_name, to_name, subject, message_details, folder, message_date)
            VALUES (?, ?, ?, ?, ?, 'outbox', NOW())
        ");

        if ($stmt2) {
            $fromName = $memberName;
            $toName = $sendTo;
            $stmt2->bind_param("sssss", $customerId, $fromName, $toName, $subject, $messageDetails);

            if ($stmt2->execute()) {
                // Insert into admin's inbox
                $stmt3 = $conn->prepare("
                    INSERT INTO inbox_messages (from_name, subject, message_details, status, message_date)
                    VALUES (?, ?, ?, 'Unread', NOW())
                ");

                if ($stmt3) {
                    $stmt3->bind_param("sss", $fromName, $subject, $messageDetails);
                    $stmt3->execute();
                    $stmt3->close();
                }

                $successMessage = 'Message sent successfully to Admin.';
                $sendTo = 'Admin';
                $subject = '';
                $messageDetails = '';
            } else {
                $errorMessage = 'Failed to save message.';
            }

            $stmt2->close();
        } else {
            $errorMessage = 'Database query preparation failed.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Compose</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        body{
            font-family: Arial, Helvetica, sans-serif;
            background:#f3f3f3;
        }
    </style>
</head>
<body class="bg-[#f3f3f3]">

    <?php include __DIR__ . '/includes/sidebar.php'; ?>

    <div class="lg:ml-64 min-h-screen bg-[#f3f3f3]">
        <?php include __DIR__ . '/includes/navbar.php'; ?>

        <div class="px-3 sm:px-5 py-3">
            <!-- Section title -->
            <div class="mt-5 flex flex-wrap items-center gap-3 sm:gap-4">
                <div class="w-[36px] h-[36px] rounded-[8px] bg-[#f5eee3] flex items-center justify-center shrink-0">
                    <i data-lucide="badge-check" class="w-[18px] h-[18px] text-[#cd9737]"></i>
                </div>

                <h2 class="whitespace-nowrap text-[18px] sm:text-[20px] font-normal text-[#40352a]" style="font-family: Georgia, 'Times New Roman', serif;">
                    COMPOSE
                </h2>

                <div class="hidden sm:block flex-1 h-[2px] bg-[#d1a14b]"></div>
            </div>

            <!-- messages -->
            <?php if ($successMessage !== ''): ?>
                <div class="mt-4 rounded-[8px] border border-green-200 bg-green-50 px-4 py-3 text-[14px] text-green-700">
                    <?php echo htmlspecialchars($successMessage); ?>
                </div>
            <?php endif; ?>

            <?php if ($errorMessage !== ''): ?>
                <div class="mt-4 rounded-[8px] border border-red-200 bg-red-50 px-4 py-3 text-[14px] text-red-700">
                    <?php echo htmlspecialchars($errorMessage); ?>
                </div>
            <?php endif; ?>

            <!-- Compose box -->
            <div class="mt-6 sm:mt-10 rounded-[14px] border border-[#dddddd] bg-[#f7f7f7] px-4 sm:px-7 py-5 sm:py-7">
                <form action="" method="POST">
                    <div class="grid grid-cols-1 sm:grid-cols-[120px_1fr] gap-x-6 gap-y-2 sm:gap-y-5 items-start">
                        <!-- Send To -->
                        <label class="pt-1 sm:pt-3 text-[15px] font-semibold text-[#1f1f1f]">
                            Send To<span class="text-red-500">*</span>
                        </label>
                        <div class="w-full h-[32px] rounded-[8px] border border-[#eceef1] bg-[#eef0f3] px-4 text-[13px] text-[#555] flex items-center">
                            Admin
                        </div>
                        <input
                            type="hidden"
                            name="send_to"
                            value="Admin"
                        >

                        <!-- Subject -->
                        <label class="pt-1 sm:pt-3 text-[15px] font-semibold text-[#1f1f1f]">
                            Subject<span class="text-red-500">*</span>
                        </label>
                        <input
                            type="text"
                            name="subject"
                            value="<?php echo htmlspecialchars($subject); ?>"
                            class="w-full h-[32px] rounded-[8px] border border-[#eceef1] bg-[#eef0f3] px-4 text-[13px] text-[#555] outline-none"
                        >

                        <!-- Message Details -->
                        <label class="pt-1 sm:pt-3 text-[15px] font-semibold text-[#1f1f1f]">
                            Message Details<span class="text-red-500">*</span>
                        </label>

                        <div>
                            <!-- editor toolbar -->
                            <div class="h-[32px] rounded-[8px] border border-[#eceef1] bg-[#eef0f3] px-3 sm:px-5 flex items-center gap-2 sm:gap-4 text-[#232323] text-[13px] overflow-x-auto whitespace-nowrap">
                                <span class="text-[#666] text-[12px]">A Normal text</span>
                                <span class="text-[#222] text-[12px]">Black</span>

                                <span class="w-px h-5 bg-[#bfc3c9] shrink-0"></span>

                                <button type="button" class="text-[18px] leading-none underline shrink-0">U</button>
                                <button type="button" class="text-[18px] leading-none italic font-serif shrink-0">I</button>
                                <button type="button" class="text-[24px] leading-none font-bold shrink-0">B</button>

                                <span class="w-px h-5 bg-[#bfc3c9] shrink-0"></span>

                                <button type="button" class="text-[14px] shrink-0">≣</button>
                                <button type="button" class="text-[14px] shrink-0">≡</button>
                                <button type="button" class="text-[14px] shrink-0">Ξ</button>

                                <span class="w-px h-5 bg-[#bfc3c9] shrink-0"></span>

                                <i data-lucide="paperclip" class="w-[14px] h-[14px] shrink-0"></i>
                                <i data-lucide="link" class="w-[14px] h-[14px] shrink-0"></i>
                                <i data-lucide="image" class="w-[14px] h-[14px] shrink-0"></i>
                            </div>

                            <!-- editor body -->
                            <textarea
                                name="message_details"
                                class="mt-4 w-full h-[210px] rounded-[8px] border border-[#eceef1] bg-[#eef0f3] p-4 text-[14px] text-[#555] outline-none resize-none"
                            ><?php echo htmlspecialchars($messageDetails); ?></textarea>
                        </div>
                    </div>

                    <!-- send button -->
                    <div class="flex justify-center mt-5">
                        <button
                            type="submit"
                            class="w-full sm:w-auto h-[40px] sm:h-[36px] px-5 rounded-[8px] bg-[#2d78d0] hover:bg-[#2467b4] text-white text-[14px] font-semibold flex items-center justify-center gap-2"
                        >
                            <i data-lucide="send" class="w-[14px] h-[14px]"></i>
                            Send Message
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        lucide.createIcons();
    </script>
</body>
</html>