<?php
$conn = new mysqli('localhost', 'root', '', 'ind_new');
if ($conn->connect_errno) {
    echo "CONN_ERR\n";
    exit(1);
}
$cityId = '13';
$cityCode = 'FRC78474';
$tables = ['franchise_stock', 'franchise_stock_wallet'];
foreach ($tables as $t) {
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
    if (!$res) {
        echo "ERROR: {$conn->error}\n";
        continue;
    }
    while ($row = $res->fetch_assoc()) {
        echo json_encode($row) . "\n";
    }
}

echo "TABLE=franchies_product_stock_request\n";
$sql = sprintf(
    "SELECT * FROM `franchies_product_stock_request` WHERE franchise_id = '%s' ORDER BY id",
    $conn->real_escape_string($cityId)
);
$res = $conn->query($sql);
if ($res) {
    while ($row = $res->fetch_assoc()) {
        echo json_encode($row) . "\n";
    }
} else {
    echo "ERROR: {$conn->error}\n";
}
$conn->close();
