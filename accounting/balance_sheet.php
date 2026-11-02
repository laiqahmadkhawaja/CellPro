<?php
// accounting/balance_sheet.php - Statement of Financial Position (Balance Sheet)
$page_title = 'Balance Sheet';
require_once __DIR__ . '/../includes/header.php';

$branch_id = getActiveBranchId();
$as_of_date = $_GET['as_of'] ?? date('Y-m-d');

// Helper to calculate total for an account group up to $as_of_date
function getAccountsByGroupType($pdo, $group_type, $as_of_date, $branch_id = null) {
    $sql = "SELECT a.id, a.code, a.name,
                   COALESCE(SUM(jel.debit), 0) as tot_debit,
                   COALESCE(SUM(jel.credit), 0) as tot_credit
            FROM accounts a
            JOIN account_groups ag ON a.group_id = ag.id
            LEFT JOIN journal_entry_lines jel ON a.id = jel.account_id
            LEFT JOIN journal_entries je ON jel.entry_id = je.id AND je.entry_date <= ? " . ($branch_id ? "AND je.branch_id = $branch_id" : "") . "
            WHERE ag.group_type = ? AND a.status = 'active'
            GROUP BY a.id, a.code, a.name
            ORDER BY a.code ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$as_of_date, $group_type]);
    return $stmt->fetchAll();
}

// 1. Assets
$asset_rows = getAccountsByGroupType($pdo, 'asset', $as_of_date, $branch_id);
$total_assets = 0;
foreach ($asset_rows as $a) {
    $total_assets += ((float)$a['tot_debit'] - (float)$a['tot_credit']);
}

// 2. Liabilities
$liab_rows = getAccountsByGroupType($pdo, 'liability', $as_of_date, $branch_id);
$total_liabilities = 0;
foreach ($liab_rows as $l) {
    $total_liabilities += ((float)$l['tot_credit'] - (float)$l['tot_debit']);
}

// 3. Equity
$equity_rows = getAccountsByGroupType($pdo, 'equity', $as_of_date, $branch_id);
$total_equity_base = 0;
foreach ($equity_rows as $eq) {
    $total_equity_base += ((float)$eq['tot_credit'] - (float)$eq['tot_debit']);
}

// 4. Retained Net Income (Revenue - COGS - Expenses)
$rev_rows = getAccountsByGroupType($pdo, 'revenue', $as_of_date, $branch_id);
$tot_rev = 0;
foreach ($rev_rows as $r) $tot_rev += ((float)$r['tot_credit'] - (float)$r['tot_debit']);

$cogs_rows = getAccountsByGroupType($pdo, 'cogs', $as_of_date, $branch_id);
$tot_cogs = 0;
foreach ($cogs_rows as $c) $tot_cogs += ((float)$c['tot_debit'] - (float)$c['tot_credit']);

$exp_rows = getAccountsByGroupType($pdo, 'expense', $as_of_date, $branch_id);
$tot_exp = 0;
foreach ($exp_rows as $e) $tot_exp += ((float)$e['tot_debit'] - (float)$e['tot_credit']);

$retained_profit = $tot_rev - $tot_cogs - $tot_exp;
$total_equity = $total_equity_base + $retained_profit;
$total_liabilities_equity = $total_liabilities + $total_equity;
?>

<?= renderPrintHeader(
    'STATEMENT OF FINANCIAL POSITION',
    'Balance Sheet & Capital Adequacy',
    [
        'As of Date' => formatDate($as_of_date),
        'Total Assets' => formatCurrency($total_assets ?? 0),
        'Total Liabilities & Equity' => formatCurrency(($total_liabilities ?? 0) + ($total_equity_with_pnl ?? 0))
    ]
) ?>

<div class="d-flex justify-content-between align-items-center mb-3 no-print">
    <div>
        <h5 class="fw-bold m-0"><i class="bi bi-pie-chart text-primary me-2"></i>Statement of Financial Position (Balance Sheet)</h5>
        <small class="text-muted">As of <?= formatDate($as_of_date) ?></small>
    </div>
    <div class="d-flex gap-2">
        <button onclick="window.print()" class="btn btn-primary btn-sm no-print">
            <i class="bi bi-printer me-1"></i> Print Balance Sheet
        </button>
    </div>
</div>

<!-- Date Filter -->
<div class="card mb-3 no-print">
    <div class="card-body p-3">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-12 col-md-4">
                <label class="form-label small text-muted">As Of Date</label>
                <input type="date" name="as_of" class="form-control form-control-sm" value="<?= htmlspecialchars($as_of_date) ?>">
            </div>
            <div class="col-12 col-md-2">
                <button type="submit" class="btn btn-sm btn-primary w-100">Calculate</button>
            </div>
        </form>
    </div>
</div>

<div class="card mx-auto" style="max-width: 850px;">
    <div class="card-header bg-light text-center py-3">
        <h5 class="fw-bold mb-0 text-primary"><?= htmlspecialchars(getSetting('business_name', 'CellPro Mobile World')) ?></h5>
        <div class="fw-semibold text-dark">Balance Sheet Statement</div>
        <small class="text-muted">As of <?= formatDate($as_of_date) ?></small>
    </div>
    <div class="card-body p-0">
        <table class="table table-bordered mb-0 align-middle">
            <!-- ASSETS -->
            <thead class="table-primary">
                <tr>
                    <th colspan="2" class="fw-bold text-uppercase fs-6">Assets</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($asset_rows as $a): ?>
                    <?php $bal = (float)$a['tot_debit'] - (float)$a['tot_credit']; ?>
                    <tr>
                        <td class="ps-4"><?= htmlspecialchars($a['code']) ?> - <?= htmlspecialchars($a['name']) ?></td>
                        <td class="text-end fw-semibold" style="width: 220px;"><?= formatCurrency($bal) ?></td>
                    </tr>
                <?php endforeach; ?>
                <tr class="table-light fw-bold fs-6">
                    <td class="ps-4">TOTAL ASSETS:</td>
                    <td class="text-end text-primary"><?= formatCurrency($total_assets) ?></td>
                </tr>
            </tbody>

            <!-- LIABILITIES -->
            <thead class="table-danger">
                <tr>
                    <th colspan="2" class="fw-bold text-uppercase fs-6">Liabilities</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($liab_rows as $l): ?>
                    <?php $bal = (float)$l['tot_credit'] - (float)$l['tot_debit']; ?>
                    <tr>
                        <td class="ps-4"><?= htmlspecialchars($l['code']) ?> - <?= htmlspecialchars($l['name']) ?></td>
                        <td class="text-end fw-semibold" style="width: 220px;"><?= formatCurrency($bal) ?></td>
                    </tr>
                <?php endforeach; ?>
                <tr class="table-light fw-bold">
                    <td class="ps-4">Total Liabilities:</td>
                    <td class="text-end text-danger"><?= formatCurrency($total_liabilities) ?></td>
                </tr>
            </tbody>

            <!-- EQUITY -->
            <thead class="table-success">
                <tr>
                    <th colspan="2" class="fw-bold text-uppercase fs-6">Equity & Retained Earnings</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($equity_rows as $eq): ?>
                    <?php $bal = (float)$eq['tot_credit'] - (float)$eq['tot_debit']; ?>
                    <tr>
                        <td class="ps-4"><?= htmlspecialchars($eq['code']) ?> - <?= htmlspecialchars($eq['name']) ?></td>
                        <td class="text-end fw-semibold" style="width: 220px;"><?= formatCurrency($bal) ?></td>
                    </tr>
                <?php endforeach; ?>
                <tr>
                    <td class="ps-4">Accumulated Operating Profit (Retained Earnings)</td>
                    <td class="text-end fw-bold text-success" style="width: 220px;"><?= formatCurrency($retained_profit) ?></td>
                </tr>
                <tr class="table-light fw-bold">
                    <td class="ps-4">Total Owner Equity:</td>
                    <td class="text-end text-success"><?= formatCurrency($total_equity) ?></td>
                </tr>
            </tbody>

            <!-- TOTAL LIABILITIES & EQUITY -->
            <tr class="table-dark fw-bold fs-5 text-white">
                <td class="ps-4">TOTAL LIABILITIES & EQUITY:</td>
                <td class="text-end text-white"><?= formatCurrency($total_liabilities_equity) ?></td>
            </tr>
        </table>
    </div>
</div>

<?= renderPrintSignatures(['Prepared By (Accountant)', 'Verified By (Auditor)', 'Director / Proprietor']) ?>
<?= renderPrintFooter('Certified Statement of Financial Position generated from Double-Entry General Ledger.') ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
