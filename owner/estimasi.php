<?php
require_once '../auth.php';
checkOwner();
require_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

// Filter
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Query dasar
$base_query = "FROM estimasi e 
               LEFT JOIN users u ON e.user_id = u.id 
               LEFT JOIN jenis_kayu jk ON e.jenis_kayu_id = jk.id 
               WHERE 1=1";

// Tambahkan filter jika ada
if (!empty($status_filter)) {
    $base_query .= " AND e.status = :status";
}

if (!empty($search)) {
    $base_query .= " AND (e.kode_pesanan LIKE :search OR e.nama_barang LIKE :search OR u.username LIKE :search)";
}

// Query untuk hitung total
$count_query = "SELECT COUNT(*) as total " . $base_query;
$count_stmt = $db->prepare($count_query);

if (!empty($status_filter)) {
    $count_stmt->bindParam(':status', $status_filter);
}

if (!empty($search)) {
    $search_param = "%$search%";
    $count_stmt->bindParam(':search', $search_param);
}

$count_stmt->execute();
$total_rows = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($total_rows / $per_page);

// Query untuk ambil data
$query = "SELECT e.*, u.username, jk.nama as jenis_kayu_nama " . $base_query . " ORDER BY e.created_at DESC LIMIT :offset, :limit";
$stmt = $db->prepare($query);

if (!empty($status_filter)) {
    $stmt->bindParam(':status', $status_filter);
}

if (!empty($search)) {
    $search_param = "%$search%";
    $stmt->bindParam(':search', $search_param);
}

$stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
$stmt->bindParam(':limit', $per_page, PDO::PARAM_INT);
$stmt->execute();
$estimasi_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Estimasi - Owner Panel</title>
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
        
        .navbar-admin .dropdown-toggle::after {
            display: none;
        }
        
        .dropdown-menu {
            box-shadow: 0 .15rem 1.75rem 0 rgba(58,59,69,.15);
            border: none;
            border-radius: 0.5rem;
            overflow: hidden;
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
        }
        
        .main-content {
            padding-top: 1.5rem;
            margin-left: 17% !important;
        }
        
        .filter-form .form-control {
            border-radius: 0.5rem;
            font-size: 0.9rem;
            padding: 0.75rem 1.25rem;
            box-shadow: 0 0.125rem 0.25rem rgba(58, 59, 69, 0.15);
        }
        
        .filter-form .btn {
            border-radius: 0.5rem;
            font-size: 0.9rem;
            padding: 0.75rem 1.25rem;
            box-shadow: 0 0.125rem 0.25rem rgba(58, 59, 69, 0.15);
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            gap: 0.5rem;
            margin-top: 1.5rem;
        }
        
        .pagination .page-item .page-link {
            border-radius: 0.5rem;
            font-size: 0.9rem;
            padding: 0.5rem 0.9rem;
            color: var(--primary-color);
            background-color: #fff;
            border: 1px solid #e3e6f0;
            transition: all 0.2s;
        }
        
        .pagination .page-item.active .page-link {
            background: linear-gradient(40deg, #4e73df 0%, #3662e0 100%);
            border-color: #4e73df;
            color: #fff;
            font-weight: 600;
            box-shadow: 0 4px 10px rgba(78, 115, 223, 0.2);
        }
        
        .pagination .page-item .page-link:hover {
            background-color: #f8f9fc;
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(78, 115, 223, 0.1);
        }
        
        .pagination .page-item.active .page-link:hover {
            background: linear-gradient(40deg, #3662e0 0%, #2850bf 100%);
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
                            <a class="nav-link" href="laporan.php">
                                <i class="bi bi-file-earmark-text"></i> Laporan
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="estimasi.php">
                                <i class="bi bi-calculator"></i> Estimasi
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
                <!-- Navbar -->
                <nav class="navbar navbar-expand-lg navbar-light navbar-admin mb-4">
                    <button class="navbar-toggler d-md-none collapsed me-3" type="button" data-bs-toggle="collapse" data-bs-target=".sidebar">
                        <span class="navbar-toggler-icon"></span>
                    </button>
                    <span class="navbar-brand">Data Estimasi</span>
                    <div class="ms-auto">
                        <div class="dropdown profile-dropdown">
                            <button class="btn dropdown-toggle" type="button" data-bs-toggle="dropdown">
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

                <div class="card">
                    <div class="card-header">
                        <h6 class="m-0 font-weight-bold">Filter Estimasi</h6>
                    </div>
                    <div class="card-body">
                        <form class="row filter-form" method="get">
                            <div class="col-md-4 mb-2">
                                <input type="text" class="form-control" name="search" placeholder="Cari kode/nama barang/customer..." value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                            <div class="col-md-4 mb-2">
                                <select class="form-control" name="status">
                                    <option value="">Semua Status</option>
                                    <option value="pending" <?php if($status_filter == 'pending') echo 'selected'; ?>>Pending</option>
                                    <option value="diproses" <?php if($status_filter == 'diproses') echo 'selected'; ?>>Diproses</option>
                                    <option value="selesai" <?php if($status_filter == 'selesai') echo 'selected'; ?>>Selesai</option>
                                    <option value="batal" <?php if($status_filter == 'batal') echo 'selected'; ?>>Batal</option>
                                </select>
                            </div>
                            <div class="col-md-4 mb-2">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="bi bi-search me-1"></i> Filter
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h6 class="m-0 font-weight-bold">Daftar Estimasi</h6>
                        <div class="header-icon">
                            <i class="bi bi-table"></i>
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
                                        <th>Estimasi Biaya</th>
                                        <th>Status</th>
                                        <th>Tanggal</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if(empty($estimasi_list)): ?>
                                        <tr>
                                            <td colspan="8" class="text-center">Tidak ada data estimasi</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach($estimasi_list as $estimasi): ?>
                                            <tr>
                                                <td><?php echo $estimasi['kode_pesanan']; ?></td>
                                                <td><?php echo htmlspecialchars($estimasi['nama_barang']); ?></td>
                                                <td><?php echo $estimasi['username'] ?? 'Guest'; ?></td>
                                                <td><?php echo $estimasi['jenis_kayu_nama']; ?></td>
                                                <td>Rp <?php echo number_format($estimasi['estimasi_biaya'], 0, ',', '.'); ?></td>
                                                <td>
                                                    <span class="badge bg-<?php 
                                                        echo $estimasi['status'] == 'pending' ? 'warning' : 
                                                            ($estimasi['status'] == 'diproses' ? 'info' : 
                                                            ($estimasi['status'] == 'selesai' ? 'success' : 'danger')); 
                                                    ?>">
                                                        <?php echo ucfirst($estimasi['status']); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo date('d/m/Y H:i', strtotime($estimasi['created_at'])); ?></td>
                                                <td>
                                                    <a href="../hasil_estimasi.php?id=<?php echo $estimasi['id']; ?>" class="btn btn-sm btn-primary btn-action">
                                                        <i class="bi bi-eye"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Pagination -->
                        <?php if($total_pages > 1): ?>
                            <nav>
                                <ul class="pagination">
                                    <?php if($page > 1): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?page=<?php echo $page - 1; ?>&status=<?php echo urlencode($status_filter); ?>&search=<?php echo urlencode($search); ?>">
                                                <i class="bi bi-chevron-left"></i>
                                            </a>
                                        </li>
                                    <?php endif; ?>
                                    
                                    <?php
                                    $start_page = max(1, $page - 2);
                                    $end_page = min($start_page + 4, $total_pages);
                                    if($end_page - $start_page < 4) {
                                        $start_page = max(1, $end_page - 4);
                                    }
                                    ?>
                                    
                                    <?php for($i = $start_page; $i <= $end_page; $i++): ?>
                                        <li class="page-item <?php if($i == $page) echo 'active'; ?>">
                                            <a class="page-link" href="?page=<?php echo $i; ?>&status=<?php echo urlencode($status_filter); ?>&search=<?php echo urlencode($search); ?>">
                                                <?php echo $i; ?>
                                            </a>
                                        </li>
                                    <?php endfor; ?>
                                    
                                    <?php if($page < $total_pages): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?page=<?php echo $page + 1; ?>&status=<?php echo urlencode($status_filter); ?>&search=<?php echo urlencode($search); ?>">
                                                <i class="bi bi-chevron-right"></i>
                                            </a>
                                        </li>
                                    <?php endif; ?>
                                </ul>
                            </nav>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>