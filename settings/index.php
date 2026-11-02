<?php
// settings/index.php - General & Tax Settings
$page_title = 'System Settings';
require_once __DIR__ . '/../includes/header.php';

requireRole(['Super Administrator']);

$business = $pdo->query("SELECT * FROM businesses WHERE id = 1")->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validatePostCsrf();

    // 1. Update Businesses table
    $name = sanitize($_POST['name'] ?? '');
    $tagline = sanitize($_POST['tagline'] ?? '');
    $phone = sanitize($_POST['phone'] ?? '');
    $email = sanitize($_POST['email'] ?? '');
    $address = sanitize($_POST['address'] ?? '');
    $currency_symbol = sanitize($_POST['currency_symbol'] ?? 'Rs.');
    $tax_enabled = isset($_POST['tax_enabled']) ? 1 : 0;
    $tax_rate = (float)($_POST['tax_rate'] ?? 0);
    $tax_name = sanitize($_POST['tax_name'] ?? 'Sales Tax');

    $stmt = $pdo->prepare("UPDATE businesses SET name = ?, tagline = ?, phone = ?, email = ?, address = ?, currency_symbol = ?, tax_enabled = ?, tax_rate = ?, tax_name = ? WHERE id = 1");
    $stmt->execute([$name, $tagline, $phone, $email, $address, $currency_symbol, $tax_enabled, $tax_rate, $tax_name]);

    // 2. Update Application Settings table
    $settings_to_update = [
        'business_name' => $name,
        'tax_enabled' => (string)$tax_enabled,
        'tax_name' => $tax_name,
        'tax_rate' => (string)$tax_rate,
        'thermal_printer_width' => sanitize($_POST['thermal_printer_width'] ?? '80mm'),
        'invoice_footer_note' => sanitize($_POST['invoice_footer_note'] ?? '')
    ];

    $setStmt = $pdo->prepare("INSERT INTO application_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
    foreach ($settings_to_update as $k => $v) {
        $setStmt->execute([$k, $v]);
    }

    logAudit($pdo, 'update', 'settings', 1, "Updated system settings");
    flash('success', 'System and Tax settings updated successfully.');
    redirect(BASE_URL . 'settings/index.php');
}

$thermal_width = getSetting('thermal_printer_width', '80mm');
$footer_note = getSetting('invoice_footer_note', 'Thank you for your business!');
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h5 class="fw-bold m-0"><i class="bi bi-gear text-primary me-2"></i>System & Tax Settings</h5>
        <small class="text-muted">Store branding, tax management and receipt configuration</small>
    </div>
</div>

<form method="POST" action="">
    <?= csrfField() ?>

    <div class="row g-3">
        <!-- 1. Store Branding -->
        <div class="col-12 col-lg-6">
            <div class="card h-100">
                <div class="card-header bg-light">
                    <span class="fw-bold"><i class="bi bi-shop me-1 text-primary"></i> Business & Store Identity</span>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Store / Company Name *</label>
                        <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($business['name']) ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Tagline / Motto</label>
                        <input type="text" name="tagline" class="form-control" value="<?= htmlspecialchars($business['tagline'] ?? '') ?>">
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Phone Number</label>
                            <input type="tel" name="phone" class="form-control" value="<?= htmlspecialchars($business['phone'] ?? '') ?>">
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Email Address</label>
                            <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($business['email'] ?? '') ?>">
                        </div>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Currency Symbol</label>
                            <input type="text" name="currency_symbol" class="form-control" value="<?= htmlspecialchars($business['currency_symbol'] ?? 'Rs.') ?>">
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Receipt Printer Type</label>
                            <select name="thermal_printer_width" class="form-select">
                                <option value="80mm" <?= $thermal_width === '80mm' ? 'selected' : '' ?>>Thermal 80mm (Standard)</option>
                                <option value="58mm" <?= $thermal_width === '58mm' ? 'selected' : '' ?>>Thermal 58mm (Small)</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-0">
                        <label class="form-label small fw-semibold">Head Office / Primary Address</label>
                        <textarea name="address" class="form-control form-control-sm" rows="2"><?= htmlspecialchars($business['address'] ?? '') ?></textarea>
                    </div>
                </div>
            </div>
        </div>

        <!-- 2. Tax Settings & Receipt Disclaimer -->
        <div class="col-12 col-lg-6">
            <div class="card h-100">
                <div class="card-header bg-light">
                    <span class="fw-bold"><i class="bi bi-percent me-1 text-danger"></i> Optional Tax Configuration (PRD Section 17)</span>
                </div>
                <div class="card-body">
                    <div class="form-check form-switch mb-3 p-2 bg-light rounded-3 ps-5">
                        <input class="form-check-input" type="checkbox" name="tax_enabled" id="tax_enabled" <?= $business['tax_enabled'] ? 'checked' : '' ?>>
                        <label class="form-check-label fw-bold text-dark small" for="tax_enabled">
                            Enable Sales Tax on POS & Invoices
                        </label>
                        <small class="text-muted d-block" style="font-size:0.75rem;">
                            When enabled, tax is automatically calculated during POS checkout and recorded into Tax Payable accounts. When disabled, tax does not interfere with POS workflows.
                        </small>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Tax Name / Title</label>
                            <input type="text" name="tax_name" class="form-control" value="<?= htmlspecialchars($business['tax_name'] ?? 'Sales Tax') ?>">
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Default Tax Percentage (%)</label>
                            <input type="number" step="0.01" name="tax_rate" class="form-control fw-bold text-danger" value="<?= $business['tax_rate'] ?>">
                        </div>
                    </div>

                    <h6 class="border-top pt-3 small fw-bold text-muted">Receipt Bottom Warranty Disclaimer Note</h6>
                    <div class="mb-0">
                        <textarea name="invoice_footer_note" class="form-control form-control-sm" rows="4"><?= htmlspecialchars($footer_note) ?></textarea>
                        <small class="text-muted" style="font-size:0.7rem;">Printed at the bottom of all customer thermal and A4 sales invoices.</small>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 text-end">
            <button type="submit" class="btn btn-primary btn-lg px-4 shadow-sm">
                <i class="bi bi-check2-circle me-1"></i> Save Configuration
            </button>
        </div>
    </div>
</form>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
