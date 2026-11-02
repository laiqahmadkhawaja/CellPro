<?php
// purchases/accessories.php - Purchase Accessories from Suppliers
$page_title = 'Purchase Accessories';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/accounting.php';
require_once __DIR__ . '/../includes/inventory.php';

$branch_id = getActiveBranchId();
$suppliers = $pdo->query("SELECT id, name, phone, current_balance FROM suppliers ORDER BY name ASC")->fetchAll();
$all_prods = $pdo->prepare("SELECT id, name, barcode, sku, cost_price, quantity FROM products WHERE branch_id = ? AND is_active = 1 ORDER BY name ASC");
$all_prods->execute([$branch_id]);
$products = $all_prods->fetchAll();
$financial_accounts = getFinancialAccounts($pdo, $branch_id);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validatePostCsrf();
    $supplier_id = (int)$_POST['supplier_id'];
    $purchase_date = sanitize($_POST['purchase_date'] ?? date('Y-m-d'));
    $payment_account_id = (int)$_POST['payment_account_id'];
    $payment_method = sanitize($_POST['payment_method'] ?? 'Cash');
    $paid_amount = (float)($_POST['paid_amount'] ?? 0);
    $discount = (float)($_POST['discount'] ?? 0);
    $notes = sanitize($_POST['notes'] ?? '');

    $prod_ids = $_POST['product_id'] ?? [];
    $quantities = $_POST['quantity'] ?? [];
    $unit_costs = $_POST['cost_price'] ?? [];

    if (empty($prod_ids)) {
        flash('danger', 'Please select at least one accessory.');
        redirect(BASE_URL . 'purchases/accessories.php');
    }

    try {
        $pdo->beginTransaction();

        $subtotal = 0.00;
        $items_to_save = [];

        for ($i = 0; $i < count($prod_ids); $i++) {
            $pid = (int)$prod_ids[$i];
            $qty = (int)$quantities[$i];
            $cost = (float)$unit_costs[$i];

            if ($pid <= 0 || $qty <= 0) continue;

            $line_total = $qty * $cost;
            $subtotal += $line_total;

            $items_to_save[] = [
                'product_id' => $pid,
                'quantity' => $qty,
                'unit_price' => $cost,
                'total_cost' => $line_total
            ];
        }

        if (empty($items_to_save)) {
            throw new Exception("No valid items entered.");
        }

        $grand_total = max(0, $subtotal - $discount);
        $actual_paid = min($paid_amount, $grand_total);
        $remaining = max(0, $grand_total - $actual_paid);
        $status = ($remaining == 0) ? 'paid' : (($actual_paid > 0) ? 'partial' : 'unpaid');

        // Create Purchase
        $purchase_no = generateDocNo($pdo, 'PUR-A', 'purchases', 'purchase_no');
        $stmt = $pdo->prepare("INSERT INTO purchases 
            (purchase_no, branch_id, supplier_id, purchase_type, purchase_date, subtotal, discount, tax_amount, additional_cost, grand_total, paid_amount, payment_status, notes, created_by)
            VALUES (?, ?, ?, 'accessory', ?, ?, ?, 0.00, 0.00, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $purchase_no, $branch_id, $supplier_id, $purchase_date, $subtotal, $discount, $grand_total, $actual_paid, $status, $notes, $_SESSION['user_id']
        ]);
        $purchase_id = $pdo->lastInsertId();

        // Items & Stock Movements
        $itemStmt = $pdo->prepare("INSERT INTO purchase_items (purchase_id, product_id, item_name, quantity, unit_price, additional_cost, total_cost) VALUES (?, ?, ?, ?, ?, 0.00, ?)");
        $pNameStmt = $pdo->prepare("SELECT name FROM products WHERE id = ?");

        foreach ($items_to_save as $item) {
            $pNameStmt->execute([$item['product_id']]);
            $pName = $pNameStmt->fetchColumn() ?: 'Accessory';

            $itemStmt->execute([$purchase_id, $item['product_id'], $pName, $item['quantity'], $item['unit_price'], $item['total_cost']]);

            // Update cost_price on product if changed, and record movement
            $pdo->prepare("UPDATE products SET cost_price = ? WHERE id = ?")->execute([$item['unit_price'], $item['product_id']]);
            recordStockMovement($pdo, $branch_id, $item['product_id'], null, 'purchase', $item['quantity'], $item['unit_price'], 'purchase', $purchase_id, "Purchased in {$purchase_no}");
        }

        // Record Payment
        if ($actual_paid > 0) {
            $payStmt = $pdo->prepare("INSERT INTO purchase_payments (purchase_id, branch_id, account_id, payment_method, amount, payment_date, notes) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $payStmt->execute([$purchase_id, $branch_id, $payment_account_id, $payment_method, $actual_paid, $purchase_date, "Payment for {$purchase_no}"]);

            $pdo->prepare("UPDATE financial_accounts SET current_balance = current_balance - ? WHERE id = ?")->execute([$actual_paid, $payment_account_id]);
        }

        // Update Supplier balance
        if ($remaining > 0) {
            $pdo->prepare("UPDATE suppliers SET current_balance = current_balance + ? WHERE id = ?")->execute([$remaining, $supplier_id]);
        }

        // DOUBLE-ENTRY ACCOUNTING
        $inventory_acct = getAccountIdByCode($pdo, '1004'); // Merchandise Inventory
        $supplier_acct = getAccountIdByCode($pdo, '2001'); // Supplier Payables
        $cash_bank_acct = getFinancialLedgerAccountId($pdo, $payment_account_id);

        $j_lines = [
            [
                'account_id' => $inventory_acct,
                'debit' => $grand_total,
                'credit' => 0.00,
                'description' => "Accessories inventory acquired {$purchase_no}"
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

        postJournalEntry($pdo, $branch_id, 'purchase', $purchase_id, $j_lines, $purchase_date, "Accessory Purchase {$purchase_no}");

        logAudit($pdo, 'create', 'purchases', $purchase_id, "Recorded accessory purchase {$purchase_no}");

        $pdo->commit();
        flash('success', "Accessory purchase {$purchase_no} recorded successfully!");
        redirect(BASE_URL . 'products/accessories.php');

    } catch (Exception $e) {
        $pdo->rollBack();
        flash('danger', 'Error recording purchase: ' . $e->getMessage());
    }
}
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h5 class="fw-bold m-0"><i class="bi bi-bag-plus text-primary me-2"></i>Purchase Accessories & Spare Parts</h5>
        <small class="text-muted">Stock procurement with supplier ledgers & accounting</small>
    </div>
    <a href="<?= BASE_URL ?>purchases/index.php" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i> Purchases List
    </a>
</div>

<form method="POST" action="">
    <?= csrfField() ?>

    <div class="card mb-3">
        <div class="card-header bg-light">
            <span class="fw-bold"><i class="bi bi-truck me-1 text-primary"></i> Supplier & Date</span>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-12 col-md-5">
                    <label class="form-label small fw-semibold">Select Supplier *</label>
                    <select name="supplier_id" class="form-select" required>
                        <option value="">-- Choose Supplier --</option>
                        <?php foreach ($suppliers as $s): ?>
                            <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['name']) ?> (<?= $s['phone'] ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-6 col-md-3">
                    <label class="form-label small fw-semibold">Purchase Date</label>
                    <input type="date" name="purchase_date" class="form-control" value="<?= date('Y-m-d') ?>">
                </div>
                <div class="col-6 col-md-4">
                    <label class="form-label small fw-semibold">Supplier Invoice / Ref #</label>
                    <input type="text" name="notes" class="form-control" placeholder="Supplier Bill #">
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header bg-light d-flex justify-content-between align-items-center">
            <span class="fw-bold"><i class="bi bi-list-check me-1 text-primary"></i> Purchased Items</span>
            <button type="button" class="btn btn-primary btn-sm" id="btn-add-acc-row">
                <i class="bi bi-plus-lg me-1"></i> Add Item
            </button>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-bordered align-middle mb-0">
                    <thead class="table-light small text-center">
                        <tr>
                            <th style="min-width: 250px;">Accessory / Part *</th>
                            <th style="min-width: 120px;">Unit Cost (Rs.) *</th>
                            <th style="min-width: 100px;">Quantity *</th>
                            <th style="min-width: 140px;">Line Total</th>
                            <th style="width: 40px;"></th>
                        </tr>
                    </thead>
                    <tbody id="acc-tbody">
                        <tr class="acc-row">
                            <td>
                                <select name="product_id[]" class="form-select form-select-sm prod-select" required>
                                    <option value="">-- Select Product --</option>
                                    <?php foreach ($products as $p): ?>
                                        <option value="<?= $p['id'] ?>" data-cost="<?= $p['cost_price'] ?>">
                                            <?= htmlspecialchars($p['name']) ?> (Stock: <?= $p['quantity'] ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td><input type="number" step="0.01" name="cost_price[]" class="form-control form-control-sm text-end acc-cost" placeholder="0.00" required></td>
                            <td><input type="number" name="quantity[]" class="form-control form-control-sm text-center acc-qty" value="10" min="1" required></td>
                            <td class="text-end fw-bold acc-linetotal">Rs. 0.00</td>
                            <td class="text-center">
                                <button type="button" class="btn btn-sm text-danger p-0 btn-remove-acc"><i class="bi bi-trash"></i></button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header bg-light">
            <span class="fw-bold"><i class="bi bi-cash-stack me-1 text-success"></i> Payment & Accounting Summary</span>
        </div>
        <div class="card-body">
            <div class="row g-3 align-items-center">
                <div class="col-6 col-md-2">
                    <label class="form-label small text-muted">Subtotal</label>
                    <div class="h5 fw-bold text-dark m-0" id="acc-subtotal">Rs. 0.00</div>
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label small fw-semibold">Discount</label>
                    <input type="number" step="0.01" name="discount" id="acc-discount" class="form-control form-control-sm text-end" value="0.00">
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label small text-muted">Grand Total</label>
                    <div class="h5 fw-bold text-primary m-0" id="acc-grandtotal">Rs. 0.00</div>
                </div>
                <div class="col-6 col-md-3">
                    <label class="form-label small fw-semibold">Paid from Account</label>
                    <select name="payment_account_id" class="form-select form-select-sm">
                        <?php foreach ($financial_accounts as $fa): ?>
                            <option value="<?= $fa['id'] ?>"><?= htmlspecialchars($fa['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-6 col-md-3">
                    <label class="form-label small fw-semibold">Amount Paid</label>
                    <input type="number" step="0.01" name="paid_amount" id="acc-paid" class="form-control form-control-sm fw-bold text-success" value="0.00">
                </div>
            </div>
        </div>
        <div class="card-footer bg-white text-end">
            <button type="submit" class="btn btn-primary btn-lg px-4 shadow-sm">
                <i class="bi bi-check2-circle me-1"></i> Save Accessory Purchase
            </button>
        </div>
    </div>
</form>

<script>
function calcAccTotals() {
    let subtotal = 0;
    $('.acc-row').each(function() {
        let cost = parseFloat($(this).find('.acc-cost').val()) || 0;
        let qty = parseInt($(this).find('.acc-qty').val()) || 0;
        let line = cost * qty;
        $(this).find('.acc-linetotal').text('Rs. ' + line.toLocaleString(undefined, {minimumFractionDigits: 2}));
        subtotal += line;
    });

    let discount = parseFloat($('#acc-discount').val()) || 0;
    let grand = Math.max(0, subtotal - discount);

    $('#acc-subtotal').text('Rs. ' + subtotal.toLocaleString(undefined, {minimumFractionDigits: 2}));
    $('#acc-grandtotal').text('Rs. ' + grand.toLocaleString(undefined, {minimumFractionDigits: 2}));
}

$(document).on('change', '.prod-select', function() {
    let cost = $(this).find('option:selected').data('cost');
    if (cost) {
        $(this).closest('tr').find('.acc-cost').val(cost);
        calcAccTotals();
    }
});

$(document).on('input', '.acc-cost, .acc-qty, #acc-discount', calcAccTotals);

$('#btn-add-acc-row').on('click', function() {
    let row = $('#acc-tbody tr:first').clone();
    row.find('select').val('');
    row.find('.acc-cost').val('');
    row.find('.acc-qty').val('10');
    row.find('.acc-linetotal').text('Rs. 0.00');
    $('#acc-tbody').append(row);
});

$(document).on('click', '.btn-remove-acc', function() {
    if ($('#acc-tbody tr').length > 1) {
        $(this).closest('tr').remove();
        calcAccTotals();
    } else {
        alert('At least one row is required.');
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
