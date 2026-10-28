<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config/db.php';
if (!isset($conn) || !($conn instanceof mysqli)) {
    die('Database connection failed.');
}
$conn->set_charset('utf8mb4');
require_once __DIR__ . '/../includes/income_common.php';

if (!isset($_SESSION['customer_id']) || trim((string)$_SESSION['customer_id']) === '') {
    header('Location: login.php');
    exit;
}
$customer_id = trim((string)$_SESSION['customer_id']);
$memberName  = $_SESSION['full_name'] ?? 'Member';

function e($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function normalizeUploadError(int $code): string
{
    return match ($code) {
        UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Uploaded file is too large.',
        UPLOAD_ERR_PARTIAL   => 'File was only partially uploaded.',
        UPLOAD_ERR_NO_FILE   => 'Please upload your payment receipt.',
        UPLOAD_ERR_NO_TMP_DIR, UPLOAD_ERR_CANT_WRITE => 'Server upload folder problem.',
        default => 'File upload failed.',
    };
}

/* fixed booking amount (admin-configurable) */
$bookingAmount = round(income_setting_num($conn, 'business_qualifying_amount', 10000.0), 2);

/* member status */
$user = null;
$st = $conn->prepare("SELECT customer_id, full_name, registration_status FROM signup_users WHERE customer_id = ? LIMIT 1");
$st->bind_param('s', $customer_id);
$st->execute();
$user = $st->get_result()->fetch_assoc() ?: null;
$st->close();
$isActivated = strtolower((string)($user['registration_status'] ?? '')) === 'active';

$hasPending = false;
$st = $conn->prepare("SELECT id FROM activation_requests WHERE customer_id = ? AND status = 'Pending' LIMIT 1");
$st->bind_param('s', $customer_id);
$st->execute();
$hasPending = (bool)$st->get_result()->fetch_row();
$st->close();

/* booking history — this member's own activation_requests only, never a URL param */
$bookingHistory = [];
$st = $conn->prepare("SELECT id, amount, payment_mode, transaction_id, status, created_at FROM activation_requests WHERE customer_id = ? ORDER BY id DESC");
$st->bind_param('s', $customer_id);
$st->execute();
$rs = $st->get_result();
while ($row = $rs->fetch_assoc()) $bookingHistory[] = $row;
$st->close();

$bank = [
    'Account Name' => 'NETVEXA PVT LTD.',
    'Account No'    => '000000XXXX',
    'IFSC'          => 'SBIN000XXXX',
    'Bank'         => 'State Bank of India',
    'Branch'       => 'Kopar Khairane, Navi Mumbai',
    'UPI'          => 'netvexa@phonepe',
];

$success = '';
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['place_order']) && !$isActivated && !$hasPending) {
    $payment_mode   = trim((string)($_POST['payment_mode'] ?? ''));
    $transaction_no = trim((string)($_POST['transaction_no'] ?? ''));
    $remark         = trim((string)($_POST['remark'] ?? ''));
    $allowedModes   = ['UPI', 'IMPS', 'NEFT', 'RTGS', 'Cash Deposit'];

    if (!in_array($payment_mode, $allowedModes, true)) {
        $error = 'Please select a valid payment mode.';
    } elseif ($transaction_no === '') {
        $error = 'Please enter the transaction / UTR number.';
    } elseif (!isset($_FILES['receipt']) || (int)$_FILES['receipt']['error'] !== UPLOAD_ERR_OK) {
        $error = normalizeUploadError((int)($_FILES['receipt']['error'] ?? UPLOAD_ERR_NO_FILE));
    } else {
        $tmp  = (string)$_FILES['receipt']['tmp_name'];
        $name = (string)$_FILES['receipt']['name'];
        $size = (int)$_FILES['receipt']['size'];
        $ext  = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $mime = @mime_content_type($tmp) ?: '';
        $okMimes = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'pdf' => 'application/pdf'];

        if (!is_uploaded_file($tmp)) {
            $error = 'Invalid uploaded file.';
        } elseif (!isset($okMimes[$ext]) || $mime !== $okMimes[$ext]) {
            $error = 'Only JPG, PNG or PDF files are allowed.';
        } elseif ($size <= 0 || $size > 5 * 1024 * 1024) {
            $error = 'File must be between 1 byte and 5 MB.';
        } else {
            $dir = __DIR__ . '/uploads/receipts/';
            if (!is_dir($dir)) {
                @mkdir($dir, 0755, true);
            }
            $newName  = 'receipt_' . date('Ymd_His') . '_' . random_int(1000, 9999) . '.' . $ext;
            $relPath  = 'uploads/receipts/' . $newName;

            if (@move_uploaded_file($tmp, __DIR__ . '/' . $relPath)) {
                $productsJson = json_encode([['item' => 'Joining Booking', 'amount' => $bookingAmount, 'qty' => 1]], JSON_UNESCAPED_UNICODE);
                $ins = $conn->prepare("INSERT INTO activation_requests
                    (customer_id, amount, payment_mode, transaction_id, payment_slip, remark, products_json, status, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, 'Pending', NOW())");
                $ins->bind_param('sdsssss', $customer_id, $bookingAmount, $payment_mode, $transaction_no, $relPath, $remark, $productsJson);
                if ($ins->execute()) {
                    $success = 'Your booking request has been submitted. Please wait for admin approval.';
                    $hasPending = true;
                } else {
                    $error = 'Could not save the request. Please try again.';
                    @unlink(__DIR__ . '/' . $relPath);
                }
                $ins->close();
            } else {
                $error = 'Failed to upload the receipt.';
            }
        }
    }
}

function money($n) { return '₹' . number_format((float)$n, 2); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book / Activate</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        body { background:#f3f3f3; }
        @media (min-width: 1024px) { .main-wrap { margin-left: 256px; } }
        input, select, textarea { outline:none; }
    </style>
</head>
<body>
<?php if (file_exists(__DIR__ . '/includes/sidebar.php')) include __DIR__ . '/includes/sidebar.php'; ?>
<div class="main-wrap min-h-screen">
    <?php if (file_exists(__DIR__ . '/includes/navbar.php')) include __DIR__ . '/includes/navbar.php'; ?>
    <div class="px-3 sm:px-5 py-3 max-w-[820px]">

        <div class="mt-4 sm:mt-5 flex items-center gap-3">
            <div class="w-[34px] h-[34px] rounded-[8px] bg-[#f5eee3] flex items-center justify-center shrink-0">
                <i data-lucide="zap" class="w-[17px] h-[17px] text-[#cd9737]"></i>
            </div>
            <h2 class="whitespace-nowrap text-[20px] font-normal text-[#40352a]" style="font-family: Georgia, 'Times New Roman', serif;">BOOK / ACTIVATE</h2>
            <div class="w-full h-[2px] bg-[#d1a14b]"></div>
        </div>

        <p class="mt-3 text-[12px] text-[#8a7c68]">
            Member: <strong><?php echo e($memberName); ?></strong> &middot; ID <strong><?php echo e($customer_id); ?></strong>
        </p>

        <?php if ($success !== ''): ?>
            <div class="mt-4 rounded-xl border border-[#bfe3c9] bg-[#eefaf1] px-4 py-3 text-[13px] text-[#1e7a48]"><?php echo e($success); ?></div>
        <?php endif; ?>
        <?php if ($error !== ''): ?>
            <div class="mt-4 rounded-xl border border-[#f3c9c9] bg-[#fdecec] px-4 py-3 text-[13px] text-[#9b1c1c]"><?php echo e($error); ?></div>
        <?php endif; ?>

        <?php if ($isActivated): ?>

            <div class="mt-4 rounded-2xl border border-[#bfe3c9] bg-[#eefaf1] px-5 py-6 text-[14px] text-[#1e7a48] flex items-center gap-3">
                <i data-lucide="check-circle" class="w-6 h-6"></i>
                Your ID is already activated.
            </div>

            <?php if (!empty($bookingHistory)): ?>
                <div class="mt-4 bg-white rounded-2xl border border-[#e2d8c8] overflow-hidden">
                    <div class="px-5 py-3 border-b border-[#e2d8c8]"><h3 class="text-[14px] font-semibold text-[#40352a]">Booking History</h3></div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-[13px]">
                            <thead class="bg-[#faf5ec] text-[#9e8f7e] text-[11px] uppercase">
                                <tr>
                                    <th class="text-left px-5 py-2.5">Date</th>
                                    <th class="text-right px-5 py-2.5">Amount</th>
                                    <th class="text-left px-5 py-2.5">Payment Mode</th>
                                    <th class="text-left px-5 py-2.5">Transaction ID</th>
                                    <th class="text-left px-5 py-2.5">Status</th>
                                    <th class="px-5 py-2.5"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-[#f0e9dc]">
                                <?php foreach ($bookingHistory as $bh): ?>
                                    <tr>
                                        <td class="px-5 py-2.5 text-[#6a5f4f] whitespace-nowrap"><?php echo e(substr((string)$bh['created_at'], 0, 16)); ?></td>
                                        <td class="px-5 py-2.5 text-right font-semibold text-[#40352a]"><?php echo money($bh['amount']); ?></td>
                                        <td class="px-5 py-2.5 text-[#6a5f4f]"><?php echo e($bh['payment_mode']); ?></td>
                                        <td class="px-5 py-2.5 text-[#6a5f4f]"><?php echo e($bh['transaction_id']); ?></td>
                                        <td class="px-5 py-2.5">
                                            <span class="text-[11px] rounded-full px-2 py-0.5 <?php echo $bh['status'] === 'Approved' ? 'bg-green-50 text-green-700' : ($bh['status'] === 'Pending' ? 'bg-amber-50 text-amber-700' : 'bg-red-50 text-red-700'); ?>"><?php echo e($bh['status']); ?></span>
                                        </td>
                                        <td class="px-5 py-2.5 text-right whitespace-nowrap">
                                            <?php if ($bh['status'] === 'Approved'): ?>
                                                <a href="activation-invoice.php?id=<?php echo (int)$bh['id']; ?>" target="_blank" class="inline-flex items-center gap-1 text-[12px] text-[#0E7A3B] hover:underline">
                                                    <i data-lucide="file-text" class="w-3.5 h-3.5"></i> Invoice
                                                </a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>

        <?php elseif ($hasPending): ?>

            <div class="mt-4 rounded-2xl border border-[#f0d9a8] bg-[#fdf6e6] px-5 py-6 text-[14px] text-[#b07d00] flex items-center gap-3">
                <i data-lucide="clock" class="w-6 h-6"></i>
                Your booking request is submitted and waiting for admin approval.
            </div>

        <?php else: ?>

            <div class="mt-4 grid grid-cols-1 lg:grid-cols-2 gap-4">

                <div class="bg-[#40352a] rounded-2xl px-5 py-5 text-white">
                    <p class="text-[10px] uppercase tracking-widest text-[#e7d3ad] font-semibold">Booking Amount</p>
                    <p class="mt-1 text-[34px] font-extrabold"><?php echo money($bookingAmount); ?></p>
                    <p class="mt-1 text-[11px] text-[#cdbfa6]">One-time joining booking. Pay this amount to the account below, then submit the details.</p>
                </div>

                <div class="bg-white rounded-2xl border border-[#e2d8c8] px-5 py-4">
                    <p class="text-[10px] uppercase tracking-widest text-[#9e8f7e] font-semibold">Pay To</p>
                    <table class="mt-2 w-full text-[12px]">
                        <?php foreach ($bank as $k => $v): ?>
                            <tr><td class="py-1 text-[#9e8f7e] pr-3 whitespace-nowrap"><?php echo e($k); ?></td><td class="py-1 font-medium text-[#40352a]"><?php echo e($v); ?></td></tr>
                        <?php endforeach; ?>
                    </table>
                </div>
            </div>

            <form method="POST" enctype="multipart/form-data" class="mt-4 bg-white rounded-2xl border border-[#e2d8c8] p-5">
                <h3 class="text-[14px] font-semibold text-[#40352a]">Payment details</h3>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mt-3">
                    <label class="block">
                        <span class="text-[11px] text-[#9e8f7e]">Amount paid</span>
                        <input type="text" value="<?php echo money($bookingAmount); ?>" readonly class="mt-1 w-full border border-[#e2d8c8] rounded-lg px-3 py-2 text-[13px] bg-[#faf5ec] text-[#40352a] font-semibold">
                    </label>
                    <label class="block">
                        <span class="text-[11px] text-[#9e8f7e]">Payment mode</span>
                        <select name="payment_mode" required class="mt-1 w-full border border-[#e2d8c8] rounded-lg px-3 py-2 text-[13px] bg-white">
                            <option value="">Select…</option>
                            <?php foreach (['UPI','IMPS','NEFT','RTGS','Cash Deposit'] as $mode): ?>
                                <option value="<?php echo $mode; ?>" <?php echo (($_POST['payment_mode'] ?? '') === $mode) ? 'selected' : ''; ?>><?php echo $mode; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label class="block sm:col-span-2">
                        <span class="text-[11px] text-[#9e8f7e]">Transaction / UTR number</span>
                        <input type="text" name="transaction_no" required value="<?php echo e($_POST['transaction_no'] ?? ''); ?>" placeholder="Enter transaction or UTR number" class="mt-1 w-full border border-[#e2d8c8] rounded-lg px-3 py-2 text-[13px] bg-white">
                    </label>
                    <label class="block sm:col-span-2">
                        <span class="text-[11px] text-[#9e8f7e]">Payment receipt (JPG / PNG / PDF, max 5MB)</span>
                        <input type="file" name="receipt" accept=".jpg,.jpeg,.png,.pdf" required class="mt-1 w-full border border-[#e2d8c8] rounded-lg px-3 py-2 text-[13px] bg-white">
                    </label>
                    <label class="block sm:col-span-2">
                        <span class="text-[11px] text-[#9e8f7e]">Remark (optional)</span>
                        <textarea name="remark" rows="2" class="mt-1 w-full border border-[#e2d8c8] rounded-lg px-3 py-2 text-[13px] bg-white"><?php echo e($_POST['remark'] ?? ''); ?></textarea>
                    </label>
                </div>

                <button type="submit" name="place_order" onclick="return confirm('Submit your booking for <?php echo money($bookingAmount); ?>?');"
                        class="mt-4 rounded-lg bg-[#c79435] px-8 py-2.5 text-white text-[13px] hover:bg-[#b3822c]">
                    Submit Booking
                </button>
            </form>

        <?php endif; ?>

    </div>
</div>
<script>lucide.createIcons();</script>
</body>
</html>
