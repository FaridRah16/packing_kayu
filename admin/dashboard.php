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

// Ambil statistik
$query = "SELECT COUNT(*) as total FROM estimasi";
$stmt = $db->prepare($query);
$stmt->execute();
$total_estimasi = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Ambil statistik estimasi terpisah dari order
try {
    $query = "SELECT COUNT(*) as total FROM estimasi WHERE tipe = 'estimasi'";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $total_estimasi_saja = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
    $query = "SELECT COUNT(*) as total FROM estimasi WHERE tipe = 'order'";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $total_order_saja = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
    if ($total_estimasi_saja == 0 && $total_order_saja == 0) {
        // Jika tidak ada pemisahan tipe, semua dianggap sebagai order
        $total_order_saja = $total_estimasi;
        $total_estimasi_saja = 0;
    }
} catch (PDOException $e) {
    // Jika gagal query (misalnya kolom belum tersedia), semua data dianggap sebagai order
    $total_order_saja = $total_estimasi;
    $total_estimasi_saja = 0;
}

// Ambil statistik berdasarkan status
$query = "SELECT COUNT(*) as total FROM estimasi WHERE status = 'pending'";
$stmt = $db->prepare($query);
$stmt->execute();
$estimasi_pending = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

$query = "SELECT COUNT(*) as total FROM estimasi WHERE status = 'diproses'";
$stmt = $db->prepare($query);
$stmt->execute();
$estimasi_diproses = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

$query = "SELECT COUNT(*) as total FROM estimasi WHERE status = 'selesai'";
$stmt = $db->prepare($query);
$stmt->execute();
$estimasi_selesai = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

$query = "SELECT COUNT(*) as total FROM estimasi WHERE status = 'dibatalkan'";
$stmt = $db->prepare($query);
$stmt->execute();
$estimasi_batal = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

// Ambil total pendapatan (hanya dari estimasi dengan status 'selesai')
$query = "SELECT SUM(estimasi_biaya) as total FROM estimasi WHERE status = 'selesai'";
$stmt = $db->prepare($query);
$stmt->execute();
$total_pendapatan = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

$query = "SELECT COUNT(*) as total FROM users WHERE role = 'customer'";
$stmt = $db->prepare($query);
$stmt->execute();
$total_customer = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Ambil data untuk grafik - estimasi per bulan
$query = "SELECT DATE_FORMAT(created_at, '%Y-%m') AS bulan, COUNT(*) as jumlah 
          FROM estimasi 
          WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
          GROUP BY bulan 
          ORDER BY bulan ASC";
$stmt = $db->prepare($query);
$stmt->execute();
$data_bulan = $stmt->fetchAll(PDO::FETCH_ASSOC);

$labels_bulan = [];
$data_jumlah = [];

foreach ($data_bulan as $item) {
    $bulan = explode('-', $item['bulan']);
    $nama_bulan = '';
    switch($bulan[1]) {
        case '01': $nama_bulan = 'Jan'; break;
        case '02': $nama_bulan = 'Feb'; break;
        case '03': $nama_bulan = 'Mar'; break;
        case '04': $nama_bulan = 'Apr'; break;
        case '05': $nama_bulan = 'Mei'; break;
        case '06': $nama_bulan = 'Jun'; break;
        case '07': $nama_bulan = 'Jul'; break;
        case '08': $nama_bulan = 'Ags'; break;
        case '09': $nama_bulan = 'Sep'; break;
        case '10': $nama_bulan = 'Okt'; break;
        case '11': $nama_bulan = 'Nov'; break;
        case '12': $nama_bulan = 'Des'; break;
    }
    $labels_bulan[] = $nama_bulan . ' ' . $bulan[0];
    $data_jumlah[] = $item['jumlah'];
}

// Ambil estimasi terbaru
$query = "SELECT e.*, u.username, jk.nama as jenis_kayu_nama 
          FROM estimasi e 
          LEFT JOIN users u ON e.user_id = u.id 
          LEFT JOIN jenis_kayu jk ON e.jenis_kayu_id = jk.id 
          WHERE (e.tipe = 'order' OR e.tipe IS NULL)
          ORDER BY e.created_at DESC LIMIT 5";
$stmt = $db->prepare($query);
$stmt->execute();
$estimasi_terbaru = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Data untuk donut chart
$status_data = [
    $estimasi_pending ?? 0,
    $estimasi_diproses ?? 0,
    $estimasi_selesai ?? 0,
    $estimasi_batal ?? 0
];

// Pastikan semua posisi status dalam array ada nilainya (minimal 0)
// Ini untuk memastikan chart selalu menampilkan semua status
$status_labels = ['Pending', 'Diproses', 'Selesai', 'Dibatalkan'];
$status_colors = [
    '#f6c23e', // Pending - Kuning
    '#36b9cc', // Diproses - Biru
    '#1cc88a', // Selesai - Hijau
    '#e74a3b'  // Dibatalkan - Merah
];
$status_hover_colors = [
    '#e0ae29', // Pending hover
    '#2c9faf', // Diproses hover
    '#17a673', // Selesai hover
    '#d52a1a'  // Dibatalkan hover
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin - Order Packing Kayu</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
        
        .stats-card {
            position: relative;
            overflow: hidden;
            border-left: 0.25rem solid var(--primary-color);
            border-radius: 0.75rem;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .stats-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 .5rem 2rem 0 rgba(58,59,69,.15);
        }
        
        .stats-card.primary {
            border-left-color: var(--primary-color);
        }
        
        .stats-card.success {
            border-left-color: var(--success-color);
        }
        
        .stats-card.info {
            border-left-color: var(--info-color);
        }
        
        .stats-card.warning {
            border-left-color: var(--warning-color);
        }
        
        .stats-card-body {
            padding: 1.25rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: relative;
            z-index: 2;
        }
        
        .stats-card-icon-container {
            width: 55px;
            height: 55px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            background-color: rgba(255, 255, 255, 0.8);
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
            position: relative;
            z-index: 2;
        }
        
        .stats-card.primary .stats-card-icon-container {
            background: linear-gradient(45deg, rgba(78, 115, 223, 0.1), rgba(78, 115, 223, 0.2));
        }
        
        .stats-card.success .stats-card-icon-container {
            background: linear-gradient(45deg, rgba(28, 200, 138, 0.1), rgba(28, 200, 138, 0.2));
        }
        
        .stats-card.info .stats-card-icon-container {
            background: linear-gradient(45deg, rgba(54, 185, 204, 0.1), rgba(54, 185, 204, 0.2));
        }
        
        .stats-card.warning .stats-card-icon-container {
            background: linear-gradient(45deg, rgba(246, 194, 62, 0.1), rgba(246, 194, 62, 0.2));
        }
        
        .stats-card-icon {
            font-size: 1.8rem;
            opacity: 1;
        }
        
        .stats-card.primary .stats-card-icon {
            color: var(--primary-color);
            filter: drop-shadow(0 2px 4px rgba(78, 115, 223, 0.3));
        }
        
        .stats-card.success .stats-card-icon {
            color: var(--success-color);
            filter: drop-shadow(0 2px 4px rgba(28, 200, 138, 0.3));
        }
        
        .stats-card.info .stats-card-icon {
            color: var(--info-color);
            filter: drop-shadow(0 2px 4px rgba(54, 185, 204, 0.3));
        }
        
        .stats-card.warning .stats-card-icon {
            color: var(--warning-color);
            filter: drop-shadow(0 2px 4px rgba(246, 194, 62, 0.3));
        }
        
        .stats-card-title {
            color: #858796;
            text-transform: uppercase;
            font-size: 0.8rem;
            font-weight: 700;
            margin-bottom: 0.25rem;
        }
        
        .stats-card-value {
            color: #5a5c69;
            font-size: 1.75rem;
            font-weight: 700;
            margin-bottom: 0;
        }
        
        .stats-card::after {
            content: '';
            position: absolute;
            top: -20px;
            right: -20px;
            width: 120px;
            height: 120px;
            border-radius: 50%;
            opacity: 0.1;
            z-index: 1;
        }
        
        .stats-card.primary::after {
            background-color: var(--primary-color);
        }
        
        .stats-card.success::after {
            background-color: var(--success-color);
        }
        
        .stats-card.info::after {
            background-color: var(--info-color);
        }
        
        .stats-card.warning::after {
            background-color: var(--warning-color);
        }
        
        .table-wrapper {
            max-height: 400px;
            overflow-y: auto;
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
        
        .badge {
            padding: 0.5rem 0.75rem;
            font-weight: 600;
            letter-spacing: 0.03rem;
            border-radius: 0.5rem;
        }
        
        .alert {
            border-radius: 0.75rem;
            border: none;
            box-shadow: 0 4px 10px rgba(0,0,0,0.05);
        }
        
        .alert-success {
            background: linear-gradient(to right, rgba(28, 200, 138, 0.15), rgba(28, 200, 138, 0.05));
            border-left: 4px solid #1cc88a;
        }
        
        .alert-danger {
            background: linear-gradient(to right, rgba(231, 74, 59, 0.15), rgba(231, 74, 59, 0.05));
            border-left: 4px solid #e74a3b;
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
        
        .chart-container {
            position: relative;
            margin: auto;
            height: 300px;
        }
        
        .progress {
            height: 1rem;
            border-radius: 0.5rem;
            margin-bottom: 0.5rem;
            box-shadow: inset 0 1px 3px rgba(0,0,0,.1);
        }
        
        .progress-bar {
            border-radius: 0.5rem;
            background-image: linear-gradient(to right, rgba(255,255,255,.1) 0, rgba(255,255,255,.15) 100%);
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
                            <a class="nav-link active" href="dashboard.php">
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
                                <i class="bi bi-rulers"></i> Harga Dimensi & Berat
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="estimasi.php">
                                <i class="bi bi-calculator"></i> Order
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="form_estimasi.php">
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
                    <span class="navbar-brand">Dashboard Admin</span>
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

                <!-- Welcome Banner -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card bg-primary text-white">
                            <div class="card-body py-3">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h4 class="mb-1">Selamat Datang, <?php echo $_SESSION['username']; ?>!</h4>
                                        <p class="mb-0">Dashboard Administrasi Packing Kayu</p>
                                    </div>
                                    <i class="bi bi-clipboard-data display-4 opacity-50"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Statistik -->
                <div class="row">
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card stats-card primary">
                            <div class="stats-card-body">
                                <div>
                                    <div class="stats-card-title">Total Order</div>
                                    <div class="stats-card-value"><?php echo $total_order_saja; ?></div>
                                </div>
                                <div class="stats-card-icon-container">
                                    <i class="bi bi-clipboard-data-fill stats-card-icon"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card stats-card warning">
                            <div class="stats-card-body">
                                <div>
                                    <div class="stats-card-title">Order Pending</div>
                                    <div class="stats-card-value"><?php echo $estimasi_pending; ?></div>
                                </div>
                                <div class="stats-card-icon-container">
                                    <i class="bi bi-hourglass-split stats-card-icon"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card stats-card success">
                            <div class="stats-card-body">
                                <div>
                                    <div class="stats-card-title">Order Selesai</div>
                                    <div class="stats-card-value"><?php echo $estimasi_selesai; ?></div>
                                </div>
                                <div class="stats-card-icon-container">
                                    <i class="bi bi-check2-circle stats-card-icon"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card stats-card danger">
                            <div class="stats-card-body">
                                <div>
                                    <div class="stats-card-title">Order Dibatalkan</div>
                                    <div class="stats-card-value"><?php echo $estimasi_batal; ?></div>
                                </div>
                                <div class="stats-card-icon-container">
                                    <i class="bi bi-x-circle-fill stats-card-icon"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Total Pendapatan -->
                <div class="row">
                    <div class="col-xl-12 col-md-12 mb-4">
                        <div class="card stats-card info">
                            <div class="stats-card-body">
                                <div>
                                    <div class="stats-card-title">Total Pendapatan (Dari Order Status Selesai)</div>
                                    <div class="stats-card-value">Rp. <?php echo number_format($total_pendapatan, 0, ',', '.'); ?></div>
                                </div>
                                <div class="stats-card-icon-container">
                                    <i class="bi bi-cash-stack stats-card-icon"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Grafik -->
                <div class="row">
                    <div class="col-xl-8 col-lg-7">
                        <div class="card">
                            <div class="card-header">
                                <h6 class="m-0 font-weight-bold">Statistik Order</h6>
                                <div class="header-icon">
                                    <i class="bi bi-bar-chart"></i>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="chart-container">
                                    <canvas id="estimasiChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-4 col-lg-5">
                        <div class="card">
                            <div class="card-header">
                                <h6 class="m-0 font-weight-bold">Status Order</h6>
                                <div class="header-icon">
                                    <i class="bi bi-pie-chart"></i>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="chart-container">
                                    <canvas id="statusChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Estimasi Terbaru -->
                <div class="card">
                    <div class="card-header">
                        <h6 class="m-0 font-weight-bold">Order Terbaru</h6>
                        <div class="header-icon">
                            <i class="bi bi-table"></i>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-wrapper">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Kode</th>
                                        <th>Nama Barang</th>
                                        <th>Customer</th>
                                        <th>Jenis Kayu</th>
                                        <th>Estimasi Biaya</th>
                                        <th>Status</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($estimasi_terbaru as $estimasi): ?>
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
                                                    ($estimasi['status'] == 'selesai' ? 'success' : 
                                                    ($estimasi['status'] == 'dibatalkan' ? 'danger' : 'secondary'))); 
                                            ?>">
                                                <?php echo ucfirst($estimasi['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <a href="../hasil_estimasi.php?id=<?php echo $estimasi['id']; ?>" class="btn btn-sm btn-primary">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                            <a href="estimasi.php?id=<?php echo $estimasi['id']; ?>&status=diproses" class="btn btn-sm btn-info">
                                                <i class="bi bi-arrow-right-circle"></i>
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="text-center mt-3">
                            <a href="estimasi.php" class="btn btn-primary">Lihat Semua Order</a>
                            <a href="form_estimasi.php" class="btn btn-success ms-2">Buat Estimasi Baru</a>
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
    <script>
        // Chart untuk Statistik Order
        const ctx = document.getElementById('estimasiChart').getContext('2d');
        const estimasiChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($labels_bulan); ?>,
                datasets: [{
                    label: 'Jumlah Order',
                    data: <?php echo json_encode($data_jumlah); ?>,
                    backgroundColor: 'rgba(78, 115, 223, 0.05)',
                    borderColor: 'rgba(78, 115, 223, 1)',
                    borderWidth: 2,
                    pointBackgroundColor: 'rgba(78, 115, 223, 1)',
                    pointBorderColor: '#fff',
                    pointHoverRadius: 5,
                    pointHoverBackgroundColor: 'rgba(78, 115, 223, 1)',
                    pointHoverBorderColor: '#fff',
                    pointHitRadius: 10,
                    pointBorderWidth: 2,
                    tension: 0.3,
                    fill: true
                }]
            },
            options: {
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(0, 0, 0, 0.05)'
                        },
                        ticks: {
                            precision: 0
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    }
                }
            }
        });
        
        // Chart untuk Status Estimasi
        const statusCtx = document.getElementById('statusChart').getContext('2d');
        const statusChart = new Chart(statusCtx, {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode($status_labels); ?>,
                datasets: [{
                    data: <?php echo json_encode($status_data); ?>,
                    backgroundColor: <?php echo json_encode($status_colors); ?>,
                    hoverBackgroundColor: <?php echo json_encode($status_hover_colors); ?>,
                    hoverBorderColor: 'rgba(234, 236, 244, 1)',
                }]
            },
            options: {
                maintainAspectRatio: false,
                cutout: '70%',
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
    </script>
</body>
</html> 