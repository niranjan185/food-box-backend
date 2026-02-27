<?php
session_start();
require 'db_connect.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $phone = trim($_POST['phn']);
    $password = $_POST['password'];
    $user_type = $_POST['user_type'];

    // Select the correct table
    $table = ($user_type === 'customer') ? 'customer' : 'delivery';

    $stmt = $conn->prepare("SELECT id, password, full_name FROM $table WHERE phone=?");
    $stmt->bind_param("s", $phone);
    $stmt->execute();
    $stmt->store_result();
    $stmt->bind_result($id, $hashed_password, $full_name);

    if ($stmt->num_rows > 0) {
        $stmt->fetch();
        if (password_verify($password, $hashed_password)) {
            // Store session
            $_SESSION['user_id'] = $id;
            $_SESSION['user_phone'] = $phone;
            $_SESSION['full_name'] = $full_name;
            $_SESSION['user_type'] = $user_type;

            // ✅ Add this for delivery login
            if ($user_type === 'delivery') {
                $_SESSION['delivery_id'] = $id;
            }

            // Redirect based on user type
            if ($user_type === 'customer') {
                header('Location: /webtechnologies/foodbox/frontend/customer_dashboard/customer_dashboard.html');
            } else {
                header('Location: /webtechnologies/foodbox/frontend/delivery_dashboard/delivery_dashboard.html');
            }
            exit;
        } else {
            $_SESSION['error'] = "Invalid password";
            header('Location: /webtechnologies/foodbox/frontend/login/login_page.html');
            exit;
        }
    } else {
        $_SESSION['error'] = "Phone number not registered";
        header('Location: /webtechnologies/foodbox/frontend/login/login_page.html');
        exit;
    }

    $stmt->close();
    $conn->close();
}
?>
