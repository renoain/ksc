<?php
/**
 * CRON Job Script untuk Auto-Generate Jadwal
 * Jalankan setiap hari jam 00:01
 * Contoh cron: 1 0 * * * php /path/to/cron/generate-jadwal.php
 */

// Set waktu execution unlimited
set_time_limit(0);

// Simulate web environment
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['REQUEST_METHOD'] = 'GET';

require_once '../includes/config.php';
require_once '../includes/jadwal-generator.php';

// Log file
$log_file = 'generate_jadwal.log';
$log_message = "[" . date('Y-m-d H:i:s') . "] ";

try {
    $generator = new JadwalGenerator();
    
    // Generate untuk 7 hari ke depan
    $start_date = date('Y-m-d');
    $result = $generator->generateJadwalMultipleDays($start_date, 7);
    
    if($result['total_generated'] > 0) {
        $log_message .= "SUCCESS: Generated {$result['total_generated']} jadwal untuk 7 hari ke depan.\n";
        $log_message .= "Dates: " . implode(', ', $result['dates']) . "\n";
    } else {
        $log_message .= "INFO: Tidak ada jadwal yang digenerate (sudah ada semua atau hari libur).\n";
    }
    
} catch (Exception $e) {
    $log_message .= "ERROR: " . $e->getMessage() . "\n";
}

// Write to log file
file_put_contents($log_file, $log_message, FILE_APPEND);

// Output for manual run
if(php_sapi_name() === 'cli') {
    echo $log_message;
}
?>