<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';
require_once 'includes/payment-system.php';

$auth = new Auth();

if(!$auth->isLoggedIn()) {
    redirect('login.php');
}

$payment_code = $_GET['code'] ?? '';
if(empty($payment_code)) {
    setFlashMessage('danger', 'Kode pembayaran tidak ditemukan.');
    redirect('riwayat.php');
}

$paymentSystem = new PaymentSystem();
$payment = $paymentSystem->getPaymentByCode($payment_code);

if(!$payment) {
    setFlashMessage('danger', 'Kode pembayaran tidak valid.');
    redirect('riwayat.php');
}

// Verify payment belongs to user
if($payment->id_user != $_SESSION['user_id']) {
    setFlashMessage('danger', 'Anda tidak memiliki akses ke pembayaran ini.');
    redirect('riwayat.php');
}

// Process payment confirmation
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['confirm_payment'])) {
    $bukti_pembayaran = null;
    
    if(isset($_FILES['bukti_pembayaran']) && $_FILES['bukti_pembayaran']['error'] == 0) {
        $upload_dir = 'uploads/payments/';
        
        // Create directory if not exists
        if(!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        $filename = uniqid() . '_' . basename($_FILES['bukti_pembayaran']['name']);
        $target_file = $upload_dir . $filename;
        
        // Check file type
        $allowed_types = ['image/jpeg', 'image/png', 'image/jpg', 'image/gif'];
        $file_type = mime_content_type($_FILES['bukti_pembayaran']['tmp_name']);
        
        if(in_array($file_type, $allowed_types)) {
            if(move_uploaded_file($_FILES['bukti_pembayaran']['tmp_name'], $target_file)) {
                $bukti_pembayaran = $filename;
            }
        }
    }
    
    if($paymentSystem->processPayment($payment_code, $bukti_pembayaran)) {
        setFlashMessage('success', 'Pembayaran berhasil dikonfirmasi! Invoice telah dibuat.');
        redirect('invoice.php?code=' . $payment_code);
    } else {
        setFlashMessage('danger', 'Gagal memproses pembayaran.');
    }
}

$page_title = 'Pembayaran';
require_once 'includes/header.php';
?>

<div class="container">
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <div class="card shadow">
                <div class="card-header bg-primary text-white">
                    <h3 class="mb-0"><i class="fas fa-money-bill-wave"></i> Pembayaran</h3>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="card mb-4">
                                <div class="card-header bg-info text-white">
                                    <h5 class="mb-0">Detail Pembayaran</h5>
                                </div>
                                <div class="card-body">
                                    <table class="table table-bordered">
                                        <tr>
                                            <th width="40%">Kode Pembayaran</th>
                                            <td><code><?= $payment->kode_pembayaran ?></code></td>
                                        </tr>
                                        <tr>
                                            <th>Status</th>
                                            <td><?= getStatusBadge($payment->status_bayar) ?></td>
                                        </tr>
                                        <tr>
                                            <th>Tipe Pembayaran</th>
                                            <td><?= strtoupper($payment->tipe_pembayaran) ?></td>
                                        </tr>
                                        <tr>
                                            <th>Jumlah Bayar</th>
                                            <td class="fw-bold"><?= formatCurrency($payment->jumlah_bayar) ?></td>
                                        </tr>
                                        <?php if($payment->tipe_pembayaran == 'dp'): ?>
                                        <tr>
                                            <th>DP Amount</th>
                                            <td><?= formatCurrency($payment->dp_amount) ?> (<?= $payment->dp_percent ?>%)</td>
                                        </tr>
                                        <tr>
                                            <th>Sisa Tagihan</th>
                                            <td><?= formatCurrency($payment->sisa_tagihan) ?></td>
                                        </tr>
                                        <?php endif; ?>
                                        <tr>
                                            <th>Batas Waktu</th>
                                            <td class="<?= strtotime($payment->expired_time) < time() ? 'text-danger' : '' ?>">
                                                <?= formatDateTime($payment->expired_time) ?>
                                            </td>
                                        </tr>
                                    </table>
                                </div>
                            </div>
                            
                            <div class="card">
                                <div class="card-header bg-success text-white">
                                    <h5 class="mb-0">Detail Booking</h5>
                                </div>
                                <div class="card-body">
                                    <table class="table table-bordered">
                                        <tr>
                                            <th width="40%">Lapangan</th>
                                            <td><?= $payment->nama_lapangan ?></td>
                                        </tr>
                                        <tr>
                                            <th>Tanggal Main</th>
                                            <td><?= formatDate($payment->tanggal) ?></td>
                                        </tr>
                                        <tr>
                                            <th>Jam</th>
                                            <td><?= substr($payment->jam_mulai, 0, 5) ?> - <?= substr($payment->jam_selesai, 0, 5) ?></td>
                                        </tr>
                                        <tr>
                                            <th>Total Harga</th>
                                            <td><?= formatCurrency($payment->total_harga) ?></td>
                                        </tr>
                                    </table>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="card mb-4">
                                <div class="card-header bg-warning text-dark">
                                    <h5 class="mb-0"><i class="fas fa-qrcode"></i> QRIS Payment</h5>
                                </div>
                                <div class="card-body text-center">
                                    <?php if($payment->status_bayar == 'pending'): ?>
                                        <div class="mb-4">
                                            <?php if(!empty($payment->qr_code) && strpos($payment->qr_code, 'data:image') === 0): ?>
                                                <img src="image.png" alt="QR Code" class="img-fluid mb-3" style="max-width: 250px;">
                                            <?php else: ?>
                                                <div class="alert alert-info">
                                                    QR Code akan muncul setelah pembayaran dibuat
                                                </div>
                                            <?php endif; ?>
                                            <p class="text-muted">Scan QR Code di atas untuk membayar</p>
                                        </div>
                                        
                                        <div class="alert alert-info">
                                            <h6><i class="fas fa-exclamation-triangle"></i> Instruksi Pembayaran:</h6>
                                            <ol class="text-start">
                                                <li>Buka aplikasi e-wallet atau mobile banking yang mendukung QRIS</li>
                                                <li>Pilih fitur scan QR code</li>
                                                <li>Arahkan kamera ke QR code di atas</li>
                                                <li>Konfirmasi nominal dan lakukan pembayaran</li>
                                                <li>Simpan bukti pembayaran</li>
                                            </ol>
                                        </div>
                                        
                                        <form method="POST" enctype="multipart/form-data">
                                            <div class="mb-3">
                                                <label class="form-label">Upload Bukti Pembayaran</label>
                                                <input type="file" class="form-control" name="bukti_pembayaran" accept="image/*" required>
                                                <small class="text-muted">Upload screenshot/slip pembayaran (JPG, PNG, GIF)</small>
                                            </div>
                                            
                                            <button type="submit" name="confirm_payment" class="btn btn-success btn-lg w-100">
                                                <i class="fas fa-check-circle"></i> Konfirmasi Pembayaran
                                            </button>
                                        </form>
                                    <?php elseif($payment->status_bayar == 'lunas' || $payment->status_bayar == 'dp_lunas'): ?>
                                        <div class="alert alert-success">
                                            <h4><i class="fas fa-check-circle"></i> Pembayaran Berhasil!</h4>
                                            <p>Pembayaran Anda telah dikonfirmasi.</p>
                                            <a href="invoice.php?code=<?= $payment_code ?>" class="btn btn-primary">
                                                <i class="fas fa-file-invoice"></i> Lihat Invoice
                                            </a>
                                        </div>
                                    <?php else: ?>
                                        <div class="alert alert-danger">
                                            <h4><i class="fas fa-times-circle"></i> Pembayaran Gagal/Expired</h4>
                                            <p>Silakan hubungi admin untuk informasi lebih lanjut.</p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="card">
                                <div class="card-header bg-secondary text-white">
                                    <h5 class="mb-0">Alternatif Pembayaran</h5>
                                </div>
                                <div class="card-body">
                                    <h6>Transfer Manual:</h6>
                                    <div class="alert alert-light">
                                        <p class="mb-1"><strong>Bank:</strong> BCA</p>
                                        <p class="mb-1"><strong>No. Rekening:</strong> 123-456-7890</p>
                                        <p class="mb-1"><strong>Atas Nama:</strong> <?= QRIS_MERCHANT ?></p>
                                        <p class="mb-0"><strong>Kode Unik:</strong> <?= substr($payment->kode_pembayaran, -3) ?></p>
                                    </div>
                                    <p class="text-muted small">Setelah transfer, upload bukti transfer di atas untuk konfirmasi.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-footer">
                    <a href="riwayat.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Kembali ke Riwayat
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>