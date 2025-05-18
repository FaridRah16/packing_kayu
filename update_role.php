<?php
require_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();

try {
    // Mengubah ENUM pada kolom role untuk menambahkan 'keuangan'
    $sql = "ALTER TABLE users 
            MODIFY COLUMN role ENUM('admin', 'customer', 'staff', 'owner', 'kurir', 'keuangan') NOT NULL";
    
    $stmt = $db->prepare($sql);
    
    if ($stmt->execute()) {
        echo "Role 'keuangan' berhasil ditambahkan ke tabel users.";
    } else {
        echo "Gagal menambahkan role 'keuangan'.";
    }
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?> 