<?php
/**
 * upload-design.php - Upload file desain dari customer
 * Digunakan oleh AJAX di halaman produk
 * 
 * Metode: POST
 * Response: JSON
 */

// 🔥 DEBUG
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/config.php';

header('Content-Type: application/json');

// 🔥 🔥 FUNGSI LOG 🔥 🔥
function logDesignUpload($message, $data = null) {
    $logFile = __DIR__ . '/logs/design_uploads.log';
    if (!is_dir(__DIR__ . '/logs')) {
        mkdir(__DIR__ . '/logs', 0755, true);
    }
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] " . $message;
    if ($data) {
        $logMessage .= " - " . print_r($data, true);
    }
    file_put_contents($logFile, $logMessage . PHP_EOL, FILE_APPEND);
}

// 🔥 🔥 CEK METODE & SESSION 🔥 🔥
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// 🔥 Customer harus login
if (!isset($_SESSION['customer_id'])) {
    logDesignUpload("Unauthorized upload attempt", ['ip' => $_SERVER['REMOTE_ADDR']]);
    echo json_encode(['success' => false, 'message' => 'Silakan login terlebih dahulu']);
    exit;
}

// 🔥 🔥 CEK FILE 🔥 🔥
if (!isset($_FILES['design_file']) || $_FILES['design_file']['error'] !== UPLOAD_ERR_OK) {
    $errorCode = $_FILES['design_file']['error'] ?? 'No file';
    logDesignUpload("Upload error", ['error' => $errorCode]);
    echo json_encode(['success' => false, 'message' => 'File tidak valid atau gagal diupload']);
    exit;
}

$file = $_FILES['design_file'];
$customerId = $_SESSION['customer_id'];
$customerName = $_SESSION['customer_name'] ?? 'Customer';

// 🔥 🔥 VALIDASI UKURAN 🔥 🔥
$maxSize = 20 * 1024 * 1024; // 20MB
if ($file['size'] > $maxSize) {
    logDesignUpload("File too large", ['size' => $file['size'], 'customer' => $customerId]);
    echo json_encode(['success' => false, 'message' => 'Ukuran file maksimal 20MB']);
    exit;
}

// 🔥 🔥 VALIDASI EKSTENSI 🔥 🔥
$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
$allowedExts = ['jpg', 'jpeg', 'png', 'gif', 'pdf'];
if (!in_array($ext, $allowedExts)) {
    logDesignUpload("Invalid extension", ['ext' => $ext, 'customer' => $customerId]);
    echo json_encode(['success' => false, 'message' => 'Format file harus JPG, JPEG, PNG, GIF, atau PDF']);
    exit;
}

// 🔥 🔥 VALIDASI MIME TYPE (KEAMANAN) 🔥 🔥
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

$allowedMimes = [
    'image/jpeg', 'image/png', 'image/gif', 'application/pdf'
];
if (!in_array($mimeType, $allowedMimes)) {
    logDesignUpload("Invalid MIME type", ['mime' => $mimeType, 'customer' => $customerId]);
    echo json_encode(['success' => false, 'message' => 'Tipe file tidak didukung']);
    exit;
}

// 🔥 🔥 CEK APAKAH FILE BENAR-BENAR GAMBAR/PDF 🔥 🔥
if (strpos($mimeType, 'image/') === 0) {
    // Cek apakah gambar valid
    $imageInfo = @getimagesize($file['tmp_name']);
    if ($imageInfo === false) {
        logDesignUpload("Invalid image file", ['customer' => $customerId]);
        echo json_encode(['success' => false, 'message' => 'File gambar rusak atau tidak valid']);
        exit;
    }
}

// 🔥 🔥 PASTIKAN TABEL DESIGN_UPLOADS ADA 🔥 🔥
$db->exec("CREATE TABLE IF NOT EXISTS design_uploads (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    customer_id INTEGER NOT NULL,
    filename TEXT NOT NULL,
    original_name TEXT NOT NULL,
    file_size INTEGER NOT NULL,
    mime_type TEXT NOT NULL,
    ip_address TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)");

// 🔥 🔥 RATE LIMITING (CEGAH SPAM UPLOAD) 🔥 🔥
$ipAddress = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$stmt = $db->prepare("SELECT COUNT(*) as c FROM design_uploads WHERE ip_address = ? AND created_at > datetime('now', '-1 hour')");
$stmt->execute([$ipAddress]);
$uploadCount = $stmt->fetch()['c'];

if ($uploadCount >= 10) {
    logDesignUpload("Rate limit exceeded", ['ip' => $ipAddress, 'count' => $uploadCount]);
    echo json_encode(['success' => false, 'message' => 'Terlalu banyak upload dalam 1 jam. Coba lagi nanti.']);
    exit;
}

// 🔥 🔥 SIMPAN FILE 🔥 🔥
$uploadDir = __DIR__ . '/uploads/designs/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// 🔥 Buat nama file unik + sanitasi nama asli untuk referensi
$originalName = basename($file['name']);
$safeOriginal = preg_replace('/[^a-zA-Z0-9._-]/', '', $originalName);
$filename = uniqid('design_') . '_' . time() . '.' . $ext;
$targetPath = $uploadDir . $filename;

if (move_uploaded_file($file['tmp_name'], $targetPath)) {
    // 🔥 🔥 SIMPAN RECORD KE DATABASE 🔥 🔥
    try {
        $stmt = $db->prepare("INSERT INTO design_uploads (customer_id, filename, original_name, file_size, mime_type, ip_address) 
                               VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $customerId,
            $filename,
            $originalName,
            $file['size'],
            $mimeType,
            $ipAddress
        ]);
        logDesignUpload("File uploaded", ['customer' => $customerId, 'file' => $filename]);
    } catch (Exception $e) {
        logDesignUpload("DB error: " . $e->getMessage(), ['customer' => $customerId]);
        // Tidak mengganggu proses utama
    }

    // 🔥 🔥 KIRIM NOTIFIKASI KE ADMIN 🔥 🔥
    try {
        $adminEmail = getSetting('admin_email');
        if ($adminEmail) {
            $subject = "📎 Upload Desain Baru - " . $originalName;
            $message = "Customer mengupload file desain:\n\n";
            $message .= "Nama file: " . $originalName . "\n";
            $message .= "Ukuran: " . number_format($file['size'] / 1024, 1) . " KB\n";
            $message .= "Tipe: " . strtoupper($ext) . "\n";
            $message .= "Customer: " . $customerName . " (ID: $customerId)\n";
            $message .= "IP: " . $ipAddress . "\n";
            $message .= "Link: https://rainbowprinting.web.id/uploads/designs/" . $filename . "\n";
            $message .= "Waktu: " . date('d/m/Y H:i:s') . "\n";
            sendEmail($adminEmail, $subject, $message);
            logDesignUpload("Admin email sent", ['email' => $adminEmail]);
        }
    } catch (Exception $e) {
        logDesignUpload("Email error: " . $e->getMessage());
    }

    // 🔥 🔥 RESPONSE 🔥 🔥
    echo json_encode([
        'success' => true,
        'filename' => $filename,
        'original_name' => $originalName,
        'message' => 'File berhasil diupload'
    ]);

} else {
    logDesignUpload("Move failed", ['customer' => $customerId, 'target' => $targetPath]);
    echo json_encode(['success' => false, 'message' => 'Gagal menyimpan file. Periksa izin folder.']);
}

exit;