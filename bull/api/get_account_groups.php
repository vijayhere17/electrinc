<?php

include '../includes/config.php';

header('Content-Type: application/json');

$search = trim($_GET['q'] ?? '');
$data = [];

if ($search !== '') {
    $like = '%' . $search . '%';
    $stmt = $conn->prepare(
        "SELECT id, account_group_name
         FROM account_groups
         WHERE account_group_name LIKE ?
         ORDER BY account_group_name ASC"
    );
    $stmt->bind_param('s', $like);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();
} else {
    $result = $conn->query(
        "SELECT id, account_group_name FROM account_groups ORDER BY account_group_name ASC"
    );
}

while ($row = $result->fetch_assoc()) {
    $data[] = [
        'id'   => $row['id'],
        'text' => $row['account_group_name'],
    ];
}

echo json_encode([
    'results' => $data,
]);
