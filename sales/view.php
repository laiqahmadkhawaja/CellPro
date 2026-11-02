<?php
// sales/view.php - Sale Details & Realized Profit Analysis
$page_title = 'Sale Invoice Details';
require_once __DIR__ . '/../includes/header.php';

$sale_id = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare("SELECT s.*, c.name as customer_name, c.phone as customer_phone, c.address as customer_address,
                              b.name as branch_name, u.name as cashier_name
                       FROM sales s
                       LEFT JOIN customers c ON s.customer_id = c.id
                       LEFT JOIN branches b ON s.branch_id = b.id
                       LEFT JOIN users u ON s.created_by = u.id
                       WHERE s.id = ?");
$stmt->execute([$sale_id]);
$sale = $stmt->fetch();

if (!$sale) {
    flash('danger', 'Invoice not found.');
    redirect(BASE_URL . 'sales/index.php');
}

// Items
$stmt = $pdo->prepare("SELECT si.*, md.model_name, md.imei_1, md.actual_cost as dev_cost 
                       FROM sale_items si
                       LEFT JOIN mobile_devices md ON si.device_id = md.id
                       WHERE si.sale_id = ?");
$stmt->execute([$sale_id]);
$items = $stmt->fetchAll();

// Payments
$stmt = $pdo->prepare("SELECT sp.*, fa.name as account_name 
                       FROM sale_payments sp
                       LEFT JOIN financial_accounts fa ON sp.account_id = fa.id
                       WHERE sp.sale_id = ?");
$stmt->execute([$sale_id]);
$payments = $stmt->fetchAll();

// Traded device
$trade_device = null;
if ($sale['trade_in_device_id']) {
    $stmt = $pdo->prepare("SELECT * FROM mobile_devices WHERE id = ?");
    $stmt->execute([$sale['trade_in_device_id']]);
    $trade_device = $stmt->fetch();
}
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h5 class="fw-bold m-0"><i class="bi bi-receipt me-2 text-primary"></i>Invoice #<?= htmlspecialchars($sale['invoice_no']) ?></h5>
        <small class="text-muted"><?= formatDate($sale['sale_date']) ?> &bull; Branch: <?= htmlspecialchars($sale['branch_name']) ?></small>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= BASE_URL ?>sales/index.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i> Back to Sales
        </a>
        <a href="<?= BASE_URL ?>pos/invoice.php?id=<?= $sale['id'] ?>&format=thermal" class="btn btn-outline-primary btn-sm" target="_blank">
            <i class="bi bi-printer me-1"></i> Thermal Receipt
        </a>
        <a href="<?= BASE_URL ?>pos/invoice.php?id=<?= $sale['id'] ?>&format=a4" class="btn btn-primary btn-sm" target="_blank">
            <i class="bi bi-file-earmark-text me-1"></i> A4 Invoice
        </a>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-12 col-md-6">
        <div class="card h-100">
            <div class="card-header bg-light">
                <span class="fw-bold"><i class="bi bi-person me-1 text-primary"></i> Customer Information</span>
            </div>
            <div class="card-body">
                <div class="fw-bold fs-6 text-dark"><?= htmlspecialchars($sale['customer_name'] ?? 'Walk-in Customer') ?></div>
                <div class="text-muted small">Phone: <?= htmlspecialchars($sale['customer_phone'] ?? 'N/A') ?></div>
                <?php if ($sale['customer_address']): ?>
                    <div class="text-muted small">Address: <?= htmlspecialchars($sale['customer_address']) ?></div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-12 col-md-6">
        <div class="card h-100">
            <div class="card-header bg-light">
                <span class="fw-bold"><i class="bi bi-info-circle me-1 text-primary"></i> Transaction Summary</span>
            </div>
            <div class="card-body">
                <div class="row g-2 small">
                    <div class="col-6 text-muted">Payment Status:</div>
                    <div class="col-6"><?= getStatusBadge($sale['payment_status']) ?></div>
                    <div class="col-6 text-muted">Cashier / Staff:</div>
                    <div class="col-6 fw-semibold"><?= htmlspecialchars($sale['cashier_name'] ?? 'System') ?></div>
                    <div class="col-6 text-muted">Payment Method:</div>
                    <div class="col-6"><?= htmlspecialchars($sale['payment_method']) ?></div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Itemized Profit Table -->
<div class="card mb-3">
    <div class="card-header bg-light">
        <span class="fw-bold"><i class="bi bi-graph-up-arrow me-1 text-success"></i> Items Sold & Realized Gross Profit Analysis</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-bordered align-middle mb-0">
                <thead class="table-light small">
                    <tr>
                        <th>#</th>
                        <th>Product / Device Specs</th>
                        <th>IMEI</th>
                        <th class="text-center">Qty</th>
                        <th class="text-end">Inventory Cost</th>
                        <th class="text-end">Selling Price</th>
                        <th class="text-end">Line Total</th>
                        <th class="text-end">Gross Profit</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $i = 1; 
                    $total_profit = 0; 
                    $total_cost = 0;
                    foreach ($items as $item): 
                        $total_profit += $item['gross_profit'];
                        $total_cost += ($item['unit_cost'] * $item['quantity']);
                    ?>
                        <tr>
                            <td><?= $i++ ?></td>
                            <td>
                                <div class="fw-bold text-dark"><?= htmlspecialchars($item['item_name']) ?></div>
                                <span class="badge bg-secondary text-uppercase" style="font-size:0.7rem;"><?= $item['item_type'] ?></span>
                            </td>
                            <td>
                                <?php if ($item['imei']): ?>
                                    <span class="font-monospace fw-semibold small">IMEI: <?= htmlspecialchars($item['imei']) ?></span>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center"><?= $item['quantity'] ?></td>
                            <td class="text-end text-muted"><?= formatCurrency($item['unit_cost']) ?></td>
                            <td class="text-end"><?= formatCurrency($item['unit_price']) ?></td>
                            <td class="text-end fw-semibold"><?= formatCurrency($item['total_price']) ?></td>
                            <td class="text-end fw-bold text-success">
                                <?= formatCurrency($item['gross_profit']) ?>
                                <small class="text-muted d-block" style="font-size:0.7rem;">
                                    (<?= $item['total_price'] > 0 ? round(($item['gross_profit'] / $item['total_price']) * 100, 1) : 0 ?>%)
                                </small>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot class="table-light">
                    <tr>
                        <td colspan="7" class="text-end fw-bold">Total Gross Profit Realized:</td>
                        <td class="text-end fw-bold text-success fs-6"><?= formatCurrency($total_profit) ?></td>
                    </tr>
                    <tr>
                        <td colspan="6" class="text-end text-muted">Subtotal:</td>
                        <td class="text-end fw-semibold"><?= formatCurrency($sale['subtotal']) ?></td>
                        <td></td>
                    </tr>
                    <?php if ($sale['invoice_discount'] > 0): ?>
                        <tr>
                            <td colspan="6" class="text-end text-danger">Invoice Discount:</td>
                            <td class="text-end text-danger">-<?= formatCurrency($sale['invoice_discount']) ?></td>
                            <td></td>
                        </tr>
                    <?php endif; ?>
                    <?php if ($sale['trade_in_value'] > 0): ?>
                        <tr class="text-info fw-bold">
                            <td colspan="6" class="text-end">Trade-in Device Credit (<?= htmlspecialchars($trade_device['model_name'] ?? 'Phone') ?>):</td>
                            <td class="text-end">-<?= formatCurrency($sale['trade_in_value']) ?></td>
                            <td></td>
                        </tr>
                    <?php endif; ?>
                    <tr>
                        <td colspan="6" class="text-end fw-bold">Net Total:</td>
                        <td class="text-end fw-bold text-primary fs-6"><?= formatCurrency($sale['net_payable']) ?></td>
                        <td></td>
                    </tr>
                    <tr>
                        <td colspan="6" class="text-end text-success fw-bold">Paid Amount:</td>
                        <td class="text-end text-success fw-bold"><?= formatCurrency($sale['paid_amount']) ?></td>
                        <td></td>
                    </tr>
                    <?php if ($sale['remaining_amount'] > 0): ?>
                        <tr class="text-danger fw-bold">
                            <td colspan="6" class="text-end">Customer Remaining Due:</td>
                            <td class="text-end"><?= formatCurrency($sale['remaining_amount']) ?></td>
                            <td></td>
                        </tr>
                    <?php endif; ?>
                </tfoot>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
