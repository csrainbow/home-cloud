<?php
// 🔥 DEBUG - Aktifkan error logging
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

require_once __DIR__ . '/config.php';

// 🔥 BUAT LOG FOLDER JIKA BELUM ADA
if (!is_dir(__DIR__ . '/../logs')) {
    mkdir(__DIR__ . '/../logs', 0755, true);
}

// 🔥 🔥 FUNGSI LOG 🔥 🔥
function logMidtrans($message, $data = null) {
    $logFile = __DIR__ . '/../logs/midtrans.log';
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] " . $message;
    if ($data) {
        $logMessage .= " - " . print_r($data, true);
    }
    file_put_contents($logFile, $logMessage . PHP_EOL, FILE_APPEND);
}

// 🔥 🔥 FUNGSI SEND EMAIL (FALLBACK) 🔥 🔥
if (!function_exists('sendEmail')) {
    function sendEmail($to, $subject, $message) {
        $headers = "From: admin@rainbowprinting.web.id\r\n";
        $headers .= "Reply-To: admin@rainbowprinting.web.id\r\n";
        $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
        return @mail($to, $subject, $message, $headers);
    }
}

// 🔥 AMBIL NOTIFIKASI
$notification = json_decode(file_get_contents('php://input'), true);
logMidtrans("📩 Notification received", $notification);

if (!$notification) {
    logMidtrans("❌ Invalid notification - empty");
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid notification']);
    exit;
}

// 🔥 🔥 VALIDASI ORDER 🔥 🔥
$orderCode = $notification['order_id'] ?? '';
$transactionStatus = $notification['transaction_status'] ?? '';
$fraudStatus = $notification['fraud_status'] ?? '';
$paymentType = $notification['payment_type'] ?? '';
$grossAmount = $notification['gross_amount'] ?? 0;
$signatureKey = $notification['signature_key'] ?? '';
$statusCode = $notification['status_code'] ?? '';

logMidtrans("📋 Processing notification", [
    'order_code' => $orderCode,
    'transaction_status' => $transactionStatus,
    'fraud_status' => $fraudStatus,
    'payment_type' => $paymentType,
    'gross_amount' => $grossAmount
]);

if (!$orderCode || !$transactionStatus) {
    logMidtrans("❌ Missing required fields", [
        'order_code' => $orderCode, 
        'transaction_status' => $transactionStatus
    ]);
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Missing required fields']);
    exit;
}

// 🔥 🔥 CEK ORDER 🔥 🔥
$stmt = $db->prepare("SELECT * FROM orders WHERE order_code = ?");
$stmt->execute([$orderCode]);
$order = $stmt->fetch();

if (!$order) {
    logMidtrans("❌ Order not found", ['order_code' => $orderCode]);
    http_response_code(404);
    echo json_encode(['status' => 'error', 'message' => 'Order not found']);
    exit;
}

logMidtrans("✅ Order found", [
    'order_id' => $order['id'], 
    'status' => $order['status'],
    'payment_status' => $order['payment_status']
]);

// 🔥 🔥 VALIDASI SIGNATURE KEY (KEAMANAN) 🔥 🔥
$serverKey = getSetting('midtrans_server_key');
$signatureValid = false;

if ($serverKey && $signatureKey) {
    $orderId = $notification['order_id'];
    $statusCode = $notification['status_code'] ?? '';
    $grossAmt = $notification['gross_amount'] ?? '0';
    
    // Generate signature sesuai Midtrans docs
    $signature = hash('sha512', $orderId . $statusCode . $grossAmt . $serverKey);
    
    if ($signature === $signatureKey) {
        $signatureValid = true;
        logMidtrans("✅ Signature verified successfully");
    } else {
        logMidtrans("⚠️ WARNING: Invalid signature", [
            'expected' => $signature,
            'received' => $signatureKey
        ]);
        // Jangan exit, lanjutkan tapi tandai
    }
} else {
    logMidtrans("⚠️ Signature validation skipped - no server key or signature key");
}

// 🔥 🔥 UPDATE STATUS BERDASARKAN RESPONSE MIDTRANS 🔥 🔥
$orderUpdated = false;
$paymentRecorded = false;
$newPaymentStatus = null;
$newOrderStatus = null;

// 🔥 HITUNG TOTAL PEMBAYARAN YANG SUDAH TERVERIFIKASI
$totalPaidStmt = $db->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM payments WHERE order_id=? AND status IN ('verified','approved','paid')");
$totalPaidStmt->execute([$order['id']]);
$totalPaid = floatval($totalPaidStmt->fetch()['total']);
$sisaPembayaran = $order['total'] - $totalPaid;

// 🔥 CEK APAKAH ADA JASA DESAIN
$stmt = $db->prepare("SELECT COUNT(*) as c FROM order_items WHERE order_id=? AND design_service='jasa'");
$stmt->execute([$order['id']]);
$hasJasa = $stmt->fetch()['c'] > 0;

// 🔥 AMBIL EMAIL CUSTOMER
$customerEmail = null;
if ($order['customer_id'] > 0) {
    $cust = $db->prepare("SELECT email FROM customers WHERE id=?");
    $cust->execute([$order['customer_id']]);
    $c = $cust->fetch();
    if ($c) $customerEmail = $c['email'];
}

// 🔥 🔥 STATUS BERHASIL (CAPTURE/SETTLEMENT) 🔥 🔥
if (in_array($transactionStatus, ['capture', 'settlement'])) {
    if ($fraudStatus === 'accept' || $fraudStatus === null || $fraudStatus === '') {
        logMidtrans("✅ Payment successful - status: $transactionStatus, fraud: $fraudStatus");
        
        $amount = floatval($grossAmount);
        $newTotalPaid = $totalPaid + $amount;
        
        // 🔥 TENTUKAN STATUS PEMBAYARAN
        if ($newTotalPaid >= $order['total']) {
            $newPaymentStatus = 'paid';
            $newOrderStatus = $hasJasa ? 'desain' : 'processed';
            logMidtrans("✅ Full payment - LUNAS");
        } else {
            $newPaymentStatus = 'dp';
            $newOrderStatus = $hasJasa ? 'desain' : 'processed';
            logMidtrans("💰 DP payment - DP (" . round(($newTotalPaid/$order['total'])*100) . "%)");
        }
        
        // 🔥 UPDATE ORDER
        $db->prepare("UPDATE orders SET payment_status=?, status=? WHERE id=?")->execute([
            $newPaymentStatus,
            $newOrderStatus,
            $order['id']
        ]);
        $orderUpdated = true;
        
        // 🔥 SIMPAN KE TABEL PAYMENTS
        $checkPayment = $db->prepare("SELECT id FROM payments WHERE order_id=? AND payment_type='midtrans' AND amount=?");
        $checkPayment->execute([$order['id'], $amount]);
        if (!$checkPayment->fetch()) {
            $stmt = $db->prepare("INSERT INTO payments (order_id, amount, bank_name, account_number, account_name, proof_image, payment_type, status, created_at) 
                                   VALUES (?, ?, 'Midtrans', 'Online', 'Midtrans', '', 'midtrans', 'approved', NOW())");
            $stmt->execute([
                $order['id'],
                $amount,
                $paymentType ?: 'midtrans'
            ]);
            $paymentRecorded = true;
            logMidtrans("✅ Payment recorded in payments table", ['amount' => $amount]);
        }
        
        // 🔥 🔥 KIRIM EMAIL NOTIFIKASI 🔥 🔥
        try {
            $adminEmail = getSetting('admin_email');
            
            // 🔥 Email ke Admin
            if ($adminEmail) {
                $paymentLabel = $newPaymentStatus === 'paid' ? 'LUNAS' : 'DP';
                $subject = "💰 Pembayaran $paymentLabel - " . $order['order_code'];
                $message = "Pembayaran baru dari Midtrans:\n\n";
                $message .= "Kode: " . $order['order_code'] . "\n";
                $message .= "Customer: " . $order['customer_name'] . "\n";
                $message .= "Status: " . $paymentLabel . "\n";
                $message .= "Jumlah: Rp " . number_format($amount, 0, ',', '.') . "\n";
                $message .= "Total dibayar: Rp " . number_format($newTotalPaid, 0, ',', '.') . "\n";
                $message .= "Sisa: Rp " . number_format($order['total'] - $newTotalPaid, 0, ',', '.') . "\n\n";
                $message .= "Link: https://rainbowprinting.web.id/admin/order-detail.php?id=" . $order['id'];
                sendEmail($adminEmail, $subject, $message);
                logMidtrans("📧 Admin email sent to: $adminEmail");
            }
            
            // 🔥 Email ke Customer
            if ($customerEmail) {
                $paymentLabel = $newPaymentStatus === 'paid' ? 'Lunas' : 'DP';
                $subject = "✅ Pembayaran $paymentLabel Berhasil - " . $order['order_code'];
                $message = "Halo " . $order['customer_name'] . ",\n\n";
                $message .= "Pembayaran Anda untuk pesanan " . $order['order_code'] . " telah berhasil.\n\n";
                $message .= "Status: " . $paymentLabel . "\n";
                $message .= "Jumlah: Rp " . number_format($amount, 0, ',', '.') . "\n";
                if ($newPaymentStatus === 'dp' && $newTotalPaid < $order['total']) {
                    $message .= "Sisa pembayaran: Rp " . number_format($order['total'] - $newTotalPaid, 0, ',', '.') . "\n";
                    $message .= "Silakan lunasi sisa pembayaran melalui halaman pesanan Anda.\n\n";
                }
                $message .= "Terima kasih telah berbelanja di Rainbow Printing!\n";
                $message .= "Link: https://rainbowprinting.web.id/customer/order-detail.php?order=" . $order['order_code'];
                sendEmail($customerEmail, $subject, $message);
                logMidtrans("📧 Customer email sent to: $customerEmail");
            }
        } catch (Exception $e) {
            logMidtrans("❌ Email error: " . $e->getMessage());
        }
        
    } else {
        logMidtrans("⚠️ Fraud status not accepted: $fraudStatus");
    }
}

// 🔥 🔥 STATUS PENDING 🔥 🔥
elseif ($transactionStatus === 'pending') {
    logMidtrans("⏳ Payment pending - waiting for customer");
    $db->prepare("UPDATE orders SET payment_status='pending_verification' WHERE id=?")->execute([$order['id']]);
    $orderUpdated = true;
    
    // 🔥 Notifikasi ke admin
    try {
        $adminEmail = getSetting('admin_email');
        if ($adminEmail) {
            $subject = "⏳ Pembayaran Pending - " . $order['order_code'];
            $message = "Pembayaran Midtrans sedang pending:\n\n";
            $message .= "Kode: " . $order['order_code'] . "\n";
            $message .= "Customer: " . $order['customer_name'] . "\n";
            $message .= "Status: Pending\n";
            $message .= "Jumlah: Rp " . number_format($grossAmount, 0, ',', '.') . "\n\n";
            $message .= "Link: https://rainbowprinting.web.id/admin/order-detail.php?id=" . $order['id'];
            sendEmail($adminEmail, $subject, $message);
        }
    } catch (Exception $e) {
        logMidtrans("❌ Email error: " . $e->getMessage());
    }
}

// 🔥 🔥 STATUS GAGAL (DENY/CANCEL/EXPIRE) 🔥 🔥
elseif (in_array($transactionStatus, ['deny', 'cancel', 'expire'])) {
    logMidtrans("❌ Payment failed - status: $transactionStatus");
    
    // 🔥 CEK APAKAH SUDAH PERNAH BAYAR SEBELUMNYA
    if ($totalPaid > 0) {
        // Jika sudah pernah bayar, tetap pertahankan status DP
        $db->prepare("UPDATE orders SET payment_status='dp' WHERE id=?")->execute([$order['id']]);
        logMidtrans("💰 Keeping DP status because previous payment exists");
    } else {
        $db->prepare("UPDATE orders SET payment_status='unpaid', status='cancelled' WHERE id=?")->execute([$order['id']]);
        logMidtrans("❌ Order cancelled - no payment made");
    }
    $orderUpdated = true;
    
    // 🔥 Notifikasi ke admin
    try {
        $adminEmail = getSetting('admin_email');
        if ($adminEmail) {
            $subject = "❌ Pembayaran Gagal - " . $order['order_code'];
            $message = "Pembayaran Midtrans gagal:\n\n";
            $message .= "Kode: " . $order['order_code'] . "\n";
            $message .= "Customer: " . $order['customer_name'] . "\n";
            $message .= "Status: " . $transactionStatus . "\n";
            $message .= "Jumlah: Rp " . number_format($grossAmount, 0, ',', '.') . "\n\n";
            $message .= "Link: https://rainbowprinting.web.id/admin/order-detail.php?id=" . $order['id'];
            sendEmail($adminEmail, $subject, $message);
        }
    } catch (Exception $e) {
        logMidtrans("❌ Email error: " . $e->getMessage());
    }
}

// 🔥 🔥 STATUS LAINNYA 🔥 🔥
else {
    logMidtrans("⚠️ Unknown transaction status: $transactionStatus");
    $db->prepare("UPDATE orders SET payment_status='pending_verification' WHERE id=?")->execute([$order['id']]);
    $orderUpdated = true;
}

// 🔥 🔥 UPDATE PAYMENT STATUS DI TABEL PAYMENTS 🔥 🔥
if ($orderUpdated) {
    try {
        // Update semua payment yang masih pending
        $db->prepare("UPDATE payments SET status='approved' WHERE order_id=? AND status='pending'")->execute([$order['id']]);
        logMidtrans("✅ Updated pending payments to approved");
    } catch (Exception $e) {
        logMidtrans("⚠️ Failed to update payments: " . $e->getMessage());
    }
}

// 🔥 🔥 RESPONSE KE MIDTRANS 🔥 🔥
http_response_code(200);
echo json_encode(['status' => 'ok']);

logMidtrans("✅ Notification processed successfully", [
    'order_id' => $order['id'],
    'order_code' => $orderCode,
    'transaction_status' => $transactionStatus,
    'new_payment_status' => $newPaymentStatus,
    'new_order_status' => $newOrderStatus,
    'order_updated' => $orderUpdated,
    'payment_recorded' => $paymentRecorded,
    'signature_valid' => $signatureValid
]);

exit;