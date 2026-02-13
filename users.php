<?php
require_once 'config.php';
requirePermission('manage_users'); // PROTECT THIS PAGE

$conn = getDBConnection();
$message = '';

// Handle Add/Edit/Delete
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'];

    if ($action === 'create_user') {
        $user = $_POST['username'];
        $pass = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $name = $_POST['full_name'];
        $role = $_POST['role_id'];

        $stmt = $conn->prepare("INSERT INTO users (username, password, full_name, role_id) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("sssi", $user, $pass, $name, $role);
        if ($stmt->execute()) $message = "User created successfully!";
        else $message = "Error: " . $conn->error;
    
    } elseif ($action === 'delete_user') {
        $id = $_POST['user_id'];
        // Prevent self-delete
        if ($id != $_SESSION['user_id']) {
            $conn->query("DELETE FROM users WHERE id = $id");
            $message = "User deleted.";
        }
    }
}

// Fetch Users
$users = $conn->query("SELECT u.*, r.name as role_name FROM users u LEFT JOIN roles r ON u.role_id = r.id ORDER BY u.id");

// Fetch Roles for Dropdown
$roles = $conn->query("SELECT * FROM roles");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>User Management - NAM Supply</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* Reusing your clean CSS */
        body { font-family: 'Inter', sans-serif; background: #f3f4f6; padding: 0; margin: 0; }
        .container { max-width: 1000px; margin: 20px auto; padding: 0 20px; }
        .card { background: white; padding: 25px; border-radius: 12px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
        .btn { padding: 8px 16px; border-radius: 6px; border:none; cursor:pointer; font-weight:600; font-size:13px; }
        .btn-primary { background: #2563eb; color: white; }
        .btn-danger { background: #fee2e2; color: #991b1b; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #eee; }
        th { color: #6b7280; font-size: 12px; text-transform: uppercase; }
        .alert { background: #dcfce7; color: #166534; padding: 15px; border-radius: 8px; margin-bottom: 20px; }
        
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); align-items: center; justify-content: center; }
        .modal.active { display: flex; }
        .modal-content { background: white; padding: 30px; border-radius: 12px; width: 400px; }
        input, select { width: 100%; padding: 10px; margin-bottom: 15px; border: 1px solid #ddd; border-radius: 6px; }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>

    <div class="container">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
            <h1 style="font-size:24px; color:#1e40af;">👥 User Management</h1>
            <button onclick="openModal()" class="btn btn-primary">➕ Add User</button>
        </div>

        <?php if($message): ?>
            <div class="alert"><?php echo $message; ?></div>
        <?php endif; ?>

        <div class="card">
            <table>
                <thead>
                    <tr>
                        <th>Username</th>
                        <th>Full Name</th>
                        <th>Role</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($u = $users->fetch_assoc()): ?>
                    <tr>
                        <td style="font-weight:600;"><?php echo $u['username']; ?></td>
                        <td><?php echo $u['full_name']; ?></td>
                        <td>
                            <span style="background:#eff6ff; color:#2563eb; padding:4px 8px; border-radius:4px; font-size:12px; font-weight:600;">
                                <?php echo $u['role_name']; ?>
                            </span>
                        </td>
                        <td><?php echo date('M d, Y', strtotime($u['created_at'])); ?></td>
                        <td>
                            <?php if($u['id'] != $_SESSION['user_id']): ?>
                            <form method="POST" style="display:inline;" onsubmit="return confirm('Delete user?');">
                                <input type="hidden" name="action" value="delete_user">
                                <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                <button class="btn btn-danger">🗑️</button>
                            </form>
                            <?php else: ?>
                                <span style="color:#999; font-size:12px;">(You)</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="modal" id="userModal">
        <div class="modal-content">
            <h2>Add New User</h2>
            <form method="POST">
                <input type="hidden" name="action" value="create_user">
                
                <label>Username</label>
                <input type="text" name="username" required>
                
                <label>Password</label>
                <input type="password" name="password" required>
                
                <label>Full Name</label>
                <input type="text" name="full_name" required>
                
                <label>Role</label>
                <select name="role_id">
                    <?php 
                    $roles->data_seek(0); // Reset pointer
                    while($r = $roles->fetch_assoc()): 
                    ?>
                        <option value="<?php echo $r['id']; ?>"><?php echo $r['name']; ?></option>
                    <?php endwhile; ?>
                </select>
                
                <div style="text-align:right;">
                    <button type="button" onclick="closeModal()" class="btn" style="background:#eee;">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save User</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openModal() { document.getElementById('userModal').classList.add('active'); }
        function closeModal() { document.getElementById('userModal').classList.remove('active'); }
    </script>
</body>
</html>