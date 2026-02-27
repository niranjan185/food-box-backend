<?php
session_start();
require 'db_connect.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $phone = trim($_POST['phone']);
    $password = $_POST['password'];

    $stmt = $conn->prepare("SELECT id, password, restaurant_name, is_verified FROM restaurant WHERE phone=?");
    $stmt->bind_param("s", $phone);
    $stmt->execute();
    $stmt->store_result();
    $stmt->bind_result($id, $hashed_password, $restaurant_name, $is_verified);

    if ($stmt->num_rows > 0) {
        $stmt->fetch();
        if (!password_verify($password, $hashed_password)) {
            $_SESSION['error'] = "Invalid password";
        } 
        // elseif ($is_verified == 0) {
        //     $_SESSION['error'] = "Your account is not verified yet";
        // } 
        else {
            $_SESSION['restaurant_id'] = $id;
            $_SESSION['restaurant_name'] = $restaurant_name;
            header("Location: /webtechnologies/Foodbox/frontend/restaurant_dashboard/restaurant_dashboard.html");
            exit;
        }
    } else {
        $_SESSION['error'] = "Phone number not registered";
    }

    header("Location: /webtechnologies/foodbox/frontend/restaurant_login/restaurant_login.html");
    exit;
}
?>
