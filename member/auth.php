<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


$isAdminPreview =
    !empty($_SESSION['admin_preview']) ||
    !empty($_SESSION['admin_view_mode']) ||
    !empty($_SESSION['preview_login']);


if ($isAdminPreview) {
    return;
}


if (!isset($_SESSION['customer_id']) || trim($_SESSION['customer_id']) === '') {
    header("Location: login.php");
    exit;
}