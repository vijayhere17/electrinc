<?php
session_name('MEMBER_ADMIN_VIEW');
session_start();

include __DIR__ . '/config/db.php';

if (!isset($conn) || !($conn instanceof mysqli)) {
    die('Database connection failed.');
}

$conn->set_charset("utf8mb4");

function tableExists(mysqli $conn, string $tableName): bool
{
    $safeName = $conn->real_escape_string($tableName);
    $result = $conn->query("SHOW TABLES LIKE '{$safeName}'");
    return $result instanceof mysqli_result && $result->num_rows > 0;
}

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

function hasColumn(array $columns, string $column): bool
{
    return in_array(strtolower($column), $columns, true);
}

function firstExistingColumn(array $columns, array $candidates): ?string
{
    foreach ($candidates as $candidate) {
        if (hasColumn($columns, $candidate)) {
            return strtolower($candidate);
        }
    }
    return null;
}

$token = trim($_GET['token'] ?? '');

if ($token === '') {
    die('Invalid access token.');
}

if (!tableExists($conn, 'admin_member_access_tokens')) {
    die('Token table not found.');
}

$stmt = $conn->prepare("
    SELECT id, customer_id, expires_at, is_used
    FROM admin_member_access_tokens
    WHERE token = ?
    LIMIT 1
");

if (!$stmt) {
    die('Database error: ' . $conn->error);
}

$stmt->bind_param('s', $token);
$stmt->execute();
$result = $stmt->get_result();
$tokenRow = $result ? $result->fetch_assoc() : null;
$stmt->close();

if (!$tokenRow) {
    die('Access token not found.');
}

if ((int)($tokenRow['is_used'] ?? 0) === 1) {
    die('This link has already been used.');
}

if (strtotime((string)($tokenRow['expires_at'] ?? '')) < time()) {
    die('This link has expired.');
}

$customerId = trim((string)($tokenRow['customer_id'] ?? ''));
if ($customerId === '') {
    die('Invalid customer id in token.');
}

if (!tableExists($conn, 'signup_users')) {
    die('signup_users table not found.');
}

$signupColumns = getTableColumns($conn, 'signup_users');

$customerCol = firstExistingColumn($signupColumns, ['customer_id', 'member_id', 'user_id']);
$nameCol     = firstExistingColumn($signupColumns, ['full_name', 'name']);
$emailCol    = firstExistingColumn($signupColumns, ['email']);
$mobileCol   = firstExistingColumn($signupColumns, ['mobile', 'mobile_no', 'phone']);
$statusCol   = firstExistingColumn($signupColumns, ['status', 'active_status']);
$statusCol   = firstExistingColumn($signupColumns, ['registration_status', 'status', 'active_status']);

if (!$customerCol) {
    die('Customer ID column not found in signup_users table.');
}

$selectParts = [];
$selectParts[] = "id";
$selectParts[] = "`{$customerCol}` AS customer_id";
$selectParts[] = $nameCol   ? "`{$nameCol}` AS full_name" : "'' AS full_name";
$selectParts[] = $emailCol  ? "`{$emailCol}` AS email" : "'' AS email";
$selectParts[] = $mobileCol ? "`{$mobileCol}` AS mobile" : "'' AS mobile";

if ($statusCol) {
    if ($statusCol === 'status') {
        $selectParts[] = "CASE 
            WHEN `status` IN ('1', 1) THEN 'Active'
            WHEN `status` IN ('0', 0) THEN 'Inactive'
            ELSE `status`
        END AS member_status";
    } else {
        $selectParts[] = "`{$statusCol}` AS member_status";
    }
} else {
    $selectParts[] = "'Active' AS member_status";
}

$sql = "
    SELECT " . implode(", ", $selectParts) . "
    FROM signup_users
    WHERE `{$customerCol}` = ?
    LIMIT 1
";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    die('Member query failed: ' . $conn->error);
}

$stmt->bind_param('s', $customerId);
$stmt->execute();
$result = $stmt->get_result();
$member = $result ? $result->fetch_assoc() : null;
$stmt->close();

if (!$member) {
    die('Member not found.');
}

$updateStmt = $conn->prepare("
    UPDATE admin_member_access_tokens
    SET is_used = 1
    WHERE id = ?
");
if ($updateStmt) {
    $tokenId = (int)$tokenRow['id'];
    $updateStmt->bind_param('i', $tokenId);
    $updateStmt->execute();
    $updateStmt->close();
}

setcookie('member_preview_mode', '1', time() + 3600, '/');

$_SESSION['admin_view_mode'] = true;
$_SESSION['preview_login']   = true;
$_SESSION['member_id']       = $member['customer_id'] ?? '';
$_SESSION['customer_id']     = $member['customer_id'] ?? '';
$_SESSION['full_name']       = $member['full_name'] ?? 'Member';
$_SESSION['email']           = $member['email'] ?? '';
$_SESSION['mobile']          = $member['mobile'] ?? '';
$_SESSION['status']          = $member['member_status'] ?? 'Active';
$_SESSION['registration_status'] = $member['member_status'] ?? 'Active';

header('Location: dashboard.php?preview=1');
exit();