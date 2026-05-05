<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Database Configuration
$host = 'localhost';
$dbname = 'ventdepot';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Include engineering helpers
require_once __DIR__ . '/../includes/engineering_helpers.php';

// Include other helper functions
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function getUserRole() {
    return $_SESSION['user_role'] ?? '';
}

function requireRole($role) {
    if (!isLoggedIn() || getUserRole() !== $role) {
        header('Location: ../login.php');
        exit;
    }
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit;
    }
}

function getCartCount() {
    return array_sum($_SESSION['cart'] ?? []);
}
?>
