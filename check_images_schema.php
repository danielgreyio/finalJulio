<?php
require_once 'config/database.php';
$stmt = $pdo->query("SHOW TABLES LIKE 'product_images'");
$exists = $stmt->fetch();
echo "product_images table: " . ($exists ? "EXISTS" : "MISSING") . "\n";

$stmt = $pdo->query("DESCRIBE products");
echo "Products Columns:\n";
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo $row['Field'] . "\n";
}
?>
