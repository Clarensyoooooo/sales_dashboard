<?php
// users.php - Clean Bootstrap Version
require_once 'config.php';
requirePermission('manage_users');

$conn = getDBConnection();
$message = '';
$msgType = '';

// Handle Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'];

    // 1. CREATE USER
    if ($action === 'create_user') {
        $user = trim($_POST['username']);
        $pass = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $name = trim($_POST['full_name']);
        $role = (int)$_POST['role_id'];

        $stmt = $conn->prepare("INSERT INTO users (username, password, full_name, role_id) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("sssi", $user, $pass, $name, $role);
        
        if ($stmt->execute()) {
            $message = "User created successfully!";
            $msgType = "success";
        } else {
            $message = "Error: " . $conn->error;
            $msgType = "danger";
        }
        $stmt->close();
    } 
    
    // 2. EDIT USER
    elseif ($action === 'edit_user') {
        $user_id = (int)$_POST['user_id'];
        $user = trim($_POST['username']);
        $name = trim($_POST['full_name']);
        $role = (int)$_POST['role_id'];

        // If password field is not empty, update it. Otherwise, keep the old password.
        if (!empty($_POST['password'])) {
            $pass = password_hash($_POST['password'], PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET username = ?, full_name = ?, role_id = ?, password = ? WHERE id = ?");
            $stmt->bind_param("ssisi", $user, $name, $role, $pass, $user_id);
        } else {
            $stmt = $conn->prepare("UPDATE users SET username = ?, full_name = ?, role_id = ? WHERE id = ?");
            $stmt->bind_param("ssii", $user, $name, $role, $user_id);
        }

        if ($stmt->execute()) {
            $message = "User updated successfully!";
            $msgType = "success";
        } else {
            $message = "Error updating user: " . $conn->error;
            $msgType = "danger";
        }
        $stmt->close();
    }
    
    // 3. DELETE USER
    elseif ($action === 'delete_user') {
        $id = (int)$_POST['user_id'];
        if ($id != $_SESSION['user_id']) { // Prevent self-deletion
            $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
            $stmt->bind_param("i", $id);
            if ($stmt->execute()) {
                $message = "User deleted.";
                $msgType = "success";
            }
            $stmt->close();
        } else {
            $message = "You cannot delete your own account.";
            $msgType = "warning";
        }
    }
}

// Fetch Data
$users = $conn->query("SELECT u.*, r.name as role_name FROM users u LEFT JOIN roles r ON u.role_id = r.id ORDER BY u.id");
$roles = $conn->query("SELECT * FROM roles");
// Store roles in an array so we can reuse them in both modals
$roles_array = [];
while($r = $roles->fetch_assoc()) {
    $roles_array[] = $r;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>User Management - NAM Supply</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-light">

    <?php include 'navbar.php'; ?>

    <div class="container mt-5 pb-5">
        
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="fw-bold text-primary mb-0"><i class="fas fa-users-cog me-2"></i>User Management</h2>
                <p class="text-muted mb-0">Manage system access and roles.</p>
            </div>
            <button class="btn btn-primary shadow-sm" data-bs-toggle="modal" data-bs-target="#addUserModal">
                <i class="fas fa-user-plus me-2"></i>Add User
            </button>
        </div>

        <?php if($message): ?>
            <div class="alert alert-<?php echo $msgType; ?> alert-dismissible fade show shadow-sm" role="alert">
                <?php echo $message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="card shadow-sm border-0">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light text-muted small text-uppercase">
                            <tr>
                                <th class="ps-4 py-3">User</th>
                                <th>Role</th>
                                <th>Created</th>
                                <th class="text-end pe-4">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($u = $users->fetch_assoc()): ?>
                            <tr>
                                <td class="ps-4">
                                    <div class="d-flex align-items-center">
                                        <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-3 shadow-sm" style="width: 38px; height: 38px; font-weight: bold;">
                                            <?php echo strtoupper(substr($u['full_name'], 0, 1)); ?>
                                        </div>
                                        <div>
                                            <div class="fw-bold text-dark"><?php echo htmlspecialchars($u['full_name']); ?></div>
                                            <div class="small text-muted">@<?php echo htmlspecialchars($u['username']); ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <?php if($u['role_id'] == 1): ?>
                                        <span class="badge bg-warning text-dark border border-warning-subtle rounded-pill px-3 shadow-sm">
                                            <i class="fas fa-crown me-1"></i> <?php echo htmlspecialchars($u['role_name']); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-soft-primary text-primary border border-primary-subtle rounded-pill px-3">
                                            <?php echo htmlspecialchars($u['role_name'] ?? 'No Role'); ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-muted small">
                                    <?php echo date('M d, Y', strtotime($u['created_at'])); ?>
                                </td>
                                <td class="text-end pe-4">
                                    <button class="btn btn-outline-primary btn-sm me-1" onclick='openEditModal(<?php echo json_encode($u); ?>)' title="Edit User">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    
                                    <?php if($u['id'] != $_SESSION['user_id']): ?>
                                    <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this user?');">
                                        <input type="hidden" name="action" value="delete_user">
                                        <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                        <button class="btn btn-outline-danger btn-sm" title="Delete User">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </form>
                                    <?php else: ?>
                                        <span class="badge bg-light text-secondary border d-inline-block p-2" title="You cannot delete yourself">Current User</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="addUserModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content border-0 shadow">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title fw-bold"><i class="fas fa-user-plus me-2"></i>Create New User</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body bg-light">
                        <input type="hidden" name="action" value="create_user">
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold small text-muted text-uppercase">Full Name <span class="text-danger">*</span></label>
                            <input type="text" name="full_name" class="form-control" required placeholder="e.g. John Doe">
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold small text-muted text-uppercase">Username <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-white">@</span>
                                <input type="text" name="username" class="form-control" required placeholder="jdoe">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold small text-muted text-uppercase">Password <span class="text-danger">*</span></label>
                            <input type="password" name="password" class="form-control" required placeholder="••••••••">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold small text-muted text-uppercase">Role <span class="text-danger">*</span></label>
                            <select name="role_id" class="form-select" required>
                                <option value="">Select a role...</option>
                                <?php foreach($roles_array as $r): ?>
                                    <option value="<?php echo $r['id']; ?>"><?php echo htmlspecialchars($r['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer bg-white">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary fw-bold"><i class="fas fa-save me-1"></i> Create Account</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="editUserModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content border-0 shadow">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title fw-bold"><i class="fas fa-user-edit me-2"></i>Edit User</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body bg-light">
                        <input type="hidden" name="action" value="edit_user">
                        <input type="hidden" name="user_id" id="edit_user_id">
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold small text-muted text-uppercase">Full Name <span class="text-danger">*</span></label>
                            <input type="text" name="full_name" id="edit_full_name" class="form-control" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold small text-muted text-uppercase">Username <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-white">@</span>
                                <input type="text" name="username" id="edit_username" class="form-control" required>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold small text-muted text-uppercase">Role <span class="text-danger">*</span></label>
                            <select name="role_id" id="edit_role_id" class="form-select" required>
                                <?php foreach($roles_array as $r): ?>
                                    <option value="<?php echo $r['id']; ?>"><?php echo htmlspecialchars($r['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-2 p-3 bg-white border rounded">
                            <label class="form-label fw-bold small text-primary text-uppercase mb-1"><i class="fas fa-lock me-1"></i> Change Password</label>
                            <input type="password" name="password" id="edit_password" class="form-control" placeholder="••••••••">
                            <div class="form-text small text-muted mt-1"><i class="fas fa-info-circle me-1"></i>Leave blank to keep the current password.</div>
                        </div>
                    </div>
                    <div class="modal-footer bg-white">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary fw-bold"><i class="fas fa-save me-1"></i> Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Function to populate and show the Edit User modal
        function openEditModal(user) {
            document.getElementById('edit_user_id').value = user.id;
            document.getElementById('edit_full_name').value = user.full_name;
            document.getElementById('edit_username').value = user.username;
            document.getElementById('edit_role_id').value = user.role_id;
            document.getElementById('edit_password').value = ''; // Ensure password field is cleared
            
            var editModal = new bootstrap.Modal(document.getElementById('editUserModal'));
            editModal.show();
        }
    </script>
</body>
</html>