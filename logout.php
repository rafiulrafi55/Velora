<?php
ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

session_start();

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

$redirect = filter_input(INPUT_GET, 'redirect', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?? 'signin.html';
$allowedRedirects = ['index.html', 'signin.html', 'dashboard.html', 'dashboard.php'];

if (!in_array($redirect, $allowedRedirects, true)) {
    $redirect = 'signin.html';
}

header('Location: ' . $redirect);
exit;
?>