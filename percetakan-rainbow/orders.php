<?php
require_once __DIR__ . '/config.php';
if (!isAdmin()) redirect('/admin/index.php');

// 🔥 🔥 FILTER & PENCARIAN 🔥 🔥
$statusFilter = $_GET['status'] ?? '';
$paymentFilter = $_GET['payment'] ?? '';
$search = trim($_GET['search'] ?? '');
$sort = $_GET['sort'] ?? 'desc';
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

// 🔥 🔥 PROSES POST 🔥 🔥
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $returnTo = $_POST['return_to'] ?? 'orders.php';
    
    // 🔥 UPDATE STATUS
    if (isset($_POST['update_status'])) {
        $stmt = $db->prepare("UPDATE orders SET status=? WHERE id=?");
        $stmt->execute([$_POST['status'], $_POST['order_id']]);
        $_SESSION['success'] = "✅ Status pesanan berhasil diupdate!";
        header('Location: ' . $returnTo);
        exit;
    }
    
    // 🔥 UPDATE PEMBAYARAN
    if (isset($_POST['update_payment'])) {
        $stmt = $db->prepare("UPDATE orders SET payment_status=? WHERE id=?");
        $stmt->execute([$_POST['payment_status'], $_POST['order_id']]);
        $_SESSION['success'] = "✅ Status pembayaran berhasil diupdate!";
        header('Location: ' . $returnTo);
        exit;
    }
    
    // 🔥 VERIFIKASI PEMBAYARAN (OTOMATIS - DETEKSI DP/LUNAS)
    if (isset($_POST['verify_payment'])) {
        $payment = $db->prepare("SELECT amount, payment_type FROM payments WHERE id=?");
        $payment->execute([$_POST['payment_id']]);
        $paymentData = $payment->fetch();
        
        if (!$paymentData) {
            $_SESSION['error'] = "❌ Data pembayaran tidak ditemukan!";
            header('Location: ' . $returnTo);
            exit;
        }
        
        $db->prepare("UPDATE payments SET status='verified' WHERE id=?")->execute([$_POST['payment_id']]);
        
        $order = $db->prepare("SELECT id, total FROM orders WHERE id=?");
        $order->execute([$_POST['order_id']]);
        $order = $order->fetch();
        
        $stmt = $db->prepare("SELECT COUNT(*) as c FROM order_items WHERE order_id=? AND design_service='jasa'");
        $stmt->execute([$_POST['order_id']]);
        $hasJasa = $stmt->fetch()['c'] > 0;
        
        $total = floatval($order['total']);
        $paymentType = $paymentData['payment_type'] ?? 'dp';
        
        $paidStmt = $db->prepare("SELECT SUM(amount) as total_paid FROM payments WHERE order_id=? AND status IN ('verified','approved','paid')");
        $paidStmt->execute([$_POST['order_id']]);
        $totalPaid = floatval($paidStmt->fetch()['total_paid']);
        
        // 🔥 UPDATE STATUS
        if ($paymentType === 'pelunasan' || $totalPaid >= $total) {
            if ($hasJasa) {
                $db->prepare("UPDATE orders SET payment_status='paid', status='desain' WHERE id=?")->execute([$_POST['order_id']]);
            } else {
                $db->prepare("UPDATE orders SET payment_status='paid', status='processed' WHERE id=?")->execute([$_POST['order_id']]);
            }
            $_SESSION['success'] = "✅ Pembayaran LUNAS berhasil diverifikasi!";
        } else {
            if ($hasJasa) {
                $db->prepare("UPDATE orders SET payment_status='dp', status='desain' WHERE id=?")->execute([$_POST['order_id']]);
            } else {
                $db->prepare("UPDATE orders SET payment_status='dp', status='processed' WHERE id=?")->execute([$_POST['order_id']]);
            }
            $_SESSION['success'] = "💰 Pembayaran DP berhasil diverifikasi! Sisa: " . formatRupiah($total - $totalPaid);
        }
        
        header('Location: ' . $returnTo);
        exit;
    }
    
    // 🔥 VERIFIKASI DP (PAKSA)
    if (isset($_POST['verify_dp'])) {
        $db->prepare("UPDATE payments SET status='verified' WHERE id=?")->execute([$_POST['payment_id']]);
        
        $order = $db->prepare("SELECT id, total FROM orders WHERE id=?");
        $order->execute([$_POST['order_id']]);
        $order = $order->fetch();
        
        $stmt = $db->prepare("SELECT COUNT(*) as c FROM order_items WHERE order_id=? AND design_service='jasa'");
        $stmt->execute([$_POST['order_id']]);
        $hasJasa = $stmt->fetch()['c'] > 0;
        
        if ($hasJasa) {
            $db->prepare("UPDATE orders SET payment_status='dp', status='desain' WHERE id=?")->execute([$_POST['order_id']]);
        } else {
            $db->prepare("UPDATE orders SET payment_status='dp', status='processed' WHERE id=?")->execute([$_POST['order_id']]);
        }
        
        $db->prepare("UPDATE payments SET payment_type='dp' WHERE id=? AND (payment_type IS NULL OR payment_type='')")->execute([$_POST['payment_id']]);
        
        $_SESSION['success'] = "💰 Pembayaran DP berhasil diverifikasi (paksa)!";
        header('Location: ' . $returnTo);
        exit;
    }
    
    // 🔥 DESAIN SELESAI
    if (isset($_POST['design_done'])) {
        $db->prepare("UPDATE orders SET status='processed' WHERE id=? AND status='desain'")->execute([$_POST['order_id']]);
        $_SESSION['success'] = "🎨 Desain selesai! Pesanan masuk ke proses cetak.";
        header('Location: ' . $returnTo);
        exit;
    }
    
    // 🔥 TOLAK PEMBAYARAN
    if (isset($_POST['reject_payment'])) {
        $db->prepare("UPDATE payments SET status='rejected' WHERE id=?")->execute([$_POST['payment_id']]);
        $_SESSION['error'] = "❌ Pembayaran ditolak!";
        header('Location: ' . $returnTo);
        exit;
    }
    
    // 🔥 🔥 EXPORT CSV 🔥 🔥
    if (isset($_POST['export_csv'])) {
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="pesanan_' . date('Y-m-d') . '.csv"');
        
        $output = fopen('php://output', 'w');
        fputcsv($output, ['Kode', 'Nama', 'Total', 'Dibayar', 'Sisa', 'Status', 'Pembayaran', 'Tanggal']);
        
        $exportOrders = $db->query("
            SELECT o.*, 
                   COALESCE((SELECT SUM(amount) FROM payments WHERE order_id=o.id AND status IN ('verified','approved','paid')), 0) as total_paid
            FROM orders o 
            ORDER BY o.created_at DESC
        ")->fetchAll();
        
        foreach ($exportOrders as $o) {
            $sisa = $o['total'] - $o['total_paid'];
            fputcsv($output, [
                $o['order_code'],
                $o['customer_name'],
                $o['total'],
                $o['total_paid'],
                $sisa,
                $o['status'],
                $o['payment_status'],
                $o['created_at']
            ]);
        }
        fclose($output);
        exit;
    }
}

// 🔥 🔥 BUILD QUERY 🔥 🔥
$whereConditions = [];
$params = [];

if ($statusFilter) {
    $whereConditions[] = "status = ?";
    $params[] = $statusFilter;
}

if ($paymentFilter) {
    $whereConditions[] = "payment_status = ?";
    $params[] = $paymentFilter;
}

if ($search) {
    $whereConditions[] = "(order_code LIKE ? OR customer_name LIKE ? OR customer_phone LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$whereSql = empty($whereConditions) ? '' : 'WHERE ' . implode(' AND ', $whereConditions);
$orderBy = "ORDER BY created_at $sort";

// 🔥 🔥 HITUNG TOTAL 🔥 🔥
$countSql = "SELECT COUNT(*) as total FROM orders $whereSql";
$countStmt = $db->prepare($countSql);
$countStmt->execute($params);
$totalOrders = $countStmt->fetch()['total'];
$totalPages = ceil($totalOrders / $perPage);

// 🔥 🔥 AMBIL DATA 🔥 🔥
$sql = "
    SELECT o.*, 
           COALESCE((SELECT SUM(amount) FROM payments WHERE order_id=o.id AND status IN ('verified','approved','paid')), 0) as total_paid,
           (SELECT payment_type FROM payments WHERE order_id=o.id ORDER BY created_at DESC LIMIT 1) as last_payment_type
    FROM orders o 
    $whereSql
    $orderBy
    LIMIT ? OFFSET ?
";

$stmt = $db->prepare($sql);
$allParams = array_merge($params, [$perPage, $offset]);
$stmt->execute($allParams);
$orders = $stmt->fetchAll();

// 🔥 AMBIL PAYMENT_ID UNTUK VERIFIKASI
$paymentIds = [];
foreach ($orders as $o) {
    $stmt = $db->prepare("SELECT id, payment_type FROM payments WHERE order_id=? AND status='pending_verification' ORDER BY created_at DESC LIMIT 1");
    $stmt->execute([$o['id']]);
    $payment = $stmt->fetch();
    $paymentIds[$o['id']] = $payment ? $payment : null;
}

// 🔥 🔥 STATISTIK 🔥 🔥
$stats = $db->query("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status='pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN payment_status='paid' THEN 1 ELSE 0 END) as paid,
        SUM(CASE WHEN payment_status='dp' THEN 1 ELSE 0 END) as dp,
        SUM(CASE WHEN payment_status='pending_verification' THEN 1 ELSE 0 END) as verification
    FROM orders
")->fetch();

$pageTitle = 'Pesanan';
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
.admin-sidebar ul { list-style: none; padding: 0; }
.admin-sidebar ul li { margin-bottom: 4px; }
.admin-sidebar ul li a {
    display: block;
    padding: 8px 12px;
    color: #bdc3c7;
    text-decoration: none;
    border-radius: 4px;
    font-size: 14px;
    transition: all 0.3s;
}
.admin-sidebar ul li a:hover { background: rgba(255,255,255,0.1); color: #fff; }
.admin-sidebar ul li a.active { background: #f39c12; color: #fff; }

.admin-main { flex: 1; min-width: 0; }
.admin-main h1 { font-size: 24px; color: #2c3e50; margin-bottom: 20px; }

/* STATS */
.stats-mini {
    display: flex;
    gap: 15px;
    flex-wrap: wrap;
    margin-bottom: 20px;
}
.stats-mini .stat-item {
    background: #fff;
    padding: 10px 18px;
    border-radius: 8px;
    box-shadow: 0 1px 4px rgba(0,0,0,0.06);
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 14px;
}
.stats-mini .stat-item .number {
    font-weight: bold;
    font-size: 18px;
}
.stats-mini .stat-item .label { color: #6c757d; }

/* FILTER */
.filter-bar {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    margin-bottom: 20px;
    background: #fff;
    padding: 15px;
    border-radius: 8px;
    box-shadow: 0 1px 4px rgba(0,0,0,0.06);
    align-items: center;
}
.filter-bar select, .filter-bar input {
    padding: 8px 12px;
    border: 1px solid #ddd;
    border-radius: 6px;
    font-size: 13px;
}
.filter-bar .btn {
    padding: 8px 16px;
    border-radius: 6px;
    border: none;
    cursor: pointer;
    font-size: 13px;
}
.filter-bar .btn-primary { background: #2c3e50; color: #fff; }
.filter-bar .btn-primary:hover { background: #1a252f; }
.filter-bar .btn-success { background: #27ae60; color: #fff; }
.filter-bar .btn-success:hover { background: #1e8449; }
.filter-bar .btn-outline { background: #fff; color: #2c3e50; border: 1px solid #2c3e50; }
.filter-bar .btn-outline:hover { background: #f8f9fa; }

/* TABLE */
.table-wrapper {
    overflow-x: auto;
    background: #fff;
    border-radius: 8px;
    box-shadow: 0 1px 4px rgba(0,0,0,0.06);
}
.table {
    width: 100%;
    border-collapse: collapse;
    font-size: 13px;
}
.table thead { background: #f8f9fa; }
.table th {
    padding: 10px 12px;
    text-align: left;
    font-weight: 600;
    color: #6c757d;
    border-bottom: 2px solid #dee2e6;
    white-space: nowrap;
}
.table td { padding: 10px 12px; border-bottom: 1px solid #f1f3f5; vertical-align: middle; }
.table tbody tr:hover { background: #f8f9fa; }

.status-badge {
    display: inline-block;
    padding: 3px 10px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 600;
}
.status-pending { background: #f39c12; color: #fff; }
.status-desain { background: #8e44ad; color: #fff; }
.status-processed { background: #3498db; color: #fff; }
.status-printing { background: #2c3e50; color: #fff; }
.status-done { background: #27ae60; color: #fff; }
.status-cancelled { background: #e74c3c; color: #fff; }
.status-unpaid { background: #95a5a6; color: #fff; }
.status-dp { background: #f39c12; color: #fff; }
.status-paid { background: #27ae60; color: #fff; }
.status-pending_verification { background: #3498db; color: #fff; }

.btn-sm { padding: 3px 8px; font-size: 11px; border-radius: 4px; border: none; cursor: pointer; }
.btn-success { background: #27ae60; color: #fff; }
.btn-success:hover { background: #1e8449; }
.btn-warning { background: #f39c12; color: #fff; }
.btn-warning:hover { background: #d68910; }
.btn-danger { background: #e74c3c; color: #fff; }
.btn-danger:hover { background: #c0392b; }
.btn-info { background: #3498db; color: #fff; }
.btn-info:hover { background: #2c81ba; }
.btn-outline { background: #fff; color: #2c3e50; border: 1px solid #2c3e50; }
.btn-outline:hover { background: #f8f9fa; }

/* PAGINATION */
.pagination {
    display: flex;
    gap: 6px;
    justify-content: center;
    margin-top: 15px;
    flex-wrap: wrap;
}
.pagination a, .pagination span {
    padding: 6px 12px;
    border: 1px solid #ddd;
    border-radius: 4px;
    text-decoration: none;
    color: #2c3e50;
    font-size: 13px;
}
.pagination a:hover { background: #f8f9fa; }
.pagination .active { background: #2c3e50; color: #fff; border-color: #2c3e50; }

/* ALERT */
.alert {
    padding: 12px 15px;
    border-radius: 6px;
    margin-bottom: 15px;
}
.alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
.alert-danger { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }

/* RESPONSIVE */
@media (max-width: 768px) {
    .admin-layout { flex-direction: column; }
    .admin-sidebar { width: 100%; position: relative; top: 0; }
    .admin-sidebar ul { display: flex; flex-wrap: wrap; gap: 4px; }
    .admin-sidebar ul li a { padding: 6px 12px; font-size: 13px; }
    .filter-bar { flex-direction: column; align-items: stretch; }
    .stats-mini { gap: 8px; }
    .stats-mini .stat-item { padding: 8px 12px; font-size: 12px; }
}
</style>

<div class="admin-layout">
    <aside class="admin-sidebar">
        <h2>Admin Panel</h2>
        <ul>
            <li><a href="dashboard.php">Dashboard</a></li>
            <li><a href="products.php">Produk</a></li>
            <li><a href="orders.php" class="active">Pesanan</a></li>
            <li><a href="settings.php">Pengaturan</a></li>
            <li><a href="logout.php">Logout</a></li>
        </ul>
    </aside>
    <main class="admin-main">
        <h1>📋 Pesanan</h1>
        
        <!-- 🔥 ALERT -->
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success"><?= $_SESSION['success']; unset($_SESSION['success']); ?></div>
        <?php endif; ?>
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger"><?= $_SESSION['error']; unset($_SESSION['error']); ?></div>
        <?php endif; ?>
        
        <!-- 🔥 STATISTIK -->
        <div class="stats-mini">
            <div class="stat-item">
                <span class="number"><?= $stats['total'] ?? 0 ?></span>
                <span class="label">📦 Total</span>
            </div>
            <div class="stat-item">
                <span class="number" style="color:#f39c12;"><?= $stats['pending'] ?? 0 ?></span>
                <span class="label">⏳ Pending</span>
            </div>
            <div class="stat-item">
                <span class="number" style="color:#27ae60;"><?= $stats['paid'] ?? 0 ?></span>
                <span class="label">✅ Lunas</span>
            </div>
            <div class="stat-item">
                <span class="number" style="color:#f39c12;"><?= $stats['dp'] ?? 0 ?></span>
                <span class="label">💰 DP</span>
            </div>
            <div class="stat-item">
                <span class="number" style="color:#3498db;"><?= $stats['verification'] ?? 0 ?></span>
                <span class="label">⏳ Verifikasi</span>
            </div>
        </div>
        
        <!-- 🔥 FILTER -->
        <form method="GET" class="filter-bar">
            <select name="status" onchange="this.form.submit()">
                <option value="">Semua Status</option>
                <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>Pending</option>
                <option value="desain" <?= $statusFilter === 'desain' ? 'selected' : '' ?>>Proses Desain</option>
                <option value="processed" <?= $statusFilter === 'processed' ? 'selected' : '' ?>>Diproses</option>
                <option value="printing" <?= $statusFilter === 'printing' ? 'selected' : '' ?>>Cetak</option>
                <option value="done" <?= $statusFilter === 'done' ? 'selected' : '' ?>>Selesai</option>
                <option value="cancelled" <?= $statusFilter === 'cancelled' ? 'selected' : '' ?>>Dibatalkan</option>
            </select>
            
            <select name="payment" onchange="this.form.submit()">
                <option value="">Semua Pembayaran</option>
                <option value="unpaid" <?= $paymentFilter === 'unpaid' ? 'selected' : '' ?>>Belum</option>
                <option value="pending_verification" <?= $paymentFilter === 'pending_verification' ? 'selected' : '' ?>>Verifikasi</option>
                <option value="dp" <?= $paymentFilter === 'dp' ? 'selected' : '' ?>>DP</option>
                <option value="paid" <?= $paymentFilter === 'paid' ? 'selected' : '' ?>>Lunas</option>
            </select>
            
            <input type="text" name="search" placeholder="🔍 Cari kode/nama..." value="<?= htmlspecialchars($search) ?>">
            
            <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Filter</button>
            <a href="orders.php" class="btn btn-outline"><i class="fas fa-undo"></i> Reset</a>
            
            <button type="submit" name="export_csv" value="1" class="btn btn-success" style="margin-left:auto;">
                <i class="fas fa-file-export"></i> Export CSV
            </button>
        </form>
        
        <!-- 🔥 TABLE -->
        <div class="table-wrapper">
            <table class="table">
                <thead>
                    <tr>
                        <th>Kode</th>
                        <th>Pembeli</th>
                        <th>Total</th>
                        <th>Dibayar</th>
                        <th>Sisa</th>
                        <th>Status</th>
                        <th>Pembayaran</th>
                        <th>Tanggal</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orders as $o): 
                        $sisa = $o['total'] - $o['total_paid'];
                        $paymentData = $paymentIds[$o['id']] ?? null;
                        $payment_id = $paymentData ? $paymentData['id'] : null;
                        $payment_type = $paymentData ? $paymentData['payment_type'] : null;
                    ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($o['order_code']) ?></strong></td>
                        <td>
                            <strong><?= htmlspecialchars($o['customer_name']) ?></strong><br>
                            <small><?= htmlspecialchars($o['customer_phone']) ?></small>
                        </td>
                        <td><?= formatRupiah($o['total']) ?></td>
                        <td><?= formatRupiah($o['total_paid']) ?></td>
                        <td>
                            <strong style="color: <?= $sisa > 0 ? '#e74c3c' : '#27ae60' ?>">
                                <?= formatRupiah($sisa) ?>
                            </strong>
                            <?php if ($sisa > 0): ?>
                                <br><small style="color:#999;">(<?= round(($o['total_paid']/$o['total'])*100) ?>%)</small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="status-badge status-<?= $o['status'] ?>"><?= ucfirst($o['status']) ?></span>
                            <form method="POST" style="margin-top:5px;">
                                <input type="hidden" name="order_id" value="<?= $o['id'] ?>">
                                <input type="hidden" name="return_to" value="orders.php">
                                <select name="status" style="padding:3px 6px;font-size:11px;border:1px solid #ddd;border-radius:4px;">
                                    <option value="pending" <?= $o['status']==='pending'?'selected':'' ?>>Pending</option>
                                    <option value="desain" <?= $o['status']==='desain'?'selected':'' ?>>Desain</option>
                                    <option value="processed" <?= $o['status']==='processed'?'selected':'' ?>>Diproses</option>
                                    <option value="printing" <?= $o['status']==='printing'?'selected':'' ?>>Cetak</option>
                                    <option value="done" <?= $o['status']==='done'?'selected':'' ?>>Selesai</option>
                                    <option value="cancelled" <?= $o['status']==='cancelled'?'selected':'' ?>>Batal</option>
                                </select>
                                <button type="submit" name="update_status" class="btn btn-sm btn-outline">OK</button>
                            </form>
                        </td>
                        <td>
                            <span class="status-badge status-<?= $o['payment_status'] ?>">
                                <?php
                                $pl = ['unpaid'=>'Belum','pending_verification'=>'Verifikasi','paid'=>'Lunas','dp'=>'DP'];
                                echo $pl[$o['payment_status']] ?? $o['payment_status'];
                                ?>
                            </span>
                            
                            <form method="POST" style="margin-top:5px;">
                                <input type="hidden" name="order_id" value="<?= $o['id'] ?>">
                                <input type="hidden" name="return_to" value="orders.php">
                                <select name="payment_status" style="padding:3px 6px;font-size:11px;border:1px solid #ddd;border-radius:4px;">
                                    <option value="unpaid" <?= $o['payment_status']==='unpaid'?'selected':'' ?>>Belum</option>
                                    <option value="pending_verification" <?= $o['payment_status']==='pending_verification'?'selected':'' ?>>Verifikasi</option>
                                    <option value="dp" <?= $o['payment_status']==='dp'?'selected':'' ?>>DP</option>
                                    <option value="paid" <?= $o['payment_status']==='paid'?'selected':'' ?>>Lunas</option>
                                </select>
                                <button type="submit" name="update_payment" class="btn btn-sm btn-outline">OK</button>
                            </form>
                            
                            <!-- 🔥 TOMBOL VERIFIKASI -->
                            <?php if ($o['payment_status'] == 'pending_verification' && $payment_id): ?>
                                <div style="margin-top:5px; display:flex; gap:4px; flex-wrap:wrap;">
                                    <?php if ($payment_type): ?>
                                        <small style="width:100%;color:#666;font-size:9px;">
                                            Jenis: <?= $payment_type === 'pelunasan' ? '✅ Pelunasan' : '💰 DP' ?>
                                        </small>
                                    <?php endif; ?>
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="order_id" value="<?= $o['id'] ?>">
                                        <input type="hidden" name="payment_id" value="<?= $payment_id ?>">
                                        <input type="hidden" name="return_to" value="orders.php">
                                        <button type="submit" name="verify_payment" class="btn btn-sm btn-success">✅</button>
                                    </form>
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="order_id" value="<?= $o['id'] ?>">
                                        <input type="hidden" name="payment_id" value="<?= $payment_id ?>">
                                        <input type="hidden" name="return_to" value="orders.php">
                                        <button type="submit" name="verify_dp" class="btn btn-sm btn-warning">💰</button>
                                    </form>
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="order_id" value="<?= $o['id'] ?>">
                                        <input type="hidden" name="payment_id" value="<?= $payment_id ?>">
                                        <input type="hidden" name="return_to" value="orders.php">
                                        <button type="submit" name="reject_payment" class="btn btn-sm btn-danger">✕</button>
                                    </form>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($o['status'] == 'desain'): ?>
                                <form method="POST" style="margin-top:5px;">
                                    <input type="hidden" name="order_id" value="<?= $o['id'] ?>">
                                    <input type="hidden" name="return_to" value="orders.php">
                                    <button type="submit" name="design_done" class="btn btn-sm btn-info">🎨</button>
                                </form>
                            <?php endif; ?>
                        </td>
                        <td><?= date('d/m/Y H:i', strtotime($o['created_at'])) ?></td>
                        <td>
                            <a href="order-detail.php?id=<?= $o['id'] ?>" class="btn btn-sm btn-outline">Detail</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($orders)): ?>
                    <tr><td colspan="9" style="text-align:center;padding:30px;color:#999;">Belum ada pesanan</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- 🔥 PAGINATION -->
        <?php if ($totalPages > 1): ?>
        <div class="pagination">
            <?php if ($page > 1): ?>
                <a href="?page=<?= $page-1 ?>&status=<?= urlencode($statusFilter) ?>&payment=<?= urlencode($paymentFilter) ?>&search=<?= urlencode($search) ?>">&laquo; Sebelumnya</a>
            <?php endif; ?>
            
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <?php if ($i == $page): ?>
                    <span class="active"><?= $i ?></span>
                <?php else: ?>
                    <a href="?page=<?= $i ?>&status=<?= urlencode($statusFilter) ?>&payment=<?= urlencode($paymentFilter) ?>&search=<?= urlencode($search) ?>"><?= $i ?></a>
                <?php endif; ?>
            <?php endfor; ?>
            
            <?php if ($page < $totalPages): ?>
                <a href="?page=<?= $page+1 ?>&status=<?= urlencode($statusFilter) ?>&payment=<?= urlencode($paymentFilter) ?>&search=<?= urlencode($search) ?>">Selanjutnya &raquo;</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        
        <p style="margin-top:10px;font-size:12px;color:#999;text-align:center;">
            Menampilkan <?= count($orders) ?> dari <?= $totalOrders ?> pesanan
        </p>
    </main>
</div>
<?php include '../includes/footer.php'; ?>