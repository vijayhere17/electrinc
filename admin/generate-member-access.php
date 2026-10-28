<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config/db.php';

// Check DB connection
if (!isset($conn) || !($conn instanceof mysqli)) {
    die("Database connection failed.");
}

$conn->set_charset("utf8mb4");

// Get customer ID
$customerId = trim($_GET['customer_id'] ?? '');

if ($customerId === '') {
    die("Customer ID missing.");
}


$stmt = $conn->prepare("SELECT customer_id FROM signup_users WHERE customer_id = ? LIMIT 1");
if (!$stmt) {
    die("Query failed: " . $conn->error);
}

$stmt->bind_param("s", $customerId);
$stmt->execute();
$result = $stmt->get_result();

if (!$result || $result->num_rows === 0) {
    die("Member not found.");
}
$stmt->close();

// Generate secure token
$token = bin2hex(random_bytes(32));
$expiresAt = date("Y-m-d H:i:s", time() + 3600); // 1 hour expiry
$adminSessionId = session_id();

// Insert token into DB
$stmt = $conn->prepare("
    INSERT INTO admin_member_access_tokens
    (admin_session_id, customer_id, token, expires_at, is_used)
    VALUES (?, ?, ?, ?, 0)
");

if (!$stmt) {
    die("Token insert failed: " . $conn->error);
}

$stmt->bind_param("ssss", $adminSessionId, $customerId, $token, $expiresAt);

if (!$stmt->execute()) {
    die("Execute failed: " . $stmt->error);
}

$stmt->close();


$baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? "https://" : "http://") 
            . $_SERVER['HTTP_HOST'];


$redirectUrl = $baseUrl . "/member/admin-member-view.php?token=" . urlencode($token);

// Redirect
header("Location: " . $redirectUrl);
exit;
?>