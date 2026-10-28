<?php
$conn = new mysqli('localhost', 'root', '', 'ind_new');
if ($conn->connect_errno) { echo "CONN_ERR\n"; exit(1); }
foreach (['franchise_stock','franchise_stock_wallet'] as $t) {
    echo "TABLE=$t\n";
    $hasCode = false;
    $res = $conn->query("SHOW COLUMNS FROM `" . $conn->real_escape_string($t) . "` LIKE 'franchise_code'");
    if ($res && $res->num_rows > 0) { $hasCode = true; }
    if ($hasCode) {
        $stmt = $conn->prepare("SELECT * FROM `$t` WHERE franchise_type = 'city' ORDER BY id");
    } else {
        $stmt = $conn->prepare("SELECT * FROM `$t` WHERE franchise_type = 'city' ORDER BY id");
    }
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        echo json_encode($row) . "\n";
    }
    $stmt->close();
}
$conn->close();
