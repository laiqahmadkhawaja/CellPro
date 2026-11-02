<?php
// repairs/index.php - Simple Mobile Repairing Bills List
$page_title = 'Mobile Repairing Bills';
require_once __DIR__ . '/../includes/header.php';

$branch_id = getActiveBranchId();
$search = trim($_GET['search'] ?? '');

$sql = "SELECT rb.*, b.name as branch_name, u.name as technician_name 
        FROM repairing_bills rb
        LEFT JOIN branches b ON rb.branch_id = b.id
        LEFT JOIN users u ON rb.created_by = u.id
        WHERE 1=1";
$params = [];

if ($branch_id) {
    $sql .= " AND rb.branch_id = ?";
    $params[] = $branch_id;
}
if (!empty($search)) {
    $sql .= " AND (rb.bill_no LIKE ? OR rb.customer_name LIKE ? OR rb.customer_phone LIKE ? OR rb.mobile_model LIKE ? OR rb.imei LIKE ?)";
    $t = "%{$search}%";
    $params = array_merge($params, [$t, $t, $t, $t, $t]);
}

$sql .= " ORDER BY rb.id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$bills = $stmt->fetchAll();
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h5 class="fw-bold m-0"><i class="bi bi-tools text-warning me-2"></i>Mobile Repairing Bills</h5>
        <small class="text-muted">Simple service billing for labour, screen replacements, and repair spare parts</small>
    </div>
    <a href="<?= BASE_URL ?>repairs/create.php" class="btn btn-primary btn-sm shadow-sm">
        <i class="bi bi-plus-circle me-1"></i> New Repair Bill
    </a>
</div>

<!-- Search Filter -->
<div class="card mb-3">
    <div class="card-body p-3">
        <form method="GET" class="row g-2">
            <div class="col-12 col-md-10">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                    <input type="text" name="search" class="form-control" placeholder="Search bill #, customer name, phone, model or IMEI..." value="<?= htmlspecialchars($search) ?>">
                </div>
            </div>
            <div class="col-12 col-md-2">
                <button type="submit" class="btn btn-sm btn-primary w-100">Search</button>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body p-0">
        <?php if (empty($bills)): ?>
            <div class="text-center py-5 text-muted">
                <i class="bi bi-tools fs-1 mb-2 d-block opacity-50"></i>
                No repair bills found. Click <strong>New Repair Bill</strong> to record a repair service.
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Bill #</th>
                            <th>Date</th>
                            <th>Customer</th>
                            <th>Device Details</th>
                            <th>Labour Charges</th>
                            <th>Spare Parts</th>
                            <th>Total Bill</th>
                            <th>Paid</th>
                            <th>Status</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($bills as $b): ?>
                            <tr>
                                <td class="fw-bold text-primary"><?= htmlspecialchars($b['bill_no']) ?></td>
                                <td><?= formatDate($b['repair_date']) ?></td>
                                <td>
                                    <div class="fw-semibold text-dark"><?= htmlspecialchars($b['customer_name']) ?></div>
                                    <small class="text-muted"><?= htmlspecialchars($b['customer_phone']) ?></small>
                                </td>
                                <td>
                                    <div class="fw-bold"><?= htmlspecialchars($b['mobile_brand']) ?> <?= htmlspecialchars($b['mobile_model']) ?></div>
                                    <?= $b['imei'] ? '<small class="text-muted font-monospace">IMEI: ' . htmlspecialchars($b['imei']) . '</small>' : '' ?>
                                </td>
                                <td><?= formatCurrency($b['labour_charges']) ?></td>
                                <td><?= formatCurrency($b['parts_charges']) ?></td>
                                <td class="fw-bold text-dark"><?= formatCurrency($b['total_amount']) ?></td>
                                <td class="text-success fw-semibold"><?= formatCurrency($b['paid_amount']) ?></td>
                                <td>
                                    <?= $b['status'] === 'delivered' ? '<span class="badge bg-success">Delivered</span>' : '<span class="badge bg-warning">Completed</span>' ?>
                                </td>
                                <td class="text-end">
                                    <a href="<?= BASE_URL ?>repairs/view.php?id=<?= $b['id'] ?>" class="btn btn-sm btn-outline-primary" title="View & Print Bill">
                                        <i class="bi bi-printer"></i> Bill
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
