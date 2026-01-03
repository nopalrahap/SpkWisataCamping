<?php
session_start();
require_once 'koneksi.php';

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$error = '';
$success = '';

// Handle Login
if (isset($_POST['login'])) {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    
    if (!empty($username) && !empty($password)) {
        $stmt = $conn->prepare("SELECT id, username, password, nama_lengkap FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            if (password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['nama_lengkap'] = $user['nama_lengkap'];
                header('Location: index.php');
                exit;
            } else {
                $error = 'Password salah!';
            }
        } else {
            $error = 'Username tidak ditemukan!';
        }
        $stmt->close();
    } else {
        $error = 'Mohon isi semua field!';
    }
}

// Handle Register
if (isset($_POST['register'])) {
    $username = trim($_POST['reg_username']);
    $password = $_POST['reg_password'];
    $confirm_password = $_POST['reg_confirm_password'];
    $nama_lengkap = trim($_POST['reg_nama_lengkap']);
    $email = trim($_POST['reg_email']);
    
    if (!empty($username) && !empty($password) && !empty($nama_lengkap)) {
        if ($password === $confirm_password) {
            // Check if username exists
            $stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("INSERT INTO users (username, password, nama_lengkap, email) VALUES (?, ?, ?, ?)");
                $stmt->bind_param("ssss", $username, $hashed_password, $nama_lengkap, $email);
                
                if ($stmt->execute()) {
                    $success = 'Registrasi berhasil! Silakan login.';
                } else {
                    $error = 'Gagal registrasi!';
                }
            } else {
                $error = 'Username sudah digunakan!';
            }
            $stmt->close();
        } else {
            $error = 'Password tidak cocok!';
        }
    } else {
        $error = 'Mohon isi semua field!';
    }
}
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Login - Camping Bogor</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #2563eb;
            --primary-dark: #1e40af;
            --secondary: #10b981;
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem 0;
        }
        
        .login-container {
            max-width: 900px;
            width: 100%;
        }
        
        .card-modern {
            background: white;
            border-radius: 20px;
            border: none;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
        }
        
        .login-header {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            padding: 2.5rem;
            text-align: center;
        }
        
        .login-header h2 {
            margin: 0;
            font-weight: 700;
        }
        
        .login-header p {
            margin: 0.5rem 0 0;
            opacity: 0.9;
        }
        
        .form-control-modern {
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            padding: 0.75rem 1rem;
            transition: all 0.3s ease;
        }
        
        .form-control-modern:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
            outline: none;
        }
        
        .btn-modern {
            padding: 0.75rem 1.5rem;
            border-radius: 10px;
            font-weight: 600;
            border: none;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .btn-modern::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: rgba(255,255,255,0.2);
            transition: left 0.3s ease;
        }
        
        .btn-modern:hover::before {
            left: 100%;
        }
        
        .btn-modern:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 15px -3px rgba(0,0,0,0.2);
        }
        
        .btn-primary-modern {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
        }
        
        .btn-success-modern {
            background: linear-gradient(135deg, var(--secondary), #059669);
            color: white;
        }
        
        .nav-tabs-modern {
            border: none;
            justify-content: center;
            margin-bottom: 2rem;
        }
        
        .nav-tabs-modern .nav-link {
            border: none;
            color: #64748b;
            font-weight: 600;
            padding: 1rem 2rem;
            transition: all 0.3s ease;
            position: relative;
        }
        
        .nav-tabs-modern .nav-link:hover {
            color: var(--primary);
        }
        
        .nav-tabs-modern .nav-link.active {
            color: var(--primary);
            background: transparent;
        }
        
        .nav-tabs-modern .nav-link.active::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 60%;
            height: 3px;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            border-radius: 10px;
        }
        
        .alert-modern {
            border: none;
            border-radius: 10px;
            padding: 1rem;
        }
        
        .input-group-modern {
            position: relative;
        }
        
        .input-group-modern i {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            z-index: 10;
        }
        
        .input-group-modern .form-control-modern {
            padding-left: 2.75rem;
        }
        
        .brand-logo {
            width: 80px;
            height: 80px;
            background: white;
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1);
        }
        
        .brand-logo i {
            font-size: 2.5rem;
            color: var(--primary);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="login-container mx-auto">
            <div class="card-modern">
                <div class="login-header">
                    <div class="brand-logo">
                        <i class="fas fa-campground"></i>
                    </div>
                    <h2>Camping Bogor SPK</h2>
                    <p>Sistem Pendukung Keputusan Pemilihan Tempat Camping</p>
                </div>
                
                <div class="card-body p-5">
                    <?php if ($error): ?>
                        <div class="alert alert-danger alert-modern mb-4">
                            <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($success): ?>
                        <div class="alert alert-success alert-modern mb-4">
                            <i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?>
                        </div>
                    <?php endif; ?>
                    
                    <ul class="nav nav-tabs nav-tabs-modern" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#login-tab">
                                <i class="fas fa-sign-in-alt"></i> Login
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#register-tab">
                                <i class="fas fa-user-plus"></i> Register
                            </button>
                        </li>
                    </ul>
                    
                    <div class="tab-content">
                        <!-- Login Tab -->
                        <div class="tab-pane fade show active" id="login-tab">
                            <form method="post">
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Username</label>
                                    <div class="input-group-modern">
                                        <i class="fas fa-user"></i>
                                        <input type="text" name="username" class="form-control form-control-modern" placeholder="Masukkan username" required autofocus>
                                    </div>
                                </div>
                                
                                <div class="mb-4">
                                    <label class="form-label fw-semibold">Password</label>
                                    <div class="input-group-modern">
                                        <i class="fas fa-lock"></i>
                                        <input type="password" name="password" class="form-control form-control-modern" placeholder="Masukkan password" required>
                                    </div>
                                </div>
                                
                                <button type="submit" name="login" class="btn btn-primary-modern btn-modern w-100 mb-3">
                                    <i class="fas fa-sign-in-alt"></i> Login Sekarang
                                </button>
                                
                                <div class="text-center">
                                    <small class="text-muted">
                                        Default: <strong>admin</strong> / <strong>admin123</strong>
                                    </small>
                                </div>
                            </form>
                        </div>
                        
                        <!-- Register Tab -->
                        <div class="tab-pane fade" id="register-tab">
                            <form method="post">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label fw-semibold">Username</label>
                                        <div class="input-group-modern">
                                            <i class="fas fa-user"></i>
                                            <input type="text" name="reg_username" class="form-control form-control-modern" placeholder="Pilih username" required>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label fw-semibold">Nama Lengkap</label>
                                        <div class="input-group-modern">
                                            <i class="fas fa-id-card"></i>
                                            <input type="text" name="reg_nama_lengkap" class="form-control form-control-modern" placeholder="Nama lengkap Anda" required>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Email (Opsional)</label>
                                    <div class="input-group-modern">
                                        <i class="fas fa-envelope"></i>
                                        <input type="email" name="reg_email" class="form-control form-control-modern" placeholder="email@example.com">
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label fw-semibold">Password</label>
                                        <div class="input-group-modern">
                                            <i class="fas fa-lock"></i>
                                            <input type="password" name="reg_password" class="form-control form-control-modern" placeholder="Minimal 6 karakter" required>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label fw-semibold">Konfirmasi Password</label>
                                        <div class="input-group-modern">
                                            <i class="fas fa-lock"></i>
                                            <input type="password" name="reg_confirm_password" class="form-control form-control-modern" placeholder="Ketik ulang password" required>
                                        </div>
                                    </div>
                                </div>
                                
                                <button type="submit" name="register" class="btn btn-success-modern btn-modern w-100">
                                    <i class="fas fa-user-plus"></i> Daftar Sekarang
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="text-center mt-4">
                <p class="text-white mb-0">
                    <i class="fas fa-shield-alt"></i> Sistem Login Terenkripsi dengan Password Hashing
                </p>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>