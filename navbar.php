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

// Function to generate a unique, visible color based on the user's name
function getAvatarColor($name) {
    $hash = 0;
    for ($i = 0; $i < strlen($name); $i++) {
        $hash = ord($name[$i]) + (($hash << 5) - $hash);
    }
    $h = abs($hash) % 360;
    // HSL (Hue, Saturation, Lightness): 
    // 70% saturation and 55% lightness keeps colors vibrant and readable on dark/light modes
    return "hsl({$h}, 70%, 55%)"; 
}

$avatar_bg_color = getAvatarColor($user_name);
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
    
    /* Bigger Logo Styling */
    .navbar-logo {
        height: 50px; 
        width: auto;
        object-fit: contain;
        transition: height 0.3s ease;
    }

    /* Brand Text & Slogan Container */
    .brand-text-container {
        white-space: normal; /* Allows long company names to wrap instead of pushing the burger menu */
        line-height: 1.2;
    }

    .navbar-brand-text {
        font-size: 1.15rem; /* Desktop size */
    }

    /* Slogan Text Styling */
    .slogan-text {
        font-size: 0.75rem;
        color: #64748b; 
        font-weight: 500;
        margin-top: 2px;
    }
    
    /* Dynamic User Avatar */
    .avatar-circle {
        width: 32px; height: 32px; 
        color: #ffffff; 
        border-radius: 50%; display: flex; align-items: center; justify-content: center;
        font-weight: 700; font-size: 14px; margin-right: 8px;
        text-shadow: 0px 1px 2px rgba(0,0,0,0.3); 
        border: 2px solid rgba(255,255,255,0.4);
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
    
    /* Extra Small Mobile Fixes (Where it gets messy) */
    @media (max-width: 576px) {
        .navbar-logo {
            height: 38px; /* Slightly smaller logo to free up horizontal space */
        }
        .brand-text-container {
            max-width: 195px; /* Constrains the text width so the burger menu stays visible */
        }
        .navbar-brand-text {
            font-size: 0.9rem !important; /* Smaller text for mobile */
            line-height: 1.1;
        }
        .slogan-text {
            font-size: 0.65rem;
        }
    }
</style>

<nav class="navbar navbar-expand-lg navbar-light sticky-top py-2">
    <div class="container-fluid px-lg-4">
        
        <a class="navbar-brand d-flex align-items-center text-decoration-none" href="index.php">
            <img src="YOUR_LOGO_HERE.png" alt="NAM Supply Logo" class="navbar-logo me-2" onerror="this.style.display='none'">
            <div class="d-flex flex-column justify-content-center brand-text-container">
                <span class="fw-bold text-primary navbar-brand-text">NAM Builders and Supply Corp.</span>
                <span class="slogan-text">Built for Business. Powered by Supply.</span>
            </div>
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
                
                <?php if (function_exists('hasPermission') && hasPermission('manage_finance')): ?>
                    <li class="nav-item border-start ms-lg-2 ps-lg-3">
                        <a class="nav-link <?php echo isActive('finance.php'); ?>" href="finance.php">
                            <i class="fas fa-hand-holding-usd text-success me-1"></i> <span class="text-success fw-bold d-none d-sm-inline">Finance</span>
                            <span class="d-inline d-sm-none fw-bold text-success">Finance</span>
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
                        <div class="avatar-circle" style="background-color: <?php echo $avatar_bg_color; ?>;">
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

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>