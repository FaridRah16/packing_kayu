<?php
session_start();
require_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();

// Ambil jenis kayu
$query = "SELECT * FROM jenis_kayu ORDER BY nama";
$stmt = $db->prepare($query);
$stmt->execute();
$jenis_kayu = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Ambil kategori harga dimensi (standard, medium, premium)
$query = "SELECT * FROM harga_dimensi ORDER BY nama";
$stmt = $db->prepare($query);
$stmt->execute();
$jenis_harga_dimensi = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Ambil harga dimensi yang aktif
$query = "SELECT * FROM harga_dimensi WHERE is_active = 1 ORDER BY id DESC LIMIT 1";
$stmt = $db->prepare($query);
$stmt->execute();
$harga_dimensi = $stmt->fetch(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Siapkan kode pesanan
    $kode_pesanan = "EST" . date('YmdHis') . rand(100, 999);
    
    $panjang = $_POST['panjang'];
    $lebar = $_POST['lebar'];
    $tinggi = $_POST['tinggi'];
    $berat = $_POST['berat'];
    $jenis_kayu_id = $_POST['jenis_kayu'];
    $harga_dimensi_id = $_POST['jenis_harga'];
    
    // Hitung volume (dalam m³)
    $volume = ($panjang * $lebar * $tinggi) / 1000000; // Konversi cm³ ke m³
    
    // Ambil harga per m³ untuk jenis kayu yang dipilih
    $query = "SELECT harga_per_m3 FROM jenis_kayu WHERE id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$jenis_kayu_id]);
    $harga_kayu = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Ambil data harga dimensi
    $query = "SELECT * FROM harga_dimensi WHERE id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$harga_dimensi_id]);
    $harga_dimensi = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Hitung biaya berdasarkan dimensi
    $biaya_dimensi = 0;
    if($harga_dimensi) {
        // Hitung biaya berdasarkan dimensi
        $biaya_dimensi = ($panjang * $harga_dimensi['harga_panjang']) + 
                         ($lebar * $harga_dimensi['harga_lebar']) + 
                         ($tinggi * $harga_dimensi['harga_tinggi']) +
                         ($berat * $harga_dimensi['harga_per_kg']);
    }
    
    // Hitung biaya kayu berdasarkan volume dan harga per m³
    $biaya_jenis_kayu = 0;
    if(!$harga_dimensi) {
        // Jika tidak menggunakan harga dimensi, hitung berdasarkan volume kayu
        $biaya_jenis_kayu = $volume * $harga_kayu['harga_per_m3'];
    }
    
    // Total estimasi biaya (biaya kayu + biaya dimensi)
    $estimasi_biaya = $biaya_jenis_kayu + $biaya_dimensi;
    
    // Simpan data estimasi
    $query = "INSERT INTO estimasi (kode_pesanan, user_id, nama_barang, panjang, lebar, tinggi, berat, volume, jenis_kayu_id, harga_dimensi_id, nomor_whatsapp, estimasi_biaya) 
              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $db->prepare($query);
    $stmt->execute([
        $kode_pesanan,
        $_SESSION['user_id'] ?? null,
        $_POST['nama_barang'],
        $_POST['panjang'],
        $_POST['lebar'],
        $_POST['tinggi'],
        $_POST['berat'],
        $volume,
        $jenis_kayu_id,
        $harga_dimensi_id,
        $_POST['nomor_whatsapp'],
        $estimasi_biaya
    ]);
    
    $estimasi_id = $db->lastInsertId();
    
    // Handle file upload
    if (!empty($_FILES['foto_barang']['name'][0])) {
        $upload_dir = 'uploads/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        foreach ($_FILES['foto_barang']['tmp_name'] as $key => $tmp_name) {
            $file_name = $_FILES['foto_barang']['name'][$key];
            $file_path = $upload_dir . $kode_pesanan . '_' . $file_name;
            
            if (move_uploaded_file($tmp_name, $file_path)) {
                $query = "INSERT INTO foto_barang (estimasi_id, nama_file, path) VALUES (?, ?, ?)";
                $stmt = $db->prepare($query);
                $stmt->execute([$estimasi_id, $file_name, $file_path]);
            }
        }
    }
    
    // Redirect ke hasil estimasi
    header("Location: hasil_estimasi.php?id=" . $estimasi_id);
    exit();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Estimasi Packing Kayu</title>
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
                        <a class="nav-link active" href="estimasi.php">Estimasi</a>
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
                        <h3 class="text-center">Form Estimasi Packing Kayu</h3>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle-fill me-2"></i> Ini hanya untuk estimasi biaya, jika ada perubahan dimensi, maka biaya akan berubah Hubungi Admin untuk perhitungan yang lebih detail
                        </div>
                        
                        <form action="estimasi.php" method="POST" enctype="multipart/form-data">
                            <div class="mb-3">
                                <label for="nama_barang" class="form-label">Nama Barang</label>
                                <input type="text" class="form-control" id="nama_barang" name="nama_barang" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="foto_barang" class="form-label">Foto Barang</label>
                                <input type="file" class="form-control" id="foto_barang" name="foto_barang[]" multiple accept="image/*">
                                <small class="text-muted">Format: JPG, PNG. Maksimal 5MB per file</small>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="panjang" class="form-label">Panjang (cm)</label>
                                        <input type="number" class="form-control" id="panjang" name="panjang" required>
                                        <?php if($harga_dimensi): ?>
                                            <small class="text-info">Harga: Rp <?php echo number_format($harga_dimensi['harga_panjang'], 0, ',', '.'); ?>/cm</small>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="lebar" class="form-label">Lebar (cm)</label>
                                        <input type="number" class="form-control" id="lebar" name="lebar" required>
                                        <?php if($harga_dimensi): ?>
                                            
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="tinggi" class="form-label">Tinggi (cm)</label>
                                        <input type="number" class="form-control" id="tinggi" name="tinggi" required>
                                        <?php if($harga_dimensi): ?>
                                            <small class="text-info">Harga: Rp <?php echo number_format($harga_dimensi['harga_tinggi'], 0, ',', '.'); ?>/cm</small>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="berat" class="form-label">Berat (kg)</label>
                                <input type="number" class="form-control" id="berat" name="berat" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="jenis_kayu" class="form-label">Jenis Kayu</label>
                                <select class="form-select" id="jenis_kayu" name="jenis_kayu" required>
                                    <option value="">Pilih Jenis Kayu</option>
                                    <?php foreach($jenis_kayu as $kayu): ?>
                                        <option value="<?php echo $kayu['id']; ?>">
                                            <?php echo $kayu['nama']; ?> <?php if(!$harga_dimensi): ?> - Rp <?php echo number_format($kayu['harga_per_m3'], 0, ',', '.'); ?>/m³<?php endif; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if($harga_dimensi): ?>
                                    <small class="text-muted">Harga dihitung berdasarkan dimensi, bukan volume kayu</small>
                                <?php endif; ?>
                            </div>
                            
                            <div class="mb-3">
                                <label for="jenis_harga" class="form-label">Kategori Harga</label>
                                <select class="form-select" id="jenis_harga" name="jenis_harga" required>
                                    <option value="">Pilih Kategori Harga</option>
                                    <?php foreach($jenis_harga_dimensi as $harga): ?>
                                        <option value="<?php echo $harga['id']; ?>">
                                            <?php echo $harga['nama']; ?> 
                                            (P: Rp <?php echo number_format($harga['harga_panjang'], 0, ',', '.'); ?>/cm, 
                                             L: Rp <?php echo number_format($harga['harga_lebar'], 0, ',', '.'); ?>/cm, 
                                             T: Rp <?php echo number_format($harga['harga_tinggi'], 0, ',', '.'); ?>/cm)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <small class="text-muted">Pilih kategori harga sesuai kebutuhan Anda</small>
                            </div>
                            
                            <div class="mb-3">
                                <label for="nomor_whatsapp" class="form-label">Nomor WhatsApp</label>
                                <input type="text" class="form-control" id="nomor_whatsapp" name="nomor_whatsapp" required>
                            </div>
                            
                            <div class="text-center">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-calculator me-1"></i> Hitung Estimasi
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 