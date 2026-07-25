<?php
require_once __DIR__ . '/../config.php';
if (!isAdmin()) redirect('/admin/index.php');

$action = $_GET['action'] ?? 'list';
$message = '';
$error = '';

// 🔥 🔥 PROSES POST 🔥 🔥
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // 🔥 SAVE PRODUCT
    if (isset($_POST['save'])) {
        $id = $_POST['id'] ?? '';
        $name = trim($_POST['name']);
        $description = trim($_POST['description']);
        $price = str_replace(',', '', $_POST['price']);
        $category = $_POST['category'];
        $stock = intval($_POST['stock']);
        $sizeUnit = $_POST['size_unit'] ?? 'none';
        $pricePerM2 = str_replace(',', '', $_POST['price_per_m2'] ?? '0');
        $customSize = ($sizeUnit !== 'none') ? 1 : 0;
        
        // 🔥 Validasi
        if (empty($name)) {
            $error = "❌ Nama produk harus diisi!";
        } elseif (empty($price) || floatval($price) <= 0) {
            $error = "❌ Harga harus diisi dan lebih dari 0!";
        } elseif (empty($category)) {
            $error = "❌ Kategori harus dipilih!";
        } else {
            // 🔥 Handle kategori baru
            if ($category === '__new__' && !empty(trim($_POST['new_category'] ?? ''))) {
                $category = trim($_POST['new_category']);
                ensureCategory($category);
            }
            
            // 🔥 Generate slug
            if (!$id) {
                $slug = strtolower(trim(preg_replace('/[^a-z0-9-]/', '-', str_replace(' ', '-', $name)), '-')) . '-' . uniqid();
            } else {
                $slug = strtolower(trim(preg_replace('/[^a-z0-9-]/', '-', str_replace(' ', '-', $name)), '-'));
            }
            
            try {
                if ($id) {
                    // 🔥 UPDATE
                    $stmt = $db->prepare("UPDATE products SET 
                        name=?, slug=?, description=?, price=?, category=?, stock=?, custom_size=?, size_unit=?, price_per_m2=? 
                        WHERE id=?");
                    $stmt->execute([$name, $slug, $description, $price, $category, $stock, $customSize, $sizeUnit, $pricePerM2, $id]);
                    $message = "✅ Produk berhasil diupdate!";
                } else {
                    // 🔥 INSERT
                    $stmt = $db->prepare("INSERT INTO products (name, slug, description, price, category, stock, custom_size, size_unit, price_per_m2, created_at) 
                                           VALUES (?,?,?,?,?,?,?,?,?, CURRENT_TIMESTAMP)");
                    $stmt->execute([$name, $slug, $description, $price, $category, $stock, $customSize, $sizeUnit, $pricePerM2]);
                    $id = $db->lastInsertId();
                    $message = "✅ Produk berhasil ditambahkan!";
                }
                
                // 🔥 🔥 UPDATE MATERIALS 🔥 🔥
                if ($customSize && $id) {
                    $matNames = $_POST['mat_name'] ?? [];
                    $matPrices = $_POST['mat_price'] ?? [];
                    $matIds = $_POST['mat_id'] ?? [];
                    $existingIds = [];
                    
                    foreach ($matNames as $i => $matName) {
                        $matName = trim($matName);
                        $matPrice = str_replace(',', '', $matPrices[$i] ?? '0');
                        if (!$matName || floatval($matPrice) <= 0) continue;
                        
                        if (!empty($matIds[$i])) {
                            $stmt = $db->prepare("UPDATE product_materials SET name=?, price_per_m2=? WHERE id=? AND product_id=?");
                            $stmt->execute([$matName, $matPrice, $matIds[$i], $id]);
                            $existingIds[] = $matIds[$i];
                        } else {
                            $stmt = $db->prepare("INSERT INTO product_materials (product_id, name, price_per_m2) VALUES (?,?,?)");
                            $stmt->execute([$id, $matName, $matPrice]);
                            $existingIds[] = $db->lastInsertId();
                        }
                    }
                    
                    if (!empty($existingIds)) {
                        $placeholders = implode(',', array_fill(0, count($existingIds), '?'));
                        $db->prepare("DELETE FROM product_materials WHERE product_id=? AND id NOT IN ($placeholders)")->execute(array_merge([$id], $existingIds));
                    } else {
                        $db->prepare("DELETE FROM product_materials WHERE product_id=?")->execute([$id]);
                    }
                }
                
                // 🔥 🔥 UPDATE VARIANTS 🔥 🔥
                if ($id) {
                    $varNames = $_POST['var_name'] ?? [];
                    $varPrices = $_POST['var_price'] ?? [];
                    $varIds = $_POST['var_id'] ?? [];
                    $varActive = $_POST['var_active'] ?? [];
                    $existingVarIds = [];
                    
                    foreach ($varNames as $i => $varName) {
                        $varName = trim($varName);
                        if (!$varName) continue;
                        $varPrice = str_replace(',', '', $varPrices[$i] ?? '0');
                        $isActive = !empty($varActive[$i]) ? 1 : 0;
                        
                        if (!empty($varIds[$i])) {
                            $stmt = $db->prepare("UPDATE product_variants SET name=?, price=?, is_active=? WHERE id=? AND product_id=?");
                            $stmt->execute([$varName, $varPrice, $isActive, $varIds[$i], $id]);
                            $existingVarIds[] = $varIds[$i];
                        } else {
                            $stmt = $db->prepare("INSERT INTO product_variants (product_id, name, price, is_active) VALUES (?,?,?,?)");
                            $stmt->execute([$id, $varName, $varPrice, $isActive]);
                            $existingVarIds[] = $db->lastInsertId();
                        }
                    }
                    
                    if (!empty($existingVarIds)) {
                        $placeholders = implode(',', array_fill(0, count($existingVarIds), '?'));
                        $db->prepare("DELETE FROM product_variants WHERE product_id=? AND id NOT IN ($placeholders)")->execute(array_merge([$id], $existingVarIds));
                    } else {
                        $db->prepare("DELETE FROM product_variants WHERE product_id=?")->execute([$id]);
                    }
                }
                
                // 🔥 🔥 UPLOAD GAMBAR 🔥 🔥
                if (isset($_FILES['images']) && $id) {
                    // Hapus gambar yang ditandai
                    $deleteImages = $_POST['delete_images'] ?? [];
                    foreach ($deleteImages as $delId) {
                        $imgStmt = $db->prepare("SELECT image FROM product_images WHERE id=? AND product_id=?");
                        $imgStmt->execute([$delId, $id]);
                        $img = $imgStmt->fetch();
                        if ($img) {
                            $path = __DIR__ . '/../uploads/' . $img['image'];
                            if (file_exists($path)) unlink($path);
                            $db->prepare("DELETE FROM product_images WHERE id=?")->execute([$delId]);
                        }
                    }
                    
                    // Upload gambar baru
                    $files = $_FILES['images'];
                    if (is_array($files['tmp_name'])) {
                        foreach ($files['tmp_name'] as $i => $tmp) {
                            if (empty($tmp) || $files['error'][$i] !== UPLOAD_ERR_OK) continue;
                            $file = ['tmp_name' => $tmp, 'name' => $files['name'][$i]];
                            $filename = uploadImage($file, __DIR__ . '/../uploads');
                            if ($filename) {
                                $stmt = $db->prepare("INSERT INTO product_images (product_id, image) VALUES (?,?)");
                                $stmt->execute([$id, $filename]);
                                // Set gambar utama jika belum ada
                                if (!$db->query("SELECT image FROM products WHERE id=$id")->fetch()['image']) {
                                    $db->prepare("UPDATE products SET image=? WHERE id=?")->execute([$filename, $id]);
                                }
                            }
                        }
                    }
                }
                
                // 🔥 Redirect setelah sukses
                header('Location: products.php?success=' . urlencode($message));
                exit;
                
            } catch (Exception $e) {
                $error = "❌ Gagal menyimpan: " . $e->getMessage();
            }
        }
    }
    
    // 🔥 DELETE PRODUCT
    if (isset($_POST['delete'])) {
        $id = intval($_POST['delete']);
        try {
            // Hapus gambar
            $images = $db->prepare("SELECT image FROM product_images WHERE product_id=?");
            $images->execute([$id]);
            foreach ($images->fetchAll() as $img) {
                $path = __DIR__ . '/../uploads/' . $img['image'];
                if (file_exists($path)) unlink($path);
            }
            $db->prepare("DELETE FROM product_images WHERE product_id=?")->execute([$id]);
            $db->prepare("DELETE FROM product_materials WHERE product_id=?")->execute([$id]);
            $db->prepare("DELETE FROM products WHERE id=?")->execute([$id]);
            $message = "✅ Produk berhasil dihapus!";
        } catch (Exception $e) {
            $error = "❌ Gagal menghapus: " . $e->getMessage();
        }
    }
}

// 🔥 🔥 AMBIL DATA 🔥 🔥
$search = trim($_GET['search'] ?? '');
$categoryFilter = $_GET['category'] ?? '';

$whereConditions = [];
$params = [];

if ($search) {
    $whereConditions[] = "(name LIKE ? OR category LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($categoryFilter) {
    $whereConditions[] = "category = ?";
    $params[] = $categoryFilter;
}

$whereSql = empty($whereConditions) ? '' : 'WHERE ' . implode(' AND ', $whereConditions);

$products = $db->prepare("SELECT * FROM products $whereSql ORDER BY category, name");
$products->execute($params);
$products = $products->fetchAll();

$editProduct = null;
$materials = [];
$variants = [];
if (isset($_GET['edit'])) {
    $stmt = $db->prepare("SELECT * FROM products WHERE id=?");
    $stmt->execute([$_GET['edit']]);
    $editProduct = $stmt->fetch();
    if ($editProduct) {
        $stmt = $db->prepare("SELECT * FROM product_materials WHERE product_id=? ORDER BY id");
        $stmt->execute([$editProduct['id']]);
        $materials = $stmt->fetchAll();
        $stmt = $db->prepare("SELECT * FROM product_variants WHERE product_id=? ORDER BY id");
        $stmt->execute([$editProduct['id']]);
        $variants = $stmt->fetchAll();
    }
}

$categories = getCategories();
$pageTitle = 'Produk';
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
.btn-danger { background: #e74c3c; color: #fff; }
.btn-danger:hover { background: #c0392b; }
.btn-outline { background: #fff; color: #2c3e50; border: 1px solid #2c3e50; }
.btn-outline:hover { background: #f8f9fa; }
.btn-sm { padding: 4px 10px; font-size: 12px; }

/* 🔥 FILTER */
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
.filter-bar input, .filter-bar select {
    padding: 8px 12px;
    border: 1px solid #ddd;
    border-radius: 6px;
    font-size: 13px;
    flex: 1;
    min-width: 150px;
}

/* 🔥 TABLE */
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
.status-pending_verification { background: #3498db; color: #fff; }

/* 🔥 FORM */
.admin-form {
    background: #fff;
    padding: 25px;
    border-radius: 8px;
    box-shadow: 0 1px 4px rgba(0,0,0,0.06);
    margin-bottom: 20px;
}
.admin-form h2 {
    font-size: 18px;
    color: #2c3e50;
    margin-bottom: 15px;
    padding-bottom: 10px;
    border-bottom: 2px solid #f39c12;
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
.form-group input, .form-group textarea, .form-group select {
    width: 100%;
    padding: 10px 12px;
    border: 1px solid #ddd;
    border-radius: 6px;
    font-size: 14px;
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
.material-row {
    display: flex;
    gap: 10px;
    margin-bottom: 8px;
    align-items: center;
}
.material-row input { flex: 1; }
.material-row input:last-child { width: 120px; flex: none; }

.variant-row {
    display: flex;
    gap: 10px;
    margin-bottom: 8px;
    align-items: center;
}
.variant-row .variant-checkbox {
    width: 30px;
    display: flex;
    align-items: center;
    justify-content: center;
}
.variant-row input[type="text"] { flex: 1; }
.variant-row input[type="text"]:last-of-type { width: 130px; flex: none; }

/* 🔥 GAMBAR */
.image-grid {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    margin-top: 10px;
}
.image-item {
    position: relative;
    width: 120px;
    height: 120px;
    border-radius: 8px;
    border: 2px solid #e9ecef;
    overflow: hidden;
    transition: all 0.3s;
}
.image-item:hover { border-color: #f39c12; }
.image-item img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
.image-item .delete-btn {
    position: absolute;
    top: 4px;
    right: 4px;
    width: 24px;
    height: 24px;
    background: #e74c3c;
    color: #fff;
    border: none;
    border-radius: 50%;
    cursor: pointer;
    font-size: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    opacity: 0.7;
    transition: opacity 0.3s;
}
.image-item .delete-btn:hover { opacity: 1; }
.image-item .main-badge {
    position: absolute;
    bottom: 4px;
    left: 4px;
    background: #27ae60;
    color: #fff;
    padding: 2px 8px;
    border-radius: 4px;
    font-size: 10px;
    font-weight: bold;
}

/* 🔥 RESPONSIVE */
@media (max-width: 768px) {
    .admin-layout { flex-direction: column; }
    .admin-sidebar { width: 100%; position: relative; top: 0; }
    .admin-sidebar ul { display: flex; flex-wrap: wrap; gap: 4px; }
    .admin-sidebar ul li a { padding: 6px 12px; font-size: 13px; }
    .form-row { grid-template-columns: 1fr; }
    .material-row { flex-wrap: wrap; }
    .material-row input { flex: 1; min-width: 100px; }
    .material-row input:last-child { width: 100%; }
    .variant-row { flex-wrap: wrap; }
    .variant-row input[type="text"] { min-width: 80px; }
    .filter-bar { flex-direction: column; }
    .filter-bar input, .filter-bar select { width: 100%; }
}
</style>

<div class="admin-layout">
    <aside class="admin-sidebar">
        <h2>Admin Panel</h2>
        <ul>
            <li><a href="dashboard.php">Dashboard</a></li>
            <li><a href="products.php" class="active">Produk</a></li>
            <li><a href="orders.php">Pesanan</a></li>
            <li><a href="edit-halaman.php?slug=tentang-kami">Tentang Kami</a></li>
            <li><a href="settings.php">Pengaturan</a></li>
            <li><a href="logout.php">Logout</a></li>
        </ul>
    </aside>
    <main class="admin-main">
        <h1>📦 Produk</h1>
        
        <!-- 🔥 ALERT -->
        <?php if ($message): ?>
            <div class="alert alert-success"><?= $message ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-danger"><?= $error ?></div>
        <?php endif; ?>
        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success"><?= htmlspecialchars($_GET['success']) ?></div>
        <?php endif; ?>

        <!-- 🔥 TOOLBAR -->
        <div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:20px;">
            <a href="products.php?action=add" class="btn btn-primary">
                <i class="fas fa-plus"></i> Tambah Produk
            </a>
            <?php if ($editProduct || $action === 'add'): ?>
                <a href="products.php" class="btn btn-outline">
                    <i class="fas fa-times"></i> Batal
                </a>
            <?php endif; ?>
        </div>

        <!-- 🔥 FILTER -->
        <form method="GET" class="filter-bar">
            <input type="text" name="search" placeholder="🔍 Cari produk..." value="<?= htmlspecialchars($search) ?>">
            <select name="category">
                <option value="">Semua Kategori</option>
                <?php foreach ($categories as $c): ?>
                    <option value="<?= htmlspecialchars($c['name']) ?>" <?= $categoryFilter === $c['name'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($c['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Filter</button>
            <a href="products.php" class="btn btn-outline"><i class="fas fa-undo"></i> Reset</a>
        </form>

        <!-- 🔥 🔥 FORM TAMBAH/EDIT 🔥 🔥 -->
        <?php if ($action === 'add' || $editProduct): ?>
        <form method="POST" enctype="multipart/form-data" class="admin-form" id="product-form">
            <h2><?= $editProduct ? '✏️ Edit Produk' : '➕ Tambah Produk Baru' ?></h2>
            
            <?php if ($editProduct): ?>
                <input type="hidden" name="id" value="<?= $editProduct['id'] ?>">
            <?php endif; ?>
            
            <div class="form-group">
                <label>Nama Produk *</label>
                <input type="text" name="name" value="<?= $editProduct ? htmlspecialchars($editProduct['name']) : '' ?>" required>
            </div>
            
            <div class="form-group">
                <label>Deskripsi</label>
                <textarea name="description" rows="4"><?= $editProduct ? htmlspecialchars($editProduct['description']) : '' ?></textarea>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Harga (Rp) *</label>
                    <input type="text" name="price" value="<?= $editProduct ? $editProduct['price'] : '' ?>" required>
                </div>
                <div class="form-group">
                    <label>Stok *</label>
                    <input type="number" name="stock" value="<?= $editProduct ? $editProduct['stock'] : '0' ?>" required min="0">
                </div>
            </div>
            
            <!-- 🔥 VARIAN -->
            <div class="form-group">
                <label>
                    Varian Produk
                    <button type="button" class="btn btn-sm btn-outline" onclick="addVariant()" style="margin-left:10px;">
                        <i class="fas fa-plus"></i> Tambah Varian
                    </button>
                </label>
                <p style="font-size:12px;color:#999;margin-bottom:8px;">Pelanggan bisa memilih varian (checkbox) saat memesan. Centang Aktif untuk mengaktifkan varian.</p>
                <div id="variants-list">
                    <?php foreach ($variants as $v): ?>
                    <div class="variant-row">
                        <input type="hidden" name="var_id[]" value="<?= $v['id'] ?>">
                        <label class="variant-checkbox">
                            <input type="checkbox" name="var_active[]" value="1" <?= $v['is_active'] ? 'checked' : '' ?>>
                        </label>
                        <input type="text" name="var_name[]" value="<?= htmlspecialchars($v['name']) ?>" placeholder="Nama varian" required>
                        <input type="text" name="var_price[]" value="<?= $v['price'] ?>" placeholder="Harga tambahan" required>
                        <button type="button" class="btn btn-sm btn-danger" onclick="this.parentElement.remove()">×</button>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <div class="form-group">
                <label>Satuan Ukuran</label>
                <select name="size_unit" onchange="toggleSizeUnit(this)">
                    <option value="none" <?= ($editProduct && ($editProduct['size_unit'] ?? 'none') === 'none') ? 'selected' : '' ?>>Barang Satuan (Pcs)</option>
                    <option value="m2" <?= ($editProduct && ($editProduct['size_unit'] ?? '') === 'm2') ? 'selected' : '' ?>>Meter Persegi (m²) — Lebar × Panjang</option>
                    <option value="meter" <?= ($editProduct && ($editProduct['size_unit'] ?? '') === 'meter') ? 'selected' : '' ?>>Meter (m) — Panjang saja</option>
                    <option value="lembar" <?= ($editProduct && ($editProduct['size_unit'] ?? '') === 'lembar') ? 'selected' : '' ?>>Lembar</option>
                    <option value="buku" <?= ($editProduct && ($editProduct['size_unit'] ?? '') === 'buku') ? 'selected' : '' ?>>Buku</option>
                    <option value="rim" <?= ($editProduct && ($editProduct['size_unit'] ?? '') === 'rim') ? 'selected' : '' ?>>Rim</option>
                    <option value="pcs" <?= ($editProduct && ($editProduct['size_unit'] ?? '') === 'pcs') ? 'selected' : '' ?>>Pcs</option>
                </select>
            </div>
            
            <div id="custom-size-fields" style="display:<?= ($editProduct && ($editProduct['size_unit'] ?? 'none') !== 'none') ? 'block' : 'none' ?>">
                <div class="form-group">
                    <label>Harga per satuan (Rp)</label>
                    <input type="text" name="price_per_m2" value="<?= $editProduct ? $editProduct['price_per_m2'] : '0' ?>">
                    <p style="font-size:12px;color:#999;">Harga per m² / per meter / per lembar / per buku / per rim / per pcs</p>
                </div>
                <div class="form-group">
                    <label>
                        Daftar Bahan 
                        <button type="button" class="btn btn-sm btn-outline" onclick="addMaterial()" style="margin-left:10px;">
                            <i class="fas fa-plus"></i> Tambah Bahan
                        </button>
                    </label>
                    <div id="materials-list">
                        <?php foreach ($materials as $m): ?>
                        <div class="material-row">
                            <input type="hidden" name="mat_id[]" value="<?= $m['id'] ?>">
                            <input type="text" name="mat_name[]" value="<?= htmlspecialchars($m['name']) ?>" placeholder="Nama bahan" required>
                            <input type="text" name="mat_price[]" value="<?= $m['price_per_m2'] ?>" placeholder="Harga/satuan" required>
                            <button type="button" class="btn btn-sm btn-danger" onclick="this.parentElement.remove()">×</button>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            
            <div class="form-group">
                <label>Kategori *</label>
                <select name="category" id="category-select" required onchange="showNewCategory(this)">
                    <option value="">-- Pilih --</option>
                    <?php foreach ($categories as $c): ?>
                        <option value="<?= htmlspecialchars($c['name']) ?>" <?= ($editProduct && $editProduct['category'] === $c['name']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($c['name']) ?>
                        </option>
                    <?php endforeach; ?>
                    <option value="__new__">+ Tambah Kategori Baru...</option>
                </select>
                <div id="new-category-field" style="display:none;margin-top:8px;">
                    <input type="text" name="new_category" placeholder="Nama kategori baru">
                </div>
            </div>
            
            <div class="form-group">
                <label>
                    <i class="fas fa-image"></i> Gambar Produk (bisa lebih dari 1)
                </label>
                <input type="file" name="images[]" accept="image/*" multiple>
                <p style="font-size:12px;color:#999;margin-top:5px;">Upload beberapa gambar sekaligus. Klik × pada gambar untuk menghapus.</p>
                
                <?php if ($editProduct): 
                    $existingImages = getProductImages($editProduct['id']);
                    if (!empty($existingImages)): ?>
                    <div class="image-grid">
                        <?php foreach ($existingImages as $ei): ?>
                        <div class="image-item">
                            <img src="/uploads/<?= htmlspecialchars($ei['image']) ?>" alt="Product image">
                            <?php if ($ei['is_main'] ?? false): ?>
                                <span class="main-badge">⭐ Utama</span>
                            <?php endif; ?>
                            <button type="button" class="delete-btn" onclick="deleteImage(this, <?= $ei['id'] ?>, <?= $editProduct['id'] ?>)">×</button>
                            <input type="hidden" name="delete_images[]" value="<?= $ei['id'] ?>" class="delete-image-input" disabled>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
            
            <div style="display:flex;gap:10px;flex-wrap:wrap;">
                <button type="submit" name="save" class="btn btn-primary">
                    <i class="fas fa-save"></i> Simpan
                </button>
                <a href="products.php" class="btn btn-outline">Batal</a>
            </div>
        </form>
        <?php endif; ?>

        <!-- 🔥 🔥 TABLE LIST PRODUCTS 🔥 🔥 -->
        <?php if (!$editProduct && $action !== 'add'): ?>
        <div class="table-wrapper">
            <table class="table">
                <thead>
                    <tr>
                        <th style="width:40px;">#</th>
                        <th>Nama</th>
                        <th>Kategori</th>
                        <th>Tipe</th>
                        <th>Harga</th>
                        <th>Stok</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($products)): ?>
                        <tr>
                            <td colspan="7" style="text-align:center;padding:30px;color:#999;">
                                <i class="fas fa-box-open" style="font-size:40px;display:block;margin-bottom:10px;"></i>
                                Belum ada produk
                                <br>
                                <a href="products.php?action=add" class="btn btn-primary" style="margin-top:10px;">Tambah Produk</a>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($products as $index => $p): ?>
                        <tr>
                            <td><?= $index + 1 ?></td>
                            <td>
                                <strong><?= htmlspecialchars($p['name']) ?></strong>
                                <?php if ($p['image']): ?>
                                    <br><img src="/uploads/<?= htmlspecialchars($p['image']) ?>" style="width:40px;height:40px;object-fit:cover;border-radius:4px;margin-top:4px;">
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($p['category']) ?></td>
                            <td>
                                <?php
                                $unitLabels = ['none'=>'','m2'=>'/m²','meter'=>'/m','lembar'=>'/lembar','buku'=>'/buku','rim'=>'/rim','pcs'=>'/pcs'];
                                $ul = $unitLabels[$p['size_unit'] ?? 'none'] ?? '';
                                echo ($p['size_unit'] ?? 'none') !== 'none'
                                    ? formatRupiah($p['price_per_m2']) . $ul
                                    : formatRupiah($p['price']);
                                ?>
                            </td>
                            <td>
                                <?= $p['custom_size'] 
                                    ? formatRupiah($p['price_per_m2']) . '/m²' 
                                    : formatRupiah($p['price']) ?>
                            </td>
                            <td>
                                <?php if ($p['stock'] > 10): ?>
                                    <span style="color:#27ae60;">✅ <?= $p['stock'] ?></span>
                                <?php elseif ($p['stock'] > 0): ?>
                                    <span style="color:#f39c12;">⚠️ <?= $p['stock'] ?></span>
                                <?php else: ?>
                                    <span style="color:#e74c3c;">❌ Habis</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div style="display:flex;gap:4px;flex-wrap:wrap;">
                                    <a href="products.php?edit=<?= $p['id'] ?>" class="btn btn-sm btn-outline">
                                        <i class="fas fa-edit"></i> Edit
                                    </a>
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Yakin ingin menghapus produk ini? Semua gambar dan data terkait akan hilang.');">
                                        <button type="submit" name="delete" value="<?= $p['id'] ?>" class="btn btn-sm btn-danger">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </main>
</div>

<script>
// 🔥 TOGGLE CUSTOM SIZE
function toggleSizeUnit(sel) {
    document.getElementById('custom-size-fields').style.display = sel.value !== 'none' ? 'block' : 'none';
}

// 🔥 SHOW NEW CATEGORY
function showNewCategory(sel) {
    document.getElementById('new-category-field').style.display = sel.value === '__new__' ? 'block' : 'none';
}

// 🔥 ADD MATERIAL
function addMaterial() {
    const div = document.createElement('div');
    div.className = 'material-row';
    div.innerHTML = `
        <input type="hidden" name="mat_id[]" value="">
        <input type="text" name="mat_name[]" placeholder="Nama bahan" required>
        <input type="text" name="mat_price[]" placeholder="Harga/satuan" required>
        <button type="button" class="btn btn-sm btn-danger" onclick="this.parentElement.remove()">×</button>
    `;
    document.getElementById('materials-list').appendChild(div);
}

// 🔥 ADD VARIANT
function addVariant() {
    const div = document.createElement('div');
    div.className = 'variant-row';
    div.innerHTML = `
        <input type="hidden" name="var_id[]" value="">
        <label class="variant-checkbox">
            <input type="checkbox" name="var_active[]" value="1" checked>
        </label>
        <input type="text" name="var_name[]" placeholder="Nama varian" required>
        <input type="text" name="var_price[]" placeholder="Harga tambahan" required>
        <button type="button" class="btn btn-sm btn-danger" onclick="this.parentElement.remove()">×</button>
    `;
    document.getElementById('variants-list').appendChild(div);
}

// 🔥 DELETE IMAGE
function deleteImage(btn, imageId, productId) {
    if (!confirm('Hapus gambar ini?')) return;
    
    // Tandai untuk dihapus di server
    const input = btn.parentElement.querySelector('.delete-image-input');
    if (input) {
        input.disabled = false;
        input.value = imageId;
    }
    
    // Sembunyikan secara visual
    btn.parentElement.style.opacity = '0.3';
    btn.parentElement.style.pointerEvents = 'none';
    btn.innerHTML = '⏳';
    
    // Submit form otomatis
    document.getElementById('product-form').submit();
}

// 🔥 PREVIEW IMAGE UPLOAD
document.addEventListener('DOMContentLoaded', function() {
    const fileInput = document.querySelector('input[type="file"][name="images[]"]');
    if (fileInput) {
        fileInput.addEventListener('change', function() {
            const files = this.files;
            let previewHtml = '';
            for (let i = 0; i < files.length; i++) {
                const file = files[i];
                if (file.type.startsWith('image/')) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        const container = document.querySelector('.image-grid') || document.createElement('div');
                        container.className = 'image-grid';
                        const img = document.createElement('div');
                        img.className = 'image-item';
                        img.innerHTML = `<img src="${e.target.result}" style="width:100%;height:100%;object-fit:cover;">`;
                        container.appendChild(img);
                        if (!document.querySelector('.image-grid')) {
                            fileInput.parentElement.appendChild(container);
                        }
                    };
                    reader.readAsDataURL(file);
                }
            }
        });
    }
});
</script>

<?php include '../includes/footer.php'; ?>