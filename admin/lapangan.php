<?php
$page_title = 'Kelola Lapangan';
require_once '../includes/admin-header.php';
require_once '../includes/database.php';
require_once '../includes/functions.php';

$db = new Database();

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_field'])) {
        $nama_lapangan = $_POST['nama_lapangan'];
        $tipe_lapangan = $_POST['tipe_lapangan'];
        $harga_per_jam = $_POST['harga_per_jam'];
        $deskripsi = $_POST['deskripsi'];
        $status_lapangan = $_POST['status_lapangan'];
        
        $db->query('INSERT INTO lapangan 
                   (nama_lapangan, tipe_lapangan, harga_per_jam, deskripsi, status_lapangan) 
                   VALUES (:nama, :tipe, :harga, :deskripsi, :status)');
        $db->bind(':nama', $nama_lapangan);
        $db->bind(':tipe', $tipe_lapangan);
        $db->bind(':harga', $harga_per_jam);
        $db->bind(':deskripsi', $deskripsi);
        $db->bind(':status', $status_lapangan);
        
        if ($db->execute()) {
            setFlashMessage('success', 'Lapangan berhasil ditambahkan.');
        } else {
            setFlashMessage('danger', 'Gagal menambahkan lapangan.');
        }
    }
    
    if (isset($_POST['update_field'])) {
        $id = $_POST['id'];
        $nama_lapangan = $_POST['nama_lapangan'];
        $tipe_lapangan = $_POST['tipe_lapangan'];
        $harga_per_jam = $_POST['harga_per_jam'];
        $deskripsi = $_POST['deskripsi'];
        $status_lapangan = $_POST['status_lapangan'];
        
        $db->query('UPDATE lapangan 
                   SET nama_lapangan = :nama, 
                       tipe_lapangan = :tipe, 
                       harga_per_jam = :harga, 
                       deskripsi = :deskripsi, 
                       status_lapangan = :status 
                   WHERE id_lapangan = :id');
        $db->bind(':id', $id);
        $db->bind(':nama', $nama_lapangan);
        $db->bind(':tipe', $tipe_lapangan);
        $db->bind(':harga', $harga_per_jam);
        $db->bind(':deskripsi', $deskripsi);
        $db->bind(':status', $status_lapangan);
        
        if ($db->execute()) {
            setFlashMessage('success', 'Lapangan berhasil diperbarui.');
        } else {
            setFlashMessage('danger', 'Gagal memperbarui lapangan.');
        }
    }
}

// Handle delete
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    
    // Check if field has active bookings
    $db->query('SELECT COUNT(*) as total FROM jadwal j
               JOIN pemesanan p ON j.id_jadwal = p.id_jadwal
               WHERE j.id_lapangan = :id 
               AND p.status_pemesanan IN ("menunggu", "disetujui")');
    $db->bind(':id', $id);
    $hasBookings = $db->single();
    
    if ($hasBookings->total > 0) {
        setFlashMessage('danger', 'Tidak dapat menghapus lapangan yang memiliki booking aktif.');
    } else {
        $db->query('DELETE FROM lapangan WHERE id_lapangan = :id');
        $db->bind(':id', $id);
        
        if ($db->execute()) {
            // Also delete related schedules
            $db->query('DELETE FROM jadwal WHERE id_lapangan = :id');
            $db->bind(':id', $id);
            $db->execute();
            
            // Delete templates
            $db->query('DELETE FROM template_jadwal WHERE id_lapangan = :id');
            $db->bind(':id', $id);
            $db->execute();
            
            setFlashMessage('success', 'Lapangan dan semua jadwal terkait berhasil dihapus.');
        } else {
            setFlashMessage('danger', 'Gagal menghapus lapangan.');
        }
    }
    
    redirect('lapangan.php');
}

// Get all fields
$db->query('SELECT * FROM lapangan ORDER BY status_lapangan DESC, tipe_lapangan, nama_lapangan');
$fields = $db->resultSet();

// Get field statistics
$db->query('SELECT 
           COUNT(*) as total,
           SUM(CASE WHEN status_lapangan = "aktif" THEN 1 ELSE 0 END) as active,
           SUM(CASE WHEN status_lapangan = "tidak_aktif" THEN 1 ELSE 0 END) as inactive
           FROM lapangan');
$stats = $db->single();

// Field types
$field_types = ['Futsal', 'Badminton', 'Voli', 'Basket', 'Tennis'];
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0"><i class="fas fa-futbol"></i> Kelola Lapangan</h1>
        <div>
            <a href="dashboard.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Kembali
            </a>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addFieldModal">
                <i class="fas fa-plus"></i> Tambah Lapangan
            </button>
        </div>
    </div>
    
    <!-- Statistics -->
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card bg-primary text-white">
                <div class="card-body text-center">
                    <h6 class="text-white-50">Total Lapangan</h6>
                    <h2><?= $stats->total ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-success text-white">
                <div class="card-body text-center">
                    <h6 class="text-white-50">Aktif</h6>
                    <h2><?= $stats->active ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-warning text-white">
                <div class="card-body text-center">
                    <h6 class="text-white-50">Tidak Aktif</h6>
                    <h2><?= $stats->inactive ?></h2>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Fields Table -->
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Nama Lapangan</th>
                            <th>Tipe</th>
                            <th>Harga/Jam</th>
                            <th>Status</th>
                            <th>Dibuat</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($fields)): ?>
                            <tr>
                                <td colspan="7" class="text-center text-muted">Belum ada lapangan</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($fields as $index => $field): ?>
                            <tr>
                                <td><?= $index + 1 ?></td>
                                <td>
                                    <strong><?= htmlspecialchars($field->nama_lapangan) ?></strong>
                                    <?php if ($field->deskripsi): ?>
                                        <br><small class="text-muted"><?= substr($field->deskripsi, 0, 50) ?>...</small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-info"><?= $field->tipe_lapangan ?></span>
                                </td>
                                <td><?= formatCurrency($field->harga_per_jam) ?></td>
                                <td>
                                    <?php if ($field->status_lapangan === 'aktif'): ?>
                                        <span class="badge bg-success">Aktif</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">Tidak Aktif</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <small><?= formatDate($field->created_at) ?></small>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <button type="button" class="btn btn-outline-primary" 
                                                onclick="editField(<?= $field->id_lapangan ?>)">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <a href="lapangan.php?delete=<?= $field->id_lapangan ?>" 
                                           class="btn btn-outline-danger"
                                           onclick="return confirm('Hapus lapangan ini? Semua jadwal dan template terkait juga akan dihapus.')">
                                            <i class="fas fa-trash"></i>
                                        </a>
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
    
    <!-- Field Type Distribution -->
    <div class="row mt-4">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0"><i class="fas fa-chart-pie"></i> Distribusi Tipe Lapangan</h6>
                </div>
                <div class="card-body">
                    <?php
                    $db->query('SELECT tipe_lapangan, COUNT(*) as total 
                               FROM lapangan 
                               WHERE status_lapangan = "aktif"
                               GROUP BY tipe_lapangan');
                    $type_stats = $db->resultSet();
                    
                    if (empty($type_stats)): ?>
                        <p class="text-muted">Tidak ada data</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Tipe</th>
                                        <th>Jumlah</th>
                                        <th>Persentase</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($type_stats as $stat): ?>
                                    <tr>
                                        <td>
                                            <span class="badge bg-info"><?= $stat->tipe_lapangan ?></span>
                                        </td>
                                        <td><?= $stat->total ?></td>
                                        <td>
                                            <?php 
                                            $percentage = ($stat->total / $stats->active) * 100;
                                            echo number_format($percentage, 1) . '%';
                                            ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0"><i class="fas fa-info-circle"></i> Informasi</h6>
                </div>
                <div class="card-body">
                    <div class="alert alert-info">
                        <h6>Status Lapangan:</h6>
                        <ul class="mb-0">
                            <li><span class="badge bg-success">Aktif</span> - Dapat dibooking</li>
                            <li><span class="badge bg-danger">Tidak Aktif</span> - Tidak dapat dibooking</li>
                        </ul>
                    </div>
                    <p class="small text-muted">
                        Lapangan yang tidak aktif tidak akan muncul di halaman booking pengguna.
                        Hapus lapangan hanya jika tidak ada booking aktif.
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Field Modal -->
<div class="modal fade" id="addFieldModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="fas fa-plus"></i> Tambah Lapangan</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Nama Lapangan</label>
                        <input type="text" class="form-control" name="nama_lapangan" 
                               placeholder="Contoh: Lapangan Futsal A" required>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Tipe Lapangan</label>
                            <select class="form-select" name="tipe_lapangan" required>
                                <option value="">Pilih Tipe</option>
                                <?php foreach ($field_types as $type): ?>
                                <option value="<?= $type ?>"><?= $type ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Harga per Jam</label>
                            <div class="input-group">
                                <span class="input-group-text">Rp</span>
                                <input type="number" class="form-control" name="harga_per_jam" 
                                       value="100000" min="0" step="1000" required>
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Deskripsi</label>
                        <textarea class="form-control" name="deskripsi" rows="3" 
                                  placeholder="Deskripsi lapangan..."></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <select class="form-select" name="status_lapangan" required>
                            <option value="aktif" selected>Aktif</option>
                            <option value="tidak_aktif">Tidak Aktif</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" name="add_field" class="btn btn-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Field Modal -->
<div class="modal fade" id="editFieldModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title"><i class="fas fa-edit"></i> Edit Lapangan</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="id" id="editFieldId">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Nama Lapangan</label>
                        <input type="text" class="form-control" name="nama_lapangan" 
                               id="editFieldName" required>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Tipe Lapangan</label>
                            <select class="form-select" name="tipe_lapangan" id="editFieldType" required>
                                <?php foreach ($field_types as $type): ?>
                                <option value="<?= $type ?>"><?= $type ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Harga per Jam</label>
                            <div class="input-group">
                                <span class="input-group-text">Rp</span>
                                <input type="number" class="form-control" name="harga_per_jam" 
                                       id="editFieldPrice" min="0" step="1000" required>
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Deskripsi</label>
                        <textarea class="form-control" name="deskripsi" 
                                  id="editFieldDesc" rows="3"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <select class="form-select" name="status_lapangan" id="editFieldStatus" required>
                            <option value="aktif">Aktif</option>
                            <option value="tidak_aktif">Tidak Aktif</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" name="update_field" class="btn btn-primary">Update</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function editField(id) {
    fetch(`ajax/get_field.php?id=${id}`)
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                alert(data.error);
                return;
            }
            
            document.getElementById('editFieldId').value = data.id_lapangan;
            document.getElementById('editFieldName').value = data.nama_lapangan;
            document.getElementById('editFieldType').value = data.tipe_lapangan;
            document.getElementById('editFieldPrice').value = data.harga_per_jam;
            document.getElementById('editFieldDesc').value = data.deskripsi || '';
            document.getElementById('editFieldStatus').value = data.status_lapangan;
            
            const modal = new bootstrap.Modal(document.getElementById('editFieldModal'));
            modal.show();
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Gagal memuat data lapangan');
        });
}
</script>

<?php require_once '../includes/admin-footer.php'; ?>