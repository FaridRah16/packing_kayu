<?php
require_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();

try {
    // Cek apakah user staff_keuangan sudah ada
    $check_query = "SELECT COUNT(*) as count FROM users WHERE username = 'staff_keuangan'";
    $check_stmt = $db->prepare($check_query);
    $check_stmt->execute();
    $result = $check_stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result['count'] > 0) {
        echo "User dengan username 'staff_keuangan' sudah ada di database.";
    } else {
        // Hash password (password: 'password123')
        $password = password_hash('password123', PASSWORD_DEFAULT);
        
        // Insert user dengan role keuangan
        $query = "INSERT INTO users (username, password, email, role) 
                 VALUES ('staff_keuangan', :password, 'staffkeuangan@packingkayu.com', 'keuangan')";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':password', $password);
        
        if ($stmt->execute()) {
            echo "User staff keuangan berhasil ditambahkan!<br>";
            echo "Username: staff_keuangan<br>";
            echo "Password: password123<br>";
            echo "Email: staffkeuangan@packingkayu.com<br>";
            echo "Role: keuangan";
        } else {
            echo "Gagal menambahkan user staff keuangan.";
        }
    }
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?> 