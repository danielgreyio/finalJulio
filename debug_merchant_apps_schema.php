<?php
require_once 'config/database.php';
try {
    $stmt = $pdo->query("DESCRIBE merchant_applications");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "Columns in merchant_applications table:\n";
    print_r($columns);
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
