<?php
// products/barcodes.php - Barcode Label Generator & Printing Sheet
$page_title = 'Barcode Label Generator';
require_once __DIR__ . '/../includes/header.php';

$branch_id = getActiveBranchId();
$products = $pdo->prepare("SELECT id, name, barcode, sku, retail_price FROM products WHERE branch_id = ? AND is_active = 1 ORDER BY name ASC");
$products->execute([$branch_id]);
$all_prods = $products->fetchAll();

$selected_prod_id = (int)($_GET['product_id'] ?? 0);
$custom_code = trim($_GET['code'] ?? '');
$custom_title = trim($_GET['title'] ?? '');
$custom_price = (float)($_GET['price'] ?? 0);
$num_labels = max(1, (int)($_GET['quantity'] ?? 10));

if ($selected_prod_id > 0) {
    foreach ($all_prods as $p) {
        if ($p['id'] == $selected_prod_id) {
            $custom_code = $p['barcode'] ?: $p['sku'];
            $custom_title = $p['name'];
            $custom_price = (float)$p['retail_price'];
            break;
        }
    }
}
?>

<div class="d-flex justify-content-between align-items-center mb-3 no-print">
    <div>
        <h5 class="fw-bold m-0"><i class="bi bi-upc-scan text-primary me-2"></i>Barcode Generator & Printer</h5>
        <small class="text-muted">Generate Code128 barcode stickers for accessories and mobile phones</small>
    </div>
    <?php if (!empty($custom_code)): ?>
        <button onclick="window.print()" class="btn btn-primary btn-sm shadow-sm">
            <i class="bi bi-printer me-1"></i> Print Barcode Labels
        </button>
    <?php endif; ?>
</div>

<!-- Configuration Form (hidden on print) -->
<div class="card mb-4 no-print">
    <div class="card-body p-3">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-12 col-md-4">
                <label class="form-label small text-muted">Select Existing Product</label>
                <select name="product_id" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">-- Choose Accessory / Part --</option>
                    <?php foreach ($all_prods as $p): ?>
                        <option value="<?= $p['id'] ?>" <?= $p['id'] == $selected_prod_id ? 'selected' : '' ?>>
                            <?= htmlspecialchars($p['name']) ?> (<?= htmlspecialchars($p['barcode'] ?: $p['sku']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label small text-muted">Barcode Text / SKU / IMEI</label>
                <input type="text" name="code" class="form-control form-control-sm" placeholder="e.g. 848061058291" value="<?= htmlspecialchars($custom_code) ?>" required>
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label small text-muted">Label Item Title</label>
                <input type="text" name="title" class="form-control form-control-sm" placeholder="Product Name" value="<?= htmlspecialchars($custom_title) ?>">
            </div>
            <div class="col-4 col-md-1">
                <label class="form-label small text-muted">Price</label>
                <input type="number" step="0.01" name="price" class="form-control form-control-sm" placeholder="Price" value="<?= $custom_price > 0 ? $custom_price : '' ?>">
            </div>
            <div class="col-4 col-md-1">
                <label class="form-label small text-muted">Count</label>
                <input type="number" name="quantity" class="form-control form-control-sm" value="<?= $num_labels ?>" min="1" max="100">
            </div>
            <div class="col-4 col-md-12 text-end mt-2">
                <button type="submit" class="btn btn-sm btn-primary px-4">Generate Preview</button>
            </div>
        </form>
    </div>
</div>

<?php if (!empty($custom_code)): ?>
    <style>
        .barcode-sheet {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            justify-content: flex-start;
        }
        .barcode-label {
            width: 180px;
            height: 105px;
            border: 1px dashed #cbd5e1;
            padding: 6px;
            text-align: center;
            background: #fff;
            border-radius: 4px;
            box-shadow: 0 1px 2px rgba(0,0,0,0.05);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        .barcode-bars {
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            letter-spacing: 2px;
            font-family: 'Courier New', monospace;
            background: repeating-linear-gradient(90deg, #000, #000 2px, #fff 2px, #fff 5px, #000 5px, #000 6px, #fff 6px, #fff 9px);
            margin: 2px 0;
            border-radius: 2px;
        }
        @media print {
            .barcode-sheet {
                gap: 5px;
            }
            .barcode-label {
                border: 1px dotted #ccc !important;
                box-shadow: none !important;
                page-break-inside: avoid;
            }
        }
    </style>

    <div class="barcode-sheet">
        <?php for ($i = 0; $i < $num_labels; $i++): ?>
            <div class="barcode-label">
                <div class="fw-bold text-truncate" style="font-size: 10px;"><?= htmlspecialchars(substr($custom_title ?: 'CellPro Product', 0, 24)) ?></div>
                <div class="barcode-bars"></div>
                <div class="font-monospace fw-bold" style="font-size: 9px; letter-spacing: 1px;"><?= htmlspecialchars($custom_code) ?></div>
                <?php if ($custom_price > 0): ?>
                    <div class="fw-bold text-dark" style="font-size: 11px;">Rs. <?= number_format($custom_price) ?></div>
                <?php endif; ?>
            </div>
        <?php endfor; ?>
    </div>
<?php else: ?>
    <div class="card p-5 text-center text-muted">
        <i class="bi bi-upc fs-1 mb-2 opacity-50"></i>
        Select a product above or enter a barcode code to generate printable stickers.
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
