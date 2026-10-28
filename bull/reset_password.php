<?php

define('SKIP_AUTH', true);
require_once __DIR__ . '/includes/config.php';

$users = [
    ['username' => 'admin', 'password' => 'admin123', 'name' => 'Admin'],
    ['username' => 'bull', 'password' => 'bull123', 'name' => 'Bull User'],
];

foreach ($users as $user) {
    $hash = password_hash($user['password'], PASSWORD_DEFAULT);

    $check = $conn->prepare('SELECT id FROM users WHERE username = ? LIMIT 1');
    $check->bind_param('s', $user['username']);
    $check->execute();
    $exists = $check->get_result()->fetch_assoc();
    $check->close();

    if ($exists) {
        $stmt = $conn->prepare(
            'UPDATE users SET password = ?, name = ?, is_active = 1 WHERE username = ?'
        );
        $stmt->bind_param('sss', $hash, $user['name'], $user['username']);
    } else {
        $stmt = $conn->prepare(
            'INSERT INTO users (username, password, name, is_active) VALUES (?, ?, ?, 1)'
        );
        $stmt->bind_param('sss', $user['username'], $hash, $user['name']);
    }

    $stmt->execute();
    $stmt->close();
}

header('Content-Type: text/plain; charset=utf-8');
echo "Login credentials reset successfully.\n\n";
echo "Use any of these accounts:\n";
echo "1) Username: admin   Password: admin123\n";
echo "2) Username: bull    Password: bull123\n\n";
echo "Login URL: http://localhost/bull/login.php\n";
