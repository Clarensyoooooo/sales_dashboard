<?php
// navbar.php - Responsive Bootstrap Version
// Ensure this file is included AFTER config.php

$current_page = basename($_SERVER['PHP_SELF']);
$user_name = $_SESSION['full_name'] ?? 'User';
$role_id = $_SESSION['role_id'] ?? 0;

// Helper function to set active class
function isActive($page) {
    global $current_page;
    return $current_page == $page ? 'active fw-bold text-primary' : 'text-secondary';
}
?>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">

<style>
    /* Navbar Tweaks */
    .navbar { 
        font-family: 'Inter', sans-serif; 
        box-shadow: 0 2px 4px rgba(0,0,0,0.05); 
        background: rgba(255, 255, 255, 0.95);
        backdrop-filter: blur(10px);
    }
    .nav-link { 
        font-size: 0.9rem; 
        font-weight: 500; 
        transition: color 0.2s; 
    }
    .nav-link:hover { color: #2563eb !important; }
    
    /* Logo Styling for Responsiveness */
    .navbar-logo {
        height: 35px; /* Default desktop size */
        width: auto;
        object-fit: contain;
        transition: height 0.3s ease;
    }
    
    /* User Avatar */
    .avatar-circle {
        width: 32px; height: 32px; background-color: #f1f5f9; color: #475569;
        border-radius: 50%; display: flex; align-items: center; justify-content: center;
        font-weight: 700; font-size: 14px; margin-right: 8px; border: 1px solid #e2e8f0;
    }
    
    /* Mobile Fixes */
    @media (max-width: 991px) {
        .navbar-collapse {
            background: white;
            padding: 15px;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            margin-top: 10px;
        }
    }
    
    /* Extra Small Mobile Fixes for Logo */
    @media (max-width: 576px) {
        .navbar-logo {
            height: 28px; /* Slightly smaller on mobile to save space */
        }
        .navbar-brand-text {
            font-size: 1.1rem !important;
        }
    }
</style>

<nav class="navbar navbar-expand-lg navbar-light sticky-top py-2">
    <div class="container-fluid px-lg-4">
        
        <a class="navbar-brand d-flex align-items-center fw-bold text-primary" href="index.php" style="font-size: 1.25rem;">
            <img src="YOUR_LOGO_HERE.png" alt="NAM Supply Logo" class="navbar-logo me-2" onerror="this.style.display='none'">
        </a>

        <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav" aria-controls="mainNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="mainNav">
            
            <ul class="navbar-nav me-auto mb-2 mb-lg-0 gap-lg-2">
                
                <?php if (function_exists('hasPermission') && hasPermission('view_dashboard')): ?>
                    <li class="nav-item">
                        <a class="nav-link <?php echo isActive('index.php'); ?>" href="index.php">
                            <i class="fas fa-chart-pie me-1 d-lg-none"></i> Dashboard
                        </a>
                    </li>
                <?php endif; ?>

                <?php if (function_exists('hasPermission') && hasPermission('manage_sales')): ?>
                    <li class="nav-item">
                        <a class="nav-link <?php echo isActive('records.php'); ?>" href="records.php">
                            <i class="fas fa-clipboard-list me-1 d-lg-none"></i> Records
                        </a>
                    </li>
                    
                    <li class="nav-item">
                        <a class="nav-link <?php echo isActive('quotations.php'); ?>" href="quotations.php">
                            <i class="fas fa-file-invoice me-1 d-lg-none"></i> Quotations
                        </a>
                    </li>

                    <li class="nav-item">
                        <a class="nav-link <?php echo isActive('form.php'); ?>" href="form.php">
                            <i class="fas fa-plus-circle me-1 d-lg-none"></i> New Sale
                        </a>
                    </li>
                <?php endif; ?>

                <?php if (function_exists('hasPermission') && hasPermission('manage_products')): ?>
                    <li class="nav-item">
                        <a class="nav-link <?php echo isActive('products.php'); ?>" href="products.php">
                            <i class="fas fa-boxes me-1 d-lg-none"></i> Inventory
                        </a>
                    </li>
                <?php endif; ?>

                <?php if (function_exists('hasPermission') && hasPermission('view_logistics')): ?>
                    <li class="nav-item">
                        <a class="nav-link <?php echo isActive('delivery_view.php'); ?>" href="delivery_view.php">
                            <i class="fas fa-truck me-1 d-lg-none"></i> Logistics
                        </a>
                    </li>
                <?php endif; ?>

                <?php if ($role_id == 1): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle <?php echo (isActive('users.php') || isActive('roles.php') || isActive('import.php')) ? 'text-primary fw-bold' : ''; ?>" 
                           href="#" role="button" data-bs-toggle="dropdown">
                           <i class="fas fa-user-shield me-1 d-lg-none"></i> Admin
                        </a>
                        <ul class="dropdown-menu border-0 shadow-sm">
                            <li><a class="dropdown-item" href="logs.php"><i class="fas fa-history me-2 text-muted"></i>System Logs</a></li>
                            <li><a class="dropdown-item" href="users.php"><i class="fas fa-users me-2 text-muted"></i>Manage Users</a></li>
                            <li><a class="dropdown-item" href="roles.php"><i class="fas fa-user-tag me-2 text-muted"></i>Manage Roles</a></li>
                            <li><a class="dropdown-item" href="import.php"><i class="fas fa-file-import me-2 text-muted"></i>Import Data</a></li>
                        </ul>
                    </li>
                <?php endif; ?>
            </ul>

            <ul class="navbar-nav ms-auto align-items-center">
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle d-flex align-items-center text-dark" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <div class="avatar-circle">
                            <?php echo strtoupper(substr($user_name, 0, 1)); ?>
                        </div>
                        <span class="d-none d-sm-inline small fw-bold"><?php echo htmlspecialchars($user_name); ?></span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end border-0 shadow mt-2">
                        <li><span class="dropdown-header">Signed in as <br><strong><?php echo htmlspecialchars($user_name); ?></strong></span></li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <a class="dropdown-item text-danger fw-semibold" href="logout.php">
                                <i class="fas fa-sign-out-alt me-2"></i>Logout
                            </a>
                        </li>
                    </ul>
                </li>
            </ul>

        </div>
    </div>
</nav>