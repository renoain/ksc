<?php
$page_title = 'Management Pembayaran';
require_once '../includes/admin-header.php';
require_once '../includes/database.php'; // Tambahkan ini
require_once '../includes/functions.php'; // Tambahkan ini (tapi sudah include di admin-header)

$db = new Database();

// Handle actions
if(isset($_GET['action'])) {
    $action = $_GET['action'];
    $id = $_GET['id'] ?? 0;
    
    switch($action) {
        case 'verify':
            $db->query('UPDATE pembayaran SET status_bayar = "lunas", tanggal_bayar = NOW() WHERE id_pembayaran = :id');
            $db->bind(':id', $id);
            $db->execute();
            
            // Update booking status
            $db->query('UPDATE pemesanan p 
                       JOIN pembayaran py ON p.id_pemesanan = py.id_pemesanan
                       SET p.status_pemesanan = "disetujui" 
                       WHERE py.id_pembayaran = :id');
            $db->bind(':id', $id);
            $db->execute();
            
            setFlashMessage('success', 'Pembayaran berhasil diverifikasi.');
            break;
            
        case 'reject':
            $reason = $_GET['reason'] ?? '';
            $db->query('UPDATE pembayaran SET status_bayar = "gagal" WHERE id_pembayaran = :id');
            $db->bind(':id', $id);
            $db->execute();
            setFlashMessage('warning', 'Pembayaran ditolak.');
            break;
    }
    
    redirect('pembayaran.php');
}

// Get all payments with filters
$status = $_GET['status'] ?? '';
$search = $_GET['search'] ?? '';

$sql = 'SELECT py.*, p.*, u.nama, u.email,
               l.nama_lapangan, l.tipe_lapangan,
               j.tanggal, j.jam_mulai, j.jam_selesai
        FROM pembayaran py
        JOIN pemesanan p ON py.id_pemesanan = p.id_pemesanan
        JOIN user u ON p.id_user = u.id_user
        JOIN jadwal j ON p.id_jadwal = j.id_jadwal
        JOIN lapangan l ON j.id_lapangan = l.id_lapangan
        WHERE 1=1';
        
if($status) {
    $sql .= ' AND py.status_bayar = :status';
}

if($search) {
    $sql .= ' AND (u.nama LIKE :search OR u.email LIKE :search OR py.kode_pembayaran LIKE :search)';
}

$sql .= ' ORDER BY py.created_at DESC';

$db->query($sql);

if($status) {
    $db->bind(':status', $status);
}

if($search) {
    $db->bind(':search', "%$search%");
}

$payments = $db->resultSet();
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0"><i class="fas fa-money-bill-wave"></i> Management Pembayaran</h1>
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
                        <option value="pending" <?= $status == 'pending' ? 'selected' : '' ?>>Pending</option>
                        <option value="lunas" <?= $status == 'lunas' ? 'selected' : '' ?>>Lunas</option>
                        <option value="dp_lunas" <?= $status == 'dp_lunas' ? 'selected' : '' ?>>DP Lunas</option>
                        <option value="expired" <?= $status == 'expired' ? 'selected' : '' ?>>Expired</option>
                        <option value="gagal" <?= $status == 'gagal' ? 'selected' : '' ?>>Gagal</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Pencarian</label>
                    <input type="text" class="form-control" name="search" value="<?= htmlspecialchars($search) ?>" 
                           placeholder="Cari nama, email, atau kode pembayaran...">
                </div>
                <div class="col-md-4 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-search"></i> Filter
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Payments Table -->
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Kode</th>
                            <th>Customer</th>
                            <th>Lapangan</th>
                            <th>Tanggal</th>
                            <th>Tipe</th>
                            <th>Jumlah</th>
                            <th>Status</th>
                            <th>Expired</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($payments)): ?>
                            <tr>
                                <td colspan="9" class="text-center text-muted">Tidak ada data pembayaran</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach($payments as $payment): ?>
                            <tr>
                                <td><code><?= $payment->kode_pembayaran ?></code></td>
                                <td>
                                    <strong><?= htmlspecialchars($payment->nama) ?></strong><br>
                                    <small class="text-muted"><?= htmlspecialchars($payment->email) ?></small>
                                </td>
                                <td>
                                    <?= htmlspecialchars($payment->nama_lapangan) ?><br>
                                    <span class="badge bg-secondary"><?= $payment->tipe_lapangan ?></span>
                                </td>
                                <td>
                                    <?= formatDate($payment->tanggal) ?><br>
                                    <small class="text-muted"><?= substr($payment->jam_mulai, 0, 5) ?>-<?= substr($payment->jam_selesai, 0, 5) ?></small>
                                </td>
                                <td>
                                    <span class="badge bg-info"><?= strtoupper($payment->tipe_pembayaran) ?></span>
                                    <?php if($payment->tipe_pembayaran == 'dp'): ?>
                                        <br><small><?= $payment->dp_percent ?>%</small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?= formatCurrency($payment->jumlah_bayar) ?>
                                    <?php if($payment->sisa_tagihan > 0): ?>
                                        <br><small class="text-muted">Sisa: <?= formatCurrency($payment->sisa_tagihan) ?></small>
                                    <?php endif; ?>
                                </td>
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
                                <td>
                                    <?= formatDateTime($payment->expired_time) ?><br>
                                    <small class="text-muted <?= strtotime($payment->expired_time) < time() ? 'text-danger' : '' ?>">
                                        <?= strtotime($payment->expired_time) < time() ? 'Expired' : 'Active' ?>
                                    </small>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <button type="button" class="btn btn-outline-info" 
                                                onclick="showPaymentDetail(<?= $payment->id_pembayaran ?>)">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <?php if($payment->status_bayar == 'pending'): ?>
                                            <a href="pembayaran.php?action=verify&id=<?= $payment->id_pembayaran ?>" 
                                               class="btn btn-outline-success"
                                               onclick="return confirm('Verifikasi pembayaran ini?')">
                                                <i class="fas fa-check"></i>
                                            </a>
                                            <button type="button" class="btn btn-outline-danger"
                                                    onclick="showRejectPaymentModal(<?= $payment->id_pembayaran ?>)">
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
                <h5 class="modal-title"><i class="fas fa-info-circle"></i> Detail Pembayaran</h5>
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
                <h5 class="modal-title"><i class="fas fa-times-circle"></i> Tolak Pembayaran</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="GET" action="pembayaran.php">
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
                    <button type="submit" class="btn btn-danger">Tolak Pembayaran</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function showPaymentDetail(paymentId) {
    fetch(`ajax/get_payment_detail.php?id=${paymentId}`)
        .then(response => response.text())
        .then(data => {
            document.getElementById('detailContent').innerHTML = data;
            const modal = new bootstrap.Modal(document.getElementById('detailModal'));
            modal.show();
        });
}

function showRejectPaymentModal(paymentId) {
    document.getElementById('rejectId').value = paymentId;
    const modal = new bootstrap.Modal(document.getElementById('rejectModal'));
    modal.show();
}
</script>

<?php require_once '../includes/admin-footer.php'; ?>