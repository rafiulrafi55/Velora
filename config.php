<?php
$host = 'sql12.freesqldatabase.com';
$database = 'sql12819673';
$username = 'sql12819673';
$password_db = 'DTkU4u5VXa';
$port = 3306;

$conn = mysqli_connect($host, $username, $password_db, $database, $port);
if (mysqli_connect_errno()) {
    die("Failed to connect to MySQL: " . mysqli_connect_error());
}
?>