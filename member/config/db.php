<?php
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Detect local even with ngrok
$isLocal = (
    $_SERVER['SERVER_NAME'] === 'localhost' ||
    $_SERVER['SERVER_NAME'] === '127.0.0.1' ||
    strpos($_SERVER['SERVER_NAME'], 'ngrok') !== false
);

if ($isLocal) {
    // LOCAL (XAMPP + NGROK)
    $host = "localhost";
    $username = "root";
    $password = "";
    $database = "ind_new";
} else {
    // LIVE SERVER
    $host = "localhost";
    $username = "u594386032_freshiya22";
    $password = "Freshiya@123";
    $database = "u594386032_freshiya22";
}

try {
    $conn = new mysqli($host, $username, $password, $database);
    $conn->set_charset("utf8mb4");
} catch (mysqli_sql_exception $e) {
    die("Database connection failed: " . $e->getMessage());
}
?>