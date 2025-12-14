<?php
require_once '../includes/config.php';
require_once '../includes/database.php';
require_once '../includes/functions.php'; // Tambah include functions

$db = new Database();

$tipe = $_GET['tipe'] ?? '';
$tanggal = $_GET['tanggal'] ?? date('Y-m-d');

// Check if holiday
$db->query('SELECT * FROM hari_libur WHERE tanggal = :tanggal');
$db->bind(':tanggal', $tanggal);
$holiday = $db->single();

if($holiday && $holiday->status == 'libur') {
    echo '<div class="alert alert-warning">';
    echo '<i class="fas fa-calendar-times"></i> Tanggal ' . date('d/m/Y', strtotime($tanggal)) . ' adalah hari libur.';
    if($holiday->keterangan) {
        echo '<br><small>' . $holiday->keterangan . '</small>';
    }
    echo '</div>';
    return;
}

// Get available fields based on type
$sql = 'SELECT * FROM lapangan WHERE status_lapangan = "aktif"';
if(!empty($tipe)) {
    $sql .= ' AND tipe_lapangan = :tipe';
}

$db->query($sql);
if(!empty($tipe)) {
    $db->bind(':tipe', $tipe);
}
$fields = $db->resultSet();

if(empty($fields)) {
    echo '<div class="alert alert-info">';
    echo '<i class="fas fa-info-circle"></i> Tidak ada lapangan tersedia untuk tipe yang dipilih.';
    echo '</div>';
    return;
}

// Generate time slots (08:00 - 22:00)
$time_slots = [];
$start_hour = 8;
$end_hour = 22;

for($hour = $start_hour; $hour < $end_hour; $hour++) {
    $time_slots[] = [
        'start' => sprintf('%02d:00', $hour),
        'end' => sprintf('%02d:00', $hour + 1)
    ];
}

echo '<h4 class="mb-3">Jadwal untuk ' . date('d/m/Y', strtotime($tanggal)) . '</h4>';

foreach($fields as $field) {
    echo '<div class="card mb-3">';
    echo '<div class="card-header bg-light">';
    echo '<h5 class="mb-0"><i class="fas fa-futbol"></i> ' . $field->nama_lapangan . '</h5>';
    echo '<small class="text-muted">' . $field->tipe_lapangan . ' - ' . formatCurrency($field->harga_per_jam) . '/jam</small>';
    echo '</div>';
    echo '<div class="card-body">';
    echo '<div class="row g-2">';
    
    foreach($time_slots as $slot) {
        // Check schedule availability
        $db->query('SELECT * FROM jadwal 
                   WHERE id_lapangan = :id_lapangan 
                   AND tanggal = :tanggal 
                   AND jam_mulai = :jam_mulai');
        $db->bind(':id_lapangan', $field->id_lapangan);
        $db->bind(':tanggal', $tanggal);
        $db->bind(':jam_mulai', $slot['start']);
        $schedule = $db->single();
        
        $status = $schedule ? $schedule->status_ketersediaan : 'tersedia';
        $harga = $schedule ? ($schedule->harga ?: $field->harga_per_jam) : $field->harga_per_jam;
        
        $btn_class = 'btn-outline-';
        $disabled = false;
        
        switch($status) {
            case 'tersedia':
                $btn_class .= 'success';
                break;
            case 'dibooking':
                $btn_class .= 'warning';
                $disabled = true;
                break;
            case 'blokir':
                $btn_class .= 'danger';
                $disabled = true;
                break;
            default:
                $btn_class .= 'secondary';
        }
        
        echo '<div class="col-md-2 col-sm-3 col-4">';
        if($disabled) {
            echo '<button class="btn ' . $btn_class . ' w-100 text-truncate" disabled>';
            echo $slot['start'] . '<br>';
            echo '<small>' . formatCurrency($harga) . '</small>';
            echo '</button>';
        } else {
            echo '<button class="btn ' . $btn_class . ' w-100 text-truncate" 
                   onclick="showBookingModal(' . ($schedule ? $schedule->id_jadwal : '0') . ', ' . $field->id_lapangan . ')" 
                   data-bs-toggle="modal" data-bs-target="#bookingModal">';
            echo $slot['start'] . '<br>';
            echo '<small>' . formatCurrency($harga) . '</small>';
            echo '</button>';
        }
        echo '</div>';
    }
    
    echo '</div>'; // Close row
    echo '</div>'; // Close card-body
    echo '</div>'; // Close card
}
?>