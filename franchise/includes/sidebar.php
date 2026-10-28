<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$basePath = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/\\');
if ($basePath === '' || $basePath === '.') {
    $basePath = '';
}

$currentPage = basename($_SERVER['PHP_SELF']);

function isActivePage(array $pages, string $currentPage): bool {
    return in_array($currentPage, $pages, true);
}

$profilePages = [
    'profile.php',
    'shipping-address.php',
    'bank-info.php',
    'welcome-letter.php',
    'change-password.php',
    'change_password.php',
    'change_transaction_password.php'
];

$networkPages = [
    'my-direct-network.php',
    'my-team-volume-status.php',
    'wallet-status.php',
    'wallet-report.php',
    'wallet-request.php'
];

$incomeStatusPages = [
    'associate_report.php',
    'bank-info.php',
    'view_network_tree.php',
    'upline_view.php'
];

$incomeReportPages = [
    'ewallet-status.php',
    'ewallet-report.php',
    'wallet-status.php',
    'wallet-report.php'
];

$repurchasePages = [
    'process_payout.php',
    'payout_closing_list.php',
    'cheque_request_all.php',
    'tds_report.php'
];
$memberpurchasePages = [
    'member-activation.php',
    'order-sale.php',
    'member-order.php',
    'member_order_list.php',
    'commission-report.php'
];

$messagePages = [
    'compose.php',
    'inbox.php',
    'outbox.php',
    'inbox-thread-details.php'
];

$productPages = [
    'product-request.php',
    'product-request-city-mobile.php',
    'member-activation.php',
    'send-product-request.php',
    'order-sale.php',
    'member-order.php',
    'member_order_list.php',
    'product-stock-request.php',
    'inventory.php',
    'stock-entry.php',
    'stock-report.php',
    'product-unit.php',
    'vendors.php',
    'customers.php',
    'sales.php',
    'turnover.php'
];

$productOpen = isActivePage($productPages, $currentPage);

/* --- Product area split into focused groups --- */
$invPages = [
    'inventory.php', 'product-unit.php', 'stock-entry.php',
    'vendors.php',
];
$salesPages = [
    'pos.php', 'customers.php', 'sale-invoice.php',
];
$reqPages = [
    'product-request.php', 'send-product-request.php',
    'product-request-city-mobile.php', 'product-stock-request.php',
];
$reportPages = [
    'sales.php', 'turnover.php', 'stock-report.php', 'stock-reduce-report.php',
    'commission-report.php', 'cashback.php', 'sponsor-report.php',
];
$invOpen    = isActivePage($invPages, $currentPage);
$salesOpen  = isActivePage($salesPages, $currentPage);
$reqOpen    = isActivePage($reqPages, $currentPage);
$reportOpen = isActivePage($reportPages, $currentPage);

// Detect state franchise via session hints
$isStateFranchise = false;
$isCityFranchise = false;
$typeRaw = strtolower(trim((string)($_SESSION['franchise_type'] ?? $_SESSION['franchies_type'] ?? '')));
if (str_contains($typeRaw, 'state') || !empty($_SESSION['state_franchies_holder_id'])) {
    $isStateFranchise = true;
}
if (str_contains($typeRaw, 'city') || !empty($_SESSION['city_franchies_holder_id'])) {
    $isCityFranchise = true;
}

$dashboardActive    = $currentPage === 'dashboard.php';
$profileActive      = $currentPage === 'profile.php';
$composeActive      = $currentPage === 'compose.php';
$inboxActive        = $currentPage === 'inbox.php';
$outboxActive       = $currentPage === 'outbox.php';
$orderStatusActive  = $currentPage === 'order_status.php';
$dispatchViewActive = $currentPage === 'dispatch_view.php';
$memberpurchase       = isActivePage(['member-activation.php', 'order-sale.php', 'member-order.php', 'member_order_list.php'], $currentPage);
$profileOpen        = isActivePage($profilePages, $currentPage);
$networkOpen        = isActivePage($networkPages, $currentPage);
$incomeStatusOpen   = isActivePage($incomeStatusPages, $currentPage);
$incomeReportOpen   = isActivePage($incomeReportPages, $currentPage);
$repurchaseOpen     = isActivePage($repurchasePages, $currentPage);
$messageOpen        = isActivePage($messagePages, $currentPage);

$loggedUserName = isset($_SESSION['franchise_name']) && !empty(trim((string)$_SESSION['franchise_name']))
    ? $_SESSION['franchise_name']
    : 'Franchise';
$loggedCustomerId = isset($_SESSION['franchise_id']) && !empty(trim((string)$_SESSION['franchise_id']))
    ? $_SESSION['franchise_id']
    : (isset($_SESSION['franchise_login_id']) && !empty(trim((string)$_SESSION['franchise_login_id']))
        ? $_SESSION['franchise_login_id']
        : 'FRN0001');

$parts = preg_split('/\s+/', trim((string)$loggedUserName));
$initials = '';
foreach ($parts as $part) {
    if ($part !== '') {
        $initials .= strtoupper(substr($part, 0, 1));
    }
}
$userInitials = substr($initials ?: 'F', 0, 2);

if (!function_exists('franchisePageUrl')) {
    function franchisePageUrl(string $basePath, string $page): string
    {
        $basePath = rtrim($basePath, '/\\');
        return ($basePath === '') ? '/' . ltrim($page, '/') : $basePath . '/' . ltrim($page, '/');
    }
}
?>
    <!-- Responsive helpers loaded site-wide for franchise pages -->
    <link rel="stylesheet" href="<?php echo e(franchisePageUrl($basePath, 'assets/responsive.css')); ?>">
    <link rel="stylesheet" href="<?php echo e(franchisePageUrl($basePath, 'assets/theme.css')); ?>">

<style>
    .layout-sidebar {
        z-index: 60;
    }

    .layout-overlay {
        display: none;
    }

    @media (max-width: 1023px) {
        .layout-sidebar {
            transform: translateX(-100%);
            transition: transform 0.25s ease;
        }

        .layout-sidebar.sidebar-open {
            transform: translateX(0);
        }

        .layout-overlay {
            display: block;
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.45);
            z-index: 50;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.2s ease;
        }

        .layout-overlay.sidebar-open {
            opacity: 1;
            pointer-events: auto;
        }

        .ml-64 {
            margin-left: 0 !important;
        }

        [class*="w-[calc(100%-16rem)]"] {
            width: 100% !important;
        }

        body.sidebar-lock {
            overflow: hidden;
        }
    }

    @media (min-width: 1024px) {
        .layout-sidebar {
            transform: translateX(0) !important;
        }
    }
</style>

<div id="layoutSidebar" class="layout-sidebar fixed top-0 left-0 h-screen w-64 bg-[#103258] text-white flex flex-col border-r border-[#254A73]">
    <div class="px-5 py-5 flex flex-col items-center justify-center gap-1.5 shrink-0">
        <img src="<?php echo e(franchisePageUrl($basePath, '/assets/electrinc-logo.png')); ?>" alt="Electrinc" class="h-7 w-auto max-w-[80%] object-contain">
        <p class="text-[10px] font-bold tracking-[.25em] uppercase" style="color:rgba(255,255,255,.55)">Franchise Panel</p>
    </div>

    <div class="flex-1 px-2 py-3 overflow-y-auto">
        <a href="<?php echo e(franchisePageUrl($basePath, 'dashboard.php')); ?>" class="flex items-center gap-3 px-3 h-[40px] rounded-md text-[13px] font-medium transition <?php echo $dashboardActive ? 'bg-[#DDBE7E] text-white' : 'text-white hover:bg-[#1A3F66]'; ?>">
            <i data-lucide="layout-dashboard" class="w-4 h-4 shrink-0"></i>
            <span>Dashboard</span>
        </a>

        <details class="group mt-1 rounded-md" <?php echo $profileOpen ? 'open' : ''; ?>>
                <summary class="list-none cursor-pointer flex items-center justify-between px-3 h-[40px] rounded-md text-[13px] <?php echo $profileOpen ? 'bg-[#1A3F66]' : 'hover:bg-[#1A3F66]'; ?>">
                    <span class="flex items-center gap-3">
                        <i data-lucide="package" class="w-4 h-4 shrink-0"></i>
                        <span>Profile</span>
                    </span>
                    <i data-lucide="chevron-right" class="w-4 h-4 shrink-0 transition-transform group-open:rotate-90"></i>
                </summary>

                <div class="mt-1 pl-2 pr-1 space-y-1">
                    <a href="<?php echo e(franchisePageUrl($basePath, 'profile.php')); ?>" class="block rounded px-3 py-2 text-[12px] <?php echo $currentPage === 'profile.php' ? 'bg-[#DDBE7E] text-white' : 'text-[#D7E1EC] hover:bg-[#DDBE7E] hover:text-white'; ?>">View Profile</a>
                    <a href="<?php echo e(franchisePageUrl($basePath, 'my-kyc.php')); ?>" class="block rounded px-3 py-2 text-[12px] <?php echo $currentPage === 'my-kyc.php' ? 'bg-[#DDBE7E] text-white' : 'text-[#D7E1EC] hover:bg-[#DDBE7E] hover:text-white'; ?>">My Kyc</a>
                    <a href="<?php echo e(franchisePageUrl($basePath, 'change-password.php')); ?>" class="block rounded px-3 py-2 text-[12px] <?php echo $currentPage === 'change-password.php' ? 'bg-[#DDBE7E] text-white' : 'text-[#D7E1EC] hover:bg-[#DDBE7E] hover:text-white'; ?>">Change Password</a>
                </div>
            </details>

            <?php
            /* menu-link renderer: keeps every item visually identical */
            $frLink = function (string $page, string $label) use ($basePath, $currentPage) {
                $active = $currentPage === $page;
                $cls = $active ? 'bg-[#DDBE7E] text-white' : 'text-[#D7E1EC] hover:bg-[#DDBE7E] hover:text-white';
                echo '<a href="' . e(franchisePageUrl($basePath, $page)) . '" class="block rounded px-3 py-2 text-[12px] ' . $cls . '">' . e($label) . '</a>';
            };
            $frGroupSummary = function (string $icon, string $title, bool $open) {
                echo '<summary class="list-none cursor-pointer flex items-center justify-between px-3 h-[40px] rounded-md text-[13px] ' . ($open ? 'bg-[#1A3F66]' : 'hover:bg-[#1A3F66]') . '">'
                   . '<span class="flex items-center gap-3"><i data-lucide="' . e($icon) . '" class="w-4 h-4 shrink-0"></i><span>' . e($title) . '</span></span>'
                   . '<i data-lucide="chevron-right" class="w-4 h-4 shrink-0 transition-transform group-open:rotate-90"></i></summary>';
            };
            ?>

            <!-- Inventory & Vendors -->
            <details class="group mt-1 rounded-md" <?php echo $invOpen ? 'open' : ''; ?>>
                <?php $frGroupSummary('boxes', 'Inventory & Vendors', $invOpen); ?>
                <div class="mt-1 pl-2 pr-1 space-y-1">
                    <a href="<?php echo e(franchisePageUrl($basePath, 'inventory.php')); ?>" class="block rounded px-3 py-2 text-[12px] <?php echo ($currentPage === 'inventory.php' || $currentPage === 'product-unit.php') ? 'bg-[#DDBE7E] text-white' : 'text-[#D7E1EC] hover:bg-[#DDBE7E] hover:text-white'; ?>">Product Master</a>
                    <?php
                    $frLink('stock-entry.php', 'Stock Entry');
                    $frLink('vendors.php', 'Vendors / Suppliers');
                    ?>
                </div>
            </details>

            <!-- Sales & Customers -->
            <details class="group mt-1 rounded-md" <?php echo $salesOpen ? 'open' : ''; ?>>
                <?php $frGroupSummary('shopping-cart', 'Sales & Customers', $salesOpen); ?>
                <div class="mt-1 pl-2 pr-1 space-y-1">
                    <?php
                    $frLink('pos.php', 'Billing POS');
                    $frLink('customers.php', 'Customers / Buyers');
                    ?>
                </div>
            </details>

            <!-- Reports -->
            <details class="group mt-1 rounded-md" <?php echo $reportOpen ? 'open' : ''; ?>>
                <?php $frGroupSummary('bar-chart-3', 'Reports', $reportOpen); ?>
                <div class="mt-1 pl-2 pr-1 space-y-1">
                    <?php
                    $frLink('sales.php', 'Sales Report');
                    $frLink('turnover.php', 'Turnover Report');
                    $frLink('stock-report.php', 'Stock Report');
                    $frLink('stock-reduce-report.php', 'Stock Reduce Report');
                    $frLink('commission-report.php', 'Commission Report');
                    $frLink('cashback.php', 'Cashback Report');
                    $frLink('sponsor-report.php', 'Sponsor Franchise Report');
                    ?>
                </div>
            </details>

            <?php /* Product Requests — commented out for now (2026-09-11)
            <details class="group mt-1 rounded-md" <?php echo $reqOpen ? 'open' : ''; ?>>
                <?php $frGroupSummary('clipboard-list', 'Product Requests', $reqOpen); ?>
                <div class="mt-1 pl-2 pr-1 space-y-1">
                    <?php
                    $frLink('product-request.php', 'Product Request');
                    $frLink('send-product-request.php', 'Send Product Request');
                    if ($isStateFranchise || $isCityFranchise) $frLink('product-request-city-mobile.php', 'Product Request (City/Mobile)');
                    // Product Stock — commented out for now (2026-09-11)
                    // $frLink('product-stock-request.php', 'Product Stock');
                    ?>

                </div>
            </details>
            */ ?>

            <?php /* Member Purchase — commented out for now (2026-09-11)
            <details class="group mt-1 rounded-md" <?php echo $memberpurchase ? 'open' : ''; ?>>
                <summary class="list-none cursor-pointer flex items-center justify-between px-3 h-[40px] rounded-md text-[13px] <?php echo $memberpurchase ? 'bg-[#1A3F66]' : 'hover:bg-[#1A3F66]'; ?>">
                    <span class="flex items-center gap-3">
                        <i data-lucide="package" class="w-4 h-4 shrink-0"></i>
                        <span>Member Purchase</span>
                    </span>
                    <i data-lucide="chevron-right" class="w-4 h-4 shrink-0 transition-transform group-open:rotate-90"></i>
                </summary>

                <div class="mt-1 pl-2 pr-1 space-y-1">
                    <?php if ($isStateFranchise): ?>
                    <?php endif; ?>
                    <a href="<?php echo e(franchisePageUrl($basePath, 'member-activation.php')); ?>" class="block rounded px-3 py-2 text-[12px] <?php echo $currentPage === 'member-activation.php' ? 'bg-[#DDBE7E] text-white' : 'text-[#D7E1EC] hover:bg-[#DDBE7E] hover:text-white'; ?>">Member Activation</a>
                    <a href="<?php echo e(franchisePageUrl($basePath, 'member-order.php')); ?>" class="block rounded px-3 py-2 text-[12px] <?php echo $currentPage === 'member-order.php' ? 'bg-[#DDBE7E] text-white' : 'text-[#D7E1EC] hover:bg-[#DDBE7E] hover:text-white'; ?>">Repurchase Order</a>
                    <a href="<?php echo e(franchisePageUrl($basePath, 'member_order_list.php')); ?>" class="block rounded px-3 py-2 text-[12px] <?php echo $currentPage === 'member_order_list.php' ? 'bg-[#DDBE7E] text-white' : 'text-[#D7E1EC] hover:bg-[#DDBE7E] hover:text-white'; ?>">Member Order List</a>



                </div>
            </details>
            */ ?>

            <!-- Join Member List — commented out for now (2026-09-11)
            <a href="join-member-list.php" class="mt-1 flex items-center gap-3 px-3 h-[40px] rounded-md text-[13px] font-medium transition">
    <i data-lucide="users" class="w-5 h-5"></i>
    <span>Join Member List</span>
    -->
    <!-- <a href="<?php echo e(franchisePageUrl($basePath, 'stock-transfer-commission.php')); ?>" class="mt-1 flex items-center gap-3 px-3 h-[40px] rounded-md text-[13px] font-medium transition <?php echo $outboxActive ? 'bg-[#DDBE7E] text-white' : 'text-white hover:bg-[#1A3F66]'; ?>">
            <i data-lucide="send" class="w-4 h-4 shrink-0"></i>
            <span>Stock transfer commission</span>
        </a> -->
        
        <details class="group mt-1 rounded-md" <?php echo $messageOpen ? 'open' : ''; ?>>
            <?php $frGroupSummary('life-buoy', 'Support', $messageOpen); ?>
            <div class="mt-1 pl-2 pr-1 space-y-1">
                <?php
                $frLink('compose.php', 'Compose');
                $frLink('inbox.php', 'Inbox');
                $frLink('outbox.php', 'Outbox');
                ?>
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
                <?php echo e($userInitials); ?>
            </div>

            <div class="leading-tight">
                <p class="text-[13px] font-medium text-white"><?php echo e($loggedUserName); ?></p>
               <p class="text-[10px] text-[#7E95AE] mt-1">
    <?php echo e($_SESSION['franchise_code'] ?? ''); ?>
</p>
            </div>
        </div>

        <a href="<?php echo e(franchisePageUrl($basePath, 'logout.php')); ?>" class="text-[#9CB2C9]">
            <i data-lucide="log-out" class="w-4 h-4"></i>
        </a>
    </div>
</div>

<!-- top accent line — plain silver divider, same as the sidebar's own header/footer border lines, spans full width so it lines up flush across the sidebar and the navbar -->
<div class="fixed left-0 right-0 z-[65] pointer-events-none" style="top:82px; height:1px; background:#C7CDD6;"></div>

<div id="layoutOverlay" class="layout-overlay" onclick="closeSidebar()"></div>

<script>
    function openSidebar() {
        const sidebar = document.getElementById('layoutSidebar');
        const overlay = document.getElementById('layoutOverlay');
        if (sidebar) sidebar.classList.add('sidebar-open');
        if (overlay) overlay.classList.add('sidebar-open');
        document.body.classList.add('sidebar-lock');
    }

    function closeSidebar() {
        const sidebar = document.getElementById('layoutSidebar');
        const overlay = document.getElementById('layoutOverlay');
        if (sidebar) sidebar.classList.remove('sidebar-open');
        if (overlay) overlay.classList.remove('sidebar-open');
        document.body.classList.remove('sidebar-lock');
    }

    window.addEventListener('resize', function () {
        if (window.innerWidth >= 1024) {
            const sidebar = document.getElementById('layoutSidebar');
            const overlay = document.getElementById('layoutOverlay');
            if (sidebar) sidebar.classList.add('sidebar-open');
            if (overlay) overlay.classList.remove('sidebar-open');
            document.body.classList.remove('sidebar-lock');
        }
    });
</script>
