<?php
require_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();

// Ambil ID estimasi yang ada dengan status pending atau diproses
$query = "SELECT id FROM estimasi WHERE status IN ('pending', 'diproses') LIMIT 1";
$stmt = $db->prepare($query);
$stmt->execute();
$result = $stmt->fetch(PDO::FETCH_ASSOC);

if ($result) {
    $id = $result['id'];
    
    // Update status menjadi dibatalkan
    $query = "UPDATE estimasi SET status = 'dibatalkan' WHERE id = :id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id', $id);
    $stmt->execute();
    
    echo "Berhasil mengubah status estimasi ID: $id menjadi 'dibatalkan'\n";
} else {
    echo "Tidak ada data estimasi dengan status pending atau diproses\n";
    
    // Jika tidak ada, buat data baru
    $kode_pesanan = 'TEST-' . date('YmdHis');
    $nama_barang = 'Test Barang Dibatalkan';
    $panjang = 100;
    $lebar = 100;
    $tinggi = 100;
    $berat = 10;
    $jenis_kayu_id = 1; // Pastikan ID ini ada di tabel jenis_kayu
    $estimasi_biaya = 500000;
    $status = 'dibatalkan';
    
    $query = "INSERT INTO estimasi (kode_pesanan, nama_barang, panjang, lebar, tinggi, berat, jenis_kayu_id, estimasi_biaya, status) 
              VALUES (:kode_pesanan, :nama_barang, :panjang, :lebar, :tinggi, :berat, :jenis_kayu_id, :estimasi_biaya, :status)";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':kode_pesanan', $kode_pesanan);
    $stmt->bindParam(':nama_barang', $nama_barang);
    $stmt->bindParam(':panjang', $panjang);
    $stmt->bindParam(':lebar', $lebar);
    $stmt->bindParam(':tinggi', $tinggi);
    $stmt->bindParam(':berat', $berat);
    $stmt->bindParam(':jenis_kayu_id', $jenis_kayu_id);
    $stmt->bindParam(':estimasi_biaya', $estimasi_biaya);
    $stmt->bindParam(':status', $status);
    $stmt->execute();
    
    echo "Berhasil membuat data estimasi baru dengan status 'dibatalkan'\n";
}

echo "Selesai!"; 