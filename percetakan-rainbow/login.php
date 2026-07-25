<?php
require_once __DIR__ . '/config.php';

$pageTitle = 'Masuk - Rainbow Printing';
$error = '';
$email = '';

// 🔥 CEK APAKAH SUDAH LOGIN
if (isset($_SESSION['customer_id'])) {
    header('Location: /customer/dashboard.php');
    exit;
}

// 🔥 🔥 RATE LIMITING 🔥 🔥
$maxAttempts = 5;
$lockoutTime = 900; // 15 menit
$ipAddress = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

// 🔥 Cek percobaan login gagal
$stmt = $db->prepare("SELECT COUNT(*) as c FROM login_attempts WHERE ip_address = ? AND attempt_time > datetime('now', ?)");
$stmt->execute([$ipAddress, '-' . $lockoutTime . ' seconds']);
$attemptCount = $stmt->fetch()['c'];

$isLocked = $attemptCount >= $maxAttempts;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$isLocked) {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']) ? true : false;

    if ($email && $password) {
        $stmt = $db->prepare("SELECT * FROM customers WHERE email = ?");
        $stmt->execute([$email]);
        $customer = $stmt->fetch();
        
        if ($customer && password_verify($password, $customer['password'])) {
            // ✅ LOGIN BERHASIL
            $_SESSION['customer_id'] = $customer['id'];
            $_SESSION['customer_name'] = $customer['name'];
            $_SESSION['customer_email'] = $customer['email'];
            
            // 🔥 Hapus percobaan gagal
            $stmt = $db->prepare("DELETE FROM login_attempts WHERE ip_address = ?");
            $stmt->execute([$ipAddress]);
            
            // 🔥 REMEMBER ME
            if ($remember) {
                $token = bin2hex(random_bytes(32));
                $expires = time() + (86400 * 30);
                setcookie('customer_remember', $token, $expires, '/', '', false, true);
                
                // Simpan token ke database
                $stmt = $db->prepare("UPDATE customers SET remember_token = ?, remember_expires = datetime(?, 'unixepoch') WHERE id = ?");
                $stmt->execute([$token, $expires, $customer['id']]);
            }
            
            // 🔥 Redirect ke halaman sebelumnya jika ada
            $redirect = $_SESSION['redirect_after_login'] ?? '/customer/dashboard.php';
            unset($_SESSION['redirect_after_login']);
            header('Location: ' . $redirect);
            exit;
        } else {
            // ❌ LOGIN GAGAL
            $error = '❌ Email atau password salah.';
            
            // 🔥 Simpan percobaan gagal
            $stmt = $db->prepare("INSERT INTO login_attempts (ip_address, username, attempt_time) VALUES (?, ?, CURRENT_TIMESTAMP)");
            $stmt->execute([$ipAddress, $email]);
            
            // 🔥 Cek ulang apakah sudah melewati batas
            $stmt = $db->prepare("SELECT COUNT(*) as c FROM login_attempts WHERE ip_address = ? AND attempt_time > datetime('now', ?)");
            $stmt->execute([$ipAddress, '-' . $lockoutTime . ' seconds']);
            $newAttemptCount = $stmt->fetch()['c'];
            
            if ($newAttemptCount >= $maxAttempts) {
                $error = '❌ Terlalu banyak percobaan login. Coba lagi dalam 15 menit.';
                $isLocked = true;
            }
        }
    } else {
        $error = '❌ Isi email dan password.';
    }
}

include 'includes/header.php';
?>

<style>
/* ============================================
   LOGIN STYLES
   ============================================ */
.login-container {
    max-width: 420px;
    margin: 20px auto;
}

.login-box {
    background: #fff;
    padding: 35px 30px;
    border-radius: 12px;
    box-shadow: 0 2px 20px rgba(0,0,0,0.08);
    border: 1px solid #e9ecef;
}

.login-box .logo-icon {
    text-align: center;
    margin-bottom: 20px;
}
.login-box .logo-icon .icon {
    font-size: 48px;
    display: block;
}
.login-box h1 {
    font-size: 22px;
    color: #2c3e50;
    text-align: center;
    margin-bottom: 5px;
}
.login-box .subtitle {
    text-align: center;
    color: #6c757d;
    font-size: 14px;
    margin-bottom: 25px;
}

/* 🔥 Form */
.form-group {
    margin-bottom: 18px;
}
.form-group label {
    display: block;
    font-weight: 600;
    font-size: 14px;
    color: #2c3e50;
    margin-bottom: 5px;
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
.form-group .input-group input {
    width: 100%;
    padding: 10px 12px 10px 40px;
    border: 2px solid #e9ecef;
    border-radius: 8px;
    font-size: 14px;
    transition: border-color 0.3s;
    background: #f8f9fa;
}
.form-group .input-group input:focus {
    border-color: #f39c12;
    background: #fff;
    outline: none;
    box-shadow: 0 0 0 3px rgba(243,156,18,0.15);
}
.form-group .input-group input.error {
    border-color: #e74c3c;
    background: #fff5f5;
}
.form-group .input-group input.error:focus {
    box-shadow: 0 0 0 3px rgba(231,76,60,0.15);
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

/* 🔥 Options */
.form-options {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    font-size: 13px;
}
.form-options label {
    display: flex;
    align-items: center;
    gap: 8px;
    cursor: pointer;
    color: #555;
    font-weight: normal;
}
.form-options label input[type="checkbox"] {
    width: 16px;
    height: 16px;
    accent-color: #f39c12;
    cursor: pointer;
}
.form-options a {
    color: #f39c12;
    text-decoration: none;
}
.form-options a:hover {
    text-decoration: underline;
}

/* 🔥 Button */
.btn-login {
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
.btn-login:hover {
    background: #1a252f;
    transform: translateY(-1px);
    box-shadow: 0 4px 15px rgba(44,62,80,0.3);
}
.btn-login:disabled {
    opacity: 0.7;
    cursor: not-allowed;
    transform: none;
}
.btn-login .spinner {
    display: none;
    width: 20px;
    height: 20px;
    border: 3px solid rgba(255,255,255,0.3);
    border-top-color: #fff;
    border-radius: 50%;
    animation: spin 0.8s linear infinite;
}
.btn-login.loading .spinner {
    display: inline-block;
}
.btn-login.loading .btn-text {
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
.alert-info {
    background: #d1ecf1;
    color: #0c5460;
    border: 1px solid #bee5eb;
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

/* 🔥 Footer Links */
.login-footer {
    text-align: center;
    margin-top: 20px;
    font-size: 14px;
    color: #6c757d;
}
.login-footer a {
    color: #f39c12;
    text-decoration: none;
}
.login-footer a:hover {
    text-decoration: underline;
}
.login-footer .divider {
    display: inline-block;
    margin: 0 10px;
    color: #dee2e6;
}
.login-footer .admin-link {
    font-size: 12px;
    color: #999;
}

/* 🔥 Responsive */
@media (max-width: 480px) {
    .login-box {
        padding: 25px 20px;
        margin: 10px;
    }
    .login-box h1 {
        font-size: 20px;
    }
    .form-options {
        flex-direction: column;
        gap: 8px;
        align-items: flex-start;
    }
}
</style>

<div class="login-container">
    <div class="login-box">
        <!-- 🔥 LOGO -->
        <div class="logo-icon">
            <span class="icon">🌈</span>
            <h1>Masuk</h1>
            <p class="subtitle">Silakan masuk ke akun Anda</p>
        </div>

        <!-- 🔥 ALERT -->
        <?php if ($error): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <?= htmlspecialchars($error) ?>
                <button class="close-btn" onclick="this.parentElement.remove()">&times;</button>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['register_success'])): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?= $_SESSION['register_success']; unset($_SESSION['register_success']); ?>
                <button class="close-btn" onclick="this.parentElement.remove()">&times;</button>
            </div>
        <?php endif; ?>

        <?php if ($isLocked): ?>
            <div class="alert alert-info">
                <i class="fas fa-clock"></i>
                Terlalu banyak percobaan login. Silakan coba lagi dalam 15 menit.
                <button class="close-btn" onclick="this.parentElement.remove()">&times;</button>
            </div>
        <?php endif; ?>

        <!-- 🔥 FORM -->
        <form method="POST" id="loginForm" autocomplete="off">
            <div class="form-group">
                <label for="email">Email</label>
                <div class="input-group">
                    <span class="input-icon"><i class="fas fa-envelope"></i></span>
                    <input type="email" name="email" id="email" required 
                           value="<?= htmlspecialchars($email) ?>"
                           placeholder="Masukkan email Anda"
                           class="<?= $error ? 'error' : '' ?>"
                           <?= $isLocked ? 'disabled' : '' ?>>
                </div>
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <div class="input-group">
                    <span class="input-icon"><i class="fas fa-lock"></i></span>
                    <input type="password" name="password" id="password" required 
                           placeholder="Masukkan password"
                           class="<?= $error ? 'error' : '' ?>"
                           <?= $isLocked ? 'disabled' : '' ?>>
                    <button type="button" class="toggle-password" onclick="togglePassword()">
                        <i class="fas fa-eye" id="toggleIcon"></i>
                    </button>
                </div>
            </div>

            <!-- 🔥 REMEMBER ME -->
            <div class="form-options">
                <label>
                    <input type="checkbox" name="remember" id="remember" <?= isset($_POST['remember']) ? 'checked' : '' ?>>
                    <span>Ingat saya</span>
                </label>
                <a href="/lupa-password.php">Lupa password?</a>
            </div>

            <!-- 🔥 SUBMIT -->
            <button type="submit" class="btn-login" id="loginBtn" <?= $isLocked ? 'disabled' : '' ?>>
                <span class="btn-text"><i class="fas fa-sign-in-alt"></i> Masuk</span>
                <span class="spinner"></span>
            </button>
        </form>

        <!-- 🔥 FOOTER -->
        <div class="login-footer">
            <p>
                Belum punya akun? <a href="register.php">Daftar Sekarang</a>
            </p>
            <p style="margin-top:8px;">
                <span class="admin-link">
                    Login untuk admin? <a href="/admin/">Masuk di sini</a>
                </span>
            </p>
        </div>
    </div>
</div>

<script>
/**
 * 🔥 Toggle Password Visibility
 */
function togglePassword() {
    var password = document.getElementById('password');
    var icon = document.getElementById('toggleIcon');
    if (password.type === 'password') {
        password.type = 'text';
        icon.className = 'fas fa-eye-slash';
    } else {
        password.type = 'password';
        icon.className = 'fas fa-eye';
    }
}

/**
 * 🔥 Loading State on Submit
 */
document.getElementById('loginForm').addEventListener('submit', function(e) {
    var btn = document.getElementById('loginBtn');
    btn.classList.add('loading');
    btn.disabled = true;
});

/**
 * 🔥 Auto-focus jika ada error
 */
document.addEventListener('DOMContentLoaded', function() {
    <?php if ($error): ?>
        var email = document.getElementById('email');
        if (email.value) {
            document.getElementById('password').focus();
        } else {
            email.focus();
        }
    <?php endif; ?>
});

/**
 * 🔥 Enter key untuk submit
 */
document.addEventListener('keydown', function(e) {
    if (e.key === 'Enter') {
        var active = document.activeElement;
        if (active && (active.id === 'email' || active.id === 'password')) {
            document.getElementById('loginBtn').click();
        }
    }
});

/**
 * 🔥 Auto hide alert
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