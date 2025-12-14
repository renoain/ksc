<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

$auth = new Auth();
$error = '';
$success = '';

if($auth->isLoggedIn()) {
    redirect('index.php');
}

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nama = trim($_POST['nama']);
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $no_hp = trim($_POST['no_hp']);
    $password = trim($_POST['password']);
    $confirm_password = trim($_POST['confirm_password']);
    
    // Validasi
    if(empty($nama) || empty($username) || empty($email) || empty($password)) {
        $error = 'Semua field harus diisi!';
    } elseif($password !== $confirm_password) {
        $error = 'Password dan konfirmasi password tidak sama!';
    } elseif($auth->checkUsernameExists($username)) {
        $error = 'Username sudah digunakan!';
    } elseif($auth->checkEmailExists($email)) {
        $error = 'Email sudah terdaftar!';
    } else {
        $data = [
            'nama' => $nama,
            'username' => $username,
            'email' => $email,
            'no_hp' => $no_hp,
            'password' => $password
        ];
        
        if($auth->register($data)) {
            $success = 'Pendaftaran berhasil! Silakan login.';
        } else {
            $error = 'Terjadi kesalahan. Silakan coba lagi.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - KSC</title>
    <link rel="stylesheet" href="assets/css/user/user-style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .register-container {
            max-width: 500px;
            margin: 50px auto;
            padding: 30px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .register-header {
            text-align: center;
            margin-bottom: 30px;
        }
        .register-header h2 {
            color: #2c3e50;
        }
        .form-row {
            display: flex;
            gap: 15px;
            margin-bottom: 15px;
        }
        .form-group {
            flex: 1;
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            color: #34495e;
            font-weight: 500;
        }
        .form-group input {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
        }
        .btn-register {
            width: 100%;
            padding: 12px;
            background: #2ecc71;
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            cursor: pointer;
            transition: background 0.3s;
        }
        .btn-register:hover {
            background: #27ae60;
        }
        .error {
            background: #e74c3c;
            color: white;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
            text-align: center;
        }
        .success {
            background: #2ecc71;
            color: white;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
            text-align: center;
        }
        .login-link {
            text-align: center;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="container">
            <div class="logo">
                <h1><a href="index.php">KSC</a></h1>
                <span>Komplek Sport Center</span>
            </div>
        </div>
    </nav>

    <div class="container">
        <div class="register-container">
            <div class="register-header">
                <h2><i class="fas fa-user-plus"></i> Daftar Akun Baru</h2>
                <p>Bergabung dengan KSC</p>
            </div>
            
            <?php if($error): ?>
                <div class="error">
                    <?= $error ?>
                </div>
            <?php endif; ?>
            
            <?php if($success): ?>
                <div class="success">
                    <?= $success ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="form-row">
                    <div class="form-group">
                        <label><i class="fas fa-user"></i> Nama Lengkap</label>
                        <input type="text" name="nama" required placeholder="Masukkan nama lengkap">
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-at"></i> Username</label>
                        <input type="text" name="username" required placeholder="Masukkan username">
                    </div>
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-envelope"></i> Email</label>
                    <input type="email" name="email" required placeholder="Masukkan email">
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-phone"></i> No. HP</label>
                    <input type="text" name="no_hp" placeholder="Masukkan nomor HP">
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label><i class="fas fa-lock"></i> Password</label>
                        <input type="password" name="password" required placeholder="Masukkan password">
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-lock"></i> Konfirmasi Password</label>
                        <input type="password" name="confirm_password" required placeholder="Ulangi password">
                    </div>
                </div>
                
                <button type="submit" class="btn-register">
                    <i class="fas fa-user-plus"></i> Daftar
                </button>
            </form>
            
            <div class="login-link">
                Sudah punya akun? <a href="login.php">Login disini</a>
            </div>
            <div class="login-link">
                <a href="index.php"><i class="fas fa-home"></i> Kembali ke Home</a>
            </div>
        </div>
    </div>
    
    <script src="assets/js/user/user-script.js"></script>
</body>
</html>