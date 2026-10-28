<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['customer_id'])) {
    header("Location: login.php");
    exit();
}

$memberName   = $_SESSION['full_name'] ?? "Member";
$customerId   = $_SESSION['customer_id'];
$managerName  = "Swapnil Solanki";
$managerRole  = "MLM Manager";
$profileImage = "https://i.pravatar.cc/80?img=12";
$navTitle     = "Repurchase";
$navIcon      = "shopping-cart";

include __DIR__ . '/config/db.php';

$successMessage = '';
$errorMessage = '';

$amount = '';
$paymentMode = 'By Online Payment';
$transactionNumber = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $amount = trim($_POST['amount'] ?? '');
    $paymentMode = trim($_POST['payment_mode'] ?? '');
    $transactionNumber = trim($_POST['transaction_no'] ?? '');

    if ($amount === '' || !is_numeric($amount) || (float)$amount < 500) {
        $errorMessage = 'Please enter valid amount. Minimum amount is ₹500.';
    } elseif ($paymentMode === '') {
        $errorMessage = 'Please select payment mode.';
    } else {
        $rbv = (float)$amount;
        $orderNo = 'ORD' . date('YmdHis');
        $paymentSlipName = null;

        if (isset($_FILES['payment_slip']) && $_FILES['payment_slip']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = __DIR__ . '/uploads/payment-slips/';

            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            $originalName = basename($_FILES['payment_slip']['name']);
            $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
            $allowedExtensions = ['jpg', 'jpeg', 'png', 'pdf', 'webp'];

            if (!in_array($extension, $allowedExtensions, true)) {
                $errorMessage = 'Only JPG, JPEG, PNG, WEBP and PDF files are allowed.';
            } else {
                $paymentSlipName = 'slip_' . time() . '_' . mt_rand(1000, 9999) . '.' . $extension;
                $targetPath = $uploadDir . $paymentSlipName;

                if (!move_uploaded_file($_FILES['payment_slip']['tmp_name'], $targetPath)) {
                    $errorMessage = 'Failed to upload payment slip.';
                }
            }
        }

        if ($errorMessage === '') {
            $stmt = $conn->prepare("
                INSERT INTO repurchase_orders
                (customer_id, order_no, amount, payment_mode, transaction_number, rbv, payment_slip, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, 'Pending')
            ");

            if ($stmt) {
                $stmt->bind_param(
                    "ssdssds",
                    $customerId,
                    $orderNo,
                    $amount,
                    $paymentMode,
                    $transactionNumber,
                    $rbv,
                    $paymentSlipName
                );

                if ($stmt->execute()) {
                    $successMessage = 'Order request submitted successfully.';
                    $amount = '';
                    $paymentMode = 'By Online Payment';
                    $transactionNumber = '';
                } else {
                    $errorMessage = 'Failed to submit request.';
                }

                $stmt->close();
            } else {
                $errorMessage = 'Database query failed.';
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
    <title>Create New Order</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        body{
            font-family: Arial, Helvetica, sans-serif;
            background:#f3f3f3;
        }
    </style>
</head>
<body class="bg-[#f3f3f3]">

    <?php include __DIR__ . '/includes/sidebar.php'; ?>

    <div class="ml-64 min-h-screen bg-[#f3f3f3]">
        <?php include __DIR__ . '/includes/navbar.php'; ?>

        <div class="px-5 py-3">
            <div class="mt-5 flex items-center gap-4">
                <div class="w-[36px] h-[36px] rounded-[8px] bg-[#f5eee3] flex items-center justify-center shrink-0">
                    <i data-lucide="badge-check" class="w-[18px] h-[18px] text-[#cd9737]"></i>
                </div>

                <h2 class="whitespace-nowrap text-[20px] font-normal text-[#40352a]" style="font-family: Georgia, 'Times New Roman', serif;">
                    CREATE NEW ORDER
                </h2>

                <div class="w-full h-[2px] bg-[#d1a14b]"></div>
            </div>

            <?php if ($successMessage !== ''): ?>
                <div class="mt-5 rounded-[10px] border border-green-200 bg-green-50 px-4 py-3 text-[14px] text-green-700">
                    <?php echo htmlspecialchars($successMessage); ?>
                </div>
            <?php endif; ?>

            <?php if ($errorMessage !== ''): ?>
                <div class="mt-5 rounded-[10px] border border-red-200 bg-red-50 px-4 py-3 text-[14px] text-red-700">
                    <?php echo htmlspecialchars($errorMessage); ?>
                </div>
            <?php endif; ?>

            <div class="mt-10 rounded-[14px] bg-[#C89741] px-6 py-6">
                <div class="flex items-center gap-3 text-white">
                    <i data-lucide="landmark" class="w-[22px] h-[22px]"></i>
                    <h3 class="text-[16px] font-semibold">ICICI BANK - Transfer Details</h3>
                </div>

                <div class="mt-5 grid grid-cols-4 gap-4">
                    <?php for ($i = 0; $i < 4; $i++): ?>
                        <div class="rounded-[10px] bg-[#d5b170] px-4 py-4">
                            <p class="text-[11px] font-semibold uppercase text-[#f8f0dd]">Account Name</p>
                            <p class="mt-1 text-[16px] font-bold text-white">FRESHIYA LIMITED</p>
                        </div>
                    <?php endfor; ?>
                </div>
            </div>

            <div class="mt-5 rounded-[14px] border border-[#dddddd] bg-[#f7f7f7] px-7 py-7">
                <form action="" method="POST" enctype="multipart/form-data">
                    <div class="grid grid-cols-4 gap-x-10 gap-y-6">
                        <div>
                            <label class="mb-3 flex items-center gap-2 text-[13px] font-semibold text-[#8f8f8f]">
                                <i data-lucide="indian-rupee" class="w-[15px] h-[15px] text-[#8f8f8f]"></i>
                                <span>Enter Amount<span class="text-red-400">*</span></span>
                            </label>
                            <input
                                type="text"
                                name="amount"
                                value="<?php echo htmlspecialchars($amount); ?>"
                                placeholder="Min ₹500"
                                class="w-full h-[30px] rounded-[8px] border border-[#d9dde2] bg-[#eef0f3] px-3 text-[13px] text-[#666] outline-none placeholder:text-[#777]"
                            >
                        </div>

                        <div>
                            <label class="mb-3 flex items-center gap-2 text-[13px] font-semibold text-[#8f8f8f]">
                                <i data-lucide="credit-card" class="w-[15px] h-[15px] text-[#8f8f8f]"></i>
                                <span>Mode of Payment<span class="text-red-400">*</span></span>
                            </label>
                            <div class="relative">
                                <select
                                    name="payment_mode"
                                    class="appearance-none w-full h-[30px] rounded-[8px] border border-[#d9dde2] bg-[#eef0f3] px-3 pr-8 text-[13px] text-[#666] outline-none"
                                >
                                    <option <?php echo $paymentMode === 'By Online Payment' ? 'selected' : ''; ?>>By Online Payment</option>
                                    <option <?php echo $paymentMode === 'By UPI' ? 'selected' : ''; ?>>By UPI</option>
                                    <option <?php echo $paymentMode === 'By NEFT' ? 'selected' : ''; ?>>By NEFT</option>
                                    <option <?php echo $paymentMode === 'By IMPS' ? 'selected' : ''; ?>>By IMPS</option>
                                </select>
                                <i data-lucide="chevron-down" class="w-[14px] h-[14px] text-[#7f7f7f] absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none"></i>
                            </div>
                        </div>

                        <div>
                            <label class="mb-3 flex items-center gap-2 text-[13px] font-semibold text-[#8f8f8f]">
                                <i data-lucide="badge-dollar-sign" class="w-[15px] h-[15px] text-[#8f8f8f]"></i>
                                <span>Transaction No.</span>
                            </label>
                            <input
                                type="text"
                                name="transaction_no"
                                value="<?php echo htmlspecialchars($transactionNumber); ?>"
                                placeholder="Enter Transaction No"
                                class="w-full h-[30px] rounded-[8px] border border-[#d9dde2] bg-[#eef0f3] px-3 pr-8 text-[13px] text-[#666] outline-none"
                            >
                        </div>

                        <div>
                            <label class="mb-3 flex items-center gap-2 text-[13px] font-semibold text-[#8f8f8f]">
                                <i data-lucide="download" class="w-[15px] h-[15px] text-[#8f8f8f]"></i>
                                <span>Payment Slip</span>
                            </label>
                            <input
                                type="file"
                                name="payment_slip"
                                class="w-full h-[30px] rounded-[8px] border border-[#d9dde2] bg-[#eef0f3] px-2 text-[12px] text-[#666] file:mr-2 file:border-0 file:bg-transparent file:text-[12px] file:text-[#333]"
                            >
                        </div>
                    </div>

                    <div class="mt-6 h-px bg-[#d9d9d9]"></div>

                    <div class="mt-5 flex items-center justify-between">
                        <p class="text-[13px] text-[#9a9a9a]">
                            * Minimum Amount ₹500 allowed for Purchase Balance request
                        </p>

                        <button
                            type="submit"
                            class="h-[38px] min-w-[142px] rounded-[8px] bg-[#b98733] px-5 text-[14px] font-semibold text-white hover:bg-[#a77628] transition"
                        >
                            Submit Request
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        lucide.createIcons();
    </script>
</body>
</html>