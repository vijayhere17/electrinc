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
$memberName = $_SESSION['full_name'] ?? 'Member';

function tableExists($conn, $table) {
    $db = $conn->query("SELECT DATABASE() AS db")->fetch_assoc()['db'] ?? '';
    $stmt = $conn->prepare("
        SELECT COUNT(*) AS total
        FROM INFORMATION_SCHEMA.TABLES
        WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?
    ");
    $stmt->bind_param("ss", $db, $table);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return (int)($row['total'] ?? 0) > 0;
}

function columnExists($conn, $table, $column) {
    $db = $conn->query("SELECT DATABASE() AS db")->fetch_assoc()['db'] ?? '';
    $stmt = $conn->prepare("
        SELECT COUNT(*) AS total
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?
    ");
    $stmt->bind_param("sss", $db, $table, $column);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return (int)($row['total'] ?? 0) > 0;
}

if (!tableExists($conn, 'member_messages')) {
    $conn->query("
        CREATE TABLE member_messages (
            id INT AUTO_INCREMENT PRIMARY KEY,
            customer_id VARCHAR(50) NOT NULL,
            folder VARCHAR(30) NOT NULL DEFAULT 'outbox',
            from_name VARCHAR(150) NOT NULL,
            to_name VARCHAR(150) NULL,
            subject VARCHAR(255) NOT NULL,
            message_details TEXT NULL,
            thread_id INT NULL,
            message_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            status VARCHAR(30) NOT NULL DEFAULT 'Unread'
        )
    ");
}

$messages = [];

/*
    Fetch received messages dynamically:
    - Any member_messages row that is not an outbox message
    - Any inbox_messages row belonging to the current customer
*/
$stmt = $conn->prepare("
    SELECT 
        id,
        IFNULL(thread_id, id) AS thread_id,
        from_name,
        to_name,
        subject,
        message_date,
        status,
        'member_messages' AS source_table
    FROM member_messages
    WHERE customer_id = ?
            AND LOWER(TRIM(COALESCE(folder, ''))) <> 'outbox'
    ORDER BY message_date DESC, id DESC
");

$stmt->bind_param("s", $customerId);
$stmt->execute();
$res = $stmt->get_result();

while ($row = $res->fetch_assoc()) {
    $messages[] = $row;
}
$stmt->close();

/* Fetch old admin messages from inbox_messages also */
if (tableExists($conn, 'inbox_messages')) {
    $hasCustomerId = columnExists($conn, 'inbox_messages', 'customer_id');

    if ($hasCustomerId) {
        $stmt = $conn->prepare("
            SELECT 
                id,
                id AS thread_id,
                from_name,
                subject,
                message_date,
                status,
                'inbox_messages' AS source_table
            FROM inbox_messages
            WHERE customer_id = ?
            ORDER BY message_date DESC, id DESC
        ");
        $stmt->bind_param("s", $customerId);
    } else {
        $stmt = $conn->prepare("
            SELECT 
                id,
                id AS thread_id,
                from_name,
                subject,
                message_date,
                status,
                'inbox_messages' AS source_table
            FROM inbox_messages
            ORDER BY message_date DESC, id DESC
        ");
    }

    if ($stmt) {
        $stmt->execute();
        $res = $stmt->get_result();

        while ($row = $res->fetch_assoc()) {
            $messages[] = $row;
        }

        $stmt->close();
    }
}

usort($messages, function ($a, $b) {
    return strtotime($b['message_date'] ?? 'now') <=> strtotime($a['message_date'] ?? 'now');
});
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inbox</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
</head>

<body class="bg-[#f3f3f3] font-sans">

<?php include __DIR__ . '/includes/sidebar.php'; ?>

<div class="lg:ml-64 min-h-screen bg-[#f3f3f3]">
    <?php include __DIR__ . '/includes/navbar.php'; ?>

    <div class="px-3 sm:px-5 py-3">

        <div class="mt-5 flex flex-wrap items-center gap-3 sm:gap-4">
            <div class="w-[36px] h-[36px] rounded-[8px] bg-[#f5eee3] flex items-center justify-center shrink-0">
                <i data-lucide="badge-check" class="w-[18px] h-[18px] text-[#cd9737]"></i>
            </div>

            <h2 class="whitespace-nowrap text-[18px] sm:text-[20px] font-normal text-[#40352a]" style="font-family: Georgia, 'Times New Roman', serif;">
                INBOX
            </h2>

            <div class="hidden sm:block flex-1 h-[2px] bg-[#d1a14b]"></div>
        </div>

        <div class="mt-5 sm:mt-8 rounded-[14px] border border-[#dddddd] bg-[#f7f7f7] p-2 sm:p-4 min-h-[170px]">
            <div class="overflow-x-auto">
                <table class="w-full border-separate border-spacing-0">
                    <thead>
                    <tr class="bg-[#C89741] text-white text-[12px] sm:text-[13px] font-semibold">
                        <th class="px-3 sm:px-6 py-3 sm:py-5 text-left rounded-l-[10px] min-w-[60px] sm:min-w-[70px]">Sr No</th>
                        <th class="px-3 sm:px-6 py-3 sm:py-5 text-left min-w-[110px] sm:min-w-[120px]">From</th>
                        <th class="px-3 sm:px-6 py-3 sm:py-5 text-left min-w-[180px] sm:min-w-[220px]">Subject</th>
                        <th class="px-3 sm:px-6 py-3 sm:py-5 text-left min-w-[110px] sm:min-w-[130px]">Date</th>
                        <th class="px-3 sm:px-6 py-3 sm:py-5 text-left min-w-[100px] sm:min-w-[110px]">Status</th>
                        <th class="px-3 sm:px-6 py-3 sm:py-5 text-left rounded-r-[10px] min-w-[90px] sm:min-w-[110px]">Action</th>
                    </tr>
                    </thead>

                    <tbody>
                    <?php if (!empty($messages)): ?>
                        <?php foreach ($messages as $index => $row): ?>
                            <tr class="text-[12px] sm:text-[13px] text-[#2f2f2f] bg-white">
                                <td class="px-3 sm:px-6 py-3 sm:py-5 border-b border-[#d8d8d8]">
                                    <?php echo $index + 1; ?>
                                </td>

                                <td class="px-3 sm:px-6 py-3 sm:py-5 border-b border-[#d8d8d8] font-semibold">
                                    <?php echo htmlspecialchars($row['from_name'] ?? '-'); ?>
                                </td>

                                <td class="px-3 sm:px-6 py-3 sm:py-5 border-b border-[#d8d8d8] font-semibold text-[#1f1f1f]">
                                    <?php echo htmlspecialchars($row['subject'] ?? '-'); ?>
                                </td>

                                <td class="px-3 sm:px-6 py-3 sm:py-5 border-b border-[#d8d8d8]">
                                    <?php echo !empty($row['message_date']) ? htmlspecialchars(date('Y-m-d', strtotime($row['message_date']))) : '-'; ?>
                                </td>

                                <td class="px-3 sm:px-6 py-3 sm:py-5 border-b border-[#d8d8d8]">
                                    <?php echo htmlspecialchars($row['status'] ?? 'Unread'); ?>
                                </td>

                                <td class="px-3 sm:px-6 py-3 sm:py-5 border-b border-[#d8d8d8]">
                                    <a href="inbox-thread-details.php?id=<?php echo (int)$row['id']; ?>&thread_id=<?php echo (int)($row['thread_id'] ?: $row['id']); ?>&source=<?php echo urlencode($row['source_table']); ?>"
                                       class="text-[#1f5fbf] hover:underline font-medium">
                                        View
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="h-[120px] text-center text-[14px] font-semibold text-[#b0b0b0]">
                                No Record Found...
                            </td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>

<script>
    lucide.createIcons();
</script>
</body>
</html>