<?php
// 🔥 DEBUG - Aktifkan error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/config.php';

// 🔥 CEK FUNGSI - Jika tidak ada, buat fallback
if (!function_exists('formatRupiah')) {
    function formatRupiah($number) {
        return 'Rp ' . number_format($number, 0, ',', '.');
    }
}

if (!function_exists('getSetting')) {
    function getSetting($key) {
        global $db;
        try {
            $stmt = $db->prepare("SELECT value FROM settings WHERE key = ?");
            $stmt->execute([$key]);
            $result = $stmt->fetch();
            return $result ? $result['value'] : null;
        } catch (Exception $e) {
            return null;
        }
    }
}

if (!function_exists('sendEmail')) {
    function sendEmail($to, $subject, $message) {
        $headers = "From: admin@rainbowprinting.web.id\r\n";
        $headers .= "Reply-To: admin@rainbowprinting.web.id\r\n";
        $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
        return @mail($to, $subject, $message, $headers);
    }
}

// 🔥 CEK SESSION CUSTOMER
if (!isset($_SESSION['customer_id'])) {
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
    header('Location: /login.php');
    exit;
}

$orderCode = $_GET['order'] ?? '';
if (empty($orderCode)) {
    header('Location: /customer/dashboard.php');
    exit;
}

$stmt = $db->prepare("SELECT * FROM orders WHERE order_code = ? AND customer_id = ?");
$stmt->execute([$orderCode, $_SESSION['customer_id']]);
$order = $stmt->fetch();
if (!$order) {
    $_SESSION['error'] = "Pesanan tidak ditemukan!";
    header('Location: /customer/dashboard.php');
    exit;
}

// 🔥 CEK TABEL payments - Tambahkan kolom payment_type jika belum ada
try {
    $db->query("SELECT payment_type FROM payments LIMIT 1");
} catch (PDOException $e) {
    $db->exec("ALTER TABLE payments ADD COLUMN payment_type VARCHAR(20) DEFAULT 'dp'");
}

// Hitung total pembayaran yang sudah terverifikasi
$totalPaidStmt = $db->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM payments WHERE order_id=? AND status IN ('verified','approved','paid')");
$totalPaidStmt->execute([$order['id']]);
$totalPaid = floatval($totalPaidStmt->fetch()['total']);
$sisaPembayaran = $order['total'] - $totalPaid;

// Cek apakah sudah lunas
if ($sisaPembayaran <= 0) {
    $_SESSION['success'] = "Pesanan ini sudah lunas!";
    session_write_close();
    header('Location: /customer/order-detail.php?order=' . urlencode($orderCode));
    exit;
}

// 🔥 Tentukan jenis pembayaran: DP atau Pelunasan
$isDp = ($totalPaid == 0);
$isPelunasan = ($totalPaid > 0 && $sisaPembayaran > 0);

// 🔥 AMBIL DAFTAR BANK DARI DATABASE
$bankList = [];
$bank1_name = getSetting('bank1_name');
$bank1_account = getSetting('bank1_account');
$bank1_holder = getSetting('bank1_name_holder');

if ($bank1_name && $bank1_account) {
    $bankList[] = [
        'bank' => $bank1_name,
        'account_number' => $bank1_account,
        'account_name' => $bank1_holder ?: 'Rainbow Printing'
    ];
}

$bank2_name = getSetting('bank2_name');
$bank2_account = getSetting('bank2_account');
$bank2_holder = getSetting('bank2_name_holder');

if ($bank2_name && $bank2_account) {
    $bankList[] = [
        'bank' => $bank2_name,
        'account_number' => $bank2_account,
        'account_name' => $bank2_holder ?: 'Rainbow Printing'
    ];
}

$bank3_name = getSetting('bank3_name');
$bank3_account = getSetting('bank3_account');
$bank3_holder = getSetting('bank3_name_holder');

if ($bank3_name && $bank3_account) {
    $bankList[] = [
        'bank' => $bank3_name,
        'account_number' => $bank3_account,
        'account_name' => $bank3_holder ?: 'Rainbow Printing'
    ];
}

// 🔥 Jika tidak ada bank, gunakan default
if (empty($bankList)) {
    $bankList = [
        ['bank' => 'BCA', 'account_number' => '1234567890', 'account_name' => 'Rainbow Printing'],
        ['bank' => 'Mandiri', 'account_number' => '9876543210', 'account_name' => 'Rainbow Printing'],
    ];
}

$errors = [];
$success = '';

// Proses upload bukti pembayaran
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_payment'])) {
    $bankName = trim($_POST['bank_name'] ?? '');
    $accountNumber = trim($_POST['account_number'] ?? '');
    $accountName = trim($_POST['account_name'] ?? '');
    $amount = floatval(str_replace(',', '', $_POST['amount'] ?? 0));
    $paymentType = $_POST['payment_type'] ?? 'dp';
    
    // 🔥 Validasi
    if (empty($bankName)) $errors[] = "❌ Nama bank harus diisi";
    if (empty($accountNumber)) $errors[] = "❌ Nomor rekening harus diisi";
    if (empty($accountName)) $errors[] = "❌ Nama pemilik rekening harus diisi";
    if ($amount <= 0) $errors[] = "❌ Jumlah transfer harus lebih dari 0";
    if ($amount > $sisaPembayaran) $errors[] = "❌ Jumlah transfer tidak boleh melebihi sisa pembayaran (Rp " . formatRupiah($sisaPembayaran) . ")";
    
    // 🔥 Validasi minimal DP (50%)
    if ($paymentType === 'dp' && $amount < ($order['total'] * 0.7)) {
        $errors[] = "❌ DP minimal 70% dari total pesanan (Rp " . formatRupiah($order['total'] * 0.7) . ")";
    }
    
    // 🔥 Validasi nomor rekening (hanya angka)
    if (!empty($accountNumber) && !preg_match('/^[0-9\-]+$/', $accountNumber)) {
        $errors[] = "❌ Nomor rekening hanya boleh angka dan tanda minus (-)";
    }
    
    // Cek upload file
    if (!isset($_FILES['proof_image']) || $_FILES['proof_image']['error'] !== UPLOAD_ERR_OK) {
        $errors[] = "❌ Bukti transfer wajib diupload";
    } else {
        $ext = strtolower(pathinfo($_FILES['proof_image']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg','jpeg','png','pdf'];
        if (!in_array($ext, $allowed)) {
            $errors[] = "❌ Format file harus JPG, JPEG, PNG, atau PDF";
        }
        if ($_FILES['proof_image']['size'] > 5 * 1024 * 1024) {
            $errors[] = "❌ Ukuran file maksimal 5MB";
        }
    }
    
    if (empty($errors)) {
        $uploadDir = __DIR__ . '/../uploads/proofs/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
        
        $filename = 'proof_' . $order['id'] . '_' . time() . '_' . uniqid() . '.' . $ext;
        if (move_uploaded_file($_FILES['proof_image']['tmp_name'], $uploadDir . $filename)) {
            try {
                $stmt = $db->prepare("INSERT INTO payments (order_id, amount, bank_name, account_number, account_name, proof_image, payment_type, status, created_at) 
                                       VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', datetime('now'))");
                $stmt->execute([
                    $order['id'],
                    $amount,
                    $bankName,
                    $accountNumber,
                    $accountName,
                    $filename,
                    $paymentType
                ]);
            } catch (PDOException $e) {
                $stmt = $db->prepare("INSERT INTO payments (order_id, amount, bank_name, account_number, account_name, proof_image, status, created_at) 
                                       VALUES (?, ?, ?, ?, ?, ?, 'pending', datetime('now'))");
                $stmt->execute([
                    $order['id'],
                    $amount,
                    $bankName,
                    $accountNumber,
                    $accountName,
                    $filename
                ]);
            }
            
            $db->prepare("UPDATE orders SET payment_status='pending_verification' WHERE id=?")->execute([$order['id']]);
            
            try {
                $adminEmail = getSetting('admin_email');
                if ($adminEmail) {
                    $paymentLabel = $paymentType === 'dp' ? 'DP' : 'PELUNASAN';
                    $subject = '📥 Bukti Pembayaran ' . $paymentLabel . ' - ' . $order['order_code'];
                    $message = "Ada bukti pembayaran baru untuk pesanan:\n\n";
                    $message .= "Kode Pesanan: " . $order['order_code'] . "\n";
                    $message .= "Customer: " . $order['customer_name'] . "\n";
                    $message .= "Jenis: " . $paymentLabel . "\n";
                    $message .= "Jumlah: Rp " . number_format($amount, 0, ',', '.') . "\n";
                    $message .= "Bank: " . $bankName . "\n";
                    $message .= "No. Rekening: " . $accountNumber . "\n";
                    $message .= "a.n: " . $accountName . "\n\n";
                    $message .= "Silakan verifikasi pembayaran di admin panel.\n";
                    $message .= "Link: https://rainbowprinting.web.id/admin/order-detail.php?id=" . $order['id'];
                    sendEmail($adminEmail, $subject, $message);
                }
            } catch (Exception $e) {
                // Abaikan error email
            }
            
            $_SESSION['success'] = "✅ Bukti pembayaran " . ($paymentType === 'dp' ? 'DP' : 'pelunasan') . " berhasil diupload! Menunggu verifikasi admin.";
            
            session_write_close();
            
            $redirectUrl = '/customer/order-detail.php?order=' . urlencode($orderCode);
            header('Location: ' . $redirectUrl);
            exit;
        } else {
            $errors[] = "❌ Gagal upload file. Silakan coba lagi.";
        }
    }
}

$pageTitle = 'Konfirmasi Pembayaran';
include '../includes/header.php';
?>

<style>
.payment-confirm-container {
    max-width: 700px;
    margin: 0 auto;
}
.payment-summary-box {
    background: #f8f9fa;
    padding: 20px;
    border-radius: 10px;
    margin-bottom: 25px;
    border-left: 4px solid #f39c12;
}
.payment-summary-box .amount {
    font-size: 28px;
    font-weight: bold;
    color: #2c3e50;
}
.payment-summary-box .label {
    color: #6c757d;
    font-size: 14px;
}
.payment-type-badge {
    display: inline-block;
    padding: 5px 15px;
    border-radius: 20px;
    font-weight: bold;
    font-size: 14px;
}
.payment-type-dp {
    background: #f39c12;
    color: #fff;
}
.payment-type-pelunasan {
    background: #27ae60;
    color: #fff;
}
.bank-card {
    border: 1px solid #dee2e6;
    border-radius: 8px;
    padding: 12px 15px;
    margin-bottom: 8px;
    background: #fff;
    cursor: pointer;
    transition: all 0.2s;
}
.bank-card:hover {
    border-color: #f39c12;
    background: #fef9e7;
}
.bank-card.selected {
    border-color: #f39c12;
    background: #fef9e7;
    box-shadow: 0 0 0 2px rgba(243, 156, 18, 0.2);
}
.bank-card .bank-name {
    font-weight: bold;
    font-size: 16px;
}
.bank-card .bank-detail {
    color: #6c757d;
    font-size: 13px;
}
.alert-danger {
    background: #f8d7da;
    color: #721c24;
    padding: 12px 15px;
    border-radius: 6px;
    margin-bottom: 15px;
    border: 1px solid #f5c6cb;
}
.alert-success {
    background: #d4edda;
    color: #155724;
    padding: 12px 15px;
    border-radius: 6px;
    margin-bottom: 15px;
    border: 1px solid #c3e6cb;
}
.form-group {
    margin-bottom: 15px;
}
.form-group label {
    display: block;
    font-weight: bold;
    margin-bottom: 5px;
}
.form-group input,
.form-group select {
    width: 100%;
    padding: 10px 12px;
    border: 1px solid #ddd;
    border-radius: 6px;
    font-size: 14px;
}
.btn {
    display: inline-block;
    padding: 8px 16px;
    border-radius: 6px;
    font-size: 14px;
    cursor: pointer;
    text-decoration: none;
    border: none;
    transition: all 0.3s;
}
.btn-primary {
    background: #2c3e50;
    color: #fff;
}
.btn-primary:hover {
    background: #1a252f;
}
.btn-primary:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}
.btn-outline {
    background: #fff;
    color: #2c3e50;
    border: 1px solid #2c3e50;
}
.btn-outline:hover {
    background: #f8f9fa;
}
.btn-sm {
    padding: 4px 10px;
    font-size: 12px;
}
.btn-loading {
    position: relative;
    padding-right: 40px;
}
.btn-loading::after {
    content: '';
    position: absolute;
    right: 12px;
    top: 50%;
    transform: translateY(-50%);
    width: 18px;
    height: 18px;
    border: 3px solid rgba(255,255,255,0.3);
    border-top-color: #fff;
    border-radius: 50%;
    animation: spin 0.8s linear infinite;
}
@keyframes spin {
    to { transform: translateY(-50%) rotate(360deg); }
}
.info-box {
    padding: 12px;
    border-radius: 6px;
    margin-bottom: 15px;
    border-left: 4px solid;
}
.info-box-dp {
    background: #fef9e7;
    border-color: #f39c12;
}
.info-box-dp strong {
    color: #f39c12;
}
.info-box-pelunasan {
    background: #e8f5e9;
    border-color: #27ae60;
}
.info-box-pelunasan strong {
    color: #27ae60;
}
.file-info {
    font-size: 12px;
    color: #999;
    margin-top: 4px;
}
.image-preview {
    margin-top: 10px;
    max-width: 200px;
    border-radius: 8px;
    border: 2px solid #e9ecef;
    padding: 8px;
    background: #fff;
    display: none;
}
.image-preview.show {
    display: block;
}
.image-preview img {
    width: 100%;
    height: auto;
    border-radius: 4px;
}
</style>

<div class="payment-confirm-container">
    <h1>💰 Konfirmasi Pembayaran</h1>
    
    <?php if (!empty($errors)): ?>
        <div class="alert-danger">
            <strong>❌ Terjadi kesalahan:</strong>
            <ul style="margin:5px 0 0 20px;">
                <?php foreach ($errors as $error): ?>
                    <li><?= htmlspecialchars($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert-success">
            <?= $_SESSION['success']; unset($_SESSION['success']); ?>
        </div>
    <?php endif; ?>
    
    <!-- 🔥 INFO BOX UNTUK DP -->
    <?php if ($isDp): ?>
        <div class="info-box info-box-dp">
            <strong>💡 Informasi DP</strong>
            <p style="margin:5px 0 0;font-size:13px;color:#555;">
                Minimal DP adalah <strong>70%</strong> dari total pesanan (<?= formatRupiah($order['total'] * 0.7) ?>).
                Anda bisa memilih tombol cepat di bawah.
            </p>
        </div>
    <?php endif; ?>
    
    <!-- 🔥 RINGKASAN PEMBAYARAN -->
    <div class="payment-summary-box">
        <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;">
            <div>
                <div class="label">Kode Pesanan</div>
                <strong style="font-size:18px;"><?= htmlspecialchars($order['order_code']) ?></strong>
            </div>
            <div style="text-align:right;">
                <div class="label">Sisa Pembayaran</div>
                <div class="amount"><?= formatRupiah($sisaPembayaran) ?></div>
            </div>
        </div>
        <div style="margin-top:12px;padding-top:12px;border-top:1px solid #dee2e6;display:flex;justify-content:space-between;flex-wrap:wrap;gap:10px;">
            <div>
                <span class="label">Total Pesanan:</span>
                <strong><?= formatRupiah($order['total']) ?></strong>
            </div>
            <div>
                <span class="label">Sudah Dibayar:</span>
                <strong><?= formatRupiah($totalPaid) ?></strong>
            </div>
            <div>
                <span class="label">Jenis Pembayaran:</span>
                <span class="payment-type-badge <?= $isDp ? 'payment-type-dp' : 'payment-type-pelunasan' ?>">
                    <?php if ($isDp): ?>
                        💰 DP (Minimal 70%)
                    <?php else: ?>
                        ✅ Pelunasan (Sisa: <?= formatRupiah($sisaPembayaran) ?>)
                    <?php endif; ?>
                </span>
            </div>
        </div>
        <?php if ($sisaPembayaran > 0 && $sisaPembayaran < $order['total']): ?>
            <div style="margin-top:10px;background:#fff;border-radius:4px;height:6px;overflow:hidden;">
                <div style="width:<?= round(($totalPaid/$order['total'])*100) ?>%;height:100%;background:linear-gradient(90deg,#f39c12,#27ae60);"></div>
            </div>
            <small style="color:#999;"><?= round(($totalPaid/$order['total'])*100) ?>% dari total sudah dibayar</small>
        <?php endif; ?>
    </div>
    
    <!-- 🔥 FORM -->
    <form method="POST" enctype="multipart/form-data" style="background:#fff;padding:25px;border-radius:10px;border:1px solid #dee2e6;" id="paymentForm">
        <input type="hidden" name="payment_type" value="<?= $isDp ? 'dp' : 'pelunasan' ?>">
        
        <h3 style="margin-top:0;">📋 Informasi Transfer</h3>
        
        <!-- 🔥 Pilih Bank Tujuan -->
        <div class="form-group">
            <label>Pilih Bank Tujuan</label>
            <?php foreach ($bankList as $index => $bank): ?>
            <div class="bank-card" onclick="selectBank(this, '<?= htmlspecialchars($bank['bank']) ?>', '<?= htmlspecialchars($bank['account_number']) ?>', '<?= htmlspecialchars($bank['account_name']) ?>')">
                <div class="bank-name">🏦 <?= htmlspecialchars($bank['bank']) ?></div>
                <div class="bank-detail">No. Rekening: <?= htmlspecialchars($bank['account_number']) ?></div>
                <div class="bank-detail">a.n. <?= htmlspecialchars($bank['account_name']) ?></div>
            </div>
            <?php endforeach; ?>
            <input type="hidden" name="bank_name" id="bank_name" required>
            <input type="hidden" name="account_number" id="account_number" required>
            <input type="hidden" name="account_name" id="account_name" required>
        </div>
        
        <!-- 🔥 Jumlah Transfer -->
        <div class="form-group">
            <label for="amount">
                Jumlah Transfer
                <span style="font-weight:normal;color:#6c757d;font-size:13px;">
                    (maks: <?= formatRupiah($sisaPembayaran) ?>)
                </span>
            </label>
            <div style="position:relative;">
                <span style="position:absolute;left:12px;top:50%;transform:translateY(-50%);font-weight:bold;color:#6c757d;">Rp</span>
                <input type="number" name="amount" id="amount" 
                       style="width:100%;padding:10px 10px 10px 40px;border:1px solid #ddd;border-radius:6px;font-size:16px;"
                       placeholder="Masukkan jumlah transfer" 
                       min="1" max="<?= $sisaPembayaran ?>" 
                       value="<?= $isDp ? round($order['total'] * 0.7) : $sisaPembayaran ?>" required>
            </div>
            
            <!-- 🔥 Tombol Cepat -->
            <div style="margin-top:5px;display:flex;gap:8px;flex-wrap:wrap;">
                <?php if ($isDp): ?>
                    <button type="button" onclick="setAmount(<?= round($order['total'] * 0.7) ?>)" class="btn btn-sm btn-outline" style="font-size:12px;">
                        70% (<?= formatRupiah($order['total'] * 0.7) ?>)
                    </button>
                    <button type="button" onclick="setAmount(<?= $sisaPembayaran ?>)" class="btn btn-sm btn-outline" style="font-size:12px;">
                        Lunas (<?= formatRupiah($sisaPembayaran) ?>)
                    </button>
                <?php else: ?>
                    <button type="button" onclick="setAmount(<?= $sisaPembayaran ?>)" class="btn btn-sm btn-primary" style="font-size:12px;">
                        ✅ Bayar Lunas (<?= formatRupiah($sisaPembayaran) ?>)
                    </button>
                <?php endif; ?>
            </div>
            
            <?php if ($isDp): ?>
                <small style="color:#f39c12;display:block;margin-top:5px;">
                    ⚠️ Minimal DP adalah 70% dari total pesanan
                </small>
            <?php endif; ?>
        </div>
        
        <!-- 🔥 Upload Bukti Transfer -->
        <div class="form-group">
            <label>
                Upload Bukti Transfer
                <span style="font-weight:normal;color:#6c757d;font-size:13px;">(JPG, PNG, PDF - maks 5MB)</span>
            </label>
            <input type="file" name="proof_image" accept=".jpg,.jpeg,.png,.pdf" 
                   style="width:100%;padding:10px;border:1px solid #ddd;border-radius:6px;" 
                   data-max-size="5242880" required
                   onchange="previewImage(this)">
            <div class="file-info" id="fileInfo">Maksimal ukuran file 5MB</div>
            <div class="image-preview" id="imagePreview">
                <img id="previewImg" src="#" alt="Preview bukti transfer">
            </div>
        </div>
        
        <!-- 🔥 Info Pelunasan -->
        <?php if ($isPelunasan): ?>
            <div class="info-box info-box-pelunasan">
                <strong>✅ Pelunasan</strong>
                <p style="margin:5px 0 0;font-size:13px;color:#555;">
                    Anda sudah membayar DP sebesar <?= formatRupiah($totalPaid) ?>. 
                    Silakan lunasi sisa pembayaran sebesar <?= formatRupiah($sisaPembayaran) ?>.
                </p>
            </div>
        <?php endif; ?>
        
        <!-- 🔥 Submit Button -->
        <button type="submit" name="submit_payment" value="1" class="btn btn-primary" 
                style="width:100%;padding:12px;font-size:16px;" 
                id="submitBtn"
                onclick="return handleSubmit(this)">
            <?= $isDp ? '💰 Kirim Bukti DP' : '✅ Kirim Bukti Pelunasan' ?>
        </button>
        
        <p style="text-align:center;margin-top:12px;font-size:13px;color:#6c757d;">
            Pembayaran akan diverifikasi oleh admin dalam waktu maksimal 1x24 jam
        </p>
    </form>
    
    <p style="margin-top:15px;text-align:center;">
        <a href="/customer/order-detail.php?order=<?= urlencode($orderCode) ?>" style="color:#6c757d;">← Kembali ke Detail Pesanan</a>
    </p>
</div>

<script>
function selectBank(element, bank, accountNumber, accountName) {
    document.querySelectorAll('.bank-card').forEach(card => {
        card.classList.remove('selected');
    });
    element.classList.add('selected');
    document.getElementById('bank_name').value = bank;
    document.getElementById('account_number').value = accountNumber;
    document.getElementById('account_name').value = accountName;
}

function setAmount(value) {
    document.getElementById('amount').value = value;
}

// 🔥 LOADING INDICATOR
function handleSubmit(button) {
    var form = document.getElementById('paymentForm');
    if (!form.checkValidity()) {
        form.reportValidity();
        return false;
    }
    
    // 🔥 Cek apakah file sudah dipilih
    var fileInput = document.querySelector('input[type="file"]');
    if (fileInput && !fileInput.files.length) {
        alert('❌ Silakan upload bukti transfer terlebih dahulu!');
        return false;
    }
    
    button.innerHTML = '⏳ Mengirim...';
    button.disabled = true;
    button.classList.add('btn-loading');
    return true;
}

// 🔥 PREVIEW GAMBAR
function previewImage(input) {
    var preview = document.getElementById('imagePreview');
    var previewImg = document.getElementById('previewImg');
    var fileInfo = document.getElementById('fileInfo');
    var maxSize = parseInt(input.getAttribute('data-max-size')) || 5242880;
    
    if (input.files && input.files[0]) {
        var file = input.files[0];
        var fileSize = file.size;
        var fileName = file.name;
        
        // 🔥 Validasi ukuran
        if (fileSize > maxSize) {
            var sizeMB = (fileSize / 1024 / 1024).toFixed(2);
            var maxMB = (maxSize / 1024 / 1024).toFixed(0);
            fileInfo.innerHTML = '❌ File <strong>' + fileName + '</strong> (' + sizeMB + 'MB) melebihi batas maksimal ' + maxMB + 'MB';
            fileInfo.style.color = '#e74c3c';
            input.value = '';
            preview.classList.remove('show');
            return;
        }
        
        // 🔥 Tampilkan preview
        var reader = new FileReader();
        reader.onload = function(e) {
            previewImg.src = e.target.result;
            preview.classList.add('show');
            
            var sizeKB = (fileSize / 1024).toFixed(0);
            var sizeDisplay = fileSize > 1024 * 1024 ? (fileSize / 1024 / 1024).toFixed(2) + ' MB' : sizeKB + ' KB';
            fileInfo.innerHTML = '✅ File <strong>' + fileName + '</strong> (' + sizeDisplay + ') — siap diupload';
            fileInfo.style.color = '#27ae60';
        };
        reader.readAsDataURL(file);
    } else {
        preview.classList.remove('show');
        fileInfo.innerHTML = 'Maksimal ukuran file 5MB';
        fileInfo.style.color = '#999';
    }
}

// 🔥 AUTO SELECT BANK PERTAMA
document.addEventListener('DOMContentLoaded', function() {
    var firstBank = document.querySelector('.bank-card');
    if (firstBank) {
        firstBank.click();
    }
    
    // 🔥 Auto focus ke input jumlah
    setTimeout(function() {
        document.getElementById('amount').focus();
    }, 500);
});

// 🔥 NOTIFICATION TOAST
function showNotification(msg, type) {
    type = type || 'success';
    var existing = document.querySelector('.notif-toast');
    if (existing) existing.remove();
    
    var div = document.createElement('div');
    div.className = 'notif-toast';
    var bgColor = type === 'success' ? '#27ae60' : type === 'error' ? '#e74c3c' : '#f39c12';
    div.style.cssText = 'position:fixed;top:15px;left:50%;transform:translateX(-50%);background:' + bgColor + ';color:#fff;padding:12px 24px;border-radius:8px;z-index:99999;font-size:14px;box-shadow:0 4px 12px rgba(0,0,0,.15);text-align:center;max-width:90%;';
    div.textContent = msg;
    document.body.appendChild(div);
    setTimeout(function() {
        div.style.opacity = '0';
        div.style.transition = '.3s';
    }, 3000);
    setTimeout(function() { div.remove(); }, 3500);
}
</script>

<?php include '../includes/footer.php'; ?>