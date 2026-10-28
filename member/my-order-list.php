<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['customer_id']) && !isset($_SESSION['member_id'])) {
    header("Location: login.php");
    exit();
}

require_once __DIR__ . '/config/db.php';

if (!isset($conn) || !($conn instanceof mysqli)) {
    die("Database connection failed.");
}

$conn->set_charset("utf8mb4");

$memberName   = $_SESSION['full_name'] ?? "Member";
$customerId   = $_SESSION['customer_id'] ?? $_SESSION['member_id'];
$managerName  = $memberName;
$managerRole  = "Distributor";
$profileImage = "https://i.pravatar.cc/80?img=12";
$navTitle     = "Repurchase";
$navIcon      = "shopping-cart";

function columnExists($conn, $table, $column) {
    $stmt = $conn->prepare("
        SELECT COUNT(*) AS c
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = ?
        AND COLUMN_NAME = ?
    ");
    $stmt->bind_param("ss", $table, $column);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return !empty($row['c']);
}

function ensureColumn($conn, $table, $column, $definition) {
    if (!columnExists($conn, $table, $column)) {
        $conn->query("ALTER TABLE `$table` ADD COLUMN `$column` $definition");
    }
}

$conn->query("
    CREATE TABLE IF NOT EXISTS my_order_list (
        id INT AUTO_INCREMENT PRIMARY KEY,
        customer_id VARCHAR(50) NOT NULL,
        order_no VARCHAR(50) NOT NULL UNIQUE,
        order_date DATETIME DEFAULT CURRENT_TIMESTAMP,
        amount DECIMAL(12,2) DEFAULT 0,
        payment_mode VARCHAR(100) DEFAULT 'Online',
        transaction_number VARCHAR(150) NULL,
        rbv DECIMAL(12,2) DEFAULT 0,
        payment_slip VARCHAR(255) NULL,
        status VARCHAR(50) DEFAULT 'Pending',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )
");

ensureColumn($conn, 'my_order_list', 'customer_id', "VARCHAR(50) NOT NULL");
ensureColumn($conn, 'my_order_list', 'order_no', "VARCHAR(50) NOT NULL");
ensureColumn($conn, 'my_order_list', 'order_date', "DATETIME DEFAULT CURRENT_TIMESTAMP");
ensureColumn($conn, 'my_order_list', 'amount', "DECIMAL(12,2) DEFAULT 0");
ensureColumn($conn, 'my_order_list', 'payment_mode', "VARCHAR(100) DEFAULT 'Online'");
ensureColumn($conn, 'my_order_list', 'transaction_number', "VARCHAR(150) NULL");
ensureColumn($conn, 'my_order_list', 'rbv', "DECIMAL(12,2) DEFAULT 0");
ensureColumn($conn, 'my_order_list', 'payment_slip', "VARCHAR(255) NULL");
ensureColumn($conn, 'my_order_list', 'status', "VARCHAR(50) DEFAULT 'Pending'");

$orderRows = [];

$stmt = $conn->prepare("
    SELECT 
        order_no,
        order_date,
        amount,
        payment_mode,
        transaction_number,
        rbv,
        payment_slip,
        status
    FROM my_order_list
    WHERE customer_id = ?
    ORDER BY id DESC
");

if ($stmt) {
    $stmt->bind_param("s", $customerId);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $orderRows[] = $row;
    }

    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Order List</title>
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
                MY ORDER LIST
            </h2>

            <div class="w-full h-[2px] bg-[#d1a14b]"></div>
        </div>

        <div class="mt-8 rounded-[14px] border border-[#dddddd] bg-[#f7f7f7] p-4 min-h-[170px]">
            <div class="overflow-x-auto">
                <table class="w-full border-separate border-spacing-0">
                    <thead>
                        <tr class="bg-[#C89741] text-white text-[13px] font-semibold">
                            <th class="px-6 py-5 text-left rounded-l-[10px] min-w-[110px]">Order No</th>
                            <th class="px-6 py-5 text-left min-w-[120px]">Order Date</th>
                            <th class="px-6 py-5 text-left min-w-[100px]">Amount</th>
                            <th class="px-6 py-5 text-left min-w-[140px]">Payment Mode</th>
                            <th class="px-6 py-5 text-left min-w-[170px]">Transaction Number</th>
                            <th class="px-6 py-5 text-left min-w-[80px]">RBV</th>
                            <th class="px-6 py-5 text-left min-w-[90px]">Slip</th>
                            <th class="px-6 py-5 text-left rounded-r-[10px] min-w-[100px]">Status</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php if (!empty($orderRows)): ?>
                            <?php foreach ($orderRows as $row): ?>
                                <tr class="text-[13px] text-[#2f2f2f] bg-white">
                                    <td class="px-6 py-5 border-b border-[#d8d8d8] align-middle">
                                        <?php echo htmlspecialchars($row['order_no'] ?? '-'); ?>
                                    </td>

                                    <td class="px-6 py-5 border-b border-[#d8d8d8] align-middle">
                                        <?php echo !empty($row['order_date']) ? date('d-m-Y', strtotime($row['order_date'])) : '-'; ?>
                                    </td>

                                    <td class="px-6 py-5 border-b border-[#d8d8d8] align-middle">
                                        ₹<?php echo number_format((float)($row['amount'] ?? 0), 2); ?>
                                    </td>

                                    <td class="px-6 py-5 border-b border-[#d8d8d8] align-middle">
                                        <?php echo htmlspecialchars($row['payment_mode'] ?? '-'); ?>
                                    </td>

                                    <td class="px-6 py-5 border-b border-[#d8d8d8] align-middle">
                                        <?php echo htmlspecialchars($row['transaction_number'] ?: '-'); ?>
                                    </td>

                                    <td class="px-6 py-5 border-b border-[#d8d8d8] align-middle">
                                        <?php echo number_format((float)($row['rbv'] ?? 0), 2); ?>
                                    </td>

                                    <td class="px-6 py-5 border-b border-[#d8d8d8] align-middle">
                                        <?php if (!empty($row['payment_slip'])): ?>
                                            <a href="<?php echo htmlspecialchars($row['payment_slip']); ?>" target="_blank" class="text-[#C89741] underline">
                                                View
                                            </a>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>

                                    <td class="px-6 py-5 border-b border-[#d8d8d8] align-middle">
                                        <?php
                                            $status = $row['status'] ?? 'Pending';
                                            $statusLower = strtolower($status);

                                            $statusClass = 'bg-yellow-100 text-yellow-700';

                                            if ($statusLower === 'paid' || $statusLower === 'approved' || $statusLower === 'completed') {
                                                $statusClass = 'bg-green-100 text-green-700';
                                            }

                                            if ($statusLower === 'rejected' || $statusLower === 'cancelled') {
                                                $statusClass = 'bg-red-100 text-red-700';
                                            }
                                        ?>

                                        <span class="inline-flex rounded-full px-3 py-1 text-[12px] font-semibold <?php echo $statusClass; ?>">
                                            <?php echo htmlspecialchars($status); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="px-6 py-10 text-center text-[13px] text-gray-500 bg-white">
                                    No orders found.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>

                </table>
            </div>
        </div>

    </div>
</div>

<script>
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }
</script>

</body>
</html>