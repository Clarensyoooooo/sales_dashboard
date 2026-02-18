<?php
require_once 'config.php';
requireLogin();
requirePermission('view_logistics');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Logistics Dashboard - NAM Supply</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .summary-card { border: none; border-radius: 12px; transition: transform 0.2s; }
        .summary-card:hover { transform: translateY(-3px); }
        .icon-box { width: 48px; height: 48px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; }
        
        /* Group Styling */
        .po-group { border: 1px solid #e5e7eb; border-radius: 12px; margin-bottom: 1.5rem; overflow: hidden; background: white; }
        .po-header { background: #f8f9fa; padding: 1rem 1.25rem; cursor: pointer; border-bottom: 1px solid #e5e7eb; }
        .po-header:hover { background: #f1f5f9; }
        .progress-thin { height: 6px; border-radius: 3px; background: #e9ecef; margin-top: 8px; }
        
        .item-row { padding: 1rem 1.25rem; border-bottom: 1px solid #f3f4f6; display: flex; align-items: center; justify-content: space-between; transition: bg 0.2s; }
        .item-row:last-child { border-bottom: none; }
        .item-row.delivered { background-color: #f0fdf4; opacity: 0.7; }
        .item-row:hover { background-color: #f9fafb; }
    </style>
</head>
<body class="bg-light">

    <?php include 'navbar.php'; ?>

    <div class="container-fluid mt-4" style="max-width: 1200px;">
        
        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="card summary-card shadow-sm h-100">
                    <div class="card-body d-flex align-items-center">
                        <div class="icon-box bg-primary bg-opacity-10 text-primary me-3"><i class="fas fa-truck-loading"></i></div>
                        <div>
                            <h3 class="fw-bold mb-0" id="stat-pos">0</h3>
                            <small class="text-muted text-uppercase fw-bold">Active POs</small>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card summary-card shadow-sm h-100">
                    <div class="card-body d-flex align-items-center">
                        <div class="icon-box bg-warning bg-opacity-10 text-warning me-3"><i class="fas fa-box-open"></i></div>
                        <div>
                            <h3 class="fw-bold mb-0" id="stat-items">0</h3>
                            <small class="text-muted text-uppercase fw-bold">Items to Deliver</small>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card summary-card shadow-sm h-100">
                    <div class="card-body d-flex align-items-center">
                        <div class="icon-box bg-success bg-opacity-10 text-success me-3"><i class="fas fa-check-circle"></i></div>
                        <div>
                            <h3 class="fw-bold mb-0" id="stat-completed">0%</h3>
                            <small class="text-muted text-uppercase fw-bold">Completion Rate</small>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                 <div class="card summary-card shadow-sm h-100 bg-primary text-white">
                    <div class="card-body d-flex align-items-center justify-content-between" style="cursor:pointer" onclick="loadLogistics()">
                        <div>
                            <h5 class="fw-bold mb-0">Refresh Data</h5>
                            <small class="text-white-50">Last updated: Just now</small>
                        </div>
                        <i class="fas fa-sync-alt fa-lg"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="fw-bold text-dark"><i class="fas fa-clipboard-list me-2"></i>Delivery Manifest</h5>
            <div class="input-group" style="width: 300px;">
                <span class="input-group-text bg-white"><i class="fas fa-search"></i></span>
                <input type="text" id="searchInput" class="form-control" placeholder="Search PO or Client..." onkeyup="renderGroups()">
            </div>
        </div>

        <div id="logistics-container">
            <div class="text-center py-5"><div class="spinner-border text-primary"></div></div>
        </div>

    </div>

    <script>
    let rawData = [];

    document.addEventListener('DOMContentLoaded', loadLogistics);

    async function loadLogistics() {
        try {
            const res = await fetch('get_deliveries.php');
            rawData = await res.json();
            renderGroups();
        } catch(e) {
            console.error(e);
            document.getElementById('logistics-container').innerHTML = `<div class="alert alert-danger">Failed to load data.</div>`;
        }
    }

    function renderGroups() {
        const container = document.getElementById('logistics-container');
        const search = document.getElementById('searchInput').value.toLowerCase();
        
        // 1. Group Data by PO + Company
        const groups = {};
        let totalItems = 0;
        let deliveredItems = 0;
        let activePOs = 0;

        rawData.forEach(item => {
            // Basic filtering
            const str = (item.company + " " + item.po_number + " " + item.item).toLowerCase();
            if(!str.includes(search)) return;

            const key = item.po_number ? `${item.company} - PO: ${item.po_number}` : `${item.company} (No PO)`;
            
            if(!groups[key]) groups[key] = { 
                title: item.company, 
                po: item.po_number || 'No PO', 
                items: [],
                totalQty: 0,
                doneQty: 0
            };

            const isDone = (item.date_delivered && item.date_delivered !== '0000-00-00');
            
            groups[key].items.push(item);
            groups[key].totalQty++;
            if(isDone) groups[key].doneQty++;
            
            // Global Stats
            totalItems++;
            if(isDone) deliveredItems++;
        });

        // 2. Update Header Stats
        activePOs = Object.keys(groups).length;
        document.getElementById('stat-pos').innerText = activePOs;
        document.getElementById('stat-items').innerText = totalItems - deliveredItems;
        const rate = totalItems > 0 ? Math.round((deliveredItems / totalItems) * 100) : 0;
        document.getElementById('stat-completed').innerText = rate + "%";

        // 3. Render HTML
        if(activePOs === 0) {
            container.innerHTML = `<div class="text-center py-5 text-muted"><i class="fas fa-check-circle fa-3x mb-3"></i><br>All caught up! No pending deliveries matching your search.</div>`;
            return;
        }

        let html = '';
        // Convert to array to sort (put least completed first)
        const sortedGroups = Object.values(groups).sort((a,b) => (a.doneQty/a.totalQty) - (b.doneQty/b.totalQty));

        sortedGroups.forEach(g => {
            // Only show group if it has pending items (optional preference)
            // if(g.doneQty === g.totalQty) return; 

            const progress = (g.doneQty / g.totalQty) * 100;
            const isComplete = g.doneQty === g.totalQty;
            const badgeClass = isComplete ? 'bg-success' : 'bg-primary';
            const borderClass = isComplete ? 'border-success' : '';

            html += `
            <div class="po-group shadow-sm ${isComplete ? 'opacity-75' : ''}">
                <div class="po-header" data-bs-toggle="collapse" data-bs-target="#collapse-${g.po.replace(/\W/g,'')}">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="fw-bold text-dark mb-0">${g.title}</h5>
                            <small class="text-muted fw-bold"><i class="fas fa-file-invoice me-1"></i> ${g.po}</small>
                        </div>
                        <div class="text-end">
                            <span class="badge ${badgeClass} rounded-pill mb-1">${g.doneQty} / ${g.totalQty} Delivered</span>
                            <div style="font-size: 10px;" class="text-muted">${isComplete ? 'COMPLETED' : 'IN PROGRESS'}</div>
                        </div>
                    </div>
                    <div class="progress progress-thin">
                        <div class="progress-bar ${badgeClass}" style="width: ${progress}%"></div>
                    </div>
                </div>

                <div id="collapse-${g.po.replace(/\W/g,'')}" class="collapse show">
                    <div class="bg-white">
                        ${g.items.map(item => {
                            const isDelivered = (item.date_delivered && item.date_delivered !== '0000-00-00');
                            return `
                            <div class="item-row ${isDelivered ? 'delivered' : ''}">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="bg-light rounded p-2 text-center border" style="width:50px;">
                                        <div class="fw-bold h5 mb-0 text-dark">${item.quantity_requested}</div>
                                        <div style="font-size:9px;" class="text-muted">QTY</div>
                                    </div>
                                    <div>
                                        <div class="fw-bold text-dark">${item.item}</div>
                                        <div class="small text-muted">${item.category}</div>
                                    </div>
                                </div>
                                <div>
                                    ${isDelivered 
                                        ? `<span class="text-success fw-bold small"><i class="fas fa-check-double me-1"></i>Done</span>`
                                        : `<button onclick="markDelivered(${item.id}, this)" class="btn btn-outline-primary btn-sm rounded-pill px-3 fw-bold">
                                             <i class="far fa-square me-2"></i>Deliver
                                           </button>`
                                    }
                                </div>
                            </div>
                            `;
                        }).join('')}
                    </div>
                </div>
            </div>`;
        });

        container.innerHTML = html;
    }

    async function markDelivered(id, btn) {
        if(!confirm("Confirm delivery? This is irreversible.")) return;

        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
        btn.disabled = true;

        try {
            const res = await fetch('mark_delivered.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({id: id})
            });
            const data = await res.json();
            
            if(data.success) {
                // Determine if group just finished
                if(data.group_completed) {
                    alert("🎉 All items in this PO are delivered! Due Date Timer has started.");
                }
                loadLogistics(); // Refresh to update progress bars
            } else {
                alert("Error: " + data.message);
                btn.disabled = false;
                btn.innerHTML = 'Retry';
            }
        } catch(e) {
            console.error(e);
            alert("Network error");
        }
    }
    </script>
</body>
</html>