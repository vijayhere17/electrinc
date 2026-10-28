<?php
include __DIR__ . '/session_bootstrap.php';

if (!isset($_SESSION['customer_id']) && !isset($_SESSION['member_id'])) {
    header("Location: login.php");
    exit();
}
?>