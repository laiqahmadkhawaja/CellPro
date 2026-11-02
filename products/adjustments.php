<?php
// products/adjustments.php - Stock Adjustments (Damage, Physical Count Correction)
$page_title = 'Stock Adjustments';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/inventory.php';

$branch_id = getActiveBranchId();
$products = $pdo->prepare("SELECT id, name, barcode, sku, quantity FROM products WHERE branch_id = ? AND is_active = 1 ORDER BY name ASC");
$products->execute([$branch_id]);
$all_prods = $products->fetchAll();

// Handle adjustment submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validatePostCsrf();
    $product_id = (int)($_POST['product_id'] ?? 0);
    $adj_type = sanitize($_POST['adjustment_type'] ?? 'deduct'); // 'add' or 'deduct'
    $qty = (int)($_POST['quantity'] ?? 0);
    $reason = sanitize($_POST['reason'] ?? 'Inventory Count Correction');

    if ($product_id <= 0 || $qty <= 0) {
        flash('danger', 'Please select a valid product and enter a positive quantity.');
        redirect(BASE_URL . 'products/adjustments.php');
    }

    try {
        $pdo->beginTransaction();

        $adj_no = generateDocNo($pdo, 'ADJ', 'stock_adjustments', 'adjustment_no');
        $movement_qty = ($adj_type === 'add') ? $qty : -$qty;

        // Record Adjustment
        $stmt = $pdo->prepare("INSERT INTO stock_adjustments 
            (adjustment_no, branch_id, product_id, adjustment_type, quantity, reason, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$adj_no, $branch_id, $product_id, $adj_type, $qty, $reason, $_SESSION['user_id']]);
        $adj_id = $pdo->lastInsertId();

        // Get product cost for stock movement
        $pStmt = $pdo->prepare("SELECT cost_price FROM products WHERE id = ?");
        $pStmt->execute([$product_id]);
        $cost = (float)$pStmt->fetchColumn();

        // Record stock movement & update products quantity
        recordStockMovement($pdo, $branch_id, $product_id, null, 'adjustment', $movement_qty, $cost, 'adjustment', $adj_id, "Stock adjustment #{$adj_no}: {$reason}");

        logAudit($pdo, 'create', 'stock_adjustments', $adj_id, "Adjusted product ID {$product_id} by {$movement_qty}");

        $pdo->commit();
        flash('success', "Stock adjustment {$adj_no} processed successfully.");
        redirect(BASE_URL . 'products/adjustments.php');

    } catch (Exception $e) {
        $pdo->rollBack();
        flash('danger', 'Error processing adjustment: ' . $e->getMessage());
        redirect(BASE_URL . 'products/adjustments.php');
    }
}

// Fetch adjustment history
$stmt = $pdo->prepare("SELECT sa.*, p.name as product_name, p.barcode, u.name as user_name 
                       FROM stock_adjustments sa
                       JOIN products p ON sa.product_id = p.id
                       LEFT JOIN users u ON sa.created_by = u.id
                       WHERE sa.branch_id = ?
                       ORDER BY sa.id DESC LIMIT 50");
$stmt->execute([$branch_id]);
$adjustments = $stmt->fetchAll();
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h5 class="fw-bold m-0"><i class="bi bi-sliders text-warning me-2"></i>Stock Adjustments</h5>
        <small class="text-muted">Reconcile inventory for damaged, expired, or discrepancy items</small>
    </div>
    <button type="button" class="btn btn-primary btn-sm shadow-sm" data-toggle-panel="panel-new-adjustment">
        <i class="bi bi-plus-circle me-1"></i> New Stock Adjustment
    </button>
</div>

<!-- Adjustments Log Table -->
<div class="card">
    <div class="card-body p-0">
        <?php if (empty($adjustments)): ?>
            <div class="text-center py-5 text-muted">
                <i class="bi bi-clipboard-check fs-1 mb-2 d-block opacity-50"></i>
                No stock adjustments recorded yet.
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Adjustment #</th>
                            <th>Date</th>
                            <th>Product Name</th>
                            <th>Adjustment Type</th>
                            <th class="text-center">Quantity</th>
                            <th>Reason</th>
                            <th>Adjusted By</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($adjustments as $a): ?>
                            <tr>
                                <td class="fw-bold text-primary"><?= htmlspecialchars($a['adjustment_no']) ?></td>
                                <td><?= formatDate($a['created_at'], true) ?></td>
                                <td>
                                    <div class="fw-semibold text-dark"><?= htmlspecialchars($a['product_name']) ?></div>
                                    <small class="text-muted font-monospace"><?= htmlspecialchars($a['barcode']) ?></small>
                                </td>
                                <td>
                                    <?php if ($a['adjustment_type'] === 'add'): ?>
                                        <span class="badge bg-success-subtle text-success border"><i class="bi bi-plus-lg me-1"></i>Stock Addition</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger-subtle text-danger border"><i class="bi bi-dash-lg me-1"></i>Stock Deduction</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center fw-bold fs-6">
                                    <?= $a['adjustment_type'] === 'add' ? '+' : '-' ?><?= $a['quantity'] ?>
                                </td>
                                <td class="small text-muted"><?= htmlspecialchars($a['reason']) ?></td>
                                <td><?= htmlspecialchars($a['user_name'] ?? 'System') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- ============================================== -->
<!-- SIDE-PANEL DRAWER: CREATE ADJUSTMENT           -->
<!-- (PRD: Zero Modals Rule)                        -->
<!-- ============================================== -->
<div id="panel-new-adjustment" class="side-panel">
    <div class="side-panel-header">
        <h6 class="m-0 fw-bold"><i class="bi bi-sliders me-2 text-warning"></i>Record Stock Adjustment</h6>
        <button type="button" class="btn-close" data-close-panel></button>
    </div>
    <div class="side-panel-body">
        <form method="POST" action="">
            <?= csrfField() ?>
            <div class="mb-3">
                <label class="form-label small fw-semibold">Select Product *</label>
                <select name="product_id" class="form-select" required>
                    <option value="">-- Choose Product --</option>
                    <?php foreach ($all_prods as $p): ?>
                        <option value="<?= $p['id'] ?>">
                            <?= htmlspecialchars($p['name']) ?> (Current Stock: <?= $p['quantity'] ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="mb-3">
                <label class="form-label small fw-semibold">Adjustment Action *</label>
                <select name="adjustment_type" class="form-select" required>
                    <option value="deduct">Deduct Stock (Damage, Loss, Theft, Expiry)</option>
                    <option value="add">Add Stock (Found Stock, Physical Count Surplus)</option>
                </select>
            </div>

            <div class="mb-3">
                <label class="form-label small fw-semibold">Adjustment Quantity *</label>
                <input type="number" name="quantity" class="form-control" placeholder="1" min="1" required>
            </div>

            <div class="mb-4">
                <label class="form-label small fw-semibold">Reason / Explanation *</label>
                <textarea name="reason" class="form-control" rows="3" placeholder="Explain why stock is being adjusted..." required></textarea>
            </div>

            <div class="side-panel-footer px-0 pb-0">
                <button type="button" class="btn btn-light" data-close-panel>Cancel</button>
                <button type="submit" class="btn btn-primary">Save Adjustment</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
