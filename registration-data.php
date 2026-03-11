<?php
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
    exit;
}



require_once 'config.php';

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

$sql = "INSERT INTO registration ( name, email, countryCode, phone, role, adminKey, password) 
VALUES (?, ?, ?, ?, ?, ?, ?)";
$stmt = mysqli_stmt_init($conn);

try {
    if (!mysqli_stmt_prepare($stmt, $sql)) {
        throw new Exception("Error preparing statement: " . mysqli_error($conn));
    }

    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    mysqli_stmt_bind_param($stmt, "sssssss", $name, $email, $country_code, $phone, $role, $admin_key, $hashed_password);
    mysqli_stmt_execute($stmt);

    mysqli_stmt_close($stmt);
    mysqli_close($conn);
    header("Location: homepage.html?registered=1");
    exit;
} catch (mysqli_sql_exception $e) {
    if ((int)$e->getCode() === 1062) {
        $err_detail = $e->getMessage();
        $duplicate_field = getDuplicateFieldFromError($err_detail);

        if ($stmt) {
            mysqli_stmt_close($stmt);
        }
        mysqli_close($conn);
        header("Location: signup.html?error=duplicate&field=" . urlencode($duplicate_field));
        exit;
    }

    if ($stmt) {
        mysqli_stmt_close($stmt);
    }
    mysqli_close($conn);
    die("Database error: " . $e->getMessage());
} catch (Exception $e) {
    if ($stmt) {
        mysqli_stmt_close($stmt);
    }
    mysqli_close($conn);
    die($e->getMessage());
}

?>