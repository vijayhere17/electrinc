<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

require_once __DIR__ . '/../config/db.php';

if (!isset($conn) || !($conn instanceof mysqli)) {
    echo json_encode([
        'success' => false,
        'message' => 'DB connection error'
    ]);
    exit;
}

$search = trim($_GET['q'] ?? '');

if ($search === '') {
    echo json_encode([
        'success' => true,
        'members' => []
    ]);
    exit;
}

$q = '%' . $search . '%';

$stmt = $conn->prepare("
    SELECT
        customer_id,
        full_name,
        mobile,
        status
    FROM signup_users
    WHERE
        customer_id LIKE ?
        OR full_name LIKE ?
        OR mobile LIKE ?
    ORDER BY full_name ASC
    LIMIT 15
");

if (!$stmt) {
    echo json_encode([
        'success' => false,
        'message' => 'Query preparation failed'
    ]);
    exit;
}

$stmt->bind_param(
    'sss',
    $q,
    $q,
    $q
);

$stmt->execute();

$result = $stmt->get_result();

$members = [];

while ($row = $result->fetch_assoc()) {
    $members[] = [
        'id'      => $row['customer_id'],
        'name'    => $row['full_name'],
        'mobile'  => $row['mobile'] ?? '',
        'status'  => $row['status'] ?? 'Active'
    ];
}

$stmt->close();

echo json_encode([
    'success' => true,
    'members' => $members
]);
exit;