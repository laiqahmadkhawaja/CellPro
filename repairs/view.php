<?php
// repairs/view.php - 80mm Thermal Mobile Repair Service Receipt & Payment Collection
$page_title = 'Repair Receipt (80mm Thermal)';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/accounting.php';

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

$branch_id = $bill['branch_id'];
$financial_accounts = getFinancialAccounts($pdo, $branch_id);

// Handle Collecting Remaining Balance Payment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'collect_repair_payment') {
    validatePostCsrf();
    $amount = (float)($_POST['amount'] ?? 0);
    $account_id = (int)($_POST['payment_account_id'] ?? 0);
    $pay_date = sanitize($_POST['payment_date'] ?? date('Y-m-d'));
    $method = sanitize($_POST['payment_method'] ?? 'Cash');
    $notes = sanitize($_POST['notes'] ?? 'Remaining repair balance payment');
    $mark_delivered = !empty($_POST['mark_delivered']) ? 1 : 0;

    if ($amount <= 0 || $account_id <= 0) {
        flash('danger', 'Please enter a valid payment amount and select a deposit account.');
        redirect(BASE_URL . 'repairs/view.php?id=' . $bill_id);
    }

    try {
        $pdo->beginTransaction();

        $bStmt = $pdo->prepare("SELECT * FROM repairing_bills WHERE id = ? FOR UPDATE");
        $bStmt->execute([$bill_id]);
        $latest_bill = $bStmt->fetch();

        if ($amount > (float)$latest_bill['remaining_amount']) {
            throw new Exception("Payment amount exceeds outstanding balance (" . formatCurrency($latest_bill['remaining_amount']) . ").");
        }

        $new_paid = (float)$latest_bill['paid_amount'] + $amount;
        $new_remaining = max(0, (float)$latest_bill['total_amount'] - $new_paid);
        $new_status = ($mark_delivered || $new_remaining <= 0) ? 'delivered' : $latest_bill['status'];

        // 1. Update Repair Bill
        $pdo->prepare("UPDATE repairing_bills SET paid_amount = ?, remaining_amount = ?, status = ? WHERE id = ?")
            ->execute([$new_paid, $new_remaining, $new_status, $bill_id]);

        // 2. Insert into repair_payments
        $pdo->prepare("INSERT INTO repair_payments (bill_id, branch_id, payment_date, account_id, amount, payment_method, notes, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)")
            ->execute([$bill_id, $latest_bill['branch_id'], $pay_date, $account_id, $amount, $method, $notes, $_SESSION['user_id']]);

        // 3. Deposit into Financial Account
        $pdo->prepare("UPDATE financial_accounts SET current_balance = current_balance + ? WHERE id = ?")
            ->execute([$amount, $account_id]);

        // 4. Update customer balance if customer was linked
        if (!empty($latest_bill['customer_id'])) {
            $pdo->prepare("UPDATE customers SET current_balance = current_balance - ? WHERE id = ?")
                ->execute([$amount, $latest_bill['customer_id']]);
        }

        // 5. Double-Entry Accounting Journal Entry
        $cash_acct = getFinancialLedgerAccountId($pdo, $account_id);
        $recv_acct = getAccountIdByCode($pdo, '1003'); // Receivables

        $j_lines = [
            [
                'account_id' => $cash_acct,
                'debit' => $amount,
                'credit' => 0.00,
                'description' => "Repair fee collected on Bill #{$latest_bill['bill_no']} - {$notes}"
            ],
            [
                'account_id' => $recv_acct,
                'debit' => 0.00,
                'credit' => $amount,
                'description' => "Receivables settled on Repair Bill #{$latest_bill['bill_no']}"
            ]
        ];

        postJournalEntry($pdo, $latest_bill['branch_id'], 'repair_payment', $bill_id, $j_lines, $pay_date, "Repair Payment on {$latest_bill['bill_no']}");

        logAudit($pdo, 'payment', 'repairing_bills', $bill_id, "Collected remaining repair payment " . formatCurrency($amount) . " for Bill #{$latest_bill['bill_no']}");

        $pdo->commit();
        flash('success', "Payment of " . formatCurrency($amount) . " collected successfully!");
        redirect(BASE_URL . 'repairs/view.php?id=' . $bill_id);

    } catch (Exception $e) {
        $pdo->rollBack();
        flash('danger', 'Error recording payment: ' . $e->getMessage());
        redirect(BASE_URL . 'repairs/view.php?id=' . $bill_id);
    }
}

// Items / Services
$stmt = $pdo->prepare("SELECT * FROM repairing_bill_items WHERE bill_id = ?");
$stmt->execute([$bill_id]);
$items = $stmt->fetchAll();

// Payments History
$stmt = $pdo->prepare("SELECT rp.*, fa.name as account_name FROM repair_payments rp LEFT JOIN financial_accounts fa ON rp.account_id = fa.id WHERE rp.bill_id = ? ORDER BY rp.id ASC");
$stmt->execute([$bill_id]);
$payments = $stmt->fetchAll();

$business_name = getSetting('business_name', 'CellPro Mobile World');
?>

<style>
    /* Thermal 80mm Receipt Styles */
    .thermal-receipt {
        width: 80mm;
        max-width: 80mm;
        margin: 15px auto;
        background: #ffffff;
        padding: 14px 12px;
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Courier New", monospace, sans-serif;
        font-size: 12px;
        color: #000000;
        border: 1px dashed #94a3b8;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.08);
        box-sizing: border-box;
    }
    .thermal-receipt .border-top-dashed {
        border-top: 1px dashed #000000;
    }
    .thermal-receipt .border-bottom-dashed {
        border-bottom: 1px dashed #000000;
    }
    .thermal-receipt .receipt-title {
        font-size: 15px;
        font-weight: 800;
        letter-spacing: -0.3px;
        line-height: 1.1;
    }
    .thermal-receipt table {
        width: 100%;
        border-collapse: collapse;
        font-size: 11px;
    }
    .thermal-receipt table th, 
    .thermal-receipt table td {
        padding: 3px 0;
        vertical-align: top;
    }

    @media print {
        @page {
            size: 80mm auto;
            margin: 0;
        }
        html, body, #app-wrapper, #main-content, .page-container {
            width: 80mm !important;
            max-width: 80mm !important;
            margin: 0 auto !important;
            padding: 0 !important;
            background: #ffffff !important;
            overflow: visible !important;
        }
        #sidebar, .top-navbar, footer, .no-print, .btn, .flash-messages-container {
            display: none !important;
        }
        .thermal-receipt {
            width: 80mm !important;
            max-width: 80mm !important;
            margin: 0 auto !important;
            padding: 3mm 2mm !important;
            border: none !important;
            box-shadow: none !important;
            font-size: 11px !important;
            color: #000000 !important;
        }
        .thermal-receipt * {
            color: #000000 !important;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }
    }
</style>

<!-- Control Bar (hidden during printing) -->
<div class="d-flex justify-content-between align-items-center mb-3 no-print mx-auto" style="max-width: 80mm;">
    <div>
        <h6 class="fw-bold m-0"><i class="bi bi-printer text-primary me-1"></i>Thermal 80mm</h6>
        <small class="text-muted">Bill #<?= htmlspecialchars($bill['bill_no']) ?></small>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= BASE_URL ?>repairs/index.php" class="btn btn-outline-secondary btn-sm" title="Back">
            <i class="bi bi-arrow-left"></i>
        </a>
        <?php if ($bill['remaining_amount'] > 0): ?>
            <button type="button" class="btn btn-success btn-sm shadow-sm" data-toggle-panel="panel-collect-repair-pay">
                <i class="bi bi-cash-stack me-1"></i> Pay Balance
            </button>
        <?php endif; ?>
        <button onclick="window.print()" class="btn btn-primary btn-sm px-3 shadow-sm">
            <i class="bi bi-printer me-1"></i> Print
        </button>
    </div>
</div>

<!-- ================= THERMAL RECEIPT (80mm) ================= -->
<div class="thermal-receipt">
    <!-- Store Header -->
    <div class="text-center mb-2">
        <div class="receipt-title fw-bold"><?= htmlspecialchars($business_name) ?></div>
        <div class="small fw-semibold mt-1"><?= htmlspecialchars($bill['branch_name']) ?></div>
        <div style="font-size: 10px;" class="text-muted"><?= htmlspecialchars($bill['branch_address']) ?></div>
        <?php if (!empty($bill['branch_phone'])): ?>
            <div style="font-size: 10px;" class="text-muted">Tel: <?= htmlspecialchars($bill['branch_phone']) ?></div>
        <?php endif; ?>
        <div class="fw-bold text-uppercase mt-1" style="font-size: 11px; letter-spacing: 0.5px;">Mobile Repairing Receipt</div>
    </div>

    <!-- Metadata Section -->
    <div class="border-top-dashed border-bottom-dashed py-1 mb-2" style="font-size: 11px;">
        <div class="d-flex justify-content-between">
            <span>Bill No: <strong><?= htmlspecialchars($bill['bill_no']) ?></strong></span>
            <span><?= date('d-M-y h:i A', strtotime($bill['created_at'] ?? $bill['repair_date'])) ?></span>
        </div>
        <div class="d-flex justify-content-between">
            <span>Customer: <strong><?= htmlspecialchars($bill['customer_name']) ?></strong></span>
            <span><?= htmlspecialchars($bill['customer_phone']) ?></span>
        </div>
        <div class="d-flex justify-content-between">
            <span>Device: <strong><?= htmlspecialchars($bill['mobile_brand'] . ' ' . $bill['mobile_model']) ?></strong></span>
            <span>Status: <strong class="text-uppercase"><?= htmlspecialchars($bill['status']) ?></strong></span>
        </div>
        <?php if (!empty($bill['imei'])): ?>
            <div>IMEI: <span class="font-monospace"><?= htmlspecialchars($bill['imei']) ?></span></div>
        <?php endif; ?>
        <?php if (!empty($bill['technician_name'])): ?>
            <div>Technician: <?= htmlspecialchars($bill['technician_name']) ?></div>
        <?php endif; ?>
    </div>

    <!-- Line Items Table -->
    <table class="mb-2">
        <thead>
            <tr class="border-bottom-dashed">
                <th class="text-start pb-1">Service / Replaced Part</th>
                <th class="text-center pb-1" style="width: 35px;">Qty</th>
                <th class="text-end pb-1" style="width: 55px;">Rate</th>
                <th class="text-end pb-1" style="width: 60px;">Total</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($items as $item): ?>
                <tr>
                    <td class="pt-1">
                        <div class="fw-semibold"><?= htmlspecialchars($item['description']) ?></div>
                        <span style="font-size: 9px;" class="text-muted">(<?= ucfirst(str_replace('_', ' ', $item['item_type'])) ?>)</span>
                    </td>
                    <td class="text-center pt-1"><?= $item['quantity'] ?></td>
                    <td class="text-end pt-1"><?= number_format($item['unit_price']) ?></td>
                    <td class="text-end pt-1 fw-bold"><?= number_format($item['total_price']) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <!-- Totals & Accounting -->
    <div class="border-top-dashed pt-1" style="font-size: 11px;">
        <div class="d-flex justify-content-between">
            <span>Subtotal:</span>
            <span>Rs. <?= number_format($bill['total_amount'] + $bill['discount'], 2) ?></span>
        </div>
        <?php if ($bill['discount'] > 0): ?>
            <div class="d-flex justify-content-between">
                <span>Discount:</span>
                <span>-Rs. <?= number_format($bill['discount'], 2) ?></span>
            </div>
        <?php endif; ?>
        <div class="d-flex justify-content-between fw-bold border-top-dashed pt-1 my-1" style="font-size: 13px;">
            <span>Total Bill:</span>
            <span>Rs. <?= number_format($bill['total_amount'], 2) ?></span>
        </div>
        <div class="d-flex justify-content-between">
            <span>Paid Amount:</span>
            <span class="text-success fw-bold">Rs. <?= number_format($bill['paid_amount'], 2) ?></span>
        </div>
        <?php if ($bill['remaining_amount'] > 0): ?>
            <div class="d-flex justify-content-between fw-bold border-top-dashed pt-1 mt-1 text-danger" style="font-size: 12px;">
                <span>Balance Due:</span>
                <span>Rs. <?= number_format($bill['remaining_amount'], 2) ?></span>
            </div>
        <?php else: ?>
            <div class="d-flex justify-content-between fw-bold border-top-dashed pt-1 mt-1 text-success" style="font-size: 11px;">
                <span>Payment Status:</span>
                <span>FULL PAID</span>
            </div>
        <?php endif; ?>
    </div>

    <!-- Payments Breakdown History -->
    <?php if (!empty($payments)): ?>
        <div class="border-top-dashed mt-2 pt-1" style="font-size: 10px;">
            <div class="fw-bold mb-1">Payment Transactions:</div>
            <?php foreach ($payments as $p): ?>
                <div class="d-flex justify-content-between text-muted">
                    <span><?= date('d-M h:i A', strtotime($p['payment_date'] ?? $p['created_at'])) ?> (<?= htmlspecialchars($p['payment_method']) ?>)</span>
                    <span class="fw-bold text-dark">Rs. <?= number_format($p['amount']) ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- Signatures -->
    <div class="d-flex justify-content-between border-top-dashed mt-3 pt-2" style="font-size: 10px;">
        <div>Tech: ____________</div>
        <div>Customer: ____________</div>
    </div>

    <!-- Terms & Footer -->
    <div class="text-center border-top-dashed mt-2 pt-2 text-muted" style="font-size: 9px; line-height: 1.3;">
        <div>Warranty on touch/LCD replacement is 3 days only. No warranty for liquid damage, drop damage, or board IC repair.</div>
        <div class="mt-1 fw-semibold">Thank you for visiting <?= htmlspecialchars($business_name) ?>!</div>
        <div style="font-size: 8px;">Powered by CellPro ERP</div>
    </div>
</div>

<!-- SIDE-PANEL DRAWER: COLLECT REMAINING PAYMENT (Zero-Modal Compliance) -->
<?php if ($bill['remaining_amount'] > 0): ?>
<div id="panel-collect-repair-pay" class="side-panel">
    <div class="side-panel-header">
        <h6 class="m-0 fw-bold"><i class="bi bi-cash-stack me-2 text-success"></i>Collect Remaining Payment</h6>
        <button type="button" class="btn-close" data-close-panel></button>
    </div>
    <div class="side-panel-body">
        <div class="alert alert-warning py-2 small mb-3">
            <div>Bill No: <strong><?= htmlspecialchars($bill['bill_no']) ?></strong></div>
            <div>Customer: <strong><?= htmlspecialchars($bill['customer_name']) ?></strong> (<?= htmlspecialchars($bill['customer_phone']) ?>)</div>
            <div>Device: <strong><?= htmlspecialchars($bill['mobile_brand'] . ' ' . $bill['mobile_model']) ?></strong></div>
            <div class="mt-1 pt-1 border-top border-warning border-opacity-50">
                Outstanding Balance Due: <span class="h5 fw-bold text-danger m-0">Rs. <?= number_format($bill['remaining_amount'], 2) ?></span>
            </div>
        </div>

        <form method="POST" action="">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="collect_repair_payment">

            <div class="mb-3">
                <label class="form-label small fw-semibold">Amount Paid (Rs.) *</label>
                <input type="number" step="0.01" name="amount" class="form-control form-control-lg fw-bold text-success" value="<?= $bill['remaining_amount'] ?>" max="<?= $bill['remaining_amount'] ?>" required min="1">
            </div>

            <div class="mb-3">
                <label class="form-label small fw-semibold">Payment Received Into Account *</label>
                <select name="payment_account_id" class="form-select" required>
                    <?php foreach ($financial_accounts as $fa): ?>
                        <option value="<?= $fa['id'] ?>"><?= htmlspecialchars($fa['name']) ?> (<?= ucfirst($fa['type']) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="row g-2 mb-3">
                <div class="col-6">
                    <label class="form-label small fw-semibold">Payment Date</label>
                    <input type="date" name="payment_date" class="form-control form-control-sm" value="<?= date('Y-m-d') ?>">
                </div>
                <div class="col-6">
                    <label class="form-label small fw-semibold">Payment Method</label>
                    <select name="payment_method" class="form-select form-select-sm">
                        <option value="Cash">Cash</option>
                        <option value="Bank Transfer">Bank Transfer</option>
                        <option value="JazzCash">JazzCash</option>
                        <option value="EasyPaisa">EasyPaisa</option>
                        <option value="Card">Card</option>
                    </select>
                </div>
            </div>

            <div class="form-check form-switch mb-3">
                <input class="form-check-input" type="checkbox" name="mark_delivered" id="mark_delivered_view" value="1" checked>
                <label class="form-check-label small fw-semibold" for="mark_delivered_view">Mark Repair Status as "Delivered"</label>
            </div>

            <div class="mb-4">
                <label class="form-label small fw-semibold">Remarks / Slip Reference</label>
                <input type="text" name="notes" class="form-control form-control-sm" placeholder="e.g. Cleared full balance at phone pickup">
            </div>

            <div class="side-panel-footer px-0 pb-0">
                <button type="button" class="btn btn-light" data-close-panel>Cancel</button>
                <button type="submit" class="btn btn-success px-4">
                    <i class="bi bi-check2-circle me-1"></i> Record Payment
                </button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
