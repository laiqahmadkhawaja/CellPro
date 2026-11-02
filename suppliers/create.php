<?php
// suppliers/create.php - Supplier Creation Handler
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validatePostCsrf();

    $name = sanitize($_POST['name'] ?? '');
    $contact_person = sanitize($_POST['contact_person'] ?? '');
    $phone = sanitize($_POST['phone'] ?? '');
    $alt_phone = sanitize($_POST['alt_phone'] ?? '');
    $email = sanitize($_POST['email'] ?? '');
    $address = sanitize($_POST['address'] ?? '');
    $opening_balance = (float)($_POST['opening_balance'] ?? 0);

    if (empty($name) || empty($phone)) {
        flash('danger', 'Supplier name and phone are required.');
        redirect(BASE_URL . 'suppliers/index.php');
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO suppliers (name, contact_person, phone, alt_phone, email, address, opening_balance, current_balance)
                               VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$name, $contact_person, $phone, $alt_phone, $email, $address, $opening_balance, $opening_balance]);
        $sid = $pdo->lastInsertId();

        logAudit($pdo, 'create', 'suppliers', $sid, "Added supplier {$name}");
        flash('success', "Supplier '{$name}' created successfully.");
        redirect(BASE_URL . 'suppliers/index.php');

    } catch (Exception $e) {
        flash('danger', 'Error adding supplier: ' . $e->getMessage());
        redirect(BASE_URL . 'suppliers/index.php');
    }
}
