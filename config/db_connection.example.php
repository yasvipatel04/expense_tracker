<?php
$host = "127.0.0.1";
$username = "root";
$password = "YOUR PASSWORD HERE";
$database = "expense_tracker";

$con = mysqli_connect($host, $username, $password, $database);

if (!$con) {
    die("Database connection failed: " . mysqli_connect_error());
}

mysqli_set_charset($con, "utf8mb4");
?>