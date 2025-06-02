<?php
require_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();

// Hitung jumlah data berdasarkan tipe
$stmt = $db->query('SELECT COUNT(*) as total FROM estimasi WHERE tipe = "order" OR tipe IS NULL');
$order_count = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

$stmt = $db->query('SELECT COUNT(*) as total FROM estimasi WHERE tipe = "estimasi"');
$estimasi_count = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

echo "Jumlah order: $order_count\n";
echo "Jumlah estimasi (simulasi): $estimasi_count\n";

// Tampilkan semua data
echo "\nData dalam database:\n";
$stmt = $db->query('SELECT id, kode_pesanan, tipe, status FROM estimasi ORDER BY id');
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "ID: {$row['id']}, Kode: {$row['kode_pesanan']}, Tipe: {$row['tipe']}, Status: {$row['status']}\n";
}

// Periksa semua data dengan tipe estimasi
echo "\nData dengan tipe 'estimasi':\n";
$stmt = $db->query('SELECT id, kode_pesanan, tipe, status FROM estimasi WHERE tipe = "estimasi" ORDER BY id');
$estimasi_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (count($estimasi_list) == 0) {
    echo "Tidak ada data dengan tipe 'estimasi'\n";
} else {
    foreach ($estimasi_list as $estimasi) {
        echo "ID: {$estimasi['id']}, Kode: {$estimasi['kode_pesanan']}, Tipe: {$estimasi['tipe']}, Status: {$estimasi['status']}\n";
    }
}

echo "\nUJI QUERY DAFTAR ORDER:\n";
echo "Query: SELECT * FROM estimasi WHERE (tipe = 'order' OR tipe IS NULL)\n\n";

$stmt = $db->query('SELECT id, kode_pesanan, tipe, status FROM estimasi WHERE (tipe = "order" OR tipe IS NULL) ORDER BY id');
$order_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (count($order_list) == 0) {
    echo "Tidak ada data order\n";
} else {
    foreach ($order_list as $order) {
        echo "ID: {$order['id']}, Kode: {$order['kode_pesanan']}, Tipe: {$order['tipe']}, Status: {$order['status']}\n";
    }
}

// Periksa apakah ada data dengan kode pesanan yang dimulai dengan EST yang masih bertipe 'order'
echo "\nPeriksa data EST yang masih bertipe 'order':\n";
$stmt = $db->query('SELECT id, kode_pesanan, tipe, status FROM estimasi WHERE kode_pesanan LIKE "EST%" AND tipe = "order"');
$wrong_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (count($wrong_data) == 0) {
    echo "Tidak ada data yang salah\n";
} else {
    foreach ($wrong_data as $data) {
        echo "ID: {$data['id']}, Kode: {$data['kode_pesanan']}, Tipe: {$data['tipe']}, Status: {$data['status']}\n";
    }
} 