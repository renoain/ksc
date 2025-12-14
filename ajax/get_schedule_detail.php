<?php
require_once '../includes/config.php';
require_once '../includes/database.php';
require_once '../includes/functions.php';

$db = new Database();

$id_jadwal = $_GET['id'] ?? 0;

if($id_jadwal == 0) {
    // Create dummy data for new schedule
    $data = [
        'nama_lapangan' => 'Lapangan Baru',
        'tanggal' => date('d/m/Y'),
        'jam_mulai' => '08:00',
        'jam_selesai' => '09:00',
        'harga_formatted' => 'Rp 100.000'
    ];
    echo json_encode($data);
    exit();
}

// Get schedule details
$db->query('SELECT j.*, l.nama_lapangan, l.harga_per_jam 
           FROM jadwal j 
           JOIN lapangan l ON j.id_lapangan = l.id_lapangan
           WHERE j.id_jadwal = :id');
$db->bind(':id', $id_jadwal);
$jadwal = $db->single();

if($jadwal) {
    $data = [
        'nama_lapangan' => $jadwal->nama_lapangan,
        'tanggal' => formatDate($jadwal->tanggal),
        'jam_mulai' => substr($jadwal->jam_mulai, 0, 5),
        'jam_selesai' => substr($jadwal->jam_selesai, 0, 5),
        'harga_formatted' => formatCurrency($jadwal->harga ?: $jadwal->harga_per_jam)
    ];
    echo json_encode($data);
} else {
    echo json_encode(['error' => 'Jadwal tidak ditemukan']);
}
?>