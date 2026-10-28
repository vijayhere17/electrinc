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

$successMessage = '';
$errorMessage = '';

$editId = isset($_GET['edit_id']) ? (int) $_GET['edit_id'] : 0;
$isEditMode = false;

$productName = '';
$productAmount = '';
$pvBv = '';
$capping = '';
$quantity = '';
$sponsorPercentage = '';
$binaryPercentage = '';
$productType = '';
$productStatus = '';
$displayOrder = '';

/*
|--------------------------------------------------------------------------
| Create table automatically if missing
|--------------------------------------------------------------------------
*/
$createTableSql = "
CREATE TABLE IF NOT EXISTS products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    type VARCHAR(50) NOT NULL,
    product_name VARCHAR(255) NOT NULL,
    amount DECIMAL(10,2) NOT NULL DEFAULT 0,
    pv_bv DECIMAL(10,2) NOT NULL DEFAULT 0,
    capping DECIMAL(10,2) NOT NULL DEFAULT 0,
    quantity INT NOT NULL DEFAULT 0,
    sponsor_percentage DECIMAL(10,2) NOT NULL DEFAULT 0,
    binary_percentage DECIMAL(10,2) NOT NULL DEFAULT 0,
    status VARCHAR(50) NOT NULL,
    display_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
";
$conn->query($createTableSql);

/*
|--------------------------------------------------------------------------
| Load edit data
|--------------------------------------------------------------------------
*/
if ($editId > 0 && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    $editStmt = $conn->prepare("SELECT * FROM products WHERE id = ? LIMIT 1");
    if ($editStmt) {
        $editStmt->bind_param("i", $editId);
        $editStmt->execute();
        $result = $editStmt->get_result();

        if ($result && $result->num_rows === 1) {
            $row = $result->fetch_assoc();
            $isEditMode = true;

            $productName = $row['product_name'];
            $productAmount = $row['amount'];
            $pvBv = $row['pv_bv'];
            $capping = $row['capping'];
            $quantity = $row['quantity'];
            $sponsorPercentage = $row['sponsor_percentage'];
            $binaryPercentage = $row['binary_percentage'];
            $productType = $row['type'];
            $productStatus = $row['status'];
            $displayOrder = $row['display_order'];
        } else {
            $errorMessage = 'Product not found.';
        }

        $editStmt->close();
    }
}

/*
|--------------------------------------------------------------------------
| Save / Update
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $editId = (int)($_POST['edit_id'] ?? 0);

    $productName = trim($_POST['product_name'] ?? '');
    $productAmount = trim($_POST['product_amount'] ?? '');
    $pvBv = trim($_POST['pv_bv'] ?? '');
    $capping = trim($_POST['capping'] ?? '');
    $quantity = trim($_POST['quantity'] ?? '');
    $sponsorPercentage = trim($_POST['sponsor_percentage'] ?? '');
    $binaryPercentage = trim($_POST['binary_percentage'] ?? '');
    $productType = trim($_POST['product_type'] ?? '');
    $productStatus = trim($_POST['product_status'] ?? '');
    $displayOrder = trim($_POST['display_order'] ?? '');

    $isEditMode = $editId > 0;

    if (
        $productName === '' || $productAmount === '' || $pvBv === '' || $capping === '' ||
        $quantity === '' || $sponsorPercentage === '' || $binaryPercentage === '' ||
        $productType === '' || $productStatus === '' || $displayOrder === '' ||
        $productType === 'Select Product Type' || $productStatus === 'Select Product Status'
    ) {
        $errorMessage = 'Please fill all required fields.';
    } elseif (
        !is_numeric($productAmount) || !is_numeric($pvBv) || !is_numeric($capping) ||
        !is_numeric($quantity) || !is_numeric($sponsorPercentage) ||
        !is_numeric($binaryPercentage) || !is_numeric($displayOrder)
    ) {
        $errorMessage = 'Please enter valid numeric values.';
    } else {
        $amount = (float)$productAmount;
        $pv = (float)$pvBv;
        $cap = (float)$capping;
        $qty = (int)$quantity;
        $sponsor = (float)$sponsorPercentage;
        $binary = (float)$binaryPercentage;
        $order = (int)$displayOrder;

        if ($isEditMode) {
            $stmt = $conn->prepare("
                UPDATE products
                SET type = ?, product_name = ?, amount = ?, pv_bv = ?, capping = ?, quantity = ?,
                    sponsor_percentage = ?, binary_percentage = ?, status = ?, display_order = ?
                WHERE id = ?
            ");

            if ($stmt) {
                $stmt->bind_param(
                    'ssdddiddsii',
                    $productType,
                    $productName,
                    $amount,
                    $pv,
                    $cap,
                    $qty,
                    $sponsor,
                    $binary,
                    $productStatus,
                    $order,
                    $editId
                );

                if ($stmt->execute()) {
                    $successMessage = 'Product updated successfully.';
                } else {
                    $errorMessage = 'Unable to update product.';
                }
                $stmt->close();
            } else {
                $errorMessage = 'Database query preparation failed.';
            }
        } else {
            $stmt = $conn->prepare("
                INSERT INTO products
                (type, product_name, amount, pv_bv, capping, quantity, sponsor_percentage, binary_percentage, status, display_order)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            if ($stmt) {
                $stmt->bind_param(
                    'ssdddiddsi',
                    $productType,
                    $productName,
                    $amount,
                    $pv,
                    $cap,
                    $qty,
                    $sponsor,
                    $binary,
                    $productStatus,
                    $order
                );

                if ($stmt->execute()) {
                    $successMessage = 'Product added successfully.';

                    $productName = '';
                    $productAmount = '';
                    $pvBv = '';
                    $capping = '';
                    $quantity = '';
                    $sponsorPercentage = '';
                    $binaryPercentage = '';
                    $productType = '';
                    $productStatus = '';
                    $displayOrder = '';
                } else {
                    $errorMessage = 'Unable to save product.';
                }
                $stmt->close();
            } else {
                $errorMessage = 'Database query preparation failed.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Product Master</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body class="m-0 bg-[#f3f4f6] font-sans">

<div class="ml-64 min-h-screen w-[calc(100%-16rem)]">

    <?php include __DIR__ . '/includes/sidebar.php'; ?>

    <div class="flex-1 min-w-0 flex flex-col">
        <?php include __DIR__ . '/includes/navbar.php'; ?>

        <main class="px-8 py-5">

            <?php if ($successMessage !== ''): ?>
                <div class="mb-5 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-green-700 text-sm">
                    <?php echo htmlspecialchars($successMessage); ?>
                </div>
            <?php endif; ?>

            <?php if ($errorMessage !== ''): ?>
                <div class="mb-5 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-red-700 text-sm">
                    <?php echo htmlspecialchars($errorMessage); ?>
                </div>
            <?php endif; ?>

            <div class="flex items-center gap-4 mb-14">
                <div class="w-9 h-9 rounded-[8px] border border-[#ead9b5] bg-[#f8f1e7] flex items-center justify-center shrink-0">
                    <i data-lucide="badge-dollar-sign" class="w-5 h-5 text-[#c79435]"></i>
                </div>

                <h2 class="text-[24px] font-normal text-[#313131] leading-none tracking-wide" style="font-family: Georgia, 'Times New Roman', serif;">
                    PRODUCT MASTER
                </h2>

                <div class="h-[1px] bg-[#c9963b] flex-1"></div>
            </div>

            <div class="max-w-[1280px] mx-auto">
                <form method="POST" action="" class="space-y-5">
                    <input type="hidden" name="edit_id" value="<?php echo (int)$editId; ?>">

                    <div class="grid grid-cols-[260px_1fr] gap-x-10 items-center">
                        <label class="text-right text-[18px] text-[#9c9c9c] font-semibold">
                            <span class="text-red-500">*</span> Product Name
                        </label>
                        <input type="text" name="product_name" value="<?php echo htmlspecialchars($productName); ?>"
                            class="w-full h-[44px] rounded-[12px] border border-[#d4d6db] bg-[#e8e9eb] px-4 text-[16px] text-[#4a4a4a] outline-none">
                    </div>

                    <div class="grid grid-cols-[260px_1fr] gap-x-10 items-center">
                        <label class="text-right text-[18px] text-[#9c9c9c] font-semibold">
                            <span class="text-red-500">*</span> Product Amount
                        </label>
                        <input type="text" name="product_amount" value="<?php echo htmlspecialchars($productAmount); ?>"
                            class="w-full h-[44px] rounded-[12px] border border-[#d4d6db] bg-[#e8e9eb] px-4 text-[16px] text-[#4a4a4a] outline-none">
                    </div>

                    <!-- <div class="grid grid-cols-[260px_1fr] gap-x-10 items-center">
                        <label class="text-right text-[18px] text-[#9c9c9c] font-semibold">
                            <span class="text-red-500">*</span> PV/BV
                        </label>
                        <input type="text" name="pv_bv" value="<?php echo htmlspecialchars($pvBv); ?>"
                            class="w-full h-[44px] rounded-[12px] border border-[#d4d6db] bg-[#e8e9eb] px-4 text-[16px] text-[#4a4a4a] outline-none">
                    </div> -->

                    <!-- <div class="grid grid-cols-[260px_660px] gap-x-10 items-center">
                        <label class="text-right text-[18px] text-[#9c9c9c] font-semibold">
                            <span class="text-red-500">*</span> Capping
                        </label>
                        <input type="text" name="capping" value="<?php echo htmlspecialchars($capping); ?>"
                            class="w-full h-[44px] rounded-[12px] border border-[#d4d6db] bg-[#e8e9eb] px-4 text-[16px] text-[#4a4a4a] outline-none">
                    </div> -->

                    <div class="grid grid-cols-[260px_660px] gap-x-10 items-center">
                        <label class="text-right text-[18px] text-[#9c9c9c] font-semibold">
                            <span class="text-red-500">*</span> Quantity
                        </label>
                        <input type="text" name="quantity" value="<?php echo htmlspecialchars($quantity); ?>"
                            class="w-full h-[44px] rounded-[12px] border border-[#d4d6db] bg-[#e8e9eb] px-4 text-[16px] text-[#4a4a4a] outline-none">
                    </div>

                    <!-- <div class="grid grid-cols-[260px_660px] gap-x-10 items-center">
                        <label class="text-right text-[18px] text-[#9c9c9c] font-semibold">
                            <span class="text-red-500">*</span> Sponsor Percentage
                        </label>
                        <input type="text" name="sponsor_percentage" value="<?php echo htmlspecialchars($sponsorPercentage); ?>"
                            class="w-full h-[44px] rounded-[12px] border border-[#d4d6db] bg-[#e8e9eb] px-4 text-[16px] text-[#4a4a4a] outline-none">
                    </div> -->

                    <!-- <div class="grid grid-cols-[260px_660px] gap-x-10 items-center">
                        <label class="text-right text-[18px] text-[#9c9c9c] font-semibold">
                            <span class="text-red-500">*</span> Binary Percentage
                        </label>
                        <input type="text" name="binary_percentage" value="<?php echo htmlspecialchars($binaryPercentage); ?>"
                            class="w-full h-[44px] rounded-[12px] border border-[#d4d6db] bg-[#e8e9eb] px-4 text-[16px] text-[#4a4a4a] outline-none">
                    </div> -->

                    <!-- <div class="grid grid-cols-[260px_660px] gap-x-10 items-center">
                        <label class="text-right text-[18px] text-[#9c9c9c] font-semibold">
                            <span class="text-red-500">*</span> Product Type
                        </label>
                        <div class="relative">
                            <select name="product_type"
                                class="w-full h-[44px] rounded-[12px] border border-[#d4d6db] bg-[#e8e9eb] px-4 pr-12 text-[16px] text-[#9c9c9c] outline-none appearance-none">
                                <option <?php if ($productType === '') echo 'selected'; ?>>Select Product Type</option>
                                <option value="Physical" <?php if ($productType === 'Physical') echo 'selected'; ?>>Physical</option>
                                <option value="Digital" <?php if ($productType === 'Digital') echo 'selected'; ?>>Digital</option>
                            </select>
                            <i data-lucide="chevron-down" class="w-5 h-5 text-[#8b8b8b] absolute right-4 top-1/2 -translate-y-1/2 pointer-events-none"></i>
                        </div>
                    </div> -->

                    <!-- <div class="grid grid-cols-[260px_660px] gap-x-10 items-center">
                        <label class="text-right text-[18px] text-[#9c9c9c] font-semibold">
                            <span class="text-red-500">*</span> Product Status
                        </label>
                        <div class="relative">
                            <select name="product_status"
                                class="w-full h-[44px] rounded-[12px] border border-[#d4d6db] bg-[#e8e9eb] px-4 pr-12 text-[16px] text-[#9c9c9c] outline-none appearance-none">
                                <option <?php if ($productStatus === '') echo 'selected'; ?>>Select Product Status</option>
                                <option value="Active" <?php if ($productStatus === 'Active') echo 'selected'; ?>>Active</option>
                                <option value="Inactive" <?php if ($productStatus === 'Inactive') echo 'selected'; ?>>Inactive</option>
                            </select>
                            <i data-lucide="chevron-down" class="w-5 h-5 text-[#8b8b8b] absolute right-4 top-1/2 -translate-y-1/2 pointer-events-none"></i>
                        </div>
                    </div> -->

                    <!-- <div class="grid grid-cols-[260px_660px] gap-x-10 items-center">
                        <label class="text-right text-[18px] text-[#9c9c9c] font-semibold">
                            <span class="text-red-500">*</span> Display Order
                        </label>
                        <input type="text" name="display_order" value="<?php echo htmlspecialchars($displayOrder); ?>"
                            class="w-full h-[44px] rounded-[12px] border border-[#d4d6db] bg-[#e8e9eb] px-4 text-[16px] text-[#4a4a4a] outline-none">
                    </div> -->

                    <div class="pt-8 flex justify-center gap-3">
                        <button type="submit"
                            class="w-[168px] h-[44px] rounded-[12px] bg-[#be8b36] hover:bg-[#a97a2f] text-white text-[16px] font-semibold transition">
                            <?php echo $isEditMode ? 'Update' : 'Add'; ?>
                        </button>

                        <?php if ($isEditMode): ?>
                            <a href="product-master.php"
                               class="w-[168px] h-[44px] rounded-[12px] bg-[#8d8d8d] hover:bg-[#777777] text-white text-[16px] font-semibold transition inline-flex items-center justify-center">
                                Cancel
                            </a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

        </main>
    </div>
</div>

<script>
    lucide.createIcons();
</script>
</body>
</html>