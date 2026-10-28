<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

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

$adminId = $_SESSION['customer_id'];
$adminName = $_SESSION['full_name'] ?? 'Admin';
$messageId = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($messageId <= 0) {
    header("Location: outbox.php");
    exit();
}

// Fetch the original message from member_messages
$sql = "SELECT * FROM member_messages WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $messageId);
$stmt->execute();
$originalMessage = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$originalMessage) {
    header("Location: outbox.php");
    exit();
}

$successMessage = '';
$errorMessage = '';

// Handle reply submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $replyMessage = trim($_POST['reply_message'] ?? '');
    
    if (empty($replyMessage)) {
        $errorMessage = 'Please enter your reply message.';
    } else {
        // Store reply in member_messages table
        $stmt = $conn->prepare("
            INSERT INTO member_messages 
            (customer_id, from_name, to_name, subject, message_details, folder, thread_id, message_date)
            VALUES (?, ?, ?, ?, ?, 'outbox', ?, NOW())
        ");

        if ($stmt) {
            $fromName = $adminName;
            $toName = trim((string)($originalMessage['to_name'] ?? ''));
            if ($toName === '') {
                $toName = trim((string)($originalMessage['from_name'] ?? ''));
            }
            $subject = 'Re: ' . $originalMessage['subject'];
            $threadId = $originalMessage['id'];
            
            $stmt->bind_param("sssssi", $adminId, $fromName, $toName, $subject, $replyMessage, $threadId);

            if ($stmt->execute()) {
                // Also insert in member's inbox
                $memberCustomerId = '';
                $memberStmt = $conn->prepare("SELECT customer_id FROM signup_users WHERE full_name = ? LIMIT 1");
                if ($memberStmt) {
                    $memberStmt->bind_param("s", $toName);
                    $memberStmt->execute();
                    $memberResult = $memberStmt->get_result();
                    $memberRow = $memberResult ? $memberResult->fetch_assoc() : null;
                    $memberStmt->close();

                    if ($memberRow) {
                        $memberCustomerId = trim((string)($memberRow['customer_id'] ?? ''));
                    }
                }

                if ($memberCustomerId !== '') {
                    $inboxHasToName = columnExists($conn, 'inbox_messages', 'to_name');
                    if ($inboxHasToName) {
                        $stmt2 = $conn->prepare("
                            INSERT INTO inbox_messages 
                            (customer_id, from_name, to_name, subject, message_details, status, message_date)
                            VALUES (?, ?, ?, ?, ?, 'Unread', NOW())
                        ");

                        if ($stmt2) {
                            $stmt2->bind_param("sssss", $memberCustomerId, $fromName, $toName, $subject, $replyMessage);
                            $stmt2->execute();
                            $stmt2->close();
                        }
                    } else {
                        $stmt2 = $conn->prepare("
                            INSERT INTO inbox_messages 
                            (customer_id, from_name, subject, message_details, status, message_date)
                            VALUES (?, ?, ?, ?, 'Unread', NOW())
                        ");

                        if ($stmt2) {
                            $stmt2->bind_param("ssss", $memberCustomerId, $fromName, $subject, $replyMessage);
                            $stmt2->execute();
                            $stmt2->close();
                        }
                    }
                }

                $successMessage = 'Reply sent successfully.';
            } else {
                $errorMessage = 'Failed to send reply.';
            }

            $stmt->close();
        } else {
            $errorMessage = 'Database query preparation failed.';
        }
    }
}

// Fetch all related messages (thread)
$threadMessages = [];

// Add the original message
$threadMessages[] = $originalMessage;

// Also fetch any replies from inbox_messages that came back as responses
$sql = "SELECT * FROM inbox_messages WHERE from_name = ? ORDER BY message_date ASC LIMIT 20";
$stmt = $conn->prepare($sql);
if ($stmt) {
    $receiverName = $originalMessage['to_name'];
    $stmt->bind_param("s", $receiverName);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        // Only add if message date is after original message
        if (strtotime($row['message_date']) > strtotime($originalMessage['message_date'])) {
            $threadMessages[] = $row;
        }
    }
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Message Thread</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        body {
            font-family: Arial, Helvetica, sans-serif;
            background: #f3f3f3;
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
                    <i data-lucide="mail-open" class="w-[18px] h-[18px] text-[#cd9737]"></i>
                </div>

                <h2 class="whitespace-nowrap text-[20px] font-normal text-[#40352a]" style="font-family: Georgia, 'Times New Roman', serif;">MESSAGE THREAD</h2>
            </div>

            <!-- Back button -->
            <a href="outbox.php" class="text-[#1f5fbf] hover:underline font-medium text-[14px] mt-4 inline-flex items-center gap-2">
                <i data-lucide="arrow-left" class="w-4 h-4"></i>
                Back to Outbox
            </a>

            <!-- Threads -->
            <div class="mt-7 space-y-6">
                <?php foreach ($threadMessages as $msg): ?>
                    <div class="rounded-[14px] border border-[#dddddd] bg-white px-7 py-7">
                        <div class="flex items-start justify-between gap-4 mb-5">
                            <div>
                                <div class="text-[15px] font-semibold text-[#1f1f1f]">
                                    <?php 
                                    if (isset($msg['folder']) && $msg['folder'] === 'outbox') {
                                        echo 'Sent Message';
                                    } else {
                                        echo 'Received Message';
                                    }
                                    ?>
                                </div>
                                <div class="text-[12px] text-[#777]">
                                    <?php echo htmlspecialchars(date('Y-m-d h:i A', strtotime($msg['message_date'] ?? 'now'))); ?>
                                </div>
                            </div>
                        </div>

                        <?php if (isset($msg['status'])): ?>
                            <div class="text-[12px] font-semibold px-3 py-1 rounded-full bg-[#f5eee3] text-[#9a6c18] inline-block mb-4">
                                <?php echo htmlspecialchars($msg['status']); ?>
                            </div>
                        <?php endif; ?>

                        <div class="grid grid-cols-[90px_1fr] gap-y-3 text-[14px] text-[#2f2f2f]">
                            <div class="font-semibold">From</div>
                            <div><?php echo htmlspecialchars($msg['from_name'] ?? '-'); ?></div>

                            <div class="font-semibold">To</div>
                            <div><?php echo htmlspecialchars($msg['to_name'] ?? '-'); ?></div>

                            <div class="font-semibold">Subject</div>
                            <div><?php echo htmlspecialchars($msg['subject'] ?? '-'); ?></div>

                            <div class="font-semibold">Message</div>
                            <div class="rounded-[10px] border border-[#ececec] bg-[#fafafa] p-4 leading-7 text-[#444]">
                                <?php echo nl2br(htmlspecialchars($msg['message_details'] ?? '')); ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Reply form -->
            <div class="mt-10 rounded-[14px] border border-[#dddddd] bg-[#f7f7f7] px-7 py-7">
                <h3 class="text-[18px] font-semibold text-[#1f1f1f] mb-5">Send Reply</h3>

                <?php if ($successMessage !== ''): ?>
                    <div class="mb-4 rounded-[8px] border border-green-200 bg-green-50 px-4 py-3 text-[14px] text-green-700">
                        <?php echo htmlspecialchars($successMessage); ?>
                    </div>
                <?php endif; ?>

                <?php if ($errorMessage !== ''): ?>
                    <div class="mb-4 rounded-[8px] border border-red-200 bg-red-50 px-4 py-3 text-[14px] text-red-700">
                        <?php echo htmlspecialchars($errorMessage); ?>
                    </div>
                <?php endif; ?>

                <form action="" method="POST">
                    <div class="space-y-5">
                        <!-- Message Details -->
                        <div>
                            <label class="block text-[15px] font-semibold text-[#1f1f1f] mb-3">
                                Your Reply<span class="text-red-500">*</span>
                            </label>

                            <textarea
                                name="reply_message"
                                class="w-full h-[210px] rounded-[8px] border border-[#eceef1] bg-[#eef0f3] p-4 text-[14px] text-[#555] outline-none resize-none"
                                required
                            ></textarea>
                        </div>
                    </div>

                    <div class="mt-6 flex justify-end gap-3">
                        <button type="button" onclick="window.history.back()" class="px-6 py-2 rounded-[6px] border border-[#d8d8d8] bg-white text-[#1f1f1f] font-semibold text-[14px] hover:bg-[#f5f5f5]">
                            Cancel
                        </button>
                        <button type="submit" class="px-6 py-2 rounded-[6px] bg-[#1f5fbf] text-white font-semibold text-[14px] hover:bg-[#1a4a99]">
                            Send Reply
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
