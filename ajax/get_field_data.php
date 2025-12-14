<?php
require_once '../includes/config.php';
require_once '../includes/database.php';

$db = new Database();

// Get booking distribution by field type
$db->query('SELECT l.tipe_lapangan, COUNT(p.id_pemesanan) as total
           FROM pemesanan p
           JOIN jadwal j ON p.id_jadwal = j.id_jadwal
           JOIN lapangan l ON j.id_lapangan = l.id_lapangan
           WHERE MONTH(p.tanggal_pesan) = MONTH(CURDATE())
           AND YEAR(p.tanggal_pesan) = YEAR(CURDATE())
           GROUP BY l.tipe_lapangan
           ORDER BY total DESC');
$results = $db->resultSet();

$labels = [];
$data = [];

foreach($results as $row) {
    $labels[] = $row->tipe_lapangan;
    $data[] = $row->total;
}

// Fill with zero if no data
if(empty($labels)) {
    $labels = ['Futsal', 'Badminton', 'Voli', 'Basket', 'Tennis'];
    $data = [0, 0, 0, 0, 0];
}

echo json_encode([
    'labels' => $labels,
    'data' => $data
]);
?>