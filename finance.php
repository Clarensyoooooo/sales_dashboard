<?php 
require_once 'config.php'; 
requireLogin(); 

// --- 1. AUTO-HEAL DATABASE: ADD PAYMENT COLUMNS & RBAC PERMISSION ---
$conn = getDBConnection();

$checkCol = $conn->query("SHOW COLUMNS FROM sales LIKE 'payment_status'");
if($checkCol && $checkCol->num_rows == 0) {
    $conn->query("ALTER TABLE sales ADD COLUMN payment_status VARCHAR(20) DEFAULT 'Pending' AFTER due_date");
}

$checkDatePaid = $conn->query("SHOW COLUMNS FROM sales LIKE 'date_paid'");
if($checkDatePaid && $checkDatePaid->num_rows == 0) {
    $conn->query("ALTER TABLE sales ADD COLUMN date_paid DATETIME DEFAULT NULL AFTER payment_status");
}

// Automatically add the new permission to the database if it doesn't exist yet
$permCheck = $conn->query("SELECT id FROM permissions WHERE name = 'manage_finance'");
if($permCheck && $permCheck->num_rows == 0) {
    $conn->query("INSERT IGNORE INTO permissions (name) VALUES ('manage_finance')");
}
$conn->close();

// --- 2. ENFORCE FINANCE PERMISSION ---
// Admins (Role 1) automatically bypass this. Other roles need the box checked in roles.php!
requirePermission('manage_finance'); 

$conn = getDBConnection();

// --- 3. AJAX HANDLER FOR STATUS UPDATES ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'toggle_status') {
    header('Content-Type: application/json');
    $id = intval($_POST['id']);
    $new_status = $conn->real_escape_string($_POST['status']);
    
    // Automatically set the timestamp when marked as paid, or clear it if reverted to pending
    $date_paid_sql = ($new_status === 'Paid') ? "NOW()" : "NULL";
    
    $sql = "UPDATE sales SET payment_status = '$new_status', date_paid = $date_paid_sql WHERE id = $id";
    
    if($conn->query($sql)) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => $conn->error]);
    }
    exit;
}

// --- 4. KPI CALCULATIONS ---
$kpiSql = "SELECT 
            SUM(CASE WHEN payment_status = 'Paid' THEN total_nam_amount ELSE 0 END) as collected,
            SUM(CASE WHEN payment_status = 'Pending' THEN total_nam_amount ELSE 0 END) as outstanding,
            SUM(CASE WHEN payment_status = 'Pending' AND due_date < CURRENT_DATE() AND due_date IS NOT NULL AND due_date != '0000-00-00' THEN total_nam_amount ELSE 0 END) as overdue
           FROM sales 
           WHERE date_delivered IS NOT NULL AND date_delivered != '0000-00-00'";
$kpi = $conn->query($kpiSql)->fetch_assoc();

// --- 5. FETCH UNIQUE COMPANIES & TERMS FOR FILTERS ---
$companies = [];
$terms = [];
$filterData = $conn->query("SELECT DISTINCT company, payment_term FROM sales WHERE date_delivered IS NOT NULL AND date_delivered != '0000-00-00'");
if ($filterData) {
    while($r = $filterData->fetch_assoc()) {
        if(!empty($r['company'])) $companies[] = trim($r['company']);
        if(!empty($r['payment_term'])) $terms[] = trim($r['payment_term']);
    }
}
$companies = array_unique($companies);
$terms = array_unique($terms);
sort($companies);
sort($terms);

// --- 6. FETCH DELIVERED RECORDS ---
$sql = "SELECT id, date, company, po_number, item, quantity_requested, total_nam_amount, payment_term, due_date, date_delivered, payment_status, date_paid, sales_invoice_no 
        FROM sales 
        WHERE date_delivered IS NOT NULL AND date_delivered != '0000-00-00'
        ORDER BY 
            CASE WHEN payment_status = 'Pending' THEN 0 ELSE 1 END, 
            due_date ASC, 
            date_delivered DESC";
$result = $conn->query($sql);
$records = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $records[] = $row;
    }
}
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Finance & Collections - NAM Supply</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .kpi-card { border-radius: 12px; border: none; box-shadow: 0 4px 6px rgba(0,0,0,0.05); transition: transform 0.2s; }
        .kpi-card:hover { transform: translateY(-3px); }
        .table-responsive { max-height: 65vh; overflow-y: auto; }
        thead th { position: sticky; top: 0; background: #fff; z-index: 1; box-shadow: 0 1px 2px rgba(0,0,0,0.1); }
        
        /* Alert Row Colors */
        .row-overdue { background-color: #ffe6e6 !important; }
        .row-neardue { background-color: #fff4cc !important; }
        .row-paid { opacity: 0.65; background-color: #f1f8f5 !important; }
        
        .filter-label { font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: #6c757d; margin-bottom: 4px; display: block; }
    </style>
</head>
<body>

    <?php include 'navbar.php'; ?>

    <div class="container-fluid mt-4 px-4 pb-5">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h4 class="fw-bold text-dark mb-0"><i class="fas fa-hand-holding-usd text-success me-2"></i>Finance & Collections Tracker</h4>
            <span class="text-muted small"><i class="fas fa-truck text-primary me-1"></i> Showing strictly Delivered items.</span>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="card kpi-card bg-white border-start border-success border-4 h-100">
                    <div class="card-body">
                        <div class="text-muted text-uppercase fw-bold small mb-1">Total Collected (Paid)</div>
                        <h2 class="mb-0 fw-bold text-success">₱<?= number_format($kpi['collected'] ?? 0, 2) ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card kpi-card bg-white border-start border-primary border-4 h-100">
                    <div class="card-body">
                        <div class="text-muted text-uppercase fw-bold small mb-1">Outstanding Receivables</div>
                        <h2 class="mb-0 fw-bold text-primary">₱<?= number_format($kpi['outstanding'] ?? 0, 2) ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card kpi-card bg-white border-start border-danger border-4 h-100">
                    <div class="card-body">
                        <div class="text-danger text-uppercase fw-bold small mb-1"><i class="fas fa-exclamation-triangle me-1"></i> Overdue Collections</div>
                        <h2 class="mb-0 fw-bold text-danger">₱<?= number_format($kpi['overdue'] ?? 0, 2) ?></h2>
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm border-0">
            
            <div class="card-header bg-white py-3 border-bottom">
                <div class="row g-3 align-items-end">
                    <div class="col-md-3">
                        <label class="filter-label">Search</label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-light"><i class="fas fa-search text-muted"></i></span>
                            <input type="text" id="searchInput" class="form-control shadow-none" placeholder="PO, SI, Item..." onkeyup="filterTable()">
                        </div>
                    </div>
                    <div class="col-md-2">
                        <label class="filter-label">Company</label>
                        <select id="companyFilter" class="form-select form-select-sm shadow-none" onchange="filterTable()">
                            <option value="All">All Companies</option>
                            <?php foreach($companies as $c): ?>
                                <option value="<?= htmlspecialchars($c) ?>"><?= htmlspecialchars($c) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="filter-label">Payment Terms</label>
                        <select id="termFilter" class="form-select form-select-sm shadow-none" onchange="filterTable()">
                            <option value="All">All Terms</option>
                            <?php foreach($terms as $t): ?>
                                <option value="<?= htmlspecialchars($t) ?>"><?= htmlspecialchars($t) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-5">
                        <label class="filter-label">Delivery Date Range</label>
                        <div class="input-group input-group-sm">
                            <input type="date" id="startDate" class="form-control shadow-none" onchange="filterTable()">
                            <span class="input-group-text border-start-0 border-end-0">to</span>
                            <input type="date" id="endDate" class="form-control shadow-none" onchange="filterTable()">
                            <button class="btn btn-outline-secondary" onclick="clearDates()" title="Clear Dates"><i class="fas fa-times"></i></button>
                        </div>
                    </div>
                    
                    <div class="col-12 mt-3 pt-2 border-top d-flex justify-content-between align-items-center flex-wrap">
                        <span class="small text-muted fw-bold"><i class="fas fa-filter me-1"></i> Quick Status Toggle:</span>
                        <div class="btn-group shadow-sm">
                            <input type="radio" class="btn-check" name="statusFilter" id="fAll" value="All" autocomplete="off" checked onchange="filterTable()">
                            <label class="btn btn-outline-secondary btn-sm fw-bold px-3" for="fAll">Show All</label>

                            <input type="radio" class="btn-check" name="statusFilter" id="fPending" value="Pending" autocomplete="off" onchange="filterTable()">
                            <label class="btn btn-outline-primary btn-sm fw-bold px-3" for="fPending">Pending</label>

                            <input type="radio" class="btn-check" name="statusFilter" id="fOverdue" value="Overdue" autocomplete="off" onchange="filterTable()">
                            <label class="btn btn-outline-danger btn-sm fw-bold px-3" for="fOverdue">Overdue</label>
                            
                            <input type="radio" class="btn-check" name="statusFilter" id="fPaid" value="Paid" autocomplete="off" onchange="filterTable()">
                            <label class="btn btn-outline-success btn-sm fw-bold px-3" for="fPaid">Paid</label>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" id="financeTable">
                        <thead class="text-uppercase small text-muted">
                            <tr>
                                <th class="ps-3">Client Details</th>
                                <th>Item Overview</th>
                                <th class="text-end">Total Amount</th>
                                <th class="text-center">Due Date Tracker</th>
                                <th class="text-center pe-3">Payment Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($records)): ?>
                                <tr><td colspan="5" class="text-center py-5 text-muted">No delivered records found.</td></tr>
                            <?php else: ?>
                                <?php 
                                $today = new DateTime();
                                $today->setTime(0, 0, 0); // Normalize to midnight for accurate day counting

                                foreach ($records as $row): 
                                    $status = $row['payment_status'] ?: 'Pending';
                                    $dueStr = $row['due_date'];
                                    $deliveredStr = $row['date_delivered'];
                                    $datePaid = $row['date_paid'];
                                    
                                    // --- ALERT LOGIC ---
                                    $rowClass = '';
                                    $dueBadge = '';
                                    $filterTag = $status; // Used for JS filtering

                                    if ($status == 'Paid') {
                                        $rowClass = 'row-paid';
                                        $dueBadge = '<span class="badge bg-success shadow-sm"><i class="fas fa-check-circle me-1"></i> Status: Cleared</span>';
                                        if (!empty($datePaid)) {
                                            $dueBadge .= '<div class="small fw-bold text-success mt-1"><i class="fas fa-clock me-1"></i> Paid: ' . date('M d, Y h:i A', strtotime($datePaid)) . '</div>';
                                        }
                                    } else {
                                        if (empty($dueStr) || $dueStr == '0000-00-00') {
                                            $dueBadge = '<span class="badge bg-secondary shadow-sm">No Due Date Set</span>';
                                        } else {
                                            $dueObj = new DateTime($dueStr);
                                            $dueObj->setTime(0,0,0);
                                            $interval = $today->diff($dueObj);
                                            $days = (int)$interval->format('%R%a'); // + means future, - means past

                                            if ($days < 0) {
                                                $rowClass = 'row-overdue';
                                                $dueBadge = '<span class="badge bg-danger shadow-sm"><i class="fas fa-exclamation-circle me-1"></i> Overdue ('.abs($days).' days)</span>';
                                                $filterTag .= ' Overdue';
                                            } elseif ($days <= 7) {
                                                $rowClass = 'row-neardue';
                                                $dueBadge = '<span class="badge bg-warning text-dark shadow-sm"><i class="fas fa-clock me-1"></i> Due in '.$days.' days</span>';
                                            } else {
                                                $dueBadge = '<span class="badge bg-info shadow-sm">Due in '.$days.' days</span>';
                                            }
                                        }
                                    }
                                ?>
                                <tr class="<?= $rowClass ?>" 
                                    data-filter="<?= $filterTag ?>" 
                                    data-date="<?= $deliveredStr ?>" 
                                    data-company="<?= htmlspecialchars(trim($row['company'])) ?>" 
                                    data-term="<?= htmlspecialchars(trim($row['payment_term'])) ?>">
                                    
                                    <td class="ps-3">
                                        <strong class="text-dark d-block searchable"><?= htmlspecialchars($row['company']) ?></strong>
                                        <div class="small text-muted searchable">PO: <?= $row['po_number'] ?: 'N/A' ?> | SI: <?= $row['sales_invoice_no'] ?: 'N/A' ?></div>
                                        <div class="small fw-bold text-primary mt-1">Delivered: <?= date('M d, Y', strtotime($deliveredStr)) ?></div>
                                    </td>
                                    <td>
                                        <div class="text-dark fw-bold searchable"><?= htmlspecialchars($row['item']) ?></div>
                                        <div class="small text-muted">Qty: <?= $row['quantity_requested'] ?></div>
                                    </td>
                                    <td class="text-end">
                                        <h6 class="mb-0 fw-bold text-dark">₱<?= number_format($row['total_nam_amount'], 2) ?></h6>
                                    </td>
                                    <td class="text-center">
                                        <div class="mb-1 text-muted small fw-bold">Due: <?= (!empty($dueStr) && $dueStr != '0000-00-00') ? date('M d, Y', strtotime($dueStr)) : '--' ?></div>
                                        <div class="mb-1"><span class="badge border text-dark fw-normal"><?= htmlspecialchars($row['payment_term']) ?: 'No Term' ?></span></div>
                                        <?= $dueBadge ?>
                                    </td>
                                    <td class="text-center pe-3">
                                        <?php if($status == 'Pending'): ?>
                                            <button class="btn btn-sm btn-success fw-bold shadow-sm w-100" onclick="toggleStatus(<?= $row['id'] ?>, 'Paid', this)">
                                                <i class="fas fa-check me-1"></i> Mark as Paid
                                            </button>
                                        <?php else: ?>
                                            <button class="btn btn-sm btn-outline-secondary fw-bold w-100" onclick="toggleStatus(<?= $row['id'] ?>, 'Pending', this)">
                                                <i class="fas fa-undo me-1"></i> Revert
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function clearDates() {
            document.getElementById('startDate').value = '';
            document.getElementById('endDate').value = '';
            filterTable();
        }

        // Advanced Multi-Layer Filter Logic
        function filterTable() {
            const term = document.getElementById('searchInput').value.toLowerCase();
            const companyFilter = document.getElementById('companyFilter').value;
            const pTermFilter = document.getElementById('termFilter').value;
            const filterState = document.querySelector('input[name="statusFilter"]:checked').value;
            const startDate = document.getElementById('startDate').value;
            const endDate = document.getElementById('endDate').value;
            
            const rows = document.querySelectorAll('#financeTable tbody tr');

            rows.forEach(row => {
                if (row.cells.length === 1) return; // Skip "Empty" row placeholder

                const text = row.innerText.toLowerCase();
                const tag = row.getAttribute('data-filter');
                const rowDate = row.getAttribute('data-date'); 
                const rowCompany = row.getAttribute('data-company');
                const rowTerm = row.getAttribute('data-term');

                // 1. Check Search Text
                const matchesSearch = text.includes(term);
                
                // 2. Check Dropdowns
                const matchesCompany = (companyFilter === 'All' || rowCompany === companyFilter);
                const matchesTerm = (pTermFilter === 'All' || rowTerm === pTermFilter);

                // 3. Check Status Toggles
                const matchesStatus = (filterState === 'All' || tag.includes(filterState));

                // 4. Check Date Range
                let matchesDate = true;
                if (startDate && rowDate < startDate) matchesDate = false;
                if (endDate && rowDate > endDate) matchesDate = false;

                // Show only if ALL conditions are met
                if (matchesSearch && matchesStatus && matchesDate && matchesCompany && matchesTerm) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        }

        // AJAX Status Toggle Logic
        async function toggleStatus(id, newStatus, btnElement) {
            if (!confirm(`Are you sure you want to mark this invoice as ${newStatus}?`)) return;

            const originalHtml = btnElement.innerHTML;
            btnElement.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
            btnElement.disabled = true;

            const formData = new FormData();
            formData.append('action', 'toggle_status');
            formData.append('id', id);
            formData.append('status', newStatus);

            try {
                const response = await fetch('finance.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();

                if (data.success) {
                    // Reload page to automatically apply the timestamp and recalculate the KPI metrics
                    window.location.reload(); 
                } else {
                    alert("Error updating status.");
                    btnElement.innerHTML = originalHtml;
                    btnElement.disabled = false;
                }
            } catch (error) {
                alert("Network error. Please try again.");
                btnElement.innerHTML = originalHtml;
                btnElement.disabled = false;
            }
        }
    </script>
</body>
</html>