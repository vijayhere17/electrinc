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
$navTitle     = "My Profile";
$navIcon      = "user-round";

include __DIR__ . '/config/db.php';

$teamVolumeRows = [];

$totalForwardTeam1 = 0;
$totalForwardTeam2 = 0;
$totalCurrentTeam1 = 0;
$totalCurrentTeam2 = 0;
$totalTotalTeam1 = 0;
$totalTotalTeam2 = 0;
$totalQualifiedTeamSalesVolume = 0;
$totalSalesVolume = 0;

$sql = "SELECT *
        FROM team_volume_status
        WHERE customer_id = ?
        ORDER BY id DESC";

$stmt = $conn->prepare($sql);

if ($stmt) {
    $stmt->bind_param("s", $customerId);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $teamVolumeRows[] = $row;

        $totalForwardTeam1 += (int) $row['forward_team_1'];
        $totalForwardTeam2 += (int) $row['forward_team_2'];
        $totalCurrentTeam1 += (int) $row['current_team_1'];
        $totalCurrentTeam2 += (int) $row['current_team_2'];
        $totalTotalTeam1 += (int) $row['total_team_1'];
        $totalTotalTeam2 += (int) $row['total_team_2'];
        $totalQualifiedTeamSalesVolume += is_numeric($row['total_qualified_team_sales_volume']) ? (int)$row['total_qualified_team_sales_volume'] : 0;
        $totalSalesVolume += is_numeric($row['qualified_team_sales_volume']) ? (int)$row['qualified_team_sales_volume'] : 0;
    }

    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Team Volume Status</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body class="bg-[#f3f3f3] font-sans">

    <?php include __DIR__ . '/includes/sidebar.php'; ?>

    <div class="ml-64 min-h-screen">
        <?php include __DIR__ . '/includes/navbar.php'; ?>

        <div class="px-5 py-3">
            <div class="mt-5 flex items-center gap-4">
                <div class="w-[36px] h-[36px] rounded-[8px] bg-[#f5eee3] flex items-center justify-center shrink-0">
                    <i data-lucide="badge-check" class="w-[18px] h-[18px] text-[#cd9737]"></i>
                </div>

                <h2 class="whitespace-nowrap text-[20px] font-normal text-[#40352a]" style="font-family: Georgia, 'Times New Roman', serif;">
                    MY TEAM VOLUME STATUS
                </h2>

                <div class="w-full h-[2px] bg-[#d1a14b]"></div>
            </div>

            <div class="mt-8 rounded-[14px] border border-[#dddddd] bg-[#f7f7f7] p-4 min-h-[492px]">
                <div class="overflow-x-auto">
                    <table class="w-full border-separate border-spacing-0 text-[13px] text-[#333]">
                        <thead>
                            <tr class="bg-[#C89741] text-white font-semibold">
                                <th rowspan="2" class="px-4 py-4 text-left align-middle border-r border-[#e7c88d] rounded-tl-[10px] min-w-[70px]">
                                    Sr No
                                </th>
                                <th rowspan="2" class="px-6 py-4 text-left align-middle border-r border-[#e7c88d] min-w-[95px]">
                                    Date
                                </th>
                                <th colspan="2" class="px-4 py-2 text-center border-r border-l border-[#e7c88d] min-w-[150px]">
                                    Forward
                                </th>
                                <th colspan="2" class="px-4 py-2 text-center border-r border-[#e7c88d] min-w-[150px]">
                                    Current
                                </th>
                                <th colspan="2" class="px-4 py-2 text-center border-r border-[#e7c88d] min-w-[150px]">
                                    Total
                                </th>
                                <th rowspan="2" class="px-4 py-4 text-left align-middle border-r border-[#e7c88d] min-w-[150px] leading-[16px]">
                                    Total Qualified Team Sales Volume
                                </th>
                                <th rowspan="2" class="px-4 py-4 text-left align-middle rounded-tr-[10px] min-w-[145px] leading-[16px]">
                                    Qualified Team Sales Volume
                                </th>
                            </tr>
                            <tr class="bg-[#c89a42] text-white font-semibold">
                                <th class="px-4 py-3 text-left border-r border-t border-[#e7c88d] min-w-[75px]">Team 1</th>
                                <th class="px-4 py-3 text-left border-r border-t border-[#e7c88d] min-w-[75px]">Team 2</th>
                                <th class="px-4 py-3 text-left border-r border-t border-[#e7c88d] min-w-[75px]">Team 1</th>
                                <th class="px-4 py-3 text-left border-r border-t border-[#e7c88d] min-w-[75px]">Team 2</th>
                                <th class="px-4 py-3 text-left border-r border-t border-[#e7c88d] min-w-[75px]">Team 1</th>
                                <th class="px-4 py-3 text-left border-r border-t border-[#e7c88d] min-w-[75px]">Team 2</th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php if (count($teamVolumeRows) > 0): ?>
                                <?php foreach ($teamVolumeRows as $index => $row): ?>
                                    <tr class="text-[13px] font-semibold text-[#3d3d3d] bg-white">
                                        <td class="px-4 py-7 border-b border-[#d8d8d8] align-middle">
                                            <?php echo $index + 1; ?>
                                        </td>
                                        <td class="px-6 py-7 border-b border-[#d8d8d8] align-middle">
                                            <?php echo htmlspecialchars($row['volume_date']); ?>
                                        </td>
                                        <td class="px-4 py-7 border-b border-[#d8d8d8] align-middle">
                                            <?php echo htmlspecialchars($row['forward_team_1']); ?>
                                        </td>
                                        <td class="px-4 py-7 border-b border-[#d8d8d8] align-middle">
                                            <?php echo htmlspecialchars($row['forward_team_2']); ?>
                                        </td>
                                        <td class="px-4 py-7 border-b border-[#d8d8d8] align-middle">
                                            <?php echo htmlspecialchars($row['current_team_1']); ?>
                                        </td>
                                        <td class="px-4 py-7 border-b border-[#d8d8d8] align-middle">
                                            <?php echo htmlspecialchars($row['current_team_2']); ?>
                                        </td>
                                        <td class="px-4 py-7 border-b border-[#d8d8d8] align-middle">
                                            <?php echo htmlspecialchars($row['total_team_1']); ?>
                                        </td>
                                        <td class="px-4 py-7 border-b border-[#d8d8d8] align-middle">
                                            <?php echo htmlspecialchars($row['total_team_2']); ?>
                                        </td>
                                        <td class="px-5 py-7 border-b border-[#d8d8d8] align-middle">
                                            <?php echo htmlspecialchars($row['total_qualified_team_sales_volume']); ?>
                                        </td>
                                        <td class="px-5 py-7 border-b border-[#d8d8d8] align-middle">
                                            <?php echo htmlspecialchars($row['qualified_team_sales_volume']); ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>

                                <tr class="bg-[#fdf8ef] font-semibold text-[#2f3440]">
                                    <td colspan="2" class="px-4 py-5 border-b border-[#d8d8d8] text-left">
                                        Total
                                    </td>
                                    <td class="px-4 py-5 border-b border-[#d8d8d8]">
                                        <?php echo $totalForwardTeam1; ?>
                                    </td>
                                    <td class="px-4 py-5 border-b border-[#d8d8d8]">
                                        <?php echo $totalForwardTeam2; ?>
                                    </td>
                                    <td class="px-4 py-5 border-b border-[#d8d8d8]">
                                        <?php echo $totalCurrentTeam1; ?>
                                    </td>
                                    <td class="px-4 py-5 border-b border-[#d8d8d8]">
                                        <?php echo $totalCurrentTeam2; ?>
                                    </td>
                                    <td class="px-4 py-5 border-b border-[#d8d8d8]">
                                        <?php echo $totalTotalTeam1; ?>
                                    </td>
                                    <td class="px-4 py-5 border-b border-[#d8d8d8]">
                                        <?php echo $totalTotalTeam2; ?>
                                    </td>
                                    <td class="px-5 py-5 border-b border-[#d8d8d8]">
                                        <?php echo $totalQualifiedTeamSalesVolume; ?>
                                    </td>
                                    <td class="px-5 py-5 border-b border-[#d8d8d8]">
                                        <?php echo $totalSalesVolume; ?>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <tr>
                                    <td colspan="10" class="h-[420px] align-top text-center">
                                        <div class="pt-5 text-[14px] font-semibold text-[#b0b0b0]">
                                            No Record Found...
                                        </div>
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
        lucide.createIcons();
    </script>
</body>
</html>