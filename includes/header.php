<?php
// includes/header.php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/functions.php';

requireLogin();

$user = currentUser();
$branches = getBranches($pdo);
$active_branch_id = getActiveBranchId();
$active_branch = getActiveBranchInfo($pdo, $active_branch_id);

// Handle active branch switch for superadmin
if (isset($_GET['switch_branch']) && isSuperAdmin()) {
    $switched = (int)$_GET['switch_branch'];
    setActiveBranchId($switched);
    $current_uri = strtok($_SERVER["REQUEST_URI"], '?');
    redirect($current_uri);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?= htmlspecialchars($page_title ?? 'Dashboard') ?> - <?= APP_NAME ?></title>
    <!-- Local Vendor CSS -->
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/vendor/bootstrap/bootstrap.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/vendor/bootstrap-icons/bootstrap-icons.css">
    <!-- Custom Application CSS -->
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css">
    <!-- Local jQuery -->
    <script src="<?= BASE_URL ?>assets/vendor/jquery/jquery.min.js"></script>
</head>
<body>

<div id="app-wrapper">
    <!-- Sidebar Navigation -->
    <?php include __DIR__ . '/sidebar.php'; ?>

    <!-- Main Content Area -->
    <div id="main-content">
        <!-- Top Sticky Header -->
        <header class="top-navbar">
            <div class="d-flex align-items-center gap-3">
                <button type="button" class="btn btn-sm btn-outline-secondary d-lg-none" id="mobile-sidebar-toggle" aria-label="Toggle Menu">
                    <i class="bi bi-list fs-5"></i>
                </button>
                <h5 class="m-0 d-none d-sm-block fw-semibold text-dark"><?= htmlspecialchars($page_title ?? 'Dashboard') ?></h5>
            </div>

            <div class="d-flex align-items-center gap-3">
                <!-- Branch Selector -->
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-geo-alt-fill text-primary"></i>
                    <?php if (isSuperAdmin()): ?>
                        <select class="form-select form-select-sm" style="max-width: 220px;" onchange="window.location.href='?switch_branch='+this.value">
                            <?php foreach ($branches as $b): ?>
                                <option value="<?= $b['id'] ?>" <?= $b['id'] == $active_branch_id ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($b['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    <?php else: ?>
                        <span class="badge bg-light text-dark border px-2 py-1">
                            <i class="bi bi-building me-1"></i><?= htmlspecialchars($active_branch['name'] ?? 'Branch') ?>
                        </span>
                    <?php endif; ?>
                </div>

                <!-- Fast POS Link -->
                <a href="<?= BASE_URL ?>pos/index.php" class="btn btn-sm btn-primary d-flex align-items-center gap-1 shadow-sm px-3">
                    <i class="bi bi-calculator"></i> <span class="d-none d-md-inline">POS Terminal</span>
                </a>

                <!-- User Dropdown -->
                <div class="dropdown">
                    <button class="btn btn-sm btn-light border dropdown-toggle d-flex align-items-center gap-2" type="button" data-bs-toggle="dropdown">
                        <i class="bi bi-person-circle fs-6 text-primary"></i>
                        <span class="d-none d-md-inline small fw-semibold"><?= htmlspecialchars($user['name']) ?></span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                        <li class="dropdown-header">
                            <div class="fw-bold"><?= htmlspecialchars($user['name']) ?></div>
                            <small class="text-muted"><?= htmlspecialchars($user['role_name']) ?></small>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="<?= BASE_URL ?>settings/index.php"><i class="bi bi-gear me-2"></i>Settings</a></li>
                        <li><a class="dropdown-item text-danger" href="<?= BASE_URL ?>auth/logout.php"><i class="bi bi-box-arrow-right me-2"></i>Sign Out</a></li>
                    </ul>
                </div>
            </div>
        </header>

        <!-- Page Container -->
        <main class="page-container">
            <?= renderFlashAlerts() ?>
