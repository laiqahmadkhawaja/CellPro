<?php
// purchases/index.php - Purchase Invoices History
$page_title = 'Purchase Invoices';
require_once __DIR__ . '/../includes/header.php';

$branch_id = getActiveBranchId();
$suppliers = $pdo->query("SELECT id, name FROM suppliers ORDER BY name ASC")->fetchAll();

$supp_filter = !empty($_GET['supplier']) ? (int)$_GET['supplier'] : 0;
$type_filter = $_GET['type'] ?? 'all';
$search = trim($_GET['search'] ?? '');

$sql = "SELECT p.*, s.name as supplier_name, b.name as branch_name, u.name as created_by_name 
        FROM purchases p
        LEFT JOIN suppliers s ON p.supplier_id = s.id
        LEFT JOIN branches b ON p.branch_id = b.id
        LEFT JOIN users u ON p.created_by = u.id
        WHERE 1=1";
$params = [];

if ($branch_id) {
    $sql .= " AND p.branch_id = ?";
    $params[] = $branch_id;
}
if ($supp_filter > 0) {
    $sql .= " AND p.supplier_id = ?";
    $params[] = $supp_filter;
}
if ($type_filter !== 'all') {
    $sql .= " AND p.purchase_type = ?";
    $params[] = $type_filter;
}
if (!empty($search)) {
    $sql .= " AND (p.purchase_no LIKE ? OR p.seller_name LIKE ? OR s.name LIKE ?)";
    $t = "%{$search}%";
    $params = array_merge($params, [$t, $t, $t]);
}

$sql .= " ORDER BY p.id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$purchases = $stmt->fetchAll();
?>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h5 class="fw-bold m-0"><i class="bi bi-receipt text-primary me-2"></i>Purchase Invoices</h5>
        <small class="text-muted">Procurement records, supplier bills and used phone purchases</small>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= BASE_URL ?>purchases/new_mobile.php" class="btn btn-primary btn-sm shadow-sm">
            <i class="bi bi-phone me-1"></i> New Mobiles
        </a>
        <a href="<?= BASE_URL ?>purchases/used_mobile.php" class="btn btn-info text-white btn-sm shadow-sm">
            <i class="bi bi-arrow-repeat me-1"></i> Buy Used Mobile
        </a>
        <a href="<?= BASE_URL ?>purchases/accessories.php" class="btn btn-outline-primary btn-sm shadow-sm">
            <i class="bi bi-bag-plus me-1"></i> Accessories
        </a>
    </div>
</div>

<!-- Filters -->
<div class="card mb-3">
    <div class="card-body p-3">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-12 col-md-4">
                <label class="form-label small text-muted">Search Purchase # or Seller</label>
                <input type="text" name="search" class="form-control form-control-sm" placeholder="Invoice #, customer name..." value="<?= htmlspecialchars($search) ?>">
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label small text-muted">Purchase Type</label>
                <select name="type" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="all">All Types</option>
                    <option value="new_mobile" <?= $type_filter === 'new_mobile' ? 'selected' : '' ?>>New Mobiles</option>
                    <option value="used_mobile" <?= $type_filter === 'used_mobile' ? 'selected' : '' ?>>Used Mobiles</option>
                    <option value="accessory" <?= $type_filter === 'accessory' ? 'selected' : '' ?>>Accessories</option>
                </select>
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label small text-muted">Supplier</label>
                <select name="supplier" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="0">All Suppliers</option>
                    <?php foreach ($suppliers as $s): ?>
                        <option value="<?= $s['id'] ?>" <?= $s['id'] == $supp_filter ? 'selected' : '' ?>>
                            <?= htmlspecialchars($s['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-md-2 d-flex gap-1">
                <button type="submit" class="btn btn-sm btn-primary w-100">Filter</button>
                <a href="<?= BASE_URL ?>purchases/index.php" class="btn btn-sm btn-outline-secondary">Reset</a>
            </div>
        </form>
    </div>
</div>

<!-- Table -->
<div class="card">
    <div class="card-body p-0">
        <?php if (empty($purchases)): ?>
            <div class="text-center py-5 text-muted">
                <i class="bi bi-inbox fs-1 mb-2 d-block opacity-50"></i>
                No purchases recorded matching filters.
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Invoice #</th>
                            <th>Date</th>
                            <th>Type</th>
                            <th>Supplier / Seller</th>
                            <th>Grand Total</th>
                            <th>Paid Amount</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($purchases as $p): ?>
                            <tr>
                                <td class="fw-bold text-primary"><?= htmlspecialchars($p['purchase_no']) ?></td>
                                <td><?= formatDate($p['purchase_date']) ?></td>
                                <td>
                                    <?php if ($p['purchase_type'] === 'new_mobile'): ?>
                                        <span class="badge bg-primary">New Mobile</span>
                                    <?php elseif ($p['purchase_type'] === 'used_mobile'): ?>
                                        <span class="badge bg-info">Used Mobile</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Accessory</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="fw-semibold text-dark"><?= htmlspecialchars($p['supplier_name'] ?? $p['seller_name'] ?? 'Direct Seller') ?></div>
                                    <small class="text-muted"><?= htmlspecialchars($p['seller_phone'] ?? '') ?></small>
                                </td>
                                <td class="fw-bold"><?= formatCurrency($p['grand_total']) ?></td>
                                <td class="text-success fw-semibold"><?= formatCurrency($p['paid_amount']) ?></td>
                                <td><?= getStatusBadge($p['payment_status']) ?></td>
                                <td class="text-end">
                                    <a href="<?= BASE_URL ?>purchases/view.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-eye"></i> View
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
