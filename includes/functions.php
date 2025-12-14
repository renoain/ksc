<?php
// Pastikan config.php sudah diinclude untuk mendapatkan konstanta
if (!function_exists('formatCurrency')) {
    function formatCurrency($amount) {
        if ($amount === null) {
            return 'Rp 0';
        }
        return 'Rp ' . number_format($amount, 0, ',', '.');
    }
}

if (!function_exists('formatDateTime')) {
    function formatDateTime($datetime) {
        if (!$datetime) {
            return '-';
        }
        return date('d/m/Y H:i', strtotime($datetime));
    }
}

if (!function_exists('formatDate')) {
    function formatDate($date) {
        if (!$date) {
            return '-';
        }
        return date('d/m/Y', strtotime($date));
    }
}

if (!function_exists('setFlashMessage')) {
    function setFlashMessage($type, $message) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION['flash_message'] = [
            'type' => $type,
            'message' => $message
        ];
    }
}

if (!function_exists('showFlashMessage')) {
    function showFlashMessage() {
        if (session_status() === PHP_SESSION_ACTIVE && isset($_SESSION['flash_message'])) {
            $type = $_SESSION['flash_message']['type'];
            $message = $_SESSION['flash_message']['message'];
            
            echo '<div class="alert alert-' . $type . ' alert-dismissible fade show" role="alert">
                    ' . $message . '
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                  </div>';
            
            unset($_SESSION['flash_message']);
        }
    }
}

if (!function_exists('redirect')) {
    function redirect($url) {
        header('Location: ' . $url);
        exit();
    }
}

if (!function_exists('generateBookingCode')) {
    function generateBookingCode() {
        return 'KSC' . date('Ymd') . strtoupper(substr(md5(microtime()), 0, 6));
    }
}

if (!function_exists('generatePaymentCode')) {
    function generatePaymentCode() {
        return 'PAY' . date('YmdHis') . rand(100, 999);
    }
}

if (!function_exists('getStatusBadge')) {
    function getStatusBadge($status) {
        $badges = [
            'menunggu' => 'warning',
            'disetujui' => 'success',
            'ditolak' => 'danger',
            'selesai' => 'info',
            'dibatalkan' => 'secondary',
            'pending' => 'warning',
            'lunas' => 'success',
            'dp_lunas' => 'primary',
            'gagal' => 'danger',
            'expired' => 'secondary',
            'tersedia' => 'success',
            'dibooking' => 'warning',
            'blokir' => 'danger'
        ];
        
        $class = isset($badges[$status]) ? $badges[$status] : 'secondary';
        return '<span class="badge bg-' . $class . '">' . ucfirst($status) . '</span>';
    }
}

if (!function_exists('generateQRCode')) {
    function generateQRCode($data) {
        // Simulasi generate QR code
        $qrData = base64_encode(json_encode($data));
        return 'data:image/png;base64,' . $qrData;
    }
}

// Fungsi bantu untuk admin
if (!function_exists('getUserRoleBadge')) {
    function getUserRoleBadge($role) {
        $badges = [
            'admin' => 'danger',
            'penyewa' => 'primary'
        ];
        
        $class = isset($badges[$role]) ? $badges[$role] : 'secondary';
        return '<span class="badge bg-' . $class . '">' . ucfirst($role) . '</span>';
    }
}

if (!function_exists('getPaymentMethodIcon')) {
    function getPaymentMethodIcon($method) {
        $icons = [
            'qris' => 'fa-qrcode',
            'transfer' => 'fa-university',
            'cash' => 'fa-money-bill'
        ];
        
        $icon = isset($icons[$method]) ? $icons[$method] : 'fa-money-bill';
        return '<i class="fas ' . $icon . '"></i> ' . ucfirst($method);
    }
}
// Tambahkan function ini di includes/functions.php
if (!function_exists('generateScheduleFromTemplate')) {
    function generateScheduleFromTemplate($start_date, $days = 7) {
        global $db;
        
        // Get all active templates
        $db->query('SELECT t.*, l.nama_lapangan 
                   FROM template_jadwal t
                   JOIN lapangan l ON t.id_lapangan = l.id_lapangan
                   WHERE t.status_aktif = 1');
        $templates = $db->resultSet();
        
        // Generate for each day
        for ($day = 0; $day < $days; $day++) {
            $current_date = date('Y-m-d', strtotime($start_date . " + $day days"));
            $day_name = date('l', strtotime($current_date));
            
            // Convert English day to Indonesian
            $day_map = [
                'Monday' => 'Senin',
                'Tuesday' => 'Selasa',
                'Wednesday' => 'Rabu',
                'Thursday' => 'Kamis',
                'Friday' => 'Jumat',
                'Saturday' => 'Sabtu',
                'Sunday' => 'Minggu'
            ];
            
            $indonesian_day = $day_map[$day_name] ?? $day_name;
            
            // Check if holiday
            $db->query('SELECT * FROM hari_libur WHERE tanggal = :tanggal');
            $db->bind(':tanggal', $current_date);
            $holiday = $db->single();
            
            // Skip if holiday with status "libur"
            if ($holiday && $holiday->status === 'libur') {
                continue;
            }
            
            // For each template matching the day
            foreach ($templates as $template) {
                if ($template->hari === $indonesian_day) {
                    // Check if schedule already exists
                    $db->query('SELECT id_jadwal FROM jadwal 
                               WHERE id_lapangan = :id_lapangan 
                               AND tanggal = :tanggal 
                               AND jam_mulai = :jam_mulai');
                    $db->bind(':id_lapangan', $template->id_lapangan);
                    $db->bind(':tanggal', $current_date);
                    $db->bind(':jam_mulai', $template->jam_mulai);
                    
                    if (!$db->single()) {
                        // Insert new schedule
                        $db->query('INSERT INTO jadwal 
                                   (id_lapangan, tanggal, jam_mulai, jam_selesai, harga, status_ketersediaan) 
                                   VALUES 
                                   (:id_lapangan, :tanggal, :jam_mulai, :jam_selesai, :harga, :status)');
                        
                        $db->bind(':id_lapangan', $template->id_lapangan);
                        $db->bind(':tanggal', $current_date);
                        $db->bind(':jam_mulai', $template->jam_mulai);
                        $db->bind(':jam_selesai', $template->jam_selesai);
                        $db->bind(':harga', $template->harga);
                        $db->bind(':status', 'tersedia');
                        
                        $db->execute();
                    }
                }
            }
        }
        
        return true;
    }
}
?>