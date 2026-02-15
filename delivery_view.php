<?php
require_once 'config.php';
requireLogin();
requirePermission('view_logistics');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>NAM Logistics - Driver View</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; -webkit-tap-highlight-color: transparent; }
        
        body { 
            font-family: 'Inter', sans-serif; 
            background: #f3f4f6; 
            color: #1f2937;
            min-height: 100vh;
        }

        /* --- WEB FRIENDLY CONTAINER --- */
        /* This keeps the app centered and phone-sized on desktop screens */
        .app-container {
            max-width: 600px;
            margin: 0 auto;
            padding: 15px;
            min-height: 100vh;
            background: #f3f4f6; /* Match body bg to blend in */
            position: relative;
        }

        /* On larger screens, maybe give it a white "paper" look (Optional) */
        @media (min-width: 640px) {
            .app-container {
                background: #ffffff;
                box-shadow: 0 0 20px rgba(0,0,0,0.05);
                min-height: 100vh;
            }
            body { background: #e5e7eb; } /* Darker background outside the app */
        }

        /* --- HEADER --- */
        .header {
            background: #1e40af; 
            color: white; 
            padding: 16px 20px; 
            border-radius: 12px;
            margin-bottom: 20px; 
            display: flex; 
            justify-content: space-between; 
            align-items: center;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }
        
        .header-title h1 { font-size: 18px; font-weight: 700; margin: 0; }
        .header-title small { font-size: 11px; opacity: 0.8; font-weight: 400; }

        .header-actions {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .nav-link { 
            color: white; 
            text-decoration: none; 
            font-size: 13px; 
            font-weight: 500; 
            padding: 6px 10px;
            border-radius: 6px;
            background: rgba(255,255,255,0.1);
            transition: background 0.2s;
        }
        .nav-link:hover { background: rgba(255,255,255,0.2); }
        .nav-link.logout { background: rgba(220, 38, 38, 0.8); }

        /* --- TABS --- */
        .tabs {
            display: flex; 
            gap: 10px; 
            margin-bottom: 20px;
            overflow-x: auto; /* Allow scroll on very small screens */
            padding-bottom: 5px;
        }
        
        .tab-btn {
            flex: 1;
            padding: 12px;
            border: 1px solid #e5e7eb;
            background: white;
            color: #4b5563;
            border-radius: 8px;
            font-weight: 600;
            font-size: 13px;
            cursor: pointer;
            white-space: nowrap;
            transition: all 0.2s;
        }
        
        .tab-btn.active {
            background: #2563eb;
            color: white;
            border-color: #2563eb;
            box-shadow: 0 2px 4px rgba(37, 99, 235, 0.2);
        }

        /* --- CARDS --- */
        .card {
            background: white; 
            border-radius: 12px; 
            padding: 20px; 
            margin-bottom: 15px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05); 
            border-left: 5px solid #fbbf24; /* Pending Color */
            transition: transform 0.2s;
        }
        
        /* Hover effect only on desktop */
        @media (hover: hover) {
            .card:hover { transform: translateY(-2px); box-shadow: 0 4px 6px rgba(0,0,0,0.08); }
        }
        
        .card.delivered { border-left-color: #10b981; opacity: 0.8; }

        .row { display: flex; justify-content: space-between; margin-bottom: 8px; align-items: baseline; }
        .label { font-size: 11px; color: #6b7280; text-transform: uppercase; font-weight: 700; letter-spacing: 0.05em; }
        .value { font-size: 15px; color: #1f2937; font-weight: 500; }
        .highlight { color: #1e40af; font-weight: 700; font-size: 16px; }

        .address-box {
            background: #f8fafc; 
            padding: 12px; 
            border-radius: 8px; 
            margin: 12px 0;
            font-size: 13px; 
            color: #334155; 
            border: 1px solid #e2e8f0;
            line-height: 1.5;
        }

        /* --- ACTION BUTTON --- */
        .btn-deliver {
            width: 100%; 
            background: #2563eb; 
            color: white; 
            border: none; 
            padding: 14px;
            border-radius: 8px; 
            font-weight: 600; 
            font-size: 15px; 
            cursor: pointer;
            display: flex; 
            align-items: center; 
            justify-content: center; 
            gap: 8px;
            transition: background 0.2s, transform 0.1s;
            touch-action: manipulation; /* Improves touch response */
        }
        
        .btn-deliver:active { transform: scale(0.98); background: #1d4ed8; }
        .btn-check { background: #10b981; pointer-events: none; }

        .empty-state { 
            text-align: center; 
            padding: 60px 20px; 
            color: #9ca3af;
            font-size: 14px;
        }
        
        .divider {
            display: flex;
            align-items: center;
            margin: 25px 0 15px;
            color: #9ca3af;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .divider::before, .divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: #e5e7eb;
        }
        .divider span { padding: 0 10px; }

    </style>
</head>
<body>

    <div class="app-container">
        
        <div class="header">
            <div class="header-title">
                <h1>Delivery</h1>
                <small><?php echo date('D, M j'); ?></small>
            </div>
            <div class="header-actions">
                <?php if($_SESSION['role_id'] != 3): ?>
                    <a href="index.php" class="nav-link">Admin</a>
                <?php endif; ?>
                <a href="logout.php" class="nav-link logout">Logout</a>
            </div>
        </div>

        <div class="tabs">
            <button onclick="setFilter('pending')" id="tab-pending" class="tab-btn active">⏳ Pending</button>
            <button onclick="setFilter('today')" id="tab-today" class="tab-btn">✅ Completed</button>
        </div>

        <div id="deliveryList">
            <div class="empty-state">Loading manifests...</div>
        </div>

    </div>

    <script>
        let currentFilter = 'pending';
        let allDeliveries = [];

        function setFilter(filter) {
            currentFilter = filter;
            
            // Update Tab UI
            document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
            document.getElementById('tab-' + filter).classList.add('active');
            
            renderList();
        }

        async function loadDeliveries() {
            try {
                const res = await fetch('get_records.php');
                const data = await res.json();
                allDeliveries = data.records; // Assuming API structure returns { records: [...] }
                renderList();
            } catch(e) {
                console.error(e);
                document.getElementById('deliveryList').innerHTML = '<div class="empty-state">Error connecting to server.</div>';
            }
        }

        function renderList() {
            const list = document.getElementById('deliveryList');
            list.innerHTML = '';

            const todayStr = new Date().toISOString().split('T')[0];
            
            // Filter logic
            const pending = allDeliveries.filter(r => !r.date_delivered || r.date_delivered === '0000-00-00');
            const doneToday = allDeliveries.filter(r => r.date_delivered === todayStr);

            const itemsToShow = (currentFilter === 'pending') ? pending : doneToday;

            if(itemsToShow.length === 0) {
                list.innerHTML = `<div class="empty-state">No ${currentFilter} deliveries found.</div>`;
                return;
            }

            itemsToShow.forEach(item => {
                const isDelivered = (item.date_delivered && item.date_delivered !== '0000-00-00');
                const card = document.createElement('div');
                card.className = `card ${isDelivered ? 'delivered' : ''}`;
                
                const btnHtml = isDelivered 
                    ? `<button class="btn-deliver btn-check">✅ Delivered</button>`
                    : `<button onclick="markDelivered(${item.id}, this)" class="btn-deliver">📦 Mark as Delivered</button>`;

                card.innerHTML = `
                    <div class="row">
                        <div>
                            <div class="label">Client</div>
                            <div class="value highlight">${item.company}</div>
                        </div>
                        <div style="text-align:right;">
                            <div class="label">PO #</div>
                            <div class="value">${item.po_number || '-'}</div>
                        </div>
                    </div>

                    <div class="row">
                        <div style="flex:1; padding-right:10px;">
                            <div class="label">Item Description</div>
                            <div class="value" style="line-height:1.4;">${item.item}</div>
                        </div>
                        <div style="text-align:right; min-width:60px;">
                            <div class="label">Qty</div>
                            <div class="value highlight">${item.quantity_requested}</div>
                        </div>
                    </div>

                    <div class="address-box">
                        <div style="margin-bottom:4px;">📍 <strong>${item.address || 'No address'}</strong></div>
                        <div style="color:#64748b;">👤 ${item.contact_person_contact || 'No contact info'}</div>
                    </div>

                    ${btnHtml}
                `;
                list.appendChild(card);
            });
        }

        async function markDelivered(id, btn) {
            if(!confirm("Confirm delivery?")) return;

            const originalText = btn.innerHTML;
            btn.textContent = "Processing...";
            btn.disabled = true;
            btn.style.opacity = "0.7";

            try {
                const res = await fetch('mark_delivered.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({ id: id })
                });
                const result = await res.json();

                if(result.success) {
                    btn.className = "btn-deliver btn-check";
                    btn.innerHTML = "✅ Delivered!";
                    
                    // Optional: Animate card away if we are in 'pending' view
                    if(currentFilter === 'pending') {
                        const card = btn.closest('.card');
                        card.style.transition = "all 0.5s";
                        card.style.opacity = "0";
                        card.style.transform = "translateX(100%)";
                        setTimeout(() => {
                            // Reload data to ensure sync
                            loadDeliveries();
                        }, 300);
                    }
                } else {
                    alert("Error: " + result.message);
                    btn.innerHTML = originalText;
                    btn.disabled = false;
                    btn.style.opacity = "1";
                }
            } catch(e) {
                alert("Network error");
                btn.innerHTML = originalText;
                btn.disabled = false;
            }
        }

        // Auto-refresh every 60 seconds to get new assignments
        setInterval(loadDeliveries, 60000);
        
        // Initial Load
        loadDeliveries();
    </script>
</body>
</html>