<?php
// pos/invoice.php - Printable Sales Invoice (Thermal 80mm & A4 Modern)
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();

$sale_id = (int)($_GET['id'] ?? 0);
$format = $_GET['format'] ?? 'thermal'; // 'thermal' or 'a4'

$stmt = $pdo->prepare("SELECT s.*, c.name as customer_name, c.phone as customer_phone, c.address as customer_address,
                              b.name as branch_name, b.address as branch_address, b.phone as branch_phone,
                              u.name as cashier_name
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

// Fetch Items
$stmt = $pdo->prepare("SELECT * FROM sale_items WHERE sale_id = ?");
$stmt->execute([$sale_id]);
$items = $stmt->fetchAll();

// Fetch Traded Device if any
$trade_device = null;
if ($sale['trade_in_device_id']) {
    $stmt = $pdo->prepare("SELECT * FROM mobile_devices WHERE id = ?");
    $stmt->execute([$sale['trade_in_device_id']]);
    $trade_device = $stmt->fetch();
}

$business_name = getSetting('business_name', 'CellPro Mobile World');
$footer_note = getSetting('invoice_footer_note', 'Thank you for your business!');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice <?= htmlspecialchars($sale['invoice_no']) ?> - <?= htmlspecialchars($business_name) ?></title>
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/vendor/bootstrap/bootstrap.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/vendor/bootstrap-icons/bootstrap-icons.css">
    <style>
        body {
            background-color: #f1f5f9;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, monospace, sans-serif;
            color: #000;
        }

        /* Thermal 80mm Layout */
        .thermal-invoice {
            width: 80mm;
            max-width: 80mm;
            margin: 20px auto;
            background: #fff;
            padding: 15px;
            font-size: 12px;
            border: 1px dashed #cbd5e1;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
        }

        /* A4 Layout */
        .a4-invoice {
            width: 210mm;
            min-height: 297mm;
            margin: 20px auto;
            background: #fff;
            padding: 40px;
            font-size: 14px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
        }

        .border-top-dashed {
            border-top: 1px dashed #000;
        }

        .border-bottom-dashed {
            border-bottom: 1px dashed #000;
        }

        @media print {
            body {
                background: #fff !important;
                margin: 0 !important;
                padding: 0 !important;
            }
            .no-print {
                display: none !important;
            }
            .thermal-invoice {
                width: 100% !important;
                max-width: 80mm !important;
                margin: 0 !important;
                padding: 5px !important;
                border: none !important;
                box-shadow: none !important;
            }
            .a4-invoice {
                width: 100% !important;
                margin: 0 !important;
                padding: 20px !important;
                border: none !important;
                box-shadow: none !important;
            }
        }
    </style>
</head>
<body>

<!-- Control Bar (hidden during printing) -->
<div class="container py-3 no-print text-center">
    <div class="d-inline-flex gap-2 bg-white p-2 rounded-3 shadow-sm border">
        <button onclick="window.print()" class="btn btn-primary btn-sm px-3">
            <i class="bi bi-printer me-1"></i> Print Invoice
        </button>
        <a href="<?= BASE_URL ?>pos/invoice.php?id=<?= $sale['id'] ?>&format=<?= $format === 'thermal' ? 'a4' : 'thermal' ?>" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-layout-text-window-reverse me-1"></i> Switch to <?= $format === 'thermal' ? 'A4 Invoice' : 'Thermal (80mm)' ?>
        </a>
        <a href="<?= BASE_URL ?>pos/index.php" class="btn btn-outline-primary btn-sm">
            <i class="bi bi-cart4 me-1"></i> Back to POS
        </a>
    </div>
</div>

<?php if ($format === 'thermal'): ?>
    <!-- ================= THERMAL RECEIPT (80mm) ================= -->
    <div class="thermal-invoice">
        <div class="text-center mb-2">
            <h5 class="fw-bold mb-0"><?= htmlspecialchars($business_name) ?></h5>
            <div class="small fw-semibold"><?= htmlspecialchars($sale['branch_name']) ?></div>
            <div class="small text-muted"><?= htmlspecialchars($sale['branch_address']) ?></div>
            <div class="small text-muted">Phone: <?= htmlspecialchars($sale['branch_phone']) ?></div>
        </div>

        <div class="border-top-dashed border-bottom-dashed py-1 mb-2 small">
            <div class="d-flex justify-content-between">
                <span>Inv: <strong><?= htmlspecialchars($sale['invoice_no']) ?></strong></span>
                <span><?= date('d-m-y h:i A', strtotime($sale['created_at'])) ?></span>
            </div>
            <div class="d-flex justify-content-between">
                <span>Customer: <?= htmlspecialchars($sale['customer_name'] ?? 'Walk-in') ?></span>
                <span><?= htmlspecialchars($sale['customer_phone'] ?? '') ?></span>
            </div>
            <div>Cashier: <?= htmlspecialchars($sale['cashier_name'] ?? 'Staff') ?></div>
        </div>

        <table class="w-100 mb-2 small">
            <thead>
                <tr class="border-bottom-dashed">
                    <th class="text-start pb-1">Item / IMEI</th>
                    <th class="text-center pb-1">Qty</th>
                    <th class="text-end pb-1">Total</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $item): ?>
                    <tr>
                        <td class="pt-1">
                            <div class="fw-semibold"><?= htmlspecialchars($item['item_name']) ?></div>
                            <?php if ($item['imei']): ?>
                                <div class="font-monospace" style="font-size: 10px;">IMEI: <?= htmlspecialchars($item['imei']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td class="text-center pt-1"><?= $item['quantity'] ?></td>
                        <td class="text-end pt-1 fw-bold"><?= number_format($item['total_price']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="border-top-dashed pt-1 small">
            <div class="d-flex justify-content-between">
                <span>Subtotal:</span>
                <span>Rs. <?= number_format($sale['subtotal'], 2) ?></span>
            </div>
            <?php if ($sale['invoice_discount'] > 0): ?>
                <div class="d-flex justify-content-between">
                    <span>Discount:</span>
                    <span>-Rs. <?= number_format($sale['invoice_discount'], 2) ?></span>
                </div>
            <?php endif; ?>
            <?php if ($sale['tax_amount'] > 0): ?>
                <div class="d-flex justify-content-between">
                    <span>Tax:</span>
                    <span>Rs. <?= number_format($sale['tax_amount'], 2) ?></span>
                </div>
            <?php endif; ?>
            <?php if ($trade_device): ?>
                <div class="d-flex justify-content-between fw-semibold text-danger">
                    <span>Trade-In (<?= htmlspecialchars($trade_device['model_name']) ?>):</span>
                    <span>-Rs. <?= number_format($sale['trade_in_value'], 2) ?></span>
                </div>
            <?php endif; ?>
            <div class="d-flex justify-content-between fw-bold border-top-dashed pt-1 my-1" style="font-size: 14px;">
                <span>Net Payable:</span>
                <span>Rs. <?= number_format($sale['net_payable'], 2) ?></span>
            </div>
            <div class="d-flex justify-content-between">
                <span>Paid (<?= htmlspecialchars($sale['payment_method']) ?>):</span>
                <span>Rs. <?= number_format($sale['paid_amount'], 2) ?></span>
            </div>
            <?php if ($sale['remaining_amount'] > 0): ?>
                <div class="d-flex justify-content-between fw-bold text-danger">
                    <span>Balance Due:</span>
                    <span>Rs. <?= number_format($sale['remaining_amount'], 2) ?></span>
                </div>
            <?php endif; ?>
        </div>

        <div class="text-center border-top-dashed mt-3 pt-2 small text-muted" style="font-size: 10px;">
            <div><?= nl2br(htmlspecialchars($footer_note)) ?></div>
            <div class="mt-1">Powered by CellPro ERP</div>
        </div>
    </div>

<?php else: ?>
    <!-- ================= FULL A4 INVOICE ================= -->
    <div class="a4-invoice">
        <div class="d-flex justify-content-between align-items-start border-bottom pb-4 mb-4">
            <div>
                <h3 class="fw-bold text-primary mb-1"><?= htmlspecialchars($business_name) ?></h3>
                <div class="fw-semibold text-dark"><?= htmlspecialchars($sale['branch_name']) ?></div>
                <div class="text-muted small"><?= htmlspecialchars($sale['branch_address']) ?></div>
                <div class="text-muted small">Phone: <?= htmlspecialchars($sale['branch_phone']) ?></div>
            </div>
            <div class="text-end">
                <h4 class="fw-bold mb-1">INVOICE</h4>
                <div class="fw-semibold text-dark">#<?= htmlspecialchars($sale['invoice_no']) ?></div>
                <div class="text-muted small">Date: <?= date('d M Y, h:i A', strtotime($sale['created_at'])) ?></div>
                <div class="badge bg-<?= $sale['payment_status'] === 'paid' ? 'success' : 'warning' ?> text-uppercase mt-1">
                    <?= htmlspecialchars($sale['payment_status']) ?>
                </div>
            </div>
        </div>

        <div class="row mb-4">
            <div class="col-6">
                <small class="text-muted text-uppercase fw-bold">Billed To:</small>
                <h6 class="fw-bold mb-1"><?= htmlspecialchars($sale['customer_name'] ?? 'Walk-in Customer') ?></h6>
                <?php if ($sale['customer_phone']): ?>
                    <div>Phone: <?= htmlspecialchars($sale['customer_phone']) ?></div>
                <?php endif; ?>
                <?php if ($sale['customer_address']): ?>
                    <div>Address: <?= htmlspecialchars($sale['customer_address']) ?></div>
                <?php endif; ?>
            </div>
            <div class="col-6 text-end">
                <small class="text-muted text-uppercase fw-bold">Payment Details:</small>
                <div>Method: <strong><?= htmlspecialchars($sale['payment_method']) ?></strong></div>
                <div>Issued By: <?= htmlspecialchars($sale['cashier_name'] ?? 'System') ?></div>
            </div>
        </div>

        <table class="table table-bordered mb-4">
            <thead class="table-light">
                <tr>
                    <th style="width: 50px;">#</th>
                    <th>Item Description & Device Specs</th>
                    <th class="text-center" style="width: 80px;">Qty</th>
                    <th class="text-end" style="width: 120px;">Unit Price</th>
                    <th class="text-end" style="width: 120px;">Total</th>
                </tr>
            </thead>
            <tbody>
                <?php $i = 1; foreach ($items as $item): ?>
                    <tr>
                        <td><?= $i++ ?></td>
                        <td>
                            <div class="fw-bold"><?= htmlspecialchars($item['item_name']) ?></div>
                            <?php if ($item['imei']): ?>
                                <small class="text-muted font-monospace">IMEI: <?= htmlspecialchars($item['imei']) ?></small>
                            <?php endif; ?>
                        </td>
                        <td class="text-center"><?= $item['quantity'] ?></td>
                        <td class="text-end"><?= number_format($item['unit_price'], 2) ?></td>
                        <td class="text-end fw-bold"><?= number_format($item['total_price'], 2) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="row">
            <div class="col-7">
                <?php if ($trade_device): ?>
                    <div class="card p-3 bg-light border-0 mb-3">
                        <h6 class="fw-bold text-info"><i class="bi bi-arrow-repeat me-1"></i> Mobile Exchange / Trade-In Details</h6>
                        <div class="small">
                            <div><strong>Device:</strong> <?= htmlspecialchars($trade_device['model_name']) ?> (<?= $trade_device['ram'] ?>/<?= $trade_device['storage'] ?>)</div>
                            <div><strong>IMEI 1:</strong> <span class="font-monospace"><?= htmlspecialchars($trade_device['imei_1']) ?></span></div>
                            <div><strong>Agreed Trade-in Credit:</strong> Rs. <?= number_format($sale['trade_in_value'], 2) ?></div>
                        </div>
                    </div>
                <?php endif; ?>
                <div class="small text-muted mt-3">
                    <strong>Warranty & Terms:</strong><br>
                    <?= nl2br(htmlspecialchars($footer_note)) ?>
                </div>
            </div>
            <div class="col-5">
                <table class="table table-sm table-borderless">
                    <tr>
                        <td class="text-muted">Subtotal:</td>
                        <td class="text-end fw-semibold">Rs. <?= number_format($sale['subtotal'], 2) ?></td>
                    </tr>
                    <?php if ($sale['invoice_discount'] > 0): ?>
                        <tr>
                            <td class="text-muted">Discount:</td>
                            <td class="text-end text-danger">-Rs. <?= number_format($sale['invoice_discount'], 2) ?></td>
                        </tr>
                    <?php endif; ?>
                    <?php if ($sale['tax_amount'] > 0): ?>
                        <tr>
                            <td class="text-muted">Sales Tax:</td>
                            <td class="text-end">Rs. <?= number_format($sale['tax_amount'], 2) ?></td>
                        </tr>
                    <?php endif; ?>
                    <?php if ($sale['trade_in_value'] > 0): ?>
                        <tr class="text-info fw-bold">
                            <td>Trade-In Credit:</td>
                            <td class="text-end">-Rs. <?= number_format($sale['trade_in_value'], 2) ?></td>
                        </tr>
                    <?php endif; ?>
                    <tr class="border-top border-2">
                        <td class="h5 fw-bold">Net Total:</td>
                        <td class="h5 fw-bold text-end text-primary">Rs. <?= number_format($sale['net_payable'], 2) ?></td>
                    </tr>
                    <tr>
                        <td class="text-muted">Paid Amount:</td>
                        <td class="text-end fw-semibold">Rs. <?= number_format($sale['paid_amount'], 2) ?></td>
                    </tr>
                    <?php if ($sale['remaining_amount'] > 0): ?>
                        <tr class="text-danger fw-bold">
                            <td>Outstanding Due:</td>
                            <td class="text-end">Rs. <?= number_format($sale['remaining_amount'], 2) ?></td>
                        </tr>
                    <?php endif; ?>
                </table>
            </div>
        </div>

        <div class="mt-5 pt-5 border-top d-flex justify-content-between text-muted small">
            <div>Authorized Signature: ______________________</div>
            <div>Customer Acceptance: ______________________</div>
        </div>
    </div>
<?php endif; ?>

</body>
</html>
