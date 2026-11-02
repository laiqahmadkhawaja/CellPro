<?php
// customers/ledger.php - Comprehensive Customer Ledger & Running Balance
$page_title = 'Customer Ledger Statement';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/accounting.php';

$customer_id = (int)($_GET['id'] ?? 0);
$branch_id = getActiveBranchId();
$financial_accounts = getFinancialAccounts($pdo, $branch_id);

$stmt = $pdo->prepare("SELECT * FROM customers WHERE id = ?");
$stmt->execute([$customer_id]);
$customer = $stmt->fetch();

if (!$customer) {
    flash('danger', 'Customer not found.');
    redirect(BASE_URL . 'customers/index.php');
}

// Handle Receiving Payment from Customer
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'receive_payment') {
    validatePostCsrf();
    $amount = (float)($_POST['amount'] ?? 0);
    $pay_date = sanitize($_POST['payment_date'] ?? date('Y-m-d'));
    $account_id = (int)$_POST['payment_account_id'];
    $method = sanitize($_POST['payment_method'] ?? 'Cash');
    $notes = sanitize($_POST['notes'] ?? 'Customer ledger payment');

    if ($amount <= 0) {
        flash('danger', 'Please enter a valid payment amount.');
        redirect(BASE_URL . 'customers/ledger.php?id=' . $customer_id);
    }

    try {
        $pdo->beginTransaction();

        // 1. Deduct customer balance
        $pdo->prepare("UPDATE customers SET current_balance = current_balance - ? WHERE id = ?")
            ->execute([$amount, $customer_id]);

        // 2. Increase financial account balance
        $pdo->prepare("UPDATE financial_accounts SET current_balance = current_balance + ? WHERE id = ?")
            ->execute([$amount, $account_id]);

        // 3. Post balanced journal entry: Debit Cash/Bank, Credit Customer Receivables
        $cash_acct = getFinancialLedgerAccountId($pdo, $account_id);
        $recv_acct = getAccountIdByCode($pdo, '1003'); // Receivables

        $j_lines = [
            [
                'account_id' => $cash_acct,
                'debit' => $amount,
                'credit' => 0.00,
                'description' => "Received from customer {$customer['name']} - {$notes}"
            ],
            [
                'account_id' => $recv_acct,
                'debit' => 0.00,
                'credit' => $amount,
                'description' => "Account credit for customer {$customer['name']}"
            ]
        ];

        postJournalEntry($pdo, $branch_id, 'customer_payment', $customer_id, $j_lines, $pay_date, "Payment from {$customer['name']}");

        logAudit($pdo, 'payment', 'customers', $customer_id, "Received {$amount} from customer {$customer['name']}");

        $pdo->commit();
        flash('success', "Payment of " . formatCurrency($amount) . " received successfully.");
        redirect(BASE_URL . 'customers/ledger.php?id=' . $customer_id);

    } catch (Exception $e) {
        $pdo->rollBack();
        flash('danger', 'Error recording payment: ' . $e->getMessage());
    }
}

// Fetch Chronological Transactions
$transactions = [];

// A. Opening balance
$transactions[] = [
    'date' => $customer['created_at'],
    'type' => 'Opening Balance',
    'reference' => 'OB-001',
    'description' => 'Account Opening Balance',
    'debit' => $customer['opening_balance'] > 0 ? (float)$customer['opening_balance'] : 0.00,
    'credit' => $customer['opening_balance'] < 0 ? (float)abs($customer['opening_balance']) : 0.00
];

// B. Sales Invoices (Debits)
$stmt = $pdo->prepare("SELECT id, invoice_no, sale_date, net_payable, created_at FROM sales WHERE customer_id = ?");
$stmt->execute([$customer_id]);
while ($row = $stmt->fetch()) {
    $transactions[] = [
        'date' => $row['created_at'],
        'type' => 'Sale Invoice',
        'reference' => $row['invoice_no'],
        'description' => 'Goods purchased',
        'debit' => (float)$row['net_payable'],
        'credit' => 0.00,
        'link' => BASE_URL . 'pos/invoice.php?id=' . $row['id']
    ];
}

// C. Sale Payments (Credits)
$stmt = $pdo->prepare("SELECT sp.amount, sp.payment_date, sp.payment_method, sp.notes, s.invoice_no, sp.created_at
                       FROM sale_payments sp
                       JOIN sales s ON sp.sale_id = s.id
                       WHERE s.customer_id = ?");
$stmt->execute([$customer_id]);
while ($row = $stmt->fetch()) {
    $transactions[] = [
        'date' => $row['created_at'],
        'type' => 'POS Payment',
        'reference' => $row['invoice_no'],
        'description' => "Paid via {$row['payment_method']} on {$row['invoice_no']}",
        'debit' => 0.00,
        'credit' => (float)$row['amount']
    ];
}

// D. Direct Ledger Payments (Credits from Journal)
$stmt = $pdo->prepare("SELECT je.entry_no, je.entry_date, je.narration, jel.credit, je.created_at
                       FROM journal_entry_lines jel
                       JOIN journal_entries je ON jel.entry_id = je.id
                       WHERE je.reference_type = 'customer_payment' AND je.reference_id = ? AND jel.credit > 0");
$stmt->execute([$customer_id]);
while ($row = $stmt->fetch()) {
    $transactions[] = [
        'date' => $row['created_at'],
        'type' => 'Direct Payment',
        'reference' => $row['entry_no'],
        'description' => $row['narration'],
        'debit' => 0.00,
        'credit' => (float)$row['credit']
    ];
}

// E. Sales Returns (Credits)
$stmt = $pdo->prepare("SELECT return_no, return_date, total_refund, created_at FROM sales_returns WHERE customer_id = ?");
$stmt->execute([$customer_id]);
while ($row = $stmt->fetch()) {
    $transactions[] = [
        'date' => $row['created_at'],
        'type' => 'Sales Return',
        'reference' => $row['return_no'],
        'description' => 'Return reversal credit',
        'debit' => 0.00,
        'credit' => (float)$row['total_refund']
    ];
}

// Sort all transactions by date ASC
usort($transactions, function($a, $b) {
    return strtotime($a['date']) <=> strtotime($b['date']);
});

// Calculate running balances
$running_bal = 0.00;
foreach ($transactions as &$t) {
    $running_bal += ($t['debit'] - $t['credit']);
    $t['running_balance'] = $running_bal;
}
unset($t);
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h5 class="fw-bold m-0"><i class="bi bi-journal-text text-primary me-2"></i>Customer Ledger: <?= htmlspecialchars($customer['name']) ?></h5>
        <small class="text-muted">Mobile: <?= htmlspecialchars($customer['phone']) ?> &bull; Current Due: <span class="fw-bold text-danger"><?= formatCurrency($customer['current_balance']) ?></span></small>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= BASE_URL ?>customers/index.php" class="btn btn-outline-secondary btn-sm no-print">
            <i class="bi bi-arrow-left me-1"></i> Customers List
        </a>
        <button type="button" class="btn btn-success btn-sm no-print shadow-sm" data-toggle-panel="panel-receive-customer-pay">
            <i class="bi bi-cash me-1"></i> Receive Payment
        </button>
        <button onclick="window.print()" class="btn btn-primary btn-sm no-print">
            <i class="bi bi-printer me-1"></i> Print Statement
        </button>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header bg-light d-flex justify-content-between align-items-center">
        <span class="fw-bold">Running Statement of Account</span>
        <span class="badge bg-<?= $customer['current_balance'] > 0 ? 'danger' : 'success' ?> fs-6">
            Net Balance: <?= formatCurrency($customer['current_balance']) ?>
        </span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-bordered table-hover align-middle mb-0">
                <thead class="table-light small">
                    <tr>
                        <th style="width: 140px;">Date</th>
                        <th style="width: 130px;">Transaction Type</th>
                        <th style="width: 140px;">Reference #</th>
                        <th>Description</th>
                        <th class="text-end" style="width: 120px;">Debit (+)</th>
                        <th class="text-end" style="width: 120px;">Credit (-)</th>
                        <th class="text-end" style="width: 140px;">Running Balance</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($transactions)): ?>
                        <tr><td colspan="7" class="text-center py-4 text-muted">No transactions found for this customer.</td></tr>
                    <?php else: ?>
                        <?php foreach ($transactions as $tx): ?>
                            <tr>
                                <td><?= formatDate($tx['date'], true) ?></td>
                                <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($tx['type']) ?></span></td>
                                <td>
                                    <?php if (!empty($tx['link'])): ?>
                                        <a href="<?= $tx['link'] ?>" target="_blank" class="fw-semibold text-primary text-decoration-none">
                                            <?= htmlspecialchars($tx['reference']) ?> &rarr;
                                        </a>
                                    <?php else: ?>
                                        <span class="font-monospace fw-semibold"><?= htmlspecialchars($tx['reference']) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="small text-muted"><?= htmlspecialchars($tx['description']) ?></td>
                                <td class="text-end text-danger fw-semibold">
                                    <?= $tx['debit'] > 0 ? formatCurrency($tx['debit']) : '-' ?>
                                </td>
                                <td class="text-end text-success fw-semibold">
                                    <?= $tx['credit'] > 0 ? formatCurrency($tx['credit']) : '-' ?>
                                </td>
                                <td class="text-end fw-bold <?= $tx['running_balance'] > 0 ? 'text-danger' : 'text-success' ?>">
                                    <?= formatCurrency($tx['running_balance']) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- SIDE-PANEL DRAWER: RECEIVE PAYMENT (Zero Modals Rule) -->
<div id="panel-receive-customer-pay" class="side-panel">
    <div class="side-panel-header">
        <h6 class="m-0 fw-bold"><i class="bi bi-cash me-2 text-success"></i>Receive Customer Payment</h6>
        <button type="button" class="btn-close" data-close-panel></button>
    </div>
    <div class="side-panel-body">
        <div class="alert alert-info py-2 small mb-3">
            Customer: <strong><?= htmlspecialchars($customer['name']) ?></strong><br>
            Current Outstanding Balance: <strong><?= formatCurrency($customer['current_balance']) ?></strong>
        </div>

        <form method="POST" action="">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="receive_payment">

            <div class="mb-3">
                <label class="form-label small fw-semibold">Amount Received (Rs.) *</label>
                <input type="number" step="0.01" name="amount" class="form-control form-control-lg fw-bold text-success" placeholder="0.00" value="<?= max(0, $customer['current_balance']) ?>" required min="1">
            </div>

            <div class="mb-3">
                <label class="form-label small fw-semibold">Deposit Account *</label>
                <select name="payment_account_id" class="form-select" required>
                    <?php foreach ($financial_accounts as $fa): ?>
                        <option value="<?= $fa['id'] ?>"><?= htmlspecialchars($fa['name']) ?> (<?= ucfirst($fa['type']) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="mb-3">
                <label class="form-label small fw-semibold">Payment Date</label>
                <input type="date" name="payment_date" class="form-control" value="<?= date('Y-m-d') ?>">
            </div>

            <div class="mb-4">
                <label class="form-label small fw-semibold">Notes / Receipt Reference</label>
                <input type="text" name="notes" class="form-control" placeholder="Online transfer slip # or cheque #">
            </div>

            <div class="side-panel-footer px-0 pb-0">
                <button type="button" class="btn btn-light" data-close-panel>Cancel</button>
                <button type="submit" class="btn btn-success">Record Payment Receipt</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
