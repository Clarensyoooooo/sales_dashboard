<?php
require_once 'config.php';

// --- ZOMBIE SESSION FIX START ---
if (isset($_SESSION['user_id'])) {
    $conn = getDBConnection();
    $check = $conn->prepare("SELECT id, role_id FROM users WHERE id = ?");
    $check->bind_param("i", $_SESSION['user_id']);
    $check->execute();
    $check->store_result();
    $check->bind_result($db_id, $db_role_id);
    $check->fetch();
    
    if ($check->num_rows > 0) {
        $check->close();
        $conn->close();
        if ($db_role_id == 3) { 
            header("Location: delivery_view.php");
        } else {
            header("Location: index.php");
        }
        exit;
    } else {
        $check->close();
        $conn->close();
        session_unset();
        session_destroy();
        session_start(); 
    }
}
// --- ZOMBIE SESSION FIX END ---

$error = '';

if (isset($_GET['error']) && $_GET['error'] == 'session_expired') {
    $error = "You have been logged out because your account was accessed from another device.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $conn = getDBConnection();
    $username = $_POST['username'];
    $password = $_POST['password'];

    $stmt = $conn->prepare("SELECT id, password, full_name, role_id FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        if (password_verify($password, $row['password'])) {
            
            // Generate Session Token
            $session_token = bin2hex(random_bytes(32));
            $update = $conn->prepare("UPDATE users SET session_token = ? WHERE id = ?");
            $update->bind_param("si", $session_token, $row['id']);
            $update->execute();
            $update->close();

            // Login Success
            $_SESSION['user_id'] = $row['id'];
            $_SESSION['full_name'] = $row['full_name'];
            $_SESSION['role_id'] = $row['role_id'];
            $_SESSION['session_token'] = $session_token; 

            // --- LOG ACTION HERE ---
            logAction('System Login', "User successfully logged in.");
            
            if ($row['role_id'] == 3) {
                header("Location: delivery_view.php");
            } else {
                header("Location: index.php");
            }
            exit;
            
            if ($row['role_id'] == 3) {
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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { 
            font-family: 'Inter', sans-serif; 
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            min-height: 100vh; 
            margin: 0; 
        }
        .login-card { 
            background: white; 
            border-radius: 1rem; 
            box-shadow: 0 10px 30px rgba(0,0,0,0.08); 
            width: 100%; 
            max-width: 420px; 
            overflow: hidden;
            border: 1px solid rgba(0,0,0,0.05);
        }
        .card-header-custom {
            background: #ffffff;
            padding: 30px 30px 10px 30px;
            text-align: center;
            border-bottom: none;
        }
        .brand-logo {
            max-height: 65px; /* Adjust based on your logo */
            width: auto;
            margin-bottom: 15px;
        }
        .form-control { 
            padding: 12px 15px; 
            border-radius: 0.5rem; 
            font-size: 15px;
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
        }
        .form-control:focus { 
            background-color: #ffffff;
            border-color: #0d6efd;
            box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.15); 
        }
        .input-group-text {
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            border-right: none;
            color: #6c757d;
        }
        .form-control.border-start-0 {
            border-left: none;
        }
        .btn-login { 
            padding: 12px; 
            font-weight: 600; 
            font-size: 16px; 
            border-radius: 0.5rem; 
            letter-spacing: 0.5px;
        }
    </style>
</head>
<body>
    <div class="login-card">
        <div class="card-header-custom">
            <img src="YOUR_LOGO_HERE.png" alt="NAM Supply Logo" class="brand-logo" onerror="this.style.display='none'">
            <h4 class="fw-bold text-dark mb-1">Welcome Back</h4>
            <p class="text-muted small">Please sign in to your account</p>
        </div>
        
        <div class="card-body p-4 pt-2">
            <?php if($error): ?>
                <div class="alert alert-danger d-flex align-items-center py-2" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <div class="small fw-semibold"><?php echo $error; ?></div>
                </div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="mb-3">
                    <label class="form-label small fw-bold text-secondary">Username</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-user"></i></span>
                        <input type="text" name="username" class="form-control border-start-0" required autofocus placeholder="Enter your username">
                    </div>
                </div>
                
                <div class="mb-4">
                    <label class="form-label small fw-bold text-secondary">Password</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-lock"></i></span>
                        <input type="password" name="password" class="form-control border-start-0" required placeholder="••••••••">
                    </div>
                </div>
                
                <button type="submit" class="btn btn-primary w-100 btn-login shadow-sm">
                    Sign In <i class="fas fa-arrow-right ms-1"></i>
                </button>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>