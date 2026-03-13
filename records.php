<?php 
require_once 'config.php'; 
requireLogin(); 
requirePermission('manage_sales'); 

$conn = getDBConnection();

// --- 1. AUTO-HEAL DATABASE: Add Payment Columns ---
$checkCol = $conn->query("SHOW COLUMNS FROM sales LIKE 'payment_status'");
if($checkCol && $checkCol->num_rows == 0) {
    $conn->query("ALTER TABLE sales ADD COLUMN payment_status VARCHAR(20) DEFAULT 'Pending' AFTER due_date");
}

$checkDatePaid = $conn->query("SHOW COLUMNS FROM sales LIKE 'date_paid'");
if($checkDatePaid && $checkDatePaid->num_rows == 0) {
    $conn->query("ALTER TABLE sales ADD COLUMN date_paid DATETIME DEFAULT NULL AFTER payment_status");
}

// --- 2. AJAX HANDLER FOR PAYMENT STATUS UPDATES ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'toggle_payment_status') {
    header('Content-Type: application/json');
    $id = intval($_POST['id']);
    $new_status = $conn->real_escape_string($_POST['status']);
    
    // Fetch details FIRST so we know what to put in the log
    $details = $conn->query("SELECT company, item FROM sales WHERE id = $id")->fetch_assoc();
    
    // Automatically timestamp the payment
    $date_paid_sql = ($new_status === 'Paid') ? "NOW()" : "NULL";
    $sql = "UPDATE sales SET payment_status = '$new_status', date_paid = $date_paid_sql WHERE id = $id";
    
    if($conn->query($sql)) {
        // --- LOG THE ACTION ---
        if ($details) {
            logAction('Updated Payment Status', "Changed payment status to $new_status for {$details['company']} (Item: {$details['item']})");
        }
        
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => $conn->error]);
    }
    exit;
}

// --- 3. FETCH FINANCE KPIs (For Delivered Items Only) ---
$kpiSql = "SELECT 
            SUM(CASE WHEN payment_status = 'Paid' THEN total_nam_amount ELSE 0 END) as collected,
            SUM(CASE WHEN payment_status = 'Pending' THEN total_nam_amount ELSE 0 END) as outstanding,
            SUM(CASE WHEN payment_status = 'Pending' AND due_date < CURRENT_DATE() AND due_date IS NOT NULL AND due_date != '0000-00-00' THEN total_nam_amount ELSE 0 END) as overdue
           FROM sales 
           WHERE date_delivered IS NOT NULL AND date_delivered != '0000-00-00'";
$finance_kpi = $conn->query($kpiSql)->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales Records - NAM Supply</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .table-responsive { max-height: 70vh; overflow-y: auto; }
        thead th { position: sticky; top: 0; background: #f8f9fa; z-index: 1; }
        .kpi-card { border-left: 4px solid; transition: transform 0.2s; }
        .kpi-card:hover { transform: translateY(-3px); }
        .kpi-card.blue { border-color: #0d6efd; }
        .kpi-card.orange { border-color: #fd7e14; }
        .kpi-card.green { border-color: #198754; }
        
        /* Compact Table Styles */
        .table-sm td, .table-sm th { font-size: 0.85rem; vertical-align: middle; white-space: nowrap; }
        .col-truncate { max-width: 200px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        
        /* Alert Row Colors for Finance Tracker */
        .row-overdue { background-color: #ffe6e6 !important; }
        .row-neardue { background-color: #fff4cc !important; }
        .row-paid { opacity: 0.7; background-color: #f1f8f5 !important; }
    </style>
</head>
<body class="bg-light">

    <?php include 'navbar.php'; ?>

    <div class="container-fluid mt-4 px-4">
        
        <div class="row g-3 mb-3">
            <div class="col-md-4">
                <div class="card shadow-sm border-0 kpi-card blue h-100">
                    <div class="card-body">
                        <h6 class="text-muted text-uppercase fw-bold small mb-1">Total Records (Filtered)</h6>
                        <h2 class="mb-0 fw-bold text-dark" id="totalRecords">0</h2>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card shadow-sm border-0 kpi-card orange h-100">
                    <div class="card-body">
                        <h6 class="text-muted text-uppercase fw-bold small mb-1">Pending Delivery</h6>
                        <h2 class="mb-0 fw-bold text-warning" id="pendingCount">0</h2>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card shadow-sm border-0 kpi-card green h-100">
                    <div class="card-body">
                        <h6 class="text-muted text-uppercase fw-bold small mb-1">Total Sales (Filtered)</h6>
                        <h2 class="mb-0 fw-bold text-success" id="filteredSales">₱0.00</h2>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="card shadow-sm border-0 kpi-card border-success h-100 bg-success bg-opacity-10">
                    <div class="card-body">
                        <h6 class="text-success text-uppercase fw-bold small mb-1"><i class="fas fa-check-circle me-1"></i> Total Collected (Paid)</h6>
                        <h4 class="mb-0 fw-bold text-success">₱<?= number_format($finance_kpi['collected'] ?? 0, 2) ?></h4>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card shadow-sm border-0 kpi-card border-primary h-100 bg-primary bg-opacity-10">
                    <div class="card-body">
                        <h6 class="text-primary text-uppercase fw-bold small mb-1"><i class="fas fa-hand-holding-usd me-1"></i> Outstanding Receivables</h6>
                        <h4 class="mb-0 fw-bold text-primary">₱<?= number_format($finance_kpi['outstanding'] ?? 0, 2) ?></h4>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card shadow-sm border-0 kpi-card border-danger h-100 bg-danger bg-opacity-10">
                    <div class="card-body">
                        <h6 class="text-danger text-uppercase fw-bold small mb-1"><i class="fas fa-exclamation-triangle me-1"></i> Overdue Collections</h6>
                        <h4 class="mb-0 fw-bold text-danger">₱<?= number_format($finance_kpi['overdue'] ?? 0, 2) ?></h4>
                    </div>
                </div>
            </div>
        </div>

        <div id="alertContainer"></div>

        <div class="card shadow-sm border-0 mb-3">
            <div class="card-body bg-white py-3">
                <div class="row g-2 align-items-end">
                    <div class="col-md-2">
                        <label class="form-label small fw-bold text-muted">Delivery</label>
                        <select id="filterStatus" class="form-select form-select-sm" onchange="applyFilters()">
                            <option value="">All Statuses</option>
                            <option value="pending">Pending Delivery</option>
                            <option value="partial">Partially Delivered</option>
                            <option value="delivered">Delivered</option>
                            <option value="reserved">Reserved</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small fw-bold text-muted">Payment</label>
                        <select id="filterPayment" class="form-select form-select-sm" onchange="applyFilters()">
                            <option value="">All Payments</option>
                            <option value="Pending">Unpaid (Pending)</option>
                            <option value="Paid">Paid</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small fw-bold text-muted">Company</label>
                        <select id="filterCompany" class="form-select form-select-sm" onchange="applyFilters()">
                            <option value="">All Companies</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small fw-bold text-muted">Category</label>
                        <select id="filterCategory" class="form-select form-select-sm" onchange="applyFilters()">
                            <option value="">All Categories</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-bold text-muted">Search</label>
                        <div class="input-group input-group-sm">
                            <input type="text" id="searchItem" class="form-control" placeholder="Item, PO, Remarks..." onkeyup="applyFilters()">
                            <button class="btn btn-outline-secondary" onclick="clearFilters()" type="button" title="Reset"><i class="fas fa-undo"></i></button>
                        </div>
                    </div>
                    <div class="col-12 mt-2">
                        <div class="d-flex align-items-center gap-2">
                            <span class="small fw-bold text-muted">Date Range:</span>
                            <input type="date" id="dateFrom" class="form-control form-control-sm" style="width: 140px;" onchange="applyFilters()">
                            <span class="text-muted small">to</span>
                            <input type="date" id="dateTo" class="form-control form-control-sm" style="width: 140px;" onchange="applyFilters()">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="d-flex justify-content-between align-items-center mb-2 px-1">
            <button class="btn btn-success btn-sm fw-bold shadow-sm" id="btnBulkDeliver" onclick="openBulkDeliverModal()" style="display: none;">
                <i class="fas fa-truck-loading me-1"></i> Deliver Selected (<span id="selectedCount">0</span>)
            </button>
        </div>

        <div class="card shadow-sm border-0">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover table-striped table-sm mb-0" id="recordsTable">
                        <thead class="bg-light">
                            <tr>
                                <th class="ps-3" style="width: 40px;">
                                    <input class="form-check-input border-secondary" type="checkbox" id="selectAll" onclick="toggleSelectAll(this)">
                                </th>
                                <th>Delivery Status</th>
                                <th>Date</th>
                                <th>S/N</th>
                                <th>PO No.</th>
                                <th>Company</th>
                                <th>Address</th>
                                <th>TIN</th>
                                <th>Contact</th>
                                <th>Category</th>
                                <th>Item Description</th>
                                <th class="text-center">Qty</th>
                                <th class="text-end">Unit Cost</th>
                                <th class="text-end">Total Cost</th>
                                <th class="text-end">Unit Price</th>
                                <th class="text-end">Total Sales</th>
                                <th class="text-end">Income</th>
                                <th class="text-end">Margin</th>
                                <th>Supplier</th>
                                <th>Delivered</th>
                                <th>Pay Term</th>
                                <th>Due Tracker</th>
                                <th>SI No.</th>
                                <th>Inv No.</th>
                                <th>Remarks</th>
                                <th class="text-center bg-light" style="position:sticky; right:0; box-shadow: -2px 0 5px rgba(0,0,0,0.05);">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="recordsBody">
                            <tr><td colspan="26" class="text-center py-5 text-muted"><i class="fas fa-spinner fa-spin me-2"></i>Loading records...</td></tr>
                        </tbody>
                    </table>
                </div>
                
                <div id="noData" class="text-center py-5 d-none">
                    <div class="text-muted">
                        <i class="fas fa-folder-open fa-3x mb-3 opacity-25"></i>
                        <p>No records found matching your filters.</p>
                    </div>
                </div>

                <div class="d-flex justify-content-between align-items-center p-3 border-top" id="paginationBar">
                    <span class="small text-muted">Showing page <span id="currentPage" class="fw-bold">1</span> of <span id="totalPages">1</span></span>
                    <div class="btn-group btn-group-sm">
                        <button class="btn btn-outline-secondary" onclick="changePage('first')"><i class="fas fa-angle-double-left"></i></button>
                        <button class="btn btn-outline-secondary" onclick="changePage('prev')"><i class="fas fa-angle-left"></i></button>
                        <button class="btn btn-outline-secondary" onclick="changePage('next')"><i class="fas fa-angle-right"></i></button>
                        <button class="btn btn-outline-secondary" onclick="changePage('last')"><i class="fas fa-angle-double-right"></i></button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="bulkDeliverModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold text-success"><i class="fas fa-truck-loading me-2"></i>Bulk Deliver Items</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small mb-3">Adjust the quantities below if you are making a partial delivery. Delivering partial quantities will split the remaining amount into a new pending record.</p>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered">
                            <thead class="bg-light">
                                <tr>
                                    <th>Item Description</th>
                                    <th>PO No.</th>
                                    <th class="text-center">Pending Qty</th>
                                    <th class="text-center" style="width: 150px;">Deliver Qty</th>
                                </tr>
                            </thead>
                            <tbody id="bulkDeliverBody">
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-success fw-bold" id="btnConfirmBulk" onclick="submitBulkDelivery()">
                        <i class="fas fa-check me-2"></i>Confirm Delivery
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="editModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold text-primary"><i class="fas fa-edit me-2"></i>Edit Record</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="editForm" onsubmit="saveEdit(event)">
                    <div class="modal-body">
                        <input type="hidden" id="editId">
                        
                        <h6 class="text-uppercase small fw-bold text-muted border-bottom pb-2 mb-3">Record Details</h6>
                        <div class="row g-3 mb-4">
                            <div class="col-md-3">
                                <label class="form-label small fw-bold">Date <span class="text-danger">*</span></label>
                                <input type="date" id="editDate" class="form-control form-control-sm" required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small fw-bold">PO Number</label>
                                <input type="text" id="editPO" class="form-control form-control-sm">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small fw-bold">Serial No (S/N)</label>
                                <input type="text" id="editSN" class="form-control form-control-sm">
                            </div>
                        </div>

                        <h6 class="text-uppercase small fw-bold text-muted border-bottom pb-2 mb-3">Client Information</h6>
                        <div class="row g-3 mb-4">
                            <div class="col-md-4">
                                <label class="form-label small fw-bold">Company Name <span class="text-danger">*</span></label>
                                <input type="text" id="editCompany" class="form-control form-control-sm" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-bold">Address</label>
                                <input type="text" id="editAddress" class="form-control form-control-sm">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small fw-bold">TIN</label>
                                <input type="text" id="editTIN" class="form-control form-control-sm">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small fw-bold">Contact Person</label>
                                <input type="text" id="editContact" class="form-control form-control-sm">
                            </div>
                        </div>

                        <h6 class="text-uppercase small fw-bold text-muted border-bottom pb-2 mb-3">Product & Financials</h6>
                        <div class="row g-3 mb-4">
                            <div class="col-md-3">
                                <label class="form-label small fw-bold">Category <span class="text-danger">*</span></label>
                                <select id="editCategory" class="form-select form-select-sm" required>
                                    <option value="">Select Category</option>
                                    <option value="OFFICE SUPPLIES">OFFICE SUPPLIES</option>
                                    <option value="CLEANING MATERIALS">CLEANING MATERIALS</option>
                                    <option value="CONSUMABLES">CONSUMABLES</option>
                                    <option value="OFFICE TOOLS AND EQUIPMENT">OFFICE TOOLS</option>
                                    <option value="PPE">PPE</option>
                                    <option value="MATERIALS">MATERIALS</option>
                                    <option value="COMPANY UNIFORM">UNIFORMS</option>
                                    <option value="OFFICE FURNITURE & FIXTURES">FURNITURE</option>
                                </select>
                            </div>
                            <div class="col-md-5">
                                <label class="form-label small fw-bold">Item Description <span class="text-danger">*</span></label>
                                <input type="text" id="editItem" class="form-control form-control-sm" required>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small fw-bold">Quantity <span class="text-danger">*</span></label>
                                <input type="number" id="editQuantity" class="form-control form-control-sm" required min="1" onchange="calculateEdit()">
                            </div>
                            
                            <div class="col-md-2"></div> <div class="col-md-3">
                                <label class="form-label small fw-bold">Supplier Price</label>
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text">₱</span>
                                    <input type="number" id="editSupplierPrice" class="form-control" step="0.01" onchange="calculateEdit()">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small fw-bold text-primary">NAM Unit Price</label>
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text">₱</span>
                                    <input type="number" id="editNAMPrice" class="form-control border-primary" step="0.01" required onchange="calculateEdit()">
                                </div>
                            </div>
                            
                            <div class="col-md-2">
                                <label class="form-label small fw-bold text-muted">Total Cost</label>
                                <input type="number" id="editTotalActual" class="form-control form-control-sm bg-light" readonly>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small fw-bold text-success">Total Sales</label>
                                <input type="number" id="editTotalNAM" class="form-control form-control-sm bg-light fw-bold text-success" readonly>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small fw-bold text-muted">Income</label>
                                <input type="number" id="editIncome" class="form-control form-control-sm bg-light" readonly>
                            </div>
                        </div>

                        <h6 class="text-uppercase small fw-bold text-muted border-bottom pb-2 mb-3">Logistics & Payment</h6>
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label small fw-bold">Supplier Name</label>
                                <input type="text" id="editSupplier" class="form-control form-control-sm">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small fw-bold">Date Delivered</label>
                                <input type="date" id="editDateDelivered" class="form-control form-control-sm">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small fw-bold">Payment Terms</label>
                                <input type="text" id="editPaymentTerm" class="form-control form-control-sm">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small fw-bold">Due Date</label>
                                <input type="date" id="editDueDate" class="form-control form-control-sm">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small fw-bold">SI Number</label>
                                <input type="text" id="editSINumber" class="form-control form-control-sm">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small fw-bold">Invoice No</label>
                                <input type="text" id="editSalesInvoiceNo" class="form-control form-control-sm">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Remarks</label>
                                <textarea id="editRemarks" class="form-control form-control-sm" rows="1"></textarea>
                            </div>
                        </div>

                    </div>
                    <div class="modal-footer bg-light">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary fw-bold"><i class="fas fa-save me-2"></i>Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        let allRecords = [];
        let filteredRecords = [];
        let currentPage = 1;
        const perPage = 50;
        let itemStatus = {}; 

        let editModal, bulkDeliverModal;

        document.addEventListener('DOMContentLoaded', () => {
            editModal = new bootstrap.Modal(document.getElementById('editModal'));
            bulkDeliverModal = new bootstrap.Modal(document.getElementById('bulkDeliverModal'));
            loadRecords();
        });

        function formatCurrency(val) {
            return '₱' + parseFloat(val || 0).toLocaleString('en-PH', {minimumFractionDigits: 2});
        }

        function showAlert(message, type = 'success') {
            const container = document.getElementById('alertContainer');
            const alertId = 'alert-' + Date.now();
            container.innerHTML = `
                <div id="${alertId}" class="alert alert-${type} alert-dismissible fade show" role="alert">
                    ${message}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            `;
            setTimeout(() => {
                const alertElement = document.getElementById(alertId);
                if (alertElement) {
                    const bsAlert = bootstrap.Alert.getOrCreateInstance(alertElement);
                    bsAlert.close();
                }
            }, 3000);
        }

        async function loadRecords() {
            try {
                const res = await fetch('get_records.php');
                const data = await res.json();
                allRecords = data.records;
                
                itemStatus = {};
                allRecords.forEach(r => {
                    const key = r.company + '|' + (r.po_number || 'NO_PO') + '|' + r.item;
                    if(!itemStatus[key]) { itemStatus[key] = { total: 0, delivered: 0 }; }
                    const qty = parseFloat(r.quantity_requested) || 0;
                    itemStatus[key].total += qty;
                    if(r.date_delivered && r.date_delivered !== '0000-00-00') {
                        itemStatus[key].delivered += qty;
                    }
                });

                // Safely apply options to dropdowns, if they exist
                const compSelect = document.getElementById('filterCompany');
                const catSelect = document.getElementById('filterCategory');
                
                if (compSelect) compSelect.innerHTML = '<option value="">All Companies</option>';
                if (catSelect) catSelect.innerHTML = '<option value="">All Categories</option>';

                data.companies.forEach(c => {
                    if (compSelect) {
                        const opt = document.createElement('option');
                        opt.value = c; opt.textContent = c; compSelect.appendChild(opt);
                    }
                });
                
                data.categories.forEach(c => {
                    if (catSelect) {
                        const opt = document.createElement('option');
                        opt.value = c; opt.textContent = c; catSelect.appendChild(opt);
                    }
                });

                applyFilters();
            } catch(e) {
                console.error(e);
                showAlert("Error loading records from server.", "danger");
            }
        }

        function applyFilters() {
            // Safely fetch filter values using optional chaining
            const status = document.getElementById('filterStatus')?.value || '';
            const payStatus = document.getElementById('filterPayment')?.value || '';
            const company = document.getElementById('filterCompany')?.value || '';
            const category = document.getElementById('filterCategory')?.value || '';
            const search = document.getElementById('searchItem')?.value.toLowerCase() || '';
            const dFrom = document.getElementById('dateFrom')?.value || '';
            const dTo = document.getElementById('dateTo')?.value || '';

            filteredRecords = allRecords.filter(r => {
                const isDelivered = (r.date_delivered && r.date_delivered !== '0000-00-00');
                const isReserved = (r.is_reserved == 1);
                
                const key = r.company + '|' + (r.po_number || 'NO_PO') + '|' + r.item;
                const stat = itemStatus[key];

                let isPartial = false;
                if (!isDelivered && stat && stat.delivered > 0) isPartial = true;

                // Delivery Filter
                if (status === 'pending' && isDelivered) return false;
                if (status === 'delivered' && !isDelivered) return false;
                if (status === 'partial' && !isPartial) return false;
                if (status === 'reserved' && !isReserved) return false;

                // Payment Filter
                if (payStatus && (r.payment_status || 'Pending') !== payStatus) return false;

                if (company && r.company !== company) return false;
                if (category && r.category !== category) return false;
                
                if (search) {
                    const haystack = (r.item + r.po_number + r.remarks + r.sn).toLowerCase();
                    if (!haystack.includes(search)) return false;
                }

                if (dFrom && r.date < dFrom) return false;
                if (dTo && r.date > dTo) return false;

                return true;
            });

            document.getElementById('totalRecords').textContent = filteredRecords.length.toLocaleString();
            const totalSales = filteredRecords.reduce((sum, r) => sum + parseFloat(r.total_nam_amount || 0), 0);
            document.getElementById('filteredSales').textContent = formatCurrency(totalSales);

            const pending = filteredRecords.filter(r => !r.date_delivered || r.date_delivered === '0000-00-00').length;
            document.getElementById('pendingCount').textContent = pending;

            currentPage = 1;
            renderTable();
        }

        function clearFilters() {
            // Safely reset elements
            ['filterStatus', 'filterPayment', 'filterCompany', 'filterCategory', 'searchItem', 'dateFrom', 'dateTo'].forEach(id => {
                const el = document.getElementById(id);
                if(el) el.value = '';
            });
            applyFilters();
        }

        function renderTable() {
            const tbody = document.getElementById('recordsBody');
            tbody.innerHTML = '';
            
            const selectAllCb = document.getElementById('selectAll');
            if(selectAllCb) selectAllCb.checked = false;
            updateSelectedCount();

            if(filteredRecords.length === 0) {
                document.getElementById('noData').classList.remove('d-none');
                document.getElementById('paginationBar').classList.add('d-none');
                return;
            } else {
                document.getElementById('noData').classList.add('d-none');
                document.getElementById('paginationBar').classList.remove('d-none');
            }

            const totalPages = Math.ceil(filteredRecords.length / perPage);
            const start = (currentPage - 1) * perPage;
            const end = start + perPage;
            const pageData = filteredRecords.slice(start, end);

            // Date setup for the Finance "Due Date Tracker"
            const today = new Date();
            today.setHours(0,0,0,0);

            pageData.forEach(r => {
                const tr = document.createElement('tr');
                const isDelivered = (r.date_delivered && r.date_delivered !== '0000-00-00');
                const isReserved = (r.is_reserved == 1);
                
                const key = r.company + '|' + (r.po_number || 'NO_PO') + '|' + r.item;
                const stat = itemStatus[key];

                let isPartial = false;
                if (!isDelivered && stat && stat.delivered > 0) isPartial = true;

                // --- 1. DELIVERY BADGE ---
                let statusHtml = '';
                if (isDelivered) {
                    statusHtml = `<span class="badge rounded-pill bg-success mb-1"><i class="fas fa-check me-1"></i>Delivered</span>`;
                } else if (isPartial) {
                    statusHtml = `<span class="badge rounded-pill bg-info text-dark mb-1"><i class="fas fa-truck-loading me-1"></i>Partial</span>`;
                } else if (isReserved) {
                    statusHtml = `<span class="badge rounded-pill bg-danger mb-1"><i class="fas fa-bookmark me-1"></i>Reserved</span>`;
                } else {
                    statusHtml = `<span class="badge rounded-pill bg-warning text-dark mb-1"><i class="fas fa-clock me-1"></i>Pending</span>`;
                }

                // --- 2. FINANCE / PAYMENT BADGE (Color Magic) ---
                let payStatus = r.payment_status || 'Pending';
                let dueStr = r.due_date;
                let dueBadge = '';
                let rowClass = '';
                let payActionBtn = '';

                if (isDelivered) {
                    if (payStatus === 'Paid') {
                        rowClass = 'row-paid';
                        dueBadge = `<span class="badge border border-success text-success"><i class="fas fa-check-circle me-1"></i>Paid</span>`;
                        // The "Revert" button is added to the Actions column
                        payActionBtn = `<button class="btn btn-sm btn-outline-secondary" onclick="togglePayment(${r.id}, 'Pending')" title="Revert Payment"><i class="fas fa-undo"></i></button>`;
                    } else {
                        // The "Mark Paid" button
                        payActionBtn = `<button class="btn btn-sm btn-success shadow-sm fw-bold" onclick="togglePayment(${r.id}, 'Paid')" title="Mark as Paid"><i class="fas fa-check-double"></i></button>`;

                        if (!dueStr || dueStr === '0000-00-00') {
                            dueBadge = '<span class="badge bg-secondary">No Due Date</span>';
                        } else {
                            const dueObj = new Date(dueStr);
                            dueObj.setHours(0,0,0,0);
                            const diffTime = dueObj - today;
                            const days = Math.ceil(diffTime / (1000 * 60 * 60 * 24));

                            if (days < 0) {
                                rowClass = 'row-overdue';
                                dueBadge = `<span class="badge bg-danger"><i class="fas fa-exclamation-circle me-1"></i>Overdue (${Math.abs(days)}d)</span>`;
                            } else if (days <= 7) {
                                rowClass = 'row-neardue';
                                dueBadge = `<span class="badge bg-warning text-dark"><i class="fas fa-clock me-1"></i>Due in ${days}d</span>`;
                            } else {
                                dueBadge = `<span class="badge bg-info text-dark">Due in ${days}d</span>`;
                            }
                        }
                    }
                }

                const checkboxHtml = !isDelivered 
                    ? `<input class="form-check-input border-secondary row-checkbox" type="checkbox" value="${r.id}" onchange="updateSelectedCount()">` 
                    : `<input class="form-check-input" type="checkbox" disabled>`;

                tr.className = rowClass;
                tr.innerHTML = `
                    <td class="ps-3">${checkboxHtml}</td>
                    <td>${statusHtml}</td>
                    <td>${r.date}</td>
                    <td>${r.sn || ''}</td>
                    <td>${r.po_number || ''}</td>
                    <td class="fw-bold text-dark">${r.company}</td>
                    <td class="col-truncate" title="${r.address || ''}">${r.address || ''}</td>
                    <td>${r.tin || ''}</td>
                    <td>${r.contact_person_contact || ''}</td>
                    <td class="small text-muted">${r.category}</td>
                    <td class="col-truncate text-wrap" style="min-width:200px;">${r.item}</td>
                    <td class="text-center">${r.quantity_requested}</td>
                    <td class="text-end font-monospace">${formatCurrency(r.suppliers_price)}</td>
                    <td class="text-end font-monospace text-muted">${formatCurrency(r.total_actual_amount)}</td>
                    <td class="text-end font-monospace">${formatCurrency(r.nam_unit_price)}</td>
                    <td class="text-end font-monospace fw-bold text-primary">${formatCurrency(r.total_nam_amount)}</td>
                    <td class="text-end font-monospace text-success">${formatCurrency(r.income)}</td>
                    <td class="text-end small">${parseFloat(r.income_percent || 0).toFixed(1)}%</td>
                    <td>${r.supplier || ''}</td>
                    <td class="${isDelivered ? 'text-success fw-bold' : 'text-muted'}">${isDelivered ? r.date_delivered : '-'}</td>
                    <td>${r.payment_term || ''}</td>
                    <td class="text-center">
                        <div class="mb-1 text-dark small">${(!dueStr || dueStr === '0000-00-00') ? '--' : r.due_date}</div>
                        ${dueBadge}
                    </td>
                    <td>${r.si_number || ''}</td>
                    <td>${r.sales_invoice_no || ''}</td>
                    <td class="col-truncate" title="${r.remarks || ''}">${r.remarks || ''}</td>
                    
                    <td class="text-end bg-white" style="position:sticky; right:0;">
                        <div class="btn-group btn-group-sm">
                            ${payActionBtn}
                            <button class="btn btn-outline-warning" onclick="toggleReserve(${r.id}, ${isReserved ? 0 : 1})" title="${isReserved ? 'Remove Reservation' : 'Reserve Item'}">
                                <i class="${isReserved ? 'fas' : 'far'} fa-bookmark"></i>
                            </button>
                            <button class="btn btn-outline-primary" onclick="editRecord(${r.id})" title="Edit"><i class="fas fa-edit"></i></button>
                            <button class="btn btn-outline-danger" onclick="deleteRecord(${r.id})" title="Delete"><i class="fas fa-trash-alt"></i></button>
                        </div>
                    </td>
                `;
                tbody.appendChild(tr);
            });

            document.getElementById('currentPage').textContent = currentPage;
            document.getElementById('totalPages').textContent = totalPages;
        }

        function updateSelectedCount() {
            const count = document.querySelectorAll('.row-checkbox:checked').length;
            const btn = document.getElementById('btnBulkDeliver');
            document.getElementById('selectedCount').textContent = count;
            btn.style.display = count > 0 ? 'inline-block' : 'none';
        }

        function toggleSelectAll(source) {
            document.querySelectorAll('.row-checkbox').forEach(cb => {
                if(!cb.disabled) cb.checked = source.checked;
            });
            updateSelectedCount();
        }

        // --- NEW PAYMENT AJAX TOGGLE ---
        async function togglePayment(id, newStatus) {
            if (!confirm(`Are you sure you want to mark this item as ${newStatus}?`)) return;
            try {
                const formData = new FormData();
                formData.append('action', 'toggle_payment_status');
                formData.append('id', id);
                formData.append('status', newStatus);

                const res = await fetch('records.php', { method: 'POST', body: formData });
                const data = await res.json();
                
                if(data.success) {
                    window.location.reload(); // Quickest way to refresh the KPI cards up top!
                } else {
                    showAlert("Error updating payment status.", "danger");
                }
            } catch(e) {
                showAlert("Network error.", "danger");
            }
        }

        function openBulkDeliverModal() {
            const checkboxes = document.querySelectorAll('.row-checkbox:checked');
            const tbody = document.getElementById('bulkDeliverBody');
            tbody.innerHTML = '';
            
            checkboxes.forEach(cb => {
                const r = allRecords.find(item => item.id == cb.value);
                if (r) {
                    const tr = document.createElement('tr');
                    tr.innerHTML = `
                        <td class="align-middle col-truncate" title="${r.item}">${r.item}</td>
                        <td class="align-middle">${r.po_number || '-'}</td>
                        <td class="align-middle text-center fw-bold">${r.quantity_requested}</td>
                        <td class="align-middle">
                            <input type="number" class="form-control form-control-sm text-center bulk-qty-input border-primary fw-bold" 
                                   data-id="${r.id}" data-max="${r.quantity_requested}" 
                                   value="${r.quantity_requested}" min="1" max="${r.quantity_requested}">
                        </td>
                    `;
                    tbody.appendChild(tr);
                }
            });
            
            bulkDeliverModal.show();
        }

        async function submitBulkDelivery() {
            const inputs = document.querySelectorAll('.bulk-qty-input');
            const deliveries = [];
            
            for (const input of inputs) {
                const id = input.getAttribute('data-id');
                const qty = parseInt(input.value);
                const max = parseInt(input.getAttribute('data-max'));
                
                if (qty < 1 || qty > max) {
                    alert('Invalid delivery quantity entered for an item.');
                    return;
                }
                deliveries.push({ id: id, qty: qty });
            }
            
            if (deliveries.length === 0) return;
            
            const btn = document.getElementById('btnConfirmBulk');
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Processing...';
            
            try {
                const res = await fetch('mark_delivered.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({ deliveries: deliveries })
                });
                const data = await res.json();
                
                if (data.success) {
                    bulkDeliverModal.hide();
                    loadRecords();
                    showAlert(data.message || "Items successfully marked as delivered.", "success");
                } else {
                    showAlert("Error: " + data.message, "danger");
                }
            } catch(err) {
                showAlert("Network error.", "danger");
            } finally {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-check me-2"></i>Confirm Delivery';
            }
        }

        function changePage(action) {
            const totalPages = Math.ceil(filteredRecords.length / perPage);
            if(action === 'first') currentPage = 1;
            else if(action === 'prev' && currentPage > 1) currentPage--;
            else if(action === 'next' && currentPage < totalPages) currentPage++;
            else if(action === 'last') currentPage = totalPages;
            renderTable();
        }

        function editRecord(id) {
            const r = allRecords.find(item => item.id == id);
            if(!r) return;

            document.getElementById('editId').value = r.id;
            document.getElementById('editDate').value = r.date;
            document.getElementById('editSN').value = r.sn;
            document.getElementById('editPO').value = r.po_number;
            document.getElementById('editCompany').value = r.company;
            document.getElementById('editAddress').value = r.address;
            document.getElementById('editTIN').value = r.tin;
            document.getElementById('editContact').value = r.contact_person_contact;
            document.getElementById('editCategory').value = r.category;
            document.getElementById('editItem').value = r.item;
            document.getElementById('editQuantity').value = r.quantity_requested;
            document.getElementById('editSupplierPrice').value = r.suppliers_price;
            document.getElementById('editNAMPrice').value = r.nam_unit_price;
            document.getElementById('editSupplier').value = r.supplier;
            document.getElementById('editDateDelivered').value = r.date_delivered; 
            document.getElementById('editPaymentTerm').value = r.payment_term;
            document.getElementById('editDueDate').value = r.due_date;
            document.getElementById('editSINumber').value = r.si_number;
            document.getElementById('editSalesInvoiceNo').value = r.sales_invoice_no;
            document.getElementById('editRemarks').value = r.remarks;

            calculateEdit();
            editModal.show();
        }

        function calculateEdit() {
            const qty = parseFloat(document.getElementById('editQuantity').value) || 0;
            const sPrice = parseFloat(document.getElementById('editSupplierPrice').value) || 0;
            const nPrice = parseFloat(document.getElementById('editNAMPrice').value) || 0;

            const tAct = qty * sPrice;
            const tNam = qty * nPrice;
            
            document.getElementById('editTotalActual').value = tAct.toFixed(2);
            document.getElementById('editTotalNAM').value = tNam.toFixed(2);
            document.getElementById('editIncome').value = (tNam - tAct).toFixed(2);
        }

        async function saveEdit(e) {
            e.preventDefault();
            const formData = new FormData();
            
            const map = {
                'id': 'editId', 'date': 'editDate', 'sn': 'editSN', 'po_number': 'editPO',
                'company': 'editCompany', 'address': 'editAddress', 'tin': 'editTIN',
                'contact_person_contact': 'editContact', 'category': 'editCategory', 'item': 'editItem',
                'quantity_requested': 'editQuantity', 'suppliers_price': 'editSupplierPrice',
                'nam_unit_price': 'editNAMPrice', 'supplier': 'editSupplier', 'date_delivered': 'editDateDelivered',
                'payment_term': 'editPaymentTerm', 'due_date': 'editDueDate', 'si_number': 'editSINumber',
                'sales_invoice_no': 'editSalesInvoiceNo', 'remarks': 'editRemarks'
            };

            for(const [key, id] of Object.entries(map)) {
                formData.append(key, document.getElementById(id).value);
            }

            try {
                const res = await fetch('update_record.php', { method: 'POST', body: formData });
                const data = await res.json();
                if(data.success) {
                    editModal.hide();
                    loadRecords();
                    showAlert("Record updated successfully!", "success");
                } else {
                    showAlert(data.message || "Error updating", "danger");
                }
            } catch(err) {
                showAlert("Network error. Please check console.", "danger");
            }
        }

        async function deleteRecord(id) {
            if(!confirm("Are you sure you want to delete this record? This cannot be undone.")) return;
            try {
                const res = await fetch('delete_record.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: 'id=' + id
                });
                const data = await res.json();
                if(data.success) {
                    loadRecords();
                    showAlert("Record deleted.", "success");
                } else {
                    showAlert("Error deleting record.", "danger");
                }
            } catch(e) {
                showAlert("Network error.", "danger");
            }
        }
        
        async function toggleReserve(id, val) {
            try {
                const res = await fetch('toggle_reserve.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({ id: id, val: val })
                });
                const data = await res.json();
                
                if (data.success) {
                    showAlert(val === 1 ? "Item Reserved successfully." : "Item Unreserved.", "success");
                    loadRecords(); 
                } else {
                    showAlert("Failed to update reservation.", "danger");
                }
            } catch (err) {
                showAlert("Network Error.", "danger");
            }
        }
    </script>
</body>
</html>