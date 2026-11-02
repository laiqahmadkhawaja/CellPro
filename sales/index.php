<?php
// sales/index.php - Sales Invoices & Realized Profit History
$page_title = 'Sales Invoices & History';
require_once __DIR__ . '/../includes/header.php';

$branch_id = getActiveBranchId();
$customers = $pdo->query("SELECT id, name FROM customers ORDER BY name ASC")->fetchAll();

$cust_filter = !empty($_GET['customer']) ? (int)$_GET['customer'] : 0;
$status_filter = $_GET['status'] ?? 'all';
$search = trim($_GET['search'] ?? '');
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

$sql = "SELECT s.*, c.name as customer_name, c.phone as customer_phone, b.name as branch_name, u.name as cashier_name,
               (SELECT COALESCE(SUM(si.gross_profit), 0) FROM sale_items si WHERE si.sale_id = s.id) as total_profit
        FROM sales s
        LEFT JOIN customers c ON s.customer_id = c.id
        LEFT JOIN branches b ON s.branch_id = b.id
        LEFT JOIN users u ON s.created_by = u.id
        WHERE 1=1";
$params = [];

if ($branch_id) {
    $sql .= " AND s.branch_id = ?";
    $params[] = $branch_id;
}
if ($cust_filter > 0) {
    $sql .= " AND s.customer_id = ?";
    $params[] = $cust_filter;
}
if ($status_filter !== 'all') {
    $sql .= " AND s.payment_status = ?";
    $params[] = $status_filter;
}
if (!empty($date_from)) {
    $sql .= " AND s.sale_date >= ?";
    $params[] = $date_from;
}
if (!empty($date_to)) {
    $sql .= " AND s.sale_date <= ?";
    $params[] = $date_to;
}
if (!empty($search)) {
    $sql .= " AND (s.invoice_no LIKE ? OR c.name LIKE ? OR c.phone LIKE ? OR EXISTS (
        SELECT 1 FROM sale_items si WHERE si.sale_id = s.id AND (si.imei LIKE ? OR si.item_name LIKE ?)
    ))";
    $t = "%{$search}%";
    $params = array_merge($params, [$t, $t, $t, $t, $t]);
}

$sql .= " ORDER BY s.id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$sales = $stmt->fetchAll();
?>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h5 class="fw-bold m-0"><i class="bi bi-receipt-cutoff text-primary me-2"></i>Sales & Invoices</h5>
        <small class="text-muted">Transaction records, customer receivables and realized item gross profits</small>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= BASE_URL ?>sales/return.php" class="btn btn-outline-danger btn-sm shadow-sm">
            <i class="bi bi-arrow-counterclockwise me-1"></i> Sales Return
        </a>
        <a href="<?= BASE_URL ?>pos/index.php" class="btn btn-primary btn-sm shadow-sm">
            <i class="bi bi-cart4 me-1"></i> Open POS Terminal
        </a>
    </div>
</div>

<!-- Filters -->
<div class="card mb-3">
    <div class="card-body p-3">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-12 col-md-3">
                <label class="form-label small text-muted">Search Invoice, Customer or IMEI</label>
                <input type="text" name="search" class="form-control form-control-sm" placeholder="INV#, customer, IMEI..." value="<?= htmlspecialchars($search) ?>">
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small text-muted">Customer</label>
                <select name="customer" class="form-select form-select-sm">
                    <option value="0">All Customers</option>
                    <?php foreach ($customers as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= $c['id'] == $cust_filter ? 'selected' : '' ?>><?= htmlspecialchars($c['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small text-muted">Payment Status</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="all">All Statuses</option>
                    <option value="paid" <?= $status_filter === 'paid' ? 'selected' : '' ?>>Paid</option>
                    <option value="partial" <?= $status_filter === 'partial' ? 'selected' : '' ?>>Partial</option>
                    <option value="unpaid" <?= $status_filter === 'unpaid' ? 'selected' : '' ?>>Unpaid</option>
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small text-muted">Date From</label>
                <input type="date" name="date_from" class="form-control form-control-sm" value="<?= htmlspecialchars($date_from) ?>">
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small text-muted">Date To</label>
                <input type="date" name="date_to" class="form-control form-control-sm" value="<?= htmlspecialchars($date_to) ?>">
            </div>
            <div class="col-12 col-md-1 d-flex gap-1">
                <button type="submit" class="btn btn-sm btn-primary w-100">Filter</button>
            </div>
        </form>
    </div>
</div>

<!-- Sales Table -->
<div class="card">
    <div class="card-body p-0">
        <?php if (empty($sales)): ?>
            <div class="text-center py-5 text-muted">
                <i class="bi bi-receipt fs-1 mb-2 d-block opacity-50"></i>
                No sales invoices found matching your criteria.
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Invoice #</th>
                            <th>Date & Time</th>
                            <th>Customer</th>
                            <th>Net Total</th>
                            <th>Paid</th>
                            <th>Balance Due</th>
                            <th>Gross Profit</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($sales as $s): ?>
                            <tr>
                                <td class="fw-bold text-primary"><?= htmlspecialchars($s['invoice_no']) ?></td>
                                <td><?= formatDate($s['created_at'], true) ?></td>
                                <td>
                                    <div class="fw-semibold text-dark"><?= htmlspecialchars($s['customer_name'] ?? 'Walk-in Customer') ?></div>
                                    <small class="text-muted"><?= htmlspecialchars($s['customer_phone'] ?? '') ?></small>
                                </td>
                                <td class="fw-bold"><?= formatCurrency($s['net_payable']) ?></td>
                                <td class="text-success fw-semibold"><?= formatCurrency($s['paid_amount']) ?></td>
                                <td>
                                    <?php if ($s['remaining_amount'] > 0): ?>
                                        <span class="text-danger fw-bold"><?= formatCurrency($s['remaining_amount']) ?></span>
                                    <?php else: ?>
                                        <span class="text-muted">Rs. 0.00</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-success-subtle text-success border fw-bold"><?= formatCurrency($s['total_profit']) ?></span>
                                </td>
                                <td><?= getStatusBadge($s['payment_status']) ?></td>
                                <td class="text-end text-nowrap">
                                    <a href="<?= BASE_URL ?>pos/invoice.php?id=<?= $s['id'] ?>" class="btn btn-sm btn-outline-secondary" target="_blank" title="Print Invoice">
                                        <i class="bi bi-printer"></i>
                                    </a>
                                    <a href="<?= BASE_URL ?>sales/view.php?id=<?= $s['id'] ?>" class="btn btn-sm btn-outline-primary" title="Details">
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

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
