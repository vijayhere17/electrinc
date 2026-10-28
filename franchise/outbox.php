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

/*
|--------------------------------------------------------------------------
| SESSION DATA
|--------------------------------------------------------------------------
*/
$franchise_id = (int)$_SESSION['franchise_id'];

/*
|--------------------------------------------------------------------------
| IMPORTANT FIX
|--------------------------------------------------------------------------
|
| OLD ISSUE:
| ❌ only city franchise messages showing
|
| NOW FIX:
| ✔ state franchise messages show
| ✔ city franchise messages show
| ✔ mobile franchise messages show
| ✔ only logged-in franchise messages show
|
*/

/*
|--------------------------------------------------------------------------
| FETCH OUTBOX MESSAGES
|--------------------------------------------------------------------------
*/
$sql = "
SELECT *
FROM franchise_messages
WHERE franchise_id = ?
AND folder = 'outbox'
ORDER BY id DESC
";

$stmt = $conn->prepare($sql);

if(!$stmt){
    die("SQL Error : " . $conn->error);
}

$stmt->bind_param(
    "i",
    $franchise_id
);

$stmt->execute();

$result = $stmt->get_result();

$messages = [];

while($row = $result->fetch_assoc()){
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

    <title>Outbox</title>

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

    <!-- CONTENT -->
    <div class="px-6 py-6">

        <!-- PAGE TITLE -->
        <div class="flex items-center gap-4">

            <div class="w-[38px] h-[38px] rounded-[10px] bg-[#f7efe2] flex items-center justify-center">

                <i data-lucide="send"
                   class="w-[18px] h-[18px] text-[#C89741]"></i>

            </div>

            <h2 class="text-[24px] text-[#40352a]"
                style="font-family: Georgia, 'Times New Roman', serif;">

                Outbox

            </h2>

            <div class="flex-1 h-[2px] bg-[#d1a14b]"></div>

        </div>

        <!-- TABLE -->
        <div class="mt-8 bg-white rounded-2xl border border-[#e5e5e5] shadow-sm overflow-hidden">

            <div class="overflow-x-auto">

                <table class="w-full border-separate border-spacing-0">

                    <!-- TABLE HEADER -->
                    <thead>

                        <tr class="bg-[#C89741] text-white text-[14px]">

                            <th class="px-6 py-4 text-left">
                                Sr No
                            </th>

                            <th class="px-6 py-4 text-left">
                                To
                            </th>

                            <th class="px-6 py-4 text-left">
                                Subject
                            </th>

                            <th class="px-6 py-4 text-left">
                                Message
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

                                <!-- TO -->
                                <td class="px-6 py-5 border-b border-[#eee]">

                                    <span class="font-semibold text-[#333]">

                                        <?php echo e($row['to_name'] ?: 'Admin'); ?>

                                    </span>

                                </td>

                                <!-- SUBJECT -->
                                <td class="px-6 py-5 border-b border-[#eee] font-semibold text-[#333]">

                                    <?php echo e($row['subject']); ?>

                                </td>

                                <!-- MESSAGE -->
                                <td class="px-6 py-5 border-b border-[#eee] text-[#555]">

                                    <?php echo e($row['message_details']); ?>

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

                                No Outbox Messages Found

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