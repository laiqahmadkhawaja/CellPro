<?php
// repairs/create.php - Create Simple Mobile Repair Bill
$page_title = 'New Mobile Repair Bill';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/accounting.php';
require_once __DIR__ . '/../includes/inventory.php';

$branch_id = getActiveBranchId();
$customers = $pdo->query("SELECT id, name, phone FROM customers ORDER BY name ASC")->fetchAll();
$brands = $pdo->query("SELECT id, name FROM brands WHERE status = 'active' ORDER BY name ASC")->fetchAll();
$spare_parts = $pdo->prepare("SELECT id, name, cost_price, retail_price, quantity FROM products WHERE branch_id = ? AND is_active = 1 ORDER BY name ASC");
$spare_parts->execute([$branch_id]);
$all_parts = $spare_parts->fetchAll();
$financial_accounts = getFinancialAccounts($pdo, $branch_id);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validatePostCsrf();
    $customer_name = sanitize($_POST['customer_name'] ?? '');
    $customer_phone = sanitize($_POST['customer_phone'] ?? '');
    $mobile_brand = sanitize($_POST['mobile_brand'] ?? '');
    $mobile_model = sanitize($_POST['mobile_model'] ?? '');
    $imei = trim($_POST['imei'] ?? '');
    $repair_date = sanitize($_POST['repair_date'] ?? date('Y-m-d'));
    $description = sanitize($_POST['description'] ?? '');

    $labour_charges = (float)($_POST['labour_charges'] ?? 0);
    $other_charges = (float)($_POST['other_charges'] ?? 0);
    $discount = (float)($_POST['discount'] ?? 0);

    $part_ids = $_POST['part_id'] ?? [];
    $part_qtys = $_POST['part_qty'] ?? [];
    $part_prices = $_POST['part_price'] ?? [];

    $payment_account_id = (int)$_POST['payment_account_id'];
    $payment_method = sanitize($_POST['payment_method'] ?? 'Cash');
    $paid_amount = (float)($_POST['paid_amount'] ?? 0);
    $remarks = sanitize($_POST['remarks'] ?? '');

    if (empty($customer_name) || empty($customer_phone) || empty($mobile_model)) {
        flash('danger', 'Customer Name, Mobile Phone Number, and Phone Model are required.');
        redirect(BASE_URL . 'repairs/create.php');
    }

    try {
        $pdo->beginTransaction();

        // 1. Calculate parts total and cost
        $parts_charges = 0.00;
        $total_parts_cost = 0.00;
        $parts_used = [];

        $pCheck = $pdo->prepare("SELECT id, name, cost_price, quantity FROM products WHERE id = ? AND branch_id = ? FOR UPDATE");

        for ($i = 0; $i < count($part_ids); $i++) {
            $pid = (int)$part_ids[$i];
            $qty = (int)$part_qtys[$i];
            $price = (float)$part_prices[$i];

            if ($pid <= 0 || $qty <= 0) continue;

            $pCheck->execute([$pid, $branch_id]);
            $prod = $pCheck->fetch();
            if (!$prod) continue;

            if ($prod['quantity'] < $qty) {
                throw new Exception("Insufficient stock for spare part '{$prod['name']}'. In stock: {$prod['quantity']}.");
            }

            $line_price = $qty * $price;
            $line_cost = $qty * (float)$prod['cost_price'];

            $parts_charges += $line_price;
            $total_parts_cost += $line_cost;

            $parts_used[] = [
                'product_id' => $pid,
                'name' => $prod['name'],
                'quantity' => $qty,
                'unit_price' => $price,
                'total_price' => $line_price,
                'cost' => $line_cost
            ];
        }

        $total_amount = max(0, ($labour_charges + $parts_charges + $other_charges) - $discount);
        $actual_paid = min($paid_amount, $total_amount);
        $remaining = max(0, $total_amount - $actual_paid);

        $bill_no = generateDocNo($pdo, 'REP', 'repairing_bills', 'bill_no');

        // Customer ID linking if found
        $customer_id = !empty($_POST['customer_id']) ? (int)$_POST['customer_id'] : null;

        $stmt = $pdo->prepare("INSERT INTO repairing_bills 
            (bill_no, branch_id, customer_id, customer_name, customer_phone, mobile_brand, mobile_model, imei, 
             repair_date, description, labour_charges, parts_charges, other_charges, discount, tax_amount, 
             total_amount, paid_amount, remaining_amount, payment_method, status, remarks, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0.00, ?, ?, ?, ?, 'completed', ?, ?)");
        $stmt->execute([
            $bill_no, $branch_id, $customer_id, $customer_name, $customer_phone, $mobile_brand, $mobile_model, $imei,
            $repair_date, $description, $labour_charges, $parts_charges, $other_charges, $discount,
            $total_amount, $actual_paid, $remaining, $payment_method, $remarks, $_SESSION['user_id']
        ]);
        $bill_id = $pdo->lastInsertId();

        // 2. Insert items and reduce spare part stock
        $itemStmt = $pdo->prepare("INSERT INTO repairing_bill_items (bill_id, item_type, product_id, description, quantity, unit_price, total_price) VALUES (?, ?, ?, ?, ?, ?, ?)");

        if ($labour_charges > 0) {
            $itemStmt->execute([$bill_id, 'labour', null, $description ?: 'Repair Labour Service', 1, $labour_charges, $labour_charges]);
        }

        foreach ($parts_used as $pu) {
            $itemStmt->execute([$bill_id, 'spare_part', $pu['product_id'], $pu['name'], $pu['quantity'], $pu['unit_price'], $pu['total_price']]);

            // Deduct product inventory
            recordStockMovement($pdo, $branch_id, $pu['product_id'], null, 'repair_use', -$pu['quantity'], $pu['cost'] / $pu['quantity'], 'repair', $bill_id, "Used in repair bill {$bill_no}");
        }

        // 3. Process Payment
        if ($actual_paid > 0) {
            $pdo->prepare("UPDATE financial_accounts SET current_balance = current_balance + ? WHERE id = ?")
                ->execute([$actual_paid, $payment_account_id]);
        }

        // Update customer balance if remaining due
        if ($customer_id && $remaining > 0) {
            $pdo->prepare("UPDATE customers SET current_balance = current_balance + ? WHERE id = ?")
                ->execute([$remaining, $customer_id]);
        }

        // 4. DOUBLE-ENTRY ACCOUNTING
        $cash_acct = getFinancialLedgerAccountId($pdo, $payment_account_id);
        $receivable_acct = getAccountIdByCode($pdo, '1003'); // Receivables
        $rep_rev_acct = getAccountIdByCode($pdo, '4003'); // Repairing Revenue
        $cogs_repair_acct = getAccountIdByCode($pdo, '5003'); // Cost of Spare Parts
        $inventory_acct = getAccountIdByCode($pdo, '1004'); // Inventory

        $j_lines = [];

        // Debit Cash/Bank
        if ($actual_paid > 0) {
            $j_lines[] = [
                'account_id' => $cash_acct,
                'debit' => $actual_paid,
                'credit' => 0.00,
                'description' => "Repair fee collected on {$bill_no}"
            ];
        }

        // Debit Customer Receivable if remaining
        if ($remaining > 0) {
            $j_lines[] = [
                'account_id' => $receivable_acct,
                'debit' => $remaining,
                'credit' => 0.00,
                'description' => "Remaining fee due on repair {$bill_no}"
            ];
        }

        // Credit Repairing Revenue
        $j_lines[] = [
            'account_id' => $rep_rev_acct,
            'debit' => 0.00,
            'credit' => $total_amount,
            'description' => "Repairing services revenue on {$bill_no}"
        ];

        // COGS for spare parts
        if ($total_parts_cost > 0) {
            $j_lines[] = [
                'account_id' => $cogs_repair_acct,
                'debit' => $total_parts_cost,
                'credit' => 0.00,
                'description' => "Cost of parts consumed in repair {$bill_no}"
            ];
            $j_lines[] = [
                'account_id' => $inventory_acct,
                'debit' => 0.00,
                'credit' => $total_parts_cost,
                'description' => "Inventory reduced for repair {$bill_no}"
            ];
        }

        postJournalEntry($pdo, $branch_id, 'repair', $bill_id, $j_lines, $repair_date, "Repair Bill {$bill_no}");

        logAudit($pdo, 'create', 'repairing_bills', $bill_id, "Created repair bill {$bill_no} total: {$total_amount}");

        $pdo->commit();
        flash('success', "Repair bill {$bill_no} created successfully!");
        redirect(BASE_URL . 'repairs/view.php?id=' . $bill_id);

    } catch (Exception $e) {
        $pdo->rollBack();
        flash('danger', 'Error creating repair bill: ' . $e->getMessage());
    }
}
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h5 class="fw-bold m-0"><i class="bi bi-tools text-warning me-2"></i>New Mobile Repair Bill</h5>
        <small class="text-muted">Record repair service, labour charges and replacement spare parts</small>
    </div>
    <a href="<?= BASE_URL ?>repairs/index.php" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i> Repair Bills List
    </a>
</div>

<form method="POST" action="">
    <?= csrfField() ?>

    <div class="row g-3">
        <!-- 1. Customer & Phone Details -->
        <div class="col-12 col-md-6">
            <div class="card h-100">
                <div class="card-header bg-light">
                    <span class="fw-bold"><i class="bi bi-person me-1 text-primary"></i> Customer & Device Info</span>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Customer Name *</label>
                        <input type="text" name="customer_name" class="form-control" placeholder="Customer Name" required>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Customer Mobile # *</label>
                            <input type="tel" name="customer_phone" class="form-control" placeholder="03001234567" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Repair Date</label>
                            <input type="date" name="repair_date" class="form-control" value="<?= date('Y-m-d') ?>">
                        </div>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Mobile Brand *</label>
                            <select name="mobile_brand" class="form-select" required>
                                <?php foreach ($brands as $b): ?>
                                    <option value="<?= htmlspecialchars($b['name']) ?>"><?= htmlspecialchars($b['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Mobile Model *</label>
                            <input type="text" name="mobile_model" class="form-control" placeholder="e.g. iPhone 13 or S23" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">IMEI (Optional)</label>
                        <input type="text" name="imei" class="form-control font-monospace" placeholder="15 digits...">
                    </div>
                    <div>
                        <label class="form-label small fw-semibold">Repair Fault / Problem Description</label>
                        <textarea name="description" class="form-control form-control-sm" rows="2" placeholder="e.g. Broken display glass replaced, speaker cleaned"></textarea>
                    </div>
                </div>
            </div>
        </div>

        <!-- 2. Labour Charges & Spare Parts Picker -->
        <div class="col-12 col-md-6">
            <div class="card h-100">
                <div class="card-header bg-light d-flex justify-content-between align-items-center">
                    <span class="fw-bold"><i class="bi bi-cpu me-1 text-info"></i> Labour & Spare Parts Consumed</span>
                    <button type="button" class="btn btn-primary btn-sm" id="btn-add-part-row">
                        <i class="bi bi-plus-lg me-1"></i> Add Spare Part
                    </button>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-dark">Technician Labour Service Charges (Rs.)</label>
                        <input type="number" step="0.01" name="labour_charges" id="rep-labour" class="form-control form-control-lg fw-bold text-primary" placeholder="0.00" value="1500.00">
                    </div>

                    <h6 class="small fw-bold text-muted border-top pt-2">Replacement Parts From Inventory</h6>
                    <div id="parts-container">
                        <div class="row g-2 align-items-center mb-2 part-row">
                            <div class="col-6">
                                <select name="part_id[]" class="form-select form-select-sm part-select">
                                    <option value="">-- Choose Spare Part --</option>
                                    <?php foreach ($all_parts as $ap): ?>
                                        <option value="<?= $ap['id'] ?>" data-retail="<?= $ap['retail_price'] ?>">
                                            <?= htmlspecialchars($ap['name']) ?> (Stock: <?= $ap['quantity'] ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-2">
                                <input type="number" name="part_qty[]" class="form-control form-control-sm text-center part-qty" value="1" min="1">
                            </div>
                            <div class="col-3">
                                <input type="number" step="0.01" name="part_price[]" class="form-control form-control-sm text-end part-price" placeholder="Price">
                            </div>
                            <div class="col-1 text-center">
                                <button type="button" class="btn btn-sm text-danger p-0 btn-remove-part"><i class="bi bi-trash"></i></button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 3. Payment & Grand Total -->
        <div class="col-12">
            <div class="card mb-4">
                <div class="card-header bg-light">
                    <span class="fw-bold"><i class="bi bi-wallet2 me-1 text-success"></i> Bill Total & Payment Settlement</span>
                </div>
                <div class="card-body">
                    <div class="row g-3 align-items-center">
                        <div class="col-6 col-md-2">
                            <label class="form-label small text-muted">Discount (Rs.)</label>
                            <input type="number" step="0.01" name="discount" id="rep-discount" class="form-control form-control-sm text-end" value="0.00">
                        </div>
                        <div class="col-6 col-md-2">
                            <label class="form-label small text-muted">Total Bill Amount</label>
                            <div class="h4 fw-bold text-primary m-0" id="rep-total">Rs. 0.00</div>
                        </div>
                        <div class="col-6 col-md-3">
                            <label class="form-label small fw-semibold">Payment Received Into</label>
                            <select name="payment_account_id" class="form-select form-select-sm">
                                <?php foreach ($financial_accounts as $fa): ?>
                                    <option value="<?= $fa['id'] ?>"><?= htmlspecialchars($fa['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-6 col-md-2">
                            <label class="form-label small fw-semibold">Amount Paid Now</label>
                            <input type="number" step="0.01" name="paid_amount" id="rep-paid" class="form-control form-control-sm fw-bold text-success" value="0.00">
                        </div>
                        <div class="col-12 col-md-3">
                            <label class="form-label small text-muted">Remaining Balance</label>
                            <div class="h5 fw-bold text-danger m-0" id="rep-rem">Rs. 0.00</div>
                        </div>
                    </div>
                </div>
                <div class="card-footer bg-white text-end">
                    <button type="submit" class="btn btn-primary btn-lg px-4 shadow-sm">
                        <i class="bi bi-check2-circle me-1"></i> Save & Generate Repair Bill
                    </button>
                </div>
            </div>
        </div>
    </div>
</form>

<script>
function calcRepairTotals() {
    let labour = parseFloat($('#rep-labour').val()) || 0;
    let parts = 0;
    $('.part-row').each(function() {
        let price = parseFloat($(this).find('.part-price').val()) || 0;
        let qty = parseInt($(this).find('.part-qty').val()) || 0;
        parts += (price * qty);
    });
    let discount = parseFloat($('#rep-discount').val()) || 0;
    let total = Math.max(0, (labour + parts) - discount);

    $('#rep-total').text('Rs. ' + total.toLocaleString(undefined, {minimumFractionDigits: 2}));

    if (!$('#rep-paid').val() || $('#rep-paid').val() == '0') {
        $('#rep-paid').val(total);
    }

    let paid = parseFloat($('#rep-paid').val()) || 0;
    let rem = Math.max(0, total - paid);
    $('#rep-rem').text('Rs. ' + rem.toLocaleString(undefined, {minimumFractionDigits: 2}));
}

$(document).on('change', '.part-select', function() {
    let retail = $(this).find('option:selected').data('retail');
    if (retail) {
        $(this).closest('.part-row').find('.part-price').val(retail);
        calcRepairTotals();
    }
});

$(document).on('input', '#rep-labour, .part-qty, .part-price, #rep-discount, #rep-paid', calcRepairTotals);

$('#btn-add-part-row').on('click', function() {
    let row = $('.part-row:first').clone();
    row.find('select').val('');
    row.find('.part-qty').val('1');
    row.find('.part-price').val('');
    $('#parts-container').append(row);
});

$(document).on('click', '.btn-remove-part', function() {
    if ($('.part-row').length > 1) {
        $(this).closest('.part-row').remove();
        calcRepairTotals();
    } else {
        $(this).closest('.part-row').find('select').val('');
        $(this).closest('.part-row').find('.part-price').val('');
        calcRepairTotals();
    }
});

$(document).ready(calcRepairTotals);
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
