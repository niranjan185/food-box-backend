<?php
$host = "localhost";
$db_user = "root";    // your MySQL username
$db_pass = "";        // your MySQL password
$db_name = "foodbox"; // your database name

$conn = new mysqli($host, $db_user, $db_pass, $db_name);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>
