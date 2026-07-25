<?php
// ============================================
// CONFIGURASI SESSION & KEAMANAN
// ============================================

// 🔥 Atur session dengan aman
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 0); // Set 1 jika pakai HTTPS
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_samesite', 'Lax');

// 🔥 Mulai session jika belum
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ============================================
// ERROR REPORTING
// ============================================
error_reporting(E_ALL);
ini_set('display_errors', 0); // Set 0 untuk production
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/php-error.log');

// ============================================
// KONSTANTA
// ============================================

// 🔥 Database
define('DB_PATH', __DIR__ . '/database.sqlite');

// 🔥 URL
$baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '/';
define('BASE_URL', $baseUrl);

// 🔥 Nama Toko
define('SITE_NAME', 'Rainbow Printing');
define('SITE_DESCRIPTION', 'Percetakan online terpercaya di Samarinda. Cetak undangan, stiker, banner, dan kebutuhan percetakan lainnya.');

// 🔥 WhatsApp
define('WHATSAPP_NUMBER', '6281234567890');

// 🔥 Admin Login
define('ADMIN_USERNAME', 'admin');
define('ADMIN_PASSWORD_HASH', '$2y$10$0P6ctbBhBr7BmiHhifZvy.5vU694Ri4RHSdEbK14Cjc6ejzWOyBdS');

// ============================================
// LOGS FOLDER
// ============================================

if (!is_dir(__DIR__ . '/logs')) {
    mkdir(__DIR__ . '/logs', 0755, true);
}

// ============================================
// TIMEZONE
// ============================================

date_default_timezone_set('Asia/Makassar'); // WITA

// ============================================
// KONEKSI DATABASE
// ============================================

try {
    // 🔥 Cek apakah file database ada, jika tidak buat
    if (!file_exists(DB_PATH)) {
        file_put_contents(DB_PATH, '');
        chmod(DB_PATH, 0666);
    }
    
    $db = new PDO('sqlite:' . DB_PATH);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $db->setAttribute(PDO::ATTR_TIMEOUT, 30);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage() . "<br>Path: " . DB_PATH);
}

// ============================================
// 🔥 INCLUDE FUNCTIONS
// ============================================

$functionsFile = __DIR__ . '/includes/functions.php';
if (!file_exists($functionsFile)) {
    die("❌ File functions.php tidak ditemukan di: " . $functionsFile);
}

require_once $functionsFile;

// ============================================
// 🔥 INISIALISASI DATABASE (dari functions.php)
// ============================================

if (function_exists('initDatabase')) {
    initDatabase();
} else {
    die("❌ Fungsi initDatabase() tidak ditemukan di functions.php");
}

// ============================================
// 🔥 CEK KONEKSI DATABASE
// ============================================

try {
    $db->query("SELECT 1");
} catch (Exception $e) {
    die("❌ Database tidak bisa diakses: " . $e->getMessage());
}