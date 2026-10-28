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
    'direct-sponsor-income.php',
    'binary-income.php',
    'level-income.php',
    'auto-pool-income.php',
    'performance-bonus.php',
    'team-performance-bonus.php',
    'income-pool.php',
];

$walletPayoutPages = [
    'withdrawal-requests.php',
    'wallet-transfers.php',
    'franchies-payout-report.php',
    'member-payout-report.php',
    'tds_report.php',
];

$franchiseReportPages = [
    'franchise-sales-report.php',
    'franchise-directory.php',
];

$repurchasePages = [
    'process_payout.php',
    'payout_closing_list.php',
    'cheque_request_all.php',
    'tds_report.php'
];

$messagePages = [
    'compose.php',
    'inbox.php',
    'outbox.php',
    'inbox-thread-details.php'
];

$franchiesPages = [
    'franchies_reg.php',
    'franchies_product_request.php',
    'franchies_product_add_list.php',
    'franchise-kyc-details.php', 
    'city-franchies.php',
    'city-franchies-kyc.php',
    'mobile-franchies-reg.php',
    'mobile-franchies-kyc.php',
    'franchies-messages.php',
    
    
];
$stockistPages = [
    'franchies_product_add_list.php',
    'product-list.php',
    'franchies_product_stock_report.php',
    'stock-reduction-report.php',
];





$dashboardActive    = $currentPage === 'dashboard.php';
$orderStatusActive  = $currentPage === 'order_status.php';
$dispatchViewActive = $currentPage === 'dispatch_view.php';
$profileOpen        = isActivePage($profilePages, $currentPage);
$networkOpen        = isActivePage($networkPages, $currentPage);
$incomeStatusOpen   = isActivePage($incomeStatusPages, $currentPage);
$incomeReportOpen   = isActivePage($incomeReportPages, $currentPage);
$walletPayoutOpen   = isActivePage($walletPayoutPages, $currentPage);
$franchiseReportOpen = isActivePage($franchiseReportPages, $currentPage);
$repurchaseOpen     = isActivePage($repurchasePages, $currentPage);
$messageOpen        = isActivePage($messagePages, $currentPage);
$stockOpen        = isActivePage($stockistPages, $currentPage);
$franchiselistActive    = isActivePage($franchiesPages, $currentPage);
$franchiseproductrequestActive    = $currentPage === 'franchies_product_request.php';


$loggedUserName = $_SESSION['full_name'] ?? 'Member';
$loggedCustomerId = $_SESSION['customer_id'] ?? $_SESSION['member_id'] ?? 'ADM0001';

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

<link rel="stylesheet" href="<?php echo $basePath; ?>/assets/theme.css">

<div id="layoutSidebar" class="layout-sidebar fixed top-0 left-0 h-screen w-64 bg-[#103258] text-white flex flex-col border-r border-[#254A73]">

    <div class="px-5 py-5 flex flex-col items-center justify-center gap-1.5 shrink-0">
        <img
            src="<?php echo $basePath; ?>/assets/electrinc-logo.png"
            class="h-7 w-auto max-w-[80%] object-contain"
            alt="Electrinc"
        >
        <p class="text-[10px] font-bold tracking-[.25em] uppercase" style="color:rgba(255,255,255,.55)">Admin Panel</p>
    </div>

    <div class="flex-1 px-2 py-3 overflow-y-auto">

        <a href="<?php echo $basePath; ?>/dashboard.php"
           class="flex items-center gap-3 px-3 h-[40px] rounded-md text-[13px] font-medium transition <?php echo $dashboardActive ? 'bg-[#DDBE7E] text-white' : 'text-white hover:bg-[#1A3F66]'; ?>">
            <i data-lucide="layout-dashboard" class="w-4 h-4 shrink-0"></i>
            <span>Dashboard</span>
        </a>

        <details class="group mt-1 rounded-md" <?php echo $profileOpen ? 'open' : ''; ?>>
            <summary class="list-none cursor-pointer flex items-center justify-between px-3 h-[40px] rounded-md text-[13px] <?php echo $profileOpen ? 'bg-[#1A3F66]' : 'hover:bg-[#1A3F66]'; ?>">
                <span class="flex items-center gap-3">
                    <i data-lucide="shield-user" class="w-4 h-4 shrink-0"></i>
                    <span>Admin</span>
                </span>
                <i data-lucide="chevron-right" class="w-4 h-4 shrink-0 transition-transform group-open:rotate-90"></i>
            </summary>

            <div class="mt-1 pl-2 pr-1 space-y-1">
                <a href="<?php echo $basePath; ?>/change_password.php" class="block rounded px-3 py-2 text-[12px] <?php echo $currentPage === 'change_password.php' ? 'bg-[#DDBE7E] text-white' : 'text-[#D7E1EC] hover:bg-[#DDBE7E] hover:text-white'; ?>">Change Password</a>
                
                <!-- <a href="<?php echo $basePath; ?>/product-master.php" class="block rounded px-3 py-2 text-[12px] <?php echo $currentPage === 'product-master.php' ? 'bg-[#DDBE7E] text-white' : 'text-[#D7E1EC] hover:bg-[#DDBE7E] hover:text-white'; ?>">Product Master</a> -->
                <!-- <a href="<?php echo $basePath; ?>/product-list.php" class="block rounded px-3 py-2 text-[12px] <?php echo $currentPage === 'product-list.php' ? 'bg-[#DDBE7E] text-white' : 'text-[#D7E1EC] hover:bg-[#DDBE7E] hover:text-white'; ?>">Product List</a> -->
            </div>
        </details>

        <!-- <details class="group mt-1 rounded-md" <?php echo $networkOpen ? 'open' : ''; ?>>
            <summary class="list-none cursor-pointer flex items-center justify-between px-3 h-[40px] rounded-md text-[13px] <?php echo $networkOpen ? 'bg-[#1A3F66]' : 'hover:bg-[#1A3F66]'; ?>">
                <span class="flex items-center gap-3">
                    <i data-lucide="wallet-cards" class="w-4 h-4 shrink-0"></i>
                    <span>Wallet</span>
                </span>
                <i data-lucide="chevron-right" class="w-4 h-4 shrink-0 transition-transform group-open:rotate-90"></i>
            </summary>

            <div class="mt-1 pl-2 pr-1 space-y-1">
                 <a href="<?php echo $basePath; ?>/wallet-status.php" class="block rounded px-3 py-2 text-[12px] <?php echo $currentPage === 'wallet-status.php' ? 'bg-[#DDBE7E] text-white' : 'text-[#D7E1EC] hover:bg-[#DDBE7E] hover:text-white'; ?>">Wallet Status</a>                
                 <a href="<?php echo $basePath; ?>/wallet-report.php" class="block rounded px-3 py-2 text-[12px] <?php echo $currentPage === 'wallet-report.php' ? 'bg-[#DDBE7E] text-white' : 'text-[#D7E1EC] hover:bg-[#DDBE7E] hover:text-white'; ?>">Wallet Report</a>               
                 <a href="<?php echo $basePath; ?>/wallet-request.php" class="block rounded px-3 py-2 text-[12px] <?php echo $currentPage === 'wallet-request.php' ? 'bg-[#DDBE7E] text-white' : 'text-[#D7E1EC] hover:bg-[#DDBE7E] hover:text-white'; ?>">Wallet Request</a>           
            </div>
        </details> -->

        <!--<a href="<?php echo $basePath; ?>/repurchase_order_request.php"-->
        <!--   class="flex items-center gap-3 px-3 h-[40px] rounded-md text-[13px] font-medium transition text-white hover:bg-[#1A3F66]">-->
        <!--    <i data-lucide="clipboard-list" class="w-4 h-4 shrink-0"></i>-->
        <!--    <span>Repurchase Order Request</span>-->
        <!--</a>-->

        <details class="group mt-1 rounded-md" <?php echo $incomeStatusOpen ? 'open' : ''; ?>>
            <summary class="list-none cursor-pointer flex items-center justify-between px-3 h-[40px] rounded-md text-[13px] <?php echo $incomeStatusOpen ? 'bg-[#1A3F66]' : 'hover:bg-[#1A3F66]'; ?>">
                <span class="flex items-center gap-3">
                    <i data-lucide="users" class="w-4 h-4 shrink-0"></i>
                    <span>Associate</span>
                </span>
                <i data-lucide="chevron-right" class="w-4 h-4 shrink-0 transition-transform group-open:rotate-90"></i>
            </summary>

            <div class="mt-1 pl-2 pr-1 space-y-1">
                <a href="<?php echo $basePath; ?>/associate_report.php" class="block rounded px-3 py-2 text-[12px] <?php echo $currentPage === 'associate_report.php' ? 'bg-[#DDBE7E] text-white' : 'text-[#D7E1EC] hover:bg-[#DDBE7E] hover:text-white'; ?>">Associate Report</a>
                <a href="<?php echo $basePath; ?>/activation-requests.php" 
   class="block rounded px-3 py-2 text-[12px] 
   <?php echo $currentPage === 'activation-requests.php' 
       ? 'bg-[#DDBE7E] text-white' 
       : 'text-[#D7E1EC] hover:bg-[#DDBE7E] hover:text-white'; ?>">
    Activation Requests
</a>
                <!-- Shipping Details — commented out for now (2026-09-12) -->
                <!-- <a href="<?php echo $basePath; ?>/shipping-details.php" class="block rounded px-3 py-2 text-[12px] <?php echo $currentPage === 'shipping-details.php' ? 'bg-[#DDBE7E] text-white' : 'text-[#D7E1EC] hover:bg-[#DDBE7E] hover:text-white'; ?>">Shipping Details</a> -->
                <a href="<?php echo $basePath; ?>/bank-info.php" class="block rounded px-3 py-2 text-[12px] <?php echo $currentPage === 'bank-info.php' ? 'bg-[#DDBE7E] text-white' : 'text-[#D7E1EC] hover:bg-[#DDBE7E] hover:text-white'; ?>">Bank Info</a>
                
                <a href="<?php echo $basePath; ?>/activation_id.php" class="block rounded px-3 py-2 text-[12px] <?php echo $currentPage === 'activation_id.php' ? 'bg-[#DDBE7E] text-white' : 'text-[#D7E1EC] hover:bg-[#DDBE7E] hover:text-white'; ?>">Activation ID Block</a>
                
                <a href="<?php echo $basePath; ?>/reset-member-password.php"
   class="block rounded px-3 py-2 text-[12px]
   <?php echo $currentPage === 'reset-member-password.php'
       ? 'bg-[#DDBE7E] text-white'
       : 'text-[#D7E1EC] hover:bg-[#DDBE7E] hover:text-white'; ?>">
    Reset Member Password
</a>

                <!-- <a href="<?php echo $basePath; ?>/upline_view.php" class="block rounded px-3 py-2 text-[12px] <?php echo $currentPage === 'upline_view.php' ? 'bg-[#DDBE7E] text-white' : 'text-[#D7E1EC] hover:bg-[#DDBE7E] hover:text-white'; ?>">Upline View</a> -->
            </div>
        </details>
        
        <details class="group mt-1 rounded-md">
            <summary class="list-none cursor-pointer flex items-center justify-between px-3 h-[40px] rounded-md text-[13px] hover:bg-[#1A3F66]">
                <span class="flex items-center gap-3">
                    <i data-lucide="building-2" class="w-4 h-4 shrink-0"></i>
                    <span>Franchies </span>
                </span>
                <i data-lucide="chevron-right" class="w-4 h-4 shrink-0 transition-transform group-open:rotate-90"></i>
            </summary>


            <div class="mt-1 pl-2 pr-1 space-y-1">
                <a href="<?php echo $basePath; ?>/franchies_reg.php" class="block rounded px-3 py-2 text-[12px] <?php echo $currentPage === 'franchies_reg.php' ? 'bg-[#DDBE7E] text-white' : 'text-[#D7E1EC] hover:bg-[#DDBE7E] hover:text-white'; ?>"> State Franchies Registration</a>
                <a href="<?php echo $basePath; ?>/city-franchies.php" class="block rounded px-3 py-2 text-[12px] <?php echo $currentPage === 'city-franchies.php' ? 'bg-[#DDBE7E] text-white' : 'text-[#D7E1EC] hover:bg-[#DDBE7E] hover:text-white'; ?>">City Franchies Registration</a>
                <a href="<?php echo $basePath; ?>/mobile-franchies-reg.php" class="block rounded px-3 py-2 text-[12px] <?php echo $currentPage === 'mobile-franchies-reg.php' ? 'bg-[#DDBE7E] text-white' : 'text-[#D7E1EC] hover:bg-[#DDBE7E] hover:text-white'; ?>">Mobile Franchies Registration</a>

                <a href="<?php echo $basePath; ?>/franchies_product_request.php" class="block rounded px-3 py-2 text-[12px] <?php echo $currentPage === 'franchies_product_request.php' ? 'bg-[#DDBE7E] text-white' : 'text-[#D7E1EC] hover:bg-[#DDBE7E] hover:text-white'; ?>">Franchies product request</a>
                <!-- <a href="<?php echo $basePath; ?>/franchies_product_add_list.php" class="block rounded px-3 py-2 text-[12px] <?php echo $currentPage === 'franchies_product_add_list.php' ? 'bg-[#DDBE7E] text-white' : 'text-[#D7E1EC] hover:bg-[#DDBE7E] hover:text-white'; ?>">Franchies product add list</a> -->
                <a href="<?php echo $basePath; ?>/franchise-kyc-details.php" class="block rounded px-3 py-2 text-[12px] <?php echo $currentPage === 'franchise-kyc-details.php' ? 'bg-[#DDBE7E] text-white' : 'text-[#D7E1EC] hover:bg-[#DDBE7E] hover:text-white'; ?>">Franchise KYC</a>
                <!--<a href="<?php echo $basePath; ?>/city-franchies-kyc.php" class="block rounded px-3 py-2 text-[12px] <?php echo $currentPage === 'city-franchies-kyc.php' ? 'bg-[#DDBE7E] text-white' : 'text-[#D7E1EC] hover:bg-[#DDBE7E] hover:text-white'; ?>">City Franchies KYC</a>-->
                <!--<a href="<?php echo $basePath; ?>/mobile-franchies-kyc.php" class="block rounded px-3 py-2 text-[12px] <?php echo $currentPage === 'mobile-franchies-kyc.php' ? 'bg-[#DDBE7E] text-white' : 'text-[#D7E1EC] hover:bg-[#DDBE7E] hover:text-white'; ?>">Mobile Franchies KYC</a>-->
                <a href="<?php echo $basePath; ?>/franchise-messages.php" class="block rounded px-3 py-2 text-[12px] <?php echo $currentPage === 'franchies-messages.php' ? 'bg-[#DDBE7E] text-white' : 'text-[#D7E1EC] hover:bg-[#DDBE7E] hover:text-white'; ?>">Franchise Messages</a>
                <a href="<?php echo $basePath; ?>/franchise-rules.php" class="block rounded px-3 py-2 text-[12px] <?php echo $currentPage === 'franchise-rules.php' ? 'bg-[#DDBE7E] text-white' : 'text-[#D7E1EC] hover:bg-[#DDBE7E] hover:text-white'; ?>">Franchise Rules &amp; Models</a>




            </div>
        </details>

        <!-- <a href="<?php echo $basePath; ?>/purchase_dispatch_view.php"
           class="flex items-center gap-3 px-3 h-[40px] rounded-md text-[13px] font-medium transition text-white hover:bg-[#1A3F66]">
            <i data-lucide="package-check" class="w-4 h-4 shrink-0"></i>
            <span>Purchase Delivery Dispatch</span>
        </a>

        <a href="<?php echo $basePath; ?>/repurchase_dispatch_view.php"
           class="flex items-center gap-3 px-3 h-[40px] rounded-md text-[13px] font-medium transition text-white hover:bg-[#1A3F66]">
            <i data-lucide="truck" class="w-4 h-4 shrink-0"></i>
            <span>Repurchase Delivery Dispatch</span>
        </a> -->

       <!-- Achievers — commented out for now (2026-09-12)
       <details class="group mt-1 rounded-md">
    <summary class="list-none cursor-pointer flex items-center justify-between px-3 h-[40px] rounded-md text-[13px] hover:bg-[#1A3F66]">
        <span class="flex items-center gap-3">
            <i data-lucide="trophy" class="w-4 h-4 shrink-0"></i>
            <span>Achievers</span>
        </span>
        <i data-lucide="chevron-right" class="w-4 h-4 shrink-0 transition-transform group-open:rotate-90"></i>
    </summary>

    <div class="mt-1 pl-2 pr-1 space-y-1">

        <a href="<?php echo $basePath; ?>/club_achiever.php"
           class="block rounded px-3 py-2 text-[12px] <?php echo $currentPage === 'club_achiever.php' ? 'bg-[#DDBE7E] text-white' : 'text-[#D7E1EC] hover:bg-[#DDBE7E] hover:text-white'; ?>">
            Club Achiever
        </a>

        <a href="<?php echo $basePath; ?>/reward-achievers.php"
           class="block rounded px-3 py-2 text-[12px] <?php echo $currentPage === 'reward-achievers.php' ? 'bg-[#DDBE7E] text-white' : 'text-[#D7E1EC] hover:bg-[#DDBE7E] hover:text-white'; ?>">
            Reward Achiever
        </a>

    </div>
</details>
-->


<a href="<?php echo $basePath; ?>/franchise-list.php"
   class="flex items-center gap-3 px-3 h-[40px] rounded-md text-[13px] font-medium transition text-white hover:bg-[#1A3F66]">
    <i data-lucide="store" class="w-4 h-4 shrink-0"></i>
    <span>Franchise List</span>
</a>
       

        <!-- <details class="group mt-1 rounded-md" <?php echo $incomeReportOpen ? 'open' : ''; ?>>
            <summary class="list-none cursor-pointer flex items-center justify-between px-3 h-[40px] rounded-md text-[13px] <?php echo $incomeReportOpen ? 'bg-[#1A3F66]' : 'hover:bg-[#1A3F66]'; ?>">
                <span class="flex items-center gap-3">
                    <i data-lucide="badge-indian-rupee" class="w-4 h-4 shrink-0"></i>
                    <span>Ewallet</span>
                </span>
                <i data-lucide="chevron-right" class="w-4 h-4 shrink-0 transition-transform group-open:rotate-90"></i>
            </summary>

            <div class="mt-1 pl-2 pr-1 space-y-1">
                <a href="<?php echo $basePath; ?>/wallet-status.php" class="block rounded px-3 py-2 text-[12px] <?php echo $currentPage === 'wallet-status.php' ? 'bg-[#DDBE7E] text-white' : 'text-[#D7E1EC] hover:bg-[#DDBE7E] hover:text-white'; ?>">Ewallet Status</a>
                <a href="<?php echo $basePath; ?>/wallet-report.php" class="block rounded px-3 py-2 text-[12px] <?php echo $currentPage === 'wallet-report.php' ? 'bg-[#DDBE7E] text-white' : 'text-[#D7E1EC] hover:bg-[#DDBE7E] hover:text-white'; ?>">Ewallet Report</a>
            </div>
        </details> -->

        <a href="<?php echo $basePath; ?>/income-rules.php"
           class="flex items-center gap-3 px-3 h-[40px] rounded-md text-[13px] font-medium transition <?php echo $currentPage === 'income-rules.php' ? 'bg-[#DDBE7E] text-white' : 'text-white hover:bg-[#1A3F66]'; ?>">
            <i data-lucide="sliders-horizontal" class="w-4 h-4 shrink-0"></i>
            <span>Income &amp; Business Rules</span>
        </a>

        <details class="group mt-1 rounded-md" <?php echo $incomeReportOpen ? 'open' : ''; ?>>
            <summary class="list-none cursor-pointer flex items-center justify-between px-3 h-[40px] rounded-md text-[13px] <?php echo $incomeReportOpen ? 'bg-[#1A3F66]' : 'hover:bg-[#1A3F66]'; ?>">
                <span class="flex items-center gap-3">
                    <i data-lucide="badge-indian-rupee" class="w-4 h-4 shrink-0"></i>
                    <span>Income Reports</span>
                </span>
                <i data-lucide="chevron-right" class="w-4 h-4 shrink-0 transition-transform group-open:rotate-90"></i>
            </summary>

            <div class="mt-1 pl-2 pr-1 space-y-1">
                <a href="<?php echo $basePath; ?>/direct-sponsor-income.php"
   class="block rounded px-3 py-2 text-[12px] <?php echo $currentPage === 'direct-sponsor-income.php' ? 'bg-[#DDBE7E] text-white' : 'text-[#D7E1EC] hover:bg-[#DDBE7E] hover:text-white'; ?>">
    Direct Sponsor Income
</a>
                <a href="<?php echo $basePath; ?>/binary-income.php"
   class="block rounded px-3 py-2 text-[12px] <?php echo $currentPage === 'binary-income.php' ? 'bg-[#DDBE7E] text-white' : 'text-[#D7E1EC] hover:bg-[#DDBE7E] hover:text-white'; ?>">
    Binary Income
</a>
                <a href="<?php echo $basePath; ?>/level-income.php"
   class="block rounded px-3 py-2 text-[12px] <?php echo $currentPage === 'level-income.php' ? 'bg-[#DDBE7E] text-white' : 'text-[#D7E1EC] hover:bg-[#DDBE7E] hover:text-white'; ?>">
    Level Income
</a>
                <a href="<?php echo $basePath; ?>/auto-pool-income.php"
   class="block rounded px-3 py-2 text-[12px] <?php echo $currentPage === 'auto-pool-income.php' ? 'bg-[#DDBE7E] text-white' : 'text-[#D7E1EC] hover:bg-[#DDBE7E] hover:text-white'; ?>">
    Auto Pool Income
</a>
                <a href="<?php echo $basePath; ?>/performance-bonus.php"
   class="block rounded px-3 py-2 text-[12px] <?php echo $currentPage === 'performance-bonus.php' ? 'bg-[#DDBE7E] text-white' : 'text-[#D7E1EC] hover:bg-[#DDBE7E] hover:text-white'; ?>">
    Performance Bonus
</a>
                <a href="<?php echo $basePath; ?>/team-performance-bonus.php"
   class="block rounded px-3 py-2 text-[12px] <?php echo $currentPage === 'team-performance-bonus.php' ? 'bg-[#DDBE7E] text-white' : 'text-[#D7E1EC] hover:bg-[#DDBE7E] hover:text-white'; ?>">
    Team Performance Bonus
</a>
                <a href="<?php echo $basePath; ?>/income-pool.php"
   class="block rounded px-3 py-2 text-[12px] <?php echo $currentPage === 'income-pool.php' ? 'bg-[#DDBE7E] text-white' : 'text-[#D7E1EC] hover:bg-[#DDBE7E] hover:text-white'; ?>">
    Income Distribution Pool
</a>
            </div>
        </details>

        <details class="group mt-1 rounded-md" <?php echo $walletPayoutOpen ? 'open' : ''; ?>>
            <summary class="list-none cursor-pointer flex items-center justify-between px-3 h-[40px] rounded-md text-[13px] <?php echo $walletPayoutOpen ? 'bg-[#1A3F66]' : 'hover:bg-[#1A3F66]'; ?>">
                <span class="flex items-center gap-3">
                    <i data-lucide="wallet" class="w-4 h-4 shrink-0"></i>
                    <span>Wallet &amp; Payouts</span>
                </span>
                <i data-lucide="chevron-right" class="w-4 h-4 shrink-0 transition-transform group-open:rotate-90"></i>
            </summary>

            <div class="mt-1 pl-2 pr-1 space-y-1">
                <a href="<?php echo $basePath; ?>/withdrawal-requests.php"
   class="block rounded px-3 py-2 text-[12px] <?php echo $currentPage === 'withdrawal-requests.php' ? 'bg-[#DDBE7E] text-white' : 'text-[#D7E1EC] hover:bg-[#DDBE7E] hover:text-white'; ?>">
    Withdrawal Requests
</a>
                <a href="<?php echo $basePath; ?>/wallet-transfers.php"
   class="block rounded px-3 py-2 text-[12px] <?php echo $currentPage === 'wallet-transfers.php' ? 'bg-[#DDBE7E] text-white' : 'text-[#D7E1EC] hover:bg-[#DDBE7E] hover:text-white'; ?>">
    P2P Wallet Transfers
</a>
<a href="<?php echo $basePath; ?>/franchies-payout-report.php"
   class="block rounded px-3 py-2 text-[12px] <?php echo $currentPage === 'franchies-payout-report.php' ? 'bg-[#DDBE7E] text-white' : 'text-[#D7E1EC] hover:bg-[#DDBE7E] hover:text-white'; ?>">
    Franchise Payout Report
</a>

<a href="<?php echo $basePath; ?>/member-payout-report.php"
   class="block rounded px-3 py-2 text-[12px] <?php echo $currentPage === 'member-payout-report.php' ? 'bg-[#DDBE7E] text-white' : 'text-[#D7E1EC] hover:bg-[#DDBE7E] hover:text-white'; ?>">
    Member Payout Report
</a>

<a href="<?php echo $basePath; ?>/tds_report.php" class="block rounded px-3 py-2 text-[12px] <?php echo $currentPage === 'tds_report.php' ? 'bg-[#DDBE7E] text-white' : 'text-[#D7E1EC] hover:bg-[#DDBE7E] hover:text-white'; ?>">TDS Report</a>
            </div>
        </details>

        <details class="group mt-1 rounded-md" <?php echo $franchiseReportOpen ? 'open' : ''; ?>>
            <summary class="list-none cursor-pointer flex items-center justify-between px-3 h-[40px] rounded-md text-[13px] <?php echo $franchiseReportOpen ? 'bg-[#1A3F66]' : 'hover:bg-[#1A3F66]'; ?>">
                <span class="flex items-center gap-3">
                    <i data-lucide="trending-up" class="w-4 h-4 shrink-0"></i>
                    <span>Franchise Reports</span>
                </span>
                <i data-lucide="chevron-right" class="w-4 h-4 shrink-0 transition-transform group-open:rotate-90"></i>
            </summary>

            <div class="mt-1 pl-2 pr-1 space-y-1">
                <a href="<?php echo $basePath; ?>/franchise-sales-report.php"
   class="block rounded px-3 py-2 text-[12px] <?php echo $currentPage === 'franchise-sales-report.php' ? 'bg-[#DDBE7E] text-white' : 'text-[#D7E1EC] hover:bg-[#DDBE7E] hover:text-white'; ?>">
    Sales &amp; Turnover Report
</a>
                <a href="<?php echo $basePath; ?>/franchise-directory.php"
   class="block rounded px-3 py-2 text-[12px] <?php echo $currentPage === 'franchise-directory.php' ? 'bg-[#DDBE7E] text-white' : 'text-[#D7E1EC] hover:bg-[#DDBE7E] hover:text-white'; ?>">
    Vendor &amp; Customer Directory
</a>
            </div>
        </details>
        <details class="group mt-1 rounded-md" <?php echo (isset($stockOpen) && $stockOpen) ? 'open' : ''; ?>>
            <summary class="list-none cursor-pointer flex items-center justify-between px-3 h-[40px] rounded-md text-[13px] <?php echo (isset($stockOpen) && $stockOpen) ? 'bg-[#1A3F66]' : 'hover:bg-[#1A3F66]'; ?>">
                <span class="flex items-center gap-3">
                    <i data-lucide="message-square" class="w-4 h-4 shrink-0"></i>
                    <span>Stock</span>
                </span>
                <i data-lucide="chevron-right" class="w-4 h-4 shrink-0 transition-transform group-open:rotate-90"></i>
            </summary>
            <div class="mt-1 pl-2 pr-1 space-y-1">
                <a href="<?php echo $basePath; ?>/franchies_product_add_list.php" class="block rounded px-3 py-2 text-[12px] <?php echo $currentPage === 'franchies_product_add_list.php' ? 'bg-[#DDBE7E] text-white' : 'text-[#D7E1EC] hover:bg-[#DDBE7E] hover:text-white'; ?>">Admin product add list</a>
                <a href="<?php echo $basePath; ?>/product-list.php" class="block rounded px-3 py-2 text-[12px] <?php echo $currentPage === 'product-list.php' ? 'bg-[#DDBE7E] text-white' : 'text-[#D7E1EC] hover:bg-[#DDBE7E] hover:text-white'; ?>">Admin Product List</a>
                <a href="<?php echo $basePath; ?>/franchies_product_stock_report.php" class="block rounded px-3 py-2 text-[12px] <?php echo $currentPage === 'franchies_product_stock_report.php' ? 'bg-[#DDBE7E] text-white' : 'text-[#D7E1EC] hover:bg-[#DDBE7E] hover:text-white'; ?>">Stock Report</a>
                <!-- <a href="<?php echo $basePath; ?>/franchies_product_add_list.php" class="block rounded px-3 py-2 text-[12px] <?php echo $currentPage === 'franchies_product_add_list.php' ? 'bg-[#DDBE7E] text-white' : 'text-[#D7E1EC] hover:bg-[#DDBE7E] hover:text-white'; ?>">Admin product add list</a> -->
                <!-- <a href="<?php echo $basePath; ?>/outbox.php" class="block rounded px-3 py-2 text-[12px] <?php echo $currentPage === 'outbox.php' ? 'bg-[#DDBE7E] text-white' : 'text-[#D7E1EC] hover:bg-[#DDBE7E] hover:text-white'; ?>">Outbox</a> -->
                
                <a href="<?php echo $basePath; ?>/stock-reduction-report.php" class="block rounded px-3 py-2 text-[12px] <?php echo $currentPage === 'stock-reduction-report.php' ? 'bg-[#DDBE7E] text-white' : 'text-[#D7E1EC] hover:bg-[#DDBE7E] hover:text-white'; ?>">Stock Reduction Report</a>
            </div>
        </details>

        <!-- Messages Section -->
        <details class="group mt-1 rounded-md" <?php echo $messageOpen ? 'open' : ''; ?>>
            <summary class="list-none cursor-pointer flex items-center justify-between px-3 h-[40px] rounded-md text-[13px] <?php echo $messageOpen ? 'bg-[#1A3F66]' : 'hover:bg-[#1A3F66]'; ?>">
                <span class="flex items-center gap-3">
                    <i data-lucide="message-square" class="w-4 h-4 shrink-0"></i>
                    <span>Messages</span>
                </span>
                <i data-lucide="chevron-right" class="w-4 h-4 shrink-0 transition-transform group-open:rotate-90"></i>
            </summary>

            <div class="mt-1 pl-2 pr-1 space-y-1">
                <a href="<?php echo $basePath; ?>/inbox.php" class="block rounded px-3 py-2 text-[12px] <?php echo $currentPage === 'inbox.php' ? 'bg-[#DDBE7E] text-white' : 'text-[#D7E1EC] hover:bg-[#DDBE7E] hover:text-white'; ?>">Inbox</a>
                <!-- <a href="<?php echo $basePath; ?>/compose.php" class="block rounded px-3 py-2 text-[12px] <?php echo $currentPage === 'compose.php' ? 'bg-[#DDBE7E] text-white' : 'text-[#D7E1EC] hover:bg-[#DDBE7E] hover:text-white'; ?>">Compose</a> -->
                <a href="<?php echo $basePath; ?>/outbox.php" class="block rounded px-3 py-2 text-[12px] <?php echo $currentPage === 'outbox.php' ? 'bg-[#DDBE7E] text-white' : 'text-[#D7E1EC] hover:bg-[#DDBE7E] hover:text-white'; ?>">Outbox</a>
            </div>
        </details>
        <!-- State Franchies Section -->
        

        <!--City Franchies Section -->
        <!-- <details class="group mt-1 rounded-md">
            <summary class="list-none cursor-pointer flex items-center justify-between px-3 h-[40px] rounded-md text-[13px] hover:bg-[#1A3F66]">
                <span class="flex items-center gap-3">
                    <i data-lucide="building-2" class="w-4 h-4 shrink-0"></i>
                    <span>City Franchies </span>
                </span>
                <i data-lucide="chevron-right" class="w-4 h-4 shrink-0 transition-transform group-open:rotate-90"></i>
            </summary>


            <div class="mt-1 pl-2 pr-1 space-y-1">
                <a href="<?php echo $basePath; ?>/city-franchies.php" class="block rounded px-3 py-2 text-[12px] <?php echo $currentPage === 'city-franchies.php' ? 'bg-[#DDBE7E] text-white' : 'text-[#D7E1EC] hover:bg-[#DDBE7E] hover:text-white'; ?>">City Franchies Registration</a>
                <a href="<?php echo $basePath; ?>/city-franchies-kyc.php" class="block rounded px-3 py-2 text-[12px] <?php echo $currentPage === 'city-franchies-kyc.php' ? 'bg-[#DDBE7E] text-white' : 'text-[#D7E1EC] hover:bg-[#DDBE7E] hover:text-white'; ?>">City Franchies KYC</a>
                <a href="<?php echo $basePath; ?>/franchies_product_request.php" class="block rounded px-3 py-2 text-[12px] <?php echo $currentPage === 'franchies_product_request.php' ? 'bg-[#DDBE7E] text-white' : 'text-[#D7E1EC] hover:bg-[#DDBE7E] hover:text-white'; ?>">Franchies product request</a>
                <a href="<?php echo $basePath; ?>/franchies_product_add_list.php" class="block rounded px-3 py-2 text-[12px] <?php echo $currentPage === 'franchies_product_add_list.php' ? 'bg-[#DDBE7E] text-white' : 'text-[#D7E1EC] hover:bg-[#DDBE7E] hover:text-white'; ?>">Franchies product add list</a>
                <a href="<?php echo $basePath; ?>/franchise-kyc-details.php" class="block rounded px-3 py-2 text-[12px] <?php echo $currentPage === 'franchise-kyc-details.php' ? 'bg-[#DDBE7E] text-white' : 'text-[#D7E1EC] hover:bg-[#DDBE7E] hover:text-white'; ?>">Franchise KYC</a>



            </div>
        </details> -->

         <!-- <a href="<?php echo $basePath; ?>/.php"
           class="flex items-center gap-3 px-3 h-[40px] rounded-md text-[13px] font-medium transition text-white hover:bg-[#1A3F66]">
            <i data-lucide="trophy" class="w-4 h-4 shrink-0"></i>
            <span>Mobile franchies</span>
        </a> -->
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

        <a href="<?php echo $basePath; ?>/logout.php" class="text-[#9CB2C9]">
            <i data-lucide="log-out" class="w-4 h-4"></i>
        </a>
    </div>
</div>

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
            closeSidebar();
        }
    });

    lucide.createIcons();
</script>