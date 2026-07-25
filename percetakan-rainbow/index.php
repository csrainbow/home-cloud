<?php
require_once __DIR__ . '/config.php';

$pageTitle = 'Beranda - Percetakan Online Samarinda';

// 🔥 AMBIL PRODUK TERBARU
$products = $db->query("SELECT * FROM products ORDER BY id DESC LIMIT 8")->fetchAll();

// 🔥 AMBIL STATISTIK
$totalProducts = $db->query("SELECT COUNT(*) as c FROM products")->fetch()['c'];
$totalOrders = $db->query("SELECT COUNT(*) as c FROM orders")->fetch()['c'];
$totalCustomers = $db->query("SELECT COUNT(*) as c FROM customers")->fetch()['c'];

include 'includes/header.php';
?>

<style>
/* ============================================
   HOME PAGE STYLES
   ============================================ */

/* 🔥 HERO */
.hero {
    text-align: center;
    padding: 60px 20px 50px;
    background: linear-gradient(135deg, #2c3e50 0%, #3498db 100%);
    color: #fff;
    border-radius: 16px;
    margin-bottom: 40px;
    position: relative;
    overflow: hidden;
}
.hero::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -20%;
    width: 300px;
    height: 300px;
    background: rgba(255,255,255,0.05);
    border-radius: 50%;
}
.hero::after {
    content: '';
    position: absolute;
    bottom: -30%;
    left: -10%;
    width: 200px;
    height: 200px;
    background: rgba(255,255,255,0.05);
    border-radius: 50%;
}
.hero h1 {
    font-size: 36px;
    font-weight: 700;
    margin-bottom: 15px;
    position: relative;
    z-index: 1;
}
.hero h1 span {
    color: #f39c12;
}
.hero p {
    font-size: 18px;
    opacity: 0.9;
    margin-bottom: 25px;
    position: relative;
    z-index: 1;
    max-width: 600px;
    margin-left: auto;
    margin-right: auto;
}
.hero .btn {
    background: #f39c12;
    color: #fff;
    padding: 14px 40px;
    border-radius: 50px;
    font-size: 16px;
    font-weight: 600;
    text-decoration: none;
    transition: all 0.3s;
    display: inline-block;
    position: relative;
    z-index: 1;
}
.hero .btn:hover {
    background: #d68910;
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(243,156,18,0.4);
}

/* 🔥 STATS */
.home-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 15px;
    margin-bottom: 40px;
}
.home-stats .stat-item {
    background: #fff;
    padding: 20px;
    border-radius: 10px;
    text-align: center;
    box-shadow: 0 1px 4px rgba(0,0,0,0.06);
    border-bottom: 3px solid #2c3e50;
    transition: all 0.3s;
}
.home-stats .stat-item:hover {
    transform: translateY(-3px);
    box-shadow: 0 4px 16px rgba(0,0,0,0.08);
}
.home-stats .stat-item .number {
    font-size: 28px;
    font-weight: 700;
    color: #2c3e50;
}
.home-stats .stat-item .label {
    font-size: 13px;
    color: #6c757d;
    margin-top: 4px;
}
.home-stats .stat-item .icon {
    font-size: 28px;
    display: block;
    margin-bottom: 6px;
}

/* 🔥 CATEGORIES */
.categories {
    margin-bottom: 40px;
}
.categories h2 {
    font-size: 24px;
    color: #2c3e50;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 10px;
}
.categories h2::after {
    content: '';
    flex: 1;
    height: 2px;
    background: linear-gradient(90deg, #f39c12, transparent);
}
.category-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(130px, 1fr));
    gap: 15px;
}
.category-card {
    background: #fff;
    padding: 20px 15px;
    text-align: center;
    border-radius: 10px;
    text-decoration: none;
    color: #333;
    box-shadow: 0 1px 4px rgba(0,0,0,0.06);
    transition: all 0.3s;
    border: 1px solid #e9ecef;
}
.category-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 4px 16px rgba(0,0,0,0.08);
    border-color: #f39c12;
}
.category-icon {
    font-size: 36px;
    display: block;
    margin-bottom: 8px;
}
.category-card span {
    font-size: 13px;
    font-weight: 500;
}

/* 🔥 FEATURED PRODUCTS */
.featured {
    margin-bottom: 40px;
}
.featured h2 {
    font-size: 24px;
    color: #2c3e50;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 10px;
}
.featured h2::after {
    content: '';
    flex: 1;
    height: 2px;
    background: linear-gradient(90deg, #f39c12, transparent);
}
.product-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 20px;
}
.product-card {
    background: #fff;
    border-radius: 10px;
    overflow: hidden;
    box-shadow: 0 1px 4px rgba(0,0,0,0.06);
    transition: all 0.3s;
    border: 1px solid #e9ecef;
}
.product-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.1);
    border-color: #f39c12;
}
.product-img-link {
    display: block;
    text-decoration: none;
    position: relative;
    overflow: hidden;
    background: #f8f9fa;
    height: 180px;
}
.product-img-link .product-img {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 48px;
    transition: transform 0.3s;
    background-size: cover;
    background-position: center;
}
.product-card:hover .product-img {
    transform: scale(1.05);
}
.product-info {
    padding: 14px 16px;
}
.product-category {
    font-size: 11px;
    color: #999;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.product-info h3 {
    font-size: 14px;
    margin: 4px 0 6px;
    line-height: 1.4;
    color: #2c3e50;
}
.product-price {
    font-size: 17px;
    font-weight: 700;
    color: #2c3e50;
    margin-bottom: 8px;
}
.product-info .btn {
    display: block;
    width: 100%;
    padding: 8px;
    text-align: center;
    border-radius: 6px;
    font-size: 13px;
    text-decoration: none;
    transition: all 0.3s;
    border: 1px solid #2c3e50;
    color: #2c3e50;
    background: transparent;
}
.product-info .btn:hover {
    background: #2c3e50;
    color: #fff;
}

.text-center {
    text-align: center;
    margin-top: 20px;
}
.text-center .btn {
    display: inline-block;
    padding: 12px 32px;
    border-radius: 6px;
    background: #2c3e50;
    color: #fff;
    text-decoration: none;
    transition: all 0.3s;
}
.text-center .btn:hover {
    background: #1a252f;
    transform: translateY(-2px);
}

/* 🔥 RESPONSIVE */
@media (max-width: 768px) {
    .hero {
        padding: 40px 20px;
    }
    .hero h1 {
        font-size: 26px;
    }
    .hero p {
        font-size: 15px;
    }
    .product-grid {
        grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
        gap: 12px;
    }
    .category-grid {
        grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
        gap: 10px;
    }
    .category-card {
        padding: 15px 10px;
    }
    .category-icon {
        font-size: 28px;
    }
}

@media (max-width: 480px) {
    .hero h1 {
        font-size: 20px;
    }
    .hero .btn {
        padding: 10px 28px;
        font-size: 14px;
    }
    .product-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 10px;
    }
    .product-img-link {
        height: 140px;
    }
    .product-info {
        padding: 10px 12px;
    }
    .product-info h3 {
        font-size: 12px;
    }
    .product-price {
        font-size: 14px;
    }
    .home-stats {
        grid-template-columns: repeat(2, 1fr);
    }
}
</style>

<!-- 🔥 HERO SECTION -->
<section class="hero">
    <h1>Selamat Datang di <span><?= SITE_NAME ?></span></h1>
    <p>Solusi cetak cepat, murah, dan berkualitas untuk kebutuhanmu.</p>
    <a href="products.php" class="btn">
        <i class="fas fa-shopping-bag"></i> Lihat Produk
    </a>
</section>

<!-- 🔥 STATISTIK -->
<section class="home-stats">
    <div class="stat-item">
        <span class="icon">📦</span>
        <div class="number"><?= $totalProducts ?></div>
        <div class="label">Produk Tersedia</div>
    </div>
    <div class="stat-item">
        <span class="icon">📋</span>
        <div class="number"><?= $totalOrders ?></div>
        <div class="label">Pesanan Selesai</div>
    </div>
    <div class="stat-item">
        <span class="icon">👤</span>
        <div class="number"><?= $totalCustomers ?></div>
        <div class="label">Pelanggan</div>
    </div>
    <div class="stat-item">
        <span class="icon">⭐</span>
        <div class="number">4.9</div>
        <div class="label">Rating Pelanggan</div>
    </div>
</section>

<!-- 🔥 KATEGORI -->
<section class="categories">
    <h2>📂 Kategori Produk</h2>
    <div class="category-grid">
        <?php foreach (getCategories() as $cat): ?>
        <a href="products.php?category=<?= urlencode($cat['name']) ?>" class="category-card">
            <span class="category-icon"><?= htmlspecialchars($cat['icon']) ?></span>
            <span><?= htmlspecialchars($cat['name']) ?></span>
        </a>
        <?php endforeach; ?>
    </div>
</section>

<!-- 🔥 PRODUK TERBARU -->
<section class="featured">
    <h2>🔥 Produk Terbaru</h2>
    <div class="product-grid">
        <?php foreach ($products as $p): 
            $firstImg = getFirstProductImage($p['id']);
            $priceDisplay = $p['custom_size'] ? formatRupiah($p['price_per_m2']) . '/m²' : formatRupiah($p['price']);
        ?>
        <div class="product-card">
            <a href="product.php?slug=<?= urlencode($p['slug']) ?>" class="product-img-link">
                <div class="product-img" style="<?= $firstImg ? 'background-image:url(/uploads/' . htmlspecialchars($firstImg) . ');' : '' ?>">
                    <?php if (!$firstImg): ?>
                    <?php
                    $icons = ['Brosur'=>'📄','Kartu Nama'=>'🪪','Banner'=>'🖼️','Sticker'=>'🏷️','Undangan'=>'💌','Foto'=>'📸','Desain'=>'🎨','Kalender'=>'📅'];
                    echo $icons[$p['category']] ?? '📄';
                    ?>
                    <?php endif; ?>
                </div>
            </a>
            <div class="product-info">
                <span class="product-category"><?= htmlspecialchars($p['category']) ?></span>
                <h3><?= htmlspecialchars($p['name']) ?></h3>
                <p class="product-price"><?= $priceDisplay ?></p>
                <a href="product.php?slug=<?= urlencode($p['slug']) ?>" class="btn">Detail</a>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <div class="text-center">
        <a href="products.php" class="btn">Lihat Semua Produk</a>
    </div>
</section>

<?php include 'includes/footer.php'; ?>