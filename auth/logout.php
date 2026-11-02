<?php
// auth/logout.php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';

if (!empty($_SESSION['user_id'])) {
    logAudit($pdo, 'logout', 'users', $_SESSION['user_id'], 'User logged out');
}

session_unset();
session_destroy();

// Start fresh session for flash message
session_start();
flash('info', 'You have been signed out successfully.');
redirect(BASE_URL . 'auth/login.php');
