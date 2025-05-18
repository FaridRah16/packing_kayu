<?php
require_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();

// Simulasi proses login
$username = 'staff_keuangan'; 
$password = 'password123';

try {
    // Cek user
    $query = "SELECT * FROM users WHERE username = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "<h3>Debug Login Staff Keuangan</h3>";
    
    if ($user) {
        echo "User ditemukan di database:<br>";
        echo "ID: " . $user['id'] . "<br>";
        echo "Username: " . $user['username'] . "<br>";
        echo "Role: " . $user['role'] . "<br>";
        
        if (password_verify($password, $user['password'])) {
            echo "<p style='color:green'>Password benar!</p>";
            
            echo "<h4>Simulasi Redirect berdasarkan role</h4>";
            
            echo "Nilai role: '" . $user['role'] . "'<br>";
            if ($user['role'] == 'admin') {
                echo "Redirect ke: admin/dashboard.php<br>";
            } else if ($user['role'] == 'owner') {
                echo "Redirect ke: owner/dashboard.php<br>";
            } else if ($user['role'] == 'staff') {
                echo "Redirect ke: staff_dashboard.php<br>";
            } else if ($user['role'] == 'keuangan') {
                echo "Redirect ke: keuangan/dashboard.php<br>";
            } else {
                echo "Redirect ke: index.php<br>";
            }
        } else {
            echo "<p style='color:red'>Password salah!</p>";
        }
    } else {
        echo "<p style='color:red'>User tidak ditemukan!</p>";
    }
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}

// Periksa struktur ENUM pada kolom role
echo "<h3>Struktur ENUM pada kolom 'role'</h3>";
try {
    $query = "SHOW COLUMNS FROM users WHERE Field = 'role'";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $column = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "Tipe: " . $column['Type'] . "<br>";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}

// Periksa semua user
echo "<h3>Daftar semua user</h3>";
try {
    $query = "SELECT id, username, email, role, created_at FROM users";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>ID</th><th>Username</th><th>Email</th><th>Role</th><th>Created At</th></tr>";
    
    foreach ($users as $user) {
        echo "<tr>";
        echo "<td>" . $user['id'] . "</td>";
        echo "<td>" . $user['username'] . "</td>";
        echo "<td>" . $user['email'] . "</td>";
        echo "<td>" . $user['role'] . "</td>";
        echo "<td>" . $user['created_at'] . "</td>";
        echo "</tr>";
    }
    
    echo "</table>";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?> 