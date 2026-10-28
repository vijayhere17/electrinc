<?php

define('SKIP_AUTH', true);
include 'includes/config.php';

if (is_logged_in()) {
    header('Location: ' . BASE_URL . 'party-summary.php');
} else {
    header('Location: ' . BASE_URL . 'login.php');
}

exit;
