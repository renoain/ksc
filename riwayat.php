<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';
require_once 'includes/database.php';
require_once 'includes/functions.php';

$auth = new Auth();
$db = new Database();

if(!$auth->isLoggedIn()) {
    redirect('login.php');
}

// Handle cancel booking
if(isset($_GET['cancel'])) {
    $booking_id = $_GET['cancel'];
    $reason = $_GET['reason'] ?? '';
    
    // Verify booking belongs to user
    $db->query('SELECT * FROM pemesanan WHERE id_pemesanan = :id AND id_user = :user_id');
    $db->bind(':id', $booking_id);
    $db->bind(':user_id', $_SESSION['user_id']);
    $booking = $db->single();
    
    if($booking) {
        // Check if payment already made
        $db->query('SELECT * FROM pembayaran WHERE id_pemesanan = :id AND status_bayar IN ("lunas", "dp_lunas")');
        $db->bind(':id', $booking_id);
        $payment = $db->single();
        
        if($payment) {
            setFlashMessage('danger', 'Tidak dapat membatalkan booking yang sudah dibayar.');
        } else {
            // Update booking status
            $db->query('UPDATE pemesanan 
                       SET status_pemesanan = "dibatalkan", 
                           cancel_reason = :reason,
                           canceled_at = NOW()
                       WHERE id_pemesanan = :id');
            $db->bind(':reason', $reason);
            $db->bind(':id', $booking_id);
            
            if($db->execute()) {
                // Update schedule status back to available
                $db->query('UPDATE jadwal j
                           JOIN pemesanan p ON j.id_jadwal = p.id_jadwal
                           SET j.status_ketersediaan = "tersedia"
                           WHERE p.id_pemesanan = :id');
                $db->bind(':id', $booking_id);
                $db->execute();
                
                // Update payment status if exists
                $db->query('UPDATE pembayaran SET status_bayar = "dibatalkan" WHERE id_pemesanan = :id');
                $db->bind(':id', $booking_id);
                $db->execute();
                
                setFlashMessage('success', 'Booking berhasil dibatalkan.');
            } else {
                setFlashMessage('danger', 'Gagal membatalkan booking.');
            }
        }
    } else {
        setFlashMessage('danger', 'Booking tidak ditemukan.');
    }
    
    redirect('riwayat.php');
}

// Get user's booking history
$db->query('SELECT p.*, j.tanggal, j.jam_mulai, j.jam_selesai, 
                   l.nama_lapangan, l.tipe_lapangan,
                   py.kode_pembayaran, py.status_bayar, py.jumlah_bayar
            FROM pemesanan p
            JOIN jadwal j ON p.id_jadwal = j.id_jadwal
            JOIN lapangan l ON j.id_lapangan = l.id_lapangan
            LEFT JOIN pembayaran py ON p.id_pemesanan = py.id_pemesanan
            WHERE p.id_user = :user_id
            ORDER BY p.tanggal_pesan DESC');
$db->bind(':user_id', $_SESSION['user_id']);
$bookings = $db->resultSet();
?>

<?php 
$page_title = 'Riwayat Booking';
require_once 'includes/header.php';
?>

<div class="container">
    <h2 class="mb-4"><i class="fas fa-history"></i> Riwayat Booking Saya</h2>
    
    <?php if(empty($bookings)): ?>
        <div class="alert alert-info">
            <i class="fas fa-info-circle"></i> Anda belum memiliki riwayat booking.
            <a href="booking.php" class="alert-link">Booking sekarang</a>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead class="table-dark">
                    <tr>
                        <th>#</th>
                        <th>Tanggal</th>
                        <th>Lapangan</th>
                        <th>Jam</th>
                        <th>Total</th>
                        <th>Status Booking</th>
                        <th>Status Bayar</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($bookings as $index => $booking): ?>
                    <tr>
                        <td><?= $index + 1 ?></td>
                        <td>
                            <?= formatDate($booking->tanggal) ?><br>
                            <small class="text-muted">Booking: <?= formatDateTime($booking->tanggal_pesan) ?></small>
                        </td>
                        <td>
                            <strong><?= htmlspecialchars($booking->nama_lapangan) ?></strong><br>
                            <span class="badge bg-secondary"><?= $booking->tipe_lapangan ?></span>
                        </td>
                        <td>
                            <?= substr($booking->jam_mulai, 0, 5) ?> - <?= substr($booking->jam_selesai, 0, 5) ?>
                        </td>
                        <td><?= formatCurrency($booking->total_harga) ?></td>
                        <td><?= getStatusBadge($booking->status_pemesanan) ?></td>
                        <td>
                            <?php if($booking->kode_pembayaran): ?>
                                <?= getStatusBadge($booking->status_bayar) ?><br>
                                <small><?= formatCurrency($booking->jumlah_bayar) ?></small>
                            <?php else: ?>
                                <span class="badge bg-secondary">Belum Bayar</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <?php if($booking->kode_pembayaran): ?>
                                    <a href="payment.php?code=<?= $booking->kode_pembayaran ?>" 
                                       class="btn btn-outline-primary" title="Lihat Pembayaran">
                                        <i class="fas fa-money-bill-wave"></i>
                                    </a>
                                    <?php if($booking->status_bayar == 'lunas' || $booking->status_bayar == 'dp_lunas'): ?>
                                        <a href="invoice.php?code=<?= $booking->kode_pembayaran ?>" 
                                           class="btn btn-outline-success" title="Invoice" target="_blank">
                                            <i class="fas fa-file-invoice"></i>
                                        </a>
                                    <?php endif; ?>
                                <?php endif; ?>
                                
                                <?php if($booking->status_pemesanan == 'menunggu'): ?>
                                    <button class="btn btn-outline-danger" 
                                            onclick="showCancelModal(<?= $booking->id_pemesanan ?>)" 
                                            title="Batalkan Booking">
                                        <i class="fas fa-times"></i>
                                    </button>
                                <?php endif; ?>
                                
                                <button class="btn btn-outline-info" 
                                        onclick="showBookingDetail(<?= $booking->id_pemesanan ?>)" 
                                        title="Detail">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- Detail Modal -->
<div class="modal fade" id="detailModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title"><i class="fas fa-info-circle"></i> Detail Booking</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="detailContent">
                <!-- Content will be loaded via AJAX -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

<!-- Cancel Modal -->
<div class="modal fade" id="cancelModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title"><i class="fas fa-times-circle"></i> Batalkan Booking</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="cancelForm" method="GET" action="riwayat.php">
                <input type="hidden" name="cancel" id="cancelBookingId">
                <div class="modal-body">
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle"></i>
                        <strong>Perhatian!</strong> Anda akan membatalkan booking ini.
                        Tindakan ini tidak dapat dibatalkan.
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Alasan Pembatalan</label>
                        <textarea class="form-control" name="reason" rows="3" 
                                  placeholder="Masukkan alasan pembatalan..." required></textarea>
                    </div>
                    <div class="alert alert-info">
                        <small>
                            <i class="fas fa-info-circle"></i>
                            Booking hanya dapat dibatalkan jika belum dilakukan pembayaran.
                        </small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-danger">Ya, Batalkan Booking</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function showBookingDetail(bookingId) {
    fetch(`ajax/get_booking_detail.php?id=${bookingId}`)
        .then(response => response.text())
        .then(data => {
            document.getElementById('detailContent').innerHTML = data;
            const modal = new bootstrap.Modal(document.getElementById('detailModal'));
            modal.show();
        });
}

function showCancelModal(bookingId) {
    document.getElementById('cancelBookingId').value = bookingId;
    const modal = new bootstrap.Modal(document.getElementById('cancelModal'));
    modal.show();
}
</script>

<?php require_once 'includes/footer.php'; ?>