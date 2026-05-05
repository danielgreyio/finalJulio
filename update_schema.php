<?php
require_once 'config/database.php';

function safeAlter($pdo, $sql) {
    try {
        $pdo->exec($sql);
        echo "Executed: $sql\n";
    } catch (PDOException $e) {
        // Ignore "duplicate column" error (1060)
        if ($e->errorInfo[1] == 1060) {
            echo "Column already exists (skipped): $sql\n";
        } else {
            echo "Error: " . $e->getMessage() . "\n";
        }
    }
}

// Add missing columns to orders
safeAlter($pdo, "ALTER TABLE orders ADD COLUMN merchant_id INT(11) AFTER user_id");
safeAlter($pdo, "ALTER TABLE orders ADD COLUMN tax DECIMAL(10,2) DEFAULT 0.00 AFTER shipping_cost");
safeAlter($pdo, "ALTER TABLE orders ADD COLUMN payment_status VARCHAR(50) DEFAULT 'pending' AFTER status");

// Add missing columns to order_items
safeAlter($pdo, "ALTER TABLE order_items ADD COLUMN total DECIMAL(10,2) AFTER price_at_purchase");

echo "Schema update complete.\n";
?>
