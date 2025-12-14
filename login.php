<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

$auth = new Auth();
$error = '';

if($auth->isLoggedIn()) {
    redirect($auth->isAdmin() ? ADMIN_URL . 'dashboard.php' : 'index.php');
}

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    
    if(empty($username) || empty($password)) {
        $error = 'Username dan password harus diisi!';
    } else {
        if($auth->login($username, $password)) {
            setFlashMessage('success', 'Login berhasil! Selamat datang ' . $_SESSION['nama']);
            redirect($auth->isAdmin() ? ADMIN_URL . 'dashboard.php' : 'index.php');
        } else {
            $error = 'Username atau password salah!';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - KSC</title>
    <link rel="stylesheet" href="assets/css/user/user-style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .login-container {
            max-width: 400px;
            margin: 100px auto;
            padding: 30px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .login-header {
            text-align: center;
            margin-bottom: 30px;
        }
        .login-header h2 {
            color: #2c3e50;
        }
        .form-group {
            margin-bottom: 20px;
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
        .btn-login {
            width: 100%;
            padding: 12px;
            background: #3498db;
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            cursor: pointer;
            transition: background 0.3s;
        }
        .btn-login:hover {
            background: #2980b9;
        }
        .error {
            background: #e74c3c;
            color: white;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
            text-align: center;
        }
        .register-link {
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
        <div class="login-container">
            <div class="login-header">
                <h2><i class="fas fa-sign-in-alt"></i> Login</h2>
                <p>Masuk ke akun Anda</p>
            </div>
            
            <?php if($error): ?>
                <div class="error">
                    <?= $error ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="form-group">
                    <label><i class="fas fa-user"></i> Username atau Email</label>
                    <input type="text" name="username" required placeholder="Masukkan username atau email">
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-lock"></i> Password</label>
                    <input type="password" name="password" required placeholder="Masukkan password">
                </div>
                
                <button type="submit" class="btn-login">
                    <i class="fas fa-sign-in-alt"></i> Login
                </button>
            </form>
            
            <div class="register-link">
                Belum punya akun? <a href="register.php">Daftar disini</a>
            </div>
            <div class="register-link">
                <a href="index.php"><i class="fas fa-home"></i> Kembali ke Home</a>
            </div>
        </div>
    </div>
    
    <script src="assets/js/user/user-script.js"></script>
</body>
</html>