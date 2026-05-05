<?php
require_once 'config/database.php';

try {
    $pdo->exec("ALTER TABLE orders ADD COLUMN subtotal DECIMAL(10,2) DEFAULT 0.00 AFTER merchant_id");
    echo "Successfully added 'subtotal' column to orders table.\n";
} catch (PDOException $e) {
    if ($e->errorInfo[1] == 1060) {
        echo "Column 'subtotal' already exists.\n";
    } else {
        echo "Error: " . $e->getMessage() . "\n";
    }
}
?>
