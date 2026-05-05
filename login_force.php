<?php
session_start();
require_once 'config/database.php';

$email = 'farida@123.com';
$password = 'Farida123*';

// direct login
$stmt = $pdo->prepare("SELECT id, email, password, role FROM users WHERE email = ?");
$stmt->execute([$email]);
$user = $stmt->fetch();

if ($user && password_verify($password, $user['password'])) {
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_role'] = $user['role'];
    echo "<h1>Login Successful!</h1>";
    echo "<p>Welcome " . htmlspecialchars($user['email']) . "</p>";
    echo "<p><a href='index.php'>Go to Homepage</a></p>";
    echo "<p><a href='merchant/dashboard.php'>Go to Merchant Dashboard</a></p>";
    echo "<p><a href='admin/dashboard.php'>Go to Admin Dashboard</a></p>";
} else {
    echo "Login Failed (Password mismatch?)";
}
?>
