<?php

require_once __DIR__ . '/config/db.php';

$customer_id = $_POST['customer_id'] ?? '';
$full_name   = $_POST['full_name'] ?? '';
$mobile      = $_POST['mobile'] ?? '';
$email       = $_POST['email'] ?? '';
$state       = $_POST['state'] ?? '';
$city        = $_POST['city'] ?? '';
$number      = $_POST['number'] ?? '';

if (empty($customer_id)) {
    echo 'Customer ID is required';
    exit;
}

$stmt = $conn->prepare("
    UPDATE signup_users
    SET
        full_name = ?,
        mobile = ?,
        email = ?,
        state = ?,
        city = ?
    WHERE customer_id = ?
");

$stmt->bind_param(
    'ssssss',
    $full_name,
    $mobile,
    $email,
    $state,
    $city,
    $customer_id
);

if ($stmt->execute()) {
    echo 'success';
} else {
    echo 'Update failed: ' . $stmt->error;
}

$stmt->close();