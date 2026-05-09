<?php
require_once __DIR__ . '/bootstrap.php';

// Database connection — credentials from .env
$host     = env('DB_HOST',     'localhost');
$dbname   = env('DB_DATABASE', 'ventdepot');
$username = env('DB_USERNAME', 'root');
$password = env('DB_PASSWORD', '');
$port     = env('DB_PORT',     '3306');

try {
    $pdo = new PDO(
        "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4",
        $username,
        $password
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
} catch (PDOException $e) {
    error_log('DB connection failed: ' . $e->getMessage());
    die('Database connection error. Please try again later.');
}

// Engineering helpers (optional — only if file exists)
$engHelpers = __DIR__ . '/../includes/engineering_helpers.php';
if (file_exists($engHelpers)) {
    require_once $engHelpers;
}
?>
