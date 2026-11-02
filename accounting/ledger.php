<?php
// accounting/ledger.php - General Ledger Statement per Account
$page_title = 'General Ledger Account';
require_once __DIR__ . '/../includes/header.php';

$branch_id = getActiveBranchId();
$all_accounts = $pdo->query("SELECT a.*, ag.name as group_name, ag.group_type 
                             FROM accounts a 
                             JOIN account_groups ag ON a.group_id = ag.id 
                             WHERE a.status = 'active' 
                             ORDER BY a.code ASC")->fetchAll();

$account_id = (int)($_GET['account_id'] ?? ($all_accounts[0]['id'] ?? 1));
$date_from = $_GET['date_from'] ?? date('Y-m-01');
$date_to = $_GET['date_to'] ?? date('Y-m-d');

// Selected Account info
$stmt = $pdo->prepare("SELECT a.*, ag.name as group_name, ag.group_type 
                       FROM accounts a 
                       JOIN account_groups ag ON a.group_id = ag.id 
                       WHERE a.id = ?");
$stmt->execute([$account_id]);
$account = $stmt->fetch();

$is_asset_or_expense = in_array($account['group_type'], ['asset', 'expense', 'cogs']);

// 1. Opening Balance prior to $date_from
$sql = "SELECT COALESCE(SUM(jel.debit), 0) as tot_debit, COALESCE(SUM(jel.credit), 0) as tot_credit
        FROM journal_entry_lines jel
        JOIN journal_entries je ON jel.entry_id = je.id
        WHERE jel.account_id = ? AND je.entry_date < ?";
$params = [$account_id, $date_from];
if ($branch_id) {
    $sql .= " AND je.branch_id = ?";
    $params[] = $branch_id;
}
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$prior = $stmt->fetch();

if ($is_asset_or_expense) {
    $opening_balance = (float)$prior['tot_debit'] - (float)$prior['tot_credit'];
} else {
    $opening_balance = (float)$prior['tot_credit'] - (float)$prior['tot_debit'];
}

// 2. Lines within the date range
$sql = "SELECT jel.*, je.entry_no, je.entry_date, je.reference_type, je.narration, b.name as branch_name
        FROM journal_entry_lines jel
        JOIN journal_entries je ON jel.entry_id = je.id
        JOIN branches b ON je.branch_id = b.id
        WHERE jel.account_id = ? AND je.entry_date >= ? AND je.entry_date <= ?";
$params = [$account_id, $date_from, $date_to];
if ($branch_id) {
    $sql .= " AND je.branch_id = ?";
    $params[] = $branch_id;
}
$sql .= " ORDER BY je.entry_date ASC, je.id ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$lines = $stmt->fetchAll();
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h5 class="fw-bold m-0"><i class="bi bi-journal-album text-primary me-2"></i>General Ledger Statement</h5>
        <small class="text-muted"><?= htmlspecialchars($account['code']) ?> - <?= htmlspecialchars($account['name']) ?> (<?= htmlspecialchars($account['group_name']) ?>)</small>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= BASE_URL ?>accounting/coa.php" class="btn btn-outline-secondary btn-sm no-print">
            <i class="bi bi-diagram-3 me-1"></i> Chart of Accounts
        </a>
        <button onclick="window.print()" class="btn btn-primary btn-sm no-print">
            <i class="bi bi-printer me-1"></i> Print Statement
        </button>
    </div>
</div>

<!-- Account & Date Range Filter -->
<div class="card mb-3 no-print">
    <div class="card-body p-3">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-12 col-md-5">
                <label class="form-label small text-muted">Select Ledger Account</label>
                <select name="account_id" class="form-select form-select-sm" onchange="this.form.submit()">
                    <?php foreach ($all_accounts as $a): ?>
                        <option value="<?= $a['id'] ?>" <?= $a['id'] == $account_id ? 'selected' : '' ?>>
                            <?= htmlspecialchars($a['code']) ?> - <?= htmlspecialchars($a['name']) ?> (<?= ucfirst($a['group_type']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label small text-muted">Date From</label>
                <input type="date" name="date_from" class="form-control form-control-sm" value="<?= htmlspecialchars($date_from) ?>">
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label small text-muted">Date To</label>
                <input type="date" name="date_to" class="form-control form-control-sm" value="<?= htmlspecialchars($date_to) ?>">
            </div>
            <div class="col-12 col-md-1">
                <button type="submit" class="btn btn-sm btn-primary w-100">Filter</button>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header bg-light d-flex justify-content-between align-items-center">
        <span class="fw-bold"><?= htmlspecialchars($account['code']) ?>: <?= htmlspecialchars($account['name']) ?></span>
        <span class="badge bg-light text-dark border">
            Statement Period: <?= formatDate($date_from) ?> to <?= formatDate($date_to) ?>
        </span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-bordered table-hover align-middle mb-0">
                <thead class="table-light small">
                    <tr>
                        <th style="width: 120px;">Date</th>
                        <th style="width: 130px;">Voucher #</th>
                        <th>Narration / Particulars</th>
                        <th class="text-end" style="width: 130px;">Debit (+)</th>
                        <th class="text-end" style="width: 130px;">Credit (-)</th>
                        <th class="text-end" style="width: 140px;">Running Balance</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- Opening Balance Row -->
                    <tr class="table-light fw-semibold">
                        <td><?= formatDate($date_from) ?></td>
                        <td><span class="badge bg-secondary">OPENING</span></td>
                        <td>Balance Brought Forward (B/F)</td>
                        <td class="text-end"><?= $opening_balance > 0 && $is_asset_or_expense ? formatCurrency($opening_balance) : '-' ?></td>
                        <td class="text-end"><?= $opening_balance > 0 && !$is_asset_or_expense ? formatCurrency($opening_balance) : '-' ?></td>
                        <td class="text-end fw-bold text-dark"><?= formatCurrency($opening_balance) ?></td>
                    </tr>

                    <?php 
                    $running = $opening_balance;
                    $period_debits = 0;
                    $period_credits = 0;

                    foreach ($lines as $l): 
                        $d = (float)$l['debit'];
                        $c = (float)$l['credit'];
                        $period_debits += $d;
                        $period_credits += $c;

                        if ($is_asset_or_expense) {
                            $running += ($d - $c);
                        } else {
                            $running += ($c - $d);
                        }
                    ?>
                        <tr>
                            <td><?= formatDate($l['entry_date']) ?></td>
                            <td class="font-monospace fw-semibold text-primary"><?= htmlspecialchars($l['entry_no']) ?></td>
                            <td>
                                <div><?= htmlspecialchars($l['description'] ?: $l['narration']) ?></div>
                                <small class="text-muted">Ref: <?= htmlspecialchars($l['reference_type']) ?> &bull; <?= htmlspecialchars($l['branch_name']) ?></small>
                            </td>
                            <td class="text-end text-success fw-semibold"><?= $d > 0 ? formatCurrency($d) : '-' ?></td>
                            <td class="text-end text-danger fw-semibold"><?= $c > 0 ? formatCurrency($c) : '-' ?></td>
                            <td class="text-end fw-bold text-dark"><?= formatCurrency($running) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot class="table-light">
                    <tr>
                        <td colspan="3" class="text-end fw-bold">Period Totals:</td>
                        <td class="text-end fw-bold text-success fs-6"><?= formatCurrency($period_debits) ?></td>
                        <td class="text-end fw-bold text-danger fs-6"><?= formatCurrency($period_credits) ?></td>
                        <td class="text-end fw-bold text-primary fs-6"><?= formatCurrency($running) ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
