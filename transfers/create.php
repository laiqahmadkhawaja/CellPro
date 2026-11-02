<?php
// transfers/create.php - Create Inter-Branch Transfer
$page_title = 'Create Stock Transfer';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/inventory.php';

$branch_id = getActiveBranchId();
$branches = getBranches($pdo);

// Available mobiles in source branch
$stmt = $pdo->prepare("SELECT id, model_name, imei_1, ram, storage, actual_cost, selling_price 
                       FROM mobile_devices 
                       WHERE branch_id = ? AND device_status = 'available' 
                       ORDER BY model_name ASC");
$stmt->execute([$branch_id]);
$source_mobiles = $stmt->fetchAll();

// Available accessories in source branch
$stmt = $pdo->prepare("SELECT id, name, barcode, sku, quantity, cost_price 
                       FROM products 
                       WHERE branch_id = ? AND is_active = 1 AND quantity > 0 
                       ORDER BY name ASC");
$stmt->execute([$branch_id]);
$source_accessories = $stmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validatePostCsrf();
    $from_branch_id = (int)$_POST['from_branch_id'];
    $to_branch_id = (int)$_POST['to_branch_id'];
    $notes = sanitize($_POST['notes'] ?? '');

    $device_ids = $_POST['transfer_devices'] ?? [];
    $acc_ids = $_POST['transfer_accessories'] ?? [];
    $acc_qtys = $_POST['acc_qty'] ?? [];

    if ($from_branch_id === $to_branch_id) {
        flash('danger', 'Source and destination branches must be different.');
        redirect(BASE_URL . 'transfers/create.php');
    }

    if (empty($device_ids) && empty($acc_ids)) {
        flash('danger', 'Please select at least one mobile device or accessory to transfer.');
        redirect(BASE_URL . 'transfers/create.php');
    }

    try {
        $pdo->beginTransaction();

        $transfer_no = generateDocNo($pdo, 'TRF', 'stock_transfers', 'transfer_no');
        $total_items = count($device_ids);

        foreach ($acc_ids as $aid) {
            $total_items += (int)($acc_qtys[$aid] ?? 1);
        }

        $stmt = $pdo->prepare("INSERT INTO stock_transfers 
            (transfer_no, from_branch_id, to_branch_id, status, total_items, notes, created_by)
            VALUES (?, ?, ?, 'dispatched', ?, ?, ?)");
        $stmt->execute([$transfer_no, $from_branch_id, $to_branch_id, $total_items, $notes, $_SESSION['user_id']]);
        $transfer_id = $pdo->lastInsertId();

        $itemStmt = $pdo->prepare("INSERT INTO stock_transfer_items (transfer_id, item_type, product_id, device_id, item_name, quantity, unit_cost) VALUES (?, ?, ?, ?, ?, ?, ?)");

        // 1. Process Mobile Devices
        $mStmt = $pdo->prepare("SELECT * FROM mobile_devices WHERE id = ? AND branch_id = ? AND device_status = 'available' FOR UPDATE");
        foreach ($device_ids as $did) {
            $mStmt->execute([$did, $from_branch_id]);
            $device = $mStmt->fetch();
            if (!$device) continue;

            $itemStmt->execute([$transfer_id, 'mobile', null, $device['id'], $device['model_name'] . ' (' . $device['imei_1'] . ')', 1, $device['actual_cost']]);

            // Mark device as transferred (in transit)
            recordStockMovement($pdo, $from_branch_id, null, $device['id'], 'transfer_out', -1, $device['actual_cost'], 'transfer', $transfer_id, "Dispatched to branch in {$transfer_no}");
        }

        // 2. Process Accessories
        $aStmt = $pdo->prepare("SELECT * FROM products WHERE id = ? AND branch_id = ? FOR UPDATE");
        foreach ($acc_ids as $aid) {
            $aStmt->execute([$aid, $from_branch_id]);
            $prod = $aStmt->fetch();
            if (!$prod) continue;

            $qty = min((int)($acc_qtys[$aid] ?? 1), $prod['quantity']);
            if ($qty <= 0) continue;

            $itemStmt->execute([$transfer_id, 'accessory', $prod['id'], null, $prod['name'], $qty, $prod['cost_price']]);

            // Deduct from source branch stock
            recordStockMovement($pdo, $from_branch_id, $prod['id'], null, 'transfer_out', -$qty, $prod['cost_price'], 'transfer', $transfer_id, "Dispatched in {$transfer_no}");
        }

        logAudit($pdo, 'create', 'stock_transfers', $transfer_id, "Initiated stock transfer {$transfer_no} to branch {$to_branch_id}");

        $pdo->commit();
        flash('success', "Stock transfer {$transfer_no} dispatched successfully!");
        redirect(BASE_URL . 'transfers/view.php?id=' . $transfer_id);

    } catch (Exception $e) {
        $pdo->rollBack();
        flash('danger', 'Error creating transfer: ' . $e->getMessage());
    }
}
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h5 class="fw-bold m-0"><i class="bi bi-arrow-left-right text-info me-2"></i>New Stock Transfer</h5>
        <small class="text-muted">Dispatch devices or accessories to another branch</small>
    </div>
    <a href="<?= BASE_URL ?>transfers/index.php" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i> Transfers List
    </a>
</div>

<form method="POST" action="">
    <?= csrfField() ?>

    <div class="card mb-3">
        <div class="card-header bg-light">
            <span class="fw-bold">Routing & Branches</span>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-12 col-md-4">
                    <label class="form-label small fw-semibold">Source Branch (From)</label>
                    <select name="from_branch_id" class="form-select" required>
                        <?php foreach ($branches as $b): ?>
                            <option value="<?= $b['id'] ?>" <?= $b['id'] == $branch_id ? 'selected' : '' ?>>
                                <?= htmlspecialchars($b['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12 col-md-4">
                    <label class="form-label small fw-semibold">Destination Branch (To) *</label>
                    <select name="to_branch_id" class="form-select" required>
                        <option value="">-- Select Receiving Branch --</option>
                        <?php foreach ($branches as $b): ?>
                            <?php if ($b['id'] != $branch_id): ?>
                                <option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['name']) ?></option>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12 col-md-4">
                    <label class="form-label small fw-semibold">Transfer Notes</label>
                    <input type="text" name="notes" class="form-control" placeholder="Driver name, courier slip #...">
                </div>
            </div>
        </div>
    </div>

    <!-- 1. Available Mobiles in Source Branch -->
    <div class="card mb-3">
        <div class="card-header bg-light">
            <span class="fw-bold"><i class="bi bi-phone me-1 text-primary"></i> Select Mobile Phones to Dispatch</span>
        </div>
        <div class="card-body p-0">
            <?php if (empty($source_mobiles)): ?>
                <div class="p-4 text-center text-muted">No mobile phones available in source branch stock.</div>
            <?php else: ?>
                <div class="table-responsive" style="max-height: 250px;">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light small sticky-top">
                            <tr>
                                <th style="width: 40px;"></th>
                                <th>Model Name</th>
                                <th>IMEI 1</th>
                                <th>Specs</th>
                                <th class="text-end">Cost</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($source_mobiles as $m): ?>
                                <tr>
                                    <td class="text-center">
                                        <input type="checkbox" name="transfer_devices[]" value="<?= $m['id'] ?>" class="form-check-input">
                                    </td>
                                    <td class="fw-bold text-dark"><?= htmlspecialchars($m['model_name']) ?></td>
                                    <td class="font-monospace fw-semibold small"><?= htmlspecialchars($m['imei_1']) ?></td>
                                    <td class="small text-muted"><?= $m['ram'] ?> / <?= $m['storage'] ?></td>
                                    <td class="text-end"><?= formatCurrency($m['actual_cost']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- 2. Available Accessories in Source Branch -->
    <div class="card mb-4">
        <div class="card-header bg-light">
            <span class="fw-bold"><i class="bi bi-headphones me-1 text-success"></i> Select Accessories to Dispatch</span>
        </div>
        <div class="card-body p-0">
            <?php if (empty($source_accessories)): ?>
                <div class="p-4 text-center text-muted">No accessories in stock in source branch.</div>
            <?php else: ?>
                <div class="table-responsive" style="max-height: 250px;">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light small sticky-top">
                            <tr>
                                <th style="width: 40px;"></th>
                                <th>Product Name</th>
                                <th>Barcode / SKU</th>
                                <th class="text-center">Available Stock</th>
                                <th class="text-center" style="width: 120px;">Qty to Send</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($source_accessories as $a): ?>
                                <tr>
                                    <td class="text-center">
                                        <input type="checkbox" name="transfer_accessories[]" value="<?= $a['id'] ?>" class="form-check-input">
                                    </td>
                                    <td class="fw-bold text-dark"><?= htmlspecialchars($a['name']) ?></td>
                                    <td class="font-monospace small text-muted"><?= htmlspecialchars($a['barcode'] ?: $a['sku']) ?></td>
                                    <td class="text-center"><span class="badge bg-light text-dark border"><?= $a['quantity'] ?></span></td>
                                    <td class="text-center">
                                        <input type="number" name="acc_qty[<?= $a['id'] ?>]" class="form-control form-control-sm text-center" value="1" min="1" max="<?= $a['quantity'] ?>">
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
        <div class="card-footer bg-white text-end">
            <button type="submit" class="btn btn-primary btn-lg px-4 shadow-sm">
                <i class="bi bi-send me-1"></i> Dispatch Transfer
            </button>
        </div>
    </div>
</form>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
