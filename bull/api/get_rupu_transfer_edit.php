<?php

include '../includes/config.php';

header('Content-Type: application/json');

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id <= 0) {
    echo json_encode(['success' => false]);
    exit;
}

$stmt = $conn->prepare("SELECT id, entry_date, party_id, narration, badlo, after_badlo, fine_badlo, created_at FROM rupu_transfer WHERE id = ?");
$stmt->bind_param('i', $id);
$stmt->execute();
$res = $stmt->get_result();
$data = $res->fetch_assoc();
$stmt->close();

$items = [];
$it_stmt = $conn->prepare("SELECT id, rupu_item_id, rupu_id, gross, actual_touch, changed_touch, actual_fine, changed_fine, narration, created_at FROM rupu_transfer_items WHERE transfer_id = ? ORDER BY id ASC");
$it_stmt->bind_param('i', $id);
$it_stmt->execute();
$it_res = $it_stmt->get_result();

while ($r = $it_res->fetch_assoc()) {
    $items[] = $r;
}

$it_stmt->close();

echo json_encode(['success' => true, 'data' => $data, 'items' => $items]);
