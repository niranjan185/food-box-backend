<?php
session_start();
require 'db_connect.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['fullname']);
    $phone = trim($_POST['phn']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm-password'];
    $user_type = $_POST['user_type'];

    if ($password !== $confirm_password) {
        $_SESSION['error'] = "Passwords do not match!";
        header("Location: /webtechnologies/foodbox/frontend/register/register.html"); 
        exit;
    }

    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    // Decide table based on user type
    $table = ($user_type === 'customer') ? 'customer' : 'delivery';

    // Prepare insert
    $stmt = $conn->prepare("INSERT INTO $table (full_name, phone, password) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $full_name, $phone, $hashed_password);

    if ($stmt->execute()) {
        $_SESSION['success'] = "Registration successful! Please login.";
        header("Location: /webtechnologies/foodbox/frontend/login/login_page.html");
        exit;
    } else {
        $_SESSION['error'] = "Error: " . $stmt->error;
        header("Location: /webtechnologies/foodbox/frontend/register/register.html");
        exit;
    }

    $stmt->close();
    $conn->close();
}
?>
