<?php
// 🔥 DEBUG
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../config.php';
header('Content-Type: application/json');

// 🔥 CEK SESSION - CUSTOMER HARUS LOGIN
if (!isset($_SESSION['customer_id'])) {
    echo json_encode([
        'success' => false, 
        'message' => 'Silakan login terlebih dahulu',
        'code' => 'unauthorized'
    ]);
    exit;
}

// 🔥 CEK MIDTRANS CONFIG
$serverKey = getSetting('midtrans_server_key');
if (!$serverKey) {
    echo json_encode([
        'success' => false, 
        'message' => 'Midtrans belum dikonfigurasi. Hubungi admin.',
        'code' => 'config_error'
    ]);
    exit;
}

// 🔥 AMBIL INPUT
$input = json_decode(file_get_contents('php://input'), true);
$orderCode = $input['order_code'] ?? '';

if (empty($orderCode)) {
    echo json_encode([
        'success' => false, 
        'message' => 'Kode pesanan tidak ditemukan',
        'code' => 'invalid_order'
    ]);
    exit;
}

// 🔥 CEK ORDER - PASTIKAN MILIK CUSTOMER YANG LOGIN
$stmt = $db->prepare("SELECT * FROM orders WHERE order_code = ? AND customer_id = ?");
$stmt->execute([$orderCode, $_SESSION['customer_id']]);
$order = $stmt->fetch();

if (!$order) {
    echo json_encode([
        'success' => false, 
        'message' => 'Pesanan tidak ditemukan',
        'code' => 'order_not_found'
    ]);
    exit;
}

// 🔥 CEK STATUS PEMBAYARAN
if ($order['payment_status'] === 'paid') {
    echo json_encode([
        'success' => false, 
        'message' => 'Pesanan ini sudah lunas!',
        'code' => 'already_paid'
    ]);
    exit;
}

// 🔥 HITUNG SISA PEMBAYARAN
$totalPaidStmt = $db->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM payments WHERE order_id=? AND status IN ('verified','approved','paid')");
$totalPaidStmt->execute([$order['id']]);
$totalPaid = floatval($totalPaidStmt->fetch()['total']);
$sisaPembayaran = $order['total'] - $totalPaid;

// 🔥 JIKA SUDAH LUNAS
if ($sisaPembayaran <= 0) {
    echo json_encode([
        'success' => false, 
        'message' => 'Pesanan ini sudah lunas!',
        'code' => 'already_paid'
    ]);
    exit;
}

// 🔥 AMBIL ITEM PESANAN
$items = $db->prepare("SELECT * FROM order_items WHERE order_id = ?");
$items->execute([$order['id']]);
$orderItems = $items->fetchAll();

if (empty($orderItems)) {
    echo json_encode([
        'success' => false, 
        'message' => 'Tidak ada item dalam pesanan',
        'code' => 'empty_order'
    ]);
    exit;
}

// 🔥 KONFIGURASI MIDTRANS
$isSandbox = strpos($serverKey, 'SB-') === 0;
$baseUrl = $isSandbox ? 'https://app.sandbox.midtrans.com' : 'https://app.midtrans.com';

// 🔥 SPLIT NAMA CUSTOMER
$customerName = explode(' ', $order['customer_name'], 2);
$firstName = $customerName[0];
$lastName = $customerName[1] ?? '';

// 🔥 🔥 BUILD ITEM DETAILS UNTUK MIDTRANS 🔥 🔥
$itemDetails = [];
$totalItemAmount = 0;

foreach ($orderItems as $item) {
    $price = (int) $item['price'];
    $qty = (int) $item['quantity'];
    $name = $item['product_name'];
    
    if ($item['design_service'] === 'jasa') {
        $name .= ' (+ Jasa Desain)';
    } elseif ($item['design_service'] === 'upload') {
        $name .= ' (+ File Desain)';
    }
    
    // 🔥 Tambahkan dimensi jika custom size
    if ($item['width'] > 0 && $item['height'] > 0) {
        $name .= ' (' . $item['width'] . '×' . $item['height'] . ' cm)';
    }
    
    $itemDetails[] = [
        'id' => $item['product_id'],
        'price' => $price,
        'quantity' => $qty,
        'name' => substr($name, 0, 50) // Maks 50 karakter
    ];
    
    $totalItemAmount += $price * $qty;
}

// 🔥 🔥 PARAMETER MIDTRANS 🔥 🔥
$params = [
    'transaction_details' => [
        'order_id' => $order['order_code'] . '-' . time(),
        'gross_amount' => (int) $sisaPembayaran,
    ],
    'customer_details' => [
        'first_name' => substr($firstName, 0, 20),
        'last_name' => substr($lastName, 0, 20),
        'phone' => $order['customer_phone'],
        'email' => $order['customer_email'] ?? 'customer@rainbowprinting.com',
    ],
    'item_details' => $itemDetails,
    'enabled_payments' => [
        'credit_card',
        'mandiri_clickpay',
        'bca_klikbca',
        'bca_klikpay',
        'bri_epay',
        'echannel',
        'permata_va',
        'bca_va',
        'bni_va',
        'bri_va',
        'other_va',
        'gopay',
        'shopeepay',
        'qris',
        'indomaret',
        'alfamart',
        'danamon_online',
        'akulaku',
        'kredivo'
    ],
    'finish_redirect_url' => BASE_URL . 'payment/finish.php?order=' . urlencode($order['order_code']),
    'unfinish_redirect_url' => BASE_URL . 'payment/finish.php?order=' . urlencode($order['order_code']) . '&status=pending',
    'error_redirect_url' => BASE_URL . 'payment/finish.php?order=' . urlencode($order['order_code']) . '&status=error',
];

// 🔥 TAMBAHKAN CUSTOM FIELD UNTUK DP
if ($totalPaid > 0 && $sisaPembayaran > 0) {
    $params['custom_field1'] = 'pelunasan';
    $params['custom_field2'] = 'Sisa pembayaran dari DP: Rp ' . number_format($sisaPembayaran, 0, ',', '.');
    $params['custom_field3'] = 'DP sudah dibayar: Rp ' . number_format($totalPaid, 0, ',', '.');
} else {
    $params['custom_field1'] = 'full_payment';
    $params['custom_field2'] = 'Pembayaran penuh';
}

// 🔥 🔥 LOG UNTUK DEBUGGING 🔥 🔥
logMidtrans("=== MIDTRANS CREATE PAYMENT ===");
logMidtrans("Order Code: " . $order['order_code']);
logMidtrans("Amount: " . $sisaPembayaran);
logMidtrans("Customer: " . $order['customer_name']);
logMidtrans("Items: " . count($orderItems));
logMidtrans("Params: " . json_encode($params));

// 🔥 🔥 KIRIM REQUEST KE MIDTRANS 🔥 🔥
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $baseUrl . '/snap/v1/transactions');
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Accept: application/json'
]);
curl_setopt($ch, CURLOPT_USERPWD, $serverKey . ':');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
$curlInfo = curl_getinfo($ch);
curl_close($ch);

// 🔥 🔥 LOG RESPONSE 🔥 🔥
logMidtrans("HTTP Code: " . $httpCode);
if ($curlError) {
    logMidtrans("CURL Error: " . $curlError);
}
logMidtrans("Response: " . $response);

// 🔥 🔥 PROSES RESPONSE 🔥 🔥
$result = json_decode($response, true);

if ($httpCode === 201 && isset($result['redirect_url'])) {
    // 🔥 UPDATE STATUS ORDER
    try {
        $db->prepare("UPDATE orders SET payment_status='pending_verification' WHERE id=?")->execute([$order['id']]);
        $db->prepare("UPDATE orders SET midtrans_token=? WHERE id=?")->execute([$result['token'] ?? '', $order['id']]);
        
        // 🔥 Simpan transaksi ke payments
        $stmt = $db->prepare("INSERT INTO payments (order_id, amount, bank_name, account_number, account_name, proof_image, payment_type, status, created_at) 
                               VALUES (?, ?, 'Midtrans', 'Online', 'Midtrans', '', 'midtrans', 'pending', CURRENT_TIMESTAMP)");
        $stmt->execute([$order['id'], $sisaPembayaran]);
        
        logMidtrans("✅ Payment created successfully for order: " . $order['order_code']);
        
        echo json_encode([
            'success' => true,
            'redirect_url' => $result['redirect_url'],
            'token' => $result['token'] ?? '',
            'amount' => $sisaPembayaran,
            'order_code' => $order['order_code'],
            'payment_type' => $totalPaid > 0 ? 'pelunasan' : 'full_payment'
        ]);
    } catch (Exception $e) {
        logMidtrans("❌ Database error: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => 'Gagal menyimpan data: ' . $e->getMessage(),
            'code' => 'db_error'
        ]);
    }
} else {
    $errorMsg = $result['error_messages'][0] ?? $result['message'] ?? 'Gagal membuat transaksi Midtrans';
    
    if ($curlError) {
        $errorMsg = 'CURL Error: ' . $curlError . '. Cek koneksi internet server.';
    }
    
    // 🔥 Handle error spesifik Midtrans
    if (isset($result['error_messages'])) {
        $errorMsg = implode('; ', $result['error_messages']);
    }
    
    logMidtrans("❌ MIDTRANS ERROR: " . $errorMsg);
    
    echo json_encode([
        'success' => false,
        'message' => $errorMsg,
        'code' => 'midtrans_error',
        'http_code' => $httpCode,
        'response' => $response
    ]);
}

// 🔥 🔥 FUNGSI LOG 🔥 🔥
function logMidtrans($message) {
    $logFile = __DIR__ . '/../logs/midtrans.log';
    if (!is_dir(__DIR__ . '/../logs')) {
        mkdir(__DIR__ . '/../logs', 0755, true);
    }
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] $message" . PHP_EOL, FILE_APPEND);
}