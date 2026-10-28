<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['customer_id']) && !isset($_SESSION['member_id'])) {
    header("Location: login.php");
    exit();
}

include __DIR__ . '/config/db.php';

$customerId = $_SESSION['customer_id'] ?? $_SESSION['member_id'] ?? '';
$memberName = $_SESSION['full_name'] ?? 'Member';
$messageId  = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($messageId <= 0) {
    header("Location: inbox.php");
    exit();
}

function tableExists(mysqli $conn, string $table): bool
{
    $dbRes = $conn->query("SELECT DATABASE() AS db");
    $dbRow = $dbRes ? $dbRes->fetch_assoc() : [];
    $db = $dbRow['db'] ?? '';

    $sql = "
        SELECT COUNT(*) AS total
        FROM INFORMATION_SCHEMA.TABLES
        WHERE TABLE_SCHEMA = ?
        AND TABLE_NAME = ?
    ";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $db, $table);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return ((int)($row['total'] ?? 0)) > 0;
}

function columnExists(mysqli $conn, string $table, string $column): bool
{
    $dbRes = $conn->query("SELECT DATABASE() AS db");
    $dbRow = $dbRes ? $dbRes->fetch_assoc() : [];
    $db = $dbRow['db'] ?? '';

    $sql = "
        SELECT COUNT(*) AS total
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = ?
        AND TABLE_NAME = ?
        AND COLUMN_NAME = ?
    ";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sss", $db, $table, $column);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return ((int)($row['total'] ?? 0)) > 0;
}

if (!tableExists($conn, 'inbox_messages')) {
    $conn->query("
        CREATE TABLE inbox_messages (
            id INT AUTO_INCREMENT PRIMARY KEY,
            customer_id VARCHAR(50) NULL,
            from_name VARCHAR(150) NOT NULL DEFAULT 'Administrator',
            to_name VARCHAR(150) NULL,
            subject VARCHAR(255) NOT NULL,
            message_details TEXT NULL,
            message_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            status VARCHAR(30) NOT NULL DEFAULT 'Unread'
        )
    ");
}

if (!tableExists($conn, 'member_messages')) {
    $conn->query("
        CREATE TABLE member_messages (
            id INT AUTO_INCREMENT PRIMARY KEY,
            customer_id VARCHAR(50) NOT NULL,
            from_name VARCHAR(150) NOT NULL,
            to_name VARCHAR(150) NOT NULL,
            subject VARCHAR(255) NOT NULL,
            message_details TEXT NULL,
            folder VARCHAR(30) NOT NULL DEFAULT 'outbox',
            thread_id INT NULL,
            message_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        )
    ");
}

$hasCustomerId     = columnExists($conn, 'inbox_messages', 'customer_id');
$hasFromName       = columnExists($conn, 'inbox_messages', 'from_name');
$hasSenderName     = columnExists($conn, 'inbox_messages', 'sender_name');
$hasToName         = columnExists($conn, 'inbox_messages', 'to_name');
$hasSubject        = columnExists($conn, 'inbox_messages', 'subject');
$hasTitle          = columnExists($conn, 'inbox_messages', 'title');
$hasMessageDetails = columnExists($conn, 'inbox_messages', 'message_details');
$hasMessage        = columnExists($conn, 'inbox_messages', 'message');
$hasDate           = columnExists($conn, 'inbox_messages', 'message_date');
$hasCreatedAt      = columnExists($conn, 'inbox_messages', 'created_at');
$hasStatus         = columnExists($conn, 'inbox_messages', 'status');

$fromCol    = $hasFromName ? "from_name" : ($hasSenderName ? "sender_name" : "'Administrator'");
$toCol      = $hasToName ? "to_name" : "''";
$subjectCol = $hasSubject ? "subject" : ($hasTitle ? "title" : "''");
$msgCol     = $hasMessageDetails ? "message_details" : ($hasMessage ? "message" : "''");
$dateCol    = $hasDate ? "message_date" : ($hasCreatedAt ? "created_at" : "NOW()");
$statusCol  = $hasStatus ? "status" : "'Unread'";

if ($hasCustomerId) {
    $sql = "
        SELECT 
            id,
            customer_id,
            $fromCol AS from_name,
            $toCol AS to_name,
            $subjectCol AS subject,
            $msgCol AS message_details,
            $dateCol AS message_date,
            $statusCol AS status
        FROM inbox_messages
        WHERE id = ? AND customer_id = ?
        LIMIT 1
    ";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("is", $messageId, $customerId);
} else {
    $sql = "
        SELECT 
            id,
            $fromCol AS from_name,
            $toCol AS to_name,
            $subjectCol AS subject,
            $msgCol AS message_details,
            $dateCol AS message_date,
            $statusCol AS status
        FROM inbox_messages
        WHERE id = ?
        LIMIT 1
    ";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $messageId);
}

$stmt->execute();
$originalMessage = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$originalMessage) {
    header("Location: inbox.php");
    exit();
}

if ($hasStatus) {
    $stmt = $conn->prepare("UPDATE inbox_messages SET status = 'Read' WHERE id = ?");
    $stmt->bind_param("i", $messageId);
    $stmt->execute();
    $stmt->close();
}

$successMessage = '';
$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $replyMessage = trim($_POST['reply_message'] ?? '');

    if ($replyMessage === '') {
        $errorMessage = 'Please enter your reply message.';
    } else {
        $cleanSubject = preg_replace('/^Re:\s*/i', '', $originalMessage['subject'] ?? '');
        $subject = 'Re: ' . $cleanSubject;
        $toName = $originalMessage['from_name'] ?? 'Administrator';

        $stmt = $conn->prepare("
            INSERT INTO member_messages
            (customer_id, from_name, to_name, subject, message_details, folder, thread_id, message_date)
            VALUES (?, ?, ?, ?, ?, 'outbox', ?, NOW())
        ");

        if ($stmt) {
            $stmt->bind_param("sssssi", $customerId, $memberName, $toName, $subject, $replyMessage, $messageId);

            if ($stmt->execute()) {
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

$threadMessages = [];

$threadMessages[] = [
    'from_name' => $originalMessage['from_name'] ?? '-',
    'to_name' => $originalMessage['to_name'] ?: $memberName,
    'subject' => $originalMessage['subject'] ?? '-',
    'message_details' => $originalMessage['message_details'] ?? '',
    'message_date' => $originalMessage['message_date'] ?? date('Y-m-d H:i:s'),
    'folder' => 'inbox',
    'status' => 'Read'
];

$stmt = $conn->prepare("
    SELECT 
        from_name,
        to_name,
        subject,
        message_details,
        folder,
        thread_id,
        message_date
    FROM member_messages
    WHERE customer_id = ? AND thread_id = ?
    ORDER BY message_date ASC, id ASC
");

$stmt->bind_param("si", $customerId, $messageId);
$stmt->execute();
$res = $stmt->get_result();

while ($row = $res->fetch_assoc()) {
    $threadMessages[] = $row;
}

$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Message Thread</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
</head>

<body class="bg-[#f3f3f3] font-sans">

<?php include __DIR__ . '/includes/sidebar.php'; ?>

<div class="ml-64 min-h-screen bg-[#f3f3f3]">
    <?php include __DIR__ . '/includes/navbar.php'; ?>

    <div class="px-5 py-3">

        <div class="mt-5 flex items-center gap-4">
            <div class="w-[36px] h-[36px] rounded-[8px] bg-[#f5eee3] flex items-center justify-center shrink-0">
                <i data-lucide="mail-open" class="w-[18px] h-[18px] text-[#cd9737]"></i>
            </div>

            <h2 class="whitespace-nowrap text-[20px] font-normal text-[#40352a]" style="font-family: Georgia, 'Times New Roman', serif;">
                MESSAGE THREAD
            </h2>

            <div class="w-full h-[2px] bg-[#d1a14b]"></div>
        </div>

        <div class="mt-5">
            <a href="inbox.php" class="text-[#2d78d0] hover:underline text-[14px] font-medium flex items-center gap-1">
                <i data-lucide="arrow-left" class="w-4 h-4"></i>
                Back to Inbox
            </a>
        </div>

        <div class="mt-8 space-y-5">
            <?php foreach ($threadMessages as $msg): ?>
                <div class="rounded-[14px] border border-[#dddddd] bg-white p-5">
                    <div class="flex items-center justify-between mb-4">
                        <div class="flex items-center gap-3">
                            <div class="w-[34px] h-[34px] rounded-[8px] bg-[#f5eee3] flex items-center justify-center">
                                <?php if (($msg['folder'] ?? '') === 'outbox'): ?>
                                    <i data-lucide="send" class="w-[16px] h-[16px] text-[#cd9737]"></i>
                                <?php else: ?>
                                    <i data-lucide="inbox" class="w-[16px] h-[16px] text-[#cd9737]"></i>
                                <?php endif; ?>
                            </div>

                            <div>
                                <div class="text-[15px] font-semibold text-[#2f2f2f]">
                                    <?php echo (($msg['folder'] ?? '') === 'outbox') ? 'Sent Message' : 'Received Message'; ?>
                                </div>
                                <div class="text-[12px] text-[#777]">
                                    <?php echo htmlspecialchars(date('Y-m-d h:i A', strtotime($msg['message_date'] ?? 'now'))); ?>
                                </div>
                            </div>
                        </div>

                        <?php if (isset($msg['status'])): ?>
                            <div class="text-[12px] font-semibold px-3 py-1 rounded-full bg-[#f5eee3] text-[#9a6c18]">
                                <?php echo htmlspecialchars($msg['status']); ?>
                            </div>
                        <?php endif; ?>
                    </div>

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
                <label class="block text-[15px] font-semibold text-[#1f1f1f] mb-3">
                    Your Reply<span class="text-red-500">*</span>
                </label>

                <textarea
                    name="reply_message"
                    class="w-full h-[210px] rounded-[8px] border border-[#eceef1] bg-[#eef0f3] p-4 text-[14px] text-[#555] outline-none resize-none"
                    required
                ></textarea>

                <div class="flex justify-center mt-5">
                    <button type="submit"
                        class="h-[36px] px-5 rounded-[8px] bg-[#2d78d0] hover:bg-[#2467b4] text-white text-[14px] font-semibold flex items-center gap-2">
                        <i data-lucide="send" class="w-[14px] h-[14px]"></i>
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