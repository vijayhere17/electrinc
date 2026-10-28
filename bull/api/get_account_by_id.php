<?php

include '../includes/config.php';

header('Content-Type: application/json');

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id <= 0) {
    echo json_encode(['success' => false]);
    exit;
}

$stmt = $conn->prepare("SELECT id, name FROM accounts WHERE id = ? LIMIT 1");
$stmt->bind_param('i', $id);
$stmt->execute();
$res = $stmt->get_result();
$row = $res->fetch_assoc();
$stmt->close();

if ($row) {
    echo json_encode(['success' => true, 'id' => $row['id'], 'text' => $row['name']]);
} else {
    echo json_encode(['success' => false]);
}
