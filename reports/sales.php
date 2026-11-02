<?php
// reports/sales.php - Item-Wise Profit & Sales Report
$page_title = 'Item-Wise Profit & Sales Report';
require_once __DIR__ . '/../includes/header.php';

$branch_id = getActiveBranchId();
$date_from = $_GET['date_from'] ?? date('Y-m-01');
$date_to = $_GET['date_to'] ?? date('Y-m-d');
$type_filter = $_GET['type'] ?? 'all';

$sql = "SELECT si.*, s.invoice_no, s.sale_date, b.name as branch_name, c.name as customer_name
        FROM sale_items si
        JOIN sales s ON si.sale_id = s.id
        JOIN branches b ON s.branch_id = b.id
        LEFT JOIN customers c ON s.customer_id = c.id
        WHERE s.sale_date >= ? AND s.sale_date <= ?";
$params = [$date_from, $date_to];

if ($branch_id) {
    $sql .= " AND s.branch_id = ?";
    $params[] = $branch_id;
}
if ($type_filter !== 'all') {
    $sql .= " AND si.item_type = ?";
    $params[] = $type_filter;
}

$sql .= " ORDER BY s.sale_date DESC, si.id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$items = $stmt->fetchAll();

$tot_qty = 0;
$tot_sales = 0;
$tot_cogs = 0;
$tot_profit = 0;

foreach ($items as $it) {
    $tot_qty += (int)$it['quantity'];
    $tot_sales += (float)$it['total_price'];
    $tot_cogs += ((float)$it['unit_cost'] * (int)$it['quantity']);
    $tot_profit += (float)$it['gross_profit'];
}
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h5 class="fw-bold m-0"><i class="bi bi-file-earmark-bar-graph text-primary me-2"></i>Item-Wise Profit & Sales Report</h5>
        <small class="text-muted">Exact gross profit tracked against actual device IMEI acquisition costs</small>
    </div>
    <div class="d-flex gap-2">
        <button onclick="window.print()" class="btn btn-primary btn-sm no-print">
            <i class="bi bi-printer me-1"></i> Print Report
        </button>
    </div>
</div>

<!-- Filters -->
<div class="card mb-3 no-print">
    <div class="card-body p-3">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-6 col-md-3">
                <label class="form-label small text-muted">Item Type</label>
                <select name="type" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="all">All Items</option>
                    <option value="mobile" <?= $type_filter === 'mobile' ? 'selected' : '' ?>>Mobile Phones Only</option>
                    <option value="accessory" <?= $type_filter === 'accessory' ? 'selected' : '' ?>>Accessories Only</option>
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
            <div class="col-6 col-md-3">
                <button type="submit" class="btn btn-sm btn-primary w-100">Filter Analysis</button>
            </div>
        </form>
    </div>
</div>

<!-- Stat Cards -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div>
                <small class="text-muted text-uppercase fw-semibold" style="font-size:0.75rem;">Total Net Sales</small>
                <h4 class="fw-bold m-0 text-dark"><?= formatCurrency($tot_sales) ?></h4>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div>
                <small class="text-muted text-uppercase fw-semibold" style="font-size:0.75rem;">Total Inventory Cost</small>
                <h4 class="fw-bold m-0 text-danger"><?= formatCurrency($tot_cogs) ?></h4>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div>
                <small class="text-muted text-uppercase fw-semibold" style="font-size:0.75rem;">Gross Profit Realized</small>
                <h4 class="fw-bold m-0 text-success"><?= formatCurrency($tot_profit) ?></h4>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div>
                <small class="text-muted text-uppercase fw-semibold" style="font-size:0.75rem;">Overall Margin</small>
                <h4 class="fw-bold m-0 text-primary"><?= $tot_sales > 0 ? round(($tot_profit / $tot_sales) * 100, 1) : 0 ?>%</h4>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-body p-0">
        <?php if (empty($items)): ?>
            <div class="text-center py-5 text-muted">No sales items recorded in this date range.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-bordered table-hover align-middle mb-0">
                    <thead class="table-light small">
                        <tr>
                            <th>Date</th>
                            <th>Invoice #</th>
                            <th>Item Description</th>
                            <th>IMEI / Identifiers</th>
                            <th class="text-center">Qty</th>
                            <th class="text-end">Actual Cost</th>
                            <th class="text-end">Sale Price</th>
                            <th class="text-end">Net Total</th>
                            <th class="text-end">Gross Profit</th>
                            <th class="text-end">Margin</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $row): ?>
                            <?php 
                            $line_cogs = (float)$row['unit_cost'] * (int)$row['quantity'];
                            $margin = $row['total_price'] > 0 ? round(($row['gross_profit'] / $row['total_price']) * 100, 1) : 0;
                            ?>
                            <tr>
                                <td><?= formatDate($row['sale_date']) ?></td>
                                <td class="fw-bold">
                                    <a href="<?= BASE_URL ?>pos/invoice.php?id=<?= $row['sale_id'] ?>" target="_blank" class="text-primary text-decoration-none">
                                        <?= htmlspecialchars($row['invoice_no']) ?>
                                    </a>
                                </td>
                                <td>
                                    <div class="fw-bold text-dark"><?= htmlspecialchars($row['item_name']) ?></div>
                                    <span class="badge bg-secondary text-uppercase" style="font-size:0.65rem;"><?= $row['item_type'] ?></span>
                                </td>
                                <td>
                                    <?= $row['imei'] ? '<span class="font-monospace small fw-semibold">' . htmlspecialchars($row['imei']) . '</span>' : '-' ?>
                                </td>
                                <td class="text-center"><?= $row['quantity'] ?></td>
                                <td class="text-end text-muted"><?= formatCurrency($line_cogs) ?></td>
                                <td class="text-end"><?= formatCurrency($row['unit_price']) ?></td>
                                <td class="text-end fw-semibold"><?= formatCurrency($row['total_price']) ?></td>
                                <td class="text-end fw-bold text-success"><?= formatCurrency($row['gross_profit']) ?></td>
                                <td class="text-end fw-semibold <?= $margin >= 15 ? 'text-success' : 'text-warning' ?>"><?= $margin ?>%</td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot class="table-light fw-bold fs-6">
                        <tr>
                            <td colspan="4" class="text-end">Totals:</td>
                            <td class="text-center"><?= $tot_qty ?></td>
                            <td class="text-end text-danger"><?= formatCurrency($tot_cogs) ?></td>
                            <td></td>
                            <td class="text-end text-dark"><?= formatCurrency($tot_sales) ?></td>
                            <td class="text-end text-success"><?= formatCurrency($tot_profit) ?></td>
                            <td class="text-end text-primary"><?= $tot_sales > 0 ? round(($tot_profit / $tot_sales) * 100, 1) : 0 ?>%</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
