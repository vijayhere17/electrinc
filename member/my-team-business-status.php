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

if (!isset($conn) || !($conn instanceof mysqli)) {
    die("Database connection failed. Please check config/db.php");
}

$conn->set_charset("utf8mb4");

$teamBusinessRows = [];

$sql = "SELECT 
            id,
            order_no,
            serial_no,
            member_code,
            member_name,
            state_name,
            city_name,
            self_rbv,
            team_rbv,
            team_a_rbv,
            team_b_rbv
        FROM team_business_status
        WHERE customer_id = ?
        ORDER BY id DESC";

$stmt = $conn->prepare($sql);

if ($stmt) {
    $stmt->bind_param("s", $customerId);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $teamBusinessRows[] = [
            'row_id'      => (int)$row['id'],
            'order_no'    => $row['order_no'] ?? '',
            'serial'      => $row['serial_no'] ?? '',
            'id'          => $row['member_code'] ?? '',
            'name'        => $row['member_name'] ?? '',
            'state'       => $row['state_name'] ?: '-',
            'city'        => $row['city_name'] ?: '-',
            'self_rbv'    => number_format((float)$row['self_rbv'], 2),
            'team_rbv'    => number_format((float)$row['team_rbv'], 2),
            'team_a_rbv'  => number_format((float)$row['team_a_rbv'], 2),
            'team_b_rbv'  => number_format((float)$row['team_b_rbv'], 2),
        ];
    }

    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Team Business Status</title>
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
                    MY TEAM BUSINESS STATUS
                </h2>

                <div class="w-full h-[2px] bg-[#d1a14b]"></div>
            </div>

            <div class="mt-8 rounded-[14px] border border-[#dddddd] bg-[#f7f7f7] p-4 min-h-[170px]">
                <div class="overflow-x-auto">
                    <table class="w-full border-separate border-spacing-0">
                        <thead>
                            <tr class="bg-[#C89741] text-white text-[13px] font-semibold">
                                <th class="px-6 py-5 text-left rounded-l-[10px] min-w-[110px]">Order No</th>
                                <th class="px-6 py-5 text-left min-w-[60px]">#</th>
                                <th class="px-6 py-5 text-left min-w-[80px]">ID</th>
                                <th class="px-6 py-5 text-left min-w-[120px]">Name</th>
                                <th class="px-6 py-5 text-left min-w-[110px]">State</th>
                                <th class="px-6 py-5 text-left min-w-[100px]">City</th>
                                <th class="px-6 py-5 text-left min-w-[100px]">Self RBV</th>
                                <th class="px-6 py-5 text-left min-w-[110px]">Team RBV</th>
                                <th class="px-6 py-5 text-left min-w-[120px]">Team A RBV</th>
                                <th class="px-6 py-5 text-left min-w-[120px]">Team B RBV</th>
                                <th class="px-6 py-5 text-left rounded-r-[10px] min-w-[90px]">View</th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php if (count($teamBusinessRows) > 0): ?>
                                <?php foreach ($teamBusinessRows as $row): ?>
                                    <tr class="text-[13px] text-[#2f2f2f] bg-white">
                                        <td class="px-6 py-5 border-b border-[#d8d8d8] align-middle">
                                            <?php echo htmlspecialchars($row['order_no']); ?>
                                        </td>
                                        <td class="px-6 py-5 border-b border-[#d8d8d8] align-middle">
                                            <?php echo htmlspecialchars($row['serial']); ?>
                                        </td>
                                        <td class="px-6 py-5 border-b border-[#d8d8d8] align-middle">
                                            <?php echo htmlspecialchars($row['id']); ?>
                                        </td>
                                        <td class="px-6 py-5 border-b border-[#d8d8d8] align-middle">
                                            <?php echo htmlspecialchars($row['name']); ?>
                                        </td>
                                        <td class="px-6 py-5 border-b border-[#d8d8d8] align-middle">
                                            <?php echo htmlspecialchars($row['state']); ?>
                                        </td>
                                        <td class="px-6 py-5 border-b border-[#d8d8d8] align-middle">
                                            <?php echo htmlspecialchars($row['city']); ?>
                                        </td>
                                        <td class="px-6 py-5 border-b border-[#d8d8d8] align-middle">
                                            <?php echo htmlspecialchars($row['self_rbv']); ?>
                                        </td>
                                        <td class="px-6 py-5 border-b border-[#d8d8d8] align-middle">
                                            <?php echo htmlspecialchars($row['team_rbv']); ?>
                                        </td>
                                        <td class="px-6 py-5 border-b border-[#d8d8d8] align-middle">
                                            <?php echo htmlspecialchars($row['team_a_rbv']); ?>
                                        </td>
                                        <td class="px-6 py-5 border-b border-[#d8d8d8] align-middle">
                                            <?php echo htmlspecialchars($row['team_b_rbv']); ?>
                                        </td>
                                        <td class="px-6 py-5 border-b border-[#d8d8d8] align-middle">
                                            <button
                                                type="button"
                                                class="text-[#1f5fbf] hover:underline"
                                                onclick='openTeamBusinessModal(<?php echo json_encode($row, JSON_HEX_APOS | JSON_HEX_QUOT); ?>)'
                                            >
                                                View
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="11" class="h-[80px] align-middle text-center">
                                        <div class="text-[14px] font-semibold text-[#b0b0b0]">
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

    <!-- Modal -->
    <div id="teamBusinessModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40 px-4">
        <div class="w-full max-w-[700px] rounded-[14px] bg-white shadow-xl border border-[#dddddd] overflow-hidden">
            <div class="flex items-center justify-between px-5 py-4 bg-[#C89741] text-white">
                <h3 class="text-[16px] font-semibold">Team Business Details</h3>
                <button type="button" onclick="closeTeamBusinessModal()" class="text-white">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>

            <div class="p-5 grid grid-cols-1 md:grid-cols-2 gap-4 text-[14px] text-[#2f2f2f]">
                <div class="rounded-[9px] border border-[#d9dde2] bg-[#eef0f3] px-4 py-3">
                    <div class="text-[#8d8d8d] text-[12px] uppercase font-semibold">Order No</div>
                    <div id="modal_order_no" class="mt-1 font-semibold"></div>
                </div>

                <div class="rounded-[9px] border border-[#d9dde2] bg-[#eef0f3] px-4 py-3">
                    <div class="text-[#8d8d8d] text-[12px] uppercase font-semibold">Serial No</div>
                    <div id="modal_serial" class="mt-1 font-semibold"></div>
                </div>

                <div class="rounded-[9px] border border-[#d9dde2] bg-[#eef0f3] px-4 py-3">
                    <div class="text-[#8d8d8d] text-[12px] uppercase font-semibold">Member ID</div>
                    <div id="modal_id" class="mt-1 font-semibold"></div>
                </div>

                <div class="rounded-[9px] border border-[#d9dde2] bg-[#eef0f3] px-4 py-3">
                    <div class="text-[#8d8d8d] text-[12px] uppercase font-semibold">Member Name</div>
                    <div id="modal_name" class="mt-1 font-semibold"></div>
                </div>

                <div class="rounded-[9px] border border-[#d9dde2] bg-[#eef0f3] px-4 py-3">
                    <div class="text-[#8d8d8d] text-[12px] uppercase font-semibold">State</div>
                    <div id="modal_state" class="mt-1 font-semibold"></div>
                </div>

                <div class="rounded-[9px] border border-[#d9dde2] bg-[#eef0f3] px-4 py-3">
                    <div class="text-[#8d8d8d] text-[12px] uppercase font-semibold">City</div>
                    <div id="modal_city" class="mt-1 font-semibold"></div>
                </div>

                <div class="rounded-[9px] border border-[#d9dde2] bg-[#eef0f3] px-4 py-3">
                    <div class="text-[#8d8d8d] text-[12px] uppercase font-semibold">Self RBV</div>
                    <div id="modal_self_rbv" class="mt-1 font-semibold"></div>
                </div>

                <div class="rounded-[9px] border border-[#d9dde2] bg-[#eef0f3] px-4 py-3">
                    <div class="text-[#8d8d8d] text-[12px] uppercase font-semibold">Team RBV</div>
                    <div id="modal_team_rbv" class="mt-1 font-semibold"></div>
                </div>

                <div class="rounded-[9px] border border-[#d9dde2] bg-[#eef0f3] px-4 py-3">
                    <div class="text-[#8d8d8d] text-[12px] uppercase font-semibold">Team A RBV</div>
                    <div id="modal_team_a_rbv" class="mt-1 font-semibold"></div>
                </div>

                <div class="rounded-[9px] border border-[#d9dde2] bg-[#eef0f3] px-4 py-3">
                    <div class="text-[#8d8d8d] text-[12px] uppercase font-semibold">Team B RBV</div>
                    <div id="modal_team_b_rbv" class="mt-1 font-semibold"></div>
                </div>
            </div>

            <div class="px-5 pb-5 flex justify-center">
                <button
                    type="button"
                    onclick="closeTeamBusinessModal()"
                    class="min-w-[120px] h-[40px] rounded-[8px] bg-[#9A7133] text-white text-[14px] font-semibold hover:bg-[#a77628] transition"
                >
                    Close
                </button>
            </div>
        </div>
    </div>

    <script>
        lucide.createIcons();

        function openTeamBusinessModal(row) {
            document.getElementById('modal_order_no').textContent = row.order_no || '-';
            document.getElementById('modal_serial').textContent = row.serial || '-';
            document.getElementById('modal_id').textContent = row.id || '-';
            document.getElementById('modal_name').textContent = row.name || '-';
            document.getElementById('modal_state').textContent = row.state || '-';
            document.getElementById('modal_city').textContent = row.city || '-';
            document.getElementById('modal_self_rbv').textContent = row.self_rbv || '0.00';
            document.getElementById('modal_team_rbv').textContent = row.team_rbv || '0.00';
            document.getElementById('modal_team_a_rbv').textContent = row.team_a_rbv || '0.00';
            document.getElementById('modal_team_b_rbv').textContent = row.team_b_rbv || '0.00';

            const modal = document.getElementById('teamBusinessModal');
            modal.classList.remove('hidden');
            modal.classList.add('flex');

            lucide.createIcons();
        }

        function closeTeamBusinessModal() {
            const modal = document.getElementById('teamBusinessModal');
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }

        document.getElementById('teamBusinessModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeTeamBusinessModal();
            }
        });
    </script>
</body>
</html>