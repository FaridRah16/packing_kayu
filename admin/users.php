<?php
require_once '../auth.php';
checkAdmin();
require_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

$message = '';
$error = '';

// Tambah user
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['tambah'])) {
    $username = $_POST['username'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $email = $_POST['email'];
    $role = $_POST['role'];
    
    try {
        $query = "INSERT INTO users (username, password, email, role) VALUES (:username, :password, :email, :role)";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':username', $username);
        $stmt->bindParam(':password', $password);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':role', $role);
        $stmt->execute();
        $message = "Pengguna berhasil ditambahkan";
    } catch (PDOException $e) {
        $error = "Gagal menambahkan pengguna: " . $e->getMessage();
    }
}

// Edit user
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit'])) {
    $id = $_POST['id'];
    $username = $_POST['username'];
    $email = $_POST['email'];
    $role = $_POST['role'];
    
    try {
        if (!empty($_POST['password'])) {
            $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
            $query = "UPDATE users SET username = :username, password = :password, email = :email, role = :role WHERE id = :id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':password', $password);
        } else {
            $query = "UPDATE users SET username = :username, email = :email, role = :role WHERE id = :id";
            $stmt = $db->prepare($query);
        }
        
        $stmt->bindParam(':id', $id);
        $stmt->bindParam(':username', $username);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':role', $role);
        $stmt->execute();
        $message = "Pengguna berhasil diperbarui";
    } catch (PDOException $e) {
        $error = "Gagal memperbarui pengguna: " . $e->getMessage();
    }
}

// Hapus user
if (isset($_GET['hapus'])) {
    $id = $_GET['hapus'];
    try {
        $query = "DELETE FROM users WHERE id = :id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        $message = "Pengguna berhasil dihapus";
    } catch (PDOException $e) {
        $error = "Gagal menghapus pengguna: " . $e->getMessage();
    }
}

// Ambil data users
$query = "SELECT * FROM users ORDER BY username";
$stmt = $db->prepare($query);
$stmt->execute();
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Pengguna - Admin</title>
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
        
        .modal-content {
            border: none;
            border-radius: 0.75rem;
            box-shadow: 0 .15rem 1.75rem 0 rgba(58,59,69,.15);
            overflow: hidden;
        }
        
        .modal-header {
            background: linear-gradient(40deg, rgba(78, 115, 223, 0.1) 0%, rgba(54, 185, 204, 0.1) 100%);
            border-bottom: 1px solid #e3e6f0;
            padding: 1.25rem 1.5rem;
        }
        
        .modal-title {
            font-weight: 700;
            color: #5a5c69;
            display: flex;
            align-items: center;
        }
        
        .modal-title i {
            color: var(--primary-color);
            margin-right: 0.5rem;
        }
        
        .avatar {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            color: #fff;
            text-transform: uppercase;
            background: linear-gradient(45deg, var(--primary-color), #2e59d9);
            border-radius: 50%;
            box-shadow: 0 3px 6px rgba(0,0,0,0.16);
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
        
        .badge {
            padding: 0.5rem 0.75rem;
            font-weight: 600;
            letter-spacing: 0.03rem;
            border-radius: 0.5rem;
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
                            <a class="nav-link active" href="users.php">
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
                    <span class="navbar-brand">Manajemen Users</span>
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

                <!-- Content Row -->
                <div class="row">
                    <div class="col-md-12">
                        <div class="card">
                            <div class="card-header">
                                <h6 class="m-0 font-weight-bold">Daftar Pengguna</h6>
                                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#tambahModal">
                                    <i class="bi bi-plus-lg me-1"></i> Tambah User
                                </button>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>No.</th>
                                                <th>Username</th>
                                                <th>Email</th>
                                                <th>Role</th>
                                                <th>Terdaftar</th>
                                                <th class="text-end">Aksi</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if(count($users) > 0): ?>
                                                <?php $no = 1; ?>
                                                <?php foreach($users as $user): ?>
                                                    <tr>
                                                        <td><?php echo $no++; ?></td>
                                                        <td>
                                                            <div class="d-flex align-items-center">
                                                                <div class="avatar me-2 bg-primary rounded-circle" style="width: 40px; height: 40px; display: flex; align-items: center; justify-content: center; color: white;">
                                                                    <?php echo strtoupper(substr($user['username'], 0, 1)); ?>
                                                                </div>
                                                                <div>
                                                                    <div class="fw-bold"><?php echo htmlspecialchars($user['username']); ?></div>
                                                                    <div class="small text-muted">User #<?php echo $user['id']; ?></div>
                                                                </div>
                                                            </div>
                                                        </td>
                                                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                                                        <td>
                                                            <span class="badge bg-<?php 
                                                                echo $user['role'] == 'admin' ? 'danger' : 
                                                                    ($user['role'] == 'staff' ? 'info' : 
                                                                    ($user['role'] == 'owner' ? 'warning' : 
                                                                    ($user['role'] == 'kurir' ? 'success' : 
                                                                    ($user['role'] == 'keuangan' ? 'primary' : 'secondary')))); 
                                                            ?>"><?php echo ucfirst($user['role']); ?></span>
                                                        </td>
                                                        <td><?php echo date('d M Y', strtotime($user['created_at'])); ?></td>
                                                        <td class="text-end">
                                                            <button class="btn btn-info btn-action" onclick="editUser(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>', '<?php echo htmlspecialchars($user['email']); ?>', '<?php echo $user['role']; ?>')">
                                                                <i class="bi bi-pencil-square"></i>
                                                            </button>
                                                            <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                                            <a href="?hapus=<?php echo $user['id']; ?>" class="btn btn-danger btn-action" onclick="return confirm('Apakah Anda yakin ingin menghapus user ini?')">
                                                                <i class="bi bi-trash"></i>
                                                            </a>
                                                            <?php endif; ?>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="6" class="text-center">
                                                        <div class="alert alert-info mb-0">
                                                            <i class="bi bi-info-circle me-2"></i> Tidak ada data users
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
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

    <!-- Modal Tambah User -->
    <div class="modal fade" id="tambahModal" tabindex="-1" aria-labelledby="tambahModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="tambahModalLabel"><i class="bi bi-person-plus me-2 text-primary"></i>Tambah User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="" method="POST">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="username" class="form-label">Username</label>
                            <input type="text" class="form-control" id="username" name="username" required>
                        </div>
                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" name="email" required>
                        </div>
                        <div class="mb-3">
                            <label for="password" class="form-label">Password</label>
                            <input type="password" class="form-control" id="password" name="password" required>
                        </div>
                        <div class="mb-3">
                            <label for="role" class="form-label">Role</label>
                            <select class="form-select" id="role" name="role" required>
                                <option value="" selected disabled>Pilih Role</option>
                                <option value="admin">Admin</option>
                                <option value="customer">Customer</option>
                                <option value="staff">Staff</option>
                                <option value="owner">Owner</option>
                                <option value="kurir">Kurir</option>
                                <option value="keuangan">Staff Keuangan</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" name="tambah" class="btn btn-primary">
                            <i class="bi bi-save me-1"></i> Simpan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Edit User -->
    <div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editModalLabel"><i class="bi bi-pencil-square me-2 text-primary"></i>Edit User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="" method="POST">
                    <input type="hidden" name="id" id="edit-id">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="edit-username" class="form-label">Username</label>
                            <input type="text" class="form-control" id="edit-username" name="username" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit-email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="edit-email" name="email" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit-password" class="form-label">Password</label>
                            <input type="password" class="form-control" id="edit-password" name="password">
                            <small class="text-muted">Kosongkan jika tidak ingin mengubah password</small>
                        </div>
                        <div class="mb-3">
                            <label for="edit-role" class="form-label">Role</label>
                            <select class="form-select" id="edit-role" name="role" required>
                                <option value="admin">Admin</option>
                                <option value="customer">Customer</option>
                                <option value="staff">Staff</option>
                                <option value="owner">Owner</option>
                                <option value="kurir">Kurir</option>
                                <option value="keuangan">Staff Keuangan</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" name="edit" class="btn btn-primary">
                            <i class="bi bi-save me-1"></i> Simpan Perubahan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function editUser(id, username, email, role) {
            document.getElementById('edit-id').value = id;
            document.getElementById('edit-username').value = username;
            document.getElementById('edit-email').value = email;
            document.getElementById('edit-role').value = role;
            
            var editModal = new bootstrap.Modal(document.getElementById('editModal'));
            editModal.show();
        }
    </script>
</body>
</html> 