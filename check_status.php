<?php
require_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();

// Periksa nilai status yang ada
$query = "SELECT DISTINCT status FROM estimasi";
$stmt = $db->prepare($query);
$stmt->execute();
$statuses = $stmt->fetchAll(PDO::FETCH_COLUMN);

echo "Status yang ada di database: ";
print_r($statuses);
echo "\n";

// Periksa jumlah status batal
$query = "SELECT COUNT(*) as total FROM estimasi WHERE status = 'batal'";
$stmt = $db->prepare($query);
$stmt->execute();
$batal_count = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

echo "Jumlah status 'batal': " . $batal_count . "\n";

// Periksa jumlah status dibatalkan
$query = "SELECT COUNT(*) as total FROM estimasi WHERE status = 'dibatalkan'";
$stmt = $db->prepare($query);
$stmt->execute();
$dibatalkan_count = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

echo "Jumlah status 'dibatalkan': " . $dibatalkan_count . "\n";

// Periksa definisi kolom status
$query = "SHOW COLUMNS FROM estimasi LIKE 'status'";
$stmt = $db->prepare($query);
$stmt->execute();
$status_column = $stmt->fetch(PDO::FETCH_ASSOC);

echo "Definisi kolom status: ";
print_r($status_column);
echo "\n";

// Perbaiki status yang salah
if ($batal_count > 0) {
    $query = "UPDATE estimasi SET status = 'dibatalkan' WHERE status = 'batal'";
    $stmt = $db->prepare($query);
    $stmt->execute();
    echo "Status 'batal' telah diubah menjadi 'dibatalkan'\n";
}

echo "Selesai!"; 