<?php
require_once '../auth.php';
checkAdmin();
require_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

$message = '';
$error = '';

// Ambil data harga berat yang aktif
$query = "SELECT * FROM harga_berat WHERE is_active = 1 ORDER BY id DESC LIMIT 1";
$stmt = $db->prepare($query);
$stmt->execute();
$harga_berat_aktif = $stmt->fetch(PDO::FETCH_ASSOC);

// Ambil semua data harga berat
$query = "SELECT * FROM harga_berat ORDER BY created_at DESC";
$stmt = $db->prepare($query);
$stmt->execute();
$daftar_harga_berat = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Proses tambah atau update harga berat
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $harga_per_kg = $_POST['harga_per_kg'];
    $keterangan = $_POST['keterangan'];
    
    try {
        $db->beginTransaction();
        
        // Nonaktifkan semua harga berat yang aktif
        $query = "UPDATE harga_berat SET is_active = 0 WHERE is_active = 1";
        $stmt = $db->prepare($query);
        $stmt->execute();
        
        // Tambahkan harga berat baru
        $query = "INSERT INTO harga_berat (harga_per_kg, keterangan, is_active) VALUES (:harga_per_kg, :keterangan, 1)";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':harga_per_kg', $harga_per_kg);
        $stmt->bindParam(':keterangan', $keterangan);
        $stmt->execute();
        
        $db->commit();
        
        $message = "Harga berat berhasil diperbarui";
        
        // Refresh halaman setelah insert
        header("Location: harga_berat.php?success=1");
        exit();
    } catch (PDOException $e) {
        $db->rollBack();
        $error = "Gagal memperbarui harga berat: " . $e->getMessage();
    }
}

// Aktivasi harga berat
if (isset($_GET['activate'])) {
    $id = $_GET['activate'];
    
    try {
        $db->beginTransaction();
        
        // Nonaktifkan semua harga berat
        $query = "UPDATE harga_berat SET is_active = 0";
        $stmt = $db->prepare($query);
        $stmt->execute();
        
        // Aktifkan harga berat yang dipilih
        $query = "UPDATE harga_berat SET is_active = 1 WHERE id = :id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        
        $db->commit();
        
        $message = "Harga berat berhasil diaktifkan";
        
        // Refresh halaman
        header("Location: harga_berat.php?success=1");
        exit();
    } catch (PDOException $e) {
        $db->rollBack();
        $error = "Gagal mengaktifkan harga berat: " . $e->getMessage();
    }
}

// Hapus harga berat
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    
    try {
        // Cek dulu apakah harga berat yang akan dihapus sedang aktif
        $query = "SELECT is_active FROM harga_berat WHERE id = :id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        $is_active = $stmt->fetchColumn();
        
        if ($is_active) {
            $error = "Tidak dapat menghapus harga berat yang sedang aktif";
        } else {
            $query = "DELETE FROM harga_berat WHERE id = :id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':id', $id);
            $stmt->execute();
            
            $message = "Harga berat berhasil dihapus";
            
            // Refresh halaman
            header("Location: harga_berat.php?success=1");
            exit();
        }
    } catch (PDOException $e) {
        $error = "Gagal menghapus harga berat: " . $e->getMessage();
    }
}

if (isset($_GET['success'])) {
    $message = "Operasi berhasil dilakukan";
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Harga Berat - Admin</title>
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
        
        .profile-dropdown img {
            height: 2rem;
            width: 2rem;
            border-radius: 50%;
        }
        
        .dropdown-toggle {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .table {
            border-collapse: separate;
            border-spacing: 0;
        }
        
        .table th {
            background-color: #f8f9fc;
            font-weight: 700;
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 0.05rem;
            color: #5a5c69;
            padding: 1rem;
            border-bottom: 2px solid #e3e6f0;
        }
        
        .table td {
            vertical-align: middle;
            padding: 1rem;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .table tr {
            transition: all 0.2s ease;
        }
        
        .table tr:hover {
            background-color: rgba(78, 115, 223, 0.05);
            transform: scale(1.01);
        }
        
        .table-hover > tbody > tr:hover {
            background-color: rgba(78, 115, 223, 0.05);
        }
        
        .badge {
            padding: 0.5rem 0.75rem;
            font-weight: 600;
            letter-spacing: 0.03rem;
            border-radius: 0.5rem;
        }
        
        .btn-action {
            padding: 0.35rem 0.65rem;
            border-radius: 0.5rem;
            margin-right: 0.35rem;
            transition: all 0.2s ease;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        .btn-action:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .btn-action.btn-warning {
            background: linear-gradient(40deg, #f6c23e 0%, #f8d35e 100%);
            border: none;
        }
        
        .btn-action.btn-danger {
            background: linear-gradient(40deg, #e74a3b 0%, #f86b5e 100%);
            border: none;
        }
        
        .btn-action.btn-info {
            background: linear-gradient(40deg, #36b9cc 0%, #4dd4e9 100%);
            border: none;
            color: white;
        }
        
        .btn-action.btn-success {
            background: linear-gradient(40deg, #1cc88a 0%, #2ee89c 100%);
            border: none;
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
        
        .btn-secondary {
            background: linear-gradient(40deg, #858796 0%, #6e707a 100%);
            border: none;
            box-shadow: 0 4px 7px rgba(133, 135, 150, 0.2);
        }
        
        .btn-secondary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(133, 135, 150, 0.3);
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
        
        .main-content {
            padding-top: 1.5rem;
            margin-left: 17% !important;
        }
        
        .sticky-footer {
            padding: 1rem 0;
            margin-top: 2rem;
            background-color: transparent;
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
                            <a class="nav-link active" href="harga_berat.php">
                                <i class="bi bi-weight"></i> Harga Berat
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="estimasi.php">
                                <i class="bi bi-calculator"></i> Estimasi
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
                    <span class="navbar-brand">Kelola Harga Berat</span>
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

                <!-- Tambah Harga Berat -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h6 class="m-0 font-weight-bold">Tambah Harga Berat</h6>
                        <div class="header-icon">
                            <i class="bi bi-weight"></i>
                        </div>
                    </div>
                    <div class="card-body">
                        <form action="" method="POST">
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="harga_per_kg" class="form-label">Harga per Kilogram (Rp)</label>
                                    <input type="number" class="form-control" id="harga_per_kg" name="harga_per_kg" required>
                                    <small class="text-muted">Harga akan digunakan untuk menghitung estimasi berdasarkan berat barang</small>
                                </div>
                                <div class="col-md-6">
                                    <label for="keterangan" class="form-label">Keterangan</label>
                                    <input type="text" class="form-control" id="keterangan" name="keterangan" placeholder="Misalnya: Harga berat per Juli 2023">
                                </div>
                            </div>
                            
                            <div class="d-flex justify-content-end">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-save me-2"></i> Simpan
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Info Harga Berat Aktif -->
                <?php if($harga_berat_aktif): ?>
                <div class="card mb-4 border-left-success">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col-auto">
                                <div class="icon-circle bg-success text-white">
                                    <i class="bi bi-check-lg"></i>
                                </div>
                            </div>
                            <div class="col">
                                <div class="text-xs text-uppercase mb-1 fw-bold text-success">Harga Berat Aktif</div>
                                <div class="h5 mb-0 fw-bold">Rp <?php echo number_format($harga_berat_aktif['harga_per_kg'], 0, ',', '.'); ?> per kilogram</div>
                                <div class="small text-muted"><?php echo $harga_berat_aktif['keterangan']; ?> (Diperbarui: <?php echo date('d M Y', strtotime($harga_berat_aktif['created_at'])); ?>)</div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php else: ?>
                <div class="alert alert-warning">
                    <i class="bi bi-exclamation-triangle me-2"></i> Belum ada harga berat yang aktif. Silakan tambahkan harga berat baru.
                </div>
                <?php endif; ?>

                <!-- Daftar Harga Berat -->
                <div class="card">
                    <div class="card-header">
                        <h6 class="m-0 font-weight-bold">Riwayat Harga Berat</h6>
                        <div class="header-icon">
                            <i class="bi bi-clock-history"></i>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>No.</th>
                                        <th>Harga per KG</th>
                                        <th>Keterangan</th>
                                        <th>Tanggal Dibuat</th>
                                        <th>Status</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if(count($daftar_harga_berat) > 0): ?>
                                        <?php $no = 1; foreach($daftar_harga_berat as $harga): ?>
                                            <tr>
                                                <td><?php echo $no++; ?></td>
                                                <td>Rp <?php echo number_format($harga['harga_per_kg'], 0, ',', '.'); ?></td>
                                                <td><?php echo $harga['keterangan']; ?></td>
                                                <td><?php echo date('d M Y H:i', strtotime($harga['created_at'])); ?></td>
                                                <td>
                                                    <?php if($harga['is_active']): ?>
                                                        <span class="badge bg-success">Aktif</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary">Tidak Aktif</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if(!$harga['is_active']): ?>
                                                        <a href="?activate=<?php echo $harga['id']; ?>" class="btn btn-sm btn-success btn-action" title="Aktifkan">
                                                            <i class="bi bi-check-circle"></i>
                                                        </a>
                                                        <a href="?delete=<?php echo $harga['id']; ?>" class="btn btn-sm btn-danger btn-action" title="Hapus" onclick="return confirm('Yakin ingin menghapus data ini?')">
                                                            <i class="bi bi-trash"></i>
                                                        </a>
                                                    <?php else: ?>
                                                        <button class="btn btn-sm btn-secondary" disabled>
                                                            <i class="bi bi-check-circle-fill"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="6" class="text-center py-4">
                                                <div class="alert alert-info mb-0">
                                                    <i class="bi bi-info-circle me-2"></i> Belum ada data harga berat
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
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
</body>
</html> 