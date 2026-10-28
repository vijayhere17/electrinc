<?php
session_start();

// get logged user
$customer_id = $_SESSION['customer_id'] ?? '';

if (!$customer_id) {
    die("User not logged in");
}

header("Location: https://netvexa.in/shop?uid=" . urlencode($customer_id));
exit;