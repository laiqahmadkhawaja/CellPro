<?php
// products/barcodes.php - Real Code128 Barcode Label Generator & Printer
$page_title = 'Barcode Label Generator';
require_once __DIR__ . '/../includes/header.php';

$branch_id = getActiveBranchId();

// 1. Fetch Accessories & Products
$productsStmt = $pdo->prepare("SELECT id, name, barcode, sku, retail_price FROM products WHERE branch_id = ? AND is_active = 1 ORDER BY name ASC");
$productsStmt->execute([$branch_id]);
$all_prods = $productsStmt->fetchAll();

// 2. Fetch Available Mobile Phones (for IMEI barcode printing)
$mobilesStmt = $pdo->prepare("SELECT id, model_name as name, imei_1 as barcode, imei_1 as sku, selling_price as retail_price FROM mobile_devices WHERE branch_id = ? AND device_status = 'available' ORDER BY model_name ASC");
$mobilesStmt->execute([$branch_id]);
$all_mobiles = $mobilesStmt->fetchAll();

$selected_prod_id = isset($_GET['product_id']) ? (int)$_GET['product_id'] : 0;
$custom_code = trim($_GET['code'] ?? '');
$custom_title = trim($_GET['title'] ?? '');
$custom_price = isset($_GET['price']) ? (float)$_GET['price'] : 0.00;
$num_labels = max(1, min(100, (int)($_GET['quantity'] ?? 10)));

// If product_id was supplied WITHOUT custom title/code, populate from DB
if ($selected_prod_id > 0 && empty($custom_title) && empty($custom_code)) {
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

<!-- JsBarcode Local Library -->
<script src="<?= BASE_URL ?>assets/vendor/jsbarcode/JsBarcode.all.min.js"></script>

<style>
    .barcode-sheet {
        display: flex;
        flex-wrap: wrap;
        gap: 12px;
        justify-content: flex-start;
    }
    .barcode-label {
        width: 180px;
        height: 112px;
        border: 1px dashed #cbd5e1;
        padding: 6px 8px;
        text-align: center;
        background: #ffffff;
        border-radius: 6px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.04);
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        align-items: center;
        overflow: hidden;
        box-sizing: border-box;
    }
    .barcode-label .label-title {
        font-size: 11px;
        font-weight: 700;
        color: #1e293b;
        line-height: 1.2;
        max-height: 24px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        width: 100%;
    }
    .barcode-label .barcode-img-container {
        width: 100%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 2px 0;
    }
    .barcode-label .barcode-img {
        max-width: 100%;
        height: 48px;
        object-fit: contain;
        display: block;
        image-rendering: -webkit-optimize-contrast;
        image-rendering: pixelated;
    }
    .barcode-label .label-price {
        font-size: 12px;
        font-weight: 800;
        color: #0f172a;
        line-height: 1;
    }

    @media print {
        @page {
            margin: 4mm;
            size: auto;
        }
        * {
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
            color-adjust: exact !important;
        }
        body, html, #app-wrapper, #main-content, .page-container {
            background: #ffffff !important;
            padding: 0 !important;
            margin: 0 !important;
            height: auto !important;
            overflow: visible !important;
        }
        #sidebar, .top-navbar, footer, .no-print, .btn, .card:not(.printable-area) {
            display: none !important;
        }
        .barcode-sheet {
            display: flex !important;
            flex-wrap: wrap !important;
            gap: 4mm !important;
            justify-content: flex-start !important;
            padding: 0 !important;
            margin: 0 !important;
        }
        .barcode-label {
            border: 1px dotted #999999 !important;
            box-shadow: none !important;
            page-break-inside: avoid !important;
            break-inside: avoid !important;
            margin: 0 !important;
            background: #ffffff !important;
            width: 180px !important;
            height: 112px !important;
        }
        .barcode-img {
            max-width: 100% !important;
            height: 48px !important;
            display: block !important;
            visibility: visible !important;
        }
    }
</style>

<div class="d-flex justify-content-between align-items-center mb-3 no-print">
    <div>
        <h5 class="fw-bold m-0"><i class="bi bi-upc-scan text-primary me-2"></i>Barcode Generator & Printer</h5>
        <small class="text-muted">Generate Code128 scannable barcode stickers for accessories and mobile phones</small>
    </div>
    <button type="button" id="btn-print-labels" onclick="window.print()" class="btn btn-primary btn-sm shadow-sm <?= empty($custom_code) ? 'd-none' : '' ?>">
        <i class="bi bi-printer me-1"></i> Print Barcode Labels
    </button>
</div>

<!-- Configuration Form (hidden on print) -->
<div class="card mb-4 no-print shadow-sm">
    <div class="card-body p-3">
        <form id="barcode-config-form" method="GET" class="row g-2 align-items-end" onsubmit="return false;">
            <div class="col-12 col-md-4">
                <label class="form-label small text-muted mb-1">Select Existing Product</label>
                <select id="barcode-product-select" name="product_id" class="form-select form-select-sm">
                    <option value="">-- Choose Accessory or Mobile --</option>
                    <?php if (!empty($all_prods)): ?>
                        <optgroup label="Accessories & Spare Parts">
                            <?php foreach ($all_prods as $p): ?>
                                <option value="<?= $p['id'] ?>" 
                                        data-code="<?= htmlspecialchars($p['barcode'] ?: $p['sku']) ?>"
                                        data-title="<?= htmlspecialchars($p['name']) ?>"
                                        data-price="<?= $p['retail_price'] ?>"
                                        <?= $p['id'] == $selected_prod_id ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($p['name']) ?> (<?= htmlspecialchars($p['barcode'] ?: $p['sku']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </optgroup>
                    <?php endif; ?>
                    <?php if (!empty($all_mobiles)): ?>
                        <optgroup label="Mobile Phones (IMEI)">
                            <?php foreach ($all_mobiles as $m): ?>
                                <option value="m_<?= $m['id'] ?>" 
                                        data-code="<?= htmlspecialchars($m['barcode']) ?>"
                                        data-title="<?= htmlspecialchars($m['name']) ?>"
                                        data-price="<?= $m['retail_price'] ?>">
                                    <?= htmlspecialchars($m['name']) ?> (IMEI: <?= htmlspecialchars($m['barcode']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </optgroup>
                    <?php endif; ?>
                </select>
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label small text-muted mb-1">Barcode Text / SKU / IMEI *</label>
                <input type="text" id="input-code" name="code" class="form-control form-control-sm font-monospace" placeholder="e.g. 848061058291" value="<?= htmlspecialchars($custom_code) ?>" required>
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label small text-muted mb-1">Label Item Title</label>
                <input type="text" id="input-title" name="title" class="form-control form-control-sm" placeholder="Product Name" value="<?= htmlspecialchars($custom_title) ?>">
            </div>
            <div class="col-4 col-md-1">
                <label class="form-label small text-muted mb-1">Price (Rs.)</label>
                <input type="number" step="0.01" id="input-price" name="price" class="form-control form-control-sm text-end" placeholder="Price" value="<?= $custom_price > 0 ? $custom_price : '' ?>">
            </div>
            <div class="col-4 col-md-1">
                <label class="form-label small text-muted mb-1">Count</label>
                <input type="number" id="input-quantity" name="quantity" class="form-control form-control-sm text-center" value="<?= $num_labels ?>" min="1" max="100">
            </div>
            <div class="col-4 col-md-12 text-end mt-2">
                <button type="button" id="btn-generate-preview" class="btn btn-sm btn-primary px-4">Generate Preview</button>
            </div>
        </form>
    </div>
</div>

<!-- Printable Area / Dynamic Live Preview Container -->
<div id="barcode-sheet" class="barcode-sheet">
    <!-- Populated by JavaScript -->
</div>

<script>
function escapeHtml(text) {
    if (!text) return '';
    return String(text)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function renderLiveBarcodes() {
    let code = $('#input-code').val().trim();
    let title = $('#input-title').val().trim();
    let priceVal = $('#input-price').val();
    let price = priceVal !== '' ? parseFloat(priceVal) : 0;
    let qty = parseInt($('#input-quantity').val()) || 1;
    if (qty < 1) qty = 1;
    if (qty > 100) qty = 100;

    let $sheet = $('#barcode-sheet');
    $sheet.empty();

    if (!code) {
        $sheet.html(`
            <div class="card p-5 text-center text-muted w-100 no-print border-0 bg-light rounded-4">
                <i class="bi bi-upc fs-1 mb-2 opacity-50"></i>
                Select a product above or enter a barcode code to generate printable stickers.
            </div>
        `);
        $('#btn-print-labels').addClass('d-none');
        return;
    }

    // Generate crisp PNG barcode using an offscreen canvas.
    // Images are foreground elements that browsers (Chrome, Edge, Firefox) NEVER drop during printing.
    let barcodeDataUrl = '';
    try {
        let canvas = document.createElement('canvas');
        JsBarcode(canvas, code, {
            format: "CODE128",
            width: 2,
            height: 44,
            displayValue: true,
            fontSize: 13,
            font: "monospace",
            textAlign: "center",
            textPosition: "bottom",
            textMargin: 3,
            margin: 2,
            background: "#ffffff",
            lineColor: "#000000"
        });
        barcodeDataUrl = canvas.toDataURL("image/png");
    } catch (err) {
        console.error("Barcode generation error:", err);
        $sheet.html(`<div class="alert alert-danger w-100 no-print">Invalid barcode code for CODE128 format: ${escapeHtml(code)}</div>`);
        $('#btn-print-labels').addClass('d-none');
        return;
    }

    $('#btn-print-labels').removeClass('d-none');

    let displayTitle = title || '';
    let titleHtml = displayTitle ? `<div class="label-title fw-bold text-truncate w-100" title="${escapeHtml(displayTitle)}">${escapeHtml(displayTitle)}</div>` : '<div class="label-title">&nbsp;</div>';
    let priceHtml = price > 0 ? `<div class="label-price fw-bold text-dark">Rs. ${price.toLocaleString(undefined, {minimumFractionDigits: 0, maximumFractionDigits: 2})}</div>` : '<div class="label-price">&nbsp;</div>';

    let sheetHtml = '';
    for (let i = 0; i < qty; i++) {
        sheetHtml += `
            <div class="barcode-label">
                ${titleHtml}
                <div class="barcode-img-container">
                    <img src="${barcodeDataUrl}" class="barcode-img" alt="${escapeHtml(code)}">
                </div>
                ${priceHtml}
            </div>
        `;
    }
    $sheet.html(sheetHtml);
}

$(document).ready(function() {
    // 1. Live change on product select dropdown (supports both native and Select2 events)
    $('#barcode-product-select').on('change select2:select', function() {
        let $opt = $(this).find('option:selected');
        if ($opt.val()) {
            $('#input-code').val($opt.data('code') || '');
            $('#input-title').val($opt.data('title') || '');
            $('#input-price').val($opt.data('price') || '');
            renderLiveBarcodes();
        }
    });

    // 2. Real-time typing/input listeners on all fields
    $('#input-code, #input-title, #input-price, #input-quantity').on('input keyup change paste', function() {
        renderLiveBarcodes();
    });

    // 3. Generate Preview button click
    $('#btn-generate-preview').on('click', function(e) {
        e.preventDefault();
        renderLiveBarcodes();
    });

    // Initial render on load
    renderLiveBarcodes();
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
