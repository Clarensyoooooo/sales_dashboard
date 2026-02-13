<?php require_once 'config.php'; requireLogin(); requirePermission('view_logistics'); ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NAM Logistics - Driver View</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f3f4f6; padding: 15px; }

        .header {
            background: #1e40af; color: white; padding: 15px; border-radius: 12px;
            margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }
        .header h1 { font-size: 18px; font-weight: 700; }
        .back-link { color: white; text-decoration: none; font-size: 13px; opacity: 0.8; }

        .card {
            background: white; border-radius: 12px; padding: 20px; margin-bottom: 15px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05); border-left: 5px solid #fbbf24; /* Amber for Pending */
        }
        
        .card.delivered { border-left-color: #10b981; opacity: 0.6; }

        .row { display: flex; justify-content: space-between; margin-bottom: 8px; }
        .label { font-size: 11px; color: #6b7280; text-transform: uppercase; font-weight: 600; }
        .value { font-size: 14px; color: #1f2937; font-weight: 500; }
        .highlight { color: #1e40af; font-weight: 700; }

        .address-box {
            background: #f8fafc; padding: 10px; border-radius: 6px; margin: 10px 0;
            font-size: 13px; color: #334155; border: 1px solid #e2e8f0;
        }

        .btn-deliver {
            width: 100%; background: #2563eb; color: white; border: none; padding: 12px;
            border-radius: 8px; font-weight: 600; font-size: 14px; cursor: pointer;
            display: flex; align-items: center; justify-content: center; gap: 8px;
            transition: background 0.2s;
        }
        .btn-deliver:active { transform: scale(0.98); }
        .btn-deliver:hover { background: #1d4ed8; }
        
        .btn-check { background: #10b981; pointer-events: none; }

        .empty-state { text-align: center; padding: 40px; color: #9ca3af; }
    </style>
</head>
<body>

    <div class="header">
        <h1>🚚 Delivery Manifest</h1>
        <a href="index.php" class="back-link">Admin View &rarr;</a>
    </div>

    <div style="display:flex; gap:10px; margin-bottom:15px;">
        <button onclick="filter('pending')" class="btn-deliver" style="background:white; color:#333; border:1px solid #ddd;">⏳ Pending</button>
        <button onclick="filter('today')" class="btn-deliver" style="background:white; color:#333; border:1px solid #ddd;">✅ Delivered Today</button>
    </div>

    <div id="deliveryList">
        <div class="empty-state">Loading deliveries...</div>
    </div>

    <script>
        async function loadDeliveries() {
            // We reuse get_records.php but filter it in JS for now
            // In production, make a dedicated API to save bandwidth
            const res = await fetch('get_records.php');
            const data = await res.json();
            
            const list = document.getElementById('deliveryList');
            list.innerHTML = '';

            // Filter: Show only items that are NOT delivered yet OR delivered today
            const todayStr = new Date().toISOString().split('T')[0];
            
            const pending = data.filter(r => !r.date_delivered || r.date_delivered === '0000-00-00');
            const deliveredToday = data.filter(r => r.date_delivered === todayStr);

            // Combine for display or toggle (Here we show Pending by default)
            // Let's show Pending at top
            
            if(pending.length === 0 && deliveredToday.length === 0) {
                list.innerHTML = '<div class="empty-state">No deliveries scheduled.</div>';
                return;
            }

            // Render Pending
            pending.forEach(item => renderCard(item, false, list));
            
            // Render Completed (Optional, maybe at bottom)
            if(deliveredToday.length > 0) {
                const divider = document.createElement('div');
                divider.innerHTML = '<h3 style="margin:20px 0 10px; color:#6b7280; font-size:14px;">COMPLETED TODAY</h3>';
                list.appendChild(divider);
                deliveredToday.forEach(item => renderCard(item, true, list));
            }
        }

        function renderCard(item, isDelivered, container) {
            const card = document.createElement('div');
            card.className = `card ${isDelivered ? 'delivered' : ''}`;
            
            const btnHtml = isDelivered 
                ? `<button class="btn-deliver btn-check">✅ Delivered on ${item.date_delivered}</button>`
                : `<button onclick="markDelivered(${item.id}, this)" class="btn-deliver">📦 Mark as Delivered</button>`;

            card.innerHTML = `
                <div class="row">
                    <div>
                        <div class="label">Company</div>
                        <div class="value highlight">${item.company}</div>
                    </div>
                    <div style="text-align:right;">
                        <div class="label">PO Number</div>
                        <div class="value">${item.po_number || '-'}</div>
                    </div>
                </div>

                <div class="row">
                    <div>
                        <div class="label">Item</div>
                        <div class="value">${item.item}</div>
                    </div>
                    <div style="text-align:right;">
                        <div class="label">Qty</div>
                        <div class="value">${item.quantity_requested}</div>
                    </div>
                </div>

                <div class="address-box">
                    📍 <strong>Dest:</strong> ${item.address || 'No address provided'}<br>
                    👤 <strong>Contact:</strong> ${item.contact_person_contact || 'N/A'}
                </div>

                ${btnHtml}
            `;
            container.appendChild(card);
        }

        async function markDelivered(id, btn) {
            if(!confirm("Confirm delivery of this item?")) return;

            btn.textContent = "Updating...";
            btn.disabled = true;

            try {
                const res = await fetch('mark_delivered.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({ id: id })
                });
                const result = await res.json();

                if(result.success) {
                    // Visual feedback
                    btn.className = "btn-deliver btn-check";
                    btn.textContent = "✅ Delivered!";
                    // Move card visual style
                    btn.closest('.card').classList.add('delivered');
                    btn.closest('.card').style.borderLeftColor = "#10b981";
                } else {
                    alert("Error: " + result.message);
                    btn.textContent = "📦 Mark as Delivered";
                    btn.disabled = false;
                }
            } catch(e) {
                alert("Network error");
                btn.disabled = false;
            }
        }

        loadDeliveries();
    </script>
</body>
</html>