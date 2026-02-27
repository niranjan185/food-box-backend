<?php
// Unified session configuration for all backend endpoints
// Important: Use a consistent session name and cookie flags so the browser
// sends the cookie back for all admin/support API requests.

// Secure cookie flags
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_httponly', 1);
// Set SameSite=Lax so cookies are sent on same-site navigations and XHR
ini_set('session.cookie_samesite', 'Lax');

// Set cookie secure flag only if HTTPS
$__is_https = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
ini_set('session.cookie_secure', $__is_https ? 1 : 0);

// Give session a clear, consistent name
session_name('FOODBOX_ADMIN_SESSION');

// Start session once
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
