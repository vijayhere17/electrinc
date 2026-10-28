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

$outboxRows = [];

/*
|------------------------------------------
| Fetch only sent messages for Outbox table
|------------------------------------------
*/
$sql = "SELECT id, from_name, to_name, subject, message_date, status, message_details, thread_id
        FROM member_messages
        WHERE customer_id = ? AND folder = 'outbox'
        ORDER BY id DESC";

$stmt = $conn->prepare($sql);

if ($stmt) {
    $stmt->bind_param("s", $customerId);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $outboxRows[] = $row;
    }

    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Outbox</title>
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
            <div class="mt-5 flex items-center gap-4">
                <div class="w-[36px] h-[36px] rounded-[8px] bg-[#f5eee3] flex items-center justify-center shrink-0">
                    <i data-lucide="badge-check" class="w-[18px] h-[18px] text-[#cd9737]"></i>
                </div>

                <h2 class="whitespace-nowrap text-[20px] font-normal text-[#40352a]" style="font-family: Georgia, 'Times New Roman', serif;">
                    OUTBOX
                </h2>

                <div class="w-full h-[2px] bg-[#d1a14b]"></div>
            </div>

            <div class="mt-8 rounded-[14px] border border-[#dddddd] bg-[#f7f7f7] p-4 min-h-[170px]">
                <div class="overflow-x-auto">
                    <table class="w-full border-separate border-spacing-0">
                        <thead>
                            <tr class="bg-[#C89741] text-white text-[13px] font-semibold">
                                <th class="px-6 py-5 text-left rounded-l-[10px] min-w-[70px]">Sr No</th>
                                <th class="px-6 py-5 text-left min-w-[120px]">From</th>
                                <th class="px-6 py-5 text-left min-w-[220px]">Subject</th>
                                <th class="px-6 py-5 text-left min-w-[130px]">Date</th>
                                <th class="px-6 py-5 text-left min-w-[110px]">Status</th>
                                <th class="px-6 py-5 text-left rounded-r-[10px] min-w-[110px]">Action</th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php if (count($outboxRows) > 0): ?>
                                <?php foreach ($outboxRows as $index => $row): ?>
                                    <tr class="text-[13px] text-[#2f2f2f] bg-white">
                                        <td class="px-6 py-5 border-b border-[#d8d8d8] align-middle">
                                            <?php echo $index + 1; ?>
                                        </td>

                                        <td class="px-6 py-5 border-b border-[#d8d8d8] align-middle font-semibold">
                                            <?php echo htmlspecialchars($row['from_name']); ?>
                                        </td>

                                        <td class="px-6 py-5 border-b border-[#d8d8d8] align-middle font-semibold text-[#1f1f1f]">
                                            <?php echo htmlspecialchars($row['subject']); ?>
                                        </td>

                                        <td class="px-6 py-5 border-b border-[#d8d8d8] align-middle">
                                            <?php echo htmlspecialchars(date('Y-m-d', strtotime($row['message_date']))); ?>
                                        </td>

                                        <td class="px-6 py-5 border-b border-[#d8d8d8] align-middle">
                                            <?php echo htmlspecialchars($row['status']); ?>
                                        </td>

                                        <td class="px-6 py-5 border-b border-[#d8d8d8] align-middle">
                                            <a
                                                href="outbox-thread-details.php?id=<?php echo urlencode($row['id']); ?>"
                                                class="text-[#1f5fbf] hover:underline font-medium"
                                            >
                                                View
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="h-[120px] align-middle text-center">
                                        <div class="text-[14px] font-semibold text-[#b0b0b0]">
                                            No Record Found...
                                        </div>
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