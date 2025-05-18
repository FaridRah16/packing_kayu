<?php
require_once '../auth.php';
checkKeuangan();
require_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

// Statistik total
$query = "SELECT 
          COUNT(*) as total_estimasi,
          SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
          SUM(CASE WHEN status = 'diproses' THEN 1 ELSE 0 END) as diproses,
          SUM(CASE WHEN status = 'selesai' THEN 1 ELSE 0 END) as selesai,
          SUM(CASE WHEN status = 'selesai' THEN estimasi_biaya ELSE 0 END) as total_pendapatan
          FROM estimasi";
$stmt = $db->prepare($query);
$stmt->execute();
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Estimasi terbaru
$query = "SELECT e.*, u.username, jk.nama as jenis_kayu_nama 
          FROM estimasi e 
          LEFT JOIN users u ON e.user_id = u.id 
          LEFT JOIN jenis_kayu jk ON e.jenis_kayu_id = jk.id 
          ORDER BY e.created_at DESC 
          LIMIT 10";
$stmt = $db->prepare($query);
$stmt->execute();
$estimasi_terbaru = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Statistik per bulan (6 bulan terakhir)
$query = "SELECT 
          DATE_FORMAT(created_at, '%Y-%m') as bulan,
          COUNT(*) as jumlah_estimasi,
          SUM(CASE WHEN status = 'selesai' THEN estimasi_biaya ELSE 0 END) as pendapatan
          FROM estimasi 
          WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
          GROUP BY DATE_FORMAT(created_at, '%Y-%m')
          ORDER BY bulan ASC";
$stmt = $db->prepare($query);
$stmt->execute();
$statistik_bulanan = $stmt->fetchAll(PDO::FETCH_ASSOC);

$bulan_labels = [];
$data_estimasi = [];
$data_pendapatan = [];

foreach ($statistik_bulanan as $item) {
    // Convert YYYY-MM to Bulan Tahun format (e.g., "Jan 2023")
    $timestamp = strtotime($item['bulan'] . '-01');
    $bulan_labels[] = date('M Y', $timestamp);
    $data_estimasi[] = (int)$item['jumlah_estimasi'];
    $data_pendapatan[] = (int)$item['pendapatan'];
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Staff Keuangan - Estimasi Packing Kayu</title>
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
                    <span class="navbar-brand">Dashboard Staff Keuangan</span>
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
                
                <!-- Stats cards -->
                <div class="row">
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card stats-card primary">
                            <div class="stats-card-body">
                                <div>
                                    <div class="stats-card-title">Total Estimasi</div>
                                    <div class="stats-card-value"><?php echo number_format($stats['total_estimasi']); ?></div>
                                </div>
                                <div class="stats-card-icon-container">
                                    <i class="bi bi-clipboard-data stats-card-icon"></i>
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
                                    <i class="bi bi-check-circle stats-card-icon"></i>
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
                                    <div class="stats-card-title">Pendapatan</div>
                                    <div class="stats-card-value">Rp <?php echo number_format($stats['total_pendapatan'], 0, ',', '.'); ?></div>
                                </div>
                                <div class="stats-card-icon-container">
                                    <i class="bi bi-cash-stack stats-card-icon"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Charts -->
                <div class="row">
                    <div class="col-xl-8 col-lg-7">
                        <div class="card">
                            <div class="card-header">
                                <h6 class="m-0 font-weight-bold">Statistik 6 Bulan Terakhir</h6>
                                <div class="header-icon">
                                    <i class="bi bi-bar-chart"></i>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="chart-container">
                                    <canvas id="statistikChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-4 col-lg-5">
                        <div class="card">
                            <div class="card-header">
                                <h6 class="m-0 font-weight-bold">Status Estimasi</h6>
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
                        <h6 class="m-0 font-weight-bold">Estimasi Terbaru</h6>
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
                                        <th>Tanggal</th>
                                        <th>Customer</th>
                                        <th>Nama Barang</th>
                                        <th>Jenis Kayu</th>
                                        <th>Status</th>
                                        <th>Estimasi Biaya</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if(count($estimasi_terbaru) > 0): ?>
                                        <?php foreach($estimasi_terbaru as $estimasi): ?>
                                            <tr>
                                                <td><?php echo $estimasi['kode_pesanan']; ?></td>
                                                <td><?php echo date('d/m/Y', strtotime($estimasi['created_at'])); ?></td>
                                                <td><?php echo $estimasi['username'] ?? 'Guest'; ?></td>
                                                <td><?php echo htmlspecialchars($estimasi['nama_barang']); ?></td>
                                                <td><?php echo htmlspecialchars($estimasi['jenis_kayu_nama']); ?></td>
                                                <td>
                                                    <?php
                                                    $status_class = '';
                                                    switch($estimasi['status']) {
                                                        case 'pending':
                                                            $status_class = 'warning';
                                                            break;
                                                        case 'diproses':
                                                            $status_class = 'info';
                                                            break;
                                                        case 'selesai':
                                                            $status_class = 'success';
                                                            break;
                                                        case 'dibatalkan':
                                                            $status_class = 'danger';
                                                            break;
                                                    }
                                                    ?>
                                                    <span class="badge bg-<?php echo $status_class; ?>">
                                                        <?php echo ucfirst($estimasi['status']); ?>
                                                    </span>
                                                </td>
                                                <td>Rp <?php echo number_format($estimasi['estimasi_biaya'], 0, ',', '.'); ?></td>
                                                <td>
                                                    <a href="../hasil_estimasi.php?id=<?php echo $estimasi['id']; ?>" class="btn btn-sm btn-primary btn-action">
                                                        <i class="bi bi-eye"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="8" class="text-center">Tidak ada data estimasi</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Statistik Chart
        const statistikCtx = document.getElementById('statistikChart').getContext('2d');
        const statistikChart = new Chart(statistikCtx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($bulan_labels); ?>,
                datasets: [
                    {
                        label: 'Jumlah Estimasi',
                        data: <?php echo json_encode($data_estimasi); ?>,
                        backgroundColor: 'rgba(78, 115, 223, 0.6)',
                        borderColor: 'rgba(78, 115, 223, 1)',
                        borderWidth: 1,
                        yAxisID: 'y',
                    },
                    {
                        label: 'Pendapatan (Rp)',
                        data: <?php echo json_encode($data_pendapatan); ?>,
                        type: 'line',
                        backgroundColor: 'rgba(28, 200, 138, 0.05)',
                        borderColor: 'rgba(28, 200, 138, 1)',
                        borderWidth: 2,
                        pointBackgroundColor: 'rgba(28, 200, 138, 1)',
                        pointBorderColor: '#fff',
                        pointRadius: 4,
                        pointHoverRadius: 6,
                        fill: true,
                        tension: 0.3,
                        yAxisID: 'y1',
                    }
                ]
            },
            options: {
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        position: 'left',
                        grid: {
                            color: 'rgba(0, 0, 0, 0.05)'
                        },
                        title: {
                            display: true,
                            text: 'Jumlah Estimasi'
                        }
                    },
                    y1: {
                        beginAtZero: true,
                        position: 'right',
                        grid: {
                            display: false,
                        },
                        title: {
                            display: true,
                            text: 'Pendapatan (Rp)'
                        },
                        ticks: {
                            callback: function(value) {
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
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                let label = context.dataset.label || '';
                                if (label) {
                                    label += ': ';
                                }
                                if (context.parsed.y !== null) {
                                    if (context.datasetIndex === 1) {
                                        label += 'Rp ' + context.parsed.y.toLocaleString('id-ID');
                                    } else {
                                        label += context.parsed.y;
                                    }
                                }
                                return label;
                            }
                        }
                    }
                }
            }
        });
        
        // Status Chart
        const statusCtx = document.getElementById('statusChart').getContext('2d');
        const statusChart = new Chart(statusCtx, {
            type: 'pie',
            data: {
                labels: ['Selesai', 'Diproses', 'Pending'],
                datasets: [{
                    data: [
                        <?php echo $stats['selesai']; ?>, 
                        <?php echo $stats['diproses']; ?>, 
                        <?php echo $stats['pending']; ?>
                    ],
                    backgroundColor: [
                        'rgba(28, 200, 138, 0.8)',
                        'rgba(54, 185, 204, 0.8)',
                        'rgba(246, 194, 62, 0.8)'
                    ],
                    borderColor: [
                        'rgba(28, 200, 138, 1)',
                        'rgba(54, 185, 204, 1)',
                        'rgba(246, 194, 62, 1)'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                maintainAspectRatio: false,
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