<?php
$page_title = 'Hari Libur';
require_once '../includes/admin-header.php';
require_once '../includes/database.php';
require_once '../includes/functions.php';

$db = new Database();

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_holiday'])) {
        $tanggal = $_POST['tanggal'];
        $keterangan = $_POST['keterangan'];
        $status = $_POST['status'];
        
        $db->query('INSERT INTO hari_libur (tanggal, keterangan, status) 
                   VALUES (:tanggal, :keterangan, :status)');
        $db->bind(':tanggal', $tanggal);
        $db->bind(':keterangan', $keterangan);
        $db->bind(':status', $status);
        
        if ($db->execute()) {
            setFlashMessage('success', 'Hari libur berhasil ditambahkan.');
        } else {
            setFlashMessage('danger', 'Gagal menambahkan hari libur.');
        }
    }
    
    if (isset($_POST['update_holiday'])) {
        $id = $_POST['id'];
        $tanggal = $_POST['tanggal'];
        $keterangan = $_POST['keterangan'];
        $status = $_POST['status'];
        
        $db->query('UPDATE hari_libur 
                   SET tanggal = :tanggal, keterangan = :keterangan, status = :status 
                   WHERE id_libur = :id');
        $db->bind(':id', $id);
        $db->bind(':tanggal', $tanggal);
        $db->bind(':keterangan', $keterangan);
        $db->bind(':status', $status);
        
        if ($db->execute()) {
            setFlashMessage('success', 'Hari libur berhasil diperbarui.');
        } else {
            setFlashMessage('danger', 'Gagal memperbarui hari libur.');
        }
    }
}

// Handle delete
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    
    $db->query('DELETE FROM hari_libur WHERE id_libur = :id');
    $db->bind(':id', $id);
    
    if ($db->execute()) {
        setFlashMessage('success', 'Hari libur berhasil dihapus.');
    } else {
        setFlashMessage('danger', 'Gagal menghapus hari libur.');
    }
    
    redirect('hari-libur.php');
}

// Get all holidays
$db->query('SELECT * FROM hari_libur ORDER BY tanggal DESC');
$holidays = $db->resultSet();
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0"><i class="fas fa-calendar-times"></i> Kelola Hari Libur</h1>
        <div>
            <a href="dashboard.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Kembali
            </a>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addHolidayModal">
                <i class="fas fa-plus"></i> Tambah Hari Libur
            </button>
        </div>
    </div>
    
    <!-- Holidays Table -->
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Tanggal</th>
                            <th>Keterangan</th>
                            <th>Status</th>
                            <th>Dibuat</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($holidays)): ?>
                            <tr>
                                <td colspan="6" class="text-center text-muted">Belum ada hari libur</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($holidays as $index => $holiday): ?>
                            <tr>
                                <td><?= $index + 1 ?></td>
                                <td>
                                    <strong><?= formatDate($holiday->tanggal) ?></strong><br>
                                    <small class="text-muted"><?= date('l', strtotime($holiday->tanggal)) ?></small>
                                </td>
                                <td><?= htmlspecialchars($holiday->keterangan) ?></td>
                                <td>
                                    <?php if ($holiday->status === 'libur'): ?>
                                        <span class="badge bg-danger">Libur</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning">Operasional Khusus</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <small><?= formatDate($holiday->created_at) ?></small><br>
                                    <small class="text-muted"><?= date('H:i', strtotime($holiday->created_at)) ?></small>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <button type="button" class="btn btn-outline-primary" 
                                                onclick="editHoliday(<?= $holiday->id_libur ?>)">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <a href="hari-libur.php?delete=<?= $holiday->id_libur ?>" 
                                           class="btn btn-outline-danger"
                                           onclick="return confirm('Hapus hari libur ini?')">
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
    
    <!-- Info Section -->
    <div class="row mt-4">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0"><i class="fas fa-info-circle"></i> Informasi</h6>
                </div>
                <div class="card-body">
                    <div class="alert alert-info">
                        <h6>Status Hari Libur:</h6>
                        <ul class="mb-0">
                            <li><span class="badge bg-danger">Libur</span> - Lapangan tutup total</li>
                            <li><span class="badge bg-warning">Operasional Khusus</span> - Lapangan buka dengan jam terbatas</li>
                        </ul>
                    </div>
                    <p class="small text-muted">
                        Hari libur akan mempengaruhi ketersediaan jadwal. 
                        Saat hari libur, tidak ada booking yang bisa dilakukan.
                    </p>
                </div>
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0"><i class="fas fa-calendar-alt"></i> Jadwal Libur Mendatang</h6>
                </div>
                <div class="card-body">
                    <?php
                    $db->query('SELECT * FROM hari_libur 
                               WHERE tanggal >= CURDATE() 
                               ORDER BY tanggal ASC LIMIT 5');
                    $upcoming = $db->resultSet();
                    
                    if (empty($upcoming)): ?>
                        <p class="text-muted">Tidak ada jadwal libur mendatang</p>
                    <?php else: ?>
                        <ul class="list-group list-group-flush">
                            <?php foreach ($upcoming as $holiday): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <div>
                                    <strong><?= formatDate($holiday->tanggal) ?></strong><br>
                                    <small class="text-muted"><?= $holiday->keterangan ?></small>
                                </div>
                                <span class="badge bg-danger">Libur</span>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Holiday Modal -->
<div class="modal fade" id="addHolidayModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="fas fa-plus"></i> Tambah Hari Libur</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Tanggal</label>
                        <input type="date" class="form-control" name="tanggal" required 
                               min="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Keterangan</label>
                        <input type="text" class="form-control" name="keterangan" required 
                               placeholder="Contoh: Hari Raya Idul Fitri, Natal, Tahun Baru">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <select class="form-select" name="status" required>
                            <option value="libur">Libur (Tutup Total)</option>
                            <option value="operasional_khusus">Operasional Khusus</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" name="add_holiday" class="btn btn-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Holiday Modal -->
<div class="modal fade" id="editHolidayModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title"><i class="fas fa-edit"></i> Edit Hari Libur</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="id" id="editHolidayId">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Tanggal</label>
                        <input type="date" class="form-control" name="tanggal" id="editHolidayDate" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Keterangan</label>
                        <input type="text" class="form-control" name="keterangan" id="editHolidayDesc" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <select class="form-select" name="status" id="editHolidayStatus" required>
                            <option value="libur">Libur (Tutup Total)</option>
                            <option value="operasional_khusus">Operasional Khusus</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" name="update_holiday" class="btn btn-primary">Update</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function editHoliday(id) {
    fetch(`ajax/get_holiday.php?id=${id}`)
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                alert(data.error);
                return;
            }
            
            document.getElementById('editHolidayId').value = data.id_libur;
            document.getElementById('editHolidayDate').value = data.tanggal;
            document.getElementById('editHolidayDesc').value = data.keterangan;
            document.getElementById('editHolidayStatus').value = data.status;
            
            const modal = new bootstrap.Modal(document.getElementById('editHolidayModal'));
            modal.show();
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Gagal memuat data hari libur');
        });
}
</script>

<?php require_once '../includes/admin-footer.php'; ?>