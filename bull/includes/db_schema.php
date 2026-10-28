<?php

function ensure_app_tables($conn)
{
    $conn->query("
        CREATE TABLE IF NOT EXISTS account_groups (
            id INT AUTO_INCREMENT PRIMARY KEY,
            account_group_name VARCHAR(100) NOT NULL UNIQUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $conn->query("
        CREATE TABLE IF NOT EXISTS accounts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            account_name VARCHAR(255) NOT NULL,
            account_type VARCHAR(50) NOT NULL DEFAULT 'party',
            account_group_id INT NOT NULL,
            address TEXT NULL,
            mobile_no VARCHAR(20) NULL,
            opening_gold DECIMAL(15,3) NOT NULL DEFAULT 0,
            opening_silver DECIMAL(15,3) NOT NULL DEFAULT 0,
            opening_amount DECIMAL(15,2) NOT NULL DEFAULT 0,
            tds DECIMAL(8,2) NOT NULL DEFAULT 0,
            is_cash_bank TINYINT(1) NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY idx_account_type (account_type),
            KEY idx_account_group_id (account_group_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    ensure_account_groups_data($conn);

    $conn->query("
        CREATE TABLE IF NOT EXISTS items (
            id INT AUTO_INCREMENT PRIMARY KEY,
            item_name VARCHAR(100) NOT NULL,
            opening_stock DECIMAL(15,3) NOT NULL DEFAULT 0,
            current_stock DECIMAL(15,3) NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $conn->query("
        CREATE TABLE IF NOT EXISTS entries (
            id INT AUTO_INCREMENT PRIMARY KEY,
            entry_date DATE NOT NULL,
            party_id INT NOT NULL,
            item_id INT NOT NULL DEFAULT 1,
            type_id TINYINT NOT NULL DEFAULT 1,
            bill_cash VARCHAR(10) NOT NULL DEFAULT 'Cash',
            rate DECIMAL(15,2) NOT NULL DEFAULT 0,
            qty DECIMAL(15,3) NOT NULL DEFAULT 0,
            pending_qty DECIMAL(15,3) NOT NULL DEFAULT 0,
            mcx DECIMAL(15,2) NOT NULL DEFAULT 0,
            range_min DECIMAL(15,2) NULL,
            range_max DECIMAL(15,2) NULL,
            narration TEXT NULL,
            tds DECIMAL(8,2) NOT NULL DEFAULT 0,
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_party_id (party_id),
            KEY idx_entry_date (entry_date),
            KEY idx_item_id (item_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $conn->query("
        CREATE TABLE IF NOT EXISTS transactions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            transaction_date DATE NOT NULL,
            party_id INT NOT NULL,
            cash_bank_id INT NOT NULL DEFAULT 0,
            amount DECIMAL(15,2) NOT NULL DEFAULT 0,
            item_id INT NOT NULL DEFAULT 1,
            rate DECIMAL(15,2) NOT NULL DEFAULT 0,
            qty DECIMAL(15,3) NOT NULL DEFAULT 0,
            mcx DECIMAL(15,2) NOT NULL DEFAULT 0,
            narration TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            KEY idx_party_id (party_id),
            KEY idx_transaction_date (transaction_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $items = [
        [1, 'Gold'],
        [2, 'Silver'],
    ];

    $item_stmt = $conn->prepare(
        "INSERT IGNORE INTO items (id, item_name, opening_stock, current_stock) VALUES (?, ?, 0, 0)"
    );

    foreach ($items as $item) {
        $item_stmt->bind_param('is', $item[0], $item[1]);
        $item_stmt->execute();
    }

    $item_stmt->close();

    ensure_default_cash_bank_accounts($conn);
}

function ensure_account_groups_data($conn)
{
    $conn->query("
        UPDATE account_groups
        SET account_group_name = 'TDS account group'
        WHERE account_group_name = 'TDS'
    ");

    $duplicate_check = $conn->query("
        SELECT COUNT(*) AS total,
               COUNT(DISTINCT account_group_name) AS distinct_names
        FROM account_groups
    ");

    if ($duplicate_check) {
        $counts = $duplicate_check->fetch_assoc();
        if ((int) $counts['total'] > (int) $counts['distinct_names']) {
            $conn->query("
                UPDATE accounts a
                INNER JOIN account_groups ag ON ag.id = a.account_group_id
                INNER JOIN (
                    SELECT account_group_name, MIN(id) AS canonical_id
                    FROM account_groups
                    GROUP BY account_group_name
                ) canon ON canon.account_group_name = ag.account_group_name
                SET a.account_group_id = canon.canonical_id
            ");

            $conn->query("
                DELETE ag
                FROM account_groups ag
                INNER JOIN account_groups ag2
                    ON ag.account_group_name = ag2.account_group_name
                   AND ag.id > ag2.id
            ");
        }
    }

    $index_check = $conn->query("
        SHOW INDEX FROM account_groups WHERE Key_name = 'account_group_name'
    ");

    if ($index_check && $index_check->num_rows === 0) {
        $conn->query("
            ALTER TABLE account_groups
            ADD UNIQUE KEY account_group_name (account_group_name)
        ");
    }

    $result = $conn->query("SELECT COUNT(*) AS total FROM account_groups");
    $row = $result ? $result->fetch_assoc() : ['total' => 0];

    if ((int) $row['total'] > 0) {
        return;
    }

    $groups = [
        'Cash/Bank',
        'Expense',
        'Interest',
        'Labour',
        'Party',
        'TDS account group',
    ];

    $stmt = $conn->prepare(
        "INSERT INTO account_groups (account_group_name) VALUES (?)"
    );

    foreach ($groups as $group) {
        $stmt->bind_param('s', $group);
        $stmt->execute();
    }

    $stmt->close();
}

function cash_bank_accounts_where_sql($alias = '')
{
    $prefix = $alias !== '' ? rtrim($alias, '.') . '.' : '';

    return "(
        {$prefix}account_type IN ('cash', 'bank')
        OR {$prefix}account_group_id = (
            SELECT id FROM account_groups WHERE account_group_name = 'Cash/Bank' LIMIT 1
        )
    )";
}

function ensure_default_cash_bank_accounts($conn)
{
    $group_result = $conn->query(
        "SELECT id FROM account_groups WHERE account_group_name = 'Cash/Bank' LIMIT 1"
    );
    $group_row = $group_result ? $group_result->fetch_assoc() : null;
    $group_id = $group_row ? (int) $group_row['id'] : 1;

    $defaults = [
        ['Cash Account', 'cash'],
        ['Bank Account', 'bank'],
        ['AXIS BANK', 'bank'],
        ['BOI BANK', 'bank'],
        ['HDFC BANK', 'bank'],
        ['RBL BANK', 'bank'],
        ['SBL BANK', 'bank'],
        ['Cash', 'cash'],
    ];

    $check_stmt = $conn->prepare(
        "SELECT id FROM accounts WHERE account_name = ? LIMIT 1"
    );
    $insert_stmt = $conn->prepare(
        "INSERT INTO accounts (
            account_name,
            account_type,
            account_group_id,
            opening_gold,
            opening_silver,
            opening_amount,
            tds,
            is_cash_bank
        ) VALUES (?, ?, ?, 0, 0, 0, 0, 1)"
    );

    foreach ($defaults as $account) {
        $check_stmt->bind_param('s', $account[0]);
        $check_stmt->execute();
        $exists = $check_stmt->get_result()->fetch_assoc();

        if ($exists) {
            continue;
        }

        $insert_stmt->bind_param('ssi', $account[0], $account[1], $group_id);
        $insert_stmt->execute();
    }

    $check_stmt->close();
    $insert_stmt->close();
}

function resolve_account_type($conn, $account_group_id, $is_cash_bank)
{
    $account_group_id = (int) $account_group_id;

    $result = $conn->query(
        "SELECT account_group_name
         FROM account_groups
         WHERE id = {$account_group_id}
         LIMIT 1"
    );

    if (!$result || $result->num_rows === 0) {
        return 'party';
    }

    $group_name = strtolower(trim($result->fetch_assoc()['account_group_name']));

    switch ($group_name) {
        case 'cash/bank':
            return $is_cash_bank ? 'bank' : 'cash';
        case 'party':
            return 'party';
        case 'expense':
            return 'expense';
        case 'interest':
            return 'interest';
        case 'labour':
            return 'labour';
        case 'tds account group':
        case 'tds':
            return 'tds';
        default:
            return 'party';
    }
}
