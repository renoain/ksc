<?php
$page_title = 'Management Pemesanan';
require_once '../includes/admin-header.php';
require_once '../includes/database.php';
require_once '../includes/functions.php';

$db = new Database();

// Handle actions
if(isset($_GET['action'])) {
    $action = $_GET['action'];
    $id = $_GET['id'] ?? 0;
    
    switch($action) {
        case 'approve':
            $db->query('UPDATE pemesanan SET status_pemesanan = "disetujui" WHERE id_pemesanan = :id');
            $db->bind(':id', $id);
            $db->execute();
            setFlashMessage('success', 'Pemesanan berhasil disetujui.');
            break;
            
        case 'reject':
            $reason = $_GET['reason'] ?? '';
            $db->query('UPDATE pemesanan SET status_pemesanan = "ditolak", catatan = :reason WHERE id_pemesanan = :id');
            $db->bind(':id', $id);
            $db->bind(':reason', $reason);
            $db->execute();
            setFlashMessage('warning', 'Pemesanan ditolak.');
            break;
            
        case 'cancel':
            $db->query('UPDATE pemesanan SET status_pemesanan = "dibatalkan" WHERE id_pemesanan = :id');
            $db->bind(':id', $id);
            $db->execute();
            setFlashMessage('info', 'Pemesanan dibatalkan.');
            break;
    }
    
    redirect('pemesanan.php');
}

// Get all bookings with filters
$status = $_GET['status'] ?? '';
$search = $_GET['search'] ?? '';

$sql = 'SELECT p.*, u.nama, u.email, u.no_hp, 
               l.nama_lapangan, l.tipe_lapangan,
               j.tanggal, j.jam_mulai, j.jam_selesai,
               py.kode_pembayaran, py.status_bayar, py.jumlah_bayar
        FROM pemesanan p
        JOIN user u ON p.id_user = u.id_user
        JOIN jadwal j ON p.id_jadwal = j.id_jadwal
        JOIN lapangan l ON j.id_lapangan = l.id_lapangan
        LEFT JOIN pembayaran py ON p.id_pemesanan = py.id_pemesanan
        WHERE 1=1';
        
if($status) {
    $sql .= ' AND p.status_pemesanan = :status';
}

if($search) {
    $sql .= ' AND (u.nama LIKE :search OR u.email LIKE :search OR l.nama_lapangan LIKE :search)';
}

$sql .= ' ORDER BY p.tanggal_pesan DESC';

$db->query($sql);

if($status) {
    $db->bind(':status', $status);
}

if($search) {
    $db->bind(':search', "%$search%");
}

$bookings = $db->resultSet();
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0"><i class="fas fa-calendar-check"></i> Management Pemesanan</h1>
        <div>
            <a href="dashboard.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Kembali
            </a>
        </div>
    </div>
    
    <!-- Filter Section -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Status</label>
                    <select class="form-select" name="status">
                        <option value="">Semua Status</option>
                        <option value="menunggu" <?= $status == 'menunggu' ? 'selected' : '' ?>>Menunggu</option>
                        <option value="disetujui" <?= $status == 'disetujui' ? 'selected' : '' ?>>Disetujui</option>
                        <option value="ditolak" <?= $status == 'ditolak' ? 'selected' : '' ?>>Ditolak</option>
                        <option value="selesai" <?= $status == 'selesai' ? 'selected' : '' ?>>Selesai</option>
                        <option value="dibatalkan" <?= $status == 'dibatalkan' ? 'selected' : '' ?>>Dibatalkan</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Pencarian</label>
                    <input type="text" class="form-control" name="search" value="<?= htmlspecialchars($search) ?>" 
                           placeholder="Cari nama, email, atau lapangan...">
                </div>
                <div class="col-md-4 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-search"></i> Filter
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Bookings Table -->
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Customer</th>
                            <th>Lapangan</th>
                            <th>Tanggal</th>
                            <th>Jam</th>
                            <th>Total</th>
                            <th>Status Booking</th>
                            <th>Status Bayar</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($bookings)): ?>
                            <tr>
                                <td colspan="9" class="text-center text-muted">Tidak ada data pemesanan</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach($bookings as $booking): ?>
                            <tr>
                                <td>#<?= str_pad($booking->id_pemesanan, 4, '0', STR_PAD_LEFT) ?></td>
                                <td>
                                    <strong><?= $booking->nama ?></strong><br>
                                    <small class="text-muted"><?= $booking->email ?></small><br>
                                    <small class="text-muted"><?= $booking->no_hp ?></small>
                                </td>
                                <td>
                                    <?= $booking->nama_lapangan ?><br>
                                    <span class="badge bg-secondary"><?= $booking->tipe_lapangan ?></span>
                                </td>
                                <td>
                                    <?= formatDate($booking->tanggal) ?><br>
                                    <small class="text-muted"><?= formatDate($booking->tanggal_pesan) ?></small>
                                </td>
                                <td><?= substr($booking->jam_mulai, 0, 5) ?> - <?= substr($booking->jam_selesai, 0, 5) ?></td>
                                <td><?= formatCurrency($booking->total_harga) ?></td>
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
                                <td>
                                    <?php if($booking->kode_pembayaran): ?>
                                        <?php 
                                        $pay_class = '';
                                        switch($booking->status_bayar) {
                                            case 'lunas': $pay_class = 'bg-success'; break;
                                            case 'dp_lunas': $pay_class = 'bg-primary'; break;
                                            case 'pending': $pay_class = 'bg-warning'; break;
                                            case 'expired': $pay_class = 'bg-secondary'; break;
                                            case 'gagal': $pay_class = 'bg-danger'; break;
                                        }
                                        ?>
                                        <span class="badge <?= $pay_class ?>">
                                            <?= ucfirst($booking->status_bayar) ?>
                                        </span><br>
                                        <small><?= formatCurrency($booking->jumlah_bayar) ?></small>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Belum Bayar</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <button type="button" class="btn btn-outline-info" 
                                                onclick="showBookingDetail(<?= $booking->id_pemesanan ?>)">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <?php if($booking->status_pemesanan == 'menunggu'): ?>
                                            <a href="pemesanan.php?action=approve&id=<?= $booking->id_pemesanan ?>" 
                                               class="btn btn-outline-success" 
                                               onclick="return confirm('Setujui pemesanan ini?')">
                                                <i class="fas fa-check"></i>
                                            </a>
                                            <button type="button" class="btn btn-outline-danger" 
                                                    onclick="showRejectModal(<?= $booking->id_pemesanan ?>)">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Detail Modal -->
<div class="modal fade" id="detailModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title"><i class="fas fa-info-circle"></i> Detail Pemesanan</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="detailContent">
                <!-- Content will be loaded via AJAX -->
            </div>
        </div>
    </div>
</div>

<!-- Reject Modal -->
<div class="modal fade" id="rejectModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title"><i class="fas fa-times-circle"></i> Tolak Pemesanan</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="GET" action="pemesanan.php">
                <input type="hidden" name="action" value="reject">
                <input type="hidden" name="id" id="rejectId">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Alasan Penolakan</label>
                        <textarea class="form-control" name="reason" rows="3" required 
                                  placeholder="Masukkan alasan penolakan..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-danger">Tolak Pemesanan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function showBookingDetail(bookingId) {
    fetch(`ajax/get_booking_admin.php?id=${bookingId}`)
        .then(response => response.text())
        .then(data => {
            document.getElementById('detailContent').innerHTML = data;
            const modal = new bootstrap.Modal(document.getElementById('detailModal'));
            modal.show();
        });
}

function showRejectModal(bookingId) {
    document.getElementById('rejectId').value = bookingId;
    const modal = new bootstrap.Modal(document.getElementById('rejectModal'));
    modal.show();
}
</script>

<?php require_once '../includes/admin-footer.php'; ?>