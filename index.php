<?php
$page_title = 'Home';
require_once 'includes/config.php';
require_once 'includes/header.php';
require_once 'includes/database.php';

$db = new Database();

// Get featured fields
$db->query('SELECT * FROM lapangan WHERE status_lapangan = "aktif" ORDER BY RAND() LIMIT 3');
$featured_fields = $db->resultSet();
?>

<!-- Hero Section -->
<section class="hero-section py-5">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-6">
                <h1 class="display-4 fw-bold mb-3">Selamat Datang di <span class="text-primary">KSC</span></h1>
                 <p class="lead mb-4">Tempat terbaik untuk berolahraga dan berkumpul bersama teman-teman. Fasilitas lengkap untuk futsal, badminton, dan voli dengan booking online 24/7.</p>
                <div class="d-flex gap-3">
                    <a href="booking.php" class="btn btn-primary btn-lg">
                        <i class="fas fa-calendar-plus me-2"></i> Booking Sekarang
                    </a>
                    <?php if(!isset($_SESSION['user_id'])): ?>
                    <a href="register.php" class="btn btn-outline-primary btn-lg">
                        <i class="fas fa-user-plus me-2"></i> Daftar Gratis
                    </a>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="text-center">
                    <img src="https://images.unsplash.com/photo-1571019613454-1cb2f99b2d8b?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80" 
                         alt="Sports Center" class="img-fluid rounded-3 shadow">
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Features Section -->
<section class="py-5">
    <div class="container">
        <div class="text-center mb-5">
            <h2 class="mb-3">Mengapa Memilih KSC?</h2>
            <p class="text-muted">Fasilitas terbaik dengan pengalaman booking yang mudah</p>
        </div>
        
        <div class="row g-4">
            <div class="col-md-4">
                <div class="card text-center p-4 h-100">
                    <div class="mb-3">
                        <i class="fas fa-calendar-check fa-3x text-primary"></i>
                    </div>
                    <h4 class="mb-3">Booking Online 24/7</h4>
                    <p class="text-muted">Booking kapan saja melalui website kami tanpa perlu datang ke lokasi</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card text-center p-4 h-100">
                    <div class="mb-3">
                        <i class="fas fa-shield-alt fa-3x text-primary"></i>
                    </div>
                    <h4 class="mb-3">Pembayaran Aman</h4>
                    <p class="text-muted">Sistem pembayaran terjamin dengan QRIS dan berbagai metode pembayaran</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card text-center p-4 h-100">
                    <div class="mb-3">
                        <i class="fas fa-headset fa-3x text-primary"></i>
                    </div>
                    <h4 class="mb-3">Customer Support</h4>
                    <p class="text-muted">Tim support siap membantu 7 hari seminggu untuk kebutuhan Anda</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Available Fields Section -->
<section class="py-5 bg-light">
    <div class="container">
        <div class="text-center mb-5">
            <h2 class="mb-3">Lapangan Tersedia</h2>
            <p class="text-muted">Pilih lapangan favorit Anda untuk mulai booking</p>
        </div>
        
        <div class="row g-4">
            <?php if(empty($featured_fields)): ?>
                <div class="col-12">
                    <div class="alert alert-info text-center">
                        <i class="fas fa-info-circle me-2"></i> Tidak ada lapangan tersedia saat ini.
                    </div>
                </div>
            <?php else: ?>
                <?php foreach($featured_fields as $field): ?>
                <div class="col-md-4">
                    <div class="card h-100">
                        <?php 
                        $image_map = [
                            'Futsal' => 'https://images.unsplash.com/photo-1511882150382-421056c89033?w=400&h=250&fit=crop',
                            'Badminton' => 'https://images.unsplash.com/photo-1622278648956-5d2c8f46b7bb?w=400&h=250&fit=crop',
                            'Voli' => 'https://images.unsplash.com/photo-1612872087720-bb876e2e67d1?w=400&h=250&fit=crop',
                        ];
                        $image = $image_map[$field->tipe_lapangan] ?? 'https://images.unsplash.com/photo-1546519638-68e109498ffc?w=400&h=250&fit=crop';
                        ?>
                        <img src="<?= $image ?>" class="card-img-top" alt="<?= $field->nama_lapangan ?>" style="height: 200px; object-fit: cover;">
                        <div class="card-body">
                            <h5 class="card-title"><?= $field->nama_lapangan ?></h5>
                            <p class="card-text text-muted"><?= $field->deskripsi ?: 'Lapangan '.$field->tipe_lapangan.' dengan fasilitas lengkap' ?></p>
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="h5 mb-0 text-primary">Rp <?= number_format($field->harga_per_jam, 0, ',', '.') ?>/jam</span>
                                <a href="booking.php" class="btn btn-sm btn-outline-primary">
                                    <i class="fas fa-calendar-plus me-1"></i> Booking
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
        <div class="text-center mt-4">
            <a href="booking.php" class="btn btn-primary">
                <i class="fas fa-eye me-2"></i> Lihat Semua Lapangan
            </a>
        </div>
    </div>
</section>

<!-- Contact CTA -->
<section class="py-5 bg-primary text-white">
    <div class="container text-center">
        <h2 class="mb-4">Siap Mulai Olahraga?</h2>
        <p class="lead mb-4">Booking lapangan favorit Anda sekarang dan nikmati pengalaman olahraga terbaik!</p>
        <a href="booking.php" class="btn btn-light btn-lg px-5">
            <i class="fas fa-calendar-plus me-2"></i> Booking Sekarang
        </a>
    </div>
</section>

<?php require_once 'includes/footer.php'; ?>