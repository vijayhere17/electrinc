<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();

require_once __DIR__ . '/config/db.php';

include __DIR__ . '/includes/admin_access_control.php';

$message = '';

if (isset($_POST['activate_user'])) {

    $customer_id = trim($_POST['customer_id']);

    $check = $conn->query("
        SELECT *
        FROM signup_users
        WHERE customer_id='$customer_id'
        LIMIT 1
    ");

    if (!$check || $check->num_rows == 0) {

        $message = "
            <div class='bg-red-100 border border-red-200 text-red-700 px-4 py-3 rounded-xl mb-4 text-sm'>
                Invalid Customer ID
            </div>
        ";

    } else {

        $user = $check->fetch_assoc();

        if ($user['registration_status'] == 'Active') {

            $message = "
                <div class='bg-yellow-100 border border-yellow-200 text-yellow-700 px-4 py-3 rounded-xl mb-4 text-sm'>
                    User already active
                </div>
            ";

        } else {

            $conn->query("
                UPDATE signup_users
                SET
                    registration_status='Active',
                    activated_at=NOW()
                WHERE customer_id='$customer_id'
            ");

            include __DIR__ . '/includes/process-activation.php';

            // Income Plan — Direct Sponsor (#1) + Binary (#2)
            require_once __DIR__ . '/../includes/income_common.php';
            process_member_income_on_activation($conn, $customer_id, ['source' => 'Activation']);

            $message = "
                <div class='bg-green-100 border border-green-200 text-green-700 px-4 py-3 rounded-xl mb-4 text-sm'>
                    User activated successfully
                </div>
            ";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Activate / Block User</title>

    <script src="https://cdn.tailwindcss.com"></script>

</head>

<body class="bg-gray-100 overflow-x-hidden">

<?php include __DIR__ . '/includes/sidebar.php'; ?>

<div class="lg:ml-64 min-h-screen">

    <?php include __DIR__ . '/includes/navbar.php'; ?>

    <div class="p-3 sm:p-5">

        <div class="mb-5">

            <h1 class="text-xl sm:text-2xl font-bold text-gray-800">
                Activate / Block User
            </h1>

            <p class="text-sm text-gray-500 mt-1">
                Directly activate any customer ID
            </p>

        </div>

        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-4 sm:p-6 w-full max-w-xl">

            <?php echo $message; ?>

            <form method="POST">

                <div class="mb-5">

                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        Customer ID
                    </label>

                    <input
                        type="text"
                        name="customer_id"
                        placeholder="Enter Customer ID"
                        class="w-full border border-gray-300 rounded-xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-yellow-500 focus:border-yellow-500"
                        required
                    >

                </div>

                <div class="flex flex-col sm:flex-row gap-3">

                    <button
                        type="submit"
                        name="activate_user"
                        class="w-full sm:w-auto bg-yellow-600 hover:bg-yellow-700 text-white px-6 py-3 rounded-xl font-medium transition duration-200"
                    >
                        Activate User
                    </button>


                </div>

            </form>

        </div>

    </div>

</div>

<script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function () {

    if (typeof lucide !== "undefined") {
        lucide.createIcons();
    }

});
</script>

</body>
</html>