<?php
$conn = new mysqli('localhost', 'root', '', 'ind_new');
if ($conn->connect_errno) {
    echo "CONN_ERR\n";
    exit(1);
}
$mobileCode = 'FRM8981';
$stmt = $conn->prepare("SELECT * FROM mobile_franchies_holder WHERE mobile_franchies_id = ? LIMIT 1");
$stmt->bind_param('s', $mobileCode);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();
echo "MOBILE_ROW=" . json_encode($row) . "\n";
$cityParent = '';
if (!empty($row['city'])) {
    $cityParent = $row['city'];
}
if (!$cityParent) {
    echo "NO_CITY_PARENT\n";
    exit;
}
$cityStmt = $conn->prepare("SELECT * FROM city_franchies_holder WHERE franchies_id = ? OR franchise_id = ? OR franchise_code = ? LIMIT 1");
$cityStmt->bind_param('sss', $cityParent, $cityParent, $cityParent);
$cityStmt->execute();
$cityRow = $cityStmt->get_result()->fetch_assoc();
$cityStmt->close();
echo "CITY_ROW=" . json_encode($cityRow) . "\n";
$cityId = $cityRow['id'] ?? '';
$cityCode = $cityRow['franchies_id'] ?? '';
foreach (['franchise_stock', 'franchise_stock_wallet'] as $t) {
    echo "TABLE=$t\n";
    $hasCode = false;
    $res = $conn->query("SHOW COLUMNS FROM `" . $conn->real_escape_string($t) . "` LIKE 'franchise_code'");
    if ($res && $res->num_rows > 0) {
        $hasCode = true;
    }
    if ($hasCode) {
        $sql = sprintf(
            "SELECT * FROM `%s` WHERE franchise_type = 'city' AND (franchise_id = '%s' OR franchise_code = '%s') ORDER BY id",
            $conn->real_escape_string($t),
            $conn->real_escape_string($cityId),
            $conn->real_escape_string($cityCode)
        );
    } else {
        $sql = sprintf(
            "SELECT * FROM `%s` WHERE franchise_type = 'city' AND franchise_id = '%s' ORDER BY id",
            $conn->real_escape_string($t),
            $conn->real_escape_string($cityId)
        );
    }
    $res = $conn->query($sql);
    while ($row = $res->fetch_assoc()) {
        echo json_encode($row) . "\n";
    }
}
$sql = sprintf("SELECT * FROM `franchies_product_stock_request` WHERE franchise_id = '%s' ORDER BY id", $conn->real_escape_string($cityId));
$res = $conn->query($sql);
echo "TABLE=franchies_product_stock_request\n";
while ($row = $res->fetch_assoc()) {
    echo json_encode($row) . "\n";
}
$conn->close();
