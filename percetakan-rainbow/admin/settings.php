<?php
require_once __DIR__ . '/../config.php';
if (!isAdmin()) redirect('/admin/index.php');

$message = '';
$error = '';

// 🔥 🔥 PROSES POST 🔥 🔥
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $allowedKeys = [
        'store_name', 'store_address', 'store_phone', 'admin_email',
        'sendgrid_api_key',
        'bank1_name', 'bank1_account', 'bank1_name_holder',
        'bank2_name', 'bank2_account', 'bank2_name_holder',
        'bank3_name', 'bank3_account', 'bank3_name_holder',
        'qris_name', 'qris_merchant_id',
        'midtrans_server_key', 'midtrans_client_key',
        'invoice_template', 'invoice_footer', 'printer_options',
        'whatsapp_number', 'footer_text'
    ];
    
    try {
        $stmt = $db->prepare("INSERT OR REPLACE INTO settings (key, value) VALUES (?, ?)");
        
        // 🔥 SAVE TEXT FIELDS
        foreach ($allowedKeys as $key) {
            if (isset($_POST[$key])) {
                $value = trim($_POST[$key]);
                $stmt->execute([$key, $value]);
            }
        }
        
        // 🔥 UPLOAD QRIS IMAGE
        if (isset($_FILES['qris_image']) && $_FILES['qris_image']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['qris_image']['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            if (in_array($ext, $allowed)) {
                $uploadDir = __DIR__ . '/../uploads/';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
                
                $filename = 'qris_' . time() . '.' . $ext;
                if (move_uploaded_file($_FILES['qris_image']['tmp_name'], $uploadDir . $filename)) {
                    // Hapus QRIS lama
                    $oldQris = $db->query("SELECT value FROM settings WHERE key='qris_image'")->fetch();
                    if ($oldQris && $oldQris['value']) {
                        $oldPath = $uploadDir . $oldQris['value'];
                        if (file_exists($oldPath)) unlink($oldPath);
                    }
                    
                    $stmt->execute(['qris_image', $filename]);
                } else {
                    $error = "❌ Gagal upload gambar QRIS!";
                }
            } else {
                $error = "❌ Format gambar QRIS tidak didukung! (JPG, PNG, GIF, WEBP)";
            }
        }
        
        if (empty($error)) {
            $message = "✅ Pengaturan berhasil disimpan!";
        }
    } catch (Exception $e) {
        $error = "❌ Gagal menyimpan: " . $e->getMessage();
    }
}

// 🔥 🔥 AMBIL SETTINGS 🔥 🔥
$settings = [];
$rows = $db->query("SELECT * FROM settings")->fetchAll();
foreach ($rows as $row) {
    $settings[$row['key']] = $row['value'];
}

$pageTitle = 'Pengaturan';
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

.alert {
    padding: 12px 15px;
    border-radius: 6px;
    margin-bottom: 15px;
}
.alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
.alert-danger { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }

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

/* 🔥 SETTINGS FORM */
.settings-form {
    background: #fff;
    padding: 25px;
    border-radius: 8px;
    box-shadow: 0 1px 4px rgba(0,0,0,0.06);
}

.settings-section {
    margin-bottom: 30px;
    padding-bottom: 20px;
    border-bottom: 2px solid #f8f9fa;
}
.settings-section:last-child { border-bottom: none; margin-bottom: 0; }

.settings-section h2 {
    font-size: 18px;
    color: #2c3e50;
    margin-bottom: 15px;
    display: flex;
    align-items: center;
    gap: 10px;
}
.settings-section h2 .badge {
    font-size: 11px;
    background: #f39c12;
    color: #fff;
    padding: 2px 10px;
    border-radius: 20px;
}

.form-group {
    margin-bottom: 15px;
}
.form-group label {
    display: block;
    font-weight: 600;
    margin-bottom: 5px;
    font-size: 14px;
    color: #2c3e50;
}
.form-group .helper-text {
    font-size: 12px;
    color: #999;
    margin-top: 4px;
}
.form-group input, .form-group textarea, .form-group select {
    width: 100%;
    padding: 10px 12px;
    border: 1px solid #ddd;
    border-radius: 6px;
    font-size: 14px;
    transition: border-color 0.3s;
}
.form-group input:focus, .form-group textarea:focus, .form-group select:focus {
    border-color: #f39c12;
    outline: none;
}
.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 15px;
}

/* 🔥 QRIS PREVIEW */
.qris-preview {
    margin-top: 10px;
    display: flex;
    align-items: center;
    gap: 15px;
    flex-wrap: wrap;
}
.qris-preview img {
    max-width: 150px;
    max-height: 150px;
    border-radius: 8px;
    border: 2px solid #e9ecef;
    padding: 8px;
    background: #fff;
}
.qris-preview .qris-info {
    font-size: 13px;
    color: #6c757d;
}
.qris-preview .qris-info strong { color: #2c3e50; }

/* 🔥 RESPONSIVE */
@media (max-width: 768px) {
    .admin-layout { flex-direction: column; }
    .admin-sidebar { width: 100%; position: relative; top: 0; }
    .admin-sidebar ul { display: flex; flex-wrap: wrap; gap: 4px; }
    .admin-sidebar ul li a { padding: 6px 12px; font-size: 13px; }
    .form-row { grid-template-columns: 1fr; }
}

/* 🔥 TAB NAVIGATION */
.tab-nav {
    display: flex;
    gap: 4px;
    flex-wrap: wrap;
    margin-bottom: 20px;
    background: #fff;
    padding: 6px;
    border-radius: 8px;
    box-shadow: 0 1px 4px rgba(0,0,0,0.06);
}
.tab-nav .tab-btn {
    padding: 8px 18px;
    border: none;
    background: none;
    border-radius: 6px;
    cursor: pointer;
    font-size: 13px;
    transition: all 0.3s;
    color: #6c757d;
}
.tab-nav .tab-btn:hover { background: #f8f9fa; color: #2c3e50; }
.tab-nav .tab-btn.active {
    background: #2c3e50;
    color: #fff;
}
.tab-section {
    display: none;
}
.tab-section.active {
    display: block;
}
</style>

<div class="admin-layout">
    <aside class="admin-sidebar">
        <h2>Admin Panel</h2>
        <ul>
            <li><a href="dashboard.php">Dashboard</a></li>
            <li><a href="products.php">Produk</a></li>
            <li><a href="orders.php">Pesanan</a></li>
            <li><a href="edit-halaman.php?slug=tentang-kami">Tentang Kami</a></li>
            <li><a href="settings.php" class="active">Pengaturan</a></li>
            <li><a href="logout.php">Logout</a></li>
        </ul>
    </aside>
    <main class="admin-main">
        <h1>⚙️ Pengaturan</h1>
        
        <?php if ($message): ?>
            <div class="alert alert-success"><?= $message ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-danger"><?= $error ?></div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data" class="settings-form" id="settingsForm">
            
            <!-- 🔥 TAB NAVIGATION -->
            <div class="tab-nav">
                <button type="button" class="tab-btn active" data-tab="tab-toko">🏪 Toko</button>
                <button type="button" class="tab-btn" data-tab="tab-bank">🏦 Bank</button>
                <button type="button" class="tab-btn" data-tab="tab-qris">📱 QRIS</button>
                <button type="button" class="tab-btn" data-tab="tab-midtrans">💳 Midtrans</button>
                <button type="button" class="tab-btn" data-tab="tab-invoice">🧾 Invoice</button>
            </div>

            <!-- 🔥 TAB 1: TOKO -->
            <div class="tab-section active" id="tab-toko">
                <div class="settings-section">
                    <h2>🏪 Informasi Toko</h2>
                    
                    <div class="form-group">
                        <label>Nama Toko</label>
                        <input type="text" name="store_name" value="<?= htmlspecialchars($settings['store_name'] ?? 'Rainbow Printing') ?>" placeholder="Nama toko Anda">
                    </div>
                    <div class="form-group">
                        <label>Alamat</label>
                        <textarea name="store_address" rows="2"><?= htmlspecialchars($settings['store_address'] ?? '') ?></textarea>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>No. Telepon/WA</label>
                            <input type="text" name="store_phone" value="<?= htmlspecialchars($settings['store_phone'] ?? '') ?>" placeholder="08123456789">
                        </div>
                        <div class="form-group">
                            <label>WhatsApp Number (untuk tombol kontak)</label>
                            <input type="text" name="whatsapp_number" value="<?= htmlspecialchars($settings['whatsapp_number'] ?? '') ?>" placeholder="628123456789">
                            <div class="helper-text">Gunakan format internasional tanpa + (contoh: 628123456789)</div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Email Admin</label>
                        <input type="email" name="admin_email" value="<?= htmlspecialchars($settings['admin_email'] ?? '') ?>" placeholder="admin@email.com">
                        <div class="helper-text">Email untuk menerima notifikasi upload file desain dari customer</div>
                    </div>
                    <div class="form-group">
                        <label>Footer Text</label>
                        <input type="text" name="footer_text" value="<?= htmlspecialchars($settings['footer_text'] ?? '') ?>" placeholder="Teks footer website">
                        <div class="helper-text">Teks yang muncul di bagian bawah setiap halaman</div>
                    </div>
                </div>
            </div>

            <!-- 🔥 TAB 2: BANK -->
            <div class="tab-section" id="tab-bank">
                <div class="settings-section">
                    <h2>🏦 Rekening Bank 1</h2>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Nama Bank</label>
                            <input type="text" name="bank1_name" value="<?= htmlspecialchars($settings['bank1_name'] ?? '') ?>" placeholder="BRI">
                        </div>
                        <div class="form-group">
                            <label>No. Rekening</label>
                            <input type="text" name="bank1_account" value="<?= htmlspecialchars($settings['bank1_account'] ?? '') ?>" placeholder="1234567890">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Atas Nama</label>
                        <input type="text" name="bank1_name_holder" value="<?= htmlspecialchars($settings['bank1_name_holder'] ?? '') ?>" placeholder="Nama Pemilik Rekening">
                    </div>
                </div>

                <div class="settings-section">
                    <h2>🏦 Rekening Bank 2</h2>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Nama Bank</label>
                            <input type="text" name="bank2_name" value="<?= htmlspecialchars($settings['bank2_name'] ?? '') ?>" placeholder="BCA">
                        </div>
                        <div class="form-group">
                            <label>No. Rekening</label>
                            <input type="text" name="bank2_account" value="<?= htmlspecialchars($settings['bank2_account'] ?? '') ?>" placeholder="9876543210">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Atas Nama</label>
                        <input type="text" name="bank2_name_holder" value="<?= htmlspecialchars($settings['bank2_name_holder'] ?? '') ?>" placeholder="Nama Pemilik Rekening">
                    </div>
                </div>

                <div class="settings-section">
                    <h2>🏦 Rekening Bank 3 <span class="badge">Opsional</span></h2>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Nama Bank</label>
                            <input type="text" name="bank3_name" value="<?= htmlspecialchars($settings['bank3_name'] ?? '') ?>" placeholder="Mandiri">
                        </div>
                        <div class="form-group">
                            <label>No. Rekening</label>
                            <input type="text" name="bank3_account" value="<?= htmlspecialchars($settings['bank3_account'] ?? '') ?>" placeholder="5555555555">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Atas Nama</label>
                        <input type="text" name="bank3_name_holder" value="<?= htmlspecialchars($settings['bank3_name_holder'] ?? '') ?>" placeholder="Nama Pemilik Rekening">
                    </div>
                </div>
            </div>

            <!-- 🔥 TAB 3: QRIS -->
            <div class="tab-section" id="tab-qris">
                <div class="settings-section">
                    <h2>📱 QRIS</h2>
                    <p style="color:#666;font-size:13px;margin-bottom:15px;">Upload QR code untuk pembayaran QRIS.</p>
                    
                    <div class="form-group">
                        <label>Nama Merchant/Pemilik</label>
                        <input type="text" name="qris_name" value="<?= htmlspecialchars($settings['qris_name'] ?? '') ?>" placeholder="Nama Kamu">
                    </div>
                    <div class="form-group">
                        <label>Merchant ID <span class="badge" style="font-size:10px;">Opsional</span></label>
                        <input type="text" name="qris_merchant_id" value="<?= htmlspecialchars($settings['qris_merchant_id'] ?? '') ?>" placeholder="QRIS Merchant ID">
                    </div>
                    <div class="form-group">
                        <label>Gambar QR Code</label>
                        <input type="file" name="qris_image" accept="image/*" id="qrisInput">
                        <div class="helper-text">Format: JPG, PNG, GIF, WEBP. Kosongkan jika tidak ingin mengganti.</div>
                        
                        <?php if (!empty($settings['qris_image'])): ?>
                        <div class="qris-preview" id="qrisPreview">
                            <img src="/uploads/<?= htmlspecialchars($settings['qris_image']) ?>" alt="QRIS" id="qrisPreviewImg">
                            <div class="qris-info">
                                <strong>QRIS saat ini</strong><br>
                                <span style="font-size:12px;color:#999;">Klik "Choose File" untuk mengganti</span>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- 🔥 TAB 4: MIDTRANS -->
            <div class="tab-section" id="tab-midtrans">
                <div class="settings-section">
                    <h2>💳 Midtrans <span class="badge">Opsional</span></h2>
                    <p style="color:#666;font-size:13px;margin-bottom:15px;">Konfigurasi untuk pembayaran via Midtrans. Kosongkan jika tidak menggunakan.</p>
                    
                    <div class="form-group">
                        <label>Server Key</label>
                        <input type="text" name="midtrans_server_key" value="<?= htmlspecialchars($settings['midtrans_server_key'] ?? '') ?>" placeholder="SB-Mid-server-xxxx">
                        <div class="helper-text">Dapatkan dari dashboard Midtrans</div>
                    </div>
                    <div class="form-group">
                        <label>Client Key</label>
                        <input type="text" name="midtrans_client_key" value="<?= htmlspecialchars($settings['midtrans_client_key'] ?? '') ?>" placeholder="SB-Mid-client-xxxx">
                        <div class="helper-text">Dapatkan dari dashboard Midtrans</div>
                    </div>
                    <div class="form-group">
                        <label>SendGrid API Key <span class="badge" style="font-size:10px;">Opsional</span></label>
                        <input type="password" name="sendgrid_api_key" value="<?= htmlspecialchars($settings['sendgrid_api_key'] ?? '') ?>" placeholder="SG.xxxx">
                        <div class="helper-text">Untuk kirim email notifikasi via SendGrid. Daftar di <a href="https://sendgrid.com" target="_blank">sendgrid.com</a></div>
                    </div>
                </div>
            </div>

            <!-- 🔥 TAB 5: INVOICE -->
            <div class="tab-section" id="tab-invoice">
                <div class="settings-section">
                    <h2>🧾 Invoice & Cetakan</h2>
                    
                    <div class="form-group">
                        <label>Tampilan Invoice</label>
                        <select name="invoice_template">
                            <option value="classic" <?= ($settings['invoice_template'] ?? '') === 'classic' ? 'selected' : '' ?>>📄 Classic</option>
                            <option value="modern" <?= ($settings['invoice_template'] ?? '') === 'modern' ? 'selected' : '' ?>>🎨 Modern</option>
                            <option value="professional" <?= ($settings['invoice_template'] ?? '') === 'professional' ? 'selected' : '' ?>>💼 Professional</option>
                        </select>
                        <div class="helper-text">Pilih tampilan/style invoice yang dicetak</div>
                    </div>
                    
                    <div class="form-group">
                        <label>Opsi Tipe Printer</label>
                        <input type="text" name="printer_options" value="<?= htmlspecialchars($settings['printer_options'] ?? 'In-Fus/Solvent,Digital Printing,Offset,UV Printer,Sablon') ?>" placeholder="In-Fus/Solvent,Digital Printing,Offset">
                        <div class="helper-text">Tipe printer yang tersedia, dipisahkan dengan koma</div>
                    </div>
                    
                    <div class="form-group">
                        <label>Teks Footer Invoice</label>
                        <textarea name="invoice_footer" rows="2"><?= htmlspecialchars($settings['invoice_footer'] ?? 'Terima kasih telah berbelanja di Percetakan Rainbow') ?></textarea>
                        <div class="helper-text">Teks yang muncul di bagian bawah invoice</div>
                    </div>
                </div>
            </div>

            <!-- 🔥 SUBMIT -->
            <div style="margin-top:20px;display:flex;gap:10px;flex-wrap:wrap;">
                <button type="submit" class="btn btn-primary" onclick="return confirmSave()">
                    <i class="fas fa-save"></i> Simpan Pengaturan
                </button>
                <button type="reset" class="btn btn-outline" style="background:#fff;color:#2c3e50;border:1px solid #2c3e50;padding:8px 16px;border-radius:6px;cursor:pointer;">
                    <i class="fas fa-undo"></i> Reset
                </button>
            </div>
        </form>
    </main>
</div>

<script>
// 🔥 TAB NAVIGATION
document.querySelectorAll('.tab-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        // Hapus active dari semua tab
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        document.querySelectorAll('.tab-section').forEach(s => s.classList.remove('active'));
        
        // Aktifkan tab yang dipilih
        this.classList.add('active');
        const target = document.getElementById(this.dataset.tab);
        if (target) target.classList.add('active');
    });
});

// 🔥 QRIS PREVIEW
document.getElementById('qrisInput')?.addEventListener('change', function(e) {
    const file = this.files[0];
    if (!file) return;
    
    const reader = new FileReader();
    reader.onload = function(e) {
        let preview = document.getElementById('qrisPreview');
        if (!preview) {
            preview = document.createElement('div');
            preview.id = 'qrisPreview';
            preview.className = 'qris-preview';
            this.parentElement.appendChild(preview);
        }
        preview.innerHTML = `
            <img src="${e.target.result}" alt="QRIS Preview" style="max-width:150px;max-height:150px;border-radius:8px;border:2px solid #27ae60;padding:8px;background:#fff;">
            <div class="qris-info">
                <strong style="color:#27ae60;">✅ QRIS baru</strong><br>
                <span style="font-size:12px;color:#999;">File: ${file.name} (${(file.size/1024).toFixed(1)} KB)</span>
            </div>
        `;
    };
    reader.readAsDataURL(file);
});

// 🔥 CONFIRM SAVE
function confirmSave() {
    // Validasi form
    const form = document.getElementById('settingsForm');
    const requiredFields = form.querySelectorAll('input[required]');
    for (const field of requiredFields) {
        if (!field.value.trim()) {
            alert('❌ ' + field.previousElementSibling?.textContent + ' harus diisi!');
            field.focus();
            return false;
        }
    }
    
    return confirm('⚠️ Yakin ingin menyimpan semua pengaturan?');
}

// 🔥 AUTO HIDE ALERT
document.addEventListener('DOMContentLoaded', function() {
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.style.transition = 'opacity 0.5s';
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 500);
        }, 5000);
    });
});
</script>

<?php include '../includes/footer.php'; ?>