<?php
// cash_bank/index.php - Cash & Bank Accounts Management
$page_title = 'Cash & Bank Accounts';
require_once __DIR__ . '/../includes/header.php';

$branch_id = getActiveBranchId();
$financial_accounts = getFinancialAccounts($pdo, $branch_id);

// Fetch recent internal transfers
$stmt = $pdo->prepare("SELECT at.*, fa1.name as from_name, fa2.name as to_name, u.name as user_name
                       FROM account_transfers at
                       JOIN financial_accounts fa1 ON at.from_account_id = fa1.id
                       JOIN financial_accounts fa2 ON at.to_account_id = fa2.id
                       LEFT JOIN users u ON at.created_by = u.id
                       WHERE fa1.branch_id = ? OR fa2.branch_id = ?
                       ORDER BY at.id DESC LIMIT 20");
$stmt->execute([$branch_id, $branch_id]);
$transfers = $stmt->fetchAll();

$total_cash = 0;
$total_bank = 0;
foreach ($financial_accounts as $fa) {
    if ($fa['type'] === 'cash') $total_cash += (float)$fa['current_balance'];
    else $total_bank += (float)$fa['current_balance'];
}
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h5 class="fw-bold m-0"><i class="bi bi-bank text-primary me-2"></i>Cash & Bank Management</h5>
        <small class="text-muted">Branch cash tills, commercial bank accounts, mobile wallets and deposits</small>
    </div>
    <div class="d-flex gap-2">
        <button type="button" class="btn btn-outline-primary btn-sm shadow-sm" data-toggle-panel="panel-new-account">
            <i class="bi bi-plus-circle me-1"></i> New Account
        </button>
        <button type="button" class="btn btn-primary btn-sm shadow-sm" data-toggle-panel="panel-transfer">
            <i class="bi bi-arrow-left-right me-1"></i> Account Transfer / Deposit
        </button>
    </div>
</div>

<!-- Balance Cards -->
<div class="row g-3 mb-4">
    <div class="col-12 col-md-4">
        <div class="stat-card">
            <div class="stat-icon bg-warning bg-opacity-10 text-warning">
                <i class="bi bi-cash"></i>
            </div>
            <div>
                <small class="text-muted text-uppercase fw-semibold" style="font-size:0.75rem;">Branch Cash In Hand</small>
                <h4 class="fw-bold m-0 text-dark"><?= formatCurrency($total_cash) ?></h4>
            </div>
        </div>
    </div>
    <div class="col-12 col-md-4">
        <div class="stat-card">
            <div class="stat-icon bg-primary bg-opacity-10 text-primary">
                <i class="bi bi-bank2"></i>
            </div>
            <div>
                <small class="text-muted text-uppercase fw-semibold" style="font-size:0.75rem;">Total Bank & Mobile Wallets</small>
                <h4 class="fw-bold m-0 text-dark"><?= formatCurrency($total_bank) ?></h4>
            </div>
        </div>
    </div>
    <div class="col-12 col-md-4">
        <div class="stat-card">
            <div class="stat-icon bg-success bg-opacity-10 text-success">
                <i class="bi bi-safe2"></i>
            </div>
            <div>
                <small class="text-muted text-uppercase fw-semibold" style="font-size:0.75rem;">Consolidated Liquidity</small>
                <h4 class="fw-bold m-0 text-success"><?= formatCurrency($total_cash + $total_bank) ?></h4>
            </div>
        </div>
    </div>
</div>

<!-- Accounts Table -->
<div class="card mb-4">
    <div class="card-header bg-light">
        <span class="fw-bold">Active Financial Accounts</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Account Name</th>
                        <th>Type</th>
                        <th>Account / IBAN Number</th>
                        <th>Bank / Institution</th>
                        <th>Branch</th>
                        <th class="text-end">Current Live Balance</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($financial_accounts as $fa): ?>
                        <tr>
                            <td>
                                <div class="fw-bold text-dark"><?= htmlspecialchars($fa['name']) ?></div>
                                <?php if ($fa['is_default']): ?>
                                    <span class="badge bg-primary-subtle text-primary border" style="font-size:0.65rem;">Default Branch Till</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge bg-light text-dark border text-uppercase"><?= $fa['type'] ?></span>
                            </td>
                            <td class="font-monospace small"><?= htmlspecialchars($fa['account_number'] ?: 'N/A') ?></td>
                            <td><?= htmlspecialchars($fa['bank_name'] ?: 'Local Cash Box') ?></td>
                            <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($fa['branch_name']) ?></span></td>
                            <td class="text-end fw-bold text-dark fs-6"><?= formatCurrency($fa['current_balance']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Recent Internal Transfers -->
<div class="card">
    <div class="card-header bg-light">
        <span class="fw-bold"><i class="bi bi-arrow-left-right me-1 text-primary"></i> Recent Internal Fund Transfers</span>
    </div>
    <div class="card-body p-0">
        <?php if (empty($transfers)): ?>
            <div class="text-center py-4 text-muted small">No internal fund transfers recorded yet.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Transfer #</th>
                            <th>Date</th>
                            <th>Source Account</th>
                            <th>Destination Account</th>
                            <th>Amount</th>
                            <th>Reference / Notes</th>
                            <th>Processed By</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($transfers as $tr): ?>
                            <tr>
                                <td class="fw-bold text-primary"><?= htmlspecialchars($tr['transfer_no']) ?></td>
                                <td><?= formatDate($tr['transfer_date']) ?></td>
                                <td class="fw-semibold text-danger"><?= htmlspecialchars($tr['from_name']) ?></td>
                                <td class="fw-semibold text-success"><?= htmlspecialchars($tr['to_name']) ?></td>
                                <td class="fw-bold"><?= formatCurrency($tr['amount']) ?></td>
                                <td class="small text-muted"><?= htmlspecialchars($tr['notes'] ?: '-') ?></td>
                                <td><?= htmlspecialchars($tr['user_name'] ?? 'Staff') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- SIDE-PANEL DRAWER: INTERNAL TRANSFER (Zero Modals Rule) -->
<div id="panel-transfer" class="side-panel">
    <div class="side-panel-header">
        <h6 class="m-0 fw-bold"><i class="bi bi-arrow-left-right me-2 text-primary"></i>Internal Account Transfer / Deposit</h6>
        <button type="button" class="btn-close" data-close-panel></button>
    </div>
    <div class="side-panel-body">
        <form method="POST" action="<?= BASE_URL ?>cash_bank/transfer.php">
            <?= csrfField() ?>
            <div class="mb-3">
                <label class="form-label small fw-semibold">Transfer Out From (Source) *</label>
                <select name="from_account_id" class="form-select" required>
                    <option value="">-- Select Source Account --</option>
                    <?php foreach ($financial_accounts as $fa): ?>
                        <option value="<?= $fa['id'] ?>"><?= htmlspecialchars($fa['name']) ?> (Balance: <?= formatCurrency($fa['current_balance']) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="mb-3">
                <label class="form-label small fw-semibold">Deposit Into (Destination) *</label>
                <select name="to_account_id" class="form-select" required>
                    <option value="">-- Select Destination Account --</option>
                    <?php foreach ($financial_accounts as $fa): ?>
                        <option value="<?= $fa['id'] ?>"><?= htmlspecialchars($fa['name']) ?> (Balance: <?= formatCurrency($fa['current_balance']) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="mb-3">
                <label class="form-label small fw-semibold">Transfer Amount (Rs.) *</label>
                <input type="number" step="0.01" name="amount" class="form-control form-control-lg fw-bold text-primary" placeholder="0.00" required min="1">
            </div>

            <div class="mb-3">
                <label class="form-label small fw-semibold">Date</label>
                <input type="date" name="transfer_date" class="form-control" value="<?= date('Y-m-d') ?>">
            </div>

            <div class="mb-4">
                <label class="form-label small fw-semibold">Description / Slip #</label>
                <textarea name="notes" class="form-control form-control-sm" rows="2" placeholder="e.g. Deposited cash till balance into bank"></textarea>
            </div>

            <div class="side-panel-footer px-0 pb-0">
                <button type="button" class="btn btn-light" data-close-panel>Cancel</button>
                <button type="submit" class="btn btn-primary">Complete Transfer</button>
            </div>
        </form>
    </div>
</div>

<!-- SIDE-PANEL DRAWER: CREATE FINANCIAL ACCOUNT -->
<div id="panel-new-account" class="side-panel">
    <div class="side-panel-header">
        <h6 class="m-0 fw-bold"><i class="bi bi-bank me-2 text-primary"></i>Create Financial Account</h6>
        <button type="button" class="btn-close" data-close-panel></button>
    </div>
    <div class="side-panel-body">
        <form method="POST" action="<?= BASE_URL ?>cash_bank/transfer.php?action=create_account">
            <?= csrfField() ?>
            <div class="mb-3">
                <label class="form-label small fw-semibold">Account Display Name *</label>
                <input type="text" name="name" class="form-control" placeholder="e.g. Meezan Bank Current A/C" required>
            </div>
            <div class="row g-2 mb-3">
                <div class="col-6">
                    <label class="form-label small fw-semibold">Account Type</label>
                    <select name="type" class="form-select">
                        <option value="cash">Cash Till</option>
                        <option value="bank" selected>Bank Account</option>
                        <option value="mobile_wallet">Mobile Wallet (JazzCash/Easypaisa)</option>
                    </select>
                </div>
                <div class="col-6">
                    <label class="form-label small fw-semibold">Bank Name</label>
                    <input type="text" name="bank_name" class="form-control" placeholder="e.g. Meezan Bank">
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label small fw-semibold">Account / IBAN Number</label>
                <input type="text" name="account_number" class="form-control font-monospace" placeholder="PK12MEZN...">
            </div>
            <div class="mb-3">
                <label class="form-label small fw-semibold">Initial Balance (Rs.)</label>
                <input type="number" step="0.01" name="initial_balance" class="form-control" value="0.00">
            </div>
            <div class="side-panel-footer px-0 pb-0">
                <button type="button" class="btn btn-light" data-close-panel>Cancel</button>
                <button type="submit" class="btn btn-primary">Save Account</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
