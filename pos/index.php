<?php
// pos/index.php - Modern Mobile-First POS Terminal
$page_title = 'Point of Sale (POS)';
require_once __DIR__ . '/../includes/header.php';

$branch_id = getActiveBranchId();
$customers = $pdo->query("SELECT id, name, phone, current_balance FROM customers ORDER BY name ASC")->fetchAll();
$financial_accounts = getFinancialAccounts($pdo, $branch_id);

$tax_enabled = (int)getSetting('tax_enabled', 0);
$tax_rate = (float)getSetting('tax_rate', 0.00);
$tax_name = getSetting('tax_name', 'Sales Tax');
?>

<div class="row g-3">
    <!-- LEFT: Product Explorer & Scanning -->
    <div class="col-12 col-lg-7 col-xl-8">
        <div class="card h-100 mb-0 shadow-sm">
            <div class="card-body p-3">
                <!-- Search & Filter Bar -->
                <div class="row g-2 mb-3">
                    <div class="col-12 col-md-8">
                        <div class="input-group">
                            <span class="input-group-text bg-white border-end-0"><i class="bi bi-upc-scan text-primary fs-5"></i></span>
                            <input type="text" id="pos-search-input" class="form-control border-start-0" placeholder="Scan Barcode / IMEI or search model..." autofocus>
                            <button class="btn btn-outline-secondary" type="button" id="pos-search-clear"><i class="bi bi-x-lg"></i></button>
                        </div>
                    </div>
                    <div class="col-12 col-md-4">
                        <div class="btn-group w-100" role="group">
                            <input type="radio" class="btn-check" name="prod-filter" id="filter-all" value="all" checked autocomplete="off">
                            <label class="btn btn-outline-primary btn-sm" for="filter-all">All</label>

                            <input type="radio" class="btn-check" name="prod-filter" id="filter-mobiles" value="mobile" autocomplete="off">
                            <label class="btn btn-outline-primary btn-sm" for="filter-mobiles">Mobiles</label>

                            <input type="radio" class="btn-check" name="prod-filter" id="filter-acc" value="accessory" autocomplete="off">
                            <label class="btn btn-outline-primary btn-sm" for="filter-acc">Accessories</label>
                        </div>
                    </div>
                </div>

                <!-- Products Grid Container -->
                <div id="pos-products-container" class="overflow-auto pe-1" style="max-height: calc(100vh - 240px); min-height: 400px;">
                    <div class="text-center py-5 text-muted">
                        <div class="spinner-border spinner-border-sm text-primary mb-2"></div>
                        <div>Loading catalogue...</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- RIGHT: Cart & Checkout Summary -->
    <div class="col-12 col-lg-5 col-xl-4">
        <div class="card h-100 mb-0 shadow-sm d-flex flex-column">
            <!-- Customer Bar -->
            <div class="card-header bg-light py-2 px-3">
                <div class="d-flex align-items-center justify-content-between gap-2 w-100">
                    <div class="flex-grow-1">
                        <select id="pos-customer" class="form-select form-select-sm">
                            <?php foreach ($customers as $c): ?>
                                <option value="<?= $c['id'] ?>" data-balance="<?= $c['current_balance'] ?>">
                                    <?= htmlspecialchars($c['name']) ?> (<?= $c['phone'] ?>)
                                    <?= $c['current_balance'] > 0 ? ' [Due: Rs. ' . number_format($c['current_balance']) . ']' : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-primary text-nowrap" data-toggle-panel="panel-new-customer" title="Quick Add Customer">
                        <i class="bi bi-person-plus-fill"></i>
                    </button>
                </div>
            </div>

            <!-- Cart Table Items -->
            <div class="card-body p-0 flex-grow-1 overflow-auto" style="max-height: 280px; min-height: 180px;">
                <table class="table table-sm pos-cart-table align-middle mb-0">
                    <thead class="table-light sticky-top">
                        <tr>
                            <th>Item Details</th>
                            <th class="text-center" style="width: 75px;">Qty</th>
                            <th class="text-end" style="width: 85px;">Price</th>
                            <th style="width: 35px;"></th>
                        </tr>
                    </thead>
                    <tbody id="pos-cart-tbody">
                        <tr>
                            <td colspan="4" class="text-center text-muted py-5">
                                <i class="bi bi-cart-x fs-1 d-block mb-1 opacity-50"></i>
                                Cart is empty. Scan an IMEI or click a product to add.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Cart Calculation Summary -->
            <div class="card-footer bg-light p-3 border-top">
                <!-- Trade-in pill indicator if added -->
                <div id="trade-in-active-badge" class="alert alert-info py-1 px-2 small d-none justify-content-between align-items-center mb-2">
                    <div>
                        <i class="bi bi-arrow-repeat me-1"></i>
                        <span id="trade-in-summary-text">Trade-In Device</span>
                    </div>
                    <button type="button" class="btn btn-sm text-danger p-0" id="btn-remove-tradein"><i class="bi bi-x-circle-fill"></i></button>
                </div>

                <div class="d-flex justify-content-between small mb-1">
                    <span class="text-muted">Subtotal:</span>
                    <span class="fw-semibold" id="summary-subtotal">Rs. 0.00</span>
                </div>

                <div class="row g-2 mb-1">
                    <div class="col-6">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text py-0 text-muted" style="font-size:0.75rem;">Disc.</span>
                            <input type="number" id="pos-invoice-discount" class="form-control form-control-sm text-end" value="0" min="0">
                        </div>
                    </div>
                    <div class="col-6">
                        <button type="button" class="btn btn-sm btn-outline-info w-100 py-0" data-toggle-panel="panel-trade-in" style="height: 31px; font-size: 0.75rem;">
                            <i class="bi bi-arrow-repeat"></i> Trade-In
                        </button>
                    </div>
                </div>

                <?php if ($tax_enabled): ?>
                    <div class="d-flex justify-content-between small mb-1">
                        <span class="text-muted"><?= htmlspecialchars($tax_name) ?> (<?= $tax_rate ?>%):</span>
                        <span class="fw-semibold" id="summary-tax">Rs. 0.00</span>
                    </div>
                <?php endif; ?>

                <div class="d-flex justify-content-between small mb-2 text-danger d-none" id="summary-tradein-row">
                    <span>Trade-In Allowance:</span>
                    <span class="fw-semibold" id="summary-tradein-val">-Rs. 0.00</span>
                </div>

                <div class="d-flex justify-content-between align-items-center border-top pt-2 mb-3">
                    <span class="h6 mb-0 fw-bold">Net Payable:</span>
                    <span class="h4 mb-0 fw-bold text-primary" id="summary-grand-total">Rs. 0.00</span>
                </div>

                <!-- Payment Selection -->
                <div class="row g-2 mb-2">
                    <div class="col-6">
                        <label class="form-label small text-muted mb-1">Payment Account</label>
                        <select id="pos-payment-account" class="form-select form-select-sm">
                            <?php foreach ($financial_accounts as $fa): ?>
                                <option value="<?= $fa['id'] ?>" <?= $fa['is_default'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($fa['name']) ?> (<?= ucfirst($fa['type']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-6">
                        <label class="form-label small text-muted mb-1">Amount Paid</label>
                        <input type="number" id="pos-paid-amount" class="form-control form-control-sm text-end fw-bold" placeholder="0.00">
                    </div>
                </div>

                <!-- Quick Cash Presets -->
                <div class="d-flex gap-1 mb-3">
                    <button type="button" class="btn btn-outline-secondary btn-sm flex-fill py-0 text-nowrap quick-cash" data-val="exact">Exact</button>
                    <button type="button" class="btn btn-outline-secondary btn-sm flex-fill py-0 quick-cash" data-val="5000">5k</button>
                    <button type="button" class="btn btn-outline-secondary btn-sm flex-fill py-0 quick-cash" data-val="10000">10k</button>
                    <button type="button" class="btn btn-outline-secondary btn-sm flex-fill py-0 quick-cash" data-val="50000">50k</button>
                </div>

                <div class="d-flex justify-content-between small mb-3">
                    <span class="text-muted" id="balance-label">Remaining Balance:</span>
                    <span class="fw-bold" id="summary-change-due">Rs. 0.00</span>
                </div>

                <button type="button" id="pos-btn-checkout" class="btn btn-success btn-lg w-100 fw-bold shadow-sm py-2">
                    <i class="bi bi-check-circle-fill me-1"></i> Complete Sale & Print
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ========================================== -->
<!-- SIDE-PANEL DRAWER: QUICK ADD CUSTOMER     -->
<!-- (PRD: Zero Modals Rule)                   -->
<!-- ========================================== -->
<div id="panel-new-customer" class="side-panel">
    <div class="side-panel-header">
        <h6 class="m-0 fw-bold"><i class="bi bi-person-plus me-2 text-primary"></i>Quick Add Customer</h6>
        <button type="button" class="btn-close" data-close-panel></button>
    </div>
    <div class="side-panel-body">
        <form id="form-quick-customer">
            <div class="mb-3">
                <label class="form-label small fw-semibold">Customer Full Name *</label>
                <input type="text" id="qc-name" class="form-control" placeholder="e.g. Farhan Ali" required>
            </div>
            <div class="mb-3">
                <label class="form-label small fw-semibold">Mobile Number *</label>
                <input type="tel" id="qc-phone" class="form-control" placeholder="03001234567" required>
            </div>
            <div class="mb-3">
                <label class="form-label small fw-semibold">CNIC / ID (Optional)</label>
                <input type="text" id="qc-cnic" class="form-control" placeholder="35201-xxxxxxx-x">
            </div>
            <div class="mb-3">
                <label class="form-label small fw-semibold">Address / City</label>
                <input type="text" id="qc-address" class="form-control" placeholder="Gulberg, Lahore">
            </div>
            <div class="mb-3">
                <label class="form-label small fw-semibold">Credit Limit (Rs.)</label>
                <input type="number" id="qc-credit" class="form-control" value="50000">
            </div>
        </form>
    </div>
    <div class="side-panel-footer">
        <button type="button" class="btn btn-light" data-close-panel>Cancel</button>
        <button type="button" class="btn btn-primary" id="btn-save-quick-customer">Save & Select</button>
    </div>
</div>

<!-- ========================================== -->
<!-- SIDE-PANEL DRAWER: MOBILE TRADE-IN / EXCHANGE -->
<!-- (PRD Section 9: Mobile Exchange)          -->
<!-- ========================================== -->
<div id="panel-trade-in" class="side-panel">
    <div class="side-panel-header">
        <h6 class="m-0 fw-bold"><i class="bi bi-arrow-repeat me-2 text-info"></i>Mobile Trade-In / Exchange</h6>
        <button type="button" class="btn-close" data-close-panel></button>
    </div>
    <div class="side-panel-body">
        <p class="text-muted small">Record customer's old phone details, inspection checklist, and agreed trade-in value to deduct from this sale.</p>
        <form id="form-trade-in">
            <div class="mb-3">
                <label class="form-label small fw-semibold">Phone Model & Variant *</label>
                <input type="text" id="ti-model" class="form-control" placeholder="e.g. iPhone 12 Pro 128GB" required>
            </div>
            <div class="row g-2 mb-3">
                <div class="col-6">
                    <label class="form-label small fw-semibold">IMEI 1 *</label>
                    <input type="text" id="ti-imei1" class="form-control" placeholder="15 digits" required maxlength="18">
                </div>
                <div class="col-6">
                    <label class="form-label small fw-semibold">IMEI 2 (Optional)</label>
                    <input type="text" id="ti-imei2" class="form-control" placeholder="15 digits" maxlength="18">
                </div>
            </div>
            <div class="row g-2 mb-3">
                <div class="col-6">
                    <label class="form-label small fw-semibold">RAM / Storage</label>
                    <input type="text" id="ti-storage" class="form-control" placeholder="6GB / 128GB">
                </div>
                <div class="col-6">
                    <label class="form-label small fw-semibold">Battery Health %</label>
                    <input type="number" id="ti-battery" class="form-control" placeholder="85" min="1" max="100">
                </div>
            </div>
            <div class="row g-2 mb-3">
                <div class="col-6">
                    <label class="form-label small fw-semibold">PTA Status</label>
                    <select id="ti-pta" class="form-select">
                        <option value="approved">PTA Approved</option>
                        <option value="non_approved">Non-Approved</option>
                        <option value="cpid">CPID Approved</option>
                    </select>
                </div>
                <div class="col-6">
                    <label class="form-label small fw-semibold">Color</label>
                    <input type="text" id="ti-color" class="form-control" placeholder="Pacific Blue">
                </div>
            </div>

            <h6 class="border-top pt-3 small fw-bold text-muted">Device Inspection Checklist</h6>
            <div class="mb-2">
                <label class="form-label small">Screen Condition</label>
                <input type="text" id="ti-screen" class="form-control form-control-sm" placeholder="No scratches, clean display">
            </div>
            <div class="mb-2">
                <label class="form-label small">Body Condition</label>
                <input type="text" id="ti-body" class="form-control form-control-sm" placeholder="9/10 minor corner scuff">
            </div>
            <div class="mb-3">
                <label class="form-label small">Accessories Handed Over</label>
                <input type="text" id="ti-accessories" class="form-control form-control-sm" placeholder="Box, original charger">
            </div>

            <div class="border-top pt-3">
                <label class="form-label small fw-bold text-dark">Agreed Trade-in Exchange Value (Rs.) *</label>
                <input type="number" id="ti-value" class="form-control form-control-lg fw-bold text-success" placeholder="e.g. 50000" required min="1">
            </div>
        </form>
    </div>
    <div class="side-panel-footer">
        <button type="button" class="btn btn-light" data-close-panel>Cancel</button>
        <button type="button" class="btn btn-info text-white" id="btn-apply-tradein">Apply Exchange Allowance</button>
    </div>
</div>

<script>
// POS Terminal Client Logic
let cart = [];
let tradeInItem = null;
const taxEnabled = <?= $tax_enabled ? 'true' : 'false' ?>;
const taxRate = <?= $tax_rate ?>;

function renderCatalogue(items) {
    let $container = $('#pos-products-container');
    $container.empty();

    let count = 0;
    let html = '<div class="row g-2">';

    // Render Mobiles
    if (items.mobiles && items.mobiles.length) {
        items.mobiles.forEach(m => {
            count++;
            let conditionBadge = m.device_condition === 'new' 
                ? '<span class="badge bg-primary">NEW</span>' 
                : '<span class="badge bg-info">USED (' + m.battery_health + '%)</span>';
            let ptaBadge = m.pta_status === 'approved' 
                ? '<span class="badge bg-success-subtle text-success border">PTA Approved</span>' 
                : '<span class="badge bg-danger-subtle text-danger border">Non-PTA</span>';

            html += `
            <div class="col-6 col-sm-6 col-md-4 col-xl-3">
                <div class="pos-product-card is-device" onclick='addToCartMobile(${JSON.stringify(m).replace(/'/g, "&apos;")})'>
                    <div>
                        <div class="d-flex justify-content-between align-items-start mb-1">
                            ${conditionBadge}
                            ${ptaBadge}
                        </div>
                        <h6 class="fw-bold mb-1 text-dark" style="font-size:0.85rem;">${m.model_name}</h6>
                        <div class="small text-muted mb-1">${m.ram || ''} ${m.storage || ''} &bull; ${m.color || ''}</div>
                        <div class="font-monospace text-muted" style="font-size:0.7rem;">IMEI: ${m.imei_1}</div>
                    </div>
                    <div class="mt-2 pt-2 border-top d-flex justify-content-between align-items-center">
                        <span class="fw-bold text-primary" style="font-size:0.9rem;">Rs. ${Number(m.selling_price).toLocaleString()}</span>
                        <button class="btn btn-sm btn-primary py-0 px-2"><i class="bi bi-plus-lg"></i></button>
                    </div>
                </div>
            </div>`;
        });
    }

    // Render Accessories
    if (items.accessories && items.accessories.length) {
        items.accessories.forEach(a => {
            count++;
            let stockBadge = a.quantity > a.min_stock_level
                ? `<span class="badge bg-light text-dark border">Stock: ${a.quantity}</span>`
                : `<span class="badge bg-danger">Low: ${a.quantity}</span>`;

            html += `
            <div class="col-6 col-sm-6 col-md-4 col-xl-3">
                <div class="pos-product-card" onclick='addToCartAccessory(${JSON.stringify(a).replace(/'/g, "&apos;")})'>
                    <div>
                        <div class="d-flex justify-content-between align-items-start mb-1">
                            <span class="badge bg-secondary">${a.brand_name || 'Item'}</span>
                            ${stockBadge}
                        </div>
                        <h6 class="fw-bold mb-1 text-dark" style="font-size:0.85rem;">${a.name}</h6>
                        <div class="font-monospace text-muted" style="font-size:0.7rem;">${a.barcode || a.sku || ''}</div>
                    </div>
                    <div class="mt-2 pt-2 border-top d-flex justify-content-between align-items-center">
                        <span class="fw-bold text-dark" style="font-size:0.9rem;">Rs. ${Number(a.retail_price).toLocaleString()}</span>
                        <button class="btn btn-sm btn-outline-primary py-0 px-2"><i class="bi bi-plus-lg"></i></button>
                    </div>
                </div>
            </div>`;
        });
    }

    html += '</div>';

    if (count === 0) {
        $container.html('<div class="text-center py-5 text-muted"><i class="bi bi-search fs-2 mb-2 d-block"></i>No available items match your search.</div>');
    } else {
        $container.html(html);
    }
}

function fetchProducts(query = '', type = 'all', autoAdd = false) {
    $.getJSON('<?= BASE_URL ?>api/search_products.php', { q: query, type: type }, function(res) {
        if (res.status === 'success') {
            renderCatalogue(res.data);

            // Auto-add on single exact barcode/IMEI scan
            if (autoAdd) {
                if (res.data.mobiles && res.data.mobiles.length === 1 && (!res.data.accessories || !res.data.accessories.length)) {
                    addToCartMobile(res.data.mobiles[0]);
                    $('#pos-search-input').val('').focus();
                } else if (res.data.accessories && res.data.accessories.length === 1 && (!res.data.mobiles || !res.data.mobiles.length)) {
                    addToCartAccessory(res.data.accessories[0]);
                    $('#pos-search-input').val('').focus();
                }
            }
        }
    });
}

function addToCartMobile(device) {
    // Check if device already in cart
    let exists = cart.find(i => i.type === 'mobile' && i.device_id === device.id);
    if (exists) {
        alert('This mobile phone is already added to cart.');
        return;
    }
    cart.push({
        type: 'mobile',
        device_id: device.id,
        name: device.model_name,
        imei: device.imei_1,
        price: parseFloat(device.selling_price),
        quantity: 1,
        discount: 0,
        maxQty: 1
    });
    renderCart();
}

function addToCartAccessory(prod) {
    let exists = cart.find(i => i.type === 'accessory' && i.product_id === prod.id);
    if (exists) {
        if (exists.quantity + 1 > prod.quantity) {
            alert('Cannot exceed available stock of ' + prod.quantity);
            return;
        }
        exists.quantity += 1;
    } else {
        cart.push({
            type: 'accessory',
            product_id: prod.id,
            name: prod.name,
            imei: null,
            price: parseFloat(prod.retail_price),
            quantity: 1,
            discount: 0,
            maxQty: parseInt(prod.quantity)
        });
    }
    renderCart();
}

function removeFromCart(index) {
    cart.splice(index, 1);
    renderCart();
}

function updateQty(index, delta) {
    let item = cart[index];
    if (item.type === 'mobile') return;
    let newQty = item.quantity + delta;
    if (newQty <= 0) {
        removeFromCart(index);
    } else if (newQty > item.maxQty) {
        alert('Maximum available stock is ' + item.maxQty);
    } else {
        item.quantity = newQty;
        renderCart();
    }
}

function renderCart() {
    let $tbody = $('#pos-cart-tbody');
    $tbody.empty();

    if (cart.length === 0) {
        $tbody.html(`
            <tr>
                <td colspan="4" class="text-center text-muted py-5">
                    <i class="bi bi-cart-x fs-1 d-block mb-1 opacity-50"></i>
                    Cart is empty. Scan an IMEI or click a product to add.
                </td>
            </tr>
        `);
    } else {
        cart.forEach((item, idx) => {
            let imeiHtml = item.imei ? `<div class="font-monospace text-muted" style="font-size:0.7rem;">IMEI: ${item.imei}</div>` : '';
            let qtyHtml = item.type === 'mobile'
                ? `<span class="badge bg-light text-dark border">1</span>`
                : `<div class="d-flex align-items-center justify-content-center gap-1">
                     <button class="btn btn-xs btn-outline-secondary p-0" style="width:20px;height:20px;" onclick="updateQty(${idx}, -1)">-</button>
                     <span class="small fw-bold px-1">${item.quantity}</span>
                     <button class="btn btn-xs btn-outline-secondary p-0" style="width:20px;height:20px;" onclick="updateQty(${idx}, 1)">+</button>
                   </div>`;

            let lineTotal = (item.price * item.quantity) - item.discount;

            $tbody.append(`
                <tr>
                    <td>
                        <div class="fw-semibold text-dark text-truncate" style="max-width: 140px;">${item.name}</div>
                        ${imeiHtml}
                    </td>
                    <td class="text-center">${qtyHtml}</td>
                    <td class="text-end fw-bold">Rs. ${lineTotal.toLocaleString()}</td>
                    <td class="text-center">
                        <button class="btn btn-sm text-danger p-0" onclick="removeFromCart(${idx})"><i class="bi bi-x-circle"></i></button>
                    </td>
                </tr>
            `);
        });
    }

    calculateTotals();
}

function calculateTotals() {
    let subtotal = 0;
    cart.forEach(item => {
        subtotal += (item.price * item.quantity) - item.discount;
    });

    let discount = parseFloat($('#pos-invoice-discount').val()) || 0;
    let netSubtotal = Math.max(0, subtotal - discount);

    let tax = 0;
    if (taxEnabled && taxRate > 0) {
        tax = netSubtotal * (taxRate / 100);
    }

    let tradeInVal = tradeInItem ? parseFloat(tradeInItem.value) : 0;
    let grandTotal = Math.max(0, netSubtotal + tax - tradeInVal);

    $('#summary-subtotal').text('Rs. ' + subtotal.toLocaleString(undefined, {minimumFractionDigits: 2}));
    if (taxEnabled) {
        $('#summary-tax').text('Rs. ' + tax.toLocaleString(undefined, {minimumFractionDigits: 2}));
    }

    if (tradeInVal > 0) {
        $('#summary-tradein-row').removeClass('d-none');
        $('#summary-tradein-val').text('-Rs. ' + tradeInVal.toLocaleString(undefined, {minimumFractionDigits: 2}));
        $('#trade-in-active-badge').removeClass('d-none').addClass('d-flex');
        $('#trade-in-summary-text').text(tradeInItem.model_name + ' (Rs. ' + tradeInVal.toLocaleString() + ')');
    } else {
        $('#summary-tradein-row').addClass('d-none');
        $('#trade-in-active-badge').addClass('d-none').removeClass('d-flex');
    }

    $('#summary-grand-total').text('Rs. ' + grandTotal.toLocaleString(undefined, {minimumFractionDigits: 2}));

    // Update remaining balance or change
    let paid = parseFloat($('#pos-paid-amount').val()) || 0;
    let diff = paid - grandTotal;

    if (diff >= 0) {
        $('#balance-label').text('Change Due:');
        $('#summary-change-due').text('Rs. ' + diff.toLocaleString(undefined, {minimumFractionDigits: 2})).removeClass('text-danger').addClass('text-success');
    } else {
        $('#balance-label').text('Customer Remaining Due:');
        $('#summary-change-due').text('Rs. ' + Math.abs(diff).toLocaleString(undefined, {minimumFractionDigits: 2})).removeClass('text-success').addClass('text-danger');
    }
}

// Quick Cash Buttons
$(document).on('click', '.quick-cash', function() {
    let val = $(this).data('val');
    let subtotal = 0;
    cart.forEach(item => { subtotal += (item.price * item.quantity) - item.discount; });
    let discount = parseFloat($('#pos-invoice-discount').val()) || 0;
    let tradeInVal = tradeInItem ? parseFloat(tradeInItem.value) : 0;
    let grandTotal = Math.max(0, subtotal - discount - tradeInVal);

    if (val === 'exact') {
        $('#pos-paid-amount').val(grandTotal);
    } else {
        $('#pos-paid-amount').val(val);
    }
    calculateTotals();
});

// Realtime listeners
$('#pos-invoice-discount, #pos-paid-amount').on('input', calculateTotals);

// Search input
let searchTimeout;
$('#pos-search-input').on('keyup', function(e) {
    if (e.keyCode === 13) {
        // Enter pressed (e.g. barcode scanner finished scan)
        fetchProducts($(this).val(), $('input[name="prod-filter"]:checked').val(), true);
    } else {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(function() {
            fetchProducts($('#pos-search-input').val(), $('input[name="prod-filter"]:checked').val());
        }, 300);
    }
});

$('input[name="prod-filter"]').on('change', function() {
    fetchProducts($('#pos-search-input').val(), $(this).val());
});

$('#pos-search-clear').on('click', function() {
    $('#pos-search-input').val('');
    fetchProducts('', $('input[name="prod-filter"]:checked').val());
});

// Trade-In handlers
$('#btn-apply-tradein').on('click', function() {
    let model = $('#ti-model').val().trim();
    let imei1 = $('#ti-imei1').val().trim();
    let val = parseFloat($('#ti-value').val());

    if (!model || !imei1 || !val || val <= 0) {
        alert('Please fill out Model, IMEI 1, and Agreed Trade-in Value.');
        return;
    }

    tradeInItem = {
        model_name: model,
        imei_1: imei1,
        imei_2: $('#ti-imei2').val().trim(),
        storage: $('#ti-storage').val().trim(),
        battery_health: $('#ti-battery').val(),
        pta_status: $('#ti-pta').val(),
        color: $('#ti-color').val().trim(),
        screen_condition: $('#ti-screen').val().trim(),
        body_condition: $('#ti-body').val().trim(),
        accessories_received: $('#ti-accessories').val().trim(),
        value: val
    };

    closeSidePanel();
    calculateTotals();
});

$('#btn-remove-tradein').on('click', function() {
    tradeInItem = null;
    calculateTotals();
});

// Quick Add Customer Handler
$('#btn-save-quick-customer').on('click', function() {
    let name = $('#qc-name').val().trim();
    let phone = $('#qc-phone').val().trim();
    if (!name || !phone) {
        alert('Name and Phone are required.');
        return;
    }

    $.post('<?= BASE_URL ?>customers/create.php?ajax=1', {
        name: name,
        phone: phone,
        cnic: $('#qc-cnic').val().trim(),
        address: $('#qc-address').val().trim(),
        credit_limit: $('#qc-credit').val(),
        csrf_token: '<?= generateCsrfToken() ?>'
    }, function(res) {
        if (res.status === 'success') {
            let newOpt = new Option(res.customer.name + ' (' + res.customer.phone + ')', res.customer.id, true, true);
            $('#pos-customer').append(newOpt).trigger('change');
            closeSidePanel();
            $('#form-quick-customer')[0].reset();
        } else {
            alert(res.message || 'Error saving customer.');
        }
    }, 'json').fail(function() {
        alert('Could not save customer.');
    });
});

// Complete POS Checkout
$('#pos-btn-checkout').on('click', function() {
    if (cart.length === 0) {
        alert('Please add at least one product to the cart.');
        return;
    }

    let payload = {
        customer_id: $('#pos-customer').val(),
        items: cart,
        invoice_discount: parseFloat($('#pos-invoice-discount').val()) || 0,
        tax_rate: taxEnabled ? taxRate : 0,
        payment_account_id: $('#pos-payment-account').val(),
        payment_method: $('#pos-payment-account option:selected').text().split('(')[1]?.replace(')', '') || 'Cash',
        paid_amount: parseFloat($('#pos-paid-amount').val()) || 0,
        trade_in: tradeInItem,
        csrf_token: '<?= generateCsrfToken() ?>'
    };

    let $btn = $(this);
    $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span> Processing...');

    $.ajax({
        url: '<?= BASE_URL ?>api/pos_checkout.php',
        method: 'POST',
        contentType: 'application/json',
        data: JSON.stringify(payload),
        dataType: 'json',
        success: function(res) {
            if (res.status === 'success') {
                window.location.href = res.redirect_url;
            } else {
                alert('Error: ' + res.message);
                $btn.prop('disabled', false).html('<i class="bi bi-check-circle-fill me-1"></i> Complete Sale & Print');
            }
        },
        error: function(xhr) {
            let msg = xhr.responseJSON?.message || 'Transaction failed. Please check inputs.';
            alert('Error: ' + msg);
            $btn.prop('disabled', false).html('<i class="bi bi-check-circle-fill me-1"></i> Complete Sale & Print');
        }
    });
});

// Initial catalogue load
$(document).ready(function() {
    fetchProducts();
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
