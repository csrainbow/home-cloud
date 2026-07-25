<?php
require_once __DIR__ . '/config.php';

// 🔥 CEK LOGIN
if (!isset($_SESSION['customer_id'])) {
    header('Location: /login.php');
    exit;
}

$customerId = $_SESSION['customer_id'];
$customerName = $_SESSION['customer_name'] ?? 'Pelanggan';

// 🔥 🔥 STATISTIK PESANAN 🔥 🔥
$stats = $db->prepare("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status='pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN payment_status='paid' THEN 1 ELSE 0 END) as paid,
        SUM(CASE WHEN payment_status='dp' THEN 1 ELSE 0 END) as dp,
        SUM(CASE WHEN payment_status='pending_verification' THEN 1 ELSE 0 END) as verification
    FROM orders 
    WHERE customer_id = ?
");
$stats->execute([$customerId]);
$stats = $stats->fetch();

// 🔥 🔥 AMBIL PESANAN DENGAN TOTAL PAID 🔥 🔥
$orders = $db->prepare("
    SELECT o.*, 
           COALESCE((SELECT SUM(amount) FROM payments WHERE order_id=o.id AND status IN ('verified','approved','paid')), 0) as total_paid
    FROM orders o 
    WHERE o.customer_id = ? 
    ORDER BY o.created_at DESC
");
$orders->execute([$customerId]);
$orders = $orders->fetchAll();

$pageTitle = 'Dashboard Saya';
include '../includes/header.php';
?>

<style>
/* ============================================
   DASHBOARD STYLES
   ============================================ */
.dashboard-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 10px;
    margin-bottom: 20px;
}
.dashboard-header h1 {
    margin: 0;
    font-size: 24px;
    color: #2c3e50;
}
.dashboard-header .subtitle {
    color: #6c757d;
    margin: 4px 0 0;
    font-size: 14px;
}

/* 🔥 STATS */
.stats-mini {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(100px, 1fr));
    gap: 10px;
    margin-bottom: 25px;
}
.stat-item {
    background: #fff;
    padding: 12px 15px;
    border-radius: 8px;
    box-shadow: 0 1px 4px rgba(0,0,0,0.06);
    text-align: center;
    border-bottom: 3px solid #e9ecef;
}
.stat-item .number {
    font-size: 22px;
    font-weight: bold;
    display: block;
}
.stat-item .label {
    font-size: 11px;
    color: #6c757d;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.stat-item .number.total { color: #2c3e50; }
.stat-item .number.pending { color: #f39c12; }
.stat-item .number.paid { color: #27ae60; }
.stat-item .number.dp { color: #f39c12; }
.stat-item .number.verification { color: #3498db; }
.stat-item.border-total { border-bottom-color: #2c3e50; }
.stat-item.border-pending { border-bottom-color: #f39c12; }
.stat-item.border-paid { border-bottom-color: #27ae60; }
.stat-item.border-dp { border-bottom-color: #f39c12; }
.stat-item.border-verification { border-bottom-color: #3498db; }

/* 🔥 ORDER CARD */
.order-card {
    background: #fff;
    border: 1px solid #e9ecef;
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 15px;
    transition: all 0.3s;
}
.order-card:hover {
    box-shadow: 0 4px 16px rgba(0,0,0,0.08);
    border-color: #f39c12;
}
.order-card .order-header {
    display: flex;
    justify-content: space-between;
    align-items: start;
    flex-wrap: wrap;
    gap: 10px;
    margin-bottom: 12px;
}
.order-card .order-code {
    font-size: 16px;
    font-weight: 600;
    color: #2c3e50;
    text-decoration: none;
}
.order-card .order-code:hover {
    color: #f39c12;
}
.order-card .order-date {
    font-size: 13px;
    color: #6c757d;
}
.order-card .order-status {
    text-align: right;
}
.order-card .order-items {
    font-size: 14px;
    color: #555;
}
.order-card .order-items .item-row {
    display: flex;
    justify-content: space-between;
    padding: 3px 0;
}
.order-card .order-total {
    display: flex;
    justify-content: space-between;
    padding: 10px 0 0;
    border-top: 1px solid #eee;
    margin-top: 8px;
    font-weight: bold;
    font-size: 15px;
}
.order-card .order-actions {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    margin-top: 12px;
}

/* 🔥 PROGRESS BAR */
.progress-container {
    margin: 8px 0;
}
.progress-bar {
    background: #ecf0f1;
    border-radius: 10px;
    height: 6px;
    overflow: hidden;
}
.progress-bar .fill {
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

/* 🔥 STATUS BADGE */
.status-badge {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
}
.status-pending { background: #fff3cd; color: #856404; }
.status-desain { background: #e8daef; color: #6c3483; }
.status-processed { background: #cce5ff; color: #004085; }
.status-printing { background: #d4edda; color: #155724; }
.status-done { background: #d1ecf1; color: #0c5460; }
.status-cancelled { background: #f8d7da; color: #721c24; }
.status-unpaid { background: #e9ecef; color: #495057; }
.status-pending_verification { background: #fff3cd; color: #856404; }
.status-dp { background: #f39c12; color: #fff; }
.status-paid { background: #27ae60; color: #fff; }

/* 🔥 EMPTY STATE */
.empty-state {
    text-align: center;
    padding: 60px 20px;
    background: #f8f9fa;
    border-radius: 12px;
}
.empty-state .icon {
    font-size: 48px;
    margin-bottom: 15px;
}
.empty-state p {
    color: #6c757d;
}
.empty-state .btn {
    margin-top: 10px;
}

/* 🔥 BUTTONS */
.btn {
    display: inline-block;
    padding: 8px 16px;
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
@media (max-width: 480px) {
    .stats-mini {
        grid-template-columns: repeat(2, 1fr);
    }
    .order-card .order-header {
        flex-direction: column;
    }
    .order-card .order-status {
        text-align: left;
        width: 100%;
    }
    .order-card .order-actions {
        flex-direction: column;
    }
    .order-card .order-actions .btn {
        width: 100%;
        text-align: center;
    }
}
</style>

<div class="dashboard-header">
    <div>
        <h1>👋 Halo, <?= htmlspecialchars($customerName) ?>!</h1>
        <p class="subtitle">Ini daftar pesanan kamu.</p>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap;">
        <a href="profile.php" class="btn btn-outline btn-sm">
            <i class="fas fa-user-edit"></i> Edit Profil
        </a>
    </div>
</div>

<!-- 🔥 🔥 STATISTIK PESANAN 🔥 🔥 -->
<div class="stats-mini">
    <div class="stat-item border-total">
        <span class="number total"><?= $stats['total'] ?? 0 ?></span>
        <span class="label">📦 Total Pesanan</span>
    </div>
    <div class="stat-item border-pending">
        <span class="number pending"><?= $stats['pending'] ?? 0 ?></span>
        <span class="label">⏳ Pending</span>
    </div>
    <div class="stat-item border-paid">
        <span class="number paid"><?= $stats['paid'] ?? 0 ?></span>
        <span class="label">✅ Lunas</span>
    </div>
    <div class="stat-item border-dp">
        <span class="number dp"><?= $stats['dp'] ?? 0 ?></span>
        <span class="label">💰 DP</span>
    </div>
    <div class="stat-item border-verification">
        <span class="number verification"><?= $stats['verification'] ?? 0 ?></span>
        <span class="label">⏳ Verifikasi</span>
    </div>
</div>

<!-- 🔥 🔥 DAFTAR PESANAN 🔥 🔥 -->
<?php if (empty($orders)): ?>
    <div class="empty-state">
        <div class="icon">📦</div>
        <p>Belum ada pesanan.</p>
        <a href="/products.php" class="btn btn-primary">
            <i class="fas fa-shopping-bag"></i> Belanja yuk!
        </a>
    </div>
<?php else: ?>
    <div style="display:grid;gap:15px;">
        <?php foreach ($orders as $o): 
            $items = $db->prepare("SELECT * FROM order_items WHERE order_id=?");
            $items->execute([$o['id']]);
            $orderItems = $items->fetchAll();
            
            $sisa = $o['total'] - $o['total_paid'];
            $persentaseDibayar = $o['total'] > 0 ? round(($o['total_paid'] / $o['total']) * 100) : 0;
            
            // 🔥 CEK APAKAH ADA HASIL DESAIN
            $hasDesignResult = false;
            foreach ($orderItems as $item) {
                if ($item['design_result_file']) {
                    $hasDesignResult = true;
                    break;
                }
            }
        ?>
        <div class="order-card">
            <!-- 🔥 HEADER -->
            <div class="order-header">
                <div>
                    <a href="order-detail.php?order=<?= urlencode($o['order_code']) ?>" class="order-code">
                        <?= htmlspecialchars($o['order_code']) ?>
                    </a>
                    <div class="order-date">
                        <i class="far fa-calendar-alt"></i> 
                        <?= date('d/m/Y H:i', strtotime($o['created_at'])) ?>
                    </div>
                </div>
                <div class="order-status">
                    <span class="status-badge status-<?= $o['status'] ?>">
                        <?php
                        $sl = ['pending'=>'⏳ Pending','desain'=>'🎨 Proses Desain','processed'=>'⚙️ Diproses','printing'=>'🖨️ Cetak','done'=>'✅ Selesai','cancelled'=>'❌ Dibatalkan'];
                        echo $sl[$o['status']] ?? ucfirst($o['status']);
                        ?>
                    </span>
                    <br>
                    <span class="status-badge status-<?= $o['payment_status'] ?>" style="margin-top:4px;display:inline-block;">
                        <?php
                        $pl = [
                            'unpaid' => '❌ Belum Dibayar',
                            'pending_verification' => '⏳ Verifikasi',
                            'paid' => '✅ Lunas',
                            'dp' => '💰 DP'
                        ];
                        echo $pl[$o['payment_status']] ?? ucfirst($o['payment_status']);
                        ?>
                    </span>
                </div>
            </div>
            
            <!-- 🔥 ITEMS -->
            <div class="order-items">
                <?php foreach ($orderItems as $item): ?>
                <div class="item-row">
                    <span>
                        <?= htmlspecialchars($item['product_name']) ?>
                        <?php if ($item['design_service'] === 'jasa'): ?>
                            <span style="color:#e67e22;font-size:12px;">(+ Jasa Desain)</span>
                        <?php endif; ?>
                        <?php if ($item['design_result_file']): ?>
                            <span style="color:#27ae60;font-size:12px;">✅ Hasil siap</span>
                        <?php endif; ?>
                        <?php if ($item['design_file']): ?>
                            <span style="color:#3498db;font-size:12px;">📎 File</span>
                        <?php endif; ?>
                        × <?= $item['quantity'] ?>
                    </span>
                    <span><?= formatRupiah($item['subtotal']) ?></span>
                </div>
                <?php endforeach; ?>
            </div>
            
            <!-- 🔥 TOTAL -->
            <div class="order-total">
                <span>Total</span>
                <span><?= formatRupiah($o['total']) ?></span>
            </div>
            
            <!-- 🔥 PROGRESS BAR PEMBAYARAN -->
            <?php if ($o['payment_status'] !== 'unpaid' && $o['payment_status'] !== 'pending_verification'): ?>
                <div class="progress-container">
                    <div class="progress-bar">
                        <div class="fill" style="width: <?= $persentaseDibayar ?>%;"></div>
                    </div>
                    <div class="progress-label">
                        <span><?= $persentaseDibayar ?>% dibayar</span>
                        <span>
                            <?php if ($sisa > 0): ?>
                                Sisa: <?= formatRupiah($sisa) ?>
                            <?php else: ?>
                                ✅ Lunas
                            <?php endif; ?>
                        </span>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- 🔥 ACTIONS -->
            <div class="order-actions">
                <?php if ($o['payment_status'] === 'unpaid'): ?>
                    <?php if (in_array($o['payment_method'], ['transfer','qris'])): ?>
                        <a href="/payment/confirm.php?order=<?= urlencode($o['order_code']) ?>" class="btn btn-primary btn-sm">
                            <i class="fas fa-credit-card"></i> Upload Bukti Bayar
                        </a>
                    <?php elseif ($o['payment_method'] === 'midtrans' && getSetting('midtrans_server_key')): ?>
                        <button onclick="payMidtrans('<?= $o['order_code'] ?>')" class="btn btn-primary btn-sm">
                            <i class="fas fa-credit-card"></i> Bayar Sekarang
                        </button>
                    <?php endif; ?>
                <?php endif; ?>
                
                <?php if ($o['payment_status'] === 'dp' && $sisa > 0): ?>
                    <a href="/payment/confirm.php?order=<?= urlencode($o['order_code']) ?>" class="btn btn-warning btn-sm">
                        <i class="fas fa-money-bill-wave"></i> Bayar Sisa (<?= formatRupiah($sisa) ?>)
                    </a>
                <?php endif; ?>
                
                <?php if ($o['payment_status'] === 'pending_verification'): ?>
                    <span style="display:inline-block;padding:6px 12px;background:#fff3cd;border-radius:6px;font-size:12px;color:#856404;">
                        <i class="fas fa-clock"></i> Menunggu Verifikasi
                    </span>
                <?php endif; ?>
                
                <?php if ($o['status'] === 'desain'): ?>
                    <span style="display:inline-block;padding:6px 12px;background:#e8daef;border-radius:6px;font-size:12px;color:#6c3483;">
                        <i class="fas fa-paint-brush"></i> Proses Desain
                    </span>
                <?php endif; ?>
                
                <?php if ($hasDesignResult): ?>
                    <span style="display:inline-block;padding:6px 12px;background:#d4edda;border-radius:6px;font-size:12px;color:#155724;">
                        <i class="fas fa-check-circle"></i> Hasil Desain Siap
                    </span>
                <?php endif; ?>
                
                <a href="order-detail.php?order=<?= urlencode($o['order_code']) ?>" class="btn btn-outline btn-sm">
                    <i class="fas fa-eye"></i> Detail
                </a>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<!-- 🔥 LOGOUT -->
<p style="margin-top:20px;display:flex;gap:10px;flex-wrap:wrap;">
    <a href="/logout.php" class="btn btn-outline btn-sm">
        <i class="fas fa-sign-out-alt"></i> Keluar
    </a>
</p>

<script>
/**
 * 🔥 PAY MIDTRANS
 */
async function payMidtrans(orderCode) {
    var btn = document.querySelector('button[onclick*="' + orderCode + '"]');
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '⏳ Mengarahkan...';
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
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-credit-card"></i> Bayar Sekarang';
            }
            showNotification(result.message || 'Gagal memproses pembayaran', 'error');
        }
    } catch (err) {
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-credit-card"></i> Bayar Sekarang';
        }
        showNotification('Terjadi kesalahan, coba lagi', 'error');
    }
}

/**
 * 🔥 SHOW NOTIFICATION
 */
function showNotification(msg, type) {
    type = type || 'success';
    var existing = document.querySelector('.notif-toast');
    if (existing) existing.remove();
    
    var div = document.createElement('div');
    div.className = 'notif-toast';
    var bgColor = type === 'success' ? '#27ae60' : type === 'error' ? '#e74c3c' : '#f39c12';
    div.style.cssText = 'position:fixed;top:15px;left:50%;transform:translateX(-50%);background:' + bgColor + ';color:#fff;padding:12px 24px;border-radius:8px;z-index:99999;font-size:14px;box-shadow:0 4px 12px rgba(0,0,0,.15);text-align:center;max-width:90%;';
    div.textContent = msg;
    document.body.appendChild(div);
    setTimeout(function() {
        div.style.opacity = '0';
        div.style.transition = '.3s';
    }, 3000);
    setTimeout(function() { div.remove(); }, 3500);
}
</script>

<?php include '../includes/footer.php'; ?>