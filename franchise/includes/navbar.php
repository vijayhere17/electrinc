<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$currentPage = basename($_SERVER['PHP_SELF'] ?? '');

$pageConfig = [
    'dashboard.php' => ['title' => 'Dashboard', 'icon' => 'layout-dashboard'],
    'profile.php'    => ['title' => 'Profile', 'icon' => 'user-round'],
    'my-kit.php'     => ['title' => 'My Kit', 'icon' => 'box'],
    'inbox.php'      => ['title' => 'Inbox', 'icon' => 'inbox'],
    'outbox.php'     => ['title' => 'Outbox', 'icon' => 'send'],
    'product-stock-transaction.php' => ['title' => 'Product Stock Transaction', 'icon' => 'package'],
    'product-request.php' => ['title' => 'Product Request', 'icon' => 'shopping-cart'],
    'product-request-city-mobile.php' => ['title' => 'Product Request (City/Mobile)', 'icon' => 'shopping-cart'],
    'product-request-state.php' => ['title' => 'Product Request (State)', 'icon' => 'shopping-cart'],
    'member-activation.php' => ['title' => 'Member Activation', 'icon' => 'user-check'],
    'send-product-request.php' => ['title' => 'Send Product Request', 'icon' => 'send'],
    'order-sale.php' => ['title' => 'Order Sale', 'icon' => 'cart-shopping'],
    'member-order.php' => ['title' => 'Member Order', 'icon' => 'clipboard-list'],
    'member_order_list.php' => ['title' => 'Member Order List', 'icon' => 'clipboard-list'],
    'product-stock-request.php' => ['title' => 'Product Stock Report', 'icon' => 'report']
    , 'join-member-list.php' => ['title' => 'Join Member List', 'icon' => 'users'],
    'join-member-kyc.php' => ['title' => 'Join Member KYC', 'icon' => 'id-card'],
    'franchise-messages.php' => ['title' => 'Franchise Messages', 'icon' => 'message-square'],
    'compose.php' => ['title' => 'Compose Message', 'icon' => 'send']
    , 'inbox-thread-details.php' => ['title' => 'Message Details', 'icon' => 'message-square'],
    'change-password.php' => ['title' => 'Change Password', 'icon' => 'key'],
    'change_password.php' => ['title' => 'Change Password', 'icon' => 'key'],
    'edit-profile.php' => ['title' => 'Edit Profile', 'icon' => 'user-edit']
    ,'view-product-stock-transaction.php' => ['title' => 'Product Stock Transaction Details', 'icon' => 'package']
    , 'member_order_list.php' => ['title' => 'Member Order List', 'icon' => 'clipboard-list']
];

$navTitle = $pageConfig[$currentPage]['title'] ?? 'Dashboard';
$navIcon  = $pageConfig[$currentPage]['icon'] ?? 'layout-dashboard';
$franchiseName = isset($_SESSION['franchise_name']) && trim((string)$_SESSION['franchise_name']) !== ''
    ? $_SESSION['franchise_name']
    : 'Franchise';
$franchiseCode = isset($_SESSION['franchise_code']) && trim((string)$_SESSION['franchise_code']) !== ''
    ? $_SESSION['franchise_code']
    : (isset($_SESSION['franchise_id']) ? (string)$_SESSION['franchise_id'] : '');
$profileImage = 'https://i.pravatar.cc/80?img=12';

/* franchise common details for the top bar (from auth.php globals, session fallback) */
$_frRow   = $GLOBALS['franchise'] ?? [];
$_frType  = strtolower(trim((string)($GLOBALS['franchiseType'] ?? ($_SESSION['franchise_type'] ?? ($_SESSION['franchies_type'] ?? '')))));
$_frState = trim((string)($_frRow['state'] ?? ($_SESSION['franchise_state'] ?? '')));
$_frCity  = trim((string)($_frRow['city']  ?? ($_SESSION['franchise_city']  ?? '')));
$_frTypeLabel = $_frType === 'city' ? 'City Franchise' : ($_frType === 'mobile' ? 'Mobile Franchise' : 'State Franchise');
$_frMeta = [
    ['scan-barcode', 'Code',  $franchiseCode ?: '—'],
    ['building-2',   'Type',  $_frTypeLabel],
    ['map-pin',      'State', $_frState ?: '—'],
    ['navigation',   'City',  $_frCity ?: '—'],
];
?>

<div class="relative bg-white h-[82px] flex items-center w-full shadow-[0_2px_16px_-4px_rgba(14,58,42,.12)]">
    <div class="w-full px-6">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-3 min-w-0">
                <button
                    type="button"
                    class="lg:hidden inline-flex h-9 w-9 items-center justify-center rounded-md border border-gray-300 text-[#173a67]"
                    onclick="openSidebar()"
                    aria-label="Open menu"
                >
                    <i data-lucide="menu" class="w-5 h-5"></i>
                </button>

                <div class="hidden sm:flex h-10 w-10 rounded-xl items-center justify-center shrink-0" style="background:linear-gradient(135deg,#FFF3E7,#EAF5EC)">
                    <i data-lucide="<?php echo e($navIcon); ?>" class="w-[18px] h-[18px]" style="color:#B25400"></i>
                </div>

                <div class="min-w-0">
                    <div class="flex items-center gap-2 text-[#232323] font-semibold text-[18px] leading-none">
                        <span class="truncate"><?php echo e($navTitle); ?></span>
                    </div>

                    <p class="mt-2 text-[11px] uppercase tracking-wide text-gray-400 leading-none">
                        <?php echo e($franchiseName); ?>
                    </p>
                </div>
            </div>

            <div class="hidden lg:flex items-center gap-1.5 flex-1 justify-center px-4 min-w-0">
                <?php foreach ($_frMeta as $m): ?>
                    <span class="inline-flex items-center gap-1.5 rounded-full border border-[#EEE7DB] bg-[#FBF8F2] px-3 py-1.5 text-[11.5px] text-[#7A736A] whitespace-nowrap">
                        <i data-lucide="<?php echo e($m[0]); ?>" class="w-3.5 h-3.5 text-[#E0700C]"></i>
                        <?php echo e($m[1]); ?> <b class="font-semibold text-[#2C2C2C]"><?php echo e($m[2]); ?></b>
                    </span>
                <?php endforeach; ?>
            </div>

            <div class="relative z-[70] hidden sm:block ml-4 shrink-0" id="navProfileMenu">
                <button type="button" onclick="navToggleProfileMenu(event)" class="flex items-center gap-3 focus:outline-none">
                    <div class="leading-tight text-right">
                        <p class="text-[14px] font-semibold text-[#1f2d3d] leading-none">
                            <?php echo e($franchiseName); ?>
                        </p>
                        <p class="text-[12px] mt-1.5 leading-none font-medium" style="color:#0E7A3B">
                            Franchise Panel
                        </p>
                    </div>

                    <div class="h-10 w-10 rounded-full p-[2px] shrink-0" style="background:linear-gradient(135deg,#E0700C,#0E7A3B)">
                        <img
                            src="<?php echo e($profileImage); ?>"
                            alt="Profile"
                            class="h-full w-full rounded-full object-cover border-2 border-white"
                        >
                    </div>
                    <i data-lucide="chevron-down" class="w-4 h-4 text-gray-400 shrink-0"></i>
                </button>

                <div id="navProfileDropdown" class="hidden absolute right-0 top-[calc(100%+10px)] w-52 bg-white rounded-xl border border-gray-200 shadow-lg py-2 z-[70]">
                    <div class="px-4 py-2 border-b border-gray-100">
                        <p class="text-[13px] font-semibold text-[#1f2d3d] truncate"><?php echo e($franchiseName); ?></p>
                        <p class="text-[11px] text-gray-400 truncate"><?php echo e($franchiseCode); ?></p>
                    </div>
                    <a href="profile.php" class="flex items-center gap-2.5 px-4 py-2.5 text-[13px] text-gray-700 hover:bg-[#FBF8F2]">
                        <span class="h-7 w-7 rounded-lg flex items-center justify-center shrink-0" style="background:#FFF3E7">
                            <i data-lucide="user-round" class="w-3.5 h-3.5" style="color:#B25400"></i>
                        </span>
                        My Profile
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

<!-- LUCIDE ICONS: ensure icons render for franchise pages -->
<script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
<script>
    if (typeof lucide !== 'undefined') {
        try { lucide.createIcons(); } catch(e) { /* ignore */ }
    }

    function navToggleProfileMenu(evt) {
        evt.stopPropagation();
        const dd = document.getElementById('navProfileDropdown');
        if (dd) dd.classList.toggle('hidden');
    }
    document.addEventListener('click', function (evt) {
        const wrap = document.getElementById('navProfileMenu');
        const dd = document.getElementById('navProfileDropdown');
        if (wrap && dd && !wrap.contains(evt.target)) dd.classList.add('hidden');
    });
</script>
