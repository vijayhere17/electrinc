<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function ensure_users_table($conn)
{
    $sql = "
    CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        name VARCHAR(100) NOT NULL,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ";

    $conn->query($sql);

    $result = $conn->query("SELECT COUNT(*) AS total FROM users");
    $row = $result->fetch_assoc();

    if ((int) $row['total'] === 0) {
        $username = 'admin';
        $password = password_hash('admin123', PASSWORD_DEFAULT);
        $name = 'Admin';

        $stmt = $conn->prepare(
            "INSERT INTO users (username, password, name) VALUES (?, ?, ?)"
        );
        $stmt->bind_param('sss', $username, $password, $name);
        $stmt->execute();
        $stmt->close();
    }
}

function is_logged_in()
{
    return !empty($_SESSION['user_id']);
}

function current_user_name()
{
    return $_SESSION['user_name'] ?? 'User';
}

function login_user($conn, $username, $password)
{
    $stmt = $conn->prepare(
        "SELECT id, username, password, name FROM users WHERE username = ? AND is_active = 1 LIMIT 1"
    );
    $stmt->bind_param('s', $username);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();

    if (!$user || !password_verify($password, $user['password'])) {
        return false;
    }

    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_name'] = $user['name'];
    $_SESSION['username'] = $user['username'];

    return true;
}

function logout_user()
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }

    session_destroy();
}

function require_login()
{
    if (defined('SKIP_AUTH') && SKIP_AUTH === true) {
        return;
    }

    if (is_logged_in()) {
        return;
    }

    $is_api = strpos($_SERVER['SCRIPT_NAME'], '/api/') !== false;

    if ($is_api) {
        header('Content-Type: application/json');
        http_response_code(401);
        echo json_encode([
            'status' => false,
            'message' => 'Please login to continue'
        ]);
        exit;
    }

    header('Location: ' . BASE_URL . 'login.php');
    exit;
}
