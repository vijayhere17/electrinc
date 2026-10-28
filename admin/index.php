<?php
session_start();

if (isset($_SESSION['member_id'])) {
    header('Location: dashboard.php');
    exit();
}

header('Location: login.php');
exit();
