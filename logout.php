<?php
// We must include config.php to have access to logAction() and the DB connection
require_once 'config.php';

// Log the action while the session is still active
if(isset($_SESSION['user_id'])) {
    logAction('System Logout', "User logged out.");
}

// Unset all session variables
$_SESSION = array();

// Destroy the session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destroy the session
session_destroy();

// Redirect to login
header("Location: login.php");
exit;
?>