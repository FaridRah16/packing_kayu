<?php
session_start();
require_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();

// Cek apakah kolom 'tipe' sudah ada di tabel 'estimasi'
try {
    $query = "SHOW COLUMNS FROM estimasi LIKE 'tipe'";
    $stmt = $db->prepare($query);
    $stmt->execute();
    
    // Jika kolom 'tipe' belum ada, tambahkan
    if ($stmt->rowCount() == 0) {
        $query = "ALTER TABLE estimasi ADD COLUMN tipe ENUM('order', 'estimasi') DEFAULT 'order'";
        $stmt = $db->prepare($query);
        $stmt->execute();
    }
} catch (PDOException $e) {
    // Jika gagal menambahkan kolom, abaikan dan lanjutkan
}

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
    
    // Tentukan ukuran berdasarkan pilihan
    if (isset($_POST['pilihan_ukuran']) && $_POST['pilihan_ukuran'] == 'default') {
        // Ukuran dari preset
        $ukuran_preset = $_POST['ukuran_preset'];
        
        switch ($ukuran_preset) {
            case 'minimum':
                $panjang = 10;
                $lebar = 10;
                $tinggi = 10;
                break;
            case 'small':
                $panjang = 30;
                $lebar = 20;
                $tinggi = 15;
                break;
            case 'medium':
                $panjang = 50;
                $lebar = 40;
                $tinggi = 30;
                break;
            case 'large':
                $panjang = 100;
                $lebar = 80;
                $tinggi = 60;
                break;
            default:
                $panjang = 10; // Nilai default jika tidak ada yang sesuai
                $lebar = 10;
                $tinggi = 10;
                break;
        }
    } else {
        // Ukuran custom
        $panjang = $_POST['panjang'];
        $lebar = $_POST['lebar'];
        $tinggi = $_POST['tinggi'];
    }
    
    $berat = $_POST['berat'];
    $jenis_kayu_id = $_POST['jenis_kayu'];
    $harga_dimensi_id = $_POST['jenis_harga'];
    
    // Validasi minimum
    $min_dimensi = 10; // Minimum 10 cm
    if ($_POST['pilihan_ukuran'] == 'default' && ($panjang < $min_dimensi || $lebar < $min_dimensi || $tinggi < $min_dimensi)) {
        // Redirect dengan error
        $_SESSION['error'] = "Dimensi minimum untuk ukuran default adalah {$min_dimensi}cm";
        header("Location: estimasi.php");
        exit();
    } elseif ($_POST['pilihan_ukuran'] == 'custom' && ($panjang <= 0 || $lebar <= 0 || $tinggi <= 0)) {
        // Untuk ukuran custom, pastikan hanya nilainya tidak 0 atau negatif
        $_SESSION['error'] = "Dimensi harus lebih besar dari 0 untuk ukuran custom";
        header("Location: estimasi.php");
        exit();
    }
    
    // Validasi berat minimum
    if ($berat < 1) {
        $_SESSION['error'] = "Berat minimum adalah 1kg";
        header("Location: estimasi.php");
        exit();
    }
    
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
    $query = "INSERT INTO estimasi (kode_pesanan, user_id, nama_barang, panjang, lebar, tinggi, berat, volume, jenis_kayu_id, harga_dimensi_id, nomor_whatsapp, estimasi_biaya, tipe, status) 
              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'order', 'pending')";
    $stmt = $db->prepare($query);
    $stmt->execute([
        $kode_pesanan,
        $_SESSION['user_id'] ?? null,
        $_POST['nama_barang'],
        $panjang,
        $lebar,
        $tinggi,
        $berat,
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
                <span class="fw-bold">PT. Cahaya Lintang Lestari</span>
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
                        <?php if(isset($_SESSION['error'])): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="bi bi-exclamation-triangle-fill me-2"></i> <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                        <?php endif; ?>
                        
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
                            
                            <div class="mb-3">
                                <label class="form-label">Pilihan Ukuran</label>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="pilihan_ukuran" id="ukuran_default" value="default" checked>
                                    <label class="form-check-label" for="ukuran_default">
                                        Ukuran Minimum
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="pilihan_ukuran" id="ukuran_custom" value="custom">
                                    <label class="form-check-label" for="ukuran_custom">
                                        Ukuran Custom
                                    </label>
                                </div>
                            </div>

                            <div id="ukuran_default_section">
                                <div class="mb-3">
                                    <label for="ukuran_preset" class="form-label">Pilih Ukuran</label>
                                    <select class="form-select" id="ukuran_preset" name="ukuran_preset">
                                        <option value="minimum">Minimum (10cm x 10cm x 10cm)</option>      
                                    </select>
                                </div>
                            </div>

                            <div id="ukuran_custom_section" style="display:none;">
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label for="panjang" class="form-label">Panjang (cm)</label>
                                            <input type="number" class="form-control" id="panjang" name="panjang" min="0" required>
                                            <?php if($harga_dimensi): ?>
                                                
                                            <?php endif; ?> 
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label for="lebar" class="form-label">Lebar (cm)</label>
                                            <input type="number" class="form-control" id="lebar" name="lebar" min="0" required>
                                            <?php if($harga_dimensi): ?>
                                                
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label for="tinggi" class="form-label">Tinggi (cm)</label>
                                            <input type="number" class="form-control" id="tinggi" name="tinggi" min="0" required>
                                            <?php if($harga_dimensi): ?>
                                                
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="berat" class="form-label">Berat (kg)</label>
                                <input type="number" class="form-control" id="berat" name="berat" min="1" required>
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
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const ukuranDefault = document.getElementById('ukuran_default');
            const ukuranCustom = document.getElementById('ukuran_custom');
            const ukuranDefaultSection = document.getElementById('ukuran_default_section');
            const ukuranCustomSection = document.getElementById('ukuran_custom_section');
            const ukuranPreset = document.getElementById('ukuran_preset');
            const panjangInput = document.getElementById('panjang');
            const lebarInput = document.getElementById('lebar');
            const tinggiInput = document.getElementById('tinggi');
            
            // Fungsi untuk toggle tampilan section
            function toggleUkuranSection() {
                if(ukuranDefault.checked) {
                    ukuranDefaultSection.style.display = 'block';
                    ukuranCustomSection.style.display = 'none';
                    updateDimensiDariPreset();
                } else {
                    ukuranDefaultSection.style.display = 'none';
                    ukuranCustomSection.style.display = 'block';
                }
            }
            
            // Update dimensi berdasarkan preset yang dipilih
            function updateDimensiDariPreset() {
                const selectedPreset = ukuranPreset.value;
                
                switch(selectedPreset) {
                    case 'minimum':
                        panjangInput.value = 10;
                        lebarInput.value = 10;
                        tinggiInput.value = 10;
                        break;
                    case 'small':
                        panjangInput.value = 30;
                        lebarInput.value = 20;
                        tinggiInput.value = 15;
                        break;
                    case 'medium':
                        panjangInput.value = 50;
                        lebarInput.value = 40;
                        tinggiInput.value = 30;
                        break;
                    case 'large':
                        panjangInput.value = 100;
                        lebarInput.value = 80;
                        tinggiInput.value = 60;
                        break;
                }
            }
            
            // Event listeners
            ukuranDefault.addEventListener('change', toggleUkuranSection);
            ukuranCustom.addEventListener('change', toggleUkuranSection);
            ukuranPreset.addEventListener('change', updateDimensiDariPreset);
            
            // Initialize
            toggleUkuranSection();
        });
    </script>
</body>
</html> 