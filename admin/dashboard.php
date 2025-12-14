<?php
$page_title = 'Dashboard Admin';
require_once '../includes/admin-header.php'; // Sudah include functions.php
require_once '../includes/database.php'; // Tambahkan ini

$db = new Database();

// Get statistics for current month
$current_month = date('Y-m');
?>

<div class="dashboard-container">
    <!-- Statistics Cards -->
    <div class="dashboard-stats mb-4">
        <div class="stat-card">
            <div class="stat-icon" style="background: #3498db;">
                <i class="fas fa-futbol"></i>
            </div>
            <div class="stat-info">
                <h3>Total Lapangan</h3>
                <?php
                $db->query('SELECT COUNT(*) as total FROM lapangan WHERE status_lapangan = "aktif"');
                $result = $db->single();
                ?>
                <p class="stat-number"><?= $result->total ?></p>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon" style="background: #2ecc71;">
                <i class="fas fa-calendar-check"></i>
            </div>
            <div class="stat-info">
                <h3>Pemesanan Bulan Ini</h3>
                <?php
                $db->query('SELECT COUNT(*) as total FROM pemesanan 
                           WHERE MONTH(tanggal_pesan) = MONTH(CURDATE()) 
                           AND YEAR(tanggal_pesan) = YEAR(CURDATE())');
                $result = $db->single();
                ?>
                <p class="stat-number"><?= $result->total ?></p>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon" style="background: #9b59b6;">
                <i class="fas fa-money-bill-wave"></i>
            </div>
            <div class="stat-info">
                <h3>Pendapatan Bulan Ini</h3>
                <?php
                $db->query('SELECT SUM(p.jumlah_bayar) as total 
                           FROM pembayaran p
                           JOIN pemesanan pm ON p.id_pemesanan = pm.id_pemesanan
                           WHERE p.status_bayar IN ("lunas", "dp_lunas")
                           AND MONTH(pm.tanggal_pesan) = MONTH(CURDATE()) 
                           AND YEAR(pm.tanggal_pesan) = YEAR(CURDATE())');
                $result = $db->single();
                ?>
                <p class="stat-number"><?= formatCurrency($result->total ?: 0) ?></p>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon" style="background: #e74c3c;">
                <i class="fas fa-clock"></i>
            </div>
            <div class="stat-info">
                <h3>Pending Payment</h3>
                <?php
                $db->query('SELECT COUNT(*) as total FROM pembayaran WHERE status_bayar = "pending"');
                $result = $db->single();
                ?>
                <p class="stat-number"><?= $result->total ?></p>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon" style="background: #f39c12;">
                <i class="fas fa-users"></i>
            </div>
            <div class="stat-info">
                <h3>Total User</h3>
                <?php
                $db->query('SELECT COUNT(*) as total FROM user WHERE role = "penyewa"');
                $result = $db->single();
                ?>
                <p class="stat-number"><?= $result->total ?></p>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon" style="background: #1abc9c;">
                <i class="fas fa-chart-line"></i>
            </div>
            <div class="stat-info">
                <h3>Omset Total</h3>
                <?php
                $db->query('SELECT SUM(jumlah_bayar) as total FROM pembayaran WHERE status_bayar IN ("lunas", "dp_lunas")');
                $result = $db->single();
                ?>
                <p class="stat-number"><?= formatCurrency($result->total ?: 0) ?></p>
            </div>
        </div>
    </div>
    
    <!-- Quick Report Filters -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0"><i class="fas fa-filter"></i> Filter Report</h5>
        </div>
        <div class="card-body">
            <form id="reportFilter" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Periode</label>
                    <select class="form-select" name="period" id="period">
                        <option value="today">Hari Ini</option>
                        <option value="week">Minggu Ini</option>
                        <option value="month" selected>Bulan Ini</option>
                        <option value="year">Tahun Ini</option>
                        <option value="custom">Custom</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Dari Tanggal</label>
                    <input type="date" class="form-control" name="start_date" id="start_date" 
                           value="<?= date('Y-m-01') ?>" <?= date('Y-m-d') ?>>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Sampai Tanggal</label>
                    <input type="date" class="form-control" name="end_date" id="end_date" 
                           value="<?= date('Y-m-d') ?>">
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <button type="button" class="btn btn-primary w-100" onclick="loadReport()">
                        <i class="fas fa-sync-alt"></i> Refresh Report
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Revenue Chart -->
    <div class="row mb-4">
        <div class="col-md-8">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-chart-bar"></i> Pendapatan 30 Hari Terakhir</h5>
                </div>
                <div class="card-body">
                    <canvas id="revenueChart" height="250"></canvas>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-chart-pie"></i> Booking per Lapangan</h5>
                </div>
                <div class="card-body">
                    <canvas id="fieldChart" height="250"></canvas>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Recent Data Tables -->
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-history"></i> Pemesanan Terbaru</h5>
                    <a href="pemesanan.php" class="btn btn-sm btn-primary">Lihat Semua</a>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Pelanggan</th>
                                    <th>Lapangan</th>
                                    <th>Tanggal</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $db->query('SELECT p.*, u.nama, l.nama_lapangan, j.tanggal, j.jam_mulai, j.jam_selesai
                                           FROM pemesanan p 
                                           JOIN user u ON p.id_user = u.id_user 
                                           JOIN jadwal j ON p.id_jadwal = j.id_jadwal
                                           JOIN lapangan l ON j.id_lapangan = l.id_lapangan
                                           ORDER BY p.tanggal_pesan DESC LIMIT 5');
                                $bookings = $db->resultSet();
                                
                                if(empty($bookings)): ?>
                                    <tr>
                                        <td colspan="5" class="text-center text-muted">Belum ada pemesanan</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach($bookings as $booking): ?>
                                    <tr>
                                        <td>#<?= str_pad($booking->id_pemesanan, 4, '0', STR_PAD_LEFT) ?></td>
                                        <td><?= htmlspecialchars($booking->nama) ?></td>
                                        <td><?= htmlspecialchars($booking->nama_lapangan) ?></td>
                                        <td>
                                            <small><?= formatDate($booking->tanggal) ?></small><br>
                                            <small class="text-muted"><?= substr($booking->jam_mulai, 0, 5) ?> - <?= substr($booking->jam_selesai, 0, 5) ?></small>
                                        </td>
                                        <td>
                                            <?php 
                                            $status_class = '';
                                            switch($booking->status_pemesanan) {
                                                case 'disetujui': $status_class = 'bg-success'; break;
                                                case 'menunggu': $status_class = 'bg-warning'; break;
                                                case 'ditolak': $status_class = 'bg-danger'; break;
                                                case 'selesai': $status_class = 'bg-info'; break;
                                                default: $status_class = 'bg-secondary';
                                            }
                                            ?>
                                            <span class="badge <?= $status_class ?>">
                                                <?= ucfirst($booking->status_pemesanan) ?>
                                            </span>
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
        
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-money-bill-wave"></i> Pembayaran Terbaru</h5>
                    <a href="pembayaran.php" class="btn btn-sm btn-primary">Lihat Semua</a>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Kode</th>
                                    <th>Pelanggan</th>
                                    <th>Jumlah</th>
                                    <th>Status</th>
                                    <th>Tanggal</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $db->query('SELECT p.*, u.nama, pm.total_harga
                                           FROM pembayaran p
                                           JOIN pemesanan pm ON p.id_pemesanan = pm.id_pemesanan
                                           JOIN user u ON pm.id_user = u.id_user
                                           ORDER BY p.created_at DESC LIMIT 5');
                                $payments = $db->resultSet();
                                
                                if(empty($payments)): ?>
                                    <tr>
                                        <td colspan="5" class="text-center text-muted">Belum ada pembayaran</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach($payments as $payment): ?>
                                    <tr>
                                        <td><code><?= substr($payment->kode_pembayaran, 0, 10) ?>...</code></td>
                                        <td><?= htmlspecialchars($payment->nama) ?></td>
                                        <td><?= formatCurrency($payment->jumlah_bayar) ?></td>
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
                                        <td><small><?= formatDate($payment->created_at) ?></small></td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Detailed Report Section -->
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-file-alt"></i> Detail Report</h5>
                </div>
                <div class="card-body">
                    <div id="reportContent">
                        <!-- Report will be loaded here via AJAX -->
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Include Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
// Initialize Charts
document.addEventListener('DOMContentLoaded', function() {
    loadCharts();
    loadReport();
    
    // Period selector change
    document.getElementById('period').addEventListener('change', function() {
        const period = this.value;
        const today = new Date().toISOString().split('T')[0];
        
        if(period === 'today') {
            document.getElementById('start_date').value = today;
            document.getElementById('end_date').value = today;
        } else if(period === 'week') {
            const lastWeek = new Date();
            lastWeek.setDate(lastWeek.getDate() - 7);
            document.getElementById('start_date').value = lastWeek.toISOString().split('T')[0];
            document.getElementById('end_date').value = today;
        } else if(period === 'month') {
            const firstDay = new Date();
            firstDay.setDate(1);
            document.getElementById('start_date').value = firstDay.toISOString().split('T')[0];
            document.getElementById('end_date').value = today;
        } else if(period === 'year') {
            document.getElementById('start_date').value = new Date().getFullYear() + '-01-01';
            document.getElementById('end_date').value = today;
        }
    });
});

function loadCharts() {
    // Revenue Chart
    fetch('ajax/get_revenue_data.php')
        .then(response => response.json())
        .then(data => {
            const ctx = document.getElementById('revenueChart').getContext('2d');
            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: data.labels,
                    datasets: [{
                        label: 'Pendapatan (Rp)',
                        data: data.revenue,
                        borderColor: '#3498db',
                        backgroundColor: 'rgba(52, 152, 219, 0.1)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            position: 'top',
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return 'Rp ' + context.parsed.y.toLocaleString('id-ID');
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return 'Rp ' + value.toLocaleString('id-ID');
                                }
                            }
                        }
                    }
                }
            });
        });
    
    // Field Distribution Chart
    fetch('ajax/get_field_data.php')
        .then(response => response.json())
        .then(data => {
            const ctx = document.getElementById('fieldChart').getContext('2d');
            new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: data.labels,
                    datasets: [{
                        data: data.data,
                        backgroundColor: [
                            '#3498db',
                            '#2ecc71',
                            '#e74c3c',
                            '#f39c12',
                            '#9b59b6',
                            '#1abc9c',
                            '#34495e'
                        ]
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            position: 'right',
                        }
                    }
                }
            });
        });
}

function loadReport() {
    const startDate = document.getElementById('start_date').value;
    const endDate = document.getElementById('end_date').value;
    
    fetch(`ajax/get_report.php?start_date=${startDate}&end_date=${endDate}`)
        .then(response => response.text())
        .then(html => {
            document.getElementById('reportContent').innerHTML = html;
        })
        .catch(error => {
            document.getElementById('reportContent').innerHTML = 
                '<div class="alert alert-danger">Error loading report</div>';
        });
}
</script>

<style>
.dashboard-container {
    padding: 20px;
}

.dashboard-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.stat-card {
    background: white;
    border-radius: 10px;
    padding: 20px;
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    display: flex;
    align-items: center;
    transition: all 0.3s ease;
    border: 1px solid #e9ecef;
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 20px rgba(0,0,0,0.15);
}

.stat-icon {
    width: 60px;
    height: 60px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 15px;
    color: white;
    font-size: 1.5rem;
}

.stat-info h3 {
    font-size: 0.9rem;
    color: #6c757d;
    margin-bottom: 5px;
    font-weight: 600;
    text-transform: uppercase;
}

.stat-number {
    font-size: 1.8rem;
    font-weight: 700;
    color: #2c3e50;
    margin: 0;
}

.card {
    border: none;
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    border-radius: 10px;
    margin-bottom: 20px;
}

.card-header {
    background-color: #f8f9fa;
    border-bottom: 1px solid #e9ecef;
    padding: 15px 20px;
    border-radius: 10px 10px 0 0 !important;
}

.card-header h5 {
    margin: 0;
    color: #2c3e50;
}

.table th {
    font-weight: 600;
    color: #495057;
    background-color: #f8f9fa;
    border-bottom: 2px solid #dee2e6;
}

.table td {
    vertical-align: middle;
}

.table-hover tbody tr:hover {
    background-color: rgba(52, 152, 219, 0.05);
}

@media (max-width: 768px) {
    .dashboard-stats {
        grid-template-columns: 1fr;
    }
    
    .stat-card {
        flex-direction: column;
        text-align: center;
        padding: 15px;
    }
    
    .stat-icon {
        margin-right: 0;
        margin-bottom: 10px;
        width: 50px;
        height: 50px;
        font-size: 1.2rem;
    }
    
    .stat-number {
        font-size: 1.5rem;
    }
}
</style>

<?php require_once '../includes/admin-footer.php'; ?>