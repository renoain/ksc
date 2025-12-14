<?php
require_once '../includes/config.php';
require_once '../includes/database.php';
require_once '../includes/functions.php';

$db = new Database();

$payment_id = $_GET['id'] ?? 0;

$db->query('SELECT py.*, p.*, u.*, l.*, j.*
           FROM pembayaran py
           JOIN pemesanan p ON py.id_pemesanan = p.id_pemesanan
           JOIN user u ON p.id_user = u.id_user
           JOIN jadwal j ON p.id_jadwal = j.id_jadwal
           JOIN lapangan l ON j.id_lapangan = l.id_lapangan
           WHERE py.id_pembayaran = :id');
$db->bind(':id', $payment_id);
$payment = $db->single();

if(!$payment) {
    echo '<div class="alert alert-danger">Data tidak ditemukan</div>';
    exit();
}
?>

<div class="row">
    <div class="col-md-6">
        <h6>Informasi Pembayaran</h6>
        <table class="table table-sm">
            <tr>
                <th width="40%">Kode Pembayaran</th>
                <td><code><?= $payment->kode_pembayaran ?></code></td>
            </tr>
            <tr>
                <th>Tipe Pembayaran</th>
                <td><?= strtoupper($payment->tipe_pembayaran) ?></td>
            </tr>
            <tr>
                <th>Metode Bayar</th>
                <td><?= ucfirst($payment->metode_bayar) ?></td>
            </tr>
            <tr>
                <th>Status</th>
                <td>
                    <?php 
                    $status_class = '';
                    switch($payment->status_bayar) {
                        case 'lunas': $status_class = 'bg-success'; break;
                        case 'dp_lunas': $status_class = 'bg-primary'; break;
                        case 'pending': $status_class = 'bg-warning'; break;
                        case 'expired': $status_class = 'bg-secondary'; break;
                        case 'gagal': $status_class = 'bg-danger'; break;
                    }
                    ?>
                    <span class="badge <?= $status_class ?>">
                        <?= ucfirst($payment->status_bayar) ?>
                    </span>
                </td>
            </tr>
            <tr>
                <th>Jumlah Bayar</th>
                <td><?= formatCurrency($payment->jumlah_bayar) ?></td>
            </tr>
            <?php if($payment->tipe_pembayaran == 'dp'): ?>
            <tr>
                <th>DP Percentage</th>
                <td><?= $payment->dp_percent ?>%</td>
            </tr>
            <tr>
                <th>DP Amount</th>
                <td><?= formatCurrency($payment->dp_amount) ?></td>
            </tr>
            <tr>
                <th>Sisa Tagihan</th>
                <td><?= formatCurrency($payment->sisa_tagihan) ?></td>
            </tr>
            <?php endif; ?>
            <tr>
                <th>Tanggal Bayar</th>
                <td><?= $payment->tanggal_bayar ? formatDateTime($payment->tanggal_bayar) : '-' ?></td>
            </tr>
            <tr>
                <th>Expired Time</th>
                <td><?= formatDateTime($payment->expired_time) ?></td>
            </tr>
        </table>
    </div>
    
    <div class="col-md-6">
        <h6>Informasi Customer</h6>
        <table class="table table-sm">
            <tr>
                <th width="40%">Nama</th>
                <td><?= $payment->nama ?></td>
            </tr>
            <tr>
                <th>Email</th>
                <td><?= $payment->email ?></td>
            </tr>
            <tr>
                <th>No. HP</th>
                <td><?= $payment->no_hp ?></td>
            </tr>
            <tr>
                <th>Username</th>
                <td><?= $payment->username ?></td>
            </tr>
        </table>
    </div>
</div>

<div class="row mt-3">
    <div class="col-md-6">
        <h6>Detail Booking</h6>
        <table class="table table-sm">
            <tr>
                <th width="40%">Booking ID</th>
                <td>#<?= str_pad($payment->id_pemesanan, 4, '0', STR_PAD_LEFT) ?></td>
            </tr>
            <tr>
                <th>Tanggal Pesan</th>
                <td><?= formatDateTime($payment->tanggal_pesan) ?></td>
            </tr>
            <tr>
                <th>Status Booking</th>
                <td>
                    <?php 
                    $status_class = '';
                    switch($payment->status_pemesanan) {
                        case 'disetujui': $status_class = 'bg-success'; break;
                        case 'menunggu': $status_class = 'bg-warning'; break;
                        case 'ditolak': $status_class = 'bg-danger'; break;
                        case 'selesai': $status_class = 'bg-info'; break;
                        case 'dibatalkan': $status_class = 'bg-secondary'; break;
                    }
                    ?>
                    <span class="badge <?= $status_class ?>">
                        <?= ucfirst($payment->status_pemesanan) ?>
                    </span>
                </td>
            </tr>
            <tr>
                <th>Total Harga</th>
                <td><?= formatCurrency($payment->total_harga) ?></td>
            </tr>
        </table>
    </div>
    
    <div class="col-md-6">
        <h6>Detail Lapangan</h6>
        <table class="table table-sm">
            <tr>
                <th width="40%">Nama Lapangan</th>
                <td><?= $payment->nama_lapangan ?></td>
            </tr>
            <tr>
                <th>Tipe</th>
                <td><span class="badge bg-secondary"><?= $payment->tipe_lapangan ?></span></td>
            </tr>
            <tr>
                <th>Tanggal Main</th>
                <td><?= formatDate($payment->tanggal) ?></td>
            </tr>
            <tr>
                <th>Jam</th>
                <td><?= substr($payment->jam_mulai, 0, 5) ?> - <?= substr($payment->jam_selesai, 0, 5) ?></td>
            </tr>
        </table>
    </div>
</div>

<?php if($payment->bukti_pembayaran): ?>
<div class="row mt-3">
    <div class="col-12">
        <h6>Bukti Pembayaran</h6>
        <div class="text-center">
            <img src="../uploads/payments/<?= $payment->bukti_pembayaran ?>" 
                 alt="Bukti Pembayaran" class="img-fluid rounded" style="max-height: 300px;">
            <p class="mt-2"><small>File: <?= $payment->bukti_pembayaran ?></small></p>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="mt-3 text-end">
    <a href="../invoice.php?code=<?= $payment->kode_pembayaran ?>" class="btn btn-primary btn-sm" target="_blank">
        <i class="fas fa-file-invoice"></i> Lihat Invoice
    </a>
</div>