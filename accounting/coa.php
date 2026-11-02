<?php
// accounting/coa.php - Chart of Accounts Management
$page_title = 'Chart of Accounts';
require_once __DIR__ . '/../includes/header.php';

$groups = $pdo->query("SELECT * FROM account_groups ORDER BY id ASC")->fetchAll();

// Handle new account creation
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validatePostCsrf();
    $code = sanitize($_POST['code'] ?? '');
    $name = sanitize($_POST['name'] ?? '');
    $group_id = (int)$_POST['group_id'];
    $desc = sanitize($_POST['description'] ?? '');

    if (empty($code) || empty($name) || $group_id <= 0) {
        flash('danger', 'Account Code, Name and Group are required.');
        redirect(BASE_URL . 'accounting/coa.php');
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO accounts (group_id, code, name, description) VALUES (?, ?, ?, ?)");
        $stmt->execute([$group_id, $code, $name, $desc]);
        flash('success', "Account '{$name}' ({$code}) created successfully.");
        redirect(BASE_URL . 'accounting/coa.php');
    } catch (Exception $e) {
        flash('danger', 'Error adding account: ' . $e->getMessage());
    }
}
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h5 class="fw-bold m-0"><i class="bi bi-diagram-3 text-primary me-2"></i>Chart of Accounts (COA)</h5>
        <small class="text-muted">Master general ledger accounts structure for complete double-entry accounting</small>
    </div>
    <button type="button" class="btn btn-primary btn-sm shadow-sm" data-toggle-panel="panel-new-account">
        <i class="bi bi-plus-circle me-1"></i> Add Account
    </button>
</div>

<div class="row g-3">
    <?php foreach ($groups as $g): ?>
        <?php
        $stmt = $pdo->prepare("SELECT * FROM accounts WHERE group_id = ? ORDER BY code ASC");
        $stmt->execute([$g['id']]);
        $accts = $stmt->fetchAll();
        ?>
        <div class="col-12 col-md-6 col-xl-4">
            <div class="card h-100">
                <div class="card-header bg-light d-flex justify-content-between align-items-center">
                    <span class="fw-bold text-dark"><?= htmlspecialchars($g['name']) ?></span>
                    <span class="badge bg-secondary text-uppercase"><?= $g['group_type'] ?></span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Code</th>
                                    <th>Account Name</th>
                                    <th class="text-end">Balance</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($accts)): ?>
                                    <tr><td colspan="3" class="text-center py-3 text-muted small">No accounts in this group.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($accts as $a): ?>
                                        <tr>
                                            <td class="font-monospace fw-semibold small text-muted"><?= htmlspecialchars($a['code']) ?></td>
                                            <td>
                                                <a href="<?= BASE_URL ?>accounting/ledger.php?account_id=<?= $a['id'] ?>" class="fw-semibold text-dark text-decoration-none">
                                                    <?= htmlspecialchars($a['name']) ?>
                                                </a>
                                            </td>
                                            <td class="text-end fw-bold <?= $a['current_balance'] < 0 ? 'text-danger' : 'text-dark' ?>">
                                                <?= formatCurrency($a['current_balance']) ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<!-- SIDE-PANEL DRAWER: CREATE ACCOUNT (Zero Modals Rule) -->
<div id="panel-new-account" class="side-panel">
    <div class="side-panel-header">
        <h6 class="m-0 fw-bold"><i class="bi bi-plus-circle me-2 text-primary"></i>Add Ledger Account</h6>
        <button type="button" class="btn-close" data-close-panel></button>
    </div>
    <div class="side-panel-body">
        <form method="POST" action="">
            <?= csrfField() ?>
            <div class="mb-3">
                <label class="form-label small fw-semibold">Account Group *</label>
                <select name="group_id" class="form-select" required>
                    <option value="">-- Select Group --</option>
                    <?php foreach ($groups as $g): ?>
                        <option value="<?= $g['id'] ?>"><?= htmlspecialchars($g['name']) ?> (<?= ucfirst($g['group_type']) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="row g-2 mb-3">
                <div class="col-5">
                    <label class="form-label small fw-semibold">Account Code *</label>
                    <input type="text" name="code" class="form-control font-monospace" placeholder="e.g. 6005" required>
                </div>
                <div class="col-7">
                    <label class="form-label small fw-semibold">Account Name *</label>
                    <input type="text" name="name" class="form-control" placeholder="e.g. Advertising & Marketing" required>
                </div>
            </div>

            <div class="mb-4">
                <label class="form-label small fw-semibold">Description</label>
                <textarea name="description" class="form-control form-control-sm" rows="3" placeholder="Notes on when to use this account..."></textarea>
            </div>

            <div class="side-panel-footer px-0 pb-0">
                <button type="button" class="btn btn-light" data-close-panel>Cancel</button>
                <button type="submit" class="btn btn-primary">Save Ledger Account</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
