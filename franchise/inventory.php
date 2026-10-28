<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/includes/inventory.php';

if (!isset($conn) || !($conn instanceof mysqli)) die('Database connection failed.');

$fid   = (int)$franchiseId;
$fcode = (string)$franchiseCode;
$by    = (string)($franchiseName ?: $franchiseCode ?: ('FR' . $fid));

$msg = ''; $err = '';
$viewId = (int)($_GET['id'] ?? 0);

/* ---------- optional product photo upload (jpg/jpeg/png/webp, 3MB max) ---------- */
function iv_upload_product_image(string $fcode, string &$err): string
{
    if (!isset($_FILES['image']) || $_FILES['image']['error'] === UPLOAD_ERR_NO_FILE) return '';
    $file = $_FILES['image'];
    if ($file['error'] !== UPLOAD_ERR_OK) { $err = 'Image upload failed.'; return ''; }

    $allowedExt = ['jpg', 'jpeg', 'png', 'webp'];
    $maxSize = 3 * 1024 * 1024;
    $ext = strtolower(pathinfo((string)$file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExt, true)) { $err = 'Only JPG, PNG and WEBP images are allowed.'; return ''; }
    if ((int)$file['size'] > $maxSize) { $err = 'Image must be under 3 MB.'; return ''; }

    $uploadDir = __DIR__ . '/uploads/products/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

    $safeCode = preg_replace('/[^a-zA-Z0-9_-]/', '_', $fcode);
    $newFileName = 'product_' . $safeCode . '_' . time() . '_' . random_int(1000, 9999) . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], $uploadDir . $newFileName)) { $err = 'Could not save the uploaded image.'; return ''; }

    return 'uploads/products/' . $newFileName;
}

/* ---------- POST actions ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = (string)($_POST['action'] ?? '');
    if ($act === 'create_product') {
        $uploadErr = '';
        $imagePath = iv_upload_product_image($fcode, $uploadErr);
        if ($uploadErr !== '') { $err = $uploadErr; }
        else {
            $r = fr_product_save($conn, $fid, $fcode, [
                'name' => $_POST['name'] ?? '', 'category' => $_POST['category'] ?? '', 'brand' => $_POST['brand'] ?? '',
                'model' => $_POST['model'] ?? '', 'price' => $_POST['price'] ?? 0, 'image_path' => $imagePath,
                'is_serialized' => isset($_POST['is_serialized']) ? 1 : 0, 'status' => $_POST['status'] ?? 'Active',
                'opening_qty' => $_POST['opening_qty'] ?? 0,
            ], $by);
            if (!empty($r['ok'])) { header("Location: inventory.php?id=" . (int)$r['id'] . "&ok=" . urlencode($r['message'])); exit; }
            $err = $r['message'];
        }
    } elseif ($act === 'update_product' && $viewId > 0) {
        $uploadErr = '';
        $imagePath = iv_upload_product_image($fcode, $uploadErr);
        if ($uploadErr !== '') { $err = $uploadErr; }
        else {
            $r = fr_product_save($conn, $fid, $fcode, [
                'name' => $_POST['name'] ?? '', 'category' => $_POST['category'] ?? '', 'brand' => $_POST['brand'] ?? '',
                'model' => $_POST['model'] ?? '', 'price' => $_POST['price'] ?? 0, 'status' => $_POST['status'] ?? 'Active',
                'image_path' => $imagePath,
            ], $by, $viewId);
            $msg = $r['ok'] ? $r['message'] : ''; $err = $r['ok'] ? '' : $r['message'];
        }
    } elseif ($act === 'add_field' && $viewId > 0) {
        $r = fr_field_add($conn, $fid, $viewId, (string)($_POST['field_label'] ?? ''), (string)($_POST['field_type'] ?? 'text'), !empty($_POST['is_identifier']));
        $msg = $r['ok'] ? $r['message'] : ''; $err = $r['ok'] ? '' : $r['message'];
    } elseif ($act === 'del_field' && $viewId > 0) {
        $r = fr_field_delete($conn, $fid, $viewId, (int)($_POST['field_id'] ?? 0));
        $msg = $r['ok'] ? $r['message'] : ''; $err = $r['ok'] ? '' : $r['message'];
    } elseif ($act === 'save_product_values' && $viewId > 0) {
        fr_values_set($conn, 'fr_product_values', $viewId, $viewId, (array)($_POST['cf'] ?? []));
        $msg = 'Custom field values saved.';
    } elseif ($act === 'nonserial_move' && $viewId > 0) {
        $r = fr_nonserial_move($conn, $fid, $viewId, (string)($_POST['move'] ?? ''), (int)($_POST['qty'] ?? 0), [
            'reason' => $_POST['reason'] ?? '', 'reference' => $_POST['reference'] ?? '', 'remarks' => $_POST['remarks'] ?? '',
            'unit_price' => $_POST['unit_price'] ?? 0,
            'customer_ref' => $_POST['customer_ref'] ?? 0, 'invoice_no' => $_POST['invoice_no'] ?? '',
            'sale_date' => $_POST['sale_date'] ?? '', 'payment_status' => $_POST['payment_status'] ?? 'Paid',
            'franchise_code' => $fcode, 'franchise_type' => $franchiseType,
        ], $by);
        $msg = $r['ok'] ? $r['message'] : ''; $err = $r['ok'] ? '' : $r['message'];
    }
}
if (isset($_GET['ok'])) $msg = (string)$_GET['ok'];

$product = $viewId > 0 ? fr_product_get($conn, $fid, $viewId) : null;

/* ---------- list ---------- */
$products = [];
$q = trim((string)($_GET['q'] ?? ''));
$sql = "SELECT * FROM fr_products WHERE franchise_id = ?";
$params = [$fid]; $types = 'i';
if ($q !== '') { $sql .= " AND (name LIKE ? OR category LIKE ? OR brand LIKE ? OR model LIKE ?)"; $like = "%$q%"; array_push($params, $like, $like, $like, $like); $types .= 'ssss'; }
$sql .= " ORDER BY id DESC LIMIT 300";
$st = $conn->prepare($sql); $st->bind_param($types, ...$params); $st->execute();
$rs = $st->get_result();
while ($x = $rs->fetch_assoc()) { $x['_c'] = fr_product_counts($conn, $x); $products[] = $x; }
$st->close();

$customers = function_exists('frc_active_customers') ? frc_active_customers($conn, $fid) : [];
$fields = $product ? fr_fields_list($conn, $viewId) : [];
$pcounts = $product ? fr_product_counts($conn, $product) : null;
$pvals = [];
if ($product && (int)$product['is_serialized'] === 0) {
    $r = $conn->query("SELECT field_key, field_value FROM fr_product_values WHERE product_id = " . $viewId);
    while ($r && ($x = $r->fetch_assoc())) $pvals[$x['field_key']] = $x['field_value'];
}
$units = [];
if ($product && (int)$product['is_serialized'] === 1) {
    $r = $conn->query("SELECT id, unit_no, status, customer_name, sale_date, received_date FROM fr_units WHERE product_id = " . $viewId . " ORDER BY id DESC LIMIT 200");
    while ($r && ($x = $r->fetch_assoc())) $units[] = $x;
}
function iv_money($n) { return '₹' . number_format((float)$n, 2); }
$suggested = ['Chassis Number', 'Motor Number', 'Battery Number', 'Controller Number', 'Charger Number', 'Serial Number', 'Colour', 'Battery Type', 'Motor Type', 'Warranty Number', 'Registration Number'];

/* Category dropdown: starts with the standard EV line-up, plus remembers every Category
   this franchise has ever typed — still a free-text combo box, not a locked enum. */
$seedCategories = [
    'E-Rickshaw (Passenger)', 'E-Rickshaw (Cargo)', 'E-Rickshaw (Utility)',
    'E-Scooter (Sports)', 'E-Scooter (Premium)', 'E-Scooter (Family)',
    'E-Bike (Sports)', 'E-Bike (Utility)',
];
$knownCategories = $seedCategories;
$r = $conn->prepare("SELECT DISTINCT category FROM fr_products WHERE franchise_id = ? AND category <> '' ORDER BY category");
$r->bind_param('i', $fid); $r->execute();
$rs = $r->get_result(); while ($x = $rs->fetch_assoc()) $knownCategories[] = $x['category']; $r->close();
$knownCategories = array_values(array_unique($knownCategories));
sort($knownCategories, SORT_NATURAL | SORT_FLAG_CASE);

$knownBrands = [];
$r = $conn->prepare("SELECT DISTINCT brand FROM fr_products WHERE franchise_id = ? AND brand <> '' ORDER BY brand");
$r->bind_param('i', $fid); $r->execute();
$rs = $r->get_result(); while ($x = $rs->fetch_assoc()) $knownBrands[] = $x['brand']; $r->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Product Master &amp; Inventory</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        .ivcombo-list{
            position:absolute; left:0; right:0; top:100%; margin-top:4px; z-index:30;
            max-height:208px; overflow-y:auto; background:#fff; border:1px solid #e5e7eb;
            border-radius:10px; box-shadow:0 10px 28px -8px rgba(0,0,0,.18);
        }
        .ivcombo-item{ padding:8px 12px; font-size:13px; color:#374151; cursor:pointer; }
        .ivcombo-item:hover, .ivcombo-item.active{ background:#FFF3E7; color:#B25400; }
        .ivcombo-empty{ padding:8px 12px; font-size:12px; color:#9ca3af; }
    </style>
</head>
<body class="bg-[#f3f3f3] font-sans overflow-x-hidden">
<?php include __DIR__ . '/includes/sidebar.php'; ?>
<div class="ml-64 min-h-screen bg-[#f3f3f3]">
    <?php include __DIR__ . '/includes/navbar.php'; ?>
    <main class="px-4 sm:px-5 py-6">
        <div class="max-w-7xl mx-auto space-y-6">

            <div class="bg-[#17457a] rounded-xl px-5 sm:px-6 py-4 text-white shadow flex items-center justify-between flex-wrap gap-3">
                <div>
                    <h1 class="text-[18px] sm:text-[20px] font-semibold">Product Master &amp; Inventory</h1>
                    <p class="text-[12px] text-white/80 mt-1"><?php echo e($franchiseName); ?> · <?php echo e($franchiseCode); ?></p>
                </div>
                <div class="flex gap-2">
                    <a href="stock-entry.php" class="bg-white/15 hover:bg-white/25 rounded-lg px-3 py-2 text-[12px]">Stock Entry</a>
                    <a href="stock-report.php" class="bg-white/15 hover:bg-white/25 rounded-lg px-3 py-2 text-[12px]">Stock Report</a>
                </div>
            </div>

            <?php if ($msg): ?><div class="rounded-lg border border-green-200 bg-green-50 px-4 py-2.5 text-green-700 text-[13px]"><?php echo e($msg); ?></div><?php endif; ?>
            <?php if ($err): ?><div class="rounded-lg border border-red-200 bg-red-50 px-4 py-2.5 text-red-700 text-[13px]"><?php echo e($err); ?></div><?php endif; ?>


            <?php if ($product): /* ============ SINGLE PRODUCT ============ */ ?>

            <a href="inventory.php" class="text-[12px] text-[#17457a] hover:underline inline-flex items-center gap-1"><i data-lucide="arrow-left" class="w-3.5 h-3.5"></i> All products</a>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- product form -->
                <form method="post" enctype="multipart/form-data" class="bg-white border border-gray-200 rounded-xl p-5 shadow-sm">
                    <input type="hidden" name="action" value="update_product">
                    <h2 class="text-[14px] font-semibold text-[#173a67] mb-3">Product Details</h2>
                    <div class="space-y-3 text-[13px]">
                        <label class="block"><span class="text-gray-500 text-[11px]">Product Name *</span>
                            <input name="name" required value="<?php echo e($product['name']); ?>" class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2"></label>
                        <div class="grid grid-cols-2 gap-3">
                            <label class="block relative"><span class="text-gray-500 text-[11px]">Category</span>
                                <input name="category" id="edit_category" autocomplete="off" value="<?php echo e($product['category']); ?>"
                                       oninput="ivComboRender('edit_category','edit_category_list','category')"
                                       onfocus="ivComboRender('edit_category','edit_category_list','category')"
                                       class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2">
                                <div id="edit_category_list" class="ivcombo-list hidden"></div>
                            </label>
                            <label class="block relative"><span class="text-gray-500 text-[11px]">Brand</span>
                                <input name="brand" id="edit_brand" autocomplete="off" value="<?php echo e($product['brand']); ?>"
                                       oninput="ivComboRender('edit_brand','edit_brand_list','brand')"
                                       onfocus="ivComboRender('edit_brand','edit_brand_list','brand')"
                                       class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2">
                                <div id="edit_brand_list" class="ivcombo-list hidden"></div>
                            </label>
                            <label class="block"><span class="text-gray-500 text-[11px]">Model</span>
                                <input name="model" value="<?php echo e($product['model']); ?>" class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2"></label>
                            <label class="block"><span class="text-gray-500 text-[11px]">Price</span>
                                <input name="price" type="number" step="0.01" value="<?php echo e($product['price']); ?>" class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2"></label>
                        </div>
                        <label class="block"><span class="text-gray-500 text-[11px]">Status</span>
                            <select name="status" class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2">
                                <option <?php echo $product['status'] === 'Active' ? 'selected' : ''; ?>>Active</option>
                                <option <?php echo $product['status'] === 'Inactive' ? 'selected' : ''; ?>>Inactive</option>
                            </select></label>

                        <label class="block"><span class="text-gray-500 text-[11px]">Product Photo (optional)</span>
                            <div class="mt-1 flex items-center gap-3">
                                <img id="editImgPreview" src="<?php echo $product['image_path'] ? e($product['image_path']) : ''; ?>"
                                     class="h-16 w-16 rounded-lg object-cover border border-gray-200 bg-gray-50 <?php echo $product['image_path'] ? '' : 'hidden'; ?>" alt="">
                                <input name="image" type="file" accept="image/png,image/jpeg,image/webp"
                                       onchange="ivPreview(this,'editImgPreview')"
                                       class="flex-1 text-[12px] border border-gray-300 rounded-lg px-3 py-2 file:mr-3 file:py-1 file:px-3 file:rounded-md file:border-0 file:bg-[#EAF5EC] file:text-[#0A5E2D] file:text-[12px]">
                            </div>
                            <span class="text-[10px] text-gray-400">JPG, PNG or WEBP, up to 3 MB. Leave blank to keep the current photo.</span>
                        </label>

                        <p class="text-[11px] text-gray-400">Type: <?php echo (int)$product['is_serialized'] === 1 ? 'Unit-tracked (individual identifiers)' : 'Quantity only'; ?> · Added <?php echo e(substr((string)$product['created_at'], 0, 10)); ?></p>
                    </div>
                    <button class="mt-4 bg-[#17457a] text-white rounded-lg px-5 py-2 text-[13px]">Save</button>
                </form>

                <!-- stock counts -->
                <div class="bg-white border border-gray-200 rounded-xl p-5 shadow-sm">
                    <h2 class="text-[14px] font-semibold text-[#173a67] mb-3">Stock</h2>
                    <div class="space-y-2 text-[13px]">
                        <?php foreach (['Total' => 'total', 'Available' => 'available', 'Reserved' => 'reserved', 'Sold' => 'sold', 'Delivered' => 'delivered', 'Returned' => 'returned', 'Damaged' => 'damaged'] as $lbl => $k): ?>
                            <div class="flex justify-between"><span class="text-gray-500"><?php echo $lbl; ?></span><span class="font-semibold text-gray-800"><?php echo (int)$pcounts[$k]; ?></span></div>
                        <?php endforeach; ?>
                    </div>
                    <a href="stock-entry.php?product_id=<?php echo $viewId; ?>" class="mt-4 inline-block bg-[#17457a] text-white rounded-lg px-4 py-2 text-[12px]">+ Receive Stock</a>

                    <?php if ((int)$product['is_serialized'] === 0): ?>
                    <form method="post" class="mt-4 border-t border-gray-100 pt-3 space-y-2 text-[12px]">
                        <input type="hidden" name="action" value="nonserial_move">
                        <p class="text-gray-500 font-medium">Quantity movement</p>
                        <div class="flex gap-2">
                            <select name="move" class="border border-gray-300 rounded px-2 py-1.5 flex-1">
                                <option value="sell">Sell</option><option value="reserve">Reserve</option>
                                <option value="unreserve">Un-reserve</option><option value="return">Return</option>
                                <option value="damage">Damage</option><option value="adjust">Manual adjust (±)</option>
                            </select>
                            <input name="qty" type="number" value="1" class="border border-gray-300 rounded px-2 py-1.5 w-20">
                        </div>
                        <input name="unit_price" type="number" step="0.01" placeholder="Unit price for a sale (defaults to product price)" class="w-full border border-gray-300 rounded px-2 py-1.5">
                        <select name="customer_ref" class="w-full border border-gray-300 rounded px-2 py-1.5">
                            <option value="0">Customer for a sale — none / walk-in</option>
                            <?php foreach ($customers as $cu): ?>
                                <option value="<?php echo (int)$cu['id']; ?>"><?php echo e($cu['customer_name'] . ($cu['mobile'] ? ' — ' . $cu['mobile'] : '')); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="flex gap-2">
                            <input name="invoice_no" placeholder="Invoice no. — auto if blank" class="w-full border border-gray-300 rounded px-2 py-1.5">
                            <input name="sale_date" type="date" class="w-full border border-gray-300 rounded px-2 py-1.5">
                        </div>
                        <input name="reason" placeholder="Reason (required for manual adjust)" class="w-full border border-gray-300 rounded px-2 py-1.5">
                        <input name="reference" placeholder="Reference (optional)" class="w-full border border-gray-300 rounded px-2 py-1.5">
                        <button class="bg-gray-800 text-white rounded px-3 py-1.5">Apply</button>
                    </form>
                    <?php endif; ?>
                </div>

                <!-- custom fields -->
                <div class="bg-white border border-gray-200 rounded-xl p-5 shadow-sm">
                    <h2 class="text-[14px] font-semibold text-[#173a67] mb-3">Custom Fields</h2>
                    <div class="space-y-1.5">
                        <?php if (empty($fields)): ?><p class="text-[12px] text-gray-400">No custom fields yet.</p><?php endif; ?>
                        <?php foreach ($fields as $f): ?>
                            <div class="flex items-center justify-between text-[12px] bg-gray-50 rounded px-2 py-1.5">
                                <span><?php echo e($f['field_label']); ?>
                                    <span class="text-gray-400">(<?php echo e($f['field_type']); ?><?php echo (int)$f['is_identifier'] === 1 ? ', ID' : ''; ?>)</span></span>
                                <form method="post" onsubmit="return confirm('Remove this field?');">
                                    <input type="hidden" name="action" value="del_field"><input type="hidden" name="field_id" value="<?php echo (int)$f['id']; ?>">
                                    <button class="text-red-500 hover:text-red-700"><i data-lucide="x" class="w-3.5 h-3.5"></i></button>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <form method="post" class="mt-3 border-t border-gray-100 pt-3 space-y-2 text-[12px]">
                        <input type="hidden" name="action" value="add_field">
                        <input name="field_label" list="fieldsuggest" placeholder="Field name e.g. Chassis Number" required class="w-full border border-gray-300 rounded px-2 py-1.5">
                        <datalist id="fieldsuggest"><?php foreach ($suggested as $sug): ?><option value="<?php echo e($sug); ?>"><?php endforeach; ?></datalist>
                        <div class="flex items-center gap-2">
                            <select name="field_type" class="border border-gray-300 rounded px-2 py-1.5"><option value="text">text</option><option value="number">number</option><option value="date">date</option></select>
                            <label class="flex items-center gap-1"><input type="checkbox" name="is_identifier" value="1"> identifier</label>
                        </div>
                        <button class="bg-[#17457a] text-white rounded px-3 py-1.5">+ Add Custom Field</button>
                    </form>
                    <p class="mt-2 text-[10px] text-gray-400">Identifier fields are searchable and used for product tracking.</p>
                </div>
            </div>

            <?php if ((int)$product['is_serialized'] === 0 && !empty($fields)): ?>
            <form method="post" class="bg-white border border-gray-200 rounded-xl p-5 shadow-sm">
                <input type="hidden" name="action" value="save_product_values">
                <h2 class="text-[14px] font-semibold text-[#173a67] mb-3">Product Field Values</h2>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 text-[13px]">
                    <?php foreach ($fields as $f): ?>
                        <label class="block"><span class="text-gray-500 text-[11px]"><?php echo e($f['field_label']); ?></span>
                            <input name="cf[<?php echo e($f['field_key']); ?>]" type="<?php echo $f['field_type'] === 'date' ? 'date' : ($f['field_type'] === 'number' ? 'number' : 'text'); ?>"
                                   value="<?php echo e($pvals[$f['field_key']] ?? ''); ?>" class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2"></label>
                    <?php endforeach; ?>
                </div>
                <button class="mt-4 bg-[#17457a] text-white rounded-lg px-5 py-2 text-[13px]">Save Values</button>
            </form>
            <?php endif; ?>

            <?php if ((int)$product['is_serialized'] === 1): ?>
            <div class="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden">
                <div class="px-5 py-3 border-b border-gray-100 flex items-center justify-between">
                    <h2 class="text-[14px] font-semibold text-[#173a67]">Units (<?php echo count($units); ?>)</h2>
                    <a href="stock-entry.php?product_id=<?php echo $viewId; ?>" class="text-[12px] text-[#17457a] hover:underline">+ Receive units</a>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-[13px]">
                        <thead class="bg-gray-50 text-gray-500 text-[12px]">
                            <tr><th class="text-left px-5 py-2.5">Unit No</th><th class="text-left px-5 py-2.5">Status</th><th class="text-left px-5 py-2.5">Customer</th><th class="text-left px-5 py-2.5">Received</th><th class="text-left px-5 py-2.5">Sold</th><th class="px-5 py-2.5"></th></tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <?php if (empty($units)): ?><tr><td colspan="6" class="px-5 py-6 text-center text-gray-400">No units yet — receive stock to create units.</td></tr><?php endif; ?>
                            <?php foreach ($units as $u): ?>
                                <tr>
                                    <td class="px-5 py-2.5 font-medium"><?php echo e($u['unit_no']); ?></td>
                                    <td class="px-5 py-2.5"><span class="text-[11px] rounded-full px-2 py-0.5 bg-gray-100 text-gray-700"><?php echo e($u['status']); ?></span></td>
                                    <td class="px-5 py-2.5 text-gray-600"><?php echo e($u['customer_name'] ?: '—'); ?></td>
                                    <td class="px-5 py-2.5 text-gray-500"><?php echo e($u['received_date'] ?: '—'); ?></td>
                                    <td class="px-5 py-2.5 text-gray-500"><?php echo e($u['sale_date'] ?: '—'); ?></td>
                                    <td class="px-5 py-2.5 text-right"><a href="product-unit.php?id=<?php echo (int)$u['id']; ?>" class="text-[12px] text-[#17457a] hover:underline">Open</a></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>

            <?php else: /* ============ LIST + ADD ============ */ ?>

            <details class="bg-white border border-gray-200 rounded-xl shadow-sm">
                <summary class="cursor-pointer px-5 py-4 text-[14px] font-semibold text-[#173a67] flex items-center gap-2"><i data-lucide="plus" class="w-4 h-4"></i> Add Product</summary>
                <form method="post" enctype="multipart/form-data" class="px-5 pb-5 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 text-[13px]">
                    <input type="hidden" name="action" value="create_product">
                    <label class="block sm:col-span-2 lg:col-span-1"><span class="text-gray-500 text-[11px]">Product Name *</span>
                        <input name="name" required class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2"></label>
                    <label class="block relative"><span class="text-gray-500 text-[11px]">Category</span>
                        <input name="category" id="add_category" autocomplete="off"
                               oninput="ivComboRender('add_category','add_category_list','category')"
                               onfocus="ivComboRender('add_category','add_category_list','category')"
                               class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2">
                        <div id="add_category_list" class="ivcombo-list hidden"></div>
                    </label>
                    <label class="block relative"><span class="text-gray-500 text-[11px]">Brand</span>
                        <input name="brand" id="add_brand" autocomplete="off"
                               oninput="ivComboRender('add_brand','add_brand_list','brand')"
                               onfocus="ivComboRender('add_brand','add_brand_list','brand')"
                               class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2">
                        <div id="add_brand_list" class="ivcombo-list hidden"></div>
                    </label>
                    <label class="block"><span class="text-gray-500 text-[11px]">Model</span><input name="model" class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2"></label>
                    <label class="block"><span class="text-gray-500 text-[11px]">Price</span><input name="price" type="number" step="0.01" value="0" class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2"></label>
                    <label class="block"><span class="text-gray-500 text-[11px]">Status</span>
                        <select name="status" class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2"><option>Active</option><option>Inactive</option></select></label>
                    <label class="block"><span class="text-gray-500 text-[11px]">Opening Qty (quantity-only products)</span>
                        <input name="opening_qty" type="number" value="0" class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2"></label>
                    <label class="block sm:col-span-2 lg:col-span-2"><span class="text-gray-500 text-[11px]">Product Photo (optional)</span>
                        <div class="mt-1 flex items-center gap-3">
                            <img id="addImgPreview" src="" class="h-14 w-14 rounded-lg object-cover border border-gray-200 bg-gray-50 hidden" alt="">
                            <input name="image" type="file" accept="image/png,image/jpeg,image/webp"
                                   onchange="ivPreview(this,'addImgPreview')"
                                   class="flex-1 text-[12px] border border-gray-300 rounded-lg px-3 py-2 file:mr-3 file:py-1 file:px-3 file:rounded-md file:border-0 file:bg-[#EAF5EC] file:text-[#0A5E2D] file:text-[12px]">
                        </div>
                        <span class="text-[10px] text-gray-400">JPG, PNG or WEBP, up to 3 MB.</span>
                    </label>
                    <label class="flex items-center gap-2 mt-6"><input type="checkbox" name="is_serialized" value="1" checked> Track individual units / identifiers</label>
                    <div class="sm:col-span-2 lg:col-span-3"><button class="bg-[#17457a] text-white rounded-lg px-5 py-2 text-[13px]">Create Product</button></div>
                </form>
            </details>

            <form method="get" class="flex gap-2">
                <input name="q" value="<?php echo e($q); ?>" placeholder="Search products (name / category / brand / model)" class="flex-1 border border-gray-300 rounded-lg px-3 py-2 text-[13px]">
                <button class="bg-[#17457a] text-white rounded-lg px-4 py-2 text-[13px]">Search</button>
                <?php if ($q !== ''): ?><a href="inventory.php" class="border border-gray-300 rounded-lg px-4 py-2 text-[13px] bg-white">Clear</a><?php endif; ?>
            </form>

            <div class="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-[13px]">
                        <thead class="bg-gray-50 text-gray-500 text-[12px]">
                            <tr>
                                <th class="text-left px-5 py-2.5">Product</th>
                                <th class="text-left px-5 py-2.5">Category / Brand / Model</th>
                                <th class="text-right px-5 py-2.5">Price</th>
                                <th class="text-right px-5 py-2.5">Total</th>
                                <th class="text-right px-5 py-2.5">Available</th>
                                <th class="text-right px-5 py-2.5">Reserved</th>
                                <th class="text-right px-5 py-2.5">Sold</th>
                                <th class="text-left px-5 py-2.5">Type</th>
                                <th class="px-5 py-2.5"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <?php if (empty($products)): ?>
                                <tr><td colspan="9" class="px-5 py-8 text-center text-gray-400">No products yet. Use “Add Product” above.</td></tr>
                            <?php else: foreach ($products as $p): $c = $p['_c']; ?>
                                <tr>
                                    <td class="px-5 py-2.5 font-medium text-gray-800">
                                        <div class="flex items-center gap-2.5">
                                            <?php if (!empty($p['image_path'])): ?>
                                                <img src="<?php echo e($p['image_path']); ?>" class="h-8 w-8 rounded-md object-cover border border-gray-200 shrink-0" alt="">
                                            <?php else: ?>
                                                <span class="h-8 w-8 rounded-md bg-gray-100 flex items-center justify-center shrink-0"><i data-lucide="image" class="w-3.5 h-3.5 text-gray-300"></i></span>
                                            <?php endif; ?>
                                            <span><?php echo e($p['name']); ?>
                                                <?php if ($p['status'] !== 'Active'): ?><span class="text-[10px] text-gray-400">(<?php echo e($p['status']); ?>)</span><?php endif; ?></span>
                                        </div>
                                    </td>
                                    <td class="px-5 py-2.5 text-gray-500"><?php echo e(trim(($p['category'] ?: '—') . ' / ' . ($p['brand'] ?: '—') . ' / ' . ($p['model'] ?: '—'))); ?></td>
                                    <td class="px-5 py-2.5 text-right"><?php echo iv_money($p['price']); ?></td>
                                    <td class="px-5 py-2.5 text-right font-semibold"><?php echo (int)$c['total']; ?></td>
                                    <td class="px-5 py-2.5 text-right text-green-700"><?php echo (int)$c['available']; ?></td>
                                    <td class="px-5 py-2.5 text-right text-amber-700"><?php echo (int)$c['reserved']; ?></td>
                                    <td class="px-5 py-2.5 text-right"><?php echo (int)($c['sold'] + $c['delivered']); ?></td>
                                    <td class="px-5 py-2.5 text-gray-500 text-[12px]"><?php echo (int)$p['is_serialized'] === 1 ? 'Unit-tracked' : 'Qty only'; ?></td>
                                    <td class="px-5 py-2.5 text-right"><a href="inventory.php?id=<?php echo (int)$p['id']; ?>" class="text-[12px] text-[#17457a] hover:underline">Manage</a></td>
                                </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <?php endif; ?>
        </div>
    </main>
</div>
<script>
if (typeof lucide !== 'undefined') lucide.createIcons();
function ivPreview(input, imgId) {
    const img = document.getElementById(imgId);
    if (!img || !input.files || !input.files[0]) return;
    const reader = new FileReader();
    reader.onload = e => { img.src = e.target.result; img.classList.remove('hidden'); };
    reader.readAsDataURL(input.files[0]);
}

/* searchable Category / Brand combo box — type to filter, click to pick, free text still allowed */
const IV_COMBO_OPTIONS = {
    category: <?php echo json_encode($knownCategories, JSON_UNESCAPED_UNICODE); ?>,
    brand: <?php echo json_encode($knownBrands, JSON_UNESCAPED_UNICODE); ?>
};

function ivComboRender(inputId, listId, source) {
    const input = document.getElementById(inputId);
    const list = document.getElementById(listId);
    if (!input || !list) return;
    const q = input.value.trim().toLowerCase();
    const all = IV_COMBO_OPTIONS[source] || [];
    const items = q === '' ? all : all.filter(v => v.toLowerCase().includes(q));

    list.innerHTML = '';
    if (items.length === 0) {
        list.innerHTML = '<div class="ivcombo-empty">No match — you can still type your own value</div>';
    } else {
        items.forEach(v => {
            const row = document.createElement('div');
            row.className = 'ivcombo-item';
            row.textContent = v;
            row.onmousedown = function (e) {
                e.preventDefault();
                input.value = v;
                list.classList.add('hidden');
            };
            list.appendChild(row);
        });
    }
    list.classList.remove('hidden');
}

document.addEventListener('click', function (evt) {
    document.querySelectorAll('.ivcombo-list').forEach(function (list) {
        const wrap = list.closest('label, div');
        if (wrap && !wrap.contains(evt.target)) list.classList.add('hidden');
    });
});
</script>
</body>
</html>
