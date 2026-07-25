<?php
require_once __DIR__ . '/config.php';

$orderCode = $_GET['order'] ?? '';
if (empty($orderCode)) {
    header('Location: /index.php');
    exit;
}

$stmt = $db->prepare("SELECT * FROM orders WHERE order_code = ?");
$stmt->execute([$orderCode]);
$order = $stmt->fetch();

if (!$order) {
    header('Location: /index.php');
    exit;
}

// 🔥 HITUNG TOTAL PEMBAYARAN YANG SUDAH TERVERIFIKASI
$totalPaidStmt = $db->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM payments WHERE order_id=? AND status IN ('verified','approved','paid')");
$totalPaidStmt->execute([$order['id']]);
$totalPaid = floatval($totalPaidStmt->fetch()['total']);
$sisaPembayaran = $order['total'] - $totalPaid;

// Cek apakah pesanan pakai jasa desain
$hasJasaStmt = $db->prepare("SELECT COUNT(*) as c FROM order_items WHERE order_id=? AND design_service='jasa'");
$hasJasaStmt->execute([$order['id']]);
$hasJasaDesain = $hasJasaStmt->fetch()['c'] > 0;

// 🔥 VALIDASI AKSES INVOICE (Diperbaiki)
$canViewInvoice = false;

if ($hasJasaDesain) {
    // Jasa desain: invoice bisa diakses jika:
    // 1. Payment_status = 'paid' (LUNAS) ATAU
    // 2. Payment_status = 'dp' DAN status sudah 'desain' atau lebih
    if ($order['payment_status'] === 'paid') {
        $canViewInvoice = true;
    } elseif ($order['payment_status'] === 'dp' && in_array($order['status'], ['desain', 'processed', 'printing', 'done'])) {
        $canViewInvoice = true;
    }
} else {
    // Non jasa desain: invoice bisa diakses jika:
    // 1. Payment_status = 'paid' (LUNAS) ATAU
    // 2. Payment_status = 'dp' DAN status sudah 'processed' atau lebih
    if ($order['payment_status'] === 'paid') {
        $canViewInvoice = true;
    } elseif ($order['payment_status'] === 'dp' && in_array($order['status'], ['processed', 'printing', 'done'])) {
        $canViewInvoice = true;
    }
}

if (!$canViewInvoice) {
    $_SESSION['error'] = "Invoice belum dapat diakses. Pastikan pembayaran sudah lunas atau DP sudah diverifikasi.";
    header('Location: /customer/order-detail.php?order=' . urlencode($orderCode));
    exit;
}

$items = $db->prepare("SELECT * FROM order_items WHERE order_id=?");
$items->execute([$order['id']]);
$items = $items->fetchAll();

$payments = $db->prepare("SELECT * FROM payments WHERE order_id=? AND status IN ('verified','approved','paid') ORDER BY created_at DESC");
$payments->execute([$order['id']]);
$payments = $payments->fetchAll();

$storeName = getSetting('store_name') ?: SITE_NAME;
$storeAddress = getSetting('store_address') ?: '';
$storePhone = getSetting('store_phone') ?: '';
$adminEmail = getSetting('admin_email') ?: '';
$invoiceTemplate = getSetting('invoice_template') ?: 'classic';
$invoiceFooter = getSetting('invoice_footer') ?: 'Terima kasih telah berbelanja di ' . $storeName;

$pageTitle = 'Invoice ' . $order['order_code'];
include 'includes/header.php';

$printerLabels = [
    'in-fus-solvent' => 'In-Fus / Solvent',
    'digital-printing' => 'Digital Printing',
    'offset' => 'Offset',
    'uv-printer' => 'UV Printer',
    'sablon' => 'Sablon',
];
$printerText = $printerLabels[$order['printer_type']] ?? ($order['printer_type'] ?: '');

$grandTotal = floatval($order['total']);
$sisaAmount = $sisaPembayaran; // 🔥 Perbaikan: sisa pembayaran yang sebenarnya
$totalDibayar = $totalPaid;

$hasDesignService = false;
foreach ($items as $item) {
    if ($item['design_service'] === 'jasa' || $item['design_service'] === 'upload') {
        $hasDesignService = true;
        break;
    }
}
?>
<style>
<?php if ($invoiceTemplate === 'modern'): ?>
.invoice-wrapper { margin:20px auto;background:#fff;padding:30px;border-radius:12px;box-shadow:0 2px 20px rgba(0,0,0,0.08);width:100%;max-width:none; }
.invoice-head { display:flex;justify-content:space-between;align-items:start;margin-bottom:15px; }
.invoice-head-left { font-size:12px;color:#555; }
.invoice-head-left strong { font-size:16px;color:#667eea; }
.invoice-head-right { text-align:right;font-size:12px;color:#555; }
.invoice-head-right .inv-no { font-size:13px;font-weight:bold;color:#667eea;margin-top:5px; }
.invoice-mid { display:flex;justify-content:space-between;margin-bottom:15px;padding:12px;background:linear-gradient(135deg,#667eea,#764ba2);color:#fff;border-radius:8px;font-size:12px; }
.invoice-mid a { color:#fff; }
.invoice-table { width:100%;border-collapse:collapse;margin-bottom:10px; }
.invoice-table th { background:#667eea;color:#fff;padding:8px 10px;text-align:left;font-size:11px; }
.invoice-table td { padding:7px 10px;border-bottom:1px solid #eee;font-size:12px; }
.invoice-table .item-note { font-size:10px;color:#999; }
.invoice-bottom { display:flex;justify-content:space-between;margin-top:10px;gap:20px; }
.invoice-bottom-left { flex:1;font-size:11px;color:#555; }
.invoice-bottom-left ol { margin:5px 0;padding-left:16px; }
.invoice-bottom-left ol li { margin-bottom:3px; }
.invoice-bottom-right { min-width:200px;font-size:12px; }
.invoice-bottom-right table { width:100%;border-collapse:collapse; }
.invoice-bottom-right td { padding:4px 8px;border-bottom:1px solid #ddd; }
.invoice-bottom-right .total-row td { font-weight:bold;font-size:14px;border-top:2px solid #667eea; }
.invoice-sign { display:flex;justify-content:space-between;margin-top:30px; }
.invoice-sign div { text-align:center;min-width:180px;font-size:12px; }
.invoice-sign .sign-space { margin-bottom:50px; }
.invoice-footer2 { margin-top:20px;padding-top:10px;border-top:1px solid #ddd;text-align:center;font-size:10px;color:#999; }

<?php elseif ($invoiceTemplate === 'professional'): ?>
.invoice-wrapper { margin:20px auto;background:#fff;padding:30px;border:1px solid #ddd;width:100%;max-width:none; }
.invoice-head { display:flex;justify-content:space-between;align-items:start;margin-bottom:15px;border-bottom:3px solid #1a1a2e;padding-bottom:15px; }
.invoice-head-left { font-size:12px;color:#555; }
.invoice-head-left strong { font-size:16px;color:#1a1a2e;letter-spacing:2px; }
.invoice-head-right { text-align:right;font-size:12px;color:#555; }
.invoice-head-right .inv-no { font-size:13px;font-weight:bold;color:#1a1a2e;margin-top:5px;letter-spacing:1px; }
.invoice-mid { display:flex;justify-content:space-between;margin-bottom:15px;padding:10px;background:#f5f5f5;font-size:12px;border-left:4px solid #1a1a2e; }
.invoice-table { width:100%;border-collapse:collapse;margin-bottom:10px;border:1px solid #ddd; }
.invoice-table th { background:#1a1a2e;color:#fff;padding:8px 10px;text-align:left;font-size:10px;text-transform:uppercase; }
.invoice-table td { padding:7px 10px;border-bottom:1px solid #ddd;font-size:11px; }
.invoice-table .item-note { font-size:10px;color:#999; }
.invoice-bottom { display:flex;justify-content:space-between;margin-top:10px;gap:20px; }
.invoice-bottom-left { flex:1;font-size:11px;color:#555; }
.invoice-bottom-left ol { margin:5px 0;padding-left:16px; }
.invoice-bottom-left ol li { margin-bottom:3px; }
.invoice-bottom-right { min-width:200px;font-size:12px; }
.invoice-bottom-right table { width:100%;border-collapse:collapse; }
.invoice-bottom-right td { padding:4px 8px;border-bottom:1px solid #ddd; }
.invoice-bottom-right .total-row td { font-weight:bold;font-size:14px;border-top:2px solid #1a1a2e; }
.invoice-sign { display:flex;justify-content:space-between;margin-top:30px; }
.invoice-sign div { text-align:center;min-width:180px;font-size:12px; }
.invoice-sign .sign-space { margin-bottom:50px; }
.invoice-footer2 { margin-top:20px;padding-top:10px;border-top:2px solid #1a1a2e;text-align:center;font-size:10px;color:#999; }

<?php else: /* classic */ ?>
.invoice-wrapper { margin:20px auto;background:#fff;padding:30px;border-radius:8px;box-shadow:0 2px 12px rgba(0,0,0,0.06);width:100%;max-width:none; }
.invoice-head { display:flex;justify-content:space-between;align-items:start;margin-bottom:15px; }
.invoice-head-left { font-size:12px;color:#555; }
.invoice-head-left strong { font-size:15px;color:#2c3e50; }
.invoice-head-right { text-align:right;font-size:12px;color:#555; }
.invoice-head-right .inv-no { font-size:13px;font-weight:bold;color:#2c3e50;margin-top:5px; }
.invoice-mid { display:flex;justify-content:space-between;margin-bottom:15px;padding:10px 12px;background:#f8f9fa;border-radius:6px;font-size:12px; }
.invoice-table { width:100%;border-collapse:collapse;margin-bottom:10px; }
.invoice-table th { background:#2c3e50;color:#fff;padding:8px 10px;text-align:left;font-size:11px; }
.invoice-table td { padding:7px 10px;border-bottom:1px solid #eee;font-size:12px; }
.invoice-table .item-note { font-size:10px;color:#999; }
.invoice-bottom { display:flex;justify-content:space-between;margin-top:10px;gap:20px; }
.invoice-bottom-left { flex:1;font-size:11px;color:#555; }
.invoice-bottom-left ol { margin:5px 0;padding-left:16px; }
.invoice-bottom-left ol li { margin-bottom:3px; }
.invoice-bottom-right { min-width:200px;font-size:12px; }
.invoice-bottom-right table { width:100%;border-collapse:collapse; }
.invoice-bottom-right td { padding:4px 8px;border-bottom:1px solid #ddd; }
.invoice-bottom-right .total-row td { font-weight:bold;font-size:14px;border-top:2px solid #2c3e50; }
.invoice-sign { display:flex;justify-content:space-between;margin-top:30px; }
.invoice-sign div { text-align:center;min-width:180px;font-size:12px; }
.invoice-sign .sign-space { margin-bottom:50px; }
.invoice-footer2 { margin-top:20px;padding-top:10px;border-top:1px solid #ddd;text-align:center;font-size:10px;color:#999; }
<?php endif; ?>
.print-btn { position:fixed;top:80px;right:20px;padding:10px 20px;background:#2c3e50;color:#fff;border:none;border-radius:6px;cursor:pointer;font-size:14px;z-index:100; }
.print-btn:hover { background:#34495e; }
.page-break { page-break-after:always; }
@media print { @page { size:A5 landscape; margin:3mm; } .navbar,.footer,.print-btn,.no-print{display:none!important;} .invoice-wrapper{box-shadow:none;border-radius:0;padding:6px 4px;margin:0;max-width:none;width:100%;} body{background:#fff;} .main-content{padding:0;margin:0;max-width:none;} .container.main-content{max-width:none;padding:0;} .invoice-head,.invoice-mid{font-size:8px!important;} .invoice-head-left strong{font-size:11px!important;} .invoice-head-right .inv-no{font-size:9px!important;} .invoice-table th{padding:3px 4px!important;font-size:8px!important;} .invoice-table td{padding:2px 4px!important;font-size:8px!important;line-height:1.2!important;} .invoice-bottom{font-size:8px!important;} .invoice-sign{margin-top:8px!important;} .invoice-sign div{font-size:8px!important;} .invoice-sign .sign-space{margin-bottom:18px!important;} .invoice-footer2{font-size:7px!important;margin-top:5px!important;} .inv-no{margin-top:2px!important;font-size:9px!important;} .invoice-head-right p,.invoice-head-left p{font-size:8px!important;margin:0!important;line-height:1.2!important;} .invoice-table td:nth-child(6),.invoice-table td:nth-child(7){font-size:8px!important;white-space:nowrap;} .invoice-bottom-left ol{font-size:7px!important;line-height:1.3!important;padding-left:10px!important;margin:2px 0!important;} .invoice-bottom-left ol li{margin-bottom:1px!important;} .invoice-bottom-right td{padding:2px 4px!important;font-size:8px!important;} .invoice-bottom-right .total-row td{font-size:10px!important;} }
</style>

<button onclick="window.print()" class="print-btn no-print">🖨️ Cetak / Simpan PDF</button>

<?php
$perPage = 5;
$chunks = array_chunk($items, $perPage);
$totalPages = count($chunks);
$globalNo = 1;
$pageNo = 0;
foreach ($chunks as $chunk):
    $pageNo++;
    $isLast = ($pageNo === $totalPages);
    $invCode = $pageNo > 1 ? $order['order_code'] . '/L-' . ($pageNo - 1) : $order['order_code'];
?>
<div class="invoice-wrapper<?= !$isLast ? ' page-break' : '' ?>">
    <div class="invoice-head">
        <div class="invoice-head-left">
            <strong><?= htmlspecialchars($storeName) ?></strong>
            <p><?= nl2br(htmlspecialchars($storeAddress)) ?></p>
            <p>Telp: <?= htmlspecialchars($storePhone) ?></p>
            <p>Email: <?= htmlspecialchars($adminEmail) ?></p>
            <div class="inv-no" style="margin-top:8px;">Invoice No : <?= htmlspecialchars($invCode) ?></div>
        </div>
        <div class="invoice-head-right">
            <p>Samarinda, <?= date('d F Y', strtotime($order['created_at'])) ?></p>
            <p><strong>Kepada Yth,</strong></p>
            <p><?= htmlspecialchars($order['customer_name']) ?></p>
            <p><?= htmlspecialchars($order['customer_phone']) ?></p>
            <?php if ($order['customer_address']): ?>
            <p style="font-size:11px;color:#999;"><?= nl2br(htmlspecialchars($order['customer_address'])) ?></p>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($printerText && $pageNo === 1): ?>
    <div class="invoice-mid">
        <span><strong>Tipe Printer:</strong> <?= htmlspecialchars($printerText) ?></span>
        <span><strong>Status Pembayaran:</strong> <?= ($order['payment_status'] === 'paid' || $sisaPembayaran <= 0) ? '✅ LUNAS' : '💰 DP' ?></span>
        <?php if ($sisaPembayaran > 0): ?>
        <span><strong>Sisa:</strong> <?= formatRupiah($sisaPembayaran) ?></span>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <table class="invoice-table">
        <thead>
            <tr>
                <th style="width:30px;text-align:center;">No</th>
                <th>Nama Barang</th>
                <th style="width:80px;">Ukuran (L×P cm)</th>
                <th style="width:70px;">Bahan</th>
                <th style="width:35px;text-align:center;">Qty</th>
                <th style="width:80px;text-align:right;">Harga</th>
                <th style="width:80px;text-align:right;">Total</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($chunk as $item): ?>
            <tr>
                <td style="text-align:center;"><?= $globalNo++ ?></td>
                <td>
                    <?= htmlspecialchars($item['product_name']) ?>
                    <?php if ($item['design_service'] === 'jasa'): ?>
                    <span class="item-note"> + Jasa Desain</span>
                    <?php elseif ($item['design_service'] === 'upload'): ?>
                    <span class="item-note"> + Upload File Desain</span>
                    <?php endif; ?>
                    <?php 
                    $varData = !empty($item['variants']) ? json_decode($item['variants'], true) : [];
                    if (!empty($varData)): 
                        foreach ($varData as $vr): ?>
                            <br><small style="color:#e67e22;">+ <?= htmlspecialchars($vr['name']) ?> <?= formatRupiah($vr['price']) ?></small>
                    <?php endforeach; endif; ?>
                </td>
                <td><?= ($item['width'] && $item['height']) ? intval($item['width']) . ' &times; ' . intval($item['height']) : htmlspecialchars($item['unit_label'] ?: '-') ?></td>
                <td><?= htmlspecialchars($item['material_name']) ?: '-' ?></td>
                <td style="text-align:center;"><?= $item['quantity'] ?></td>
                <td style="text-align:right;"><?= formatRupiah($item['price']) ?></td>
                <td style="text-align:right;"><?= formatRupiah($item['subtotal']) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <?php if ($isLast): ?>
    <div class="invoice-bottom">
        <div class="invoice-bottom-left">
            <?php if ($order['notes']): ?>
            <p><strong>Catatan:</strong></p>
            <p><?= nl2br(htmlspecialchars($order['notes'])) ?></p>
            <?php endif; ?>
            
            <!-- 🔥 TAMPILKAN RIWAYAT PEMBAYARAN -->
            <?php if (!empty($payments)): ?>
            <p style="margin-top:8px;"><strong>Riwayat Pembayaran:</strong></p>
            <?php foreach ($payments as $p): ?>
            <p style="font-size:10px;margin:2px 0;">
                <?= date('d/m/Y H:i', strtotime($p['created_at'])) ?> — 
                <?= formatRupiah($p['amount']) ?> 
                (<?= ucfirst($p['status']) ?>)
                <?php if ($p['payment_type'] === 'dp'): ?>
                    <?php if ($p['amount'] >= $order['total']): ?>✅ Lunas
                    <?php else: ?>💰 DP<?php endif; ?>
                <?php endif; ?>
                <?php if ($p['payment_type'] === 'pelunasan'): ?>✅ Pelunasan<?php endif; ?>
            </p>
            <?php endforeach; ?>
            <?php endif; ?>
            
            <ol style="font-size:10px;line-height:1.5;margin:3px 0;padding-left:14px;">
                <li>Pekerjaan akan diselesaikan dalam waktu kurang lebih 2 hari.</li>
                <li>Apabila ada Kesalahan Desain/File setelah di cetak dari Customer bukan Tanggung jawab Kami.</li>
                <li>Pekerjaan dilaksanakan Setelah ada bukti transfer dan kesepakatan Sebelumnya.</li>
                <li>Apabila ada hal lain yang kurang berkenan silahkan hubungi No. yang ada di website resmi kami.</li>
                <li>Terima kasih atas kepercayaan Anda.</li>
            </ol>
        </div>
        <div class="invoice-bottom-right">
            <table>
                <tr><td>Total Pesanan</td><td style="text-align:right;"><?= formatRupiah($grandTotal) ?></td></tr>
                <tr><td>Sudah Dibayar</td><td style="text-align:right;color:#27ae60;"><?= formatRupiah($totalDibayar) ?></td></tr>
                <tr class="total-row">
                    <td><strong>Sisa</strong></td>
                    <td style="text-align:right;<?= $sisaAmount > 0 ? 'color:#e74c3c;' : 'color:#27ae60;' ?>">
                        <strong><?= $sisaAmount > 0 ? formatRupiah($sisaAmount) : '✅ LUNAS' ?></strong>
                    </td>
                </tr>
            </table>
        </div>
    </div>

    <div class="invoice-sign">
        <div>
            <p class="sign-space">Penerima,</p>
            <p style="font-weight:bold;"><?= htmlspecialchars($order['customer_name']) ?></p>
        </div>
        <div>
            <p class="sign-space">Hormat Kami,</p>
            <p style="font-weight:bold;"><?= htmlspecialchars($storeName) ?></p>
        </div>
    </div>

    <div class="invoice-footer2">
        <p><?= htmlspecialchars($storeName) ?> | Telp: <?= htmlspecialchars($storePhone) ?> | <?= htmlspecialchars($adminEmail ? "Email: $adminEmail" : '') ?></p>
        <p><?= nl2br(htmlspecialchars($invoiceFooter)) ?></p>
    </div>
    <?php endif; ?>
</div>
<?php endforeach; ?>
<?php include 'includes/footer.php'; ?>