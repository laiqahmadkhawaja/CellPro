<?php
// transfers/view.php - Transfer Details & Confirmation
$page_title = 'Stock Transfer Details';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/inventory.php';

$transfer_id = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare("SELECT st.*, b1.name as from_branch_name, b2.name as to_branch_name, 
                              u1.name as creator_name, u2.name as receiver_name
                       FROM stock_transfers st
                       JOIN branches b1 ON st.from_branch_id = b1.id
                       JOIN branches b2 ON st.to_branch_id = b2.id
                       LEFT JOIN users u1 ON st.created_by = u1.id
                       LEFT JOIN users u2 ON st.received_by = u2.id
                       WHERE st.id = ?");
$stmt->execute([$transfer_id]);
$transfer = $stmt->fetch();

if (!$transfer) {
    flash('danger', 'Transfer not found.');
    redirect(BASE_URL . 'transfers/index.php');
}

// Items
$stmt = $pdo->prepare("SELECT sti.*, md.imei_1, md.model_name as dev_model 
                       FROM stock_transfer_items sti
                       LEFT JOIN mobile_devices md ON sti.device_id = md.id
                       WHERE sti.transfer_id = ?");
$stmt->execute([$transfer_id]);
$items = $stmt->fetchAll();

// Handle Actions (Receive or Cancel)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validatePostCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'receive' && $transfer['status'] === 'dispatched') {
        try {
            $pdo->beginTransaction();

            $recv_date = date('Y-m-d');
            $upd = $pdo->prepare("UPDATE stock_transfers SET status = 'received', receive_date = ?, received_by = ? WHERE id = ?");
            $upd->execute([$recv_date, $_SESSION['user_id'], $transfer_id]);

            foreach ($items as $item) {
                if ($item['item_type'] === 'mobile') {
                    // Update device to new branch & available
                    $pdo->prepare("UPDATE mobile_devices SET branch_id = ?, device_status = 'available' WHERE id = ?")
                        ->execute([$transfer['to_branch_id'], $item['device_id']]);

                    recordStockMovement($pdo, $transfer['to_branch_id'], null, $item['device_id'], 'transfer_in', 1, $item['unit_cost'], 'transfer', $transfer_id, "Received from {$transfer['from_branch_name']}");
                } else {
                    // Check if receiving branch already has product with same barcode/sku
                    $orig = $pdo->prepare("SELECT * FROM products WHERE id = ?");
                    $orig->execute([$item['product_id']]);
                    $orig_p = $orig->fetch();

                    // Find product in destination branch
                    $findDest = $pdo->prepare("SELECT id FROM products WHERE branch_id = ? AND (barcode = ? OR sku = ?) LIMIT 1");
                    $findDest->execute([$transfer['to_branch_id'], $orig_p['barcode'], $orig_p['sku']]);
                    $dest_pid = $findDest->fetchColumn();

                    if (!$dest_pid) {
                        // Create in destination branch
                        $insP = $pdo->prepare("INSERT INTO products (branch_id, category_id, brand_id, name, sku, barcode, unit_id, product_type, cost_price, retail_price, quantity, min_stock_level, description)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?, ?)");
                        $insP->execute([
                            $transfer['to_branch_id'], $orig_p['category_id'], $orig_p['brand_id'], $orig_p['name'], $orig_p['sku'], $orig_p['barcode'],
                            $orig_p['unit_id'], $orig_p['product_type'], $orig_p['cost_price'], $orig_p['retail_price'], $orig_p['min_stock_level'], $orig_p['description']
                        ]);
                        $dest_pid = $pdo->lastInsertId();
                    }

                    recordStockMovement($pdo, $transfer['to_branch_id'], $dest_pid, null, 'transfer_in', $item['quantity'], $item['unit_cost'], 'transfer', $transfer_id, "Received from {$transfer['from_branch_name']}");
                }
            }

            logAudit($pdo, 'receive', 'stock_transfers', $transfer_id, "Received stock transfer {$transfer['transfer_no']}");

            $pdo->commit();
            flash('success', "Transfer {$transfer['transfer_no']} successfully received and added to inventory!");
            redirect(BASE_URL . 'transfers/view.php?id=' . $transfer_id);

        } catch (Exception $e) {
            $pdo->rollBack();
            flash('danger', 'Error receiving transfer: ' . $e->getMessage());
        }
    } elseif ($action === 'cancel' && $transfer['status'] === 'dispatched') {
        try {
            $pdo->beginTransaction();

            $upd = $pdo->prepare("UPDATE stock_transfers SET status = 'cancelled' WHERE id = ?");
            $upd->execute([$transfer_id]);

            // Return items back to source branch
            foreach ($items as $item) {
                if ($item['item_type'] === 'mobile') {
                    $pdo->prepare("UPDATE mobile_devices SET branch_id = ?, device_status = 'available' WHERE id = ?")
                        ->execute([$transfer['from_branch_id'], $item['device_id']]);
                    recordStockMovement($pdo, $transfer['from_branch_id'], null, $item['device_id'], 'return_in', 1, $item['unit_cost'], 'transfer_cancel', $transfer_id, "Transfer cancelled");
                } else {
                    recordStockMovement($pdo, $transfer['from_branch_id'], $item['product_id'], null, 'return_in', $item['quantity'], $item['unit_cost'], 'transfer_cancel', $transfer_id, "Transfer cancelled");
                }
            }

            logAudit($pdo, 'cancel', 'stock_transfers', $transfer_id, "Cancelled transfer {$transfer['transfer_no']}");

            $pdo->commit();
            flash('info', "Transfer {$transfer['transfer_no']} cancelled. Items restored to source branch.");
            redirect(BASE_URL . 'transfers/view.php?id=' . $transfer_id);

        } catch (Exception $e) {
            $pdo->rollBack();
            flash('danger', 'Error cancelling transfer: ' . $e->getMessage());
        }
    }
}
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h5 class="fw-bold m-0"><i class="bi bi-arrow-left-right text-info me-2"></i>Transfer #<?= htmlspecialchars($transfer['transfer_no']) ?></h5>
        <?= getStatusBadge($transfer['status']) ?>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= BASE_URL ?>transfers/index.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i> Back to Transfers
        </a>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-12 col-md-6">
        <div class="card h-100">
            <div class="card-header bg-light">
                <span class="fw-bold">Routing Details</span>
            </div>
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between p-3 bg-light rounded-3 mb-2">
                    <div>
                        <small class="text-muted text-uppercase d-block">Source Branch (From)</small>
                        <h6 class="fw-bold m-0 text-dark"><?= htmlspecialchars($transfer['from_branch_name']) ?></h6>
                    </div>
                    <i class="bi bi-arrow-right fs-4 text-primary"></i>
                    <div>
                        <small class="text-muted text-uppercase d-block">Destination Branch (To)</small>
                        <h6 class="fw-bold m-0 text-dark"><?= htmlspecialchars($transfer['to_branch_name']) ?></h6>
                    </div>
                </div>
                <div class="small text-muted">Notes: <?= htmlspecialchars($transfer['notes'] ?: 'No notes') ?></div>
            </div>
        </div>
    </div>

    <div class="col-12 col-md-6">
        <div class="card h-100">
            <div class="card-header bg-light">
                <span class="fw-bold">Audit & Timestamps</span>
            </div>
            <div class="card-body small">
                <div class="row g-2">
                    <div class="col-6 text-muted">Created / Dispatched:</div>
                    <div class="col-6 fw-semibold"><?= formatDate($transfer['created_at'], true) ?> by <?= htmlspecialchars($transfer['creator_name'] ?? 'Staff') ?></div>
                    <div class="col-6 text-muted">Status:</div>
                    <div class="col-6"><?= getStatusBadge($transfer['status']) ?></div>
                    <?php if ($transfer['receive_date']): ?>
                        <div class="col-6 text-muted">Received Date:</div>
                        <div class="col-6 fw-semibold text-success"><?= formatDate($transfer['receive_date']) ?> by <?= htmlspecialchars($transfer['receiver_name'] ?? 'Staff') ?></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Items Table -->
<div class="card mb-3">
    <div class="card-header bg-light">
        <span class="fw-bold">Transferred Items (<?= count($items) ?> Records)</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-bordered align-middle mb-0">
                <thead class="table-light small">
                    <tr>
                        <th>#</th>
                        <th>Item Type</th>
                        <th>Item Name / Device</th>
                        <th>IMEI</th>
                        <th class="text-center">Quantity</th>
                        <th class="text-end">Unit Cost</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $i = 1; foreach ($items as $item): ?>
                        <tr>
                            <td><?= $i++ ?></td>
                            <td><span class="badge bg-secondary text-uppercase"><?= $item['item_type'] ?></span></td>
                            <td class="fw-bold text-dark"><?= htmlspecialchars($item['item_name']) ?></td>
                            <td>
                                <?= $item['imei_1'] ? '<span class="font-monospace fw-semibold small">IMEI: ' . htmlspecialchars($item['imei_1']) . '</span>' : '-' ?>
                            </td>
                            <td class="text-center fw-semibold"><?= $item['quantity'] ?></td>
                            <td class="text-end"><?= formatCurrency($item['unit_cost']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Action Bar -->
<?php if ($transfer['status'] === 'dispatched'): ?>
    <div class="card bg-light border p-3">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div>
                <h6 class="fw-bold m-0"><i class="bi bi-clock-history me-1 text-warning"></i> Transfer In Transit</h6>
                <small class="text-muted">Stock has been deducted from source branch and is waiting for destination branch to accept.</small>
            </div>
            <div class="d-flex gap-2">
                <form method="POST" action="" onsubmit="return confirm('Cancel this transfer and return stock to source branch?');">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="cancel">
                    <button type="submit" class="btn btn-outline-danger btn-sm">Cancel Transfer</button>
                </form>

                <form method="POST" action="" onsubmit="return confirm('Confirm receipt of all devices and accessories into destination branch stock?');">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="receive">
                    <button type="submit" class="btn btn-success btn-sm px-3 shadow-sm">
                        <i class="bi bi-check2-circle me-1"></i> Confirm Receipt & Add to Inventory
                    </button>
                </form>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
