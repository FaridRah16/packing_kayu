<?php
// Script untuk mengupdate struktur tabel estimasi
// Menghapus kolom lokasi_tujuan dan menambahkan kolom harga_dimensi_id

require_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();

try {
    // Menambahkan kolom harga_dimensi_id jika belum ada
    $checkColumn = "SHOW COLUMNS FROM `estimasi` LIKE 'harga_dimensi_id'";
    $stmt = $db->query($checkColumn);
    if ($stmt->rowCount() == 0) {
        $addColumn = "ALTER TABLE `estimasi` ADD COLUMN `harga_dimensi_id` INT AFTER `jenis_kayu_id`";
        $db->exec($addColumn);
        echo "Kolom harga_dimensi_id berhasil ditambahkan.<br>";
    } else {
        echo "Kolom harga_dimensi_id sudah ada.<br>";
    }
    
    // Menghapus kolom lokasi_tujuan jika ada
    $checkColumn = "SHOW COLUMNS FROM `estimasi` LIKE 'lokasi_tujuan'";
    $stmt = $db->query($checkColumn);
    if ($stmt->rowCount() > 0) {
        $dropColumn = "ALTER TABLE `estimasi` DROP COLUMN `lokasi_tujuan`";
        $db->exec($dropColumn);
        echo "Kolom lokasi_tujuan berhasil dihapus.<br>";
    } else {
        echo "Kolom lokasi_tujuan tidak ditemukan.<br>";
    }
    
    echo "Update struktur tabel estimasi berhasil.";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?> 