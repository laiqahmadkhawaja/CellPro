<?php
// suppliers/index.php - Suppliers Directory & Payables
$page_title = 'Suppliers & Ledgers';
require_once __DIR__ . '/../includes/header.php';

$search = trim($_GET['search'] ?? '');

$sql = "SELECT s.*, 
               (SELECT COUNT(id) FROM purchases WHERE supplier_id = s.id) as total_purchases
        FROM suppliers s
        WHERE 1=1";
$params = [];

if (!empty($search)) {
    $sql .= " AND (s.name LIKE ? OR s.phone LIKE ? OR s.contact_person LIKE ?)";
    $t = "%{$search}%";
    $params = [$t, $t, $t];
}
$sql .= " ORDER BY s.name ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$suppliers = $stmt->fetchAll();
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h5 class="fw-bold m-0"><i class="bi bi-truck text-primary me-2"></i>Suppliers & Payables</h5>
        <small class="text-muted">Wholesalers, distributors, payables balances and purchase statements</small>
    </div>
    <button type="button" class="btn btn-primary btn-sm shadow-sm" data-toggle-panel="panel-new-supplier">
        <i class="bi bi-plus-circle me-1"></i> Add New Supplier
    </button>
</div>

<!-- Search -->
<div class="card mb-3">
    <div class="card-body p-3">
        <form method="GET" class="row g-2">
            <div class="col-12 col-md-10">
                <input type="text" name="search" class="form-control form-control-sm" placeholder="Search by supplier company, contact person, or phone..." value="<?= htmlspecialchars($search) ?>">
            </div>
            <div class="col-12 col-md-2">
                <button type="submit" class="btn btn-sm btn-primary w-100">Search</button>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body p-0">
        <?php if (empty($suppliers)): ?>
            <div class="text-center py-5 text-muted">
                <i class="bi bi-truck fs-1 mb-2 d-block opacity-50"></i>
                No suppliers found. Click <strong>Add New Supplier</strong> to register a vendor.
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Supplier Name</th>
                            <th>Contact Person</th>
                            <th>Phone / Alternate</th>
                            <th>Address</th>
                            <th class="text-center">Purchases</th>
                            <th>Outstanding Payable</th>
                            <th class="text-end">Ledger & Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($suppliers as $s): ?>
                            <tr>
                                <td>
                                    <div class="fw-bold text-dark"><?= htmlspecialchars($s['name']) ?></div>
                                    <small class="text-muted"><?= htmlspecialchars($s['email'] ?: '') ?></small>
                                </td>
                                <td><?= htmlspecialchars($s['contact_person'] ?: '-') ?></td>
                                <td>
                                    <div class="fw-semibold"><?= htmlspecialchars($s['phone']) ?></div>
                                    <?php if ($s['alt_phone']): ?>
                                        <small class="text-muted"><?= htmlspecialchars($s['alt_phone']) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td class="small text-muted"><?= htmlspecialchars($s['address'] ?: '-') ?></td>
                                <td class="text-center">
                                    <span class="badge bg-light text-dark border"><?= $s['total_purchases'] ?> bills</span>
                                </td>
                                <td>
                                    <?php if ($s['current_balance'] > 0): ?>
                                        <span class="fw-bold text-primary"><?= formatCurrency($s['current_balance']) ?></span>
                                        <small class="text-muted d-block" style="font-size:0.7rem;">We Owe Supplier</small>
                                    <?php elseif ($s['current_balance'] < 0): ?>
                                        <span class="fw-bold text-success"><?= formatCurrency(abs($s['current_balance'])) ?></span>
                                        <small class="text-success d-block" style="font-size:0.7rem;">Advance Paid</small>
                                    <?php else: ?>
                                        <span class="text-muted fw-semibold">Rs. 0.00</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <a href="<?= BASE_URL ?>suppliers/ledger.php?id=<?= $s['id'] ?>" class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-journal-text me-1"></i> Supplier Ledger
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

<!-- SIDE-PANEL DRAWER: CREATE SUPPLIER (Zero Modals Rule) -->
<div id="panel-new-supplier" class="side-panel">
    <div class="side-panel-header">
        <h6 class="m-0 fw-bold"><i class="bi bi-truck me-2 text-primary"></i>Register New Supplier</h6>
        <button type="button" class="btn-close" data-close-panel></button>
    </div>
    <div class="side-panel-body">
        <form method="POST" action="<?= BASE_URL ?>suppliers/create.php">
            <?= csrfField() ?>
            <div class="mb-3">
                <label class="form-label small fw-semibold">Supplier / Company Name *</label>
                <input type="text" name="name" class="form-control" placeholder="e.g. Al-Rehman Mobile Distributors" required>
            </div>
            <div class="mb-3">
                <label class="form-label small fw-semibold">Contact Person</label>
                <input type="text" name="contact_person" class="form-control" placeholder="Tariq Rehman">
            </div>
            <div class="row g-2 mb-3">
                <div class="col-6">
                    <label class="form-label small fw-semibold">Phone Number *</label>
                    <input type="tel" name="phone" class="form-control" placeholder="03001234567" required>
                </div>
                <div class="col-6">
                    <label class="form-label small fw-semibold">Alternate Phone</label>
                    <input type="tel" name="alt_phone" class="form-control" placeholder="Optional">
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label small fw-semibold">Email</label>
                <input type="email" name="email" class="form-control" placeholder="supplier@example.com">
            </div>
            <div class="mb-3">
                <label class="form-label small fw-semibold">Office / Shop Address</label>
                <input type="text" name="address" class="form-control" placeholder="Market, Hall Road, Lahore">
            </div>
            <div class="mb-3">
                <label class="form-label small fw-semibold">Opening Balance (Rs.)</label>
                <input type="number" step="0.01" name="opening_balance" class="form-control" value="0.00">
                <small class="text-muted" style="font-size:0.75rem;">Positive if you already owe supplier money.</small>
            </div>
            <div class="side-panel-footer px-0 pb-0">
                <button type="button" class="btn btn-light" data-close-panel>Cancel</button>
                <button type="submit" class="btn btn-primary">Save Supplier</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
