<?php
// 🔥 DEBUG
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../config.php';

// 🔥 CLEAN OUTPUT BUFFER
ob_start();

$error = '';
$pageTitle = 'Admin Login - Rainbow Printing';

// 🔥 CEK APAKAH SUDAH LOGIN
if (isAdmin()) {
    redirect('/admin/dashboard.php');
}

// 🔥 🔥 RATE LIMITING - CEK PERCOBAAN LOGIN 🔥 🔥
$maxAttempts = 5;
$lockoutTime = 900; // 15 menit dalam detik
$ipAddress = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

// 🔥 Cek apakah IP diblokir
$stmt = $db->prepare("SELECT * FROM login_attempts WHERE ip_address = ? AND attempt_time > datetime('now', ?)");
$stmt->execute([$ipAddress, '-' . $lockoutTime . ' seconds']);
$recentAttempts = $stmt->fetchAll();

if (count($recentAttempts) >= $maxAttempts) {
    $remaining = $lockoutTime - (time() - strtotime($recentAttempts[0]['attempt_time']));
    $remainingMinutes = ceil($remaining / 60);
    $error = "❌ Terlalu banyak percobaan login. Coba lagi dalam $remainingMinutes menit.";
}

$username = '';

// 🔥 🔥 PROSES LOGIN 🔥 🔥
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($error)) {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']) ? true : false;
    
    // 🔥 VALIDASI
    if (empty($username) || empty($password)) {
        $error = '⚠️ Harap isi username dan password!';
    } else {
        // 🔥 CEK KREDENSIAL
        if ($username === ADMIN_USERNAME && password_verify($password, ADMIN_PASSWORD_HASH)) {
            // ✅ LOGIN BERHASIL
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_username'] = $username;
            $_SESSION['admin_login_time'] = time();
            
            // 🔥 HAPUS ATTEMPT YANG GAGAL
            $stmt = $db->prepare("DELETE FROM login_attempts WHERE ip_address = ?");
            $stmt->execute([$ipAddress]);
            
            // 🔥 REMEMBER ME (Cookie 30 hari)
            if ($remember) {
                $token = bin2hex(random_bytes(32));
                $expires = time() + (86400 * 30);
                setcookie('admin_remember', $token, $expires, '/', '', false, true);
                
                // Simpan token ke database
                $stmt = $db->prepare("UPDATE admin_sessions SET remember_token = ?, expires_at = datetime(?, 'unixepoch') WHERE admin_id = 1");
                $stmt->execute([$token, $expires]);
            }
            
            // 🔥 LOG AKTIVITAS
            error_log("Admin login successful: $username from IP: $ipAddress");
            
            // 🔥 REDIRECT
            ob_end_clean();
            redirect('/admin/dashboard.php');
            exit;
        } else {
            // ❌ LOGIN GAGAL
            $error = '❌ Username atau password salah!';
            
            // 🔥 SIMPAN ATTEMPT GAGAL
            $stmt = $db->prepare("INSERT INTO login_attempts (ip_address, username, attempt_time) VALUES (?, ?, CURRENT_TIMESTAMP)");
            $stmt->execute([$ipAddress, $username]);
            
            // 🔥 CEK APAKAH SUDAH MELEWATI BATAS
            $stmt = $db->prepare("SELECT COUNT(*) as c FROM login_attempts WHERE ip_address = ? AND attempt_time > datetime('now', ?)");
            $stmt->execute([$ipAddress, '-' . $lockoutTime . ' seconds']);
            $attemptCount = $stmt->fetch()['c'];
            
            if ($attemptCount >= $maxAttempts) {
                $error = "❌ Terlalu banyak percobaan login. Coba lagi dalam $lockoutTime detik.";
            }
        }
    }
}

// 🔥 🔥 AUTO-LOGOUT JIKA SESSION KEDALUWARSA 🔥 🔥
if (isset($_SESSION['admin_login_time']) && (time() - $_SESSION['admin_login_time']) > 3600) {
    session_destroy();
    setcookie('admin_remember', '', time() - 3600, '/');
    header('Location: /admin/login.php?expired=1');
    exit;
}

include '../includes/header.php';
?>

<style>
/* ============================================
   ADMIN LOGIN STYLES
   ============================================ */
.admin-login {
    display: flex;
    justify-content: center;
    align-items: center;
    min-height: 70vh;
    padding: 20px;
    background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
}

.login-container {
    width: 100%;
    max-width: 420px;
}

.login-box {
    background: #fff;
    border-radius: 16px;
    padding: 40px 35px;
    box-shadow: 0 10px 40px rgba(0,0,0,0.1);
    position: relative;
    overflow: hidden;
}

.login-box::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(90deg, #2c3e50, #f39c12);
}

.login-logo {
    text-align: center;
    margin-bottom: 30px;
}

.login-logo .logo-icon {
    width: 64px;
    height: 64px;
    background: #2c3e50;
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 32px;
    color: #f39c12;
    margin-bottom: 12px;
}

.login-logo h1 {
    font-size: 24px;
    color: #2c3e50;
    font-weight: 700;
    margin: 0;
}

.login-logo p {
    color: #6c757d;
    font-size: 14px;
    margin: 4px 0 0;
}

.login-form .form-group {
    margin-bottom: 20px;
}

.login-form label {
    display: block;
    font-weight: 600;
    font-size: 13px;
    color: #2c3e50;
    margin-bottom: 6px;
}

.login-form .input-group {
    position: relative;
}

.login-form .input-group .input-icon {
    position: absolute;
    left: 14px;
    top: 50%;
    transform: translateY(-50%);
    color: #999;
    font-size: 16px;
}

.login-form .input-group input {
    width: 100%;
    padding: 12px 12px 12px 44px;
    border: 2px solid #e9ecef;
    border-radius: 8px;
    font-size: 14px;
    transition: all 0.3s;
    background: #f8f9fa;
}

.login-form .input-group input:focus {
    border-color: #f39c12;
    background: #fff;
    outline: none;
    box-shadow: 0 0 0 3px rgba(243, 156, 18, 0.15);
}

.login-form .input-group input.error {
    border-color: #e74c3c;
    background: #fff5f5;
}

.login-form .input-group input.error:focus {
    box-shadow: 0 0 0 3px rgba(231, 76, 60, 0.15);
}

/* 🔥 Toggle Password */
.toggle-password {
    position: absolute;
    right: 14px;
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

/* 🔥 Remember Me */
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
    padding: 14px;
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
    box-shadow: 0 4px 15px rgba(44, 62, 80, 0.3);
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
    padding: 12px 15px;
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

/* 🔥 Footer */
.login-footer {
    text-align: center;
    margin-top: 20px;
    font-size: 13px;
    color: #6c757d;
}

.login-footer a {
    color: #f39c12;
    text-decoration: none;
}

.login-footer a:hover {
    text-decoration: underline;
}

/* 🔥 Responsive */
@media (max-width: 480px) {
    .login-box {
        padding: 28px 20px;
    }
    .login-logo .logo-icon {
        width: 50px;
        height: 50px;
        font-size: 24px;
    }
    .login-logo h1 {
        font-size: 20px;
    }
    .form-options {
        flex-direction: column;
        gap: 8px;
        align-items: flex-start;
    }
}
</style>

<div class="admin-login">
    <div class="login-container">
        <div class="login-box">
            <!-- 🔥 LOGO -->
            <div class="login-logo">
                <div class="logo-icon">
                    <i class="fas fa-print"></i>
                </div>
                <h1>Rainbow Printing</h1>
                <p>Admin Panel</p>
            </div>

            <!-- 🔥 ALERT -->
            <?php if ($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?= htmlspecialchars($error) ?>
                    <button class="close-btn" onclick="this.parentElement.remove()">&times;</button>
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['expired'])): ?>
                <div class="alert alert-info">
                    <i class="fas fa-clock"></i>
                    Session telah berakhir. Silakan login kembali.
                    <button class="close-btn" onclick="this.parentElement.remove()">&times;</button>
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['logout'])): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    Anda telah logout.
                    <button class="close-btn" onclick="this.parentElement.remove()">&times;</button>
                </div>
            <?php endif; ?>

            <!-- 🔥 FORM -->
            <form method="POST" class="login-form" id="loginForm" autocomplete="off">
                <div class="form-group">
                    <label for="username">
                        <i class="fas fa-user" style="color:#f39c12;"></i> Username
                    </label>
                    <div class="input-group">
                        <span class="input-icon"><i class="fas fa-user"></i></span>
                        <input type="text" name="username" id="username" 
                               value="<?= htmlspecialchars($username) ?>" 
                               placeholder="Masukkan username" required autofocus
                               class="<?= $error ? 'error' : '' ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label for="password">
                        <i class="fas fa-lock" style="color:#f39c12;"></i> Password
                    </label>
                    <div class="input-group">
                        <span class="input-icon"><i class="fas fa-lock"></i></span>
                        <input type="password" name="password" id="password" 
                               placeholder="Masukkan password" required
                               class="<?= $error ? 'error' : '' ?>">
                        <button type="button" class="toggle-password" onclick="togglePassword()">
                            <i class="fas fa-eye" id="toggleIcon"></i>
                        </button>
                    </div>
                </div>

                <!-- 🔥 REMEMBER ME -->
                <div class="form-options">
                    <label>
                        <input type="checkbox" name="remember" id="remember">
                        <span>Ingat saya</span>
                    </label>
                    <a href="/admin/forgot-password.php">Lupa password?</a>
                </div>

                <!-- 🔥 SUBMIT -->
                <button type="submit" class="btn-login" id="loginBtn">
                    <span class="btn-text"><i class="fas fa-sign-in-alt"></i> Login</span>
                    <span class="spinner"></span>
                </button>
            </form>

            <!-- 🔥 FOOTER -->
            <div class="login-footer">
                <p>
                    <a href="/" target="_blank"><i class="fas fa-globe"></i> Lihat Website</a>
                    &nbsp;|&nbsp;
                    <a href="/login.php" target="_blank"><i class="fas fa-user"></i> Customer Login</a>
                </p>
                <p style="font-size:12px;color:#999;margin-top:8px;">
                    &copy; <?= date('Y') ?> Rainbow Printing. All rights reserved.
                </p>
            </div>
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
        var username = document.getElementById('username');
        if (username.value) {
            document.getElementById('password').focus();
        } else {
            username.focus();
        }
    <?php endif; ?>
});

/**
 * 🔥 Enter key untuk submit
 */
document.addEventListener('keydown', function(e) {
    if (e.key === 'Enter') {
        var active = document.activeElement;
        if (active && (active.id === 'username' || active.id === 'password')) {
            document.getElementById('loginBtn').click();
        }
    }
});
</script>

<?php 
// 🔥 CLEAN OUTPUT BUFFER
ob_end_flush();
include '../includes/footer.php'; 
?>