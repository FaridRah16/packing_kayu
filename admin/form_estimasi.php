<?php
require_once '../auth.php';
checkAdmin();
require_once '../config/database.php';

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

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Siapkan kode pesanan
    $kode_pesanan = "EST" . date('YmdHis') . rand(100, 999);
    
    // Ambil data formulir
    $nama_barang = $_POST['nama_barang'];
    $jenis_kayu_id = $_POST['jenis_kayu'];
    $harga_dimensi_id = $_POST['jenis_harga'];
    $nomor_whatsapp = $_POST['nomor_whatsapp'];
    
    // Tentukan ukuran berdasarkan pilihan
    if ($_POST['pilihan_ukuran'] == 'default') {
        // Default ukuran minimum
        $panjang = 10;
        $lebar = 10;
        $tinggi = 10;
    } else {
        // Custom ukuran
        $panjang = $_POST['panjang'];
        $lebar = $_POST['lebar'];
        $tinggi = $_POST['tinggi'];
    }
    
    // Validasi ukuran minimum untuk ukuran default
    if ($_POST['pilihan_ukuran'] == 'default') {
        if ($panjang < 10 || $lebar < 10 || $tinggi < 10) {
            $error = "Ukuran minimum untuk panjang, lebar, dan tinggi adalah 10 cm";
        }
    }
    
    // Ambil berat
    $berat = $_POST['berat'];
    
    // Hitung volume dalam m³
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
    
    if (empty($error)) {
        try {
            // Simpan data estimasi dengan tipe 'estimasi' untuk pengetesan saja
            $query = "INSERT INTO estimasi (kode_pesanan, user_id, nama_barang, panjang, lebar, tinggi, berat, volume, jenis_kayu_id, harga_dimensi_id, nomor_whatsapp, estimasi_biaya, tipe, status) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'estimasi', 'pending')";
            $stmt = $db->prepare($query);
            $stmt->execute([
                $kode_pesanan,
                $_SESSION['user_id'] ?? null,
                $nama_barang,
                $panjang,
                $lebar,
                $tinggi,
                $berat,
                $volume,
                $jenis_kayu_id,
                $harga_dimensi_id,
                $nomor_whatsapp,
                $estimasi_biaya
            ]);
            
            $estimasi_id = $db->lastInsertId();
            
            // Handle file upload
            if (!empty($_FILES['foto_barang']['name'][0])) {
                $upload_dir = '../uploads/';
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
            
            $message = "Estimasi berhasil dibuat dengan kode pesanan: " . $kode_pesanan . " (Simulasi/Pengetesan saja, tidak masuk ke daftar order)";
            
            // Redirect ke hasil estimasi
            header("Location: ../hasil_estimasi.php?id=" . $estimasi_id);
            exit();
        } catch (PDOException $e) {
            $error = "Gagal menyimpan estimasi: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Form Estimasi - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        :root {
            --primary-color: #4e73df;
            --success-color: #1cc88a;
            --info-color: #36b9cc;
            --warning-color: #f6c23e;
            --danger-color: #e74a3b;
            --secondary-color: #858796;
            --light-color: #f8f9fc;
            --dark-color: #5a5c69;
        }
        
        body {
            background-color: #f8f9fc;
            font-family: 'Nunito', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
        }
        
        .sidebar {
            background: linear-gradient(180deg, #4e73df 10%, #224abe 100%);
            box-shadow: 0 .15rem 1.75rem 0 rgba(58,59,69,.15);
            min-height: 100vh;
            position: fixed;
            z-index: 1;
            width: inherit;
            max-width: inherit;
        }
        
        .sidebar .nav-link {
            color: rgba(255, 255, 255, 0.8);
            font-weight: 600;
            padding: 1rem;
            margin-bottom: 0.2rem;
            border-radius: 0.5rem;
            transition: all 0.2s ease;
        }
        
        .sidebar .nav-link:hover {
            color: #fff;
            background-color: rgba(255, 255, 255, 0.1);
            transform: translateX(5px);
        }
        
        .sidebar .nav-link.active {
            color: #fff;
            background-color: rgba(255, 255, 255, 0.2);
        }
        
        .sidebar .nav-link i {
            margin-right: 0.5rem;
            font-size: 1.1rem;
        }
        
        .sidebar-brand {
            padding: 1.5rem 1rem;
            color: white;
            font-size: 1.2rem;
            font-weight: 800;
            text-align: center;
            text-transform: uppercase;
            letter-spacing: 0.05rem;
        }
        
        .sidebar-divider {
            border-top: 1px solid rgba(255, 255, 255, 0.15);
            margin: 1rem 0;
        }
        
        .card {
            border: none;
            border-radius: 0.75rem;
            box-shadow: 0 .15rem 1.75rem 0 rgba(58,59,69,.1);
            margin-bottom: 1.5rem;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            overflow: hidden;
        }
        
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 .5rem 2rem 0 rgba(58,59,69,.15);
        }
        
        .card-header {
            background: linear-gradient(40deg, rgba(78, 115, 223, 0.05) 0%, rgba(54, 185, 204, 0.05) 100%);
            border-bottom: 1px solid #e3e6f0;
            padding: 1.25rem 1.5rem;
            font-weight: 700;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .card-header .header-icon {
            font-size: 1.75rem;
            color: var(--primary-color);
            opacity: 0.7;
        }
        
        .navbar-admin {
            background-color: white;
            box-shadow: 0 .15rem 1.75rem 0 rgba(58,59,69,.1);
            padding: 0.75rem 1.5rem;
            border-radius: 0.5rem;
            margin-bottom: 1.5rem;
        }
        
        .navbar-admin .navbar-brand {
            color: #5a5c69;
            font-weight: 700;
        }
        
        .navbar-admin .dropdown-toggle::after {
            display: none;
        }
        
        .navbar-admin .user-dropdown {
            font-weight: 600;
            color: #5a5c69;
        }
        
        .dropdown-menu {
            box-shadow: 0 .15rem 1.75rem 0 rgba(58,59,69,.15);
            border: none;
            border-radius: 0.5rem;
            overflow: hidden;
        }
        
        .form-control, .form-select {
            border-radius: 0.5rem;
            padding: 0.75rem 1rem;
            border: 1px solid #eaecf4;
            transition: all 0.2s ease;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: #bac8f3;
            box-shadow: 0 0 0 0.25rem rgba(78, 115, 223, 0.1);
        }
        
        .main-content {
            padding-top: 1.5rem;
            margin-left: 17% !important;
        }
        
        .sticky-footer {
            padding: 1rem 0;
            margin-top: 2rem;
            background-color: transparent;
        }
        
        .btn-primary {
            background: linear-gradient(40deg, #4e73df 0%, #3662e0 100%);
            border: none;
            box-shadow: 0 4px 7px rgba(78, 115, 223, 0.2);
            transition: all 0.2s ease;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(78, 115, 223, 0.3);
        }
        
        @media (max-width: 768px) {
            .sidebar {
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                z-index: 1050;
                overflow-y: auto;
                transform: translateX(-100%);
                transition: transform 0.3s ease;
            }
            
            .sidebar.show {
                transform: translateX(0);
            }
            
            .navbar-toggler {
                display: block;
            }
            
            .main-content {
                margin-left: 0 !important;
            }
            
            .card-body {
                padding: 1rem;
            }
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <nav class="col-md-3 col-lg-2 d-md-block sidebar collapse">
                <div class="position-sticky">
                    <div class="sidebar-brand d-flex align-items-center justify-content-center">
                        <i class="bi bi-box-seam me-2"></i>
                        <span>Packing Kayu</span>
                    </div>
                    
                    <hr class="sidebar-divider">
                    
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link" href="dashboard.php">
                                <i class="bi bi-speedometer2"></i> Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="users.php">
                                <i class="bi bi-people"></i> Users
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="jenis_kayu.php">
                                <i class="bi bi-tree"></i> Jenis Kayu
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="harga_dimensi.php">
                                <i class="bi bi-rulers"></i> Harga Dimensi
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="estimasi.php">
                                <i class="bi bi-calculator"></i> Order
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="form_estimasi.php">
                                <i class="bi bi-clipboard-check"></i> Estimasi
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="laporan.php">
                                <i class="bi bi-file-earmark-text"></i> Laporan
                            </a>
                        </li>
                        <li class="nav-item mt-3">
                            <a class="nav-link" href="../logout.php">
                                <i class="bi bi-box-arrow-left"></i> Logout
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>

            <!-- Main content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 main-content">
                <!-- Navbar -->
                <nav class="navbar navbar-expand-lg navbar-light navbar-admin mb-4">
                    <button class="navbar-toggler d-md-none collapsed me-3" type="button" data-bs-toggle="collapse" data-bs-target=".sidebar">
                        <span class="navbar-toggler-icon"></span>
                    </button>
                    <span class="navbar-brand">Form Estimasi</span>
                    <div class="ms-auto">
                        <div class="dropdown profile-dropdown">
                            <button class="btn dropdown-toggle user-dropdown" type="button" data-bs-toggle="dropdown">
                                <span class="d-none d-md-inline"><?php echo $_SESSION['username']; ?></span>
                                <i class="bi bi-person-circle"></i>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li><a class="dropdown-item" href="../profile.php"><i class="bi bi-person me-2"></i> Profil</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="../logout.php"><i class="bi bi-box-arrow-right me-2"></i> Logout</a></li>
                            </ul>
                        </div>
                    </div>
                </nav>

                <!-- Alert Message -->
                <?php if($message): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="bi bi-check-circle me-2"></i> <?php echo $message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php endif; ?>
                
                <?php if($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-triangle me-2"></i> <?php echo $error; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php endif; ?>

                <!-- Form Estimasi -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h6 class="m-0 font-weight-bold">Form Estimasi Packing Kayu</h6>
                        <div class="header-icon">
                            <i class="bi bi-calculator"></i>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-info mb-4">
                            <i class="bi bi-info-circle me-2"></i> Form ini hanya untuk simulasi/pengetesan estimasi biaya packing kayu. Data yang diinput tidak akan masuk ke daftar order.
                        </div>
                        
                        <form method="post" action="" enctype="multipart/form-data" id="formEstimasi">
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="nama_barang" class="form-label">Nama Barang</label>
                                    <input type="text" class="form-control" id="nama_barang" name="nama_barang" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="jenis_kayu" class="form-label">Jenis Kayu</label>
                                    <select class="form-select" id="jenis_kayu" name="jenis_kayu" required>
                                        <option value="">Pilih Jenis Kayu</option>
                                        <?php foreach ($jenis_kayu as $kayu): ?>
                                        <option value="<?php echo $kayu['id']; ?>"><?php echo $kayu['nama']; ?> - Rp <?php echo number_format($kayu['harga_per_m3'], 0, ',', '.'); ?>/m³</option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="jenis_harga" class="form-label">Jenis Harga Dimensi</label>
                                    <select class="form-select" id="jenis_harga" name="jenis_harga" required>
                                        <option value="">Pilih Jenis Harga</option>
                                        <?php foreach ($jenis_harga_dimensi as $harga): ?>
                                        <option value="<?php echo $harga['id']; ?>"><?php echo $harga['nama']; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label for="berat" class="form-label">Berat (kg)</label>
                                    <input type="number" class="form-control" id="berat" name="berat" step="0.01" min="0" required>
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-12">
                                    <label class="form-label">Pilihan Ukuran</label>
                                    <div class="form-check mb-2">
                                        <input class="form-check-input" type="radio" name="pilihan_ukuran" id="ukuran_default" value="default" checked>
                                        <label class="form-check-label" for="ukuran_default">
                                            Ukuran Minimum (10cm x 10cm x 10cm)
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="pilihan_ukuran" id="ukuran_custom" value="custom">
                                        <label class="form-check-label" for="ukuran_custom">
                                            Ukuran Custom
                                        </label>
                                    </div>
                                </div>
                            </div>
                            
                            <div id="ukuran_custom_fields" class="row mb-3" style="display: none;">
                                <div class="col-md-4">
                                    <label for="panjang" class="form-label">Panjang (cm)</label>
                                    <input type="number" class="form-control" id="panjang" name="panjang" value="10">
                                </div>
                                <div class="col-md-4">
                                    <label for="lebar" class="form-label">Lebar (cm)</label>
                                    <input type="number" class="form-control" id="lebar" name="lebar" value="10">
                                </div>
                                <div class="col-md-4">
                                    <label for="tinggi" class="form-label">Tinggi (cm)</label>
                                    <input type="number" class="form-control" id="tinggi" name="tinggi" value="10">
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="nomor_whatsapp" class="form-label">Nomor WhatsApp</label>
                                    <div class="input-group">
                                        <span class="input-group-text">+62</span>
                                        <input type="text" class="form-control" id="nomor_whatsapp" name="nomor_whatsapp" placeholder="8xxxxxxxxxx" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label for="foto_barang" class="form-label">Foto Barang (opsional)</label>
                                    <input type="file" class="form-control" id="foto_barang" name="foto_barang[]" multiple accept="image/*">
                                </div>
                            </div>
                            
                            <div class="mt-4">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-calculator me-1"></i> Hitung Estimasi
                                </button>
                                <a href="dashboard.php" class="btn btn-secondary ms-2">
                                    <i class="bi bi-arrow-left me-1"></i> Kembali
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Footer -->
                <footer class="sticky-footer mt-4 mb-2">
                    <div class="container my-auto">
                        <div class="copyright text-center my-auto">
                            <span>Copyright &copy; Packing Kayu 2023</span>
                        </div>
                    </div>
                </footer>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const ukuranDefault = document.getElementById('ukuran_default');
            const ukuranCustom = document.getElementById('ukuran_custom');
            const ukuranCustomFields = document.getElementById('ukuran_custom_fields');
            
            ukuranDefault.addEventListener('change', function() {
                if (this.checked) {
                    ukuranCustomFields.style.display = 'none';
                    document.getElementById('panjang').value = 10;
                    document.getElementById('lebar').value = 10;
                    document.getElementById('tinggi').value = 10;
                }
            });
            
            ukuranCustom.addEventListener('change', function() {
                if (this.checked) {
                    ukuranCustomFields.style.display = 'flex';
                }
            });
        });
    </script>
</body>
</html> 