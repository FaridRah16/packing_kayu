<?php

require_once 'config/database.php';
require_once 'auth.php';


checkAuth();

$database = new Database();
$db = $database->getConnection();

$user_id = $_SESSION['user_id'];
$message = '';
$error = '';

// Ambil data user
$query = "SELECT * FROM users WHERE id = ?";
$stmt = $db->prepare($query);
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Update profile
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'];
    $email = $_POST['email'];
    $new_password = $_POST['new_password'];
    $current_password = $_POST['current_password'];
    
    // Validasi password lama
    if (password_verify($current_password, $user['password'])) {
        try {
            if (!empty($new_password)) {
                // Update dengan password baru
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $query = "UPDATE users SET username = ?, email = ?, password = ? WHERE id = ?";
                $stmt = $db->prepare($query);
                $stmt->execute([$username, $email, $hashed_password, $user_id]);
            } else {
                // Update tanpa password baru
                $query = "UPDATE users SET username = ?, email = ? WHERE id = ?";
                $stmt = $db->prepare($query);
                $stmt->execute([$username, $email, $user_id]);
            }
            
            // Update session
            $_SESSION['username'] = $username;
            
            $message = "Profil berhasil diperbarui";
            
            // Ambil data terbaru
            $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $error = "Gagal memperbarui profil: " . $e->getMessage();
        }
    } else {
        $error = "Password saat ini salah";
    }
}

// Ambil riwayat estimasi
$query = "SELECT e.*, jk.nama as jenis_kayu_nama 
          FROM estimasi e 
          JOIN jenis_kayu jk ON e.jenis_kayu_id = jk.id 
          WHERE e.user_id = ? 
          ORDER BY e.created_at DESC";
$stmt = $db->prepare($query);
$stmt->execute([$user_id]);
$estimasi_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil Saya - Estimasi Packing Kayu</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <!-- <img src="assets/images/logo.png" alt="Logo" height="40"> -->
                <span class="fw-bold">Packing Kayu</span>
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
                        <a class="nav-link" href="estimasi.php">Estimasi</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="profile.php">Profile</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="logout.php">Logout</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-5">
        <div class="row">
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Profil Saya</h5>
                    </div>
                    <div class="card-body">
                        <?php if ($message): ?>
                            <div class="alert alert-success"><?php echo $message; ?></div>
                        <?php endif; ?>
                        
                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?php echo $error; ?></div>
                        <?php endif; ?>
                        
                        <form method="POST">
                            <div class="mb-3">
                                <label for="username" class="form-label">Username</label>
                                <input type="text" class="form-control" id="username" name="username" value="<?php echo htmlspecialchars($user['username']); ?>" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="new_password" class="form-label">Password Baru</label>
                                <input type="password" class="form-control" id="new_password" name="new_password">
                                <small class="text-muted">Biarkan kosong jika tidak ingin mengganti password</small>
                            </div>
                            
                            <div class="mb-3">
                                <label for="current_password" class="form-label">Password Saat Ini</label>
                                <input type="password" class="form-control" id="current_password" name="current_password" required>
                                <small class="text-muted">Diperlukan untuk mengonfirmasi perubahan</small>
                            </div>
                            
                            <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
                        </form>
                    </div>
                </div>
            </div>
            
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Riwayat Estimasi</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($estimasi_list)): ?>
                            <p class="text-center">Belum ada riwayat estimasi</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Kode</th>
                                            <th>Nama Barang</th>
                                            <th>Jenis Kayu</th>
                                            <th>Estimasi Biaya</th>
                                            <th>Status</th>
                                            <th>Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($estimasi_list as $estimasi): ?>
                                        <tr>
                                            <td><?php echo $estimasi['kode_pesanan']; ?></td>
                                            <td><?php echo htmlspecialchars($estimasi['nama_barang']); ?></td>
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
                                            <td>
                                                <a href="hasil_estimasi.php?id=<?php echo $estimasi['id']; ?>" class="btn btn-sm btn-primary">
                                                    Lihat
                                                </a>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 