<?php
// repairs/view.php - Repair Bill Printable Receipt
$page_title = 'Repair Bill Receipt';
require_once __DIR__ . '/../includes/header.php';

$bill_id = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare("SELECT rb.*, b.name as branch_name, b.address as branch_address, b.phone as branch_phone, u.name as technician_name 
                       FROM repairing_bills rb
                       LEFT JOIN branches b ON rb.branch_id = b.id
                       LEFT JOIN users u ON rb.created_by = u.id
                       WHERE rb.id = ?");
$stmt->execute([$bill_id]);
$bill = $stmt->fetch();

if (!$bill) {
    flash('danger', 'Repair bill not found.');
    redirect(BASE_URL . 'repairs/index.php');
}

// Items
$stmt = $pdo->prepare("SELECT * FROM repairing_bill_items WHERE bill_id = ?");
$stmt->execute([$bill_id]);
$items = $stmt->fetchAll();
?>

<div class="d-flex justify-content-between align-items-center mb-3 no-print">
    <div>
        <h5 class="fw-bold m-0"><i class="bi bi-tools text-warning me-2"></i>Repair Bill #<?= htmlspecialchars($bill['bill_no']) ?></h5>
        <small class="text-muted"><?= formatDate($bill['repair_date']) ?> &bull; Branch: <?= htmlspecialchars($bill['branch_name']) ?></small>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= BASE_URL ?>repairs/index.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i> Back to Repairs
        </a>
        <button onclick="window.print()" class="btn btn-primary btn-sm">
            <i class="bi bi-printer me-1"></i> Print Repair Receipt
        </button>
    </div>
</div>

<div class="card p-4 mx-auto" style="max-width: 650px; background: #fff;">
    <div class="text-center border-bottom pb-3 mb-3">
        <h4 class="fw-bold text-primary mb-1"><?= htmlspecialchars(getSetting('business_name', 'CellPro Mobile World')) ?></h4>
        <div class="fw-semibold text-dark"><?= htmlspecialchars($bill['branch_name']) ?></div>
        <div class="text-muted small"><?= htmlspecialchars($bill['branch_address']) ?> | Tel: <?= htmlspecialchars($bill['branch_phone']) ?></div>
        <h6 class="fw-bold text-uppercase mt-2 tracking-wide text-dark">Mobile Repairing Service Bill</h6>
    </div>

    <div class="row g-2 mb-3 small">
        <div class="col-6">
            <div>Bill No: <strong><?= htmlspecialchars($bill['bill_no']) ?></strong></div>
            <div>Date: <?= formatDate($bill['repair_date']) ?></div>
            <div>Customer: <strong><?= htmlspecialchars($bill['customer_name']) ?></strong></div>
            <div>Mobile: <?= htmlspecialchars($bill['customer_phone']) ?></div>
        </div>
        <div class="col-6 text-end">
            <div>Device: <strong><?= htmlspecialchars($bill['mobile_brand']) ?> <?= htmlspecialchars($bill['mobile_model']) ?></strong></div>
            <?php if ($bill['imei']): ?>
                <div class="font-monospace">IMEI: <?= htmlspecialchars($bill['imei']) ?></div>
            <?php endif; ?>
            <div>Status: <span class="badge bg-success text-uppercase"><?= htmlspecialchars($bill['status']) ?></span></div>
        </div>
    </div>

    <table class="table table-bordered table-sm mb-3">
        <thead class="table-light small">
            <tr>
                <th>Description</th>
                <th class="text-center" style="width: 60px;">Qty</th>
                <th class="text-end" style="width: 100px;">Rate</th>
                <th class="text-end" style="width: 100px;">Amount</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($items as $item): ?>
                <tr>
                    <td>
                        <div class="fw-semibold"><?= htmlspecialchars($item['description']) ?></div>
                        <span class="badge bg-light text-dark border" style="font-size:0.65rem;"><?= ucfirst(str_replace('_', ' ', $item['item_type'])) ?></span>
                    </td>
                    <td class="text-center"><?= $item['quantity'] ?></td>
                    <td class="text-end"><?= number_format($item['unit_price'], 2) ?></td>
                    <td class="text-end fw-bold"><?= number_format($item['total_price'], 2) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <?php if ($bill['discount'] > 0): ?>
                <tr>
                    <td colspan="3" class="text-end text-muted small">Discount:</td>
                    <td class="text-end text-danger small">-Rs. <?= number_format($bill['discount'], 2) ?></td>
                </tr>
            <?php endif; ?>
            <tr>
                <td colspan="3" class="text-end fw-bold">Total Repair Bill:</td>
                <td class="text-end fw-bold text-primary fs-6">Rs. <?= number_format($bill['total_amount'], 2) ?></td>
            </tr>
            <tr>
                <td colspan="3" class="text-end text-success fw-semibold small">Paid Amount:</td>
                <td class="text-end text-success fw-semibold small">Rs. <?= number_format($bill['paid_amount'], 2) ?></td>
            </tr>
            <?php if ($bill['remaining_amount'] > 0): ?>
                <tr class="text-danger fw-bold">
                    <td colspan="3" class="text-end">Balance Due:</td>
                    <td class="text-end">Rs. <?= number_format($bill['remaining_amount'], 2) ?></td>
                </tr>
            <?php endif; ?>
        </tfoot>
    </table>

    <div class="text-muted small border-top pt-2 text-center" style="font-size: 11px;">
        Checking warranty for replaced touch/screens valid for 3 days only. No warranty for liquid/physical damage or board IC work.
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
