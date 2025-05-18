-- Menambahkan role keuangan pada tabel users
ALTER TABLE users 
MODIFY COLUMN role ENUM('admin', 'customer', 'staff', 'owner', 'kurir', 'keuangan') NOT NULL; 