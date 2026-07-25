<?php
require_once __DIR__ . '/config.php';

$pageTitle = 'Produk - Rainbow Printing';

// 🔥 🔥 FILTER & SORTING 🔥 🔥
$category = $_GET['category'] ?? '';
$search = trim($_GET['search'] ?? '');
$sort = $_GET['sort'] ?? 'name';
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 12;
$offset = ($page - 1) * $perPage;

// 🔥 🔥 BUILD QUERY 🔥 🔥
$whereConditions = [];
$params = [];

if ($category) {
    $whereConditions[] = "category = ?";
    $params[] = $category;
}

if ($search) {
    $whereConditions[] = "(name LIKE ? OR description LIKE ? OR category LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$whereSql = empty($whereConditions) ? '' : 'WHERE ' . implode(' AND ', $whereConditions);

// 🔥 Sorting
$sortMap = [
    'name' => 'name ASC',
    'price_asc' => 'price ASC',
    'price_desc' => 'price DESC',
    'newest' => 'id DESC'
];
$orderBy = $sortMap[$sort] ?? 'name ASC';

// 🔥 🔥 HITUNG TOTAL 🔥 🔥
$countSql = "SELECT COUNT(*) as total FROM products $whereSql";
$countStmt = $db->prepare($countSql);
$countStmt->execute($params);
$totalProducts = $countStmt->fetch()['total'];
$totalPages = ceil($totalProducts / $perPage);

// 🔥 🔥 AMBIL DATA 🔥 🔥
$sql = "SELECT * FROM products $whereSql ORDER BY $orderBy LIMIT ? OFFSET ?";
$stmt = $db->prepare($sql);
$allParams = array_merge($params, [$perPage, $offset]);
$stmt->execute($allParams);
$products = $stmt->fetchAll();

// 🔥 AMBIL KATEGORI
$categories = $db->query("SELECT DISTINCT category FROM products ORDER BY category")->fetchAll();

// 🔥 ICON KATEGORI
$categoryIcons = [
    'Brosur' => '📄',
    'Kartu Nama' => '🪪',
    'Banner' => '🖼️',
    'Sticker' => '🏷️',
    'Undangan' => '💌',
    'Foto' => '📸',
    'Desain' => '🎨',
    'Kalender' => '📅'
];

include 'includes/header.php';
?>

<style>
/* ============================================
   PRODUCTS PAGE STYLES
   ============================================ */
.products-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 10px;
    margin-bottom: 20px;
}
.products-header h1 {
    font-size: 24px;
    color: #2c3e50;
    margin: 0;
}
.products-header .product-count {
    font-size: 14px;
    color: #6c757d;
}

/* 🔥 FILTER BAR */
.filter-bar {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    margin-bottom: 20px;
    background: #fff;
    padding: 15px;
    border-radius: 10px;
    box-shadow: 0 1px 4px rgba(0,0,0,0.06);
    align-items: center;
    border: 1px solid #e9ecef;
}
.filter-bar .filter-group {
    display: flex;
    gap: 6px;
    flex-wrap: wrap;
    align-items: center;
}
.filter-bar .filter-group label {
    font-size: 13px;
    color: #6c757d;
    font-weight: 600;
}
.filter-bar .btn {
    padding: 6px 14px;
    border-radius: 6px;
    font-size: 13px;
    text-decoration: none;
    border: 1px solid #dee2e6;
    transition: all 0.3s;
    color: #2c3e50;
    background: #fff;
}
.filter-bar .btn:hover {
    border-color: #f39c12;
    background: #fef9e7;
}
.filter-bar .btn.active {
    background: #2c3e50;
    color: #fff;
    border-color: #2c3e50;
}
.filter-bar .btn.active:hover {
    background: #1a252f;
}
.filter-bar .search-box {
    display: flex;
    gap: 6px;
    margin-left: auto;
}
.filter-bar .search-box input {
    padding: 6px 12px;
    border: 1px solid #ddd;
    border-radius: 6px;
    font-size: 13px;
    min-width: 180px;
}
.filter-bar .search-box input:focus {
    border-color: #f39c12;
    outline: none;
}
.filter-bar .search-box .btn {
    padding: 6px 14px;
}

/* 🔥 SORT */
.sort-select {
    padding: 6px 12px;
    border: 1px solid #ddd;
    border-radius: 6px;
    font-size: 13px;
    background: #fff;
    cursor: pointer;
}
.sort-select:focus {
    border-color: #f39c12;
    outline: none;
}

/* 🔥 PRODUCT GRID */
.product-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 20px;
}
@media (max-width: 480px) {
    .product-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 12px;
    }
}
@media (min-width: 900px) {
    .product-grid {
        grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
    }
}

.product-card {
    background: #fff;
    border-radius: 10px;
    overflow: hidden;
    box-shadow: 0 1px 4px rgba(0,0,0,0.06);
    transition: all 0.3s;
    border: 1px solid #e9ecef;
    display: flex;
    flex-direction: column;
}
.product-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 4px 20px rgba(0,0,0,0.08);
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
    padding: 12px 14px 14px;
    flex: 1;
    display: flex;
    flex-direction: column;
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
    flex: 1;
}
.product-price {
    font-size: 16px;
    font-weight: 700;
    color: #2c3e50;
    margin-bottom: 8px;
}
.product-stock {
    font-size: 11px;
    margin-bottom: 6px;
}
.product-stock .in-stock { color: #27ae60; }
.product-stock .out-of-stock { color: #e74c3c; }
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

/* 🔥 EMPTY STATE */
.empty-state {
    text-align: center;
    padding: 60px 20px;
    grid-column: 1 / -1;
}
.empty-state .icon {
    font-size: 64px;
    display: block;
    margin-bottom: 15px;
}
.empty-state h2 {
    color: #2c3e50;
    font-size: 22px;
    margin-bottom: 8px;
}
.empty-state p {
    color: #6c757d;
    margin-bottom: 15px;
}
.empty-state .btn {
    display: inline-block;
    padding: 10px 28px;
    border-radius: 6px;
    background: #2c3e50;
    color: #fff;
    text-decoration: none;
}
.empty-state .btn:hover {
    background: #1a252f;
}

/* 🔥 PAGINATION */
.pagination {
    display: flex;
    gap: 6px;
    justify-content: center;
    margin-top: 25px;
    flex-wrap: wrap;
}
.pagination a, .pagination span {
    padding: 8px 14px;
    border: 1px solid #ddd;
    border-radius: 6px;
    text-decoration: none;
    color: #2c3e50;
    font-size: 14px;
    transition: all 0.3s;
}
.pagination a:hover {
    background: #f8f9fa;
    border-color: #f39c12;
}
.pagination .active {
    background: #2c3e50;
    color: #fff;
    border-color: #2c3e50;
}
.pagination .disabled {
    opacity: 0.4;
    cursor: not-allowed;
}

/* 🔥 RESPONSIVE */
@media (max-width: 600px) {
    .products-header {
        flex-direction: column;
        align-items: flex-start;
    }
    .filter-bar {
        flex-direction: column;
        align-items: stretch;
        padding: 12px;
    }
    .filter-bar .search-box {
        margin-left: 0;
        width: 100%;
    }
    .filter-bar .search-box input {
        flex: 1;
        min-width: auto;
    }
    .filter-bar .filter-group {
        justify-content: center;
    }
    .filter-bar .btn {
        font-size: 12px;
        padding: 4px 10px;
    }
    .sort-select {
        width: 100%;
    }
    .product-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 10px;
    }
    .product-img-link {
        height: 140px;
    }
    .product-info h3 {
        font-size: 12px;
    }
    .product-price {
        font-size: 14px;
    }
}
</style>

<div class="products-header">
    <div>
        <h1>🛍️ Produk Kami</h1>
        <span class="product-count"><?= $totalProducts ?> produk ditemukan</span>
    </div>
</div>

<!-- 🔥 FILTER BAR -->
<form method="GET" class="filter-bar" id="filterForm">
    <div class="filter-group">
        <label>Kategori:</label>
        <a href="products.php<?= $search ? '?search=' . urlencode($search) : '' ?>" 
           class="btn <?= !$category ? 'active' : '' ?>">Semua</a>
        <?php foreach ($categories as $cat): ?>
        <a href="products.php?category=<?= urlencode($cat['category']) ?><?= $search ? '&search=' . urlencode($search) : '' ?><?= $sort ? '&sort=' . urlencode($sort) : '' ?>" 
           class="btn <?= $category === $cat['category'] ? 'active' : '' ?>">
            <?= htmlspecialchars($cat['category']) ?>
        </a>
        <?php endforeach; ?>
    </div>
    
    <div class="search-box">
        <input type="text" name="search" placeholder="🔍 Cari produk..." 
               value="<?= htmlspecialchars($search) ?>" 
               onchange="this.form.submit()">
        <?php if ($search): ?>
            <a href="products.php" class="btn btn-outline">✕</a>
        <?php endif; ?>
    </div>
    
    <select name="sort" class="sort-select" onchange="this.form.submit()">
        <option value="name" <?= $sort === 'name' ? 'selected' : '' ?>>Urutkan: Nama</option>
        <option value="price_asc" <?= $sort === 'price_asc' ? 'selected' : '' ?>>Harga: Terendah</option>
        <option value="price_desc" <?= $sort === 'price_desc' ? 'selected' : '' ?>>Harga: Tertinggi</option>
        <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Terbaru</option>
    </select>
</form>

<!-- 🔥 PRODUCT GRID -->
<div class="product-grid">
    <?php if (empty($products)): ?>
        <div class="empty-state">
            <span class="icon">🔍</span>
            <h2>Tidak ada produk ditemukan</h2>
            <p>
                <?php if ($search): ?>
                    Produk dengan kata kunci "<strong><?= htmlspecialchars($search) ?></strong>" tidak ditemukan.
                <?php elseif ($category): ?>
                    Tidak ada produk di kategori "<strong><?= htmlspecialchars($category) ?></strong>".
                <?php else: ?>
                    Belum ada produk yang tersedia.
                <?php endif; ?>
            </p>
            <a href="products.php" class="btn">Lihat Semua Produk</a>
        </div>
    <?php else: ?>
        <?php foreach ($products as $p): 
            $firstImg = getFirstProductImage($p['id']);
            $isCustom = $p['custom_size'];
            $priceDisplay = $isCustom ? formatRupiah($p['price_per_m2']) . '/m²' : formatRupiah($p['price']);
            $inStock = $p['stock'] > 0;
            $icon = $categoryIcons[$p['category']] ?? '📄';
        ?>
        <div class="product-card">
            <a href="product.php?slug=<?= urlencode($p['slug']) ?>" class="product-img-link">
                <div class="product-img" style="<?= $firstImg ? 'background-image:url(/uploads/' . htmlspecialchars($firstImg) . ');' : '' ?>">
                    <?php if (!$firstImg): ?>
                        <?= $icon ?>
                    <?php endif; ?>
                </div>
            </a>
            <div class="product-info">
                <span class="product-category"><?= htmlspecialchars($p['category']) ?></span>
                <h3><?= htmlspecialchars($p['name']) ?></h3>
                <div class="product-stock">
                    <?php if ($inStock): ?>
                        <span class="in-stock">✅ Tersedia (<?= $p['stock'] ?>)</span>
                    <?php else: ?>
                        <span class="out-of-stock">❌ Habis</span>
                    <?php endif; ?>
                </div>
                <p class="product-price"><?= $priceDisplay ?></p>
                <a href="product.php?slug=<?= urlencode($p['slug']) ?>" class="btn">Detail</a>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- 🔥 PAGINATION -->
<?php if ($totalPages > 1): ?>
<div class="pagination">
    <?php if ($page > 1): ?>
        <a href="?page=<?= $page-1 ?>&category=<?= urlencode($category) ?>&search=<?= urlencode($search) ?>&sort=<?= urlencode($sort) ?>">« Sebelumnya</a>
    <?php else: ?>
        <span class="disabled">« Sebelumnya</span>
    <?php endif; ?>
    
    <?php
    $startPage = max(1, $page - 2);
    $endPage = min($totalPages, $page + 2);
    for ($i = $startPage; $i <= $endPage; $i++):
    ?>
        <?php if ($i == $page): ?>
            <span class="active"><?= $i ?></span>
        <?php else: ?>
            <a href="?page=<?= $i ?>&category=<?= urlencode($category) ?>&search=<?= urlencode($search) ?>&sort=<?= urlencode($sort) ?>"><?= $i ?></a>
        <?php endif; ?>
    <?php endfor; ?>
    
    <?php if ($page < $totalPages): ?>
        <a href="?page=<?= $page+1 ?>&category=<?= urlencode($category) ?>&search=<?= urlencode($search) ?>&sort=<?= urlencode($sort) ?>">Selanjutnya »</a>
    <?php else: ?>
        <span class="disabled">Selanjutnya »</span>
    <?php endif; ?>
</div>
<?php endif; ?>

<script>
// 🔥 Auto submit form on search change with debounce
document.addEventListener('DOMContentLoaded', function() {
    var searchInput = document.querySelector('input[name="search"]');
    var form = document.getElementById('filterForm');
    if (searchInput && form) {
        var timeout;
        searchInput.addEventListener('input', function() {
            clearTimeout(timeout);
            timeout = setTimeout(function() {
                form.submit();
            }, 500);
        });
    }
});
</script>

<?php include 'includes/footer.php'; ?>