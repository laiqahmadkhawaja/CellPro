<?php
// branches/index.php - Multi-Branch Management
$page_title = 'Branch Management';
require_once __DIR__ . '/../includes/header.php';

requireRole(['Super Administrator']);

$branches = $pdo->query("SELECT b.*, 
                                (SELECT COUNT(id) FROM mobile_devices WHERE branch_id = b.id AND device_status = 'available') as phone_count,
                                (SELECT COALESCE(SUM(quantity), 0) FROM products WHERE branch_id = b.id AND is_active = 1) as acc_count,
                                (SELECT COALESCE(SUM(current_balance), 0) FROM financial_accounts WHERE branch_id = b.id AND status = 'active') as cash_liquidity
                         FROM branches b 
                         ORDER BY b.id ASC")->fetchAll();

// Handle Add/Edit Branch
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validatePostCsrf();
    $action = $_POST['action'] ?? 'create';
    $name = sanitize($_POST['name'] ?? '');
    $code = sanitize($_POST['code'] ?? '');
    $address = sanitize($_POST['address'] ?? '');
    $phone = sanitize($_POST['phone'] ?? '');
    $manager = sanitize($_POST['manager_name'] ?? '');
    $prefix = sanitize($_POST['invoice_prefix'] ?? 'INV');
    $opening_date = sanitize($_POST['opening_date'] ?? date('Y-m-d'));

    if (empty($name) || empty($code)) {
        flash('danger', 'Branch Name and Code are required.');
        redirect(BASE_URL . 'branches/index.php');
    }

    if ($action === 'create') {
        $stmt = $pdo->prepare("INSERT INTO branches (business_id, name, code, address, phone, manager_name, invoice_prefix, opening_date)
                               VALUES (1, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$name, $code, $address, $phone, $manager, $prefix, $opening_date]);
        $new_branch_id = $pdo->lastInsertId();

        // Create Default Cash Till Account for new branch
        $cash_ledger_id = getAccountIdByCode($pdo, '1001');
        $pdo->prepare("INSERT INTO financial_accounts (branch_id, account_id, name, type, is_default, current_balance)
                       VALUES (?, ?, ?, 'cash', 1, 0.00)")
            ->execute([$new_branch_id, $cash_ledger_id, "Cash Till - {$name}"]);

        logAudit($pdo, 'create', 'branches', $new_branch_id, "Created branch {$name} ({$code})");
        flash('success', "Branch '{$name}' created successfully with default cash till.");
        redirect(BASE_URL . 'branches/index.php');

    } elseif ($action === 'update') {
        $b_id = (int)$_POST['branch_id'];
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        $stmt = $pdo->prepare("UPDATE branches SET name = ?, code = ?, address = ?, phone = ?, manager_name = ?, invoice_prefix = ?, is_active = ? WHERE id = ?");
        $stmt->execute([$name, $code, $address, $phone, $manager, $prefix, $is_active, $b_id]);

        logAudit($pdo, 'update', 'branches', $b_id, "Updated branch {$name}");
        flash('success', "Branch '{$name}' updated successfully.");
        redirect(BASE_URL . 'branches/index.php');
    }
}
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h5 class="fw-bold m-0"><i class="bi bi-buildings text-primary me-2"></i>Multi-Branch Management</h5>
        <small class="text-muted">Centralized administration of all store locations, tills and inventory quotas</small>
    </div>
    <button type="button" class="btn btn-primary btn-sm shadow-sm" data-toggle-panel="panel-new-branch">
        <i class="bi bi-plus-circle me-1"></i> Add New Branch
    </button>
</div>

<div class="row g-3">
    <?php foreach ($branches as $b): ?>
        <div class="col-12 col-md-6">
            <div class="card h-100">
                <div class="card-header bg-light d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="fw-bold mb-0 text-dark"><?= htmlspecialchars($b['name']) ?></h6>
                        <span class="badge bg-light text-dark border font-monospace"><?= htmlspecialchars($b['code']) ?></span>
                    </div>
                    <div>
                        <?= $b['is_active'] ? '<span class="badge bg-success">Active Branch</span>' : '<span class="badge bg-secondary">Inactive</span>' ?>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row g-2 mb-3 small">
                        <div class="col-6 text-muted">Branch Manager:</div>
                        <div class="col-6 fw-semibold text-dark"><?= htmlspecialchars($b['manager_name'] ?: 'Not Assigned') ?></div>
                        <div class="col-6 text-muted">Phone / Contact:</div>
                        <div class="col-6 fw-semibold text-dark"><?= htmlspecialchars($b['phone'] ?: 'N/A') ?></div>
                        <div class="col-6 text-muted">Invoice Prefix:</div>
                        <div class="col-6 font-monospace fw-bold text-primary"><?= htmlspecialchars($b['invoice_prefix']) ?></div>
                        <div class="col-6 text-muted">Address:</div>
                        <div class="col-6 text-muted"><?= htmlspecialchars($b['address'] ?: 'N/A') ?></div>
                    </div>

                    <div class="row g-2 pt-2 border-top text-center">
                        <div class="col-4">
                            <small class="text-muted d-block" style="font-size:0.75rem;">Mobiles In Stock</small>
                            <span class="fw-bold text-dark fs-6"><?= $b['phone_count'] ?></span>
                        </div>
                        <div class="col-4">
                            <small class="text-muted d-block" style="font-size:0.75rem;">Accessories</small>
                            <span class="fw-bold text-dark fs-6"><?= $b['acc_count'] ?></span>
                        </div>
                        <div class="col-4">
                            <small class="text-muted d-block" style="font-size:0.75rem;">Cash Liquidity</small>
                            <span class="fw-bold text-success fs-6"><?= formatCurrency($b['cash_liquidity']) ?></span>
                        </div>
                    </div>
                </div>
                <div class="card-footer bg-light text-end">
                    <button type="button" class="btn btn-sm btn-outline-primary btn-edit-branch"
                            data-json='<?= htmlspecialchars(json_encode($b), ENT_QUOTES, 'UTF-8') ?>'>
                        <i class="bi bi-pencil me-1"></i> Edit Branch Info
                    </button>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<!-- SIDE-PANEL DRAWER: CREATE / EDIT BRANCH (Zero Modals Rule) -->
<div id="panel-new-branch" class="side-panel">
    <div class="side-panel-header">
        <h6 class="m-0 fw-bold" id="panel-branch-title"><i class="bi bi-building me-2 text-primary"></i>Register New Branch</h6>
        <button type="button" class="btn-close" data-close-panel></button>
    </div>
    <div class="side-panel-body">
        <form method="POST" action="" id="form-branch">
            <?= csrfField() ?>
            <input type="hidden" name="action" id="br-action" value="create">
            <input type="hidden" name="branch_id" id="br-id" value="">

            <div class="mb-3">
                <label class="form-label small fw-semibold">Branch Name *</label>
                <input type="text" name="name" id="br-name" class="form-control" placeholder="e.g. DHA Phase 5 Branch" required>
            </div>

            <div class="row g-2 mb-3">
                <div class="col-6">
                    <label class="form-label small fw-semibold">Branch Code *</label>
                    <input type="text" name="code" id="br-code" class="form-control font-monospace" placeholder="BR-DHA" required>
                </div>
                <div class="col-6">
                    <label class="form-label small fw-semibold">Invoice Prefix *</label>
                    <input type="text" name="invoice_prefix" id="br-prefix" class="form-control font-monospace" placeholder="INV-D" required>
                </div>
            </div>

            <div class="row g-2 mb-3">
                <div class="col-6">
                    <label class="form-label small fw-semibold">Manager Name</label>
                    <input type="text" name="manager_name" id="br-manager" class="form-control" placeholder="Manager Name">
                </div>
                <div class="col-6">
                    <label class="form-label small fw-semibold">Contact Phone</label>
                    <input type="tel" name="phone" id="br-phone" class="form-control" placeholder="03001234567">
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label small fw-semibold">Full Address</label>
                <textarea name="address" id="br-address" class="form-control form-control-sm" rows="2" placeholder="Street, Plaza, City..."></textarea>
            </div>

            <div class="mb-4 form-check form-switch" id="br-active-group" style="display: none;">
                <input class="form-check-input" type="checkbox" name="is_active" id="br-active" checked>
                <label class="form-check-label small fw-semibold" for="br-active">Branch is Active & Accepting Sales</label>
            </div>

            <div class="side-panel-footer px-0 pb-0">
                <button type="button" class="btn btn-light" data-close-panel>Cancel</button>
                <button type="submit" class="btn btn-primary" id="btn-save-branch">Save Branch</button>
            </div>
        </form>
    </div>
</div>

<script>
$(document).ready(function() {
    $('.btn-edit-branch').on('click', function() {
        let b = $(this).data('json');
        $('#panel-branch-title').html('<i class="bi bi-pencil me-2 text-primary"></i>Edit Branch: ' + b.name);
        $('#br-action').val('update');
        $('#br-id').val(b.id);
        $('#br-name').val(b.name);
        $('#br-code').val(b.code);
        $('#br-prefix').val(b.invoice_prefix);
        $('#br-manager').val(b.manager_name);
        $('#br-phone').val(b.phone);
        $('#br-address').val(b.address);
        $('#br-active').prop('checked', b.is_active == 1);
        $('#br-active-group').show();
        $('#btn-save-branch').text('Update Branch');
        openSidePanel('panel-new-branch');
    });

    $('[data-toggle-panel="panel-new-branch"]').on('click', function() {
        $('#panel-branch-title').html('<i class="bi bi-building me-2 text-primary"></i>Register New Branch');
        $('#br-action').val('create');
        $('#br-id').val('');
        $('#form-branch')[0].reset();
        $('#br-active-group').hide();
        $('#btn-save-branch').text('Save Branch');
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
