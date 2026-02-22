<?php
// config.php - Master Configuration
session_start();

define('DB_HOST', 'localhost');
define('DB_USER', 'root'); // Change in production
define('DB_PASS', '');     // Change in production
define('DB_NAME', 'sales_dashboard');

// Better error handling for production
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

function getDBConnection() {
    try {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        $conn->set_charset("utf8mb4");
        return $conn;
    } catch (Exception $e) {
        error_log($e->getMessage());
        die("System error. Please contact the administrator.");
    }
}

// --- AUTHENTICATION & SINGLE DEVICE ENFORCEMENT ---
function requireLogin() {
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['session_token'])) {
        header("Location: login.php");
        exit();
    }

    $conn = getDBConnection();
    $stmt = $conn->prepare("SELECT session_token FROM users WHERE id = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $stmt->bind_result($db_token);
    $stmt->fetch();
    $stmt->close();
    $conn->close();

    // If token in DB doesn't match browser token, they logged in elsewhere
    if ($db_token !== $_SESSION['session_token']) {
        session_unset();
        session_destroy();
        header("Location: login.php?error=session_expired");
        exit();
    }
}

// --- ROLE BASED ACCESS CONTROL (RBAC) ---
function hasPermission($permission_name) {
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['role_id'])) return false;
    if ($_SESSION['role_id'] == 1) return true; // Super Admin bypass

    $conn = getDBConnection();
    $role_id = $_SESSION['role_id'];
    
    $sql = "SELECT COUNT(*) FROM role_permissions rp
            JOIN permissions p ON rp.permission_id = p.id
            WHERE rp.role_id = ? AND p.name = ?";
            
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("is", $role_id, $permission_name);
    $stmt->execute();
    $stmt->bind_result($count);
    $stmt->fetch();
    $stmt->close();
    $conn->close();

    return $count > 0;
}

function requirePermission($permission_name) {
    requireLogin();
    if (!hasPermission($permission_name)) {
        die("<h1>403 Forbidden</h1><p>You do not have permission.</p><a href='index.php'>Back to Dashboard</a>");
    }
}

// --- GLOBAL SYSTEM LOGGER ---
function logAction($action, $description = '') {
    if (!isset($_SESSION['user_id'])) return;
    
    $conn = getDBConnection();
    $user_id = $_SESSION['user_id'];
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
    
    $stmt = $conn->prepare("INSERT INTO system_logs (user_id, action, description, ip_address) VALUES (?, ?, ?, ?)");
    if ($stmt) {
        $stmt->bind_param("isss", $user_id, $action, $description, $ip_address);
        $stmt->execute();
        $stmt->close();
    }
    $conn->close();
}
?>