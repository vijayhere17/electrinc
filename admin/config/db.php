<?php
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Detect environment (LOCAL + NGROK)
$isLocal = (
    in_array($_SERVER['SERVER_NAME'], ['localhost', '127.0.0.1']) ||
    strpos($_SERVER['SERVER_NAME'], 'ngrok') !== false
);

// Local server (XAMPP + NGROK)
if ($isLocal) {
    $host = "localhost";
    $username = "root";
    $password = "";
    $databases = ["ind_new"];
} else {
    // Live server
    $host = "localhost";
    $username = "u594386032_freshiya22";
    $password = "Freshiya@123";
    $databases = ["u594386032_freshiya22"];
}

// Try connecting to available DB
$conn = null;

foreach ($databases as $db) {
    try {
        $conn = new mysqli($host, $username, $password, $db);
        $conn->set_charset("utf8mb4");
        break;
    } catch (mysqli_sql_exception $e) {
        continue;
    }
}

// If no DB connected
if (!$conn) {
    die("Database connection failed: No valid database found.");
}
?>