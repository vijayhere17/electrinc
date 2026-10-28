<?php

session_start();

// Step 1: Wipe all session variables
$_SESSION = [];

// Step 2: Delete the session cookie from browser
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// Step 3: Destroy server-side session
session_destroy();

// Step 4: Redirect to the branded franchise sign-in page
header("Location: index.php");
exit();