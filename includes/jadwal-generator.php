<?php
require_once 'database.php';

class JadwalGenerator {
    private $db;
    
    public function __construct() {
        $this->db = new Database();
    }
    
    /**
     * Generate template default untuk semua lapangan (untuk setup awal)
     */
    public function generateDefaultTemplates() {
        // Get semua lapangan aktif
        $this->db->query('SELECT * FROM lapangan WHERE status_lapangan = "aktif"');
        $lapangans = $this->db->resultSet();
        
        $total_templates = 0;
        
        foreach($lapangans as $lapangan) {
            // Harga dasar dari lapangan
            $harga_dasar = $lapangan->harga_per_jam;
            
            // Template untuk semua hari
            $hari_list = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'];
            
            foreach($hari_list as $hari) {
                // Tentukan harga berdasarkan hari
                $harga_normal = $harga_dasar;
                $harga_malam = $harga_dasar * 1.2; // +20% untuk malam
                
                // Weekend lebih mahal
                if($hari == 'Sabtu' || $hari == 'Minggu') {
                    $harga_normal = $harga_dasar * 1.3; // +30% weekend
                    $harga_malam = $harga_dasar * 1.5; // +50% weekend malam
                }
                
                // Jam operasional: 08:00 - 21:00
                $jam_mulai = 8;
                $jam_selesai = 21;
                
                for($jam = $jam_mulai; $jam < $jam_selesai; $jam++) {
                    $jam_mulai_str = str_pad($jam, 2, '0', STR_PAD_LEFT) . ':00:00';
                    $jam_selesai_str = str_pad($jam + 1, 2, '0', STR_PAD_LEFT) . ':00:00';
                    
                    // Tentukan harga berdasarkan waktu
                    $harga = ($jam >= 18) ? $harga_malam : $harga_normal; // Malam mulai jam 18:00
                    
                    // Check jika template sudah ada
                    $this->db->query('SELECT id_template FROM template_jadwal 
                                    WHERE id_lapangan = :id_lapangan 
                                    AND hari = :hari 
                                    AND jam_mulai = :jam_mulai');
                    $this->db->bind(':id_lapangan', $lapangan->id_lapangan);
                    $this->db->bind(':hari', $hari);
                    $this->db->bind(':jam_mulai', $jam_mulai_str);
                    $existing = $this->db->single();
                    
                    if(!$existing) {
                        $this->db->query('INSERT INTO template_jadwal 
                                        (id_lapangan, hari, jam_mulai, jam_selesai, harga, status_aktif) 
                                        VALUES (:id_lapangan, :hari, :jam_mulai, :jam_selesai, :harga, 1)');
                        
                        $this->db->bind(':id_lapangan', $lapangan->id_lapangan);
                        $this->db->bind(':hari', $hari);
                        $this->db->bind(':jam_mulai', $jam_mulai_str);
                        $this->db->bind(':jam_selesai', $jam_selesai_str);
                        $this->db->bind(':harga', $harga);
                        
                        if($this->db->execute()) {
                            $total_templates++;
                        }
                    }
                }
            }
        }
        
        return $total_templates;
    }
    
    /**
     * Generate jadwal untuk hari tertentu
     */
    public function generateJadwalForDate($tanggal) {
        // Check hari libur
        if($this->isHariLibur($tanggal)) {
            return 0; // Tidak generate karena libur
        }
        
        // Check apakah sudah ada jadwal untuk tanggal ini
        $this->db->query('SELECT COUNT(*) as total FROM jadwal WHERE tanggal = :tanggal');
        $this->db->bind(':tanggal', $tanggal);
        $result = $this->db->single();
        
        if($result->total > 0) {
            return false; // Sudah ada jadwal
        }
        
        // Get nama hari Indonesia
        $hari_indonesia = $this->getHariIndonesia($tanggal);
        
        // Get semua lapangan aktif
        $this->db->query('SELECT * FROM lapangan WHERE status_lapangan = "aktif"');
        $lapangans = $this->db->resultSet();
        
        $total_generated = 0;
        
        foreach($lapangans as $lapangan) {
            // Get template untuk hari ini
            $this->db->query('SELECT * FROM template_jadwal 
                            WHERE id_lapangan = :id_lapangan 
                            AND hari = :hari 
                            AND status_aktif = 1
                            ORDER BY jam_mulai');
            $this->db->bind(':id_lapangan', $lapangan->id_lapangan);
            $this->db->bind(':hari', $hari_indonesia);
            $templates = $this->db->resultSet();
            
            // Jika tidak ada template, buat default dulu
            if(count($templates) == 0) {
                // Buat template default untuk lapangan ini
                $this->createDefaultTemplateForLapangan($lapangan->id_lapangan, $hari_indonesia);
                
                // Get template lagi setelah dibuat
                $this->db->query('SELECT * FROM template_jadwal 
                                WHERE id_lapangan = :id_lapangan 
                                AND hari = :hari 
                                AND status_aktif = 1
                                ORDER BY jam_mulai');
                $this->db->bind(':id_lapangan', $lapangan->id_lapangan);
                $this->db->bind(':hari', $hari_indonesia);
                $templates = $this->db->resultSet();
            }
            
            foreach($templates as $template) {
                // Insert jadwal
                $this->db->query('INSERT INTO jadwal 
                                (id_lapangan, tanggal, jam_mulai, jam_selesai, harga, status_ketersediaan) 
                                VALUES (:id_lapangan, :tanggal, :jam_mulai, :jam_selesai, :harga, "tersedia")');
                
                $this->db->bind(':id_lapangan', $lapangan->id_lapangan);
                $this->db->bind(':tanggal', $tanggal);
                $this->db->bind(':jam_mulai', $template->jam_mulai);
                $this->db->bind(':jam_selesai', $template->jam_selesai);
                $this->db->bind(':harga', $template->harga);
                
                if($this->db->execute()) {
                    $total_generated++;
                }
            }
        }
        
        // Log generate
        $this->logGenerate($tanggal, $total_generated);
        
        return $total_generated;
    }
    
    /**
     * Create default template untuk lapangan yang belum punya template
     */
    private function createDefaultTemplateForLapangan($id_lapangan, $hari) {
        // Get info lapangan
        $this->db->query('SELECT * FROM lapangan WHERE id_lapangan = :id');
        $this->db->bind(':id', $id_lapangan);
        $lapangan = $this->db->single();
        
        if(!$lapangan) return false;
        
        $harga_dasar = $lapangan->harga_per_jam;
        
        // Tentukan harga berdasarkan hari
        $harga_normal = $harga_dasar;
        $harga_malam = $harga_dasar * 1.2; // +20% untuk malam
        
        // Weekend lebih mahal
        if($hari == 'Sabtu' || $hari == 'Minggu') {
            $harga_normal = $harga_dasar * 1.3; // +30% weekend
            $harga_malam = $harga_dasar * 1.5; // +50% weekend malam
        }
        
        // Jam operasional: 08:00 - 21:00
        $jam_mulai = 8;
        $jam_selesai = 21;
        
        for($jam = $jam_mulai; $jam < $jam_selesai; $jam++) {
            $jam_mulai_str = str_pad($jam, 2, '0', STR_PAD_LEFT) . ':00:00';
            $jam_selesai_str = str_pad($jam + 1, 2, '0', STR_PAD_LEFT) . ':00:00';
            
            // Tentukan harga berdasarkan waktu
            $harga = ($jam >= 18) ? $harga_malam : $harga_normal; // Malam mulai jam 18:00
            
            $this->db->query('INSERT INTO template_jadwal 
                            (id_lapangan, hari, jam_mulai, jam_selesai, harga, status_aktif) 
                            VALUES (:id_lapangan, :hari, :jam_mulai, :jam_selesai, :harga, 1)');
            
            $this->db->bind(':id_lapangan', $id_lapangan);
            $this->db->bind(':hari', $hari);
            $this->db->bind(':jam_mulai', $jam_mulai_str);
            $this->db->bind(':jam_selesai', $jam_selesai_str);
            $this->db->bind(':harga', $harga);
            
            $this->db->execute();
        }
        
        return true;
    }
    
    /**
     * Generate jadwal untuk tanggal tertentu dan lapangan tertentu
     */
    public function ensureJadwalExists($tanggal, $id_lapangan = null) {
        // Check hari libur
        if($this->isHariLibur($tanggal)) {
            return 0;
        }
        
        if($id_lapangan) {
            // Check apakah sudah ada jadwal untuk lapangan ini di tanggal ini
            $this->db->query('SELECT COUNT(*) as total FROM jadwal 
                            WHERE tanggal = :tanggal AND id_lapangan = :id_lapangan');
            $this->db->bind(':id_lapangan', $id_lapangan);
        } else {
            // Check apakah sudah ada jadwal untuk tanggal ini
            $this->db->query('SELECT COUNT(*) as total FROM jadwal WHERE tanggal = :tanggal');
        }
        $this->db->bind(':tanggal', $tanggal);
        $result = $this->db->single();
        
        if($result->total == 0) {
            // Generate jadwal
            if($id_lapangan) {
                return $this->generateJadwalForLapangan($tanggal, $id_lapangan);
            } else {
                return $this->generateJadwalForDate($tanggal);
            }
        }
        
        return true; // Sudah ada
    }
    
    /**
     * Generate jadwal untuk lapangan tertentu saja
     */
    private function generateJadwalForLapangan($tanggal, $id_lapangan) {
        // Check hari libur
        if($this->isHariLibur($tanggal)) {
            return 0;
        }
        
        // Check apakah sudah ada
        $this->db->query('SELECT COUNT(*) as total FROM jadwal 
                        WHERE tanggal = :tanggal AND id_lapangan = :id_lapangan');
        $this->db->bind(':tanggal', $tanggal);
        $this->db->bind(':id_lapangan', $id_lapangan);
        $result = $this->db->single();
        
        if($result->total > 0) {
            return false;
        }
        
        // Get nama hari
        $hari_indonesia = $this->getHariIndonesia($tanggal);
        
        // Get template
        $this->db->query('SELECT * FROM template_jadwal 
                        WHERE id_lapangan = :id_lapangan 
                        AND hari = :hari 
                        AND status_aktif = 1
                        ORDER BY jam_mulai');
        $this->db->bind(':id_lapangan', $id_lapangan);
        $this->db->bind(':hari', $hari_indonesia);
        $templates = $this->db->resultSet();
        
        // Jika tidak ada template, buat default dulu
        if(count($templates) == 0) {
            $this->createDefaultTemplateForLapangan($id_lapangan, $hari_indonesia);
            
            // Get template lagi
            $this->db->query('SELECT * FROM template_jadwal 
                            WHERE id_lapangan = :id_lapangan 
                            AND hari = :hari 
                            AND status_aktif = 1
                            ORDER BY jam_mulai');
            $this->db->bind(':id_lapangan', $id_lapangan);
            $this->db->bind(':hari', $hari_indonesia);
            $templates = $this->db->resultSet();
        }
        
        $generated = 0;
        
        foreach($templates as $template) {
            $this->db->query('INSERT INTO jadwal 
                            (id_lapangan, tanggal, jam_mulai, jam_selesai, harga, status_ketersediaan) 
                            VALUES (:id_lapangan, :tanggal, :jam_mulai, :jam_selesai, :harga, "tersedia")');
            
            $this->db->bind(':id_lapangan', $id_lapangan);
            $this->db->bind(':tanggal', $tanggal);
            $this->db->bind(':jam_mulai', $template->jam_mulai);
            $this->db->bind(':jam_selesai', $template->jam_selesai);
            $this->db->bind(':harga', $template->harga);
            
            if($this->db->execute()) {
                $generated++;
            }
        }
        
        return $generated;
    }
    
    /**
     * Generate jadwal untuk beberapa hari ke depan
     */
    public function generateJadwalMultipleDays($start_date, $days_ahead = 7) {
        $total_all = 0;
        $dates = [];
        
        for($i = 0; $i < $days_ahead; $i++) {
            $tanggal = date('Y-m-d', strtotime($start_date . " +{$i} days"));
            $generated = $this->generateJadwalForDate($tanggal);
            
            if($generated !== false && $generated > 0) {
                $total_all += $generated;
                $dates[] = $tanggal;
            }
        }
        
        return [
            'total_generated' => $total_all,
            'dates' => $dates
        ];
    }
    
    /**
     * Check hari libur
     */
    public function isHariLibur($tanggal) {
        $this->db->query('SELECT * FROM hari_libur WHERE tanggal = :tanggal AND status = "libur"');
        $this->db->bind(':tanggal', $tanggal);
        $result = $this->db->single();
        return $result ? true : false;
    }
    
    /**
     * Get nama hari Indonesia
     */
    public function getHariIndonesia($tanggal) {
        $day_of_week = date('N', strtotime($tanggal));
        
        $hari_map = [
            1 => 'Senin',
            2 => 'Selasa',
            3 => 'Rabu',
            4 => 'Kamis',
            5 => 'Jumat',
            6 => 'Sabtu',
            7 => 'Minggu'
        ];
        
        return $hari_map[$day_of_week];
    }
    
    /**
     * Log generate activity
     */
    private function logGenerate($tanggal, $total_generated) {
        try {
            $this->db->query('INSERT INTO log_update_mingguan (tanggal_update, deskripsi, status) 
                            VALUES (:tanggal, :deskripsi, "success")');
            $this->db->bind(':tanggal', $tanggal);
            $this->db->bind(':deskripsi', "Auto-generate $total_generated jadwal untuk tanggal $tanggal");
            $this->db->execute();
        } catch(Exception $e) {
            // Skip log error
        }
    }
    
    /**
     * Get template untuk lapangan dan hari tertentu
     */
    public function getTemplate($id_lapangan, $hari) {
        $this->db->query('SELECT * FROM template_jadwal 
                        WHERE id_lapangan = :id_lapangan 
                        AND hari = :hari 
                        AND status_aktif = 1
                        ORDER BY jam_mulai');
        $this->db->bind(':id_lapangan', $id_lapangan);
        $this->db->bind(':hari', $hari);
        return $this->db->resultSet();
    }
    
    /**
     * Copy template dari hari lain
     */
    public function copyTemplate($id_lapangan, $from_hari, $to_hari) {
        // Get template from source day
        $templates = $this->getTemplate($id_lapangan, $from_hari);
        
        $copied = 0;
        foreach($templates as $template) {
            // Check if template already exists for target day
            $this->db->query('SELECT id_template FROM template_jadwal 
                            WHERE id_lapangan = :id_lapangan 
                            AND hari = :hari 
                            AND jam_mulai = :jam_mulai');
            $this->db->bind(':id_lapangan', $id_lapangan);
            $this->db->bind(':hari', $to_hari);
            $this->db->bind(':jam_mulai', $template->jam_mulai);
            $existing = $this->db->single();
            
            if(!$existing) {
                $this->db->query('INSERT INTO template_jadwal 
                                (id_lapangan, hari, jam_mulai, jam_selesai, harga, status_aktif) 
                                VALUES (:id_lapangan, :hari, :jam_mulai, :jam_selesai, :harga, 1)');
                
                $this->db->bind(':id_lapangan', $id_lapangan);
                $this->db->bind(':hari', $to_hari);
                $this->db->bind(':jam_mulai', $template->jam_mulai);
                $this->db->bind(':jam_selesai', $template->jam_selesai);
                $this->db->bind(':harga', $template->harga);
                
                if($this->db->execute()) {
                    $copied++;
                }
            }
        }
        
        return $copied;
    }
    
    /**
     * Delete jadwal untuk tanggal tertentu (untuk reset)
     */
    public function deleteJadwalForDate($tanggal) {
        $this->db->query('DELETE FROM jadwal WHERE tanggal = :tanggal');
        $this->db->bind(':tanggal', $tanggal);
        return $this->db->execute();
    }
    
    /**
     * Reset dan regenerate jadwal untuk tanggal tertentu
     */
    public function resetAndRegenerate($tanggal) {
        // Delete existing jadwal
        $this->deleteJadwalForDate($tanggal);
        
        // Regenerate
        return $this->generateJadwalForDate($tanggal);
    }
}
?>