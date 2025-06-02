<?php
require_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();

// Ubah semua estimasi yang kode pesanannya dimulai dengan 'EST' dan berasal dari form_estimasi.php menjadi tipe 'estimasi'
$query = "UPDATE estimasi SET tipe = 'estimasi' WHERE kode_pesanan LIKE 'EST%' AND tipe = 'order'";
$stmt = $db->prepare($query);
$stmt->execute();
$updated = $stmt->rowCount();

echo "<h2>Perbaikan Tipe Estimasi</h2>";
echo "<p>Berhasil mengubah $updated data estimasi menjadi tipe 'estimasi'.</p>";

// Periksa apakah masih ada data EST yang bertipe order
$query = "SELECT id, kode_pesanan, tipe, status FROM estimasi WHERE kode_pesanan LIKE 'EST%' AND tipe = 'order'";
$stmt = $db->prepare($query);
$stmt->execute();
$wrong_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (count($wrong_data) > 0) {
    echo "<h3>Data Yang Masih Perlu Diperbaiki:</h3>";
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>ID</th><th>Kode Pesanan</th><th>Tipe</th><th>Status</th></tr>";
    
    foreach ($wrong_data as $data) {
        echo "<tr>";
        echo "<td>" . $data['id'] . "</td>";
        echo "<td>" . $data['kode_pesanan'] . "</td>";
        echo "<td>" . $data['tipe'] . "</td>";
        echo "<td>" . $data['status'] . "</td>";
        echo "</tr>";
    }
    
    echo "</table>";
} else {
    echo "<p>Semua data dengan kode 'EST' sudah bertipe 'estimasi'.</p>";
}

// Tampilkan data estimasi setelah perubahan
$query = "SELECT id, kode_pesanan, tipe, status FROM estimasi";
$stmt = $db->prepare($query);
$stmt->execute();
$estimasi_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<h3>Data Estimasi Setelah Perubahan:</h3>";
echo "<table border='1' cellpadding='5'>";
echo "<tr><th>ID</th><th>Kode Pesanan</th><th>Tipe</th><th>Status</th></tr>";

foreach ($estimasi_list as $estimasi) {
    echo "<tr>";
    echo "<td>" . $estimasi['id'] . "</td>";
    echo "<td>" . $estimasi['kode_pesanan'] . "</td>";
    echo "<td>" . $estimasi['tipe'] . "</td>";
    echo "<td>" . $estimasi['status'] . "</td>";
    echo "</tr>";
}

echo "</table>";
?> 