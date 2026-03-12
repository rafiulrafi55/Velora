<?php
ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

$host = 'sql12.freesqldatabase.com';
$database = 'sql12819673';
$username = 'sql12819673';
$password_db = 'DTkU4u5VXa';
$port = 3306;

$conn = mysqli_connect($host, $username, $password_db, $database, $port);
if (mysqli_connect_errno()) {
    error_log('Database connection failed: ' . mysqli_connect_error());
    die('A server error occurred. Please try again later.');
}
?>