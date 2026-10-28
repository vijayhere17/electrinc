<?php
session_start();

$memberName   = $_SESSION['full_name'] ?? 'Swapnil Solanki';
$customerId   = $_SESSION['member_id'] ?? 'MLM-00484';
$managerName  = $_SESSION['full_name'] ?? 'Swapnil Solanki';
$managerRole  = 'MLM Manager';
$profileImage = 'https://i.pravatar.cc/80?img=12';

include __DIR__ . '/config/db.php';

$associateId = trim($_GET['associate_id'] ?? '');
$status = trim($_GET['status'] ?? '');
$fromDate = trim($_GET['from_date'] ?? '');
$toDate = trim($_GET['to_date'] ?? '');

$addAssociateId = trim($_POST['add_associate_id'] ?? '');
$addCourierName = trim($_POST['courier_name'] ?? '');
$addTrackingNo = trim($_POST['tracking_no'] ?? '');
$addStatus = trim($_POST['dispatch_status'] ?? 'Packed');
$addDispatchDate = trim($_POST['dispatch_date'] ?? date('Y-m-d'));

$successMessage = '';
$errorMessage = '';
$rows = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($addAssociateId === '' || $addCourierName === '' || $addTrackingNo === '' || $addDispatchDate === '') {
        $errorMessage = 'Please fill all required fields.';
    } elseif (!in_array($addStatus, ['Packed', 'Shipped', 'Delivered'], true)) {
        $errorMessage = 'Invalid dispatch status selected.';
    } else {
        $dispatchNo = 'DSP-' . date('Ymd') . '-' . strtoupper(substr(md5((string)microtime(true)), 0, 6));
        $stmt = $conn->prepare('INSERT INTO dispatches (dispatch_no, dispatch_date, associate_id, courier_name, tracking_no, status) VALUES (?, ?, ?, ?, ?, ?)');

        if ($stmt) {
            $stmt->bind_param('ssssss', $dispatchNo, $addDispatchDate, $addAssociateId, $addCourierName, $addTrackingNo, $addStatus);
            if ($stmt->execute()) {
                $successMessage = 'Dispatch saved successfully.';
                $addAssociateId = '';
                $addCourierName = '';
                $addTrackingNo = '';
                $addStatus = 'Packed';
                $addDispatchDate = date('Y-m-d');
            } else {
                $errorMessage = 'Unable to save dispatch record.';
            }
            $stmt->close();
        } else {
            $errorMessage = 'Database query preparation failed.';
        }
    }
}

$sql = 'SELECT id, dispatch_no, dispatch_date, associate_id, courier_name, tracking_no, status FROM dispatches WHERE 1=1';
$params = [];
$types = '';

if ($associateId !== '') {
    $sql .= ' AND associate_id LIKE ?';
    $types .= 's';
    $params[] = '%' . $associateId . '%';
}

if (in_array($status, ['Packed', 'Shipped', 'Delivered'], true)) {
    $sql .= ' AND status = ?';
    $types .= 's';
    $params[] = $status;
}

if ($fromDate !== '') {
    $sql .= ' AND dispatch_date >= ?';
    $types .= 's';
    $params[] = $fromDate;
}

if ($toDate !== '') {
    $sql .= ' AND dispatch_date <= ?';
    $types .= 's';
    $params[] = $toDate;
}

$sql .= ' ORDER BY id DESC';

$stmt = $conn->prepare($sql);
if ($stmt) {
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dispatch View</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body class="m-0 bg-[#f3f4f6] font-sans overflow-x-hidden">

    <?php include __DIR__ . '/includes/sidebar.php'; ?>

    <div class="ml-64 min-h-screen w-[calc(100%-16rem)]">
        <?php include __DIR__ . '/includes/navbar.php'; ?>

        <main class="px-5 py-4">

            <div class="flex items-center gap-4 mb-4">
                <div class="w-9 h-9 rounded-[8px] bg-[#f6f2ea] flex items-center justify-center shrink-0">
                    <i data-lucide="truck" class="w-4 h-4 text-[#c9983b]"></i>
                </div>

                <h2 class="text-[17px] md:text-[18px] leading-none text-[#2f2f2f] font-normal whitespace-nowrap"
                    style="font-family: Georgia, 'Times New Roman', serif;">
                    REPURCHASE DISPATCH VIEW
                </h2>

                <div class="h-[1px] bg-[#c9983b] flex-1"></div>
            </div>

            <?php if ($successMessage !== ''): ?>
                <div class="mb-4 rounded-[8px] border border-green-200 bg-green-50 px-4 py-3 text-[14px] text-green-700">
                    <?php echo htmlspecialchars($successMessage); ?>
                </div>
            <?php endif; ?>

            <?php if ($errorMessage !== ''): ?>
                <div class="mb-4 rounded-[8px] border border-red-200 bg-red-50 px-4 py-3 text-[14px] text-red-700">
                    <?php echo htmlspecialchars($errorMessage); ?>
                </div>
            <?php endif; ?>

            <div class="bg-white border border-[#dfdfdf] rounded-[12px] px-3 py-4">
                <form method="POST" class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-5 gap-3">
                    <input
                        type="text"
                        name="add_associate_id"
                        value="<?php echo htmlspecialchars($addAssociateId); ?>"
                        placeholder="Associate ID"
                        class="h-[36px] rounded-[6px] border border-[#d8dce2] bg-[#f2f4f7] px-3 text-[12px] outline-none"
                    >
                    <input
                        type="text"
                        name="courier_name"
                        value="<?php echo htmlspecialchars($addCourierName); ?>"
                        placeholder="Courier Name"
                        class="h-[36px] rounded-[6px] border border-[#d8dce2] bg-[#f2f4f7] px-3 text-[12px] outline-none"
                    >
                    <input
                        type="text"
                        name="tracking_no"
                        value="<?php echo htmlspecialchars($addTrackingNo); ?>"
                        placeholder="Tracking No"
                        class="h-[36px] rounded-[6px] border border-[#d8dce2] bg-[#f2f4f7] px-3 text-[12px] outline-none"
                    >
                    <input
                        type="date"
                        name="dispatch_date"
                        value="<?php echo htmlspecialchars($addDispatchDate); ?>"
                        class="h-[36px] rounded-[6px] border border-[#d8dce2] bg-[#f2f4f7] px-3 text-[12px] outline-none"
                    >
                    <div class="flex gap-2">
                        <select
                            name="dispatch_status"
                            class="h-[36px] w-full rounded-[6px] border border-[#d8dce2] bg-[#f2f4f7] px-2 text-[12px] outline-none"
                        >
                            <option value="Packed" <?php if ($addStatus === 'Packed') echo 'selected'; ?>>Packed</option>
                            <option value="Shipped" <?php if ($addStatus === 'Shipped') echo 'selected'; ?>>Shipped</option>
                            <option value="Delivered" <?php if ($addStatus === 'Delivered') echo 'selected'; ?>>Delivered</option>
                        </select>
                        <button
                            type="submit"
                            class="h-[36px] min-w-[82px] rounded-[6px] bg-[#143a66] text-white text-[12px] px-3"
                        >
                            Add
                        </button>
                    </div>
                </form>
            </div>

            <div class="bg-white border border-[#dfdfdf] rounded-[12px] mt-4 px-3 py-4">
                <form method="GET" class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-5 gap-3">
                    <input
                        type="text"
                        name="associate_id"
                        value="<?php echo htmlspecialchars($associateId); ?>"
                        placeholder="Search Associate ID"
                        class="h-[34px] rounded-[6px] border border-[#d8dce2] bg-[#f2f4f7] px-3 text-[12px] outline-none"
                    >

                    <select
                        name="status"
                        class="h-[34px] rounded-[6px] border border-[#d8dce2] bg-[#f2f4f7] px-2 text-[12px] outline-none"
                    >
                        <option value="">All Status</option>
                        <option value="Packed" <?php if ($status === 'Packed') echo 'selected'; ?>>Packed</option>
                        <option value="Shipped" <?php if ($status === 'Shipped') echo 'selected'; ?>>Shipped</option>
                        <option value="Delivered" <?php if ($status === 'Delivered') echo 'selected'; ?>>Delivered</option>
                    </select>

                    <input
                        type="date"
                        name="from_date"
                        value="<?php echo htmlspecialchars($fromDate); ?>"
                        class="h-[34px] rounded-[6px] border border-[#d8dce2] bg-[#f2f4f7] px-3 text-[12px] outline-none"
                    >

                    <input
                        type="date"
                        name="to_date"
                        value="<?php echo htmlspecialchars($toDate); ?>"
                        class="h-[34px] rounded-[6px] border border-[#d8dce2] bg-[#f2f4f7] px-3 text-[12px] outline-none"
                    >

                    <button
                        type="submit"
                        class="h-[34px] rounded-[6px] bg-[#143a66] text-white text-[12px] px-3"
                    >
                        Filter
                    </button>
                </form>

                <div class="mt-4 rounded-[6px] overflow-hidden border border-[#d6a246]">
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[980px] border-collapse">
                            <thead>
                                <tr class="bg-[#cd9737] text-white text-[15px] h-[34px]">
                                    <th class="px-3 font-medium whitespace-nowrap text-left">Dispatch No.</th>
                                    <th class="px-3 font-medium whitespace-nowrap text-left">Date</th>
                                    <th class="px-3 font-medium whitespace-nowrap text-left">Associate ID</th>
                                    <th class="px-3 font-medium whitespace-nowrap text-left">Courier Name</th>
                                    <th class="px-3 font-medium whitespace-nowrap text-left">Tracking No.</th>
                                    <th class="px-3 font-medium whitespace-nowrap text-left">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($rows)): ?>
                                    <?php foreach ($rows as $row): ?>
                                        <tr class="h-[36px] text-[15px] text-[#2d2d2d] border-t border-[#ececec] bg-white">
                                            <td class="px-3 whitespace-nowrap"><?php echo htmlspecialchars($row['dispatch_no']); ?></td>
                                            <td class="px-3 whitespace-nowrap"><?php echo htmlspecialchars($row['dispatch_date']); ?></td>
                                            <td class="px-3 whitespace-nowrap"><?php echo htmlspecialchars($row['associate_id']); ?></td>
                                            <td class="px-3 whitespace-nowrap"><?php echo htmlspecialchars($row['courier_name']); ?></td>
                                            <td class="px-3 whitespace-nowrap"><?php echo htmlspecialchars($row['tracking_no']); ?></td>
                                            <td class="px-3 whitespace-nowrap"><?php echo htmlspecialchars($row['status']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="h-[180px] bg-white text-center text-[12px] text-[#9ca3af]">No dispatch record found.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        lucide.createIcons();
    </script>
</body>
</html>
