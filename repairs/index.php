<?php
// repairs/index.php - Simple Mobile Repairing Bills List & Payment Collection
$page_title = 'Mobile Repairing Bills';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/accounting.php';

$branch_id = getActiveBranchId();
$financial_accounts = getFinancialAccounts($pdo, $branch_id);

// Handle Collecting Remaining Balance Payment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'collect_repair_payment') {
    validatePostCsrf();
    $bill_id = (int)($_POST['bill_id'] ?? 0);
    $amount = (float)($_POST['amount'] ?? 0);
    $account_id = (int)($_POST['payment_account_id'] ?? 0);
    $pay_date = sanitize($_POST['payment_date'] ?? date('Y-m-d'));
    $method = sanitize($_POST['payment_method'] ?? 'Cash');
    $notes = sanitize($_POST['notes'] ?? 'Remaining repair balance payment');
    $mark_delivered = !empty($_POST['mark_delivered']) ? 1 : 0;

    if ($bill_id <= 0 || $amount <= 0 || $account_id <= 0) {
        flash('danger', 'Please enter a valid payment amount and select a deposit account.');
        redirect(BASE_URL . 'repairs/index.php');
    }

    try {
        $pdo->beginTransaction();

        $bStmt = $pdo->prepare("SELECT * FROM repairing_bills WHERE id = ? FOR UPDATE");
        $bStmt->execute([$bill_id]);
        $bill = $bStmt->fetch();

        if (!$bill) {
            throw new Exception("Repair bill not found.");
        }

        if ($amount > (float)$bill['remaining_amount']) {
            throw new Exception("Payment amount (" . formatCurrency($amount) . ") exceeds outstanding balance (" . formatCurrency($bill['remaining_amount']) . ").");
        }

        $new_paid = (float)$bill['paid_amount'] + $amount;
        $new_remaining = max(0, (float)$bill['total_amount'] - $new_paid);
        $new_status = ($mark_delivered || $new_remaining <= 0) ? 'delivered' : $bill['status'];

        // 1. Update Repair Bill
        $pdo->prepare("UPDATE repairing_bills SET paid_amount = ?, remaining_amount = ?, status = ? WHERE id = ?")
            ->execute([$new_paid, $new_remaining, $new_status, $bill_id]);

        // 2. Insert into repair_payments
        $pdo->prepare("INSERT INTO repair_payments (bill_id, branch_id, payment_date, account_id, amount, payment_method, notes, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)")
            ->execute([$bill_id, $bill['branch_id'], $pay_date, $account_id, $amount, $method, $notes, $_SESSION['user_id']]);

        // 3. Deposit into Financial Account
        $pdo->prepare("UPDATE financial_accounts SET current_balance = current_balance + ? WHERE id = ?")
            ->execute([$amount, $account_id]);

        // 4. Update customer balance if customer was linked
        if (!empty($bill['customer_id'])) {
            $pdo->prepare("UPDATE customers SET current_balance = current_balance - ? WHERE id = ?")
                ->execute([$amount, $bill['customer_id']]);
        }

        // 5. Double-Entry Accounting Journal Entry
        $cash_acct = getFinancialLedgerAccountId($pdo, $account_id);
        $recv_acct = getAccountIdByCode($pdo, '1003'); // Receivables

        $j_lines = [
            [
                'account_id' => $cash_acct,
                'debit' => $amount,
                'credit' => 0.00,
                'description' => "Repair fee collected on Bill #{$bill['bill_no']} - {$notes}"
            ],
            [
                'account_id' => $recv_acct,
                'debit' => 0.00,
                'credit' => $amount,
                'description' => "Receivables settled on Repair Bill #{$bill['bill_no']}"
            ]
        ];

        postJournalEntry($pdo, $bill['branch_id'], 'repair_payment', $bill_id, $j_lines, $pay_date, "Repair Payment on {$bill['bill_no']}");

        logAudit($pdo, 'payment', 'repairing_bills', $bill_id, "Collected remaining repair payment " . formatCurrency($amount) . " for Bill #{$bill['bill_no']}");

        $pdo->commit();
        flash('success', "Payment of " . formatCurrency($amount) . " collected successfully for Bill #{$bill['bill_no']}!");
        redirect(BASE_URL . 'repairs/index.php');

    } catch (Exception $e) {
        $pdo->rollBack();
        flash('danger', 'Error recording payment: ' . $e->getMessage());
        redirect(BASE_URL . 'repairs/index.php');
    }
}

$search = trim($_GET['search'] ?? '');

$sql = "SELECT rb.*, b.name as branch_name, u.name as technician_name 
        FROM repairing_bills rb
        LEFT JOIN branches b ON rb.branch_id = b.id
        LEFT JOIN users u ON rb.created_by = u.id
        WHERE 1=1";
$params = [];

if ($branch_id) {
    $sql .= " AND rb.branch_id = ?";
    $params[] = $branch_id;
}
if (!empty($search)) {
    $sql .= " AND (rb.bill_no LIKE ? OR rb.customer_name LIKE ? OR rb.customer_phone LIKE ? OR rb.mobile_model LIKE ? OR rb.imei LIKE ?)";
    $t = "%{$search}%";
    $params = array_merge($params, [$t, $t, $t, $t, $t]);
}

$sql .= " ORDER BY rb.id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$bills = $stmt->fetchAll();
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h5 class="fw-bold m-0"><i class="bi bi-tools text-warning me-2"></i>Mobile Repairing Bills</h5>
        <small class="text-muted">Simple service billing for labour, screen replacements, and repair spare parts</small>
    </div>
    <a href="<?= BASE_URL ?>repairs/create.php" class="btn btn-primary btn-sm shadow-sm">
        <i class="bi bi-plus-circle me-1"></i> New Repair Bill
    </a>
</div>

<!-- Search Filter -->
<div class="card mb-3 shadow-sm">
    <div class="card-body p-3">
        <form method="GET" class="row g-2">
            <div class="col-12 col-md-10">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                    <input type="text" name="search" class="form-control" placeholder="Search bill #, customer name, phone, model or IMEI..." value="<?= htmlspecialchars($search) ?>">
                </div>
            </div>
            <div class="col-12 col-md-2">
                <button type="submit" class="btn btn-sm btn-primary w-100">Search</button>
            </div>
        </form>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-body p-0">
        <?php if (empty($bills)): ?>
            <div class="text-center py-5 text-muted">
                <i class="bi bi-tools fs-1 mb-2 d-block opacity-50"></i>
                No repair bills found. Click <strong>New Repair Bill</strong> to record a repair service.
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light small">
                        <tr>
                            <th>Bill #</th>
                            <th>Date</th>
                            <th>Customer</th>
                            <th>Device Details</th>
                            <th class="text-end">Labour</th>
                            <th class="text-end">Parts</th>
                            <th class="text-end">Total Bill</th>
                            <th class="text-end">Paid</th>
                            <th class="text-end">Remaining Due</th>
                            <th>Status</th>
                            <th class="text-end" style="min-width: 140px;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($bills as $b): ?>
                            <tr>
                                <td class="fw-bold text-primary"><?= htmlspecialchars($b['bill_no']) ?></td>
                                <td><?= formatDate($b['repair_date']) ?></td>
                                <td>
                                    <div class="fw-semibold text-dark"><?= htmlspecialchars($b['customer_name']) ?></div>
                                    <small class="text-muted"><?= htmlspecialchars($b['customer_phone']) ?></small>
                                </td>
                                <td>
                                    <div class="fw-bold text-dark"><?= htmlspecialchars($b['mobile_brand']) ?> <?= htmlspecialchars($b['mobile_model']) ?></div>
                                    <?= $b['imei'] ? '<small class="text-muted font-monospace">IMEI: ' . htmlspecialchars($b['imei']) . '</small>' : '' ?>
                                </td>
                                <td class="text-end"><?= formatCurrency($b['labour_charges']) ?></td>
                                <td class="text-end"><?= formatCurrency($b['parts_charges']) ?></td>
                                <td class="text-end fw-bold text-dark"><?= formatCurrency($b['total_amount']) ?></td>
                                <td class="text-end text-success fw-semibold"><?= formatCurrency($b['paid_amount']) ?></td>
                                <td class="text-end">
                                    <?php if ($b['remaining_amount'] > 0): ?>
                                        <span class="badge bg-danger-subtle text-danger border border-danger fw-bold fs-7">
                                            <?= formatCurrency($b['remaining_amount']) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-success small fw-semibold"><i class="bi bi-check-circle me-1"></i>Cleared</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?= $b['status'] === 'delivered' ? '<span class="badge bg-success">Delivered</span>' : '<span class="badge bg-warning text-dark">Completed</span>' ?>
                                </td>
                                <td class="text-end">
                                    <div class="btn-group btn-group-sm">
                                        <?php if ($b['remaining_amount'] > 0): ?>
                                            <button type="button" class="btn btn-sm btn-success btn-pay-repair" 
                                                    data-id="<?= $b['id'] ?>"
                                                    data-bill="<?= htmlspecialchars($b['bill_no']) ?>"
                                                    data-customer="<?= htmlspecialchars($b['customer_name']) ?>"
                                                    data-phone="<?= htmlspecialchars($b['customer_phone']) ?>"
                                                    data-device="<?= htmlspecialchars($b['mobile_brand'] . ' ' . $b['mobile_model']) ?>"
                                                    data-remaining="<?= $b['remaining_amount'] ?>"
                                                    title="Collect Remaining Balance">
                                                <i class="bi bi-cash-stack me-1"></i> Pay Balance
                                            </button>
                                        <?php endif; ?>
                                        <a href="<?= BASE_URL ?>repairs/view.php?id=<?= $b['id'] ?>" class="btn btn-sm btn-outline-primary" title="View & Print 80mm Receipt">
                                            <i class="bi bi-printer me-1"></i> Bill
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- SIDE-PANEL DRAWER: COLLECT REMAINING PAYMENT (Zero-Modal Compliance) -->
<div id="panel-collect-repair-pay" class="side-panel">
    <div class="side-panel-header">
        <h6 class="m-0 fw-bold"><i class="bi bi-cash-stack me-2 text-success"></i>Collect Remaining Payment</h6>
        <button type="button" class="btn-close" data-close-panel></button>
    </div>
    <div class="side-panel-body">
        <div class="alert alert-warning py-2 small mb-3">
            <div>Bill No: <strong id="pay-bill-no"></strong></div>
            <div>Customer: <strong id="pay-customer"></strong> (<span id="pay-phone"></span>)</div>
            <div>Device: <strong id="pay-device"></strong></div>
            <div class="mt-1 pt-1 border-top border-warning border-opacity-50">
                Outstanding Balance Due: <span class="h5 fw-bold text-danger m-0" id="pay-remaining-text"></span>
            </div>
        </div>

        <form method="POST" action="">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="collect_repair_payment">
            <input type="hidden" name="bill_id" id="pay-bill-id" value="">

            <div class="mb-3">
                <label class="form-label small fw-semibold">Amount Paid (Rs.) *</label>
                <input type="number" step="0.01" name="amount" id="pay-amount-input" class="form-control form-control-lg fw-bold text-success" placeholder="0.00" required min="1">
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
                <input class="form-check-input" type="checkbox" name="mark_delivered" id="mark_delivered" value="1" checked>
                <label class="form-check-label small fw-semibold" for="mark_delivered">Mark Repair Status as "Delivered"</label>
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

<script>
$(document).ready(function() {
    $('.btn-pay-repair').on('click', function() {
        let billId = $(this).data('id');
        let billNo = $(this).data('bill');
        let customer = $(this).data('customer');
        let phone = $(this).data('phone');
        let device = $(this).data('device');
        let remaining = parseFloat($(this).data('remaining')) || 0;

        $('#pay-bill-id').val(billId);
        $('#pay-bill-no').text(billNo);
        $('#pay-customer').text(customer);
        $('#pay-phone').text(phone);
        $('#pay-device').text(device);
        $('#pay-remaining-text').text('Rs. ' + remaining.toLocaleString(undefined, {minimumFractionDigits: 2}));
        $('#pay-amount-input').val(remaining).attr('max', remaining);

        openSidePanel('panel-collect-repair-pay');
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
