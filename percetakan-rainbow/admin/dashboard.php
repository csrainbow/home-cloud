<?php
require_once __DIR__ . '/../config.php';
if (!isAdmin()) redirect('/admin/index.php');

// 🔥 STATISTIK UTAMA
$totalOrders = $db->query("SELECT COUNT(*) as c FROM orders")->fetch()['c'];
$totalRevenue = $db->query("SELECT COALESCE(SUM(total),0) as t FROM orders WHERE payment_status='paid'")->fetch()['t'];
$pendingOrders = $db->query("SELECT COUNT(*) as c FROM orders WHERE status='pending'")->fetch()['c'];
$totalProducts = $db->query("SELECT COUNT(*) as c FROM products")->fetch()['c'];

// 🔥 🔥 STATISTIK DP vs LUNAS 🔥 🔥
$dpOrders = $db->query("SELECT COUNT(*) as c FROM orders WHERE payment_status='dp'")->fetch()['c'];
$paidOrders = $db->query("SELECT COUNT(*) as c FROM orders WHERE payment_status='paid'")->fetch()['c'];
$unpaidOrders = $db->query("SELECT COUNT(*) as c FROM orders WHERE payment_status='unpaid'")->fetch()['c'];
$pendingVerificationOrders = $db->query("SELECT COUNT(*) as c FROM orders WHERE payment_status='pending_verification'")->fetch()['c'];

// 🔥 TOTAL PENDAPATAN DARI DP + LUNAS
$totalDpRevenue = $db->query("SELECT COALESCE(SUM(amount),0) as t FROM payments WHERE status IN ('verified','approved','paid') AND payment_type='dp'")->fetch()['t'];
$totalPaidRevenue = $db->query("SELECT COALESCE(SUM(amount),0) as t FROM payments WHERE status IN ('verified','approved','paid') AND payment_type='pelunasan'")->fetch()['t'];
$totalAllRevenue = $totalDpRevenue + $totalPaidRevenue;

// 🔥 STATISTIK PER BULAN (UNTUK GRAFIK)
$monthlyStats = $db->query("
    SELECT 
        strftime('%Y-%m', created_at) as month,
        COUNT(*) as total_orders,
        COALESCE(SUM(total),0) as total_amount
    FROM orders 
    WHERE payment_status IN ('paid','dp')
    GROUP BY strftime('%Y-%m', created_at)
    ORDER BY month DESC
    LIMIT 6
")->fetchAll();

// 🔥 RECENT ORDERS DENGAN STATUS PEMBAYARAN
$recentOrders = $db->query("
    SELECT o.*, 
           COALESCE((SELECT SUM(amount) FROM payments WHERE order_id=o.id AND status IN ('verified','approved','paid')), 0) as total_paid
    FROM orders o 
    ORDER BY o.created_at DESC 
    LIMIT 5
")->fetchAll();

$pageTitle = 'Dashboard';
include '../includes/header.php';
?>

<style>
.admin-layout {
    display: flex;
    gap: 20px;
    margin-top: 20px;
}

.admin-sidebar {
    width: 220px;
    background: #2c3e50;
    padding: 20px 15px;
    border-radius: 8px;
    flex-shrink: 0;
    position: sticky;
    top: 80px;
    height: fit-content;
}

.admin-sidebar h2 {
    color: #f39c12;
    font-size: 16px;
    margin-bottom: 15px;
    padding-bottom: 10px;
    border-bottom: 1px solid rgba(255,255,255,0.1);
}

.admin-sidebar ul {
    list-style: none;
    padding: 0;
}

.admin-sidebar ul li {
    margin-bottom: 4px;
}

.admin-sidebar ul li a {
    display: block;
    padding: 8px 12px;
    color: #bdc3c7;
    text-decoration: none;
    border-radius: 4px;
    font-size: 14px;
    transition: all 0.3s;
}

.admin-sidebar ul li a:hover {
    background: rgba(255,255,255,0.1);
    color: #fff;
}

.admin-sidebar ul li a.active {
    background: #f39c12;
    color: #fff;
}

.admin-main {
    flex: 1;
    min-width: 0;
}

.admin-main h1 {
    font-size: 24px;
    color: #2c3e50;
    margin-bottom: 20px;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 15px;
    margin-bottom: 20px;
}

.stat-card {
    background: #fff;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 1px 4px rgba(0,0,0,0.06);
    text-align: center;
    border-left: 4px solid #2c3e50;
}

.stat-card .stat-value {
    font-size: 28px;
    font-weight: bold;
    color: #2c3e50;
}

.stat-card .stat-label {
    font-size: 13px;
    color: #6c757d;
    margin-top: 4px;
}

.stat-card.primary { border-left-color: #2c3e50; }
.stat-card.success { border-left-color: #27ae60; }
.stat-card.warning { border-left-color: #f39c12; }
.stat-card.danger { border-left-color: #e74c3c; }
.stat-card.info { border-left-color: #3498db; }
.stat-card.purple { border-left-color: #8e44ad; }

/* 🔥 STATISTIK PEMBAYARAN */
.payment-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 15px;
    margin-bottom: 20px;
}

.payment-stat-card {
    background: #fff;
    padding: 15px;
    border-radius: 8px;
    box-shadow: 0 1px 4px rgba(0,0,0,0.06);
    text-align: center;
}

.payment-stat-card .stat-value {
    font-size: 22px;
    font-weight: bold;
}

.payment-stat-card .stat-label {
    font-size: 12px;
    color: #6c757d;
    margin-top: 4px;
}

.payment-stat-card .stat-value.dp { color: #f39c12; }
.payment-stat-card .stat-value.paid { color: #27ae60; }
.payment-stat-card .stat-value.unpaid { color: #95a5a6; }
.payment-stat-card .stat-value.verification { color: #3498db; }

/* 🔥 GRAFIK SEDERHANA */
.chart-container {
    background: #fff;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 1px 4px rgba(0,0,0,0.06);
    margin-bottom: 20px;
}

.chart-container h3 {
    font-size: 16px;
    color: #2c3e50;
    margin-bottom: 15px;
}

.chart-bars {
    display: flex;
    align-items: flex-end;
    gap: 15px;
    height: 150px;
    padding: 10px 0;
    border-bottom: 1px solid #eee;
}

.chart-bar-wrapper {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    height: 100%;
    justify-content: flex-end;
}

.chart-bar {
    width: 100%;
    max-width: 40px;
    border-radius: 4px 4px 0 0;
    transition: height 0.5s ease;
    min-height: 4px;
}

.chart-bar.amount {
    background: linear-gradient(180deg, #2c3e50, #3498db);
}

.chart-label {
    font-size: 10px;
    color: #6c757d;
    margin-top: 6px;
    text-align: center;
}

/* 🔥 TABLE */
.table {
    width: 100%;
    border-collapse: collapse;
    background: #fff;
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 1px 4px rgba(0,0,0,0.06);
}

.table thead {
    background: #f8f9fa;
}

.table th {
    padding: 10px 12px;
    text-align: left;
    font-size: 12px;
    text-transform: uppercase;
    color: #6c757d;
    font-weight: 600;
    border-bottom: 2px solid #dee2e6;
}

.table td {
    padding: 10px 12px;
    border-bottom: 1px solid #f1f3f5;
    font-size: 14px;
}

.table tbody tr:hover {
    background: #f8f9fa;
}

.status-badge {
    display: inline-block;
    padding: 3px 10px;
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
.status-dp { background: #f39c12; color: #fff; }
.status-paid { background: #27ae60; color: #fff; }
.status-pending_verification { background: #3498db; color: #fff; }

/* 🔥 RESPONSIVE */
@media (max-width: 768px) {
    .admin-layout {
        flex-direction: column;
    }
    .admin-sidebar {
        width: 100%;
        position: relative;
        top: 0;
    }
    .admin-sidebar ul {
        display: flex;
        flex-wrap: wrap;
        gap: 4px;
    }
    .admin-sidebar ul li a {
        padding: 6px 12px;
        font-size: 13px;
    }
    .stats-grid, .payment-stats {
        grid-template-columns: repeat(2, 1fr);
    }
}
</style>

<div class="admin-layout">
    <aside class="admin-sidebar">
        <h2>Admin Panel</h2>
        <ul>
            <li><a href="dashboard.php" class="active">Dashboard</a></li>
            <li><a href="products.php">Produk</a></li>
            <li><a href="orders.php">Pesanan</a></li>
            <li><a href="edit-halaman.php?slug=tentang-kami">Tentang Kami</a></li>
            <li><a href="settings.php">Pengaturan</a></li>
            <li><a href="logout.php">Logout</a></li>
        </ul>
    </aside>
    <main class="admin-main">
        <h1>📊 Dashboard</h1>

        <!-- 🔥 STATISTIK UTAMA -->
        <div class="stats-grid">
            <div class="stat-card primary">
                <div class="stat-value"><?= $totalOrders ?></div>
                <div class="stat-label">Total Pesanan</div>
            </div>
            <div class="stat-card success">
                <div class="stat-value"><?= formatRupiah($totalAllRevenue) ?></div>
                <div class="stat-label">Total Pendapatan (DP + Lunas)</div>
            </div>
            <div class="stat-card warning">
                <div class="stat-value"><?= $pendingOrders ?></div>
                <div class="stat-label">Pesanan Pending</div>
            </div>
            <div class="stat-card info">
                <div class="stat-value"><?= $totalProducts ?></div>
                <div class="stat-label">Total Produk</div>
            </div>
        </div>

        <!-- 🔥 🔥 STATISTIK PEMBAYARAN 🔥 🔥 -->
        <h2 style="font-size:18px;color:#2c3e50;margin-bottom:15px;">💰 Status Pembayaran</h2>
        <div class="payment-stats">
            <div class="payment-stat-card">
                <div class="stat-value paid"><?= $paidOrders ?></div>
                <div class="stat-label">✅ Lunas</div>
            </div>
            <div class="payment-stat-card">
                <div class="stat-value dp"><?= $dpOrders ?></div>
                <div class="stat-label">💰 DP</div>
            </div>
            <div class="payment-stat-card">
                <div class="stat-value verification"><?= $pendingVerificationOrders ?></div>
                <div class="stat-label">⏳ Menunggu Verifikasi</div>
            </div>
            <div class="payment-stat-card">
                <div class="stat-value unpaid"><?= $unpaidOrders ?></div>
                <div class="stat-label">Belum Dibayar</div>
            </div>
        </div>

        <!-- 🔥 🔥 GRAFIK PENDAPATAN BULANAN 🔥 🔥 -->
        <?php if (!empty($monthlyStats)): ?>
        <div class="chart-container">
            <h3>📈 Pendapatan 6 Bulan Terakhir</h3>
            <div class="chart-bars">
                <?php 
                $maxAmount = max(array_column($monthlyStats, 'total_amount'));
                $maxAmount = $maxAmount > 0 ? $maxAmount : 1;
                foreach (array_reverse($monthlyStats) as $stat): 
                    $height = ($stat['total_amount'] / $maxAmount) * 100;
                    $height = max($height, 5);
                ?>
                <div class="chart-bar-wrapper">
                    <div class="chart-bar amount" style="height: <?= $height ?>%;"></div>
                    <div class="chart-label">
                        <?= date('M Y', strtotime($stat['month'] . '-01')) ?>
                        <br>
                        <small style="font-weight:bold;color:#2c3e50;"><?= formatRupiah($stat['total_amount']) ?></small>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- 🔥 PESANAN TERBARU -->
        <h2 style="font-size:18px;color:#2c3e50;margin-bottom:15px;">📋 Pesanan Terbaru</h2>
        <table class="table">
            <thead>
                <tr>
                    <th>Kode</th>
                    <th>Nama</th>
                    <th>Total</th>
                    <th>Status</th>
                    <th>Pembayaran</th>
                    <th>Tanggal</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recentOrders as $o): 
                    $sisa = $o['total'] - $o['total_paid'];
                ?>
                <tr>
                    <td><?= htmlspecialchars($o['order_code']) ?></td>
                    <td><?= htmlspecialchars($o['customer_name']) ?></td>
                    <td><?= formatRupiah($o['total']) ?></td>
                    <td><span class="status-badge status-<?= $o['status'] ?>"><?= ucfirst($o['status']) ?></span></td>
                    <td>
                        <span class="status-badge status-<?= $o['payment_status'] ?>">
                            <?php
                            $pl = [
                                'unpaid' => 'Belum',
                                'pending_verification' => 'Verifikasi',
                                'paid' => 'Lunas',
                                'dp' => 'DP'
                            ];
                            echo $pl[$o['payment_status']] ?? ucfirst($o['payment_status']);
                            ?>
                        </span>
                        <?php if ($o['payment_status'] === 'dp'): ?>
                            <br><small style="color:#f39c12;">Sisa: <?= formatRupiah($sisa) ?></small>
                        <?php endif; ?>
                    </td>
                    <td><?= date('d/m/Y H:i', strtotime($o['created_at'])) ?></td>
                    <td>
                        <a href="order-detail.php?id=<?= $o['id'] ?>" class="btn btn-sm btn-outline">Detail</a>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($recentOrders)): ?>
                <tr>
                    <td colspan="7" style="text-align:center;padding:30px;color:#999;">Belum ada pesanan</td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </main>
</div>

<?php include '../includes/footer.php'; ?>