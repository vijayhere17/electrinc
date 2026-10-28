<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$basePath = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/\\');
if ($basePath === '' || $basePath === '.') {
    $basePath = '';
}

$currentPage = basename($_SERVER['PHP_SELF'] ?? '');

$isPreviewMode = false;

if (!function_exists('pageUrl')) {
    function pageUrl(string $basePath, string $page, bool $isPreviewMode = false): string
    {
        $basePath = rtrim($basePath, '/\\');
        $url = ($basePath === '') ? '/' . ltrim($page, '/') : $basePath . '/' . ltrim($page, '/');

        if ($isPreviewMode) {
            $url .= (strpos($url, '?') === false ? '?' : '&') . 'preview=1';
        }

        return $url;
    }
}

if (!function_exists('isActivePage')) {
    function isActivePage(array $pages, string $currentPage): bool
    {
        return in_array($currentPage, $pages, true);
    }
}

$profilePages = [
    'profile.php',
    'shipping-address.php',
    'bank-info.php',
    'welcome-letter.php',
    'change-password.php',
    'change-transaction-password.php'
];

$networkPages = [
    'my-direct-network.php',
    'my-team-volume-status.php'
];

$incomeStatusPages = [
    'retail-profit.php',
    'level-matching-bonus.php',
    'club-bonus.php',
    'referral-bonus.php',
    'reward.php',
    'repurchase.php'
];

$incomeReportPages = [
    'direct-sponsor-income.php',
    'binary-income.php',
    'level-income.php',
    'auto-pool-income.php',
    'performance-bonus.php',
    'team-performance-bonus.php',
    'income-transactions.php'
];

$repurchasePages = [
    'create-new-order.php',
    'my-order-list.php',
    'my-team-business-status.php'
];

$messagePages = [
    'compose.php',
    'inbox.php',
    'outbox.php'
];

$myTeamPages = [
    'my-referral-list.php',
    'my-team-mates.php',
    'tree-view.php'
];

$dashboardActive   = $currentPage === 'dashboard.php';
$orderStatusActive = $currentPage === 'order_status.php';
$profileOpen       = isActivePage($profilePages, $currentPage);
$networkOpen       = isActivePage($networkPages, $currentPage);
$incomeStatusOpen  = isActivePage($incomeStatusPages, $currentPage);
$incomeReportOpen  = isActivePage($incomeReportPages, $currentPage);
$repurchaseOpen    = isActivePage($repurchasePages, $currentPage);
$messageOpen       = isActivePage($messagePages, $currentPage);
$myTeamOpen        = isActivePage($myTeamPages, $currentPage);

$loggedUserName = $_SESSION['full_name'] ?? 'Member';
$loggedCustomerId = $_SESSION['customer_id'] ?? ($_SESSION['member_id'] ?? '');

$parts = preg_split('/\s+/', trim($loggedUserName));
$initials = '';

foreach ($parts as $part) {
    if ($part !== '') {
        $initials .= strtoupper(substr($part, 0, 1));
    }
}

$userInitials = substr($initials ?: 'M', 0, 2);
?>

<style>
    @media (max-width: 767px) {
        .ml-64 {
            margin-left: 0 !important;
        }
    }
</style>
<link rel="stylesheet" href="<?php echo htmlspecialchars(pageUrl($basePath, 'assets/theme.css', $isPreviewMode)); ?>">

<div id="sidebarOverlay" class="fixed inset-0 z-40 bg-black/40 hidden md:hidden" onclick="closeSidebar()"></div>

<div id="appSidebar" class="layout-sidebar fixed top-0 left-0 z-50 h-[100dvh] w-64 bg-[#103258] text-white flex flex-col border-r border-[#254A73] transform -translate-x-full md:translate-x-0 transition-transform duration-200 ease-out">

 <div class="border-b border-[#254A73] px-5 py-5 flex flex-col items-center justify-center gap-1.5 shrink-0">
    <img
        src="<?php echo htmlspecialchars(pageUrl($basePath, 'assets/electrinc-logo.png', $isPreviewMode)); ?>"
        class="h-7 w-auto max-w-[80%] object-contain"
        alt="Electrinc"
    >
    <p class="text-[10px] font-bold tracking-[.25em] uppercase" style="color:rgba(255,255,255,.55)">Member Panel</p>
</div>
    <div class="flex-1 px-2 py-3 overflow-y-auto">

        <a href="<?php echo htmlspecialchars(pageUrl($basePath, 'dashboard.php', $isPreviewMode)); ?>"
           class="flex items-center gap-3 px-3 h-[40px] rounded-md text-[13px] font-medium transition <?php echo $dashboardActive ? 'bg-[#DDBE7E] text-white' : 'text-white hover:bg-[#1A3F66]'; ?>">
            <i data-lucide="layout-dashboard" class="w-4 h-4 shrink-0"></i>
            <span>Dashboard</span>
        </a>

        <details class="group mt-1 rounded-md" <?php echo $profileOpen ? 'open' : ''; ?>>
            <summary class="list-none cursor-pointer flex items-center justify-between px-3 h-[40px] rounded-md text-[13px] <?php echo $profileOpen ? 'bg-[#1A3F66]' : 'hover:bg-[#1A3F66]'; ?>">
                <span class="flex items-center gap-3">
                    <i data-lucide="user-round" class="w-4 h-4 shrink-0"></i>
                    <span>Profile</span>
                </span>
                <i data-lucide="chevron-right" class="w-4 h-4 shrink-0 transition-transform group-open:rotate-90"></i>
            </summary>

            <div class="mt-1 pl-2 pr-1 space-y-1">
                <a href="<?php echo htmlspecialchars(pageUrl($basePath, 'profile.php', $isPreviewMode)); ?>" class="block rounded px-3 py-2 text-[12px] <?php echo $currentPage === 'profile.php' ? 'bg-[#DDBE7E] text-white' : 'text-[#D7E1EC] hover:bg-[#DDBE7E] hover:text-white'; ?>">My Profile</a>
                <a href="<?php echo htmlspecialchars(pageUrl($basePath, 'shipping-address.php', $isPreviewMode)); ?>" class="block rounded px-3 py-2 text-[12px] <?php echo $currentPage === 'shipping-address.php' ? 'bg-[#DDBE7E] text-white' : 'text-[#D7E1EC] hover:bg-[#DDBE7E] hover:text-white'; ?>">My Shipping Details</a>
                <a href="<?php echo htmlspecialchars(pageUrl($basePath, 'bank-info.php', $isPreviewMode)); ?>" class="block rounded px-3 py-2 text-[12px] <?php echo $currentPage === 'bank-info.php' ? 'bg-[#DDBE7E] text-white' : 'text-[#D7E1EC] hover:bg-[#DDBE7E] hover:text-white'; ?>">My Kyc</a>
                <a href="<?php echo htmlspecialchars(pageUrl($basePath, 'welcome-letter.php', $isPreviewMode)); ?>" class="block rounded px-3 py-2 text-[12px] <?php echo $currentPage === 'welcome-letter.php' ? 'bg-[#DDBE7E] text-white' : 'text-[#D7E1EC] hover:bg-[#DDBE7E] hover:text-white'; ?>">Welcome Letter</a>
                <a href="<?php echo htmlspecialchars(pageUrl($basePath, 'change-password.php', $isPreviewMode)); ?>" class="block rounded px-3 py-2 text-[12px] <?php echo $currentPage === 'change-password.php' ? 'bg-[#DDBE7E] text-white' : 'text-[#D7E1EC] hover:bg-[#DDBE7E] hover:text-white'; ?>">Change Password</a>
                <!--<a href="<?php echo htmlspecialchars(pageUrl($basePath, 'change-transaction-password.php', $isPreviewMode)); ?>" class="block rounded px-3 py-2 text-[12px] <?php echo $currentPage === 'change-transaction-password.php' ? 'bg-[#DDBE7E] text-white' : 'text-[#D7E1EC] hover:bg-[#DDBE7E] hover:text-white'; ?>">Change Transaction Password</a>-->
            </div>
        </details>
        
        <details class="group mt-1 rounded-md" <?php echo $myTeamOpen ? 'open' : ''; ?>>
    <summary class="list-none cursor-pointer flex items-center justify-between px-3 h-[40px] rounded-md text-[13px] <?php echo $myTeamOpen ? 'bg-[#1A3F66]' : 'hover:bg-[#1A3F66]'; ?>">
        <span class="flex items-center gap-3">
            <i data-lucide="network" class="w-4 h-4 shrink-0"></i>
            <span>My Team</span>
        </span>
        <i data-lucide="chevron-right" class="w-4 h-4 shrink-0 transition-transform group-open:rotate-90"></i>
    </summary>

    <div class="mt-1 pl-2 pr-1 space-y-1">

        <a href="<?php echo htmlspecialchars(pageUrl($basePath, 'my-referral-list.php', $isPreviewMode)); ?>"
           class="block rounded px-3 py-2 text-[12px] <?php echo $currentPage === 'my-referral-list.php' ? 'bg-[#DDBE7E] text-white' : 'text-[#D7E1EC] hover:bg-[#DDBE7E] hover:text-white'; ?>">
            My Referral List
        </a>

        <a href="<?php echo htmlspecialchars(pageUrl($basePath, 'my-team-mates.php', $isPreviewMode)); ?>"
           class="block rounded px-3 py-2 text-[12px] <?php echo $currentPage === 'my-team-mates.php' ? 'bg-[#DDBE7E] text-white' : 'text-[#D7E1EC] hover:bg-[#DDBE7E] hover:text-white'; ?>">
            My Team Mates
        </a>

        <a href="<?php echo htmlspecialchars(pageUrl($basePath, 'tree-view.php', $isPreviewMode)); ?>"
           class="block rounded px-3 py-2 text-[12px] <?php echo $currentPage === 'tree-view.php' ? 'bg-[#DDBE7E] text-white' : 'text-[#D7E1EC] hover:bg-[#DDBE7E] hover:text-white'; ?>">
            Tree View
        </a>

    </div>
</details>

        <a href="<?php echo htmlspecialchars(pageUrl($basePath, 'activation.php', $isPreviewMode)); ?>"
           class="flex items-center gap-3 px-3 h-[40px] rounded-md text-[13px] font-medium transition <?php echo $currentPage === 'activation.php' ? 'bg-[#DDBE7E] text-white' : 'text-white hover:bg-[#1A3F66]'; ?>">
            <i data-lucide="zap" class="w-4 h-4 shrink-0"></i>
            <span>Book / Activate</span>
        </a>

        <a href="go_to_shop.php"
   class="flex items-center gap-3 px-3 h-[40px] rounded-md text-[13px] font-medium text-white hover:bg-[#1A3F66]">
    <i data-lucide="shopping-bag" class="w-4 h-4 shrink-0"></i>
    <span>Shop</span>
</a>

        <a href="<?php echo htmlspecialchars(pageUrl($basePath, 'order_status.php', $isPreviewMode)); ?>"
           class="flex items-center gap-3 px-3 h-[40px] rounded-md text-[13px] font-medium transition <?php echo $orderStatusActive ? 'bg-[#DDBE7E] text-white' : 'text-white hover:bg-[#1A3F66]'; ?>">
            <i data-lucide="clipboard-list" class="w-4 h-4 shrink-0"></i>
            <span>My Order Status</span>
        </a>

        <?php $walletPages = ['wallet.php', 'wallet-p2p.php', 'wallet-bank.php']; $walletOpen = in_array($currentPage, $walletPages, true); ?>
        <details class="group mt-1 rounded-md" <?php echo $walletOpen ? 'open' : ''; ?>>
            <summary class="list-none cursor-pointer flex items-center justify-between px-3 h-[40px] rounded-md text-[13px] <?php echo $walletOpen ? 'bg-[#1A3F66]' : 'hover:bg-[#1A3F66]'; ?>">
                <span class="flex items-center gap-3">
                    <i data-lucide="wallet" class="w-4 h-4 shrink-0"></i>
                    <span>Wallet</span>
                </span>
                <i data-lucide="chevron-right" class="w-4 h-4 shrink-0 transition-transform group-open:rotate-90"></i>
            </summary>
            <div class="mt-1 pl-2 pr-1 space-y-1">
                <a href="<?php echo htmlspecialchars(pageUrl($basePath, 'wallet.php', $isPreviewMode)); ?>" class="block rounded px-3 py-2 text-[12px] <?php echo $currentPage === 'wallet.php' ? 'bg-[#DDBE7E] text-white' : 'text-[#D7E1EC] hover:bg-[#DDBE7E] hover:text-white'; ?>">Wallet Overview</a>
                <a href="<?php echo htmlspecialchars(pageUrl($basePath, 'wallet-p2p.php', $isPreviewMode)); ?>" class="block rounded px-3 py-2 text-[12px] <?php echo $currentPage === 'wallet-p2p.php' ? 'bg-[#DDBE7E] text-white' : 'text-[#D7E1EC] hover:bg-[#DDBE7E] hover:text-white'; ?>">P2P Transfer</a>
                <a href="<?php echo htmlspecialchars(pageUrl($basePath, 'wallet-bank.php', $isPreviewMode)); ?>" class="block rounded px-3 py-2 text-[12px] <?php echo $currentPage === 'wallet-bank.php' ? 'bg-[#DDBE7E] text-white' : 'text-[#D7E1EC] hover:bg-[#DDBE7E] hover:text-white'; ?>">Wallet to Bank</a>
            </div>
        </details>

        <details class="group mt-1 rounded-md" <?php echo $incomeReportOpen ? 'open' : ''; ?>>
            <summary class="list-none cursor-pointer flex items-center justify-between px-3 h-[40px] rounded-md text-[13px] <?php echo $incomeReportOpen ? 'bg-[#1A3F66]' : 'hover:bg-[#1A3F66]'; ?>">
                <span class="flex items-center gap-3">
                    <i data-lucide="file-text" class="w-4 h-4 shrink-0"></i>
                    <span>Income Report</span>
                </span>
                <i data-lucide="chevron-right" class="w-4 h-4 shrink-0 transition-transform group-open:rotate-90"></i>
            </summary>

            <div class="mt-1 pl-2 pr-1 space-y-1">
                <?php
                $incomeReportLinks = [
                    'direct-sponsor-income.php'  => 'Direct Sponsor Income',
                    'binary-income.php'         => 'Binary Income',
                    'level-income.php'          => 'Level Income',
                    'auto-pool-income.php'      => 'Auto Pool Income',
                    'performance-bonus.php'     => 'Performance Bonus',
                    'team-performance-bonus.php' => 'Team Performance Bonus',
                    'income-transactions.php'   => 'All Income Transactions',
                ];
                foreach ($incomeReportLinks as $lp => $lt): ?>
                    <a href="<?php echo htmlspecialchars(pageUrl($basePath, $lp, $isPreviewMode)); ?>" class="block rounded px-3 py-2 text-[12px] <?php echo $currentPage === $lp ? 'bg-[#DDBE7E] text-white' : 'text-[#D7E1EC] hover:bg-[#DDBE7E] hover:text-white'; ?>"><?php echo htmlspecialchars($lt); ?></a>
                <?php endforeach; ?>
            </div>
        </details>

        <details class="group mt-1 rounded-md" <?php echo $messageOpen ? 'open' : ''; ?>>
            <summary class="list-none cursor-pointer flex items-center justify-between px-3 h-[40px] rounded-md text-[13px] <?php echo $messageOpen ? 'bg-[#1A3F66]' : 'hover:bg-[#1A3F66]'; ?>">
                <span class="flex items-center gap-3">
                    <i data-lucide="messages-square" class="w-4 h-4 shrink-0"></i>
                    <span>Message Center</span>
                </span>
                <i data-lucide="chevron-right" class="w-4 h-4 shrink-0 transition-transform group-open:rotate-90"></i>
            </summary>

            <div class="mt-1 pl-2 pr-1 space-y-1">
                <a href="<?php echo htmlspecialchars(pageUrl($basePath, 'compose.php', $isPreviewMode)); ?>" class="block rounded px-3 py-2 text-[12px] <?php echo $currentPage === 'compose.php' ? 'bg-[#DDBE7E] text-white' : 'text-[#D7E1EC] hover:bg-[#DDBE7E] hover:text-white'; ?>">Compose</a>
                <a href="<?php echo htmlspecialchars(pageUrl($basePath, 'inbox.php', $isPreviewMode)); ?>" class="block rounded px-3 py-2 text-[12px] <?php echo $currentPage === 'inbox.php' ? 'bg-[#DDBE7E] text-white' : 'text-[#D7E1EC] hover:bg-[#DDBE7E] hover:text-white'; ?>">Inbox</a>
                <a href="<?php echo htmlspecialchars(pageUrl($basePath, 'outbox.php', $isPreviewMode)); ?>" class="block rounded px-3 py-2 text-[12px] <?php echo $currentPage === 'outbox.php' ? 'bg-[#DDBE7E] text-white' : 'text-[#D7E1EC] hover:bg-[#DDBE7E] hover:text-white'; ?>">Outbox</a>
            </div>
        </details>
    </div>

    <div class="px-3 pb-3 shrink-0">
        <div class="fr-side-tag rounded-2xl px-4 py-3.5 flex items-center gap-3">
            <span class="ic w-9 h-9 rounded-full flex items-center justify-center shrink-0">
                <i data-lucide="leaf" class="w-4 h-4 text-white"></i>
            </span>
            <p class="text-[12px] font-semibold text-white leading-snug">Clean Energy.<br>Brighter Business.</p>
        </div>
    </div>

    <div class="border-t border-[#254A73] px-4 h-[56px] flex items-center justify-between shrink-0">
        <div class="flex items-center gap-3">
            <div class="h-8 w-8 rounded-full bg-gray-200 text-[#103258] flex items-center justify-center text-xs font-semibold shrink-0">
                <?php echo htmlspecialchars($userInitials); ?>
            </div>

            <div class="leading-tight">
                <p class="text-[13px] font-medium text-white"><?php echo htmlspecialchars($loggedUserName); ?></p>
                <p class="text-[10px] text-[#7E95AE] mt-1"><?php echo htmlspecialchars($loggedCustomerId); ?></p>
            </div>
        </div>

        <a href="<?php echo htmlspecialchars(pageUrl($basePath, 'logout.php', $isPreviewMode)); ?>"
   class="flex items-center gap-2 px-3 py-2 rounded-md text-[12px] text-[#9CB2C9] hover:bg-[#1A3F66] hover:text-white transition">

    <i data-lucide="log-out" class="w-4 h-4"></i>
    <span class="hidden md:inline">Logout</span>
</a>
    </div>
</div>

<script>
    function openSidebar() {
        const sidebar = document.getElementById('appSidebar');
        const overlay = document.getElementById('sidebarOverlay');
        if (sidebar) sidebar.classList.remove('-translate-x-full');
        if (overlay) overlay.classList.remove('hidden');
    }

    function closeSidebar() {
        const sidebar = document.getElementById('appSidebar');
        const overlay = document.getElementById('sidebarOverlay');
        if (sidebar) sidebar.classList.add('-translate-x-full');
        if (overlay) overlay.classList.add('hidden');
    }

    window.addEventListener('resize', function () {
        if (window.innerWidth >= 768) {
            const sidebar = document.getElementById('appSidebar');
            const overlay = document.getElementById('sidebarOverlay');
            if (sidebar) sidebar.classList.remove('-translate-x-full');
            if (overlay) overlay.classList.add('hidden');
        }
    });

    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }
</script>