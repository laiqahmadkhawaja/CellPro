<?php
// expenses/index.php - Branch Expenses Management
$page_title = 'Shop Expenses';
require_once __DIR__ . '/../includes/header.php';

$branch_id = getActiveBranchId();
$categories = $pdo->query("SELECT * FROM expense_categories WHERE status = 'active' ORDER BY name ASC")->fetchAll();
$financial_accounts = getFinancialAccounts($pdo, $branch_id);

$cat_filter = !empty($_GET['category']) ? (int)$_GET['category'] : 0;
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

$sql = "SELECT e.*, ec.name as category_name, fa.name as account_name, b.name as branch_name, u.name as user_name
        FROM expenses e
        JOIN expense_categories ec ON e.category_id = ec.id
        JOIN financial_accounts fa ON e.account_id = fa.id
        JOIN branches b ON e.branch_id = b.id
        LEFT JOIN users u ON e.created_by = u.id
        WHERE 1=1";
$params = [];

if ($branch_id) {
    $sql .= " AND e.branch_id = ?";
    $params[] = $branch_id;
}
if ($cat_filter > 0) {
    $sql .= " AND e.category_id = ?";
    $params[] = $cat_filter;
}
if (!empty($date_from)) {
    $sql .= " AND e.expense_date >= ?";
    $params[] = $date_from;
}
if (!empty($date_to)) {
    $sql .= " AND e.expense_date <= ?";
    $params[] = $date_to;
}

$sql .= " ORDER BY e.id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$expenses = $stmt->fetchAll();

$total_exp = 0;
foreach ($expenses as $ex) $total_exp += (float)$ex['amount'];
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h5 class="fw-bold m-0"><i class="bi bi-wallet2 text-danger me-2"></i>Shop Expenses</h5>
        <small class="text-muted">Rent, utilities, staff refreshments, and operational costs</small>
    </div>
    <button type="button" class="btn btn-primary btn-sm shadow-sm" data-toggle-panel="panel-new-expense">
        <i class="bi bi-plus-circle me-1"></i> Add Expense
    </button>
</div>

<!-- Filters -->
<div class="card mb-3">
    <div class="card-body p-3">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-12 col-md-4">
                <label class="form-label small text-muted">Category</label>
                <select name="category" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="0">All Categories</option>
                    <?php foreach ($categories as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= $c['id'] == $cat_filter ? 'selected' : '' ?>><?= htmlspecialchars($c['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label small text-muted">Date From</label>
                <input type="date" name="date_from" class="form-control form-control-sm" value="<?= htmlspecialchars($date_from) ?>">
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label small text-muted">Date To</label>
                <input type="date" name="date_to" class="form-control form-control-sm" value="<?= htmlspecialchars($date_to) ?>">
            </div>
            <div class="col-12 col-md-2 d-flex gap-1">
                <button type="submit" class="btn btn-sm btn-primary w-100">Filter</button>
                <a href="<?= BASE_URL ?>expenses/index.php" class="btn btn-sm btn-outline-secondary">Reset</a>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header bg-light d-flex justify-content-between align-items-center">
        <span class="fw-bold">Expenses Log</span>
        <span class="badge bg-danger fs-6">Total: <?= formatCurrency($total_exp) ?></span>
    </div>
    <div class="card-body p-0">
        <?php if (empty($expenses)): ?>
            <div class="text-center py-5 text-muted">
                <i class="bi bi-wallet2 fs-1 mb-2 d-block opacity-50"></i>
                No expenses recorded for this selection.
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Expense #</th>
                            <th>Date</th>
                            <th>Category</th>
                            <th>Paid From Account</th>
                            <th>Description</th>
                            <th>Amount</th>
                            <th>Entered By</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($expenses as $e): ?>
                            <tr>
                                <td class="fw-bold text-primary"><?= htmlspecialchars($e['expense_no']) ?></td>
                                <td><?= formatDate($e['expense_date']) ?></td>
                                <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($e['category_name']) ?></span></td>
                                <td class="small fw-semibold"><?= htmlspecialchars($e['account_name']) ?></td>
                                <td class="small text-muted"><?= htmlspecialchars($e['description'] ?: '-') ?></td>
                                <td class="fw-bold text-danger"><?= formatCurrency($e['amount']) ?></td>
                                <td class="small text-muted"><?= htmlspecialchars($e['user_name'] ?? 'Staff') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- SIDE-PANEL DRAWER: CREATE EXPENSE (Zero Modals Rule) -->
<div id="panel-new-expense" class="side-panel">
    <div class="side-panel-header">
        <h6 class="m-0 fw-bold"><i class="bi bi-wallet2 me-2 text-danger"></i>Add Shop Expense</h6>
        <button type="button" class="btn-close" data-close-panel></button>
    </div>
    <div class="side-panel-body">
        <form method="POST" action="<?= BASE_URL ?>expenses/create.php" enctype="multipart/form-data">
            <?= csrfField() ?>
            <div class="mb-3">
                <label class="form-label small fw-semibold">Expense Category *</label>
                <select name="category_id" class="form-select" required>
                    <option value="">-- Choose Category --</option>
                    <?php foreach ($categories as $c): ?>
                        <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="mb-3">
                <label class="form-label small fw-semibold">Amount (Rs.) *</label>
                <input type="number" step="0.01" name="amount" class="form-control form-control-lg fw-bold text-danger" placeholder="0.00" required min="1">
            </div>

            <div class="mb-3">
                <label class="form-label small fw-semibold">Paid From Account *</label>
                <select name="account_id" class="form-select" required>
                    <?php foreach ($financial_accounts as $fa): ?>
                        <option value="<?= $fa['id'] ?>"><?= htmlspecialchars($fa['name']) ?> (Balance: <?= formatCurrency($fa['current_balance']) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="mb-3">
                <label class="form-label small fw-semibold">Expense Date</label>
                <input type="date" name="expense_date" class="form-control" value="<?= date('Y-m-d') ?>">
            </div>

            <div class="mb-3">
                <label class="form-label small fw-semibold">Description / Purpose</label>
                <textarea name="description" class="form-control form-control-sm" rows="2" placeholder="e.g. October electricity bill or tea refreshments" required></textarea>
            </div>

            <div class="side-panel-footer px-0 pb-0">
                <button type="button" class="btn btn-light" data-close-panel>Cancel</button>
                <button type="submit" class="btn btn-danger">Post Expense</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
