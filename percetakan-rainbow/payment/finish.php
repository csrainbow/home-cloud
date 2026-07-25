<?php
// 🔥 DEBUG
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../config.php';

// 🔥 CEK SESSION - CUSTOMER HARUS LOGIN
if (!isset($_SESSION['customer_id'])) {
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
    header('Location: /login.php');
    exit;
}

$orderCode = $_GET['order'] ?? '';
$transactionId = $_GET['transaction_id'] ?? '';
$statusParam = $_GET['status'] ?? '';

if (empty($orderCode)) {
    header('Location: /index.php');
    exit;
}

// 🔥 CEK ORDER - PASTIKAN MILIK CUSTOMER YANG LOGIN
$stmt = $db->prepare("SELECT * FROM orders WHERE order_code = ? AND customer_id = ?");
$stmt->execute([$orderCode, $_SESSION['customer_id']]);
$order = $stmt->fetch();

if (!$order) {
    $_SESSION['error'] = "Pesanan tidak ditemukan!";
    header('Location: /customer/dashboard.php');
    exit;
}

// 🔥 HITUNG TOTAL PEMBAYARAN YANG SUDAH TERVERIFIKASI
$totalPaidStmt = $db->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM payments WHERE order_id=? AND status IN ('verified','approved','paid')");
$totalPaidStmt->execute([$order['id']]);
$totalPaid = floatval($totalPaidStmt->fetch()['total']);
$sisaPembayaran = $order['total'] - $totalPaid;
$persentaseDibayar = $order['total'] > 0 ? round(($totalPaid / $order['total']) * 100) : 0;

// 🔥 CEK JASA DESAIN
$stmt = $db->prepare("SELECT COUNT(*) as c FROM order_items WHERE order_id=? AND design_service='jasa'");
$stmt->execute([$order['id']]);
$hasJasa = $stmt->fetch()['c'] > 0;

$serverKey = getSetting('midtrans_server_key');
$transactionStatus = null;
$paymentMethod = null;
$midtransError = null;
$isManualCheck = false;

// 🔥 🔥 CEK STATUS MIDTRANS 🔥 🔥
if ($serverKey && $orderCode) {
    $isSandbox = strpos($serverKey, 'SB-') === 0;
    $baseUrl = $isSandbox ? 'https://api.sandbox.midtrans.com' : 'https://api.midtrans.com';

    try {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $baseUrl . '/v2/' . urlencode($orderCode) . '/status');
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json']);
        curl_setopt($ch, CURLOPT_USERPWD, $serverKey . ':');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            $midtransError = "CURL Error: " . $curlError;
        } elseif ($httpCode === 200) {
            $result = json_decode($response, true);
            $transactionStatus = $result['transaction_status'] ?? '';
            $paymentMethod = $result['payment_type'] ?? '';
            $grossAmount = $result['gross_amount'] ?? 0;
            
            logMidtrans("Midtrans status check: $transactionStatus for order $orderCode");
            
            // 🔥 🔥 UPDATE STATUS BERDASARKAN RESPONSE MIDTRANS 🔥 🔥
            if (in_array($transactionStatus, ['capture', 'settlement'])) {
                // 🔥 CEK TOTAL PEMBAYARAN
                $newTotalPaid = $totalPaid + floatval($grossAmount);
                
                // 🔥 TENTUKAN STATUS PEMBAYARAN
                if ($newTotalPaid >= $order['total']) {
                    // ✅ LUNAS
                    $newPaymentStatus = 'paid';
                    $newOrderStatus = $hasJasa ? 'desain' : 'processed';
                    $_SESSION['success'] = "✅ Pembayaran LUNAS berhasil! Pesanan Anda akan segera diproses.";
                } else {
                    // 💰 DP
                    $newPaymentStatus = 'dp';
                    $newOrderStatus = $hasJasa ? 'desain' : 'processed';
                    $_SESSION['success'] = "💰 DP berhasil dibayar! Sisa pembayaran: " . formatRupiah($order['total'] - $newTotalPaid);
                }
                
                // 🔥 UPDATE ORDER
                $db->prepare("UPDATE orders SET payment_status=?, status=? WHERE id=?")->execute([
                    $newPaymentStatus,
                    $newOrderStatus,
                    $order['id']
                ]);
                
                // 🔥 SIMPAN KE TABEL PAYMENTS
                $checkPayment = $db->prepare("SELECT id FROM payments WHERE order_id=? AND payment_type='midtrans' AND amount=?");
                $checkPayment->execute([$order['id'], $grossAmount]);
                if (!$checkPayment->fetch()) {
                    $stmt = $db->prepare("INSERT INTO payments (order_id, amount, bank_name, account_number, account_name, proof_image, payment_type, status, created_at) 
                                           VALUES (?, ?, 'Midtrans', 'Online', 'Midtrans', '', 'midtrans', 'approved', NOW())");
                    $stmt->execute([
                        $order['id'],
                        $grossAmount,
                        $paymentMethod ?: 'midtrans'
                    ]);
                }
                
                $isManualCheck = true;
                
            } elseif ($transactionStatus === 'pending') {
                // ⏳ STATUS PENDING
                $db->prepare("UPDATE orders SET payment_status='pending_verification' WHERE id=?")->execute([$order['id']]);
                $_SESSION['info'] = "⏳ Pembayaran sedang menunggu konfirmasi. Silakan selesaikan pembayaran Anda.";
                $isManualCheck = true;
                
            } elseif (in_array($transactionStatus, ['deny', 'cancel', 'expire'])) {
                // ❌ STATUS GAGAL
                // 🔥 Cek apakah sudah pernah bayar sebelumnya
                if ($totalPaid > 0) {
                    $db->prepare("UPDATE orders SET payment_status='dp' WHERE id=?")->execute([$order['id']]);
                    $_SESSION['warning'] = "⚠️ Pembayaran baru gagal, tapi DP Anda tetap berlaku.";
                } else {
                    $db->prepare("UPDATE orders SET payment_status='unpaid', status='failed' WHERE id=?")->execute([$order['id']]);
                    $_SESSION['error'] = "❌ Pembayaran gagal. Status: " . $transactionStatus . ". Silakan coba lagi.";
                }
                $isManualCheck = true;
            }
            
        } elseif ($httpCode === 404) {
            $midtransError = "Order tidak ditemukan di Midtrans";
        } else {
            $midtransError = "Midtrans API Error (HTTP $httpCode)";
        }
    } catch (Exception $e) {
        $midtransError = "Exception: " . $e->getMessage();
        logMidtrans("Error checking Midtrans: " . $e->getMessage());
    }
}

// 🔥 🔥 JIKA STATUS DARI PARAMETER (REDIRECT DARI MIDTRANS) 🔥 🔥
if (!$isManualCheck && $statusParam) {
    if ($statusParam === 'pending') {
        $db->prepare("UPDATE orders SET payment_status='pending_verification' WHERE id=?")->execute([$order['id']]);
        $_SESSION['info'] = "⏳ Pembayaran sedang diproses. Tunggu konfirmasi dari Midtrans.";
    } elseif ($statusParam === 'error') {
        if ($totalPaid > 0) {
            $db->prepare("UPDATE orders SET payment_status='dp' WHERE id=?")->execute([$order['id']]);
            $_SESSION['warning'] = "⚠️ Ada masalah dengan pembayaran, tapi DP Anda tetap berlaku.";
        } else {
            $_SESSION['error'] = "❌ Terjadi kesalahan dalam pembayaran. Silakan coba lagi.";
        }
    }
}

// 🔥 REFRESH DATA ORDER
$stmt = $db->prepare("SELECT * FROM orders WHERE id = ?");
$stmt->execute([$order['id']]);
$order = $stmt->fetch();

$pageTitle = 'Status Pembayaran - ' . $order['order_code'];
include '../includes/header.php';

// 🔥 🔥 FUNGSI LOG 🔥 🔥
if (!function_exists('logMidtrans')) {
    function logMidtrans($message) {
        $logFile = __DIR__ . '/../logs/midtrans.log';
        if (!is_dir(__DIR__ . '/../logs')) {
            mkdir(__DIR__ . '/../logs', 0755, true);
        }
        $timestamp = date('Y-m-d H:i:s');
        file_put_contents($logFile, "[$timestamp] $message" . PHP_EOL, FILE_APPEND);
    }
}
?>

<style>
.order-success {
    max-width: 600px;
    margin: 40px auto;
    text-align: center;
    background: #fff;
    padding: 40px 30px;
    border-radius: 12px;
    box-shadow: 0 2px 20px rgba(0,0,0,0.08);
}
.success-icon {
    font-size: 64px;
    margin-bottom: 20px;
}
.order-success h1 {
    font-size: 28px;
    color: #2c3e50;
    margin-bottom: 10px;
}
.order-success .subtitle {
    color: #6c757d;
    font-size: 16px;
    margin-bottom: 25px;
    line-height: 1.6;
}
.order-detail-card {
    background: #f8f9fa;
    padding: 20px;
    border-radius: 8px;
    text-align: left;
    margin: 15px 0;
}
.order-detail-card p {
    margin: 8px 0;
}
.order-detail-card strong {
    color: #2c3e50;
}
.status-badge {
    display: inline-block;
    padding: 4px 14px;
    border-radius: 20px;
    font-size: 13px;
    font-weight: 600;
}
.status-paid { background: #27ae60; color: #fff; }
.status-dp { background: #f39c12; color: #fff; }
.status-pending_verification { background: #f39c12; color: #fff; }
.status-unpaid { background: #95a5a6; color: #fff; }
.status-failed { background: #e74c3c; color: #fff; }
.status-desain { background: #8e44ad; color: #fff; }
.status-processed { background: #3498db; color: #fff; }
.status-done { background: #27ae60; color: #fff; }

.payment-summary {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 10px;
    margin: 15px 0;
}
.payment-summary .item {
    background: #f8f9fa;
    padding: 12px;
    border-radius: 6px;
}
.payment-summary .item .label {
    font-size: 11px;
    color: #6c757d;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.payment-summary .item .value {
    font-size: 16px;
    font-weight: bold;
    color: #2c3e50;
}
.btn-group {
    display: flex;
    gap: 10px;
    justify-content: center;
    flex-wrap: wrap;
    margin-top: 20px;
}
.btn {
    display: inline-block;
    padding: 10px 24px;
    border-radius: 6px;
    font-size: 14px;
    text-decoration: none;
    border: none;
    cursor: pointer;
    transition: all 0.3s;
}
.btn-primary {
    background: #2c3e50;
    color: #fff;
}
.btn-primary:hover {
    background: #1a252f;
}
.btn-outline {
    background: #fff;
    color: #2c3e50;
    border: 1px solid #2c3e50;
}
.btn-outline:hover {
    background: #f8f9fa;
}
.btn-success {
    background: #27ae60;
    color: #fff;
}
.btn-success:hover {
    background: #1e8449;
}
.btn-warning {
    background: #f39c12;
    color: #fff;
}
.btn-warning:hover {
    background: #d68910;
}
.btn-danger {
    background: #e74c3c;
    color: #fff;
}
.btn-danger:hover {
    background: #c0392b;
}
.midtrans-error {
    background: #fef9e7;
    border: 1px solid #f39c12;
    padding: 12px 16px;
    border-radius: 6px;
    color: #856404;
    font-size: 13px;
    margin: 10px 0;
    text-align: left;
}
.progress-bar-container {
    margin-top: 12px;
    background: #ecf0f1;
    border-radius: 10px;
    height: 8px;
    overflow: hidden;
}
.progress-bar-fill {
    height: 100%;
    border-radius: 10px;
    background: linear-gradient(90deg, #f39c12, #27ae60);
    transition: width 0.5s ease;
}
.progress-label {
    display: flex;
    justify-content: space-between;
    font-size: 11px;
    color: #6c757d;
    margin-top: 4px;
}

/* 🔥 Responsive */
@media (max-width: 480px) {
    .order-success {
        padding: 25px 15px;
        margin: 20px 10px;
    }
    .payment-summary {
        grid-template-columns: 1fr;
    }
    .success-icon {
        font-size: 48px;
    }
    .order-success h1 {
        font-size: 22px;
    }
    .btn-group .btn {
        width: 100%;
        text-align: center;
    }
}
</style>

<div class="order-success">
    <!-- 🔥 ICON STATUS -->
    <div class="success-icon">
        <?php if ($order['payment_status'] === 'paid'): ?>
            ✅
        <?php elseif ($order['payment_status'] === 'dp'): ?>
            💰
        <?php elseif ($order['payment_status'] === 'pending_verification'): ?>
            ⏳
        <?php elseif ($order['status'] === 'failed'): ?>
            ❌
        <?php else: ?>
            🏦
        <?php endif; ?>
    </div>

    <!-- 🔥 JUDUL -->
    <h1>
        <?php if ($order['payment_status'] === 'paid'): ?>
            ✅ Pembayaran Berhasil!
        <?php elseif ($order['payment_status'] === 'dp'): ?>
            💰 DP Berhasil Dibayar!
        <?php elseif ($order['status'] === 'failed'): ?>
            ❌ Pembayaran Gagal
        <?php elseif ($order['payment_status'] === 'pending_verification'): ?>
            ⏳ Menunggu Konfirmasi
        <?php else: ?>
            📋 Status Pembayaran
        <?php endif; ?>
    </h1>

    <!-- 🔥 SUBTITLE -->
    <p class="subtitle">
        <?php if ($order['payment_status'] === 'paid'): ?>
            Terima kasih! Pembayaran Anda telah kami terima. Pesanan akan segera diproses.
        <?php elseif ($order['payment_status'] === 'dp'): ?>
            DP berhasil dibayar! Silakan lunasi sisa pembayaran sebesar <strong><?= formatRupiah($sisaPembayaran) ?></strong>.
        <?php elseif ($order['status'] === 'failed'): ?>
            Maaf, pembayaran gagal. Silakan coba lagi atau hubungi admin.
        <?php elseif ($order['payment_status'] === 'pending_verification'): ?>
            Kami sedang memverifikasi pembayaran Anda. Proses ini memakan waktu maksimal 1x24 jam.
        <?php else: ?>
            Silakan selesaikan pembayaran Anda melalui Midtrans.
        <?php endif; ?>
    </p>

    <!-- 🔥 ERROR MIDTRANS -->
    <?php if ($midtransError): ?>
        <div class="midtrans-error">
            <strong>⚠️ Informasi Midtrans:</strong> <?= htmlspecialchars($midtransError) ?>
            <br><small>Jika pembayaran sudah berhasil, abaikan pesan ini.</small>
        </div>
    <?php endif; ?>

    <!-- 🔥 RINGKASAN PEMBAYARAN -->
    <div class="payment-summary">
        <div class="item">
            <div class="label">Total Pesanan</div>
            <div class="value"><?= formatRupiah($order['total']) ?></div>
        </div>
        <div class="item">
            <div class="label">Sudah Dibayar</div>
            <div class="value" style="color:#27ae60;"><?= formatRupiah($totalPaid) ?></div>
        </div>
        <div class="item">
            <div class="label">Sisa</div>
            <div class="value" style="color:<?= $sisaPembayaran > 0 ? '#e74c3c' : '#27ae60' ?>;">
                <?= $sisaPembayaran > 0 ? formatRupiah($sisaPembayaran) : '✅ LUNAS' ?>
            </div>
        </div>
    </div>

    <!-- 🔥 PROGRESS BAR -->
    <?php if ($order['total'] > 0): ?>
        <div class="progress-bar-container">
            <div class="progress-bar-fill" style="width: <?= $persentaseDibayar ?>%;"></div>
        </div>
        <div class="progress-label">
            <span><?= $persentaseDibayar ?>% dibayar</span>
            <span><?= $sisaPembayaran > 0 ? 'Sisa ' . formatRupiah($sisaPembayaran) : '✅ Lunas' ?></span>
        </div>
    <?php endif; ?>

    <!-- 🔥 DETAIL PESANAN -->
    <div class="order-detail-card">
        <p><strong>Kode Pesanan:</strong> <?= htmlspecialchars($order['order_code']) ?></p>
        <p><strong>Total:</strong> <?= formatRupiah($order['total']) ?></p>
        <p><strong>Metode:</strong> <?= htmlspecialchars($paymentMethod ?: 'Midtrans') ?></p>
        <p><strong>Status Pesanan:</strong>
            <span class="status-badge status-<?= $order['status'] ?>">
                <?= ucfirst($order['status']) ?>
            </span>
        </p>
        <p><strong>Status Pembayaran:</strong>
            <span class="status-badge status-<?= $order['payment_status'] ?>">
                <?php
                $pl = [
                    'unpaid' => 'Belum Dibayar',
                    'pending_verification' => 'Menunggu Verifikasi',
                    'paid' => '✅ Lunas',
                    'dp' => '💰 DP'
                ];
                echo $pl[$order['payment_status']] ?? ucfirst($order['payment_status']);
                ?>
            </span>
        </p>
        <?php if ($order['payment_status'] === 'dp' && $sisaPembayaran > 0): ?>
            <p style="color:#f39c12;font-weight:bold;margin-top:5px;">
                💰 Sisa pembayaran: <?= formatRupiah($sisaPembayaran) ?>
            </p>
        <?php endif; ?>
        <?php if ($transactionId): ?>
            <p style="font-size:12px;color:#999;margin-top:5px;">
                <strong>Transaction ID:</strong> <?= htmlspecialchars($transactionId) ?>
            </p>
        <?php endif; ?>
    </div>

    <!-- 🔥 TOMBOL AKSI -->
    <div class="btn-group">
        <?php if ($order['payment_status'] === 'dp' && $sisaPembayaran > 0): ?>
            <a href="/payment/confirm.php?order=<?= urlencode($orderCode) ?>" class="btn btn-warning">
                💰 Bayar Sisa (<?= formatRupiah($sisaPembayaran) ?>)
            </a>
        <?php endif; ?>

        <?php if ($order['status'] === 'failed'): ?>
            <a href="/payment/confirm.php?order=<?= urlencode($orderCode) ?>" class="btn btn-danger">
                🔄 Coba Bayar Lagi
            </a>
        <?php endif; ?>

        <?php if ($order['payment_status'] === 'paid'): ?>
            <a href="/invoice.php?order=<?= urlencode($orderCode) ?>" target="_blank" class="btn btn-primary">
                🧾 Lihat Invoice
            </a>
        <?php endif; ?>

        <?php if ($order['payment_status'] === 'pending_verification'): ?>
            <a href="/customer/order-detail.php?order=<?= urlencode($orderCode) ?>" class="btn btn-outline">
                📋 Cek Status
            </a>
        <?php endif; ?>

        <?php
        $whatsappNumber = getSetting('whatsapp_number') ?: WHATSAPP_NUMBER ?? '6281234567890';
        ?>
        <a href="https://wa.me/<?= $whatsappNumber ?>?text=Halo%20saya%20ingin%20konfirmasi%20pesanan%20<?= urlencode($order['order_code']) ?>" target="_blank" class="btn btn-success">
            📱 Konfirmasi via WA
        </a>

        <a href="/customer/order-detail.php?order=<?= urlencode($orderCode) ?>" class="btn btn-outline">
            📋 Detail Pesanan
        </a>

        <a href="/products.php" class="btn btn-outline">
            🛍️ Belanja Lagi
        </a>
    </div>
</div>

<?php include '../includes/footer.php'; ?>