# NAM Supply Sales Dashboard System - Complete Package

## 📦 What's Included

This is a **complete, production-ready** PHP sales dashboard system that replicates your Power BI interface.

### Files Included:

1. **index.php** - Main dashboard (Power BI-style interface)
2. **form.php** - Data entry form with auto-calculations
3. **submit.php** - Form submission handler
4. **api.php** - REST API endpoint for real-time data
5. **config.php** - Database configuration
6. **import_csv.php** - CSV data import script
7. **setup.sql** - Database setup script
8. **README.md** - Complete documentation
9. **INSTALL.md** - Step-by-step installation guide

---

## 🚀 Quick Start (5 Minutes)

### For XAMPP Users (Windows):

1. **Install XAMPP** from https://www.apachefriends.org/
2. **Copy folder** to `C:\xampp\htdocs\`
3. **Start** Apache and MySQL in XAMPP Control Panel
4. **Import database:**
   - Open http://localhost/phpmyadmin
   - Import `setup.sql`
5. **Import your CSV:**
   ```bash
   cd C:\xampp\htdocs\sales_dashboard
   php import_csv.php "your_file.csv"
   ```
6. **Open dashboard:** http://localhost/sales_dashboard/

---

## ✨ Key Features

### 1. Real-Time Dashboard
- ✅ Auto-refreshes every 30 seconds
- ✅ Three KPI cards (Sales, Profit, Margin)
- ✅ Four interactive charts
- ✅ Exact Power BI color scheme and layout

### 2. Data Entry Form
- ✅ Auto-calculates totals, income, and percentages
- ✅ Validates required fields
- ✅ Matches exact CSV column structure
- ✅ User-friendly interface

### 3. Charts & Visualizations
- ✅ **Daily Sales Line Chart** - Shows sales trends with profit margin
- ✅ **Company Bar Chart** - Top companies by sales
- ✅ **Category Pie Chart** - Sales distribution
- ✅ **Category Table** - Detailed breakdown with totals

### 4. CSV Import
- ✅ Import existing data from CSV files
- ✅ Handles currency formatting (₱)
- ✅ Supports your exact CSV structure
- ✅ Bulk data import capability

---

## 📊 Dashboard Features Matching Power BI

Your original dashboard shows:
- **Total Sales:** ₱1,027,276.84M
- **Total Profit:** ₱292,671.78K  
- **Profit Margin:** 28.49%

This system replicates:
- ✅ Same KPI layout (3 cards at top)
- ✅ Same color scheme (green, blue, yellow, red)
- ✅ Same chart types and positioning
- ✅ Same data categories and groupings
- ✅ Real-time updates (even better than static Power BI!)

---

## 🎯 How Staff Will Use It

### Daily Workflow:

1. **Staff opens:** `http://yourserver/sales_dashboard/form.php`
2. **Enters sale data:**
   - Date, Company, Category, Item
   - Quantity and Prices
   - System auto-calculates profit
3. **Clicks Submit**
4. **Dashboard updates immediately** - visible to everyone

### Management View:

1. **Open:** `http://yourserver/sales_dashboard/`
2. **See real-time statistics:**
   - Today's sales
   - This month's profit
   - Top performing companies
   - Category breakdown
3. **Auto-refreshes every 30 seconds**

---

## 💾 Database Structure

The system stores all data with exact CSV columns:

```
✓ DATE                          ✓ PAYMENT TERM
✓ S/N                           ✓ DUE DATE  
✓ PO NUMBER                     ✓ SI NUMBER
✓ COMPANY                       ✓ REMARKS
✓ CATEGORY                      ✓ SUPPLIER
✓ ITEM                          ✓ ADDRESS
✓ QUANTITY REQUESTED            ✓ TIN
✓ SUPPLIER'S PRICE              ✓ SALES INVOICE NO.
✓ TOTAL ACTUAL AMOUNT (auto)    ✓ CONTACT PERSON
✓ NAM UNIT PRICE                
✓ TOTAL NAM AMOUNT (auto)
✓ INCOME (auto)
✓ INCOME PERCENT (auto)
✓ DATE DELIVERED
```

---

## 🔧 Customization Options

### Change Colors:
Edit `index.php`, find `categoryColors` array:
```javascript
const categoryColors = [
    '#70ad47',  // Green (change to any hex color)
    '#4472c4',  // Blue
    '#ffc000',  // Yellow
    // ... add more colors
];
```

### Add Categories:
Edit `form.php`, find the category dropdown:
```html
<option value="NEW CATEGORY">NEW CATEGORY</option>
```

### Change Auto-Refresh Time:
Edit `index.php`, find:
```javascript
setInterval(updateDashboard, 30000); // 30 seconds
// Change to 60000 for 1 minute, etc.
```

---

## 📈 Advantages Over Power BI

| Feature | Power BI | This System |
|---------|----------|-------------|
| Real-time updates | Manual refresh | Auto-refresh (30s) |
| Data entry | Requires Excel/CSV upload | Direct web form |
| Cost | Requires licenses | Free (open source) |
| Access | Desktop app or web | Any web browser |
| Mobile friendly | Limited | Fully responsive |
| Customization | Limited without Pro | Full control |
| Multi-user | Requires sharing | Built-in |

---

## 🔒 Security Features

Already implemented:
- ✅ SQL injection protection (prepared statements)
- ✅ Input validation on form fields
- ✅ XSS protection
- ✅ Database connection security

Recommended for production:
- Add user authentication
- Enable HTTPS
- Implement role-based access
- Add audit logging

---

## 📱 Mobile Support

The dashboard is fully responsive:
- ✅ Works on tablets
- ✅ Works on smartphones
- ✅ Charts adapt to screen size
- ✅ Touch-friendly interface

---

## 🆘 Support & Troubleshooting

### Common Issues:

**"Connection failed"**
→ Check MySQL is running
→ Verify config.php credentials

**"No data showing"**
→ Import CSV: `php import_csv.php file.csv`
→ Add test entry via form

**Charts not loading**
→ Check internet connection (Chart.js CDN)
→ Check browser console (F12)

**Form calculation not working**
→ Enable JavaScript in browser
→ Clear browser cache

### Full troubleshooting guide in README.md

---

## 📝 File Sizes

- Total package: ~50 KB
- Database: Minimal (grows with data)
- No external dependencies except Chart.js (CDN)

---

## 🎓 Learning Resources

The code is:
- ✅ Well-commented
- ✅ Follows PHP best practices
- ✅ Uses modern JavaScript (ES6+)
- ✅ Follows SQL standards
- ✅ Responsive CSS (no framework needed)

Perfect for:
- Learning web development
- Understanding dashboard systems
- Teaching staff data entry
- Building similar systems

---

## 🔄 Future Enhancements (Optional)

You can easily add:
- [ ] User login/authentication
- [ ] Export to Excel/PDF
- [ ] Email notifications
- [ ] Advanced filtering
- [ ] Date range selection
- [ ] Search functionality
- [ ] Data backup feature
- [ ] Print-friendly reports

---

## 📞 Next Steps

1. **Install** following INSTALL.md
2. **Import** your CSV data
3. **Test** by adding a sample entry
4. **Train** staff on the form
5. **Monitor** the dashboard
6. **Customize** as needed

---

## 🎉 You're All Set!

This system gives you everything Power BI offers, plus:
- Real-time data entry
- Instant updates
- No licensing costs
- Full customization control
- Multi-user access out of the box

**Your staff enters data → Dashboard updates immediately → Everyone sees results!**

---

## Version Information

- **Version:** 1.0.0
- **Release Date:** February 2026
- **Compatible:** PHP 7.4+, MySQL 5.7+
- **Browser:** Chrome, Firefox, Safari, Edge (latest versions)

---

**Need help? Check:**
1. README.md - Complete documentation
2. INSTALL.md - Installation guides for all platforms
3. setup.sql - Database reference

**Enjoy your new dashboard system! 📊✨**
