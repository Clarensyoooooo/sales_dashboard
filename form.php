<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales Data Entry Form</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f5f5;
            padding: 20px;
        }

        .container {
            max-width: 900px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        h1 {
            color: #333;
            margin-bottom: 10px;
            font-size: 28px;
        }

        .subtitle {
            color: #666;
            margin-bottom: 30px;
        }

        .nav-buttons {
            margin-bottom: 20px;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            text-decoration: none;
            display: inline-block;
            margin-right: 10px;
        }

        .btn-primary {
            background: #0078d4;
            color: white;
        }

        .btn-primary:hover {
            background: #106ebe;
        }

        .btn-secondary {
            background: #f3f2f1;
            color: #323130;
        }

        .btn-secondary:hover {
            background: #e1dfdd;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group.full-width {
            grid-column: 1 / -1;
        }

        label {
            color: #323130;
            font-weight: 600;
            margin-bottom: 5px;
            font-size: 14px;
        }

        label .required {
            color: #a4262c;
        }

        input, select, textarea {
            padding: 8px 12px;
            border: 1px solid #8a8886;
            border-radius: 2px;
            font-size: 14px;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        input:focus, select:focus, textarea:focus {
            outline: none;
            border-color: #0078d4;
        }

        textarea {
            resize: vertical;
            min-height: 80px;
        }

        .section-title {
            font-size: 18px;
            color: #323130;
            font-weight: 600;
            margin: 30px 0 15px 0;
            padding-bottom: 10px;
            border-bottom: 2px solid #0078d4;
        }

        .alert {
            padding: 12px 20px;
            border-radius: 4px;
            margin-bottom: 20px;
        }

        .alert-success {
            background: #dff6dd;
            color: #107c10;
            border: 1px solid #107c10;
        }

        .alert-error {
            background: #fde7e9;
            color: #a4262c;
            border: 1px solid #a4262c;
        }

        .calculated-field {
            background: #f3f2f1;
            font-weight: 600;
            color: #323130;
        }

        @media (max-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Sales Data Entry Form</h1>
        <p class="subtitle">NAM Supply - Sales Encoder</p>

        <div class="nav-buttons">
    <a href="index.php" class="btn btn-primary">📊 View Dashboard</a>
    <a href="products.php" class="btn btn-secondary">📦 Price List</a>
    <a href="records.php" class="btn btn-secondary">📋 View Records</a>
</div>

        <?php if (isset($_GET['error'])): ?>
            <div class="alert alert-error">
                ❌ Error submitting data. Please try again.
            </div>
        <?php endif; ?>

        <form action="submit.php" method="POST" id="salesForm">
            <div class="section-title">Basic Information</div>
            <div class="form-grid">
                <div class="form-group">
                    <label>DATE <span class="required">*</span></label>
                    <input type="date" name="date" required>
                </div>

                <div class="form-group">
                    <label>S/N</label>
                    <input type="text" name="sn" placeholder="e.g., 001">
                </div>

                <div class="form-group">
                    <label>PO NUMBER</label>
                    <input type="text" name="po_number" placeholder="e.g., SLP-NAM-26-003">
                </div>

                <div class="form-group">
                    <label>COMPANY <span class="required">*</span></label>
                    <input type="text" name="company" required placeholder="e.g., SUMOPAK">
                </div>

                <div class="form-group">
                    <label>CATEGORY <span class="required">*</span></label>
                    <select name="category" required>
                        <option value="">Select Category</option>
                        <option value="OFFICE TOOLS AND EQUIPMENT">OFFICE TOOLS AND EQUIPMENT</option>
                        <option value="CONSUMABLES">CONSUMABLES</option>
                        <option value="CLEANING MATERIALS">CLEANING MATERIALS</option>
                        <option value="OFFICE SUPPLY">OFFICE SUPPLY</option>
                        <option value="MATERIALS">MATERIALS</option>
                        <option value="OFFICE FURNITURE & FIXTURES">OFFICE FURNITURE & FIXTURES</option>
                        <option value="COMPANY UNIFORM">COMPANY UNIFORM</option>
                        <option value="PPE">PPE</option>
                        <option value="OFFICE SUPPLIES">OFFICE SUPPLIES</option>
                    </select>
                </div>

                <div class="form-group full-width">
    <label>ITEM <span class="required">*</span></label>
    <input type="text" name="item" id="itemInput" list="itemList" required placeholder="Start typing to search..." autocomplete="off">
    <datalist id="itemList"></datalist>
</div>
            </div>

            <div class="section-title">Pricing Information</div>
            <div class="form-grid">
                <div class="form-group">
                    <label>QUANTITY REQUESTED <span class="required">*</span></label>
                    <input type="number" name="quantity_requested" id="quantity" required min="1" value="1">
                </div>

                <div class="form-group">
                    <label>SUPPLIER'S PRICE (₱) <span class="required">*</span></label>
                    <input type="number" name="suppliers_price" id="suppliersPrice" required step="0.01" min="0">
                </div>

                <div class="form-group">
                    <label>TOTAL ACTUAL AMOUNT (₱)</label>
                    <input type="number" id="totalActual" readonly class="calculated-field" step="0.01">
                </div>

                <div class="form-group">
                    <label>NAM UNIT PRICE (₱) <span class="required">*</span></label>
                    <input type="number" name="nam_unit_price" id="namUnitPrice" required step="0.01" min="0">
                </div>

                <div class="form-group">
                    <label>TOTAL NAM AMOUNT (₱)</label>
                    <input type="number" id="totalNam" readonly class="calculated-field" step="0.01">
                </div>

                <div class="form-group">
                    <label>INCOME (₱)</label>
                    <input type="number" id="income" readonly class="calculated-field" step="0.01">
                </div>

                <div class="form-group">
                    <label>INCOME PERCENT (%)</label>
                    <input type="number" id="incomePercent" readonly class="calculated-field" step="0.01">
                </div>
            </div>

            <div class="section-title">Delivery & Payment Information</div>
            <div class="form-grid">
                <div class="form-group">
                    <label>DATE DELIVERED</label>
                    <input type="date" name="date_delivered">
                </div>

                <div class="form-group">
                    <label>PAYMENT TERM</label>
                    <input type="text" name="payment_term" placeholder="e.g., 30 days">
                </div>

                <div class="form-group">
                    <label>DUE DATE</label>
                    <input type="date" name="due_date">
                </div>

                <div class="form-group">
                    <label>SI NUMBER</label>
                    <input type="text" name="si_number">
                </div>

                <div class="form-group full-width">
                    <label>REMARKS</label>
                    <textarea name="remarks"></textarea>
                </div>
            </div>

            <div class="section-title">Supplier Information</div>
            <div class="form-grid">
                <div class="form-group">
                    <label>SUPPLIER</label>
                    <input type="text" name="supplier" placeholder="e.g., PAMCO">
                </div>

                <div class="form-group">
                    <label>TIN</label>
                    <input type="text" name="tin">
                </div>

                <div class="form-group full-width">
                    <label>ADDRESS</label>
                    <input type="text" name="address">
                </div>

                <div class="form-group">
                    <label>SALES INVOICE NO.</label>
                    <input type="text" name="sales_invoice_no">
                </div>

                <div class="form-group">
                    <label>CONTACT PERSON / CONTACT #</label>
                    <input type="text" name="contact_person_contact">
                </div>
            </div>

            <div style="margin-top: 30px;">
                <button type="submit" class="btn btn-primary">💾 Submit Entry</button>
                <button type="reset" class="btn btn-secondary">🔄 Reset Form</button>
            </div>
        </form>
    </div>

   <script>
    // --- 1. EXISTING CALCULATION LOGIC ---
    const quantity = document.getElementById('quantity');
    const suppliersPrice = document.getElementById('suppliersPrice');
    const namUnitPrice = document.getElementById('namUnitPrice');
    const totalActual = document.getElementById('totalActual');
    const totalNam = document.getElementById('totalNam');
    const income = document.getElementById('income');
    const incomePercent = document.getElementById('incomePercent');

    function calculate() {
        const qty = parseFloat(quantity.value) || 0;
        const supplierPrice = parseFloat(suppliersPrice.value) || 0;
        const namPrice = parseFloat(namUnitPrice.value) || 0;

        const totalActualAmt = qty * supplierPrice;
        const totalNamAmt = qty * namPrice;
        const incomeAmt = totalNamAmt - totalActualAmt;
        const incomePercentAmt = totalNamAmt > 0 ? (incomeAmt / totalNamAmt) * 100 : 0;

        totalActual.value = totalActualAmt.toFixed(2);
        totalNam.value = totalNamAmt.toFixed(2);
        income.value = incomeAmt.toFixed(2);
        incomePercent.value = incomePercentAmt.toFixed(2);
    }

    quantity.addEventListener('input', calculate);
    suppliersPrice.addEventListener('input', calculate);
    namUnitPrice.addEventListener('input', calculate);

    // --- 2. NEW: AUTO-SEARCH FUNCTIONALITY ---
    const itemInput = document.getElementById('itemInput');
    const itemList = document.getElementById('itemList');

    // Listen for typing to show suggestions
    itemInput.addEventListener('input', function() {
        const val = this.value;
        
        // If user clears the input, don't search
        if (val.length < 2) return; 

        fetch(`get_item.php?q=${encodeURIComponent(val)}`)
            .then(response => response.json())
            .then(data => {
                itemList.innerHTML = '';
                data.forEach(item => {
                    const option = document.createElement('option');
                    option.value = item.value;
                    itemList.appendChild(option);
                });
                
                // Check if user selected an exact match
                checkForExactMatch(val);
            })
            .catch(err => console.error("Error fetching items:", err));
    });

    // Check database for prices when item is selected
    function checkForExactMatch(val) {
        fetch(`get_item.php?exact=${encodeURIComponent(val)}`)
            .then(response => response.json())
            .then(data => {
                if (data) {
                    console.log("Item found:", data);
                    
                    // 1. Auto-fill Prices
                    if(data.supplier_price) document.getElementById('suppliersPrice').value = data.supplier_price;
                    if(data.nam_price) document.getElementById('namUnitPrice').value = data.nam_price;
                    
                    // 2. Auto-select Category
                    const catSelect = document.querySelector('select[name="category"]');
                    for (let i = 0; i < catSelect.options.length; i++) {
                        // Compare ignoring case just in case
                        if (catSelect.options[i].value.toUpperCase() === data.category_code.toUpperCase()) {
                            catSelect.selectedIndex = i;
                            break;
                        }
                    }

                    // 3. Auto-fill Supplier
                    const supplierInput = document.querySelector('input[name="supplier"]');
                    if(supplierInput && data.supplier) supplierInput.value = data.supplier;

                    // 4. Trigger calculation
                    calculate();
                }
            });
    }
</script>
</body>
</html>