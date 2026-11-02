# CellPro ERP - Multi-Branch Mobile Shop Management System

A complete, modern Retail ERP and Point of Sale (POS) system designed specifically for mobile phone and electronics retail businesses. Built in **Core PHP (procedural non-MVC)**, **MySQL**, **Bootstrap 5**, and **AJAX**.

---

## 🚀 Key Features

1. **Individual Mobile Phone Tracking (IMEI 1 & 2)**
   - Every physical phone has its own inventory record with unique IMEI 1, optional unique IMEI 2, serial number, RAM/storage, color, PTA status, and battery health.
   - Separate workflows for **Brand New** box-packed phones and **Used Phone purchasing** from customers.
   - Comprehensive **Used Phone Physical Inspection Checklist** (screen, body, camera, biometrics, charging port, speaker/mic, faults, accessories).

2. **Mobile Exchange / Trade-In at POS (PRD Section 9)**
   - Customers can trade in old phones when buying new or used phones.
   - Traded phone is automatically inspected, added to used phone inventory with its IMEI, and the agreed trade-in value is deducted from the invoice total.

3. **Accessories & Spare Parts Inventory**
   - Quantity and barcode-based inventory with minimum stock alerts.
   - Built-in SVG barcode label generator and printer for thermal label sheets.
   - Stock adjustments for damaged, expired, or count discrepancies.

4. **Speedy, Touch-Friendly Point of Sale (POS)**
   - Keyboard/USB scanner support (auto-detects barcode/IMEI on scan).
   - Instant search across product name, model, brand, SKU, and IMEI.
   - Line-item and invoice discounts, optional sales tax, split payments (Cash, Bank, Mobile Wallets), and credit sales.
   - Dual print formats: **Thermal 80mm** receipt and **A4 Modern Invoice**.

5. **Complete Double-Entry Accounting**
   - Chart of Accounts (Assets, Liabilities, Equity, Revenue, COGS, Expenses).
   - Automatic balanced Journal Entries for every sale, purchase, expense, return, and fund transfer.
   - Balanced **Trial Balance**, **General Ledger**, **Profit & Loss Statement**, and **Balance Sheet**.

6. **Multi-Branch Operations & Stock Transfers**
   - Multiple branches under one centralized database.
   - Branch-specific inventory, cash tills, users, and sales.
   - Inter-branch stock transfers tracking **Stock in Transit** without creating artificial revenue or profit.

7. **Customer & Supplier Ledgers**
   - Transaction-based ledgers with running balance.
   - Direct payment receipts from customers and payments to suppliers with journal voucher integration.

8. **Simple Mobile Repair Billing**
   - Fast billing for labour services and replacement spare parts.
   - Automatically reduces spare parts inventory.

9. **Daily Branch Cash Closing**
   - Reconciliation of physical cash till drawer vs expected system cash flows (Opening Cash + Cash Sales + Receipts - Expenses - Purchases - Deposits).

10. **Zero-Modal Compliance (PRD Rule 6)**
    - Never uses popup modals.
    - Uses slide-over side-panels, dedicated pages, and inline expandable forms instead.

---

## 🛠️ Technology Stack

- **Backend:** Core PHP 8.2 (Procedural architecture, reusable helpers, clean output buffering)
- **Database:** MySQL (InnoDB, Foreign Keys, Indexes on IMEIs, Barcodes & Invoices)
- **Frontend:** Bootstrap 5, Bootstrap Icons, jQuery, Chart.js
- **Assets:** Fully bundled locally in `assets/vendor/` for 100% offline localhost reliability.

---

## 📦 Default Login Credentials

- **URL:** `http://localhost/CellPro/`
- **Username:** `admin`
- **Password:** `admin123`
- **Role:** Super Administrator (Access to all branches and settings)

Additional seeded accounts:
- Branch Manager: `manager_main` / `admin123`
- Salesperson: `sales_main` / `admin123`

---

## 🗄️ Database Setup (Localhost / XAMPP)

1. Ensure Apache and MySQL are running in XAMPP.
2. The database `cellpro_db` has been created and initialized.
3. If reinstalling from scratch:
   ```bash
   mysql -u root -e "CREATE DATABASE IF NOT EXISTS cellpro_db CHARACTER SET utf8mb4;"
   mysql -u root cellpro_db < database/schema.sql
   ```
4. Access `http://localhost/CellPro/` in any browser.

---

## 🌐 Online Deployment Guide

1. Export the database or import `database/schema.sql` into your live MySQL server.
2. Update database credentials in `config/database.php`.
3. Point your domain or virtual host to the application directory. CellPro automatically detects `BASE_URL` dynamically without hardcoded URLs.
