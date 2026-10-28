<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['customer_id']) && !isset($_SESSION['member_id'])) {
    header("Location: login.php");
    exit();
}

include __DIR__ . '/config/db.php';

$customerId = $_SESSION['customer_id'] ?? $_SESSION['member_id'];
$threadId = isset($_GET['thread_id']) ? (int)$_GET['thread_id'] : 0;
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($threadId <= 0 && $id <= 0) {
    echo "Invalid thread.";
    exit;
}

if ($threadId > 0) {
    $sql = "
        SELECT id, folder, from_name, to_name, subject, message_details, message_date, status, thread_id
        FROM member_messages
        WHERE customer_id = ? 
        AND (thread_id = ? OR id = ?)
        ORDER BY message_date ASC, id ASC
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sii", $customerId, $threadId, $threadId);
} else {
    $sql = "
        SELECT id, folder, from_name, to_name, subject, message_details, message_date, status, thread_id
        FROM member_messages
        WHERE customer_id = ? 
        AND id = ?
        ORDER BY message_date ASC, id ASC
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("si", $customerId, $id);
}

$stmt->execute();
$result = $stmt->get_result();

$messages = [];
while ($row = $result->fetch_assoc()) {
    $messages[] = $row;
}
$stmt->close();

if (count($messages) === 0) {
    echo "No messages found.";
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Outbox Details</title>
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
                <i data-lucide="badge-check" class="w-[18px] h-[18px] text-[#cd9737]"></i>
            </div>

            <h2 class="whitespace-nowrap text-[20px] font-normal text-[#40352a]" style="font-family: Georgia, 'Times New Roman', serif;">
                OUTBOX MESSAGE
            </h2>

            <div class="w-full h-[2px] bg-[#d1a14b]"></div>
        </div>

        <div class="mt-5">
            <a href="outbox.php" class="text-[#1f5fbf] hover:underline text-[14px] font-medium">
                Back to Outbox
            </a>
        </div>

        <div class="mt-8 space-y-5">
            <?php foreach ($messages as $msg): ?>
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
                                    <?php echo (($msg['folder'] ?? '') === 'outbox') ? 'Sent Message' : 'Reply Message'; ?>
                                </div>

                                <div class="text-[12px] text-[#777]">
                                    <?php echo htmlspecialchars(date('Y-m-d h:i A', strtotime($msg['message_date']))); ?>
                                </div>
                            </div>
                        </div>

                        <div class="text-[12px] font-semibold px-3 py-1 rounded-full bg-[#f5eee3] text-[#9a6c18]">
                            <?php echo htmlspecialchars($msg['status'] ?? 'Unread'); ?>
                        </div>
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
                            <?php echo nl2br(htmlspecialchars($msg['message_details'] ?? '-')); ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

    </div>
</div>

<script>
    lucide.createIcons();
</script>

</body>
</html>