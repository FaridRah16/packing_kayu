<?php
require_once '../auth.php';
checkAdmin();
require_once '../config/database.php';

// Pastikan ada parameter id
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'ID tidak valid']);
    exit;
}

$id = $_GET['id'];

try {
    $database = new Database();
    $db = $database->getConnection();
    
    $query = "SELECT * FROM harga_dimensi WHERE id = :id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id', $id);
    $stmt->execute();
    
    $harga = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$harga) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Data harga tidak ditemukan']);
        exit;
    }
    
    // Mengembalikan data dalam format JSON
    header('Content-Type: application/json');
    echo json_encode($harga);
    
} catch (PDOException $e) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?> 