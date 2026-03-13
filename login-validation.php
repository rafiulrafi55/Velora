<?php
ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

session_start();

function validateCsrfToken(): bool
{
    $postedToken = $_POST['csrf_token'] ?? '';

    if (!is_string($postedToken) || $postedToken === '') {
        return false;
    }

    if (!isset($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
        return false;
    }

    return hash_equals($_SESSION['csrf_token'], $postedToken);
}

$authAction = filter_input(INPUT_POST, 'auth_action', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?? '';
$isRegistrationRequest = $authAction === 'register' || isset($_POST['name']) || isset($_POST['country_code']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: signin.html');
    exit;
}

if (!validateCsrfToken()) {
    if ($isRegistrationRequest) {
        header('Location: signup.html?error=security');
        exit;
    }

    header('Location: signin.html?error=security');
    exit;
}

require_once 'config.php';
require_once __DIR__ . '/mailer.php';

function appBaseUrl(): string
{
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $scriptDir = dirname($_SERVER['SCRIPT_NAME'] ?? '');
    $scriptDir = str_replace('\\', '/', $scriptDir);
    $scriptDir = rtrim($scriptDir, '/');

    if ($scriptDir === '.' || $scriptDir === '') {
        return $scheme . '://' . $host;
    }

    return $scheme . '://' . $host . $scriptDir;
}

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

function valueExists(mysqli $conn, string $sql, string $value): bool
{
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        throw new Exception('Failed to prepare duplicate check query.');
    }

    mysqli_stmt_bind_param($stmt, 's', $value);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_store_result($stmt);
    $exists = mysqli_stmt_num_rows($stmt) > 0;
    mysqli_stmt_close($stmt);

    return $exists;
}

function sendVerificationEmail(string $email, string $name, string $verifyLink): bool
{
    $subject = 'Verify your Velora account';
    $safeName = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
    $safeLink = htmlspecialchars($verifyLink, ENT_QUOTES, 'UTF-8');

    $htmlBody = '<!doctype html><html><body style="font-family:Arial,sans-serif;line-height:1.5;color:#111">'
        . '<h2 style="margin-bottom:8px;">Confirm your Velora account</h2>'
        . '<p>Hello ' . $safeName . ',</p>'
        . '<p>Click the link below to verify your email and complete account creation:</p>'
        . '<p><a href="' . $safeLink . '">' . $safeLink . '</a></p>'
        . '<p>This link expires in 24 hours.</p>'
        . '<p>If you did not request this account, you can ignore this email.</p>'
        . '</body></html>';

    $textBody = "Confirm your Velora account\n\n"
        . "Hello {$name},\n\n"
        . "Open this link to verify your email and complete account creation:\n"
        . "{$verifyLink}\n\n"
        . "This link expires in 24 hours.\n\n"
        . "If you did not request this account, you can ignore this email.";

    $sendResult = sendSmtpEmail($email, $name, $subject, $htmlBody, $textBody);

    if (!($sendResult['ok'] ?? false)) {
        error_log('Signup verification email send failed: ' . (string)($sendResult['error'] ?? 'unknown error'));
        return false;
    }

    return true;
}

function getDuplicateFieldFromError(string $errorMessage): string
{
    $field = 'field';

    if (preg_match("/for key '([^']+)'/i", $errorMessage, $matches) === 1) {
        $keyName = strtolower($matches[1]);

        if (strpos($keyName, '.') !== false) {
            $parts = explode('.', $keyName);
            $keyName = end($parts);
        }

        if (strpos($keyName, 'email') !== false) {
            $field = 'email';
        } elseif (strpos($keyName, 'adminkey') !== false || strpos($keyName, 'admin_key') !== false) {
            $field = 'admin key';
        } elseif (strpos($keyName, 'phone') !== false) {
            $field = 'phone number';
        }
    } else {
        $lowerMsg = strtolower($errorMessage);
        if (strpos($lowerMsg, 'email') !== false) {
            $field = 'email';
        } elseif (strpos($lowerMsg, 'adminkey') !== false || strpos($lowerMsg, 'admin_key') !== false) {
            $field = 'admin key';
        } elseif (strpos($lowerMsg, 'phone') !== false) {
            $field = 'phone number';
        }
    }

    return $field;
}

function handleLogin(mysqli $conn): void
{
    $email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL) ?? '';
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        header("Location: signin.html?error=invalid");
        exit;
    }

    $sql = "SELECT id, name, email, phone, countryCode, role, adminKey, password FROM registration WHERE email = ? LIMIT 1";
    $stmt = mysqli_stmt_init($conn);

    if (!mysqli_stmt_prepare($stmt, $sql)) {
        mysqli_close($conn);
        header("Location: signin.html?error=server");
        exit;
    }

    mysqli_stmt_bind_param($stmt, "s", $email);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $db_id, $db_name, $db_email, $db_phone, $db_countryCode, $db_role, $db_adminKey, $db_password);

    if (mysqli_stmt_fetch($stmt)) {
        $verified = password_verify($password, $db_password);

        if (!$verified && $password === $db_password) {
            mysqli_stmt_free_result($stmt);
            mysqli_stmt_close($stmt);
            $stmt = null;

            $new_hash = password_hash($password, PASSWORD_DEFAULT);
            $upd = mysqli_prepare($conn, "UPDATE registration SET password = ? WHERE id = ?");
            if ($upd) {
                mysqli_stmt_bind_param($upd, "si", $new_hash, $db_id);
                mysqli_stmt_execute($upd);
                mysqli_stmt_close($upd);
            }
            $verified = true;
        }

        if ($verified) {
            if ($stmt) {
                mysqli_stmt_close($stmt);
            }
            mysqli_close($conn);

            session_regenerate_id(true);
            $_SESSION['id'] = $db_id;
            $_SESSION['name'] = $db_name;
            $_SESSION['email'] = $db_email;
            $_SESSION['phone'] = $db_phone;
            $_SESSION['countryCode'] = $db_countryCode;

            if ($db_role === 'admin') {
                $_SESSION['adminKey'] = $db_adminKey;
            }

            header("Location: dashboard.php");
            exit;
        }
    }

    mysqli_stmt_close($stmt);
    mysqli_close($conn);
    header("Location: signin.html?error=invalid");
    exit;
}

function handleRegistration(mysqli $conn): void
{
    $name = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?? '';
    $email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL) ?? '';
    $country_code = filter_input(INPUT_POST, 'country_code', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?? '';
    $custom_code = filter_input(INPUT_POST, 'custom_code', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?? '';

    if ($country_code === 'custom') {
        $country_code = trim($custom_code);
    }

    $password = $_POST['password'] ?? '';
    $phone_raw = filter_input(INPUT_POST, 'phone', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?? '';
    $phone = preg_replace('/\D/', '', $phone_raw);
    $role = filter_input(INPUT_POST, 'role', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?? '';
    $admin_key = ($role === 'admin')
        ? (filter_input(INPUT_POST, 'admin_key', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?? '')
        : null;
    $terms = filter_input(INPUT_POST, 'terms', FILTER_VALIDATE_BOOLEAN) ?? false;
    $captcha_answer = filter_input(INPUT_POST, 'captcha_answer', FILTER_VALIDATE_INT);
    $captcha_expected = filter_input(INPUT_POST, 'captcha_expected', FILTER_VALIDATE_INT);

    $errors = [];
    if (empty($name)) $errors[] = 'Name is required';
    if (empty($email)) $errors[] = 'Valid email is required';
    if (empty($country_code)) $errors[] = 'Country code is required';
    if (!preg_match('/^\+\d{1,5}$/', $country_code)) $errors[] = 'Country code must look like +880';
    if (empty($password)) $errors[] = 'Password is required';
    if (!preg_match('/^\d{10}$/', $phone)) $errors[] = 'Phone must be exactly 10 digits';
    if ($role === 'admin' && empty($admin_key)) $errors[] = 'Administrator key is required';
    if (!$terms) $errors[] = 'You must accept the terms';
    if ($captcha_answer === false || $captcha_expected === false || $captcha_answer !== $captcha_expected) $errors[] = 'Security check answer is incorrect';

    if (!empty($errors)) {
        foreach ($errors as $error) {
            echo "Error: $error<br>";
        }
        mysqli_close($conn);
        exit;
    }

    try {
        ensurePendingRegistrationsTable($conn);
        cleanupExpiredPendingRegistrations($conn);

        if (valueExists($conn, "SELECT 1 FROM registration WHERE email = ? LIMIT 1", $email)) {
            mysqli_close($conn);
            header("Location: signup.html?error=duplicate&field=" . urlencode('email'));
            exit;
        }

        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $token = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', time() + (24 * 60 * 60));
        $hasPendingForEmail = valueExists($conn, "SELECT 1 FROM pending_registrations WHERE email = ? LIMIT 1", $email);

        if ($hasPendingForEmail) {
            $sql = "UPDATE pending_registrations
                    SET name = ?, countryCode = ?, phone = ?, role = ?, adminKey = ?, password = ?, verify_token = ?, expires_at = ?, created_at = NOW()
                    WHERE email = ?";
            $stmt = mysqli_prepare($conn, $sql);
            if (!$stmt) {
                throw new Exception('Error preparing pending update statement.');
            }

            mysqli_stmt_bind_param($stmt, "sssssssss", $name, $country_code, $phone, $role, $admin_key, $hashed_password, $token, $expiresAt, $email);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        } else {
            $sql = "INSERT INTO pending_registrations (name, email, countryCode, phone, role, adminKey, password, verify_token, expires_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = mysqli_prepare($conn, $sql);
            if (!$stmt) {
                throw new Exception('Error preparing pending insert statement.');
            }

            mysqli_stmt_bind_param($stmt, "sssssssss", $name, $email, $country_code, $phone, $role, $admin_key, $hashed_password, $token, $expiresAt);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }

        $verifyLink = appBaseUrl() . '/verify-email.php?token=' . urlencode($token);
        if (!sendVerificationEmail($email, $name, $verifyLink)) {
            $cleanupStmt = mysqli_prepare($conn, "DELETE FROM pending_registrations WHERE email = ?");
            if ($cleanupStmt) {
                mysqli_stmt_bind_param($cleanupStmt, 's', $email);
                mysqli_stmt_execute($cleanupStmt);
                mysqli_stmt_close($cleanupStmt);
            }

            mysqli_close($conn);
            header("Location: signup.html?error=mail");
            exit;
        }

        mysqli_close($conn);
        header("Location: signup.html?verify=sent");
        exit;
    } catch (mysqli_sql_exception $e) {
        if ((int)$e->getCode() === 1062) {
            $err_detail = $e->getMessage();
            $duplicate_field = getDuplicateFieldFromError($err_detail);

            mysqli_close($conn);
            header("Location: signup.html?error=duplicate&field=" . urlencode($duplicate_field));
            exit;
        }

        mysqli_close($conn);
        die("Database error: " . $e->getMessage());
    } catch (Exception $e) {
        mysqli_close($conn);
        die($e->getMessage());
    }
}

if ($isRegistrationRequest) {
    handleRegistration($conn);
}

handleLogin($conn);
?>