<?php
require_once '../includes/config.php';
require_once '../includes/database.php';

$db = new Database();

$id = $_GET['id'] ?? 0;

$db->query('SELECT * FROM template_jadwal WHERE id_template = :id');
$db->bind(':id', $id);
$template = $db->single();

if ($template) {
    echo json_encode([
        'id_template' => $template->id_template,
        'id_lapangan' => $template->id_lapangan,
        'hari' => $template->hari,
        'jam_mulai' => $template->jam_mulai,
        'jam_selesai' => $template->jam_selesai,
        'harga' => $template->harga,
        'status_aktif' => $template->status_aktif
    ]);
} else {
    echo json_encode(['error' => 'Data tidak ditemukan']);
}
?>