<?php
// Ensure this file is included AFTER config.php
$current_page = basename($_SERVER['PHP_SELF']);
$user_name = $_SESSION['full_name'] ?? 'User';
?>
<style>
    /* Navbar Styles */
    .app-navbar {
        background: #ffffff; border-bottom: 1px solid #e5e7eb; padding: 0 20px;
        position: sticky; top: 0; z-index: 1000; display: flex; justify-content: space-between;
        align-items: center; height: 64px; box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1); font-family: 'Inter', sans-serif;
    }
    .nav-brand { font-size: 20px; font-weight: 700; color: #1e40af; text-decoration: none; }
    .nav-links { display: flex; gap: 20px; height: 100%; align-items: center; }
    .nav-item { 
        text-decoration: none; color: #6b7280; font-weight: 500; font-size: 14px; 
        transition: all 0.2s; display: flex; align-items: center; gap: 6px;
    }
    .nav-item:hover { color: #1f2937; }
    .nav-item.active { color: #2563eb; font-weight: 600; }
    .user-menu { display: flex; align-items: center; gap: 15px; font-size: 14px; color: #374151; border-left: 1px solid #e5e7eb; padding-left: 15px; }
    .logout-btn { color: #dc2626; text-decoration: none; font-weight: 600; font-size: 13px; }
</style>

<nav class="app-navbar">
    <a href="index.php" class="nav-brand">🚀 NAM Supply</a>

    <div class="nav-links">
        <?php if (hasPermission('view_dashboard')): ?>
            <a href="index.php" class="nav-item <?php echo $current_page == 'index.php' ? 'active' : ''; ?>">
                📊 Dashboard
            </a>
        <?php endif; ?>

        <?php if (hasPermission('manage_products')): ?>
            <a href="products.php" class="nav-item <?php echo $current_page == 'products.php' ? 'active' : ''; ?>">
                📦 Inventory
            </a>
        <?php endif; ?>

        <?php if (hasPermission('manage_sales')): ?>
            <a href="records.php" class="nav-item <?php echo $current_page == 'records.php' ? 'active' : ''; ?>">
                📋 Records
            </a>
            <a href="form.php" class="nav-item <?php echo $current_page == 'form.php' ? 'active' : ''; ?>">
                ➕ New Sale
            </a>
        <?php endif; ?>

        <?php if (hasPermission('view_logistics')): ?>
            <a href="delivery_view.php" class="nav-item <?php echo $current_page == 'delivery_view.php' ? 'active' : ''; ?>">
                🚚 Logistics
            </a>
        <?php endif; ?>

        <?php if (hasPermission('manage_users')): ?>
            <a href="users.php" class="nav-item <?php echo $current_page == 'users.php' ? 'active' : ''; ?>">
                👥 Users
            </a>
        <?php endif; ?>

        <div class="user-menu">
            <span>👤 <?php echo htmlspecialchars($user_name); ?></span>
            <a href="logout.php" class="logout-btn">Logout</a>
        </div>
    </div>
</nav>