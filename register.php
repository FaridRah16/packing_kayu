<?php
session_start();
require_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'] ?? '';
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    if (empty($username) || empty($email) || empty($password) || empty($confirm_password)) {
        $error = 'Semua field harus diisi';
    } elseif ($password !== $confirm_password) {
        $error = 'Password tidak cocok';
    } else {
        // Cek username sudah ada
        $query = "SELECT id FROM users WHERE username = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$username]);
        if ($stmt->rowCount() > 0) {
            $error = 'Username sudah digunakan';
        } else {
            // Cek email sudah ada
            $query = "SELECT id FROM users WHERE email = ?";
            $stmt = $db->prepare($query);
            $stmt->execute([$email]);
            if ($stmt->rowCount() > 0) {
                $error = 'Email sudah digunakan';
            } else {
                // Hash password
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                
                // Insert user
                $query = "INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, 'customer')";
                $stmt = $db->prepare($query);
                if ($stmt->execute([$username, $email, $hashed_password])) {
                    $success = 'Pendaftaran berhasil. Silakan login.';
                } else {
                    $error = 'Terjadi kesalahan saat mendaftar';
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar - Estimasi Packing Kayu</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        :root {
            --primary-color: #4e73df;
            --success-color: #1cc88a;
            --info-color: #36b9cc;
            --warning-color: #f6c23e;
            --danger-color: #e74a3b;
        }
        
        body {
            background: linear-gradient(135deg, #f8f9fc 0%, #e8eaf6 100%);
            font-family: 'Nunito', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem 0;
        }
        
        .register-container {
            max-width: 480px;
            width: 100%;
            padding: 0 15px;
        }
        
        .card {
            border: none;
            border-radius: 1rem;
            box-shadow: 0 .5rem 1.5rem 0 rgba(58,59,69,.15);
            overflow: hidden;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 .8rem 2rem 0 rgba(58,59,69,.2);
        }
        
        .card-header {
            background: linear-gradient(135deg, #4e73df 0%, #224abe 100%);
            color: white;
            padding: 2rem 1.5rem;
            text-align: center;
            position: relative;
            overflow: hidden;
            border-bottom: none;
        }
        
        .card-header h3 {
            font-weight: 800;
            font-size: 1.8rem;
            position: relative;
            z-index: 2;
            margin-bottom: 0.5rem;
        }
        
        .card-header p {
            opacity: 0.8;
            font-size: 1rem;
            position: relative;
            z-index: 2;
        }
        
        .card-header .brand-icon {
            font-size: 3rem;
            position: relative;
            z-index: 2;
            margin-bottom: 1rem;
            color: rgba(255, 255, 255, 0.9);
        }
        
        .card-header::before {
            content: '';
            position: absolute;
            top: -50px;
            right: -50px;
            width: 150px;
            height: 150px;
            border-radius: 50%;
            background-color: rgba(255, 255, 255, 0.1);
            z-index: 1;
        }
        
        .card-header::after {
            content: '';
            position: absolute;
            bottom: -50px;
            left: -50px;
            width: 150px;
            height: 150px;
            border-radius: 50%;
            background-color: rgba(255, 255, 255, 0.1);
            z-index: 1;
        }
        
        .card-body {
            padding: 2rem;
        }
        
        .form-floating {
            margin-bottom: 1.5rem;
        }
        
        .form-floating label {
            color: #6c757d;
        }
        
        .form-control {
            border-radius: 0.75rem;
            padding: 0.75rem 1rem;
            height: 58px;
            font-size: 1rem;
            border: 1px solid #e3e6f0;
            box-shadow: 0 0 0 0.25rem rgba(78, 115, 223, 0);
            transition: all 0.2s ease;
        }
        
        .form-control:focus {
            border-color: #bac8f3;
            box-shadow: 0 0 0 0.25rem rgba(78, 115, 223, 0.1);
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #4e73df 0%, #3662e0 100%);
            border: none;
            border-radius: 0.75rem;
            padding: 0.75rem 0;
            font-weight: 700;
            font-size: 1rem;
            box-shadow: 0 4px 10px rgba(78, 115, 223, 0.25);
            transition: all 0.2s ease;
            width: 100%;
            margin-top: 0.5rem;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(78, 115, 223, 0.35);
        }
        
        .alert {
            border-radius: 0.75rem;
            border: none;
            margin-bottom: 1.5rem;
            box-shadow: 0 4px 10px rgba(0,0,0,0.05);
        }
        
        .alert-danger {
            background: linear-gradient(to right, rgba(231, 74, 59, 0.15), rgba(231, 74, 59, 0.05));
            border-left: 4px solid #e74a3b;
            color: #702922;
        }
        
        .alert-success {
            background: linear-gradient(to right, rgba(28, 200, 138, 0.15), rgba(28, 200, 138, 0.05));
            border-left: 4px solid #1cc88a;
            color: #105c44;
        }
        
        .register-footer {
            text-align: center;
            color: #6c757d;
            margin-top: 1.5rem;
        }
        
        .register-footer a {
            color: var(--primary-color);
            font-weight: 600;
            text-decoration: none;
            transition: all 0.2s ease;
        }
        
        .register-footer a:hover {
            color: #224abe;
            text-decoration: underline;
        }
        
        @media (max-width: 576px) {
            .card-header {
                padding: 1.5rem 1rem;
            }
            
            .card-body {
                padding: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="register-container">
        <div class="card">
            <div class="card-header">
                <div class="brand-icon">
                    <i class="bi bi-person-plus"></i>
                </div>
                <h3>Buat Akun Baru</h3>
                <p>Daftarkan diri Anda untuk mulai menggunakan layanan kami</p>
            </div>
            <div class="card-body">
                <?php if ($error): ?>
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i> <?php echo $error; ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <i class="bi bi-check-circle-fill me-2"></i> <?php echo $success; ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="register.php">
                    <div class="form-floating">
                        <input type="text" class="form-control" id="username" name="username" placeholder="Username" required>
                        <label for="username"><i class="bi bi-person me-2"></i>Username</label>
                    </div>
                    
                    <div class="form-floating">
                        <input type="email" class="form-control" id="email" name="email" placeholder="Email" required>
                        <label for="email"><i class="bi bi-envelope me-2"></i>Email</label>
                    </div>
                    
                    <div class="form-floating">
                        <input type="password" class="form-control" id="password" name="password" placeholder="Password" required>
                        <label for="password"><i class="bi bi-lock me-2"></i>Password</label>
                    </div>
                    
                    <div class="form-floating mb-4">
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" placeholder="Konfirmasi Password" required>
                        <label for="confirm_password"><i class="bi bi-shield-lock me-2"></i>Konfirmasi Password</label>
                    </div>
                    
                    <button type="submit" class="btn btn-primary btn-lg">
                        <i class="bi bi-check2-circle me-2"></i> Daftar Sekarang
                    </button>
                </form>
                
                <div class="register-footer">
                    <p>Sudah punya akun? <a href="login.php">Masuk disini</a></p>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 