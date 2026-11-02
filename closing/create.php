<?php
// closing/create.php - End of Day Cash Drawer Reconciliation
$page_title = 'Perform Daily Cash Closing';
require_once __DIR__ . '/../includes/header.php';

$branch_id = getActiveBranchId();
$today = date('Y-m-d');

// 1. Get Default Cash Till Account
$stmt = $pdo->prepare("SELECT * FROM financial_accounts WHERE branch_id = ? AND type = 'cash' AND is_default = 1 LIMIT 1");
$stmt->execute([$branch_id]);
$cash_account = $stmt->fetch();

if (!$cash_account) {
    $stmt = $pdo->prepare("SELECT * FROM financial_accounts WHERE branch_id = ? AND type = 'cash' LIMIT 1");
    $stmt->execute([$branch_id]);
    $cash_account = $stmt->fetch();
}

$cash_acct_id = $cash_account ? $cash_account['id'] : 1;

// 2. Compute Today's Cash Flow Metrics
// A. Cash Sales
$stmt = $pdo->prepare("SELECT COALESCE(SUM(sp.amount), 0) FROM sale_payments sp
                       JOIN sales s ON sp.sale_id = s.id
                       WHERE s.branch_id = ? AND sp.payment_date = ? AND sp.account_id = ?");
$stmt->execute([$branch_id, $today, $cash_acct_id]);
$today_cash_sales = (float)$stmt->fetchColumn();

// B. Cash Expenses
$stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE branch_id = ? AND expense_date = ? AND account_id = ?");
$stmt->execute([$branch_id, $today, $cash_acct_id]);
$today_cash_expenses = (float)$stmt->fetchColumn();

// C. Cash Purchases
$stmt = $pdo->prepare("SELECT COALESCE(SUM(pp.amount), 0) FROM purchase_payments pp
                       JOIN purchases p ON pp.purchase_id = p.id
                       WHERE p.branch_id = ? AND pp.payment_date = ? AND pp.account_id = ?");
$stmt->execute([$branch_id, $today, $cash_acct_id]);
$today_cash_purchases = (float)$stmt->fetchColumn();

// D. Direct Customer Ledger Cash Receipts
$stmt = $pdo->prepare("SELECT COALESCE(SUM(jel.debit), 0) 
                       FROM journal_entry_lines jel
                       JOIN journal_entries je ON jel.entry_id = je.id
                       WHERE je.branch_id = ? AND je.entry_date = ? AND je.reference_type = 'customer_payment' AND jel.account_id = ?");
$stmt->execute([$branch_id, $today, $cash_account['account_id']]);
$today_customer_receipts = (float)$stmt->fetchColumn();

// E. Bank Deposits / Transfers Out
$stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM account_transfers WHERE from_account_id = ? AND transfer_date = ?");
$stmt->execute([$cash_acct_id, $today]);
$today_transfers_out = (float)$stmt->fetchColumn();

// Opening balance estimate: current till balance minus net today flow
$current_till_balance = (float)($cash_account['current_balance'] ?? 0);
$net_today_flow = ($today_cash_sales + $today_customer_receipts) - ($today_cash_expenses + $today_cash_purchases + $today_transfers_out);
$estimated_opening = max(0, $current_till_balance - $net_today_flow);
$expected_closing = $current_till_balance;

// Handle submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validatePostCsrf();
    $closing_date = sanitize($_POST['closing_date'] ?? $today);
    $opening_cash = (float)($_POST['opening_cash'] ?? $estimated_opening);
    $cash_sales = (float)($_POST['cash_sales'] ?? $today_cash_sales);
    $cash_expenses = (float)($_POST['cash_expenses'] ?? $today_cash_expenses);
    $cash_purchases = (float)($_POST['cash_purchases'] ?? $today_cash_purchases);
    $customer_receipts = (float)($_POST['customer_receipts'] ?? $today_customer_receipts);
    $transfers_out = (float)($_POST['transfers_out'] ?? $today_transfers_out);
    $expected_cash = (float)($_POST['expected_closing_cash'] ?? $expected_closing);
    $actual_cash = (float)($_POST['actual_closing_cash'] ?? 0);
    $difference = $actual_cash - $expected_cash;
    $notes = sanitize($_POST['notes'] ?? '');

    try {
        $stmt = $pdo->prepare("INSERT INTO daily_closings 
            (branch_id, closing_date, opening_cash, cash_sales, cash_purchases, customer_payments, cash_expenses, 
             cash_withdrawals, expected_closing_cash, actual_closing_cash, difference, notes, closed_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
             opening_cash = VALUES(opening_cash), cash_sales = VALUES(cash_sales), cash_purchases = VALUES(cash_purchases),
             customer_payments = VALUES(customer_payments), cash_expenses = VALUES(cash_expenses),
             expected_closing_cash = VALUES(expected_closing_cash), actual_closing_cash = VALUES(actual_closing_cash),
             difference = VALUES(difference), notes = VALUES(notes), closed_by = VALUES(closed_by)");

        $stmt->execute([
            $branch_id, $closing_date, $opening_cash, $cash_sales, $cash_purchases, $customer_receipts,
            $cash_expenses, $transfers_out, $expected_cash, $actual_cash, $difference, $notes, $_SESSION['user_id']
        ]);

        logAudit($pdo, 'closing', 'daily_closings', $pdo->lastInsertId(), "Closed branch {$branch_id} for date {$closing_date} with difference: {$difference}");

        flash('success', "Daily closing for {$closing_date} recorded successfully.");
        redirect(BASE_URL . 'closing/index.php');

    } catch (Exception $e) {
        flash('danger', 'Error recording daily closing: ' . $e->getMessage());
    }
}
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h5 class="fw-bold m-0"><i class="bi bi-safe text-success me-2"></i>Perform Daily Cash Closing</h5>
        <small class="text-muted">Reconcile today's cash drawer balance against system calculated flows</small>
    </div>
    <a href="<?= BASE_URL ?>closing/index.php" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i> Closings History
    </a>
</div>

<form method="POST" action="">
    <?= csrfField() ?>
    <input type="hidden" name="closing_date" value="<?= $today ?>">

    <div class="row g-3">
        <!-- LEFT: Systematic Cash Inflows and Outflows -->
        <div class="col-12 col-lg-7">
            <div class="card h-100">
                <div class="card-header bg-light">
                    <span class="fw-bold"><i class="bi bi-calculator me-1 text-primary"></i> System Cash Flow Summary (<?= formatDate($today) ?>)</span>
                </div>
                <div class="card-body">
                    <table class="table table-sm table-borderless align-middle mb-0">
                        <tr>
                            <td class="text-muted py-2">Opening Cash Balance:</td>
                            <td class="text-end py-2">
                                <input type="number" step="0.01" name="opening_cash" id="c-open" class="form-control form-control-sm text-end d-inline-block fw-semibold" style="width: 140px;" value="<?= $estimated_opening ?>">
                            </td>
                        </tr>
                        <tr class="border-top">
                            <td class="text-success py-2"><i class="bi bi-plus-circle me-1"></i> Cash Sales (POS & Mobile):</td>
                            <td class="text-end text-success fw-bold py-2">+<?= formatCurrency($today_cash_sales) ?></td>
                            <input type="hidden" name="cash_sales" id="c-sales" value="<?= $today_cash_sales ?>">
                        </tr>
                        <tr>
                            <td class="text-success py-2"><i class="bi bi-plus-circle me-1"></i> Customer Ledger Cash Receipts:</td>
                            <td class="text-end text-success fw-bold py-2">+<?= formatCurrency($today_customer_receipts) ?></td>
                            <input type="hidden" name="customer_receipts" id="c-cust" value="<?= $today_customer_receipts ?>">
                        </tr>
                        <tr class="border-top">
                            <td class="text-danger py-2"><i class="bi bi-dash-circle me-1"></i> Cash Shop Expenses:</td>
                            <td class="text-end text-danger fw-bold py-2">-<?= formatCurrency($today_cash_expenses) ?></td>
                            <input type="hidden" name="cash_expenses" id="c-exp" value="<?= $today_cash_expenses ?>">
                        </tr>
                        <tr>
                            <td class="text-danger py-2"><i class="bi bi-dash-circle me-1"></i> Cash Purchases / Mobile Buy-ins:</td>
                            <td class="text-end text-danger fw-bold py-2">-<?= formatCurrency($today_cash_purchases) ?></td>
                            <input type="hidden" name="cash_purchases" id="c-pur" value="<?= $today_cash_purchases ?>">
                        </tr>
                        <tr>
                            <td class="text-danger py-2"><i class="bi bi-dash-circle me-1"></i> Bank Deposits / Cash Handed Over:</td>
                            <td class="text-end text-danger fw-bold py-2">-<?= formatCurrency($today_transfers_out) ?></td>
                            <input type="hidden" name="transfers_out" id="c-trans" value="<?= $today_transfers_out ?>">
                        </tr>
                        <tr class="border-top border-2 bg-light">
                            <td class="fw-bold py-3 fs-6">Expected Cash in Drawer:</td>
                            <td class="text-end fw-bold py-3 fs-5 text-primary" id="lbl-expected">
                                <?= formatCurrency($expected_closing) ?>
                            </td>
                            <input type="hidden" name="expected_closing_cash" id="inp-expected" value="<?= $expected_closing ?>">
                        </tr>
                    </table>
                </div>
            </div>
        </div>

        <!-- RIGHT: Physical Cash Drawer Count & Difference -->
        <div class="col-12 col-lg-5">
            <div class="card h-100">
                <div class="card-header bg-light">
                    <span class="fw-bold"><i class="bi bi-safe2 me-1 text-success"></i> Physical Cash Count</span>
                </div>
                <div class="card-body">
                    <div class="mb-4">
                        <label class="form-label small fw-bold text-dark">Counted Physical Cash in Till (Rs.) *</label>
                        <input type="number" step="0.01" name="actual_closing_cash" id="inp-actual" class="form-control form-control-lg fw-bold text-success fs-3" placeholder="0.00" value="<?= $expected_closing ?>" required autofocus>
                        <small class="text-muted">Physically count your 5000, 1000, 500 notes and enter final cash sum.</small>
                    </div>

                    <div class="p-3 rounded-3 mb-3" id="diff-box" style="background: #f8fafc; border: 1px solid #e2e8f0;">
                        <small class="text-muted text-uppercase fw-semibold d-block">Cash Discrepancy / Difference</small>
                        <h4 class="fw-bold m-0" id="lbl-diff">Rs. 0.00</h4>
                        <small class="text-muted" id="lbl-diff-msg">Drawer is balanced.</small>
                    </div>

                    <div class="mb-4">
                        <label class="form-label small fw-semibold">Closing Notes / Discrepancy Reason</label>
                        <textarea name="notes" class="form-control form-control-sm" rows="3" placeholder="Explain difference if any, or general remarks..."></textarea>
                    </div>

                    <button type="submit" class="btn btn-success btn-lg w-100 fw-bold shadow-sm">
                        <i class="bi bi-lock-fill me-1"></i> Lock & Confirm Daily Closing
                    </button>
                </div>
            </div>
        </div>
    </div>
</form>

<script>
function updateClosingCalc() {
    let open = parseFloat($('#c-open').val()) || 0;
    let sales = parseFloat($('#c-sales').val()) || 0;
    let cust = parseFloat($('#c-cust').val()) || 0;
    let exp = parseFloat($('#c-exp').val()) || 0;
    let pur = parseFloat($('#c-pur').val()) || 0;
    let trans = parseFloat($('#c-trans').val()) || 0;

    let expected = (open + sales + cust) - (exp + pur + trans);
    $('#lbl-expected').text('Rs. ' + expected.toLocaleString(undefined, {minimumFractionDigits: 2}));
    $('#inp-expected').val(expected);

    let actual = parseFloat($('#inp-actual').val()) || 0;
    let diff = actual - expected;

    $('#lbl-diff').text('Rs. ' + diff.toLocaleString(undefined, {minimumFractionDigits: 2}));

    if (diff === 0) {
        $('#lbl-diff').removeClass('text-danger text-success').addClass('text-dark');
        $('#lbl-diff-msg').text('Drawer is completely balanced! No discrepancy.').removeClass('text-danger text-success');
    } else if (diff < 0) {
        $('#lbl-diff').removeClass('text-dark text-success').addClass('text-danger');
        $('#lbl-diff-msg').text('Shortage! Till has less cash than expected.').removeClass('text-success').addClass('text-danger fw-bold');
    } else {
        $('#lbl-diff').removeClass('text-dark text-danger').addClass('text-success');
        $('#lbl-diff-msg').text('Surplus! Till has more cash than expected.').removeClass('text-danger').addClass('text-success fw-bold');
    }
}

$('#c-open, #inp-actual').on('input', updateClosingCalc);
$(document).ready(updateClosingCalc);
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
