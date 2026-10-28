<?php
require_once __DIR__ . '/config/db.php';

if (!isset($conn) || !($conn instanceof mysqli)) {
    die("Database connection is not available.\n");
}

$customerId = 'fresh0001';
$nowDate = date('Y-m-d');
$nowDateTime = date('Y-m-d H:i:s');

function tableExists(mysqli $conn, string $table): bool
{
    $stmt = $conn->prepare("SELECT COUNT(*) AS c FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?");
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('s', $table);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return !empty($row['c']);
}

function getColumnsMeta(mysqli $conn, string $table): array
{
    $stmt = $conn->prepare("SELECT COLUMN_NAME, DATA_TYPE, IS_NULLABLE, COLUMN_DEFAULT, EXTRA, COLUMN_TYPE
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?");
    if (!$stmt) {
        return [];
    }

    $stmt->bind_param('s', $table);
    $stmt->execute();
    $result = $stmt->get_result();

    $meta = [];
    while ($row = $result->fetch_assoc()) {
        $meta[$row['COLUMN_NAME']] = $row;
    }
    $stmt->close();

    return $meta;
}

function enumFirstValue(string $columnType): string
{
    if (!preg_match("/^enum\\((.*)\\)$/i", $columnType, $m)) {
        return '';
    }

    $raw = $m[1];
    $parts = str_getcsv($raw, ',', "'", '\\');
    return $parts[0] ?? '';
}

function fallbackValueForColumn(array $columnMeta)
{
    $type = strtolower((string)($columnMeta['DATA_TYPE'] ?? ''));
    $columnType = (string)($columnMeta['COLUMN_TYPE'] ?? '');

    if ($type === 'enum') {
        return enumFirstValue($columnType);
    }

    if (in_array($type, ['tinyint', 'smallint', 'mediumint', 'int', 'bigint'], true)) {
        return 0;
    }

    if (in_array($type, ['decimal', 'float', 'double', 'real'], true)) {
        return 0;
    }

    if ($type === 'date') {
        return date('Y-m-d');
    }

    if (in_array($type, ['datetime', 'timestamp'], true)) {
        return date('Y-m-d H:i:s');
    }

    if ($type === 'time') {
        return date('H:i:s');
    }

    return '';
}

function insertRow(mysqli $conn, string $table, array $inputRow, array $meta): bool
{
    $row = [];
    foreach ($inputRow as $k => $v) {
        if (isset($meta[$k])) {
            $row[$k] = $v;
        }
    }

    foreach ($meta as $col => $info) {
        $isNullable = strtoupper((string)$info['IS_NULLABLE']) === 'YES';
        $hasDefault = $info['COLUMN_DEFAULT'] !== null;
        $isAuto = stripos((string)$info['EXTRA'], 'auto_increment') !== false;

        if (!array_key_exists($col, $row) && !$isNullable && !$hasDefault && !$isAuto) {
            $row[$col] = fallbackValueForColumn($info);
        }
    }

    if (empty($row)) {
        return false;
    }

    $columns = array_keys($row);
    $placeholders = implode(',', array_fill(0, count($columns), '?'));
    $quotedCols = '`' . implode('`,`', $columns) . '`';

    $sql = "INSERT INTO `{$table}` ({$quotedCols}) VALUES ({$placeholders})";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        echo "[WARN] Prepare failed for {$table}: {$conn->error}\n";
        return false;
    }

    $types = '';
    $values = [];
    foreach ($columns as $col) {
        $value = $row[$col];
        $values[] = $value;

        if (is_int($value)) {
            $types .= 'i';
        } elseif (is_float($value)) {
            $types .= 'd';
        } else {
            $types .= 's';
        }
    }

    $bindArgs = [$types];
    foreach ($values as $i => $v) {
        $bindArgs[] = &$values[$i];
    }

    call_user_func_array([$stmt, 'bind_param'], $bindArgs);

    $ok = $stmt->execute();
    if (!$ok) {
        echo "[WARN] Insert failed for {$table}: {$stmt->error}\n";
    }
    $stmt->close();

    return $ok;
}

function seedTableByCustomer(mysqli $conn, string $table, string $customerId, array $rows): void
{
    if (!tableExists($conn, $table)) {
        echo "[SKIP] {$table}: table not found\n";
        return;
    }

    $meta = getColumnsMeta($conn, $table);
    if (empty($meta)) {
        echo "[SKIP] {$table}: no column metadata\n";
        return;
    }

    if (isset($meta['customer_id'])) {
        $del = $conn->prepare("DELETE FROM `{$table}` WHERE customer_id = ?");
        if ($del) {
            $del->bind_param('s', $customerId);
            $del->execute();
            $del->close();
        }
    }

    $inserted = 0;
    foreach ($rows as $row) {
        if (isset($meta['customer_id']) && !isset($row['customer_id'])) {
            $row['customer_id'] = $customerId;
        }
        if (insertRow($conn, $table, $row, $meta)) {
            $inserted++;
        }
    }

    echo "[OK] {$table}: inserted {$inserted} row(s)\n";
}

function seedSignupUser(mysqli $conn, string $customerId): void
{
    $table = 'signup_users';
    if (!tableExists($conn, $table)) {
        echo "[SKIP] signup_users: table not found\n";
        return;
    }

    $meta = getColumnsMeta($conn, $table);
    $idColumn = isset($meta['customer_id']) ? 'customer_id' : (isset($meta['member_id']) ? 'member_id' : null);

    if ($idColumn === null) {
        echo "[SKIP] signup_users: neither customer_id nor member_id column found\n";
        return;
    }

    $passwordHash = password_hash('123456', PASSWORD_DEFAULT);

    $row = [
        $idColumn => $customerId,
        'full_name' => 'Test User Fresh',
        'dob' => '2001-01-15',
        'gender' => 'Male',
        'email' => 'fresh0001@test.com',
        'mobile' => '9876543210',
        'password' => $passwordHash,
        'address' => '221B Test Street',
        'pincode' => '380001',
        'state' => 'Gujarat',
        'city' => 'Ahmedabad',
        'district' => 'Ahmedabad',
        'aadhar_number' => '123456789012',
        'pan_number' => 'ABCDE1234F',
        'gst_number' => '24ABCDE1234F1Z5',
        'terms_accepted' => 1,
        'age_confirmed' => 1,
    ];

    $del = $conn->prepare("DELETE FROM `signup_users` WHERE `{$idColumn}` = ?");
    if ($del) {
        $del->bind_param('s', $customerId);
        $del->execute();
        $del->close();
    }

    $ok = insertRow($conn, $table, $row, $meta);
    echo $ok
        ? "[OK] signup_users: user seeded (login password: 123456)\n"
        : "[WARN] signup_users: user seed failed\n";
}

seedSignupUser($conn, $customerId);

seedTableByCustomer($conn, 'member_profiles', $customerId, [
    [
        'full_name' => 'Test User Fresh',
        'birth_date' => '2001-01-15',
        'anniversary_date' => '2024-02-20',
        'gender' => 'Male',
        'marital_status' => 'Married',
        'aadhar_number' => '123456789012',
        'pan_number' => 'ABCDE1234F',
        'gst_number' => '24ABCDE1234F1Z5',
        'house_number' => 'A-12',
        'street_name' => 'Test Avenue',
        'state_name' => 'Gujarat',
        'district_name' => 'Ahmedabad',
        'city_name' => 'Ahmedabad',
        'pincode' => '380001',
        'email' => 'fresh0001@test.com',
        'mobile_no' => '9876543210',
        'nominee_name' => 'Test Nominee',
        'nominee_relation' => 'Brother',
        'nominee_birthdate' => '2003-08-11',
        'profile_photo' => '',
    ],
]);

seedTableByCustomer($conn, 'shipping_details', $customerId, [
    [
        'name' => 'Test User Fresh',
        'house_number' => 'A-12',
        'street_name' => 'Warehouse Road',
        'landmark' => 'Near City Mall',
        'state_name' => 'Gujarat',
        'district_name' => 'Ahmedabad',
        'city_name' => 'Ahmedabad',
        'pincode' => '380001',
        'email' => 'fresh0001@test.com',
        'mobile_no' => '9876543210',
    ],
]);

seedTableByCustomer($conn, 'member_bank_kyc_details', $customerId, [
    [
        'account_beneficiary_name' => 'TEST USER FRESH',
        'account_no' => '9876543210123456',
        'ifsc_code' => 'SBIN0001234',
        'bank_name' => 'State Bank of India',
        'aadhar_number' => '123456789012',
        'pan_number' => 'ABCDE1234F',
        'is_bank_locked' => 1,
    ],
]);

seedTableByCustomer($conn, 'direct_network', $customerId, [
    [
        'join_date' => '2026-03-01',
        'win_code' => 'WIN101',
        'activation_amount' => 2500,
        'designation' => 'Distributor',
        'state_name' => 'Gujarat',
        'city_name' => 'Surat',
        'sponsored_side' => 'Left',
        'previous_closing' => 1200,
        'qualified_team' => 1,
    ],
    [
        'join_date' => '2026-03-05',
        'win_code' => 'WIN102',
        'activation_amount' => 3000,
        'designation' => 'Associate',
        'state_name' => 'Gujarat',
        'city_name' => 'Rajkot',
        'sponsored_side' => 'Right',
        'previous_closing' => 900,
        'qualified_team' => 1,
    ],
    [
        'join_date' => '2026-03-12',
        'win_code' => 'WIN103',
        'activation_amount' => 2800,
        'designation' => 'Distributor',
        'state_name' => 'Gujarat',
        'city_name' => 'Vadodara',
        'sponsored_side' => 'Left',
        'previous_closing' => 1600,
        'qualified_team' => 0,
    ],
]);

seedTableByCustomer($conn, 'team_volume_status', $customerId, [
    [
        'forward_team_1' => 150,
        'forward_team_2' => 110,
        'current_team_1' => 220,
        'current_team_2' => 195,
        'total_team_1' => 370,
        'total_team_2' => 305,
        'total_qualified_team_sales_volume' => 675,
        'qualified_team_sales_volume' => 415,
    ],
    [
        'forward_team_1' => 95,
        'forward_team_2' => 140,
        'current_team_1' => 205,
        'current_team_2' => 210,
        'total_team_1' => 300,
        'total_team_2' => 350,
        'total_qualified_team_sales_volume' => 650,
        'qualified_team_sales_volume' => 430,
    ],
]);

seedTableByCustomer($conn, 'team_business_status', $customerId, [
    [
        'order_no' => 'ORD-BUS-1001',
        'serial_no' => 'S001',
        'member_code' => 'M1001',
        'member_name' => 'Rahul Patel',
        'state_name' => 'Gujarat',
        'city_name' => 'Surat',
        'self_rbv' => 150.00,
        'team_rbv' => 400.00,
        'team_a_rbv' => 210.00,
        'team_b_rbv' => 190.00,
    ],
    [
        'order_no' => 'ORD-BUS-1002',
        'serial_no' => 'S002',
        'member_code' => 'M1002',
        'member_name' => 'Asha Mehta',
        'state_name' => 'Gujarat',
        'city_name' => 'Rajkot',
        'self_rbv' => 180.00,
        'team_rbv' => 460.00,
        'team_a_rbv' => 240.00,
        'team_b_rbv' => 220.00,
    ],
]);

seedTableByCustomer($conn, 'repurchase_orders', $customerId, [
    [
        'order_no' => 'REP-2026-0001',
        'order_date' => '2026-04-02',
        'amount' => 2500.00,
        'payment_mode' => 'By Online Payment',
        'transaction_number' => 'TXN112233',
        'rbv' => 125.00,
        'payment_slip' => 'test-slip-1.jpg',
        'status' => 'Approved',
    ],
    [
        'order_no' => 'REP-2026-0002',
        'order_date' => '2026-04-06',
        'amount' => 3200.00,
        'payment_mode' => 'By Online Payment',
        'transaction_number' => 'TXN445566',
        'rbv' => 160.00,
        'payment_slip' => 'test-slip-2.jpg',
        'status' => 'Pending',
    ],
    [
        'order_no' => 'REP-2026-0003',
        'order_date' => '2026-04-09',
        'amount' => 1800.00,
        'payment_mode' => 'By Online Payment',
        'transaction_number' => 'TXN778899',
        'rbv' => 90.00,
        'payment_slip' => 'test-slip-3.jpg',
        'status' => 'Rejected',
    ],
]);

seedTableByCustomer($conn, 'order_status', $customerId, [
    [
        'purchase_date' => '2026-04-02',
        'product_name' => 'Freshiya Wellness Combo',
        'shipping_details' => 'A-12, Test Avenue, Ahmedabad',
        'dispatch_status' => 'Dispatched',
        'invoice_no' => 'INV-9001',
    ],
    [
        'purchase_date' => '2026-04-06',
        'product_name' => 'Freshiya Nutrition Pack',
        'shipping_details' => 'A-12, Test Avenue, Ahmedabad',
        'dispatch_status' => 'Processing',
        'invoice_no' => 'INV-9002',
    ],
]);

seedTableByCustomer($conn, 'retail_profit_status', $customerId, [
    ['profit_date' => '2026-04-03', 'transaction_id' => 'RPS-1001', 'target_business' => 5000, 'percentage' => 5, 'earned' => 250, 'status' => 'Credit'],
    ['profit_date' => '2026-04-07', 'transaction_id' => 'RPS-1002', 'target_business' => 7000, 'percentage' => 6, 'earned' => 420, 'status' => 'Credit'],
]);

seedTableByCustomer($conn, 'level_matching_status', $customerId, [
    ['bonus_date' => '2026-04-04', 'transaction_id' => 'LMS-2001', 'matched_pair' => 8, 'pair_rate' => 120, 'earned' => 960, 'status' => 'Credit'],
    ['bonus_date' => '2026-04-08', 'transaction_id' => 'LMS-2002', 'matched_pair' => 10, 'pair_rate' => 120, 'earned' => 1200, 'status' => 'Credit'],
]);

seedTableByCustomer($conn, 'club_bonus_status', $customerId, [
    ['bonus_date' => '2026-04-05', 'transaction_id' => 'CBS-3001', 'completed_pairs' => 25, 'club_level' => 'Silver Club', 'next_target' => 30, 'bonus_rate' => 40, 'earned' => 1000, 'status' => 'Credit'],
    ['bonus_date' => '2026-04-09', 'transaction_id' => 'CBS-3002', 'completed_pairs' => 32, 'club_level' => 'Gold Club', 'next_target' => 40, 'bonus_rate' => 60, 'earned' => 1920, 'status' => 'Credit'],
]);

seedTableByCustomer($conn, 'referral_bonus_status', $customerId, [
    ['bonus_date' => '2026-04-05', 'transaction_id' => 'RBS-4001', 'club_no' => 1, 'club_level' => 'Silver Club', 'referral_bonus' => 300, 'senior1_bonus' => 150, 'senior2_bonus' => 100, 'earned' => 550, 'status' => 'Credit'],
    ['bonus_date' => '2026-04-10', 'transaction_id' => 'RBS-4002', 'club_no' => 2, 'club_level' => 'Gold Club', 'referral_bonus' => 500, 'senior1_bonus' => 250, 'senior2_bonus' => 150, 'earned' => 900, 'status' => 'Credit'],
]);

seedTableByCustomer($conn, 'reward_status', $customerId, [
    ['reward_date' => '2026-04-06', 'transaction_id' => 'RWD-5001', 'club_no' => 1, 'club_level' => 'Silver Club', 'reward_name' => 'Smart Watch', 'reward_budget' => 3000, 'earned' => 3000, 'status' => 'Approved'],
    ['reward_date' => '2026-04-10', 'transaction_id' => 'RWD-5002', 'club_no' => 2, 'club_level' => 'Gold Club', 'reward_name' => 'Android Phone', 'reward_budget' => 12000, 'earned' => 12000, 'status' => 'Approved'],
]);

seedTableByCustomer($conn, 'retail_profit_income_report', $customerId, [
    ['report_date' => '2026-04-03', 'description' => 'Retail profit closing', 'percentage' => 5, 'credit' => 250, 'debit' => 0, 'balance' => 250, 'status' => 'Credit'],
    ['report_date' => '2026-04-07', 'description' => 'Retail profit closing', 'percentage' => 6, 'credit' => 420, 'debit' => 0, 'balance' => 670, 'status' => 'Credit'],
]);

seedTableByCustomer($conn, 'level_matching_income_report', $customerId, [
    ['report_date' => '2026-04-04', 'description' => 'Level matching bonus', 'percentage' => 0, 'credit' => 960, 'debit' => 0, 'balance' => 1630, 'status' => 'Credit'],
    ['report_date' => '2026-04-08', 'description' => 'Level matching bonus', 'percentage' => 0, 'credit' => 1200, 'debit' => 0, 'balance' => 2830, 'status' => 'Credit'],
]);

seedTableByCustomer($conn, 'club_bonus_income_report', $customerId, [
    ['report_date' => '2026-04-05', 'description' => 'Club bonus payout', 'percentage' => 0, 'credit' => 1000, 'debit' => 0, 'balance' => 3830, 'status' => 'Credit'],
    ['report_date' => '2026-04-09', 'description' => 'Club bonus payout', 'percentage' => 0, 'credit' => 1920, 'debit' => 0, 'balance' => 5750, 'status' => 'Credit'],
]);

seedTableByCustomer($conn, 'referral_bonus_income_report', $customerId, [
    ['report_date' => '2026-04-05', 'description' => 'Referral bonus payout', 'percentage' => 0, 'credit' => 550, 'debit' => 0, 'balance' => 6300, 'status' => 'Credit'],
    ['report_date' => '2026-04-10', 'description' => 'Referral bonus payout', 'percentage' => 0, 'credit' => 900, 'debit' => 0, 'balance' => 7200, 'status' => 'Credit'],
]);

seedTableByCustomer($conn, 'reward_income_report', $customerId, [
    ['report_date' => '2026-04-06', 'description' => 'Reward approved', 'percentage' => 0, 'credit' => 3000, 'debit' => 0, 'balance' => 10200, 'status' => 'Credit'],
    ['report_date' => '2026-04-10', 'description' => 'Reward approved', 'percentage' => 0, 'credit' => 12000, 'debit' => 0, 'balance' => 22200, 'status' => 'Credit'],
]);

seedTableByCustomer($conn, 'inbox_messages', $customerId, [
    ['from_name' => 'Admin Team', 'subject' => 'Welcome to Freshiya', 'message_details' => 'Your account has been activated successfully.', 'message_date' => $nowDateTime, 'status' => 'Unread'],
    ['from_name' => 'Support Desk', 'subject' => 'KYC Reminder', 'message_details' => 'Please verify your KYC to unlock payouts.', 'message_date' => $nowDateTime, 'status' => 'Read'],
    ['from_name' => 'Accounts', 'subject' => 'Bonus Credited', 'message_details' => 'Your latest referral bonus has been credited.', 'message_date' => $nowDateTime, 'status' => 'Unread'],
]);

seedTableByCustomer($conn, 'member_messages', $customerId, [
    ['folder' => 'outbox', 'from_name' => 'Test User Fresh', 'to_name' => 'Support Team', 'subject' => 'Need invoice copy', 'message_details' => 'Please share invoice for my April order.', 'message_date' => $nowDateTime, 'status' => 'Sent', 'thread_id' => 101],
    ['folder' => 'outbox', 'from_name' => 'Test User Fresh', 'to_name' => 'Accounts Team', 'subject' => 'RBV clarification', 'message_details' => 'Please clarify RBV calculation for order REP-2026-0002.', 'message_date' => $nowDateTime, 'status' => 'Sent', 'thread_id' => 102],
    ['folder' => 'outbox', 'from_name' => 'Test User Fresh', 'to_name' => 'Admin Team', 'subject' => 'Address update request', 'message_details' => 'Please confirm my shipping address update.', 'message_date' => $nowDateTime, 'status' => 'Sent', 'thread_id' => 103],
]);

seedTableByCustomer($conn, 'compose_messages', $customerId, [
    ['send_to' => 'Support Team', 'subject' => 'Follow-up on KYC', 'message_details' => 'KYC uploaded. Please verify soon.'],
    ['send_to' => 'Admin Team', 'subject' => 'Panel testing', 'message_details' => 'This is a test message from seeded account.'],
]);

echo "\nSeeding completed for customer_id={$customerId}\n";
