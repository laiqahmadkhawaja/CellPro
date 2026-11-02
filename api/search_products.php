<?php
// api/search_products.php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();

$query = trim($_GET['q'] ?? '');
$type = trim($_GET['type'] ?? 'all'); // 'all', 'mobile', 'accessory'
$branch_id = getActiveBranchId();

$results = [
    'mobiles' => [],
    'accessories' => []
];

// 1. Search Available Mobile Phones by IMEI, Serial, Model, Brand
if ($type === 'all' || $type === 'mobile') {
    $sql = "SELECT md.*, b.name as brand_name, br.name as branch_name 
            FROM mobile_devices md
            LEFT JOIN brands b ON md.brand_id = b.id
            LEFT JOIN branches br ON md.branch_id = br.id
            WHERE md.device_status = 'available' AND md.branch_id = ?";
    $params = [$branch_id];

    if (!empty($query)) {
        $sql .= " AND (md.imei_1 LIKE ? OR md.imei_2 LIKE ? OR md.serial_number LIKE ? OR md.model_name LIKE ? OR b.name LIKE ?)";
        $searchTerm = "%{$query}%";
        $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm]);
    }
    $sql .= " ORDER BY md.id DESC LIMIT 30";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $results['mobiles'] = $stmt->fetchAll();
}

// 2. Search Accessories & Spare Parts by Barcode, SKU, Name
if ($type === 'all' || $type === 'accessory') {
    $sql = "SELECT p.*, c.name as category_name, b.name as brand_name, u.short_name as unit_name
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.id
            LEFT JOIN brands b ON p.brand_id = b.id
            LEFT JOIN units u ON p.unit_id = u.id
            WHERE p.is_active = 1 AND p.branch_id = ?";
    $params = [$branch_id];

    if (!empty($query)) {
        $sql .= " AND (p.barcode LIKE ? OR p.sku LIKE ? OR p.name LIKE ? OR b.name LIKE ?)";
        $searchTerm = "%{$query}%";
        $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
    }
    $sql .= " ORDER BY p.name ASC LIMIT 30";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $results['accessories'] = $stmt->fetchAll();
}

jsonResponse(['status' => 'success', 'data' => $results]);
