-- Database: packing_kayu

CREATE DATABASE IF NOT EXISTS packing_kayu;
USE packing_kayu;

-- Tabel Users
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    role ENUM('admin', 'customer', 'staff', 'owner', 'kurir') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabel Jenis Kayu
CREATE TABLE jenis_kayu (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nama VARCHAR(50) NOT NULL,
    harga_per_m3 DECIMAL(10,2) NOT NULL,
    deskripsi TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabel Harga Dimensi
CREATE TABLE harga_dimensi (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nama VARCHAR(50) NOT NULL,
    harga_panjang DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    harga_lebar DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    harga_tinggi DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    keterangan TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Tabel Estimasi
CREATE TABLE estimasi (
    id INT PRIMARY KEY AUTO_INCREMENT,
    kode_pesanan VARCHAR(20) NOT NULL UNIQUE,
    user_id INT,
    nama_barang VARCHAR(100) NOT NULL,
    panjang DECIMAL(10,2) NOT NULL,
    lebar DECIMAL(10,2) NOT NULL,
    tinggi DECIMAL(10,2) NOT NULL,
    berat DECIMAL(10,2) NOT NULL,
    volume DECIMAL(10,2) NOT NULL,
    jenis_kayu_id INT,
    lokasi_tujuan VARCHAR(255),
    nomor_whatsapp VARCHAR(20),
    estimasi_biaya DECIMAL(10,2) NOT NULL,
    status ENUM('pending', 'diproses', 'selesai', 'dibatalkan') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (jenis_kayu_id) REFERENCES jenis_kayu(id)
);

-- Tabel Foto Barang
CREATE TABLE foto_barang (
    id INT PRIMARY KEY AUTO_INCREMENT,
    estimasi_id INT,
    nama_file VARCHAR(255) NOT NULL,
    path VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (estimasi_id) REFERENCES estimasi(id)
);

-- Tabel Pesanan
CREATE TABLE pesanan (
    id INT PRIMARY KEY AUTO_INCREMENT,
    estimasi_id INT,
    status ENUM('pending', 'diproses', 'selesai', 'dibatalkan') DEFAULT 'pending',
    catatan TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (estimasi_id) REFERENCES estimasi(id)
);

-- Tabel Log Aktivitas
CREATE TABLE log_aktivitas (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    aktivitas VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Insert data awal untuk admin
INSERT INTO users (username, password, email, role) 
VALUES ('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin@packingkayu.com', 'admin'); 

-- Insert data awal untuk harga dimensi
INSERT INTO harga_dimensi (nama, harga_panjang, harga_lebar, harga_tinggi, keterangan) 
VALUES ('Standar', 2500, 2000, 1500, 'Harga standar per cm untuk dimensi panjang, lebar dan tinggi'); 