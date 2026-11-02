<?php
// products/accessories.php - Accessories Inventory Management
$page_title = 'Accessories Inventory';
require_once __DIR__ . '/../includes/header.php';

$branch_id = getActiveBranchId();
$categories = $pdo->query("SELECT * FROM categories WHERE type IN ('accessory','spare_part') AND status = 'active' ORDER BY name")->fetchAll();
$brands = $pdo->query("SELECT * FROM brands WHERE status = 'active' ORDER BY name")->fetchAll();
$units = $pdo->query("SELECT * FROM units ORDER BY name")->fetchAll();

// Handle Form Submission (Add or Edit)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    validatePostCsrf();
    $action = $_POST['action'];
    $name = sanitize($_POST['name'] ?? '');
    $category_id = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;
    $brand_id = !empty($_POST['brand_id']) ? (int)$_POST['brand_id'] : null;
    $unit_id = !empty($_POST['unit_id']) ? (int)$_POST['unit_id'] : 1;
    $sku = sanitize($_POST['sku'] ?? '');
    $barcode = sanitize($_POST['barcode'] ?? '');
    $cost_price = (float)($_POST['cost_price'] ?? 0);
    $retail_price = (float)($_POST['retail_price'] ?? 0);
    $wholesale_price = (float)($_POST['wholesale_price'] ?? 0);
    $min_stock = (int)($_POST['min_stock_level'] ?? 5);
    $prod_type = sanitize($_POST['product_type'] ?? 'accessory');
    $description = sanitize($_POST['description'] ?? '');

    if ($action === 'create') {
        $init_qty = (int)($_POST['initial_quantity'] ?? 0);

        // Generate barcode if left blank
        if (empty($barcode)) {
            $barcode = 'ACC' . date('ymd') . rand(100, 999);
        }

        $stmt = $pdo->prepare("INSERT INTO products 
            (branch_id, category_id, brand_id, name, sku, barcode, unit_id, product_type, cost_price, retail_price, wholesale_price, quantity, min_stock_level, description)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $branch_id, $category_id, $brand_id, $name, $sku, $barcode, $unit_id, $prod_type, $cost_price, $retail_price, $wholesale_price, $init_qty, $min_stock, $description
        ]);
        $prod_id = $pdo->lastInsertId();

        if ($init_qty > 0) {
            recordStockMovement($pdo, $branch_id, $prod_id, null, 'purchase', $init_qty, $cost_price, 'opening', $prod_id, 'Initial Stock Entry');
        }

        logAudit($pdo, 'create', 'products', $prod_id, "Added accessory {$name}");
        flash('success', "Product '{$name}' created successfully.");
        redirect(BASE_URL . 'products/accessories.php');

    } elseif ($action === 'update') {
        $prod_id = (int)$_POST['product_id'];
        $stmt = $pdo->prepare("UPDATE products SET 
            category_id = ?, brand_id = ?, name = ?, sku = ?, barcode = ?, unit_id = ?, product_type = ?, 
            cost_price = ?, retail_price = ?, wholesale_price = ?, min_stock_level = ?, description = ?
            WHERE id = ? AND branch_id = ?");
        $stmt->execute([
            $category_id, $brand_id, $name, $sku, $barcode, $unit_id, $prod_type, $cost_price, $retail_price, $wholesale_price, $min_stock, $description, $prod_id, $branch_id
        ]);

        logAudit($pdo, 'update', 'products', $prod_id, "Updated product {$name}");
        flash('success', "Product '{$name}' updated successfully.");
        redirect(BASE_URL . 'products/accessories.php');
    }
}

// Search & Filter
$search = trim($_GET['search'] ?? '');
$cat_filter = !empty($_GET['category']) ? (int)$_GET['category'] : 0;

$sql = "SELECT p.*, c.name as category_name, b.name as brand_name, u.short_name as unit_name
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        LEFT JOIN brands b ON p.brand_id = b.id
        LEFT JOIN units u ON p.unit_id = u.id
        WHERE p.branch_id = ? AND p.is_active = 1";
$params = [$branch_id];

if (!empty($search)) {
    $sql .= " AND (p.name LIKE ? OR p.sku LIKE ? OR p.barcode LIKE ? OR b.name LIKE ?)";
    $term = "%{$search}%";
    $params = array_merge($params, [$term, $term, $term, $term]);
}
if ($cat_filter > 0) {
    $sql .= " AND p.category_id = ?";
    $params[] = $cat_filter;
}

$sql .= " ORDER BY p.name ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();
?>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h5 class="fw-bold m-0"><i class="bi bi-headphones text-primary me-2"></i>Accessories & Parts Inventory</h5>
        <small class="text-muted">Quantity and barcode-based inventory tracking</small>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= BASE_URL ?>products/barcodes.php" class="btn btn-outline-secondary btn-sm shadow-sm">
            <i class="bi bi-upc-scan me-1"></i> Print Barcode Labels
        </a>
        <a href="<?= BASE_URL ?>products/adjustments.php" class="btn btn-outline-warning btn-sm shadow-sm">
            <i class="bi bi-sliders me-1"></i> Stock Adjustments
        </a>
        <button type="button" class="btn btn-primary btn-sm shadow-sm" data-toggle-panel="panel-add-accessory">
            <i class="bi bi-plus-circle me-1"></i> Add New Product
        </button>
    </div>
</div>

<!-- Filters -->
<div class="card mb-3">
    <div class="card-body p-3">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-12 col-md-5">
                <label class="form-label small text-muted">Search Barcode, SKU or Name</label>
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                    <input type="text" name="search" class="form-control" placeholder="Scan barcode or type name..." value="<?= htmlspecialchars($search) ?>">
                </div>
            </div>
            <div class="col-6 col-md-4">
                <label class="form-label small text-muted">Category</label>
                <select name="category" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="0">All Categories</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['id'] ?>" <?= $cat['id'] == $cat_filter ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cat['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-md-3 d-flex gap-1">
                <button type="submit" class="btn btn-sm btn-primary w-100">Filter</button>
                <a href="<?= BASE_URL ?>products/accessories.php" class="btn btn-sm btn-outline-secondary">Reset</a>
            </div>
        </form>
    </div>
</div>

<!-- Products Table -->
<div class="card">
    <div class="card-body p-0">
        <?php if (empty($products)): ?>
            <div class="text-center py-5 text-muted">
                <i class="bi bi-box fs-1 mb-2 d-block opacity-50"></i>
                No accessories found. Click <strong>Add New Product</strong> to register stock.
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Product Name</th>
                            <th>Category & Brand</th>
                            <th>Barcode / SKU</th>
                            <th>Cost Price</th>
                            <th>Retail Price</th>
                            <th class="text-center">Stock</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($products as $p): ?>
                            <tr>
                                <td>
                                    <div class="fw-bold text-dark"><?= htmlspecialchars($p['name']) ?></div>
                                    <small class="text-muted"><?= ucfirst($p['product_type']) ?></small>
                                </td>
                                <td>
                                    <div><?= htmlspecialchars($p['category_name'] ?? 'Uncategorized') ?></div>
                                    <small class="text-muted"><?= htmlspecialchars($p['brand_name'] ?? 'Generic') ?></small>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark border font-monospace"><?= htmlspecialchars($p['barcode'] ?? $p['sku']) ?></span>
                                </td>
                                <td><?= formatCurrency($p['cost_price']) ?></td>
                                <td class="fw-bold text-primary"><?= formatCurrency($p['retail_price']) ?></td>
                                <td class="text-center">
                                    <?php if ($p['quantity'] <= $p['min_stock_level']): ?>
                                        <span class="badge bg-danger fs-6"><?= $p['quantity'] ?> <?= $p['unit_name'] ?></span>
                                        <div class="text-danger small" style="font-size:0.7rem;">Low Stock</div>
                                    <?php else: ?>
                                        <span class="badge bg-success-subtle text-success border fs-6"><?= $p['quantity'] ?> <?= $p['unit_name'] ?></span>
                                    <?php endif; ?>
                                </td>
                                <td><?= $p['quantity'] > 0 ? '<span class="badge bg-success">In Stock</span>' : '<span class="badge bg-secondary">Out of Stock</span>' ?></td>
                                <td class="text-end">
                                    <button type="button" class="btn btn-sm btn-outline-secondary edit-product-btn" 
                                            data-json='<?= htmlspecialchars(json_encode($p), ENT_QUOTES, 'UTF-8') ?>'
                                            title="Edit Product">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- ============================================== -->
<!-- SIDE-PANEL DRAWER: ADD / EDIT PRODUCT          -->
<!-- (PRD: Zero Modals Rule)                        -->
<!-- ============================================== -->
<div id="panel-add-accessory" class="side-panel">
    <div class="side-panel-header">
        <h6 class="m-0 fw-bold" id="panel-acc-title"><i class="bi bi-plus-circle me-2 text-primary"></i>Add New Accessory / Spare Part</h6>
        <button type="button" class="btn-close" data-close-panel></button>
    </div>
    <div class="side-panel-body">
        <form method="POST" id="form-accessory" action="<?= BASE_URL ?>products/accessories.php">
            <?= csrfField() ?>
            <input type="hidden" name="action" id="acc-action" value="create">
            <input type="hidden" name="product_id" id="acc-product-id" value="">

            <div class="mb-3">
                <label class="form-label small fw-semibold">Product Name *</label>
                <input type="text" name="name" id="acc-name" class="form-control" placeholder="e.g. Anker 20W Fast Charger" required>
            </div>

            <div class="row g-2 mb-3">
                <div class="col-6">
                    <label class="form-label small fw-semibold">Category</label>
                    <select name="category_id" id="acc-category" class="form-select">
                        <option value="">Select Category</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-6">
                    <label class="form-label small fw-semibold">Brand</label>
                    <select name="brand_id" id="acc-brand" class="form-select">
                        <option value="">Select Brand</option>
                        <?php foreach ($brands as $b): ?>
                            <option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="row g-2 mb-3">
                <div class="col-6">
                    <label class="form-label small fw-semibold">Barcode (Scan or Leave blank for auto)</label>
                    <input type="text" name="barcode" id="acc-barcode" class="form-control" placeholder="Scan Barcode...">
                </div>
                <div class="col-6">
                    <label class="form-label small fw-semibold">SKU / Item Code</label>
                    <input type="text" name="sku" id="acc-sku" class="form-control" placeholder="ANK-20W">
                </div>
            </div>

            <div class="row g-2 mb-3">
                <div class="col-6">
                    <label class="form-label small fw-semibold">Type</label>
                    <select name="product_type" id="acc-type" class="form-select">
                        <option value="accessory">Accessory</option>
                        <option value="spare_part">Repair Spare Part</option>
                    </select>
                </div>
                <div class="col-6">
                    <label class="form-label small fw-semibold">Unit</label>
                    <select name="unit_id" id="acc-unit" class="form-select">
                        <?php foreach ($units as $u): ?>
                            <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['name']) ?> (<?= $u['short_name'] ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="row g-2 mb-3">
                <div class="col-6">
                    <label class="form-label small fw-semibold">Cost Price (Rs.) *</label>
                    <input type="number" step="0.01" name="cost_price" id="acc-cost" class="form-control" placeholder="0.00" required>
                </div>
                <div class="col-6">
                    <label class="form-label small fw-semibold">Retail Price (Rs.) *</label>
                    <input type="number" step="0.01" name="retail_price" id="acc-retail" class="form-control" placeholder="0.00" required>
                </div>
            </div>

            <div class="row g-2 mb-3" id="initial-stock-group">
                <div class="col-6">
                    <label class="form-label small fw-semibold">Initial Opening Stock</label>
                    <input type="number" name="initial_quantity" id="acc-qty" class="form-control" value="0" min="0">
                </div>
                <div class="col-6">
                    <label class="form-label small fw-semibold">Min Stock Alert Level</label>
                    <input type="number" name="min_stock_level" id="acc-min-stock" class="form-control" value="5" min="1">
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label small fw-semibold">Description / Notes</label>
                <textarea name="description" id="acc-desc" class="form-control" rows="2" placeholder="Compatibility, features..."></textarea>
            </div>

            <div class="side-panel-footer px-0 pb-0">
                <button type="button" class="btn btn-light" data-close-panel>Cancel</button>
                <button type="submit" class="btn btn-primary" id="btn-save-acc">Save Product</button>
            </div>
        </form>
    </div>
</div>

<script>
$(document).ready(function() {
    $('.edit-product-btn').on('click', function() {
        let p = $(this).data('json');
        $('#panel-acc-title').html('<i class="bi bi-pencil me-2 text-primary"></i>Edit Product: ' + p.name);
        $('#acc-action').val('update');
        $('#acc-product-id').val(p.id);
        $('#acc-name').val(p.name);
        $('#acc-category').val(p.category_id);
        $('#acc-brand').val(p.brand_id);
        $('#acc-barcode').val(p.barcode);
        $('#acc-sku').val(p.sku);
        $('#acc-type').val(p.product_type);
        $('#acc-unit').val(p.unit_id);
        $('#acc-cost').val(p.cost_price);
        $('#acc-retail').val(p.retail_price);
        $('#acc-min-stock').val(p.min_stock_level);
        $('#acc-desc').val(p.description);

        $('#initial-stock-group').hide();
        $('#btn-save-acc').text('Update Product');
        openSidePanel('panel-add-accessory');
    });

    $('[data-toggle-panel="panel-add-accessory"]').on('click', function() {
        $('#panel-acc-title').html('<i class="bi bi-plus-circle me-2 text-primary"></i>Add New Accessory / Spare Part');
        $('#acc-action').val('create');
        $('#acc-product-id').val('');
        $('#form-accessory')[0].reset();
        $('#initial-stock-group').show();
        $('#btn-save-acc').text('Save Product');
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
