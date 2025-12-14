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
    setFlashMessage('danger', 'Kode invoice tidak ditemukan.');
    redirect('riwayat.php');
}

$paymentSystem = new PaymentSystem();
$payment = $paymentSystem->getPaymentByCode($payment_code);

if(!$payment) {
    setFlashMessage('danger', 'Invoice tidak ditemukan.');
    redirect('riwayat.php');
}

// Verify payment belongs to user or user is admin
if($payment->id_user != $_SESSION['user_id'] && !$auth->isAdmin()) {
    setFlashMessage('danger', 'Anda tidak memiliki akses ke invoice ini.');
    redirect('riwayat.php');
}

// Check if payment is completed
if(!in_array($payment->status_bayar, ['lunas', 'dp_lunas'])) {
    setFlashMessage('warning', 'Pembayaran belum lunas. Tidak dapat melihat invoice.');
    redirect('payment.php?code=' . $payment_code);
}

$page_title = 'Invoice';
require_once 'includes/header.php';
?>

<div class="container">
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <!-- Printable Invoice -->
            <div id="invoiceContent" class="card shadow printable">
                <div class="card-body">
                    <!-- Invoice Header -->
                    <div class="row mb-4">
                        <div class="col-6">
                            <h2 class="text-primary mb-0"><?= SITE_NAME ?></h2>
                            <p class="text-muted mb-1">Jl. Olahraga No. 123, Jakarta</p>
                            <p class="text-muted mb-1">Telp: (021) 123-4567</p>
                            <p class="text-muted mb-0">Email: info@ksc.com</p>
                        </div>
                        <div class="col-6 text-end">
                            <h2 class="mb-0">INVOICE</h2>
                            <p class="text-muted mb-1">No: <?= $payment->kode_pembayaran ?></p>
                            <p class="text-muted mb-0">Tanggal: <?= date('d/m/Y') ?></p>
                        </div>
                    </div>
                    
                    <!-- Bill To -->
                    <div class="row mb-4">
                        <div class="col-12">
                            <div class="card border">
                                <div class="card-body">
                                    <h5 class="card-title">Kepada:</h5>
                                    <p class="mb-1"><strong><?= $payment->nama ?></strong></p>
                                    <p class="mb-1"><?= $payment->email ?></p>
                                    <p class="mb-0">Customer ID: <?= str_pad($payment->id_user, 6, '0', STR_PAD_LEFT) ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Booking Details -->
                    <div class="table-responsive mb-4">
                        <table class="table table-bordered">
                            <thead class="table-light">
                                <tr>
                                    <th>Deskripsi</th>
                                    <th class="text-center">Tanggal</th>
                                    <th class="text-center">Jam</th>
                                    <th class="text-end">Harga</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>
                                        <strong><?= $payment->nama_lapangan ?></strong><br>
                                        <small class="text-muted">Booking Lapangan <?= $payment->nama_lapangan ?></small>
                                    </td>
                                    <td class="text-center"><?= formatDate($payment->tanggal) ?></td>
                                    <td class="text-center"><?= substr($payment->jam_mulai, 0, 5) ?> - <?= substr($payment->jam_selesai, 0, 5) ?></td>
                                    <td class="text-end"><?= formatCurrency($payment->total_harga) ?></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Payment Summary -->
                    <div class="row">
                        <div class="col-md-6">
                            <div class="card border">
                                <div class="card-body">
                                    <h5 class="card-title">Informasi Pembayaran:</h5>
                                    <table class="table table-sm">
                                        <tr>
                                            <td><strong>Metode</strong></td>
                                            <td>QRIS</td>
                                        </tr>
                                        <tr>
                                            <td><strong>Tipe</strong></td>
                                            <td><?= strtoupper($payment->tipe_pembayaran) ?></td>
                                        </tr>
                                        <tr>
                                            <td><strong>Tanggal Bayar</strong></td>
                                            <td><?= formatDateTime($payment->tanggal_bayar) ?></td>
                                        </tr>
                                        <tr>
                                            <td><strong>Status</strong></td>
                                            <td>
                                                <span class="badge bg-success"><?= strtoupper($payment->status_bayar) ?></span>
                                            </td>
                                        </tr>
                                    </table>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <table class="table table-bordered">
                                <tr>
                                    <th>Subtotal:</th>
                                    <td class="text-end"><?= formatCurrency($payment->total_harga) ?></td>
                                </tr>
                                <?php if($payment->tipe_pembayaran == 'dp'): ?>
                                <tr>
                                    <th>DP (<?= $payment->dp_percent ?>%):</th>
                                    <td class="text-end"><?= formatCurrency($payment->dp_amount) ?></td>
                                </tr>
                                <?php endif; ?>
                                <tr class="table-light">
                                    <th>Total Dibayar:</th>
                                    <td class="text-end text-success fw-bold"><?= formatCurrency($payment->jumlah_bayar) ?></td>
                                </tr>
                                <?php if($payment->tipe_pembayaran == 'dp'): ?>
                                <tr>
                                    <th>Sisa Tagihan:</th>
                                    <td class="text-end"><?= formatCurrency($payment->sisa_tagihan) ?></td>
                                </tr>
                                <?php endif; ?>
                            </table>
                        </div>
                    </div>
                    
                    <!-- Terms -->
                    <div class="mt-4 pt-4 border-top">
                        <h6>Catatan:</h6>
                        <ul class="small">
                            <li>Invoice ini sah sebagai bukti pembayaran</li>
                            <li>Harap tunjukkan invoice saat check-in di lokasi</li>
                            <li>Pembatalan booking maksimal 24 jam sebelum waktu main</li>
                            <li>DP tidak dapat dikembalikan jika pembatalan dilakukan kurang dari 24 jam</li>
                            <li>Terima kasih telah menggunakan layanan <?= SITE_NAME ?></li>
                        </ul>
                    </div>
                    
                    <!-- Signatures -->
                    <div class="row mt-5 pt-4">
                        <div class="col-6">
                            <p class="mb-0"><strong>Tanda Tangan Pelanggan</strong></p>
                            <div style="height: 50px; border-bottom: 1px solid #000; margin-top: 20px;"></div>
                            <p class="mb-0 mt-2"><?= $payment->nama ?></p>
                        </div>
                        <div class="col-6 text-end">
                            <p class="mb-0"><strong>Tanda Tangan <?= SITE_NAME ?></strong></p>
                            <div style="height: 50px; border-bottom: 1px solid #000; margin-top: 20px;"></div>
                            <p class="mb-0 mt-2">Administrator</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Action Buttons -->
            <div class="mt-4 text-center">
                <button onclick="printInvoice()" class="btn btn-primary">
                    <i class="fas fa-print"></i> Cetak Invoice
                </button>
                <a href="riwayat.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Kembali ke Riwayat
                </a>
                <?php if($payment->tipe_pembayaran == 'dp' && $payment->sisa_tagihan > 0): ?>
                    <a href="payment.php?code=<?= $payment_code ?>" class="btn btn-warning">
                        <i class="fas fa-money-bill"></i> Bayar Sisa Tagihan
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<style>
.printable {
    background: white;
    padding: 30px;
}

@media print {
    body * {
        visibility: hidden;
    }
    .printable, .printable * {
        visibility: visible;
    }
    .printable {
        position: absolute;
        left: 0;
        top: 0;
        width: 100%;
        border: none;
        box-shadow: none;
    }
    .no-print {
        display: none !important;
    }
}
</style>

<script>
function printInvoice() {
    window.print();
}
</script>

<?php require_once 'includes/footer.php'; ?>