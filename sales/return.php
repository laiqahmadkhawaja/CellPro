<?php
// sales/return.php - Sales Returns & Accounting Reversals
$page_title = 'Sales Return';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/accounting.php';
require_once __DIR__ . '/../includes/inventory.php';

$branch_id = getActiveBranchId();
$inv_search = trim($_GET['invoice_no'] ?? '');
$sale = null;
$sale_items = [];
$financial_accounts = getFinancialAccounts($pdo, $branch_id);

if (!empty($inv_search)) {
    $stmt = $pdo->prepare("SELECT s.*, c.name as customer_name, c.phone as customer_phone, b.name as branch_name 
                           FROM sales s
                           LEFT JOIN customers c ON s.customer_id = c.id
                           LEFT JOIN branches b ON s.branch_id = b.id
                           WHERE s.invoice_no = ? AND s.branch_id = ?");
    $stmt->execute([$inv_search, $branch_id]);
    $sale = $stmt->fetch();

    if ($sale) {
        $stmt = $pdo->prepare("SELECT * FROM sale_items WHERE sale_id = ?");
        $stmt->execute([$sale['id']]);
        $sale_items = $stmt->fetchAll();
    } else {
        flash('warning', "Invoice '{$inv_search}' not found in this branch.");
    }
}

// Handle Return Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validatePostCsrf();
    $sale_id = (int)$_POST['sale_id'];
    $return_date = sanitize($_POST['return_date'] ?? date('Y-m-d'));
    $refund_method = sanitize($_POST['refund_method'] ?? 'cash'); // 'cash', 'ledger_credit'
    $account_id = (int)($_POST['refund_account_id'] ?? 1);
    $reason = sanitize($_POST['reason'] ?? 'Customer Return');
    $selected_items = $_POST['return_items'] ?? []; // array of sale_item_ids
    $return_qtys = $_POST['return_qty'] ?? [];

    if (empty($selected_items)) {
        flash('danger', 'Please select at least one item to return.');
        redirect(BASE_URL . 'sales/return.php?invoice_no=' . urlencode($_POST['invoice_no']));
    }

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("SELECT * FROM sales WHERE id = ? FOR UPDATE");
        $stmt->execute([$sale_id]);
        $sale_rec = $stmt->fetch();

        $total_refund = 0.00;
        $total_cost_reversed = 0.00;
        $items_to_return = [];

        foreach ($selected_items as $si_id) {
            $siStmt = $pdo->prepare("SELECT * FROM sale_items WHERE id = ? AND sale_id = ?");
            $siStmt->execute([$si_id, $sale_id]);
            $item = $siStmt->fetch();

            if (!$item) continue;

            $qty_to_return = ($item['item_type'] === 'mobile') ? 1 : min((int)($return_qtys[$si_id] ?? 1), $item['quantity']);
            $unit_refund = $item['total_price'] / $item['quantity'];
            $line_refund = $unit_refund * $qty_to_return;
            $line_cost = $item['unit_cost'] * $qty_to_return;

            $total_refund += $line_refund;
            $total_cost_reversed += $line_cost;

            $items_to_return[] = [
                'sale_item_id' => $item['id'],
                'item_type' => $item['item_type'],
                'product_id' => $item['product_id'],
                'device_id' => $item['device_id'],
                'quantity' => $qty_to_return,
                'refund_price' => $line_refund,
                'cost' => $line_cost
            ];
        }

        $return_no = generateDocNo($pdo, 'RET-S', 'sales_returns', 'return_no');

        $stmt = $pdo->prepare("INSERT INTO sales_returns 
            (return_no, sale_id, branch_id, customer_id, return_date, total_refund, refund_method, reason, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $return_no, $sale_id, $branch_id, $sale_rec['customer_id'], $return_date, $total_refund, $refund_method, $reason, $_SESSION['user_id']
        ]);
        $return_id = $pdo->lastInsertId();

        $retItemStmt = $pdo->prepare("INSERT INTO sales_return_items (return_id, sale_item_id, product_id, device_id, quantity, refund_price) VALUES (?, ?, ?, ?, ?, ?)");

        foreach ($items_to_return as $r) {
            $retItemStmt->execute([$return_id, $r['sale_item_id'], $r['product_id'], $r['device_id'], $r['quantity'], $r['refund_price']]);

            // Reverse inventory stock
            if ($r['item_type'] === 'mobile') {
                recordStockMovement($pdo, $branch_id, null, $r['device_id'], 'return_in', 1, $r['cost'], 'sale_return', $return_id, "Sales return in {$return_no}");
            } else {
                recordStockMovement($pdo, $branch_id, $r['product_id'], null, 'return_in', $r['quantity'], $r['cost'] / $r['quantity'], 'sale_return', $return_id, "Sales return in {$return_no}");
            }
        }

        // Handle refund payment or customer ledger adjustment
        if ($refund_method === 'ledger_credit' && $sale_rec['customer_id']) {
            // Deduct customer receivable
            $pdo->prepare("UPDATE customers SET current_balance = current_balance - ? WHERE id = ?")
                ->execute([$total_refund, $sale_rec['customer_id']]);
        } else {
            // Cash / Bank refund
            $pdo->prepare("UPDATE financial_accounts SET current_balance = current_balance - ? WHERE id = ?")
                ->execute([$total_refund, $account_id]);
        }

        // DOUBLE-ENTRY REVERSAL POSTING
        $rev_acct = getAccountIdByCode($pdo, '4001'); // Revenue
        $cogs_acct = getAccountIdByCode($pdo, '5001'); // COGS
        $inventory_acct = getAccountIdByCode($pdo, '1004'); // Inventory
        $cash_acct = ($refund_method === 'ledger_credit') ? getAccountIdByCode($pdo, '1003') : getFinancialLedgerAccountId($pdo, $account_id);

        $j_lines = [
            // 1. Reversal of Revenue (Debit Revenue, Credit Cash/Receivables)
            [
                'account_id' => $rev_acct,
                'debit' => $total_refund,
                'credit' => 0.00,
                'description' => "Revenue reversed for return {$return_no}"
            ],
            [
                'account_id' => $cash_acct,
                'debit' => 0.00,
                'credit' => $total_refund,
                'description' => "Refund issued for return {$return_no}"
            ],
            // 2. Reversal of Inventory & COGS (Debit Inventory, Credit COGS)
            [
                'account_id' => $inventory_acct,
                'debit' => $total_cost_reversed,
                'credit' => 0.00,
                'description' => "Inventory restored for return {$return_no}"
            ],
            [
                'account_id' => $cogs_acct,
                'debit' => 0.00,
                'credit' => $total_cost_reversed,
                'description' => "COGS reversed for return {$return_no}"
            ]
        ];

        postJournalEntry($pdo, $branch_id, 'sale_return', $return_id, $j_lines, $return_date, "Sales Return {$return_no}");

        logAudit($pdo, 'create', 'sales_returns', $return_id, "Processed return {$return_no} for invoice {$sale_rec['invoice_no']}");

        $pdo->commit();
        flash('success', "Sales return {$return_no} completed. Stock restored and accounting reversed.");
        redirect(BASE_URL . 'sales/index.php');

    } catch (Exception $e) {
        $pdo->rollBack();
        flash('danger', 'Error processing sales return: ' . $e->getMessage());
    }
}
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h5 class="fw-bold m-0"><i class="bi bi-arrow-counterclockwise text-danger me-2"></i>Sales Return & Reversal</h5>
        <small class="text-muted">Return phones or accessories with automatic stock restoration and balanced accounting reversal</small>
    </div>
    <a href="<?= BASE_URL ?>sales/index.php" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i> Back to Sales
    </a>
</div>

<!-- Search Invoice Box -->
<div class="card mb-3">
    <div class="card-body p-3">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-12 col-md-8">
                <label class="form-label small fw-semibold">Enter Sale Invoice # to Return</label>
                <div class="input-group">
                    <span class="input-group-text bg-white"><i class="bi bi-search text-primary"></i></span>
                    <input type="text" name="invoice_no" class="form-control" placeholder="e.g. INV-M-261102-0001" value="<?= htmlspecialchars($inv_search) ?>" required autofocus>
                </div>
            </div>
            <div class="col-12 col-md-4">
                <button type="submit" class="btn btn-primary w-100">Find Invoice</button>
            </div>
        </form>
    </div>
</div>

<?php if ($sale): ?>
    <form method="POST" action="">
        <?= csrfField() ?>
        <input type="hidden" name="sale_id" value="<?= $sale['id'] ?>">
        <input type="hidden" name="invoice_no" value="<?= htmlspecialchars($sale['invoice_no']) ?>">

        <div class="card mb-3">
            <div class="card-header bg-light">
                <span class="fw-bold">Original Invoice: <?= htmlspecialchars($sale['invoice_no']) ?> (Customer: <?= htmlspecialchars($sale['customer_name'] ?? 'Walk-in') ?>)</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light small">
                            <tr>
                                <th style="width: 40px;">Select</th>
                                <th>Item Description</th>
                                <th>IMEI</th>
                                <th class="text-center">Sold Qty</th>
                                <th class="text-center" style="width: 100px;">Return Qty</th>
                                <th class="text-end">Sold Price</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($sale_items as $item): ?>
                                <tr>
                                    <td class="text-center">
                                        <input type="checkbox" name="return_items[]" value="<?= $item['id'] ?>" class="form-check-input return-check" data-price="<?= $item['total_price'] / $item['quantity'] ?>">
                                    </td>
                                    <td>
                                        <div class="fw-bold text-dark"><?= htmlspecialchars($item['item_name']) ?></div>
                                        <small class="text-muted text-uppercase"><?= $item['item_type'] ?></small>
                                    </td>
                                    <td>
                                        <?= $item['imei'] ? '<span class="font-monospace fw-semibold small">IMEI: ' . htmlspecialchars($item['imei']) . '</span>' : '-' ?>
                                    </td>
                                    <td class="text-center"><?= $item['quantity'] ?></td>
                                    <td class="text-center">
                                        <?php if ($item['item_type'] === 'mobile'): ?>
                                            <span class="badge bg-light text-dark border">1</span>
                                            <input type="hidden" name="return_qty[<?= $item['id'] ?>]" value="1">
                                        <?php else: ?>
                                            <input type="number" name="return_qty[<?= $item['id'] ?>]" class="form-control form-control-sm text-center return-qty-inp" value="1" min="1" max="<?= $item['quantity'] ?>">
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end fw-bold"><?= formatCurrency($item['total_price']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header bg-light">
                <span class="fw-bold">Refund Options & Reason</span>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-12 col-md-3">
                        <label class="form-label small fw-semibold">Refund Method</label>
                        <select name="refund_method" class="form-select form-select-sm" id="refund-method-select">
                            <option value="cash">Cash / Bank Refund</option>
                            <?php if ($sale['customer_id'] > 1): ?>
                                <option value="ledger_credit">Credit to Customer Account Balance</option>
                            <?php endif; ?>
                        </select>
                    </div>
                    <div class="col-12 col-md-3" id="refund-account-group">
                        <label class="form-label small fw-semibold">Pay Refund From Account</label>
                        <select name="refund_account_id" class="form-select form-select-sm">
                            <?php foreach ($financial_accounts as $fa): ?>
                                <option value="<?= $fa['id'] ?>"><?= htmlspecialchars($fa['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label small fw-semibold">Return Reason / Notes</label>
                        <input type="text" name="reason" class="form-control form-control-sm" placeholder="e.g. Defective accessory or customer exchange request" required>
                    </div>
                </div>
            </div>
            <div class="card-footer bg-white text-end">
                <button type="submit" class="btn btn-danger px-4 shadow-sm">
                    <i class="bi bi-arrow-counterclockwise me-1"></i> Process Return & Reverse Stock
                </button>
            </div>
        </div>
    </form>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
