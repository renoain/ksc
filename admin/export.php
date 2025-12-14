<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
require_once '../includes/database.php';
require_once '../includes/functions.php';

$auth = new Auth();

if(!$auth->isLoggedIn() || !$auth->isAdmin()) {
    redirect('../login.php');
}

$type = $_GET['type'] ?? 'csv';
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-d');

$db = new Database();

// Get booking data for export
$db->query('SELECT 
           p.id_pemesanan,
           u.nama as customer_name,
           u.email as customer_email,
           l.nama_lapangan,
           l.tipe_lapangan,
           j.tanggal,
           j.jam_mulai,
           j.jam_selesai,
           p.total_harga,
           p.status_pemesanan,
           p.tanggal_pesan,
           pm.kode_pembayaran,
           pm.tipe_pembayaran,
           pm.dp_percent,
           pm.dp_amount,
           pm.jumlah_bayar,
           pm.sisa_tagihan,
           pm.metode_bayar,
           pm.status_bayar
           FROM pemesanan p
           JOIN user u ON p.id_user = u.id_user
           JOIN jadwal j ON p.id_jadwal = j.id_jadwal
           JOIN lapangan l ON j.id_lapangan = l.id_lapangan
           LEFT JOIN pembayaran pm ON p.id_pemesanan = pm.id_pemesanan
           WHERE DATE(p.tanggal_pesan) BETWEEN :start_date AND :end_date
           ORDER BY p.tanggal_pesan DESC');
$db->bind(':start_date', $start_date);
$db->bind(':end_date', $end_date);
$bookings = $db->resultSet();

switch($type) {
    case 'csv':
        exportCSV($bookings, $start_date, $end_date);
        break;
    case 'excel':
        exportExcel($bookings, $start_date, $end_date);
        break;
    case 'pdf':
        exportPDF($bookings, $start_date, $end_date);
        break;
    default:
        exportCSV($bookings, $start_date, $end_date);
}

function exportCSV($data, $start_date, $end_date) {
    $filename = "report_ksc_" . date('Ymd') . ".csv";
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    $output = fopen('php://output', 'w');
    
    // Header
    fputcsv($output, ['KSC - Booking Report']);
    fputcsv($output, ['Period:', $start_date . ' to ' . $end_date]);
    fputcsv($output, ['Generated:', date('Y-m-d H:i:s')]);
    fputcsv($output, []); // Empty line
    
    // Column headers
    fputcsv($output, [
        'Booking ID',
        'Customer',
        'Email',
        'Lapangan',
        'Tipe',
        'Tanggal Main',
        'Jam',
        'Total Harga',
        'Status Booking',
        'Kode Pembayaran',
        'Tipe Pembayaran',
        'DP %',
        'DP Amount',
        'Jumlah Bayar',
        'Sisa Tagihan',
        'Metode Bayar',
        'Status Bayar',
        'Tanggal Pesan'
    ]);
    
    // Data rows
    foreach($data as $row) {
        fputcsv($output, [
            $row->id_pemesanan,
            $row->customer_name,
            $row->customer_email,
            $row->nama_lapangan,
            $row->tipe_lapangan,
            $row->tanggal,
            $row->jam_mulai . ' - ' . $row->jam_selesai,
            $row->total_harga,
            $row->status_pemesanan,
            $row->kode_pembayaran,
            $row->tipe_pembayaran,
            $row->dp_percent,
            $row->dp_amount,
            $row->jumlah_bayar,
            $row->sisa_tagihan,
            $row->metode_bayar,
            $row->status_bayar,
            $row->tanggal_pesan
        ]);
    }
    
    fclose($output);
    exit;
}

function exportExcel($data, $start_date, $end_date) {
    $filename = "report_ksc_" . date('Ymd') . ".xls";
    
    header("Content-Type: application/vnd.ms-excel");
    header("Content-Disposition: attachment; filename=\"$filename\"");
    
    echo "<table border='1'>";
    echo "<tr><th colspan='8'>KSC - Booking Report</th></tr>";
    echo "<tr><td colspan='4'><strong>Period:</strong></td><td colspan='4'>$start_date to $end_date</td></tr>";
    echo "<tr><td colspan='4'><strong>Generated:</strong></td><td colspan='4'>" . date('Y-m-d H:i:s') . "</td></tr>";
    echo "<tr></tr>";
    
    // Column headers
    echo "<tr style='background-color: #f2f2f2;'>";
    echo "<th>Booking ID</th>";
    echo "<th>Customer</th>";
    echo "<th>Email</th>";
    echo "<th>Lapangan</th>";
    echo "<th>Tipe</th>";
    echo "<th>Tanggal Main</th>";
    echo "<th>Jam</th>";
    echo "<th>Total Harga</th>";
    echo "<th>Status Booking</th>";
    echo "<th>Kode Pembayaran</th>";
    echo "<th>Tipe Pembayaran</th>";
    echo "<th>DP %</th>";
    echo "<th>DP Amount</th>";
    echo "<th>Jumlah Bayar</th>";
    echo "<th>Sisa Tagihan</th>";
    echo "<th>Metode Bayar</th>";
    echo "<th>Status Bayar</th>";
    echo "<th>Tanggal Pesan</th>";
    echo "</tr>";
    
    // Data rows
    foreach($data as $row) {
        echo "<tr>";
        echo "<td>$row->id_pemesanan</td>";
        echo "<td>$row->customer_name</td>";
        echo "<td>$row->customer_email</td>";
        echo "<td>$row->nama_lapangan</td>";
        echo "<td>$row->tipe_lapangan</td>";
        echo "<td>$row->tanggal</td>";
        echo "<td>$row->jam_mulai - $row->jam_selesai</td>";
        echo "<td>" . number_format($row->total_harga, 0, ',', '.') . "</td>";
        echo "<td>$row->status_pemesanan</td>";
        echo "<td>$row->kode_pembayaran</td>";
        echo "<td>$row->tipe_pembayaran</td>";
        echo "<td>$row->dp_percent</td>";
        echo "<td>" . number_format($row->dp_amount, 0, ',', '.') . "</td>";
        echo "<td>" . number_format($row->jumlah_bayar, 0, ',', '.') . "</td>";
        echo "<td>" . number_format($row->sisa_tagihan, 0, ',', '.') . "</td>";
        echo "<td>$row->metode_bayar</td>";
        echo "<td>$row->status_bayar</td>";
        echo "<td>$row->tanggal_pesan</td>";
        echo "</tr>";
    }
    
    echo "</table>";
    exit;
}

function exportPDF($data, $start_date, $end_date) {
    // For PDF, you would typically use a library like TCPDF or FPDF
    // This is a simplified version that redirects to a printable HTML page
    
    $_SESSION['export_data'] = [
        'data' => $data,
        'start_date' => $start_date,
        'end_date' => $end_date
    ];
    
    header('Location: export_pdf.php');
    exit;
}
?>