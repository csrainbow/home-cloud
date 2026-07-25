<?php
require_once __DIR__ . '/../config.php';

// 🔥 CEK LOGIN
if (!isset($_SESSION['customer_id'])) {
    header('Location: /login.php');
    exit;
}

$error = '';
$success = '';

// 🔥 AMBIL DATA CUSTOMER
$stmt = $db->prepare("SELECT * FROM customers WHERE id = ?");
$stmt->execute([$_SESSION['customer_id']]);
$customer = $stmt->fetch();

if (!$customer) {
    session_destroy();
    header('Location: /login.php');
    exit;
}

// 🔥 HITUNG TOTAL PESANAN
$orderStmt = $db->prepare("SELECT COUNT(*) as total FROM orders WHERE customer_id = ?");
$orderStmt->execute([$_SESSION['customer_id']]);
$totalOrders = $orderStmt->fetch()['total'];

// 🔥 🔥 PROSES UPDATE 🔥 🔥
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    // 🔥 Validasi
    if (!$name || !$phone) {
        $error = '❌ Nama dan nomor WA wajib diisi.';
    } elseif (!preg_match('/^0\d{8,12}$/', $phone) && !preg_match('/^628\d{8,12}$/', $phone)) {
        $error = '❌ Format nomor WhatsApp tidak valid. Gunakan format 08123456789 atau 628123456789.';
    } elseif ($password && strlen($password) < 6) {
        $error = '❌ Password minimal 6 karakter.';
    } elseif ($password && $password !== $confirmPassword) {
        $error = '❌ Password dan konfirmasi password tidak cocok!';
    } else {
        try {
            if ($password) {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $db->prepare("UPDATE customers SET name=?, phone=?, address=?, password=? WHERE id=?");
                $stmt->execute([$name, $phone, $address, $hash, $_SESSION['customer_id']]);
            } else {
                $stmt = $db->prepare("UPDATE customers SET name=?, phone=?, address=? WHERE id=?");
                $stmt->execute([$name, $phone, $address, $_SESSION['customer_id']]);
            }
            
            // 🔥 UPDATE SESSION
            $_SESSION['customer_name'] = $name;
            $customer['name'] = $name;
            $customer['phone'] = $phone;
            $customer['address'] = $address;
            
            $success = '✅ Profil berhasil diperbarui!';
        } catch (Exception $e) {
            $error = '❌ Gagal menyimpan: ' . $e->getMessage();
        }
    }
}

$pageTitle = 'Profil Saya - Rainbow Printing';
include '../includes/header.php';
?>

<style>
.profile-container {
    max-width: 600px;
    margin: 0 auto;
}
.profile-header {
    display: flex;
    align-items: center;
    gap: 20px;
    margin-bottom: 25px;
    flex-wrap: wrap;
}
.profile-avatar {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    background: linear-gradient(135deg, #2c3e50, #f39c12);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 36px;
    color: #fff;
    flex-shrink: 0;
}
.profile-header-info h1 {
    margin: 0;
    font-size: 24px;
    color: #2c3e50;
}
.profile-header-info .subtitle {
    color: #6c757d;
    font-size: 14px;
    margin: 4px 0 0;
}
.profile-stats {
    display: flex;
    gap: 20px;
    flex-wrap: wrap;
    margin-bottom: 25px;
}
.profile-stats .stat {
    background: #fff;
    padding: 12px 20px;
    border-radius: 8px;
    box-shadow: 0 1px 4px rgba(0,0,0,0.06);
    flex: 1;
    min-width: 120px;
    text-align: center;
}
.profile-stats .stat .number {
    font-size: 20px;
    font-weight: bold;
    color: #2c3e50;
}
.profile-stats .stat .label {
    font-size: 12px;
    color: #6c757d;
}

.profile-form {
    background: #fff;
    padding: 30px;
    border-radius: 12px;
    box-shadow: 0 1px 4px rgba(0,0,0,0.06);
}
.profile-form .form-group {
    margin-bottom: 18px;
}
.profile-form .form-group label {
    display: block;
    font-weight: 600;
    font-size: 14px;
    color: #2c3e50;
    margin-bottom: 5px;
}
.profile-form .form-group .helper-text {
    font-size: 12px;
    color: #6c757d;
    margin-top: 4px;
}
.profile-form .form-group input,
.profile-form .form-group textarea {
    width: 100%;
    padding: 10px 12px;
    border: 1px solid #ddd;
    border-radius: 6px;
    font-size: 14px;
    transition: border-color 0.3s;
}
.profile-form .form-group input:focus,
.profile-form .form-group textarea:focus {
    border-color: #f39c12;
    outline: none;
    box-shadow: 0 0 0 3px rgba(243,156,18,0.15);
}
.profile-form .form-group input:disabled {
    background: #f8f9fa;
    color: #6c757d;
}
.profile-form .form-group .input-icon {
    position: relative;
}
.profile-form .form-group .input-icon i {
    position: absolute;
    left: 12px;
    top: 50%;
    transform: translateY(-50%);
    color: #999;
}
.profile-form .form-group .input-icon input {
    padding-left: 38px;
}
.profile-form .form-actions {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    margin-top: 10px;
}

.btn {
    display: inline-block;
    padding: 10px 24px;
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
    transform: translateY(-1px);
}
.btn-outline {
    background: #fff;
    color: #2c3e50;
    border: 1px solid #2c3e50;
}
.btn-outline:hover {
    background: #f8f9fa;
}
.btn-danger {
    background: #e74c3c;
    color: #fff;
}
.btn-danger:hover {
    background: #c0392b;
}

.alert {
    padding: 12px 16px;
    border-radius: 6px;
    margin-bottom: 15px;
    font-size: 14px;
}
.alert-success {
    background: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}
.alert-error {
    background: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}

/* 🔥 RESPONSIVE */
@media (max-width: 480px) {
    .profile-header {
        flex-direction: column;
        text-align: center;
    }
    .profile-form {
        padding: 20px;
    }
    .profile-stats {
        flex-direction: column;
    }
    .profile-stats .stat {
        min-width: auto;
    }
}
</style>

<div class="profile-container">
    <!-- 🔥 HEADER -->
    <div class="profile-header">
        <div class="profile-avatar">
            <?= strtoupper(substr($customer['name'] ?? 'P', 0, 1)) ?>
        </div>
        <div class="profile-header-info">
            <h1><?= htmlspecialchars($customer['name'] ?? 'Pengguna') ?></h1>
            <p class="subtitle">
                <i class="fas fa-envelope"></i> <?= htmlspecialchars($customer['email']) ?>
                <br>
                <i class="fas fa-calendar-alt"></i> Bergabung: <?= date('d F Y', strtotime($customer['created_at'] ?? 'now')) ?>
            </p>
        </div>
    </div>

    <!-- 🔥 STATISTIK -->
    <div class="profile-stats">
        <div class="stat">
            <div class="number"><?= $totalOrders ?></div>
            <div class="label">📦 Total Pesanan</div>
        </div>
        <div class="stat">
            <div class="number"><?= htmlspecialchars($customer['phone'] ?? '-') ?></div>
            <div class="label">📱 WhatsApp</div>
        </div>
        <div class="stat">
            <div class="number"><?= $customer['address'] ? '✅' : '❌' ?></div>
            <div class="label">📍 Alamat</div>
        </div>
    </div>

    <!-- 🔥 ALERT -->
    <?php if ($error): ?>
        <div class="alert alert-error"><?= $error ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="alert alert-success"><?= $success ?></div>
    <?php endif; ?>

    <!-- 🔥 FORM -->
    <form method="POST" class="profile-form" id="profileForm">
        <div class="form-group">
            <label for="name">Nama Lengkap *</label>
            <div class="input-icon">
                <i class="fas fa-user"></i>
                <input type="text" name="name" id="name" required 
                       value="<?= htmlspecialchars($customer['name'] ?? '') ?>" 
                       placeholder="Masukkan nama lengkap">
            </div>
        </div>

        <div class="form-group">
            <label for="email">Email</label>
            <div class="input-icon">
                <i class="fas fa-envelope"></i>
                <input type="email" id="email" 
                       value="<?= htmlspecialchars($customer['email']) ?>" 
                       disabled>
            </div>
            <div class="helper-text">Email tidak bisa diubah</div>
        </div>

        <div class="form-group">
            <label for="phone">Nomor WhatsApp *</label>
            <div class="input-icon">
                <i class="fab fa-whatsapp"></i>
                <input type="tel" name="phone" id="phone" required 
                       value="<?= htmlspecialchars($customer['phone'] ?? '') ?>" 
                       placeholder="08123456789 atau 628123456789">
            </div>
            <div class="helper-text">Format: 08123456789 atau 628123456789</div>
        </div>

        <div class="form-group">
            <label for="address">Alamat</label>
            <textarea name="address" id="address" rows="3" 
                      placeholder="Masukkan alamat lengkap"><?= htmlspecialchars($customer['address'] ?? '') ?></textarea>
        </div>

        <div style="border-top:2px solid #f39c12;padding-top:15px;margin-top:5px;">
            <h3 style="font-size:15px;color:#2c3e50;margin-bottom:10px;">
                🔒 Ganti Password
            </h3>
            <div class="form-group">
                <label for="password">Password Baru</label>
                <div class="input-icon">
                    <i class="fas fa-lock"></i>
                    <input type="password" name="password" id="password" 
                           minlength="6" placeholder="Minimal 6 karakter">
                </div>
                <div class="helper-text">Kosongkan jika tidak ingin mengganti password</div>
            </div>
            <div class="form-group">
                <label for="confirm_password">Konfirmasi Password Baru</label>
                <div class="input-icon">
                    <i class="fas fa-check-circle"></i>
                    <input type="password" name="confirm_password" id="confirm_password" 
                           placeholder="Ulangi password baru">
                </div>
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary" onclick="return confirmSave()">
                <i class="fas fa-save"></i> Simpan Profil
            </button>
            <a href="dashboard.php" class="btn btn-outline">
                <i class="fas fa-arrow-left"></i> Kembali
            </a>
            <a href="/logout.php" class="btn btn-danger" style="margin-left:auto;">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </div>
    </form>
</div>

<script>
/**
 * 🔥 CONFIRM SAVE
 */
function confirmSave() {
    var form = document.getElementById('profileForm');
    var password = document.getElementById('password').value;
    var confirmPassword = document.getElementById('confirm_password').value;
    
    // Validasi password
    if (password && password.length < 6) {
        alert('❌ Password minimal 6 karakter!');
        document.getElementById('password').focus();
        return false;
    }
    
    if (password && password !== confirmPassword) {
        alert('❌ Password dan konfirmasi password tidak cocok!');
        document.getElementById('confirm_password').focus();
        return false;
    }
    
    return confirm('⚠️ Yakin ingin menyimpan perubahan profil?');
}

/**
 * 🔥 VALIDASI NOMOR WHATSAPP
 */
document.getElementById('phone')?.addEventListener('blur', function() {
    var phone = this.value.trim();
    if (phone && !phone.match(/^0\d{8,12}$/) && !phone.match(/^628\d{8,12}$/)) {
        this.style.borderColor = '#e74c3c';
        this.style.boxShadow = '0 0 0 3px rgba(231,76,60,0.15)';
        // Tampilkan peringatan
        var helper = this.parentElement.parentElement.querySelector('.helper-text');
        if (helper) {
            helper.style.color = '#e74c3c';
            helper.textContent = '⚠️ Format tidak valid! Gunakan 08123456789 atau 628123456789';
        }
    } else {
        this.style.borderColor = '';
        this.style.boxShadow = '';
        var helper = this.parentElement.parentElement.querySelector('.helper-text');
        if (helper) {
            helper.style.color = '#6c757d';
            helper.textContent = 'Format: 08123456789 atau 628123456789';
        }
    }
});

/**
 * 🔥 AUTO HIDE ALERT
 */
document.addEventListener('DOMContentLoaded', function() {
    var alerts = document.querySelectorAll('.alert');
    alerts.forEach(function(alert) {
        setTimeout(function() {
            alert.style.transition = 'opacity 0.5s';
            alert.style.opacity = '0';
            setTimeout(function() { alert.remove(); }, 500);
        }, 5000);
    });
});
</script>

<?php include '../includes/footer.php'; ?>