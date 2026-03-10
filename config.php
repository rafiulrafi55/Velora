<?php
$host = 'localhost';
$database = 'velora';
$username = 'root';
$password_db = '';

$conn = mysqli_connect($host, $username, $password_db, $database);
if (mysqli_connect_errno()) {
    die("Failed to connect to MySQL: " . mysqli_connect_error());
}
?>