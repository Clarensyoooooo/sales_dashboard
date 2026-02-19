<?php
require_once 'config.php';
requireLogin();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logistics Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background-color: #f4f6f9; }
        
        /* Company Card */
        .company-card { border: none; border-radius: 12px; margin-bottom: 20px; background: white; box-shadow: 0 2px 5px rgba(0,0,0,0.05); overflow: hidden; }
        .company-header { background: #343a40; color: white; padding: 12px 20px; display: flex; justify-content: space-between; align-items: center; }
        
        /* PO Section inside Company */
        .po-section { border-bottom: 1px solid #eee; padding: 0 0 10px 0; }
        .po-section:last-child { border-bottom: none; }
        .po-header { background: #f8f9fa; padding: 8px 20px; font-weight: 600; color: #555; font-size: 0.9rem; display: flex; justify-content: space-between; align-items: center; }
        
        /* Item Row */
        .item-row { padding: 12px 20px; display: flex; align-items: center; border-bottom: 1px solid #f1f1f1; transition: background 0.2s; }
        .item-row:hover { background: #fafafa; }
        .item-row.done { background-color: #f0fdf4; opacity: 0.8; }
        .item-row.done .item-name { text-decoration: line-through; color: #888; }
        
        .qty-box { width: 50px; text-align: center; background: #e9ecef; border-radius: 8px; padding: 5px; margin-right: 15px; }
        .qty-val { font-weight: 700; font-size: 1.1rem; line-height: 1; }
        .qty-lbl { font-size: 0.65rem; text-transform: uppercase; color: #666; }

        .btn-deliver { border-radius: 20px; font-size: 0.85rem; padding: 5px 15px; }
    </style>
</head>
<body>

    <?php include 'navbar.php'; ?>

    <div class="container py-4" style="max-width: 900px;">
        
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="fw-bold text-dark mb-0"><i class="fas fa-truck-loading me-2"></i>Daily Logistics</h4>
                <small class="text-muted">Grouped by Company & PO</small>
            </div>
            <button class="btn btn-light shadow-sm" onclick="loadLogistics()"><i class="fas fa-sync-alt"></i> Refresh</button>
        </div>

        <div class="mb-4">
            <input type="text" id="searchInput" class="form-control form-control-lg shadow-sm border-0" placeholder="Search Company, PO, or Item..." onkeyup="render()">
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
            render();
        } catch(e) {
            console.error(e);
            document.getElementById('logistics-container').innerHTML = `<div class="alert alert-danger">Failed to load data.</div>`;
        }
    }

    function render() {
        const container = document.getElementById('logistics-container');
        const search = document.getElementById('searchInput').value.toLowerCase();
        
        // 1. Group by Company -> PO
        const companyGroups = {};

        rawData.forEach(item => {
            // Search Filter
            const str = (item.company + " " + item.po_number + " " + item.item).toLowerCase();
            if(!str.includes(search)) return;

            // Init Company Group
            if(!companyGroups[item.company]) {
                companyGroups[item.company] = {
                    name: item.company,
                    pos: {}
                };
            }

            // Init PO Group inside Company
            const poKey = item.po_number ? item.po_number : 'NO_PO';
            if(!companyGroups[item.company].pos[poKey]) {
                companyGroups[item.company].pos[poKey] = {
                    id: poKey,
                    items: [],
                    total: 0,
                    done: 0
                };
            }

            // Add Item
            const isDone = (item.date_delivered && item.date_delivered !== '0000-00-00');
            companyGroups[item.company].pos[poKey].items.push(item);
            companyGroups[item.company].pos[poKey].total++;
            if(isDone) companyGroups[item.company].pos[poKey].done++;
        });

        // 2. Render HTML
        if(Object.keys(companyGroups).length === 0) {
            container.innerHTML = `<div class="text-center py-5 text-muted">No pending deliveries found.</div>`;
            return;
        }

        let html = '';
        
        // Loop Companies
        for (const [compName, compData] of Object.entries(companyGroups)) {
            html += `
            <div class="company-card">
                <div class="company-header">
                    <span class="fw-bold"><i class="fas fa-building me-2"></i>${compName}</span>
                </div>
                <div class="bg-white">`;

            // Loop POs within Company
            for (const [poId, poData] of Object.entries(compData.pos)) {
                const poLabel = poId === 'NO_PO' ? 'No PO Number' : `PO: ${poId}`;
                const isPoComplete = poData.total === poData.done;
                const progress = Math.round((poData.done / poData.total) * 100);

                html += `
                    <div class="po-section">
                        <div class="po-header">
                            <span>${poLabel}</span>
                            <span class="badge ${isPoComplete ? 'bg-success' : 'bg-secondary'}">${poData.done}/${poData.total}</span>
                        </div>
                        <div class="progress" style="height: 4px;">
                            <div class="progress-bar ${isPoComplete ? 'bg-success' : 'bg-primary'}" style="width: ${progress}%"></div>
                        </div>

                        <div>
                        ${poData.items.map(item => {
                            const isDelivered = (item.date_delivered && item.date_delivered !== '0000-00-00');
                            return `
                            <div class="item-row ${isDelivered ? 'done' : ''}">
                                <div class="qty-box">
                                    <div class="qty-val">${item.quantity_requested}</div>
                                    <div class="qty-lbl">QTY</div>
                                </div>
                                <div class="flex-grow-1">
                                    <div class="fw-bold text-dark item-name">${item.item}</div>
                                    <small class="text-muted">${item.address || ''}</small>
                                </div>
                                <div>
                                    ${isDelivered 
                                        ? `<span class="text-success fw-bold small"><i class="fas fa-check me-1"></i>Delivered</span>`
                                        : `<button onclick="markDelivered(${item.id}, this)" class="btn btn-primary btn-sm btn-deliver shadow-sm">
                                             Deliver
                                           </button>`
                                    }
                                </div>
                            </div>
                            `;
                        }).join('')}
                        </div>
                    </div>`;
            }

            html += `</div></div>`; // Close Company Card
        }

        container.innerHTML = html;
    }

    async function markDelivered(id, btn) {
        if(!confirm("Mark this item as delivered?")) return;

        // UI Feedback immediately
        btn.innerHTML = '<i class="fas fa-circle-notch fa-spin"></i>';
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
                    alert("🎉 PO Completed! Due Date Timer started.");
                }
                loadLogistics(); // Reload to update groups/progress
            } else {
                alert("Error: " + data.message);
                btn.disabled = false;
                btn.innerHTML = 'Deliver';
            }
        } catch(e) {
            console.error(e);
            alert("Network error");
            btn.disabled = false;
            btn.innerHTML = 'Deliver';
        }
    }
    </script>
</body>
</html>