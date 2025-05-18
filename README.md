# Aplikasi Estimasi Packing Kayu

Aplikasi web untuk menghitung estimasi biaya packing kayu dengan fitur upload foto barang dan cetak estimasi dalam format PDF.

## Fitur

- Input detail barang (nama, ukuran, berat)
- Upload foto barang (multiple)
- Pilih jenis kayu
- Hitung estimasi biaya otomatis
- Cetak estimasi dalam format PDF
- Kirim permintaan via WhatsApp
- Manajemen data pengguna (admin, customer, staff, owner)
- Dashboard admin untuk monitoring

## Persyaratan Sistem

- PHP >= 7.4
- MySQL >= 5.7
- Composer
- Web server (Apache/Nginx)

## Instalasi

1. Clone repository:
```bash
git clone https://github.com/username/packing-kayu.git
cd packing-kayu
```

2. Install dependensi:
```bash
composer install
```

3. Buat database dan import struktur:
```bash
mysql -u username -p < database.sql
```

4. Konfigurasi database:
- Buka file `config/database.php`
- Sesuaikan kredensial database

5. Buat direktori upload:
```bash
mkdir uploads
chmod 777 uploads
```

6. Konfigurasi web server:
- Pastikan direktori `uploads` dapat diakses
- Set document root ke direktori public
- Enable mod_rewrite (Apache)

## Struktur Database

- users: Data pengguna
- jenis_kayu: Data jenis kayu dan harga
- estimasi: Data estimasi packing
- foto_barang: Foto barang yang diupload
- pesanan: Data pesanan
- log_aktivitas: Log aktivitas pengguna

## Penggunaan

1. Buka aplikasi di browser
2. Login sebagai admin/customer
3. Input data barang
4. Upload foto barang
5. Pilih jenis kayu
6. Klik "Hitung Estimasi"
7. Cetak estimasi atau kirim permintaan via WhatsApp

## Keamanan

- Password di-hash menggunakan bcrypt
- Input validation
- XSS prevention
- CSRF protection
- File upload validation

## Kontribusi

1. Fork repository
2. Buat branch fitur
3. Commit perubahan
4. Push ke branch
5. Buat Pull Request

## Lisensi

MIT License

## Informasi Pembaruan

### Penggabungan Harga Dimensi & Berat

Pada pembaruan terbaru, fitur harga berat telah digabungkan dengan fitur harga dimensi. Perubahan ini membuat pengelolaan harga menjadi lebih sederhana dan terintegrasi dalam satu halaman.

Langkah-langkah implementasi:

1. Jalankan file SQL `update_harga_dimensi.sql` untuk menambahkan kolom `harga_per_kg` pada tabel `harga_dimensi`
2. Login sebagai admin dan akses menu "Harga Dimensi & Berat"
3. Pada saat menambah atau mengedit harga dimensi, Anda sekarang juga dapat mengatur harga per kg
4. Setelah data dipindahkan, Anda dapat menjalankan file `cleanup_harga_berat.sql` untuk membersihkan tabel lama (opsional)

Menu "Harga Berat" telah dihapus dari sidebar admin karena sudah digabungkan dengan "Harga Dimensi & Berat". 