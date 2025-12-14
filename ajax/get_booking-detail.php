<?php
require_once '../includes/config.php';
require_once '../includes/database.php';
require_once '../includes/functions.php';

$db = new Database();

$booking_id = $_GET['id'] ?? 0;

$db->query('SELECT p.*, j.*, l.*, u.nama, u.email, u.no_hp, py.*
           FROM pemesanan p
           JOIN user u ON p.id_user = u.id_user
           JOIN jadwal j ON p.id_jadwal = j.id_jadwal
           JOIN lapangan l ON j.id_lapangan = l.id_lapangan
           LEFT JOIN pembayaran py ON p.id_pemesanan = py.id_pemesanan
           WHERE p.id_pemesanan = :id');
$db->bind(':id', $booking_id);
$booking = $db->single();

if(!$booking) {
    echo '<div class="alert alert-danger">Data tidak ditemukan</div>';
    exit();
}
?>

<div class="row">
    <div class="col-md-6">
        <h6>Informasi Booking</h6>
        <table class="table table-sm">
            <tr>
                <th width="40%">Booking ID</th>
                <td>#<?= str_pad($booking->id_pemesanan, 4, '0', STR_PAD_LEFT) ?></td>
            </tr>
            <tr>
                <th>Tanggal Pesan</th>
                <td><?= formatDateTime($booking->tanggal_pesan) ?></td>
            </tr>
            <tr>
                <th>Status</th>
                <td>
                    <?php 
                    $status_class = '';
                    switch($booking->status_pemesanan) {
                        case 'disetujui': $status_class = 'bg-success'; break;
                        case 'menunggu': $status_class = 'bg-warning'; break;
                        case 'ditolak': $status_class = 'bg-danger'; break;
                        case 'selesai': $status_class = 'bg-info'; break;
                        case 'dibatalkan': $status_class = 'bg-secondary'; break;
                    }
                    ?>
                    <span class="badge <?= $status_class ?>">
                        <?= ucfirst($booking->status_pemesanan) ?>
                    </span>
                </td>
            </tr>
            <tr>
                <th>Total Harga</th>
                <td><?= formatCurrency($booking->total_harga) ?></td>
            </tr>
            <tr>
                <th>Catatan</th>
                <td><?= $booking->catatan ?: '-' ?></td>
            </tr>
            <?php if($booking->cancel_reason): ?>
            <tr>
                <th>Alasan Batal</th>
                <td><?= $booking->cancel_reason ?></td>
            </tr>
            <?php endif; ?>
        </table>
    </div>
    
    <div class="col-md-6">
        <h6>Detail Lapangan</h6>
        <table class="table table-sm">
            <tr>
                <th width="40%">Nama Lapangan</th>
                <td><?= $booking->nama_lapangan ?></td>
            </tr>
            <tr>
                <th>Tipe</th>
                <td><span class="badge bg-secondary"><?= $booking->tipe_lapangan ?></span></td>
            </tr>
            <tr>
                <th>Tanggal Main</th>
                <td><?= formatDate($booking->tanggal) ?></td>
            </tr>
            <tr>
                <th>Jam</th>
                <td><?= substr($booking->jam_mulai, 0, 5) ?> - <?= substr($booking->jam_selesai, 0, 5) ?></td>
            </tr>
            <tr>
                <th>Harga</th>
                <td><?= formatCurrency($booking->harga ?: $booking->harga_per_jam) ?></td>
            </tr>
        </table>
    </div>
</div>

<?php if($booking->kode_pembayaran): ?>
<div class="row mt-3">
    <div class="col-12">
        <h6>Informasi Pembayaran</h6>
        <table class="table table-sm">
            <tr>
                <th width="25%">Kode Pembayaran</th>
                <td><code><?= $booking->kode_pembayaran ?></code></td>
                <th width="25%">Metode Bayar</th>
                <td><?= ucfirst($booking->metode_bayar) ?></td>
            </tr>
            <tr>
                <th>Tipe Pembayaran</th>
                <td><?= strtoupper($booking->tipe_pembayaran) ?></td>
                <th>Status Bayar</th>
                <td>
                    <?php 
                    $status_class = '';
                    switch($booking->status_bayar) {
                        case 'lunas': $status_class = 'bg-success'; break;
                        case 'dp_lunas': $status_class = 'bg-primary'; break;
                        case 'pending': $status_class = 'bg-warning'; break;
                        case 'expired': $status_class = 'bg-secondary'; break;
                        case 'gagal': $status_class = 'bg-danger'; break;
                    }
                    ?>
                    <span class="badge <?= $status_class ?>">
                        <?= ucfirst($booking->status_bayar) ?>
                    </span>
                </td>
            </tr>
            <tr>
                <th>Jumlah Bayar</th>
                <td><?= formatCurrency($booking->jumlah_bayar) ?></td>
                <th>Sisa Tagihan</th>
                <td><?= formatCurrency($booking->sisa_tagihan) ?></td>
            </tr>
        </table>
    </div>
</div>
<?php endif; ?>