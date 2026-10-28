<?php
session_start();

include __DIR__ . '/config/db.php';

if (!isset($conn) || !($conn instanceof mysqli)) {
    die("Database connection failed.");
}

$conn->set_charset("utf8mb4");

$customerId = trim($_GET['customer_id'] ?? '');

if ($customerId === '') {
    die("Customer ID missing.");
}

$stmt = $conn->prepare("
    SELECT *
    FROM signup_users
    WHERE customer_id = ?
    LIMIT 1
");

if (!$stmt) {
    die("Query failed: " . $conn->error);
}

$stmt->bind_param("s", $customerId);
$stmt->execute();
$result = $stmt->get_result();
$member = $result->fetch_assoc();
$stmt->close();

if (!$member) {
    die("Member not found.");
}

/* Member panel session */
$_SESSION['member_id']   = $member['customer_id'];
$_SESSION['customer_id'] = $member['customer_id'];
$_SESSION['user_id']     = $member['customer_id'];
$_SESSION['full_name']   = $member['full_name'] ?? '';
$_SESSION['member_name'] = $member['full_name'] ?? '';
$_SESSION['mobile']      = $member['mobile'] ?? '';
$_SESSION['email']       = $member['email'] ?? '';
$_SESSION['role']        = 'member';
$_SESSION['member_login'] = true;
$_SESSION['logged_in']    = true;

/* Admin view flag */
$_SESSION['admin_view_member'] = true;

/* Redirect to real member panel */
header("Location: /freshya_member/dashboard.php");
exit;
?>