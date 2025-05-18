-- Tambahkan kolom harga_per_kg ke tabel harga_dimensi jika belum ada
ALTER TABLE `harga_dimensi` ADD COLUMN IF NOT EXISTS `harga_per_kg` decimal(15,2) DEFAULT 0 AFTER `harga_tinggi`;

-- Untuk database yang tidak mendukung "IF NOT EXISTS" dalam ALTER TABLE, gunakan cara ini:
-- (Hapus komentar jika diperlukan)
-- 
-- SET @exist := (SELECT COUNT(*) FROM information_schema.columns 
--   WHERE table_schema = DATABASE() AND table_name = 'harga_dimensi' AND column_name = 'harga_per_kg');
-- 
-- SET @query := IF(@exist = 0, 'ALTER TABLE `harga_dimensi` ADD COLUMN `harga_per_kg` decimal(15,2) DEFAULT 0 AFTER `harga_tinggi`', 'SELECT "Kolom sudah ada"');
-- 
-- PREPARE stmt FROM @query;
-- EXECUTE stmt;
-- DEALLOCATE PREPARE stmt; 