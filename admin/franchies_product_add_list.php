<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/config/db.php';

function e($v) {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

$selfPage = basename($_SERVER['PHP_SELF']);

$successMessage = '';
$errorMessage = '';

$productName   = '';
$productPrice  = '';
$productQty    = '';
$productGst    = '';
$productBv     = '';
$productStatus = 'Active';
$productType = 'repurchase';

$editId = 0;
$isEditMode = false;

/*
|--------------------------------------------------------------------------
| CREATE QUANTITY COLUMN IF NOT EXISTS
|--------------------------------------------------------------------------
*/

$checkColumn = $conn->query("
    SHOW COLUMNS FROM franchies_add_product 
    LIKE 'quantity'
");

if ($checkColumn && $checkColumn->num_rows === 0) {

    $conn->query("
        ALTER TABLE franchies_add_product
        ADD quantity INT NOT NULL DEFAULT 0
        AFTER price
    ");
}



// CREATE GST AND BV COLUMN IF NOT EXISTS
foreach (['gst','bv'] as $col) {
    $checkColumn = $conn->query("SHOW COLUMNS FROM franchies_add_product LIKE '$col'");
    if ($checkColumn && $checkColumn->num_rows === 0) {
        $conn->query("ALTER TABLE franchies_add_product ADD $col DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER price");
    }
}

/*
|--------------------------------------------------------------------------
| CREATE STOCK LOG TABLE IF NOT EXISTS
|--------------------------------------------------------------------------
| Keeps a full audit trail every time stock is added to a product:
| previous quantity, quantity added, resulting quantity, who added it,
| an optional remark, and the timestamp.
*/

$conn->query("
    CREATE TABLE IF NOT EXISTS franchies_product_stock_log (
        id INT AUTO_INCREMENT PRIMARY KEY,
        product_id INT NOT NULL,
        previous_quantity INT NOT NULL DEFAULT 0,
        added_quantity INT NOT NULL DEFAULT 0,
        new_quantity INT NOT NULL DEFAULT 0,
        added_by VARCHAR(100) NOT NULL DEFAULT 'Admin',
        remarks VARCHAR(255) DEFAULT NULL,
        created_at DATETIME NOT NULL,
        KEY idx_product_id (product_id),
        KEY idx_created_at (created_at)
    )
");

/*
|--------------------------------------------------------------------------
| DELETE PRODUCT
|--------------------------------------------------------------------------
*/

if (isset($_GET['delete'])) {

    $deleteId = (int)$_GET['delete'];

    if ($deleteId > 0) {

        $stmt = $conn->prepare("
            DELETE FROM franchies_add_product
            WHERE id = ?
        ");

        $stmt->bind_param("i", $deleteId);

        if ($stmt->execute()) {

            $_SESSION['success_msg'] = "Product deleted successfully.";

        } else {

            $_SESSION['error_msg'] = "Unable to delete product.";
        }

        $stmt->close();
    }

    header("Location: " . $selfPage);
    exit;
}

/*
|--------------------------------------------------------------------------
| EDIT FETCH
|--------------------------------------------------------------------------
*/

if (isset($_GET['edit'])) {

    $editId = (int)$_GET['edit'];

    if ($editId > 0) {

        $stmt = $conn->prepare("
            SELECT
    id,
    product_name,
    price,
    gst,
    bv,
    quantity,
    product_type,
    status
FROM franchies_add_product
            WHERE id = ?
            LIMIT 1
        ");

        $stmt->bind_param("i", $editId);
        $stmt->execute();

        $result = $stmt->get_result();

        if ($row = $result->fetch_assoc()) {

            $isEditMode  = true;
            $productName = $row['product_name'];
            $productPrice = $row['price'];
            $productQty = $row['quantity'];
            $productGst = $row['gst'];
            $productBv = $row['bv'];
            $productStatus = $row['status'];
            $productType = $row['product_type'];
        }

        $stmt->close();
    }
}

/*
|--------------------------------------------------------------------------
| ADD STOCK (separate from Add / Edit product)
|--------------------------------------------------------------------------
| This does NOT touch product name/price/gst/bv. It only increases the
| existing quantity and writes a row to franchies_product_stock_log so
| there is a full "before -> added -> after" history.
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_stock_submit'])) {

    $stockProductId = (int)($_POST['stock_product_id'] ?? 0);
    $addQtyRaw      = trim($_POST['add_qty'] ?? '');
    $remarks        = trim($_POST['remarks'] ?? '');

    // Try common session keys for the logged in admin's name/id, fallback to 'Admin'.
    $addedBy = $_SESSION['admin_name']
        ?? $_SESSION['admin_username']
        ?? $_SESSION['admin_id']
        ?? 'Admin';

    if ($stockProductId <= 0) {

        $_SESSION['error_msg'] = "Invalid product selected for stock update.";

    } elseif ($addQtyRaw === '' || !is_numeric($addQtyRaw) || (int)$addQtyRaw <= 0) {

        $_SESSION['error_msg'] = "Please enter a valid quantity to add.";

    } else {

        $addQty = (int)$addQtyRaw;

        $conn->begin_transaction();

        try {

            $stmt = $conn->prepare("
                SELECT quantity FROM franchies_add_product
                WHERE id = ?
                FOR UPDATE
            ");
            $stmt->bind_param("i", $stockProductId);
            $stmt->execute();
            $res = $stmt->get_result();
            $prod = $res->fetch_assoc();
            $stmt->close();

            if (!$prod) {
                throw new Exception("Product not found.");
            }

            $previousQty = (int)$prod['quantity'];
            $newQty = $previousQty + $addQty;

            $stmt = $conn->prepare("
                UPDATE franchies_add_product
                SET quantity = ?
                WHERE id = ?
            ");
            $stmt->bind_param("ii", $newQty, $stockProductId);
            $stmt->execute();
            $stmt->close();

            $stmt = $conn->prepare("
                INSERT INTO franchies_product_stock_log
                    (product_id, previous_quantity, added_quantity, new_quantity, added_by, remarks, created_at)
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->bind_param(
                "iiiiss",
                $stockProductId,
                $previousQty,
                $addQty,
                $newQty,
                $addedBy,
                $remarks
            );
            $stmt->execute();
            $stmt->close();

            $conn->commit();

            $_SESSION['success_msg'] = "Stock added successfully. New quantity: " . $newQty;

        } catch (Exception $ex) {

            $conn->rollback();
            $_SESSION['error_msg'] = "Unable to add stock. Please try again.";
        }
    }

    header("Location: " . $selfPage);
    exit;
}

/*
|--------------------------------------------------------------------------
| ADD / UPDATE
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['add_stock_submit'])) {

    $editId = (int)($_POST['edit_id'] ?? 0);

    $productName   = trim($_POST['product_name'] ?? '');
    $productPrice  = trim($_POST['price'] ?? '');
    $productQty    = trim($_POST['quantity'] ?? '');
    $productGst    = trim($_POST['gst'] ?? '0');
    $productBv     = trim($_POST['bv'] ?? '0');
    $productStatus = trim($_POST['status'] ?? 'Active');
    $productType = trim($_POST['product_type'] ?? 'repurchase');

    if ($productName === '') {

        $errorMessage = "Please enter product name.";

    } elseif (
        $productPrice === '' ||
        !is_numeric($productPrice) ||
        (float)$productPrice <= 0
    ) {

        $errorMessage = "Please enter valid product price.";

    } elseif (
        $productQty === '' ||
        !is_numeric($productQty) ||
        (int)$productQty < 0
    ) {

        $errorMessage = "Please enter valid quantity.";

    } elseif (
        !in_array($productStatus, ['Active', 'Inactive'], true)
    ) {

        $errorMessage = "Please select valid status.";

    } else {

        $price = (float)$productPrice;
        $quantity = (int)$productQty;
        $gst = (float)$productGst;
        $bv = (float)$productBv;

        /*
        |--------------------------------------------------------------------------
        | UPDATE
        |--------------------------------------------------------------------------
        */

        if ($editId > 0) {

            // If quantity was changed directly from the Edit form, log it too,
            // so the stock report stays complete even for manual edits.
            $stmtOld = $conn->prepare("SELECT quantity FROM franchies_add_product WHERE id = ? LIMIT 1");
            $stmtOld->bind_param("i", $editId);
            $stmtOld->execute();
            $oldRow = $stmtOld->get_result()->fetch_assoc();
            $stmtOld->close();

            $stmt = $conn->prepare("
                UPDATE franchies_add_product
SET
    product_name = ?,
    price = ?,
    gst = ?,
    bv = ?,
    quantity = ?,
    product_type = ?,
    status = ?
WHERE id = ?
            ");

           $stmt->bind_param(
    "sdddissi",
    $productName,
    $price,
    $gst,
    $bv,
    $quantity,
    $productType,
    $productStatus,
    $editId
);

            if ($stmt->execute()) {

                if ($oldRow && (int)$oldRow['quantity'] !== $quantity) {

                    $diff = $quantity - (int)$oldRow['quantity'];
                    $addedByEdit = $_SESSION['admin_name']
                        ?? $_SESSION['admin_username']
                        ?? $_SESSION['admin_id']
                        ?? 'Admin';
                    $remarkEdit = $diff >= 0 ? 'Manual edit (increase)' : 'Manual edit (decrease)';

                    $stmtLog = $conn->prepare("
                        INSERT INTO franchies_product_stock_log
                            (product_id, previous_quantity, added_quantity, new_quantity, added_by, remarks, created_at)
                        VALUES (?, ?, ?, ?, ?, ?, NOW())
                    ");
                    $oldQtyVal = (int)$oldRow['quantity'];
                    $stmtLog->bind_param(
                        "iiiiss",
                        $editId,
                        $oldQtyVal,
                        $diff,
                        $quantity,
                        $addedByEdit,
                        $remarkEdit
                    );
                    $stmtLog->execute();
                    $stmtLog->close();
                }

                $_SESSION['success_msg']
                    = "Product updated successfully.";

                header("Location: " . $selfPage);
                exit;

            } else {

                $errorMessage = "Unable to update product.";
            }

            $stmt->close();

        } else {

            /*
            |--------------------------------------------------------------------------
            | INSERT
            |--------------------------------------------------------------------------
            */

            $stmt = $conn->prepare("
    INSERT INTO franchies_add_product
    (
        product_name,
        price,
        gst,
        bv,
        quantity,
        product_type,
        status,
        created_at
    )
    VALUES
    (
        ?, ?, ?, ?, ?, ?, ?, NOW()
    )
");

$stmt->bind_param(
    "sdddiss",
    $productName,
    $price,
    $gst,
    $bv,
    $quantity,
    $productType,
    $productStatus
);

            if ($stmt->execute()) {

                $newProductId = $stmt->insert_id;

                // Log the opening stock too, so the report has a starting point.
                if ($quantity > 0) {
                    $addedByNew = $_SESSION['admin_name']
                        ?? $_SESSION['admin_username']
                        ?? $_SESSION['admin_id']
                        ?? 'Admin';
                    $zero = 0;
                    $openingRemark = 'Opening stock (new product)';

                    $stmtLog = $conn->prepare("
                        INSERT INTO franchies_product_stock_log
                            (product_id, previous_quantity, added_quantity, new_quantity, added_by, remarks, created_at)
                        VALUES (?, ?, ?, ?, ?, ?, NOW())
                    ");
                    $stmtLog->bind_param(
                        "iiiiss",
                        $newProductId,
                        $zero,
                        $quantity,
                        $quantity,
                        $addedByNew,
                        $openingRemark
                    );
                    $stmtLog->execute();
                    $stmtLog->close();
                }

                $_SESSION['success_msg']
                    = "Product added successfully.";

                header("Location: " . $selfPage);
                exit;

            } else {

                $errorMessage = "Unable to add product.";
            }

            $stmt->close();
        }
    }
}

/*
|--------------------------------------------------------------------------
| SESSION MESSAGE
|--------------------------------------------------------------------------
*/

if (isset($_SESSION['success_msg'])) {

    $successMessage = $_SESSION['success_msg'];
    unset($_SESSION['success_msg']);
}

if (isset($_SESSION['error_msg'])) {

    $errorMessage = $_SESSION['error_msg'];
    unset($_SESSION['error_msg']);
}

/*
|--------------------------------------------------------------------------
| FETCH PRODUCTS
|--------------------------------------------------------------------------
*/

$products = [];

$result = $conn->query("
    SELECT
    id,
    product_name,
    price,
    gst,
    bv,
    quantity,
    product_type,
    status,
        created_at
    FROM franchies_add_product
    ORDER BY id DESC
");

if ($result) {

    while ($row = $result->fetch_assoc()) {

        $products[] = $row;
    }
}

/*
|--------------------------------------------------------------------------
| QUICK STOCK STATS (for the small summary bar)
|--------------------------------------------------------------------------
*/

$totalProducts = count($products);
$totalStockQty = 0;
foreach ($products as $p) {
    $totalStockQty += (int)$p['quantity'];
}

$todayAdded = 0;
$todayRes = $conn->query("
    SELECT COALESCE(SUM(added_quantity), 0) AS total_added
    FROM franchies_product_stock_log
    WHERE added_quantity > 0
      AND DATE(created_at) = CURDATE()
");
if ($todayRes && ($row = $todayRes->fetch_assoc())) {
    $todayAdded = (int)$row['total_added'];
}
?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>Admin Product Stocks</title>

<script src="https://cdn.tailwindcss.com"></script>

<script src="https://unpkg.com/lucide@latest"></script>

</head>

<body class="bg-[#f3f4f6] font-sans overflow-x-hidden">

<!-- SIDEBAR -->

<?php include __DIR__ . "/includes/sidebar.php"; ?>

<!-- MAIN -->

<div class="lg:ml-64 min-h-screen bg-[#f3f4f6]">

    <!-- NAVBAR -->

    <?php include __DIR__ . "/includes/navbar.php"; ?>

    <!-- CONTENT -->

    <main class="px-4 sm:px-6 lg:px-8 py-8">

        <!-- SUCCESS -->

        <?php if ($successMessage !== ''): ?>

            <div class="mb-5 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-green-700 text-sm">
                <?php echo e($successMessage); ?>
            </div>

        <?php endif; ?>

        <!-- ERROR -->

        <?php if ($errorMessage !== ''): ?>

            <div class="mb-5 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-red-700 text-sm">
                <?php echo e($errorMessage); ?>
            </div>

        <?php endif; ?>

        <!-- PAGE TITLE -->

        <div class="flex items-center gap-4 mb-6">

            <div class="w-9 h-9 rounded-[8px] border border-[#ead9b5] bg-[#f8f1e7] flex items-center justify-center">

                <i data-lucide="package-plus"
                   class="w-5 h-5 text-[#c79435]"></i>

            </div>

            <h2 class="text-[22px] sm:text-[24px] font-normal text-[#313131] tracking-wide"
                style="font-family: Georgia, 'Times New Roman', serif;">

                ADMIN PRODUCT STOCKS

            </h2>

            <div class="h-[1px] bg-[#c9963b] flex-1"></div>

            <a href="franchies_product_stock_report.php"
               class="inline-flex items-center gap-2 rounded-[10px] bg-[#313131] hover:bg-black text-white text-sm font-semibold px-4 py-2 whitespace-nowrap transition">
                <i data-lucide="clipboard-list" class="w-4 h-4"></i>
                Stock Report
            </a>

        </div>

        <!-- QUICK STATS -->

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-8">

            <div class="bg-white rounded-2xl shadow-sm p-5 flex items-center gap-4">
                <div class="w-11 h-11 rounded-xl bg-blue-50 flex items-center justify-center">
                    <i data-lucide="package" class="w-5 h-5 text-blue-600"></i>
                </div>
                <div>
                    <p class="text-xs text-slate-500 font-semibold uppercase tracking-wide">Total Products</p>
                    <p class="text-xl font-bold text-slate-800"><?php echo (int)$totalProducts; ?></p>
                </div>
            </div>

            <div class="bg-white rounded-2xl shadow-sm p-5 flex items-center gap-4">
                <div class="w-11 h-11 rounded-xl bg-amber-50 flex items-center justify-center">
                    <i data-lucide="boxes" class="w-5 h-5 text-amber-600"></i>
                </div>
                <div>
                    <p class="text-xs text-slate-500 font-semibold uppercase tracking-wide">Total Stock (All Products)</p>
                    <p class="text-xl font-bold text-slate-800"><?php echo (int)$totalStockQty; ?></p>
                </div>
            </div>

            <div class="bg-white rounded-2xl shadow-sm p-5 flex items-center gap-4">
                <div class="w-11 h-11 rounded-xl bg-green-50 flex items-center justify-center">
                    <i data-lucide="trending-up" class="w-5 h-5 text-green-600"></i>
                </div>
                <div>
                    <p class="text-xs text-slate-500 font-semibold uppercase tracking-wide">Stock Added Today</p>
                    <p class="text-xl font-bold text-slate-800">+<?php echo (int)$todayAdded; ?></p>
                </div>
            </div>

        </div>

        <!-- FORM CARD -->

        <div class="bg-white rounded-2xl shadow-sm p-4 sm:p-6 lg:p-8 mb-8">

            <form method="POST"
                  action="<?php echo e($selfPage); ?>"
                  class="space-y-5">

                <input type="hidden"
                       name="edit_id"
                       value="<?php echo (int)$editId; ?>">

                <!-- PRODUCT NAME -->

                <div class="grid grid-cols-1 md:grid-cols-[220px_1fr] gap-3 md:gap-x-10 items-center">

                    <label class="text-left md:text-right text-[16px] text-[#9c9c9c] font-semibold">

                        <span class="text-red-500">*</span>
                        Product Name

                    </label>

                    <input type="text"
                           name="product_name"
                           value="<?php echo e($productName); ?>"
                           placeholder="Enter product name"
                           required
                           class="w-full h-[44px] rounded-[12px] border border-[#d4d6db] bg-[#e8e9eb] px-4 text-[16px] text-[#4a4a4a] outline-none">

                </div>

                <!-- PRODUCT PRICE -->

                <div class="grid grid-cols-1 md:grid-cols-[220px_1fr] gap-3 md:gap-x-10 items-center">

                    <label class="text-left md:text-right text-[16px] text-[#9c9c9c] font-semibold">

                        <span class="text-red-500">*</span>
                        Product Price

                    </label>

                    <input type="number"
                           step="0.01"
                           min="1"
                           name="price"
                           value="<?php echo e($productPrice); ?>"
                           placeholder="Enter product price"
                           required
                           class="w-full h-[44px] rounded-[12px] border border-[#d4d6db] bg-[#e8e9eb] px-4 text-[16px] text-[#4a4a4a] outline-none">

                </div>

                

                <!-- GST -->
                <div class="grid grid-cols-1 md:grid-cols-[220px_1fr] gap-3 md:gap-x-10 items-center">
                    <label class="text-left md:text-right text-[16px] text-[#9c9c9c] font-semibold"><span class="text-red-500">*</span> GST</label>
                    <input type="number" step="0.01" name="gst" value="<?php echo e($productGst); ?>" placeholder="Enter GST" required class="w-full h-[44px] rounded-[12px] border border-[#d4d6db] bg-[#e8e9eb] px-4 text-[16px] text-[#4a4a4a] outline-none">
                </div>

                <!-- BV -->
                <div class="grid grid-cols-1 md:grid-cols-[220px_1fr] gap-3 md:gap-x-10 items-center">
                    <label class="text-left md:text-right text-[16px] text-[#9c9c9c] font-semibold"><span class="text-red-500">*</span> BV</label>
                    <input type="number" step="0.01" name="bv" value="<?php echo e($productBv); ?>" placeholder="Enter BV" required class="w-full h-[44px] rounded-[12px] border border-[#d4d6db] bg-[#e8e9eb] px-4 text-[16px] text-[#4a4a4a] outline-none">
                </div>
<!-- QUANTITY -->

                <div class="grid grid-cols-1 md:grid-cols-[220px_1fr] gap-3 md:gap-x-10 items-center">

                    <label class="text-left md:text-right text-[16px] text-[#9c9c9c] font-semibold">

                        <span class="text-red-500">*</span>
                        Quantity
                        <?php if ($isEditMode): ?>
                            <span class="block text-xs font-normal text-slate-400 mt-1 md:text-right">Use "Add Stock" on the table row instead, to keep a clean history.</span>
                        <?php endif; ?>

                    </label>

                    <input type="number"
                           min="0"
                           name="quantity"
                           value="<?php echo e($productQty); ?>"
                           placeholder="Enter quantity"
                           required
                           class="w-full h-[44px] rounded-[12px] border border-[#d4d6db] bg-[#e8e9eb] px-4 text-[16px] text-[#4a4a4a] outline-none">

                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-[220px_1fr] gap-3 md:gap-x-10 items-center">

    <label class="text-left md:text-right text-[16px] text-[#9c9c9c] font-semibold">
        <span class="text-red-500">*</span>
        Product Type
    </label>

    <select name="product_type"
        class="w-full h-[44px] rounded-[12px] border border-[#d4d6db] bg-[#e8e9eb] px-4">

        <option value="activation"
    <?php echo ($productType=='activation')?'selected':''; ?>>
    Activation Product
</option>

<option value="repurchase"
    <?php echo ($productType=='repurchase')?'selected':''; ?>>
    Repurchase Product
</option>

<option value="both"
    <?php echo ($productType=='both')?'selected':''; ?>>
    Both
</option>

    </select>

</div>

                <!-- STATUS -->

                <div class="grid grid-cols-1 md:grid-cols-[220px_1fr] gap-3 md:gap-x-10 items-center">

                    <label class="text-left md:text-right text-[16px] text-[#9c9c9c] font-semibold">

                        <span class="text-red-500">*</span>
                        Status

                    </label>

                    <select name="status"
                            class="w-full h-[44px] rounded-[12px] border border-[#d4d6db] bg-[#e8e9eb] px-4 text-[16px] text-[#4a4a4a] outline-none">

                        <option value="Active"
                            <?php echo $productStatus === 'Active' ? 'selected' : ''; ?>>
                            Active
                        </option>

                        <option value="Inactive"
                            <?php echo $productStatus === 'Inactive' ? 'selected' : ''; ?>>
                            Inactive
                        </option>

                    </select>

                </div>

                <!-- BUTTON -->

                <div class="pt-6 flex flex-col sm:flex-row justify-center gap-3">

                    <button type="submit"
                            class="w-full sm:w-[168px] h-[44px] rounded-[12px] bg-[#be8b36] hover:bg-[#a97a2f] text-white text-[16px] font-semibold transition">

                        <?php echo $isEditMode ? 'Update' : 'Add'; ?>

                    </button>

                    <?php if ($isEditMode): ?>

                        <a href="<?php echo e($selfPage); ?>"
                           class="w-full sm:w-[168px] h-[44px] rounded-[12px] bg-gray-500 hover:bg-gray-600 text-white text-[16px] font-semibold transition inline-flex items-center justify-center">

                            Cancel

                        </a>

                    <?php endif; ?>

                </div>

            </form>

        </div>

        <!-- TABLE -->

        <div class="bg-white rounded-2xl shadow-sm overflow-hidden">

            <div class="overflow-x-auto">

                <table class="w-full border-collapse min-w-[950px]">

                    <thead class="bg-slate-50">

                        <tr>

                            <th class="px-4 py-4 text-left text-sm font-bold text-slate-700">#</th>

                            <th class="px-4 py-4 text-left text-sm font-bold text-slate-700">
                                Product Name
                            </th>

                            <th class="px-4 py-4 text-left text-sm font-bold text-slate-700">
                                Price
                            </th>

                            <th class="px-4 py-4 text-left text-sm font-bold text-slate-700">
                                Quantity
                            </th>

                            <th class="px-4 py-4 text-left text-sm font-bold text-slate-700">
                                GST
                            </th>

                            <th class="px-4 py-4 text-left text-sm font-bold text-slate-700">
                                BV
                            </th>
                            <th class="px-4 py-4 text-left text-sm font-bold text-slate-700">
    Product Type
</th>

                            <th class="px-4 py-4 text-left text-sm font-bold text-slate-700">
                                Status
                            </th>

                            <th class="px-4 py-4 text-left text-sm font-bold text-slate-700">
                                Created At
                            </th>

                            <th class="px-4 py-4 text-left text-sm font-bold text-slate-700">
                                Action
                            </th>

                        </tr>

                    </thead>

                    <tbody>

                    <?php if (!empty($products)): ?>

                        <?php $i = 1; ?>

                        <?php foreach ($products as $row): ?>

                            <tr class="border-t border-gray-200 hover:bg-slate-50">

                                <td class="px-4 py-4 text-sm">
                                    <?php echo $i++; ?>
                                </td>

                                <td class="px-4 py-4 text-sm font-semibold text-slate-800">

                                    <?php echo e($row['product_name']); ?>

                                </td>

                                <td class="px-4 py-4 text-sm font-bold">

                                    ₹<?php echo number_format((float)$row['price'], 2); ?>

                                </td>

                                <td class="px-4 py-4 text-sm font-semibold text-blue-700">

                                    <?php echo (int)$row['quantity']; ?>

                                </td>

                                <td class="px-4 py-4 text-sm font-semibold">
                                    <?php echo e($row['gst']); ?>%
                                </td>

                                <td class="px-4 py-4 text-sm font-semibold">
                                    <?php echo e($row['bv']); ?>
                                </td>
                                
                                <td class="px-4 py-4 text-sm font-semibold">
    <?php echo ucfirst($row['product_type']); ?>
</td>

                                <td class="px-4 py-4 text-sm">

                                    <?php if ($row['status'] === 'Active'): ?>

                                        <span class="rounded-lg bg-green-100 px-3 py-1 text-sm font-semibold text-green-700">
                                            Active
                                        </span>

                                    <?php else: ?>

                                        <span class="rounded-lg bg-red-100 px-3 py-1 text-sm font-semibold text-red-700">
                                            Inactive
                                        </span>

                                    <?php endif; ?>

                                </td>

                                <td class="px-4 py-4 text-sm whitespace-nowrap">

                                    <?php echo e($row['created_at']); ?>

                                </td>

                                <td class="px-4 py-4 text-sm whitespace-nowrap">

                                    <div class="flex gap-2">

                                        <button type="button"
                                            onclick="openStockModal('<?php echo (int)$row['id']; ?>', '<?php echo e($row['product_name']); ?>', '<?php echo (int)$row['quantity']; ?>')"
                                            class="inline-flex items-center gap-1 rounded-lg bg-amber-500 px-3 py-2 text-xs font-semibold text-white hover:bg-amber-600">

                                            <i data-lucide="plus-circle"
                                               class="w-4 h-4"></i>

                                            Add Stock

                                        </button>

                                        <a href="<?php echo e($selfPage); ?>?edit=<?php echo (int)$row['id']; ?>"
                                           class="inline-flex items-center gap-1 rounded-lg bg-blue-600 px-3 py-2 text-xs font-semibold text-white hover:bg-blue-700">

                                            <i data-lucide="edit"
                                               class="w-4 h-4"></i>

                                            Edit

                                        </a>

                                        <a href="<?php echo e($selfPage); ?>?delete=<?php echo (int)$row['id']; ?>"
                                           onclick="return confirm('Are you sure you want to delete this product?');"
                                           class="inline-flex items-center gap-1 rounded-lg bg-red-600 px-3 py-2 text-xs font-semibold text-white hover:bg-red-700">

                                            <i data-lucide="trash-2"
                                               class="w-4 h-4"></i>

                                            Delete

                                        </a>

                                    </div>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                    <?php else: ?>

                        <tr>

                            <td colspan="10"
                                class="px-4 py-10 text-center text-gray-500">

                                No products found.

                            </td>

                        </tr>

                    <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </div>

    </main>

</div>

<!-- ADD STOCK MODAL -->

<div id="stockModalBackdrop"
     class="hidden fixed inset-0 bg-black/50 z-40 flex items-center justify-center px-4">

    <div class="bg-white rounded-2xl shadow-lg w-full max-w-md p-6 relative">

        <button type="button"
                onclick="closeStockModal()"
                class="absolute top-4 right-4 text-slate-400 hover:text-slate-700">
            <i data-lucide="x" class="w-5 h-5"></i>
        </button>

        <div class="flex items-center gap-3 mb-5">
            <div class="w-10 h-10 rounded-xl bg-amber-50 flex items-center justify-center">
                <i data-lucide="package-plus" class="w-5 h-5 text-amber-600"></i>
            </div>
            <div>
                <h3 class="text-lg font-bold text-slate-800">Add Stock</h3>
                <p id="stockModalProductName" class="text-sm text-slate-500"></p>
            </div>
        </div>

        <form method="POST" action="<?php echo e($selfPage); ?>" class="space-y-4">

            <input type="hidden" name="add_stock_submit" value="1">
            <input type="hidden" name="stock_product_id" id="stockModalProductId" value="">

            <div class="rounded-xl bg-slate-50 px-4 py-3 flex items-center justify-between text-sm">
                <span class="text-slate-500 font-medium">Current Stock</span>
                <span id="stockModalCurrentQty" class="font-bold text-slate-800">0</span>
            </div>

            <div>
                <label class="block text-sm font-semibold text-slate-600 mb-1">
                    <span class="text-red-500">*</span> Quantity to Add
                </label>
                <input type="number"
                       min="1"
                       name="add_qty"
                       required
                       placeholder="e.g. 50"
                       class="w-full h-[44px] rounded-[12px] border border-[#d4d6db] bg-[#e8e9eb] px-4 text-[16px] text-[#4a4a4a] outline-none">
            </div>

            <div>
                <label class="block text-sm font-semibold text-slate-600 mb-1">
                    Remarks (optional)
                </label>
                <input type="text"
                       name="remarks"
                       maxlength="255"
                       placeholder="e.g. New purchase from supplier"
                       class="w-full h-[44px] rounded-[12px] border border-[#d4d6db] bg-[#e8e9eb] px-4 text-[16px] text-[#4a4a4a] outline-none">
            </div>

            <div class="flex gap-3 pt-2">
                <button type="submit"
                        class="flex-1 h-[44px] rounded-[12px] bg-[#be8b36] hover:bg-[#a97a2f] text-white text-[16px] font-semibold transition">
                    Add Stock
                </button>
                <button type="button"
                        onclick="closeStockModal()"
                        class="flex-1 h-[44px] rounded-[12px] bg-gray-200 hover:bg-gray-300 text-slate-700 text-[16px] font-semibold transition">
                    Cancel
                </button>
            </div>

        </form>

    </div>

</div>

<script>
lucide.createIcons();

function openStockModal(productId, productName, currentQty) {
    document.getElementById('stockModalProductId').value = productId;
    document.getElementById('stockModalProductName').textContent = productName;
    document.getElementById('stockModalCurrentQty').textContent = currentQty;
    document.getElementById('stockModalBackdrop').classList.remove('hidden');
}

function closeStockModal() {
    document.getElementById('stockModalBackdrop').classList.add('hidden');
}

document.getElementById('stockModalBackdrop').addEventListener('click', function (e) {
    if (e.target === this) {
        closeStockModal();
    }
});
</script>

</body>
</html>