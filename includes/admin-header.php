<?php
// Start session jika belum dimulai
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../includes/config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php'; // Tambahkan ini

$auth = new Auth();

if(!$auth->isLoggedIn() || !$auth->isAdmin()) {
    redirect('../login.php');
}

// Function untuk show flash message
function showFlashMessage() {
    if (session_status() === PHP_SESSION_ACTIVE && isset($_SESSION['flash_message'])) {
        $type = $_SESSION['flash_message']['type'];
        $message = $_SESSION['flash_message']['message'];
        
        echo '<div class="alert alert-' . $type . ' alert-dismissible fade show" role="alert">
                ' . $message . '
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
              </div>';
        
        unset($_SESSION['flash_message']);
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - <?= isset($page_title) ? $page_title . ' - ' : '' ?><?= SITE_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?= SITE_URL ?>assets/css/admin.css">
</head>
<body>
    <div class="d-flex" id="wrapper">
        <!-- Sidebar -->
        <div class="bg-dark text-white" id="sidebar-wrapper" style="width: 250px;">
            <div class="sidebar-heading text-center py-4">
                <h4><i class="fas fa-running"></i> KSC Admin</h4>
            </div>
            <div class="list-group list-group-flush">
                <a href="dashboard.php" class="list-group-item list-group-item-action bg-dark text-white">
                    <i class="fas fa-tachometer-alt"></i> Dashboard
                </a>
                <a href="lapangan.php" class="list-group-item list-group-item-action bg-dark text-white">
                    <i class="fas fa-futbol"></i> Kelola Lapangan
                </a>
                <a href="template-jadwal.php" class="list-group-item list-group-item-action bg-dark text-white">
                    <i class="fas fa-calendar-week"></i> Template Jadwal
                </a>
                <a href="hari-libur.php" class="list-group-item list-group-item-action bg-dark text-white">
                    <i class="fas fa-calendar-times"></i> Hari Libur
                </a>
                <a href="jadwal.php" class="list-group-item list-group-item-action bg-dark text-white">
                    <i class="fas fa-calendar-day"></i> Jadwal
                </a>
                <a href="pemesanan.php" class="list-group-item list-group-item-action bg-dark text-white">
                    <i class="fas fa-calendar-check"></i> Pemesanan
                </a>
                <a href="pembayaran.php" class="list-group-item list-group-item-action bg-dark text-white">
                    <i class="fas fa-money-bill-wave"></i> Pembayaran
                </a>
                <a href="users.php" class="list-group-item list-group-item-action bg-dark text-white">
                    <i class="fas fa-users"></i> Users
                </a>
                <a href="<?= SITE_URL ?>" class="list-group-item list-group-item-action bg-dark text-white">
                    <i class="fas fa-external-link-alt"></i> Kembali ke Site
                </a>
                <a href="../logout.php" class="list-group-item list-group-item-action bg-dark text-white">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>

        <!-- Page Content -->
        <div id="page-content-wrapper" style="flex: 1;">
            <nav class="navbar navbar-expand-lg navbar-light bg-light border-bottom">
                <div class="container-fluid">
                    <button class="btn btn-primary" id="menu-toggle">
                        <i class="fas fa-bars"></i>
                    </button>
                    <div class="navbar-nav ms-auto">
                        <span class="navbar-text">
                            <i class="fas fa-user"></i> <?= $_SESSION['nama'] ?> (Admin)
                        </span>
                    </div>
                </div>
            </nav>

            <div class="container-fluid mt-4">
                <?php showFlashMessage(); ?>