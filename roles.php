<?php
require_once 'config.php';
requireLogin();
requirePermission('manage_users'); // Only Admins can manage roles

$conn = getDBConnection();
$msg = '';
$msgType = '';

// --- POST HANDLERS ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    
    // 1. CREATE ROLE
    if ($_POST['action'] === 'create') {
        $name = trim($_POST['role_name']);
        $perms = $_POST['permissions'] ?? [];

        $stmt = $conn->prepare("INSERT INTO roles (name) VALUES (?)");
        $stmt->bind_param("s", $name);
        if ($stmt->execute()) {
            $new_role_id = $stmt->insert_id;
            
            // Insert checked permissions
            if (!empty($perms)) {
                $perm_stmt = $conn->prepare("INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)");
                foreach ($perms as $p_id) {
                    $perm_stmt->bind_param("ii", $new_role_id, $p_id);
                    $perm_stmt->execute();
                }
                $perm_stmt->close();
            }
            $msg = "Role '$name' created successfully!";
            $msgType = "success";
        } else {
            $msg = "Error creating role.";
            $msgType = "danger";
        }
        $stmt->close();
    }
    
    // 2. EDIT ROLE
    elseif ($_POST['action'] === 'edit') {
        $role_id = intval($_POST['role_id']);
        $name = trim($_POST['role_name']);
        $perms = $_POST['permissions'] ?? [];

        if ($role_id == 1) {
            $msg = "Super Admin role cannot be modified.";
            $msgType = "danger";
        } else {
            // Update Name
            $stmt = $conn->prepare("UPDATE roles SET name = ? WHERE id = ?");
            $stmt->bind_param("si", $name, $role_id);
            $stmt->execute();
            $stmt->close();

            // Clear old permissions and insert new ones
            $conn->query("DELETE FROM role_permissions WHERE role_id = $role_id");
            if (!empty($perms)) {
                $perm_stmt = $conn->prepare("INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)");
                foreach ($perms as $p_id) {
                    $perm_stmt->bind_param("ii", $role_id, $p_id);
                    $perm_stmt->execute();
                }
                $perm_stmt->close();
            }
            $msg = "Role '$name' updated successfully!";
            $msgType = "success";
        }
    }
    
    // 3. DELETE ROLE
    elseif ($_POST['action'] === 'delete') {
        $role_id = intval($_POST['delete_id']);
        
        if ($role_id == 1) {
            $msg = "Super Admin role cannot be deleted!";
            $msgType = "danger";
        } else {
            // Check if users are still using this role
            $check = $conn->query("SELECT COUNT(*) as count FROM users WHERE role_id = $role_id");
            $user_count = $check->fetch_assoc()['count'];
            
            if ($user_count > 0) {
                $msg = "Cannot delete this role! There are $user_count user(s) currently assigned to it. Reassign them first.";
                $msgType = "warning";
            } else {
                $conn->query("DELETE FROM role_permissions WHERE role_id = $role_id");
                $conn->query("DELETE FROM roles WHERE id = $role_id");
                $msg = "Role deleted successfully.";
                $msgType = "success";
            }
        }
    }
}

// Fetch all permissions for the checkboxes
$permissions = [];
$res = $conn->query("SELECT * FROM permissions");
while ($row = $res->fetch_assoc()) {
    $permissions[] = $row;
}

// Fetch all roles with user count and their permissions
$roles = [];
$res = $conn->query("
    SELECT r.id, r.name, COUNT(u.id) as user_count 
    FROM roles r 
    LEFT JOIN users u ON r.id = u.role_id 
    GROUP BY r.id 
    ORDER BY r.id ASC
");
while ($row = $res->fetch_assoc()) {
    // Get permissions for this specific role
    $p_res = $conn->query("SELECT p.name, p.id FROM role_permissions rp JOIN permissions p ON rp.permission_id = p.id WHERE rp.role_id = " . $row['id']);
    $role_perms = [];
    $role_perm_ids = [];
    while ($p_row = $p_res->fetch_assoc()) {
        $role_perms[] = $p_row['name'];
        $role_perm_ids[] = $p_row['id'];
    }
    $row['permissions_list'] = $role_perms;
    $row['permission_ids'] = $role_perm_ids;
    $roles[] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Roles - NAM Supply</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .perm-badge { font-size: 0.75rem; font-weight: 500; margin-right: 4px; margin-bottom: 4px; display: inline-block; }
        .checklist-box { max-height: 250px; overflow-y: auto; background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 6px; padding: 15px; }
    </style>
</head>
<body class="bg-light">
    <?php include 'navbar.php'; ?>

    <div class="container mt-4 pb-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h4 class="fw-bold text-dark mb-0"><i class="fas fa-user-tag text-primary me-2"></i>Role Management</h4>
            <button class="btn btn-primary fw-bold shadow-sm" onclick="openAddModal()">
                <i class="fas fa-plus me-1"></i> Create New Role
            </button>
        </div>

        <?php if ($msg): ?>
            <div class="alert alert-<?= $msgType ?> alert-dismissible fade show shadow-sm">
                <?= $msg ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="card border-0 shadow-sm">
            <div class="card-body p-0">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light text-muted small text-uppercase">
                        <tr>
                            <th class="ps-4">Role Name</th>
                            <th>Active Users</th>
                            <th>Assigned Permissions</th>
                            <th class="text-end pe-4">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($roles as $role): ?>
                        <tr>
                            <td class="ps-4 fw-bold">
                                <?= htmlspecialchars($role['name']) ?>
                                <?php if($role['id'] == 1) echo '<i class="fas fa-crown text-warning ms-1" title="Super Admin"></i>'; ?>
                            </td>
                            <td><span class="badge bg-secondary rounded-pill px-3"><?= $role['user_count'] ?> Users</span></td>
                            <td style="max-width: 400px;">
                                <?php if($role['id'] == 1): ?>
                                    <span class="badge bg-success perm-badge">ALL ACCESS Bypassed</span>
                                <?php else: ?>
                                    <?php if(empty($role['permissions_list'])): ?>
                                        <span class="text-muted small fst-italic">No permissions assigned</span>
                                    <?php else: ?>
                                        <?php foreach($role['permissions_list'] as $p): ?>
                                            <span class="badge bg-info text-dark perm-badge bg-opacity-25 border border-info"><?= htmlspecialchars($p) ?></span>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </td>
                            <td class="text-end pe-4">
                                <?php if($role['id'] != 1): ?>
                                    <button class="btn btn-sm btn-outline-primary me-1" onclick='openEditModal(<?= json_encode($role) ?>)' title="Edit"><i class="fas fa-edit"></i></button>
                                    <form method="POST" class="d-inline" onsubmit="return confirm('Delete this role? This cannot be undone.');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="delete_id" value="<?= $role['id'] ?>">
                                        <button class="btn btn-sm btn-outline-danger" title="Delete"><i class="fas fa-trash-alt"></i></button>
                                    </form>
                                <?php else: ?>
                                    <span class="text-muted small fst-italic">System Protected</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="modal fade" id="roleModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content border-0 shadow">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title fw-bold" id="modalTitle">Create Role</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body bg-light">
                        <input type="hidden" name="action" id="formAction" value="create">
                        <input type="hidden" name="role_id" id="role_id" value="">
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold small text-muted text-uppercase">Role Name</label>
                            <input type="text" name="role_name" id="role_name" class="form-control" required placeholder="e.g. Sales Manager">
                        </div>
                        
                        <label class="form-label fw-bold small text-muted text-uppercase mb-2"><i class="fas fa-tasks me-1"></i> Module Permissions</label>
                        <div class="checklist-box shadow-inner">
                            <?php foreach ($permissions as $perm): ?>
                            <div class="form-check mb-2">
                                <input class="form-check-input perm-checkbox" type="checkbox" name="permissions[]" value="<?= $perm['id'] ?>" id="perm_<?= $perm['id'] ?>">
                                <label class="form-check-label text-dark fw-medium" for="perm_<?= $perm['id'] ?>">
                                    <?= htmlspecialchars($perm['name']) ?>
                                </label>
                                <div class="form-text mt-0" style="font-size: 0.7rem;"><?= htmlspecialchars($perm['description']) ?></div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="modal-footer bg-white">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary fw-bold" id="btnSave"><i class="fas fa-save me-1"></i> Save Role</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const roleModal = new bootstrap.Modal(document.getElementById('roleModal'));
        
        function clearCheckboxes() {
            document.querySelectorAll('.perm-checkbox').forEach(cb => cb.checked = false);
        }

        function openAddModal() {
            document.getElementById('modalTitle').innerText = 'Create New Role';
            document.getElementById('formAction').value = 'create';
            document.getElementById('role_id').value = '';
            document.getElementById('role_name').value = '';
            document.getElementById('btnSave').innerHTML = '<i class="fas fa-plus me-1"></i> Create Role';
            clearCheckboxes();
            roleModal.show();
        }

        function openEditModal(role) {
            document.getElementById('modalTitle').innerText = 'Edit Role Permissions';
            document.getElementById('formAction').value = 'edit';
            document.getElementById('role_id').value = role.id;
            document.getElementById('role_name').value = role.name;
            document.getElementById('btnSave').innerHTML = '<i class="fas fa-save me-1"></i> Update Role';
            
            clearCheckboxes();
            
            // Check the boxes the role already has
            if(role.permission_ids) {
                role.permission_ids.forEach(id => {
                    let cb = document.getElementById('perm_' + id);
                    if(cb) cb.checked = true;
                });
            }
            roleModal.show();
        }
    </script>
</body>
</html>