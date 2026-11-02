<?php
// accounting/journal.php - Journal Register & Manual Journal Entries
$page_title = 'Journal Entries Register';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/accounting.php';

$branch_id = getActiveBranchId();
$all_accounts = $pdo->query("SELECT id, code, name FROM accounts WHERE status = 'active' ORDER BY code ASC")->fetchAll();

// Handle Manual JV Creation
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validatePostCsrf();
    $entry_date = sanitize($_POST['entry_date'] ?? date('Y-m-d'));
    $narration = sanitize($_POST['narration'] ?? 'Manual Journal Voucher');
    $acct_ids = $_POST['account_id'] ?? [];
    $debits = $_POST['debit'] ?? [];
    $credits = $_POST['credit'] ?? [];
    $descriptions = $_POST['line_description'] ?? [];

    $lines = [];
    $tot_d = 0;
    $tot_c = 0;

    for ($i = 0; $i < count($acct_ids); $i++) {
        $aid = (int)$acct_ids[$i];
        $d = (float)($debits[$i] ?? 0);
        $c = (float)($credits[$i] ?? 0);
        $desc = sanitize($descriptions[$i] ?? $narration);

        if ($aid <= 0 || ($d == 0 && $c == 0)) continue;

        $lines[] = [
            'account_id' => $aid,
            'debit' => $d,
            'credit' => $c,
            'description' => $desc
        ];
        $tot_d += $d;
        $tot_c += $c;
    }

    if (empty($lines) || abs($tot_d - $tot_c) > 0.01) {
        flash('danger', "Journal entry must be balanced! Total Debits: Rs. {$tot_d}, Total Credits: Rs. {$tot_c}");
        redirect(BASE_URL . 'accounting/journal.php');
    }

    try {
        $pdo->beginTransaction();
        postJournalEntry($pdo, $branch_id, 'manual', 0, $lines, $entry_date, $narration, $_SESSION['user_id']);
        $pdo->commit();

        flash('success', "Journal voucher posted successfully.");
        redirect(BASE_URL . 'accounting/journal.php');
    } catch (Exception $e) {
        $pdo->rollBack();
        flash('danger', 'Error posting journal entry: ' . $e->getMessage());
    }
}

// Fetch Journal Entries
$sql = "SELECT je.*, b.name as branch_name, u.name as user_name
        FROM journal_entries je
        JOIN branches b ON je.branch_id = b.id
        LEFT JOIN users u ON je.created_by = u.id
        WHERE 1=1";
$params = [];

if ($branch_id) {
    $sql .= " AND je.branch_id = ?";
    $params[] = $branch_id;
}
$sql .= " ORDER BY je.id DESC LIMIT 40";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$entries = $stmt->fetchAll();
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h5 class="fw-bold m-0"><i class="bi bi-journal-text text-primary me-2"></i>Journal Entries Register</h5>
        <small class="text-muted">Immutable double-entry general journal vouchers and financial logs</small>
    </div>
    <button type="button" class="btn btn-primary btn-sm shadow-sm" data-toggle-panel="panel-new-jv">
        <i class="bi bi-plus-circle me-1"></i> New Journal Voucher (JV)
    </button>
</div>

<div class="card">
    <div class="card-body p-0">
        <?php if (empty($entries)): ?>
            <div class="text-center py-5 text-muted">
                <i class="bi bi-journal-check fs-1 mb-2 d-block opacity-50"></i>
                No journal entries recorded.
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-bordered table-hover align-middle mb-0">
                    <thead class="table-light small">
                        <tr>
                            <th style="width: 140px;">Entry # / Date</th>
                            <th style="width: 100px;">Reference</th>
                            <th>Narration & Line Details</th>
                            <th class="text-end" style="width: 130px;">Debit</th>
                            <th class="text-end" style="width: 130px;">Credit</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($entries as $e): ?>
                            <?php
                            $stmt = $pdo->prepare("SELECT jel.*, a.code, a.name as account_name 
                                                   FROM journal_entry_lines jel 
                                                   JOIN accounts a ON jel.account_id = a.id 
                                                   WHERE jel.entry_id = ? ORDER BY jel.debit DESC");
                            $stmt->execute([$e['id']]);
                            $lines = $stmt->fetchAll();
                            ?>
                            <tr class="table-light">
                                <td>
                                    <div class="fw-bold text-primary"><?= htmlspecialchars($e['entry_no']) ?></div>
                                    <small class="text-muted"><?= formatDate($e['entry_date']) ?></small>
                                </td>
                                <td>
                                    <span class="badge bg-secondary text-uppercase"><?= htmlspecialchars($e['reference_type']) ?></span>
                                </td>
                                <td>
                                    <div class="fw-bold text-dark"><?= htmlspecialchars($e['narration']) ?></div>
                                    <small class="text-muted">Branch: <?= htmlspecialchars($e['branch_name']) ?> &bull; By: <?= htmlspecialchars($e['user_name'] ?? 'System') ?></small>
                                </td>
                                <td class="text-end fw-bold text-primary"><?= formatCurrency($e['total_debit']) ?></td>
                                <td class="text-end fw-bold text-primary"><?= formatCurrency($e['total_credit']) ?></td>
                            </tr>
                            <?php foreach ($lines as $ln): ?>
                                <tr style="background: #fafafa; font-size: 0.85rem;">
                                    <td colspan="2"></td>
                                    <td class="ps-4">
                                        <div class="fw-semibold text-dark">
                                            <?= htmlspecialchars($ln['code']) ?> - <?= htmlspecialchars($ln['account_name']) ?>
                                        </div>
                                        <small class="text-muted"><?= htmlspecialchars($ln['description']) ?></small>
                                    </td>
                                    <td class="text-end text-success">
                                        <?= $ln['debit'] > 0 ? formatCurrency($ln['debit']) : '' ?>
                                    </td>
                                    <td class="text-end text-danger">
                                        <?= $ln['credit'] > 0 ? formatCurrency($ln['credit']) : '' ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- SIDE-PANEL DRAWER: CREATE MANUAL JV (Zero Modals Rule) -->
<div id="panel-new-jv" class="side-panel" style="max-width: 650px;">
    <div class="side-panel-header">
        <h6 class="m-0 fw-bold"><i class="bi bi-journal-plus me-2 text-primary"></i>New Manual Journal Voucher (JV)</h6>
        <button type="button" class="btn-close" data-close-panel></button>
    </div>
    <div class="side-panel-body">
        <form method="POST" action="">
            <?= csrfField() ?>
            <div class="row g-2 mb-3">
                <div class="col-4">
                    <label class="form-label small fw-semibold">Entry Date</label>
                    <input type="date" name="entry_date" class="form-control form-control-sm" value="<?= date('Y-m-d') ?>" required>
                </div>
                <div class="col-8">
                    <label class="form-label small fw-semibold">General Narration / Notes *</label>
                    <input type="text" name="narration" class="form-control form-control-sm" placeholder="e.g. Month-end depreciation adjustment" required>
                </div>
            </div>

            <div class="d-flex justify-content-between align-items-center border-top pt-2 mb-2">
                <span class="small fw-bold text-muted">Voucher Lines (Debit = Credit)</span>
                <button type="button" class="btn btn-outline-primary btn-sm py-0" id="btn-add-jv-row">
                    <i class="bi bi-plus-lg"></i> Add Line
                </button>
            </div>

            <div id="jv-rows-container">
                <div class="row g-2 align-items-center mb-2 jv-row">
                    <div class="col-5">
                        <select name="account_id[]" class="form-select form-select-sm" required>
                            <option value="">-- Choose Account --</option>
                            <?php foreach ($all_accounts as $a): ?>
                                <option value="<?= $a['id'] ?>"><?= htmlspecialchars($a['code']) ?> - <?= htmlspecialchars($a['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-3">
                        <input type="number" step="0.01" name="debit[]" class="form-control form-control-sm text-end jv-debit" placeholder="Debit" value="0.00">
                    </div>
                    <div class="col-3">
                        <input type="number" step="0.01" name="credit[]" class="form-control form-control-sm text-end jv-credit" placeholder="Credit" value="0.00">
                    </div>
                    <div class="col-1 text-center">
                        <button type="button" class="btn btn-sm text-danger p-0 btn-remove-jv"><i class="bi bi-trash"></i></button>
                    </div>
                </div>
                <div class="row g-2 align-items-center mb-2 jv-row">
                    <div class="col-5">
                        <select name="account_id[]" class="form-select form-select-sm" required>
                            <option value="">-- Choose Account --</option>
                            <?php foreach ($all_accounts as $a): ?>
                                <option value="<?= $a['id'] ?>"><?= htmlspecialchars($a['code']) ?> - <?= htmlspecialchars($a['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-3">
                        <input type="number" step="0.01" name="debit[]" class="form-control form-control-sm text-end jv-debit" placeholder="Debit" value="0.00">
                    </div>
                    <div class="col-3">
                        <input type="number" step="0.01" name="credit[]" class="form-control form-control-sm text-end jv-credit" placeholder="Credit" value="0.00">
                    </div>
                    <div class="col-1 text-center">
                        <button type="button" class="btn btn-sm text-danger p-0 btn-remove-jv"><i class="bi bi-trash"></i></button>
                    </div>
                </div>
            </div>

            <div class="p-3 bg-light rounded-3 my-3 d-flex justify-content-between align-items-center">
                <div>
                    <small class="text-muted d-block">Debits: <strong id="lbl-jv-debit" class="text-success">Rs. 0.00</strong></small>
                    <small class="text-muted d-block">Credits: <strong id="lbl-jv-credit" class="text-danger">Rs. 0.00</strong></small>
                </div>
                <div id="lbl-jv-status" class="fw-bold small text-muted">Balanced</div>
            </div>

            <div class="side-panel-footer px-0 pb-0">
                <button type="button" class="btn btn-light" data-close-panel>Cancel</button>
                <button type="submit" class="btn btn-primary" id="btn-save-jv">Post Journal Voucher</button>
            </div>
        </form>
    </div>
</div>

<script>
function calcJvTotals() {
    let dTot = 0, cTot = 0;
    $('.jv-row').each(function() {
        dTot += parseFloat($(this).find('.jv-debit').val()) || 0;
        cTot += parseFloat($(this).find('.jv-credit').val()) || 0;
    });

    $('#lbl-jv-debit').text('Rs. ' + dTot.toLocaleString(undefined, {minimumFractionDigits: 2}));
    $('#lbl-jv-credit').text('Rs. ' + cTot.toLocaleString(undefined, {minimumFractionDigits: 2}));

    if (Math.abs(dTot - cTot) < 0.01 && dTot > 0) {
        $('#lbl-jv-status').html('<span class="text-success"><i class="bi bi-check-circle-fill me-1"></i> Balanced</span>');
        $('#btn-save-jv').prop('disabled', false);
    } else {
        $('#lbl-jv-status').html('<span class="text-danger"><i class="bi bi-exclamation-triangle-fill me-1"></i> Out of Balance (Diff: Rs. ' + Math.abs(dTot - cTot).toFixed(2) + ')</span>');
        $('#btn-save-jv').prop('disabled', true);
    }
}

$(document).on('input', '.jv-debit, .jv-credit', calcJvTotals);

$('#btn-add-jv-row').on('click', function() {
    let row = $('.jv-row:first').clone();
    row.find('select').val('');
    row.find('.jv-debit').val('0.00');
    row.find('.jv-credit').val('0.00');
    $('#jv-rows-container').append(row);
});

$(document).on('click', '.btn-remove-jv', function() {
    if ($('.jv-row').length > 2) {
        $(this).closest('.jv-row').remove();
        calcJvTotals();
    } else {
        alert('At least two lines are required for a double-entry journal voucher.');
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
