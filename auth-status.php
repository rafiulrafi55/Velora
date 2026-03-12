<?php
ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

session_start();

header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

$loggedIn = isset($_SESSION['id']) && !empty($_SESSION['id']);

$response = [
    'loggedIn' => $loggedIn,
    'name' => $loggedIn ? ($_SESSION['name'] ?? '') : ''
];

echo json_encode($response);
?>