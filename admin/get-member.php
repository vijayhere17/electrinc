<?php

require_once __DIR__ . '/config/db.php';

$customer_id = $_GET['customer_id'] ?? '';

$stmt = $conn->prepare("
    SELECT customer_id, full_name, mobile, email, state, city
    FROM signup_users
    WHERE customer_id = ?
    LIMIT 1
");

$stmt->bind_param('s', $customer_id);

$stmt->execute();

$result = $stmt->get_result();

echo json_encode($result->fetch_assoc());