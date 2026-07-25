<?php
require_once __DIR__ . '/config.php';

$pageTitle = 'Cek Pesanan';
$result = null;
$error = '';
$orderItems = [];

// 🔥 🔥 PROSES FORM 🔥 🔥
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = trim($_POST['order_code'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    
    if ($code && $phone) {
        $stmt = $db->prepare("SELECT * FROM orders WHERE order_code = ? AND customer_phone = ?");
        $stmt->execute([$code, $phone]);
        $result = $stmt->fetch();
        
        if (!$result) {
            $error = '❌ Pesanan tidak ditemukan. Periksa kode dan nomor WhatsApp.';
        } else {
            // 🔥 AMBIL ITEMS
            $items = $db->prepare("SELECT * FROM order_items WHERE order_id=?");
            $items->execute([$result['id']]);
            $orderItems = $items->fetchAll();
            
            // 🔥 HITUNG TOTAL PEMBAYARAN
            $totalPaidStmt = $db->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM payments WHERE order_id=? AND status IN ('verified','approved','paid')");
            $totalPaidStmt->execute([$result['id']]);
            $totalPaid = floatval($totalPaidStmt->fetch()['total']);
            $sisaPembayaran = $result['total'] - $totalPaid;
            $persentaseDibayar = $result['total'] > 0 ? round(($totalPaid / $result['total']) * 100) : 0;
            
            // 🔥 SIMPAN KE SESSION UNTUK LAST ORDER
            $_SESSION['last_order_code'] = $result['order_code'];
            $_SESSION['last_order_phone'] = $phone;
        }
    } else {
        $error = '❌ Isi kode pesanan dan nomor WhatsApp.';
    }
}

// 🔥 🔥 CEK LAST ORDER DARI SESSION 🔥 🔥
if (!$result && isset($_SESSION['last_order_code']) && isset($_SESSION['last_order_phone'])) {
    $stmt = $db->prepare("SELECT * FROM orders WHERE order_code = ? AND customer_phone = ?");
    $stmt->execute([$_SESSION['last_order_code'], $_SESSION['last_order_phone']]);
    $result = $stmt->fetch();
    if ($result) {
        $items = $db->prepare("SELECT * FROM order_items WHERE order_id=?");
        $items->execute([$result['id']]);
        $orderItems = $items->fetchAll();
        
        $totalPaidStmt = $db->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM payments WHERE order_id=? AND status IN ('verified','approved','paid')");
        $totalPaidStmt->execute([$result['id']]);
        $totalPaid = floatval($totalPaidStmt->fetch()['total']);
        $sisaPembayaran = $result['total'] - $totalPaid;
        $persentaseDibayar = $result['total'] > 0 ? round(($totalPaid / $result['total']) * 100) : 0;
    }
}

include 'includes/header.php';
?>

<style>
/* ============================================
   CEK PESANAN STYLES
   ============================================ */
.cek-container {
    max-width: 800px;
    margin: 0 auto;
}

.cek-container h1 {
    font-size: 24px;
    color: #2c3e50;
    margin-bottom: 10px;
}

.cek-container .subtitle {
    color: #6c757d;
    font-size: 14px;
    margin-bottom: 20px;
}

/* 🔥 FORM */
.cek-form {
    background: #fff;
    padding: 25px 30px;
    border-radius: 10px;
    box-shadow: 0 1px 4px rgba(0,0,0,0.06);
    border: 1px solid #e9ecef;
    max-width: 450px;
}
.cek-form .form-group {
    margin-bottom: 15px;
}
.cek-form .form-group label {
    display: block;
    font-weight: 600;
    font-size: 14px;
    color: #2c3e50;
    margin-bottom: 5px;
}
.cek-form .form-group input {
    width: 100%;
    padding: 10px 12px;
    border: 1px solid #ddd;
    border-radius: 6px;
    font-size: 14px;
    transition: border-color 0.3s;
}
.cek-form .form-group input:focus {
    border-color: #f39c12;
    outline: none;
    box-shadow: 0 0 0 3px rgba(243,156,18,0.15);
}
.cek-form .helper-text {
    font-size: 12px;
    color: #6c757d;
    margin-top: 4px;
}

/* 🔥 LAST ORDER BOX */
.last-order-box {
    margin-bottom: 20px;
    padding: 15px 20px;
    background: #e8f5e9;
    border-radius: 8px;
    border-left: 4px solid #27ae60;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 10px;
}
.last-order-box p {
    margin: 0;
    font-weight: 600;
    color: #1e8449;
}
.last-order-box .order-code {
    font-size: 14px;
    color: #2c3e50;
    font-weight: normal;
}

/* 🔥 ORDER DETAIL */
.order-detail-card {
    background: #fff;
    padding: 20px 25px;
    border-radius: 10px;
    box-shadow: 0 1px 4px rgba(0,0,0,0.06);
    border: 1px solid #e9ecef;
    margin-bottom: 20px;
}
.order-detail-card p {
    margin: 6px 0;
    font-size: 14px;
}
.order-detail-card strong {
    color: #2c3e50;
}

/* 🔥 PAYMENT SUMMARY */
.payment-summary {
    background: #f8f9fa;
    padding: 15px 20px;
    border-radius: 8px;
    margin-bottom: 15px;
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
    gap: 10px;
    border-left: 4px solid #f39c12;
}
.payment-summary .item {
    text-align: center;
}
.payment-summary .item .label {
    font-size: 11px;
    color: #6c757d;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.payment-summary .item .value {
    font-size: 18px;
    font-weight: bold;
    color: #2c3e50;
}
.payment-summary .item .value.success { color: #27ae60; }
.payment-summary .item .value.danger { color: #e74c3c; }
.payment-summary .item .value.warning { color: #f39c12; }

.progress-bar-container {
    margin-top: 10px;
    background: #ecf0f1;
    border-radius: 10px;
    height: 6px;
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
    margin-top: 3px;
}

/* 🔥 BUTTONS */
.btn-group {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    margin-top: 15px;
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
.btn-outline {
    background: #fff;
    color: #2c3e50;
    border: 1px solid #2c3e50;
}
.btn-outline:hover {
    background: #f8f9fa;
}
.btn-lg {
    padding: 12px 30px;
    font-size: 16px;
    width: 100%;
    text-align: center;
}

/* 🔥 RESPONSIVE */
@media (max-width: 480px) {
    .cek-form {
        padding: 20px;
    }
    .payment-summary {
        grid-template-columns: repeat(2, 1fr);
    }
    .last-order-box {
        flex-direction: column;
        text-align: center;
    }
    .btn-group .btn {
        width: 100%;
        text-align: center;
    }
}
</style>

<div class="cek-container">
    <h1>🔍 Cek Pesanan</h1>
    <p class="subtitle">Masukkan kode pesanan dan nomor WhatsApp untuk melihat status pesanan Anda.</p>

    <!-- 🔥 ALERT -->
    <?php if ($error): ?>
        <div class="alert alert-danger" style="background:#f8d7da;color:#721c24;padding:12px 16px;border-radius:6px;margin-bottom:15px;border:1px solid #f5c6cb;">
            <i class="fas fa-exclamation-circle"></i> <?= $error ?>
        </div>
    <?php endif; ?>

    <!-- 🔥 LAST ORDER BOX -->
    <?php if ($result && isset($_SESSION['last_order_code'])): ?>
        <div class="last-order-box">
            <div>
                <p><i class="fas fa-clock"></i> Pesanan Terakhir</p>
                <span class="order-code"><?= htmlspecialchars($_SESSION['last_order_code']) ?></span>
            </div>
            <button onclick="restoreLastOrder()" class="btn btn-primary btn-sm">
                <i class="fas fa-redo"></i> Refresh
            </button>
        </div>
    <?php endif; ?>

    <!-- 🔥 FORM -->
    <?php if (!$result): ?>
    <form method="POST" class="cek-form">
        <div class="form-group">
            <label for="order_code">Kode Pesanan *</label>
            <input type="text" name="order_code" id="order_code" required 
                   placeholder="Contoh: INV/20260719/XXXXXX"
                   value="<?= htmlspecialchars($_POST['order_code'] ?? $_SESSION['last_order_code'] ?? '') ?>">
            <div class="helper-text">Cek di email konfirmasi atau WhatsApp</div>
        </div>
        <div class="form-group">
            <label for="phone">Nomor WhatsApp *</label>
            <input type="tel" name="phone" id="phone" required 
                   placeholder="08xxxxxxxxxx"
                   value="<?= htmlspecialchars($_POST['phone'] ?? $_SESSION['last_order_phone'] ?? '') ?>">
            <div class="helper-text">Nomor yang didaftarkan saat order</div>
        </div>
        <button type="submit" class="btn btn-primary" style="width:100%;">
            <i class="fas fa-search"></i> Cek Pesanan
        </button>
    </form>
    <?php endif; ?>

    <!-- 🔥 🔥 HASIL PESANAN 🔥 🔥 -->
    <?php if ($result): ?>
        <?php
        // 🔥 HITUNG ULANG PEMBAYARAN
        $totalPaidStmt = $db->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM payments WHERE order_id=? AND status IN ('verified','approved','paid')");
        $totalPaidStmt->execute([$result['id']]);
        $totalPaid = floatval($totalPaidStmt->fetch()['total']);
        $sisaPembayaran = $result['total'] - $totalPaid;
        $persentaseDibayar = $result['total'] > 0 ? round(($totalPaid / $result['total']) * 100) : 0;
        ?>

        <!-- 🔥 PAYMENT SUMMARY -->
        <div class="payment-summary">
            <div class="item">
                <div class="label">Total Pesanan</div>
                <div class="value"><?= formatRupiah($result['total']) ?></div>
            </div>
            <div class="item">
                <div class="label">Sudah Dibayar</div>
                <div class="value success"><?= formatRupiah($totalPaid) ?></div>
            </div>
            <div class="item">
                <div class="label">Sisa</div>
                <div class="value <?= $sisaPembayaran > 0 ? 'danger' : 'success' ?>">
                    <?= $sisaPembayaran > 0 ? formatRupiah($sisaPembayaran) : '✅ LUNAS' ?>
                </div>
            </div>
            <div class="item">
                <div class="label">Status</div>
                <div class="value warning" style="font-size:14px;">
                    <?php if ($sisaPembayaran > 0): ?>
                        <?= $totalPaid == 0 ? '💰 Belum Bayar' : '💰 DP (' . $persentaseDibayar . '%)' ?>
                    <?php else: ?>
                        ✅ LUNAS
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- 🔥 PROGRESS BAR -->
        <?php if ($result['total'] > 0): ?>
            <div class="progress-bar-container">
                <div class="progress-bar-fill" style="width: <?= $persentaseDibayar ?>%;"></div>
            </div>
            <div class="progress-label">
                <span><?= $persentaseDibayar ?>% dibayar</span>
                <span><?= $sisaPembayaran > 0 ? 'Sisa ' . formatRupiah($sisaPembayaran) : '✅ Lunas' ?></span>
            </div>
        <?php endif; ?>

        <!-- 🔥 ORDER DETAIL -->
        <div class="order-detail-card">
            <p><strong>Kode Pesanan:</strong> <?= htmlspecialchars($result['order_code']) ?></p>
            <p><strong>Nama:</strong> <?= htmlspecialchars($result['customer_name']) ?></p>
            <p><strong>Status Pesanan:</strong>
                <span class="status-badge status-<?= $result['status'] ?>">
                    <?php
                    $sl = ['pending'=>'⏳ Pending','desain'=>'🎨 Proses Desain','processed'=>'⚙️ Diproses','printing'=>'🖨️ Cetak','done'=>'✅ Selesai','cancelled'=>'❌ Dibatalkan'];
                    echo $sl[$result['status']] ?? ucfirst($result['status']);
                    ?>
                </span>
            </p>
            <p><strong>Status Pembayaran:</strong>
                <span class="status-badge status-<?= $result['payment_status'] ?>">
                    <?php
                    $pl = ['unpaid'=>'💰 Belum Dibayar','pending_verification'=>'⏳ Menunggu Verifikasi','paid'=>'✅ Lunas','dp'=>'💰 DP'];
                    echo $pl[$result['payment_status']] ?? ucfirst($result['payment_status']);
                    ?>
                </span>
            </p>
            <p><strong>Metode:</strong> <?= ucfirst($result['payment_method'] ?? 'Transfer') ?></p>
            <p><strong>Tanggal:</strong> <?= date('d/m/Y H:i', strtotime($result['created_at'])) ?></p>
        </div>

        <!-- 🔥 ITEMS -->
        <h2 style="font-size:18px;color:#2c3e50;margin-bottom:15px;">📦 Item Pesanan</h2>
        <div style="overflow-x:auto;">
            <table class="table">
                <thead>
                    <tr>
                        <th>Produk</th>
                        <th>Bahan</th>
                        <th>Ukuran</th>
                        <th>Layanan</th>
                        <th style="text-align:center;">Jumlah</th>
                        <th style="text-align:right;">Harga</th>
                        <th style="text-align:right;">Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orderItems as $item): ?>
                    <tr>
                        <td>
                            <?= htmlspecialchars($item['product_name']) ?>
                            <?php 
                            $varData = !empty($item['variants']) ? json_decode($item['variants'], true) : [];
                            if (!empty($varData)): 
                                foreach ($varData as $vr): ?>
                                    <br><small style="color:#e67e22;">+ <?= htmlspecialchars($vr['name']) ?> <?= formatRupiah($vr['price']) ?></small>
                            <?php endforeach; endif; ?>
                        </td>
                        <td><?= htmlspecialchars($item['material_name']) ?: '-' ?></td>
                        <td><?= ($item['width'] && $item['height']) ? intval($item['width']) . '×' . intval($item['height']) . ' cm' : '-' ?></td>
                        <td>
                            <?php if ($item['design_service'] === 'jasa'): ?>
                                <span style="display:inline-block;padding:2px 10px;background:#f39c12;color:#fff;border-radius:4px;font-size:11px;font-weight:bold;">🎨 Jasa Desain</span>
                            <?php elseif ($item['design_service'] === 'upload'): ?>
                                <span style="display:inline-block;padding:2px 10px;background:#3498db;color:#fff;border-radius:4px;font-size:11px;">📎 Upload File</span>
                            <?php else: ?>
                                <span style="color:#999;font-size:12px;">-</span>
                            <?php endif; ?>
                            <?php if ($item['design_result_file']): ?>
                                <br><span style="font-size:11px;color:#27ae60;">✅ <a href="/uploads/designs/<?= htmlspecialchars($item['design_result_file']) ?>" target="_blank">Download Hasil</a></span>
                            <?php endif; ?>
                        </td>
                        <td style="text-align:center;"><?= $item['quantity'] ?></td>
                        <td style="text-align:right;"><?= formatRupiah($item['price']) ?></td>
                        <td style="text-align:right;"><?= formatRupiah($item['subtotal']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr style="background:#f8f9fa;font-weight:bold;">
                        <th colspan="5" style="text-align:right;">Total</th>
                        <th colspan="2" style="text-align:right;"><?= formatRupiah($result['total']) ?></th>
                    </tr>
                </tfoot>
            </table>
        </div>

        <!-- 🔥 STATUS DESAIN -->
        <?php if ($result['status'] === 'desain'): ?>
            <div style="margin-top:15px;padding:15px;background:#e8daef;border-radius:8px;color:#6c3483;">
                <strong>⏳ Proses Desain</strong>
                <p style="margin:5px 0 0;font-size:14px;">Pesanan sedang dalam proses desain oleh tim kami. Hasil desain akan tampil di sini setelah selesai.</p>
            </div>
        <?php endif; ?>

        <!-- 🔥 🔥 TOMBOL AKSI 🔥 🔥 -->
        <div class="btn-group">
            <?php if ($result['payment_status'] === 'unpaid' && in_array($result['payment_method'], ['transfer','qris'])): ?>
                <a href="/payment/confirm.php?order=<?= urlencode($result['order_code']) ?>" class="btn btn-warning btn-lg">
                    💳 Upload Bukti Pembayaran
                </a>
            <?php endif; ?>

            <?php if ($result['payment_status'] === 'dp' && $sisaPembayaran > 0): ?>
                <a href="/payment/confirm.php?order=<?= urlencode($result['order_code']) ?>" class="btn btn-warning btn-lg">
                    💰 Bayar Sisa (<?= formatRupiah($sisaPembayaran) ?>)
                </a>
            <?php endif; ?>

            <?php if ($result['payment_status'] === 'unpaid' && $result['payment_method'] === 'midtrans' && getSetting('midtrans_server_key')): ?>
                <button onclick="payMidtrans('<?= $result['order_code'] ?>')" class="btn btn-primary btn-lg">
                    💳 Bayar Sekarang
                </button>
                <div id="midtrans-payment-status" style="margin-top:10px;width:100%;"></div>
            <?php endif; ?>

            <?php if ($result['payment_status'] === 'pending_verification'): ?>
                <div style="padding:12px 16px;background:#fff3cd;border-radius:8px;color:#856404;width:100%;text-align:center;">
                    ⏳ Bukti pembayaran sedang diverifikasi oleh admin.
                </div>
            <?php endif; ?>

            <?php if ($result['payment_status'] === 'paid' || $result['status'] === 'done'): ?>
                <a href="/invoice.php?order=<?= urlencode($result['order_code']) ?>" target="_blank" class="btn btn-success btn-lg">
                    🧾 Lihat Invoice
                </a>
            <?php endif; ?>

            <?php if ($result['payment_method'] === 'cod' && $result['payment_status'] === 'unpaid'): ?>
                <div style="padding:12px 16px;background:#eaf2f8;border-radius:8px;color:#2c3e50;width:100%;text-align:center;">
                    💵 Pembayaran dilakukan saat barang diterima (COD).
                </div>
            <?php endif; ?>
        </div>

        <!-- 🔥 WHATSAPP LINK -->
        <p style="margin-top:15px;text-align:center;">
            <a href="https://wa.me/<?= WHATSAPP_NUMBER ?>?text=Halo%20saya%20ingin%20konfirmasi%20pesanan%20<?= urlencode($result['order_code']) ?>" target="_blank" class="btn btn-outline">
                <i class="fab fa-whatsapp"></i> Konfirmasi via WhatsApp
            </a>
            <a href="cek-pesanan.php" class="btn btn-outline">
                <i class="fas fa-undo"></i> Cek Pesanan Lain
            </a>
        </p>

        <script>
        // 🔥 SAVE LAST ORDER
        (function() {
            var data = {
                code: '<?= addslashes($result['order_code']) ?>',
                phone: '<?= addslashes($_POST['phone'] ?? $_SESSION['last_order_phone'] ?? '') ?>'
            };
            localStorage.setItem('lastOrder', JSON.stringify(data));
        })();
        </script>

    <?php endif; ?>
</div>

<script>
/**
 * 🔥 RESTORE LAST ORDER
 */
function restoreLastOrder() {
    var raw = localStorage.getItem('lastOrder');
    if (!raw) return;
    try {
        var data = JSON.parse(raw);
        document.querySelector('input[name="order_code"]').value = data.code;
        document.querySelector('input[name="phone"]').value = data.phone;
        document.querySelector('form').submit();
    } catch (e) {
        // Ignore
    }
}

/**
 * 🔥 PAY MIDTRANS
 */
async function payMidtrans(orderCode) {
    var btn = document.querySelector('button[onclick*="' + orderCode + '"]');
    if (btn) {
        btn.disabled = true;
        btn.textContent = '⏳ Mengarahkan...';
    }
    
    try {
        var response = await fetch('/payment/create.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ order_code: orderCode })
        });
        var result = await response.json();
        
        if (result.success && result.redirect_url) {
            window.location.href = result.redirect_url;
        } else {
            var statusDiv = document.getElementById('midtrans-payment-status');
            if (statusDiv) {
                statusDiv.innerHTML = '<div class="alert alert-error" style="background:#f8d7da;color:#721c24;padding:12px;border-radius:6px;border:1px solid #f5c6cb;">❌ ' + (result.message || 'Gagal memproses pembayaran') + '</div>';
            }
            if (btn) {
                btn.disabled = false;
                btn.textContent = '💳 Bayar Sekarang';
            }
        }
    } catch (err) {
        var statusDiv = document.getElementById('midtrans-payment-status');
        if (statusDiv) {
            statusDiv.innerHTML = '<div class="alert alert-error" style="background:#f8d7da;color:#721c24;padding:12px;border-radius:6px;border:1px solid #f5c6cb;">❌ Terjadi kesalahan, coba lagi</div>';
        }
        if (btn) {
            btn.disabled = false;
            btn.textContent = '💳 Bayar Sekarang';
        }
    }
}
</script>

<?php include 'includes/footer.php'; ?>