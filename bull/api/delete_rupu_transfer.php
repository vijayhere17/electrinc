<?php
header('Content-Type: application/json');

$conn = new mysqli(
    "localhost",
    "root",
    "",
    "bullion_erp"
);

if ($conn->connect_error) {
    echo json_encode(['success' => false, 'msg' => 'DB connect error']);
    exit;
}

$id = isset($_POST['id']) ? intval($_POST['id']) : 0;
if ($id <= 0) {
    echo json_encode(['success' => false, 'msg' => 'Invalid id']);
    exit;
}

$conn->begin_transaction();
try {
    $delItems = $conn->prepare("DELETE FROM rupu_transfer_items WHERE transfer_id = ?");
    $delItems->bind_param('i', $id);
    $delItems->execute();
    $delItems->close();

    $delMain = $conn->prepare("DELETE FROM rupu_transfer WHERE id = ?");
    $delMain->bind_param('i', $id);
    $delMain->execute();
    $delMain->close();

    $conn->commit();
    echo json_encode(['success' => true, 'msg' => 'Deleted']);
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'msg' => $e->getMessage()]);
}
exit;
?>
