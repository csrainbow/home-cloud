<?php
require_once __DIR__ . '/../config.php';
if (!isAdmin()) redirect('/admin/index.php');

$message = '';
$error = '';
$slug = $_GET['slug'] ?? 'tentang-kami';

// 🔥 DAFTAR HALAMAN YANG BISA DIEDIT
$availablePages = [
    'tentang-kami' => 'Tentang Kami',
    'privacy-policy' => 'Kebijakan Privasi',
    'terms-of-service' => 'Syarat & Ketentuan',
    'faq' => 'FAQ',
    'contact' => 'Kontak'
];

// 🔥 VALIDASI SLUG
if (!array_key_exists($slug, $availablePages)) {
    redirect('/admin/dashboard.php');
}

// 🔥 AMBIL DATA HALAMAN
$stmt = $db->prepare("SELECT * FROM content_pages WHERE slug = ?");
$stmt->execute([$slug]);
$page = $stmt->fetch();

// 🔥 BUAT HALAMAN BARU JIKA BELUM ADA
if (!$page) {
    $stmt = $db->prepare("INSERT INTO content_pages (slug, title, content, created_at, updated_at) VALUES (?, ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)");
    $stmt->execute([$slug, ucfirst(str_replace('-', ' ', $slug)), '']);
    $stmt = $db->prepare("SELECT * FROM content_pages WHERE slug = ?");
    $stmt->execute([$slug]);
    $page = $stmt->fetch();
}

// 🔥 🔥 PROSES UPDATE 🔥 🔥
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $content = $_POST['content'] ?? '';
    $metaTitle = trim($_POST['meta_title'] ?? '');
    $metaDescription = trim($_POST['meta_description'] ?? '');
    $metaKeywords = trim($_POST['meta_keywords'] ?? '');
    $isActive = isset($_POST['is_active']) ? 1 : 0;
    
    if (empty($title)) {
        $error = 'Judul halaman harus diisi!';
    } else {
        try {
            $stmt = $db->prepare("UPDATE content_pages SET 
                title = ?, 
                content = ?, 
                meta_title = ?, 
                meta_description = ?, 
                meta_keywords = ?, 
                is_active = ?,
                updated_at = CURRENT_TIMESTAMP 
                WHERE slug = ?");
            $stmt->execute([$title, $content, $metaTitle, $metaDescription, $metaKeywords, $isActive, $slug]);
            
            // 🔥 UPDATE VARIABLE
            $page['title'] = $title;
            $page['content'] = $content;
            $page['meta_title'] = $metaTitle;
            $page['meta_description'] = $metaDescription;
            $page['meta_keywords'] = $metaKeywords;
            $page['is_active'] = $isActive;
            
            $message = '✅ Halaman berhasil diperbarui!';
        } catch (Exception $e) {
            $error = '❌ Gagal menyimpan: ' . $e->getMessage();
        }
    }
}

$pageTitle = 'Edit Halaman: ' . $page['title'];
include __DIR__ . '/../includes/header.php';
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

.alert {
    padding: 12px 15px;
    border-radius: 6px;
    margin-bottom: 15px;
}

.alert-success {
    background: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

.alert-danger {
    background: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}

.admin-form {
    background: #fff;
    padding: 25px;
    border-radius: 8px;
    box-shadow: 0 1px 4px rgba(0,0,0,0.06);
}

.form-group {
    margin-bottom: 18px;
}

.form-group label {
    display: block;
    font-weight: 600;
    margin-bottom: 5px;
    color: #2c3e50;
    font-size: 14px;
}

.form-group .form-control {
    width: 100%;
    padding: 10px 12px;
    border: 1px solid #ddd;
    border-radius: 6px;
    font-size: 14px;
    transition: border-color 0.3s;
}

.form-group .form-control:focus {
    border-color: #f39c12;
    outline: none;
}

.form-group textarea.form-control {
    font-family: 'Courier New', monospace;
    font-size: 14px;
    line-height: 1.6;
    min-height: 300px;
}

.form-group .helper-text {
    font-size: 12px;
    color: #999;
    margin-top: 4px;
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 15px;
}

.btn {
    display: inline-block;
    padding: 10px 24px;
    border-radius: 6px;
    font-size: 14px;
    cursor: pointer;
    text-decoration: none;
    border: none;
    transition: background 0.3s;
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

.page-selector {
    display: flex;
    gap: 10px;
    margin-bottom: 20px;
    flex-wrap: wrap;
}

.page-selector .page-btn {
    padding: 8px 16px;
    border-radius: 6px;
    border: 1px solid #ddd;
    background: #fff;
    cursor: pointer;
    font-size: 13px;
    text-decoration: none;
    color: #2c3e50;
    transition: all 0.3s;
}

.page-selector .page-btn:hover {
    border-color: #f39c12;
    background: #fef9e7;
}

.page-selector .page-btn.active {
    background: #f39c12;
    color: #fff;
    border-color: #f39c12;
}

/* 🔥 TOGGLE SWITCH */
.switch {
    position: relative;
    display: inline-block;
    width: 48px;
    height: 24px;
}

.switch input {
    opacity: 0;
    width: 0;
    height: 0;
}

.slider {
    position: absolute;
    cursor: pointer;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: #ccc;
    transition: .4s;
    border-radius: 24px;
}

.slider:before {
    position: absolute;
    content: "";
    height: 18px;
    width: 18px;
    left: 3px;
    bottom: 3px;
    background: white;
    transition: .4s;
    border-radius: 50%;
}

input:checked + .slider {
    background: #27ae60;
}

input:checked + .slider:before {
    transform: translateX(24px);
}

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
    .form-row {
        grid-template-columns: 1fr;
    }
    .page-selector {
        gap: 6px;
    }
    .page-selector .page-btn {
        padding: 6px 12px;
        font-size: 12px;
    }
}

/* 🔥 TOOLBAR SEDERHANA */
.toolbar {
    display: flex;
    gap: 6px;
    flex-wrap: wrap;
    padding: 8px 12px;
    background: #f8f9fa;
    border: 1px solid #ddd;
    border-bottom: none;
    border-radius: 6px 6px 0 0;
}

.toolbar button {
    padding: 4px 10px;
    border: 1px solid #ddd;
    background: #fff;
    border-radius: 4px;
    cursor: pointer;
    font-size: 13px;
    transition: all 0.2s;
}

.toolbar button:hover {
    background: #2c3e50;
    color: #fff;
    border-color: #2c3e50;
}
</style>

<div class="admin-layout">
    <aside class="admin-sidebar">
        <h2>Admin Panel</h2>
        <ul>
            <li><a href="dashboard.php">Dashboard</a></li>
            <li><a href="products.php">Produk</a></li>
            <li><a href="orders.php">Pesanan</a></li>
            <li><a href="edit-halaman.php?slug=tentang-kami" class="<?= $slug === 'tentang-kami' ? 'active' : '' ?>">Tentang Kami</a></li>
            <li><a href="settings.php">Pengaturan</a></li>
            <li><a href="logout.php">Logout</a></li>
        </ul>
    </aside>
    <main class="admin-main">
        <h1>📝 Edit Halaman</h1>

        <!-- 🔥 PAGE SELECTOR -->
        <div class="page-selector">
            <?php foreach ($availablePages as $key => $label): ?>
                <a href="edit-halaman.php?slug=<?= $key ?>" class="page-btn <?= $key === $slug ? 'active' : '' ?>">
                    <?= htmlspecialchars($label) ?>
                </a>
            <?php endforeach; ?>
        </div>

        <!-- 🔥 MESSAGE -->
        <?php if ($message): ?>
            <div class="alert alert-success"><?= $message ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-danger"><?= $error ?></div>
        <?php endif; ?>

        <!-- 🔥 FORM -->
        <form method="POST" class="admin-form" id="pageForm">
            <div class="form-group">
                <label for="title">Judul Halaman *</label>
                <input type="text" name="title" id="title" class="form-control" 
                       value="<?= htmlspecialchars($page['title']) ?>" required>
            </div>

            <div class="form-group">
                <label>Konten (HTML diperbolehkan)</label>
                
                <!-- 🔥 TOOLBAR -->
                <div class="toolbar">
                    <button type="button" onclick="insertTag('h2')">H2</button>
                    <button type="button" onclick="insertTag('h3')">H3</button>
                    <button type="button" onclick="insertTag('p')">P</button>
                    <button type="button" onclick="insertTag('strong')">B</button>
                    <button type="button" onclick="insertTag('em')">I</button>
                    <button type="button" onclick="insertTag('ul')">UL</button>
                    <button type="button" onclick="insertTag('li')">LI</button>
                    <button type="button" onclick="insertTag('br')">BR</button>
                    <button type="button" onclick="insertTag('a')">🔗 Link</button>
                    <button type="button" onclick="insertTag('img')">🖼️ Gambar</button>
                </div>
                
                <textarea name="content" id="content" class="form-control" rows="20" required><?= htmlspecialchars($page['content']) ?></textarea>
                <div class="helper-text">
                    Gunakan tag HTML dasar: &lt;h2&gt;, &lt;h3&gt;, &lt;p&gt;, &lt;ul&gt;&lt;li&gt;, &lt;strong&gt;, &lt;em&gt;, &lt;br&gt;
                </div>
            </div>

            <!-- 🔥 SEO FIELDS -->
            <div class="form-row">
                <div class="form-group">
                    <label for="meta_title">Meta Title (SEO)</label>
                    <input type="text" name="meta_title" id="meta_title" class="form-control" 
                           value="<?= htmlspecialchars($page['meta_title'] ?? '') ?>" 
                           placeholder="Judul untuk SEO (maks 60 karakter)">
                    <div class="helper-text"><?= strlen($page['meta_title'] ?? '') ?>/60 karakter</div>
                </div>
                <div class="form-group">
                    <label for="meta_description">Meta Description (SEO)</label>
                    <textarea name="meta_description" id="meta_description" class="form-control" rows="2" 
                              placeholder="Deskripsi untuk SEO (maks 160 karakter)"><?= htmlspecialchars($page['meta_description'] ?? '') ?></textarea>
                    <div class="helper-text"><?= strlen($page['meta_description'] ?? '') ?>/160 karakter</div>
                </div>
            </div>

            <div class="form-group">
                <label for="meta_keywords">Meta Keywords (SEO)</label>
                <input type="text" name="meta_keywords" id="meta_keywords" class="form-control" 
                       value="<?= htmlspecialchars($page['meta_keywords'] ?? '') ?>" 
                       placeholder="Kata kunci, dipisahkan koma">
                <div class="helper-text">Contoh: percetakan, cetak online, samarinda</div>
            </div>

            <!-- 🔥 STATUS -->
            <div class="form-group">
                <label style="display:flex;align-items:center;gap:12px;cursor:pointer;">
                    <span>Aktifkan Halaman</span>
                    <label class="switch">
                        <input type="checkbox" name="is_active" <?= ($page['is_active'] ?? 1) ? 'checked' : '' ?>>
                        <span class="slider"></span>
                    </label>
                </label>
            </div>

            <!-- 🔥 BUTTONS -->
            <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:10px;">
                <button type="submit" class="btn btn-primary">💾 Simpan Perubahan</button>
                <a href="/<?= $slug ?>.php" target="_blank" class="btn btn-outline">👁️ Preview Halaman</a>
                <button type="button" class="btn btn-success" onclick="showPreview()">📱 Live Preview</button>
            </div>
        </form>

        <!-- 🔥 LIVE PREVIEW -->
        <div id="livePreview" style="display:none;margin-top:20px;background:#fff;padding:25px;border-radius:8px;box-shadow:0 1px 4px rgba(0,0,0,0.06);border:2px solid #f39c12;">
            <h3 style="display:flex;justify-content:space-between;align-items:center;">
                <span>📱 Live Preview</span>
                <button onclick="document.getElementById('livePreview').style.display='none'" style="background:none;border:none;font-size:20px;cursor:pointer;">&times;</button>
            </h3>
            <div id="previewContent" style="padding:15px;border:1px solid #eee;border-radius:4px;min-height:100px;"></div>
        </div>
    </main>
</div>

<script>
// 🔥 TOOLBAR FUNCTION
function insertTag(tag) {
    var textarea = document.getElementById('content');
    var start = textarea.selectionStart;
    var end = textarea.selectionEnd;
    var selected = textarea.value.substring(start, end);
    var replacement = '';
    
    switch(tag) {
        case 'h2':
            replacement = '<h2>' + (selected || 'Judul') + '</h2>';
            break;
        case 'h3':
            replacement = '<h3>' + (selected || 'Sub Judul') + '</h3>';
            break;
        case 'p':
            replacement = '<p>' + (selected || 'Paragraf') + '</p>';
            break;
        case 'strong':
            replacement = '<strong>' + (selected || 'Teks tebal') + '</strong>';
            break;
        case 'em':
            replacement = '<em>' + (selected || 'Teks miring') + '</em>';
            break;
        case 'ul':
            replacement = '<ul>\n  <li>' + (selected || 'Item') + '</li>\n</ul>';
            break;
        case 'li':
            replacement = '<li>' + (selected || 'Item') + '</li>';
            break;
        case 'br':
            replacement = '<br>';
            break;
        case 'a':
            replacement = '<a href="' + (selected || 'https://') + '">' + (selected || 'Teks link') + '</a>';
            break;
        case 'img':
            replacement = '<img src="' + (selected || '/uploads/image.jpg') + '" alt="' + (selected || 'Deskripsi gambar') + '">';
            break;
        default:
            return;
    }
    
    textarea.setRangeText(replacement, start, end, 'end');
    textarea.focus();
}

// 🔥 LIVE PREVIEW
function showPreview() {
    var content = document.getElementById('content').value;
    document.getElementById('previewContent').innerHTML = content;
    document.getElementById('livePreview').style.display = 'block';
    document.getElementById('livePreview').scrollIntoView({ behavior: 'smooth', block: 'center' });
}

// 🔥 CHARACTER COUNTER
document.addEventListener('DOMContentLoaded', function() {
    var metaTitle = document.getElementById('meta_title');
    var metaDesc = document.getElementById('meta_description');
    
    if (metaTitle) {
        metaTitle.addEventListener('input', function() {
            var len = this.value.length;
            var helper = this.parentElement.querySelector('.helper-text');
            if (helper) helper.textContent = len + '/60 karakter';
        });
    }
    
    if (metaDesc) {
        metaDesc.addEventListener('input', function() {
            var len = this.value.length;
            var helper = this.parentElement.querySelector('.helper-text');
            if (helper) helper.textContent = len + '/160 karakter';
        });
    }
});

// 🔥 PREVENT DOUBLE SUBMIT
document.querySelector('form').addEventListener('submit', function() {
    var btn = this.querySelector('button[type="submit"]');
    btn.innerHTML = '⏳ Menyimpan...';
    btn.disabled = true;
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>