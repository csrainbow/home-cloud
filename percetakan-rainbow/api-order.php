<?php
/**
 * api-order.php - API untuk membuat pesanan baru
 * Digunakan oleh JavaScript di halaman checkout
 * 
 * Metode: POST
 * Response: JSON
 */

// 🔥 DEBUG
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/config.php';

header('Content-Type: application/json');

// 🔥 🔥 LOGGING FUNCTION 🔥 🔥
function logOrder($message, $data = null) {
    $logFile = __DIR__ . '/logs/orders.log';
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

// 🔥 CEK METHOD
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false, 
        'message' => 'Method not allowed',
        'code' => 'method_not_allowed'
    ]);
    exit;
}

// 🔥 AMBIL INPUT
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    logOrder("Invalid input", ['input' => file_get_contents('php://input')]);
    echo json_encode([
        'success' => false, 
        'message' => 'Invalid input',
        'code' => 'invalid_input'
    ]);
    exit;
}

logOrder("Order request received", $input);

// 🔥 🔥 VALIDASI INPUT 🔥 🔥
$name = trim($input['name'] ?? '');
$phone = trim($input['phone'] ?? '');
$address = trim($input['address'] ?? '');
$notes = trim($input['notes'] ?? '');
$paymentMethod = $input['payment_method'] ?? '';
$items = $input['items'] ?? [];

// 🔥 Validasi wajib
if (!$name || !$phone || !$paymentMethod || empty($items)) {
    logOrder("Missing required fields", [
        'name' => $name,
        'phone' => $phone,
        'payment_method' => $paymentMethod,
        'items_count' => count($items)
    ]);
    echo json_encode([
        'success' => false, 
        'message' => 'Data tidak lengkap. Isi semua field yang wajib.',
        'code' => 'missing_fields'
    ]);
    exit;
}

// 🔥 Validasi nomor WhatsApp
$phoneClean = preg_replace('/[^0-9]/', '', $phone);
if (!preg_match('/^(0|62)\d{8,13}$/', $phoneClean)) {
    logOrder("Invalid phone number", ['phone' => $phone]);
    echo json_encode([
        'success' => false, 
        'message' => 'Nomor WhatsApp tidak valid. Gunakan format 08123456789 atau 628123456789.',
        'code' => 'invalid_phone'
    ]);
    exit;
}

// 🔥 🔥 PROSES ITEMS 🔥 🔥
$total = 0;
$validItems = [];
$hasDesignService = false;
$hasCustomSize = false;

foreach ($items as $item) {
    // 🔥 Cek produk
    $stmt = $db->prepare("SELECT * FROM products WHERE id = ?");
    $stmt->execute([$item['id']]);
    $product = $stmt->fetch();
    
    if (!$product) {
        logOrder("Product not found", ['product_id' => $item['id']]);
        continue;
    }
    
    $qty = max(1, min(999, intval($item['qty'] ?? 1)));
    $width = floatval($item['width'] ?? 0);
    $height = floatval($item['height'] ?? 0);
    
    $designService = trim($item['designService'] ?? '');
    $designFile = trim($item['designFile'] ?? '');
    $designOriginalName = trim($item['designOriginalName'] ?? '');
    
    $matName = trim($item['material'] ?? '');
    $matPrice = floatval($item['matPrice'] ?? 0);
    $effectivePricePerM2 = $matPrice > 0 ? $matPrice : ($product['price_per_m2'] ?? 0);
    $variants = trim($item['variants'] ?? '');
    
    // 🔥 🔥 HITUNG HARGA BERDASARKAN SATUAN 🔥 🔥
    $sizeUnit = $product['size_unit'] ?? 'none';
    if ($sizeUnit === 'm2' && $width > 0 && $height > 0) {
        $hasCustomSize = true;
        $m2 = ($width * $height) / 10000;
        $unitPrice = round($m2 * $effectivePricePerM2);
    } elseif ($sizeUnit === 'meter' && $width > 0) {
        $hasCustomSize = true;
        $meters = $width / 100;
        $unitPrice = round($meters * $effectivePricePerM2);
    } elseif ($sizeUnit !== 'none') {
        $hasCustomSize = true;
        $unitPrice = round($effectivePricePerM2);
    } else {
        $unitPrice = $product['price'];
    }
    if ($unitPrice <= 0) {
        $unitPrice = $sizeUnit !== 'none' ? $effectivePricePerM2 : $product['price'];
    }
    
    // 🔥 Tambahan Jasa Desain
    if ($designService === 'jasa') {
        $unitPrice += 25000;
        $hasDesignService = true;
    }
    
    $subtotal = $unitPrice * $qty;
    $total += $subtotal;
    
    $validItems[] = [
        'product_id' => $product['id'],
        'product_name' => $product['name'],
        'quantity' => $qty,
        'price' => $unitPrice,
        'subtotal' => $subtotal,
        'width' => $width,
        'height' => $height,
        'material' => $matName,
        'mat_price' => $effectivePricePerM2,
        'design_service' => $designService,
        'design_file' => $designFile,
        'design_original_name' => $designOriginalName,
        'variants' => $variants
    ];
}

if (empty($validItems)) {
    logOrder("No valid items", ['items' => $items]);
    echo json_encode([
        'success' => false, 
        'message' => 'Tidak ada item valid dalam pesanan.',
        'code' => 'no_valid_items'
    ]);
    exit;
}

// 🔥 🔥 CEK COD DENGAN JASA DESAIN 🔥 🔥
if ($paymentMethod === 'cod') {
    foreach ($validItems as $vi) {
        if ($vi['design_service'] === 'jasa') {
            logOrder("COD with design service rejected", ['order' => $name]);
            echo json_encode([
                'success' => false, 
                'message' => 'Pesanan dengan Jasa Desain tidak bisa menggunakan COD. Silakan pilih Transfer Bank atau QRIS.',
                'code' => 'cod_not_allowed'
            ]);
            exit;
        }
    }
}

// 🔥 🔥 CEK TOTAL MINIMUM 🔥 🔥
if ($total < 1000) {
    echo json_encode([
        'success' => false,
        'message' => 'Total pesanan minimal Rp 1.000.',
        'code' => 'min_order'
    ]);
    exit;
}

// 🔥 🔥 GENERATE ORDER CODE 🔥 🔥
$orderCode = generateOrderCode();
logOrder("Generated order code", ['order_code' => $orderCode]);

try {
    $db->beginTransaction();
    
    $customerId = isset($_SESSION['customer_id']) ? intval($_SESSION['customer_id']) : 0;
    
    // 🔥 🔥 INSERT ORDER 🔥 🔥
    $stmt = $db->prepare("INSERT INTO orders (
        order_code, 
        customer_name, 
        customer_phone, 
        customer_address, 
        notes, 
        total, 
        payment_method, 
        customer_id, 
        payment_deadline,
        created_at
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, datetime('now', '+5 minutes'), CURRENT_TIMESTAMP)");
    
    $stmt->execute([
        $orderCode,
        $name,
        $phoneClean,
        $address,
        $notes,
        $total,
        $paymentMethod,
        $customerId
    ]);
    
    $orderId = $db->lastInsertId();
    logOrder("Order inserted", ['order_id' => $orderId]);
    
    // 🔥 🔥 INSERT ORDER ITEMS 🔥 🔥
    $stmt = $db->prepare("INSERT INTO order_items (
        order_id, 
        product_id, 
        product_name, 
        quantity, 
        price, 
        subtotal, 
        width, 
        height, 
        unit_label, 
        material_name, 
        material_price_per_m2, 
        design_service, 
        design_file, 
        design_original_name,
        variants
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    
    foreach ($validItems as $vi) {
        $sizeUnit = $db->query("SELECT size_unit FROM products WHERE id=" . $vi['product_id'])->fetchColumn() ?: 'none';
        if ($vi['width'] > 0 && $vi['height'] > 0) {
            $label = intval($vi['width']) . 'x' . intval($vi['height']) . ' cm';
        } elseif ($vi['width'] > 0) {
            $label = intval($vi['width']) . ' cm';
        } else {
            $label = $sizeUnit !== 'none' ? $sizeUnit : '';
        }
        
        $stmt->execute([
            $orderId,
            $vi['product_id'],
            $vi['product_name'],
            $vi['quantity'],
            $vi['price'],
            $vi['subtotal'],
            $vi['width'],
            $vi['height'],
            $label,
            $vi['material'],
            $vi['mat_price'],
            $vi['design_service'],
            $vi['design_file'],
            $vi['design_original_name'],
            $vi['variants'] ?? ''
        ]);
    }
    
    // 🔥 🔥 SIMPAN KE CART (jika customer login) 🔥 🔥
    if ($customerId > 0) {
        try {
            $db->prepare("DELETE FROM cart WHERE customer_id = ?")->execute([$customerId]);
            logOrder("Cart cleared for customer", ['customer_id' => $customerId]);
        } catch (Exception $e) {
            // Abaikan error cart
        }
    }
    
    $db->commit();
    logOrder("Order committed successfully", [
        'order_code' => $orderCode,
        'total' => $total,
        'customer_id' => $customerId
    ]);
    
    // 🔥 🔥 KIRIM NOTIFIKASI KE ADMIN 🔥 🔥
    try {
        $adminEmail = getSetting('admin_email');
        if ($adminEmail) {
            $subject = "📦 Pesanan Baru - " . $orderCode;
            $message = "Pesanan baru telah dibuat:\n\n";
            $message .= "Kode: " . $orderCode . "\n";
            $message .= "Customer: " . $name . "\n";
            $message .= "Telepon: " . $phoneClean . "\n";
            $message .= "Total: Rp " . number_format($total, 0, ',', '.') . "\n";
            $message .= "Metode: " . $paymentMethod . "\n";
            $message .= "Item: " . count($validItems) . " item\n\n";
            $message .= "Link: https://rainbowprinting.web.id/admin/order-detail.php?id=" . $orderId;
            sendEmail($adminEmail, $subject, $message);
            logOrder("Admin notification sent", ['email' => $adminEmail]);
        }
    } catch (Exception $e) {
        logOrder("Email error: " . $e->getMessage());
    }
    
    // 🔥 🔥 RESPONSE 🔥 🔥
    echo json_encode([
        'success' => true,
        'message' => 'Pesanan berhasil dibuat!',
        'order_code' => $orderCode,
        'total' => $total,
        'order_id' => $orderId,
        'has_design' => $hasDesignService,
        'has_custom_size' => $hasCustomSize
    ]);
    
} catch (PDOException $e) {
    $db->rollBack();
    logOrder("Database error: " . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
    echo json_encode([
        'success' => false, 
        'message' => 'Gagal memproses pesanan: ' . $e->getMessage(),
        'code' => 'db_error'
    ]);
    
} catch (Exception $e) {
    $db->rollBack();
    logOrder("General error: " . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
    echo json_encode([
        'success' => false, 
        'message' => 'Gagal memproses pesanan: ' . $e->getMessage(),
        'code' => 'general_error'
    ]);
}

exit;