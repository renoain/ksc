<?php
require_once '../includes/config.php';
require_once '../includes/database.php';
require_once '../includes/functions.php';

$db = new Database();

$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-d');

// Get summary statistics
$db->query('SELECT 
           COUNT(DISTINCT p.id_pemesanan) as total_bookings,
           COUNT(DISTINCT p.id_user) as total_customers,
           SUM(pm.jumlah_bayar) as total_revenue,
           AVG(pm.jumlah_bayar) as avg_revenue
           FROM pemesanan p
           LEFT JOIN pembayaran pm ON p.id_pemesanan = pm.id_pemesanan
           WHERE DATE(p.tanggal_pesan) BETWEEN :start_date AND :end_date
           AND pm.status_bayar IN ("lunas", "dp_lunas")');
$db->bind(':start_date', $start_date);
$db->bind(':end_date', $end_date);
$summary = $db->single();

// Get revenue by day
$db->query('SELECT DATE(p.tanggal_pesan) as date,
           COUNT(p.id_pemesanan) as bookings,
           SUM(pm.jumlah_bayar) as revenue
           FROM pemesanan p
           LEFT JOIN pembayaran pm ON p.id_pemesanan = pm.id_pemesanan
           WHERE DATE(p.tanggal_pesan) BETWEEN :start_date AND :end_date
           AND pm.status_bayar IN ("lunas", "dp_lunas")
           GROUP BY DATE(p.tanggal_pesan)
           ORDER BY date DESC
           LIMIT 10');
$db->bind(':start_date', $start_date);
$db->bind(':end_date', $end_date);
$daily_revenue = $db->resultSet();

// Get top fields
$db->query('SELECT l.nama_lapangan, l.tipe_lapangan,
           COUNT(p.id_pemesanan) as bookings
           FROM pemesanan p
           JOIN jadwal j ON p.id_jadwal = j.id_jadwal
           JOIN lapangan l ON j.id_lapangan = l.id_lapangan
           WHERE DATE(p.tanggal_pesan) BETWEEN :start_date AND :end_date
           GROUP BY l.id_lapangan
           ORDER BY bookings DESC
           LIMIT 5');
$db->bind(':start_date', $start_date);
$db->bind(':end_date', $end_date);
$top_fields = $db->resultSet();

// Get payment methods
$db->query('SELECT pm.metode_bayar,
           COUNT(*) as count,
           SUM(pm.jumlah_bayar) as total
           FROM pembayaran pm
           JOIN pemesanan p ON pm.id_pemesanan = p.id_pemesanan
           WHERE DATE(p.tanggal_pesan) BETWEEN :start_date AND :end_date
           AND pm.status_bayar IN ("lunas", "dp_lunas")
           GROUP BY pm.metode_bayar');
$db->bind(':start_date', $start_date);
$db->bind(':end_date', $end_date);
$payment_methods = $db->resultSet();
?>

<div class="report-summary mb-4">
    <h5>Summary Report: <?= formatDate($start_date) ?> - <?= formatDate($end_date) ?></h5>
    <div class="row mt-3">
        <div class="col-md-3">
            <div class="card bg-light">
                <div class="card-body text-center">
                    <h6 class="text-muted">Total Bookings</h6>
                    <h3 class="text-primary"><?= $summary->total_bookings ?: 0 ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-light">
                <div class="card-body text-center">
                    <h6 class="text-muted">Total Customers</h6>
                    <h3 class="text-success"><?= $summary->total_customers ?: 0 ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-light">
                <div class="card-body text-center">
                    <h6 class="text-muted">Total Revenue</h6>
                    <h3 class="text-warning"><?= formatCurrency($summary->total_revenue ?: 0) ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-light">
                <div class="card-body text-center">
                    <h6 class="text-muted">Avg Revenue</h6>
                    <h3 class="text-info"><?= formatCurrency($summary->avg_revenue ?: 0) ?></h3>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0">Daily Revenue (Last 10 Days)</h6>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Bookings</th>
                                <th>Revenue</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($daily_revenue)): ?>
                                <tr>
                                    <td colspan="3" class="text-center text-muted">No data available</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach($daily_revenue as $day): ?>
                                <tr>
                                    <td><?= formatDate($day->date) ?></td>
                                    <td><?= $day->bookings ?></td>
                                    <td><?= formatCurrency($day->revenue ?: 0) ?></td>
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
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0">Top Lapangan</h6>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Lapangan</th>
                                <th>Tipe</th>
                                <th>Bookings</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($top_fields)): ?>
                                <tr>
                                    <td colspan="3" class="text-center text-muted">No data available</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach($top_fields as $field): ?>
                                <tr>
                                    <td><?= $field->nama_lapangan ?></td>
                                    <td><span class="badge bg-secondary"><?= $field->tipe_lapangan ?></span></td>
                                    <td><?= $field->bookings ?></td>
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

<div class="row mt-4">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0">Payment Methods</h6>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Method</th>
                                <th>Count</th>
                                <th>Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($payment_methods)): ?>
                                <tr>
                                    <td colspan="3" class="text-center text-muted">No data available</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach($payment_methods as $method): ?>
                                <tr>
                                    <td><?= ucfirst($method->metode_bayar) ?></td>
                                    <td><?= $method->count ?></td>
                                    <td><?= formatCurrency($method->total ?: 0) ?></td>
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
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0">Export Report</h6>
            </div>
            <div class="card-body">
                <p>Export report data in various formats:</p>
                <div class="d-flex gap-2">
                    <a href="export.php?type=pdf&start_date=<?= $start_date ?>&end_date=<?= $end_date ?>" 
                       class="btn btn-danger">
                        <i class="fas fa-file-pdf"></i> PDF
                    </a>
                    <a href="export.php?type=excel&start_date=<?= $start_date ?>&end_date=<?= $end_date ?>" 
                       class="btn btn-success">
                        <i class="fas fa-file-excel"></i> Excel
                    </a>
                    <a href="export.php?type=csv&start_date=<?= $start_date ?>&end_date=<?= $end_date ?>" 
                       class="btn btn-info">
                        <i class="fas fa-file-csv"></i> CSV
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>