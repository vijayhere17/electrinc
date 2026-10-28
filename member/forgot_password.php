<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config/db.php';

$error = '';
$success = '';

if (!isset($conn) || !($conn instanceof mysqli)) {
    die('Database connection failed. Please check config/db.php');
}

$conn->set_charset('utf8mb4');

$isLocal = (
    ($_SERVER['SERVER_NAME'] ?? '') === 'localhost' ||
    ($_SERVER['SERVER_NAME'] ?? '') === '127.0.0.1' ||
    strpos($_SERVER['SERVER_NAME'] ?? '', 'ngrok') !== false
);

// SMTP settings can be populated for real email delivery.
// Example: use Gmail SMTP or your hosting provider's SMTP server.
$smtpConfig = [
    'host' => '',
    'port' => 587,
    'username' => '',
    'password' => '',
    'encryption' => 'tls',
    'from_name' => 'NETVEXA',
];

function readSmtpResponse($socket): string
{
    $response = '';
    while ($line = fgets($socket, 515)) {
        $response .= $line;
        if (substr($line, 3, 1) === ' ') {
            break;
        }
    }
    return $response;
}

function smtpCommand($socket, string $command, array $validCodes): bool
{
    fwrite($socket, $command . "\r\n");
    $response = readSmtpResponse($socket);
    $code = substr($response, 0, 3);
    return in_array($code, $validCodes, true);
}

function sendSmtpMail(string $to, string $subject, string $message, string $fromName, string $fromEmail, array $smtpConfig): bool
{
    $host = trim($smtpConfig['host'] ?? '');
    if ($host === '') {
        return false;
    }

    $port = (int)($smtpConfig['port'] ?? 587);
    $encryption = strtolower(trim((string)($smtpConfig['encryption'] ?? 'tls')));
    $username = trim((string)($smtpConfig['username'] ?? ''));
    $password = trim((string)($smtpConfig['password'] ?? ''));

    $transport = $port === 465 ? 'ssl://' : 'tcp://';
    $socket = stream_socket_client($transport . $host . ':' . $port, $errno, $errstr, 10);
    if (!$socket) {
        return false;
    }

    stream_set_timeout($socket, 10);
    if (!smtpCommand($socket, "EHLO " . ($fromName ?: 'localhost'), ['250'])) {
        fclose($socket);
        return false;
    }

    if ($port !== 465 && $encryption === 'tls') {
        if (!smtpCommand($socket, 'STARTTLS', ['220'])) {
            fclose($socket);
            return false;
        }
        if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            fclose($socket);
            return false;
        }
        if (!smtpCommand($socket, "EHLO " . ($fromName ?: 'localhost'), ['250'])) {
            fclose($socket);
            return false;
        }
    }

    if ($username !== '' && $password !== '') {
        if (!smtpCommand($socket, 'AUTH LOGIN', ['334'])) {
            fclose($socket);
            return false;
        }
        if (!smtpCommand($socket, base64_encode($username), ['334'])) {
            fclose($socket);
            return false;
        }
        if (!smtpCommand($socket, base64_encode($password), ['235'])) {
            fclose($socket);
            return false;
        }
    }

    if (!smtpCommand($socket, "MAIL FROM:<{$fromEmail}>", ['250'])) {
        fclose($socket);
        return false;
    }
    if (!smtpCommand($socket, "RCPT TO:<{$to}>", ['250', '251'])) {
        fclose($socket);
        return false;
    }
    if (!smtpCommand($socket, 'DATA', ['354'])) {
        fclose($socket);
        return false;
    }

    $headers = [];
    $headers[] = 'From: ' . $fromName . ' <' . $fromEmail . '>';
    $headers[] = 'To: ' . $to;
    $headers[] = 'Subject: ' . $subject;
    $headers[] = 'MIME-Version: 1.0';
    $headers[] = 'Content-Type: text/plain; charset=UTF-8';
    $headers[] = 'Content-Transfer-Encoding: 8bit';

    $body = implode("\r\n", $headers) . "\r\n\r\n" . $message . "\r\n.\r\n";
    fwrite($socket, $body);

    $response = readSmtpResponse($socket);
    if (substr($response, 0, 3) !== '250') {
        fclose($socket);
        return false;
    }

    smtpCommand($socket, 'QUIT', ['221']);
    fclose($socket);
    return true;
}

function sendEmail(string $to, string $subject, string $message, string $fromName, string $fromEmail, array $smtpConfig, bool $isLocal): bool
{
    $headers = [];
    $headers[] = 'From: ' . $fromName . ' <' . $fromEmail . '>';
    $headers[] = 'Reply-To: ' . $fromEmail;
    $headers[] = 'MIME-Version: 1.0';
    $headers[] = 'Content-Type: text/plain; charset=UTF-8';
    $headers[] = 'X-Mailer: PHP/' . phpversion();

    $headerString = implode("\r\n", $headers);
    $sent = @mail($to, $subject, $message, $headerString, '-f' . $fromEmail);
    if ($sent) {
        return true;
    }

    return sendSmtpMail($to, $subject, $message, $fromName, $fromEmail, $smtpConfig);
}

function columnExists(mysqli $conn, string $table, string $column): bool
{
    $stmt = $conn->prepare(
        "SELECT COUNT(*) AS c FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?"
    );

    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('ss', $table, $column);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return !empty($row['c']);
}

function firstExistingColumn(mysqli $conn, string $table, array $columns): ?string
{
    foreach ($columns as $column) {
        if (columnExists($conn, $table, $column)) {
            return $column;
        }
    }
    return null;
}

function existingColumns(mysqli $conn, string $table, array $columns): array
{
    return array_values(array_filter($columns, fn($column) => columnExists($conn, $table, $column)));
}

$customerCol = firstExistingColumn($conn, 'signup_users', ['customer_id', 'member_id', 'user_id']);
$emailCol    = firstExistingColumn($conn, 'signup_users', ['email', 'email_id']);
$mobileCol   = firstExistingColumn($conn, 'signup_users', ['mobile', 'mobile_no', 'phone']);
$passwordCols = existingColumns($conn, 'signup_users', ['password', 'password_hash', 'plain_password']);

if (!$customerCol) {
    die('Unable to determine member ID column in signup_users table.');
}

$formStep = 'request';
$otpSession = $_SESSION['forgot_password'] ?? [];

if (!empty($otpSession['expires_at']) && $otpSession['expires_at'] > time()) {
    $formStep = 'verify';
} else {
    unset($_SESSION['forgot_password']);
    $otpSession = [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $mode            = trim($_POST['mode'] ?? 'send_otp');
    $customer_id     = trim($_POST['customer_id'] ?? '');
    $email           = trim($_POST['email'] ?? '');
    $otp             = trim($_POST['otp'] ?? '');
    $new_password    = trim($_POST['new_password'] ?? '');
    $confirm_password = trim($_POST['confirm_password'] ?? '');

    if ($customer_id === '') {
        $error = 'Please enter your Customer ID.';
    } elseif ($email === '') {
        $error = 'Please enter your registered email address.';
    } elseif (!$emailCol) {
        $error = 'Email-based reset is not supported for your account.';
    } else {
        $selectCols = ["`{$customerCol}` AS customer_id", 'full_name'];
        if ($emailCol) {
            $selectCols[] = "`{$emailCol}` AS email";
        }

        $sql = 'SELECT id, ' . implode(', ', $selectCols) . ' FROM signup_users WHERE `' . $customerCol . '` = ? LIMIT 1';
        $stmt = $conn->prepare($sql);

        if (!$stmt) {
            $error = 'SQL error: ' . $conn->error;
        } else {
            $stmt->bind_param('s', $customer_id);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result && $result->num_rows === 1) {
                $user = $result->fetch_assoc();

                if (strcasecmp($user['email'] ?? '', $email) !== 0) {
                    $error = 'The email address does not match our records.';
                }

                if ($error === '') {
                    if ($mode === 'send_otp') {
                        unset($_SESSION['forgot_password']);
                        $otpSession = [];

                        $otpCode = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
                        $expiresAt = time() + 900; // 15 minutes

                        $subject = 'Password Reset OTP';
                        $message = "Dear {$user['full_name']},\n\n" .
                                   "Your password reset OTP is: {$otpCode}\n\n" .
                                   "Enter this code on the forgot password page to set a new password. The code expires in 15 minutes.\n\n" .
                                   "If you did not request a password reset, please ignore this email.\n\n" .
                                   "Regards,\n" .
                                   "NETVEXA Team";
                        $fromDomain = $_SERVER['SERVER_NAME'] ?? 'example.com';
                        $fromEmail = 'no-reply@' . preg_replace('/^www\./', '', $fromDomain);

                        $mailSent = sendEmail(
                            $email,
                            $subject,
                            $message,
                            'NETVEXA',
                            $fromEmail,
                            $smtpConfig,
                            $isLocal
                        );

                        if ($mailSent) {
                            $_SESSION['forgot_password'] = [
                                'customer_id' => $customer_id,
                                'email' => $email,
                                'otp' => $otpCode,
                                'expires_at' => $expiresAt,
                            ];
                            $success = 'OTP has been sent to your registered email address. Please check your email and enter the OTP below.';
                            $formStep = 'verify';
                            $otpSession = $_SESSION['forgot_password'];
                        } elseif ($isLocal) {
                            $_SESSION['forgot_password'] = [
                                'customer_id' => $customer_id,
                                'email' => $email,
                                'otp' => $otpCode,
                                'expires_at' => $expiresAt,
                            ];
                            $success = 'Email delivery is not configured on this local server. Use this OTP to continue: ' . $otpCode . '.';
                            $success .= ' Configure SMTP in forgot_password.php or php.ini for real email delivery.';
                            $formStep = 'verify';
                            $otpSession = $_SESSION['forgot_password'];
                        } else {
                            unset($_SESSION['forgot_password']);
                            $otpSession = [];
                            $formStep = 'request';
                            $error = 'Unable to send OTP email. Configure SMTP in php.ini or set SMTP settings in forgot_password.php for email delivery.';
                        }
                    } elseif ($mode === 'reset_password') {
                        $formStep = 'verify';

                        if (empty($otpSession)) {
                            $error = 'No OTP session found. Please request a new OTP.';
                        } elseif ($otpSession['customer_id'] !== $customer_id || strcasecmp($otpSession['email'], $email) !== 0) {
                            $error = 'Submitted customer details do not match the OTP request.';
                        } elseif ($otpSession['expires_at'] <= time()) {
                            unset($_SESSION['forgot_password']);
                            $error = 'OTP has expired. Please request a new one.';
                        } elseif ($otp === '') {
                            $error = 'Please enter the OTP sent to your email.';
                        } elseif ($otp !== $otpSession['otp']) {
                            $error = 'The OTP is incorrect. Please try again.';
                        } elseif ($new_password === '' || $confirm_password === '') {
                            $error = 'Please enter and confirm your new password.';
                        } elseif ($new_password !== $confirm_password) {
                            $error = 'New password and confirm password do not match.';
                        } elseif (strlen($new_password) < 6) {
                            $error = 'New password must be at least 6 characters.';
                        } else {
                            if (empty($passwordCols)) {
                                $error = 'No password field found in signup_users table.';
                            } else {
                                $updateParts = [];
                                $params      = [];
                                $types       = '';

                                foreach ($passwordCols as $pwCol) {
                                    if ($pwCol === 'plain_password') {
                                        $updateParts[] = "`{$pwCol}` = ?";
                                        $params[] = $new_password;
                                        $types .= 's';
                                    } else {
                                        $updateParts[] = "`{$pwCol}` = ?";
                                        $params[] = password_hash($new_password, PASSWORD_DEFAULT);
                                        $types .= 's';
                                    }
                                }

                                $params[] = $customer_id;
                                $types .= 's';

                                $updateSql = 'UPDATE signup_users SET ' . implode(', ', $updateParts) . ' WHERE `' . $customerCol . '` = ? LIMIT 1';
                                $updateStmt = $conn->prepare($updateSql);

                                if (!$updateStmt) {
                                    $error = 'SQL error: ' . $conn->error;
                                } else {
                                    $updateStmt->bind_param($types, ...$params);
                                    if ($updateStmt->execute()) {
                                        $success = 'Your password has been updated successfully. You can now login with the new password.';
                                        unset($_SESSION['forgot_password']);
                                        $formStep = 'request';
                                    } else {
                                        $error = 'Failed to update password: ' . $updateStmt->error;
                                    }
                                    $updateStmt->close();
                                }
                            }
                        }
                    } else {
                        $error = 'Invalid request mode.';
                    }
                }
            } else {
                $error = 'Member ID not found.';
            }

            $stmt->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-[#f1f2f4] flex items-center justify-center min-h-screen">
    <div class="w-full max-w-[620px] bg-[#f7f7f8] rounded-2xl p-10 mx-4 shadow-sm">
        <div class="flex flex-col items-center">
            <div class="bg-[#103258] rounded-xl px-5 py-3.5 inline-flex items-center">
                <img src="assets/electrinc-logo.png" alt="Electrinc" class="w-[150px] md:w-[170px] object-contain">
            </div>
            <h1 class="mt-4 text-[24px] md:text-[26px] font-bold text-black leading-none">Forgot Password</h1>
            <p class="mt-2 text-sm text-slate-600 text-center">Enter your Customer ID and registered contact details to reset your password.</p>
        </div>

        <?php if ($success !== ''): ?>
            <div class="mt-6 rounded-lg border border-green-200 bg-green-50 p-4 text-green-700">
                <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>

        <?php if ($error !== ''): ?>
            <div class="mt-6 rounded-lg border border-red-200 bg-red-50 p-4 text-red-700">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <form method="POST" class="mt-8 space-y-5">
            <?php $modeValue = $formStep === 'verify' ? 'reset_password' : 'send_otp'; ?>
            <input type="hidden" name="mode" value="<?php echo $modeValue; ?>">

            <div>
                <label class="block mb-2 text-sm font-medium text-slate-700">Customer ID</label>
                <input type="text" name="customer_id" value="<?php echo htmlspecialchars($_POST['customer_id'] ?? $otpSession['customer_id'] ?? ''); ?>" placeholder="Enter Customer ID" class="w-full rounded-lg border border-slate-300 px-4 py-3 text-sm outline-none focus:border-slate-500" required>
            </div>

            <?php if ($emailCol): ?>
                <div>
                    <label class="block mb-2 text-sm font-medium text-slate-700">Registered Email</label>
                    <input type="email" name="email" value="<?php echo htmlspecialchars($_POST['email'] ?? $otpSession['email'] ?? ''); ?>" placeholder="Enter registered email" class="w-full rounded-lg border border-slate-300 px-4 py-3 text-sm outline-none focus:border-slate-500" required>
                </div>
            <?php endif; ?>

            <?php if ($formStep === 'verify'): ?>
                <div>
                    <label class="block mb-2 text-sm font-medium text-slate-700">OTP</label>
                    <input type="text" name="otp" value="<?php echo htmlspecialchars($_POST['otp'] ?? ''); ?>" placeholder="Enter the OTP sent to your email" class="w-full rounded-lg border border-slate-300 px-4 py-3 text-sm outline-none focus:border-slate-500" required>
                </div>

                <div>
                    <label class="block mb-2 text-sm font-medium text-slate-700">New Password</label>
                    <input type="password" name="new_password" placeholder="Enter new password" class="w-full rounded-lg border border-slate-300 px-4 py-3 text-sm outline-none focus:border-slate-500" required>
                </div>

                <div>
                    <label class="block mb-2 text-sm font-medium text-slate-700">Confirm New Password</label>
                    <input type="password" name="confirm_password" placeholder="Confirm new password" class="w-full rounded-lg border border-slate-300 px-4 py-3 text-sm outline-none focus:border-slate-500" required>
                </div>
            <?php endif; ?>

            <div class="text-center">
                <button type="submit" class="bg-[#c7922f] text-white px-16 py-3 rounded-lg hover:bg-[#b78324] transition">
                    <?php echo $formStep === 'verify' ? 'Verify OTP & Reset Password' : 'Send OTP'; ?>
                </button>
            </div>
        </form>

        <div class="text-center mt-6 text-sm text-slate-600">
            Remembered your password? <a href="login.php" class="text-blue-500">Sign In</a>
        </div>
    </div>
</body>
</html>
