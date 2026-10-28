<?php

error_reporting(E_ALL);
ini_set('display_errors', 0);

date_default_timezone_set('Asia/Kolkata');

define('BASE_URL', '/');

define('DB_HOST', 'localhost');
define('DB_USER', 'u408686689_bull123');       // ← from hPanel → Databases
define('DB_PASS', 'Bull@1112');    // ← password you set when creating DB
define('DB_NAME', 'u408686689_bull1112');    // ← exact name from phpMyAdmin

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if ($conn->connect_error) {
    die('Database connection failed: ' . $conn->connect_error);
}

$conn->set_charset("utf8mb4");

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db_schema.php';

ensure_users_table($conn);
ensure_app_tables($conn);
require_login();