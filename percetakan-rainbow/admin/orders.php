<?php
require_once __DIR__ . '/../config.php';
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
    
    // 🔥 🔥 HAPUS MASSAL 🔥 🔥
    if (isset($_POST['delete_bulk'])) {
        $password = $_POST['admin_password'] ?? '';
        if (!password_verify($password, ADMIN_PASSWORD_HASH)) {
            $_SESSION['error'] = "❌ Password admin salah! Penghapusan dibatalkan.";
            header('Location: ' . $returnTo);
            exit;
        }
        $ids = $_POST['order_ids'] ?? [];
        if (!is_array($ids) || empty($ids)) {
            $_SESSION['error'] = "❌ Tidak ada pesanan yang dipilih!";
            header('Location: ' . $returnTo);
            exit;
        }
        $ids = array_map('intval', $ids);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $db->beginTransaction();
        try {
            $db->prepare("DELETE FROM payments WHERE order_id IN ($placeholders)")->execute($ids);
            $db->prepare("DELETE FROM order_items WHERE order_id IN ($placeholders)")->execute($ids);
            $db->prepare("DELETE FROM orders WHERE id IN ($placeholders)")->execute($ids);
            $db->commit();
            $_SESSION['success'] = "🗑️ " . count($ids) . " pesanan berhasil dihapus!";
        } catch (Exception $e) {
            $db->rollBack();
            $_SESSION['error'] = "❌ Gagal menghapus: " . $e->getMessage();
        }
        header('Location: ' . $returnTo);
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
        <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;margin-bottom:15px;">
            <form method="GET" class="filter-bar" style="flex:1;margin-bottom:0;">
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
            </form>
            <form method="POST">
                <button type="submit" name="export_csv" value="1" class="btn btn-success">
                    <i class="fas fa-file-export"></i> Export CSV
                </button>
            </form>
        </div>
        
        <!-- 🔥 TABLE -->
        <div class="table-wrapper">
            <table class="table">
                <thead>
                    <tr>
                        <th style="width:30px;"><input type="checkbox" id="select-all" onclick="toggleAll(this)"></th>
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
                        <td><input type="checkbox" class="order-checkbox" value="<?= $o['id'] ?>"></td>
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
                    <tr><td colspan="10" style="text-align:center;padding:30px;color:#999;">Belum ada pesanan</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- 🔥 HAPUS MASSAL -->
        <div style="margin:10px 0;display:flex;gap:10px;align-items:center;">
            <span id="selected-count" style="font-size:13px;color:#666;">0 dipilih</span>
            <button type="button" class="btn btn-danger" onclick="confirmDelete()" id="deleteSelectedBtn" disabled>
                🗑️ Hapus Terpilih
            </button>
        </div>
        
        <!-- 🔥 MODAL KONFIRMASI PASSWORD -->
        <div id="deleteModal" style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.5);z-index:9999;align-items:center;justify-content:center;">
            <div style="background:#fff;padding:30px;border-radius:12px;max-width:400px;width:90%;box-shadow:0 10px 40px rgba(0,0,0,0.2);">
                <h3 style="margin-bottom:15px;">🔒 Konfirmasi Hapus</h3>
                <p style="font-size:14px;color:#666;margin-bottom:20px;">Masukkan password admin untuk menghapus <strong id="modal-count">0</strong> pesanan. Tindakan ini tidak bisa dibatalkan!</p>
                <form method="POST" id="deleteForm">
                    <input type="hidden" name="delete_bulk" value="1">
                    <div id="selected-ids-container"></div>
                    <input type="password" name="admin_password" placeholder="Password admin" required style="width:100%;padding:10px 14px;border:2px solid #ddd;border-radius:8px;font-size:16px;margin-bottom:15px;">
                    <div style="display:flex;gap:10px;">
                        <button type="button" class="btn btn-outline" style="flex:1;" onclick="closeModal()">Batal</button>
                        <button type="submit" class="btn btn-danger" style="flex:1;">🗑️ Hapus</button>
                    </div>
                </form>
            </div>
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
<script>
function toggleAll(source) {
    document.querySelectorAll('.order-checkbox').forEach(function(cb) { cb.checked = source.checked; });
    updateSelectedCount();
}
function updateSelectedCount() {
    var checked = document.querySelectorAll('.order-checkbox:checked');
    var count = checked.length;
    document.getElementById('selected-count').textContent = count + ' dipilih';
    document.getElementById('deleteSelectedBtn').disabled = count === 0;
}
function confirmDelete() {
    var checked = document.querySelectorAll('.order-checkbox:checked');
    if (checked.length === 0) return;
    document.getElementById('modal-count').textContent = checked.length;
    var container = document.getElementById('selected-ids-container');
    container.innerHTML = '';
    checked.forEach(function(cb) {
        var input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'order_ids[]';
        input.value = cb.value;
        container.appendChild(input);
    });
    document.getElementById('deleteModal').style.display = 'flex';
}
function closeModal() {
    document.getElementById('deleteModal').style.display = 'none';
}
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.order-checkbox').forEach(function(cb) { cb.addEventListener('change', updateSelectedCount); });
});
</script>
<?php include '../includes/footer.php'; ?>