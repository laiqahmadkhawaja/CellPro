<?php
// customers/create.php - Customer Creation Handler
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validatePostCsrf();

    $name = sanitize($_POST['name'] ?? '');
    $phone = sanitize($_POST['phone'] ?? '');
    $alt_phone = sanitize($_POST['alt_phone'] ?? '');
    $email = sanitize($_POST['email'] ?? '');
    $cnic = sanitize($_POST['cnic'] ?? '');
    $address = sanitize($_POST['address'] ?? '');
    $credit_limit = (float)($_POST['credit_limit'] ?? 50000);
    $opening_balance = (float)($_POST['opening_balance'] ?? 0);

    if (empty($name) || empty($phone)) {
        if (!empty($_GET['ajax'])) {
            jsonResponse(['status' => 'error', 'message' => 'Name and Phone are required.'], 400);
        }
        flash('danger', 'Name and Phone are required.');
        redirect(BASE_URL . 'customers/index.php');
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO customers (name, phone, alt_phone, email, cnic, address, opening_balance, current_balance, credit_limit)
                               VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$name, $phone, $alt_phone, $email, $cnic, $address, $opening_balance, $opening_balance, $credit_limit]);
        $cid = $pdo->lastInsertId();

        logAudit($pdo, 'create', 'customers', $cid, "Added customer {$name} ({$phone})");

        if (!empty($_GET['ajax'])) {
            jsonResponse([
                'status' => 'success',
                'customer' => [
                    'id' => $cid,
                    'name' => $name,
                    'phone' => $phone
                ]
            ]);
        }

        flash('success', "Customer '{$name}' registered successfully.");
        redirect(BASE_URL . 'customers/index.php');

    } catch (Exception $e) {
        if (!empty($_GET['ajax'])) {
            jsonResponse(['status' => 'error', 'message' => $e->getMessage()], 400);
        }
        flash('danger', 'Error adding customer: ' . $e->getMessage());
        redirect(BASE_URL . 'customers/index.php');
    }
}
