<?php
require_once 'config.php';
requireLogin();

// Only Super Admin can access
if ($_SESSION['role_id'] != 1) {
    header("Location: index.php");
    exit();
}

$conn = getDBConnection();

// Auto-create table if not exists
$conn->query("CREATE TABLE IF NOT EXISTS company_assignments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    company_name VARCHAR(255) UNIQUE,
    employee_name VARCHAR(100)
)");

$msg = '';

// Handle Delete
if (isset($_GET['del'])) {
    $id = intval($_GET['del']);
    $conn->query("DELETE FROM company_assignments WHERE id = $id");
    $msg = "Assignment removed successfully.";
}

// Handle Form Submit
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['company_name'])) {
    $company = $conn->real_escape_string($_POST['company_name']);
    $employee = $conn->real_escape_string($_POST['employee_name']);

    // Upsert (Update if exists, Insert if it doesn't)
    $sql = "INSERT INTO company_assignments (company_name, employee_name) 
            VALUES ('$company', '$employee')
            ON DUPLICATE KEY UPDATE employee_name = '$employee'";
    if ($conn->query($sql)) {
        $msg = "Successfully assigned $company to $employee.";
    } else {
        $msg = "Error saving assignment.";
    }
}

// Fetch all unique companies from sales
$companies = [];
$res = $conn->query("SELECT DISTINCT company FROM sales WHERE company != '' ORDER BY company");
while ($r = $res->fetch_assoc()) {
    $companies[] = $r['company'];
}

// Fetch current assignments
$assignments = [];
$res = $conn->query("SELECT * FROM company_assignments ORDER BY company_name");
if ($res) {
    while ($r = $res->fetch_assoc()) {
        $assignments[] = $r;
    }
}

// DYNAMIC RBAC CONNECTION: Fetch real users from the database!
$employees = [];
$res_users = $conn->query("SELECT full_name FROM users ORDER BY full_name");
if ($res_users) {
    while ($u = $res_users->fetch_assoc()) {
        $employees[] = $u['full_name'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Account Managers - NAM Supply</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <?php include 'navbar.php'; ?>

    <div class="container mt-4 pb-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h4 class="fw-bold"><i class="fas fa-users-cog me-2 text-primary"></i>Account Manager Setup</h4>
            <a href="index.php" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i> Back to Dashboard</a>
        </div>

        <?php if ($msg): ?>
            <div class="alert alert-success alert-dismissible fade show shadow-sm border-0">
                <i class="fas fa-check-circle me-2"></i><?= $msg ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row g-4">
            <div class="col-md-4">
                <div class="card shadow-sm border-0">
                    <div class="card-header bg-primary text-white fw-bold">
                        <i class="fas fa-plus-circle me-2"></i>Assign Company
                    </div>
                    <div class="card-body bg-white">
                        <form method="POST">
                            <div class="mb-3">
                                <label class="form-label fw-bold text-muted small">Select Company</label>
                                <select name="company_name" class="form-select border-secondary-subtle" required>
                                    <option value="">-- Choose Company --</option>
                                    <?php foreach ($companies as $c): ?>
                                        <option value="<?= htmlspecialchars($c) ?>"><?= htmlspecialchars($c) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-4">
                                <label class="form-label fw-bold text-muted small">Assign to Employee</label>
                                <select name="employee_name" class="form-select border-secondary-subtle" required>
                                    <option value="">-- Choose Account Manager --</option>
                                    <?php foreach ($employees as $emp): ?>
                                        <option value="<?= htmlspecialchars($emp) ?>"><?= htmlspecialchars($emp) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <button type="submit" class="btn btn-primary w-100 fw-bold">Save Assignment</button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-md-8">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-body p-0 table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light text-muted small uppercase">
                                <tr>
                                    <th class="ps-3 py-3">Client Company</th>
                                    <th>Account Manager</th>
                                    <th class="text-end pe-3">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($assignments)): ?>
                                    <tr><td colspan="3" class="text-center py-5 text-muted"><i class="fas fa-folder-open mb-2 d-block fs-3"></i>No assignments found. Add one on the left.</td></tr>
                                <?php endif; ?>
                                <?php foreach ($assignments as $a): 
                                    // Default color
                                    $bg = '#cbd5e1'; 
                                    
                                    // Match colors to specific names
                                    if(strpos($a['employee_name'], 'Anne') !== false) $bg = '#ec4899';
                                    if(strpos($a['employee_name'], 'Cherry') !== false) $bg = '#ef4444';
                                    if(strpos($a['employee_name'], 'Glenda') !== false) $bg = '#8b5cf6';
                                    if(strpos($a['employee_name'], 'Ivy') !== false) $bg = '#10b981';
                                    if(strpos($a['employee_name'], 'Ally') !== false) $bg = '#f59e0b';
                                ?>
                                    <tr>
                                        <td class="ps-3 fw-bold text-dark"><?= htmlspecialchars($a['company_name']) ?></td>
                                        <td>
                                            <span class="badge rounded-pill" style="background-color: <?= $bg ?>;">
                                                <i class="fas fa-user-circle me-1"></i><?= htmlspecialchars($a['employee_name']) ?>
                                            </span>
                                        </td>
                                        <td class="text-end pe-3">
                                            <a href="assignments.php?del=<?= $a['id'] ?>" class="btn btn-sm btn-outline-danger shadow-sm" onclick="return confirm('Are you sure you want to remove this assignment?');" title="Remove Link">
                                                <i class="fas fa-unlink"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>