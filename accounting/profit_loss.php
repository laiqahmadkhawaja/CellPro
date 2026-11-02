<?php
// accounting/profit_loss.php - Income Statement (Profit & Loss)
$page_title = 'Profit & Loss Statement';
require_once __DIR__ . '/../includes/header.php';

$branch_id = getActiveBranchId();
$date_from = $_GET['date_from'] ?? date('Y-m-01');
$date_to = $_GET['date_to'] ?? date('Y-m-d');

// Helper to sum group accounts within date range
function getGroupBalances($pdo, $group_type, $date_from, $date_to, $branch_id = null) {
    $sql = "SELECT a.id, a.code, a.name,
                   COALESCE(SUM(jel.debit), 0) as tot_debit,
                   COALESCE(SUM(jel.credit), 0) as tot_credit
            FROM accounts a
            JOIN account_groups ag ON a.group_id = ag.id
            JOIN journal_entry_lines jel ON a.id = jel.account_id
            JOIN journal_entries je ON jel.entry_id = je.id
            WHERE ag.group_type = ? AND je.entry_date >= ? AND je.entry_date <= ?";
    $params = [$group_type, $date_from, $date_to];
    if ($branch_id) {
        $sql .= " AND je.branch_id = ?";
        $params[] = $branch_id;
    }
    $sql .= " GROUP BY a.id, a.code, a.name ORDER BY a.code ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

$revenue_rows = getGroupBalances($pdo, 'revenue', $date_from, $date_to, $branch_id);
$cogs_rows = getGroupBalances($pdo, 'cogs', $date_from, $date_to, $branch_id);
$expense_rows = getGroupBalances($pdo, 'expense', $date_from, $date_to, $branch_id);

$total_revenue = 0;
foreach ($revenue_rows as $r) $total_revenue += ((float)$r['tot_credit'] - (float)$r['tot_debit']);

$total_cogs = 0;
foreach ($cogs_rows as $r) $total_cogs += ((float)$r['tot_debit'] - (float)$r['tot_credit']);

$gross_profit = $total_revenue - $total_cogs;

$total_expenses = 0;
foreach ($expense_rows as $r) $total_expenses += ((float)$r['tot_debit'] - (float)$r['tot_credit']);

$net_profit = $gross_profit - $total_expenses;
?>

<?= renderPrintHeader(
    'INCOME STATEMENT (PROFIT & LOSS)',
    'Financial Performance & Net Earnings',
    [
        'Period' => formatDate($date_from) . ' to ' . formatDate($date_to),
        'Net Sales Revenue' => formatCurrency($total_income ?? 0),
        'Net Operating Profit' => formatCurrency($net_profit ?? 0)
    ]
) ?>

<div class="d-flex justify-content-between align-items-center mb-3 no-print">
    <div>
        <h5 class="fw-bold m-0"><i class="bi bi-graph-up-arrow text-success me-2"></i>Profit & Loss Statement (Income Statement)</h5>
        <small class="text-muted">For period <?= formatDate($date_from) ?> to <?= formatDate($date_to) ?></small>
    </div>
    <div class="d-flex gap-2">
        <button onclick="window.print()" class="btn btn-primary btn-sm no-print">
            <i class="bi bi-printer me-1"></i> Print P&L Statement
        </button>
    </div>
</div>

<!-- Date Filter -->
<div class="card mb-3 no-print">
    <div class="card-body p-3">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-6 col-md-5">
                <label class="form-label small text-muted">Date From</label>
                <input type="date" name="date_from" class="form-control form-control-sm" value="<?= htmlspecialchars($date_from) ?>">
            </div>
            <div class="col-6 col-md-5">
                <label class="form-label small text-muted">Date To</label>
                <input type="date" name="date_to" class="form-control form-control-sm" value="<?= htmlspecialchars($date_to) ?>">
            </div>
            <div class="col-12 col-md-2">
                <button type="submit" class="btn btn-sm btn-primary w-100">Calculate</button>
            </div>
        </form>
    </div>
</div>

<div class="card mx-auto" style="max-width: 800px;">
    <div class="card-header bg-light text-center py-3">
        <h5 class="fw-bold mb-0 text-primary"><?= htmlspecialchars(getSetting('business_name', 'CellPro Mobile World')) ?></h5>
        <div class="fw-semibold text-dark">Statement of Profit & Loss</div>
        <small class="text-muted"><?= formatDate($date_from) ?> &mdash; <?= formatDate($date_to) ?></small>
    </div>
    <div class="card-body p-0">
        <table class="table table-bordered mb-0 align-middle">
            <!-- 1. Operating Revenue -->
            <thead class="table-light">
                <tr>
                    <th colspan="2" class="fw-bold text-primary text-uppercase">1. Operating Revenue</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($revenue_rows)): ?>
                    <tr><td colspan="2" class="text-muted small ps-4 py-2">No revenue recorded in this period.</td></tr>
                <?php else: ?>
                    <?php foreach ($revenue_rows as $rev): ?>
                        <?php $amt = (float)$rev['tot_credit'] - (float)$rev['tot_debit']; ?>
                        <tr>
                            <td class="ps-4"><?= htmlspecialchars($rev['code']) ?> - <?= htmlspecialchars($rev['name']) ?></td>
                            <td class="text-end fw-semibold" style="width: 200px;"><?= formatCurrency($amt) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                <tr class="table-light fw-bold">
                    <td class="ps-4">Total Operating Revenue:</td>
                    <td class="text-end text-success fs-6"><?= formatCurrency($total_revenue) ?></td>
                </tr>
            </tbody>

            <!-- 2. Cost of Goods Sold -->
            <thead class="table-light">
                <tr>
                    <th colspan="2" class="fw-bold text-danger text-uppercase">2. Cost of Goods Sold (COGS)</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($cogs_rows)): ?>
                    <tr><td colspan="2" class="text-muted small ps-4 py-2">No cost of goods sold recorded.</td></tr>
                <?php else: ?>
                    <?php foreach ($cogs_rows as $cogs): ?>
                        <?php $amt = (float)$cogs['tot_debit'] - (float)$cogs['tot_credit']; ?>
                        <tr>
                            <td class="ps-4"><?= htmlspecialchars($cogs['code']) ?> - <?= htmlspecialchars($cogs['name']) ?></td>
                            <td class="text-end text-danger fw-semibold" style="width: 200px;"><?= formatCurrency($amt) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                <tr class="table-light fw-bold">
                    <td class="ps-4">Total Cost of Goods Sold:</td>
                    <td class="text-end text-danger fs-6"><?= formatCurrency($total_cogs) ?></td>
                </tr>
            </tbody>

            <!-- GROSS PROFIT -->
            <tr class="table-primary border-2 fw-bold fs-6">
                <td class="ps-4">
                    GROSS PROFIT
                    <small class="text-muted fw-normal ms-2">
                        (Margin: <?= $total_revenue > 0 ? round(($gross_profit / $total_revenue) * 100, 1) : 0 ?>%)
                    </small>
                </td>
                <td class="text-end text-primary"><?= formatCurrency($gross_profit) ?></td>
            </tr>

            <!-- 3. Operating Expenses -->
            <thead class="table-light">
                <tr>
                    <th colspan="2" class="fw-bold text-danger text-uppercase">3. Operating Expenses</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($expense_rows)): ?>
                    <tr><td colspan="2" class="text-muted small ps-4 py-2">No operating expenses recorded.</td></tr>
                <?php else: ?>
                    <?php foreach ($expense_rows as $exp): ?>
                        <?php $amt = (float)$exp['tot_debit'] - (float)$exp['tot_credit']; ?>
                        <tr>
                            <td class="ps-4"><?= htmlspecialchars($exp['code']) ?> - <?= htmlspecialchars($exp['name']) ?></td>
                            <td class="text-end text-danger fw-semibold" style="width: 200px;"><?= formatCurrency($amt) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                <tr class="table-light fw-bold">
                    <td class="ps-4">Total Operating Expenses:</td>
                    <td class="text-end text-danger fs-6"><?= formatCurrency($total_expenses) ?></td>
                </tr>
            </tbody>

            <!-- NET PROFIT -->
            <tr class="<?= $net_profit >= 0 ? 'table-success' : 'table-danger' ?> border-3 fw-bold fs-5">
                <td class="ps-4"><?= $net_profit >= 0 ? 'NET PROFIT' : 'NET LOSS' ?>:</td>
                <td class="text-end <?= $net_profit >= 0 ? 'text-success' : 'text-danger' ?>"><?= formatCurrency($net_profit) ?></td>
            </tr>
        </table>
    </div>
</div>

<?= renderPrintSignatures(['Prepared By (Accountant)', 'Finance Controller', 'Managing Director']) ?>
<?= renderPrintFooter('Statement of Profit & Loss prepared in accordance with accrual accounting principles.') ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
