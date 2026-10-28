<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check admin authentication
if (!isset($_SESSION['customer_id'])) {
    header("Location: login.php");
    exit();
}

include __DIR__ . '/config/db.php';

function columnExists(mysqli $conn, string $table, string $column): bool
{
    $dbRes = $conn->query("SELECT DATABASE() AS db");
    $dbRow = $dbRes ? $dbRes->fetch_assoc() : [];
    $db = $dbRow['db'] ?? '';

    $stmt = $conn->prepare("
        SELECT COUNT(*) AS total
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = ?
          AND TABLE_NAME = ?
          AND COLUMN_NAME = ?
    ");
    $stmt->bind_param("sss", $db, $table, $column);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return (int)($row['total'] ?? 0) > 0;
}

// Get admin info from session
$adminId = $_SESSION['customer_id'];
$adminName = $_SESSION['full_name'] ?? 'Admin';
$managerName = $_SESSION['full_name'] ?? 'Admin';
$managerRole = 'Administrator';
$profileImage = $_SESSION['profile_image'] ?? 'https://i.pravatar.cc/80?img=12';

$successMessage = '';
$errorMessage = '';

$sendTo = '';
$subject = '';
$messageDetails = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sendTo = trim($_POST['send_to'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $messageDetails = trim($_POST['message_details'] ?? '');

    if ($sendTo === '' || $subject === '' || $messageDetails === '') {
        $errorMessage = 'Please fill all required fields.';
    } else {
        $recipientCustomerId = '';
        $recipientName = $sendTo;

        $recipientStmt = $conn->prepare("SELECT customer_id, full_name FROM signup_users WHERE customer_id = ? OR full_name = ? LIMIT 1");
        if ($recipientStmt) {
            $recipientStmt->bind_param("ss", $sendTo, $sendTo);
            $recipientStmt->execute();
            $recipientResult = $recipientStmt->get_result();
            $recipientRow = $recipientResult ? $recipientResult->fetch_assoc() : null;
            $recipientStmt->close();

            if ($recipientRow) {
                $recipientCustomerId = trim((string)($recipientRow['customer_id'] ?? ''));
                $recipientName = trim((string)($recipientRow['full_name'] ?? $sendTo));
            }
        }

        if ($recipientCustomerId === '') {
            $errorMessage = 'Recipient not found.';
        } else {
        // Insert reply to member
        $stmt = $conn->prepare("
            INSERT INTO member_messages (customer_id, from_name, to_name, subject, message_details, folder, thread_id)
            VALUES (?, ?, ?, ?, ?, 'outbox', ?)
        ");

        if ($stmt) {
            $toName = $sendTo;
            $fromName = $adminName;
            $threadId = isset($_POST['thread_id']) ? (int) $_POST['thread_id'] : 0;
            
            $stmt->bind_param("sssssi", $adminId, $fromName, $toName, $subject, $messageDetails, $threadId);

            if ($stmt->execute()) {
                // Also insert in inbox_messages for the member receiving it
                $inboxHasToName = columnExists($conn, 'inbox_messages', 'to_name');
                if ($inboxHasToName) {
                    $stmt2 = $conn->prepare("
                        INSERT INTO inbox_messages (customer_id, from_name, to_name, subject, message_details, status, message_date)
                        VALUES (?, ?, ?, ?, ?, 'Unread', NOW())
                    ");

                    if ($stmt2) {
                        $stmt2->bind_param("sssss", $recipientCustomerId, $fromName, $recipientName, $subject, $messageDetails);
                        $stmt2->execute();
                        $stmt2->close();
                    }
                } else {
                    $stmt2 = $conn->prepare("
                        INSERT INTO inbox_messages (customer_id, from_name, subject, message_details, status, message_date)
                        VALUES (?, ?, ?, ?, 'Unread', NOW())
                    ");

                    if ($stmt2) {
                        $stmt2->bind_param("ssss", $recipientCustomerId, $fromName, $subject, $messageDetails);
                        $stmt2->execute();
                        $stmt2->close();
                    }
                }
                
                $successMessage = 'Message sent successfully.';
                $sendTo = '';
                $subject = '';
                $messageDetails = '';
            } else {
                $errorMessage = 'Failed to save message.';
            }

            $stmt->close();
        } else {
            $errorMessage = 'Database query preparation failed.';
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

    <div class="ml-64 min-h-screen bg-[#f3f3f3]">
        <?php include __DIR__ . '/includes/navbar.php'; ?>

        <div class="px-5 py-3">
            <!-- Section title -->
            <div class="mt-5 flex items-center gap-4">
                <div class="w-[36px] h-[36px] rounded-[8px] bg-[#f5eee3] flex items-center justify-center shrink-0">
                    <i data-lucide="badge-check" class="w-[18px] h-[18px] text-[#cd9737]"></i>
                </div>

                <h2 class="whitespace-nowrap text-[20px] font-normal text-[#40352a]" style="font-family: Georgia, 'Times New Roman', serif;">
                    COMPOSE
                </h2>

                <div class="w-full h-[2px] bg-[#d1a14b]"></div>
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
            <div class="mt-10 rounded-[14px] border border-[#dddddd] bg-[#f7f7f7] px-7 py-7">
                <form action="" method="POST">
                    <div class="grid grid-cols-[120px_1fr] gap-x-6 gap-y-5 items-start">
                        <!-- Send To -->
                        <label class="pt-3 text-[15px] font-semibold text-[#1f1f1f]">
                            Send To<span class="text-red-500">*</span>
                        </label>
                        <input
                            type="text"
                            name="send_to"
                            value="<?php echo htmlspecialchars($sendTo); ?>"
                            class="w-full h-[32px] rounded-[8px] border border-[#eceef1] bg-[#eef0f3] px-4 text-[13px] text-[#555] outline-none"
                        >

                        <!-- Subject -->
                        <label class="pt-3 text-[15px] font-semibold text-[#1f1f1f]">
                            Subject<span class="text-red-500">*</span>
                        </label>
                        <input
                            type="text"
                            name="subject"
                            value="<?php echo htmlspecialchars($subject); ?>"
                            class="w-full h-[32px] rounded-[8px] border border-[#eceef1] bg-[#eef0f3] px-4 text-[13px] text-[#555] outline-none"
                        >

                        <!-- Message Details -->
                        <label class="pt-3 text-[15px] font-semibold text-[#1f1f1f]">
                            Message Details<span class="text-red-500">*</span>
                        </label>

                        <div>
                            <!-- editor toolbar -->
                            <div class="h-[32px] rounded-[8px] border border-[#eceef1] bg-[#eef0f3] px-5 flex items-center gap-4 text-[#232323] text-[13px]">
                                <span class="text-[#666] text-[12px]">A Normal text</span>
                                <span class="text-[#222] text-[12px]">Black</span>

                                <span class="w-px h-5 bg-[#bfc3c9]"></span>

                                <button type="button" class="text-[18px] leading-none underline">U</button>
                                <button type="button" class="text-[18px] leading-none italic font-serif">I</button>
                                <button type="button" class="text-[24px] leading-none font-bold">B</button>

                                <span class="w-px h-5 bg-[#bfc3c9]"></span>

                                <button type="button" class="text-[14px]">≣</button>
                                <button type="button" class="text-[14px]">≡</button>
                                <button type="button" class="text-[14px]">Ξ</button>

                                <span class="w-px h-5 bg-[#bfc3c9]"></span>

                                <i data-lucide="paperclip" class="w-[14px] h-[14px]"></i>
                                <i data-lucide="link" class="w-[14px] h-[14px]"></i>
                                <i data-lucide="image" class="w-[14px] h-[14px]"></i>
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
                            class="h-[36px] px-5 rounded-[8px] bg-[#2d78d0] hover:bg-[#2467b4] text-white text-[14px] font-semibold flex items-center gap-2"
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