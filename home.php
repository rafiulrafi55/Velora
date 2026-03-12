<?php
ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

session_start();

if (isset($_SESSION['id']) && !empty($_SESSION['id'])) {
    header('Location: dashboard.php');
    exit;
}

header('Location: index.html');
exit;
?>