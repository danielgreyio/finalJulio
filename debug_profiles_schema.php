<?php
require_once 'config/database.php';
try {
    $stmt = $pdo->query("DESCRIBE user_profiles");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "Columns in user_profiles table:\n";
    print_r($columns);
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
