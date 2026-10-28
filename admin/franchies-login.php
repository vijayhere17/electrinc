<?php
session_start();

require_once __DIR__ . '/config/db.php';

if (!isset($conn) || !($conn instanceof mysqli)) {
    die('Database connection failed. Please check config/db.php');
}

$error_message = '';
$prefilled_id = trim($_GET['franchies_id'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $franchies_id = trim($_POST['franchies_id'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($franchies_id === '' || $password === '') {
        $error_message = 'Please enter Franchies ID and Password.';
    } else {
        $stmt = $conn->prepare('SELECT id, franchies_id, franchies_password, name, status FROM franchies_holder WHERE franchies_id = ? LIMIT 1');

        if (!$stmt) {
            $error_message = 'Database error: ' . $conn->error;
        } else {
            $stmt->bind_param('s', $franchies_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $franchies = $result ? $result->fetch_assoc() : null;
            $stmt->close();

            if ($franchies && password_verify($password, (string)$franchies['franchies_password'])) {
                session_regenerate_id(true);

                $_SESSION['franchies_logged_in'] = true;
                $_SESSION['franchies_db_id'] = (int)$franchies['id'];
                $_SESSION['franchies_id'] = (string)$franchies['franchies_id'];
                $_SESSION['franchies_name'] = (string)($franchies['name'] ?? 'Franchies Owner');
                $_SESSION['franchies_status'] = (string)($franchies['status'] ?? 'active');

                header('Location: franchies_reg.php');
                exit();
            }

            $error_message = 'Invalid Franchies ID or Password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="assets/css/admin-responsive.css">
    <title>Franchies Login - Freshiya</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-gray-100 flex items-center justify-center px-4">
    <div class="w-full max-w-lg rounded-2xl bg-white shadow-lg border border-gray-200 p-8">
        <div class="mb-8 text-center">
            <h1 class="text-3xl font-bold text-gray-900">Franchies Login</h1>
            <p class="mt-2 text-sm text-gray-600">Sign in with the franchies credentials to open the registration page.</p>
        </div>

        <?php if ($error_message !== ''): ?>
            <div class="mb-6 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <form method="POST" class="space-y-5">
            <div>
                <label class="mb-2 block text-sm font-semibold text-gray-700">Franchies ID</label>
                <input
                    type="text"
                    name="franchies_id"
                    value="<?php echo htmlspecialchars($prefilled_id); ?>"
                    class="w-full rounded-lg border border-gray-300 px-4 py-3 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-200"
                    placeholder="Enter Franchies ID"
                    required
                >
            </div>

            <div>
                <label class="mb-2 block text-sm font-semibold text-gray-700">Password</label>
                <input
                    type="password"
                    name="password"
                    class="w-full rounded-lg border border-gray-300 px-4 py-3 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-200"
                    placeholder="Enter Password"
                    required
                >
            </div>

            <button
                type="submit"
                class="w-full rounded-lg bg-blue-600 px-4 py-3 font-semibold text-white transition hover:bg-blue-700"
            >
                Login and Open Registration
            </button>
        </form>

        <div class="mt-5 text-center text-sm text-gray-600">
            After login you will be redirected to the franchies registration page.
        </div>
    </div>
</body>
</html>