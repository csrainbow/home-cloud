<?php
// 🔥 Mulai session jika belum
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config.php';

// 🔥 Cek login dengan lebih baik
if (!isset($_SESSION['customer_id']) || empty($_SESSION['customer_id'])) {
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
    header('Location: /login.php');
    exit;
}

$orderCode = $_GET['order'] ?? '';
if (empty($orderCode)) {
    header('Location: /customer/dashboard.php');
    exit;
}

$stmt = $db->prepare("SELECT * FROM orders WHERE order_code = ? AND customer_id = ?");
$stmt->execute([$orderCode, $_SESSION['customer_id']]);
$order = $stmt->fetch();

if (!$order) {
    $_SESSION['error'] = "Pesanan tidak ditemukan!";
    header('Location: /customer/dashboard.php');
    exit;
}

$items = $db->prepare("SELECT * FROM order_items WHERE order_id=?");
$items->execute([$order['id']]);
$items = $items->fetchAll();

// 🔥 AMBIL RIWAYAT PEMBAYARAN
$payments = $db->prepare("SELECT * FROM payments WHERE order_id=? ORDER BY created_at DESC");
$payments->execute([$order['id']]);
$payments = $payments->fetchAll();

// 🔥 HITUNG TOTAL PEMBAYARAN
$totalPaidStmt = $db->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM payments WHERE order_id=? AND status IN ('verified','approved','paid')");
$totalPaidStmt->execute([$order['id']]);
$totalPaid = floatval($totalPaidStmt->fetch()['total']);
$sisaPembayaran = $order['total'] - $totalPaid;
$persentaseDibayar = $order['total'] > 0 ? round(($totalPaid / $order['total']) * 100) : 0;

// 🔥 CEK APAKAH ADA JASA DESAIN
$hasDesignService = false;
$hasDesignResult = false;
foreach ($items as $item) {
    if ($item['design_service'] === 'jasa') $hasDesignService = true;
    if ($item['design_result_file']) $hasDesignResult = true;
}

$pageTitle = 'Detail Pesanan - ' . $order['order_code'];
include '../includes/header.php';
?>

<style>
/* 🔥 PAYMENT SUMMARY */
.payment-summary {
    background: #f8f9fa;
    padding: 20px;
    border-radius: 10px;
    margin-bottom: 20px;
    border: 1px solid #e9ecef;
}
.payment-summary-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
    gap: 15px;
}
.payment-summary-item {
    text-align: center;
}
.payment-summary-item .label {
    font-size: 11px;
    color: #6c757d;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.payment-summary-item .value {
    font-size: 20px;
    font-weight: bold;
    margin-top: 4px;
}
.payment-summary-item .value.text-success { color: #27ae60; }
.payment-summary-item .value.text-danger { color: #e74c3c; }
.payment-summary-item .value.text-warning { color: #f39c12; }
.payment-summary-item .value.text-primary { color: #2c3e50; }

.progress-bar-container {
    margin-top: 12px;
    background: #ecf0f1;
    border-radius: 10px;
    height: 10px;
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
    font-size: 12px;
    color: #6c757d;
    margin-top: 4px;
}

/* 🔥 STATUS BADGE */
.status-badge {
    display: inline-block;
    padding: 4px 14px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
}
.status-pending { background: #f39c12; color: #fff; }
.status-desain { background: #8e44ad; color: #fff; }
.status-processed { background: #3498db; color: #fff; }
.status-printing { background: #2c3e50; color: #fff; }
.status-done { background: #27ae60; color: #fff; }
.status-cancelled { background: #e74c3c; color: #fff; }
.status-failed { background: #e74c3c; color: #fff; }
.status-unpaid { background: #95a5a6; color: #fff; }
.status-pending_verification { background: #f39c12; color: #fff; }
.status-paid { background: #27ae60; color: #fff; }
.status-dp { background: #f39c12; color: #fff; }
.status-verified { background: #27ae60; color: #fff; }
.status-rejected { background: #e74c3c; color: #fff; }

/* 🔥 ORDER DETAIL CARD */
.order-detail-card {
    margin-bottom: 20px;
    background: #fff;
    padding: 20px;
    border-radius: 10px;
    border: 1px solid #e9ecef;
}
.order-detail-card p {
    margin: 6px 0;
}

/* 🔥 TABLE */
.table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 20px;
    background: #fff;
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 1px 4px rgba(0,0,0,0.06);
}
.table thead {
    background: #f8f9fa;
    border-bottom: 2px solid #dee2e6;
}
.table th {
    padding: 10px 12px;
    text-align: left;
    font-size: 12px;
    text-transform: uppercase;
    color: #6c757d;
    font-weight: 600;
}
.table td {
    padding: 10px 12px;
    border-bottom: 1px solid #f1f3f5;
    font-size: 14px;
}
.table tbody tr:hover {
    background: #f8f9fa;
}
.table tfoot {
    background: #f8f9fa;
    font-weight: bold;
    border-top: 2px solid #dee2e6;
}

/* 🔥 PAYMENT HISTORY */
.payment-history {
    margin: 20px 0;
}
.payment-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px 15px;
    background: #fff;
    border-radius: 8px;
    border: 1px solid #e9ecef;
    margin-bottom: 8px;
    flex-wrap: wrap;
    gap: 8px;
}
.payment-item .payment-info {
    flex: 1;
}
.payment-item .payment-amount {
    font-weight: bold;
    font-size: 16px;
}
.payment-item .payment-status {
    font-size: 12px;
}
.payment-item .payment-date {
    font-size: 12px;
    color: #6c757d;
}
.payment-item .payment-type {
    font-size: 12px;
    padding: 2px 10px;
    border-radius: 12px;
    font-weight: 600;
}
.payment-type-dp {
    background: #f39c12;
    color: #fff;
}
.payment-type-pelunasan {
    background: #27ae60;
    color: #fff;
}

/* 🔥 BUTTONS */
.btn {
    display: inline-block;
    padding: 10px 20px;
    border-radius: 6px;
    font-size: 14px;
    cursor: pointer;
    text-decoration: none;
    border: none;
    transition: all 0.3s;
}
.btn-primary {
    background: #2c3e50;
    color: #fff;
}
.btn-primary:hover {
    background: #1a252f;
}
.btn-warning {
    background: #f39c12;
    color: #fff;
}
.btn-warning:hover {
    background: #d68910;
}
.btn-success {
    background: #27ae60;
    color: #fff;
}
.btn-success:hover {
    background: #1e8449;
}
.btn-outline {
    background: #fff;
    color: #2c3e50;
    border: 1px solid #2c3e50;
}
.btn-outline:hover {
    background: #f8f9fa;
}
.btn-sm {
    padding: 6px 14px;
    font-size: 12px;
}

/* 🔥 RESPONSIVE */
@media (max-width: 600px) {
    .payment-summary-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 10px;
    }
    .payment-summary-item .value {
        font-size: 17px;
    }
    .table {
        font-size: 12px;
    }
    .table th, .table td {
        padding: 6px 8px;
    }
    .payment-item {
        flex-direction: column;
        align-items: stretch;
        text-align: center;
    }
}
</style>

<h1>📋 Detail Pesanan</h1>

<!-- 🔥 ALERT MESSAGES -->
<?php if (isset($_SESSION['success'])): ?>
    <div style="background:#d4edda;color:#155724;padding:12px 15px;border-radius:6px;margin-bottom:15px;border:1px solid #c3e6cb;">
        <?= $_SESSION['success']; unset($_SESSION['success']); ?>
    </div>
<?php endif; ?>
<?php if (isset($_SESSION['error'])): ?>
    <div style="background:#f8d7da;color:#721c24;padding:12px 15px;border-radius:6px;margin-bottom:15px;border:1px solid #f5c6cb;">
        <?= $_SESSION['error']; unset($_SESSION['error']); ?>
    </div>
<?php endif; ?>

<!-- 🔥 RINGKASAN PEMBAYARAN -->
<div class="payment-summary">
    <div class="payment-summary-grid">
        <div class="payment-summary-item">
            <div class="label">Total Pesanan</div>
            <div class="value text-primary"><?= formatRupiah($order['total']) ?></div>
        </div>
        <div class="payment-summary-item">
            <div class="label">Sudah Dibayar</div>
            <div class="value text-success"><?= formatRupiah($totalPaid) ?></div>
        </div>
        <div class="payment-summary-item">
            <div class="label">Sisa Pembayaran</div>
            <div class="value <?= $sisaPembayaran > 0 ? 'text-danger' : 'text-success' ?>">
                <?= formatRupiah($sisaPembayaran) ?>
            </div>
        </div>
        <div class="payment-summary-item">
            <div class="label">Status Pembayaran</div>
            <div class="value <?= $sisaPembayaran > 0 ? 'text-warning' : 'text-success' ?>" style="font-size:18px;">
                <?php if ($sisaPembayaran > 0): ?>
                    <?php if ($totalPaid == 0): ?>
                        💰 Belum Bayar
                    <?php else: ?>
                        💰 DP (<?= $persentaseDibayar ?>%)
                    <?php endif; ?>
                <?php else: ?>
                    ✅ LUNAS
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Progress Bar -->
    <div class="progress-bar-container">
        <div class="progress-bar-fill" style="width: <?= $persentaseDibayar ?>%;"></div>
    </div>
    <div class="progress-label">
        <span><?= $persentaseDibayar ?>% dibayar</span>
        <span><?= $sisaPembayaran > 0 ? 'Sisa Rp ' . formatRupiah($sisaPembayaran) : '✅ Lunas' ?></span>
    </div>
</div>

<!-- 🔥 ORDER INFO -->
<div class="order-detail-card">
    <p><strong>Kode Pesanan:</strong> <?= htmlspecialchars($order['order_code']) ?></p>
    <p><strong>Tanggal:</strong> <?= date('d/m/Y H:i', strtotime($order['created_at'])) ?></p>
    <p><strong>Status Pesanan:</strong>
        <span class="status-badge status-<?= $order['status'] ?>"><?php
        $sl = ['pending'=>'Pending','desain'=>'Proses Desain','processed'=>'Diproses','printing'=>'Cetak','done'=>'Selesai','cancelled'=>'Dibatalkan'];
        echo $sl[$order['status']] ?? ucfirst($order['status']);
        ?></span>
    </p>
    <p><strong>Status Pembayaran:</strong>
        <span class="status-badge status-<?= $sisaPembayaran <= 0 ? 'paid' : $order['payment_status'] ?>"><?php
        if ($sisaPembayaran <= 0) {
            echo 'Lunas';
        } else {
            $pl = ['unpaid'=>'Belum Dibayar','pending_verification'=>'Menunggu Verifikasi','paid'=>'Lunas','dp'=>'DP'];
            echo $pl[$order['payment_status']] ?? $order['payment_status'];
        }
        ?></span>
    </p>
    <?php if ($order['payment_status'] === 'dp' && $sisaPembayaran > 0): ?>
        <p style="color:#f39c12;font-weight:bold;margin-top:5px;">
            💰 DP telah dibayar. Sisa pembayaran: <?= formatRupiah($sisaPembayaran) ?>
        </p>
    <?php endif; ?>
    <?php if ($order['payment_status'] === 'pending_verification'): ?>
        <p style="color:#f39c12;font-weight:bold;margin-top:5px;">
            ⏳ Bukti pembayaran sedang diverifikasi oleh admin.
        </p>
    <?php endif; ?>
</div>

<!-- 🔥 ITEM PESANAN -->
<h2 style="margin-top:20px;font-size:18px;color:#2c3e50;">📦 Item Pesanan</h2>
<table class="table">
    <thead>
        <tr>
            <th>Produk</th>
            <th>Bahan</th>
            <th>Ukuran</th>
            <th>Layanan Desain</th>
            <th style="text-align:center;">Jumlah</th>
            <th style="text-align:right;">Harga</th>
            <th style="text-align:right;">Subtotal</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($items as $item): ?>
        <tr>
            <td><?= htmlspecialchars($item['product_name']) ?></td>
            <td><?= htmlspecialchars($item['material_name']) ?: '-' ?></td>
            <td><?= ($item['width'] && $item['height']) ? intval($item['width']) . '×' . intval($item['height']) . ' cm' : '-' ?></td>
            <td>
                <?php if ($item['design_service'] === 'jasa'): ?>
                    <span style="display:inline-block;padding:3px 10px;background:#f39c12;color:#fff;border-radius:4px;font-size:12px;font-weight:bold;">Jasa Desain</span>
                <?php elseif ($item['design_service'] === 'upload'): ?>
                    <span style="display:inline-block;padding:3px 10px;background:#3498db;color:#fff;border-radius:4px;font-size:12px;">Upload File</span>
                <?php else: ?>
                    <span style="color:#999;font-size:12px;">-</span>
                <?php endif; ?>
                
                <?php if ($item['design_file']): ?>
                    <br><span style="font-size:11px;color:#3498db;margin-top:4px;display:inline-block;">
                        📎 <a href="/uploads/designs/<?= htmlspecialchars($item['design_file']) ?>" target="_blank" style="text-decoration:underline;">File Desain</a>
                    </span>
                <?php endif; ?>
                
                <?php if ($item['design_result_file']): ?>
                    <br><span style="font-size:11px;color:#27ae60;margin-top:4px;display:inline-block;">
                        ✅ <a href="/uploads/designs/<?= htmlspecialchars($item['design_result_file']) ?>" target="_blank" style="text-decoration:underline;">Download Hasil Desain</a>
                    </span>
                <?php endif; ?>
            </td>
            <td style="text-align:center;"><?= $item['quantity'] ?></td>
            <td style="text-align:right;"><?= formatRupiah($item['price']) ?></td>
            <td style="text-align:right;"><?= formatRupiah($item['subtotal']) ?></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
    <tfoot>
        <tr>
            <th colspan="5" style="text-align:right;">Total</th>
            <th colspan="2" style="text-align:right;"><?= formatRupiah($order['total']) ?></th>
        </tr>
    </tfoot>
</table>

<!-- 🔥 RIWAYAT PEMBAYARAN -->
<?php if (!empty($payments)): ?>
    <h2 style="margin-top:20px;font-size:18px;color:#2c3e50;">💰 Riwayat Pembayaran</h2>
    <div class="payment-history">
        <?php foreach ($payments as $p): 
            $isDp = ($p['payment_type'] === 'dp' && $p['amount'] < $order['total']);
            $typeLabel = $isDp ? 'DP' : ($p['payment_type'] === 'pelunasan' ? 'Pelunasan' : 'Lunas');
            $typeClass = $isDp ? 'payment-type-dp' : 'payment-type-pelunasan';
        ?>
        <div class="payment-item">
            <div class="payment-info">
                <span class="payment-type <?= $typeClass ?>"><?= $typeLabel ?></span>
                <span style="margin-left:8px;font-size:13px;">
                    <?= htmlspecialchars($p['bank_name']) ?> - <?= htmlspecialchars($p['account_number']) ?>
                </span>
            </div>
            <div class="payment-amount"><?= formatRupiah($p['amount']) ?></div>
            <div>
                <span class="status-badge status-<?= $p['status'] ?>"><?= ucfirst($p['status']) ?></span>
            </div>
            <div class="payment-date"><?= date('d/m/Y H:i', strtotime($p['created_at'])) ?></div>
        </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<!-- 🔥 STATUS DESAIN -->
<?php if ($order['status'] === 'desain'): ?>
    <div style="margin-top:15px;padding:15px;background:#e8daef;border-radius:8px;color:#6c3483;">
        <strong>⏳ Proses Desain</strong>
        <p style="margin-top:5px;font-size:14px;">Pesanan sedang dalam proses desain oleh tim kami. Hasil desain akan tampil di sini setelah selesai.</p>
    </div>
<?php endif; ?>

<!-- 🔥 TOMBOL AKSI -->
<div style="margin-top:20px;display:flex;gap:10px;flex-wrap:wrap;">
    <?php if ($order['payment_status'] === 'unpaid'): ?>
        <a href="/payment/confirm.php?order=<?= urlencode($order['order_code']) ?>" class="btn btn-primary">
            💳 Lanjutkan Pembayaran
        </a>
    <?php endif; ?>
    
    <?php if ($order['payment_status'] === 'dp' && $sisaPembayaran > 0): ?>
        <a href="/payment/confirm.php?order=<?= urlencode($order['order_code']) ?>" class="btn btn-warning">
            💰 Bayar Sisa (<?= formatRupiah($sisaPembayaran) ?>)
        </a>
    <?php endif; ?>
    
    <?php if ($sisaPembayaran <= 0 || $order['payment_status'] === 'paid' || $order['status'] === 'done'): ?>
        <a href="/invoice.php?order=<?= urlencode($order['order_code']) ?>" target="_blank" class="btn btn-primary">
            🧾 Lihat Invoice
        </a>
    <?php endif; ?>
    
    <?php if ($hasDesignResult): ?>
        <a href="#design-result" class="btn btn-success">
            🎨 Download Hasil Desain
        </a>
    <?php endif; ?>
    
    <a href="/customer/dashboard.php" class="btn btn-outline">← Kembali ke Dashboard</a>
</div>

<?php include '../includes/footer.php'; ?>