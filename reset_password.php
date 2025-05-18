<?php
require_once 'config/database.php';
$database = new Database();
$db = $database->getConnection();

$newPassword = "admin123";
$hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

$query = "UPDATE users SET password = ? WHERE username = 'admin'";
$stmt = $db->prepare($query);
$result = $stmt->execute([$hashedPassword]);

if($result) {
    echo "Reset password berhasil! Password baru: " . $newPassword;
    echo "<br><a href='login.php'>Login</a>";
} else {
    echo "Gagal mereset password!";
}
?> 