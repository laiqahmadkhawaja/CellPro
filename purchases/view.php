<?php
// purchases/view.php - Purchase Invoice Detail View
$page_title = 'Purchase Invoice Details';
require_once __DIR__ . '/../includes/header.php';

$purchase_id = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare("SELECT p.*, s.name as supplier_name, s.phone as supplier_phone, s.address as supplier_address,
                              b.name as branch_name, u.name as created_by_name
                       FROM purchases p
                       LEFT JOIN suppliers s ON p.supplier_id = s.id
                       LEFT JOIN branches b ON p.branch_id = b.id
                       LEFT JOIN users u ON p.created_by = u.id
                       WHERE p.id = ?");
$stmt->execute([$purchase_id]);
$purchase = $stmt->fetch();

if (!$purchase) {
    flash('danger', 'Purchase invoice not found.');
    redirect(BASE_URL . 'purchases/index.php');
}

// Items
$stmt = $pdo->prepare("SELECT pi.*, md.imei_1, md.imei_2, md.model_name, md.device_condition 
                       FROM purchase_items pi
                       LEFT JOIN mobile_devices md ON pi.device_id = md.id
                       WHERE pi.purchase_id = ?");
$stmt->execute([$purchase_id]);
$items = $stmt->fetchAll();

// Payments
$stmt = $pdo->prepare("SELECT pp.*, fa.name as account_name 
                       FROM purchase_payments pp
                       LEFT JOIN financial_accounts fa ON pp.account_id = fa.id
                       WHERE pp.purchase_id = ?");
$stmt->execute([$purchase_id]);
$payments = $stmt->fetchAll();
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h5 class="fw-bold m-0"><i class="bi bi-receipt text-primary me-2"></i>Invoice #<?= htmlspecialchars($purchase['purchase_no']) ?></h5>
        <span class="badge bg-primary text-uppercase"><?= str_replace('_', ' ', $purchase['purchase_type']) ?></span>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= BASE_URL ?>purchases/index.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i> Back to Purchases
        </a>
        <button onclick="window.print()" class="btn btn-outline-primary btn-sm no-print">
            <i class="bi bi-printer me-1"></i> Print
        </button>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-12 col-md-6">
        <div class="card h-100">
            <div class="card-header bg-light">
                <span class="fw-bold">Supplier / Seller Information</span>
            </div>
            <div class="card-body">
                <div class="fw-bold text-dark fs-6"><?= htmlspecialchars($purchase['supplier_name'] ?? $purchase['seller_name'] ?? 'Counter Seller') ?></div>
                <div class="text-muted small">Phone: <?= htmlspecialchars($purchase['supplier_phone'] ?? $purchase['seller_phone'] ?? 'N/A') ?></div>
                <?php if ($purchase['seller_cnic']): ?>
                    <div class="text-muted small">CNIC: <?= htmlspecialchars($purchase['seller_cnic']) ?></div>
                <?php endif; ?>
                <?php if ($purchase['supplier_address']): ?>
                    <div class="text-muted small">Address: <?= htmlspecialchars($purchase['supplier_address']) ?></div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-12 col-md-6">
        <div class="card h-100">
            <div class="card-header bg-light">
                <span class="fw-bold">Invoice Details</span>
            </div>
            <div class="card-body">
                <div class="row g-2 small">
                    <div class="col-6 text-muted">Branch:</div>
                    <div class="col-6 fw-semibold text-dark"><?= htmlspecialchars($purchase['branch_name']) ?></div>
                    <div class="col-6 text-muted">Purchase Date:</div>
                    <div class="col-6 fw-semibold text-dark"><?= formatDate($purchase['purchase_date']) ?></div>
                    <div class="col-6 text-muted">Payment Status:</div>
                    <div class="col-6"><?= getStatusBadge($purchase['payment_status']) ?></div>
                    <div class="col-6 text-muted">Recorded By:</div>
                    <div class="col-6"><?= htmlspecialchars($purchase['created_by_name'] ?? 'Staff') ?></div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card mb-3">
    <div class="card-header bg-light">
        <span class="fw-bold">Purchased Items & Devices</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-bordered align-middle mb-0">
                <thead class="table-light small">
                    <tr>
                        <th>#</th>
                        <th>Item Description</th>
                        <th>IMEI / Identifiers</th>
                        <th class="text-center">Qty</th>
                        <th class="text-end">Unit Cost</th>
                        <th class="text-end">Extra Cost</th>
                        <th class="text-end">Total Cost</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $i = 1; foreach ($items as $item): ?>
                        <tr>
                            <td><?= $i++ ?></td>
                            <td>
                                <div class="fw-bold text-dark"><?= htmlspecialchars($item['item_name']) ?></div>
                                <?php if ($item['device_id']): ?>
                                    <a href="<?= BASE_URL ?>products/mobile_view.php?id=<?= $item['device_id'] ?>" class="small text-primary text-decoration-none">View Device File &rarr;</a>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($item['imei_1']): ?>
                                    <span class="font-monospace fw-semibold">IMEI 1: <?= htmlspecialchars($item['imei_1']) ?></span>
                                    <?php if ($item['imei_2']): ?>
                                        <div class="font-monospace text-muted small">IMEI 2: <?= htmlspecialchars($item['imei_2']) ?></div>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center"><?= $item['quantity'] ?></td>
                            <td class="text-end"><?= formatCurrency($item['unit_price']) ?></td>
                            <td class="text-end"><?= formatCurrency($item['additional_cost']) ?></td>
                            <td class="text-end fw-bold"><?= formatCurrency($item['total_cost']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot class="table-light">
                    <tr>
                        <td colspan="6" class="text-end fw-bold">Grand Total:</td>
                        <td class="text-end fw-bold text-primary fs-6"><?= formatCurrency($purchase['grand_total']) ?></td>
                    </tr>
                    <tr>
                        <td colspan="6" class="text-end fw-semibold text-success">Paid Amount:</td>
                        <td class="text-end fw-semibold text-success"><?= formatCurrency($purchase['paid_amount']) ?></td>
                    </tr>
                    <?php if ($purchase['grand_total'] - $purchase['paid_amount'] > 0): ?>
                        <tr>
                            <td colspan="6" class="text-end fw-bold text-danger">Remaining Payable:</td>
                            <td class="text-end fw-bold text-danger"><?= formatCurrency($purchase['grand_total'] - $purchase['paid_amount']) ?></td>
                        </tr>
                    <?php endif; ?>
                </tfoot>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
