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

// Tentukan tipe estimasi dari parameter URL
$tipe_estimasi = isset($_GET['tipe']) ? $_GET['tipe'] : 'normal';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Siapkan kode pesanan
    $kode_pesanan = "ORD" . date('YmdHis') . rand(100, 999);
    
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
    // Jika admin mengedit harga, gunakan harga yang diedit
    if (isset($_POST['edit_harga']) && $_POST['edit_harga'] == 'ya' && isset($_POST['harga_manual'])) {
        $estimasi_biaya = $_POST['harga_manual'];
    } else {
        $estimasi_biaya = $biaya_jenis_kayu + $biaya_dimensi;
    }
    
    if (empty($error)) {
        try {
            // Simpan data estimasi dengan tipe 'order' untuk masuk ke daftar order
            $query = "INSERT INTO estimasi (kode_pesanan, user_id, nama_barang, panjang, lebar, tinggi, berat, volume, jenis_kayu_id, harga_dimensi_id, nomor_whatsapp, estimasi_biaya, tipe, status) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'order', 'pending')";
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
            
            $message = "Order berhasil dibuat dengan kode pesanan: " . $kode_pesanan;
            
            // Redirect ke hasil estimasi
            header("Location: ../hasil_estimasi.php?id=" . $estimasi_id);
            exit();
        } catch (PDOException $e) {
            $error = "Gagal menyimpan order: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Form Estimasi Offline - Admin</title>
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
        
        .main-content {
            padding-top: 1.5rem;
            margin-left: 17% !important;
        }
        
        .sticky-footer {
            padding: 1rem 0;
            margin-top: 2rem;
            background-color: transparent;
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
            
            .table-responsive {
                overflow-x: auto;
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
                            <a class="nav-link active" href="pilihan_estimasi.php">
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
            
            <!-- Main Content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 main-content">
                <nav class="navbar navbar-expand-lg navbar-light navbar-admin mb-4">
                    <button class="navbar-toggler d-md-none collapsed me-3" type="button" data-bs-toggle="collapse" data-bs-target=".sidebar">
                        <span class="navbar-toggler-icon"></span>
                    </button>
                    <span class="navbar-brand">
                        <?php 
                        if($tipe_estimasi == 'cepat') {
                            echo 'Estimasi Cepat';
                        } elseif($tipe_estimasi == 'custom') {
                            echo 'Estimasi Custom';
                        } else {
                            echo 'Form Estimasi Offline';
                        }
                        ?>
                    </span>
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
                
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2 class="mb-0">
                        <?php 
                        if($tipe_estimasi == 'cepat') {
                            echo 'Estimasi Cepat';
                        } elseif($tipe_estimasi == 'custom') {
                            echo 'Estimasi Custom';
                        } else {
                            echo 'Form Estimasi Offline';
                        }
                        ?>
                    </h2>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                            <li class="breadcrumb-item"><a href="estimasi.php">Order</a></li>
                            <li class="breadcrumb-item"><a href="pilihan_estimasi.php">Estimasi</a></li>
                            <li class="breadcrumb-item active" aria-current="page">
                                <?php 
                                if($tipe_estimasi == 'cepat') {
                                    echo 'Estimasi Cepat';
                                } elseif($tipe_estimasi == 'custom') {
                                    echo 'Estimasi Custom';
                                } else {
                                    echo 'Form Estimasi Offline';
                                }
                                ?>
                            </li>
                        </ol>
                    </nav>
                </div>
                
                <?php if(!empty($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i> <?php echo $error; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php endif; ?>
                
                <?php if(!empty($message)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="bi bi-check-circle-fill me-2"></i> <?php echo $message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php endif; ?>
                
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <?php 
                            if($tipe_estimasi == 'cepat') {
                                echo 'Form Estimasi Cepat';
                            } elseif($tipe_estimasi == 'custom') {
                                echo 'Form Estimasi Custom';
                            } else {
                                echo 'Form Estimasi Offline';
                            }
                            ?>
                        </h5>
                        <a href="pilihan_estimasi.php" class="btn btn-sm btn-outline-secondary">
                            <i class="bi bi-arrow-left"></i> Kembali
                        </a>
                    </div>
                    <div class="card-body">
                        <form action="form_estimasi_offline.php<?php echo isset($_GET['tipe']) ? '?tipe='.$_GET['tipe'] : ''; ?>" method="POST" enctype="multipart/form-data">
                            <div class="mb-3">
                                <label for="nama_barang" class="form-label">Nama Barang</label>
                                <input type="text" class="form-control" id="nama_barang" name="nama_barang" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="foto_barang" class="form-label">Foto Barang</label>
                                <input type="file" class="form-control" id="foto_barang" name="foto_barang[]" multiple accept="image/*">
                                <small class="text-muted">Format: JPG, PNG. Maksimal 5MB per file</small>
                            </div>
                            
                            <?php if($tipe_estimasi == 'cepat'): ?>
                            <!-- Untuk estimasi cepat, langsung gunakan ukuran default -->
                            <input type="hidden" name="pilihan_ukuran" value="default">
                            <div class="mb-3">
                                <label for="ukuran_preset" class="form-label">Pilih Ukuran</label>
                                <select class="form-select" id="ukuran_preset" name="ukuran_preset">
                                    <option value="minimum">Minimum (10cm x 10cm x 10cm)</option>
                                    <option value="small" selected>Small (30cm x 20cm x 15cm)</option>
                                    <option value="medium">Medium (50cm x 40cm x 30cm)</option>
                                    <option value="large">Large (100cm x 80cm x 60cm)</option>
                                </select>
                            </div>
                            <?php elseif($tipe_estimasi == 'custom'): ?>
                            <!-- Untuk estimasi custom, langsung gunakan ukuran custom -->
                            <input type="hidden" name="pilihan_ukuran" value="custom">
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="panjang" class="form-label">Panjang (cm)</label>
                                        <input type="number" class="form-control" id="panjang" name="panjang" min="1" required>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="lebar" class="form-label">Lebar (cm)</label>
                                        <input type="number" class="form-control" id="lebar" name="lebar" min="1" required>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="tinggi" class="form-label">Tinggi (cm)</label>
                                        <input type="number" class="form-control" id="tinggi" name="tinggi" min="1" required>
                                    </div>
                                </div>
                            </div>
                            <?php else: ?>
                            <div class="mb-3">
                                <label class="form-label">Pilihan Ukuran</label>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="pilihan_ukuran" id="ukuran_default" value="default" checked>
                                    <label class="form-check-label" for="ukuran_default">
                                        Ukuran Default
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
                                        <option value="small">Small (30cm x 20cm x 15cm)</option>
                                        <option value="medium">Medium (50cm x 40cm x 30cm)</option>
                                        <option value="large">Large (100cm x 80cm x 60cm)</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div id="ukuran_custom_section" style="display:none;">
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label for="panjang" class="form-label">Panjang (cm)</label>
                                            <input type="number" class="form-control" id="panjang" name="panjang" min="1">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label for="lebar" class="form-label">Lebar (cm)</label>
                                            <input type="number" class="form-control" id="lebar" name="lebar" min="1">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label for="tinggi" class="form-label">Tinggi (cm)</label>
                                            <input type="number" class="form-control" id="tinggi" name="tinggi" min="1">
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
                            
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
                                            <?php echo $kayu['nama']; ?> - Rp <?php echo number_format($kayu['harga_per_m3'], 0, ',', '.'); ?>/m³
                                        </option>
                                    <?php endforeach; ?>
                                </select>
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
                            </div>
                            
                            <div class="mb-3">
                                <label for="nomor_whatsapp" class="form-label">Nomor WhatsApp</label>
                                <input type="text" class="form-control" id="nomor_whatsapp" name="nomor_whatsapp" placeholder="8xxxxxxxxxx" required>
                            </div>
                            
                            <div class="mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="edit_harga_check" name="edit_harga" value="ya">
                                    <label class="form-check-label" for="edit_harga_check">
                                        Edit Harga Manual
                                    </label>
                                </div>
                            </div>
                            
                            <div class="mb-3" id="harga_manual_section" style="display:none;">
                                <label for="harga_manual" class="form-label">Harga Manual (Rp)</label>
                                <input type="number" class="form-control" id="harga_manual" name="harga_manual" min="0">
                                <small class="text-muted">Masukkan harga manual jika ingin mengganti hasil perhitungan otomatis.</small>
                            </div>
                            
                            <div class="text-end">
                                <a href="pilihan_estimasi.php" class="btn btn-secondary me-2">
                                    <i class="bi bi-x-circle"></i> Batal
                                </a>
                                <button type="submit" class="btn btn-success">
                                    <i class="bi bi-save"></i> Simpan Order
                                </button>
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
            <?php if($tipe_estimasi != 'cepat' && $tipe_estimasi != 'custom'): ?>
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
            <?php endif; ?>
            
            const editHargaCheck = document.getElementById('edit_harga_check');
            const hargaManualSection = document.getElementById('harga_manual_section');
            
            // Toggle tampilan harga manual
            function toggleHargaManual() {
                if(editHargaCheck.checked) {
                    hargaManualSection.style.display = 'block';
                } else {
                    hargaManualSection.style.display = 'none';
                }
            }
            
            editHargaCheck.addEventListener('change', toggleHargaManual);
            
            // Initialize
            toggleHargaManual();
        });
    </script>
</body>
</html> 