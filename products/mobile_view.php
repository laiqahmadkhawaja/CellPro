<?php
// products/mobile_view.php - Individual Device Profile & Inspection History
$page_title = 'Device Details & Inspection';
require_once __DIR__ . '/../includes/header.php';

$device_id = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare("SELECT md.*, b.name as brand_name, br.name as branch_name, s.name as supplier_name,
                              di.screen_condition, di.body_condition, di.camera_condition, di.biometrics,
                              di.charging_port, di.speaker_mic, di.accessories_received, di.existing_faults,
                              di.remarks as inspection_remarks, di.inspection_date, u.name as inspector_name
                       FROM mobile_devices md
                       LEFT JOIN brands b ON md.brand_id = b.id
                       LEFT JOIN branches br ON md.branch_id = br.id
                       LEFT JOIN suppliers s ON md.supplier_id = s.id
                       LEFT JOIN device_inspections di ON md.id = di.device_id
                       LEFT JOIN users u ON di.inspected_by = u.id
                       WHERE md.id = ?");
$stmt->execute([$device_id]);
$device = $stmt->fetch();

if (!$device) {
    flash('danger', 'Device not found.');
    redirect(BASE_URL . 'products/mobiles.php');
}

// Fetch stock movements for this device
$stmt = $pdo->prepare("SELECT sm.*, b.name as branch_name 
                       FROM stock_movements sm
                       LEFT JOIN branches b ON sm.branch_id = b.id
                       WHERE sm.device_id = ? 
                       ORDER BY sm.id ASC");
$stmt->execute([$device_id]);
$movements = $stmt->fetchAll();

// If sold, fetch sale invoice details
$sale_item = null;
if ($device['device_status'] === 'sold') {
    $stmt = $pdo->prepare("SELECT si.*, s.invoice_no, s.sale_date, c.name as customer_name 
                           FROM sale_items si
                           JOIN sales s ON si.sale_id = s.id
                           LEFT JOIN customers c ON s.customer_id = c.id
                           WHERE si.device_id = ? LIMIT 1");
    $stmt->execute([$device_id]);
    $sale_item = $stmt->fetch();
}
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h5 class="fw-bold m-0"><?= htmlspecialchars($device['model_name']) ?></h5>
        <span class="font-monospace text-muted small">IMEI 1: <?= htmlspecialchars($device['imei_1']) ?></span>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= BASE_URL ?>products/mobiles.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i> Back to Inventory
        </a>
        <?php if ($device['device_status'] === 'available'): ?>
            <a href="<?= BASE_URL ?>pos/index.php" class="btn btn-primary btn-sm">
                <i class="bi bi-cart4 me-1"></i> Sell Device in POS
            </a>
        <?php endif; ?>
    </div>
</div>

<div class="row g-3">
    <!-- LEFT: Specs & Financials -->
    <div class="col-12 col-md-5">
        <div class="card mb-3">
            <div class="card-header bg-light">
                <span class="fw-bold"><i class="bi bi-info-circle me-1 text-primary"></i> Device Specifications</span>
                <?= getStatusBadge($device['device_status']) ?>
            </div>
            <div class="card-body">
                <table class="table table-sm table-borderless mb-0">
                    <tr><td class="text-muted">Brand:</td><td class="fw-bold"><?= htmlspecialchars($device['brand_name'] ?? 'Generic') ?></td></tr>
                    <tr><td class="text-muted">Condition:</td><td><?= getStatusBadge($device['device_condition']) ?></td></tr>
                    <tr><td class="text-muted">IMEI 1:</td><td class="font-monospace fw-bold"><?= htmlspecialchars($device['imei_1']) ?></td></tr>
                    <?php if ($device['imei_2']): ?>
                        <tr><td class="text-muted">IMEI 2:</td><td class="font-monospace"><?= htmlspecialchars($device['imei_2']) ?></td></tr>
                    <?php endif; ?>
                    <tr><td class="text-muted">RAM / Storage:</td><td><?= htmlspecialchars($device['ram']) ?> / <?= htmlspecialchars($device['storage']) ?></td></tr>
                    <tr><td class="text-muted">Color:</td><td><?= htmlspecialchars($device['color']) ?></td></tr>
                    <tr><td class="text-muted">PTA Status:</td><td><?= getStatusBadge($device['pta_status']) ?></td></tr>
                    <tr><td class="text-muted">Battery Health:</td><td><span class="badge bg-success"><?= $device['battery_health'] ?>%</span></td></tr>
                    <tr><td class="text-muted">Current Branch:</td><td class="fw-semibold"><?= htmlspecialchars($device['branch_name']) ?></td></tr>
                    <tr><td class="text-muted">Supplier / Origin:</td><td><?= htmlspecialchars($device['supplier_name'] ?? 'Direct Customer Purchase') ?></td></tr>
                    <tr><td class="text-muted">Date Acquired:</td><td><?= formatDate($device['purchase_date']) ?></td></tr>
                </table>
            </div>
        </div>

        <div class="card">
            <div class="card-header bg-light">
                <span class="fw-bold"><i class="bi bi-cash-coin me-1 text-success"></i> Valuation & Financials</span>
            </div>
            <div class="card-body">
                <div class="d-flex justify-content-between py-1 border-bottom">
                    <span class="text-muted">Purchase Price:</span>
                    <span><?= formatCurrency($device['purchase_price']) ?></span>
                </div>
                <div class="d-flex justify-content-between py-1 border-bottom">
                    <span class="text-muted">Additional Acquisition Costs:</span>
                    <span><?= formatCurrency($device['additional_cost']) ?></span>
                </div>
                <div class="d-flex justify-content-between py-1 border-bottom bg-light px-2 rounded">
                    <span class="fw-bold">Actual Inventory Cost:</span>
                    <span class="fw-bold"><?= formatCurrency($device['actual_cost']) ?></span>
                </div>
                <div class="d-flex justify-content-between py-1 pt-2">
                    <span class="text-muted">Retail Selling Price:</span>
                    <span class="fw-bold text-primary"><?= formatCurrency($device['selling_price']) ?></span>
                </div>
                <div class="d-flex justify-content-between py-1">
                    <span class="text-muted">Projected Gross Profit:</span>
                    <span class="fw-bold text-success"><?= formatCurrency($device['selling_price'] - $device['actual_cost']) ?></span>
                </div>

                <?php if ($sale_item): ?>
                    <div class="alert alert-info py-2 px-3 mt-3 mb-0 small">
                        <strong><i class="bi bi-check-circle me-1"></i> Sold On:</strong> <?= formatDate($sale_item['sale_date']) ?><br>
                        <strong>Invoice:</strong> <a href="<?= BASE_URL ?>pos/invoice.php?id=<?= $sale_item['sale_id'] ?>" target="_blank"><?= htmlspecialchars($sale_item['invoice_no']) ?></a><br>
                        <strong>Customer:</strong> <?= htmlspecialchars($sale_item['customer_name'] ?? 'Walk-in') ?><br>
                        <strong>Sale Price:</strong> <?= formatCurrency($sale_item['total_price']) ?><br>
                        <strong>Actual Realized Profit:</strong> <span class="fw-bold text-success"><?= formatCurrency($sale_item['gross_profit']) ?></span>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- RIGHT: Inspection Checklist & Stock Lifecycle -->
    <div class="col-12 col-md-7">
        <!-- Inspection Checklist -->
        <div class="card mb-3">
            <div class="card-header bg-light">
                <span class="fw-bold"><i class="bi bi-shield-check me-1 text-info"></i> Device Physical Inspection Report</span>
                <?php if ($device['inspection_date']): ?>
                    <small class="text-muted">Inspected on <?= formatDate($device['inspection_date'], true) ?> by <?= htmlspecialchars($device['inspector_name'] ?? 'Admin') ?></small>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-6">
                        <small class="text-muted d-block">Screen Condition</small>
                        <span class="fw-semibold text-dark"><?= htmlspecialchars($device['screen_condition'] ?? 'Pristine') ?></span>
                    </div>
                    <div class="col-6">
                        <small class="text-muted d-block">Body / Housing</small>
                        <span class="fw-semibold text-dark"><?= htmlspecialchars($device['body_condition'] ?? 'Pristine') ?></span>
                    </div>
                    <div class="col-6">
                        <small class="text-muted d-block">Camera & Flash</small>
                        <span class="fw-semibold text-dark"><?= htmlspecialchars($device['camera_condition'] ?? 'Working Perfectly') ?></span>
                    </div>
                    <div class="col-6">
                        <small class="text-muted d-block">Biometrics (Face ID / Touch ID)</small>
                        <span class="fw-semibold text-dark"><?= htmlspecialchars($device['biometrics'] ?? 'Functional') ?></span>
                    </div>
                    <div class="col-6">
                        <small class="text-muted d-block">Charging Port</small>
                        <span class="fw-semibold text-dark"><?= htmlspecialchars($device['charging_port'] ?? 'Working') ?></span>
                    </div>
                    <div class="col-6">
                        <small class="text-muted d-block">Speaker & Microphone</small>
                        <span class="fw-semibold text-dark"><?= htmlspecialchars($device['speaker_mic'] ?? 'Working') ?></span>
                    </div>
                    <div class="col-12">
                        <small class="text-muted d-block">Accessories Handed Over</small>
                        <span class="fw-semibold text-dark"><?= htmlspecialchars($device['accessories_received'] ?? 'None / Device only') ?></span>
                    </div>
                    <div class="col-12">
                        <small class="text-muted d-block">Existing Faults / Imperfections</small>
                        <span class="text-danger fw-semibold"><?= htmlspecialchars($device['existing_faults'] ?? 'None') ?></span>
                    </div>
                    <div class="col-12">
                        <small class="text-muted d-block">Technician / Inspector Remarks</small>
                        <div class="p-2 bg-light rounded text-dark small"><?= nl2br(htmlspecialchars($device['inspection_remarks'] ?? 'No special remarks recorded.')) ?></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Stock Movements History -->
        <div class="card">
            <div class="card-header bg-light">
                <span class="fw-bold"><i class="bi bi-clock-history me-1 text-secondary"></i> Device Movement & Audit Trail</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Movement Type</th>
                                <th>Branch</th>
                                <th>Cost Value</th>
                                <th>Reference Notes</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($movements as $m): ?>
                                <tr>
                                    <td><?= formatDate($m['created_at'], true) ?></td>
                                    <td><span class="badge bg-secondary text-uppercase"><?= str_replace('_', ' ', $m['movement_type']) ?></span></td>
                                    <td><?= htmlspecialchars($m['branch_name']) ?></td>
                                    <td><?= formatCurrency($m['unit_cost']) ?></td>
                                    <td class="text-muted small"><?= htmlspecialchars($m['notes']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
