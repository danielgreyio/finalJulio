<?php
require_once 'config/database.php';
$stmt = $pdo->query("DESCRIBE users");
echo "Users Columns:\n";
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo $row['Field'] . " - " . $row['Type'] . "\n";
}
?>
