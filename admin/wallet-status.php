<?php
$customerId   = "MLM-00484";
$managerName  = "Swapnil Solanki";
$managerRole  = "MLM Manager";
$profileImage = "https://i.pravatar.cc/80?img=12";

$associateId = $_GET['associate_id'] ?? '';
$walletRow = null;

include __DIR__ . '/config/db.php';

if ($associateId !== '') {
    $stmt = $conn->prepare('SELECT associate_id, balance, last_updated FROM wallet_balance WHERE associate_id = ? LIMIT 1');
    if ($stmt) {
        $stmt->bind_param('s', $associateId);
        $stmt->execute();
        $result = $stmt->get_result();
        $walletRow = $result ? $result->fetch_assoc() : null;
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Wallet Status</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body class="m-0 bg-[#f3f4f6] font-sans">

<div class="ml-64 min-h-screen w-[calc(100%-16rem)]">

    <?php include __DIR__ . '/includes/sidebar.php'; ?>

    <div class="flex-1 min-w-0 flex flex-col">
        <?php include __DIR__ . '/includes/navbar.php'; ?>

        <main class="px-8 py-5">

            <!-- Heading -->
            <div class="flex items-center gap-4 mb-14">
                <div class="w-9 h-9 rounded-[8px] border border-[#ead9b5] bg-[#f8f1e7] flex items-center justify-center shrink-0">
                    <i data-lucide="badge-dollar-sign" class="w-5 h-5 text-[#c79435]"></i>
                </div>

                <h2
                    class="text-[24px] font-normal text-[#313131] leading-none tracking-wide"
                    style="font-family: Georgia, 'Times New Roman', serif;"
                >
                    WALLET STATUS
                </h2>

                <div class="h-[1px] bg-[#c9963b] flex-1"></div>
            </div>

            <!-- Main box -->
            <div class="w-full rounded-[18px] border border-[#d8d8d8] bg-transparent min-h-[160px] flex items-center justify-center px-6">
                <form method="GET" action="" class="flex items-center gap-4">
                    <label for="associate_id" class="text-[18px] font-semibold text-[#202020] whitespace-nowrap">
                        Enter Associate Id to view status:
                    </label>

                    <input
                        type="text"
                        id="associate_id"
                        name="associate_id"
                        value="<?php echo htmlspecialchars($associateId); ?>"
                        class="w-[165px] h-[34px] rounded-[12px] border border-[#d1d3d8] bg-[#e8e9eb] px-4 text-[14px] text-[#4a4a4a] outline-none"
                    >

                    <button
                        type="submit"
                        class="w-[86px] h-[34px] rounded-[12px] bg-[#bf8d37] hover:bg-[#a97a2f] text-white text-[14px] font-medium transition"
                    >
                        View
                    </button>
                </form>
            </div>

            <?php if ($associateId !== ''): ?>
                <div class="mt-6 rounded-[14px] border border-[#d8d8d8] bg-white p-5">
                    <?php if ($walletRow): ?>
                        <div class="text-[15px] text-[#2b2b2b]">Associate ID: <span class="font-semibold"><?php echo htmlspecialchars($walletRow['associate_id']); ?></span></div>
                        <div class="mt-2 text-[22px] font-bold text-[#17497d]">Wallet Balance: Rs. <?php echo number_format((float)$walletRow['balance'], 2); ?></div>
                        <div class="mt-2 text-[13px] text-[#6b7280]">Last Updated: <?php echo htmlspecialchars($walletRow['last_updated']); ?></div>
                    <?php else: ?>
                        <div class="text-[14px] text-[#b45309]">No wallet record found for this associate ID.</div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

        </main>
    </div>
</div>

<script>
    lucide.createIcons();
</script>
</body>
</html>