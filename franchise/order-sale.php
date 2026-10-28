<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include __DIR__ . '/config/db.php';

function e($str)
{
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}

/*
|--------------------------------------------------------------------------
| CREATE TABLE AUTOMATICALLY
|--------------------------------------------------------------------------
*/

$conn->query("
CREATE TABLE IF NOT EXISTS franchise_order_sale (
    id INT AUTO_INCREMENT PRIMARY KEY,
    distributor_id VARCHAR(100) DEFAULT NULL,
    member_id VARCHAR(100) NOT NULL,
    member_name VARCHAR(255) DEFAULT NULL,
    product_name VARCHAR(255) DEFAULT NULL,
    quantity INT DEFAULT 0,
    price DECIMAL(10,2) DEFAULT 0.00,
    total_amount DECIMAL(10,2) DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)
");

/*
|--------------------------------------------------------------------------
| SEARCH BY MEMBER ID
|--------------------------------------------------------------------------
*/

$memberId = trim($_POST['member_id'] ?? $_GET['member_id'] ?? '');
$sales = [];
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if ($memberId == '') {

        $error = "Please enter Member ID.";

    } else {

        $stmt = $conn->prepare("
            SELECT *
            FROM franchise_order_sale
            WHERE member_id = ?
            ORDER BY id DESC
        ");

        $stmt->bind_param("s", $memberId);
        $stmt->execute();

        $sales = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        if (count($sales) <= 0) {
            $error = "No order sale found for this Member ID.";
        }
    }
}

$totalSale = 0;
foreach ($sales as $sale) {
    $totalSale += (float)$sale['total_amount'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Order Sale</title>

    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
</head>

<body class="bg-[#f3f4f6] font-sans overflow-x-hidden">

<?php include __DIR__ . '/includes/sidebar.php'; ?>

<div class="ml-64 min-h-screen">

    <?php include __DIR__ . '/includes/navbar.php'; ?>

    <main class="px-5 py-5">

        <div class="max-w-[1500px] mx-auto">

            <div class="mt-5 flex items-center gap-3 mb-6">

                <div class="w-[36px] h-[36px] rounded-[8px] bg-[#f5eee3] flex items-center justify-center shrink-0">
                    <i data-lucide="shopping-cart" class="w-[18px] h-[18px] text-[#cd9737]"></i>
                </div>

                <h2
                    class="whitespace-nowrap text-[20px] font-normal text-[#40352a]"
                    style="font-family: Georgia, 'Times New Roman', serif;"
                >
                    ORDER SALE
                </h2>

                <div class="w-full h-[2px] bg-[#d1a14a]"></div>

            </div>

            <div class="bg-white rounded-[12px] shadow-sm border border-gray-200 p-7 mb-6">

                <?php if ($error != '') : ?>
                    <div class="mb-5 bg-red-100 border border-red-300 text-red-700 px-4 py-3 rounded-lg text-sm">
                        <?= e($error) ?>
                    </div>
                <?php endif; ?>

                <form method="POST">

                    <div class="flex flex-wrap items-center gap-5">

                        <label class="text-[16px] font-semibold text-[#26364d]">
                            Enter Member ID <span class="text-red-500">*</span>
                        </label>

                        <input
                            type="text"
                            name="member_id"
                            value="<?= e($memberId) ?>"
                            required
                            class="w-full max-w-[338px] h-[45px] border border-gray-300 bg-[#f7f7f9] rounded-[6px] px-4 text-[15px] focus:outline-none focus:ring-2 focus:ring-[#2b78c5]"
                        >

                    </div>

                    <p class="mt-3 text-[14px] text-[#52627a]">
                        Example IDs: fresh0003, fresh0004
                    </p>

                    <div class="mt-7 flex justify-center">
                        <button
                            type="submit"
                            class="w-[145px] h-[45px] rounded-[6px] bg-[#2b78c5] text-white text-[16px] font-medium hover:bg-[#2165aa] transition"
                        >
                            Submit
                        </button>
                    </div>

                </form>

            </div>

            <?php if ($_SERVER['REQUEST_METHOD'] === 'POST' && count($sales) > 0) : ?>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-5 mb-6">

                    <div class="bg-white rounded-[12px] shadow-sm border border-gray-200 p-5">
                        <p class="text-[13px] text-gray-500">Member ID</p>
                        <h3 class="text-[20px] font-semibold text-[#40352a] mt-1">
                            <?= e($memberId) ?>
                        </h3>
                    </div>

                    <div class="bg-white rounded-[12px] shadow-sm border border-gray-200 p-5">
                        <p class="text-[13px] text-gray-500">Total Orders</p>
                        <h3 class="text-[20px] font-semibold text-[#40352a] mt-1">
                            <?= count($sales) ?>
                        </h3>
                    </div>

                    <div class="bg-white rounded-[12px] shadow-sm border border-gray-200 p-5">
                        <p class="text-[13px] text-gray-500">Total Sale</p>
                        <h3 class="text-[20px] font-semibold text-green-600 mt-1">
                            ₹<?= number_format($totalSale, 2) ?>
                        </h3>
                    </div>

                </div>

                <div class="bg-white rounded-[12px] shadow-sm border border-gray-200 overflow-hidden">

                    <div class="table-responsive overflow-x-auto">

                        <table class="min-w-full">

                            <thead class="bg-[#40352a]">
                                <tr>
                                    <th class="px-5 py-4 text-left text-white text-[13px] font-semibold">#</th>
                                    <th class="px-5 py-4 text-left text-white text-[13px] font-semibold">Member ID</th>
                                    <th class="px-5 py-4 text-left text-white text-[13px] font-semibold">Member Name</th>
                                    <th class="px-5 py-4 text-left text-white text-[13px] font-semibold">Product</th>
                                    <th class="px-5 py-4 text-left text-white text-[13px] font-semibold">Qty</th>
                                    <th class="px-5 py-4 text-left text-white text-[13px] font-semibold">Price</th>
                                    <th class="px-5 py-4 text-left text-white text-[13px] font-semibold">Total</th>
                                    <th class="px-5 py-4 text-left text-white text-[13px] font-semibold">Date</th>
                                </tr>
                            </thead>

                            <tbody>

                                <?php foreach ($sales as $index => $sale) : ?>

                                    <tr class="border-b border-gray-200 hover:bg-gray-50">

                                        <td class="px-5 py-4 text-[14px]">
                                            <?= $index + 1 ?>
                                        </td>

                                        <td class="px-5 py-4 text-[14px] font-semibold text-[#40352a]">
                                            <?= e($sale['member_id']) ?>
                                        </td>

                                        <td class="px-5 py-4 text-[14px] break-words">
                                            <?= e($sale['member_name']) ?>
                                        </td>

                                        <td class="px-5 py-4 text-[14px] break-words">
                                            <?= e($sale['product_name']) ?>
                                        </td>

                                        <td class="px-5 py-4 text-[14px]">
                                            <?= e($sale['quantity']) ?>
                                        </td>

                                        <td class="px-5 py-4 text-[14px]">
                                            ₹<?= number_format((float)$sale['price'], 2) ?>
                                        </td>

                                        <td class="px-5 py-4 text-[14px] font-semibold text-green-600">
                                            ₹<?= number_format((float)$sale['total_amount'], 2) ?>
                                        </td>

                                        <td class="px-5 py-4 text-[14px] text-gray-600">
                                            <?= !empty($sale['created_at']) ? date('d M Y', strtotime($sale['created_at'])) : '-' ?>
                                        </td>

                                    </tr>

                                <?php endforeach; ?>

                            </tbody>

                        </table>

                    </div>

                </div>

            <?php endif; ?>

        </div>

    </main>

</div>

<script>
    lucide.createIcons();
</script>

</body>
</html>