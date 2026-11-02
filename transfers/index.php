<?php
// transfers/index.php - Inter-Branch Stock Transfers
$page_title = 'Branch Stock Transfers';
require_once __DIR__ . '/../includes/header.php';

$branch_id = getActiveBranchId();

$sql = "SELECT st.*, b1.name as from_branch_name, b2.name as to_branch_name, 
               u1.name as creator_name, u2.name as receiver_name
        FROM stock_transfers st
        JOIN branches b1 ON st.from_branch_id = b1.id
        JOIN branches b2 ON st.to_branch_id = b2.id
        LEFT JOIN users u1 ON st.created_by = u1.id
        LEFT JOIN users u2 ON st.received_by = u2.id
        WHERE 1=1";
$params = [];

if ($branch_id) {
    $sql .= " AND (st.from_branch_id = ? OR st.to_branch_id = ?)";
    $params = [$branch_id, $branch_id];
}
$sql .= " ORDER BY st.id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$transfers = $stmt->fetchAll();
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h5 class="fw-bold m-0"><i class="bi bi-arrow-left-right text-info me-2"></i>Inter-Branch Stock Transfers</h5>
        <small class="text-muted">Move mobile devices and accessories between branch stores without creating artificial sales</small>
    </div>
    <a href="<?= BASE_URL ?>transfers/create.php" class="btn btn-primary btn-sm shadow-sm">
        <i class="bi bi-plus-circle me-1"></i> New Transfer Request
    </a>
</div>

<div class="card">
    <div class="card-body p-0">
        <?php if (empty($transfers)): ?>
            <div class="text-center py-5 text-muted">
                <i class="bi bi-truck fs-1 mb-2 d-block opacity-50"></i>
                No stock transfers found. Click <strong>New Transfer Request</strong> to transfer inventory.
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Transfer #</th>
                            <th>Date</th>
                            <th>From Branch</th>
                            <th>To Destination</th>
                            <th class="text-center">Total Items</th>
                            <th>Status</th>
                            <th>Initiated By</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($transfers as $t): ?>
                            <tr>
                                <td class="fw-bold text-primary"><?= htmlspecialchars($t['transfer_no']) ?></td>
                                <td><?= formatDate($t['created_at']) ?></td>
                                <td>
                                    <span class="badge bg-light text-dark border"><?= htmlspecialchars($t['from_branch_name']) ?></span>
                                </td>
                                <td>
                                    <span class="badge bg-info-subtle text-info border"><?= htmlspecialchars($t['to_branch_name']) ?></span>
                                </td>
                                <td class="text-center fw-bold"><?= $t['total_items'] ?></td>
                                <td><?= getStatusBadge($t['status']) ?></td>
                                <td><?= htmlspecialchars($t['creator_name'] ?? 'Staff') ?></td>
                                <td class="text-end">
                                    <a href="<?= BASE_URL ?>transfers/view.php?id=<?= $t['id'] ?>" class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-eye"></i> Details / Receive
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
