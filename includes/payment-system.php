<?php
require_once 'database.php';

class PaymentSystem {
    private $db;
    
    public function __construct() {
        $this->db = new Database();
    }
    
    public function createPayment($pemesanan_id, $tipe_pembayaran = 'full', $dp_percent = 50) {
        try {
            // Get booking data
            $this->db->query('SELECT p.*, j.harga, j.tanggal, j.jam_mulai, j.jam_selesai, l.nama_lapangan, l.harga_per_jam 
                             FROM pemesanan p 
                             JOIN jadwal j ON p.id_jadwal = j.id_jadwal
                             JOIN lapangan l ON j.id_lapangan = l.id_lapangan
                             WHERE p.id_pemesanan = :id');
            $this->db->bind(':id', $pemesanan_id);
            $booking = $this->db->single();
            
            if(!$booking) {
                return false;
            }
            
            // Calculate payment
            $total_harga = $booking->total_harga;
            $jumlah_bayar = $total_harga;
            $sisa_tagihan = 0;
            $dp_amount = 0;
            
            if($tipe_pembayaran == 'dp') {
                $dp_amount = ($total_harga * $dp_percent) / 100;
                $jumlah_bayar = $dp_amount;
                $sisa_tagihan = $total_harga - $dp_amount;
            }
            
            // Generate QRIS payment data
            $payment_data = [
                'booking_id' => $pemesanan_id,
                'amount' => $jumlah_bayar,
                'merchant' => QRIS_MERCHANT,
                'timestamp' => time()
            ];
            
            // Generate QR code
            $qr_code = $this->generateQRCodeImage($payment_data);
            $kode_pembayaran = $this->generatePaymentCode();
            $expired_time = date('Y-m-d H:i:s', strtotime('+' . PAYMENT_EXPIRED_HOURS . ' hours'));
            
            // Insert payment record
            $this->db->query('INSERT INTO pembayaran 
                             (id_pemesanan, tipe_pembayaran, dp_percent, dp_amount, jumlah_bayar, sisa_tagihan, 
                              kode_pembayaran, metode_bayar, external_id, payment_url, expired_time, 
                              status_bayar, qr_code) 
                             VALUES 
                             (:id_pemesanan, :tipe, :dp_percent, :dp_amount, :jumlah_bayar, :sisa_tagihan,
                              :kode, :metode, :external_id, :payment_url, :expired_time,
                              :status, :qr_code)');
            
            $this->db->bind(':id_pemesanan', $pemesanan_id);
            $this->db->bind(':tipe', $tipe_pembayaran);
            $this->db->bind(':dp_percent', $dp_percent);
            $this->db->bind(':dp_amount', $dp_amount);
            $this->db->bind(':jumlah_bayar', $jumlah_bayar);
            $this->db->bind(':sisa_tagihan', $sisa_tagihan);
            $this->db->bind(':kode', $kode_pembayaran);
            $this->db->bind(':metode', 'qris');
            $this->db->bind(':external_id', 'PAY_' . time() . '_' . $pemesanan_id);
            $this->db->bind(':payment_url', SITE_URL . 'payment.php?code=' . $kode_pembayaran);
            $this->db->bind(':expired_time', $expired_time);
            $this->db->bind(':status', 'pending');
            $this->db->bind(':qr_code', $qr_code);
            
            if($this->db->execute()) {
                $payment_id = $this->db->lastInsertId();
                
                // Create payment history
                $this->db->query('INSERT INTO pembayaran_history 
                                 (id_pembayaran, jumlah_bayar, metode_bayar, catatan) 
                                 VALUES 
                                 (:id_pembayaran, :jumlah_bayar, :metode, :catatan)');
                
                $this->db->bind(':id_pembayaran', $payment_id);
                $this->db->bind(':jumlah_bayar', $jumlah_bayar);
                $this->db->bind(':metode', 'qris');
                $this->db->bind(':catatan', 'Pembayaran ' . ($tipe_pembayaran == 'dp' ? 'DP ' . $dp_percent . '%' : 'Full') . ' dibuat');
                
                $this->db->execute();
                
                return $kode_pembayaran;
            }
            
            return false;
            
        } catch (Exception $e) {
            error_log('Error creating payment: ' . $e->getMessage());
            return false;
        }
    }
    
    private function generateQRCodeImage($data) {
        // Generate simple QR code using Google Charts API
        $text = urlencode(json_encode($data));
        $size = '150x150';
        $url = "https://chart.googleapis.com/chart?chs={$size}&cht=qr&chl={$text}";
        
        // Get the image content
        $imageContent = file_get_contents($url);
        if ($imageContent !== false) {
            return 'data:image/png;base64,' . base64_encode($imageContent);
        }
        
        // Fallback to simple base64 if API fails
        return 'data:image/png;base64,' . base64_encode(json_encode($data));
    }
    
    private function generatePaymentCode() {
        return 'PAY' . date('YmdHis') . rand(100, 999);
    }
    
    public function processPayment($payment_code, $bukti_pembayaran = null) {
        try {
            // Get payment data
            $this->db->query('SELECT * FROM pembayaran WHERE kode_pembayaran = :code');
            $this->db->bind(':code', $payment_code);
            $payment = $this->db->single();
            
            if(!$payment) {
                return false;
            }
            
            // Check if expired
            if(strtotime($payment->expired_time) < time()) {
                $this->updatePaymentStatus($payment->id_pembayaran, 'expired');
                return false;
            }
            
            // Update payment status
            $status = ($payment->tipe_pembayaran == 'dp' && $payment->sisa_tagihan > 0) ? 'dp_lunas' : 'lunas';
            
            $this->db->query('UPDATE pembayaran 
                             SET status_bayar = :status, 
                                 tanggal_bayar = NOW(),
                                 bukti_pembayaran = :bukti
                             WHERE id_pembayaran = :id');
            
            $this->db->bind(':status', $status);
            $this->db->bind(':bukti', $bukti_pembayaran);
            $this->db->bind(':id', $payment->id_pembayaran);
            
            if($this->db->execute()) {
                // Update booking status
                $this->db->query('UPDATE pemesanan 
                                 SET status_pemesanan = :status 
                                 WHERE id_pemesanan = :id');
                
                $this->db->bind(':status', 'disetujui');
                $this->db->bind(':id', $payment->id_pemesanan);
                $this->db->execute();
                
                // Update schedule status
                $this->db->query('UPDATE jadwal j 
                                 JOIN pemesanan p ON j.id_jadwal = p.id_jadwal
                                 SET j.status_ketersediaan = "dibooking" 
                                 WHERE p.id_pemesanan = :id');
                
                $this->db->bind(':id', $payment->id_pemesanan);
                $this->db->execute();
                
                // Create payment history
                $this->db->query('INSERT INTO pembayaran_history 
                                 (id_pembayaran, jumlah_bayar, metode_bayar, bukti_pembayaran, catatan) 
                                 VALUES 
                                 (:id_pembayaran, :jumlah_bayar, :metode, :bukti, :catatan)');
                
                $this->db->bind(':id_pembayaran', $payment->id_pembayaran);
                $this->db->bind(':jumlah_bayar', $payment->jumlah_bayar);
                $this->db->bind(':metode', 'qris');
                $this->db->bind(':bukti', $bukti_pembayaran);
                $this->db->bind(':catatan', 'Pembayaran ' . ($payment->tipe_pembayaran == 'dp' ? 'DP ' . $payment->dp_percent . '%' : 'Full') . ' dilunasi');
                
                $this->db->execute();
                
                return true;
            }
            
            return false;
            
        } catch (Exception $e) {
            error_log('Error processing payment: ' . $e->getMessage());
            return false;
        }
    }
    
    private function updatePaymentStatus($payment_id, $status) {
        $this->db->query('UPDATE pembayaran SET status_bayar = :status WHERE id_pembayaran = :id');
        $this->db->bind(':status', $status);
        $this->db->bind(':id', $payment_id);
        return $this->db->execute();
    }
    
    public function getPaymentByCode($code) {
        $this->db->query('SELECT p.*, pm.*, u.nama, u.email, l.nama_lapangan, j.tanggal, j.jam_mulai, j.jam_selesai
                         FROM pembayaran p
                         JOIN pemesanan pm ON p.id_pemesanan = pm.id_pemesanan
                         JOIN user u ON pm.id_user = u.id_user
                         JOIN jadwal j ON pm.id_jadwal = j.id_jadwal
                         JOIN lapangan l ON j.id_lapangan = l.id_lapangan
                         WHERE p.kode_pembayaran = :code');
        $this->db->bind(':code', $code);
        return $this->db->single();
    }
}
?>