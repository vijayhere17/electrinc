<?php
session_start();

include __DIR__ . '/config/db.php';

/** @var mysqli $conn */
$error = "";

if (isset($_SESSION['city_franchies_holder_id'])) {
    header("Location: city-franchies-dashboard.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $franchies_id = strtoupper(trim($_POST['franchies_id'] ?? ''));
    $password     = trim($_POST['password'] ?? '');

    if ($franchies_id == "" || $password == "") {
        $error = "Please enter franchies id and password.";
    } else {

        $stmt = $conn->prepare("
            SELECT 
                id,
                franchies_id,
                full_name,
                state,
                city,
                password_text,
                password_hash,
                status
            FROM city_franchies_holder
            WHERE franchies_id = ?
            LIMIT 1
        ");

        $stmt->bind_param("s", $franchies_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result && $result->num_rows === 1) {

            $user = $result->fetch_assoc();

            if ($user['status'] !== 'Active') {
                $error = "Your franchies account is inactive.";
            } else {

                $password_ok = false;

                if (!empty($user['password_hash']) && password_verify($password, $user['password_hash'])) {
                    $password_ok = true;
                }

                if ($password === $user['password_text']) {
                    $password_ok = true;
                }

                if ($password_ok) {

                    $_SESSION['city_franchies_holder_id'] = $user['id'];
                    $_SESSION['city_franchies_login_id']  = $user['franchies_id'];
                    $_SESSION['city_franchies_name']      = $user['full_name'];
                    $_SESSION['city_franchies_state']     = $user['state'];
                    $_SESSION['city_franchies_city']      = $user['city'];

                    header("Location: city-franchies-dashboard.php");
                    exit();

                } else {
                    $error = "Invalid password.";
                }
            }

        } else {
            $error = "Invalid franchies id.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>City Franchies Login</title>
    <script src="https://cdn.tailwindcss.com"></script>
        <link rel="stylesheet" href="assets/css/admin-responsive.css">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>

<body class="bg-[#f3f4f6] min-h-screen flex items-center justify-center">

<div class="w-full max-w-md bg-white rounded-lg shadow border border-gray-200 p-7">

    <h2 class="text-center text-[24px] font-semibold text-[#40352a] mb-2">
        City Franchies Login
    </h2>

    <p class="text-center text-sm text-gray-500 mb-6">
        Login with your Franchies ID and Password
    </p>

    <?php if ($error): ?>
        <div class="mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <form method="POST">

        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-1">
                Franchies ID
            </label>

            <input
                type="text"
                name="franchies_id"
                placeholder="Example: FRC1542"
                required
                value="<?= htmlspecialchars($_POST['franchies_id'] ?? '') ?>"
                class="w-full border border-gray-300 rounded-md px-3 py-2 uppercase focus:outline-none focus:ring-2 focus:ring-[#cd9737]"
            >
        </div>

        <div class="mb-5">
            <label class="block text-sm font-medium text-gray-700 mb-1">
                Password
            </label>

            <input
                type="password"
                name="password"
                required
                class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[#cd9737]"
            >
        </div>

        <button
            type="submit"
            class="w-full bg-[#cd9737] hover:bg-[#b98225] text-white py-2 rounded-md"
        >
            Login
        </button>

    </form>

</div>

</body>
</html>