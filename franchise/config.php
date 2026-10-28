<?php
// franchise/config.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Load DB config
require_once __DIR__ . '/config/db.php';

// You can add franchise-specific config here in future

?>
