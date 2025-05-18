<?php
require_once 'auth.php';
checkRole(['staff']);
require_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();

$message = '';
$error = '';

// Update status estimasi
if (isset($_GET['id']) && isset($_GET['status'])) {
    $id = $_GET['id'];
    $status = $_GET['status'];
    
    // Staff hanya bisa mengubah status menjadi batal
    if ($status === 'batal') {
        try {
            $query = "UPDATE estimasi SET status = :status WHERE id = :id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':id', $id);
            $stmt->bindParam(':status', $status);
            $stmt->execute();
            $message = "Status estimasi berhasil diperbarui";
        } catch (PDOException $e) {
            $error = "Gagal memperbarui status: " . $e->getMessage();
        }
    } else {
        $error = "Staff hanya bisa membatalkan estimasi";
    }
}

// Filter dan pencarian
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status_filter'] ?? '';

$query = "SELECT e.*, u.username, jk.nama as jenis_kayu_nama 
          FROM estimasi e 
          LEFT JOIN users u ON e.user_id = u.id 
          LEFT JOIN jenis_kayu jk ON e.jenis_kayu_id = jk.id 
          WHERE 1=1";

$params = [];

if (!empty($search)) {
    $query .= " AND (e.kode_pesanan LIKE :search OR e.nama_barang LIKE :search OR u.username LIKE :search)";
    $params['search'] = "%$search%";
}

if (!empty($status_filter)) {
    $query .= " AND e.status = :status";
    $params['status'] = $status_filter;
}

$query .= " ORDER BY e.created_at DESC";

$stmt = $db->prepare($query);
foreach ($params as $key => $value) {
    $stmt->bindValue(":$key", $value);
}
$stmt->execute();
$estimasi_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Estimasi - Staff</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/style.css">
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
        
        .status-badge-pending {
            background-color: var(--warning-color);
            color: #fff;
        }

        .status-badge-diproses {
            background-color: var(--info-color);
            color: #fff;
        }

        .status-badge-selesai {
            background-color: var(--success-color);
            color: #fff;
        }

        .status-badge-batal {
            background-color: var(--danger-color);
            color: #fff;
        }
        
        .search-form .input-group {
            border-radius: 0.5rem;
            overflow: hidden;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
            transition: all 0.3s ease;
        }
        
        .search-form .input-group:focus-within {
            box-shadow: 0 5px 15px rgba(78, 115, 223, 0.1);
        }
        
        .search-form .form-control {
            border-right: none;
        }
        
        .search-form .btn {
            border-left: none;
            background-color: #fff;
            color: var(--primary-color);
            border-color: #eaecf4;
        }
        
        .filter-form .form-select {
            border-radius: 0.5rem;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
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
                            <a class="nav-link" href="staff_dashboard.php">
                                <i class="bi bi-speedometer2"></i> Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="staff_estimasi.php">
                                <i class="bi bi-calculator"></i> Estimasi
                            </a>
                        </li>
                        <li class="nav-item mt-3">
                            <a class="nav-link" href="logout.php">
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
                    <span class="navbar-brand">Kelola Estimasi</span>
                    <div class="ms-auto">
                        <div class="dropdown profile-dropdown">
                            <button class="btn dropdown-toggle user-dropdown" type="button" data-bs-toggle="dropdown">
                                <span class="d-none d-md-inline"><?php echo $_SESSION['username']; ?></span>
                                <i class="bi bi-person-circle"></i>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li><a class="dropdown-item" href="profile.php"><i class="bi bi-person me-2"></i> Profil</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="logout.php"><i class="bi bi-box-arrow-right me-2"></i> Logout</a></li>
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

                <!-- Search and Filter -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h6 class="m-0 font-weight-bold">Filter & Pencarian</h6>
                        <div class="header-icon">
                            <i class="bi bi-funnel"></i>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-8">
                                <form action="" method="GET" class="search-form">
                                    <div class="input-group">
                                        <input type="text" class="form-control" placeholder="Cari kode pesanan, nama barang atau username..." name="search" value="<?php echo htmlspecialchars($search); ?>">
                                        <button class="btn" type="submit">
                                            <i class="bi bi-search"></i>
                                        </button>
                                    </div>
                                </form>
                            </div>
                            <div class="col-md-4">
                                <form action="" method="GET" class="filter-form">
                                    <div class="d-flex">
                                        <select class="form-select me-2" name="status_filter" onchange="this.form.submit()">
                                            <option value="" <?php if(empty($status_filter)) echo 'selected'; ?>>Semua Status</option>
                                            <option value="pending" <?php if($status_filter == 'pending') echo 'selected'; ?>>Pending</option>
                                            <option value="diproses" <?php if($status_filter == 'diproses') echo 'selected'; ?>>Diproses</option>
                                            <option value="selesai" <?php if($status_filter == 'selesai') echo 'selected'; ?>>Selesai</option>
                                            <option value="batal" <?php if($status_filter == 'batal') echo 'selected'; ?>>Dibatalkan</option>
                                        </select>
                                        <button type="submit" class="btn btn-primary px-4">
                                            <i class="bi bi-filter"></i>
                                        </button>
                                    </div>
                                    <?php if(!empty($search)): ?>
                                        <input type="hidden" name="search" value="<?php echo htmlspecialchars($search); ?>">
                                    <?php endif; ?>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Daftar Estimasi -->
                <div class="card">
                    <div class="card-header">
                        <h6 class="m-0 font-weight-bold">Daftar Estimasi</h6>
                        <div class="header-icon">
                            <i class="bi bi-clipboard-data"></i>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Kode</th>
                                        <th>Nama Barang</th>
                                        <th>Customer</th>
                                        <th>Jenis Kayu</th>
                                        <th>Tanggal</th>
                                        <th>Estimasi Biaya</th>
                                        <th>Status</th>
                                        <th class="text-end">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if(count($estimasi_list) > 0): ?>
                                        <?php foreach($estimasi_list as $estimasi): ?>
                                            <tr>
                                                <td><strong><?php echo $estimasi['kode_pesanan']; ?></strong></td>
                                                <td><?php echo htmlspecialchars($estimasi['nama_barang']); ?></td>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <div class="avatar me-2 bg-primary rounded-circle" style="width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; color: white;">
                                                            <?php echo strtoupper(substr($estimasi['username'] ?? 'G', 0, 1)); ?>
                                                        </div>
                                                        <?php echo $estimasi['username'] ?? 'Guest'; ?>
                                                    </div>
                                                </td>
                                                <td><?php echo $estimasi['jenis_kayu_nama']; ?></td>
                                                <td><?php echo date('d M Y', strtotime($estimasi['created_at'])); ?></td>
                                                <td><span class="badge bg-primary">Rp <?php echo number_format($estimasi['estimasi_biaya'], 0, ',', '.'); ?></span></td>
                                                <td>
                                                    <span class="badge status-badge-<?php 
                                                        echo $estimasi['status'] == 'pending' ? 'pending' : 
                                                            ($estimasi['status'] == 'diproses' ? 'diproses' : 
                                                            ($estimasi['status'] == 'selesai' ? 'selesai' : 'batal')); 
                                                    ?>">
                                                        <?php echo ucfirst($estimasi['status']); ?>
                                                    </span>
                                                </td>
                                                <td class="text-end">
                                                    <div class="btn-group">
                                                        <a href="hasil_estimasi.php?id=<?php echo $estimasi['id']; ?>" class="btn btn-info btn-action" title="Lihat Detail">
                                                            <i class="bi bi-eye"></i>
                                                        </a>
                                                        <?php if($estimasi['status'] == 'pending' || $estimasi['status'] == 'diproses'): ?>
                                                        <a href="?id=<?php echo $estimasi['id']; ?>&status=batal" class="btn btn-danger btn-action" title="Batalkan Estimasi" onclick="return confirm('Yakin ingin membatalkan estimasi ini?')">
                                                            <i class="bi bi-x-circle"></i>
                                                        </a>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="8" class="text-center py-4">
                                                <div class="alert alert-info mb-0">
                                                    <i class="bi bi-info-circle me-2"></i> Tidak ada data estimasi yang ditemukan
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