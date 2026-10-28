<?php
$conn = new mysqli('localhost', 'root', '', 'ind_new');
if ($conn->connect_errno) { echo "CONN_ERR\n"; exit(1); }
$cityCode = 'FRC62042';
$stmt = $conn->prepare("SELECT * FROM mobile_franchies_holder WHERE city = ?");
$stmt->bind_param('s', $cityCode);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    echo json_encode($row) . "\n";
}
$stmt->close();
$conn->close();
