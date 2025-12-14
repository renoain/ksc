<?php
$page_title = 'Kelola Users';
require_once '../includes/admin-header.php';
require_once '../includes/database.php';
require_once '../includes/functions.php';

$db = new Database();

// Handle actions
if (isset($_GET['action'])) {
    $action = $_GET['action'];
    $id = $_GET['id'] ?? 0;
    
    switch($action) {
        case 'toggle_status':
            $db->query('UPDATE user SET role = CASE WHEN role = "admin" THEN "penyewa" ELSE "admin" END WHERE id_user = :id');
            $db->bind(':id', $id);
            $db->execute();
            setFlashMessage('success', 'Status user berhasil diubah.');
            break;
            
        case 'reset_password':
            $new_password = 'password123'; // Default password
            $db->query('UPDATE user SET password = :password WHERE id_user = :id');
            $db->bind(':password', $new_password);
            $db->bind(':id', $id);
            $db->execute();
            setFlashMessage('success', 'Password berhasil direset ke: ' . $new_password);
            break;
    }
    
    redirect('users.php');
}

// Handle delete
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    
    // Check if user has active bookings
    $db->query('SELECT COUNT(*) as total FROM pemesanan 
               WHERE id_user = :id 
               AND status_pemesanan IN ("menunggu", "disetujui")');
    $db->bind(':id', $id);
    $hasBookings = $db->single();
    
    if ($hasBookings->total > 0) {
        setFlashMessage('danger', 'Tidak dapat menghapus user yang memiliki booking aktif.');
    } else {
        $db->query('DELETE FROM user WHERE id_user = :id');
        $db->bind(':id', $id);
        
        if ($db->execute()) {
            setFlashMessage('success', 'User berhasil dihapus.');
        } else {
            setFlashMessage('danger', 'Gagal menghapus user.');
        }
    }
    
    redirect('users.php');
}

// Get all users
$search = $_GET['search'] ?? '';
$role = $_GET['role'] ?? '';

$sql = 'SELECT * FROM user WHERE 1=1';
$params = [];

if ($search) {
    $sql .= ' AND (nama LIKE :search OR username LIKE :search OR email LIKE :search)';
    $params[':search'] = "%$search%";
}

if ($role) {
    $sql .= ' AND role = :role';
    $params[':role'] = $role;
}

$sql .= ' ORDER BY role DESC, created_at DESC';

$db->query($sql);
foreach ($params as $key => $value) {
    $db->bind($key, $value);
}

$users = $db->resultSet();

// User statistics
$db->query('SELECT 
           COUNT(*) as total,
           SUM(CASE WHEN role = "admin" THEN 1 ELSE 0 END) as admins,
           SUM(CASE WHEN role = "penyewa" THEN 1 ELSE 0 END) as customers
           FROM user');
$stats = $db->single();
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0"><i class="fas fa-users"></i> Kelola Users</h1>
        <div>
            <a href="dashboard.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Kembali
            </a>
        </div>
    </div>
    
    <!-- Statistics -->
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card bg-primary text-white">
                <div class="card-body text-center">
                    <h6 class="text-white-50">Total Users</h6>
                    <h2><?= $stats->total ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-danger text-white">
                <div class="card-body text-center">
                    <h6 class="text-white-50">Admin</h6>
                    <h2><?= $stats->admins ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-success text-white">
                <div class="card-body text-center">
                    <h6 class="text-white-50">Penyewa</h6>
                    <h2><?= $stats->customers ?></h2>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Filter Section -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Pencarian</label>
                    <input type="text" class="form-control" name="search" 
                           value="<?= htmlspecialchars($search) ?>" 
                           placeholder="Cari nama, username, atau email...">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Role</label>
                    <select class="form-select" name="role">
                        <option value="">Semua Role</option>
                        <option value="admin" <?= $role == 'admin' ? 'selected' : '' ?>>Admin</option>
                        <option value="penyewa" <?= $role == 'penyewa' ? 'selected' : '' ?>>Penyewa</option>
                    </select>
                </div>
                <div class="col-md-4 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-search"></i> Filter
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Users Table -->
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>User</th>
                            <th>Username</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Bergabung</th>
                            <th>Booking</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($users)): ?>
                            <tr>
                                <td colspan="8" class="text-center text-muted">Tidak ada data user</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($users as $index => $user): ?>
                            <?php
                            // Get user booking count
                            $db->query('SELECT COUNT(*) as total FROM pemesanan WHERE id_user = :id');
                            $db->bind(':id', $user->id_user);
                            $bookingCount = $db->single();
                            ?>
                            <tr>
                                <td><?= $index + 1 ?></td>
                                <td>
                                    <strong><?= htmlspecialchars($user->nama) ?></strong><br>
                                    <small class="text-muted"><?= $user->no_hp ?></small>
                                </td>
                                <td><?= htmlspecialchars($user->username) ?></td>
                                <td><?= htmlspecialchars($user->email) ?></td>
                                <td>
                                    <?php if ($user->role === 'admin'): ?>
                                        <span class="badge bg-danger">Admin</span>
                                    <?php else: ?>
                                        <span class="badge bg-success">Penyewa</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <small><?= formatDate($user->created_at) ?></small>
                                </td>
                                <td>
                                    <span class="badge bg-info"><?= $bookingCount->total ?></span> booking
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <?php if ($user->id_user != $_SESSION['user_id']): ?>
                                            <a href="users.php?action=toggle_status&id=<?= $user->id_user ?>" 
                                               class="btn btn-outline-warning"
                                               onclick="return confirm('Ubah role user ini?')"
                                               title="<?= $user->role == 'admin' ? 'Jadikan Penyewa' : 'Jadikan Admin' ?>">
                                                <i class="fas fa-user-cog"></i>
                                            </a>
                                            <a href="users.php?action=reset_password&id=<?= $user->id_user ?>" 
                                               class="btn btn-outline-info"
                                               onclick="return confirm('Reset password user ini ke default?')"
                                               title="Reset Password">
                                                <i class="fas fa-key"></i>
                                            </a>
                                            <a href="users.php?delete=<?= $user->id_user ?>" 
                                               class="btn btn-outline-danger"
                                               onclick="return confirm('Hapus user ini?')"
                                               title="Hapus User">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Anda</span>
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
    
    <!-- Info Section -->
    <div class="row mt-4">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0"><i class="fas fa-info-circle"></i> Informasi User</h6>
                </div>
                <div class="card-body">
                    <div class="alert alert-info">
                        <h6>Role User:</h6>
                        <ul class="mb-0">
                            <li><span class="badge bg-danger">Admin</span> - Akses penuh ke dashboard admin</li>
                            <li><span class="badge bg-success">Penyewa</span> - Hanya dapat booking lapangan</li>
                        </ul>
                    </div>
                    <p class="small text-muted">
                        User dengan role admin dapat mengelola semua fitur sistem.
                        User penyewa hanya dapat melakukan booking dan melihat riwayat.
                    </p>
                </div>
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0"><i class="fas fa-exclamation-triangle"></i> Perhatian</h6>
                </div>
                <div class="card-body">
                    <div class="alert alert-warning">
                        <h6><i class="fas fa-exclamation-triangle"></i> Hati-hati!</h6>
                        <ul class="small mb-0">
                            <li>Reset password akan mengubah password ke: <code>password123</code></li>
                            <li>User tidak dapat menghapus akun sendiri</li>
                            <li>User dengan booking aktif tidak dapat dihapus</li>
                            <li>Perubahan role akan mempengaruhi akses user</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/admin-footer.php'; ?>