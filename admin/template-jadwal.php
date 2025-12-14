<?php
$page_title = 'Template Jadwal';
require_once '../includes/admin-header.php';
require_once '../includes/database.php';
require_once '../includes/functions.php';

$db = new Database();

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_template'])) {
        $id_lapangan = $_POST['id_lapangan'];
        $hari = $_POST['hari'];
        $jam_mulai = $_POST['jam_mulai'];
        $jam_selesai = $_POST['jam_selesai'];
        $harga = $_POST['harga'];
        
        $db->query('INSERT INTO template_jadwal 
                   (id_lapangan, hari, jam_mulai, jam_selesai, harga, status_aktif) 
                   VALUES (:id_lapangan, :hari, :jam_mulai, :jam_selesai, :harga, 1)');
        $db->bind(':id_lapangan', $id_lapangan);
        $db->bind(':hari', $hari);
        $db->bind(':jam_mulai', $jam_mulai);
        $db->bind(':jam_selesai', $jam_selesai);
        $db->bind(':harga', $harga);
        
        if ($db->execute()) {
            setFlashMessage('success', 'Template jadwal berhasil ditambahkan.');
        } else {
            setFlashMessage('danger', 'Gagal menambahkan template jadwal.');
        }
    }
    
    if (isset($_POST['update_template'])) {
        $id = $_POST['id'];
        $id_lapangan = $_POST['id_lapangan'];
        $hari = $_POST['hari'];
        $jam_mulai = $_POST['jam_mulai'];
        $jam_selesai = $_POST['jam_selesai'];
        $harga = $_POST['harga'];
        $status_aktif = isset($_POST['status_aktif']) ? 1 : 0;
        
        $db->query('UPDATE template_jadwal 
                   SET id_lapangan = :id_lapangan, 
                       hari = :hari, 
                       jam_mulai = :jam_mulai, 
                       jam_selesai = :jam_selesai, 
                       harga = :harga, 
                       status_aktif = :status_aktif 
                   WHERE id_template = :id');
        $db->bind(':id', $id);
        $db->bind(':id_lapangan', $id_lapangan);
        $db->bind(':hari', $hari);
        $db->bind(':jam_mulai', $jam_mulai);
        $db->bind(':jam_selesai', $jam_selesai);
        $db->bind(':harga', $harga);
        $db->bind(':status_aktif', $status_aktif);
        
        if ($db->execute()) {
            setFlashMessage('success', 'Template jadwal berhasil diperbarui.');
        } else {
            setFlashMessage('danger', 'Gagal memperbarui template jadwal.');
        }
    }
}

// Handle delete
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    
    $db->query('DELETE FROM template_jadwal WHERE id_template = :id');
    $db->bind(':id', $id);
    
    if ($db->execute()) {
        setFlashMessage('success', 'Template jadwal berhasil dihapus.');
    } else {
        setFlashMessage('danger', 'Gagal menghapus template jadwal.');
    }
    
    redirect('template-jadwal.php');
}

// Get all templates
$db->query('SELECT t.*, l.nama_lapangan, l.tipe_lapangan 
           FROM template_jadwal t
           JOIN lapangan l ON t.id_lapangan = l.id_lapangan
           ORDER BY t.hari, t.jam_mulai');
$templates = $db->resultSet();

// Get all fields
$db->query('SELECT * FROM lapangan WHERE status_lapangan = "aktif" ORDER BY nama_lapangan');
$fields = $db->resultSet();

// Days of week
$days = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'];
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0"><i class="fas fa-calendar-week"></i> Template Jadwal</h1>
        <div>
            <a href="dashboard.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Kembali
            </a>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addTemplateModal">
                <i class="fas fa-plus"></i> Tambah Template
            </button>
        </div>
    </div>
    
    <!-- Templates Table -->
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Lapangan</th>
                            <th>Hari</th>
                            <th>Jam</th>
                            <th>Harga</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($templates)): ?>
                            <tr>
                                <td colspan="7" class="text-center text-muted">Belum ada template jadwal</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($templates as $index => $template): ?>
                            <tr>
                                <td><?= $index + 1 ?></td>
                                <td>
                                    <strong><?= $template->nama_lapangan ?></strong><br>
                                    <span class="badge bg-secondary"><?= $template->tipe_lapangan ?></span>
                                </td>
                                <td><?= $template->hari ?></td>
                                <td>
                                    <?= substr($template->jam_mulai, 0, 5) ?> - <?= substr($template->jam_selesai, 0, 5) ?>
                                </td>
                                <td><?= formatCurrency($template->harga) ?></td>
                                <td>
                                    <?php if ($template->status_aktif): ?>
                                        <span class="badge bg-success">Aktif</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">Nonaktif</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <button type="button" class="btn btn-outline-primary" 
                                                onclick="editTemplate(<?= $template->id_template ?>)">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <a href="template-jadwal.php?delete=<?= $template->id_template ?>" 
                                           class="btn btn-outline-danger"
                                           onclick="return confirm('Hapus template jadwal ini?')">
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
                    <h6 class="mb-0"><i class="fas fa-info-circle"></i> Informasi Template</h6>
                </div>
                <div class="card-body">
                    <p>Template jadwal digunakan untuk:</p>
                    <ul>
                        <li>Generate jadwal otomatis</li>
                        <li>Konsistensi harga berdasarkan hari dan jam</li>
                        <li>Pengaturan jam operasional per hari</li>
                    </ul>
                    <div class="alert alert-warning">
                        <small>
                            <i class="fas fa-exclamation-triangle"></i>
                            Template yang nonaktif tidak akan digunakan saat generate jadwal otomatis.
                        </small>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0"><i class="fas fa-calendar-check"></i> Generate dari Template</h6>
                </div>
                <div class="card-body">
                    <form method="GET" action="jadwal.php" class="row g-3">
                        <input type="hidden" name="action" value="generate">
                        <div class="col-md-6">
                            <label class="form-label">Mulai Tanggal</label>
                            <input type="date" class="form-control" name="start_date" 
                                   value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Jumlah Hari</label>
                            <input type="number" class="form-control" name="days" 
                                   value="7" min="1" max="90" required>
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <button type="submit" class="btn btn-success w-100">
                                Generate
                            </button>
                        </div>
                    </form>
                    <p class="small text-muted mt-2 mb-0">
                        Generate jadwal berdasarkan template aktif untuk periode tertentu.
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Template Modal -->
<div class="modal fade" id="addTemplateModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="fas fa-plus"></i> Tambah Template Jadwal</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Lapangan</label>
                        <select class="form-select" name="id_lapangan" required>
                            <option value="">Pilih Lapangan</option>
                            <?php foreach ($fields as $field): ?>
                            <option value="<?= $field->id_lapangan ?>">
                                <?= $field->nama_lapangan ?> (<?= $field->tipe_lapangan ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Hari</label>
                            <select class="form-select" name="hari" required>
                                <option value="">Pilih Hari</option>
                                <?php foreach ($days as $day): ?>
                                <option value="<?= $day ?>"><?= $day ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Harga</label>
                            <div class="input-group">
                                <span class="input-group-text">Rp</span>
                                <input type="number" class="form-control" name="harga" 
                                       value="100000" min="0" step="1000" required>
                            </div>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Jam Mulai</label>
                            <select class="form-select" name="jam_mulai" required>
                                <?php for ($hour = 8; $hour <= 21; $hour++): ?>
                                <option value="<?= sprintf('%02d:00', $hour) ?>">
                                    <?= sprintf('%02d:00', $hour) ?>
                                </option>
                                <option value="<?= sprintf('%02d:30', $hour) ?>">
                                    <?= sprintf('%02d:30', $hour) ?>
                                </option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Jam Selesai</label>
                            <select class="form-select" name="jam_selesai" required>
                                <?php for ($hour = 9; $hour <= 22; $hour++): ?>
                                <option value="<?= sprintf('%02d:00', $hour) ?>">
                                    <?= sprintf('%02d:00', $hour) ?>
                                </option>
                                <option value="<?= sprintf('%02d:30', $hour) ?>">
                                    <?= sprintf('%02d:30', $hour) ?>
                                </option>
                                <?php endfor; ?>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" name="add_template" class="btn btn-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Template Modal -->
<div class="modal fade" id="editTemplateModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title"><i class="fas fa-edit"></i> Edit Template Jadwal</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="id" id="editTemplateId">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Lapangan</label>
                        <select class="form-select" name="id_lapangan" id="editTemplateLapangan" required>
                            <?php foreach ($fields as $field): ?>
                            <option value="<?= $field->id_lapangan ?>">
                                <?= $field->nama_lapangan ?> (<?= $field->tipe_lapangan ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Hari</label>
                            <select class="form-select" name="hari" id="editTemplateHari" required>
                                <?php foreach ($days as $day): ?>
                                <option value="<?= $day ?>"><?= $day ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Harga</label>
                            <div class="input-group">
                                <span class="input-group-text">Rp</span>
                                <input type="number" class="form-control" name="harga" 
                                       id="editTemplateHarga" min="0" step="1000" required>
                            </div>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Jam Mulai</label>
                            <select class="form-select" name="jam_mulai" id="editTemplateJamMulai" required>
                                <?php for ($hour = 8; $hour <= 21; $hour++): ?>
                                <option value="<?= sprintf('%02d:00', $hour) ?>">
                                    <?= sprintf('%02d:00', $hour) ?>
                                </option>
                                <option value="<?= sprintf('%02d:30', $hour) ?>">
                                    <?= sprintf('%02d:30', $hour) ?>
                                </option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Jam Selesai</label>
                            <select class="form-select" name="jam_selesai" id="editTemplateJamSelesai" required>
                                <?php for ($hour = 9; $hour <= 22; $hour++): ?>
                                <option value="<?= sprintf('%02d:00', $hour) ?>">
                                    <?= sprintf('%02d:00', $hour) ?>
                                </option>
                                <option value="<?= sprintf('%02d:30', $hour) ?>">
                                    <?= sprintf('%02d:30', $hour) ?>
                                </option>
                                <?php endfor; ?>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" 
                                   name="status_aktif" id="editTemplateStatus" value="1" checked>
                            <label class="form-check-label" for="editTemplateStatus">
                                Template Aktif
                            </label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" name="update_template" class="btn btn-primary">Update</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function editTemplate(id) {
    fetch(`ajax/get_template.php?id=${id}`)
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                alert(data.error);
                return;
            }
            
            document.getElementById('editTemplateId').value = data.id_template;
            document.getElementById('editTemplateLapangan').value = data.id_lapangan;
            document.getElementById('editTemplateHari').value = data.hari;
            document.getElementById('editTemplateHarga').value = data.harga;
            document.getElementById('editTemplateJamMulai').value = data.jam_mulai;
            document.getElementById('editTemplateJamSelesai').value = data.jam_selesai;
            document.getElementById('editTemplateStatus').checked = data.status_aktif == 1;
            
            const modal = new bootstrap.Modal(document.getElementById('editTemplateModal'));
            modal.show();
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Gagal memuat data template');
        });
}
</script>

<?php require_once '../includes/admin-footer.php'; ?>