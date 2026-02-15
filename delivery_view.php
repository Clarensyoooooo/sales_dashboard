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
    <title>Driver Logistics - NAM Supply</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #2563eb;
            --bg: #f3f4f6;
            --surface: #ffffff;
            --text-main: #1f2937;
            --text-light: #6b7280;
            --border: #e5e7eb;
            --success: #10b981;
            --warning: #f59e0b;
        }
        
        * { box-sizing: border-box; }
        
        body { 
            font-family: 'Inter', sans-serif; 
            background: var(--bg); 
            color: var(--text-main); 
            margin: 0; 
            padding-bottom: 40px; 
            overflow-x: hidden; /* Prevent horizontal scroll */
        }
        
        .container { 
            max-width: 1400px; 
            margin: 0 auto; 
            padding: 20px; 
            width: 100%;
        }
        
        /* Stats Grid */
        .stats-grid { 
            display: grid; 
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); 
            gap: 15px; 
            margin-bottom: 25px; 
        }
        .stat-card {
            background: var(--surface); padding: 20px; border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1); display: flex; align-items: center; justify-content: space-between;
        }
        .stat-label { font-size: 13px; font-weight: 600; color: var(--text-light); text-transform: uppercase; }
        .stat-value { font-size: 28px; font-weight: 700; color: var(--text-main); }
        .stat-icon { font-size: 24px; opacity: 0.5; }

        /* Controls Bar - THE FIX */
        .controls-bar {
            display: flex; 
            flex-wrap: wrap; /* Allow wrapping */
            gap: 15px; 
            margin-bottom: 20px;
            background: var(--surface); 
            padding: 15px; 
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1); 
            align-items: center;
        }
        
        .tab-group { 
            display: flex; 
            gap: 5px; 
            background: #f1f5f9; 
            padding: 4px; 
            border-radius: 8px; 
            white-space: nowrap; /* Prevent buttons splitting */
        }
        
        .tab-btn {
            padding: 8px 16px; border: none; background: transparent; 
            color: var(--text-light); font-weight: 600; font-size: 13px; 
            cursor: pointer; border-radius: 6px; transition: all 0.2s;
        }
        .tab-btn.active { background: white; color: var(--primary); box-shadow: 0 1px 2px rgba(0,0,0,0.05); }
        
        /* Search Box - Robust Sizing */
        .search-box { 
            flex: 1; 
            min-width: 250px; /* Ensure it wraps if space is tight */
            position: relative;
            max-width: 100%;
        }
        
        .search-input {
            width: 100%; 
            padding: 10px 10px 10px 35px; 
            border: 1px solid var(--border);
            border-radius: 8px; 
            font-size: 14px; 
            outline: none;
            background: white;
        }
        .search-icon { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: var(--text-light); }

        /* Mobile Adjustments for Controls */
        @media (max-width: 768px) {
            .controls-bar {
                flex-direction: column;
                align-items: stretch; /* Stretch full width */
            }
            .tab-group {
                width: 100%;
            }
            .tab-btn {
                flex: 1;
                text-align: center;
            }
            .search-box {
                width: 100%;
                min-width: 0; /* Allow shrinking */
            }
        }

        /* Delivery Grid */
        .delivery-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(340px, 1fr)); 
            gap: 20px;
        }
        
        /* On very small phones, go full width */
        @media (max-width: 400px) {
            .delivery-grid { grid-template-columns: 1fr; }
        }

        /* Card Design */
        .d-card {
            background: var(--surface); border-radius: 12px; overflow: hidden;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1); border: 1px solid var(--border);
            display: flex; flex-direction: column; transition: transform 0.2s;
        }
        .d-card:hover { transform: translateY(-2px); box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
        
        .d-header {
            padding: 15px; border-bottom: 1px solid #f1f5f9;
            display: flex; justify-content: space-between; align-items: start;
            background: #f8fafc;
        }
        .client-name { font-weight: 700; font-size: 16px; color: #0f172a; margin-bottom: 2px; }
        .po-ref { font-size: 11px; color: #64748b; font-weight: 600; background: #e2e8f0; padding: 2px 6px; border-radius: 4px; }
        
        .d-body { padding: 15px; flex: 1; }
        .info-group { margin-bottom: 12px; display: flex; gap: 10px; }
        .icon-box { color: var(--text-light); width: 20px; flex-shrink: 0; padding-top: 2px; text-align: center; }
        .info-text { font-size: 14px; color: #334155; line-height: 1.4; }
        .info-label { display: block; font-size: 11px; font-weight: 700; color: #94a3b8; text-transform: uppercase; margin-bottom: 2px; }
        
        .remarks { 
            background: #fffbeb; color: #b45309; padding: 10px; 
            border-radius: 6px; font-size: 13px; border: 1px solid #fcd34d;
            margin-top: 10px; display: flex; gap: 8px;
        }

        .d-footer { padding: 15px; border-top: 1px solid #f1f5f9; background: white; }
        
        .btn-action {
            width: 100%; padding: 12px; border: none; border-radius: 8px;
            font-weight: 600; font-size: 14px; cursor: pointer;
            display: flex; align-items: center; justify-content: center; gap: 8px;
            transition: background 0.2s;
        }
        .btn-pending { background: var(--primary); color: white; }
        .btn-pending:hover { background: #1d4ed8; }
        .btn-done { background: #dcfce7; color: #166534; cursor: default; }

        .empty-view { text-align: center; padding: 50px; color: var(--text-light); grid-column: 1 / -1; }
    </style>
</head>
<body>

    <?php include 'navbar.php'; ?>

    <div class="container">
        
        <div class="stats-grid">
            <div class="stat-card">
                <div>
                    <div class="stat-label">Pending Today</div>
                    <div class="stat-value" id="statPending">0</div>
                </div>
                <div class="stat-icon">📦</div>
            </div>
            <div class="stat-card">
                <div>
                    <div class="stat-label">Delivered Today</div>
                    <div class="stat-value" id="statDone" style="color: var(--success);">0</div>
                </div>
                <div class="stat-icon">✅</div>
            </div>
        </div>

        <div class="controls-bar">
            <div class="tab-group">
                <button class="tab-btn active" onclick="setFilter('pending')" id="btn-pending">To Deliver</button>
                <button class="tab-btn" onclick="setFilter('done')" id="btn-done">Completed</button>
            </div>
            <div class="search-box">
                <span class="search-icon">🔍</span>
                <input type="text" class="search-input" id="searchInput" placeholder="Search client, PO, or item..." onkeyup="renderList()">
            </div>
        </div>

        <div id="gridContainer" class="delivery-grid">
            <div class="empty-view">Loading...</div>
        </div>

    </div>

    <script>
        let allRecords = [];
        let currentFilter = 'pending';

        async function loadData() {
            try {
                const res = await fetch('get_records.php');
                const data = await res.json();
                allRecords = data.records;
                renderList();
                updateStats();
            } catch(e) {
                console.error(e);
                document.getElementById('gridContainer').innerHTML = '<div class="empty-view">Error loading data.</div>';
            }
        }

        function updateStats() {
            const pending = allRecords.filter(r => !r.date_delivered || r.date_delivered === '0000-00-00').length;
            const done = allRecords.filter(r => r.date_delivered).length;
            document.getElementById('statPending').textContent = pending;
            document.getElementById('statDone').textContent = done;
        }

        function setFilter(type) {
            currentFilter = type;
            document.getElementById('btn-pending').className = type === 'pending' ? 'tab-btn active' : 'tab-btn';
            document.getElementById('btn-done').className = type === 'done' ? 'tab-btn active' : 'tab-btn';
            renderList();
        }

        function renderList() {
            const container = document.getElementById('gridContainer');
            const search = document.getElementById('searchInput').value.toLowerCase();
            
            let filtered = allRecords.filter(r => {
                const isDelivered = (r.date_delivered && r.date_delivered !== '0000-00-00');
                if (currentFilter === 'pending' && isDelivered) return false;
                if (currentFilter === 'done' && !isDelivered) return false;
                
                const text = (r.company + r.item + r.po_number).toLowerCase();
                return text.includes(search);
            });

            if (filtered.length === 0) {
                container.innerHTML = `<div class="empty-view">No ${currentFilter} deliveries found.</div>`;
                return;
            }

            container.innerHTML = filtered.map(item => {
                const isDelivered = (item.date_delivered && item.date_delivered !== '0000-00-00');
                
                const btn = isDelivered 
                    ? `<button class="btn-action btn-done">✓ Delivered on ${item.date_delivered}</button>`
                    : `<button onclick="markDelivered(${item.id})" class="btn-action btn-pending">📦 Mark as Delivered</button>`;

                const remarks = item.remarks 
                    ? `<div class="remarks"><span>⚠️</span> <div>${item.remarks}</div></div>` : '';

                return `
                <div class="d-card">
                    <div class="d-header">
                        <div style="flex:1;">
                            <div class="client-name">${item.company}</div>
                            <div style="display:flex; gap:5px; margin-top:4px;">
                                <span class="po-ref">PO: ${item.po_number || '-'}</span>
                                <span class="po-ref">SI: ${item.si_number || '-'}</span>
                            </div>
                        </div>
                        <div style="text-align:center; background:#eff6ff; padding:6px 12px; border-radius:8px; border:1px solid #dbeafe; margin-left:10px;">
                            <div style="font-size:10px; font-weight:700; color:var(--primary); margin-bottom:0;">QTY</div>
                            <div style="font-weight:800; font-size:22px; color:var(--primary); line-height:1;">${item.quantity_requested}</div>
                        </div>
                    </div>
                    <div class="d-body">
                        <div class="info-group">
                            <div class="icon-box">📦</div>
                            <div class="info-text">
                                <span class="info-label">Item</span>
                                <div style="font-weight:500;">${item.item}</div>
                            </div>
                        </div>
                        ${remarks}
                    </div>
                    <div class="d-footer">
                        ${btn}
                    </div>
                </div>
                `;
            }).join('');
        }

        async function markDelivered(id) {
            if(!confirm("Confirm delivery?")) return;
            
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
                    alert("Error: " + data.message);
                }
            } catch(e) {
                alert("Network error");
            }
        }

        setInterval(loadData, 30000);
        loadData();
    </script>
</body>
</html>