<?php
ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

session_start();

header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'Method not allowed.']);
    exit;
}

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/mailer.php';

function fp_json(int $statusCode, bool $ok, string $message): void
{
    http_response_code($statusCode);
    echo json_encode([
        'ok' => $ok,
        'message' => $message,
    ]);
    exit;
}

function fp_validate_csrf(): bool
{
    $posted = $_POST['csrf_token'] ?? '';

    return is_string($posted)
        && $posted !== ''
        && isset($_SESSION['csrf_token'])
        && is_string($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $posted);
}

function fp_get_ip(): string
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    if (!is_string($ip) || $ip === '') {
        return 'unknown';
    }

    return substr($ip, 0, 45);
}

function fp_ensure_table(mysqli $conn): bool
{
    $sql = "CREATE TABLE IF NOT EXISTS password_reset_otps (
        id INT NOT NULL AUTO_INCREMENT,
        email VARCHAR(190) NOT NULL,
        otp_hash VARCHAR(255) NOT NULL,
        expires_at DATETIME NOT NULL,
        attempt_count INT NOT NULL DEFAULT 0,
        is_used TINYINT(1) NOT NULL DEFAULT 0,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        used_at DATETIME NULL,
        requested_ip VARCHAR(45) NULL,
        PRIMARY KEY (id),
        INDEX idx_reset_email (email),
        INDEX idx_reset_created (created_at)
    )";

    return mysqli_query($conn, $sql) === true;
}

function fp_is_rate_limited(mysqli $conn, string $email, string $ip): bool
{
    $emailCount = 0;
    $stmt = mysqli_prepare(
        $conn,
        "SELECT COUNT(*) FROM password_reset_otps WHERE email = ? AND created_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 HOUR)"
    );

    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 's', $email);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_bind_result($stmt, $emailCount);
        mysqli_stmt_fetch($stmt);
        mysqli_stmt_close($stmt);
    }

    $ipCount = 0;
    $stmtIp = mysqli_prepare(
        $conn,
        "SELECT COUNT(*) FROM password_reset_otps WHERE requested_ip = ? AND created_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 HOUR)"
    );

    if ($stmtIp) {
        mysqli_stmt_bind_param($stmtIp, 's', $ip);
        mysqli_stmt_execute($stmtIp);
        mysqli_stmt_bind_result($stmtIp, $ipCount);
        mysqli_stmt_fetch($stmtIp);
        mysqli_stmt_close($stmtIp);
    }

    return $emailCount >= 3 || $ipCount >= 8;
}

if (!fp_validate_csrf()) {
    fp_json(403, false, 'Security validation failed. Please refresh and try again.');
}

try {
    if (!fp_ensure_table($conn)) {
        error_log('Failed to create password_reset_otps table: ' . mysqli_error($conn));
        fp_json(500, false, 'A server error occurred. Please try again later.');
    }
} catch (mysqli_sql_exception $e) {
    error_log('Failed to create password_reset_otps table: ' . $e->getMessage());
    fp_json(500, false, 'A server error occurred. Please try again later.');
}

$actionRaw = filter_input(INPUT_POST, 'action', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
$emailRaw = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);

$action = is_string($actionRaw) ? trim($actionRaw) : '';
$email = is_string($emailRaw) ? trim($emailRaw) : '';

if ($action === '' || $email === '') {
    fp_json(400, false, 'Please provide a valid email address.');
}

if ($action === 'request_otp') {
    $ip = fp_get_ip();

    if (fp_is_rate_limited($conn, $email, $ip)) {
        fp_json(429, false, 'Too many requests. Please wait and try again later.');
    }

    $userExists = false;
    $name = 'User';

    $findUser = mysqli_prepare($conn, 'SELECT name FROM registration WHERE email = ? LIMIT 1');
    if ($findUser) {
        mysqli_stmt_bind_param($findUser, 's', $email);
        mysqli_stmt_execute($findUser);
        mysqli_stmt_bind_result($findUser, $dbName);
        if (mysqli_stmt_fetch($findUser)) {
            $userExists = true;
            if (is_string($dbName) && trim($dbName) !== '') {
                $name = trim($dbName);
            }
        }
        mysqli_stmt_close($findUser);
    }

    if (!$userExists) {
        fp_json(200, true, 'If your email is registered, an OTP has been sent.');
    }

    $otp = (string)random_int(100000, 999999);
    $otpHash = password_hash($otp, PASSWORD_DEFAULT);

    $invalidate = mysqli_prepare($conn, 'UPDATE password_reset_otps SET is_used = 1, used_at = UTC_TIMESTAMP() WHERE email = ? AND is_used = 0');
    if ($invalidate) {
        mysqli_stmt_bind_param($invalidate, 's', $email);
        mysqli_stmt_execute($invalidate);
        mysqli_stmt_close($invalidate);
    }

    $insert = mysqli_prepare(
        $conn,
        'INSERT INTO password_reset_otps (email, otp_hash, expires_at, requested_ip) VALUES (?, ?, DATE_ADD(UTC_TIMESTAMP(), INTERVAL 10 MINUTE), ?)'
    );

    if (!$insert) {
        error_log('OTP insert prepare failed: ' . mysqli_error($conn));
        fp_json(500, false, 'A server error occurred. Please try again later.');
    }

    mysqli_stmt_bind_param($insert, 'sss', $email, $otpHash, $ip);
    $insertOk = mysqli_stmt_execute($insert);
    mysqli_stmt_close($insert);

    if (!$insertOk) {
        error_log('OTP insert execute failed: ' . mysqli_error($conn));
        fp_json(500, false, 'A server error occurred. Please try again later.');
    }

    $subject = 'Velora Password Reset OTP';
    $htmlBody = '<h2>Password Reset Request</h2>' .
        '<p>Hello ' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . ',</p>' .
        '<p>Your OTP code is:</p>' .
        '<p style="font-size:24px;font-weight:bold;letter-spacing:2px;">' . htmlspecialchars($otp, ENT_QUOTES, 'UTF-8') . '</p>' .
        '<p>This code expires in 10 minutes.</p>' .
        '<p>If you did not request this, you can ignore this email.</p>';

    $textBody = "Password Reset Request\n\n" .
        "Hello {$name},\n" .
        "Your OTP code is: {$otp}\n" .
        "This code expires in 10 minutes.\n" .
        "If you did not request this, ignore this email.\n";

    $sendResult = sendSmtpEmail($email, $name, $subject, $htmlBody, $textBody);

    if (!$sendResult['ok']) {
        error_log('Password reset OTP mail failed: ' . $sendResult['error']);
        fp_json(500, false, 'Unable to send OTP right now. Please try again shortly.');
    }

    fp_json(200, true, 'OTP sent successfully. Please check your email.');
}

if ($action === 'reset_password') {
    $otp = filter_input(INPUT_POST, 'otp', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (!preg_match('/^\d{6}$/', $otp)) {
        fp_json(400, false, 'OTP must be exactly 6 digits.');
    }

    if (!is_string($newPassword) || strlen($newPassword) < 8) {
        fp_json(400, false, 'Password must be at least 8 characters long.');
    }

    if (!preg_match('/[A-Z]/', $newPassword) || !preg_match('/\d/', $newPassword) || !preg_match('/[^A-Za-z0-9]/', $newPassword)) {
        fp_json(400, false, 'Password must include an uppercase letter, number, and symbol.');
    }

    if ($newPassword !== $confirmPassword) {
        fp_json(400, false, 'Passwords do not match.');
    }

    $row = null;
    $findOtp = mysqli_prepare(
        $conn,
        'SELECT id, otp_hash, expires_at, attempt_count FROM password_reset_otps WHERE email = ? AND is_used = 0 ORDER BY id DESC LIMIT 1'
    );

    if (!$findOtp) {
        fp_json(500, false, 'A server error occurred. Please try again later.');
    }

    mysqli_stmt_bind_param($findOtp, 's', $email);
    mysqli_stmt_execute($findOtp);
    mysqli_stmt_bind_result($findOtp, $otpId, $otpHash, $expiresAt, $attemptCount);
    if (mysqli_stmt_fetch($findOtp)) {
        $row = [
            'id' => (int)$otpId,
            'otp_hash' => (string)$otpHash,
            'expires_at' => (string)$expiresAt,
            'attempt_count' => (int)$attemptCount,
        ];
    }
    mysqli_stmt_close($findOtp);

    if (!$row) {
        fp_json(400, false, 'No active OTP found for this email. Please request a new OTP.');
    }

    if ($row['attempt_count'] >= 5) {
        fp_json(429, false, 'Too many incorrect attempts. Please request a new OTP.');
    }

    $nowUtc = gmdate('Y-m-d H:i:s');
    if ($row['expires_at'] < $nowUtc) {
        $expire = mysqli_prepare($conn, 'UPDATE password_reset_otps SET is_used = 1, used_at = UTC_TIMESTAMP() WHERE id = ?');
        if ($expire) {
            mysqli_stmt_bind_param($expire, 'i', $row['id']);
            mysqli_stmt_execute($expire);
            mysqli_stmt_close($expire);
        }

        fp_json(400, false, 'OTP has expired. Please request a new one.');
    }

    if (!password_verify($otp, $row['otp_hash'])) {
        $inc = mysqli_prepare($conn, 'UPDATE password_reset_otps SET attempt_count = attempt_count + 1 WHERE id = ?');
        if ($inc) {
            mysqli_stmt_bind_param($inc, 'i', $row['id']);
            mysqli_stmt_execute($inc);
            mysqli_stmt_close($inc);
        }

        fp_json(400, false, 'Invalid OTP. Please try again.');
    }

    $newPasswordHash = password_hash($newPassword, PASSWORD_DEFAULT);

    $userName = 'User';
    $fetchName = mysqli_prepare($conn, 'SELECT name FROM registration WHERE email = ? LIMIT 1');
    if ($fetchName) {
        mysqli_stmt_bind_param($fetchName, 's', $email);
        mysqli_stmt_execute($fetchName);
        mysqli_stmt_bind_result($fetchName, $dbUserName);
        if (mysqli_stmt_fetch($fetchName) && is_string($dbUserName) && trim($dbUserName) !== '') {
            $userName = trim($dbUserName);
        }
        mysqli_stmt_close($fetchName);
    }

    mysqli_begin_transaction($conn);

    $updatePass = mysqli_prepare($conn, 'UPDATE registration SET password = ? WHERE email = ? LIMIT 1');
    if (!$updatePass) {
        mysqli_rollback($conn);
        fp_json(500, false, 'A server error occurred. Please try again later.');
    }

    mysqli_stmt_bind_param($updatePass, 'ss', $newPasswordHash, $email);
    $passOk = mysqli_stmt_execute($updatePass);
    $affected = mysqli_stmt_affected_rows($updatePass);
    mysqli_stmt_close($updatePass);

    if (!$passOk || $affected < 1) {
        mysqli_rollback($conn);
        fp_json(400, false, 'Account not found for this email.');
    }

    $consumeOtp = mysqli_prepare($conn, 'UPDATE password_reset_otps SET is_used = 1, used_at = UTC_TIMESTAMP() WHERE email = ? AND is_used = 0');
    if (!$consumeOtp) {
        mysqli_rollback($conn);
        fp_json(500, false, 'A server error occurred. Please try again later.');
    }

    mysqli_stmt_bind_param($consumeOtp, 's', $email);
    $consumeOk = mysqli_stmt_execute($consumeOtp);
    mysqli_stmt_close($consumeOtp);

    if (!$consumeOk) {
        mysqli_rollback($conn);
        fp_json(500, false, 'A server error occurred. Please try again later.');
    }

    mysqli_commit($conn);

    $confirmSubject = 'Your Velora Password Has Been Changed';
    $confirmHtml = '<h2>Password Changed Successfully</h2>' .
        '<p>Hello ' . htmlspecialchars($userName, ENT_QUOTES, 'UTF-8') . ',</p>' .
        '<p>Your Velora account password was just reset successfully.</p>' .
        '<p>If you made this change, no action is needed.</p>' .
        '<p><strong>If you did not make this change, please contact support immediately.</strong></p>';
    $confirmText = "Password Changed Successfully\n\n" .
        "Hello {$userName},\n" .
        "Your Velora account password was just reset successfully.\n" .
        "If you made this change, no action is needed.\n" .
        "If you did not make this change, please contact support immediately.\n";
    $confirmResult = sendSmtpEmail($email, $userName, $confirmSubject, $confirmHtml, $confirmText);
    if (!$confirmResult['ok']) {
        error_log('Password reset confirmation mail failed: ' . $confirmResult['error']);
    }

    fp_json(200, true, 'Password reset successful. You can now sign in.');
}

fp_json(400, false, 'Invalid action.');
