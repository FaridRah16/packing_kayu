<?php
require_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();

// Ambil data estimasi
$query = "SELECT id, kode_pesanan, tipe, status FROM estimasi";
$stmt = $db->prepare($query);
$stmt->execute();
$estimasi_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<h2>Data Estimasi:</h2>";
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