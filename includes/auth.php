<?php
// includes/auth.php
// Authentication, roles, and branch scope management

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/functions.php';

function isLoggedIn() {
    return !empty($_SESSION['user_id']);
}

function currentUser() {
    if (!isLoggedIn()) return null;
    return [
        'id' => $_SESSION['user_id'],
        'name' => $_SESSION['user_name'],
        'username' => $_SESSION['user_username'],
        'role_id' => $_SESSION['user_role_id'],
        'role_name' => $_SESSION['user_role_name'],
        'branch_id' => $_SESSION['user_branch_id'] ?? null, // Assigned branch
    ];
}

function isSuperAdmin() {
    return isLoggedIn() && (int)$_SESSION['user_role_id'] === 1;
}

function hasRole($roles = []) {
    if (!isLoggedIn()) return false;
    if (isSuperAdmin()) return true;
    if (is_string($roles)) $roles = [$roles];
    return in_array($_SESSION['user_role_name'], $roles);
}

function requireLogin() {
    if (!isLoggedIn()) {
        flash('warning', 'Please sign in to access this page.');
        redirect(BASE_URL . 'auth/login.php');
    }
}

function requireRole($roles = []) {
    requireLogin();
    if (!hasRole($roles)) {
        flash('danger', 'Access denied. You do not have permission to view that resource.');
        redirect(BASE_URL . 'index.php');
    }
}

// Active branch handling
function getActiveBranchId() {
    if (!isLoggedIn()) return 1;
    // If user is locked to a branch, always return their assigned branch
    if (!isSuperAdmin() && !empty($_SESSION['user_branch_id'])) {
        return (int)$_SESSION['user_branch_id'];
    }
    // For superadmin, return chosen active branch or 0 for All
    if (isset($_SESSION['active_branch_id'])) {
        return (int)$_SESSION['active_branch_id'];
    }
    return 1; // Default to main branch
}

function setActiveBranchId($branchId) {
    if (isSuperAdmin()) {
        $_SESSION['active_branch_id'] = (int)$branchId;
    }
}

function validatePostCsrf() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $token = $_POST['csrf_token'] ?? '';
        if (!verifyCsrfToken($token)) {
            flash('danger', 'Security token mismatch. Please try again.');
            redirect($_SERVER['HTTP_REFERER'] ?? (BASE_URL . 'index.php'));
        }
    }
}
