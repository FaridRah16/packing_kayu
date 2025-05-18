-- Tambahkan tabel harga_berat
CREATE TABLE IF NOT EXISTS `harga_berat` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `harga_per_kg` decimal(15,2) NOT NULL,
  `keterangan` text DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Tambahkan kolom berat di tabel estimasi jika belum ada
ALTER TABLE `estimasi` ADD COLUMN IF NOT EXISTS `berat` decimal(10,2) DEFAULT NULL AFTER `tinggi`; 