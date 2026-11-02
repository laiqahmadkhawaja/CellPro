<?php
// config/database.php
// Database connection via PDO with robust error handling and prepared statements

$db_host = '127.0.0.1';
$db_port = '3306';
$db_name = 'cellpro_db';
$db_user = 'root';
$db_pass = '';

$dsn = "mysql:host={$db_host};port={$db_port};dbname={$db_name};charset=utf8mb4";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
];

try {
    $pdo = new PDO($dsn, $db_user, $db_pass, $options);
} catch (PDOException $e) {
    error_log("Database connection failed: " . $e->getMessage());
    die("<div style='font-family:sans-serif;padding:30px;background:#fff1f0;border:1px solid #ffa39e;margin:20px;border-radius:8px;'>
        <h3 style='color:#cf1322;margin-top:0;'>Database Connection Error</h3>
        <p>Could not connect to MySQL. Please verify that MySQL service is running in XAMPP and database <code>{$db_name}</code> exists.</p>
    </div>");
}
