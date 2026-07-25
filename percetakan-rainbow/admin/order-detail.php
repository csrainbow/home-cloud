<?php
require_once __DIR__ . '/../config.php';
if (!isAdmin()) redirect('/admin/index.php');

$id = $_GET['id'] ?? 0;
$stmt = $db->prepare("SELECT * FROM orders WHERE id=?");
$stmt->execute([$id]);
$order = $stmt->fetch();
if (!$order) redirect('/admin/orders.php');

$items = $db->prepare("SELECT * FROM order_items WHERE order_id=?");
$items->execute([$id]);
$items = $items->fetchAll();

$payments = $db->prepare("SELECT * FROM payments WHERE order_id=? ORDER BY created_at DESC");
$payments->execute([$id]);
$payments = $payments->fetchAll();

// Hitung total pembayaran yang sudah terverifikasi
$totalPaidStmt = $db->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM payments WHERE order_id=? AND status IN ('verified','approved','paid')");
$totalPaidStmt->execute([$id]);
$totalPaid = floatval($totalPaidStmt->fetch()['total']);
$sisaPembayaran = $order['total'] - $totalPaid;
$persentaseDibayar = $order['total'] > 0 ? round(($totalPaid / $order['total']) * 100) : 0;

// 🔥 Ambil payment_type terakhir untuk informasi
$lastPaymentType = $db->prepare("SELECT payment_type FROM payments WHERE order_id=? AND status IN ('verified','approved','paid') ORDER BY created_at DESC LIMIT 1");
$lastPaymentType->execute([$id]);
$lastPaymentType = $lastPaymentType->fetch();
$lastPaymentType = $lastPaymentType ? $lastPaymentType['payment_type'] : null;

// 🔥 CEK JASA DESAIN
$hasJasaStmt = $db->prepare("SELECT COUNT(*) as c FROM order_items WHERE order_id=? AND design_service='jasa'");
$hasJasaStmt->execute([$id]);
$hasJasa = $hasJasaStmt->fetch()['c'] > 0;

// 🔥 CEK INVOICE STATUS
$canPublishInvoice = false;
if ($hasJasa) {
    $canPublishInvoice = in_array($order['payment_status'], ['dp','paid']) && in_array($order['status'], ['desain','processed','printing','done']);
} else {
    $canPublishInvoice = in_array($order['payment_status'], ['dp','paid']) && in_array($order['status'], ['processed','printing','done']);
}

// 🔥 CEK STATUS PEMBAYARAN UNTUK TOMBOL
$isPaid = $order['payment_status'] === 'paid';
$isDp = $order['payment_status'] === 'dp';
$isPendingVerification = $order['payment_status'] === 'pending_verification';
$isUnpaid = $order['payment_status'] === 'unpaid';

// 🔥 PROSES UPLOAD HASIL DESAIN
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_design_result'])) {
    $orderId = intval($_POST['order_id']);
    if (isset($_FILES['design_result']) && $_FILES['design_result']['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES['design_result']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg','jpeg','png','pdf'];
        if (in_array($ext, $allowed)) {
            $uploadDir = __DIR__ . '/../uploads/designs/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
            $filename = 'result_' . $orderId . '_' . uniqid() . '.' . $ext;
            if (move_uploaded_file($_FILES['design_result']['tmp_name'], $uploadDir . $filename)) {
                $stmt = $db->prepare("UPDATE order_items SET design_result_file=? WHERE order_id=? AND design_service='jasa'");
                $stmt->execute([$filename, $orderId]);
                $db->prepare("UPDATE orders SET status='processed' WHERE id=?")->execute([$orderId]);

                $order = $db->prepare("SELECT * FROM orders WHERE id=?");
                $order->execute([$orderId]);
                $order = $order->fetch();

                $adminEmail = getSetting('admin_email');
                $notifyEmails = [];
                if ($adminEmail) $notifyEmails[] = $adminEmail;

                if ($order && $order['customer_id'] > 0) {
                    $cust = $db->prepare("SELECT email FROM customers WHERE id=?");
                    $cust->execute([$order['customer_id']]);
                    $c = $cust->fetch();
                    if ($c && $c['email']) $notifyEmails[] = $c['email'];
                }

                foreach ($notifyEmails as $to) {
                    $subject = '🎨 Hasil Desain Siap - ' . $order['order_code'];
                    $message = "Hasil desain untuk pesanan {$order['order_code']} sudah selesai.\n\n";
                    $message .= "Pelanggan: {$order['customer_name']}\n";
                    $message .= "File: " . $_FILES['design_result']['name'] . "\n";
                    $message .= "Link: https://rainbowprinting.web.id/uploads/designs/" . $filename . "\n";
                    $message .= "Waktu: " . date('d/m/Y H:i:s') . "\n\n";
                    $message .= "Pelanggan dapat mendownload file hasil desain di halaman detail pesanan.\n";
                    sendEmail($to, $subject, $message);
                }

                $_SESSION['success'] = "✅ Hasil desain berhasil diupload! Customer sudah diberi notifikasi.";
                echo '<script>location.href="order-detail.php?id=' . $orderId . '";</script>';
                exit;
            }
        }
    }
}

// 🔥 PROSES SIMPAN PRINTER
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_printer'])) {
    $printerType = trim($_POST['printer_type'] ?? '');
    $db->prepare("UPDATE orders SET printer_type=? WHERE id=?")->execute([$printerType, $id]);
    $_SESSION['success'] = "✅ Tipe printer berhasil disimpan!";
    echo '<script>location.href="order-detail.php?id=' . $id . '";</script>';
    exit;
}

// 🔥 KIRIM NOTIFIKASI KE CUSTOMER
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_notification'])) {
    $message = trim($_POST['notification_message'] ?? '');
    $sendWhatsApp = isset($_POST['send_whatsapp']) ? true : false;
    
    if (!empty($message)) {
        $customerEmail = $db->prepare("SELECT email FROM customers WHERE id=?");
        $customerEmail->execute([$order['customer_id']]);
        $customer = $customerEmail->fetch();
        
        if ($customer && $customer['email']) {
            $subject = '📧 Notifikasi Pesanan - ' . $order['order_code'];
            $fullMessage = "Halo " . $order['customer_name'] . ",\n\n";
            $fullMessage .= $message . "\n\n";
            $fullMessage .= "Pesanan: " . $order['order_code'] . "\n";
            $fullMessage .= "Status: " . ucfirst($order['status']) . "\n\n";
            $fullMessage .= "Terima kasih,\nRainbow Printing";
            
            $emailSent = sendEmail($customer['email'], $subject, $fullMessage);
            
            if ($emailSent) {
                $_SESSION['success'] = "✅ Email notifikasi berhasil dikirim ke customer!";
            } else {
                $_SESSION['error'] = "⚠️ Gagal mengirim email. Coba lagi.";
            }
            
            // 🔥 Kirim WhatsApp jika dicentang
            if ($sendWhatsApp && defined('WHATSAPP_NUMBER')) {
                $waMessage = urlencode($fullMessage);
                $waUrl = "https://wa.me/" . WHATSAPP_NUMBER . "?text=" . $waMessage;
                $_SESSION['wa_link'] = $waUrl;
            }
        } else {
            $_SESSION['error'] = "❌ Email customer tidak ditemukan!";
        }
        echo '<script>location.href="order-detail.php?id=' . $id . '";</script>';
        exit;
    }
}

$pageTitle = 'Detail Pesanan - ' . $order['order_code'];
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
.order-detail-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    margin-bottom: 20px;
}
.card {
    background: #fff;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 1px 4px rgba(0,0,0,0.06);
}
.card h3 {
    font-size: 16px;
    color: #2c3e50;
    margin-bottom: 12px;
    padding-bottom: 8px;
    border-bottom: 1px solid #eee;
}
.card p {
    margin: 6px 0;
    font-size: 14px;
}
.status-badge {
    display: inline-block;
    padding: 3px 12px;
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
.status-verified { background: #27ae60; color: #fff; }
.status-rejected { background: #e74c3c; color: #fff; }
.status-approved { background: #27ae60; color: #fff; }

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
.btn-primary { background: #2c3e50; color: #fff; }
.btn-primary:hover { background: #1a252f; }
.btn-success { background: #27ae60; color: #fff; }
.btn-success:hover { background: #1e8449; }
.btn-danger { background: #e74c3c; color: #fff; }
.btn-danger:hover { background: #c0392b; }
.btn-warning { background: #f39c12; color: #fff; }
.btn-warning:hover { background: #d68910; }
.btn-outline { background: #fff; color: #2c3e50; border: 1px solid #2c3e50; }
.btn-outline:hover { background: #f8f9fa; }
.btn-sm { padding: 4px 10px; font-size: 11px; }

.table {
    width: 100%;
    border-collapse: collapse;
    background: #fff;
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 1px 4px rgba(0,0,0,0.06);
}
.table thead { background: #f8f9fa; }
.table th { padding: 10px 12px; text-align: left; font-size: 12px; text-transform: uppercase; color: #6c757d; border-bottom: 2px solid #dee2e6; }
.table td { padding: 10px 12px; border-bottom: 1px solid #f1f3f5; font-size: 14px; }
.table tbody tr:hover { background: #f8f9fa; }
.table tfoot { background: #f8f9fa; font-weight: bold; }

.alert { padding: 12px 15px; border-radius: 6px; margin-bottom: 15px; }
.alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
.alert-danger { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
.alert-warning { background: #fff3cd; color: #856404; border: 1px solid #ffeeba; }
.alert-info { background: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; }

@media (max-width: 768px) {
    .admin-layout { flex-direction: column; }
    .admin-sidebar { width: 100%; position: relative; top: 0; }
    .admin-sidebar ul { display: flex; flex-wrap: wrap; gap: 4px; }
    .admin-sidebar ul li a { padding: 6px 12px; font-size: 13px; }
    .order-detail-grid { grid-template-columns: 1fr; }
}
</style>

<div class="admin-layout">
    <aside class="admin-sidebar">
        <h2>Admin Panel</h2>
        <ul>
            <li><a href="dashboard.php">Dashboard</a></li>
            <li><a href="products.php">Produk</a></li>
            <li><a href="orders.php" class="active">Pesanan</a></li>
            <li><a href="edit-halaman.php?slug=tentang-kami">Tentang Kami</a></li>
            <li><a href="settings.php">Pengaturan</a></li>
            <li><a href="logout.php">Logout</a></li>
        </ul>
    </aside>
    <main class="admin-main">
        <!-- 🔥 ALERT MESSAGES -->
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success"><?= $_SESSION['success']; unset($_SESSION['success']); ?></div>
        <?php endif; ?>
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger"><?= $_SESSION['error']; unset($_SESSION['error']); ?></div>
        <?php endif; ?>
        <?php if (isset($_SESSION['wa_link'])): ?>
            <div class="alert alert-info">
                <strong>📱 Link WhatsApp:</strong> 
                <a href="<?= $_SESSION['wa_link'] ?>" target="_blank">Klik untuk kirim via WhatsApp</a>
                <?php unset($_SESSION['wa_link']); ?>
            </div>
        <?php endif; ?>
        
        <h1>📋 Detail Pesanan: <?= htmlspecialchars($order['order_code']) ?></h1>
        
        <!-- 🔥 INFO PEMBAYARAN RINGKAS -->
        <div style="background:<?= $sisaPembayaran > 0 ? '#fef9e7' : '#e8f5e9' ?>;padding:15px;border-radius:8px;border:1px solid <?= $sisaPembayaran > 0 ? '#f39c12' : '#27ae60' ?>;margin-bottom:20px;">
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:10px;">
                <div>
                    <strong style="color:#555;">Total Pesanan</strong><br>
                    <span style="font-size:20px;font-weight:bold;"><?= formatRupiah($order['total']) ?></span>
                </div>
                <div>
                    <strong style="color:#555;">Sudah Dibayar</strong><br>
                    <span style="font-size:20px;font-weight:bold;color:#27ae60;"><?= formatRupiah($totalPaid) ?></span>
                </div>
                <div>
                    <strong style="color:#555;">Sisa Pembayaran</strong><br>
                    <span style="font-size:20px;font-weight:bold;color:<?= $sisaPembayaran > 0 ? '#e74c3c' : '#27ae60' ?>;">
                        <?= formatRupiah($sisaPembayaran) ?>
                    </span>
                </div>
                <div>
                    <strong style="color:#555;">Status</strong><br>
                    <span style="font-size:16px;font-weight:bold;color:<?= $sisaPembayaran > 0 ? '#f39c12' : '#27ae60' ?>;">
                        <?= $sisaPembayaran > 0 ? '💰 DP (' . $persentaseDibayar . '%)' : '✅ LUNAS' ?>
                    </span>
                    <?php if ($lastPaymentType): ?>
                        <br><small style="color:#666;">Jenis: <?= $lastPaymentType === 'pelunasan' ? 'Pelunasan' : 'DP' ?></small>
                    <?php endif; ?>
                </div>
            </div>
            <?php if ($sisaPembayaran > 0): ?>
                <div style="margin-top:10px;background:#fff;border-radius:4px;height:8px;overflow:hidden;">
                    <div style="width:<?= $persentaseDibayar ?>%;height:100%;background:linear-gradient(90deg,#f39c12,#e67e22);"></div>
                </div>
                <small style="color:#999;"><?= $persentaseDibayar ?>% dari total sudah dibayar</small>
            <?php endif; ?>
        </div>
        
        <!-- 🔥 ORDER & CUSTOMER INFO -->
        <div class="order-detail-grid">
            <div class="card">
                <h3>👤 Data Pembeli</h3>
                <p><strong>Nama:</strong> <?= htmlspecialchars($order['customer_name']) ?></p>
                <p><strong>WhatsApp:</strong> <?= htmlspecialchars($order['customer_phone']) ?></p>
                <p><strong>Alamat:</strong> <?= nl2br(htmlspecialchars($order['customer_address'])) ?></p>
                <p><strong>Catatan:</strong> <?= nl2br(htmlspecialchars($order['notes'])) ?></p>
            </div>
            <div class="card">
                <h3>📊 Status</h3>
                <p><strong>Pesanan:</strong> <span class="status-badge status-<?= $order['status'] ?>"><?= ucfirst($order['status']) ?></span></p>
                <p><strong>Pembayaran:</strong> <span class="status-badge status-<?= $order['payment_status'] ?>">
                    <?php
                    $pl = ['unpaid'=>'Belum','pending_verification'=>'Verifikasi','paid'=>'Lunas','dp'=>'DP'];
                    echo $pl[$order['payment_status']] ?? ucfirst($order['payment_status']);
                    ?>
                </span></p>
                <p><strong>Metode:</strong> <?php
                $ml = ['transfer'=>'Transfer Bank','cod'=>'COD','qris'=>'QRIS','midtrans'=>'Midtrans'];
                echo $ml[$order['payment_method']] ?? ucfirst($order['payment_method']);
                ?></p>
                <p><strong>Tanggal:</strong> <?= date('d/m/Y H:i', strtotime($order['created_at'])) ?></p>
                
                <!-- 🔥 TIPE PRINTER -->
                <form method="POST" style="margin-top:10px;padding-top:10px;border-top:1px solid #eee;">
                    <p style="font-size:13px;margin-bottom:5px;"><strong>🖨️ Tipe Printer:</strong></p>
                    <div style="display:flex;gap:6px;">
                        <select name="printer_type" style="flex:1;padding:6px;font-size:13px;border:1px solid #ddd;border-radius:4px;">
                            <option value="">- Pilih -</option>
                            <?php
                            $printerOpts = getSetting('printer_options') ?: 'In-Fus/Solvent,Digital Printing,Offset,UV Printer,Sablon';
                            foreach (explode(',', $printerOpts) as $opt):
                                $opt = trim($opt);
                                $val = strtolower(str_replace([' ','/'], ['-','-'], $opt));
                            ?>
                            <option value="<?= $val ?>" <?= $order['printer_type'] === $val ? 'selected' : '' ?>><?= htmlspecialchars($opt) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" name="save_printer" value="1" class="btn btn-primary btn-sm">Simpan</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- 🔥 BUKTI PEMBAYARAN -->
        <?php if (!empty($payments)): ?>
        <h2 style="margin-top:20px;">💰 Bukti Pembayaran</h2>
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:15px;margin-bottom:20px;">
            <?php 
            $runningTotal = 0;
            foreach ($payments as $p): 
                $runningTotal += floatval($p['amount']);
                $isDpPayment = ($runningTotal < $order['total']);
                $paymentTypeLabel = '';
                if ($p['payment_type'] === 'dp') $paymentTypeLabel = $isDpPayment ? '💰 DP' : '✅ Lunas';
                elseif ($p['payment_type'] === 'pelunasan') $paymentTypeLabel = '✅ Pelunasan';
            ?>
            <div class="card" style="position:relative;padding-top:25px;">
                <?php if ($p['status'] === 'verified' || $p['status'] === 'approved' || $p['status'] === 'paid'): ?>
                    <?php if ($isDpPayment): ?>
                        <span style="position:absolute;top:-8px;right:10px;background:#f39c12;color:#fff;padding:3px 14px;border-radius:20px;font-size:11px;font-weight:bold;">💰 DP</span>
                    <?php else: ?>
                        <span style="position:absolute;top:-8px;right:10px;background:#27ae60;color:#fff;padding:3px 14px;border-radius:20px;font-size:11px;font-weight:bold;">✅ LUNAS</span>
                    <?php endif; ?>
                <?php elseif ($p['status'] === 'rejected'): ?>
                    <span style="position:absolute;top:-8px;right:10px;background:#e74c3c;color:#fff;padding:3px 14px;border-radius:20px;font-size:11px;font-weight:bold;">❌ DITOLAK</span>
                <?php else: ?>
                    <span style="position:absolute;top:-8px;right:10px;background:#95a5a6;color:#fff;padding:3px 14px;border-radius:20px;font-size:11px;font-weight:bold;">⏳ <?= ucfirst($p['status']) ?></span>
                <?php endif; ?>
                
                <?php if ($paymentTypeLabel): ?>
                    <div style="margin-bottom:6px;font-size:12px;color:#666;"><?= $paymentTypeLabel ?></div>
                <?php endif; ?>
                
                <a href="/uploads/proofs/<?= htmlspecialchars($p['proof_image']) ?>" target="_blank">
                    <img src="/uploads/proofs/<?= htmlspecialchars($p['proof_image']) ?>" style="width:100%;border-radius:6px;margin-bottom:8px;border:1px solid #eee;">
                </a>
                <p><strong><?= htmlspecialchars($p['bank_name']) ?></strong> — <?= htmlspecialchars($p['account_number']) ?></p>
                <p>a.n. <?= htmlspecialchars($p['account_name']) ?></p>
                <p><strong>Jumlah:</strong> <?= formatRupiah($p['amount']) ?></p>
                
                <?php if ($p['status'] === 'verified' || $p['status'] === 'approved' || $p['status'] === 'paid'): ?>
                    <?php if ($isDpPayment): ?>
                        <p style="font-size:12px;color:#f39c12;margin-top:-5px;">
                            <strong>Sisa:</strong> <?= formatRupiah($order['total'] - $runningTotal) ?>
                            <br><small>(<?= round(($runningTotal/$order['total'])*100) ?>% dari total)</small>
                        </p>
                    <?php else: ?>
                        <p style="font-size:12px;color:#27ae60;margin-top:-5px;">
                            <strong>✅ Lunas</strong> — <?= round(($runningTotal/$order['total'])*100) ?>% dari total
                        </p>
                    <?php endif; ?>
                <?php endif; ?>
                
                <p>Status: <span class="status-badge status-<?= $p['status'] ?>"><?= ucfirst($p['status']) ?></span></p>
                
                <?php if ($p['status'] === 'pending'): ?>
                <form method="POST" action="orders.php" style="margin-top:8px;display:flex;gap:6px;flex-wrap:wrap;">
                    <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                    <input type="hidden" name="payment_id" value="<?= $p['id'] ?>">
                    <input type="hidden" name="return_to" value="order-detail.php?id=<?= $order['id'] ?>">
                    <button type="submit" name="verify_payment" value="1" class="btn btn-success btn-sm">✅ Verifikasi (Lunas)</button>
                    <button type="submit" name="verify_dp" value="1" class="btn btn-warning btn-sm">💰 Verifikasi (DP)</button>
                    <button type="submit" name="reject_payment" value="1" class="btn btn-danger btn-sm">✕ Tolak</button>
                </form>
                <?php endif; ?>
                <p style="font-size:12px;color:#999;margin-top:5px;"><?= date('d/m/Y H:i', strtotime($p['created_at'])) ?></p>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- 🔥 ITEM PESANAN -->
        <h2>📦 Item Pesanan</h2>
        <table class="table">
            <thead><tr><th>Produk</th><th>Bahan</th><th>Ukuran</th><th>Layanan Desain</th><th>Jumlah</th><th>Harga</th><th>Subtotal</th></tr></thead>
            <tbody>
                <?php foreach ($items as $item): ?>
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
                            <span style="display:inline-block;padding:3px 10px;background:#f39c12;color:#fff;border-radius:4px;font-size:12px;font-weight:bold;">Jasa Desain</span>
                        <?php elseif ($item['design_service'] === 'upload'): ?>
                            <span style="display:inline-block;padding:3px 10px;background:#3498db;color:#fff;border-radius:4px;font-size:12px;">Upload File</span>
                            <?php if ($item['design_file']): ?>
                                <br><a href="/uploads/designs/<?= htmlspecialchars($item['design_file']) ?>" target="_blank" style="font-size:11px;text-decoration:underline;">📎 <?= htmlspecialchars($item['design_original_name'] ?: 'Lihat File') ?></a>
                            <?php endif; ?>
                        <?php else: ?>
                            <span style="color:#999;font-size:12px;">-</span>
                        <?php endif; ?>
                        <?php if ($item['design_result_file']): ?>
                            <br><span style="font-size:11px;color:#27ae60;">✅ Hasil: <a href="/uploads/designs/<?= htmlspecialchars($item['design_result_file']) ?>" target="_blank" style="text-decoration:underline;">Download</a></span>
                        <?php endif; ?>
                    </td>
                    <td><?= $item['quantity'] ?></td>
                    <td><?= formatRupiah($item['price']) ?></td>
                    <td><?= formatRupiah($item['subtotal']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr><th colspan="5" style="text-align:right;">Total</th><th colspan="2"><?= formatRupiah($order['total']) ?></th></tr>
            </tfoot>
        </table>
        
        <!-- 🔥 UPLOAD HASIL DESAIN -->
        <?php if ($order['status'] === 'desain'): ?>
        <div style="margin-top:20px;padding:15px;background:#e8daef;border-radius:8px;border:1px solid #d2b4de;">
            <h3 style="margin:0 0 10px;color:#6c3483;">🎨 Upload Hasil Desain</h3>
            <form method="POST" enctype="multipart/form-data" action="" style="display:flex;gap:10px;align-items:end;flex-wrap:wrap;">
                <input type="hidden" name="upload_design_result" value="1">
                <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                <div>
                    <input type="file" name="design_result" accept=".jpg,.jpeg,.png,.pdf" required style="font-size:14px;">
                </div>
                <button type="submit" class="btn btn-primary">📤 Upload & Selesaikan Desain</button>
            </form>
            <p style="margin-top:8px;font-size:12px;color:#6c3483;">⚠️ Setelah upload, customer akan mendapat notifikasi email.</p>
        </div>
        <?php endif; ?>
        
        <!-- 🔥 INVOICE STATUS -->
        <div style="margin-top:20px;">
            <?php if ($canPublishInvoice): ?>
                <div style="padding:15px;background:#e8f5e9;border-radius:8px;border:1px solid #27ae60;">
                    <h3 style="margin:0 0 10px;color:#1e8e49;">✅ Syarat Terbitkan Invoice Terpenuhi</h3>
                    <p style="margin:0;font-size:13px;color:#27ae60;">
                        <?php if ($hasJasa): ?>
                            Pesanan menggunakan Jasa Desain — Pembayaran sudah <strong>Lunas</strong>.
                        <?php else: ?>
                            Pesanan tanpa Jasa Desain — <strong>DP sudah diterima/terverifikasi</strong>.
                        <?php endif; ?>
                    </p>
                    <a href="/invoice.php?order=<?= urlencode($order['order_code']) ?>" target="_blank" class="btn btn-success" style="margin-top:10px;">🧾 Terbitkan / Lihat Invoice</a>
                </div>
            <?php elseif (!$isPaid && !$isDp && $order['status'] !== 'done'): ?>
                <div style="padding:15px;background:#fef9e7;border-radius:8px;border:1px solid #f39c12;">
                    <h3 style="margin:0 0 10px;color:#b7950b;">⏳ Belum Bisa Terbitkan Invoice</h3>
                    <p style="margin:0;font-size:13px;color:#b7950b;">
                        <?php if ($hasJasa): ?>
                            Pesanan menggunakan Jasa Desain — Harus <strong>Lunas</strong> (status: <?= ucfirst($order['payment_status']) ?>).
                        <?php else: ?>
                            Pesanan tanpa Jasa Desain — Butuh <strong>DP terverifikasi</strong> (status: <?= ucfirst($order['payment_status']) ?>).
                        <?php endif; ?>
                    </p>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- 🔥 FORM KIRIM NOTIFIKASI -->
        <div style="margin-top:20px;padding:15px;background:#e8f0fe;border-radius:8px;border:1px solid #4a90d9;">
            <h3 style="margin:0 0 10px;color:#2c3e50;">📧 Kirim Notifikasi ke Customer</h3>
            <form method="POST" style="display:flex;gap:10px;flex-wrap:wrap;align-items:end;">
                <input type="hidden" name="send_notification" value="1">
                <div style="flex:1;min-width:200px;">
                    <textarea name="notification_message" rows="2" style="width:100%;padding:8px;border:1px solid #ddd;border-radius:4px;font-size:13px;" placeholder="Tulis pesan notifikasi...">Pesanan Anda sedang diproses. Terima kasih telah berbelanja di Rainbow Printing.</textarea>
                </div>
                <div style="display:flex;gap:8px;align-items:center;">
                    <label style="font-size:13px;cursor:pointer;">
                        <input type="checkbox" name="send_whatsapp" value="1"> 📱 Juga via WhatsApp
                    </label>
                    <button type="submit" class="btn btn-primary">📧 Kirim</button>
                </div>
            </form>
        </div>
        
        <!-- 🔥 ACTION BUTTONS -->
        <p style="margin-top:20px;display:flex;gap:10px;flex-wrap:wrap;">
            <a href="https://wa.me/<?= WHATSAPP_NUMBER ?>?text=Halo%20<?= urlencode($order['customer_name']) ?>%2C%20pesanan%20<?= $order['order_code'] ?>%20kami%20proses" target="_blank" class="btn btn-success">
                📱 Hubungi Pembeli
            </a>
            <a href="orders.php" class="btn btn-outline">← Kembali ke Daftar Pesanan</a>
        </p>
    </main>
</div>
<?php include '../includes/footer.php'; ?>