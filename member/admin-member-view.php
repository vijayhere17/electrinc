<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config/db.php';

if (!isset($conn) || !($conn instanceof mysqli)) {
    die("Database connection failed.");
}

$conn->set_charset("utf8mb4");

$token = trim($_GET['token'] ?? '');

if ($token === '') {
    die("Invalid access token.");
}

$stmt = $conn->prepare("
    SELECT id, customer_id, expires_at, is_used
    FROM admin_member_access_tokens
    WHERE token = ?
    LIMIT 1
");
$stmt->bind_param("s", $token);
$stmt->execute();
$res = $stmt->get_result();
$tokenRow = $res ? $res->fetch_assoc() : null;
$stmt->close();

if (!$tokenRow) {
    die("Access token not found.");
}

if ((int)$tokenRow['is_used'] === 1) {
    die("This link has already been used.");
}

if (strtotime($tokenRow['expires_at']) < time()) {
    die("This link has expired.");
}

$customerId = trim($tokenRow['customer_id']);

$stmt = $conn->prepare("
    SELECT id, customer_id, full_name, email, mobile, registration_status
    FROM signup_users
    WHERE customer_id = ?
    LIMIT 1
");
$stmt->bind_param("s", $customerId);
$stmt->execute();
$res = $stmt->get_result();
$member = $res ? $res->fetch_assoc() : null;
$stmt->close();

if (!$member) {
    die("Member not found.");
}

// Mark token used
$stmt = $conn->prepare("UPDATE admin_member_access_tokens SET is_used = 1 WHERE id = ?");
$tokenId = (int)$tokenRow['id'];
$stmt->bind_param("i", $tokenId);
$stmt->execute();
$stmt->close();

/* ✅ SET MEMBER SESSION */
$_SESSION['customer_id'] = $member['customer_id'];
$_SESSION['member_id']   = $member['customer_id'];
$_SESSION['user_id']     = $member['id'];
$_SESSION['full_name']   = $member['full_name'] ?? 'Member';
$_SESSION['email']       = $member['email'] ?? '';
$_SESSION['mobile']      = $member['mobile'] ?? '';
$_SESSION['status']      = $member['registration_status'] ?? 'Active';

/* ✅ ADMIN PREVIEW FLAGS */
$_SESSION['admin_preview']   = true;
$_SESSION['admin_view_mode'] = true;
$_SESSION['preview_login']   = true;

/* ❌ REMOVE WRONG COOKIE PATH */
// setcookie('member_preview_mode', '1', time() + 3600, '/freshiya_main/');

/* ✅ CORRECT COOKIE (optional) */
setcookie('member_preview_mode', '1', time() + 3600, '/');

/* ✅ SAFE REDIRECT */
header("Location: ./dashboard.php");
exit;