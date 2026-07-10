<?php
/**
 * FILE: logout.php
 * Referenced by the sidebar "Logout" link in admin.php, cashier.php, and
 * now customer's header, but did not exist in the uploaded project.
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
}
session_destroy();
header('Location: index.php');
exit;