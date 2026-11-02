<?php
// closing/index.php - Daily Branch Closings History
$page_title = 'Daily Branch Closing';
require_once __DIR__ . '/../includes/header.php';

$branch_id = getActiveBranchId();

$sql = "SELECT dc.*, b.name as branch_name, u.name as closed_by_name
        FROM daily_closings dc
        JOIN branches b ON dc.branch_id = b.id
        LEFT JOIN users u ON dc.closed_by = u.id
        WHERE 1=1";
$params = [];

if ($branch_id) {
    $sql .= " AND dc.branch_id = ?";
    $params[] = $branch_id;
}
$sql .= " ORDER BY dc.closing_date DESC LIMIT 30";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$closings = $stmt->fetchAll();
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h5 class="fw-bold m-0"><i class="bi bi-check2-circle text-success me-2"></i>Daily Branch Cash Closing</h5>
        <small class="text-muted">End-of-day register reconciliation, cash drawer auditing and discrepancy tracking</small>
    </div>
    <a href="<?= BASE_URL ?>closing/create.php" class="btn btn-success btn-sm shadow-sm">
        <i class="bi bi-safe me-1"></i> Perform Today's Closing
    </a>
</div>

<div class="card">
    <div class="card-body p-0">
        <?php if (empty($closings)): ?>
            <div class="text-center py-5 text-muted">
                <i class="bi bi-safe fs-1 mb-2 d-block opacity-50"></i>
                No daily closings recorded yet. Click <strong>Perform Today's Closing</strong> to close your drawer.
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Branch</th>
                            <th class="text-end">Opening Cash</th>
                            <th class="text-end">Cash Sales</th>
                            <th class="text-end">Cash Expenses</th>
                            <th class="text-end">Expected Cash</th>
                            <th class="text-end">Actual Cash</th>
                            <th class="text-end">Difference</th>
                            <th>Closed By</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($closings as $c): ?>
                            <tr>
                                <td class="fw-bold text-dark"><?= formatDate($c['closing_date']) ?></td>
                                <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($c['branch_name']) ?></span></td>
                                <td class="text-end"><?= formatCurrency($c['opening_cash']) ?></td>
                                <td class="text-end text-success fw-semibold"><?= formatCurrency($c['cash_sales']) ?></td>
                                <td class="text-end text-danger"><?= formatCurrency($c['cash_expenses']) ?></td>
                                <td class="text-end fw-semibold"><?= formatCurrency($c['expected_closing_cash']) ?></td>
                                <td class="text-end fw-bold text-primary"><?= formatCurrency($c['actual_closing_cash']) ?></td>
                                <td class="text-end fw-bold <?= $c['difference'] < 0 ? 'text-danger' : ($c['difference'] > 0 ? 'text-success' : 'text-muted') ?>">
                                    <?= formatCurrency($c['difference']) ?>
                                </td>
                                <td class="small text-muted"><?= htmlspecialchars($c['closed_by_name'] ?? 'Manager') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
