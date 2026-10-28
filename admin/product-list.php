<?php
$customerId   = "MLM-00484";
$managerName  = "Swapnil Solanki";
$managerRole  = "MLM Manager";
$profileImage = "https://i.pravatar.cc/80?img=12";

include __DIR__ . '/config/db.php';

if (!isset($conn) || !($conn instanceof mysqli)) {
    die("Database connection failed.");
}

$conn->set_charset("utf8mb4");

$products = [];
$search = trim($_GET['search'] ?? '');

/*
|--------------------------------------------------------------------------
| FETCH PRODUCTS FROM franchies_add_product TABLE
|--------------------------------------------------------------------------
*/
$sql = "SELECT 
            id,
            product_name,
            price,
            quantity,
            status,
            created_at
        FROM franchies_add_product";

$params = [];
$types  = '';

if ($search !== '') {

    $sql .= " WHERE CONCAT_WS(' ',
                product_name,
                price,
                quantity,
                status
            ) LIKE ?";

    $types = 's';
    $params[] = '%' . $search . '%';
}

$sql .= " ORDER BY id DESC";

$stmt = $conn->prepare($sql);

if ($stmt) {

    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }

    $stmt->execute();

    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $products[] = $row;
    }

    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Franchise Product List</title>

    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
</head>

<body class="m-0 bg-[#f3f4f6] font-sans">

<?php include __DIR__ . '/includes/sidebar.php'; ?>

<div class="ml-64 min-h-screen">

    <?php include __DIR__ . '/includes/navbar.php'; ?>

    <main class="px-8 py-5">

        <!-- HEADER -->
        <div class="flex items-center gap-4 mb-7">

            <div class="w-9 h-9 rounded-[8px] border border-[#ead9b5] bg-[#f8f1e7] flex items-center justify-center shrink-0">
                <i data-lucide="package" class="w-5 h-5 text-[#c79435]"></i>
            </div>

            <h2 class="text-[24px] font-normal text-[#313131] leading-none tracking-wide"
                style="font-family: Georgia, 'Times New Roman', serif;">
                ADMIN PRODUCT LIST
            </h2>

            <div class="h-[1px] bg-[#c9963b] flex-1"></div>
        </div>

        <!-- SEARCH -->
        <!-- <div class="flex justify-end items-center gap-3 mb-4">

            <label for="search"
                   class="text-[14px] text-[#8a8a8a] font-semibold">
                Search:
            </label>

            <form method="GET" class="flex items-center">

                <input
                    id="search"
                    name="search"
                    type="text"
                    value="<?php echo htmlspecialchars($search); ?>"
                    placeholder="Search product..."
                    class="w-[220px] h-[38px] rounded-[12px] border border-[#d0d3d8] bg-white px-4 text-[14px] text-[#4a4a4a] outline-none focus:border-[#c9963b]"
                >

            </form>
        </div> -->

        <!-- TABLE -->
        <div class="w-full overflow-x-auto rounded-[16px]">

            <table class="w-full border-separate border-spacing-0 min-w-[800px]">

                <thead>
                    <tr class="bg-[#c99539] text-white text-[14px]">

                        <th class="h-[42px] px-4 text-center font-semibold rounded-l-[12px] whitespace-nowrap">
                            No.
                        </th>

                        <th class="h-[42px] px-4 text-left font-semibold whitespace-nowrap">
                            Product Name
                        </th>

                        <th class="h-[42px] px-4 text-left font-semibold whitespace-nowrap">
                            Price
                        </th>

                        <th class="h-[42px] px-4 text-left font-semibold whitespace-nowrap">
                            Quantity
                        </th>

                        <th class="h-[42px] px-4 text-left font-semibold whitespace-nowrap">
                            Status
                        </th>

                        <!-- <th class="h-[42px] px-4 text-left font-semibold rounded-r-[12px] whitespace-nowrap">
                            Action
                        </th> -->

                    </tr>
                </thead>

                <tbody>

                <?php if (!empty($products)): ?>

                    <?php $i = 1; ?>

                    <?php foreach ($products as $row): ?>

                        <tr class="bg-white hover:bg-[#fafafa] transition">

                            <!-- NO -->
                            <td class="px-4 py-3 text-[14px] text-[#5b5b5b] border-b border-[#ececec] text-center">
                                <?php echo $i++; ?>
                            </td>

                            <!-- PRODUCT -->
                            <td class="px-4 py-3 text-[14px] font-medium text-[#333] border-b border-[#ececec]">
                                <?php echo htmlspecialchars($row['product_name']); ?>
                            </td>

                            <!-- PRICE -->
                            <td class="px-4 py-3 text-[14px] text-[#5b5b5b] border-b border-[#ececec]">
                                ₹ <?php echo number_format($row['price'], 2); ?>
                            </td>

                            <!-- QUANTITY -->
                            <td class="px-4 py-3 text-[14px] text-[#5b5b5b] border-b border-[#ececec]">
                                <?php echo (int)$row['quantity']; ?>
                            </td>

                            <!-- STATUS -->
                            <td class="px-4 py-3 border-b border-[#ececec]">

                                <?php if ($row['status'] == 'Active'): ?>

                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-[12px] font-semibold bg-green-100 text-green-700">
                                        Active
                                    </span>

                                <?php else: ?>

                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-[12px] font-semibold bg-red-100 text-red-700">
                                        Inactive
                                    </span>

                                <?php endif; ?>

                            </td>

                            <!-- ACTION -->
                            <!-- <td class="px-4 py-3 border-b border-[#ececec]">

                                <a href="add-product.php?edit_id=<?php echo (int)$row['id']; ?>"
                                   class="inline-flex items-center justify-center w-9 h-9 rounded-lg bg-[#f3f4f6] hover:bg-[#e5e7eb] transition">

                                    <i data-lucide="square-pen"
                                       class="w-4 h-4 text-[#666]"></i>

                                </a>

                            </td> -->

                        </tr>

                    <?php endforeach; ?>

                <?php else: ?>

                    <tr>

                        <td colspan="6"
                            class="h-[350px] text-center text-[#8a8a8a] bg-white rounded-b-[12px]">

                            No products found.

                        </td>

                    </tr>

                <?php endif; ?>

                </tbody>

            </table>

        </div>

    </main>

</div>

<script>
    lucide.createIcons();
</script>

</body>
</html>