<?php
require_once __DIR__ . '/includes/auth.php';

/*
|--------------------------------------------------------------------------
| LOGIN CHECK
|--------------------------------------------------------------------------
*/
if (!isset($_SESSION['franchise_id'])) {
    header("Location: index.php");
    exit();
}

$franchise_id = (int)$_SESSION['franchise_id'];

/*
|--------------------------------------------------------------------------
| DATABASE CHECK
|--------------------------------------------------------------------------
*/
if (!isset($conn) || !($conn instanceof mysqli)) {
    die("Database connection failed.");
}

/*
|--------------------------------------------------------------------------
| FETCH INBOX MESSAGES
|--------------------------------------------------------------------------
|
| This will show:
| ✔ franchise messages
| ✔ admin replies
| ✔ dynamic data
| ✔ all replies for particular franchise
|
*/
$sql = "
SELECT *
FROM franchise_messages
WHERE franchise_id = ?
AND folder = 'inbox'
ORDER BY id DESC
";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    die("SQL Error : " . $conn->error);
}

$stmt->bind_param("i", $franchise_id);

$stmt->execute();

$result = $stmt->get_result();

$messages = [];

while ($row = $result->fetch_assoc()) {
    $messages[] = $row;
}

$stmt->close();

/*
|--------------------------------------------------------------------------
| ESCAPE FUNCTION
|--------------------------------------------------------------------------
*/
function e($string)
{
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}
?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Inbox</title>

    <!-- TAILWIND -->
    <script src="https://cdn.tailwindcss.com"></script>

    <!-- LUCIDE -->
    <script src="https://unpkg.com/lucide@latest"></script>

</head>

<body class="bg-[#f3f3f3] font-sans overflow-x-hidden">

<!-- SIDEBAR -->
<?php include __DIR__ . '/includes/sidebar.php'; ?>

<!-- MAIN -->
<div class="ml-64 min-h-screen bg-[#f3f3f3]">

    <!-- NAVBAR -->
    <?php include __DIR__ . '/includes/navbar.php'; ?>

    <div class="px-6 py-5">

        <!-- PAGE HEADER -->
        <div class="flex items-center gap-4">

            <div class="w-[38px] h-[38px] rounded-[10px] bg-[#f7efe2] flex items-center justify-center">

                <i data-lucide="inbox"
                   class="w-[18px] h-[18px] text-[#C89741]"></i>

            </div>

            <h2 class="text-[24px] text-[#40352a]"
                style="font-family: Georgia, 'Times New Roman', serif;">

                Inbox

            </h2>

            <div class="flex-1 h-[2px] bg-[#d1a14b]"></div>

        </div>

        <!-- TABLE BOX -->
        <div class="mt-8 bg-white border border-[#e5e5e5] rounded-2xl shadow-sm overflow-hidden">

            <div class="overflow-x-auto">

                <table class="w-full border-separate border-spacing-0">

                    <!-- TABLE HEADER -->
                    <thead>

                        <tr class="bg-[#C89741] text-white text-[14px]">

                            <th class="px-6 py-4 text-left">
                                Sr No
                            </th>

                            <th class="px-6 py-4 text-left">
                                Subject
                            </th>

                            <th class="px-6 py-4 text-left">
                                Your Message
                            </th>

                            <th class="px-6 py-4 text-left">
                                Admin Reply
                            </th>

                            <th class="px-6 py-4 text-left">
                                Status
                            </th>

                            <th class="px-6 py-4 text-left">
                                Date
                            </th>

                        </tr>

                    </thead>

                    <!-- TABLE BODY -->
                    <tbody>

                    <?php if(!empty($messages)): ?>

                        <?php foreach($messages as $index => $row): ?>

                            <tr class="bg-white hover:bg-[#faf7f2] transition">

                                <!-- SR -->
                                <td class="px-6 py-5 border-b border-[#eee]">

                                    <?php echo $index + 1; ?>

                                </td>

                                <!-- SUBJECT -->
                                <td class="px-6 py-5 border-b border-[#eee] font-semibold text-[#333]">

                                    <?php echo e($row['subject']); ?>

                                </td>

                                <!-- MESSAGE -->
                                <td class="px-6 py-5 border-b border-[#eee] text-[#555]">

                                    <?php echo e($row['message_details']); ?>

                                </td>

                                <!-- ADMIN REPLY -->
                                <td class="px-6 py-5 border-b border-[#eee]">

                                    <?php if(!empty($row['admin_reply'])): ?>

                                        <span class="text-green-700 font-medium">

                                            <?php echo e($row['admin_reply']); ?>

                                        </span>

                                    <?php else: ?>

                                        <span class="text-gray-400">

                                            Waiting For Reply

                                        </span>

                                    <?php endif; ?>

                                </td>

                                <!-- STATUS -->
                                <td class="px-6 py-5 border-b border-[#eee]">

                                    <?php if($row['status'] == 'Replied'): ?>

                                        <span class="bg-green-100 text-green-700 px-4 py-1 rounded-full text-[12px] font-semibold">

                                            Replied

                                        </span>

                                    <?php else: ?>

                                        <span class="bg-yellow-100 text-yellow-700 px-4 py-1 rounded-full text-[12px] font-semibold">

                                            Pending

                                        </span>

                                    <?php endif; ?>

                                </td>

                                <!-- DATE -->
                                <td class="px-6 py-5 border-b border-[#eee] text-[#666]">

                                    <?php
                                    if(!empty($row['message_date'])){
                                        echo date(
                                            'd M Y',
                                            strtotime($row['message_date'])
                                        );
                                    } else {
                                        echo '-';
                                    }
                                    ?>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                    <?php else: ?>

                        <tr>

                            <td colspan="6"
                                class="text-center py-16 text-[#999] font-semibold">

                                No Inbox Messages Found

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
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }
</script>

</body>
</html>