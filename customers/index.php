<?php
// customers/index.php - Customers Management & Balances
$page_title = 'Customers & Ledgers';
require_once __DIR__ . '/../includes/header.php';

$search = trim($_GET['search'] ?? '');

$sql = "SELECT c.*, 
               (SELECT COUNT(id) FROM sales WHERE customer_id = c.id) as total_invoices,
               (SELECT COALESCE(SUM(grand_total), 0) FROM sales WHERE customer_id = c.id) as total_spent
        FROM customers c
        WHERE 1=1";
$params = [];

if (!empty($search)) {
    $sql .= " AND (c.name LIKE ? OR c.phone LIKE ? OR c.cnic LIKE ?)";
    $t = "%{$search}%";
    $params = [$t, $t, $t];
}
$sql .= " ORDER BY c.name ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$customers = $stmt->fetchAll();
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h5 class="fw-bold m-0"><i class="bi bi-people text-primary me-2"></i>Customer Directory & Ledgers</h5>
        <small class="text-muted">Customer profiles, outstanding credit balances and running statements</small>
    </div>
    <button type="button" class="btn btn-primary btn-sm shadow-sm" data-toggle-panel="panel-new-customer">
        <i class="bi bi-person-plus me-1"></i> Add New Customer
    </button>
</div>

<!-- Search -->
<div class="card mb-3">
    <div class="card-body p-3">
        <form method="GET" class="row g-2">
            <div class="col-12 col-md-10">
                <input type="text" name="search" class="form-control form-control-sm" placeholder="Search by customer name, phone number, or CNIC..." value="<?= htmlspecialchars($search) ?>">
            </div>
            <div class="col-12 col-md-2">
                <button type="submit" class="btn btn-sm btn-primary w-100">Search</button>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body p-0">
        <?php if (empty($customers)): ?>
            <div class="text-center py-5 text-muted">
                <i class="bi bi-people fs-1 mb-2 d-block opacity-50"></i>
                No customers found.
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Customer Name</th>
                            <th>Mobile / Alternate</th>
                            <th>CNIC</th>
                            <th>City / Address</th>
                            <th class="text-center">Invoices</th>
                            <th>Outstanding Balance</th>
                            <th class="text-end">Ledger & Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($customers as $c): ?>
                            <tr>
                                <td>
                                    <div class="fw-bold text-dark"><?= htmlspecialchars($c['name']) ?></div>
                                    <?php if ($c['id'] == 1): ?>
                                        <span class="badge bg-secondary">System Walk-in</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="fw-semibold"><?= htmlspecialchars($c['phone']) ?></div>
                                    <?php if ($c['alt_phone']): ?>
                                        <small class="text-muted"><?= htmlspecialchars($c['alt_phone']) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td class="font-monospace small"><?= htmlspecialchars($c['cnic'] ?: '-') ?></td>
                                <td class="small text-muted"><?= htmlspecialchars($c['address'] ?: '-') ?></td>
                                <td class="text-center">
                                    <span class="badge bg-light text-dark border"><?= $c['total_invoices'] ?> sales</span>
                                </td>
                                <td>
                                    <?php if ($c['current_balance'] > 0): ?>
                                        <span class="fw-bold text-danger"><?= formatCurrency($c['current_balance']) ?></span>
                                        <small class="text-danger d-block" style="font-size:0.7rem;">Receivable Due</small>
                                    <?php elseif ($c['current_balance'] < 0): ?>
                                        <span class="fw-bold text-success"><?= formatCurrency(abs($c['current_balance'])) ?></span>
                                        <small class="text-success d-block" style="font-size:0.7rem;">Advance Credit</small>
                                    <?php else: ?>
                                        <span class="text-muted fw-semibold">Rs. 0.00</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <a href="<?= BASE_URL ?>customers/ledger.php?id=<?= $c['id'] ?>" class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-journal-text me-1"></i> Ledger
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

<!-- SIDE-PANEL DRAWER: CREATE CUSTOMER (Zero Modals Rule) -->
<div id="panel-new-customer" class="side-panel">
    <div class="side-panel-header">
        <h6 class="m-0 fw-bold"><i class="bi bi-person-plus me-2 text-primary"></i>Register New Customer</h6>
        <button type="button" class="btn-close" data-close-panel></button>
    </div>
    <div class="side-panel-body">
        <form method="POST" action="<?= BASE_URL ?>customers/create.php">
            <?= csrfField() ?>
            <div class="mb-3">
                <label class="form-label small fw-semibold">Customer Full Name *</label>
                <input type="text" name="name" class="form-control" placeholder="e.g. Imran Ali" required>
            </div>
            <div class="row g-2 mb-3">
                <div class="col-6">
                    <label class="form-label small fw-semibold">Mobile Number *</label>
                    <input type="tel" name="phone" class="form-control" placeholder="03001234567" required>
                </div>
                <div class="col-6">
                    <label class="form-label small fw-semibold">Alternate Phone</label>
                    <input type="tel" name="alt_phone" class="form-control" placeholder="Optional">
                </div>
            </div>
            <div class="row g-2 mb-3">
                <div class="col-6">
                    <label class="form-label small fw-semibold">CNIC / ID Card</label>
                    <input type="text" name="cnic" class="form-control" placeholder="35201-xxxxxxx-x">
                </div>
                <div class="col-6">
                    <label class="form-label small fw-semibold">Credit Limit (Rs.)</label>
                    <input type="number" name="credit_limit" class="form-control" value="50000">
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label small fw-semibold">Email Address</label>
                <input type="email" name="email" class="form-control" placeholder="customer@gmail.com">
            </div>
            <div class="mb-3">
                <label class="form-label small fw-semibold">Shop / Residential Address</label>
                <input type="text" name="address" class="form-control" placeholder="Street, Area, City">
            </div>
            <div class="mb-3">
                <label class="form-label small fw-semibold">Opening Balance (Rs.)</label>
                <input type="number" step="0.01" name="opening_balance" class="form-control" value="0.00">
                <small class="text-muted" style="font-size:0.75rem;">Positive if customer already owes money.</small>
            </div>
            <div class="side-panel-footer px-0 pb-0">
                <button type="button" class="btn btn-light" data-close-panel>Cancel</button>
                <button type="submit" class="btn btn-primary">Save Customer</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
