<?php
$page_title = 'Jadwal Lapangan';
require_once '../includes/admin-header.php';
require_once '../includes/database.php';
require_once '../includes/functions.php';

$db = new Database();

// Handle generate schedule
if (isset($_GET['action']) && $_GET['action'] === 'generate') {
    $start_date = $_GET['start_date'] ?? date('Y-m-d');
    $days = $_GET['days'] ?? 7;
    
    generateSchedule($start_date, $days);
    setFlashMessage('success', 'Jadwal berhasil digenerate untuk ' . $days . ' hari ke depan.');
    redirect('jadwal.php');
}

// Handle delete schedule
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    
    $db->query('DELETE FROM jadwal WHERE id_jadwal = :id');
    $db->bind(':id', $id);
    
    if ($db->execute()) {
        setFlashMessage('success', 'Jadwal berhasil dihapus.');
    } else {
        setFlashMessage('danger', 'Gagal menghapus jadwal.');
    }
    
    redirect('jadwal.php');
}

// Handle update schedule status
if (isset($_GET['update_status'])) {
    $id = $_GET['update_status'];
    $status = $_GET['status'] ?? 'tersedia';
    
    $db->query('UPDATE jadwal SET status_ketersediaan = :status WHERE id_jadwal = :id');
    $db->bind(':status', $status);
    $db->bind(':id', $id);
    
    if ($db->execute()) {
        setFlashMessage('success', 'Status jadwal berhasil diperbarui.');
    } else {
        setFlashMessage('danger', 'Gagal memperbarui status jadwal.');
    }
    
    redirect('jadwal.php');
}

// Get filter parameters
$tanggal = $_GET['tanggal'] ?? date('Y-m-d');
$lapangan_id = $_GET['lapangan'] ?? '';
$status = $_GET['status'] ?? '';

// Build query
$sql = 'SELECT j.*, l.nama_lapangan, l.tipe_lapangan, l.harga_per_jam 
        FROM jadwal j
        JOIN lapangan l ON j.id_lapangan = l.id_lapangan
        WHERE j.tanggal = :tanggal';
        
$params = [':tanggal' => $tanggal];

if ($lapangan_id) {
    $sql .= ' AND j.id_lapangan = :lapangan_id';
    $params[':lapangan_id'] = $lapangan_id;
}

if ($status) {
    $sql .= ' AND j.status_ketersediaan = :status';
    $params[':status'] = $status;
}

$sql .= ' ORDER BY j.jam_mulai ASC';

$db->query($sql);
foreach ($params as $key => $value) {
    $db->bind($key, $value);
}

$schedules = $db->resultSet();

// Get all fields for filter
$db->query('SELECT * FROM lapangan WHERE status_lapangan = "aktif" ORDER BY nama_lapangan');
$fields = $db->resultSet();
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0"><i class="fas fa-calendar-day"></i> Kelola Jadwal</h1>
        <div>
            <a href="dashboard.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Kembali
            </a>
            <a href="jadwal.php?action=generate" class="btn btn-success"
               onclick="return confirm('Generate jadwal untuk 7 hari ke depan?')">
                <i class="fas fa-calendar-plus"></i> Generate Jadwal
            </a>
        </div>
    </div>
    
    <!-- Filter Section -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Tanggal</label>
                    <input type="date" class="form-control" name="tanggal" value="<?= $tanggal ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Lapangan</label>
                    <select class="form-select" name="lapangan">
                        <option value="">Semua Lapangan</option>
                        <?php foreach ($fields as $field): ?>
                        <option value="<?= $field->id_lapangan ?>" <?= $lapangan_id == $field->id_lapangan ? 'selected' : '' ?>>
                            <?= $field->nama_lapangan ?> (<?= $field->tipe_lapangan ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Status</label>
                    <select class="form-select" name="status">
                        <option value="">Semua Status</option>
                        <option value="tersedia" <?= $status == 'tersedia' ? 'selected' : '' ?>>Tersedia</option>
                        <option value="dibooking" <?= $status == 'dibooking' ? 'selected' : '' ?>>Dibooking</option>
                        <option value="blokir" <?= $status == 'blokir' ? 'selected' : '' ?>>Blokir</option>
                    </select>
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-search"></i> Filter
                    </button>
                </div>
            </form>
            
            <!-- Quick Date Navigation -->
            <div class="row mt-3">
                <div class="col-12">
                    <div class="btn-group btn-group-sm">
                        <?php
                        $dates = [
                            'Hari Ini' => date('Y-m-d'),
                            'Besok' => date('Y-m-d', strtotime('+1 day')),
                            'Lusa' => date('Y-m-d', strtotime('+2 days')),
                            '3 Hari Lagi' => date('Y-m-d', strtotime('+3 days')),
                            'Minggu Depan' => date('Y-m-d', strtotime('+7 days'))
                        ];
                        
                        foreach ($dates as $label => $date):
                            $active = $tanggal == $date ? 'btn-primary' : 'btn-outline-primary';
                        ?>
                        <a href="jadwal.php?tanggal=<?= $date ?>" 
                           class="btn <?= $active ?>">
                            <?= $label ?>
                        </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Schedules Table -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Jadwal Tanggal <?= formatDate($tanggal) ?></h5>
            <div>
                <span class="badge bg-success">Tersedia</span>
                <span class="badge bg-warning">Dibooking</span>
                <span class="badge bg-danger">Blokir</span>
            </div>
        </div>
        <div class="card-body">
            <?php
            // Check if holiday
            $db->query('SELECT * FROM hari_libur WHERE tanggal = :tanggal');
            $db->bind(':tanggal', $tanggal);
            $holiday = $db->single();
            
            if ($holiday):
            ?>
            <div class="alert alert-warning mb-4">
                <h6><i class="fas fa-calendar-times"></i> Peringatan: Hari Libur</h6>
                <p class="mb-0">
                    Tanggal <?= formatDate($tanggal) ?> adalah hari libur. 
                    <?= $holiday->keterangan ?>
                </p>
            </div>
            <?php endif; ?>
            
            <?php if (empty($schedules)): ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> Tidak ada jadwal untuk tanggal ini.
                    <a href="jadwal.php?action=generate&start_date=<?= $tanggal ?>&days=1" 
                       class="alert-link">
                        Klik di sini untuk generate jadwal
                    </a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Jam</th>
                                <th>Lapangan</th>
                                <th>Tipe</th>
                                <th>Harga</th>
                                <th>Status</th>
                                <th>Dibuat</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($schedules as $schedule): ?>
                            <tr>
                                <td>
                                    <strong><?= substr($schedule->jam_mulai, 0, 5) ?> - <?= substr($schedule->jam_selesai, 0, 5) ?></strong>
                                </td>
                                <td>
                                    <?= $schedule->nama_lapangan ?>
                                </td>
                                <td>
                                    <span class="badge bg-secondary"><?= $schedule->tipe_lapangan ?></span>
                                </td>
                                <td>
                                    <?= formatCurrency($schedule->harga ?: $schedule->harga_per_jam) ?>
                                </td>
                                <td>
                                    <?php 
                                    $status_class = '';
                                    switch($schedule->status_ketersediaan) {
                                        case 'tersedia': $status_class = 'bg-success'; break;
                                        case 'dibooking': $status_class = 'bg-warning'; break;
                                        case 'blokir': $status_class = 'bg-danger'; break;
                                    }
                                    ?>
                                    <span class="badge <?= $status_class ?>">
                                        <?= ucfirst($schedule->status_ketersediaan) ?>
                                    </span>
                                </td>
                                <td>
                                    <small><?= formatDate($schedule->created_at) ?></small>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <div class="dropdown">
                                            <button class="btn btn-outline-secondary dropdown-toggle" 
                                                    type="button" data-bs-toggle="dropdown">
                                                <i class="fas fa-cog"></i>
                                            </button>
                                            <ul class="dropdown-menu">
                                                <li>
                                                    <a class="dropdown-item" 
                                                       href="jadwal.php?update_status=<?= $schedule->id_jadwal ?>&status=tersedia&tanggal=<?= $tanggal ?>">
                                                        <i class="fas fa-check text-success"></i> Set Tersedia
                                                    </a>
                                                </li>
                                                <li>
                                                    <a class="dropdown-item" 
                                                       href="jadwal.php?update_status=<?= $schedule->id_jadwal ?>&status=blokir&tanggal=<?= $tanggal ?>">
                                                        <i class="fas fa-times text-danger"></i> Set Blokir
                                                    </a>
                                                </li>
                                                <li><hr class="dropdown-divider"></li>
                                                <li>
                                                    <a class="dropdown-item text-danger" 
                                                       href="jadwal.php?delete=<?= $schedule->id_jadwal ?>&tanggal=<?= $tanggal ?>"
                                                       onclick="return confirm('Hapus jadwal ini?')">
                                                        <i class="fas fa-trash"></i> Hapus
                                                    </a>
                                                </li>
                                            </ul>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Summary -->
                <div class="row mt-4">
                    <div class="col-md-4">
                        <div class="card bg-light">
                            <div class="card-body text-center">
                                <h6 class="text-muted">Total Slot</h6>
                                <h3><?= count($schedules) ?></h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card bg-light">
                            <div class="card-body text-center">
                                <h6 class="text-muted">Tersedia</h6>
                                <h3 class="text-success">
                                    <?= count(array_filter($schedules, function($s) { return $s->status_ketersediaan === 'tersedia'; })) ?>
                                </h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card bg-light">
                            <div class="card-body text-center">
                                <h6 class="text-muted">Dibooking</h6>
                                <h3 class="text-warning">
                                    <?= count(array_filter($schedules, function($s) { return $s->status_ketersediaan === 'dibooking'; })) ?>
                                </h3>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Bulk Actions -->
    <div class="row mt-4">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0"><i class="fas fa-bolt"></i> Aksi Cepat</h6>
                </div>
                <div class="card-body">
                    <div class="btn-group w-100">
                        <a href="jadwal.php?action=generate&start_date=<?= date('Y-m-d', strtotime('+1 day')) ?>&days=7" 
                           class="btn btn-outline-success"
                           onclick="return confirm('Generate jadwal untuk 7 hari ke depan?')">
                            <i class="fas fa-calendar-plus"></i> Generate Minggu Depan
                        </a>
                        <a href="jadwal.php?action=generate&start_date=<?= date('Y-m-01', strtotime('+1 month')) ?>&days=30" 
                           class="btn btn-outline-primary"
                           onclick="return confirm('Generate jadwal untuk 1 bulan ke depan?')">
                            <i class="fas fa-calendar-alt"></i> Generate Bulan Depan
                        </a>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0"><i class="fas fa-cogs"></i> Pengaturan Otomatis</h6>
                </div>
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <input type="hidden" name="action" value="generate">
                        <div class="col-md-6">
                            <label class="form-label">Mulai Tanggal</label>
                            <input type="date" class="form-control" name="start_date" 
                                   value="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Jumlah Hari</label>
                            <select class="form-select" name="days">
                                <option value="7">1 Minggu</option>
                                <option value="14">2 Minggu</option>
                                <option value="30">1 Bulan</option>
                                <option value="60">2 Bulan</option>
                                <option value="90">3 Bulan</option>
                            </select>
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary w-100">
                                Generate
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/admin-footer.php'; ?>

<?php
// Function to generate schedule
function generateSchedule($start_date, $days = 7) {
    global $db;
    
    // Get all active fields
    $db->query('SELECT * FROM lapangan WHERE status_lapangan = "aktif"');
    $fields = $db->resultSet();
    
    // Time slots
    $time_slots = [
        ['08:00', '09:00'],
        ['09:00', '10:00'],
        ['10:00', '11:00'],
        ['11:00', '12:00'],
        ['13:00', '14:00'],
        ['14:00', '15:00'],
        ['15:00', '16:00'],
        ['16:00', '17:00'],
        ['17:00', '18:00'],
        ['18:00', '19:00'],
        ['19:00', '20:00'],
        ['20:00', '21:00'],
        ['21:00', '22:00']
    ];
    
    // Generate for each day
    for ($day = 0; $day < $days; $day++) {
        $current_date = date('Y-m-d', strtotime($start_date . " + $day days"));
        
        // Check if holiday
        $db->query('SELECT * FROM hari_libur WHERE tanggal = :tanggal');
        $db->bind(':tanggal', $current_date);
        $holiday = $db->single();
        
        // Skip if holiday with status "libur"
        if ($holiday && $holiday->status === 'libur') {
            continue;
        }
        
        // For each field
        foreach ($fields as $field) {
            // For each time slot
            foreach ($time_slots as $slot) {
                // Check if schedule already exists
                $db->query('SELECT id_jadwal FROM jadwal 
                           WHERE id_lapangan = :id_lapangan 
                           AND tanggal = :tanggal 
                           AND jam_mulai = :jam_mulai');
                $db->bind(':id_lapangan', $field->id_lapangan);
                $db->bind(':tanggal', $current_date);
                $db->bind(':jam_mulai', $slot[0]);
                
                if (!$db->single()) {
                    // Insert new schedule
                    $db->query('INSERT INTO jadwal 
                               (id_lapangan, tanggal, jam_mulai, jam_selesai, harga, status_ketersediaan) 
                               VALUES 
                               (:id_lapangan, :tanggal, :jam_mulai, :jam_selesai, :harga, :status)');
                    
                    $db->bind(':id_lapangan', $field->id_lapangan);
                    $db->bind(':tanggal', $current_date);
                    $db->bind(':jam_mulai', $slot[0]);
                    $db->bind(':jam_selesai', $slot[1]);
                    $db->bind(':harga', $field->harga_per_jam);
                    $db->bind(':status', 'tersedia');
                    
                    $db->execute();
                }
            }
        }
    }
    
    return true;
}
?>