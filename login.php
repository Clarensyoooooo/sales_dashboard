<?php
require_once 'config.php';

// --- ZOMBIE SESSION FIX START ---
// If the user is already logged in, verify they still exist in the database.
if (isset($_SESSION['user_id'])) {
    $conn = getDBConnection();
    // Check if the user exists and is active
    $check = $conn->prepare("SELECT id, role_id FROM users WHERE id = ?"); // Removed is_active check for simplicity, or keep if you have that column
    // If you used the SQL I provided, there is no is_active column by default, so I removed it here to prevent errors.
    // If you added an is_active column, change query to: "SELECT id, role_id FROM users WHERE id = ? AND is_active = 1"
    
    $check->bind_param("i", $_SESSION['user_id']);
    $check->execute();
    $check->store_result();
    
    // Bind the result to get the role_id for redirection
    $check->bind_result($db_id, $db_role_id);
    $check->fetch();
    
    if ($check->num_rows > 0) {
        // User exists! Proceed to redirect based on role.
        $check->close();
        $conn->close();
        
        if ($db_role_id == 3) { // Driver Role
            header("Location: delivery_view.php");
        } else {
            header("Location: index.php");
        }
        exit;
    } else {
        // User DOES NOT exist (Zombie Session). Destroy it.
        $check->close();
        $conn->close();
        session_unset();
        session_destroy();
        session_start(); 
        // Script continues to show the login form below...
    }
}
// --- ZOMBIE SESSION FIX END ---

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $conn = getDBConnection();
    $username = $_POST['username'];
    $password = $_POST['password'];

    // Secure query
    $stmt = $conn->prepare("SELECT id, password, full_name, role_id FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        if (password_verify($password, $row['password'])) {
            // Login Success
            $_SESSION['user_id'] = $row['id'];
            $_SESSION['full_name'] = $row['full_name'];
            $_SESSION['role_id'] = $row['role_id'];
            
            // Redirect based on role
            if ($row['role_id'] == 3) { // Driver
                header("Location: delivery_view.php");
            } else {
                header("Location: index.php");
            }
            exit;
        } else {
            $error = "Invalid password.";
        }
    } else {
        $error = "User not found.";
    }
    $stmt->close();
    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - NAM Supply</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background: #f3f4f6; display: flex; align-items: center; justify-content: center; height: 100vh; margin: 0; }
        .login-card { background: white; padding: 40px; border-radius: 16px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); width: 100%; max-width: 400px; }
        .brand { color: #1e40af; font-size: 24px; font-weight: 700; text-align: center; margin-bottom: 30px; }
        .form-group { margin-bottom: 20px; }
        label { display: block; margin-bottom: 8px; font-size: 14px; color: #374151; font-weight: 600; }
        input { width: 100%; padding: 12px; border: 1px solid #e5e7eb; border-radius: 8px; font-size: 14px; box-sizing: border-box; }
        input:focus { outline: none; border-color: #2563eb; }
        button { width: 100%; padding: 12px; background: #2563eb; color: white; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; font-size: 16px; transition: background 0.2s; }
        button:hover { background: #1e40af; }
        .error { background: #fee2e2; color: #991b1b; padding: 10px; border-radius: 8px; margin-bottom: 20px; text-align: center; font-size: 14px; }
    </style>
</head>
<body>
    <div class="login-card">
        <div class="brand">🚀 NAM Supply</div>
        
        <?php if($error): ?>
            <div class="error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="form-group">
                <label>Username</label>
                <input type="text" name="username" required autofocus placeholder="Enter username">
            </div>
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" required placeholder="Enter password">
            </div>
            <button type="submit">Sign In</button>
        </form>
    </div>
</body>
</html>