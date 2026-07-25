<?php
/**
 * logout.php - Logout Customer
 * Membersihkan session customer dan redirect ke halaman login
 */

// 🔥 Aktifkan error reporting untuk debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/config.php';

// 🔥 Mulai session jika belum
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 🔥 🔥 LOG AKTIVITAS LOGOUT 🔥 🔥
if (isset($_SESSION['customer_id'])) {
    $customerId = $_SESSION['customer_id'];
    $customerName = $_SESSION['customer_name'] ?? 'Unknown';
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    
    // 🔥 Log ke file
    error_log("Customer logout: ID: $customerId, Name: $customerName, IP: $ipAddress");
    
    // 🔥 Simpan ke database (opsional)
    try {
        $stmt = $db->prepare("INSERT INTO customer_logs (customer_id, action, ip_address, created_at) 
                               VALUES (?, 'logout', ?, CURRENT_TIMESTAMP)");
        $stmt->execute([$customerId, $ipAddress]);
    } catch (Exception $e) {
        // Abaikan error jika tabel belum ada
    }
}

// 🔥 🔥 HAPUS COOKIE REMEMBER ME 🔥 🔥
if (isset($_COOKIE['customer_remember'])) {
    // Hapus token dari database
    try {
        $token = $_COOKIE['customer_remember'];
        $stmt = $db->prepare("UPDATE customers SET remember_token = NULL, remember_expires = NULL WHERE remember_token = ?");
        $stmt->execute([$token]);
    } catch (Exception $e) {
        // Abaikan error jika kolom belum ada
    }
    
    // Hapus cookie dari browser
    setcookie('customer_remember', '', time() - 3600, '/', '', false, true);
}

// 🔥 🔥 HAPUS COOKIE CART (opsional) 🔥 🔥
if (isset($_COOKIE['cart'])) {
    setcookie('cart', '', time() - 3600, '/');
}

// 🔥 🔥 HAPUS SEMUA SESSION CUSTOMER 🔥 🔥
// Hapus semua variabel session terkait customer
unset($_SESSION['customer_id']);
unset($_SESSION['customer_name']);
unset($_SESSION['customer_email']);
unset($_SESSION['customer_phone']);
unset($_SESSION['customer_address']);
unset($_SESSION['redirect_after_login']);

// 🔥 Atau hapus semua session
// $_SESSION = array();

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

// 🔥 🔥 REDIRECT KE HALAMAN UTAMA DENGAN PESAN 🔥 🔥
// Gunakan session baru untuk flash message
session_start();
$_SESSION['success'] = '✅ Anda telah berhasil logout. Terima kasih telah berbelanja di Rainbow Printing!';

header('Location: /index.php');
exit;