<?php
// api/pos_checkout.php
// POS Transaction Checkout with Double-Entry Accounting and IMEI tracking

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/accounting.php';
require_once __DIR__ . '/../includes/inventory.php';

requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['status' => 'error', 'message' => 'Invalid request method.'], 405);
}

// Get JSON or POST body
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    $input = $_POST;
}

$branch_id = getActiveBranchId();
$user_id = $_SESSION['user_id'];

$customer_id = !empty($input['customer_id']) ? (int)$input['customer_id'] : 1; // 1 = Walk-in
$items = $input['items'] ?? [];
$invoice_discount = (float)($input['invoice_discount'] ?? 0);
$tax_rate = (float)($input['tax_rate'] ?? 0);
$payment_account_id = !empty($input['payment_account_id']) ? (int)$input['payment_account_id'] : 1;
$payment_method = sanitize($input['payment_method'] ?? 'Cash');
$paid_amount = (float)($input['paid_amount'] ?? 0);
$notes = sanitize($input['notes'] ?? '');

// Trade-in details (optional)
$has_trade_in = !empty($input['trade_in']['model_name']) && (float)($input['trade_in']['value'] ?? 0) > 0;
$trade_in_value = $has_trade_in ? (float)$input['trade_in']['value'] : 0.00;

if (empty($items)) {
    jsonResponse(['status' => 'error', 'message' => 'Your cart is empty. Please select products to sell.'], 400);
}

try {
    $pdo->beginTransaction();

    // 1. Validate & Lock Items
    $subtotal = 0.00;
    $total_cogs_mobile = 0.00;
    $total_cogs_accessory = 0.00;
    $validated_items = [];

    foreach ($items as $item) {
        $type = $item['type']; // 'mobile' or 'accessory'
        $price = (float)$item['price'];
        $discount = (float)($item['discount'] ?? 0);
        $qty = (int)($item['quantity'] ?? 1);
        if ($qty <= 0) $qty = 1;

        if ($type === 'mobile') {
            $device_id = (int)$item['device_id'];
            $devStmt = $pdo->prepare("SELECT * FROM mobile_devices WHERE id = ? AND branch_id = ? FOR UPDATE");
            $devStmt->execute([$device_id, $branch_id]);
            $device = $devStmt->fetch();

            if (!$device || $device['device_status'] !== 'available') {
                throw new Exception("Mobile phone '{$item['name']}' (IMEI: {$item['imei']}) is no longer available in this branch.");
            }

            if ($device['device_condition'] === 'used') {
                $qty = 1;
                $cost = (float)$device['actual_cost'];
                $line_total = ($price * 1) - $discount;
                $subtotal += $line_total;
                $total_cogs_mobile += $cost;

                $validated_items[] = [
                    'type' => 'mobile',
                    'condition' => 'used',
                    'product_id' => null,
                    'device_id' => $device['id'],
                    'device_ids' => [$device['id']],
                    'name' => $device['model_name'],
                    'imei' => $device['imei_1'],
                    'quantity' => 1,
                    'unit_cost' => $cost,
                    'unit_price' => $price,
                    'discount' => $discount,
                    'total_price' => $line_total,
                    'gross_profit' => $line_total - $cost
                ];
            } else {
                // New phone: match available stock of this model in this branch
                $stockStmt = $pdo->prepare("SELECT id, actual_cost, imei_1 FROM mobile_devices 
                    WHERE branch_id = ? AND device_status = 'available' AND device_condition = 'new' AND model_name = ?
                    LIMIT ? FOR UPDATE");
                $stockStmt->bindValue(1, $branch_id, PDO::PARAM_INT);
                $stockStmt->bindValue(2, $device['model_name'], PDO::PARAM_STR);
                $stockStmt->bindValue(3, $qty, PDO::PARAM_INT);
                $stockStmt->execute();
                $matchedDevices = $stockStmt->fetchAll();

                if (count($matchedDevices) < $qty) {
                    throw new Exception("Insufficient stock for new mobile '{$device['model_name']}'. Available: " . count($matchedDevices) . ", requested: {$qty}.");
                }

                $total_cost = 0;
                $matched_ids = [];
                foreach ($matchedDevices as $md) {
                    $total_cost += (float)$md['actual_cost'];
                    $matched_ids[] = (int)$md['id'];
                }
                $avg_cost = $total_cost / $qty;
                $line_total = ($price * $qty) - $discount;
                $subtotal += $line_total;
                $total_cogs_mobile += $total_cost;

                $validated_items[] = [
                    'type' => 'mobile',
                    'condition' => 'new',
                    'product_id' => null,
                    'device_id' => $device['id'],
                    'device_ids' => $matched_ids,
                    'name' => $device['model_name'],
                    'imei' => 'NEW-PACK',
                    'quantity' => $qty,
                    'unit_cost' => $avg_cost,
                    'unit_price' => $price,
                    'discount' => $discount,
                    'total_price' => $line_total,
                    'gross_profit' => $line_total - $total_cost
                ];
            }
        } else {
            $product_id = (int)$item['product_id'];
            $prodStmt = $pdo->prepare("SELECT * FROM products WHERE id = ? AND branch_id = ? FOR UPDATE");
            $prodStmt->execute([$product_id, $branch_id]);
            $prod = $prodStmt->fetch();

            if (!$prod) {
                throw new Exception("Product '{$item['name']}' not found.");
            }
            if ($prod['quantity'] < $qty) {
                throw new Exception("Insufficient stock for '{$prod['name']}'. Available: {$prod['quantity']}, requested: {$qty}.");
            }

            $cost = (float)$prod['cost_price'];
            $line_total = ($price * $qty) - $discount;
            $subtotal += $line_total;
            $total_cogs_accessory += ($cost * $qty);

            $validated_items[] = [
                'type' => 'accessory',
                'product_id' => $prod['id'],
                'device_id' => null,
                'name' => $prod['name'],
                'imei' => null,
                'quantity' => $qty,
                'unit_cost' => $cost,
                'unit_price' => $price,
                'discount' => $discount,
                'total_price' => $line_total,
                'gross_profit' => $line_total - ($cost * $qty)
            ];
        }
    }

    // 2. Calculate Final Invoice Totals
    $tax_amount = ($subtotal - $invoice_discount) * ($tax_rate / 100);
    if ($tax_amount < 0) $tax_amount = 0;
    $grand_total = ($subtotal - $invoice_discount) + $tax_amount;

    // Deduct Trade-in value
    $net_payable = max(0, $grand_total - $trade_in_value);

    // If paid amount exceeds net payable, adjust paid to net payable (change returned to customer)
    $actual_paid = min($paid_amount, $net_payable);
    $remaining_balance = max(0, $net_payable - $actual_paid);

    // Validate: No credit sale to walk-in customer
    $custStmt = $pdo->prepare("SELECT id, name FROM customers WHERE id = ?");
    $custStmt->execute([$customer_id]);
    $customer_row = $custStmt->fetch();
    $is_walk_in = (!$customer_row || $customer_row['id'] == 1 || stripos($customer_row['name'], 'walk-in') !== false);

    if ($is_walk_in && $remaining_balance > 0.001) {
        throw new Exception("Credit sales and partial payments are not allowed for Walk-in Customer. Full payment of Rs. " . number_format($net_payable, 2) . " is required. Please collect full payment or select a registered customer account.");
    }

    $payment_status = 'paid';
    if ($remaining_balance > 0 && $actual_paid > 0) {
        $payment_status = 'partial';
    } elseif ($remaining_balance > 0 && $actual_paid == 0) {
        $payment_status = 'unpaid';
    }

    // 3. Handle Mobile Trade-In (if applicable)
    $trade_in_device_id = null;
    if ($has_trade_in) {
        $t_info = $input['trade_in'];
        $t_imei1 = sanitize($t_info['imei_1']);
        $t_imei2 = !empty($t_info['imei_2']) ? sanitize($t_info['imei_2']) : null;

        // Check uniqueness of trade-in IMEI
        $checkImei = $pdo->prepare("SELECT id FROM mobile_devices WHERE imei_1 = ? OR (imei_2 IS NOT NULL AND imei_2 = ?)");
        $checkImei->execute([$t_imei1, $t_imei1]);
        if ($checkImei->fetch()) {
            throw new Exception("Trade-in IMEI 1 ($t_imei1) already exists in database.");
        }

        $insDev = $pdo->prepare("INSERT INTO mobile_devices 
            (branch_id, model_name, ram, storage, color, imei_1, imei_2, device_condition, battery_health, pta_status, 
             purchase_price, additional_cost, actual_cost, selling_price, device_status, purchase_date, description)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'used', ?, ?, ?, 0.00, ?, ?, 'available', ?, ?)");
        
        $est_selling = (float)($t_info['estimated_selling_price'] ?? ($trade_in_value * 1.2));
        $t_date = date('Y-m-d');
        $t_desc = "Customer Trade-in on Sale / " . ($t_info['remarks'] ?? '');

        $insDev->execute([
            $branch_id,
            sanitize($t_info['model_name']),
            sanitize($t_info['ram'] ?? '4GB'),
            sanitize($t_info['storage'] ?? '64GB'),
            sanitize($t_info['color'] ?? 'Black'),
            $t_imei1,
            $t_imei2,
            (int)($t_info['battery_health'] ?? 85),
            sanitize($t_info['pta_status'] ?? 'approved'),
            $trade_in_value,
            $trade_in_value,
            $est_selling,
            $t_date,
            $t_desc
        ]);
        $trade_in_device_id = $pdo->lastInsertId();

        // Record inspection for trade-in phone
        $insInsp = $pdo->prepare("INSERT INTO device_inspections 
            (device_id, screen_condition, body_condition, battery_health, camera_condition, biometrics, charging_port, speaker_mic, pta_status, accessories_received, remarks, inspected_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $insInsp->execute([
            $trade_in_device_id,
            sanitize($t_info['screen_condition'] ?? 'Good'),
            sanitize($t_info['body_condition'] ?? 'Good'),
            (int)($t_info['battery_health'] ?? 85),
            sanitize($t_info['camera_condition'] ?? 'Working'),
            sanitize($t_info['biometrics'] ?? 'Working'),
            'Working',
            'Working',
            sanitize($t_info['pta_status'] ?? 'approved'),
            sanitize($t_info['accessories_received'] ?? 'None'),
            sanitize($t_info['remarks'] ?? 'Traded in during POS checkout'),
            $user_id
        ]);
    }

    // 4. Create Sale Record
    $branch_info = getActiveBranchInfo($pdo, $branch_id);
    $prefix = $branch_info['invoice_prefix'] ?? 'INV';
    $invoice_no = generateDocNo($pdo, $prefix, 'sales', 'invoice_no');

    $saleStmt = $pdo->prepare("INSERT INTO sales 
        (invoice_no, branch_id, customer_id, sale_date, subtotal, line_discount, invoice_discount, tax_amount, grand_total, 
         trade_in_device_id, trade_in_value, net_payable, paid_amount, remaining_amount, payment_status, payment_method, notes, created_by)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    
    $sale_date = date('Y-m-d');
    $saleStmt->execute([
        $invoice_no,
        $branch_id,
        $customer_id,
        $sale_date,
        $subtotal,
        0.00,
        $invoice_discount,
        $tax_amount,
        $grand_total,
        $trade_in_device_id,
        $trade_in_value,
        $net_payable,
        $actual_paid,
        $remaining_balance,
        $payment_status,
        $payment_method,
        $notes,
        $user_id
    ]);
    $sale_id = $pdo->lastInsertId();

    // 5. Insert Sale Items, Stock Movements, & Update Inventory
    $itemStmt = $pdo->prepare("INSERT INTO sale_items 
        (sale_id, item_type, product_id, device_id, item_name, imei, quantity, unit_cost, unit_price, discount, total_price, gross_profit)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

    $revenue_mobile = 0.00;
    $revenue_accessory = 0.00;

    foreach ($validated_items as $vItem) {
        $itemStmt->execute([
            $sale_id,
            $vItem['type'],
            $vItem['product_id'],
            $vItem['device_id'],
            $vItem['name'],
            $vItem['imei'],
            $vItem['quantity'],
            $vItem['unit_cost'],
            $vItem['unit_price'],
            $vItem['discount'],
            $vItem['total_price'],
            $vItem['gross_profit']
        ]);

        if ($vItem['type'] === 'mobile') {
            $revenue_mobile += $vItem['total_price'];
            if (!empty($vItem['device_ids'])) {
                foreach ($vItem['device_ids'] as $dId) {
                    recordStockMovement($pdo, $branch_id, null, $dId, 'sale', -1, $vItem['unit_cost'], 'sale', $sale_id, "Sold in {$invoice_no}");
                }
            } else {
                recordStockMovement($pdo, $branch_id, null, $vItem['device_id'], 'sale', -1, $vItem['unit_cost'], 'sale', $sale_id, "Sold in {$invoice_no}");
            }
        } else {
            $revenue_accessory += $vItem['total_price'];
            recordStockMovement($pdo, $branch_id, $vItem['product_id'], null, 'sale', -$vItem['quantity'], $vItem['unit_cost'], 'sale', $sale_id, "Sold in {$invoice_no}");
        }
    }

    // Record trade-in phone stock movement
    if ($trade_in_device_id) {
        recordStockMovement($pdo, $branch_id, null, $trade_in_device_id, 'trade_in', 1, $trade_in_value, 'sale', $sale_id, "Traded in against {$invoice_no}");
    }

    // 6. Record Payment (if any paid amount)
    if ($actual_paid > 0) {
        $payStmt = $pdo->prepare("INSERT INTO sale_payments 
            (sale_id, branch_id, account_id, payment_method, amount, payment_date, notes) 
            VALUES (?, ?, ?, ?, ?, ?, ?)");
        $payStmt->execute([$sale_id, $branch_id, $payment_account_id, $payment_method, $actual_paid, $sale_date, "POS Payment for {$invoice_no}"]);

        // Update financial account balance
        $pdo->prepare("UPDATE financial_accounts SET current_balance = current_balance + ? WHERE id = ?")
            ->execute([$actual_paid, $payment_account_id]);
    }

    // 7. Update Customer Balance if remaining balance or credit customer
    if ($customer_id && $remaining_balance > 0) {
        $pdo->prepare("UPDATE customers SET current_balance = current_balance + ? WHERE id = ?")
            ->execute([$remaining_balance, $customer_id]);
    }

    // 8. DOUBLE-ENTRY ACCOUNTING POSTING
    $journal_lines = [];
    $cash_bank_ledger_acct = getFinancialLedgerAccountId($pdo, $payment_account_id);
    $receivable_acct = getAccountIdByCode($pdo, '1003'); // Customer Receivables
    $inventory_acct = getAccountIdByCode($pdo, '1004'); // Merchandise Inventory
    $rev_mobile_acct = getAccountIdByCode($pdo, '4001'); // Mobile Sales Revenue
    $rev_acc_acct = getAccountIdByCode($pdo, '4002'); // Accessories Sales Revenue
    $tax_acct = getAccountIdByCode($pdo, '2002'); // Sales Tax Payable
    $cogs_mobile_acct = getAccountIdByCode($pdo, '5001'); // COGS Mobile
    $cogs_acc_acct = getAccountIdByCode($pdo, '5002'); // COGS Accessories

    // A. Cash / Bank Received (Debit)
    if ($actual_paid > 0) {
        $journal_lines[] = [
            'account_id' => $cash_bank_ledger_acct,
            'debit' => $actual_paid,
            'credit' => 0.00,
            'description' => "Payment received for {$invoice_no}"
        ];
    }

    // B. Customer Receivable for remaining balance (Debit)
    if ($remaining_balance > 0) {
        $journal_lines[] = [
            'account_id' => $receivable_acct,
            'debit' => $remaining_balance,
            'credit' => 0.00,
            'description' => "Credit sale balance on {$invoice_no}"
        ];
    }

    // C. Traded-in phone added to inventory (Debit)
    if ($trade_in_value > 0) {
        $journal_lines[] = [
            'account_id' => $inventory_acct,
            'debit' => $trade_in_value,
            'credit' => 0.00,
            'description' => "Trade-in device inventory on {$invoice_no}"
        ];
    }

    // D. Sales Revenue (Credit)
    // Adjust invoice discount proportionately or allocate
    $net_subtotal = max(0.01, $subtotal - $invoice_discount);
    $mobile_share = $subtotal > 0 ? ($revenue_mobile / $subtotal) : 1;
    $acc_share = $subtotal > 0 ? ($revenue_accessory / $subtotal) : 0;

    $rev_mobile_net = round($net_subtotal * $mobile_share, 2);
    $rev_acc_net = round($net_subtotal - $rev_mobile_net, 2);

    if ($rev_mobile_net > 0) {
        $journal_lines[] = [
            'account_id' => $rev_mobile_acct,
            'debit' => 0.00,
            'credit' => $rev_mobile_net,
            'description' => "Mobile revenue from {$invoice_no}"
        ];
    }
    if ($rev_acc_net > 0) {
        $journal_lines[] = [
            'account_id' => $rev_acc_acct,
            'debit' => 0.00,
            'credit' => $rev_acc_net,
            'description' => "Accessories revenue from {$invoice_no}"
        ];
    }

    // E. Tax Payable (Credit)
    if ($tax_amount > 0) {
        $journal_lines[] = [
            'account_id' => $tax_acct,
            'debit' => 0.00,
            'credit' => $tax_amount,
            'description' => "Sales tax on {$invoice_no}"
        ];
    }

    // F. COGS & Inventory Deductions (Separate balanced set or combined)
    if ($total_cogs_mobile > 0) {
        $journal_lines[] = [
            'account_id' => $cogs_mobile_acct,
            'debit' => $total_cogs_mobile,
            'credit' => 0.00,
            'description' => "COGS for mobiles sold in {$invoice_no}"
        ];
        $journal_lines[] = [
            'account_id' => $inventory_acct,
            'debit' => 0.00,
            'credit' => $total_cogs_mobile,
            'description' => "Inventory reduced for mobiles sold in {$invoice_no}"
        ];
    }

    if ($total_cogs_accessory > 0) {
        $journal_lines[] = [
            'account_id' => $cogs_acc_acct,
            'debit' => $total_cogs_accessory,
            'credit' => 0.00,
            'description' => "COGS for accessories sold in {$invoice_no}"
        ];
        $journal_lines[] = [
            'account_id' => $inventory_acct,
            'debit' => 0.00,
            'credit' => $total_cogs_accessory,
            'description' => "Inventory reduced for accessories in {$invoice_no}"
        ];
    }

    // Post the journal entry
    postJournalEntry($pdo, $branch_id, 'sale', $sale_id, $journal_lines, $sale_date, "Sales Invoice #{$invoice_no}", $user_id);

    logAudit($pdo, 'create', 'sales', $sale_id, "Generated Sale {$invoice_no} total: {$grand_total}");

    $pdo->commit();

    jsonResponse([
        'status' => 'success',
        'message' => 'Sale processed successfully!',
        'sale_id' => $sale_id,
        'invoice_no' => $invoice_no,
        'redirect_url' => BASE_URL . "pos/invoice.php?id={$sale_id}"
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    jsonResponse(['status' => 'error', 'message' => $e->getMessage()], 400);
}
