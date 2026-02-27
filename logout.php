<?php
session_start();

$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params['path'], $params['domain'],
        $params['secure'], $params['httponly']
    );
}
session_destroy();

// If called via AJAX or explicit json flag, return JSON
$isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');
$wantsJson = isset($_GET['json']) && $_GET['json'] == '1';
if ($isAjax || $wantsJson || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false)) {
    header('Content-Type: application/json');
    echo json_encode(['success' => true]);
    exit;
}

// Otherwise, redirect to a common entry page
header('Location: /webtechnologies/FoodBox/frontend/welcome/welcome_page.html');
exit;
