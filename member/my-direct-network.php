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

$networkRows = [];

$sql = "SELECT 
            id,
            join_date,
            win_code,
            activation_amount,
            designation,
            state_name,
            city_name,
            sponsored_side,
            previous_closing,
            qualified_team
        FROM direct_network
        WHERE customer_id = ?
        ORDER BY id DESC";

$stmt = $conn->prepare($sql);

if ($stmt) {
    $stmt->bind_param("s", $customerId);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $networkRows[] = $row;
    }

    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>My Direct Network</title>
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
          MY DIRECT NETWORK
        </h2>

        <div class="w-full h-[2px] bg-[#d1a14b]"></div>
      </div>

      <div class="mt-8 rounded-[14px] border border-[#dddddd] bg-[#f7f7f7] p-3">
        <div class="overflow-x-auto">
          <table class="w-full border-separate border-spacing-0">
            <thead>
              <tr class="bg-[#C89741] text-white text-[12px] font-semibold">
                <th class="px-5 py-4 text-left rounded-l-[10px] whitespace-nowrap">Sr No.</th>
                <th class="px-4 py-4 text-left whitespace-nowrap">Join Date</th>
                <th class="px-4 py-4 text-left whitespace-nowrap">WIN Code</th>
                <th class="px-4 py-4 text-left whitespace-nowrap">Activation Amount</th>
                <th class="px-4 py-4 text-left whitespace-nowrap">Designation</th>
                <th class="px-4 py-4 text-left whitespace-nowrap">State</th>
                <th class="px-4 py-4 text-left whitespace-nowrap">City</th>
                <th class="px-4 py-4 text-left whitespace-nowrap">Sponsored Side</th>
                <th class="px-4 py-4 text-left leading-[16px] min-w-[210px]">
                  Previous Closing Qualified Team Sales Volume Earning
                </th>
                <th class="px-4 py-4 text-left rounded-r-[10px] leading-[16px] min-w-[170px]">
                  Qualified Team Sales Volume Earning
                </th>
              </tr>
            </thead>

            <tbody>
              <?php if (count($networkRows) > 0): ?>
                <?php foreach ($networkRows as $index => $row): ?>
                  <tr class="text-[13px] font-semibold text-[#3d3d3d] bg-white">
                    <td class="px-4 py-4 border-b border-[#ececec]">
                      <?php echo $index + 1; ?>
                    </td>

                    <td class="px-4 py-4 border-b border-[#ececec]">
                      <?php echo !empty($row['join_date']) ? htmlspecialchars(date('d-m-Y', strtotime($row['join_date']))) : '-'; ?>
                    </td>

                    <td class="px-4 py-4 border-b border-[#ececec]">
                      <?php echo htmlspecialchars($row['win_code'] ?? '-'); ?>
                    </td>

                    <td class="px-4 py-4 border-b border-[#ececec]">
                      ₹<?php echo number_format((float)($row['activation_amount'] ?? 0), 2); ?>
                    </td>

                    <td class="px-4 py-4 border-b border-[#ececec]">
                      <?php echo htmlspecialchars($row['designation'] ?? '-'); ?>
                    </td>

                    <td class="px-4 py-4 border-b border-[#ececec]">
                      <?php echo htmlspecialchars($row['state_name'] ?? '-'); ?>
                    </td>

                    <td class="px-4 py-4 border-b border-[#ececec]">
                      <?php echo htmlspecialchars($row['city_name'] ?? '-'); ?>
                    </td>

                    <td class="px-4 py-4 border-b border-[#ececec]">
                      <?php echo htmlspecialchars($row['sponsored_side'] ?? '-'); ?>
                    </td>

                    <td class="px-4 py-4 border-b border-[#ececec]">
                      ₹<?php echo number_format((float)($row['previous_closing'] ?? 0), 2); ?>
                    </td>

                    <td class="px-4 py-4 border-b border-[#ececec]">
                      ₹<?php echo number_format((float)($row['qualified_team'] ?? 0), 2); ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
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