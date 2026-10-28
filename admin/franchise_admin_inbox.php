<!-- FILE NAME: franchise_admin_inbox.php -->

<?php
session_start();

require_once __DIR__ . '/config.php';

if (empty($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

function e($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

/*
|--------------------------------------------------------------------------
| GET ALL FRANCHISE MESSAGES
|--------------------------------------------------------------------------
*/

$messages = [];

$query = "
    SELECT *
    FROM franchise_messages
    ORDER BY id DESC
";

$result = $conn->query($query);

if ($result) {

    while ($row = $result->fetch_assoc()) {

        $messages[] = $row;
    }
}

/*
|--------------------------------------------------------------------------
| FUNCTIONS
|--------------------------------------------------------------------------
*/

function getFranchiseType($type)
{
    if ($type == 'state') {
        return 'State Franchise';
    }

    if ($type == 'city') {
        return 'City Franchise';
    }

    return 'Mobile Franchise';
}
?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Franchise Admin Inbox</title>

    <script src="https://cdn.tailwindcss.com"></script>

</head>

<body class="bg-[#f3f3f3]">

<div class="p-5">

    <!-- HEADER -->

    <div class="flex items-center gap-4 mb-6">

        <h2 class="text-[24px] font-bold text-[#40352a]">
            Franchise Admin Inbox
        </h2>

        <div class="w-full h-[2px] bg-[#C89741]"></div>

    </div>

    <!-- TABLE -->

    <div class="bg-white rounded-xl shadow-sm border overflow-hidden">

        <div class="overflow-x-auto">

            <table class="w-full">

                <thead>

                    <tr class="bg-[#C89741] text-white text-sm">

                        <th class="px-5 py-4 text-left">
                            Sr No
                        </th>

                        <th class="px-5 py-4 text-left">
                            Franchise Type
                        </th>

                        <th class="px-5 py-4 text-left">
                            Franchise ID
                        </th>

                        <th class="px-5 py-4 text-left">
                            From
                        </th>

                        <th class="px-5 py-4 text-left">
                            Subject
                        </th>

                        <th class="px-5 py-4 text-left">
                            Message
                        </th>

                        <th class="px-5 py-4 text-left">
                            Date
                        </th>

                        <th class="px-5 py-4 text-left">
                            Status
                        </th>

                        <th class="px-5 py-4 text-left">
                            Action
                        </th>

                    </tr>

                </thead>

                <tbody>

                <?php if (!empty($messages)) { ?>

                    <?php foreach ($messages as $index => $row) { ?>

                        <tr class="border-b text-sm">

                            <!-- SR -->

                            <td class="px-5 py-4">

                                <?php echo $index + 1; ?>

                            </td>

                            <!-- TYPE -->

                            <td class="px-5 py-4 font-semibold">

                                <?php echo e(getFranchiseType($row['franchise_type'])); ?>

                            </td>

                            <!-- FRANCHISE CODE -->

                            <td class="px-5 py-4">

                                <?php echo e($row['franchise_code']); ?>

                            </td>

                            <!-- FROM -->

                            <td class="px-5 py-4">

                                <?php echo e($row['from_name']); ?>

                            </td>

                            <!-- SUBJECT -->

                            <td class="px-5 py-4 font-semibold">

                                <?php echo e($row['subject']); ?>

                            </td>

                            <!-- MESSAGE -->

                            <td class="px-5 py-4">

                                <?php echo e(substr($row['message'], 0, 50)); ?>...

                            </td>

                            <!-- DATE -->

                            <td class="px-5 py-4">

                                <?php
                                echo date(
                                    'd M Y',
                                    strtotime($row['created_at'])
                                );
                                ?>

                            </td>

                            <!-- STATUS -->

                            <td class="px-5 py-4">

                                <?php echo e($row['status']); ?>

                            </td>

                            <!-- ACTION -->

                            <td class="px-5 py-4">

                                <a href="franchise_message_view.php?id=<?php echo (int)$row['id']; ?>"
                                   class="px-4 py-2 rounded-lg bg-[#C89741] text-white text-xs font-semibold">

                                    View

                                </a>

                            </td>

                        </tr>

                    <?php } ?>

                <?php } else { ?>

                    <tr>

                        <td colspan="9"
                            class="text-center py-10 text-gray-400 font-semibold">

                            No Messages Found

                        </td>

                    </tr>

                <?php } ?>

                </tbody>

            </table>

        </div>

    </div>

</div>

</body>
</html>