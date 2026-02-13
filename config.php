<?php
// config.php - Updated with Auth Logic
session_start();

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'sales_dashboard');

function getDBConnection() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    return $conn;
}

// --- AUTHENTICATION HELPER FUNCTIONS ---

// 1. Check if user is logged in
function requireLogin() {
    if (!isset($_SESSION['user_id'])) {
        header("Location: login.php");
        exit();
    }
}

// 2. Check if current user has a specific permission
function hasPermission($permission_name) {
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['role_id'])) {
        return false;
    }
    
    // Super Admin (ID 1) always has permission
    if ($_SESSION['role_id'] == 1) return true;

    $conn = getDBConnection();
    $role_id = $_SESSION['role_id'];
    
    // Check if the role has the permission
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

// 3. Enforce permission on a page (Redirect if fail)
function requirePermission($permission_name) {
    requireLogin();
    if (!hasPermission($permission_name)) {
        die("<h1>403 Forbidden</h1><p>You do not have permission to access this page.</p><a href='index.php'>Back to Dashboard</a>");
    }
}
?>