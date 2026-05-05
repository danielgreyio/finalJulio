<?php
require_once 'config/database.php';

$email = 'farida@123.com';
$password = 'Farida123*';
$role = 'admin';
$firstName = 'Admin';
$lastName = 'User';

// Check if user exists
$stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
$stmt->execute([$email]);
if ($stmt->fetch()) {
    echo "User already exists. Updating password and role...\n";
    $stmt = $pdo->prepare("UPDATE users SET password = ?, role = ? WHERE email = ?");
    $stmt->execute([password_hash($password, PASSWORD_DEFAULT), $role, $email]);
    echo "Admin user updated successfully.\n";
} else {
    echo "Creating new admin user...\n";
    $stmt = $pdo->prepare("INSERT INTO users (email, password, first_name, last_name, role, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
    $stmt->execute([$email, password_hash($password, PASSWORD_DEFAULT), $firstName, $lastName, $role]);
    echo "Admin user created successfully.\n";
}
?>
