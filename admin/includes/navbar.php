<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$currentPage = basename($_SERVER['PHP_SELF'] ?? '');

/*
|--------------------------------------------------------------------------
| Page Title + Icon Map
| IMPORTANT:
| Keep file names SAME as sidebar links
|--------------------------------------------------------------------------
*/
$pageConfig = [
    'dashboard.php'                 => ['title' => 'Dashboard', 'icon' => 'layout-dashboard'],

    // Admin
    'change_password.php'          => ['title' => 'Change Password', 'icon' => 'lock-keyhole'],
    'member-list.php'              => ['title' => 'All Members', 'icon' => 'users'],
    'member-admin-access.php'      => ['title' => 'Member Admin Access', 'icon' => 'user-cog'],
    'product-master.php'           => ['title' => 'Product Master', 'icon' => 'package-plus'],
    'product-list.php'             => ['title' => 'Product List', 'icon' => 'package'],

    // Wallet
    'wallet-status.php'            => ['title' => 'Wallet Status', 'icon' => 'wallet'],
    'wallet-report.php'            => ['title' => 'Wallet Report', 'icon' => 'file-text'],
    'wallet-request.php'           => ['title' => 'Wallet Request', 'icon' => 'badge-indian-rupee'],

    // Orders / Dispatch
    'repurchase_order_request.php' => ['title' => 'Repurchase Order Request', 'icon' => 'clipboard-list'],
    'purchase_dispatch_view.php'   => ['title' => 'Purchase Delivery Dispatch', 'icon' => 'package-check'],
    'repurchase_dispatch_view.php' => ['title' => 'Repurchase Delivery Dispatch', 'icon' => 'truck'],
    'dispatch_view.php'            => ['title' => 'Dispatch View', 'icon' => 'truck'],

    // Associate
    'associate_report.php'         => ['title' => 'Associate Report', 'icon' => 'users'],
    'shipping-details.php'         => ['title' => 'Shipping Details', 'icon' => 'truck'],
    'bank-info.php'                => ['title' => 'Bank Info', 'icon' => 'landmark'],
    'view_network_tree.php'        => ['title' => 'View Network Tree', 'icon' => 'network'],
    'upline_view.php'              => ['title' => 'Upline View', 'icon' => 'git-branch-plus'],

    // Club / Accounts
    'club_achiever.php'            => ['title' => 'Club Achiever', 'icon' => 'trophy'],
    'process_payout.php'           => ['title' => 'Process Payout', 'icon' => 'calculator'],
    'payout_closing_list.php'      => ['title' => 'Payout Closing List', 'icon' => 'receipt-text'],
    'cheque_request_all.php'       => ['title' => 'Cheque Request All', 'icon' => 'scroll-text'],
    'tds_report.php'               => ['title' => 'TDS Report', 'icon' => 'file-chart-column'],

    // Message
    'compose.php'                  => ['title' => 'Compose', 'icon' => 'messages-square'],
    'inbox.php'                    => ['title' => 'Inbox', 'icon' => 'inbox'],
    'outbox.php'                   => ['title' => 'Outbox', 'icon' => 'send'],
    'inbox-thread-details.php'     => ['title' => 'Message Thread', 'icon' => 'mail-open'],
    'outbox-thread-details.php'    => ['title' => 'Message Thread', 'icon' => 'mail-open'],

    //State franchies
    'franchies_reg.php'            => ['title' => 'Franchies Registration', 'icon' => 'user-plus'],
    'franchies_product_request.php' => ['title' => 'Franchies Product   Request', 'icon' => 'package-plus'],
    'franchies_product_add_list.php' => ['title' => 'Franchies Product Add List', 'icon' => 'package'],
    'franchise-kyc-details.php' => ['title' => 'Franchise KYC Details', 'icon' => 'id-card'],
    'stock-reduction-report.php'    => ['title' => 'Stock Reduction Report', 'icon' => 'file-bar-chart-2'],

    //City franchies
    'city-franchies.php'            => ['title' => 'City Franchies', 'icon' => 'user-plus'],
    'city-franchies-product-request.php' => ['title' => 'City Franchies Product Request', 'icon' => 'package-plus'],
    'city-franchies-product-add-list.php' => ['title' => 'City Franchies Product Add List', 'icon' => 'package'],

    //Moblie franchies
    'mobile-franchies-reg.php'            => ['title' => 'Mobile Franchies Registration', 'icon' => 'user-plus'],
    'mobile-franchies-kyc.php'            => ['title' => 'Mobile Franchies KYC', 'icon' => 'id-card'],
    'franchies-messages.php'            => ['title' => 'Franchies Messages', 'icon' => 'messages-square'],
    ];

/*
|--------------------------------------------------------------------------
| Current page title and icon
|--------------------------------------------------------------------------
*/
$navTitle = $pageConfig[$currentPage]['title'] ?? 'Dashboard';
$navIcon  = $pageConfig[$currentPage]['icon'] ?? 'layout-dashboard';

/*
|--------------------------------------------------------------------------
| User data
|--------------------------------------------------------------------------
*/
$profileImage = $profileImage ?? 'https://i.pravatar.cc/80?img=12';
$managerName  = $_SESSION['full_name'] ?? ($managerName ?? 'Admin');
$managerRole  = $managerRole ?? 'Distributor';

/*
|--------------------------------------------------------------------------
| Customer / Member ID
|--------------------------------------------------------------------------
*/
$customerId = $_SESSION['customer_id']
    ?? $_SESSION['member_id']
    ?? ($customerId ?? 'ADM0001');
?>

<div class="bg-white border-b border-gray-300 h-[76px] flex items-center">
    <div class="w-full px-6">
        <div class="flex items-center justify-between">

            <!-- Left side -->
            <div class="flex items-center gap-3 min-w-0">
                <button
                    type="button"
                    class="lg:hidden inline-flex h-9 w-9 items-center justify-center rounded-md border border-gray-300 text-[#173a67]"
                    onclick="openSidebar()"
                    aria-label="Open menu"
                >
                    <i data-lucide="menu" class="w-5 h-5"></i>
                </button>

                <div class="min-w-0">
                    <div class="flex items-center gap-2 text-[#173a67] font-semibold text-[18px] leading-none">
                        <i data-lucide="<?php echo htmlspecialchars($navIcon); ?>" class="w-4 h-4 shrink-0"></i>
                        <span class="truncate"><?php echo htmlspecialchars($navTitle); ?></span>
                    </div>

                    <p class="mt-4 text-[12px] uppercase tracking-wide text-gray-400 leading-none">
                        MEMBER ID: <?php echo htmlspecialchars($customerId); ?>
                    </p>
                </div>
            </div>

            <!-- Right side -->
            <div class="hidden sm:flex items-center gap-3 ml-4">
                <div id="adminLiveClock" class="inline-flex items-center gap-1.5 rounded-full border border-[#EEE7DB] bg-[#FBF8F2] px-3 py-1.5 text-[12px] font-semibold text-[#2C2C2C] whitespace-nowrap">
                    <i data-lucide="clock" class="w-3.5 h-3.5" style="color:#E0700C"></i>
                    <span id="adminLiveClockText">--:--:--</span>
                </div>
                <div class="relative z-[70]" id="navProfileMenu">
                    <button type="button" onclick="navToggleProfileMenu(event)" class="flex items-center gap-3 focus:outline-none">
                        <img
                            src="<?php echo htmlspecialchars($profileImage); ?>"
                            alt="Profile"
                            class="h-10 w-10 rounded-full object-cover border border-gray-300 shrink-0"
                        >
                        <div class="leading-tight text-left">
                            <p class="text-[14px] font-semibold text-[#1f2d3d] leading-none">
                                <?php echo htmlspecialchars($managerName); ?>
                            </p>
                            <p class="text-[12px] text-gray-500 mt-1 leading-none">
                                <?php echo htmlspecialchars($managerRole); ?>
                            </p>
                        </div>
                        <i data-lucide="chevron-down" class="w-4 h-4 text-gray-400 shrink-0"></i>
                    </button>

                    <div id="navProfileDropdown" class="hidden absolute right-0 top-[calc(100%+10px)] w-52 bg-white rounded-xl border border-gray-200 shadow-lg py-2 z-[70]">
                        <div class="px-4 py-2 border-b border-gray-100">
                            <p class="text-[13px] font-semibold text-[#1f2d3d] truncate"><?php echo htmlspecialchars($managerName); ?></p>
                            <p class="text-[11px] text-gray-400 truncate"><?php echo htmlspecialchars($managerRole); ?></p>
                        </div>
                        <a href="change_password.php" class="flex items-center gap-2.5 px-4 py-2.5 text-[13px] text-gray-700 hover:bg-[#FBF8F2]">
                            <span class="h-7 w-7 rounded-lg flex items-center justify-center shrink-0" style="background:#FFF3E7">
                                <i data-lucide="key-round" class="w-3.5 h-3.5" style="color:#B25400"></i>
                            </span>
                            Change Password
                        </a>
                        <a href="logout.php" class="flex items-center gap-2.5 px-4 py-2.5 text-[13px] text-red-600 hover:bg-red-50">
                            <span class="h-7 w-7 rounded-lg flex items-center justify-center shrink-0 bg-red-50">
                                <i data-lucide="log-out" class="w-3.5 h-3.5"></i>
                            </span>
                            Logout
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    (function () {
        var el = document.getElementById('adminLiveClockText');
        if (!el) return;
        function tick() {
            el.textContent = new Date().toLocaleTimeString('en-IN', { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true });
        }
        tick();
        setInterval(tick, 1000);
    })();

    function navToggleProfileMenu(evt) {
        evt.stopPropagation();
        var dd = document.getElementById('navProfileDropdown');
        if (dd) dd.classList.toggle('hidden');
    }
    document.addEventListener('click', function (evt) {
        var wrap = document.getElementById('navProfileMenu');
        var dd = document.getElementById('navProfileDropdown');
        if (wrap && dd && !wrap.contains(evt.target)) dd.classList.add('hidden');
    });
</script>