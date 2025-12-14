<?php
require_once '../includes/config.php';
require_once '../includes/database.php';

$db = new Database();

// Get revenue data for last 30 days
$revenue_data = [];
$labels = [];

for($i = 29; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $labels[] = date('d M', strtotime($date));
    
    $db->query('SELECT SUM(p.jumlah_bayar) as total 
               FROM pembayaran p
               JOIN pemesanan pm ON p.id_pemesanan = pm.id_pemesanan
               WHERE p.status_bayar IN ("lunas", "dp_lunas")
               AND DATE(pm.tanggal_pesan) = :date');
    $db->bind(':date', $date);
    $result = $db->single();
    
    $revenue_data[] = $result->total ?: 0;
}

echo json_encode([
    'labels' => $labels,
    'revenue' => $revenue_data
]);
?>