<?php
require_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();

// Hitung jumlah data dengan tipe 'order'
$query = "SELECT COUNT(*) as total FROM estimasi WHERE tipe = 'order' OR tipe IS NULL";
$stmt = $db->prepare($query);
$stmt->execute();
$order_count = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Hitung jumlah data dengan tipe 'estimasi'
$query = "SELECT COUNT(*) as total FROM estimasi WHERE tipe = 'estimasi'";
$stmt = $db->prepare($query);
$stmt->execute();
$estimasi_count = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

echo "Jumlah data dengan tipe 'order': " . $order_count . "\n";
echo "Jumlah data dengan tipe 'estimasi': " . $estimasi_count . "\n";

// Ambil beberapa contoh data dengan tipe 'order'
$query = "SELECT id, kode_pesanan, tipe, status FROM estimasi WHERE tipe = 'order' OR tipe IS NULL LIMIT 5";
$stmt = $db->prepare($query);
$stmt->execute();
$order_samples = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "\nContoh data dengan tipe 'order':\n";
foreach ($order_samples as $order) {
    echo "ID: " . $order['id'] . ", Kode: " . $order['kode_pesanan'] . ", Tipe: " . $order['tipe'] . ", Status: " . $order['status'] . "\n";
}

// Ambil beberapa contoh data dengan tipe 'estimasi'
$query = "SELECT id, kode_pesanan, tipe, status FROM estimasi WHERE tipe = 'estimasi' LIMIT 5";
$stmt = $db->prepare($query);
$stmt->execute();
$estimasi_samples = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "\nContoh data dengan tipe 'estimasi':\n";
foreach ($estimasi_samples as $estimasi) {
    echo "ID: " . $estimasi['id'] . ", Kode: " . $estimasi['kode_pesanan'] . ", Tipe: " . $estimasi['tipe'] . ", Status: " . $estimasi['status'] . "\n";
}
?> 