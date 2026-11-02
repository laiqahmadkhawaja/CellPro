<?php
// settings/categories.php - Dynamic Brands & Categories
$page_title = 'Brands & Categories';
require_once __DIR__ . '/../includes/header.php';

requireRole(['Super Administrator', 'Branch Manager']);

// Handle Brand Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_brand'])) {
    validatePostCsrf();
    $b_name = sanitize($_POST['brand_name'] ?? '');
    if (!empty($b_name)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO brands (name) VALUES (?)");
            $stmt->execute([$b_name]);
            flash('success', "Brand '{$b_name}' added successfully.");
        } catch (Exception $e) {
            flash('danger', 'Error adding brand: ' . $e->getMessage());
        }
    }
    redirect(BASE_URL . 'settings/categories.php');
}

// Handle Category Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_category'])) {
    validatePostCsrf();
    $c_name = sanitize($_POST['category_name'] ?? '');
    $c_type = sanitize($_POST['category_type'] ?? 'accessory');
    $c_desc = sanitize($_POST['description'] ?? '');

    if (!empty($c_name)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO categories (name, type, description) VALUES (?, ?, ?)");
            $stmt->execute([$c_name, $c_type, $c_desc]);
            flash('success', "Category '{$c_name}' created successfully.");
        } catch (Exception $e) {
            flash('danger', 'Error adding category: ' . $e->getMessage());
        }
    }
    redirect(BASE_URL . 'settings/categories.php');
}

$brands = $pdo->query("SELECT * FROM brands ORDER BY name ASC")->fetchAll();
$categories = $pdo->query("SELECT * FROM categories ORDER BY type ASC, name ASC")->fetchAll();
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h5 class="fw-bold m-0"><i class="bi bi-tags text-primary me-2"></i>Product Brands & Categories</h5>
        <small class="text-muted">Dynamic configuration of phone manufacturers and product classification</small>
    </div>
</div>

<div class="row g-3">
    <!-- LEFT: Brands List & Add Form -->
    <div class="col-12 col-md-5">
        <div class="card mb-3">
            <div class="card-header bg-light">
                <span class="fw-bold"><i class="bi bi-plus-circle me-1 text-primary"></i> Add Brand</span>
            </div>
            <div class="card-body">
                <form method="POST" action="">
                    <?= csrfField() ?>
                    <input type="hidden" name="action_brand" value="1">
                    <div class="input-group">
                        <input type="text" name="brand_name" class="form-control" placeholder="Brand Name (e.g. Google Pixel)" required>
                        <button type="submit" class="btn btn-primary">Add Brand</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header bg-light">
                <span class="fw-bold"><i class="bi bi-patch-check me-1 text-primary"></i> Configured Brands (<?= count($brands) ?>)</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive" style="max-height: 450px;">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Brand Name</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $i = 1; foreach ($brands as $b): ?>
                                <tr>
                                    <td><?= $i++ ?></td>
                                    <td class="fw-bold text-dark"><?= htmlspecialchars($b['name']) ?></td>
                                    <td><?= getStatusBadge($b['status']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- RIGHT: Categories List & Add Form -->
    <div class="col-12 col-md-7">
        <div class="card mb-3">
            <div class="card-header bg-light">
                <span class="fw-bold"><i class="bi bi-plus-circle me-1 text-primary"></i> Add Category</span>
            </div>
            <div class="card-body">
                <form method="POST" action="">
                    <?= csrfField() ?>
                    <input type="hidden" name="action_category" value="1">
                    <div class="row g-2">
                        <div class="col-7">
                            <input type="text" name="category_name" class="form-control" placeholder="Category Name (e.g. Wireless Chargers)" required>
                        </div>
                        <div class="col-3">
                            <select name="category_type" class="form-select">
                                <option value="accessory">Accessory</option>
                                <option value="mobile">Mobile Phone</option>
                                <option value="spare_part">Spare Part</option>
                            </select>
                        </div>
                        <div class="col-2">
                            <button type="submit" class="btn btn-primary w-100">Add</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header bg-light">
                <span class="fw-bold"><i class="bi bi-grid me-1 text-primary"></i> Product Categories (<?= count($categories) ?>)</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive" style="max-height: 450px;">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Category Name</th>
                                <th>Category Type</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($categories as $cat): ?>
                                <tr>
                                    <td class="fw-bold text-dark"><?= htmlspecialchars($cat['name']) ?></td>
                                    <td>
                                        <span class="badge bg-secondary text-uppercase"><?= str_replace('_', ' ', $cat['type']) ?></span>
                                    </td>
                                    <td><?= getStatusBadge($cat['status']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
