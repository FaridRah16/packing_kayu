-- Catatan: Jalankan file ini setelah data harga berat dipindahkan ke tabel harga_dimensi
-- Pastikan data penting sudah dipindahkan sebelum menghapus tabel

-- Opsi 1: Drop tabel harga_berat jika sudah tidak diperlukan
DROP TABLE IF EXISTS `harga_berat`;

-- Opsi 2 (Lebih aman): Ubah nama tabel sebagai backup
-- RENAME TABLE `harga_berat` TO `harga_berat_old`; 