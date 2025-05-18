<?php
session_start();
require_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        $error = 'Username dan password harus diisi';
    } else {
        $query = "SELECT * FROM users WHERE username = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            
            // Redirect berdasarkan role
            if ($user['role'] == 'admin') {
                header("Location: admin/dashboard.php");
            } else if ($user['role'] == 'owner') {
                header("Location: owner/dashboard.php");
            } else if ($user['role'] == 'staff') {
                header("Location: staff_dashboard.php");
            } else if ($user['role'] == 'keuangan') {
                header("Location: keuangan/dashboard.php");
            } else {
                header("Location: index.php");
            }
            exit();
        } else {
            $error = 'Username atau password salah';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Estimasi Packing Kayu</title>
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
        
        .login-container {
            max-width: 420px;
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
        
        .login-footer {
            text-align: center;
            color: #6c757d;
            margin-top: 2rem;
        }
        
        .login-footer a {
            color: var(--primary-color);
            font-weight: 600;
            text-decoration: none;
            transition: all 0.2s ease;
        }
        
        .login-footer a:hover {
            color: #224abe;
            text-decoration: underline;
        }
        
        .input-group-text {
            background-color: transparent;
            border-color: #e3e6f0;
            color: #6c757d;
            cursor: pointer;
        }
        
        .input-group-text:hover {
            color: var(--primary-color);
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
    <div class="login-container">
        <div class="card">
            <div class="card-header">
                <div class="brand-icon">
                    <i class="bi bi-box-seam"></i>
                </div>
                <h3>Selamat Datang</h3>
                <p>Masuk ke akun Anda untuk melanjutkan</p>
            </div>
            <div class="card-body">
                <?php if ($error): ?>
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i> <?php echo $error; ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="login.php">
                    <div class="form-floating mb-3">
                        <input type="text" class="form-control" id="username" name="username" placeholder="Username" required>
                        <label for="username"><i class="bi bi-person me-2"></i>Username</label>
                    </div>
                    
                    <div class="form-floating mb-4">
                        <input type="password" class="form-control" id="password" name="password" placeholder="Password" required>
                        <label for="password"><i class="bi bi-lock me-2"></i>Password</label>
                    </div>
                    
                    <button type="submit" class="btn btn-primary btn-lg">
                        <i class="bi bi-box-arrow-in-right me-2"></i> Masuk
                    </button>
                </form>
                
                <div class="login-footer">
                    <p>Belum punya akun? <a href="register.php">Daftar sekarang</a></p>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 