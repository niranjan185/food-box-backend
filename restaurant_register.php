<?php
session_start();
require 'db_connect.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $restaurant_name = trim($_POST['restaurant_name']);
    $owner_name = trim($_POST['owner_name']);
    $phone = trim($_POST['phone']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm-password'];
    $license_id = trim($_POST['license_id']);
    $food_commission_id = trim($_POST['food_commission_id']);

    if ($password !== $confirm_password) {
        $_SESSION['error'] = "Passwords do not match!";
        header("Location: /webtechnologies/foodbox/frontend/restaurant_register/restaurant_register.html");
        exit;
    }

    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    $stmt = $conn->prepare("INSERT INTO restaurant (restaurant_name, owner_name, phone, password, license_id, food_commission_id) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssss", $restaurant_name, $owner_name, $phone, $hashed_password, $license_id, $food_commission_id);

    if ($stmt->execute()) {
        $_SESSION['success'] = "Registration successful! Wait for verification.";
        header("Location: /webtechnologies/foodbox/frontend/restaurant_dashboard/restaurant_login.html");
        exit;
    } else {
        $_SESSION['error'] = "Error: " . $stmt->error;
        header("Location: /webtechnologies/foodbox/frontend/restaurant_register/restaurant_register.html");
        exit;
    }

    $stmt->close();
    $conn->close();
}
?>
