<?php
require_once __DIR__ . '/config.php';

$pageTitle = 'Daftar Akun - Rainbow Printing';
$error = '';
$success = '';

// 🔥 CEK APAKAH SUDAH LOGIN
if (isset($_SESSION['customer_id'])) {
    header('Location: customer/dashboard.php');
    exit;
}

// 🔥 🔥 RATE LIMITING 🔥 🔥
$ipAddress = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$stmt = $db->prepare("SELECT COUNT(*) as c FROM register_attempts WHERE ip_address = ? AND attempt_time > datetime('now', '-1 hour')");
$stmt->execute([$ipAddress]);
$attemptCount = $stmt->fetch()['c'];

if ($attemptCount >= 5) {
    $error = '❌ Terlalu banyak percobaan pendaftaran. Coba lagi dalam 1 jam.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($error)) {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';
    $address = trim($_POST['address'] ?? '');

    // 🔥 Validasi
    if (!$name || !$email || !$phone || !$password) {
        $error = '❌ Semua field wajib diisi.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = '❌ Email tidak valid.';
    } elseif (!preg_match('/^(0|62)\d{8,13}$/', preg_replace('/[^0-9]/', '', $phone))) {
        $error = '❌ Nomor WhatsApp tidak valid. Gunakan format 08123456789 atau 628123456789.';
    } elseif ($password !== $confirm) {
        $error = '❌ Konfirmasi password tidak cocok.';
    } elseif (strlen($password) < 6) {
        $error = '❌ Password minimal 6 karakter.';
    } elseif (!preg_match('/[A-Z]/', $password) || !preg_match('/[a-z]/', $password) || !preg_match('/[0-9]/', $password)) {
        $error = '❌ Password harus mengandung huruf besar, huruf kecil, dan angka.';
    } else {
        // 🔥 CEK EMAIL DUPLIKAT
        $stmt = $db->prepare("SELECT id FROM customers WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $error = '❌ Email sudah terdaftar. Silakan <a href="login.php">login</a>.';
        } else {
            // 🔥 CEK NOMOR DUPLIKAT
            $stmt = $db->prepare("SELECT id FROM customers WHERE phone = ?");
            $stmt->execute([$phone]);
            if ($stmt->fetch()) {
                $error = '❌ Nomor WhatsApp sudah terdaftar. Silakan <a href="login.php">login</a>.';
            } else {
                // ✅ REGISTRASI BERHASIL
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $db->prepare("INSERT INTO customers (name, email, phone, password, address) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$name, $email, $phone, $hash, $address]);
                $customerId = $db->lastInsertId();

                // 🔥 HAPUS ATTEMPT
                $stmt = $db->prepare("DELETE FROM register_attempts WHERE ip_address = ?");
                $stmt->execute([$ipAddress]);

                // 🔥 LOGIN OTOMATIS
                $_SESSION['customer_id'] = $customerId;
                $_SESSION['customer_name'] = $name;
                $_SESSION['customer_email'] = $email;

                // 🔥 REDIRECT DENGAN PESAN SUKSES
                $_SESSION['register_success'] = '✅ Pendaftaran berhasil! Selamat datang, ' . $name . '!';
                header('Location: products.php');
                exit;
            }
        }
    }

    // 🔥 SIMPAN ATTEMPT GAGAL
    if ($error) {
        $stmt = $db->prepare("INSERT INTO register_attempts (ip_address, attempt_time) VALUES (?, CURRENT_TIMESTAMP)");
        $stmt->execute([$ipAddress]);
    }
}

// 🔥 AMBIL DATA YANG SUDAH DIISI (jika ada error)
$oldName = $_POST['name'] ?? '';
$oldEmail = $_POST['email'] ?? '';
$oldPhone = $_POST['phone'] ?? '';
$oldAddress = $_POST['address'] ?? '';

include 'includes/header.php';
?>

<style>
/* ============================================
   REGISTER STYLES
   ============================================ */
.register-container {
    max-width: 460px;
    margin: 20px auto;
}
.register-box {
    background: #fff;
    padding: 35px 30px;
    border-radius: 12px;
    box-shadow: 0 2px 20px rgba(0,0,0,0.08);
    border: 1px solid #e9ecef;
}
.register-box .logo-icon {
    text-align: center;
    margin-bottom: 20px;
}
.register-box .logo-icon .icon {
    font-size: 48px;
    display: block;
}
.register-box h1 {
    font-size: 22px;
    color: #2c3e50;
    text-align: center;
    margin-bottom: 5px;
}
.register-box .subtitle {
    text-align: center;
    color: #6c757d;
    font-size: 14px;
    margin-bottom: 25px;
}

/* 🔥 Form */
.form-group {
    margin-bottom: 16px;
}
.form-group label {
    display: block;
    font-weight: 600;
    font-size: 14px;
    color: #2c3e50;
    margin-bottom: 4px;
}
.form-group .input-group {
    position: relative;
}
.form-group .input-group .input-icon {
    position: absolute;
    left: 12px;
    top: 50%;
    transform: translateY(-50%);
    color: #999;
    font-size: 16px;
}
.form-group .input-group input,
.form-group .input-group textarea {
    width: 100%;
    padding: 10px 12px 10px 40px;
    border: 2px solid #e9ecef;
    border-radius: 8px;
    font-size: 14px;
    transition: border-color 0.3s;
    background: #f8f9fa;
    font-family: inherit;
}
.form-group .input-group textarea {
    padding-top: 10px;
    resize: vertical;
    min-height: 60px;
}
.form-group .input-group input:focus,
.form-group .input-group textarea:focus {
    border-color: #f39c12;
    background: #fff;
    outline: none;
    box-shadow: 0 0 0 3px rgba(243,156,18,0.15);
}
.form-group .input-group input.error,
.form-group .input-group textarea.error {
    border-color: #e74c3c;
    background: #fff5f5;
}
.form-group .input-group input.success,
.form-group .input-group textarea.success {
    border-color: #27ae60;
    background: #f0fff4;
}
.form-group .input-group input:disabled,
.form-group .input-group textarea:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}
.helper-text {
    font-size: 12px;
    color: #6c757d;
    margin-top: 4px;
}
.helper-text.error {
    color: #e74c3c;
}
.helper-text.success {
    color: #27ae60;
}

/* 🔥 Toggle Password */
.toggle-password {
    position: absolute;
    right: 12px;
    top: 50%;
    transform: translateY(-50%);
    background: none;
    border: none;
    color: #999;
    cursor: pointer;
    font-size: 16px;
    padding: 4px;
}
.toggle-password:hover {
    color: #2c3e50;
}

/* 🔥 Password Strength */
.password-strength {
    margin-top: 6px;
    height: 4px;
    border-radius: 4px;
    background: #e9ecef;
    overflow: hidden;
    transition: all 0.3s;
}
.password-strength .bar {
    height: 100%;
    width: 0%;
    transition: width 0.5s ease;
    border-radius: 4px;
}
.password-strength .bar.weak { background: #e74c3c; width: 25%; }
.password-strength .bar.medium { background: #f39c12; width: 50%; }
.password-strength .bar.strong { background: #27ae60; width: 75%; }
.password-strength .bar.very-strong { background: #2ecc71; width: 100%; }
.password-strength-text {
    font-size: 12px;
    margin-top: 2px;
    color: #6c757d;
}

/* 🔥 Button */
.btn-register {
    width: 100%;
    padding: 12px;
    background: #2c3e50;
    color: #fff;
    border: none;
    border-radius: 8px;
    font-size: 16px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
}
.btn-register:hover {
    background: #1a252f;
    transform: translateY(-1px);
    box-shadow: 0 4px 15px rgba(44,62,80,0.3);
}
.btn-register:disabled {
    opacity: 0.6;
    cursor: not-allowed;
    transform: none;
}
.btn-register .spinner {
    display: none;
    width: 20px;
    height: 20px;
    border: 3px solid rgba(255,255,255,0.3);
    border-top-color: #fff;
    border-radius: 50%;
    animation: spin 0.8s linear infinite;
}
.btn-register.loading .spinner {
    display: inline-block;
}
.btn-register.loading .btn-text {
    display: none;
}
@keyframes spin {
    to { transform: rotate(360deg); }
}

/* 🔥 Alert */
.alert {
    padding: 12px 16px;
    border-radius: 8px;
    margin-bottom: 20px;
    font-size: 14px;
    display: flex;
    align-items: center;
    gap: 10px;
}
.alert-error {
    background: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}
.alert-success {
    background: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}
.alert .close-btn {
    margin-left: auto;
    background: none;
    border: none;
    font-size: 18px;
    cursor: pointer;
    opacity: 0.6;
    color: inherit;
}
.alert .close-btn:hover {
    opacity: 1;
}

/* 🔥 Footer */
.register-footer {
    text-align: center;
    margin-top: 20px;
    font-size: 14px;
    color: #6c757d;
}
.register-footer a {
    color: #f39c12;
    text-decoration: none;
}
.register-footer a:hover {
    text-decoration: underline;
}

/* 🔥 Responsive */
@media (max-width: 480px) {
    .register-box {
        padding: 25px 20px;
        margin: 10px;
    }
    .register-box h1 {
        font-size: 20px;
    }
    .form-group .input-group input,
    .form-group .input-group textarea {
        padding-left: 36px;
    }
}
</style>

<div class="register-container">
    <div class="register-box">
        <div class="logo-icon">
            <span class="icon">🌈</span>
            <h1>Daftar Akun</h1>
            <p class="subtitle">Mulai belanja dengan akun Rainbow Printing</p>
        </div>

        <!-- 🔥 ALERT -->
        <?php if ($error): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <?= $error ?>
                <button class="close-btn" onclick="this.parentElement.remove()">&times;</button>
            </div>
        <?php endif; ?>

        <!-- 🔥 FORM -->
        <form method="POST" id="registerForm" autocomplete="off">
            <div class="form-group">
                <label for="name">Nama Lengkap *</label>
                <div class="input-group">
                    <span class="input-icon"><i class="fas fa-user"></i></span>
                    <input type="text" name="name" id="name" required 
                           value="<?= htmlspecialchars($oldName) ?>"
                           placeholder="Masukkan nama lengkap"
                           autofocus>
                </div>
            </div>

            <div class="form-group">
                <label for="email">Email *</label>
                <div class="input-group">
                    <span class="input-icon"><i class="fas fa-envelope"></i></span>
                    <input type="email" name="email" id="email" required 
                           value="<?= htmlspecialchars($oldEmail) ?>"
                           placeholder="Masukkan email aktif">
                </div>
                <div class="helper-text" id="email-helper"></div>
            </div>

            <div class="form-group">
                <label for="phone">Nomor WhatsApp *</label>
                <div class="input-group">
                    <span class="input-icon"><i class="fab fa-whatsapp"></i></span>
                    <input type="tel" name="phone" id="phone" required 
                           value="<?= htmlspecialchars($oldPhone) ?>"
                           placeholder="08123456789 atau 628123456789">
                </div>
                <div class="helper-text" id="phone-helper">Format: 08123456789 atau 628123456789</div>
            </div>

            <div class="form-group">
                <label for="address">Alamat</label>
                <div class="input-group">
                    <span class="input-icon" style="top:14px;transform:none;"><i class="fas fa-home"></i></span>
                    <textarea name="address" id="address" rows="2" 
                              placeholder="Contoh: Jl. Merdeka No. 123, Kota"><?= htmlspecialchars($oldAddress) ?></textarea>
                </div>
            </div>

            <div class="form-group">
                <label for="password">Password *</label>
                <div class="input-group">
                    <span class="input-icon"><i class="fas fa-lock"></i></span>
                    <input type="password" name="password" id="password" required minlength="6"
                           placeholder="Minimal 6 karakter">
                    <button type="button" class="toggle-password" onclick="togglePassword('password', 'toggleIcon1')">
                        <i class="fas fa-eye" id="toggleIcon1"></i>
                    </button>
                </div>
                <div class="password-strength" id="password-strength">
                    <div class="bar" id="strength-bar"></div>
                </div>
                <div class="password-strength-text" id="strength-text"></div>
                <div class="helper-text" id="password-helper">Minimal 6 karakter, kombinasi huruf dan angka</div>
            </div>

            <div class="form-group">
                <label for="confirm_password">Konfirmasi Password *</label>
                <div class="input-group">
                    <span class="input-icon"><i class="fas fa-check-circle"></i></span>
                    <input type="password" name="confirm_password" id="confirm_password" required minlength="6"
                           placeholder="Ulangi password">
                    <button type="button" class="toggle-password" onclick="togglePassword('confirm_password', 'toggleIcon2')">
                        <i class="fas fa-eye" id="toggleIcon2"></i>
                    </button>
                </div>
                <div class="helper-text" id="confirm-helper"></div>
            </div>

            <button type="submit" class="btn-register" id="registerBtn">
                <span class="btn-text"><i class="fas fa-user-plus"></i> Daftar</span>
                <span class="spinner"></span>
            </button>
        </form>

        <div class="register-footer">
            <p>Sudah punya akun? <a href="login.php">Masuk</a></p>
        </div>
    </div>
</div>

<script>
/**
 * 🔥 TOGGLE PASSWORD VISIBILITY
 */
function togglePassword(inputId, iconId) {
    var input = document.getElementById(inputId);
    var icon = document.getElementById(iconId);
    if (input.type === 'password') {
        input.type = 'text';
        icon.className = 'fas fa-eye-slash';
    } else {
        input.type = 'password';
        icon.className = 'fas fa-eye';
    }
}

/**
 * 🔥 PASSWORD STRENGTH CHECKER
 */
document.getElementById('password').addEventListener('input', function() {
    var password = this.value;
    var bar = document.getElementById('strength-bar');
    var text = document.getElementById('strength-text');
    var helper = document.getElementById('password-helper');
    
    if (password.length === 0) {
        bar.className = 'bar';
        bar.style.width = '0%';
        text.textContent = '';
        return;
    }
    
    var strength = 0;
    if (password.length >= 6) strength++;
    if (password.length >= 10) strength++;
    if (/[a-z]/.test(password) && /[A-Z]/.test(password)) strength++;
    if (/\d/.test(password)) strength++;
    if (/[^a-zA-Z0-9]/.test(password)) strength++;
    
    var level = 'weak';
    var label = 'Lemah';
    var color = '#e74c3c';
    var width = '20%';
    
    if (strength >= 4) {
        level = 'very-strong';
        label = 'Sangat Kuat';
        color = '#2ecc71';
        width = '100%';
    } else if (strength >= 3) {
        level = 'strong';
        label = 'Kuat';
        color = '#27ae60';
        width = '75%';
    } else if (strength >= 2) {
        level = 'medium';
        label = 'Sedang';
        color = '#f39c12';
        width = '50%';
    } else {
        level = 'weak';
        label = 'Lemah';
        color = '#e74c3c';
        width = '25%';
    }
    
    bar.className = 'bar ' + level;
    bar.style.width = width;
    text.textContent = 'Kekuatan: ' + label;
    text.style.color = color;
});

/**
 * 🔥 CONFIRM PASSWORD VALIDATION
 */
document.getElementById('confirm_password').addEventListener('input', function() {
    var password = document.getElementById('password').value;
    var confirm = this.value;
    var helper = document.getElementById('confirm-helper');
    var input = this;
    
    if (confirm.length === 0) {
        helper.textContent = '';
        input.className = '';
        return;
    }
    
    if (password === confirm) {
        helper.textContent = '✅ Password cocok';
        helper.className = 'helper-text success';
        input.className = 'success';
    } else {
        helper.textContent = '❌ Password tidak cocok';
        helper.className = 'helper-text error';
        input.className = 'error';
    }
});

/**
 * 🔥 EMAIL VALIDATION (real-time)
 */
document.getElementById('email').addEventListener('blur', function() {
    var email = this.value;
    var helper = document.getElementById('email-helper');
    var input = this;
    
    if (!email) {
        helper.textContent = '';
        input.className = '';
        return;
    }
    
    var regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (regex.test(email)) {
        helper.textContent = '✅ Email valid';
        helper.className = 'helper-text success';
        input.className = 'success';
    } else {
        helper.textContent = '❌ Email tidak valid';
        helper.className = 'helper-text error';
        input.className = 'error';
    }
});

/**
 * 🔥 PHONE VALIDATION (real-time)
 */
document.getElementById('phone').addEventListener('blur', function() {
    var phone = this.value;
    var helper = document.getElementById('phone-helper');
    var input = this;
    
    if (!phone) {
        helper.textContent = 'Format: 08123456789 atau 628123456789';
        helper.className = 'helper-text';
        input.className = '';
        return;
    }
    
    var clean = phone.replace(/[^0-9]/g, '');
    var regex = /^(0|62)\d{8,13}$/;
    if (regex.test(clean)) {
        helper.textContent = '✅ Nomor valid';
        helper.className = 'helper-text success';
        input.className = 'success';
    } else {
        helper.textContent = '❌ Nomor tidak valid. Gunakan 08123456789 atau 628123456789';
        helper.className = 'helper-text error';
        input.className = 'error';
    }
});

/**
 * 🔥 FORM SUBMIT - LOADING STATE
 */
document.getElementById('registerForm').addEventListener('submit', function(e) {
    var btn = document.getElementById('registerBtn');
    btn.classList.add('loading');
    btn.disabled = true;
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

<?php include 'includes/footer.php'; ?>