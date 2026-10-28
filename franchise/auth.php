<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/helpers.php';

if (!isset($conn) || !($conn instanceof mysqli)) {
    die("Database connection failed.");
}

if (!function_exists('franchiseRedirect')) {
    function franchiseRedirect(string $page)
    {
        header("Location: " . $page);
        exit();
    }
}

if (!function_exists('frAuthTableExists')) {
    function frAuthTableExists(mysqli $conn, string $table): bool
    {
        $table = $conn->real_escape_string($table);
        $result = $conn->query("SHOW TABLES LIKE '$table'");
        return $result && $result->num_rows > 0;
    }
}

if (!function_exists('frAuthColumnExists')) {
    function frAuthColumnExists(mysqli $conn, string $table, string $column): bool
    {
        $table = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
        $column = $conn->real_escape_string($column);

        $result = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
        return $result && $result->num_rows > 0;
    }
}

if (!function_exists('frAuthAddColumnIfMissing')) {
    function frAuthAddColumnIfMissing(mysqli $conn, string $table, string $columnSql): void
    {
        $column = trim(explode(' ', trim($columnSql))[0], '`');

        if (!frAuthColumnExists($conn, $table, $column)) {
            $conn->query("ALTER TABLE `$table` ADD COLUMN $columnSql");
        }
    }
}

if (empty($_SESSION['franchise_id']) || empty($_SESSION['franchise_type'])) {
    franchiseRedirect("index.php");
}

$franchiseId   = (int)$_SESSION['franchise_id'];
$franchiseType = strtolower(trim((string)$_SESSION['franchise_type']));

if (!in_array($franchiseType, ['city', 'state'], true)) {
    session_destroy();
    franchiseRedirect("index.php");
}

/* Create city tables */
$conn->query("
CREATE TABLE IF NOT EXISTS `city_franchies_payment` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `order_id` VARCHAR(50) NULL,
    `franchise_id` INT(11) NULL,
    `franchise_code` VARCHAR(50) NULL,
    `total_amount` DECIMAL(12,2) NULL DEFAULT 0.00,
    `bank_name` VARCHAR(100) NULL,
    `payment_mode` VARCHAR(50) NULL,
    `utr_number` VARCHAR(100) NULL,
    `receipt` VARCHAR(255) NULL,
    `remark` TEXT NULL,
    `status` VARCHAR(50) NULL DEFAULT 'Pending',
    `created_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

$conn->query("
CREATE TABLE IF NOT EXISTS `city_franchies_product_request` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `order_id` VARCHAR(50) NULL,
    `franchise_id` INT(11) NULL,
    `franchise_code` VARCHAR(50) NULL,
    `category` VARCHAR(150) NULL,
    `product_name` VARCHAR(255) NULL,
    `offer_price` DECIMAL(12,2) NULL DEFAULT 0.00,
    `quantity` INT(11) NULL DEFAULT 1,
    `total_amount` DECIMAL(12,2) NULL DEFAULT 0.00,
    `status` VARCHAR(50) NULL DEFAULT 'Pending',
    `created_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

$conn->query("
CREATE TABLE IF NOT EXISTS `city_franchies_kyc` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `franchise_code` VARCHAR(50) NOT NULL,
    `document_type` VARCHAR(100) NOT NULL,
    `document_file` VARCHAR(255) NOT NULL,
    `status` ENUM('Pending','Approved','Rejected') NOT NULL DEFAULT 'Pending',
    `uploaded_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX (`franchise_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

/* Decide tables */
if ($franchiseType === 'city') {
    $franchiseHolderTable = 'city_franchies_holder';
    $paymentTable = 'city_franchies_payment';
    $productRequestTable = 'city_franchies_product_request';
    $kycTable = 'city_franchies_kyc';
    $panelName = 'City Franchise Panel';
} else {
    $franchiseHolderTable = 'franchies_holder';
    $paymentTable = 'franchies_payment';
    $productRequestTable = 'franchies_product_request';
    $kycTable = 'franchise_kyc_documents';
    $panelName = 'State Franchise Panel';
}

/* Add franchise_code to all related tables */
foreach ([
    'franchies_payment',
    'city_franchies_payment',
    'franchies_product_request',
    'city_franchies_product_request'
] as $tbl) {
    if (frAuthTableExists($conn, $tbl)) {
        frAuthAddColumnIfMissing($conn, $tbl, "`franchise_code` VARCHAR(50) NULL");
    }
}

/* Fetch logged-in franchise */
$stmt = $conn->prepare("SELECT * FROM `$franchiseHolderTable` WHERE id = ? LIMIT 1");

if (!$stmt) {
    die("Franchise query failed: " . $conn->error);
}

$stmt->bind_param("i", $franchiseId);
$stmt->execute();

$result = $stmt->get_result();

if (!$result || $result->num_rows === 0) {
    session_destroy();
    franchiseRedirect("index.php");
}

$franchise = $result->fetch_assoc();
$stmt->close();

$franchiseCode = $franchise['franchies_id']
    ?? $franchise['franchise_id']
    ?? $_SESSION['franchise_code']
    ?? '';

$franchiseName = $franchise['full_name']
    ?? $franchise['name']
    ?? 'Franchise';

$_SESSION['franchise_code'] = $franchiseCode;
$_SESSION['franchise_name'] = $franchiseName;

/* Update old rows with franchise_code */
foreach ([$paymentTable, $productRequestTable] as $tbl) {
    if (frAuthTableExists($conn, $tbl) && frAuthColumnExists($conn, $tbl, 'franchise_code')) {
        $stmtUpdate = $conn->prepare("
            UPDATE `$tbl`
            SET franchise_code = ?
            WHERE franchise_id = ?
            AND (franchise_code IS NULL OR franchise_code = '')
        ");

        if ($stmtUpdate) {
            $stmtUpdate->bind_param("si", $franchiseCode, $franchiseId);
            $stmtUpdate->execute();
            $stmtUpdate->close();
        }
    }
}

/* Global variables */
$GLOBALS['franchiseId'] = $franchiseId;
$GLOBALS['franchiseType'] = $franchiseType;
$GLOBALS['franchiseCode'] = $franchiseCode;
$GLOBALS['franchiseName'] = $franchiseName;
$GLOBALS['franchiseHolderTable'] = $franchiseHolderTable;
$GLOBALS['paymentTable'] = $paymentTable;
$GLOBALS['productRequestTable'] = $productRequestTable;
$GLOBALS['kycTable'] = $kycTable;
$GLOBALS['panelName'] = $panelName;
$GLOBALS['franchise'] = $franchise;
?>