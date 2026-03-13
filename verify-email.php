<?php
ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

require_once 'config.php';

function ensurePendingRegistrationsTable(mysqli $conn): void
{
    $sql = "CREATE TABLE IF NOT EXISTS pending_registrations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        email VARCHAR(191) NOT NULL,
        countryCode VARCHAR(10) NOT NULL,
        phone VARCHAR(32) NOT NULL,
        role VARCHAR(20) NOT NULL,
        adminKey VARCHAR(191) DEFAULT NULL,
        password VARCHAR(255) NOT NULL,
        verify_token VARCHAR(128) NOT NULL,
        expires_at DATETIME NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_pending_email (email),
        UNIQUE KEY uq_pending_phone (phone),
        UNIQUE KEY uq_pending_admin_key (adminKey),
        UNIQUE KEY uq_pending_verify_token (verify_token)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    if (!mysqli_query($conn, $sql)) {
        throw new Exception('Failed to prepare pending registrations table.');
    }
}

function cleanupExpiredPendingRegistrations(mysqli $conn): void
{
    mysqli_query($conn, "DELETE FROM pending_registrations WHERE expires_at < NOW()");
}

function renderResult(string $title, string $message, string $primaryHref, string $primaryLabel): void
{
    $safeTitle = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
    $safeMessage = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
    $safePrimaryHref = htmlspecialchars($primaryHref, ENT_QUOTES, 'UTF-8');
    $safePrimaryLabel = htmlspecialchars($primaryLabel, ENT_QUOTES, 'UTF-8');

    echo '<!doctype html>';
    echo '<html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">';
    echo '<title>Email Verification | Velora</title>';
    echo '<style>';
    echo 'body{margin:0;font-family:Arial,sans-serif;min-height:100vh;display:flex;align-items:center;justify-content:center;background:linear-gradient(120deg,#081420,#0f2740);padding:24px;}';
    echo '.card{max-width:520px;width:100%;background:#fff;border-radius:14px;padding:30px 26px;box-shadow:0 14px 34px rgba(0,0,0,.24);text-align:center;}';
    echo 'h1{margin:0 0 10px;color:#111827;font-size:24px;}';
    echo 'p{margin:0 0 24px;color:#4b5563;line-height:1.6;}';
    echo 'a{display:inline-block;background:#1d4ed8;color:#fff;text-decoration:none;padding:12px 18px;border-radius:8px;font-weight:bold;}';
    echo 'a:hover{background:#1e40af;}';
    echo '.muted{display:block;margin-top:14px;color:#6b7280;font-size:13px;}';
    echo '</style></head><body>';
    echo '<div class="card">';
    echo '<h1>' . $safeTitle . '</h1>';
    echo '<p>' . $safeMessage . '</p>';
    echo '<a href="' . $safePrimaryHref . '">' . $safePrimaryLabel . '</a>';
    echo '<span class="muted">If the link expired, register again to receive a new verification email.</span>';
    echo '</div></body></html>';
}

$token = $_GET['token'] ?? '';
$token = is_string($token) ? trim($token) : '';

if ($token === '' || preg_match('/^[a-f0-9]{64}$/', $token) !== 1) {
    mysqli_close($conn);
    renderResult('Invalid verification link', 'The verification link is invalid. Please sign up again to receive a valid link.', 'signup.html', 'Back to Sign Up');
    exit;
}

try {
    ensurePendingRegistrationsTable($conn);
    cleanupExpiredPendingRegistrations($conn);

    $stmt = mysqli_prepare($conn, "SELECT id, name, email, countryCode, phone, role, adminKey, password FROM pending_registrations WHERE verify_token = ? AND expires_at >= NOW() LIMIT 1");
    if (!$stmt) {
        throw new Exception('Could not prepare verification lookup.');
    }

    mysqli_stmt_bind_param($stmt, 's', $token);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $pendingId, $name, $email, $countryCode, $phone, $role, $adminKey, $passwordHash);

    if (!mysqli_stmt_fetch($stmt)) {
        mysqli_stmt_close($stmt);
        mysqli_close($conn);
        renderResult('Link expired or already used', 'This verification link is no longer valid. Please register again to get a fresh link.', 'signup.html', 'Create Account Again');
        exit;
    }
    mysqli_stmt_close($stmt);

    mysqli_begin_transaction($conn);

    $existsStmt = mysqli_prepare($conn, "SELECT 1 FROM registration WHERE email = ? LIMIT 1");
    if (!$existsStmt) {
        throw new Exception('Could not verify account status.');
    }
    mysqli_stmt_bind_param($existsStmt, 's', $email);
    mysqli_stmt_execute($existsStmt);
    mysqli_stmt_store_result($existsStmt);
    $alreadyCreated = mysqli_stmt_num_rows($existsStmt) > 0;
    mysqli_stmt_close($existsStmt);

    if (!$alreadyCreated) {
        $insertStmt = mysqli_prepare($conn, "INSERT INTO registration (name, email, countryCode, phone, role, adminKey, password) VALUES (?, ?, ?, ?, ?, ?, ?)");
        if (!$insertStmt) {
            throw new Exception('Could not create verified account.');
        }

        mysqli_stmt_bind_param($insertStmt, 'sssssss', $name, $email, $countryCode, $phone, $role, $adminKey, $passwordHash);
        mysqli_stmt_execute($insertStmt);
        mysqli_stmt_close($insertStmt);
    }

    $deleteStmt = mysqli_prepare($conn, "DELETE FROM pending_registrations WHERE id = ?");
    if ($deleteStmt) {
        mysqli_stmt_bind_param($deleteStmt, 'i', $pendingId);
        mysqli_stmt_execute($deleteStmt);
        mysqli_stmt_close($deleteStmt);
    }

    mysqli_commit($conn);
    mysqli_close($conn);
    header('Location: signin.html?registered=1');
    exit;
} catch (Throwable $e) {
    mysqli_rollback($conn);
    mysqli_close($conn);
    renderResult('Verification failed', 'We could not verify your account right now. Please try again or create a new account.', 'signup.html', 'Back to Sign Up');
    exit;
}
