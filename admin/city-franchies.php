<?php
session_start();

ini_set('display_errors', 1);
error_reporting(E_ALL);

include __DIR__ . '/config/db.php';

if (!isset($conn) || !($conn instanceof mysqli)) {
    die("Database connection failed.");
}

$success = $_SESSION['success'] ?? '';
unset($_SESSION['success']);

$error = '';

function generateFranchiesId(mysqli $conn): string
{
    do {
        $id   = "FRC" . rand(10000, 99999);
        $stmt = $conn->prepare("SELECT id FROM city_franchies_holder WHERE franchies_id = ? LIMIT 1");
        $stmt->bind_param("s", $id);
    
        $stmt->execute();
        $res  = $stmt->get_result();
        $stmt->close();
    } while ($res && $res->num_rows > 0);

    return $id;
}

function generatePassword(): string
{
    return "CF" . rand(100000, 999999);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $full_name      = trim($_POST['full_name'] ?? '');
    $mobile         = trim($_POST['mobile'] ?? '');
    $email          = trim($_POST['email'] ?? '');
    $franchise_code = trim($_POST['franchise_code'] ?? '');
    $city           = trim($_POST['city'] ?? '');
    $status         = trim($_POST['status'] ?? 'Active');

    $state = '';
    if ($franchise_code !== '') {
        $stmtS = $conn->prepare("SELECT state, name FROM franchies_holder WHERE franchise_code = ? LIMIT 1");
        $stmtS->bind_param("s", $franchise_code);
        $stmtS->execute();
        $rowS = $stmtS->get_result()->fetch_assoc();
        $stmtS->close();

        if ($rowS) {
            $state = trim($rowS['state'] ?: $rowS['name']);
        }
    }

    if ($full_name === "" || $franchise_code === "" || $city === "") {
        $error = "Full name, state franchise and city are required.";
    } else {

        $franchies_id  = generateFranchiesId($conn);
        $password_text = generatePassword();
        $password_hash = password_hash($password_text, PASSWORD_DEFAULT);
        
        

        $stmt = $conn->prepare("
            INSERT INTO city_franchies_holder
                (franchies_id, franchise_code, full_name, mobile, email, state, city, password_text, password_hash, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        if (!$stmt) {
            $error = "SQL Error: " . $conn->error;
        } else {
            $stmt->bind_param(
                "ssssssssss",
                $franchies_id,
                $franchise_code,
                $full_name,
                $mobile,
                $email,
                $state,
                $city,
                $password_text,
                $password_hash,
                $status
            );

if ($stmt->execute()) {

    $_SESSION['success'] =
        "City Franchies created! &nbsp;|&nbsp;
        <strong>Franchies ID:</strong> " . htmlspecialchars($franchies_id) .
        " &nbsp;|&nbsp;
        <strong>Password:</strong> " . htmlspecialchars($password_text);

    $stmt->close();
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;

} else {
    $error = "Insert failed: " . $stmt->error;
}

$stmt->close();
        }
    }
}

$holders = [];
$result  = $conn->query("SELECT * FROM city_franchies_holder ORDER BY id DESC");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $holders[] = $row;
    }
}

$state_franchies = [];
$stateResult = $conn->query("
    SELECT
        COALESCE(NULLIF(franchise_code,''), NULLIF(franchies_id,''), CAST(id AS CHAR)) AS franchise_code,
        state,
        name
    FROM franchies_holder
    ORDER BY id DESC
");
if ($stateResult) {
    while ($row = $stateResult->fetch_assoc()) {
        $state_franchies[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>City Franchies Holder</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link rel="stylesheet" href="assets/css/admin-responsive.css">

    <style>
        .pwd-badge {
            font-family: 'Courier New', monospace;
            font-size: 0.82rem;
            font-weight: 600;
            letter-spacing: 0.04em;
            color: #374151;
        }

        .copy-btn {
            transition: color .2s, transform .1s, background-color .2s;
        }

        .copy-btn:hover {
            color: #cd9737;
            transform: scale(1.05);
        }

        @keyframes rowFlash {
            0% { background-color: #d1fae5; }
            100% { background-color: transparent; }
        }

        .row-flash {
            animation: rowFlash 1s ease forwards;
        }

        .table-scroll-hint::-webkit-scrollbar {
            height: 8px;
        }

        .table-scroll-hint::-webkit-scrollbar-thumb {
            background: #d1d5db;
            border-radius: 9999px;
        }
    </style>
</head>

<body class="bg-[#f3f4f6] min-h-screen overflow-x-hidden">

<?php include __DIR__ . '/includes/sidebar.php'; ?>

<div class="min-h-screen lg:ml-64">

    <?php include __DIR__ . '/includes/navbar.php'; ?>

    <main class="px-3 py-4 sm:px-4 md:px-5 md:py-6">
        <div class="mx-auto max-w-[1300px]">

            <div class="mb-6 flex flex-wrap items-center gap-3 sm:gap-4 md:gap-5">
                <div class="flex h-10 w-10 sm:h-11 sm:w-11 shrink-0 items-center justify-center rounded-[10px] bg-[#f6eee2]">
                    <i data-lucide="badge-check" class="h-5 w-5 text-[#cf9a3c]"></i>
                </div>

                <h2 class="text-[18px] sm:text-[21px] md:text-[24px] font-normal leading-tight text-[#1f2937]"
                    style="font-family: Georgia,'Times New Roman',serif;">
                    CITY FRANCHIES REGISTRATION
                </h2>

                <div class="hidden sm:block h-[2px] flex-1 bg-[#d1a14b]"></div>
            </div>

            <?php if ($success): ?>
                <div class="mb-5 flex items-start gap-3 rounded-lg border border-green-300 bg-green-50 px-4 py-3 text-sm text-green-800 shadow-sm">
                    <i data-lucide="check-circle-2" class="mt-0.5 h-5 w-5 shrink-0 text-green-500"></i>
                    <span><?= $success ?></span>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="mb-5 flex items-start gap-3 rounded-lg border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-800 shadow-sm">
                    <i data-lucide="alert-circle" class="mt-0.5 h-5 w-5 shrink-0 text-red-500"></i>
                    <span><?= htmlspecialchars($error) ?></span>
                </div>
            <?php endif; ?>

            <div class="mb-6 rounded-xl border border-gray-200 bg-white p-4 shadow-sm sm:p-5 md:p-6">
                <h3 class="mb-5 flex items-center gap-2 text-[16px] sm:text-[17px] font-semibold text-[#40352a]">
                    <i data-lucide="user-plus" class="h-5 w-5 text-[#cd9737]"></i>
                    Register New City Franchies
                </h3>

                <form method="POST" class="grid grid-cols-1 gap-4 md:grid-cols-2">

                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">
                            Full Name <span class="text-red-500">*</span>
                        </label>
                        <input
                            type="text"
                            name="full_name"
                            required
                            autocomplete="name"
                            class="w-full rounded-md border border-gray-300 px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#cd9737]"
                        >
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Mobile</label>
                        <input
                            type="text"
                            name="mobile"
                            inputmode="numeric"
                            autocomplete="tel"
                            class="w-full rounded-md border border-gray-300 px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#cd9737]"
                        >
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Email</label>
                        <input
                            type="email"
                            name="email"
                            autocomplete="email"
                            class="w-full rounded-md border border-gray-300 px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#cd9737]"
                        >
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">
                            State Franchies ID <span class="text-red-500">*</span>
                        </label>
                        <select
                            name="franchise_code"
                            required
                            class="w-full rounded-md border border-gray-300 bg-white px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#cd9737]"
                        >
                            <option value="">Select State Franchies</option>
                            <?php foreach ($state_franchies as $sf): ?>
                                <option value="<?= htmlspecialchars($sf['franchise_code']) ?>">
                                    <?= htmlspecialchars($sf['franchise_code'] . ' — ' . ($sf['state'] ?: $sf['name'])) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">
                            City <span class="text-red-500">*</span>
                        </label>
                        <input
                            type="text"
                            name="city"
                            required
                            autocomplete="address-level2"
                            class="w-full rounded-md border border-gray-300 px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#cd9737]"
                        >
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Status</label>
                        <select
                            name="status"
                            class="w-full rounded-md border border-gray-300 bg-white px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#cd9737]"
                        >
                            <option value="Active">Active</option>
                            <option value="Inactive">Inactive</option>
                        </select>
                    </div>

                    <div class="md:col-span-2">
                        <button
                            type="submit"
                            class="inline-flex w-full sm:w-auto items-center justify-center gap-2 rounded-md bg-[#cd9737] px-6 py-3 text-sm font-medium text-white transition hover:bg-[#b98225]"
                        >
                            <i data-lucide="key-round" class="h-4 w-4"></i>
                            Generate City Franchies ID &amp; Password
                        </button>
                    </div>

                </form>
            </div>

            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm sm:p-5 md:p-6">
                <div class="mb-4 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <h3 class="flex items-center gap-2 text-[17px] sm:text-[19px] font-semibold text-[#40352a]">
                        <i data-lucide="list" class="h-5 w-5 text-[#cd9737]"></i>
                        City Franchies List
                    </h3>

                    <?php if (count($holders) > 0): ?>
                        <p class="text-xs text-gray-400">
                            Showing <?= count($holders) ?> record<?= count($holders) !== 1 ? 's' : '' ?>
                        </p>
                    <?php endif; ?>
                </div>

                <div class="mb-2 block text-xs text-gray-400 sm:hidden">
                    Swipe table left/right to view all columns.
                </div>

                <div class="table-scroll-hint overflow-x-auto rounded-lg border border-gray-200">
                    <table class="min-w-[980px] w-full border-collapse text-[13px] sm:text-sm">
                        <thead>
                            <tr class="bg-[#cd9737] text-white">
                                <th class="border border-[#b98225] px-2 py-2 text-left sm:px-3">#</th>
                                <th class="border border-[#b98225] px-2 py-2 text-left whitespace-nowrap sm:px-3">Franchies ID</th>
                                <th class="border border-[#b98225] px-2 py-2 text-left whitespace-nowrap sm:px-3">Admin Password</th>
                                <th class="border border-[#b98225] px-2 py-2 text-left sm:px-3">Name</th>
                                <th class="border border-[#b98225] px-2 py-2 text-left sm:px-3">Mobile</th>
                                <th class="border border-[#b98225] px-2 py-2 text-left sm:px-3">Email</th>
                                <th class="border border-[#b98225] px-2 py-2 text-left whitespace-nowrap sm:px-3">State ID</th>
                                <th class="border border-[#b98225] px-2 py-2 text-left sm:px-3">City</th>
                                <th class="border border-[#b98225] px-2 py-2 text-left sm:px-3">Status</th>
                                <th class="border border-[#b98225] px-2 py-2 text-left whitespace-nowrap sm:px-3">Created</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($holders) > 0): ?>
                                <?php foreach ($holders as $i => $h): ?>
                                    <tr id="row-<?= (int)$h['id'] ?>" class="border-b border-gray-100 transition-colors hover:bg-[#fdf8f0]">

                                        <td class="border border-gray-200 px-2 py-2 text-center text-gray-400 sm:px-3">
                                            <?= $i + 1 ?>
                                        </td>

                                        <td class="border border-gray-200 px-2 py-2 font-semibold text-[#cd9737] whitespace-nowrap sm:px-3">
                                            <?= htmlspecialchars($h['franchies_id'] ?? '—') ?>
                                        </td>

                                        <td class="border border-gray-200 px-2 py-2 whitespace-nowrap sm:px-3">
                                            <div class="flex items-center gap-2">
                                                <?php $pwd = $h['password_text'] ?? ''; ?>
                                                <?php if ($pwd !== ''): ?>
                                                    <span class="pwd-badge" id="pwd-<?= (int)$h['id'] ?>">
                                                        <?= htmlspecialchars($pwd) ?>
                                                    </span>

                                                    <button
                                                        type="button"
                                                        onclick="copyPwd(<?= (int)$h['id'] ?>, <?= htmlspecialchars(json_encode($pwd), ENT_QUOTES) ?>)"
                                                        class="copy-btn inline-flex h-9 w-9 items-center justify-center rounded-md text-gray-400 hover:bg-gray-100"
                                                        title="Copy password"
                                                        aria-label="Copy password"
                                                    >
                                                        <i data-lucide="copy" class="h-4 w-4"></i>
                                                    </button>
                                                <?php else: ?>
                                                    <span class="text-xs italic text-gray-400">—</span>
                                                <?php endif; ?>
                                            </div>
                                        </td>

                                        <td class="border border-gray-200 px-2 py-2 sm:px-3">
                                            <?= htmlspecialchars($h['full_name'] ?? '') ?>
                                        </td>

                                        <td class="border border-gray-200 px-2 py-2 whitespace-nowrap sm:px-3">
                                            <?= htmlspecialchars($h['mobile'] ?? '') ?>
                                        </td>

                                        <td class="border border-gray-200 px-2 py-2 break-all sm:px-3">
                                            <?= htmlspecialchars($h['email'] ?? '') ?>
                                        </td>

                                        <td class="border border-gray-200 px-2 py-2 font-medium whitespace-nowrap text-[#cd9737] sm:px-3">
                                            <?= htmlspecialchars($h['franchise_code'] ?? '—') ?>
                                        </td>

                                        <td class="border border-gray-200 px-2 py-2 sm:px-3">
                                            <?= htmlspecialchars($h['city'] ?? '') ?>
                                        </td>

                                        <td class="border border-gray-200 px-2 py-2 sm:px-3">
                                            <?php if (($h['status'] ?? '') === 'Active'): ?>
                                                <span class="inline-flex items-center gap-1 rounded bg-green-100 px-2 py-1 text-xs font-medium text-green-700">
                                                    <span class="h-1.5 w-1.5 rounded-full bg-green-500"></span>
                                                    Active
                                                </span>
                                            <?php else: ?>
                                                <span class="inline-flex items-center gap-1 rounded bg-red-100 px-2 py-1 text-xs font-medium text-red-700">
                                                    <span class="h-1.5 w-1.5 rounded-full bg-red-500"></span>
                                                    Inactive
                                                </span>
                                            <?php endif; ?>
                                        </td>

                                        <td class="border border-gray-200 px-2 py-2 text-xs text-gray-500 whitespace-nowrap sm:px-3">
                                            <?= htmlspecialchars($h['created_at'] ?? '') ?>
                                        </td>

                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="10" class="border border-gray-200 py-10 text-center text-gray-400">
                                        <div class="flex flex-col items-center gap-2">
                                            <i data-lucide="inbox" class="h-8 w-8 text-gray-300"></i>
                                            No City Franchies found.
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </main>
</div>

<div
    id="copy-toast"
    class="pointer-events-none fixed bottom-4 left-1/2 z-50 flex -translate-x-1/2 items-center gap-2 rounded-lg bg-gray-800 px-4 py-2.5 text-sm text-white shadow-xl opacity-0 transition-opacity duration-300 sm:bottom-6 sm:left-auto sm:right-6 sm:translate-x-0"
>
    <i data-lucide="check" class="h-4 w-4 text-green-400"></i>
    Password copied!
</div>

<script>
document.addEventListener("DOMContentLoaded", () => {
    if (window.lucide) {
        lucide.createIcons();
    }
});

function copyPwd(rowId, pwd) {
    navigator.clipboard.writeText(pwd).then(() => {
        const row = document.getElementById('row-' + rowId);
        if (row) {
            row.classList.add('row-flash');
            setTimeout(() => row.classList.remove('row-flash'), 1000);
        }

        const toast = document.getElementById('copy-toast');
        if (toast) {
            toast.style.opacity = '1';
            setTimeout(() => {
                toast.style.opacity = '0';
            }, 2200);
        }
    }).catch(() => {
        const el = document.getElementById('pwd-' + rowId);
        if (el) {
            const range = document.createRange();
            range.selectNodeContents(el);
            const sel = window.getSelection();
            sel.removeAllRanges();
            sel.addRange(range);
            document.execCommand('copy');
            sel.removeAllRanges();
        }
    });
}
</script>

</body>
</html>