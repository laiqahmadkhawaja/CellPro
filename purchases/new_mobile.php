<?php
// purchases/new_mobile.php - Purchase New Mobile Phones with IMEI records
$page_title = 'Purchase New Mobile Phones';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/accounting.php';
require_once __DIR__ . '/../includes/inventory.php';

$branch_id = getActiveBranchId();
$suppliers = $pdo->query("SELECT id, name, phone, current_balance FROM suppliers ORDER BY name ASC")->fetchAll();
$brands = $pdo->query("SELECT id, name FROM brands WHERE status = 'active' ORDER BY name ASC")->fetchAll();
$financial_accounts = getFinancialAccounts($pdo, $branch_id);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validatePostCsrf();
    $supplier_id = (int)$_POST['supplier_id'];
    $purchase_date = sanitize($_POST['purchase_date'] ?? date('Y-m-d'));
    $payment_account_id = (int)$_POST['payment_account_id'];
    $payment_method = sanitize($_POST['payment_method'] ?? 'Cash');
    $paid_amount = (float)($_POST['paid_amount'] ?? 0);
    $notes = sanitize($_POST['notes'] ?? '');

    $models = $_POST['model_name'] ?? [];
    $brand_ids = $_POST['brand_id'] ?? [];
    $rams = $_POST['ram'] ?? [];
    $storages = $_POST['storage'] ?? [];
    $colors = $_POST['color'] ?? [];
    $imei_1s = $_POST['imei_1'] ?? [];
    $imei_2s = $_POST['imei_2'] ?? [];
    $serials = $_POST['serial_number'] ?? [];
    $ptas = $_POST['pta_status'] ?? [];
    $warranties = $_POST['warranty_months'] ?? [];
    $prices = $_POST['purchase_price'] ?? [];
    $extra_costs = $_POST['additional_cost'] ?? [];
    $sell_prices = $_POST['selling_price'] ?? [];

    if (empty($models)) {
        flash('danger', 'Please add at least one mobile phone to the purchase invoice.');
        redirect(BASE_URL . 'purchases/new_mobile.php');
    }

    try {
        $pdo->beginTransaction();

        $subtotal = 0.00;
        $total_additional_cost = 0.00;
        $created_devices = [];

        // Check IMEI uniqueness beforehand
        $checkStmt = $pdo->prepare("SELECT imei_1 FROM mobile_devices WHERE imei_1 = ? OR (imei_2 IS NOT NULL AND imei_2 = ?)");

        for ($i = 0; $i < count($models); $i++) {
            $m_name = trim($models[$i]);
            $imei1 = trim($imei_1s[$i]);
            $imei2 = !empty($imei_2s[$i]) ? trim($imei_2s[$i]) : null;

            if (empty($m_name) || empty($imei1)) {
                throw new Exception("Row #" . ($i + 1) . ": Model name and IMEI 1 are mandatory.");
            }

            // Uniqueness check
            $checkStmt->execute([$imei1, $imei1]);
            if ($checkStmt->fetch()) {
                throw new Exception("IMEI 1 '{$imei1}' already exists in inventory.");
            }
            if ($imei2) {
                $checkStmt->execute([$imei2, $imei2]);
                if ($checkStmt->fetch()) {
                    throw new Exception("IMEI 2 '{$imei2}' already exists in inventory.");
                }
            }

            $p_price = (float)$prices[$i];
            $extra = (float)($extra_costs[$i] ?? 0);
            $actual = $p_price + $extra;
            $s_price = (float)$sell_prices[$i];

            $subtotal += $p_price;
            $total_additional_cost += $extra;

            $created_devices[] = [
                'model_name' => $m_name,
                'brand_id' => !empty($brand_ids[$i]) ? (int)$brand_ids[$i] : null,
                'ram' => sanitize($rams[$i] ?? '8GB'),
                'storage' => sanitize($storages[$i] ?? '256GB'),
                'color' => sanitize($colors[$i] ?? 'Black'),
                'imei_1' => $imei1,
                'imei_2' => $imei2,
                'serial_number' => sanitize($serials[$i] ?? ''),
                'pta_status' => sanitize($ptas[$i] ?? 'approved'),
                'warranty_months' => (int)($warranties[$i] ?? 12),
                'purchase_price' => $p_price,
                'additional_cost' => $extra,
                'actual_cost' => $actual,
                'selling_price' => $s_price
            ];
        }

        $grand_total = $subtotal + $total_additional_cost;
        $actual_paid = min($paid_amount, $grand_total);
        $remaining = max(0, $grand_total - $actual_paid);
        $pay_status = ($remaining == 0) ? 'paid' : (($actual_paid > 0) ? 'partial' : 'unpaid');

        // Create Purchase
        $purchase_no = generateDocNo($pdo, 'PUR', 'purchases', 'purchase_no');
        $stmt = $pdo->prepare("INSERT INTO purchases 
            (purchase_no, branch_id, supplier_id, purchase_type, purchase_date, subtotal, discount, tax_amount, additional_cost, grand_total, paid_amount, payment_status, notes, created_by)
            VALUES (?, ?, ?, 'new_mobile', ?, ?, 0.00, 0.00, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $purchase_no, $branch_id, $supplier_id, $purchase_date, $subtotal, $total_additional_cost, $grand_total, $actual_paid, $pay_status, $notes, $_SESSION['user_id']
        ]);
        $purchase_id = $pdo->lastInsertId();

        // Insert Device Records & Purchase Items
        $devStmt = $pdo->prepare("INSERT INTO mobile_devices 
            (branch_id, supplier_id, brand_id, model_name, ram, storage, color, imei_1, imei_2, serial_number, device_condition, battery_health, pta_status, warranty_months, purchase_price, additional_cost, actual_cost, selling_price, device_status, purchase_date, description)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'new', 100, ?, ?, ?, ?, ?, ?, 'available', ?, ?)");

        $itemStmt = $pdo->prepare("INSERT INTO purchase_items 
            (purchase_id, product_id, device_id, item_name, quantity, unit_price, additional_cost, total_cost)
            VALUES (?, NULL, ?, ?, 1, ?, ?, ?)");

        foreach ($created_devices as $dev) {
            $devStmt->execute([
                $branch_id, $supplier_id, $dev['brand_id'], $dev['model_name'], $dev['ram'], $dev['storage'], $dev['color'],
                $dev['imei_1'], $dev['imei_2'], $dev['serial_number'], $dev['pta_status'], $dev['warranty_months'],
                $dev['purchase_price'], $dev['additional_cost'], $dev['actual_cost'], $dev['selling_price'],
                $purchase_date, "Purchased from Supplier in invoice {$purchase_no}"
            ]);
            $device_id = $pdo->lastInsertId();

            $itemStmt->execute([$purchase_id, $device_id, $dev['model_name'] . ' (' . $dev['imei_1'] . ')', $dev['purchase_price'], $dev['additional_cost'], $dev['actual_cost']]);

            // Stock movement
            recordStockMovement($pdo, $branch_id, null, $device_id, 'purchase', 1, $dev['actual_cost'], 'purchase', $purchase_id, "Purchased in {$purchase_no}");
        }

        // Record Payment
        if ($actual_paid > 0) {
            $pPay = $pdo->prepare("INSERT INTO purchase_payments (purchase_id, branch_id, account_id, payment_method, amount, payment_date, notes) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $pPay->execute([$purchase_id, $branch_id, $payment_account_id, $payment_method, $actual_paid, $purchase_date, "Payment for Purchase {$purchase_no}"]);

            $pdo->prepare("UPDATE financial_accounts SET current_balance = current_balance - ? WHERE id = ?")
                ->execute([$actual_paid, $payment_account_id]);
        }

        // Update Supplier Balance
        if ($remaining > 0) {
            $pdo->prepare("UPDATE suppliers SET current_balance = current_balance + ? WHERE id = ?")
                ->execute([$remaining, $supplier_id]);
        }

        // DOUBLE-ENTRY ACCOUNTING POSTING
        $inventory_acct = getAccountIdByCode($pdo, '1004'); // Merchandise Inventory
        $supplier_acct = getAccountIdByCode($pdo, '2001'); // Supplier Payables
        $cash_bank_acct = getFinancialLedgerAccountId($pdo, $payment_account_id);

        $j_lines = [
            [
                'account_id' => $inventory_acct,
                'debit' => $grand_total,
                'credit' => 0.00,
                'description' => "Inventory acquired from {$purchase_no}"
            ]
        ];

        if ($actual_paid > 0) {
            $j_lines[] = [
                'account_id' => $cash_bank_acct,
                'debit' => 0.00,
                'credit' => $actual_paid,
                'description' => "Payment made for {$purchase_no}"
            ];
        }

        if ($remaining > 0) {
            $j_lines[] = [
                'account_id' => $supplier_acct,
                'debit' => 0.00,
                'credit' => $remaining,
                'description' => "Payable credited for {$purchase_no}"
            ];
        }

        postJournalEntry($pdo, $branch_id, 'purchase', $purchase_id, $j_lines, $purchase_date, "Purchase Invoice {$purchase_no}");

        logAudit($pdo, 'create', 'purchases', $purchase_id, "Created New Phone Purchase {$purchase_no}");

        $pdo->commit();
        flash('success', "Purchase invoice {$purchase_no} recorded successfully with " . count($created_devices) . " devices added to stock.");
        redirect(BASE_URL . 'products/mobiles.php');

    } catch (Exception $e) {
        $pdo->rollBack();
        flash('danger', 'Error recording purchase: ' . $e->getMessage());
    }
}
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h5 class="fw-bold m-0"><i class="bi bi-box-seam text-primary me-2"></i>Purchase New Mobile Phones</h5>
        <small class="text-muted">Register bulk or single new devices with individual IMEI tracking</small>
    </div>
    <a href="<?= BASE_URL ?>purchases/index.php" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i> Purchases List
    </a>
</div>

<form method="POST" action="" id="purchase-form">
    <?= csrfField() ?>

    <div class="card mb-3">
        <div class="card-header bg-light">
            <span class="fw-bold"><i class="bi bi-person-badge me-1 text-primary"></i> Invoice & Supplier Details</span>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-12 col-md-4">
                    <label class="form-label small fw-semibold">Supplier *</label>
                    <select name="supplier_id" class="form-select" required>
                        <option value="">-- Choose Supplier --</option>
                        <?php foreach ($suppliers as $s): ?>
                            <option value="<?= $s['id'] ?>">
                                <?= htmlspecialchars($s['name']) ?> (<?= $s['phone'] ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-6 col-md-4">
                    <label class="form-label small fw-semibold">Purchase Date</label>
                    <input type="date" name="purchase_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                </div>
                <div class="col-6 col-md-4">
                    <label class="form-label small fw-semibold">Remarks / Reference</label>
                    <input type="text" name="notes" class="form-control" placeholder="Supplier Bill #, lot number...">
                </div>
            </div>
        </div>
    </div>

    <!-- Mobile Devices Table -->
    <div class="card mb-3">
        <div class="card-header bg-light d-flex justify-content-between align-items-center">
            <span class="fw-bold"><i class="bi bi-phone me-1 text-primary"></i> Mobile Devices To Receive</span>
            <button type="button" class="btn btn-primary btn-sm" id="btn-add-device-row">
                <i class="bi bi-plus-lg me-1"></i> Add Another Device
            </button>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-bordered align-middle mb-0" id="devices-table">
                    <thead class="table-light">
                        <tr class="small text-center">
                            <th style="min-width: 160px;">Brand & Model *</th>
                            <th style="min-width: 140px;">IMEI 1 *</th>
                            <th style="min-width: 140px;">IMEI 2</th>
                            <th style="min-width: 110px;">Specs (RAM/Storage)</th>
                            <th style="min-width: 90px;">Color</th>
                            <th style="min-width: 110px;">PTA Status</th>
                            <th style="min-width: 110px;">Purchase Price *</th>
                            <th style="min-width: 100px;">Extra Cost</th>
                            <th style="min-width: 110px;">Selling Price *</th>
                            <th style="width: 40px;"></th>
                        </tr>
                    </thead>
                    <tbody id="devices-tbody">
                        <tr class="device-row">
                            <td>
                                <input type="text" name="model_name[]" class="form-control form-control-sm mb-1" placeholder="e.g. Galaxy S24 Ultra" required>
                                <select name="brand_id[]" class="form-select form-select-sm">
                                    <?php foreach ($brands as $b): ?>
                                        <option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td><input type="text" name="imei_1[]" class="form-control form-control-sm font-monospace" placeholder="15 digits" required maxlength="18"></td>
                            <td><input type="text" name="imei_2[]" class="form-control form-control-sm font-monospace" placeholder="15 digits" maxlength="18"></td>
                            <td>
                                <input type="text" name="ram[]" class="form-control form-control-sm mb-1" placeholder="RAM e.g. 12GB" value="12GB">
                                <input type="text" name="storage[]" class="form-control form-control-sm" placeholder="Storage" value="256GB">
                            </td>
                            <td><input type="text" name="color[]" class="form-control form-control-sm" placeholder="Color" value="Titanium Black"></td>
                            <td>
                                <select name="pta_status[]" class="form-select form-select-sm">
                                    <option value="approved">PTA Approved</option>
                                    <option value="non_approved">Non-Approved</option>
                                    <option value="cpid">CPID</option>
                                </select>
                            </td>
                            <td><input type="number" step="0.01" name="purchase_price[]" class="form-control form-control-sm row-price text-end fw-bold" placeholder="0.00" required></td>
                            <td><input type="number" step="0.01" name="additional_cost[]" class="form-control form-control-sm row-extra text-end" placeholder="0.00" value="0.00"></td>
                            <td><input type="number" step="0.01" name="selling_price[]" class="form-control form-control-sm text-end fw-bold text-primary" placeholder="0.00" required></td>
                            <td class="text-center">
                                <button type="button" class="btn btn-sm text-danger p-0 btn-remove-row"><i class="bi bi-trash"></i></button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Payment & Total Card -->
    <div class="card mb-4">
        <div class="card-header bg-light">
            <span class="fw-bold"><i class="bi bi-wallet2 me-1 text-success"></i> Payment & Accounting</span>
        </div>
        <div class="card-body">
            <div class="row g-3 align-items-center">
                <div class="col-12 col-md-3">
                    <label class="form-label small text-muted">Total Invoice Cost</label>
                    <div class="h4 fw-bold text-primary m-0" id="lbl-grand-total">Rs. 0.00</div>
                </div>
                <div class="col-12 col-md-3">
                    <label class="form-label small fw-semibold">Payment Account</label>
                    <select name="payment_account_id" class="form-select form-select-sm">
                        <?php foreach ($financial_accounts as $fa): ?>
                            <option value="<?= $fa['id'] ?>"><?= htmlspecialchars($fa['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-6 col-md-3">
                    <label class="form-label small fw-semibold">Amount Paid Now (Rs.)</label>
                    <input type="number" step="0.01" name="paid_amount" id="inp-paid-amount" class="form-control form-control-sm fw-bold text-success" value="0.00">
                </div>
                <div class="col-6 col-md-3">
                    <label class="form-label small text-muted">Remaining Balance (Payable)</label>
                    <div class="h5 fw-bold text-danger m-0" id="lbl-remaining-due">Rs. 0.00</div>
                </div>
            </div>
        </div>
        <div class="card-footer bg-white text-end">
            <button type="submit" class="btn btn-primary btn-lg px-4 shadow-sm">
                <i class="bi bi-check2-circle me-1"></i> Save Purchase & Post Inventory
            </button>
        </div>
    </div>
</form>

<script>
function recalcTotals() {
    let total = 0;
    $('.device-row').each(function() {
        let p = parseFloat($(this).find('.row-price').val()) || 0;
        let e = parseFloat($(this).find('.row-extra').val()) || 0;
        total += (p + e);
    });

    $('#lbl-grand-total').text('Rs. ' + total.toLocaleString(undefined, {minimumFractionDigits: 2}));

    let paid = parseFloat($('#inp-paid-amount').val()) || 0;
    let rem = Math.max(0, total - paid);
    $('#lbl-remaining-due').text('Rs. ' + rem.toLocaleString(undefined, {minimumFractionDigits: 2}));
}

$(document).on('input', '.row-price, .row-extra, #inp-paid-amount', recalcTotals);

$('#btn-add-device-row').on('click', function() {
    let rowHtml = $('#devices-tbody tr:first').clone();
    rowHtml.find('.select2-container').remove();
    rowHtml.find('select').removeClass('select2-hidden-accessible').removeAttr('data-select2-id');
    rowHtml.find('input').val('');
    rowHtml.find('.row-extra').val('0.00');
    rowHtml.find('input[name="ram[]"]').val('8GB');
    rowHtml.find('input[name="storage[]"]').val('256GB');
    $('#devices-tbody').append(rowHtml);
    if (typeof initSearchableSelects === 'function') {
        initSearchableSelects(rowHtml);
    }
});

$(document).on('click', '.btn-remove-row', function() {
    if ($('#devices-tbody tr').length > 1) {
        $(this).closest('tr').remove();
        recalcTotals();
    } else {
        alert('At least one device row is required.');
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
