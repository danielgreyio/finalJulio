<?php
require_once 'config/database.php';

$email = 'admin@ventdepot.com';
$password = 'password123';
$role = 'admin';
$firstName = 'Super';
$lastName = 'Admin';

// Check if user exists
$stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
$stmt->execute([$email]);
if ($stmt->fetch()) {
    echo "User $email already exists. Updating password...\n";
    $stmt = $pdo->prepare("UPDATE users SET password = ?, role = ? WHERE email = ?");
    $stmt->execute([password_hash($password, PASSWORD_DEFAULT), $role, $email]);
    echo "User updated: $email / $password\n";
} else {
    echo "Creating new user $email...\n";
    $stmt = $pdo->prepare("INSERT INTO users (email, password, first_name, last_name, role, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
    $stmt->execute([$email, password_hash($password, PASSWORD_DEFAULT), $firstName, $lastName, $role]);
    echo "User created: $email / $password\n";
}
?>
