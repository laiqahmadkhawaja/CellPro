<?php
// reports/inventory.php - Inventory Valuation & Stock Summary
$page_title = 'Inventory Valuation Report';
require_once __DIR__ . '/../includes/header.php';

$branch_id = getActiveBranchId();

// Mobile Stock Summary
$stmt = $pdo->prepare("SELECT COUNT(id) as total_devices,
                              COALESCE(SUM(purchase_price), 0) as total_purchase,
                              COALESCE(SUM(additional_cost), 0) as total_extra,
                              COALESCE(SUM(actual_cost), 0) as total_actual_cost,
                              COALESCE(SUM(selling_price), 0) as total_retail_value
                       FROM mobile_devices 
                       WHERE device_status = 'available' " . ($branch_id ? "AND branch_id = $branch_id" : ""));
$stmt->execute();
$mobile_val = $stmt->fetch();

// Accessories Stock Summary
$stmt = $pdo->prepare("SELECT COUNT(id) as total_skus,
                              COALESCE(SUM(quantity), 0) as total_units,
                              COALESCE(SUM(quantity * cost_price), 0) as total_cost_value,
                              COALESCE(SUM(quantity * retail_price), 0) as total_retail_value
                       FROM products 
                       WHERE is_active = 1 " . ($branch_id ? "AND branch_id = $branch_id" : ""));
$stmt->execute();
$acc_val = $stmt->fetch();

// Top Value Mobiles in Stock
$stmt = $pdo->prepare("SELECT md.*, b.name as brand_name, br.name as branch_name 
                       FROM mobile_devices md
                       LEFT JOIN brands b ON md.brand_id = b.id
                       LEFT JOIN branches br ON md.branch_id = br.id
                       WHERE md.device_status = 'available' " . ($branch_id ? "AND md.branch_id = $branch_id" : "") . "
                       ORDER BY md.actual_cost DESC LIMIT 20");
$stmt->execute();
$mobiles = $stmt->fetchAll();

// Top Value Accessories in Stock
$stmt = $pdo->prepare("SELECT p.*, b.name as brand_name, br.name as branch_name, (p.quantity * p.cost_price) as extended_cost 
                       FROM products p
                       LEFT JOIN brands b ON p.brand_id = b.id
                       LEFT JOIN branches br ON p.branch_id = br.id
                       WHERE p.is_active = 1 " . ($branch_id ? "AND p.branch_id = $branch_id" : "") . "
                       ORDER BY extended_cost DESC LIMIT 20");
$stmt->execute();
$accessories = $stmt->fetchAll();
?>

<?= renderPrintHeader(
    'INVENTORY VALUATION REPORT',
    'Physical Stock On-Hand Valuation at Cost and Projected Retail',
    [
        'Valuation Date' => date('d M Y'),
        'Mobile Devices' => $mobile_val['total_devices'] . ' Units',
        'Total Stock Value' => formatCurrency($grand_cost_valuation)
    ]
) ?>

<div class="d-flex justify-content-between align-items-center mb-3 no-print">
    <div>
        <h5 class="fw-bold m-0"><i class="bi bi-boxes text-primary me-2"></i>Inventory Valuation Report</h5>
        <small class="text-muted">Current inventory on hand at cost value and projected retail realization</small>
    </div>
    <div class="d-flex gap-2">
        <button onclick="window.print()" class="btn btn-primary btn-sm no-print">
            <i class="bi bi-printer me-1"></i> Print Valuation Report
        </button>
    </div>
</div>

<!-- Valuation Summary Cards -->
<div class="row g-3 mb-4">
    <div class="col-12 col-md-4">
        <div class="stat-card">
            <div class="stat-icon bg-info bg-opacity-10 text-info">
                <i class="bi bi-phone"></i>
            </div>
            <div>
                <small class="text-muted text-uppercase fw-semibold" style="font-size:0.75rem;">Mobiles In Stock (<?= $mobile_val['total_devices'] ?> Units)</small>
                <h4 class="fw-bold m-0 text-dark"><?= formatCurrency($mobile_val['total_actual_cost']) ?></h4>
                <small class="text-muted">Retail Value: <?= formatCurrency($mobile_val['total_retail_value']) ?></small>
            </div>
        </div>
    </div>
    <div class="col-12 col-md-4">
        <div class="stat-card">
            <div class="stat-icon bg-success bg-opacity-10 text-success">
                <i class="bi bi-headphones"></i>
            </div>
            <div>
                <small class="text-muted text-uppercase fw-semibold" style="font-size:0.75rem;">Accessories Stock (<?= $acc_val['total_units'] ?> Units)</small>
                <h4 class="fw-bold m-0 text-dark"><?= formatCurrency($acc_val['total_cost_value']) ?></h4>
                <small class="text-muted">Retail Value: <?= formatCurrency($acc_val['total_retail_value']) ?></small>
            </div>
        </div>
    </div>
    <div class="col-12 col-md-4">
        <div class="stat-card">
            <div class="stat-icon bg-primary bg-opacity-10 text-primary">
                <i class="bi bi-safe2"></i>
            </div>
            <div>
                <small class="text-muted text-uppercase fw-semibold" style="font-size:0.75rem;">Total Store Stock Valuation</small>
                <h4 class="fw-bold m-0 text-primary"><?= formatCurrency($mobile_val['total_actual_cost'] + $acc_val['total_cost_value']) ?></h4>
                <small class="text-muted">Projected Gross Profit: <?= formatCurrency(($mobile_val['total_retail_value'] + $acc_val['total_retail_value']) - ($mobile_val['total_actual_cost'] + $acc_val['total_cost_value'])) ?></small>
            </div>
        </div>
    </div>
</div>

<!-- Mobile Phones Available -->
<div class="card mb-4">
    <div class="card-header bg-light">
        <span class="fw-bold"><i class="bi bi-phone me-1 text-primary"></i> Available Mobile Phones in Stock</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-bordered table-hover align-middle mb-0">
                <thead class="table-light small">
                    <tr>
                        <th>Device Name</th>
                        <th>IMEI 1</th>
                        <th>Condition</th>
                        <th>PTA Status</th>
                        <th>Branch</th>
                        <th class="text-end">Actual Cost</th>
                        <th class="text-end">Selling Price</th>
                        <th class="text-end">Projected Profit</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($mobiles as $m): ?>
                        <tr>
                            <td>
                                <div class="fw-bold text-dark"><?= htmlspecialchars($m['model_name']) ?></div>
                                <small class="text-muted"><?= $m['ram'] ?> / <?= $m['storage'] ?> &bull; <?= $m['color'] ?></small>
                            </td>
                            <td class="font-monospace fw-semibold small"><?= htmlspecialchars($m['imei_1']) ?></td>
                            <td><?= getStatusBadge($m['device_condition']) ?></td>
                            <td><?= getStatusBadge($m['pta_status']) ?></td>
                            <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($m['branch_name']) ?></span></td>
                            <td class="text-end text-danger fw-semibold"><?= formatCurrency($m['actual_cost']) ?></td>
                            <td class="text-end fw-bold"><?= formatCurrency($m['selling_price']) ?></td>
                            <td class="text-end fw-bold text-success"><?= formatCurrency($m['selling_price'] - $m['actual_cost']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Accessories Stock -->
<div class="card">
    <div class="card-header bg-light">
        <span class="fw-bold"><i class="bi bi-headphones me-1 text-success"></i> Accessories & Parts Stock Valuation</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-bordered table-hover align-middle mb-0">
                <thead class="table-light small">
                    <tr>
                        <th>Product Title</th>
                        <th>Barcode / SKU</th>
                        <th class="text-center">Units On Hand</th>
                        <th class="text-end">Unit Cost</th>
                        <th class="text-end">Retail Price</th>
                        <th class="text-end">Extended Cost Value</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($accessories as $a): ?>
                        <tr>
                            <td class="fw-bold text-dark"><?= htmlspecialchars($a['name']) ?></td>
                            <td class="font-monospace text-muted small"><?= htmlspecialchars($a['barcode'] ?: $a['sku']) ?></td>
                            <td class="text-center fw-bold"><?= $a['quantity'] ?></td>
                            <td class="text-end text-muted"><?= formatCurrency($a['cost_price']) ?></td>
                            <td class="text-end"><?= formatCurrency($a['retail_price']) ?></td>
                            <td class="text-end fw-bold text-primary"><?= formatCurrency($a['extended_cost']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
