-- CellPro Multi-Branch Mobile Shop Management System
-- Database Schema & Initial Data

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS audit_logs;
DROP TABLE IF EXISTS application_settings;
DROP TABLE IF EXISTS warranty_claims;
DROP TABLE IF EXISTS warranties;
DROP TABLE IF EXISTS daily_closings;
DROP TABLE IF EXISTS account_transfers;
DROP TABLE IF EXISTS financial_accounts;
DROP TABLE IF EXISTS journal_entry_lines;
DROP TABLE IF EXISTS journal_entries;
DROP TABLE IF EXISTS accounts;
DROP TABLE IF EXISTS account_groups;
DROP TABLE IF EXISTS expenses;
DROP TABLE IF EXISTS expense_categories;
DROP TABLE IF EXISTS repairing_bill_items;
DROP TABLE IF EXISTS repairing_bills;
DROP TABLE IF EXISTS sales_return_items;
DROP TABLE IF EXISTS sales_returns;
DROP TABLE IF EXISTS sale_payments;
DROP TABLE IF EXISTS sale_items;
DROP TABLE IF EXISTS sales;
DROP TABLE IF EXISTS purchase_return_items;
DROP TABLE IF EXISTS purchase_returns;
DROP TABLE IF EXISTS purchase_payments;
DROP TABLE IF EXISTS purchase_items;
DROP TABLE IF EXISTS purchases;
DROP TABLE IF EXISTS customers;
DROP TABLE IF EXISTS suppliers;
DROP TABLE IF EXISTS stock_adjustments;
DROP TABLE IF EXISTS stock_transfer_items;
DROP TABLE IF EXISTS stock_transfers;
DROP TABLE IF EXISTS stock_movements;
DROP TABLE IF EXISTS device_inspections;
DROP TABLE IF EXISTS mobile_devices;
DROP TABLE IF EXISTS products;
DROP TABLE IF EXISTS units;
DROP TABLE IF EXISTS models;
DROP TABLE IF EXISTS brands;
DROP TABLE IF EXISTS categories;
DROP TABLE IF EXISTS role_permissions;
DROP TABLE IF EXISTS permissions;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS roles;
DROP TABLE IF EXISTS branches;
DROP TABLE IF EXISTS businesses;

SET FOREIGN_KEY_CHECKS = 1;

-- 1. BUSINESS & BRANCHES
CREATE TABLE businesses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    tagline VARCHAR(255) DEFAULT NULL,
    email VARCHAR(100) DEFAULT NULL,
    phone VARCHAR(50) DEFAULT NULL,
    address TEXT DEFAULT NULL,
    currency VARCHAR(10) DEFAULT 'PKR',
    currency_symbol VARCHAR(10) DEFAULT 'Rs.',
    logo VARCHAR(255) DEFAULT NULL,
    tax_enabled TINYINT(1) DEFAULT 0,
    tax_rate DECIMAL(5,2) DEFAULT 0.00,
    tax_name VARCHAR(50) DEFAULT 'Sales Tax',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE branches (
    id INT AUTO_INCREMENT PRIMARY KEY,
    business_id INT NOT NULL DEFAULT 1,
    name VARCHAR(100) NOT NULL,
    code VARCHAR(20) NOT NULL UNIQUE,
    address TEXT DEFAULT NULL,
    phone VARCHAR(50) DEFAULT NULL,
    manager_name VARCHAR(100) DEFAULT NULL,
    opening_date DATE DEFAULT NULL,
    invoice_prefix VARCHAR(10) DEFAULT 'INV',
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. ROLES, USERS & PERMISSIONS
CREATE TABLE roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE,
    description VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    role_id INT NOT NULL,
    branch_id INT DEFAULT NULL, -- NULL means all branches (Super Admin)
    name VARCHAR(100) NOT NULL,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) DEFAULT NULL,
    password VARCHAR(255) NOT NULL,
    phone VARCHAR(50) DEFAULT NULL,
    status ENUM('active','inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE RESTRICT,
    FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    module VARCHAR(50) NOT NULL,
    action VARCHAR(50) NOT NULL,
    description VARCHAR(255) DEFAULT NULL,
    UNIQUE KEY uq_mod_act (module, action)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE role_permissions (
    role_id INT NOT NULL,
    permission_id INT NOT NULL,
    PRIMARY KEY (role_id, permission_id),
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
    FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. PRODUCT ATTRIBUTES & ACCESSORIES
CREATE TABLE categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    type ENUM('mobile','accessory','spare_part') DEFAULT 'accessory',
    description TEXT DEFAULT NULL,
    status ENUM('active','inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE brands (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    status ENUM('active','inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE models (
    id INT AUTO_INCREMENT PRIMARY KEY,
    brand_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (brand_id) REFERENCES brands(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE units (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL,
    short_name VARCHAR(10) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    branch_id INT NOT NULL,
    category_id INT DEFAULT NULL,
    brand_id INT DEFAULT NULL,
    name VARCHAR(200) NOT NULL,
    sku VARCHAR(100) DEFAULT NULL,
    barcode VARCHAR(100) DEFAULT NULL,
    unit_id INT DEFAULT NULL,
    product_type ENUM('accessory','spare_part') DEFAULT 'accessory',
    cost_price DECIMAL(12,2) DEFAULT 0.00,
    retail_price DECIMAL(12,2) DEFAULT 0.00,
    wholesale_price DECIMAL(12,2) DEFAULT 0.00,
    quantity INT DEFAULT 0,
    min_stock_level INT DEFAULT 5,
    image VARCHAR(255) DEFAULT NULL,
    description TEXT DEFAULT NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
    FOREIGN KEY (brand_id) REFERENCES brands(id) ON DELETE SET NULL,
    INDEX idx_barcode (barcode),
    INDEX idx_sku (sku),
    INDEX idx_prod_branch (branch_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. CUSTOMERS & SUPPLIERS
CREATE TABLE customers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    phone VARCHAR(30) NOT NULL,
    alt_phone VARCHAR(30) DEFAULT NULL,
    email VARCHAR(100) DEFAULT NULL,
    cnic VARCHAR(30) DEFAULT NULL,
    address TEXT DEFAULT NULL,
    opening_balance DECIMAL(12,2) DEFAULT 0.00,
    current_balance DECIMAL(12,2) DEFAULT 0.00, -- Positive = customer owes us (debit)
    credit_limit DECIMAL(12,2) DEFAULT 50000.00,
    remarks TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_cust_phone (phone)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE suppliers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    contact_person VARCHAR(100) DEFAULT NULL,
    phone VARCHAR(30) NOT NULL,
    alt_phone VARCHAR(30) DEFAULT NULL,
    email VARCHAR(100) DEFAULT NULL,
    address TEXT DEFAULT NULL,
    opening_balance DECIMAL(12,2) DEFAULT 0.00,
    current_balance DECIMAL(12,2) DEFAULT 0.00, -- Positive = we owe supplier (credit)
    remarks TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_supp_phone (phone)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. MOBILE PHONE INVENTORY (INDIVIDUAL DEVICE TRACKING)
CREATE TABLE mobile_devices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    branch_id INT NOT NULL,
    supplier_id INT DEFAULT NULL,
    brand_id INT DEFAULT NULL,
    model_id INT DEFAULT NULL,
    model_name VARCHAR(150) NOT NULL,
    ram VARCHAR(20) DEFAULT NULL,
    storage VARCHAR(20) DEFAULT NULL,
    color VARCHAR(30) DEFAULT NULL,
    imei_1 VARCHAR(25) NOT NULL UNIQUE,
    imei_2 VARCHAR(25) DEFAULT NULL UNIQUE,
    serial_number VARCHAR(50) DEFAULT NULL,
    device_condition ENUM('new','used') DEFAULT 'new',
    battery_health INT DEFAULT 100,
    pta_status ENUM('approved','non_approved','cpid','geve') DEFAULT 'approved',
    network_status VARCHAR(50) DEFAULT 'Factory Unlocked',
    warranty_months INT DEFAULT 0,
    purchase_price DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    additional_cost DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    actual_cost DECIMAL(12,2) NOT NULL DEFAULT 0.00, -- purchase_price + additional_cost
    selling_price DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    device_status ENUM('available','reserved','sold','in_repair','damaged','returned','transferred','written_off') DEFAULT 'available',
    purchase_date DATE NOT NULL,
    image VARCHAR(255) DEFAULT NULL,
    description TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE RESTRICT,
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE SET NULL,
    FOREIGN KEY (brand_id) REFERENCES brands(id) ON DELETE SET NULL,
    INDEX idx_imei1 (imei_1),
    INDEX idx_status (device_status),
    INDEX idx_dev_branch (branch_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE device_inspections (
    id INT AUTO_INCREMENT PRIMARY KEY,
    device_id INT NOT NULL,
    screen_condition VARCHAR(50) DEFAULT 'Flawless',
    body_condition VARCHAR(50) DEFAULT 'Flawless',
    battery_health INT DEFAULT 100,
    camera_condition VARCHAR(50) DEFAULT 'Working Perfectly',
    biometrics VARCHAR(50) DEFAULT 'Working (Face/Touch ID)',
    charging_port VARCHAR(50) DEFAULT 'Working',
    speaker_mic VARCHAR(50) DEFAULT 'Working',
    pta_status VARCHAR(50) DEFAULT 'PTA Approved',
    accessories_received TEXT DEFAULT NULL,
    existing_faults TEXT DEFAULT NULL,
    remarks TEXT DEFAULT NULL,
    inspected_by INT DEFAULT NULL,
    inspection_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (device_id) REFERENCES mobile_devices(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 6. DOUBLE-ENTRY ACCOUNTING & FINANCIAL ACCOUNTS
CREATE TABLE account_groups (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL,
    group_type ENUM('asset','liability','equity','revenue','cogs','expense') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE accounts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    group_id INT NOT NULL,
    branch_id INT DEFAULT NULL, -- NULL = global / centralized account
    code VARCHAR(20) NOT NULL UNIQUE,
    name VARCHAR(100) NOT NULL,
    description VARCHAR(255) DEFAULT NULL,
    is_system TINYINT(1) DEFAULT 0,
    is_cash_bank TINYINT(1) DEFAULT 0,
    current_balance DECIMAL(14,2) DEFAULT 0.00,
    status ENUM('active','inactive') DEFAULT 'active',
    FOREIGN KEY (group_id) REFERENCES account_groups(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE financial_accounts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    branch_id INT NOT NULL,
    account_id INT NOT NULL, -- linked ledger account in `accounts`
    name VARCHAR(100) NOT NULL,
    type ENUM('cash','bank','mobile_wallet') DEFAULT 'cash',
    account_number VARCHAR(100) DEFAULT NULL,
    bank_name VARCHAR(100) DEFAULT NULL,
    current_balance DECIMAL(14,2) DEFAULT 0.00,
    is_default TINYINT(1) DEFAULT 0,
    status ENUM('active','inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE CASCADE,
    FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE journal_entries (
    id INT AUTO_INCREMENT PRIMARY KEY,
    branch_id INT NOT NULL,
    entry_no VARCHAR(50) NOT NULL UNIQUE,
    entry_date DATE NOT NULL,
    reference_type VARCHAR(50) DEFAULT NULL, -- sale, purchase, expense, return, transfer, manual
    reference_id INT DEFAULT NULL,
    narration TEXT DEFAULT NULL,
    total_debit DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    total_credit DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    created_by INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE RESTRICT,
    INDEX idx_ref (reference_type, reference_id),
    INDEX idx_date (entry_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE journal_entry_lines (
    id INT AUTO_INCREMENT PRIMARY KEY,
    entry_id INT NOT NULL,
    account_id INT NOT NULL,
    debit DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    credit DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    description VARCHAR(255) DEFAULT NULL,
    FOREIGN KEY (entry_id) REFERENCES journal_entries(id) ON DELETE CASCADE,
    FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE RESTRICT,
    INDEX idx_acct_line (account_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE account_transfers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    transfer_no VARCHAR(50) NOT NULL UNIQUE,
    from_account_id INT NOT NULL,
    to_account_id INT NOT NULL,
    amount DECIMAL(14,2) NOT NULL,
    transfer_date DATE NOT NULL,
    reference_no VARCHAR(50) DEFAULT NULL,
    notes TEXT DEFAULT NULL,
    created_by INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (from_account_id) REFERENCES financial_accounts(id) ON DELETE RESTRICT,
    FOREIGN KEY (to_account_id) REFERENCES financial_accounts(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 7. PURCHASES
CREATE TABLE purchases (
    id INT AUTO_INCREMENT PRIMARY KEY,
    purchase_no VARCHAR(50) NOT NULL UNIQUE,
    branch_id INT NOT NULL,
    supplier_id INT DEFAULT NULL,
    seller_name VARCHAR(100) DEFAULT NULL,
    seller_phone VARCHAR(30) DEFAULT NULL,
    seller_cnic VARCHAR(30) DEFAULT NULL,
    purchase_type ENUM('new_mobile','used_mobile','accessory') NOT NULL,
    purchase_date DATE NOT NULL,
    subtotal DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    discount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    tax_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    additional_cost DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    grand_total DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    paid_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    payment_status ENUM('paid','partial','unpaid') DEFAULT 'paid',
    notes TEXT DEFAULT NULL,
    created_by INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE RESTRICT,
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE purchase_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    purchase_id INT NOT NULL,
    product_id INT DEFAULT NULL, -- for accessories
    device_id INT DEFAULT NULL,  -- for mobile phones
    item_name VARCHAR(200) NOT NULL,
    quantity INT NOT NULL DEFAULT 1,
    unit_price DECIMAL(12,2) NOT NULL,
    additional_cost DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    total_cost DECIMAL(12,2) NOT NULL,
    FOREIGN KEY (purchase_id) REFERENCES purchases(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE purchase_payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    purchase_id INT NOT NULL,
    branch_id INT NOT NULL,
    account_id INT NOT NULL,
    payment_method VARCHAR(50) DEFAULT 'Cash',
    amount DECIMAL(12,2) NOT NULL,
    payment_date DATE NOT NULL,
    notes VARCHAR(255) DEFAULT NULL,
    FOREIGN KEY (purchase_id) REFERENCES purchases(id) ON DELETE CASCADE,
    FOREIGN KEY (account_id) REFERENCES financial_accounts(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE purchase_returns (
    id INT AUTO_INCREMENT PRIMARY KEY,
    return_no VARCHAR(50) NOT NULL UNIQUE,
    purchase_id INT NOT NULL,
    branch_id INT NOT NULL,
    supplier_id INT DEFAULT NULL,
    return_date DATE NOT NULL,
    total_amount DECIMAL(12,2) NOT NULL,
    reason TEXT DEFAULT NULL,
    created_by INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (purchase_id) REFERENCES purchases(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE purchase_return_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    return_id INT NOT NULL,
    product_id INT DEFAULT NULL,
    device_id INT DEFAULT NULL,
    quantity INT NOT NULL DEFAULT 1,
    unit_price DECIMAL(12,2) NOT NULL,
    total_price DECIMAL(12,2) NOT NULL,
    FOREIGN KEY (return_id) REFERENCES purchase_returns(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 8. SALES & POS
CREATE TABLE sales (
    id INT AUTO_INCREMENT PRIMARY KEY,
    invoice_no VARCHAR(50) NOT NULL UNIQUE,
    branch_id INT NOT NULL,
    customer_id INT DEFAULT NULL,
    sale_date DATE NOT NULL,
    subtotal DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    line_discount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    invoice_discount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    tax_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    grand_total DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    trade_in_device_id INT DEFAULT NULL,
    trade_in_value DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    net_payable DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    paid_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    remaining_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    payment_status ENUM('paid','partial','unpaid') DEFAULT 'paid',
    payment_method VARCHAR(100) DEFAULT 'Cash',
    notes TEXT DEFAULT NULL,
    created_by INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE RESTRICT,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL,
    INDEX idx_inv (invoice_no),
    INDEX idx_sale_branch (branch_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE sale_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sale_id INT NOT NULL,
    item_type ENUM('mobile','accessory') NOT NULL,
    product_id INT DEFAULT NULL,
    device_id INT DEFAULT NULL,
    item_name VARCHAR(200) NOT NULL,
    imei VARCHAR(50) DEFAULT NULL,
    quantity INT NOT NULL DEFAULT 1,
    unit_cost DECIMAL(12,2) NOT NULL DEFAULT 0.00, -- actual inventory cost for exact profit
    unit_price DECIMAL(12,2) NOT NULL,
    discount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    total_price DECIMAL(12,2) NOT NULL,
    gross_profit DECIMAL(12,2) NOT NULL DEFAULT 0.00, -- total_price - (quantity * unit_cost)
    FOREIGN KEY (sale_id) REFERENCES sales(id) ON DELETE CASCADE,
    INDEX idx_sale_device (device_id),
    INDEX idx_sale_product (product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE sale_payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sale_id INT NOT NULL,
    branch_id INT NOT NULL,
    account_id INT NOT NULL,
    payment_method VARCHAR(50) DEFAULT 'Cash',
    amount DECIMAL(12,2) NOT NULL,
    payment_date DATE NOT NULL,
    notes VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sale_id) REFERENCES sales(id) ON DELETE CASCADE,
    FOREIGN KEY (account_id) REFERENCES financial_accounts(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE sales_returns (
    id INT AUTO_INCREMENT PRIMARY KEY,
    return_no VARCHAR(50) NOT NULL UNIQUE,
    sale_id INT NOT NULL,
    branch_id INT NOT NULL,
    customer_id INT DEFAULT NULL,
    return_date DATE NOT NULL,
    total_refund DECIMAL(12,2) NOT NULL,
    refund_method VARCHAR(50) DEFAULT 'Cash',
    reason TEXT DEFAULT NULL,
    created_by INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sale_id) REFERENCES sales(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE sales_return_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    return_id INT NOT NULL,
    sale_item_id INT NOT NULL,
    product_id INT DEFAULT NULL,
    device_id INT DEFAULT NULL,
    quantity INT NOT NULL DEFAULT 1,
    refund_price DECIMAL(12,2) NOT NULL,
    FOREIGN KEY (return_id) REFERENCES sales_returns(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 9. STOCK MOVEMENTS & TRANSFERS
CREATE TABLE stock_movements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    branch_id INT NOT NULL,
    product_id INT DEFAULT NULL,
    device_id INT DEFAULT NULL,
    movement_type ENUM('purchase','sale','transfer_out','transfer_in','return_in','return_out','adjustment','repair_use','trade_in') NOT NULL,
    quantity INT NOT NULL, -- positive for stock addition, negative for reduction
    unit_cost DECIMAL(12,2) DEFAULT 0.00,
    reference_type VARCHAR(50) NOT NULL, -- sale, purchase, transfer, repair, adjustment
    reference_id INT NOT NULL,
    notes VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE RESTRICT,
    INDEX idx_mov_prod (product_id),
    INDEX idx_mov_dev (device_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE stock_transfers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    transfer_no VARCHAR(50) NOT NULL UNIQUE,
    from_branch_id INT NOT NULL,
    to_branch_id INT NOT NULL,
    status ENUM('draft','pending','dispatched','received','cancelled') DEFAULT 'draft',
    dispatch_date DATE DEFAULT NULL,
    receive_date DATE DEFAULT NULL,
    total_items INT DEFAULT 0,
    notes TEXT DEFAULT NULL,
    created_by INT DEFAULT NULL,
    received_by INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (from_branch_id) REFERENCES branches(id) ON DELETE RESTRICT,
    FOREIGN KEY (to_branch_id) REFERENCES branches(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE stock_transfer_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    transfer_id INT NOT NULL,
    item_type ENUM('mobile','accessory') NOT NULL,
    product_id INT DEFAULT NULL,
    device_id INT DEFAULT NULL,
    item_name VARCHAR(200) NOT NULL,
    quantity INT NOT NULL DEFAULT 1,
    unit_cost DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    FOREIGN KEY (transfer_id) REFERENCES stock_transfers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE stock_adjustments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    adjustment_no VARCHAR(50) NOT NULL UNIQUE,
    branch_id INT NOT NULL,
    product_id INT DEFAULT NULL,
    device_id INT DEFAULT NULL,
    adjustment_type ENUM('add','deduct') NOT NULL,
    quantity INT NOT NULL,
    reason VARCHAR(255) NOT NULL,
    created_by INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 10. REPAIRING BILLING
CREATE TABLE repairing_bills (
    id INT AUTO_INCREMENT PRIMARY KEY,
    bill_no VARCHAR(50) NOT NULL UNIQUE,
    branch_id INT NOT NULL,
    customer_id INT DEFAULT NULL,
    customer_name VARCHAR(100) NOT NULL,
    customer_phone VARCHAR(30) NOT NULL,
    mobile_brand VARCHAR(100) NOT NULL,
    mobile_model VARCHAR(100) NOT NULL,
    imei VARCHAR(30) DEFAULT NULL,
    repair_date DATE NOT NULL,
    description TEXT DEFAULT NULL,
    labour_charges DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    parts_charges DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    other_charges DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    discount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    tax_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    total_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    paid_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    remaining_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    payment_method VARCHAR(50) DEFAULT 'Cash',
    status ENUM('completed','delivered') DEFAULT 'completed',
    remarks TEXT DEFAULT NULL,
    created_by INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE RESTRICT,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE repairing_bill_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    bill_id INT NOT NULL,
    item_type ENUM('labour','spare_part','other') NOT NULL,
    product_id INT DEFAULT NULL, -- spare part from product inventory
    description VARCHAR(200) NOT NULL,
    quantity INT NOT NULL DEFAULT 1,
    unit_price DECIMAL(12,2) NOT NULL,
    total_price DECIMAL(12,2) NOT NULL,
    FOREIGN KEY (bill_id) REFERENCES repairing_bills(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 11. EXPENSES
CREATE TABLE expense_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    description VARCHAR(255) DEFAULT NULL,
    status ENUM('active','inactive') DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE expenses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    expense_no VARCHAR(50) NOT NULL UNIQUE,
    branch_id INT NOT NULL,
    category_id INT NOT NULL,
    account_id INT NOT NULL, -- paid from financial_accounts
    amount DECIMAL(12,2) NOT NULL,
    expense_date DATE NOT NULL,
    reference_no VARCHAR(50) DEFAULT NULL,
    description TEXT DEFAULT NULL,
    receipt_attachment VARCHAR(255) DEFAULT NULL,
    created_by INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE RESTRICT,
    FOREIGN KEY (category_id) REFERENCES expense_categories(id) ON DELETE RESTRICT,
    FOREIGN KEY (account_id) REFERENCES financial_accounts(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 12. DAILY CLOSING
CREATE TABLE daily_closings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    branch_id INT NOT NULL,
    closing_date DATE NOT NULL,
    opening_cash DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    cash_sales DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    cash_purchases DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    customer_payments DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    supplier_payments DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    cash_expenses DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    other_cash_receipts DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    cash_withdrawals DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    expected_closing_cash DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    actual_closing_cash DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    difference DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    notes TEXT DEFAULT NULL,
    closed_by INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE RESTRICT,
    UNIQUE KEY uq_branch_close (branch_id, closing_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 13. WARRANTIES & CLAIMS
CREATE TABLE warranties (
    id INT AUTO_INCREMENT PRIMARY KEY,
    device_id INT NOT NULL,
    sale_id INT NOT NULL,
    customer_id INT DEFAULT NULL,
    duration_months INT NOT NULL DEFAULT 12,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    terms TEXT DEFAULT NULL,
    status ENUM('active','expired','void') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (device_id) REFERENCES mobile_devices(id) ON DELETE RESTRICT,
    FOREIGN KEY (sale_id) REFERENCES sales(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE warranty_claims (
    id INT AUTO_INCREMENT PRIMARY KEY,
    claim_no VARCHAR(50) NOT NULL UNIQUE,
    warranty_id INT NOT NULL,
    claim_date DATE NOT NULL,
    issue_description TEXT NOT NULL,
    action_taken TEXT DEFAULT NULL,
    status ENUM('pending','in_process','resolved','rejected') DEFAULT 'pending',
    resolved_date DATE DEFAULT NULL,
    created_by INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (warranty_id) REFERENCES warranties(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 14. APPLICATION SETTINGS & AUDIT LOGS
CREATE TABLE application_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT DEFAULT NULL,
    setting_group VARCHAR(50) DEFAULT 'general',
    description VARCHAR(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE audit_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT DEFAULT NULL,
    branch_id INT DEFAULT NULL,
    action VARCHAR(100) NOT NULL,
    entity_type VARCHAR(50) NOT NULL,
    entity_id INT DEFAULT NULL,
    details TEXT DEFAULT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_audit_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- SEED DATA
-- Account Groups
INSERT INTO account_groups (id, name, group_type) VALUES
(1, 'Current Assets', 'asset'),
(2, 'Fixed Assets', 'asset'),
(3, 'Current Liabilities', 'liability'),
(4, 'Long Term Liabilities', 'liability'),
(5, 'Owner Equity', 'equity'),
(6, 'Operating Revenue', 'revenue'),
(7, 'Cost of Goods Sold', 'cogs'),
(8, 'Operating Expenses', 'expense');

-- Default Chart of Accounts
INSERT INTO accounts (id, group_id, branch_id, code, name, description, is_system, is_cash_bank, current_balance, status) VALUES
(1, 1, NULL, '1001', 'Cash in Hand', 'General Cash Account', 1, 1, 0.00, 'active'),
(2, 1, NULL, '1002', 'Bank Main Account', 'Primary Bank Ledger Account', 1, 1, 0.00, 'active'),
(3, 1, NULL, '1003', 'Customer Receivables', 'Accounts Receivable (Debtors)', 1, 0, 0.00, 'active'),
(4, 1, NULL, '1004', 'Merchandise Inventory', 'Total stock on hand at cost', 1, 0, 0.00, 'active'),
(5, 1, NULL, '1005', 'Stock in Transit', 'Stock moving between branches', 1, 0, 0.00, 'active'),
(6, 3, NULL, '2001', 'Supplier Payables', 'Accounts Payable (Creditors)', 1, 0, 0.00, 'active'),
(7, 3, NULL, '2002', 'Sales Tax Payable', 'Tax collected on sales', 1, 0, 0.00, 'active'),
(8, 5, NULL, '3001', 'Owner Capital', 'Owner equity contribution', 1, 0, 0.00, 'active'),
(9, 5, NULL, '3002', 'Retained Earnings', 'Accumulated profit or loss', 1, 0, 0.00, 'active'),
(10, 6, NULL, '4001', 'Mobile Sales Revenue', 'Revenue from new and used phone sales', 1, 0, 0.00, 'active'),
(11, 6, NULL, '4002', 'Accessories Sales Revenue', 'Revenue from mobile accessories', 1, 0, 0.00, 'active'),
(12, 6, NULL, '4003', 'Repairing Services Revenue', 'Revenue from phone repairs and labour', 1, 0, 0.00, 'active'),
(13, 7, NULL, '5001', 'Cost of Goods Sold - Mobile', 'Acquisition cost of mobile phones sold', 1, 0, 0.00, 'active'),
(14, 7, NULL, '5002', 'Cost of Goods Sold - Accessories', 'Inventory cost of accessories sold', 1, 0, 0.00, 'active'),
(15, 7, NULL, '5003', 'Cost of Repair Spare Parts', 'Cost of parts used in repairs', 1, 0, 0.00, 'active'),
(16, 8, NULL, '6001', 'Shop Rent Expense', 'Rent for branch locations', 1, 0, 0.00, 'active'),
(17, 8, NULL, '6002', 'Electricity & Utilities', 'Utility bills', 1, 0, 0.00, 'active'),
(18, 8, NULL, '6003', 'Staff Salaries', 'Employee payroll', 1, 0, 0.00, 'active'),
(19, 8, NULL, '6004', 'General & Administrative', 'Miscellaneous branch expenses', 1, 0, 0.00, 'active');

-- Business Seed
INSERT INTO businesses (id, name, tagline, email, phone, address, currency, currency_symbol, tax_enabled, tax_rate, tax_name) VALUES
(1, 'CellPro Mobile World', 'Complete Mobile Phones & Accessories ERP', 'info@cellpro.local', '+92 300 1234567', 'Hall Road Electronic Market, Lahore', 'PKR', 'Rs.', 0, 0.00, 'Sales Tax');

-- Branches Seed
INSERT INTO branches (id, business_id, name, code, address, phone, manager_name, opening_date, invoice_prefix, is_active) VALUES
(1, 1, 'Main Branch (Hall Road)', 'BR-MAIN', 'Shop #12-14, Hafeez Plaza, Hall Road, Lahore', '+92 300 1112233', 'Ahmed Khan', '2024-01-01', 'INV-M', 1),
(2, 1, 'Gulberg Branch (Liberty)', 'BR-GLB', 'Shop #4, Liberty Market, Gulberg III, Lahore', '+92 300 4445566', 'Bilal Tariq', '2024-06-01', 'INV-G', 1);

-- Financial Accounts for Branches
INSERT INTO financial_accounts (id, branch_id, account_id, name, type, account_number, bank_name, current_balance, is_default, status) VALUES
(1, 1, 1, 'Cash Till - Main Branch', 'cash', 'CASH-MAIN-01', NULL, 150000.00, 1, 'active'),
(2, 1, 2, 'Meezan Bank - Main Account', 'bank', 'PK12MEZN00012345678901', 'Meezan Bank', 500000.00, 0, 'active'),
(3, 1, 1, 'JazzCash Shop Account', 'mobile_wallet', '03001234567', 'JazzCash', 25000.00, 0, 'active'),
(4, 2, 1, 'Cash Till - Gulberg Branch', 'cash', 'CASH-GLB-01', NULL, 80000.00, 1, 'active'),
(5, 2, 2, 'Meezan Bank - Gulberg Sub-Account', 'bank', 'PK12MEZN00098765432101', 'Meezan Bank', 150000.00, 0, 'active');

-- Roles Seed
INSERT INTO roles (id, name, description) VALUES
(1, 'Super Administrator', 'Full access to all branches, accounting, settings and reports'),
(2, 'Branch Manager', 'Manages branch operations, stock, sales, and branch reports'),
(3, 'Salesperson', 'Access to POS, customer records, and product availability'),
(4, 'Accountant', 'Full financial and double-entry accounting operations');

-- Users Seed (password is 'admin123' hashed with password_hash)
INSERT INTO users (id, role_id, branch_id, name, username, email, password, phone, status) VALUES
(1, 1, NULL, 'System Administrator', 'admin', 'admin@cellpro.local', '$2y$10$YNx4yLZKfVxbeSW7k4/ZOei8gWiKEmXF2USyXAICCy/IAvHlhazpW', '+92 300 0000000', 'active'),
(2, 2, 1, 'Ahmed Khan (Manager)', 'manager_main', 'ahmed@cellpro.local', '$2y$10$YNx4yLZKfVxbeSW7k4/ZOei8gWiKEmXF2USyXAICCy/IAvHlhazpW', '+92 300 1112233', 'active'),
(3, 3, 1, 'Usman POS (Salesperson)', 'sales_main', 'usman@cellpro.local', '$2y$10$YNx4yLZKfVxbeSW7k4/ZOei8gWiKEmXF2USyXAICCy/IAvHlhazpW', '+92 300 7778899', 'active');

-- Categories Seed
INSERT INTO categories (id, name, type, description) VALUES
(1, 'Smartphones', 'mobile', 'Brand new and used mobile devices'),
(2, 'Chargers & Adapters', 'accessory', 'Fast chargers, PD adapters, wireless pads'),
(3, 'Data Cables', 'accessory', 'Type-C, Lightning, Micro-USB cables'),
(4, 'Airpods & Earphones', 'accessory', 'Bluetooth earbuds, handsfree'),
(5, 'Cases & Covers', 'accessory', 'Protective silicone and leather covers'),
(6, 'Screen Protectors', 'accessory', '9D and UV tempered glass'),
(7, 'Mobile LCDs & Screens', 'spare_part', 'Replacement screens for repair service'),
(8, 'Batteries & Charging ICs', 'spare_part', 'Replacement batteries and parts');

-- Brands Seed
INSERT INTO brands (id, name) VALUES
(1, 'Apple'),
(2, 'Samsung'),
(3, 'Xiaomi / Redmi'),
(4, 'Infinix'),
(5, 'Tecno'),
(6, 'Vivo'),
(7, 'OnePlus'),
(8, 'Anker'),
(9, 'Joyroom');

-- Models Seed
INSERT INTO models (id, brand_id, name) VALUES
(1, 1, 'iPhone 15 Pro Max'),
(2, 1, 'iPhone 14 Pro'),
(3, 1, 'iPhone 13'),
(4, 2, 'Galaxy S24 Ultra'),
(5, 2, 'Galaxy A54 5G'),
(6, 3, 'Redmi Note 13 Pro'),
(7, 4, 'Infinix Note 30'),
(8, 8, 'PowerPort 20W PD Charger'),
(9, 9, 'T03S Pro ANC Earbuds');

-- Units Seed
INSERT INTO units (id, name, short_name) VALUES
(1, 'Piece', 'Pcs'),
(2, 'Box', 'Box'),
(3, 'Pack', 'Pk');

-- Expense Categories Seed
INSERT INTO expense_categories (id, name, description) VALUES
(1, 'Shop Rent', 'Monthly retail shop rental'),
(2, 'Electricity Bill', 'WAPDA electricity charges'),
(3, 'Staff Tea & Refreshments', 'Daily tea, water, snacks for shop & customers'),
(4, 'Internet & Telephone', 'Broadband and mobile packages'),
(5, 'Shop Maintenance & Cleaning', 'Store fixtures, cleaning items and repairs'),
(6, 'Transportation & Courier', 'Stock delivery and parcel courier charges');

-- Customers Seed
INSERT INTO customers (id, name, phone, alt_phone, email, cnic, address, opening_balance, current_balance, credit_limit) VALUES
(1, 'Walk-in Customer', '03000000000', NULL, NULL, NULL, 'Counter Sale', 0.00, 0.00, 0.00),
(2, 'Ali Raza', '03214567890', '03331122334', 'aliraza@gmail.com', '35201-1234567-1', 'DHA Phase 5, Lahore', 0.00, 15000.00, 100000.00),
(3, 'Hamza Malik', '03459876543', NULL, 'hamza.m@outlook.com', '35202-7654321-3', 'Model Town C Block, Lahore', 0.00, 0.00, 50000.00);

-- Suppliers Seed
INSERT INTO suppliers (id, name, contact_person, phone, address, opening_balance, current_balance) VALUES
(1, 'Al-Rehman Mobile Distributors', 'Tariq Rehman', '03009988776', 'Hall Road Wholesale Market, Lahore', 0.00, 45000.00),
(2, 'Universal Accessories Hub', 'Sohail Butt', '03223344556', 'Hafeez Centre Basement, Gulberg, Lahore', 0.00, 20000.00);

-- Mobile Devices Seed (Sample phones in inventory for testing)
INSERT INTO mobile_devices (id, branch_id, supplier_id, brand_id, model_id, model_name, ram, storage, color, imei_1, imei_2, serial_number, device_condition, battery_health, pta_status, network_status, warranty_months, purchase_price, additional_cost, actual_cost, selling_price, device_status, purchase_date, description) VALUES
(1, 1, 1, 1, 1, 'iPhone 15 Pro Max', '8GB', '256GB', 'Natural Titanium', '354892019283741', '354892019283742', 'F2LXYZ1234', 'new', 100, 'approved', 'Factory Unlocked', 12, 360000.00, 2000.00, 362000.00, 395000.00, 'available', '2024-10-15', 'Brand new box pack PTA approved device with 1 year Apple official warranty.'),
(2, 1, 1, 2, 2, 'iPhone 14 Pro', '6GB', '128GB', 'Deep Purple', '358910293847561', '358910293847562', 'DX12389101', 'used', 89, 'approved', 'Factory Unlocked', 1, 210000.00, 1500.00, 211500.00, 235000.00, 'available', '2024-10-20', 'Mint condition 9.5/10 used phone, Face ID working, pristine body.'),
(3, 1, 1, 4, 4, 'Galaxy S24 Ultra', '12GB', '512GB', 'Titanium Black', '351239847120938', '351239847120939', 'R58N123456', 'new', 100, 'approved', 'Factory Unlocked', 12, 380000.00, 2500.00, 382500.00, 420000.00, 'available', '2024-10-22', 'Samsung Pakistan official warranty with S-Pen included.'),
(4, 2, 1, 1, 3, 'iPhone 13', '4GB', '128GB', 'Midnight Blue', '356789012345671', '356789012345672', 'C02GXYZ999', 'used', 86, 'approved', 'Factory Unlocked', 1, 135000.00, 1000.00, 136000.00, 155000.00, 'available', '2024-10-25', 'Clean device with original box and cable.');

-- Device Inspection for the used iPhone 14 Pro & iPhone 13
INSERT INTO device_inspections (device_id, screen_condition, body_condition, battery_health, camera_condition, biometrics, charging_port, speaker_mic, pta_status, accessories_received, existing_faults, remarks, inspected_by) VALUES
(2, 'No scratches (Screen protector applied)', 'Minor dot on bezel, 9.5/10', 89, 'Working Perfectly, clear lenses', 'Face ID 100% Functional', 'Working & tested', 'Loud and clear', 'Official PTA Approved', 'Original Box + Braided Cable', 'None', 'Customer traded in for iPhone 15 upgrade.', 1),
(4, 'Minor hairline scratch', '9/10 clean edges', 86, 'Clean 100%', 'Face ID Working', 'Working', 'Tested good', 'Official PTA Approved', 'Device only', 'Minor wear on bottom frame', 'Direct customer purchase.', 2);

-- Accessory Products Seed
INSERT INTO products (id, branch_id, category_id, brand_id, name, sku, barcode, unit_id, product_type, cost_price, retail_price, wholesale_price, quantity, min_stock_level, description) VALUES
(1, 1, 2, 8, 'Anker 20W USB-C Nano Pro Charger', 'ANK-20W-PD', '848061058291', 1, 'accessory', 2800.00, 3800.00, 3400.00, 24, 5, 'High speed compact fast charger for iPhone and Samsung.'),
(2, 1, 3, 8, 'Anker PowerLine III USB-C to Lightning Cable 3ft', 'ANK-C2L-3FT', '848061058307', 1, 'accessory', 1800.00, 2600.00, 2300.00, 35, 8, 'Apple MFi certified durable lightning cable.'),
(3, 1, 4, 9, 'Joyroom T03S Pro Wireless ANC Earbuds', 'JR-T03S-PRO', '695611678901', 1, 'accessory', 4200.00, 5800.00, 5200.00, 15, 3, 'Active Noise Cancellation Bluetooth Earbuds with wireless charging case.'),
(4, 1, 5, 1, 'iPhone 15 Pro Max Silicon Case MagSafe', 'CS-IP15PM-BLK', '789123456789', 1, 'accessory', 600.00, 1500.00, 1200.00, 40, 10, 'Premium velvet interior magnetic protective cover.'),
(5, 1, 7, 1, 'iPhone 13 OLED Screen Replacement Panel', 'SP-IP13-LCD', '998877665544', 1, 'spare_part', 22000.00, 30000.00, 28000.00, 4, 1, 'High quality OEM replacement display assembly.'),
(6, 2, 2, 8, 'Anker 20W USB-C Nano Pro Charger', 'ANK-20W-PD-G', '848061058291', 1, 'accessory', 2800.00, 3800.00, 3400.00, 12, 5, 'Gulberg stock of Anker 20W charger.');

-- Initial Stock Movements for seeded devices and products
INSERT INTO stock_movements (branch_id, product_id, device_id, movement_type, quantity, unit_cost, reference_type, reference_id, notes) VALUES
(1, NULL, 1, 'purchase', 1, 362000.00, 'opening', 1, 'Initial opening inventory'),
(1, NULL, 2, 'purchase', 1, 211500.00, 'opening', 2, 'Initial opening inventory'),
(1, NULL, 3, 'purchase', 1, 382500.00, 'opening', 3, 'Initial opening inventory'),
(2, NULL, 4, 'purchase', 1, 136000.00, 'opening', 4, 'Initial opening inventory'),
(1, 1, NULL, 'purchase', 24, 2800.00, 'opening', 1, 'Initial opening accessory stock'),
(1, 2, NULL, 'purchase', 35, 1800.00, 'opening', 2, 'Initial opening accessory stock'),
(1, 3, NULL, 'purchase', 15, 4200.00, 'opening', 3, 'Initial opening accessory stock'),
(1, 4, NULL, 'purchase', 40, 600.00, 'opening', 4, 'Initial opening accessory stock'),
(1, 5, NULL, 'purchase', 4, 22000.00, 'opening', 5, 'Initial opening spare part stock'),
(2, 6, NULL, 'purchase', 12, 2800.00, 'opening', 6, 'Initial opening accessory stock');

-- Application Settings
INSERT INTO application_settings (setting_key, setting_value, setting_group, description) VALUES
('business_name', 'CellPro Mobile World', 'general', 'Company / Store Legal Name'),
('tax_enabled', '0', 'tax', 'Toggle tax calculation on POS and Invoices (1=Yes, 0=No)'),
('tax_name', 'Sales Tax', 'tax', 'Default tax title on receipts'),
('tax_rate', '0.00', 'tax', 'Default tax percentage if enabled'),
('thermal_printer_width', '80mm', 'pos', 'Thermal receipt paper width (80mm or 58mm)'),
('allow_negative_stock', '0', 'inventory', 'Allow selling accessories with zero balance (0=Strict)'),
('invoice_footer_note', 'Thank you for your business! Warranty claims require original receipt and IMEI verification. Physical/Water damage voids warranty.', 'pos', 'Receipt bottom policy note');
