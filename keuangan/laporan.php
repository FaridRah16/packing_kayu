<?php
require_once '../auth.php';
checkKeuangan();
require_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

// Filter laporan
$start_date = $_GET['start_date'] ?? date('Y-m-01'); // Awal bulan saat ini
$end_date = $_GET['end_date'] ?? date('Y-m-t'); // Akhir bulan saat ini
$status = $_GET['status'] ?? '';

// Query dasar
$params = [
    'start_date' => $start_date . ' 00:00:00',
    'end_date' => $end_date . ' 23:59:59'
];

$query = "SELECT e.*, u.username, jk.nama as jenis_kayu_nama 
          FROM estimasi e 
          LEFT JOIN users u ON e.user_id = u.id 
          LEFT JOIN jenis_kayu jk ON e.jenis_kayu_id = jk.id 
          WHERE e.created_at BETWEEN :start_date AND :end_date";

if (!empty($status)) {
    $query .= " AND e.status = :status";
    $params['status'] = $status;
}

$query .= " ORDER BY e.created_at DESC";

$stmt = $db->prepare($query);
foreach ($params as $key => $value) {
    $stmt->bindValue(":$key", $value);
}
$stmt->execute();
$estimasi_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Hitung total
$total_estimasi = count($estimasi_list);
$total_biaya = 0;
foreach ($estimasi_list as $estimasi) {
    // Hanya tambahkan biaya dari estimasi dengan status 'selesai'
    if ($estimasi['status'] === 'selesai') {
        $total_biaya += $estimasi['estimasi_biaya'];
    }
}

// Hitung per status
$stats = [
    'pending' => 0,
    'diproses' => 0,
    'selesai' => 0,
    'dibatalkan' => 0
];

foreach ($estimasi_list as $estimasi) {
    if (isset($stats[$estimasi['status']])) {
        $stats[$estimasi['status']]++;
    }
}

// Statistik pendapatan per hari
$query = "SELECT DATE(created_at) as tanggal, SUM(estimasi_biaya) as total 
          FROM estimasi 
          WHERE status = 'selesai' 
          AND created_at BETWEEN :start_date AND :end_date 
          GROUP BY DATE(created_at) 
          ORDER BY tanggal ASC";
$stmt = $db->prepare($query);
$stmt->bindParam(':start_date', $params['start_date']);
$stmt->bindParam(':end_date', $params['end_date']);
$stmt->execute();
$pendapatan_harian = $stmt->fetchAll(PDO::FETCH_ASSOC);

$tanggal_array = [];
$pendapatan_array = [];

foreach ($pendapatan_harian as $item) {
    $tanggal_array[] = date('d/m/Y', strtotime($item['tanggal']));
    $pendapatan_array[] = (int)$item['total'];
}

// Statistik per jenis kayu
$query = "SELECT jk.nama as jenis_kayu, COUNT(e.id) as jumlah, SUM(e.estimasi_biaya) as total_pendapatan
          FROM estimasi e
          JOIN jenis_kayu jk ON e.jenis_kayu_id = jk.id
          WHERE e.status = 'selesai' 
          AND e.created_at BETWEEN :start_date AND :end_date 
          GROUP BY jk.nama
          ORDER BY total_pendapatan DESC";
$stmt = $db->prepare($query);
$stmt->bindParam(':start_date', $params['start_date']);
$stmt->bindParam(':end_date', $params['end_date']);
$stmt->execute();
$statistik_kayu = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Data untuk pie chart
$jenis_kayu_labels = [];
$jenis_kayu_data = [];
foreach ($statistik_kayu as $item) {
    $jenis_kayu_labels[] = $item['jenis_kayu'];
    $jenis_kayu_data[] = (int)$item['total_pendapatan'];
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Keuangan - Staff Keuangan Panel</title>
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
        
        .navbar-admin .user-dropdown {
            font-weight: 600;
            color: #5a5c69;
        }
        
        .chart-container {
            position: relative;
            margin: auto;
            height: 300px;
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
        
        /* Style untuk status badge */
        .status-badge-pending {
            background-color: var(--warning-color);
        }
        
        .status-badge-diproses {
            background-color: var(--info-color);
        }
        
        .status-badge-selesai {
            background-color: var(--success-color);
        }
        
        .status-badge-batal {
            background-color: var(--danger-color);
        }
        
        /* Print style */
        @media print {
            .no-print {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <nav class="col-md-3 col-lg-2 d-md-block sidebar collapse no-print">
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
                            <a class="nav-link active" href="laporan.php">
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
                <nav class="navbar navbar-expand-lg navbar-light navbar-admin mb-4 no-print">
                    <button class="navbar-toggler d-md-none collapsed me-3" type="button" data-bs-toggle="collapse" data-bs-target=".sidebar">
                        <span class="navbar-toggler-icon"></span>
                    </button>
                    <span class="navbar-brand">Laporan Keuangan</span>
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
                <div class="row mb-4 no-print">
                    <div class="col-12">
                        <div class="card bg-primary text-white">
                            <div class="card-body py-3">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h4 class="mb-1">Laporan Estimasi</h4>
                                        <p class="mb-0">Lihat dan cetak laporan estimasi packing kayu</p>
                                    </div>
                                    <i class="bi bi-file-earmark-bar-graph display-4 opacity-50"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filter Tanggal dan Status -->
                <div class="card mb-4 no-print">
                    <div class="card-header">
                        <h6 class="m-0 font-weight-bold">Filter Laporan</h6>
                        <div class="header-icon">
                            <i class="bi bi-funnel"></i>
                        </div>
                    </div>
                    <div class="card-body">
                        <form action="" method="GET" class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Tanggal Mulai</label>
                                <div class="input-group date-filter">
                                    <input type="date" class="form-control" name="start_date" value="<?php echo $start_date; ?>">
                                    <span class="input-group-text"><i class="bi bi-calendar"></i></span>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Tanggal Akhir</label>
                                <div class="input-group date-filter">
                                    <input type="date" class="form-control" name="end_date" value="<?php echo $end_date; ?>">
                                    <span class="input-group-text"><i class="bi bi-calendar"></i></span>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Status</label>
                                <select class="form-select" name="status">
                                    <option value="" <?php if(empty($status)) echo 'selected'; ?>>Semua Status</option>
                                    <option value="pending" <?php if($status == 'pending') echo 'selected'; ?>>Pending</option>
                                    <option value="diproses" <?php if($status == 'diproses') echo 'selected'; ?>>Diproses</option>
                                    <option value="selesai" <?php if($status == 'selesai') echo 'selected'; ?>>Selesai</option>
                                    <option value="dibatalkan" <?php if($status == 'dibatalkan') echo 'selected'; ?>>Dibatalkan</option>
                                </select>
                            </div>
                            <div class="col-12">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-filter me-1"></i> Filter
                                </button>
                                <a href="laporan.php" class="btn btn-secondary">
                                    <i class="bi bi-x-circle me-1"></i> Reset
                                </a>
                                <button type="button" class="btn btn-success" onclick="window.print()">
                                    <i class="bi bi-printer me-1"></i> Cetak Laporan
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Print Header -->
                <div class="print-section mb-4">
                    <div class="text-center mb-4">
                        <h3>LAPORAN ESTIMASI PACKING KAYU</h3>
                        <p>Periode: <?php echo date('d/m/Y', strtotime($start_date)) . ' - ' . date('d/m/Y', strtotime($end_date)); ?></p>
                        <?php if (!empty($status)): ?>
                            <p>Status: <?php echo ucfirst($status); ?></p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Statistik -->
                <div class="row">
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card stats-card primary">
                            <div class="stats-card-body">
                                <div>
                                    <div class="stats-card-title">Total Estimasi</div>
                                    <div class="stats-card-value"><?php echo number_format($total_estimasi); ?></div>
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
                                    <div class="stats-card-title">Pending</div>
                                    <div class="stats-card-value"><?php echo number_format($stats['pending']); ?></div>
                                </div>
                                <div class="stats-card-icon-container">
                                    <i class="bi bi-hourglass-split stats-card-icon"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card stats-card info">
                            <div class="stats-card-body">
                                <div>
                                    <div class="stats-card-title">Diproses</div>
                                    <div class="stats-card-value"><?php echo number_format($stats['diproses']); ?></div>
                                </div>
                                <div class="stats-card-icon-container">
                                    <i class="bi bi-gear stats-card-icon"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card stats-card success">
                            <div class="stats-card-body">
                                <div>
                                    <div class="stats-card-title">Selesai</div>
                                    <div class="stats-card-value"><?php echo number_format($stats['selesai']); ?></div>
                                </div>
                                <div class="stats-card-icon-container">
                                    <i class="bi bi-check2-circle stats-card-icon"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Total Biaya -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h5 class="text-muted">Total Estimasi Biaya (Status Selesai)</h5>
                                        <h2 class="mt-2 mb-0 text-primary">Rp <?php echo number_format($total_biaya, 0, ',', '.'); ?></h2>
                                    </div>
                                    <div class="stats-card-icon-container bg-primary bg-opacity-10" style="width: 60px; height: 60px;">
                                        <i class="bi bi-cash-stack text-primary" style="font-size: 2rem;"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Grafik -->
                <div class="row mb-4">
                    <div class="col-xl-8 col-lg-7">
                        <div class="card">
                            <div class="card-header">
                                <h6 class="m-0 font-weight-bold">Pendapatan Harian (Status Selesai)</h6>
                                <div class="header-icon">
                                    <i class="bi bi-bar-chart"></i>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="chart-container">
                                    <canvas id="pendapatanChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-4 col-lg-5">
                        <div class="card">
                            <div class="card-header">
                                <h6 class="m-0 font-weight-bold">Pendapatan Per Jenis Kayu (Status Selesai)</h6>
                                <div class="header-icon">
                                    <i class="bi bi-pie-chart"></i>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="chart-container">
                                    <canvas id="jenisKayuChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Daftar Estimasi -->
                <div class="card">
                    <div class="card-header no-print">
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
                                        <th>No</th>
                                        <th>Kode</th>
                                        <th>Nama Barang</th>
                                        <th>Customer</th>
                                        <th>Jenis Kayu</th>
                                        <th>Tanggal</th>
                                        <th>Estimasi Biaya</th>
                                        <th>Status</th>
                                        <th class="no-print">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if(count($estimasi_list) > 0): ?>
                                        <?php $no = 1; foreach($estimasi_list as $estimasi): ?>
                                            <tr>
                                                <td><?php echo $no++; ?></td>
                                                <td><?php echo $estimasi['kode_pesanan']; ?></td>
                                                <td><?php echo htmlspecialchars($estimasi['nama_barang']); ?></td>
                                                <td><?php echo $estimasi['username'] ?? 'Guest'; ?></td>
                                                <td><?php echo $estimasi['jenis_kayu_nama']; ?></td>
                                                <td><?php echo date('d M Y', strtotime($estimasi['created_at'])); ?></td>
                                                <td>Rp <?php echo number_format($estimasi['estimasi_biaya'], 0, ',', '.'); ?></td>
                                                <td>
                                                    <span class="badge status-badge-<?php 
                                                        echo $estimasi['status'] == 'pending' ? 'pending' : 
                                                            ($estimasi['status'] == 'diproses' ? 'diproses' : 
                                                            ($estimasi['status'] == 'selesai' ? 'selesai' : 'batal')); 
                                                    ?>">
                                                        <?php echo ucfirst($estimasi['status']); ?>
                                                    </span>
                                                </td>
                                                <td class="no-print">
                                                    <a href="../hasil_estimasi.php?id=<?php echo $estimasi['id']; ?>" class="btn btn-info btn-action" title="Lihat Detail">
                                                        <i class="bi bi-eye"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="9" class="text-center py-4">
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
                
                <!-- Detail Pendapatan Per Jenis Kayu -->
                <div class="card mt-4">
                    <div class="card-header">
                        <h6 class="m-0 font-weight-bold">Detail Pendapatan Per Jenis Kayu (Status Selesai)</h6>
                        <div class="header-icon">
                            <i class="bi bi-table"></i>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Jenis Kayu</th>
                                        <th>Jumlah Estimasi</th>
                                        <th>Total Pendapatan</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if(empty($statistik_kayu)): ?>
                                        <tr>
                                            <td colspan="3" class="text-center">Tidak ada data pendapatan untuk periode ini</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach($statistik_kayu as $kayu): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($kayu['jenis_kayu']); ?></td>
                                                <td><?php echo number_format($kayu['jumlah']); ?></td>
                                                <td>Rp <?php echo number_format($kayu['total_pendapatan'], 0, ',', '.'); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                
                <!-- Footer -->
                <footer class="sticky-footer mt-4 mb-2 no-print">
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
        // Chart untuk Pendapatan Harian
        const pendapatanCtx = document.getElementById('pendapatanChart').getContext('2d');
        const pendapatanChart = new Chart(pendapatanCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($tanggal_array); ?>,
                datasets: [{
                    label: 'Pendapatan Harian',
                    data: <?php echo json_encode($pendapatan_array); ?>,
                    backgroundColor: 'rgba(28, 200, 138, 0.05)',
                    borderColor: 'rgba(28, 200, 138, 1)',
                    borderWidth: 2,
                    pointBackgroundColor: 'rgba(28, 200, 138, 1)',
                    pointBorderColor: '#fff',
                    pointHoverRadius: 5,
                    pointHoverBackgroundColor: 'rgba(28, 200, 138, 1)',
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
                            callback: function(value, index, values) {
                                return 'Rp ' + value.toLocaleString('id-ID');
                            }
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
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                let label = context.dataset.label || '';
                                if (label) {
                                    label += ': ';
                                }
                                if (context.parsed.y !== null) {
                                    label += 'Rp ' + context.parsed.y.toLocaleString('id-ID');
                                }
                                return label;
                            }
                        }
                    }
                }
            }
        });
        
        // Chart untuk Pendapatan Per Jenis Kayu
        const jenisKayuCtx = document.getElementById('jenisKayuChart').getContext('2d');
        const jenisKayuChart = new Chart(jenisKayuCtx, {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode($jenis_kayu_labels); ?>,
                datasets: [{
                    data: <?php echo json_encode($jenis_kayu_data); ?>,
                    backgroundColor: [
                        '#4e73df',
                        '#1cc88a',
                        '#36b9cc',
                        '#f6c23e',
                        '#e74a3b',
                        '#5a5c69',
                        '#6610f2',
                        '#fd7e14',
                        '#20c9a6',
                        '#858796'
                    ],
                    hoverOffset: 5
                }]
            },
            options: {
                maintainAspectRatio: false,
                cutout: '70%',
                plugins: {
                    legend: {
                        position: 'bottom'
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                let label = context.label || '';
                                if (label) {
                                    label += ': ';
                                }
                                if (context.raw !== null) {
                                    label += 'Rp ' + context.raw.toLocaleString('id-ID');
                                }
                                return label;
                            }
                        }
                    }
                }
            }
        });
    </script>
</body>
</html> 