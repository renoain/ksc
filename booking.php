<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';
require_once 'includes/database.php';
require_once 'includes/functions.php';

$auth = new Auth();
$db = new Database();

if(!$auth->isLoggedIn()) {
    setFlashMessage('warning', 'Silakan login terlebih dahulu untuk booking lapangan.');
    redirect('login.php');
}

// Check if user has pending bookings limit
$db->query('SELECT COUNT(*) as total FROM pemesanan 
           WHERE id_user = :user_id 
           AND status_pemesanan = "menunggu" 
           AND DATE(tanggal_pesan) = CURDATE()');
$db->bind(':user_id', $_SESSION['user_id']);
$pending = $db->single();

$MAX_BOOKINGS_PER_DAY = 3;
if($pending->total >= $MAX_BOOKINGS_PER_DAY) {
    setFlashMessage('warning', 'Anda telah mencapai batas booking harian (max 3 booking). Silakan batalkan booking yang menunggu atau coba lagi besok.');
    redirect('riwayat.php');
}

// Get all active fields
$db->query('SELECT * FROM lapangan WHERE status_lapangan = "aktif" ORDER BY tipe_lapangan, nama_lapangan');
$fields = $db->resultSet();

// Get today's date and default values
$today = date('Y-m-d');
$selected_date = $_GET['date'] ?? $today;
$selected_field = $_GET['field'] ?? '';
$selected_type = $_GET['type'] ?? '';

// Process booking
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['book'])) {
    $id_jadwal = $_POST['id_jadwal'];
    $tipe_pembayaran = $_POST['tipe_pembayaran'];
    $dp_percent = ($tipe_pembayaran == 'dp') ? $_POST['dp_percent'] : 0;
    $catatan = $_POST['catatan'] ?? '';
    
    // Get schedule details
    $db->query('SELECT j.*, l.nama_lapangan, l.harga_per_jam 
               FROM jadwal j 
               JOIN lapangan l ON j.id_lapangan = l.id_lapangan
               WHERE j.id_jadwal = :id');
    $db->bind(':id', $id_jadwal);
    $jadwal = $db->single();
    
    if($jadwal && $jadwal->status_ketersediaan == 'tersedia') {
        // Calculate total price
        $harga_jam = $jadwal->harga ?: $jadwal->harga_per_jam;
        
        // Check if date is in the past
        if (strtotime($jadwal->tanggal) < strtotime(date('Y-m-d'))) {
            setFlashMessage('danger', 'Tidak dapat booking untuk tanggal yang sudah lewat.');
            redirect('booking.php');
        }
        
        // Check if holiday
        $db->query('SELECT * FROM hari_libur WHERE tanggal = :tanggal');
        $db->bind(':tanggal', $jadwal->tanggal);
        $holiday = $db->single();
        
        if($holiday && $holiday->status == 'libur') {
            setFlashMessage('danger', 'Tanggal ' . formatDate($jadwal->tanggal) . ' adalah hari libur. Tidak dapat melakukan booking.');
            redirect('booking.php');
        }
        
        // Start transaction
        $db->beginTransaction();
        
        try {
            // Create booking
            $db->query('INSERT INTO pemesanan 
                       (id_user, id_jadwal, total_harga, status_pemesanan, catatan) 
                       VALUES 
                       (:id_user, :id_jadwal, :total_harga, :status, :catatan)');
            
            $db->bind(':id_user', $_SESSION['user_id']);
            $db->bind(':id_jadwal', $id_jadwal);
            $db->bind(':total_harga', $harga_jam);
            $db->bind(':status', 'menunggu');
            $db->bind(':catatan', $catatan);
            
            if($db->execute()) {
                $booking_id = $db->lastInsertId();
                
                // Update schedule status
                $db->query('UPDATE jadwal SET status_ketersediaan = "dibooking" WHERE id_jadwal = :id');
                $db->bind(':id', $id_jadwal);
                $db->execute();
                
                // Create payment
                require_once 'includes/payment-system.php';
                $payment = new PaymentSystem();
                $payment_code = $payment->createPayment($booking_id, $tipe_pembayaran, $dp_percent);
                
                if($payment_code) {
                    $db->commit();
                    setFlashMessage('success', 'Booking berhasil! Silakan lakukan pembayaran.');
                    redirect('payment.php?code=' . $payment_code);
                } else {
                    $db->rollBack();
                    setFlashMessage('danger', 'Gagal membuat pembayaran. Silakan coba lagi.');
                }
            }
        } catch (Exception $e) {
            $db->rollBack();
            setFlashMessage('danger', 'Terjadi kesalahan: ' . $e->getMessage());
        }
    } else {
        setFlashMessage('danger', 'Gagal melakukan booking. Slot tidak tersedia.');
    }
}

$page_title = 'Booking Lapangan';
require_once 'includes/header.php';
?>

<div class="container">
    <h2 class="mb-4"><i class="fas fa-calendar-plus"></i> Booking Lapangan</h2>
    
    <!-- Booking Steps -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="row">
                <div class="col-md-3">
                    <div class="text-center">
                        <div class="step-circle active">1</div>
                        <h6 class="mt-2">Pilih Jadwal</h6>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="text-center">
                        <div class="step-circle">2</div>
                        <h6 class="mt-2">Konfirmasi</h6>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="text-center">
                        <div class="step-circle">3</div>
                        <h6 class="mt-2">Pembayaran</h6>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="text-center">
                        <div class="step-circle">4</div>
                        <h6 class="mt-2">Selesai</h6>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row">
        <!-- Filter Section -->
        <div class="col-md-4">
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-filter"></i> Filter Pencarian</h5>
                </div>
                <div class="card-body">
                    <form id="filterForm">
                        <div class="mb-3">
                            <label class="form-label">Tipe Lapangan</label>
                            <select class="form-select" id="tipe_lapangan">
                                <option value="">Semua Tipe</option>
                                <option value="Futsal" <?= $selected_type == 'Futsal' ? 'selected' : '' ?>>Futsal</option>
                                <option value="Badminton" <?= $selected_type == 'Badminton' ? 'selected' : '' ?>>Badminton</option>
                                <option value="Voli" <?= $selected_type == 'Voli' ? 'selected' : '' ?>>Voli</option>
                                <option value="Basket" <?= $selected_type == 'Basket' ? 'selected' : '' ?>>Basket</option>
                                <option value="Tennis" <?= $selected_type == 'Tennis' ? 'selected' : '' ?>>Tennis</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Tanggal Main</label>
                            <input type="date" class="form-control" id="tanggal" 
                                   value="<?= $selected_date ?>" 
                                   min="<?= date('Y-m-d') ?>"
                                   max="<?= date('Y-m-d', strtotime('+30 days')) ?>">
                            <small class="text-muted">Maksimal booking 30 hari ke depan</small>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Lapangan</label>
                            <select class="form-select" id="lapangan">
                                <option value="">Semua Lapangan</option>
                                <?php foreach($fields as $field): ?>
                                <option value="<?= $field->id_lapangan ?>" 
                                        data-type="<?= $field->tipe_lapangan ?>"
                                        <?= $selected_field == $field->id_lapangan ? 'selected' : '' ?>>
                                    <?= $field->nama_lapangan ?> (<?= $field->tipe_lapangan ?>)
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <button type="button" class="btn btn-primary w-100" id="btnCari">
                            <i class="fas fa-search"></i> Cari Jadwal Tersedia
                        </button>
                    </form>
                </div>
            </div>
            
            <!-- Info Section -->
            <div class="card">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0"><i class="fas fa-info-circle"></i> Informasi Booking</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <h6>Status Jadwal:</h6>
                        <div class="d-flex align-items-center mb-2">
                            <div class="status-indicator bg-success me-2"></div>
                            <span>Tersedia</span>
                        </div>
                        <div class="d-flex align-items-center mb-2">
                            <div class="status-indicator bg-warning me-2"></div>
                            <span>Dibooking</span>
                        </div>
                        <div class="d-flex align-items-center">
                            <div class="status-indicator bg-danger me-2"></div>
                            <span>Tidak Tersedia</span>
                        </div>
                    </div>
                    
                    <hr>
                    
                    <div class="mb-3">
                        <h6>Jam Operasional:</h6>
                        <p class="mb-1">Senin - Jumat: 08:00 - 22:00</p>
                        <p class="mb-1">Sabtu - Minggu: 07:00 - 23:00</p>
                    </div>
                    
                    <div class="alert alert-warning">
                        <small>
                            <i class="fas fa-exclamation-triangle"></i>
                            <strong>Perhatian:</strong><br>
                            • Max 3 booking per hari<br>
                            • Booking bisa dibatalkan sebelum pembayaran<br>
                            • DP minimal 30% dari total harga
                        </small>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Schedule Results -->
        <div class="col-md-8">
            <div id="loading" class="text-center d-none">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p class="mt-2">Memuat jadwal...</p>
            </div>
            
            <div id="scheduleResult" class="mb-4">
                <!-- Schedule will be loaded here via AJAX -->
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> Silakan pilih filter untuk melihat jadwal tersedia.
                </div>
            </div>
            
            <!-- My Pending Bookings -->
            <div class="card">
                <div class="card-header bg-warning">
                    <h5 class="mb-0"><i class="fas fa-clock"></i> Booking Menunggu Pembayaran</h5>
                </div>
                <div class="card-body">
                    <?php
                    $db->query('SELECT p.*, j.tanggal, j.jam_mulai, j.jam_selesai,
                                       l.nama_lapangan, l.tipe_lapangan
                                FROM pemesanan p
                                JOIN jadwal j ON p.id_jadwal = j.id_jadwal
                                JOIN lapangan l ON j.id_lapangan = l.id_lapangan
                                WHERE p.id_user = :user_id 
                                AND p.status_pemesanan = "menunggu"
                                ORDER BY p.tanggal_pesan DESC
                                LIMIT 3');
                    $db->bind(':user_id', $_SESSION['user_id']);
                    $pending_bookings = $db->resultSet();
                    
                    if(empty($pending_bookings)): ?>
                        <p class="text-muted mb-0">Tidak ada booking yang menunggu pembayaran.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Lapangan</th>
                                        <th>Tanggal</th>
                                        <th>Jam</th>
                                        <th>Status</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($pending_bookings as $booking): ?>
                                    <tr>
                                        <td>
                                            <?= $booking->nama_lapangan ?><br>
                                            <small class="text-muted"><?= $booking->tipe_lapangan ?></small>
                                        </td>
                                        <td><?= formatDate($booking->tanggal) ?></td>
                                        <td><?= substr($booking->jam_mulai, 0, 5) ?> - <?= substr($booking->jam_selesai, 0, 5) ?></td>
                                        <td>
                                            <span class="badge bg-warning">Menunggu Bayar</span>
                                        </td>
                                        <td>
                                            <a href="riwayat.php" class="btn btn-sm btn-outline-primary">
                                                <i class="fas fa-money-bill-wave"></i> Bayar
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="text-center mt-2">
                            <a href="riwayat.php" class="btn btn-sm btn-outline-secondary">
                                Lihat Semua <i class="fas fa-arrow-right"></i>
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Booking Modal -->
<div class="modal fade" id="bookingModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="fas fa-calendar-check"></i> Konfirmasi Booking</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="bookingDetails" class="mb-4">
                    <!-- Booking details will be loaded here -->
                </div>
                
                <form id="bookingForm" method="POST">
                    <input type="hidden" name="id_jadwal" id="id_jadwal">
                    
                    <div class="mb-4">
                        <h6><i class="fas fa-credit-card"></i> Pilihan Pembayaran</h6>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-check payment-option">
                                    <input class="form-check-input" type="radio" name="tipe_pembayaran" 
                                           id="fullPayment" value="full" checked>
                                    <label class="form-check-label" for="fullPayment">
                                        <div class="payment-card">
                                            <div class="payment-icon">
                                                <i class="fas fa-money-bill-wave"></i>
                                            </div>
                                            <div class="payment-info">
                                                <h6 class="mb-1">Full Payment</h6>
                                                <p class="text-muted mb-0">Bayar lunas sekali</p>
                                            </div>
                                        </div>
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-check payment-option">
                                    <input class="form-check-input" type="radio" name="tipe_pembayaran" 
                                           id="dpPayment" value="dp">
                                    <label class="form-check-label" for="dpPayment">
                                        <div class="payment-card">
                                            <div class="payment-icon">
                                                <i class="fas fa-hand-holding-usd"></i>
                                            </div>
                                            <div class="payment-info">
                                                <h6 class="mb-1">DP (Down Payment)</h6>
                                                <p class="text-muted mb-0">Bayar sebagian sekarang</p>
                                            </div>
                                        </div>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div id="dpOptions" class="mb-4 d-none">
                        <h6><i class="fas fa-percentage"></i> Pilihan DP</h6>
                        <div class="row g-3">
                            <div class="col-md-8">
                                <label class="form-label">Persentase DP</label>
                                <select class="form-select" name="dp_percent">
                                    <option value="30">30% (Minimum)</option>
                                    <option value="50" selected>50% (Rekomendasi)</option>
                                    <option value="70">70%</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Jumlah DP</label>
                                <div class="input-group">
                                    <span class="input-group-text">Rp</span>
                                    <input type="text" class="form-control" id="dpAmount" readonly>
                                </div>
                            </div>
                        </div>
                        <small class="text-muted">Sisa pembayaran dapat dilunasi sebelum waktu main dimulai</small>
                    </div>
                    
                    <div class="mb-4">
                        <h6><i class="fas fa-edit"></i> Catatan Tambahan</h6>
                        <textarea class="form-control" name="catatan" rows="3" 
                                  placeholder="Tambahkan catatan jika diperlukan (opsional)..."></textarea>
                    </div>
                    
                    <div class="alert alert-info">
                        <h6><i class="fas fa-exclamation-circle"></i> Syarat & Ketentuan:</h6>
                        <ul class="mb-0 small">
                            <li>Booking akan diproses setelah pembayaran dikonfirmasi</li>
                            <li>Pembatalan booking maksimal 24 jam sebelum waktu main</li>
                            <li>DP tidak dapat dikembalikan jika pembatalan kurang dari 24 jam</li>
                            <li>Datang 15 menit sebelum waktu main untuk check-in</li>
                        </ul>
                    </div>
                    
                    <div class="d-grid gap-2">
                        <button type="submit" name="book" class="btn btn-success btn-lg">
                            <i class="fas fa-check-circle"></i> Konfirmasi & Lanjutkan ke Pembayaran
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<style>
/* Steps */
.step-circle {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    background: #e9ecef;
    color: #6c757d;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    font-weight: bold;
    margin: 0 auto;
}

.step-circle.active {
    background: #3498db;
    color: white;
}

/* Status Indicator */
.status-indicator {
    width: 20px;
    height: 20px;
    border-radius: 50%;
}

/* Payment Options */
.payment-option .form-check-input {
    display: none;
}

.payment-option .form-check-label {
    cursor: pointer;
    width: 100%;
}

.payment-card {
    border: 2px solid #dee2e6;
    border-radius: 10px;
    padding: 15px;
    display: flex;
    align-items: center;
    transition: all 0.3s;
}

.payment-card:hover {
    border-color: #adb5bd;
    background: #f8f9fa;
}

.payment-option .form-check-input:checked + .form-check-label .payment-card {
    border-color: #3498db;
    background: rgba(52, 152, 219, 0.05);
}

.payment-icon {
    width: 50px;
    height: 50px;
    background: #f8f9fa;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 15px;
    font-size: 1.5rem;
    color: #3498db;
}

.payment-info h6 {
    margin: 0;
    color: #2c3e50;
}

/* Time Slot Buttons */
.time-slot {
    position: relative;
    transition: all 0.3s;
}

.time-slot .price {
    font-size: 0.85rem;
    color: #28a745;
    font-weight: 600;
}

.time-slot.disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.time-slot.disabled .price {
    color: #6c757d;
}

.time-slot.booked {
    background: #fff3cd;
    border-color: #ffc107;
}

.time-slot.booked .price {
    color: #856404;
}

.time-slot.blocked {
    background: #f8d7da;
    border-color: #dc3545;
}

.time-slot.blocked .price {
    color: #721c24;
}

/* Holiday Alert */
.holiday-alert {
    background: linear-gradient(45deg, #ff9a9e, #fad0c4);
    border: none;
    color: #721c24;
}

/* Responsive */
@media (max-width: 768px) {
    .step-circle {
        width: 40px;
        height: 40px;
        font-size: 1.2rem;
    }
    
    .payment-card {
        padding: 10px;
    }
    
    .payment-icon {
        width: 40px;
        height: 40px;
        font-size: 1.2rem;
        margin-right: 10px;
    }
}
</style>

<script>
// Global variables
let currentTotalPrice = 0;

document.addEventListener('DOMContentLoaded', function() {
    // Load initial schedule if filters are set
    if(document.getElementById('tanggal').value) {
        loadSchedule();
    }
    
    // Search button click
    document.getElementById('btnCari').addEventListener('click', loadSchedule);
    
    // Payment type change
    document.querySelectorAll('input[name="tipe_pembayaran"]').forEach(radio => {
        radio.addEventListener('change', function() {
            const dpOptions = document.getElementById('dpOptions');
            dpOptions.classList.toggle('d-none', this.value !== 'dp');
            
            if(this.value === 'dp') {
                calculateDPAmount();
            }
        });
    });
    
    // DP percentage change
    const dpPercentSelect = document.querySelector('select[name="dp_percent"]');
    if(dpPercentSelect) {
        dpPercentSelect.addEventListener('change', calculateDPAmount);
    }
    
    // Filter field based on type
    document.getElementById('tipe_lapangan').addEventListener('change', function() {
        const selectedType = this.value;
        const fieldSelect = document.getElementById('lapangan');
        const options = fieldSelect.options;
        
        for(let i = 0; i < options.length; i++) {
            const fieldType = options[i].getAttribute('data-type');
            if(!selectedType || fieldType === selectedType) {
                options[i].style.display = '';
            } else {
                options[i].style.display = 'none';
                if(options[i].selected) {
                    options[i].selected = false;
                }
            }
        }
    });
});

function loadSchedule() {
    const tipe = document.getElementById('tipe_lapangan').value;
    const tanggal = document.getElementById('tanggal').value;
    const lapangan = document.getElementById('lapangan').value;
    
    // Validate date
    const today = new Date().toISOString().split('T')[0];
    const maxDate = new Date();
    maxDate.setDate(maxDate.getDate() + 30);
    const maxDateStr = maxDate.toISOString().split('T')[0];
    
    if(tanggal < today) {
        alert('Tidak dapat memilih tanggal yang sudah lewat');
        document.getElementById('tanggal').value = today;
        return;
    }
    
    if(tanggal > maxDateStr) {
        alert('Maksimal booking 30 hari ke depan');
        document.getElementById('tanggal').value = maxDateStr;
        return;
    }
    
    document.getElementById('loading').classList.remove('d-none');
    document.getElementById('scheduleResult').innerHTML = '';
    
    const params = new URLSearchParams();
    if(tipe) params.append('tipe', tipe);
    params.append('tanggal', tanggal);
    if(lapangan) params.append('lapangan', lapangan);
    
    fetch(`ajax/get_schedule.php?${params.toString()}`)
        .then(response => {
            if(!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.text();
        })
        .then(data => {
            document.getElementById('loading').classList.add('d-none');
            document.getElementById('scheduleResult').innerHTML = data;
            
            // Add click handlers to time slot buttons
            document.querySelectorAll('.time-slot:not(.disabled)').forEach(button => {
                button.addEventListener('click', function() {
                    const jadwalId = this.getAttribute('data-jadwal-id');
                    const lapanganId = this.getAttribute('data-lapangan-id');
                    showBookingModal(jadwalId, lapanganId);
                });
            });
        })
        .catch(error => {
            document.getElementById('loading').classList.add('d-none');
            document.getElementById('scheduleResult').innerHTML = 
                '<div class="alert alert-danger">Error loading schedule: ' + error.message + '</div>';
        });
}

function showBookingModal(jadwalId, lapanganId) {
    // Show loading state
    document.getElementById('bookingDetails').innerHTML = `
        <div class="text-center">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <p class="mt-2">Memuat detail jadwal...</p>
        </div>
    `;
    
    fetch(`ajax/get_schedule_detail.php?id=${jadwalId}&lapangan_id=${lapanganId}`)
        .then(response => {
            if(!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(data => {
            if(data.error) {
                alert(data.error);
                return;
            }
            
            currentTotalPrice = data.total_harga;
            
            const detailsHtml = `
                <div class="alert alert-success">
                    <h6><i class="fas fa-check-circle"></i> Detail Booking</h6>
                    <div class="row mt-3">
                        <div class="col-md-6">
                            <p class="mb-1"><strong>Lapangan:</strong> ${data.nama_lapangan}</p>
                            <p class="mb-1"><strong>Tipe:</strong> ${data.tipe_lapangan}</p>
                            <p class="mb-1"><strong>Tanggal:</strong> ${data.tanggal_formatted}</p>
                        </div>
                        <div class="col-md-6">
                            <p class="mb-1"><strong>Jam:</strong> ${data.jam_mulai} - ${data.jam_selesai}</p>
                            <p class="mb-1"><strong>Durasi:</strong> ${data.durasi} jam</p>
                            <p class="mb-0"><strong>Total Harga:</strong> <span class="h5 text-success">${data.harga_formatted}</span></p>
                        </div>
                    </div>
                </div>
            `;
            
            document.getElementById('bookingDetails').innerHTML = detailsHtml;
            document.getElementById('id_jadwal').value = jadwalId;
            
            // Calculate DP amount if DP is selected
            if(document.getElementById('dpPayment').checked) {
                calculateDPAmount();
            }
            
            // Show modal
            const modal = new bootstrap.Modal(document.getElementById('bookingModal'));
            modal.show();
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Gagal memuat detail jadwal. Silakan coba lagi.');
        });
}

function calculateDPAmount() {
    const dpPercent = document.querySelector('select[name="dp_percent"]').value;
    const dpAmount = (currentTotalPrice * dpPercent) / 100;
    
    document.getElementById('dpAmount').value = formatCurrencyInput(dpAmount);
}

function formatCurrencyInput(amount) {
    return amount.toLocaleString('id-ID');
}
</script>

<?php require_once 'includes/footer.php'; ?>