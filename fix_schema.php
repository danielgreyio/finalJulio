<?php
require_once 'config/database.php';

try {
    $pdo->exec("DROP TABLE IF EXISTS payment_transactions");
    
    $sql = "
    CREATE TABLE payment_transactions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        order_id INT NOT NULL,
        payment_method VARCHAR(50) NOT NULL,
        gateway_reference VARCHAR(255),
        amount DECIMAL(10, 2) NOT NULL,
        platform_fee DECIMAL(10, 2) DEFAULT 0,
        gateway_fee DECIMAL(10, 2) DEFAULT 0,
        net_amount DECIMAL(10, 2) DEFAULT 0,
        status ENUM('pending', 'completed', 'failed', 'refunded') DEFAULT 'pending',
        raw_response TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    
    $pdo->exec($sql);
    echo "Table 'payment_transactions' recreated successfully.";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
