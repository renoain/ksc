<?php
// Start session jika belum dimulai
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'ksc');

// Site configuration
define('SITE_URL', 'http://localhost/ksc/');
define('SITE_NAME', 'KSC');
define('ADMIN_URL', 'admin/');

// Payment configuration
define('QRIS_MERCHANT', 'KSC Sport Center');
define('PAYMENT_EXPIRED_HOURS', 24);
define('DP_PERCENTAGE', 50);

// Set timezone
date_default_timezone_set('Asia/Jakarta');

// Error reporting (development mode)
error_reporting(E_ALL);
ini_set('display_errors', 1);
?>