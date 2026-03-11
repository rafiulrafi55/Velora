<?php
session_start();

require_once 'config.php';

$email    = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL) ?? '';
$password = $_POST['password'] ?? '';

if (empty($email) || empty($password)) {
    header("Location: signin.html?error=invalid");
    exit;
}

$sql  = "SELECT id, name, email, phone, countryCode, role, adminKey, password FROM registration WHERE email = ? LIMIT 1";
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
        $upd      = mysqli_prepare($conn, "UPDATE registration SET password = ? WHERE id = ?");
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

        $_SESSION['id']          = $db_id;
        $_SESSION['name']        = $db_name;
        $_SESSION['email']       = $db_email;
        $_SESSION['phone']       = $db_phone;
        $_SESSION['countryCode'] = $db_countryCode;

        if ($db_role === 'admin') {
            $_SESSION['adminKey'] = $db_adminKey;
            header("Location: admin.html");
            exit;
        }
        header("Location: user.html");
        exit;
    }
}

mysqli_stmt_close($stmt);
mysqli_close($conn);
header("Location: signin.html?error=invalid");
exit;









?>