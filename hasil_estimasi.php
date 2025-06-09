<?php
session_start();
require_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();

$estimasi_id = $_GET['id'] ?? null;

if (!$estimasi_id) {
    header("Location: index.php");
    exit();
}

// Ambil data estimasi
$query = "SELECT e.*, jk.nama as jenis_kayu_nama, jk.harga_per_m3, hd.nama as harga_dimensi_nama, 
          hd.harga_panjang, hd.harga_lebar, hd.harga_tinggi, hd.harga_per_kg
          FROM estimasi e 
          JOIN jenis_kayu jk ON e.jenis_kayu_id = jk.id 
          LEFT JOIN harga_dimensi hd ON e.harga_dimensi_id = hd.id
          WHERE e.id = ?";
$stmt = $db->prepare($query);
$stmt->execute([$estimasi_id]);
$estimasi = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$estimasi) {
    header("Location: index.php");
    exit();
}

// Format nomor WhatsApp untuk link WhatsApp API
$whatsapp_number = $estimasi['nomor_whatsapp'];
// Hapus karakter non-numerik
$whatsapp_number = preg_replace('/[^0-9]/', '', $whatsapp_number);
// Tambahkan kode negara 62 jika dimulai dengan 0
if (substr($whatsapp_number, 0, 1) === '0') {
    $whatsapp_number = '62' . substr($whatsapp_number, 1);
} 
// Jika tidak dimulai dengan 62, tambahkan 62 di depannya
else if (substr($whatsapp_number, 0, 2) !== '62') {
    $whatsapp_number = '62' . $whatsapp_number;
}

// Ambil harga dimensi yang aktif
$query = "SELECT * FROM harga_dimensi WHERE is_active = 1 ORDER BY id DESC LIMIT 1";
$stmt = $db->prepare($query);
$stmt->execute();
$harga_dimensi = $stmt->fetch(PDO::FETCH_ASSOC);

// Jika ditemukan harga dimensi, hitung biaya dimensi
$biaya_dimensi = 0;
$biaya_panjang = 0;
$biaya_lebar = 0;
$biaya_tinggi = 0;
$biaya_berat = 0;
$biaya_jenis_kayu = 0;

if(!empty($estimasi['harga_dimensi_id'])) {
    // Hitung biaya berdasarkan dimensi
    $biaya_panjang = $estimasi['panjang'] * $estimasi['harga_panjang'];
    $biaya_lebar = $estimasi['lebar'] * $estimasi['harga_lebar'];
    $biaya_tinggi = $estimasi['tinggi'] * $estimasi['harga_tinggi'];
    $biaya_berat = $estimasi['berat'] * $estimasi['harga_per_kg'];
    $biaya_dimensi = $biaya_panjang + $biaya_lebar + $biaya_tinggi + $biaya_berat;
} else {
    // Hitung biaya berdasarkan volume kayu
    $biaya_jenis_kayu = $estimasi['volume'] * $estimasi['harga_per_m3'];
}

// Total estimasi biaya sesuai dengan yang disimpan
$total_biaya = $estimasi['estimasi_biaya'];

// Ambil foto barang
$query = "SELECT * FROM foto_barang WHERE estimasi_id = ?";
$stmt = $db->prepare($query);
$stmt->execute([$estimasi_id]);
$foto_barang = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hasil Estimasi Packing Kayu</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <!-- <img src="assets/images/logo.png" alt="Logo" height="40"> -->
                <span class="fw-bold">Packing Kayu</span>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="estimasi.php">Estimasi</a>
                    </li>
                    <?php if(isset($_SESSION['user_id'])): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="profile.php">Profile</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="logout.php">Logout</a>
                        </li>
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="nav-link" href="login.php">Login</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="register.php">Register</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h3 class="text-center">Hasil Estimasi Packing Kayu</h3>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-success text-center">
                            <h5 class="mb-0">Total Estimasi Biaya: Rp <?php echo number_format($total_biaya, 0, ',', '.'); ?></h5>
                        </div>
                        
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <h5>Detail Barang</h5>
                                <p><strong>Nama Barang:</strong> <?php echo htmlspecialchars($estimasi['nama_barang']); ?></p>
                                <p><strong>Ukuran:</strong> <?php echo $estimasi['panjang']; ?>cm x <?php echo $estimasi['lebar']; ?>cm x <?php echo $estimasi['tinggi']; ?>cm</p>
                                <p><strong>Volume:</strong> <?php echo number_format($estimasi['volume'], 3); ?> m³</p>
                                <p><strong>Berat:</strong> <?php echo $estimasi['berat']; ?> kg</p>
                            </div>
                            <div class="col-md-6">
                                <h5>Detail Pesanan</h5>
                                <p><strong>Kode Pesanan:</strong> <?php echo $estimasi['kode_pesanan']; ?></p>
                                <p><strong>Jenis Kayu:</strong> <?php echo $estimasi['jenis_kayu_nama']; ?></p>
                                <p><strong>Kategori Harga:</strong> <?php echo $estimasi['harga_dimensi_nama'] ?? 'Harga Standar'; ?></p>
                                <p><strong>Nomor WhatsApp:</strong> <?php echo $estimasi['nomor_whatsapp']; ?></p>
                                <p><strong>Tanggal:</strong> <?php echo date('d M Y H:i', strtotime($estimasi['created_at'])); ?></p>
                            </div>
                        </div>

                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="mb-0">Rincian Biaya</h5>
                            </div>
                            <div class="card-body">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Komponen</th>
                                            <th>Detail</th>
                                            <th class="text-end">Biaya</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if(!empty($estimasi['harga_dimensi_id'])): ?>
                                        <tr>
                                            <td>Biaya Panjang</td>
                                            <td><?php echo $estimasi['panjang']; ?> cm x Rp <?php echo number_format($estimasi['harga_panjang'], 0, ',', '.'); ?></td>
                                            <td class="text-end">Rp <?php echo number_format($biaya_panjang, 0, ',', '.'); ?></td>
                                        </tr>
                                        <tr>
                                            <td>Biaya Lebar</td>
                                            <td><?php echo $estimasi['lebar']; ?> cm x Rp <?php echo number_format($estimasi['harga_lebar'], 0, ',', '.'); ?></td>
                                            <td class="text-end">Rp <?php echo number_format($biaya_lebar, 0, ',', '.'); ?></td>
                                        </tr>
                                        <tr>
                                            <td>Biaya Tinggi</td>
                                            <td><?php echo $estimasi['tinggi']; ?> cm x Rp <?php echo number_format($estimasi['harga_tinggi'], 0, ',', '.'); ?></td>
                                            <td class="text-end">Rp <?php echo number_format($biaya_tinggi, 0, ',', '.'); ?></td>
                                        </tr>
                                        <tr>
                                            <td>Biaya Berat</td>
                                            <td><?php echo $estimasi['berat']; ?> kg x Rp <?php echo number_format($estimasi['harga_per_kg'], 0, ',', '.'); ?></td>
                                            <td class="text-end">Rp <?php echo number_format($biaya_berat, 0, ',', '.'); ?></td>
                                        </tr>
                                        <?php else: ?>
                                        <tr>
                                            <td>Biaya Kayu</td>
                                            <td><?php echo $estimasi['jenis_kayu_nama']; ?> (<?php echo number_format($estimasi['volume'], 3); ?> m³ x Rp <?php echo number_format($estimasi['harga_per_m3'], 0, ',', '.'); ?>)</td>
                                            <td class="text-end">Rp <?php echo number_format($biaya_jenis_kayu, 0, ',', '.'); ?></td>
                                        </tr>
                                        <?php endif; ?>
                                        <tr class="table-secondary fw-bold">
                                            <td colspan="2">Total</td>
                                            <td class="text-end">Rp <?php echo number_format($total_biaya, 0, ',', '.'); ?></td>
                                        </tr>
                                    </tbody>
                                </table>
                                
                                <div class="alert alert-info mt-3">
                                    <i class="bi bi-info-circle-fill me-2"></i> 
                                    <?php if(!empty($estimasi['harga_dimensi_id'])): ?>
                                        Biaya dihitung berdasarkan dimensi (panjang, lebar, tinggi) dan berat dengan kategori harga <?php echo $estimasi['harga_dimensi_nama']; ?>.
                                    <?php else: ?>
                                        Biaya dihitung berdasarkan volume kayu.
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <?php if (!empty($foto_barang)): ?>
                        <div class="mb-4">
                            <h5>Foto Barang</h5>
                            <div class="row">
                                <?php foreach ($foto_barang as $foto): ?>
                                <div class="col-md-4 mb-3">
                                    <img src="<?php echo $foto['path']; ?>" class="img-fluid rounded" alt="Foto Barang">
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>

                        <div class="text-center">
                            <a href="cetak_estimasi.php?id=<?php echo $estimasi_id; ?>" class="btn btn-primary" target="_blank">
                                <i class="bi bi-printer"></i> Cetak Estimasi
                            </a>
                            <?php if(!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'owner', 'staff'])): ?>
                            <a href="https://wa.me/<?php echo $whatsapp_number; ?>?text=Halo, saya ingin memesan jasa packing kayu dengan kode pesanan <?php echo $estimasi['kode_pesanan']; ?>" class="btn btn-success" target="_blank">
                                <i class="bi bi-whatsapp"></i> Kirim Permintaan via WhatsApp
                            </a>
                            <?php endif; ?>
                            <?php if(isset($_SESSION['role']) && in_array($_SESSION['role'], ['admin', 'owner', 'staff'])): ?>
                            <div class="mt-3">
                                <?php if($_SESSION['role'] === 'admin'): ?>
                                <a href="admin/dashboard.php" class="btn btn-secondary">
                                    <i class="bi bi-arrow-left"></i> Kembali ke Dashboard
                                </a>
                                <?php elseif($_SESSION['role'] === 'owner'): ?>
                                <a href="owner/dashboard.php" class="btn btn-secondary">
                                    <i class="bi bi-arrow-left"></i> Kembali ke Dashboard
                                </a>
                                <?php elseif($_SESSION['role'] === 'staff'): ?>
                                <a href="staff_dashboard.php" class="btn btn-secondary">
                                    <i class="bi bi-arrow-left"></i> Kembali ke Dashboard
                                </a>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 