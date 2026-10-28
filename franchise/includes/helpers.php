<?php

if (!function_exists('e')) {
    function e($value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('tableExists')) {
    function tableExists(mysqli $conn, string $tableName): bool
    {
        $safeName = $conn->real_escape_string($tableName);
        $result = $conn->query("SHOW TABLES LIKE '{$safeName}'");
        return $result instanceof mysqli_result && $result->num_rows > 0;
    }
}

if (!function_exists('getTableColumns')) {
    function getTableColumns(mysqli $conn, string $tableName): array
    {
        $columns = [];
        $safeName = $conn->real_escape_string($tableName);
        $result = $conn->query("SHOW COLUMNS FROM `{$safeName}`");

        if ($result instanceof mysqli_result) {
            while ($row = $result->fetch_assoc()) {
                if (isset($row['Field'])) {
                    $columns[] = strtolower((string)$row['Field']);
                }
            }
        }

        return $columns;
    }
}

if (!function_exists('firstExistingColumn')) {
    function firstExistingColumn(array $columns, array $candidates): ?string
    {
        foreach ($candidates as $candidate) {
            if (in_array(strtolower($candidate), $columns, true)) {
                return strtolower($candidate);
            }
        }

        return null;
    }
}

if (!function_exists('getFranchiseAuthTable')) {
    function getFranchiseAuthTable(mysqli $conn): ?string
    {
        foreach (['franchies_holder', 'franchises', 'freanchies_page'] as $candidate) {
            if (tableExists($conn, $candidate)) {
                return $candidate;
            }
        }

        return null;
    }
}

if (!function_exists('getFranchiseRecordByLoginId')) {
    function getFranchiseRecordByLoginId(mysqli $conn, string $loginId): ?array
    {
        $table = getFranchiseAuthTable($conn);
        if (!$table) {
            return null;
        }

        $columns = getTableColumns($conn, $table);
        // include common misspellings/variants like 'franchies_id'
        $loginColumn = firstExistingColumn($columns, ['franchies_id', 'franchises_id', 'franchise_id', 'login_id', 'member_id', 'customer_id']);
        if (!$loginColumn) {
            return null;
        }

        $stmt = $conn->prepare("SELECT * FROM `{$table}` WHERE `{$loginColumn}` = ? LIMIT 1");
        if (!$stmt) {
            return null;
        }

        $stmt->bind_param('s', $loginId);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result ? $result->fetch_assoc() : null;
        $stmt->close();

        return $row ?: null;
    }
}

if (!function_exists('getFranchiseRecordById')) {
    function getFranchiseRecordById(mysqli $conn, int $id): ?array
    {
        $table = getFranchiseAuthTable($conn);
        if (!$table) {
            return null;
        }

        $stmt = $conn->prepare("SELECT * FROM `{$table}` WHERE id = ? LIMIT 1");
        if (!$stmt) {
            return null;
        }

        $stmt->bind_param('i', $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result ? $result->fetch_assoc() : null;
        $stmt->close();

        return $row ?: null;
    }
}

if (!function_exists('ensureFranchiseMessagesTable')) {
    function ensureFranchiseMessagesTable(mysqli $conn): void
    {
        $conn->query("\n            CREATE TABLE IF NOT EXISTS franchise_messages (\n                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,\n                franchise_id INT UNSIGNED NOT NULL,\n                folder VARCHAR(20) NOT NULL DEFAULT 'inbox',\n                from_name VARCHAR(150) NOT NULL,\n                to_name VARCHAR(150) DEFAULT NULL,\n                subject VARCHAR(255) NOT NULL,\n                message_details TEXT DEFAULT NULL,\n                thread_id INT UNSIGNED DEFAULT NULL,\n                status VARCHAR(30) NOT NULL DEFAULT 'Unread',\n                message_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,\n                INDEX idx_franchise_folder (franchise_id, folder),\n                INDEX idx_thread_id (thread_id)\n            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci\n        ");
    }
}

if (!function_exists('ensureFranchiesProductRequestTable')) {
    function ensureFranchiesProductRequestTable(mysqli $conn): void
    {
        $conn->query("
            CREATE TABLE IF NOT EXISTS franchies_product_request (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                franchise_id INT UNSIGNED DEFAULT NULL,
                category VARCHAR(150) DEFAULT NULL,
                product_name VARCHAR(255) NOT NULL,
                pv INT DEFAULT 0,
                mrp DECIMAL(12,2) DEFAULT 0.00,
                offer_price DECIMAL(12,2) DEFAULT 0.00,
                quantity INT DEFAULT 1,
                total_amount DECIMAL(12,2) DEFAULT 0.00,
                commission DECIMAL(12,2) DEFAULT 0.00,
                net_amount DECIMAL(12,2) DEFAULT 0.00,
                total_pv INT DEFAULT 0,
                status VARCHAR(50) DEFAULT 'Pending',
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_franchise (franchise_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }
}

if (!function_exists('ensureFranchiesSendProductRequestTable')) {
    function ensureFranchiesSendProductRequestTable(mysqli $conn): void
    {
        $conn->query(" 
            CREATE TABLE IF NOT EXISTS franchies_send_product_request (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                franchise_id INT UNSIGNED NOT NULL,
                request_no INT UNSIGNED DEFAULT NULL,
                product_name VARCHAR(255) NOT NULL,
                request_date DATE DEFAULT NULL,
                total_products INT DEFAULT 0,
                total_amount DECIMAL(12,2) DEFAULT 0.00,
                total_pv INT DEFAULT 0,
                status VARCHAR(50) DEFAULT 'Pending',
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_franchise_status (franchise_id, status),
                INDEX idx_request_date (request_date)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }
}

if (!function_exists('ensureFranchiesProductStockRequestTable')) {
    function ensureFranchiesProductStockRequestTable(mysqli $conn): void
    {
        $conn->query(" 
            CREATE TABLE IF NOT EXISTS franchies_product_stock_request (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                franchise_id INT UNSIGNED NOT NULL,
                product_name VARCHAR(255) NOT NULL,
                mrp DECIMAL(12,2) DEFAULT 0.00,
                offer_price DECIMAL(12,2) DEFAULT 0.00,
                pv INT DEFAULT 0,
                product_received INT DEFAULT 0,
                product_used INT DEFAULT 0,
                product_balance INT DEFAULT 0,
                balance_amount DECIMAL(12,2) DEFAULT 0.00,
                status VARCHAR(50) DEFAULT 'Available',
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_franchise (franchise_id),
                INDEX idx_product_name (product_name)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }
}

if (!function_exists('getFranchiseMessages')) {
    function getFranchiseMessages(mysqli $conn, int $franchiseId, string $folder): array
    {
        ensureFranchiseMessagesTable($conn);

        $messages = [];
        $stmt = $conn->prepare("\n            SELECT id, thread_id, from_name, to_name, subject, message_details, message_date, status\n            FROM franchise_messages\n            WHERE franchise_id = ? AND folder = ?\n            ORDER BY id DESC\n        ");

        if (!$stmt) {
            return $messages;
        }

        $stmt->bind_param('is', $franchiseId, $folder);
        $stmt->execute();
        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            $messages[] = $row;
        }

        $stmt->close();

        return $messages;
    }
}
