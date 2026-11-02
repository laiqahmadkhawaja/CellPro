<?php
// index.php - Main ERP Dashboard
$page_title = 'Executive Dashboard';
require_once __DIR__ . '/includes/header.php';

$branch_id = getActiveBranchId();
$branch_sql = $branch_id ? " AND branch_id = $branch_id" : "";

// Today's Sales
$today = date('Y-m-d');
$stmt = $pdo->prepare("SELECT COALESCE(SUM(grand_total), 0) as total_sales, COUNT(id) as count_sales 
                       FROM sales WHERE sale_date = ?" . ($branch_id ? " AND branch_id = $branch_id" : ""));
$stmt->execute([$today]);
$today_sales = $stmt->fetch();

// Monthly Sales
$month_start = date('Y-m-01');
$stmt = $pdo->prepare("SELECT COALESCE(SUM(grand_total), 0) as month_sales 
                       FROM sales WHERE sale_date >= ?" . ($branch_id ? " AND branch_id = $branch_id" : ""));
$stmt->execute([$month_start]);
$month_sales = $stmt->fetchColumn();

// Phone Inventory Counts & Value
$stmt = $pdo->query("SELECT COUNT(id) as total_phones, COALESCE(SUM(actual_cost), 0) as total_cost, COALESCE(SUM(selling_price), 0) as total_retail
                     FROM mobile_devices WHERE device_status = 'available'" . ($branch_id ? " AND branch_id = $branch_id" : ""));
$phone_stock = $stmt->fetch();

// Accessories Stock Counts & Low Stock
$stmt = $pdo->query("SELECT COALESCE(SUM(quantity), 0) as total_qty, COALESCE(SUM(quantity * cost_price), 0) as total_cost,
                            SUM(CASE WHEN quantity <= min_stock_level THEN 1 ELSE 0 END) as low_stock_count
                     FROM products WHERE is_active = 1" . ($branch_id ? " AND branch_id = $branch_id" : ""));
$acc_stock = $stmt->fetch();

// Customer Receivables (Debtors)
$total_receivables = $pdo->query("SELECT COALESCE(SUM(current_balance), 0) FROM customers WHERE current_balance > 0")->fetchColumn();

// Supplier Payables (Creditors)
$total_payables = $pdo->query("SELECT COALESCE(SUM(current_balance), 0) FROM suppliers WHERE current_balance > 0")->fetchColumn();

// Cash & Bank Balances for Active Branch
$stmt = $pdo->query("SELECT COALESCE(SUM(CASE WHEN type = 'cash' THEN current_balance ELSE 0 END), 0) as cash_balance,
                            COALESCE(SUM(CASE WHEN type IN ('bank','mobile_wallet') THEN current_balance ELSE 0 END), 0) as bank_balance
                     FROM financial_accounts WHERE status = 'active'" . ($branch_id ? " AND branch_id = $branch_id" : ""));
$balances = $stmt->fetch();

// Recent Sales (Last 5)
$stmt = $pdo->query("SELECT s.*, c.name as customer_name, b.name as branch_name 
                     FROM sales s
                     LEFT JOIN customers c ON s.customer_id = c.id
                     LEFT JOIN branches b ON s.branch_id = b.id
                     " . ($branch_id ? "WHERE s.branch_id = $branch_id" : "") . "
                     ORDER BY s.id DESC LIMIT 5");
$recent_sales = $stmt->fetchAll();

// Low Stock Alert Products
$stmt = $pdo->query("SELECT p.*, b.name as brand_name, br.name as branch_name 
                     FROM products p
                     LEFT JOIN brands b ON p.brand_id = b.id
                     LEFT JOIN branches br ON p.branch_id = br.id
                     WHERE p.quantity <= p.min_stock_level AND p.is_active = 1
                     " . ($branch_id ? "AND p.branch_id = $branch_id" : "") . "
                     ORDER BY p.quantity ASC LIMIT 5");
$low_stock_items = $stmt->fetchAll();
?>

<!-- Quick Action Shortcuts Bar -->
<div class="row g-2 mb-4">
    <div class="col-6 col-md-3 col-lg-2">
        <a href="<?= BASE_URL ?>pos/index.php" class="btn btn-primary w-100 py-2 d-flex flex-column align-items-center justify-content-center shadow-sm">
            <i class="bi bi-cart4 fs-4 mb-1"></i>
            <span class="small fw-semibold">Open POS</span>
        </a>
    </div>
    <div class="col-6 col-md-3 col-lg-2">
        <a href="<?= BASE_URL ?>purchases/new_mobile.php" class="btn btn-outline-primary w-100 py-2 d-flex flex-column align-items-center justify-content-center bg-white shadow-sm">
            <i class="bi bi-phone fs-4 mb-1 text-primary"></i>
            <span class="small fw-semibold">New Phone Purchase</span>
        </a>
    </div>
    <div class="col-6 col-md-3 col-lg-2">
        <a href="<?= BASE_URL ?>purchases/used_mobile.php" class="btn btn-outline-info w-100 py-2 d-flex flex-column align-items-center justify-content-center bg-white shadow-sm">
            <i class="bi bi-arrow-repeat fs-4 mb-1 text-info"></i>
            <span class="small fw-semibold">Buy Used Phone</span>
        </a>
    </div>
    <div class="col-6 col-md-3 col-lg-2">
        <a href="<?= BASE_URL ?>repairs/create.php" class="btn btn-outline-warning w-100 py-2 d-flex flex-column align-items-center justify-content-center bg-white shadow-sm">
            <i class="bi bi-tools fs-4 mb-1 text-warning"></i>
            <span class="small fw-semibold">Repair Bill</span>
        </a>
    </div>
    <div class="col-6 col-md-3 col-lg-2">
        <a href="<?= BASE_URL ?>expenses/create.php" class="btn btn-outline-danger w-100 py-2 d-flex flex-column align-items-center justify-content-center bg-white shadow-sm">
            <i class="bi bi-wallet2 fs-4 mb-1 text-danger"></i>
            <span class="small fw-semibold">Add Expense</span>
        </a>
    </div>
    <div class="col-6 col-md-3 col-lg-2">
        <a href="<?= BASE_URL ?>closing/create.php" class="btn btn-outline-success w-100 py-2 d-flex flex-column align-items-center justify-content-center bg-white shadow-sm">
            <i class="bi bi-check2-circle fs-4 mb-1 text-success"></i>
            <span class="small fw-semibold">Daily Closing</span>
        </a>
    </div>
</div>

<!-- Primary Stats Grid -->
<div class="row g-3 mb-4">
    <!-- Today's Sales -->
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-icon bg-primary bg-opacity-10 text-primary">
                <i class="bi bi-cash-stack"></i>
            </div>
            <div>
                <small class="text-muted text-uppercase fw-semibold" style="font-size:0.75rem;">Today's Sales</small>
                <h4 class="fw-bold m-0 text-dark"><?= formatCurrency($today_sales['total_sales']) ?></h4>
                <small class="text-muted"><?= $today_sales['count_sales'] ?> transactions today</small>
            </div>
        </div>
    </div>

    <!-- Phone Inventory -->
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-icon bg-info bg-opacity-10 text-info">
                <i class="bi bi-phone"></i>
            </div>
            <div>
                <small class="text-muted text-uppercase fw-semibold" style="font-size:0.75rem;">Mobiles In Stock</small>
                <h4 class="fw-bold m-0 text-dark"><?= (int)$phone_stock['total_phones'] ?> Devices</h4>
                <small class="text-muted">Cost: <?= formatCurrency($phone_stock['total_cost']) ?></small>
            </div>
        </div>
    </div>

    <!-- Accessories Stock -->
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-icon bg-success bg-opacity-10 text-success">
                <i class="bi bi-headphones"></i>
            </div>
            <div>
                <small class="text-muted text-uppercase fw-semibold" style="font-size:0.75rem;">Accessories Stock</small>
                <h4 class="fw-bold m-0 text-dark"><?= (int)$acc_stock['total_qty'] ?> Units</h4>
                <small class="text-muted">Valuation: <?= formatCurrency($acc_stock['total_cost']) ?></small>
            </div>
        </div>
    </div>

    <!-- Cash in Hand Till -->
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-icon bg-warning bg-opacity-10 text-warning">
                <i class="bi bi-safe"></i>
            </div>
            <div>
                <small class="text-muted text-uppercase fw-semibold" style="font-size:0.75rem;">Branch Cash Till</small>
                <h4 class="fw-bold m-0 text-dark"><?= formatCurrency($balances['cash_balance']) ?></h4>
                <small class="text-muted">Bank/Wallets: <?= formatCurrency($balances['bank_balance']) ?></small>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <!-- Receivables & Payables Ledger Cards -->
    <div class="col-12 col-md-6">
        <div class="card h-100">
            <div class="card-header">
                <span class="fw-bold"><i class="bi bi-journal-bookmark me-2 text-primary"></i>Receivables & Payables Overview</span>
            </div>
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center p-3 mb-2 bg-light rounded-3">
                    <div>
                        <div class="fw-bold text-dark">Customer Receivables</div>
                        <small class="text-muted">Total outstanding customer balance</small>
                    </div>
                    <div class="text-end">
                        <h5 class="fw-bold text-danger m-0"><?= formatCurrency($total_receivables) ?></h5>
                        <a href="<?= BASE_URL ?>customers/index.php" class="small text-decoration-none">View Ledgers &rarr;</a>
                    </div>
                </div>

                <div class="d-flex justify-content-between align-items-center p-3 bg-light rounded-3">
                    <div>
                        <div class="fw-bold text-dark">Supplier Payables</div>
                        <small class="text-muted">Total amount we owe to suppliers</small>
                    </div>
                    <div class="text-end">
                        <h5 class="fw-bold text-primary m-0"><?= formatCurrency($total_payables) ?></h5>
                        <a href="<?= BASE_URL ?>suppliers/index.php" class="small text-decoration-none">View Payables &rarr;</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Low Stock Alert Panel -->
    <div class="col-12 col-md-6">
        <div class="card h-100">
            <div class="card-header">
                <span class="fw-bold text-danger"><i class="bi bi-exclamation-triangle-fill me-2"></i>Low Stock Alert (<?= (int)$acc_stock['low_stock_count'] ?> items)</span>
                <a href="<?= BASE_URL ?>products/accessories.php" class="btn btn-sm btn-outline-secondary">All Accessories</a>
            </div>
            <div class="card-body p-0">
                <?php if (empty($low_stock_items)): ?>
                    <div class="p-4 text-center text-muted">All accessories stock is healthy!</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th>SKU / Barcode</th>
                                    <th class="text-center">Current Qty</th>
                                    <th class="text-center">Min Level</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($low_stock_items as $item): ?>
                                    <tr>
                                        <td>
                                            <div class="fw-semibold text-dark"><?= htmlspecialchars($item['name']) ?></div>
                                            <small class="text-muted"><?= htmlspecialchars($item['brand_name'] ?? 'Generic') ?></small>
                                        </td>
                                        <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($item['barcode'] ?? $item['sku']) ?></span></td>
                                        <td class="text-center"><span class="badge bg-danger"><?= $item['quantity'] ?></span></td>
                                        <td class="text-center text-muted"><?= $item['min_stock_level'] ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Recent Invoices Table -->
<div class="card">
    <div class="card-header">
        <span class="fw-bold"><i class="bi bi-clock-history me-2 text-primary"></i>Recent Sales Invoices</span>
        <a href="<?= BASE_URL ?>sales/index.php" class="btn btn-sm btn-outline-primary">View All Sales</a>
    </div>
    <div class="card-body p-0">
        <?php if (empty($recent_sales)): ?>
            <div class="p-4 text-center text-muted">No sales recorded yet. Click <strong>Open POS</strong> to create your first sale!</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Invoice #</th>
                            <th>Date</th>
                            <th>Customer</th>
                            <th>Total</th>
                            <th>Paid</th>
                            <th>Status</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_sales as $sale): ?>
                            <tr>
                                <td class="fw-bold text-primary"><?= htmlspecialchars($sale['invoice_no']) ?></td>
                                <td><?= formatDate($sale['sale_date']) ?></td>
                                <td><?= htmlspecialchars($sale['customer_name'] ?? 'Walk-in Customer') ?></td>
                                <td class="fw-semibold"><?= formatCurrency($sale['grand_total']) ?></td>
                                <td><?= formatCurrency($sale['paid_amount']) ?></td>
                                <td><?= getStatusBadge($sale['payment_status']) ?></td>
                                <td class="text-end">
                                    <a href="<?= BASE_URL ?>pos/invoice.php?id=<?= $sale['id'] ?>" class="btn btn-sm btn-outline-secondary" target="_blank" title="Print Invoice">
                                        <i class="bi bi-printer"></i>
                                    </a>
                                    <a href="<?= BASE_URL ?>sales/view.php?id=<?= $sale['id'] ?>" class="btn btn-sm btn-outline-primary" title="Details">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
