<?php
require_once '../includes/config.php';
require_once '../includes/database.php';

$db = new Database();

$id = $_GET['id'] ?? 0;

$db->query('SELECT * FROM hari_libur WHERE id_libur = :id');
$db->bind(':id', $id);
$holiday = $db->single();

if ($holiday) {
    echo json_encode([
        'id_libur' => $holiday->id_libur,
        'tanggal' => $holiday->tanggal,
        'keterangan' => $holiday->keterangan,
        'status' => $holiday->status
    ]);
} else {
    echo json_encode(['error' => 'Data tidak ditemukan']);
}
?>