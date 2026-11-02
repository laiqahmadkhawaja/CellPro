<?php
// includes/sidebar.php
$current_page = $_SERVER['PHP_SELF'];
?>
<aside id="sidebar">
    <div class="sidebar-brand">
        <i class="bi bi-phone text-primary fs-4"></i>
        <span>CellPro <span class="badge bg-primary text-white" style="font-size:0.65rem;">ERP</span></span>
    </div>

    <ul class="sidebar-nav">
        <li class="nav-item">
            <a class="nav-link <?= strpos($current_page, 'index.php') !== false && strpos($current_page, 'pos/') === false ? 'active' : '' ?>" href="<?= BASE_URL ?>index.php">
                <i class="bi bi-speedometer2"></i> <span>Dashboard</span>
            </a>
        </li>

        <li class="nav-item">
            <a class="nav-link <?= strpos($current_page, 'pos/') !== false ? 'active' : '' ?>" href="<?= BASE_URL ?>pos/index.php">
                <i class="bi bi-cart4 text-success"></i> <span class="fw-bold">Point of Sale (POS)</span>
            </a>
        </li>

        <li class="nav-header">Products & Inventory</li>
        <li class="nav-item">
            <a class="nav-link <?= strpos($current_page, 'products/mobiles') !== false || strpos($current_page, 'products/mobile_') !== false ? 'active' : '' ?>" href="<?= BASE_URL ?>products/mobiles.php">
                <i class="bi bi-phone"></i> <span>Mobile Phones (IMEI)</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= strpos($current_page, 'products/accessories') !== false || strpos($current_page, 'products/accessory_') !== false ? 'active' : '' ?>" href="<?= BASE_URL ?>products/accessories.php">
                <i class="bi bi-headphones"></i> <span>Accessories Inventory</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= strpos($current_page, 'products/barcodes') !== false ? 'active' : '' ?>" href="<?= BASE_URL ?>products/barcodes.php">
                <i class="bi bi-upc-scan"></i> <span>Barcode Generator</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= strpos($current_page, 'products/adjustments') !== false ? 'active' : '' ?>" href="<?= BASE_URL ?>products/adjustments.php">
                <i class="bi bi-sliders"></i> <span>Stock Adjustments</span>
            </a>
        </li>

        <li class="nav-header">Purchasing</li>
        <li class="nav-item">
            <a class="nav-link <?= strpos($current_page, 'purchases/new_mobile') !== false ? 'active' : '' ?>" href="<?= BASE_URL ?>purchases/new_mobile.php">
                <i class="bi bi-box-seam"></i> <span>Purchase New Phones</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= strpos($current_page, 'purchases/used_mobile') !== false ? 'active' : '' ?>" href="<?= BASE_URL ?>purchases/used_mobile.php">
                <i class="bi bi-arrow-repeat"></i> <span>Buy Used Phones</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= strpos($current_page, 'purchases/accessories') !== false ? 'active' : '' ?>" href="<?= BASE_URL ?>purchases/accessories.php">
                <i class="bi bi-bag-plus"></i> <span>Purchase Accessories</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= strpos($current_page, 'purchases/index.php') !== false ? 'active' : '' ?>" href="<?= BASE_URL ?>purchases/index.php">
                <i class="bi bi-receipt"></i> <span>All Purchases</span>
            </a>
        </li>

        <li class="nav-header">Sales & Billing</li>
        <li class="nav-item">
            <a class="nav-link <?= strpos($current_page, 'sales/index.php') !== false ? 'active' : '' ?>" href="<?= BASE_URL ?>sales/index.php">
                <i class="bi bi-receipt-cutoff"></i> <span>Sales & Invoices</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= strpos($current_page, 'sales/return.php') !== false ? 'active' : '' ?>" href="<?= BASE_URL ?>sales/return.php">
                <i class="bi bi-arrow-counterclockwise"></i> <span>Sales Returns</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= strpos($current_page, 'repairs/') !== false ? 'active' : '' ?>" href="<?= BASE_URL ?>repairs/index.php">
                <i class="bi bi-tools text-warning"></i> <span>Mobile Repairing</span>
            </a>
        </li>

        <li class="nav-header">Multi-Branch</li>
        <li class="nav-item">
            <a class="nav-link <?= strpos($current_page, 'transfers/') !== false ? 'active' : '' ?>" href="<?= BASE_URL ?>transfers/index.php">
                <i class="bi bi-arrow-left-right text-info"></i> <span>Stock Transfers</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= strpos($current_page, 'branches/') !== false ? 'active' : '' ?>" href="<?= BASE_URL ?>branches/index.php">
                <i class="bi bi-buildings"></i> <span>Branch Management</span>
            </a>
        </li>

        <li class="nav-header">Parties & Ledgers</li>
        <li class="nav-item">
            <a class="nav-link <?= strpos($current_page, 'customers/') !== false ? 'active' : '' ?>" href="<?= BASE_URL ?>customers/index.php">
                <i class="bi bi-people"></i> <span>Customers & Ledgers</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= strpos($current_page, 'suppliers/') !== false ? 'active' : '' ?>" href="<?= BASE_URL ?>suppliers/index.php">
                <i class="bi bi-truck"></i> <span>Suppliers & Ledgers</span>
            </a>
        </li>

        <li class="nav-header">Finance & Accounts</li>
        <li class="nav-item">
            <a class="nav-link <?= strpos($current_page, 'expenses/') !== false ? 'active' : '' ?>" href="<?= BASE_URL ?>expenses/index.php">
                <i class="bi bi-wallet2 text-danger"></i> <span>Shop Expenses</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= strpos($current_page, 'cash_bank/') !== false ? 'active' : '' ?>" href="<?= BASE_URL ?>cash_bank/index.php">
                <i class="bi bi-bank"></i> <span>Cash & Bank Accounts</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= strpos($current_page, 'closing/') !== false ? 'active' : '' ?>" href="<?= BASE_URL ?>closing/index.php">
                <i class="bi bi-check2-circle text-success"></i> <span>Daily Closing</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= strpos($current_page, 'accounting/coa.php') !== false ? 'active' : '' ?>" href="<?= BASE_URL ?>accounting/coa.php">
                <i class="bi bi-diagram-3"></i> <span>Chart of Accounts</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= strpos($current_page, 'accounting/journal.php') !== false ? 'active' : '' ?>" href="<?= BASE_URL ?>accounting/journal.php">
                <i class="bi bi-journal-text"></i> <span>Journal Entries</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= strpos($current_page, 'accounting/trial_balance.php') !== false ? 'active' : '' ?>" href="<?= BASE_URL ?>accounting/trial_balance.php">
                <i class="bi bi-bar-chart"></i> <span>Trial Balance</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= strpos($current_page, 'accounting/profit_loss.php') !== false ? 'active' : '' ?>" href="<?= BASE_URL ?>accounting/profit_loss.php">
                <i class="bi bi-graph-up-arrow text-success"></i> <span>Profit & Loss</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= strpos($current_page, 'accounting/balance_sheet.php') !== false ? 'active' : '' ?>" href="<?= BASE_URL ?>accounting/balance_sheet.php">
                <i class="bi bi-pie-chart"></i> <span>Balance Sheet</span>
            </a>
        </li>

        <li class="nav-header">Analytics & Reports</li>
        <li class="nav-item">
            <a class="nav-link <?= strpos($current_page, 'reports/sales.php') !== false ? 'active' : '' ?>" href="<?= BASE_URL ?>reports/sales.php">
                <i class="bi bi-file-earmark-bar-graph"></i> <span>Item Profit & Sales</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= strpos($current_page, 'reports/inventory.php') !== false ? 'active' : '' ?>" href="<?= BASE_URL ?>reports/inventory.php">
                <i class="bi bi-boxes"></i> <span>Inventory Valuation</span>
            </a>
        </li>

        <li class="nav-header">System Settings</li>
        <li class="nav-item">
            <a class="nav-link <?= strpos($current_page, 'settings/index.php') !== false ? 'active' : '' ?>" href="<?= BASE_URL ?>settings/index.php">
                <i class="bi bi-gear"></i> <span>General & Tax Settings</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= strpos($current_page, 'settings/categories.php') !== false ? 'active' : '' ?>" href="<?= BASE_URL ?>settings/categories.php">
                <i class="bi bi-tags"></i> <span>Brands & Categories</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= strpos($current_page, 'settings/users.php') !== false ? 'active' : '' ?>" href="<?= BASE_URL ?>settings/users.php">
                <i class="bi bi-person-badge"></i> <span>Users & Roles</span>
            </a>
        </li>
    </ul>
</aside>
