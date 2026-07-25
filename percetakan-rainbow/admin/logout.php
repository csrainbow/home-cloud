<?php
/**
 * Admin Logout
 * Membersihkan session admin dan redirect ke halaman login
 */

// 🔥 Aktifkan error reporting untuk debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 🔥 Mulai session jika belum
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 🔥 🔥 LOG AKTIVITAS LOGOUT 🔥 🔥
if (isset($_SESSION['admin_username'])) {
    $username = $_SESSION['admin_username'];
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    error_log("Admin logout: $username from IP: $ipAddress");
    
    // 🔥 Simpan ke database (opsional)
    try {
        require_once __DIR__ . '/../config.php';
        $stmt = $db->prepare("INSERT INTO admin_logs (admin_id, username, action, ip_address, created_at) 
                               VALUES (?, ?, 'logout', ?, CURRENT_TIMESTAMP)");
        $stmt->execute([
            $_SESSION['admin_id'] ?? 0,
            $username,
            $ipAddress
        ]);
    } catch (Exception $e) {
        // Abaikan error jika tabel belum ada
    }
}

// 🔥 🔥 HAPUS COOKIE REMEMBER ME 🔥 🔥
if (isset($_COOKIE['admin_remember'])) {
    // Hapus cookie dari browser
    setcookie('admin_remember', '', time() - 3600, '/', '', false, true);
    
    // Hapus token dari database (opsional)
    try {
        require_once __DIR__ . '/../config.php';
        $stmt = $db->prepare("UPDATE admin_sessions SET remember_token = NULL, expires_at = NULL WHERE remember_token = ?");
        $stmt->execute([$_COOKIE['admin_remember']]);
    } catch (Exception $e) {
        // Abaikan error jika tabel belum ada
    }
}

// 🔥 🔥 HAPUS SEMUA SESSION 🔥 🔥
$_SESSION = array(); // Hapus semua variabel session

// 🔥 Hapus session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// 🔥 Destroy session
session_destroy();

// 🔥 🔥 REDIRECT KE LOGIN DENGAN PESAN 🔥 🔥
// Gunakan JavaScript untuk flash message (karena session sudah di-destroy)
$logoutMessage = urlencode('Anda telah logout. Terima kasih!');
header('Location: index.php?logout=success&message=' . $logoutMessage);
exit;