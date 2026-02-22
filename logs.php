<?php
require_once 'config.php';
requireLogin();

// Only Super Admin or someone with specific permission should see logs
if ($_SESSION['role_id'] != 1) {
    header("Location: index.php");
    exit();
}

$conn = getDBConnection();

// Fetch the last 500 logs to prevent the page from lagging over time
$sql = "SELECT l.*, u.full_name, u.username, r.name as role_name 
        FROM system_logs l 
        LEFT JOIN users u ON l.user_id = u.id 
        LEFT JOIN roles r ON u.role_id = r.id
        ORDER BY l.created_at DESC LIMIT 500";
$logs = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>System Logs - NAM Supply</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f8f9fa; }
        .log-card { border-radius: 12px; border: 1px solid rgba(0,0,0,0.05); box-shadow: 0 4px 15px rgba(0,0,0,0.03); background: #fff; }
        .table th { font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.5px; color: #6c757d; }
        .table td { vertical-align: middle; font-size: 0.9rem; }
        .action-badge { font-weight: 600; padding: 5px 10px; border-radius: 6px; font-size: 0.75rem; }
        .avatar-sm { width: 30px; height: 30px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-weight: bold; font-size: 12px; }
    </style>
</head>
<body>

    <?php include 'navbar.php'; ?>

    <div class="container mt-5 pb-5" style="max-width: 1100px;">
        
        <div class="d-flex justify-content-between align-items-end mb-4">
            <div>
                <h3 class="fw-bold text-dark mb-1"><i class="fas fa-history text-primary me-2"></i>Audit Trail</h3>
                <p class="text-muted mb-0 small">Showing the latest 500 system activities</p>
            </div>
            <div style="width: 300px;">
                <input type="text" id="logSearch" class="form-control" placeholder="Search logs..." onkeyup="filterLogs()">
            </div>
        </div>

        <div class="log-card p-0 overflow-hidden">
            <div class="table-responsive" style="max-height: 70vh;">
                <table class="table table-hover mb-0" id="logsTable">
                    <thead class="table-light sticky-top">
                        <tr>
                            <th class="ps-4" width="20%">Date & Time</th>
                            <th width="20%">User</th>
                            <th width="15%">Action</th>
                            <th width="35%">Description</th>
                            <th class="text-end pe-4" width="10%">IP Addr</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($row = $logs->fetch_assoc()): 
                            // Color code the actions
                            $actionColor = 'bg-secondary text-white';
                            if (strpos(strtolower($row['action']), 'create') !== false) $actionColor = 'bg-success text-white';
                            if (strpos(strtolower($row['action']), 'delete') !== false) $actionColor = 'bg-danger text-white';
                            if (strpos(strtolower($row['action']), 'edit') !== false || strpos(strtolower($row['action']), 'update') !== false) $actionColor = 'bg-info text-dark';
                            if (strpos(strtolower($row['action']), 'delivery') !== false) $actionColor = 'bg-warning text-dark';
                        ?>
                        <tr class="log-row">
                            <td class="ps-4">
                                <div class="fw-bold text-dark"><?= date('M d, Y', strtotime($row['created_at'])) ?></div>
                                <div class="small text-muted"><?= date('h:i A', strtotime($row['created_at'])) ?></div>
                            </td>
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="avatar-sm bg-primary text-white me-2 shadow-sm">
                                        <?= strtoupper(substr($row['full_name'] ?? 'U', 0, 1)) ?>
                                    </div>
                                    <div>
                                        <div class="fw-bold text-dark" style="font-size: 0.85rem;"><?= htmlspecialchars($row['full_name'] ?? 'System / Deleted User') ?></div>
                                        <div class="small text-muted" style="font-size: 0.7rem;"><?= htmlspecialchars($row['role_name'] ?? 'Unknown') ?></div>
                                    </div>
                                </div>
                            </td>
                            <td><span class="action-badge <?= $actionColor ?>"><?= htmlspecialchars($row['action']) ?></span></td>
                            <td class="text-muted"><?= htmlspecialchars($row['description']) ?></td>
                            <td class="text-end pe-4 small font-monospace text-muted"><?= $row['ip_address'] ?></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
    function filterLogs() {
        let input = document.getElementById("logSearch").value.toLowerCase();
        let rows = document.querySelectorAll(".log-row");

        rows.forEach(row => {
            if (row.innerText.toLowerCase().includes(input)) {
                row.style.display = "";
            } else {
                row.style.display = "none";
            }
        });
    }
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>