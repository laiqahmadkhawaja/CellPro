<?php
// config/app.php
// Main application initializer

if (session_status() === PHP_SESSION_NONE) {
    // Start output buffering to prevent headers already sent issues
    ob_start();
    session_start();
}

// Error reporting: Log to PHP error log, don't expose sensitive traces to users in production
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

// Dynamic base URL calculation
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)) ? "https://" : "http://";
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$script_name = $_SERVER['SCRIPT_NAME'] ?? '';
$dir = dirname($script_name);
$dir = str_replace('\\', '/', $dir);

// Normalize base path for CellPro
$parts = explode('/', trim($dir, '/'));
$base_segment = '';
if (!empty($parts[0]) && strtolower($parts[0]) === 'cellpro') {
    $base_segment = '/cellpro';
} elseif (isset($parts[0]) && $parts[0] !== '') {
    $base_segment = '/' . $parts[0];
}
define('BASE_URL', rtrim($protocol . $host . $base_segment, '/') . '/');
define('APP_NAME', 'CellPro ERP');

require_once __DIR__ . '/database.php';

// Helper to retrieve settings
function getSetting($key, $default = null) {
    global $pdo;
    static $settings_cache = null;
    if ($settings_cache === null) {
        $stmt = $pdo->query("SELECT setting_key, setting_value FROM application_settings");
        $settings_cache = [];
        while ($row = $stmt->fetch()) {
            $settings_cache[$row['setting_key']] = $row['setting_value'];
        }
    }
    return $settings_cache[$key] ?? $default;
}

// CSRF Protection
function generateCsrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrfToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], (string)$token);
}

function csrfField() {
    $token = generateCsrfToken();
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token) . '">';
}
