<?php

define('SKIP_AUTH', true);
include 'includes/config.php';

logout_user();

header('Location: ' . BASE_URL . 'login.php');
exit;
