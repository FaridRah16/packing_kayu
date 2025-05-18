<?php
require_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();

try {
    // Cek apakah user keuangan sudah ada
    $check_query = "SELECT COUNT(*) as count FROM users WHERE username = 'keuangan'";
    $check_stmt = $db->prepare($check_query);
    $check_stmt->execute();
    $result = $check_stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result['count'] > 0) {
        echo "User dengan username 'keuangan' sudah ada di database.";
    } else {
        // Hash password (password: 'password')
        $password = password_hash('password', PASSWORD_DEFAULT);
        
        // Insert user dengan role keuangan
        $query = "INSERT INTO users (username, password, email, role) 
                 VALUES ('keuangan', :password, 'keuangan@packingkayu.com', 'keuangan')";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':password', $password);
        
        if ($stmt->execute()) {
            echo "User dengan role 'keuangan' berhasil ditambahkan!<br>";
            echo "Username: keuangan<br>";
            echo "Password: password<br>";
            echo "Email: keuangan@packingkayu.com";
        } else {
            echo "Gagal menambahkan user keuangan.";
        }
    }
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?> 