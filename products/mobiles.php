<?php
// products/mobiles.php - Mobile Phone Inventory & IMEI Tracking
$page_title = 'Mobile Phone Inventory';
require_once __DIR__ . '/../includes/header.php';

$branch_id = getActiveBranchId();
$status_filter = $_GET['status'] ?? 'all';
$condition_filter = $_GET['condition'] ?? 'all';
$search = trim($_GET['search'] ?? '');

$sql = "SELECT md.*, b.name as brand_name, br.name as branch_name, s.name as supplier_name,
               di.battery_health as insp_battery, di.screen_condition, di.body_condition
        FROM mobile_devices md
        LEFT JOIN brands b ON md.brand_id = b.id
        LEFT JOIN branches br ON md.branch_id = br.id
        LEFT JOIN suppliers s ON md.supplier_id = s.id
        LEFT JOIN device_inspections di ON md.id = di.device_id
        WHERE 1=1";
$params = [];

if ($branch_id) {
    $sql .= " AND md.branch_id = ?";
    $params[] = $branch_id;
}
if ($status_filter !== 'all') {
    $sql .= " AND md.device_status = ?";
    $params[] = $status_filter;
}
if ($condition_filter !== 'all') {
    $sql .= " AND md.device_condition = ?";
    $params[] = $condition_filter;
}
if (!empty($search)) {
    $sql .= " AND (md.imei_1 LIKE ? OR md.imei_2 LIKE ? OR md.serial_number LIKE ? OR md.model_name LIKE ? OR b.name LIKE ?)";
    $term = "%{$search}%";
    $params = array_merge($params, [$term, $term, $term, $term, $term]);
}

$sql .= " ORDER BY md.id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$devices = $stmt->fetchAll();
?>

<!-- Header Actions -->
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h5 class="fw-bold m-0"><i class="bi bi-phone text-primary me-2"></i>Mobile Devices Inventory</h5>
        <small class="text-muted">IMEI-based individual tracking for new and used phones</small>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= BASE_URL ?>purchases/new_mobile.php" class="btn btn-primary btn-sm shadow-sm">
            <i class="bi bi-plus-circle me-1"></i> Purchase New Phones
        </a>
        <a href="<?= BASE_URL ?>purchases/used_mobile.php" class="btn btn-info text-white btn-sm shadow-sm">
            <i class="bi bi-arrow-repeat me-1"></i> Buy / Inspect Used Phone
        </a>
    </div>
</div>

<!-- Filters Bar -->
<div class="card mb-3">
    <div class="card-body p-3">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-12 col-md-4">
                <label class="form-label small text-muted">Search IMEI, Model or Brand</label>
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                    <input type="text" name="search" class="form-control" placeholder="Scan or type IMEI..." value="<?= htmlspecialchars($search) ?>">
                </div>
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label small text-muted">Device Status</label>
                <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="all" <?= $status_filter === 'all' ? 'selected' : '' ?>>All Statuses</option>
                    <option value="available" <?= $status_filter === 'available' ? 'selected' : '' ?>>Available (In Stock)</option>
                    <option value="sold" <?= $status_filter === 'sold' ? 'selected' : '' ?>>Sold</option>
                    <option value="reserved" <?= $status_filter === 'reserved' ? 'selected' : '' ?>>Reserved</option>
                    <option value="in_repair" <?= $status_filter === 'in_repair' ? 'selected' : '' ?>>In Repair</option>
                    <option value="transferred" <?= $status_filter === 'transferred' ? 'selected' : '' ?>>Transferred</option>
                </select>
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label small text-muted">Condition</label>
                <select name="condition" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="all" <?= $condition_filter === 'all' ? 'selected' : '' ?>>All Conditions</option>
                    <option value="new" <?= $condition_filter === 'new' ? 'selected' : '' ?>>Brand New</option>
                    <option value="used" <?= $condition_filter === 'used' ? 'selected' : '' ?>>Used</option>
                </select>
            </div>
            <div class="col-12 col-md-2 d-flex gap-1">
                <button type="submit" class="btn btn-sm btn-primary w-100">Filter</button>
                <a href="<?= BASE_URL ?>products/mobiles.php" class="btn btn-sm btn-outline-secondary">Reset</a>
            </div>
        </form>
    </div>
</div>

<!-- Devices Table -->
<div class="card">
    <div class="card-body p-0">
        <?php if (empty($devices)): ?>
            <div class="text-center py-5 text-muted">
                <i class="bi bi-phone-vibrate fs-1 mb-2 d-block opacity-50"></i>
                No mobile phones found matching the selected criteria.
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Brand / Model</th>
                            <th>IMEI Numbers</th>
                            <th>Condition & Specs</th>
                            <th>PTA & Battery</th>
                            <th>Cost & Selling Price</th>
                            <th>Branch</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($devices as $d): ?>
                            <tr>
                                <td>
                                    <div class="fw-bold text-dark"><?= htmlspecialchars($d['model_name']) ?></div>
                                    <small class="text-muted"><?= htmlspecialchars($d['brand_name'] ?? 'Other') ?> &bull; <?= htmlspecialchars($d['color'] ?? '') ?></small>
                                </td>
                                <td>
                                    <div class="font-monospace fw-semibold" style="font-size:0.85rem;">IMEI 1: <?= htmlspecialchars($d['imei_1']) ?></div>
                                    <?php if ($d['imei_2']): ?>
                                        <div class="font-monospace text-muted" style="font-size:0.75rem;">IMEI 2: <?= htmlspecialchars($d['imei_2']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div><?= getStatusBadge($d['device_condition']) ?></div>
                                    <small class="text-muted"><?= $d['ram'] ? $d['ram'] . ' / ' : '' ?><?= $d['storage'] ?></small>
                                </td>
                                <td>
                                    <div><?= getStatusBadge($d['pta_status']) ?></div>
                                    <small class="text-muted"><i class="bi bi-battery-charging me-1"></i><?= $d['battery_health'] ?>% Health</small>
                                </td>
                                <td>
                                    <div class="fw-bold text-primary"><?= formatCurrency($d['selling_price']) ?></div>
                                    <small class="text-muted" title="Actual inventory cost">Cost: <?= formatCurrency($d['actual_cost']) ?></small>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark border"><?= htmlspecialchars($d['branch_name']) ?></span>
                                </td>
                                <td>
                                    <?= getStatusBadge($d['device_status']) ?>
                                </td>
                                <td class="text-end">
                                    <a href="<?= BASE_URL ?>products/mobile_view.php?id=<?= $d['id'] ?>" class="btn btn-sm btn-outline-primary" title="View Inspection & History">
                                        <i class="bi bi-eye"></i> Details
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
