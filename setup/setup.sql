-- NAM Supply Sales Dashboard - Database Setup
-- Run this script to create the database and tables

-- Create database
CREATE DATABASE IF NOT EXISTS sales_dashboard;
USE sales_dashboard;

-- Drop table if exists (for clean installation)
DROP TABLE IF EXISTS sales;

-- Create sales table with all required fields
CREATE TABLE sales (
    id INT AUTO_INCREMENT PRIMARY KEY,
    date DATE NOT NULL,
    sn VARCHAR(10),
    po_number VARCHAR(50),
    company VARCHAR(100),
    category VARCHAR(100),
    item VARCHAR(255),
    quantity_requested INT,
    suppliers_price DECIMAL(15,2),
    total_actual_amount DECIMAL(15,2),
    nam_unit_price DECIMAL(15,2),
    total_nam_amount DECIMAL(15,2),
    total_nam_amount_sub_total DECIMAL(15,2),
    income DECIMAL(15,2),
    income_percent DECIMAL(5,2),
    date_delivered DATE,
    payment_term VARCHAR(50),
    due_date DATE,
    si_number VARCHAR(50),
    remarks TEXT,
    supplier VARCHAR(100),
    address VARCHAR(255),
    tin VARCHAR(50),
    sales_invoice_no VARCHAR(50),
    contact_person_contact VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_date (date),
    INDEX idx_company (company),
    INDEX idx_category (category),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create view for dashboard summary
CREATE OR REPLACE VIEW dashboard_summary AS
SELECT 
    DATE(date) as sale_date,
    company,
    category,
    COUNT(*) as transaction_count,
    SUM(quantity_requested) as total_quantity,
    SUM(total_actual_amount) as total_cost,
    SUM(total_nam_amount) as total_sales,
    SUM(income) as total_profit,
    AVG(income_percent) as avg_profit_margin
FROM sales
GROUP BY DATE(date), company, category;

-- Insert sample data for testing (optional)
INSERT INTO sales (
    date, sn, po_number, company, category, item, quantity_requested,
    suppliers_price, total_actual_amount, nam_unit_price, total_nam_amount,
    income, income_percent, date_delivered, payment_term, due_date,
    si_number, supplier
) VALUES
('2026-01-03', '001', 'SLP-NAM-26-001', 'TEST COMPANY', 'OFFICE SUPPLIES', 'TEST ITEM', 10,
 10.00, 100.00, 15.00, 150.00, 50.00, 33.33, '2026-01-06', '30', '2026-02-06',
 '0001', 'TEST SUPPLIER');

-- Verify installation
SELECT 'Database setup completed successfully!' as Status;
SELECT COUNT(*) as RecordCount FROM sales;
