<?php

ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();
require_once __DIR__ . '/config/db.php';

if (!isset($_SESSION['customer_id'])) {
    header("Location: login.php");
    exit;
}

$customer_id = $_SESSION['customer_id'];

function getDownline($conn, $parent_id, $level = 1, $maxLevel = 20, &$result = [], &$visited = [])
{
    // Stop after 20 levels
    if ($level > $maxLevel) {
        return $result;
    }

    // Prevent duplicate loop
    if (in_array($parent_id, $visited)) {
        return $result;
    }

    $visited[] = $parent_id;

   $stmt = $conn->prepare("
    SELECT
        u.customer_id,
        u.full_name,
        u.activated_at,
        u.registration_status,
        u.total_turnover,
        u.position,
        u.referral_id,
        r.full_name AS inviter_name
    FROM signup_users u
    LEFT JOIN signup_users r ON u.referral_id = r.customer_id
    WHERE u.placement_id = ?
");

    $stmt->bind_param("s", $parent_id);
    $stmt->execute();
    $res = $stmt->get_result();

    while ($row = $res->fetch_assoc()) {

        $row['level'] = $level;
        $result[] = $row;

        // Next level fetch
        getDownline(
            $conn,
            $row['customer_id'],
            $level + 1,
            $maxLevel,
            $result,
            $visited
        );
    }

    return $result;
}
$team = getDownline($conn, $customer_id, 1, 20);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Team Mates</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>

    <style>
        /* ── Mobile card view ── */
        .mobile-card {
            display: none;
        }

        @media (max-width: 767px) {
            .desktop-table { display: none; }
            .mobile-card   { display: block; }
            .page-wrap     { margin-left: 0 !important; }
        }

        @media (min-width: 768px) and (max-width: 1023px) {
            .page-wrap { margin-left: 0 !important; }
        }

        /* card animation */
        .team-card {
            animation: fadeUp 0.25s ease both;
        }

        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(8px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        /* scrollbar */
        .table-scroll::-webkit-scrollbar { height: 4px; }
        .table-scroll::-webkit-scrollbar-track { background: #f1f5f9; }
        .table-scroll::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 2px; }
    </style>
</head>

<body class="bg-gray-100 font-sans">

<?php include __DIR__ . '/includes/sidebar.php'; ?>

<div class="page-wrap ml-0 lg:ml-64 min-h-screen">

    <?php include __DIR__ . '/includes/navbar.php'; ?>

    <div class="px-4 md:px-6 py-5">

        <!-- Header -->
        <div class="mb-5 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
                <h1 class="text-xl font-bold text-gray-800">My Team Mates</h1>
                <p class="text-sm text-gray-500 mt-0.5">Your full downline team</p>
            </div>
            <div class="text-sm font-semibold text-gray-600 bg-white border rounded-lg px-4 py-2 shadow-sm">
                Total: <span id="memberCount" class="text-blue-600">
    <?php echo count($team); ?>
</span> members
            </div>
        </div>

        <!-- Filter -->
        <div class="mb-4 flex flex-wrap gap-3 items-center">
           <select id="levelFilter" class="border border-gray-200 bg-white px-3 py-2 rounded-lg text-sm text-gray-700 shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
    <option value="all">All Levels</option>

    <?php for ($i = 1; $i <= 20; $i++): ?>
        <option value="<?php echo $i; ?>">
            Level <?php echo $i; ?>
        </option>
    <?php endfor; ?>
</select>

            <div class="relative">
                <input type="text" id="searchInput" placeholder="Search name or ID…"
                    class="border border-gray-200 bg-white pl-9 pr-3 py-2 rounded-lg text-sm text-gray-700 shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 w-52">
                <svg class="absolute left-2.5 top-2.5 w-4 h-4 text-gray-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/>
                </svg>
            </div>
        </div>

        <!-- ══ DESKTOP TABLE ══ -->
        <div class="desktop-table bg-white rounded-xl shadow-sm border overflow-hidden">
            <div class="table-scroll overflow-x-auto">
                <table class="w-full text-sm min-w-[750px]">
                    <thead>
                        <tr class="bg-gray-50 border-b text-gray-500 text-xs uppercase tracking-wide">
                            <th class="px-4 py-3 text-left font-semibold">#</th>
                            <th class="px-4 py-3 text-left font-semibold">User ID</th>
                            <th class="px-4 py-3 text-left font-semibold">Name</th>
                            <th class="px-4 py-3 text-left font-semibold">Level</th>
                            <th class="px-4 py-3 text-left font-semibold">Activated On</th>
                            <th class="px-4 py-3 text-left font-semibold">Business</th>
                            <th class="px-4 py-3 text-left font-semibold">Position</th>
                            <th class="px-4 py-3 text-left font-semibold">Inviter</th>
                            <th class="px-4 py-3 text-left font-semibold">Status</th>
                        </tr>
                    </thead>
                    <tbody id="teamTable">
                        <?php if (!empty($team)): ?>
                            <?php $i = 1; foreach ($team as $row): ?>
                            <tr data-level="<?php echo $row['level']; ?>"
                                data-search="<?php echo strtolower(htmlspecialchars($row['customer_id'] . ' ' . $row['full_name'])); ?>"
                                class="border-t hover:bg-gray-50 transition-colors">

                                <td class="px-4 py-3 text-gray-400 font-medium serial-number"></td>

                                <td class="px-4 py-3">
                                    <span class="font-mono text-blue-600 font-semibold text-xs">
                                        <?php echo htmlspecialchars($row['customer_id']); ?>
                                    </span>
                                </td>

                                <td class="px-4 py-3 font-medium text-gray-800">
                                    <?php echo htmlspecialchars($row['full_name']); ?>
                                </td>

                                <td class="px-4 py-3">
                                    <span class="bg-blue-50 text-blue-600 text-xs font-bold px-2 py-1 rounded-full">
                                        L<?php echo $row['level']; ?>
                                    </span>
                                </td>

                                <td class="px-4 py-3 text-gray-500 text-xs">
                                    <?php
                                    if ($row['registration_status'] === 'Active' && !empty($row['activated_at'])) {
                                        echo date('d M Y', strtotime($row['activated_at']));
                                    } else { echo '—'; }
                                    ?>
                                </td>

                                <td class="px-4 py-3 font-semibold text-gray-800">
                                    ₹<?php echo number_format($row['total_turnover'], 2); ?>
                                </td>

                                <td class="px-4 py-3">
                                    <?php if (!empty($row['position'])): ?>
                                        <span class="bg-purple-50 text-purple-700 text-xs font-bold px-2 py-1 rounded-full">
                                            <?php echo htmlspecialchars($row['position']); ?>
                                        </span>
                                    <?php else: echo '—'; endif; ?>
                                </td>

                                <td class="px-4 py-3 text-gray-500 text-xs">
                                    <span class="font-mono text-gray-700 font-semibold"><?php echo htmlspecialchars($row['referral_id']); ?></span><br>
                                    <span class="text-gray-400"><?php echo htmlspecialchars($row['inviter_name'] ?? '—'); ?></span>
                                </td>

                                <td class="px-4 py-3">
                                    <?php if ($row['registration_status'] === 'Active'): ?>
                                        <span class="inline-flex items-center gap-1 px-2 py-1 bg-green-50 text-green-700 rounded-full text-xs font-semibold">
                                            <span class="w-1.5 h-1.5 rounded-full bg-green-500 inline-block"></span> Active
                                        </span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center gap-1 px-2 py-1 bg-red-50 text-red-600 rounded-full text-xs font-semibold">
                                            <span class="w-1.5 h-1.5 rounded-full bg-red-400 inline-block"></span> Inactive
                                        </span>
                                    <?php endif; ?>
                                </td>

                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="9" class="text-center py-12 text-gray-400">
                                    <svg class="w-10 h-10 mx-auto mb-3 text-gray-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                                    No team members found
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ══ MOBILE CARDS ══ -->
        <div class="mobile-card space-y-3" id="mobileCards">
            <?php if (!empty($team)): ?>
                <?php $i = 1; foreach ($team as $row): ?>
                <div class="team-card bg-white rounded-xl border shadow-sm p-4"
                     data-level="<?php echo $row['level']; ?>"
                     data-search="<?php echo strtolower(htmlspecialchars($row['customer_id'] . ' ' . $row['full_name'])); ?>">

                    <!-- Card top row -->
                    <div class="flex items-start justify-between gap-3 mb-3">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-full bg-blue-50 flex items-center justify-center flex-shrink-0">
                                <svg class="w-5 h-5 text-blue-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                            </div>
                            <div>
                                <div class="font-bold text-gray-800 text-sm leading-tight">
                                    <?php echo htmlspecialchars($row['full_name']); ?>
                                </div>
                                <div class="font-mono text-blue-600 text-xs font-semibold mt-0.5">
                                    <?php echo htmlspecialchars($row['customer_id']); ?>
                                </div>
                            </div>
                        </div>
                        <div class="flex flex-col items-end gap-1.5 flex-shrink-0">
                            <?php if ($row['registration_status'] === 'Active'): ?>
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 bg-green-50 text-green-700 rounded-full text-xs font-semibold">
                                    <span class="w-1.5 h-1.5 rounded-full bg-green-500 inline-block"></span> Active
                                </span>
                            <?php else: ?>
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 bg-red-50 text-red-600 rounded-full text-xs font-semibold">
                                    <span class="w-1.5 h-1.5 rounded-full bg-red-400 inline-block"></span> Inactive
                                </span>
                            <?php endif; ?>
                            <span class="bg-blue-50 text-blue-600 text-xs font-bold px-2 py-0.5 rounded-full">
                                Level <?php echo $row['level']; ?>
                            </span>
                        </div>
                    </div>

                    <!-- Card details grid -->
                    <div class="grid grid-cols-2 gap-x-4 gap-y-2.5 text-xs border-t pt-3">

                        <div>
                            <div class="text-gray-400 font-semibold uppercase tracking-wide mb-0.5">Business</div>
                            <div class="font-bold text-gray-800">₹<?php echo number_format($row['total_turnover'], 2); ?></div>
                        </div>

                        <div>
                            <div class="text-gray-400 font-semibold uppercase tracking-wide mb-0.5">Position</div>
                            <div>
                                <?php if (!empty($row['position'])): ?>
                                    <span class="bg-purple-50 text-purple-700 font-bold px-2 py-0.5 rounded-full">
                                        <?php echo htmlspecialchars($row['position']); ?>
                                    </span>
                                <?php else: echo '<span class="text-gray-400">—</span>'; endif; ?>
                            </div>
                        </div>

                        <div>
                            <div class="text-gray-400 font-semibold uppercase tracking-wide mb-0.5">Activated On</div>
                            <div class="font-medium text-gray-700">
                                <?php
                                if ($row['registration_status'] === 'Active' && !empty($row['activated_at'])) {
                                    echo date('d M Y', strtotime($row['activated_at']));
                                } else { echo '<span class="text-gray-400">—</span>'; }
                                ?>
                            </div>
                        </div>

                        <div>
                            <div class="text-gray-400 font-semibold uppercase tracking-wide mb-0.5">Inviter</div>
                            <div class="font-mono font-semibold text-gray-700 text-xs"><?php echo htmlspecialchars($row['referral_id']); ?></div>
                            <div class="text-gray-400 text-xs"><?php echo htmlspecialchars($row['inviter_name'] ?? '—'); ?></div>
                        </div>

                    </div>

                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="bg-white rounded-xl border p-10 text-center text-gray-400">
                    <svg class="w-10 h-10 mx-auto mb-3 text-gray-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                    No team members found
                </div>
            <?php endif; ?>
        </div>

        <!-- No results message -->
        <div id="noResults" class="hidden text-center py-10 text-gray-400 text-sm">
            No members match your search.
        </div>

    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function () {

    if (typeof lucide !== "undefined") {
        lucide.createIcons();
    }

    const levelFilter = document.getElementById('levelFilter');
    const searchInput = document.getElementById('searchInput');
    const memberCount = document.getElementById('memberCount');
    const noResults   = document.getElementById('noResults');

    function applyFilters() {

        const level  = levelFilter.value;
        const search = searchInput.value.toLowerCase().trim();

        /* Desktop rows */
        const rows = document.querySelectorAll('#teamTable tr[data-level]');

        /* Mobile cards */
        const cards = document.querySelectorAll('#mobileCards .team-card');

        let visibleCount = 0;
        let serial = 1;

        function matchRow(el) {

            const lvlOk =
                level === 'all' || el.dataset.level === level;

            const searchOk =
                !search || el.dataset.search.includes(search);

            return lvlOk && searchOk;
        }

        
        rows.forEach(row => {

            const show = matchRow(row);

            row.style.display = show ? '' : 'none';

            if (show) {

                visibleCount++;

                const serialCell =
                    row.querySelector('.serial-number');

                if (serialCell) {
                    serialCell.innerText = serial++;
                }
            }
        });

      
        cards.forEach(card => {

            const show = matchRow(card);

            card.style.display = show ? '' : 'none';
        });

        /* ===== Update Total Count ===== */
        if (memberCount) {
            memberCount.innerText = visibleCount;
        }

        /* ===== No Results ===== */
        if (noResults) {
            noResults.classList.toggle(
                'hidden',
                visibleCount > 0
            );
        }
    }

    /* ===== Events ===== */
    if (levelFilter) {
        levelFilter.addEventListener('change', applyFilters);
    }

    if (searchInput) {
        searchInput.addEventListener('input', applyFilters);
    }

    /* ===== Initial Load ===== */
    applyFilters();

});
</script>

</body>
</html>