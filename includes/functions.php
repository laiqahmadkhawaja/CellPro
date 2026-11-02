<?php
// includes/functions.php
// Reusable procedural helper functions

require_once __DIR__ . '/../config/app.php';

function sanitize($data) {
    if (is_array($data)) {
        return array_map('sanitize', $data);
    }
    return htmlspecialchars(trim((string)$data), ENT_QUOTES, 'UTF-8');
}

function formatCurrency($amount) {
    $num = (float)$amount;
    return 'Rs. ' . number_format($num, 2);
}

function formatDate($dateStr, $includeTime = false) {
    if (empty($dateStr)) return '-';
    $format = $includeTime ? 'd M Y, h:i A' : 'd M Y';
    return date($format, strtotime($dateStr));
}

function flash($type, $message) {
    if (!isset($_SESSION['flash_messages'])) {
        $_SESSION['flash_messages'] = [];
    }
    $_SESSION['flash_messages'][] = [
        'type' => $type, // 'success', 'danger', 'warning', 'info'
        'message' => $message
    ];
}

function getFlashes() {
    $flashes = $_SESSION['flash_messages'] ?? [];
    unset($_SESSION['flash_messages']);
    return $flashes;
}

function renderFlashAlerts() {
    $flashes = getFlashes();
    if (empty($flashes)) return '';
    $html = '<div class="flash-messages-container mb-3">';
    foreach ($flashes as $f) {
        $icon = 'info-circle';
        if ($f['type'] === 'success') $icon = 'check-circle';
        if ($f['type'] === 'danger') $icon = 'exclamation-circle';
        if ($f['type'] === 'warning') $icon = 'exclamation-triangle';

        $html .= '<div class="alert alert-' . htmlspecialchars($f['type']) . ' alert-dismissible fade show d-flex align-items-center shadow-sm" role="alert">';
        $html .= '<i class="bi bi-' . $icon . ' fs-5 me-2"></i>';
        $html .= '<div>' . htmlspecialchars($f['message']) . '</div>';
        $html .= '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
        $html .= '</div>';
    }
    $html .= '</div>';
    return $html;
}

function redirect($url) {
    if (!headers_sent()) {
        header("Location: " . $url);
        exit;
    }
    echo '<script>window.location.href="' . htmlspecialchars($url) . '";</script>';
    echo '<noscript><meta http-equiv="refresh" content="0;url=' . htmlspecialchars($url) . '"></noscript>';
    exit;
}

function jsonResponse($data, $status = 200) {
    if (!headers_sent()) {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode($data);
    exit;
}

function logAudit($pdo, $action, $entity_type, $entity_id, $details = null) {
    try {
        $user_id = $_SESSION['user_id'] ?? null;
        $branch_id = $_SESSION['active_branch_id'] ?? null;
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $stmt = $pdo->prepare("INSERT INTO audit_logs (user_id, branch_id, action, entity_type, entity_id, details, ip_address) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$user_id, $branch_id, $action, $entity_type, $entity_id, is_array($details) ? json_encode($details) : (string)$details, $ip]);
    } catch (Exception $e) {
        error_log("Audit log failed: " . $e->getMessage());
    }
}

function generateDocNo($pdo, $prefix, $table, $column) {
    $today = date('ymd');
    $pattern = $prefix . '-' . $today . '-%';
    $stmt = $pdo->prepare("SELECT $column FROM $table WHERE $column LIKE ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$pattern]);
    $last = $stmt->fetchColumn();
    if ($last) {
        $seq = (int)substr($last, strrpos($last, '-') + 1) + 1;
    } else {
        $seq = 1;
    }
    return sprintf("%s-%s-%04d", $prefix, $today, $seq);
}

function getBranches($pdo, $activeOnly = true) {
    $sql = "SELECT * FROM branches";
    if ($activeOnly) $sql .= " WHERE is_active = 1";
    $sql .= " ORDER BY name ASC";
    return $pdo->query($sql)->fetchAll();
}

function getActiveBranchInfo($pdo, $branch_id) {
    $stmt = $pdo->prepare("SELECT * FROM branches WHERE id = ?");
    $stmt->execute([$branch_id]);
    return $stmt->fetch();
}

function getFinancialAccounts($pdo, $branch_id = null) {
    $sql = "SELECT fa.*, a.code as account_code, b.name as branch_name 
            FROM financial_accounts fa
            JOIN accounts a ON fa.account_id = a.id
            JOIN branches b ON fa.branch_id = b.id
            WHERE fa.status = 'active'";
    $params = [];
    if ($branch_id) {
        $sql .= " AND fa.branch_id = ?";
        $params[] = $branch_id;
    }
    $sql .= " ORDER BY fa.branch_id, fa.name";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function getStatusBadge($status) {
    $map = [
        'available' => 'success',
        'sold' => 'secondary',
        'reserved' => 'warning',
        'in_repair' => 'info',
        'damaged' => 'danger',
        'returned' => 'dark',
        'transferred' => 'primary',
        'paid' => 'success',
        'partial' => 'warning',
        'unpaid' => 'danger',
        'approved' => 'success',
        'non_approved' => 'danger',
        'cpid' => 'info',
        'geve' => 'secondary',
        'new' => 'primary',
        'used' => 'info',
        'active' => 'success',
        'inactive' => 'secondary',
        'draft' => 'secondary',
        'pending' => 'warning',
        'dispatched' => 'primary',
        'received' => 'success',
        'cancelled' => 'danger'
    ];
    $color = $map[strtolower($status)] ?? 'secondary';
    $label = ucwords(str_replace('_', ' ', $status));
    return '<span class="badge bg-' . $color . '">' . htmlspecialchars($label) . '</span>';
}
