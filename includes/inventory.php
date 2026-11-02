<?php
// includes/inventory.php
// Inventory and device tracking manager

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/functions.php';

/**
 * Records an immutable stock movement and updates current stock/device status.
 */
function recordStockMovement($pdo, $branch_id, $product_id, $device_id, $movement_type, $quantity, $unit_cost, $ref_type, $ref_id, $notes = '') {
    $stmt = $pdo->prepare("INSERT INTO stock_movements 
        (branch_id, product_id, device_id, movement_type, quantity, unit_cost, reference_type, reference_id, notes) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$branch_id, $product_id, $device_id, $movement_type, $quantity, $unit_cost, $ref_type, $ref_id, $notes]);

    // If accessory/spare part, update products table quantity
    if ($product_id) {
        $updateStmt = $pdo->prepare("UPDATE products SET quantity = quantity + ? WHERE id = ?");
        $updateStmt->execute([$quantity, $product_id]);
    }

    // If mobile device, update device status
    if ($device_id) {
        $new_status = null;
        if ($movement_type === 'sale') {
            $new_status = 'sold';
        } elseif (in_array($movement_type, ['purchase', 'return_in', 'trade_in'])) {
            $new_status = 'available';
        } elseif ($movement_type === 'transfer_out') {
            $new_status = 'transferred';
        } elseif ($movement_type === 'transfer_in') {
            $new_status = 'available';
        } elseif ($movement_type === 'repair_use') {
            $new_status = 'in_repair';
        }

        if ($new_status) {
            $devStmt = $pdo->prepare("UPDATE mobile_devices SET device_status = ?, branch_id = ? WHERE id = ?");
            $devStmt->execute([$new_status, $branch_id, $device_id]);
        }
    }
}

/**
 * Get device by IMEI 1 or IMEI 2
 */
function findDeviceByImei($pdo, $imei, $branch_id = null) {
    $sql = "SELECT md.*, b.name as brand_name, br.name as branch_name, s.name as supplier_name,
                   di.screen_condition, di.body_condition, di.camera_condition, di.biometrics,
                   di.charging_port, di.speaker_mic, di.accessories_received, di.existing_faults, di.remarks as inspection_remarks
            FROM mobile_devices md
            LEFT JOIN brands b ON md.brand_id = b.id
            LEFT JOIN branches br ON md.branch_id = br.id
            LEFT JOIN suppliers s ON md.supplier_id = s.id
            LEFT JOIN device_inspections di ON md.id = di.device_id
            WHERE (md.imei_1 = ? OR md.imei_2 = ?)";
    $params = [$imei, $imei];
    if ($branch_id) {
        $sql .= " AND md.branch_id = ?";
        $params[] = $branch_id;
    }
    $sql .= " LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetch();
}

/**
 * Get product by Barcode or SKU
 */
function findProductByBarcode($pdo, $barcode, $branch_id = null) {
    $sql = "SELECT p.*, c.name as category_name, b.name as brand_name, u.short_name as unit_name
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.id
            LEFT JOIN brands b ON p.brand_id = b.id
            LEFT JOIN units u ON p.unit_id = u.id
            WHERE (p.barcode = ? OR p.sku = ?)";
    $params = [$barcode, $barcode];
    if ($branch_id) {
        $sql .= " AND p.branch_id = ?";
        $params[] = $branch_id;
    }
    $sql .= " LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetch();
}
