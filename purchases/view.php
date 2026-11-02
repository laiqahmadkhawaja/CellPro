<?php
// purchases/view.php - Purchase Invoice Detail View & Printable Voucher
$page_title = 'Purchase Invoice Details';
require_once __DIR__ . '/../includes/header.php';

$purchase_id = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare("SELECT p.*, s.name as supplier_name, s.phone as supplier_phone, s.address as supplier_address,
                              b.name as branch_name, b.address as branch_address, b.phone as branch_phone,
                              u.name as created_by_name
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

<!-- Universal High-Definition ERP Print Header (Hidden on screen, visible on print) -->
<?= renderPrintHeader(
    'PURCHASE INVOICE', 
    'Official Goods Receipt & Supplier Voucher', 
    [
        'Invoice #' => $purchase['purchase_no'],
        'Purchase Date' => formatDate($purchase['purchase_date']),
        'Type' => strtoupper(str_replace('_', ' ', $purchase['purchase_type'])),
        'Payment' => strtoupper($purchase['payment_status'])
    ],
    $purchase['branch_id']
) ?>

<!-- Top Web Controls (Hidden on print) -->
<div class="d-flex justify-content-between align-items-center mb-3 no-print">
    <div>
        <h5 class="fw-bold m-0"><i class="bi bi-receipt text-primary me-2"></i>Invoice #<?= htmlspecialchars($purchase['purchase_no']) ?></h5>
        <span class="badge bg-primary text-uppercase"><?= str_replace('_', ' ', $purchase['purchase_type']) ?></span>
        <span class="text-muted ms-2 small">Branch: <?= htmlspecialchars($purchase['branch_name']) ?></span>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= BASE_URL ?>purchases/index.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i> Back to Purchases
        </a>
        <button onclick="window.print()" class="btn btn-primary btn-sm shadow-sm">
            <i class="bi bi-printer me-1"></i> Print Invoice Voucher
        </button>
    </div>
</div>

<!-- Supplier & Invoice Details Cards -->
<div class="row g-3 mb-3">
    <div class="col-12 col-md-6">
        <div class="card h-100">
            <div class="card-header">
                <span class="fw-bold"><i class="bi bi-truck me-1 text-primary no-print"></i>Supplier / Seller Information</span>
            </div>
            <div class="card-body">
                <div class="fw-bold text-dark fs-6 mb-1"><?= htmlspecialchars($purchase['supplier_name'] ?? $purchase['seller_name'] ?? 'Counter Seller') ?></div>
                <div class="text-muted small"><strong>Phone:</strong> <?= htmlspecialchars($purchase['supplier_phone'] ?? $purchase['seller_phone'] ?? 'N/A') ?></div>
                <?php if (!empty($purchase['seller_cnic'])): ?>
                    <div class="text-muted small"><strong>CNIC / ID:</strong> <?= htmlspecialchars($purchase['seller_cnic']) ?></div>
                <?php endif; ?>
                <?php if (!empty($purchase['supplier_address'])): ?>
                    <div class="text-muted small"><strong>Address:</strong> <?= htmlspecialchars($purchase['supplier_address']) ?></div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-12 col-md-6">
        <div class="card h-100">
            <div class="card-header">
                <span class="fw-bold"><i class="bi bi-file-earmark-text me-1 text-primary no-print"></i>Invoice Information</span>
            </div>
            <div class="card-body">
                <div class="row g-2 small">
                    <div class="col-5 text-muted">Receiving Branch:</div>
                    <div class="col-7 fw-semibold text-dark"><?= htmlspecialchars($purchase['branch_name']) ?></div>
                    <div class="col-5 text-muted">Purchase Date:</div>
                    <div class="col-7 fw-semibold text-dark"><?= formatDate($purchase['purchase_date']) ?></div>
                    <div class="col-5 text-muted">Payment Status:</div>
                    <div class="col-7"><?= getStatusBadge($purchase['payment_status']) ?></div>
                    <div class="col-5 text-muted">Recorded By:</div>
                    <div class="col-7 text-dark"><?= htmlspecialchars($purchase['created_by_name'] ?? 'Staff') ?></div>
                    <?php if (!empty($purchase['notes'])): ?>
                        <div class="col-5 text-muted">Supplier Ref #:</div>
                        <div class="col-7 text-dark font-monospace"><?= htmlspecialchars($purchase['notes']) ?></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Purchased Items & Devices Table -->
<div class="card mb-3">
    <div class="card-header">
        <span class="fw-bold"><i class="bi bi-box-seam me-1 text-primary no-print"></i>Purchased Items & Devices</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-bordered align-middle mb-0">
                <thead class="table-light small">
                    <tr>
                        <th style="width: 40px;" class="text-center">#</th>
                        <th>Item Description</th>
                        <th style="width: 250px;">IMEI / Identifiers</th>
                        <th class="text-center" style="width: 60px;">Qty</th>
                        <th class="text-end" style="width: 120px;">Unit Cost</th>
                        <th class="text-end" style="width: 110px;">Extra Cost</th>
                        <th class="text-end" style="width: 130px;">Total Cost</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $i = 1; foreach ($items as $item): ?>
                        <tr>
                            <td class="text-center"><?= $i++ ?></td>
                            <td>
                                <div class="fw-bold text-dark"><?= htmlspecialchars($item['item_name']) ?></div>
                                <?php if ($item['device_id']): ?>
                                    <a href="<?= BASE_URL ?>products/mobile_view.php?id=<?= $item['device_id'] ?>" class="small text-primary text-decoration-none no-print">View Device File &rarr;</a>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($item['imei_1']): ?>
                                    <div class="font-monospace fw-semibold small">IMEI 1: <?= htmlspecialchars($item['imei_1']) ?></div>
                                    <?php if ($item['imei_2']): ?>
                                        <div class="font-monospace text-muted small">IMEI 2: <?= htmlspecialchars($item['imei_2']) ?></div>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center fw-semibold"><?= $item['quantity'] ?></td>
                            <td class="text-end"><?= formatCurrency($item['unit_price']) ?></td>
                            <td class="text-end text-muted"><?= formatCurrency($item['additional_cost']) ?></td>
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

<!-- Payment Breakdown (if available) -->
<?php if (!empty($payments)): ?>
    <div class="card mb-3">
        <div class="card-header">
            <span class="fw-bold"><i class="bi bi-cash-coin me-1 text-success no-print"></i>Payment Records</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-bordered table-sm align-middle mb-0">
                    <thead class="table-light small">
                        <tr>
                            <th style="width: 140px;">Date</th>
                            <th>Paid From Account</th>
                            <th>Method</th>
                            <th class="text-end" style="width: 150px;">Amount Paid</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($payments as $pay): ?>
                            <tr>
                                <td><?= formatDate($pay['payment_date']) ?></td>
                                <td><?= htmlspecialchars($pay['account_name'] ?? 'Cash/Bank') ?></td>
                                <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($pay['payment_method']) ?></span></td>
                                <td class="text-end fw-bold text-success"><?= formatCurrency($pay['amount']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- Universal Formal Signatures Block (Visible only on print) -->
<?= renderPrintSignatures(['Received By (Store Incharge)', 'Verified By (Accounts)', 'Authorized Signatory / Vendor']) ?>

<!-- Universal Formal Footer (Visible only on print) -->
<?= renderPrintFooter('Goods once verified and received into inventory are subject to standard procurement and warranty agreements.') ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
