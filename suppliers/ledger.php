<?php
// suppliers/ledger.php - Comprehensive Supplier Ledger & Payables
$page_title = 'Supplier Ledger Statement';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/accounting.php';

$supplier_id = (int)($_GET['id'] ?? 0);
$branch_id = getActiveBranchId();
$financial_accounts = getFinancialAccounts($pdo, $branch_id);

$stmt = $pdo->prepare("SELECT * FROM suppliers WHERE id = ?");
$stmt->execute([$supplier_id]);
$supplier = $stmt->fetch();

if (!$supplier) {
    flash('danger', 'Supplier not found.');
    redirect(BASE_URL . 'suppliers/index.php');
}

// Handle Paying Supplier
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'pay_supplier') {
    validatePostCsrf();
    $amount = (float)($_POST['amount'] ?? 0);
    $pay_date = sanitize($_POST['payment_date'] ?? date('Y-m-d'));
    $account_id = (int)$_POST['payment_account_id'];
    $method = sanitize($_POST['payment_method'] ?? 'Cash');
    $notes = sanitize($_POST['notes'] ?? 'Supplier ledger payment');

    if ($amount <= 0) {
        flash('danger', 'Please enter a valid payment amount.');
        redirect(BASE_URL . 'suppliers/ledger.php?id=' . $supplier_id);
    }

    try {
        $pdo->beginTransaction();

        // 1. Deduct supplier balance
        $pdo->prepare("UPDATE suppliers SET current_balance = current_balance - ? WHERE id = ?")
            ->execute([$amount, $supplier_id]);

        // 2. Decrease financial account balance
        $pdo->prepare("UPDATE financial_accounts SET current_balance = current_balance - ? WHERE id = ?")
            ->execute([$amount, $account_id]);

        // 3. Post journal entry: Debit Supplier Payables, Credit Cash/Bank
        $cash_acct = getFinancialLedgerAccountId($pdo, $account_id);
        $pay_acct = getAccountIdByCode($pdo, '2001'); // Supplier Payables

        $j_lines = [
            [
                'account_id' => $pay_acct,
                'debit' => $amount,
                'credit' => 0.00,
                'description' => "Payment made to supplier {$supplier['name']} - {$notes}"
            ],
            [
                'account_id' => $cash_acct,
                'debit' => 0.00,
                'credit' => $amount,
                'description' => "Cash/Bank disbursed for {$supplier['name']}"
            ]
        ];

        postJournalEntry($pdo, $branch_id, 'supplier_payment', $supplier_id, $j_lines, $pay_date, "Payment to {$supplier['name']}");

        logAudit($pdo, 'payment', 'suppliers', $supplier_id, "Paid {$amount} to supplier {$supplier['name']}");

        $pdo->commit();
        flash('success', "Payment of " . formatCurrency($amount) . " to {$supplier['name']} recorded successfully.");
        redirect(BASE_URL . 'suppliers/ledger.php?id=' . $supplier_id);

    } catch (Exception $e) {
        $pdo->rollBack();
        flash('danger', 'Error paying supplier: ' . $e->getMessage());
    }
}

// Fetch Chronological Transactions
$transactions = [];

// A. Opening balance
$transactions[] = [
    'date' => $supplier['created_at'],
    'type' => 'Opening Balance',
    'reference' => 'OB-001',
    'description' => 'Account Opening Balance',
    'debit' => $supplier['opening_balance'] < 0 ? (float)abs($supplier['opening_balance']) : 0.00,
    'credit' => $supplier['opening_balance'] > 0 ? (float)$supplier['opening_balance'] : 0.00
];

// B. Purchases (Credits - increases payable)
$stmt = $pdo->prepare("SELECT id, purchase_no, grand_total, purchase_date, created_at FROM purchases WHERE supplier_id = ?");
$stmt->execute([$supplier_id]);
while ($row = $stmt->fetch()) {
    $transactions[] = [
        'date' => $row['created_at'],
        'type' => 'Purchase Invoice',
        'reference' => $row['purchase_no'],
        'description' => 'Goods received from supplier',
        'debit' => 0.00,
        'credit' => (float)$row['grand_total'],
        'link' => BASE_URL . 'purchases/view.php?id=' . $row['id']
    ];
}

// C. Purchase Payments (Debits - reduces payable)
$stmt = $pdo->prepare("SELECT pp.amount, pp.payment_date, pp.payment_method, pp.notes, p.purchase_no, pp.created_at 
                       FROM purchase_payments pp
                       JOIN purchases p ON pp.purchase_id = p.id
                       WHERE p.supplier_id = ?");
$stmt->execute([$supplier_id]);
while ($row = $stmt->fetch()) {
    $transactions[] = [
        'date' => $row['created_at'],
        'type' => 'Purchase Payment',
        'reference' => $row['purchase_no'],
        'description' => "Paid via {$row['payment_method']} on {$row['purchase_no']}",
        'debit' => (float)$row['amount'],
        'credit' => 0.00
    ];
}

// D. Direct Ledger Payments (Debits from Journal)
$stmt = $pdo->prepare("SELECT je.entry_no, je.entry_date, je.narration, jel.debit, je.created_at
                       FROM journal_entry_lines jel
                       JOIN journal_entries je ON jel.entry_id = je.id
                       WHERE je.reference_type = 'supplier_payment' AND je.reference_id = ? AND jel.debit > 0");
$stmt->execute([$supplier_id]);
while ($row = $stmt->fetch()) {
    $transactions[] = [
        'date' => $row['created_at'],
        'type' => 'Direct Payment',
        'reference' => $row['entry_no'],
        'description' => $row['narration'],
        'debit' => (float)$row['debit'],
        'credit' => 0.00
    ];
}

// Sort by date ASC
usort($transactions, function($a, $b) {
    return strtotime($a['date']) <=> strtotime($b['date']);
});

// Calculate running balances (Credit increases payable, Debit decreases)
$running_bal = 0.00;
foreach ($transactions as &$t) {
    $running_bal += ($t['credit'] - $t['debit']);
    $t['running_balance'] = $running_bal;
}
unset($t);
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h5 class="fw-bold m-0"><i class="bi bi-truck text-primary me-2"></i>Supplier Ledger: <?= htmlspecialchars($supplier['name']) ?></h5>
        <small class="text-muted">Contact: <?= htmlspecialchars($supplier['contact_person'] ?: $supplier['phone']) ?> &bull; Current Payable: <span class="fw-bold text-primary"><?= formatCurrency($supplier['current_balance']) ?></span></small>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= BASE_URL ?>suppliers/index.php" class="btn btn-outline-secondary btn-sm no-print">
            <i class="bi bi-arrow-left me-1"></i> Suppliers List
        </a>
        <button type="button" class="btn btn-primary btn-sm no-print shadow-sm" data-toggle-panel="panel-pay-supplier">
            <i class="bi bi-wallet2 me-1"></i> Make Payment
        </button>
        <button onclick="window.print()" class="btn btn-outline-primary btn-sm no-print">
            <i class="bi bi-printer me-1"></i> Print Statement
        </button>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header bg-light d-flex justify-content-between align-items-center">
        <span class="fw-bold">Running Statement of Account</span>
        <span class="badge bg-<?= $supplier['current_balance'] > 0 ? 'primary' : 'success' ?> fs-6">
            Net Payable: <?= formatCurrency($supplier['current_balance']) ?>
        </span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-bordered table-hover align-middle mb-0">
                <thead class="table-light small">
                    <tr>
                        <th style="width: 140px;">Date</th>
                        <th style="width: 140px;">Transaction Type</th>
                        <th style="width: 140px;">Reference #</th>
                        <th>Description</th>
                        <th class="text-end" style="width: 120px;">Debit (-) Paid</th>
                        <th class="text-end" style="width: 120px;">Credit (+) Billed</th>
                        <th class="text-end" style="width: 140px;">Payable Balance</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($transactions)): ?>
                        <tr><td colspan="7" class="text-center py-4 text-muted">No transactions found for this supplier.</td></tr>
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
                                <td class="text-end text-success fw-semibold">
                                    <?= $tx['debit'] > 0 ? formatCurrency($tx['debit']) : '-' ?>
                                </td>
                                <td class="text-end text-primary fw-semibold">
                                    <?= $tx['credit'] > 0 ? formatCurrency($tx['credit']) : '-' ?>
                                </td>
                                <td class="text-end fw-bold <?= $tx['running_balance'] > 0 ? 'text-primary' : 'text-success' ?>">
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

<!-- SIDE-PANEL DRAWER: PAY SUPPLIER (Zero Modals Rule) -->
<div id="panel-pay-supplier" class="side-panel">
    <div class="side-panel-header">
        <h6 class="m-0 fw-bold"><i class="bi bi-wallet2 me-2 text-primary"></i>Pay Supplier</h6>
        <button type="button" class="btn-close" data-close-panel></button>
    </div>
    <div class="side-panel-body">
        <div class="alert alert-info py-2 small mb-3">
            Supplier: <strong><?= htmlspecialchars($supplier['name']) ?></strong><br>
            Current Balance Owed: <strong><?= formatCurrency($supplier['current_balance']) ?></strong>
        </div>

        <form method="POST" action="">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="pay_supplier">

            <div class="mb-3">
                <label class="form-label small fw-semibold">Payment Amount (Rs.) *</label>
                <input type="number" step="0.01" name="amount" class="form-control form-control-lg fw-bold text-primary" placeholder="0.00" value="<?= max(0, $supplier['current_balance']) ?>" required min="1">
            </div>

            <div class="mb-3">
                <label class="form-label small fw-semibold">Disbursed From Account *</label>
                <select name="payment_account_id" class="form-select" required>
                    <?php foreach ($financial_accounts as $fa): ?>
                        <option value="<?= $fa['id'] ?>"><?= htmlspecialchars($fa['name']) ?> (Balance: <?= formatCurrency($fa['current_balance']) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="mb-3">
                <label class="form-label small fw-semibold">Payment Date</label>
                <input type="date" name="payment_date" class="form-control" value="<?= date('Y-m-d') ?>">
            </div>

            <div class="mb-4">
                <label class="form-label small fw-semibold">Reference / Cheque / Slip #</label>
                <input type="text" name="notes" class="form-control" placeholder="Cheque number or IBFT ref">
            </div>

            <div class="side-panel-footer px-0 pb-0">
                <button type="button" class="btn btn-light" data-close-panel>Cancel</button>
                <button type="submit" class="btn btn-primary">Submit Supplier Payment</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
