<?php
// Script untuk menambahkan kolom 'tipe' ke tabel estimasi
// dan mengupdate nilai pada data yang sudah ada

require_once 'config/database.php';

// Fungsi untuk menampilkan pesan
function showMessage($message, $success = true) {
    $style = $success ? 'color: green;' : 'color: red;';
    echo "<p style='$style'>$message</p>";
}

echo "<h1>Script Update Tabel Estimasi</h1>";

try {
    // Koneksi ke database
    $database = new Database();
    $db = $database->getConnection();
    
    // 1. Cek apakah kolom 'tipe' sudah ada di tabel 'estimasi'
    $query = "SHOW COLUMNS FROM estimasi LIKE 'tipe'";
    $stmt = $db->prepare($query);
    $stmt->execute();
    
    if ($stmt->rowCount() == 0) {
        // Kolom 'tipe' belum ada, tambahkan
        $query = "ALTER TABLE estimasi ADD COLUMN tipe ENUM('order', 'estimasi') DEFAULT 'estimasi'";
        $stmt = $db->prepare($query);
        $stmt->execute();
        showMessage("Kolom 'tipe' berhasil ditambahkan ke tabel estimasi dengan default 'estimasi'!");
    } else {
        showMessage("Kolom 'tipe' sudah ada di tabel estimasi.");
    }
    
    echo "<hr/>";
    echo "<p>Update berhasil dilakukan. Silakan kembali ke <a href='admin/dashboard.php'>Dashboard Admin</a>.</p>";
    
} catch (PDOException $e) {
    showMessage("Error: " . $e->getMessage(), false);
}
?> 