<?php
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$serverName = strtolower((string)($_SERVER['SERVER_NAME'] ?? $_SERVER['HTTP_HOST'] ?? ''));
$isLocalEnvironment = (
    $serverName === 'localhost' ||
    $serverName === '127.0.0.1' ||
    $serverName === '::1' ||
    str_ends_with($serverName, '.local') ||
    str_ends_with($serverName, '.test') ||
    PHP_SAPI === 'cli'
);

if ($isLocalEnvironment) {
    $host = 'localhost';
    $username = 'root';
    $password = '';
    $database = 'ind_new';
} else {
    $host = 'localhost';
    $username = 'u594386032_freshiya22';
    $password = 'Freshiya@123';
    $database = 'u594386032_freshiya22';
}

try {
    $conn = new mysqli($host, $username, $password, $database);
    $conn->set_charset('utf8mb4');
} catch (mysqli_sql_exception $e) {
    die('Database connection failed: ' . $e->getMessage());
}
