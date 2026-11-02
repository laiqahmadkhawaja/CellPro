<?php
// accounting/trial_balance.php - Balanced Trial Balance
$page_title = 'Trial Balance';
require_once __DIR__ . '/../includes/header.php';

$branch_id = getActiveBranchId();
$date_as_of = $_GET['as_of'] ?? date('Y-m-d');

// Compute balance for every account as of $date_as_of
$sql = "SELECT a.id, a.code, a.name, ag.group_type, ag.name as group_name,
               COALESCE(SUM(jel.debit), 0) as total_debit,
               COALESCE(SUM(jel.credit), 0) as total_credit
        FROM accounts a
        JOIN account_groups ag ON a.group_id = ag.id
        LEFT JOIN journal_entry_lines jel ON a.id = jel.account_id
        LEFT JOIN journal_entries je ON jel.entry_id = je.id AND je.entry_date <= ? " . ($branch_id ? "AND je.branch_id = $branch_id" : "") . "
        WHERE a.status = 'active'
        GROUP BY a.id, a.code, a.name, ag.group_type, ag.name
        ORDER BY a.code ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute([$date_as_of]);
$rows = $stmt->fetchAll();

$total_debits = 0.00;
$total_credits = 0.00;
?>

<?= renderPrintHeader(
    'TRIAL BALANCE STATEMENT',
    'Mathematical Verification of General Ledger Balances',
    [
        'As of Date' => formatDate($date_as_of),
        'Total Debits' => formatCurrency($tot_debit ?? 0),
        'Total Credits' => formatCurrency($tot_credit ?? 0)
    ]
) ?>

<div class="d-flex justify-content-between align-items-center mb-3 no-print">
    <div>
        <h5 class="fw-bold m-0"><i class="bi bi-bar-chart text-primary me-2"></i>Trial Balance</h5>
        <small class="text-muted">Verification of double-entry mathematical balance as of <?= formatDate($date_as_of) ?></small>
    </div>
    <div class="d-flex gap-2">
        <button onclick="window.print()" class="btn btn-primary btn-sm no-print">
            <i class="bi bi-printer me-1"></i> Print Trial Balance
        </button>
    </div>
</div>

<!-- As of date filter -->
<div class="card mb-3 no-print">
    <div class="card-body p-3">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-12 col-md-4">
                <label class="form-label small text-muted">As Of Date</label>
                <input type="date" name="as_of" class="form-control form-control-sm" value="<?= htmlspecialchars($date_as_of) ?>">
            </div>
            <div class="col-12 col-md-2">
                <button type="submit" class="btn btn-sm btn-primary w-100">Calculate</button>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-bordered table-hover align-middle mb-0">
                <thead class="table-light small">
                    <tr>
                        <th style="width: 120px;">Account Code</th>
                        <th>Account Title</th>
                        <th>Classification</th>
                        <th class="text-end" style="width: 160px;">Debit Balance (Rs.)</th>
                        <th class="text-end" style="width: 160px;">Credit Balance (Rs.)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $r): ?>
                        <?php
                        $d = (float)$r['total_debit'];
                        $c = (float)$r['total_credit'];
                        $net = $d - $c;
                        $debit_val = 0;
                        $credit_val = 0;

                        if (in_array($r['group_type'], ['asset', 'expense', 'cogs'])) {
                            if ($net >= 0) $debit_val = $net;
                            else $credit_val = abs($net);
                        } else {
                            $net_credit = $c - $d;
                            if ($net_credit >= 0) $credit_val = $net_credit;
                            else $debit_val = abs($net_credit);
                        }

                        $total_debits += $debit_val;
                        $total_credits += $credit_val;
                        ?>
                        <tr>
                            <td class="font-monospace fw-semibold"><?= htmlspecialchars($r['code']) ?></td>
                            <td>
                                <a href="<?= BASE_URL ?>accounting/ledger.php?account_id=<?= $r['id'] ?>" class="text-dark fw-semibold text-decoration-none">
                                    <?= htmlspecialchars($r['name']) ?>
                                </a>
                            </td>
                            <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($r['group_name']) ?></span></td>
                            <td class="text-end fw-semibold <?= $debit_val > 0 ? 'text-dark' : 'text-muted' ?>">
                                <?= $debit_val > 0 ? formatCurrency($debit_val) : '-' ?>
                            </td>
                            <td class="text-end fw-semibold <?= $credit_val > 0 ? 'text-dark' : 'text-muted' ?>">
                                <?= $credit_val > 0 ? formatCurrency($credit_val) : '-' ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot class="table-light">
                    <tr class="fw-bold fs-6">
                        <td colspan="3" class="text-end">Total Sum:</td>
                        <td class="text-end text-success"><?= formatCurrency($total_debits) ?></td>
                        <td class="text-end text-success"><?= formatCurrency($total_credits) ?></td>
                    </tr>
                    <tr>
                        <td colspan="5" class="text-center py-2">
                            <?php if (abs($total_debits - $total_credits) < 0.05): ?>
                                <span class="badge bg-success px-3 py-2 fs-6">
                                    <i class="bi bi-check-circle-fill me-1"></i> Books Are Perfectly Balanced! Difference: Rs. 0.00
                                </span>
                            <?php else: ?>
                                <span class="badge bg-danger px-3 py-2 fs-6">
                                    <i class="bi bi-exclamation-triangle-fill me-1"></i> Discrepancy Detected: Rs. <?= number_format(abs($total_debits - $total_credits), 2) ?>
                                </span>
                            <?php endif; ?>
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

<?= renderPrintSignatures(['Accountant', 'Internal Auditor', 'Managing Partner']) ?>
<?= renderPrintFooter('General Ledger Trial Balance verified for double-entry debit-credit equality.') ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
