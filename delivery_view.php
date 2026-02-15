<?php
require_once 'config.php';
requireLogin();
requirePermission('view_logistics');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logistics & Delivery - NAM Supply</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .delivery-card { transition: transform 0.2s, box-shadow 0.2s; border: none; cursor: default; }
        .delivery-card:hover { transform: translateY(-3px); box-shadow: 0 10px 20px rgba(0,0,0,0.08) !important; }
        .status-pill { font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; }
        .qty-badge { width: 50px; height: 50px; display: flex; flex-direction: column; align-items: center; justify-content: center; border-radius: 12px; background: #eff6ff; color: #1d4ed8; border: 1px solid #dbeafe; }
        .qty-val { font-size: 1.25rem; font-weight: 800; line-height: 1; }
        .qty-lbl { font-size: 0.65rem; font-weight: 700; text-transform: uppercase; margin-top: 2px; }
        .info-row { display: flex; gap: 10px; margin-bottom: 8px; font-size: 0.9rem; color: #4b5563; }
        .info-icon { width: 20px; color: #9ca3af; text-align: center; }
        
        /* Floating Action Button (Mobile) */
        .fab-refresh { position: fixed; bottom: 20px; right: 20px; width: 50px; height: 50px; border-radius: 50%; display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 10px rgba(0,0,0,0.2); z-index: 1000; }
    </style>
</head>
<body class="bg-light">

    <?php include 'navbar.php'; ?>

    <div class="container-fluid mt-4 px-md-4" style="max-width: 1400px;">
        
        <div class="row g-3 mb-4">
            <div class="col-md-8">
                <h4 class="fw-bold text-primary mb-1"><i class="fas fa-truck me-2"></i>Logistics Operations</h4>
                <p class="text-muted small mb-0">Manage pending deliveries and track completion status.</p>
            </div>
            <div class="col-6 col-md-2">
                <div class="card border-0 shadow-sm h-100 bg-white">
                    <div class="card-body p-3 text-center">
                        <div class="text-muted small fw-bold text-uppercase">Pending</div>
                        <div class="h2 fw-bold text-warning mb-0" id="statPending">0</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-2">
                <div class="card border-0 shadow-sm h-100 bg-white">
                    <div class="card-body p-3 text-center">
                        <div class="text-muted small fw-bold text-uppercase">Delivered</div>
                        <div class="h2 fw-bold text-success mb-0" id="statDone">0</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm mb-4 sticky-top" style="top: 70px; z-index: 90;">
            <div class="card-body p-3">
                <div class="d-flex flex-column flex-md-row gap-3 justify-content-between align-items-center">
                    
                    <div class="btn-group w-100 w-md-auto" role="group">
                        <input type="radio" class="btn-check" name="filter" id="filterPending" checked onchange="setFilter('pending')">
                        <label class="btn btn-outline-primary fw-bold" for="filterPending">
                            <i class="fas fa-clock me-2"></i>To Deliver
                        </label>

                        <input type="radio" class="btn-check" name="filter" id="filterDone" onchange="setFilter('done')">
                        <label class="btn btn-outline-success fw-bold" for="filterDone">
                            <i class="fas fa-check-circle me-2"></i>Completed
                        </label>
                    </div>

                    <div class="input-group w-100" style="max-width: 400px;">
                        <span class="input-group-text bg-white border-end-0"><i class="fas fa-search text-muted"></i></span>
                        <input type="text" class="form-control border-start-0 ps-0" id="searchInput" placeholder="Search client, PO, or item..." onkeyup="renderList()">
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3" id="deliveryGrid">
            <div class="col-12 text-center py-5 text-muted">
                <div class="spinner-border text-primary mb-3" role="status"></div>
                <div>Loading deliveries...</div>
            </div>
        </div>

    </div>

    <button class="btn btn-primary fab-refresh d-md-none" onclick="loadData()">
        <i class="fas fa-sync-alt"></i>
    </button>

    <script>
        let allDeliveries = [];
        let currentFilter = 'pending';

        // Initial Load
        document.addEventListener('DOMContentLoaded', () => {
            loadData();
            // Auto-refresh every 30 seconds
            setInterval(loadData, 30000);
        });

        async function loadData() {
            try {
                const res = await fetch('get_deliveries.php');
                const data = await res.json();
                
                // Sort: Pending first, then by ID
                allDeliveries = data.sort((a, b) => {
                    if (a.date_delivered === b.date_delivered) return b.id - a.id;
                    return (a.date_delivered === null) ? -1 : 1;
                });

                updateStats();
                renderList();
            } catch(e) {
                console.error("Error loading deliveries:", e);
                document.getElementById('deliveryGrid').innerHTML = `
                    <div class="col-12 text-center py-5 text-danger">
                        <i class="fas fa-exclamation-circle fa-2x mb-2"></i><br>Failed to load data. Check connection.
                    </div>`;
            }
        }

        function updateStats() {
            const pending = allDeliveries.filter(r => !r.date_delivered || r.date_delivered === '0000-00-00').length;
            const done = allDeliveries.filter(r => r.date_delivered && r.date_delivered !== '0000-00-00').length;
            
            document.getElementById('statPending').textContent = pending;
            document.getElementById('statDone').textContent = done;
        }

        function setFilter(type) {
            currentFilter = type;
            renderList();
        }

        function renderList() {
            const container = document.getElementById('deliveryGrid');
            const search = document.getElementById('searchInput').value.toLowerCase();
            
            // Filter Data
            const filtered = allDeliveries.filter(item => {
                const isDelivered = (item.date_delivered && item.date_delivered !== '0000-00-00');
                
                // Tab Filter
                if (currentFilter === 'pending' && isDelivered) return false;
                if (currentFilter === 'done' && !isDelivered) return false;

                // Search Filter
                const haystack = (item.company + " " + item.item + " " + item.po_number).toLowerCase();
                return haystack.includes(search);
            });

            // Empty State
            if (filtered.length === 0) {
                container.innerHTML = `
                    <div class="col-12 text-center py-5 text-muted opacity-50">
                        <i class="fas fa-box-open fa-3x mb-3"></i>
                        <h5>No ${currentFilter} deliveries found</h5>
                        <p>Try changing filters or search terms.</p>
                    </div>`;
                return;
            }

            // Render Cards
            container.innerHTML = filtered.map(item => {
                const isDelivered = (item.date_delivered && item.date_delivered !== '0000-00-00');
                
                const actionBtn = isDelivered
                    ? `<button class="btn btn-light text-success fw-bold w-100" disabled><i class="fas fa-check-double me-2"></i>Delivered Today</button>`
                    : `<button onclick="markDelivered(${item.id})" class="btn btn-primary fw-bold w-100 shadow-sm" style="z-index: 5; position: relative;"><i class="fas fa-box-check me-2"></i>Mark as Delivered</button>`;

                return `
                <div class="col-12 col-md-6 col-lg-4">
                    <div class="card delivery-card shadow-sm h-100">
                        <div class="card-body d-flex flex-column">
                            
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <div style="min-width: 0;">
                                    <h5 class="fw-bold text-dark mb-1 text-truncate" title="${item.company}">${item.company}</h5>
                                    <div class="d-flex gap-2">
                                        <span class="badge bg-light text-dark border"><i class="fas fa-file-invoice me-1"></i>PO: ${item.po_number || 'N/A'}</span>
                                        ${isDelivered ? '<span class="badge bg-success status-pill">Delivered</span>' : '<span class="badge bg-warning text-dark status-pill">Pending</span>'}
                                    </div>
                                </div>
                                <div class="qty-badge flex-shrink-0 ms-2">
                                    <div class="qty-val">${item.quantity_requested}</div>
                                    <div class="qty-lbl">QTY</div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <div class="info-row">
                                    <div class="info-icon"><i class="fas fa-box"></i></div>
                                    <div class="fw-semibold text-dark text-break">${item.item}</div>
                                </div>
                            </div>

                            <div class="mt-auto pt-3 border-top">
                                ${actionBtn}
                            </div>
                        </div>
                    </div>
                </div>`;
            }).join('');
        }

        async function markDelivered(id) {
            if(!confirm("Are you sure this item has been delivered?")) return;
            try {
                const res = await fetch('mark_delivered.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({id: id})
                });
                const data = await res.json();
                if(data.success) {
                    loadData();
                } else {
                    alert("Error: " + (data.message || "Unknown error"));
                }
            } catch(e) {
                console.error(e);
                alert("Network error. Please try again.");
            }
        }
    </script>
</body>
</html>