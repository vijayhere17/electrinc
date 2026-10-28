<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!function_exists('adminGetPublicPages')) {
    function adminGetPublicPages(): array
    {
        return [
            'index.php',
            'login.php',
            'logout.php',
            'signup.php',
            'admin-member-login.php',
            'member-login-access.php',
            'generate-member-access.php'
        ];
    }
}

if (!function_exists('adminGetSystemExcludedPages')) {
    function adminGetSystemExcludedPages(): array
    {
        return [
            '.',
            '..',
            'assets',
            'config',
            'includes',
            'uploads',
            'index.php',
            'login.php',
            'logout.php',
            'signup.php',
            'admin-member-login.php',
            'member-login-access.php',
            'generate-member-access.php'
        ];
    }
}

if (!function_exists('adminFormatTitleFromFile')) {
    function adminFormatTitleFromFile(string $fileName): string
    {
        $base = str_replace(['.php', '-', '_'], ['', ' ', ' '], strtolower($fileName));
        $base = trim(preg_replace('/\s+/', ' ', $base) ?? $base);
        return ucwords($base ?: 'Untitled');
    }
}

if (!function_exists('adminGetPageCatalog')) {
    function adminGetPageCatalog(): array
    {
        return [
            'dashboard.php' => ['title' => 'Dashboard', 'section' => 'main', 'icon' => 'layout-dashboard', 'sort_order' => 10],

            'change_password.php' => ['title' => 'Change Password', 'section' => 'admin', 'icon' => 'lock-keyhole', 'sort_order' => 20],
            'member-list.php' => ['title' => 'All Members', 'section' => 'admin', 'icon' => 'users', 'sort_order' => 21],
            'member-admin-access.php' => ['title' => 'Member Admin Access', 'section' => 'admin', 'icon' => 'user-cog', 'sort_order' => 22],
            'product-master.php' => ['title' => 'Product Master', 'section' => 'admin', 'icon' => 'package-plus', 'sort_order' => 23],
            'product-list.php' => ['title' => 'Product List', 'section' => 'admin', 'icon' => 'package', 'sort_order' => 24],

            'repurchase_order_request.php' => ['title' => 'Repurchase Order Request', 'section' => 'orders', 'icon' => 'clipboard-list', 'sort_order' => 30],
            'purchase_dispatch_view.php' => ['title' => 'Purchase Delivery Dispatch', 'section' => 'orders', 'icon' => 'package-check', 'sort_order' => 31],
            'repurchase_dispatch_view.php' => ['title' => 'Repurchase Delivery Dispatch', 'section' => 'orders', 'icon' => 'truck', 'sort_order' => 32],
            'club_achiever.php' => ['title' => 'Club Achiever', 'section' => 'orders', 'icon' => 'trophy', 'sort_order' => 33],

            'associate_report.php' => ['title' => 'Associate Report', 'section' => 'associate', 'icon' => 'users', 'sort_order' => 40],
            'shipping-details.php' => ['title' => 'Shipping Details', 'section' => 'associate', 'icon' => 'truck', 'sort_order' => 41],
            'bank-info.php' => ['title' => 'Bank Info', 'section' => 'associate', 'icon' => 'landmark', 'sort_order' => 42],
            'view_network_tree.php' => ['title' => 'View Network Tree', 'section' => 'associate', 'icon' => 'network', 'sort_order' => 42],
            'upline_view.php' => ['title' => 'Upline View', 'section' => 'associate', 'icon' => 'git-branch-plus', 'sort_order' => 43],

            'process_payout.php' => ['title' => 'Process Payout', 'section' => 'accounts', 'icon' => 'calculator', 'sort_order' => 50],
            'payout_closing_list.php' => ['title' => 'Payout Closing List', 'section' => 'accounts', 'icon' => 'receipt-text', 'sort_order' => 51],
            'cheque_request_all.php' => ['title' => 'Cheque Request All', 'section' => 'accounts', 'icon' => 'scroll-text', 'sort_order' => 52],
            'tds_report.php' => ['title' => 'TDS Report', 'section' => 'accounts', 'icon' => 'file-chart-column', 'sort_order' => 53],

            'wallet-status.php' => ['title' => 'Wallet Status', 'section' => 'wallet', 'icon' => 'wallet', 'sort_order' => 60],
            'wallet-report.php' => ['title' => 'Wallet Report', 'section' => 'wallet', 'icon' => 'file-text', 'sort_order' => 61],
            'wallet-request.php' => ['title' => 'Wallet Request', 'section' => 'wallet', 'icon' => 'badge-indian-rupee', 'sort_order' => 62],

            'compose.php' => ['title' => 'Compose', 'section' => 'messages', 'icon' => 'messages-square', 'sort_order' => 70],
            'inbox.php' => ['title' => 'Inbox', 'section' => 'messages', 'icon' => 'inbox', 'sort_order' => 71],
            'outbox.php' => ['title' => 'Outbox', 'section' => 'messages', 'icon' => 'send', 'sort_order' => 72],
        ];
    }
}

if (!function_exists('adminGetSectionMeta')) {
    function adminGetSectionMeta(): array
    {
        return [
            'main' => ['title' => 'Main', 'style' => 'single'],
            'admin' => ['title' => 'Admin', 'style' => 'group'],
            'orders' => ['title' => 'Orders', 'style' => 'single'],
            'associate' => ['title' => 'Associate', 'style' => 'group'],
            'accounts' => ['title' => 'Accounts', 'style' => 'group'],
            'wallet' => ['title' => 'Wallet', 'style' => 'group'],
            'messages' => ['title' => 'Messages', 'style' => 'group'],
            'other' => ['title' => 'Other', 'style' => 'group'],
        ];
    }
}

if (!function_exists('adminEnsureAccessTables')) {
    function adminEnsureAccessTables(mysqli $conn): void
    {
        $conn->query("CREATE TABLE IF NOT EXISTS admin_panel_pages (
            id INT AUTO_INCREMENT PRIMARY KEY,
            page_slug VARCHAR(150) NOT NULL UNIQUE,
            file_name VARCHAR(150) NOT NULL UNIQUE,
            page_title VARCHAR(255) NOT NULL,
            section_key VARCHAR(100) NOT NULL DEFAULT 'other',
            icon VARCHAR(100) NOT NULL DEFAULT 'circle',
            sort_order INT NOT NULL DEFAULT 999,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $conn->query("CREATE TABLE IF NOT EXISTS member_admin_page_access (
            id INT AUTO_INCREMENT PRIMARY KEY,
            member_customer_id VARCHAR(100) NOT NULL,
            page_id INT NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_member_page (member_customer_id, page_id),
            KEY idx_member (member_customer_id),
            CONSTRAINT fk_member_admin_page_access_page
                FOREIGN KEY (page_id) REFERENCES admin_panel_pages(id)
                ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }
}

if (!function_exists('adminSyncPanelPages')) {
    function adminSyncPanelPages(mysqli $conn): void
    {
        adminEnsureAccessTables($conn);

        $catalog = adminGetPageCatalog();
        $excluded = adminGetSystemExcludedPages();
        $adminRoot = realpath(__DIR__ . '/..');

        if ($adminRoot === false) {
            return;
        }

        $detected = [];
        $entries = scandir($adminRoot);
        if (!is_array($entries)) {
            return;
        }

        foreach ($entries as $entry) {
            if (in_array($entry, $excluded, true)) {
                continue;
            }

            if (strtolower(pathinfo($entry, PATHINFO_EXTENSION)) !== 'php') {
                continue;
            }

            $meta = $catalog[$entry] ?? null;
            $title = $meta['title'] ?? adminFormatTitleFromFile($entry);
            $section = $meta['section'] ?? 'other';
            $icon = $meta['icon'] ?? 'circle';
            $sortOrder = isset($meta['sort_order']) ? (int)$meta['sort_order'] : 900;
            $slug = strtolower(str_replace('.php', '', $entry));

            $detected[] = $entry;

            $stmt = $conn->prepare("INSERT INTO admin_panel_pages
                (page_slug, file_name, page_title, section_key, icon, sort_order, is_active)
                VALUES (?, ?, ?, ?, ?, ?, 1)
                ON DUPLICATE KEY UPDATE
                page_title = VALUES(page_title),
                section_key = VALUES(section_key),
                icon = VALUES(icon),
                sort_order = VALUES(sort_order),
                is_active = 1");

            if ($stmt) {
                $stmt->bind_param('sssssi', $slug, $entry, $title, $section, $icon, $sortOrder);
                $stmt->execute();
                $stmt->close();
            }
        }

        $conn->query("UPDATE admin_panel_pages SET is_active = 0 WHERE is_active = 1");

        if (!empty($detected)) {
            $placeholders = implode(',', array_fill(0, count($detected), '?'));
            $types = str_repeat('s', count($detected));
            $stmt = $conn->prepare("UPDATE admin_panel_pages SET is_active = 1 WHERE file_name IN ($placeholders)");
            if ($stmt) {
                $stmt->bind_param($types, ...$detected);
                $stmt->execute();
                $stmt->close();
            }
        }
    }
}

if (!function_exists('adminLoadActivePages')) {
    function adminLoadActivePages(mysqli $conn): array
    {
        adminSyncPanelPages($conn);

        $rows = [];
        $result = $conn->query("SELECT id, page_slug, file_name, page_title, section_key, icon, sort_order
                                FROM admin_panel_pages
                                WHERE is_active = 1
                                ORDER BY sort_order ASC, page_title ASC");
        if ($result instanceof mysqli_result) {
            while ($row = $result->fetch_assoc()) {
                $rows[] = $row;
            }
        }

        return $rows;
    }
}

if (!function_exists('adminRefreshSessionPermissions')) {
    function adminRefreshSessionPermissions(mysqli $conn): void
    {
        $allPages = adminLoadActivePages($conn);
        $isSuperAdmin = !empty($_SESSION['is_super_admin']);
        $memberId = trim((string)($_SESSION['customer_id'] ?? $_SESSION['member_id'] ?? ''));

        $allowedByFile = [];

        if ($isSuperAdmin) {
            foreach ($allPages as $page) {
                $allowedByFile[$page['file_name']] = true;
            }
        } elseif ($memberId !== '') {
            $stmt = $conn->prepare("SELECT p.file_name
                                    FROM member_admin_page_access a
                                    INNER JOIN admin_panel_pages p ON p.id = a.page_id
                                    WHERE a.member_customer_id = ?
                                      AND p.is_active = 1");
            if ($stmt) {
                $stmt->bind_param('s', $memberId);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($result instanceof mysqli_result) {
                    while ($row = $result->fetch_assoc()) {
                        $allowedByFile[$row['file_name']] = true;
                    }
                }
                $stmt->close();
            }
        }

        $_SESSION['admin_allowed_pages'] = array_keys($allowedByFile);
        $_SESSION['admin_allowed_pages_map'] = $allowedByFile;
    }
}

if (!function_exists('adminCanAccessCurrentPage')) {
    function adminCanAccessCurrentPage(): bool
    {
        $currentPage = basename($_SERVER['PHP_SELF'] ?? '');

        if (in_array($currentPage, adminGetPublicPages(), true)) {
            return true;
        }

        if (!isset($_SESSION['member_id']) && !isset($_SESSION['customer_id'])) {
            return false;
        }

        if (!empty($_SESSION['is_super_admin'])) {
            return true;
        }

        $allowed = $_SESSION['admin_allowed_pages_map'] ?? [];
        if (!is_array($allowed)) {
            return false;
        }

        if ($currentPage === 'dashboard.php') {
            return true;
        }

        return !empty($allowed[$currentPage]);
    }
}

if (!function_exists('adminRequireAccessOrRedirect')) {
    function adminRequireAccessOrRedirect(): void
    {
        $currentPage = basename($_SERVER['PHP_SELF'] ?? '');

        if (in_array($currentPage, adminGetPublicPages(), true)) {
            return;
        }

        if (!isset($_SESSION['member_id']) && !isset($_SESSION['customer_id'])) {
            header('Location: login.php');
            exit;
        }

        if (!adminCanAccessCurrentPage()) {
            header('Location: dashboard.php?access=denied');
            exit;
        }
    }
}

if (!function_exists('adminBuildSidebarData')) {
    function adminBuildSidebarData(mysqli $conn): array
    {
        $sectionMeta = adminGetSectionMeta();
        $activePages = adminLoadActivePages($conn);
        $allowedMap = $_SESSION['admin_allowed_pages_map'] ?? [];
        $isSuperAdmin = !empty($_SESSION['is_super_admin']);

        $sections = [];
        foreach ($activePages as $page) {
            $fileName = (string)$page['file_name'];

            if (!$isSuperAdmin) {
                if ($fileName === 'dashboard.php') {
                    // always visible
                } elseif (empty($allowedMap[$fileName])) {
                    continue;
                }
            }

            $sectionKey = (string)($page['section_key'] ?? 'other');
            if (!isset($sectionMeta[$sectionKey])) {
                $sectionKey = 'other';
            }

            if (!isset($sections[$sectionKey])) {
                $sections[$sectionKey] = [
                    'key' => $sectionKey,
                    'title' => $sectionMeta[$sectionKey]['title'],
                    'style' => $sectionMeta[$sectionKey]['style'],
                    'items' => []
                ];
            }

            $sections[$sectionKey]['items'][] = [
                'id' => (int)$page['id'],
                'file_name' => $fileName,
                'title' => (string)$page['page_title'],
                'icon' => (string)$page['icon'],
                'sort_order' => (int)$page['sort_order']
            ];
        }

        return array_values($sections);
    }
}
