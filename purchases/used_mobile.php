<?php
// purchases/used_mobile.php - Used Mobile Purchasing & Physical Inspection Checklist
$page_title = 'Buy / Inspect Used Mobile Phone';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/accounting.php';
require_once __DIR__ . '/../includes/inventory.php';

$branch_id = getActiveBranchId();
$customers = $pdo->query("SELECT id, name, phone, cnic FROM customers ORDER BY name ASC")->fetchAll();
$brands = $pdo->query("SELECT id, name FROM brands WHERE status = 'active' ORDER BY name ASC")->fetchAll();
$financial_accounts = getFinancialAccounts($pdo, $branch_id);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validatePostCsrf();
    $seller_name = sanitize($_POST['seller_name'] ?? '');
    $seller_phone = sanitize($_POST['seller_phone'] ?? '');
    $seller_cnic = sanitize($_POST['seller_cnic'] ?? '');
    $purchase_date = sanitize($_POST['purchase_date'] ?? date('Y-m-d'));

    $model_name = sanitize($_POST['model_name'] ?? '');
    $brand_id = !empty($_POST['brand_id']) ? (int)$_POST['brand_id'] : null;
    $ram = sanitize($_POST['ram'] ?? '6GB');
    $storage = sanitize($_POST['storage'] ?? '128GB');
    $color = sanitize($_POST['color'] ?? 'Black');
    $imei_1 = trim($_POST['imei_1'] ?? '');
    $imei_2 = !empty($_POST['imei_2']) ? trim($_POST['imei_2']) : null;
    $serial_no = sanitize($_POST['serial_number'] ?? '');
    $pta_status = sanitize($_POST['pta_status'] ?? 'approved');
    $battery_health = (int)($_POST['battery_health'] ?? 85);

    // Inspection Fields
    $screen_cond = sanitize($_POST['screen_condition'] ?? 'Good');
    $body_cond = sanitize($_POST['body_condition'] ?? 'Good');
    $cam_cond = sanitize($_POST['camera_condition'] ?? 'Working');
    $biometrics = sanitize($_POST['biometrics'] ?? 'Working');
    $charging_port = sanitize($_POST['charging_port'] ?? 'Working');
    $speaker_mic = sanitize($_POST['speaker_mic'] ?? 'Working');
    $accessories_rec = sanitize($_POST['accessories_received'] ?? 'None');
    $faults = sanitize($_POST['existing_faults'] ?? 'None');
    $remarks = sanitize($_POST['inspection_remarks'] ?? '');

    // Financials
    $purchase_price = (float)($_POST['purchase_price'] ?? 0);
    $additional_cost = (float)($_POST['additional_cost'] ?? 0);
    $actual_cost = $purchase_price + $additional_cost;
    $selling_price = (float)($_POST['selling_price'] ?? ($actual_cost * 1.15));

    $payment_account_id = (int)$_POST['payment_account_id'];
    $payment_method = sanitize($_POST['payment_method'] ?? 'Cash');
    $paid_amount = (float)($_POST['paid_amount'] ?? $actual_cost);

    if (empty($model_name) || empty($imei_1) || $purchase_price <= 0) {
        flash('danger', 'Model name, IMEI 1, and Agreed Purchase Price are required.');
        redirect(BASE_URL . 'purchases/used_mobile.php');
    }

    try {
        $pdo->beginTransaction();

        // IMEI Uniqueness check
        $check = $pdo->prepare("SELECT id FROM mobile_devices WHERE imei_1 = ? OR (imei_2 IS NOT NULL AND imei_2 = ?)");
        $check->execute([$imei_1, $imei_1]);
        if ($check->fetch()) {
            throw new Exception("IMEI 1 '{$imei_1}' already exists in the system.");
        }
        if ($imei_2) {
            $check->execute([$imei_2, $imei_2]);
            if ($check->fetch()) {
                throw new Exception("IMEI 2 '{$imei_2}' already exists in the system.");
            }
        }

        // Link or create customer record for the seller
        $customer_id = !empty($_POST['customer_id']) ? (int)$_POST['customer_id'] : null;
        if (!$customer_id && !empty($seller_name) && !empty($seller_phone)) {
            $cStmt = $pdo->prepare("SELECT id FROM customers WHERE phone = ? LIMIT 1");
            $cStmt->execute([$seller_phone]);
            $existing_cid = $cStmt->fetchColumn();
            if ($existing_cid) {
                $customer_id = $existing_cid;
            } else {
                $insC = $pdo->prepare("INSERT INTO customers (name, phone, cnic, address) VALUES (?, ?, ?, 'Seller / Trade-in')");
                $insC->execute([$seller_name, $seller_phone, $seller_cnic]);
                $customer_id = $pdo->lastInsertId();
            }
        }

        // Create Device Record
        $devStmt = $pdo->prepare("INSERT INTO mobile_devices 
            (branch_id, brand_id, model_name, ram, storage, color, imei_1, imei_2, serial_number, device_condition, 
             battery_health, pta_status, warranty_months, purchase_price, additional_cost, actual_cost, selling_price, 
             device_status, purchase_date, description)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'used', ?, ?, 0, ?, ?, ?, ?, 'available', ?, ?)");
        
        $desc = "Used phone bought from {$seller_name} ({$seller_phone}). " . $remarks;
        $devStmt->execute([
            $branch_id, $brand_id, $model_name, $ram, $storage, $color, $imei_1, $imei_2, $serial_no,
            $battery_health, $pta_status, $purchase_price, $additional_cost, $actual_cost, $selling_price,
            $purchase_date, $desc
        ]);
        $device_id = $pdo->lastInsertId();

        // Create Device Inspection Record
        $inspStmt = $pdo->prepare("INSERT INTO device_inspections 
            (device_id, screen_condition, body_condition, battery_health, camera_condition, biometrics, 
             charging_port, speaker_mic, pta_status, accessories_received, existing_faults, remarks, inspected_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $inspStmt->execute([
            $device_id, $screen_cond, $body_cond, $battery_health, $cam_cond, $biometrics,
            $charging_port, $speaker_mic, $pta_status, $accessories_rec, $faults, $remarks, $_SESSION['user_id']
        ]);

        // Create Purchase Invoice
        $purchase_no = generateDocNo($pdo, 'PUR-U', 'purchases', 'purchase_no');
        $grand_total = $actual_cost;
        $actual_paid = min($paid_amount, $grand_total);
        $rem_payable = max(0, $grand_total - $actual_paid);
        $p_status = ($rem_payable == 0) ? 'paid' : (($actual_paid > 0) ? 'partial' : 'unpaid');

        $purStmt = $pdo->prepare("INSERT INTO purchases 
            (purchase_no, branch_id, seller_name, seller_phone, seller_cnic, purchase_type, purchase_date, subtotal, 
             discount, tax_amount, additional_cost, grand_total, paid_amount, payment_status, notes, created_by)
            VALUES (?, ?, ?, ?, ?, 'used_mobile', ?, ?, 0.00, 0.00, ?, ?, ?, ?, ?, ?)");
        $purStmt->execute([
            $purchase_no, $branch_id, $seller_name, $seller_phone, $seller_cnic, $purchase_date, $purchase_price,
            $additional_cost, $grand_total, $actual_paid, $p_status, $remarks, $_SESSION['user_id']
        ]);
        $purchase_id = $pdo->lastInsertId();

        // Purchase Item
        $pItem = $pdo->prepare("INSERT INTO purchase_items (purchase_id, device_id, item_name, quantity, unit_price, additional_cost, total_cost) VALUES (?, ?, ?, 1, ?, ?, ?)");
        $pItem->execute([$purchase_id, $device_id, "{$model_name} (IMEI: {$imei_1})", $purchase_price, $additional_cost, $actual_cost]);

        // Stock movement
        recordStockMovement($pdo, $branch_id, null, $device_id, 'purchase', 1, $actual_cost, 'purchase', $purchase_id, "Used phone bought from customer in {$purchase_no}");

        // Payment record
        if ($actual_paid > 0) {
            $pPay = $pdo->prepare("INSERT INTO purchase_payments (purchase_id, branch_id, account_id, payment_method, amount, payment_date, notes) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $pPay->execute([$purchase_id, $branch_id, $payment_account_id, $payment_method, $actual_paid, $purchase_date, "Cash paid to seller {$seller_name}"]);

            $pdo->prepare("UPDATE financial_accounts SET current_balance = current_balance - ? WHERE id = ?")
                ->execute([$actual_paid, $payment_account_id]);
        }

        // DOUBLE-ENTRY ACCOUNTING POSTING
        $inventory_acct = getAccountIdByCode($pdo, '1004'); // Merchandise Inventory
        $cash_bank_acct = getFinancialLedgerAccountId($pdo, $payment_account_id);
        $payables_acct = getAccountIdByCode($pdo, '2001'); // Supplier / Other Payables

        $j_lines = [
            [
                'account_id' => $inventory_acct,
                'debit' => $grand_total,
                'credit' => 0.00,
                'description' => "Used phone inventory received {$purchase_no}"
            ]
        ];

        if ($actual_paid > 0) {
            $j_lines[] = [
                'account_id' => $cash_bank_acct,
                'debit' => 0.00,
                'credit' => $actual_paid,
                'description' => "Payment made to customer for {$purchase_no}"
            ];
        }

        if ($rem_payable > 0) {
            $j_lines[] = [
                'account_id' => $payables_acct,
                'debit' => 0.00,
                'credit' => $rem_payable,
                'description' => "Remaining payable to seller on {$purchase_no}"
            ];
        }

        postJournalEntry($pdo, $branch_id, 'purchase', $purchase_id, $j_lines, $purchase_date, "Used Phone Purchase {$purchase_no}");

        logAudit($pdo, 'create', 'purchases', $purchase_id, "Purchased used phone {$model_name} (IMEI: {$imei_1})");

        $pdo->commit();
        flash('success', "Used phone {$model_name} (IMEI: {$imei_1}) registered and added to available inventory!");
        redirect(BASE_URL . 'products/mobile_view.php?id=' . $device_id);

    } catch (Exception $e) {
        $pdo->rollBack();
        flash('danger', 'Error purchasing used mobile: ' . $e->getMessage());
    }
}
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h5 class="fw-bold m-0"><i class="bi bi-arrow-repeat text-info me-2"></i>Buy / Inspect Used Mobile Phone</h5>
        <small class="text-muted">Purchase used phone directly from customer with comprehensive condition checklist</small>
    </div>
    <a href="<?= BASE_URL ?>products/mobiles.php" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i> Mobiles Inventory
    </a>
</div>

<form method="POST" action="">
    <?= csrfField() ?>

    <div class="row g-3">
        <!-- 1. Seller & General Info -->
        <div class="col-12 col-lg-6">
            <div class="card h-100">
                <div class="card-header bg-light">
                    <span class="fw-bold"><i class="bi bi-person me-1 text-primary"></i> Customer / Seller Information</span>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Seller Full Name *</label>
                        <input type="text" name="seller_name" class="form-control" placeholder="e.g. Kamran Rafiq" required>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Mobile Number *</label>
                            <input type="tel" name="seller_phone" class="form-control" placeholder="03001234567" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-semibold">CNIC / ID Card No.</label>
                            <input type="text" name="seller_cnic" class="form-control" placeholder="35201-xxxxxxx-x">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Purchase Date</label>
                        <input type="date" name="purchase_date" class="form-control" value="<?= date('Y-m-d') ?>">
                    </div>

                    <h6 class="border-top pt-3 small fw-bold text-primary">Device Model & Identifiers</h6>
                    <div class="row g-2 mb-3">
                        <div class="col-8">
                            <label class="form-label small fw-semibold">Model Name *</label>
                            <input type="text" name="model_name" class="form-control" placeholder="e.g. iPhone 13 Pro 128GB" required>
                        </div>
                        <div class="col-4">
                            <label class="form-label small fw-semibold">Brand</label>
                            <select name="brand_id" class="form-select">
                                <?php foreach ($brands as $b): ?>
                                    <option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-semibold">IMEI 1 *</label>
                            <input type="text" name="imei_1" class="form-control font-monospace" placeholder="15 digits" required maxlength="18">
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-semibold">IMEI 2 (Optional)</label>
                            <input type="text" name="imei_2" class="form-control font-monospace" placeholder="15 digits" maxlength="18">
                        </div>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-4">
                            <label class="form-label small fw-semibold">RAM</label>
                            <input type="text" name="ram" class="form-control form-control-sm" value="6GB">
                        </div>
                        <div class="col-4">
                            <label class="form-label small fw-semibold">Storage</label>
                            <input type="text" name="storage" class="form-control form-control-sm" value="128GB">
                        </div>
                        <div class="col-4">
                            <label class="form-label small fw-semibold">Color</label>
                            <input type="text" name="color" class="form-control form-control-sm" value="Midnight Blue">
                        </div>
                    </div>

                    <div class="row g-2">
                        <div class="col-6">
                            <label class="form-label small fw-semibold">PTA Status</label>
                            <select name="pta_status" class="form-select form-select-sm">
                                <option value="approved">PTA Approved</option>
                                <option value="non_approved">Non-Approved</option>
                                <option value="cpid">CPID Approved</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Battery Health %</label>
                            <input type="number" name="battery_health" class="form-control form-control-sm" value="88" min="1" max="100">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 2. Inspection Checklist -->
        <div class="col-12 col-lg-6">
            <div class="card h-100">
                <div class="card-header bg-light">
                    <span class="fw-bold"><i class="bi bi-shield-check me-1 text-info"></i> Physical Inspection Checklist</span>
                </div>
                <div class="card-body">
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Screen Condition</label>
                            <select name="screen_condition" class="form-select form-select-sm">
                                <option value="Pristine (No scratches)">Pristine (No scratches)</option>
                                <option value="Minor hairline scratches">Minor hairline scratches</option>
                                <option value="Noticeable scratches">Noticeable scratches</option>
                                <option value="Screen replaced / Non-genuine">Screen replaced / Non-genuine</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Body / Housing</label>
                            <select name="body_condition" class="form-select form-select-sm">
                                <option value="Flawless 10/10">Flawless 10/10</option>
                                <option value="9.5/10 Very Clean">9.5/10 Very Clean</option>
                                <option value="8.5/10 Corner Dents/Scuffs">8.5/10 Corner Dents/Scuffs</option>
                                <option value="Heavy wear / Bent">Heavy wear / Bent</option>
                            </select>
                        </div>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Camera Condition</label>
                            <select name="camera_condition" class="form-select form-select-sm">
                                <option value="Working Perfectly (All lenses clear)">Working Perfectly</option>
                                <option value="Lens scratched / Dust inside">Lens scratched / Dust inside</option>
                                <option value="0.5x Ultra-wide not working">0.5x Ultra-wide issue</option>
                                <option value="Camera faulty / Black screen">Camera faulty</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Biometrics</label>
                            <select name="biometrics" class="form-select form-select-sm">
                                <option value="Working (Face ID / Fingerprint)">Working (Face/Touch ID)</option>
                                <option value="Face ID disabled / TrueDepth issue">Face ID disabled</option>
                                <option value="Touch ID not responding">Touch ID not responding</option>
                            </select>
                        </div>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Charging Port & Cable</label>
                            <input type="text" name="charging_port" class="form-control form-control-sm" value="Working smoothly">
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Speakers & Microphone</label>
                            <input type="text" name="speaker_mic" class="form-control form-control-sm" value="Clear sound, loud">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Accessories Received with Phone</label>
                        <input type="text" name="accessories_received" class="form-control form-control-sm" placeholder="e.g. Original Box, 20W Charger, Cable">
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Known Existing Faults / Defects</label>
                        <input type="text" name="existing_faults" class="form-control form-control-sm" placeholder="e.g. None or TrueTone missing">
                    </div>

                    <div class="mb-0">
                        <label class="form-label small fw-semibold">Technician / Inspection Remarks</label>
                        <textarea name="inspection_remarks" class="form-control form-control-sm" rows="2" placeholder="General testing notes..."></textarea>
                    </div>
                </div>
            </div>
        </div>

        <!-- 3. Valuation & Cash Outflow -->
        <div class="col-12">
            <div class="card mb-4">
                <div class="card-header bg-light">
                    <span class="fw-bold"><i class="bi bi-cash-stack me-1 text-success"></i> Valuation, Selling Price & Payment Account</span>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-12 col-md-3">
                            <label class="form-label small fw-bold">Agreed Purchase Price (Rs.) *</label>
                            <input type="number" step="0.01" name="purchase_price" id="u-price" class="form-control form-control-lg fw-bold text-primary" placeholder="0.00" required>
                        </div>
                        <div class="col-6 col-md-2">
                            <label class="form-label small fw-semibold">Cleaning/Refurb Cost</label>
                            <input type="number" step="0.01" name="additional_cost" id="u-extra" class="form-control" value="0.00">
                        </div>
                        <div class="col-6 col-md-2">
                            <label class="form-label small text-muted">Total Inventory Cost</label>
                            <div class="h5 fw-bold text-dark mt-1" id="u-actual">Rs. 0.00</div>
                        </div>
                        <div class="col-6 col-md-2">
                            <label class="form-label small fw-bold text-success">Target Selling Price</label>
                            <input type="number" step="0.01" name="selling_price" id="u-sell" class="form-control fw-bold" placeholder="0.00">
                        </div>
                        <div class="col-6 col-md-3">
                            <label class="form-label small fw-semibold">Paid from Cash Till / Account</label>
                            <select name="payment_account_id" class="form-select">
                                <?php foreach ($financial_accounts as $fa): ?>
                                    <option value="<?= $fa['id'] ?>"><?= htmlspecialchars($fa['name']) ?> (Balance: Rs. <?= number_format($fa['current_balance']) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                            <div class="mt-2">
                                <label class="form-label small text-muted">Amount Handed to Seller</label>
                                <input type="number" step="0.01" name="paid_amount" id="u-paid" class="form-control form-control-sm fw-bold">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-footer bg-white text-end">
                    <button type="submit" class="btn btn-primary btn-lg px-4 shadow-sm">
                        <i class="bi bi-check-circle me-1"></i> Confirm Purchase & Add to Stock
                    </button>
                </div>
            </div>
        </div>
    </div>
</form>

<script>
function updateCosts() {
    let p = parseFloat($('#u-price').val()) || 0;
    let extra = parseFloat($('#u-extra').val()) || 0;
    let total = p + extra;
    $('#u-actual').text('Rs. ' + total.toLocaleString(undefined, {minimumFractionDigits: 2}));
    if (!$('#u-sell').val() || $('#u-sell').val() == '0') {
        $('#u-sell').val(Math.round(total * 1.15));
    }
    if (!$('#u-paid').val() || $('#u-paid').val() == '0') {
        $('#u-paid').val(total);
    }
}
$('#u-price, #u-extra').on('input', updateCosts);
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
